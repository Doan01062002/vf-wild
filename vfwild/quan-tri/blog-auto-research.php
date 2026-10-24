<?php
/**
 * Tra cứu dữ kiện trên web trước khi lập dàn ý / viết bài: số liệu, quy định, giá… lấy từ nguồn chính thức (hãng, cơ quan nhà nước, báo lớn),
 * KHÔNG lấy từ chungauto.vn, để bài vfwild.vn không trùng thông tin với chungauto và có nguồn rõ ràng (tốt cho SEO / E-E-A-T, chatbot AI).
 * Dùng công cụ tìm kiếm có sẵn của nhà cung cấp AI: Gemini (Google Search grounding), Claude (web_search). Nhà cung cấp khác: bỏ qua.
 * Kết quả lưu tạm auto/research/<md5>.json (7 ngày) nên bước lập dàn ý và bước viết dùng chung một lần tra.
 */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }

const AUTO_RESEARCH_TTL = 604800;
const AUTO_RESEARCH_SKIP = '/(^|\.)chungauto\.vn$/i';   // không lấy dữ kiện từ chungauto.vn (đã là "bài tham khảo" riêng)

/** Nhà cung cấp có công cụ tìm kiếm web không. */
function auto_search_capable(string $p): bool { return in_array($p, ['gemini_api', 'anthropic_api'], true); }

/** Liên kết chuyển hướng của Google (vertexaisearch…/grounding-api-redirect/…) → địa chỉ thật của nguồn (đọc Location, không tải trang). */
function auto_resolve_url(string $u): string {
    if (!preg_match('#^https://vertexaisearch\.cloud\.google\.com/#', $u)) return $u;
    $ctx = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 8, 'follow_location' => 0, 'ignore_errors' => true]]);
    $h = @get_headers($u, true, $ctx);
    $loc = is_array($h) ? ($h['Location'] ?? $h['location'] ?? '') : '';
    if (is_array($loc)) $loc = (string)end($loc);
    return preg_match('#^https?://#', (string)$loc) ? (string)$loc : '';
}

/** Gọi AI kèm công cụ tìm kiếm web. Trả về ['text' => chữ trả lời, 'sources' => [['title','url']]]. Lỗi thì ném ngoại lệ. */
function auto_ai_search(string $DIR, string $p, string $model, string $prompt, int $timeout = 150): array {
    if (!auto_search_capable($p)) throw new RuntimeException('Nhà cung cấp này không có công cụ tìm kiếm web.');
    $key = auto_secret($DIR, AUTO_PROVIDERS[$p]['key']);
    if ($key === '' || $model === '' || !auto_model_valid($model)) throw new RuntimeException('Chưa có khóa API hoặc model hợp lệ.');
    $base = auto_prov_base($p);
    $text = ''; $src = [];
    if ($p === 'gemini_api') {
        [$code, $j] = auto_http('POST', "$base/v1beta/models/" . rawurlencode($model) . ':generateContent', ['Content-Type: application/json', "x-goog-api-key: $key"],
            ['contents' => [['parts' => [['text' => $prompt]]]], 'tools' => [['google_search' => new stdClass()]], 'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 8192]], $timeout);
        if ($code !== 200) throw auto_http_error($p, $code, $j, $model);
        foreach ((array)($j['candidates'][0]['content']['parts'] ?? []) as $part) { if (empty($part['thought']) && isset($part['text'])) $text .= (string)$part['text']; }
        foreach ((array)($j['candidates'][0]['groundingMetadata']['groundingChunks'] ?? []) as $c) {
            if (!empty($c['web']['uri'])) $src[] = ['title' => (string)($c['web']['title'] ?? ''), 'url' => (string)$c['web']['uri']];
        }
    } else {   // anthropic_api
        [$code, $j] = auto_http('POST', "$base/v1/messages", ['Content-Type: application/json', "x-api-key: $key", 'anthropic-version: 2023-06-01'],
            ['model' => $model, 'max_tokens' => 8000, 'tools' => [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 5]], 'messages' => [['role' => 'user', 'content' => $prompt]]], $timeout);
        if ($code !== 200) throw auto_http_error($p, $code, $j, $model);
        foreach ((array)($j['content'] ?? []) as $b) {
            if (($b['type'] ?? '') === 'text') $text .= (string)($b['text'] ?? '');
            if (($b['type'] ?? '') === 'web_search_tool_result') foreach ((array)($b['content'] ?? []) as $r) { if (!empty($r['url'])) $src[] = ['title' => (string)($r['title'] ?? ''), 'url' => (string)$r['url']]; }
        }
    }
    if (trim($text) === '') throw new RuntimeException('AI không trả về kết quả tra cứu.');
    return ['text' => $text, 'sources' => $src];
}

/** Câu lệnh tra cứu: dữ kiện ngắn, có số liệu, mỗi dữ kiện ghi tên nguồn; ưu tiên nguồn chính thức, bỏ chungauto.vn. */
function auto_research_prompt(string $kw, string $type): string {
    $ctx = AUTO_TYPES[$type] ?? '';
    return "TRA CỨU DỮ KIỆN: hãy tìm trên web các thông tin MỚI NHẤT và CHÍNH XÁC để viết một bài blog tiếng Việt cho từ khóa \"$kw\"" . ($ctx !== '' ? " (loại bài: $ctx)" : '') . ". Website đăng bài là vfwild.vn (đại lý VinFast VF Wild của ChungAuto); chỉ nói về VF Wild khi từ khóa liên quan.\n"
        . "- Ưu tiên nguồn chính thức: trang của hãng (vinfastauto.com…), cơ quan nhà nước và văn bản pháp luật (chinhphu.vn, thuvienphapluat.vn, csgt.vn, mt.gov.vn…), báo lớn (vnexpress.net, tuoitre.vn, thanhnien.vn, vietnamnet.vn…). KHÔNG dùng chungauto.vn.\n"
        . "- Mỗi dữ kiện là một câu đầy đủ, cụ thể (con số, đơn vị, mốc thời gian, tên văn bản, điều kiện áp dụng), ghi rõ nguồn; không suy đoán; không chép nguyên câu của nguồn.\n"
        . "- 8–15 dữ kiện, bao quát các ý người đọc cần (định nghĩa, số liệu, quy định, giá hoặc chi phí, thủ tục, lưu ý). Thông tin nào các nguồn nói khác nhau thì ghi rõ cả hai.\n"
        . "Chỉ trả JSON: {\"facts\":[{\"fact\":\"…\",\"source\":\"tên nguồn (tên miền)\"}]}";
}

/** Tra cứu (có lưu tạm) cho một từ khóa. null nếu tắt, nhà cung cấp không hỗ trợ hoặc lỗi (không bao giờ ném lỗi ra ngoài). */
function auto_research(string $DIR, array $s, string $kw, string $type): ?array {
    $p = (string)$s['provider'];
    if (empty($s['web_research']) || !auto_search_capable($p)) return null;
    $d = auto_dir($DIR) . '/research'; if (!is_dir($d)) @mkdir($d, 0750, true);
    $f = $d . '/' . md5(mb_strtolower(trim($kw), 'UTF-8')) . '.json';
    $c = auto_json($f, []);
    if (!empty($c['facts']) && time() - (int)($c['t'] ?? 0) < AUTO_RESEARCH_TTL) return $c;
    $pause = auto_dir($DIR) . '/research-pause.json';   // hết hạn mức tìm kiếm (429): nghỉ 6 giờ, khỏi gọi lại vô ích ở mỗi lượt
    if (time() - (int)(auto_json($pause, [])['t'] ?? 0) < 21600) return null;
    try {
        $r = auto_ai_search($DIR, $p, auto_model($s, $p), auto_research_prompt($kw, $type));
        $j = auto_json_decode($r['text']) ?? [];
        $facts = [];
        foreach ((array)($j['facts'] ?? []) as $x) {
            $fx = trim((string)($x['fact'] ?? '')); $so = trim((string)($x['source'] ?? ''));
            if ($fx === '' || mb_strlen($fx, 'UTF-8') > 400 || stripos($so . ' ' . $fx, 'chungauto') !== false) continue;
            $facts[] = ['fact' => $fx, 'source' => mb_substr($so, 0, 80, 'UTF-8')];
            if (count($facts) >= 18) break;
        }
        $sources = []; $seen = [];
        foreach ($r['sources'] as $x) {
            $u = auto_resolve_url($x['url']);
            $host = strtolower((string)parse_url($u, PHP_URL_HOST));
            if ($host === '' || preg_match(AUTO_RESEARCH_SKIP, $host) || isset($seen[$host])) continue;
            $seen[$host] = 1;
            $sources[] = ['title' => mb_substr(trim($x['title']) !== '' ? trim($x['title']) : $host, 0, 120, 'UTF-8'), 'url' => $u, 'host' => preg_replace('/^www\./', '', $host)];
            if (count($sources) >= 8) break;
        }
        if (!$facts) return null;
        $out = ['t' => time(), 'kw' => $kw, 'facts' => $facts, 'sources' => $sources];
        auto_save_json($f, $out);
        return $out;
    } catch (Throwable $e) {
        if ($e->getCode() === 429) auto_save_json($pause, ['t' => time(), 'msg' => mb_substr($e->getMessage(), 0, 300, 'UTF-8')]);
        auto_log($DIR, ['keyword' => $kw, 'result' => 'cảnh báo', 'msg' => 'Chưa tra cứu được trên web' . ($e->getCode() === 429 ? ' (tạm nghỉ tra cứu 6 giờ, bài vẫn viết bình thường)' : '') . ': ' . $e->getMessage()]);
        return null;
    }
}

/** Dữ kiện tra cứu dưới dạng một mục của bộ dữ kiện (ghi nguồn từng dòng). */
function auto_research_facts(array $res): string {
    return '## Dữ kiện tra cứu trên web ngày ' . date('d/m/Y', (int)$res['t']) . " (nguồn chính thức, ghi nguồn từng dòng)\n"
        . implode("\n", array_map(fn($x) => '- ' . $x['fact'] . ($x['source'] !== '' ? ' (theo ' . $x['source'] . ')' : ''), $res['facts']));
}
/** Thứ tự ưu tiên số liệu khi có cả dữ kiện tra cứu và bài tham khảo chungauto.vn. */
function auto_research_rules(bool $hasRef): string {
    return "- THỨ TỰ ƯU TIÊN SỐ LIỆU: (1) bộ dữ kiện VF Wild / ChungAuto của site; (2) mục \"Dữ kiện tra cứu trên web\" (nguồn chính thức, nêu nguồn trong câu khi là quy định, mức phạt hoặc số liệu thị trường, ví dụ \"theo Nghị định 168/2024/NĐ-CP\")"
        . ($hasRef ? "; (3) bài tham khảo chungauto.vn CHỈ dùng cho giá, sản phẩm, dịch vụ của ChungAuto hoặc khi (2) không có. Không lấy toàn bộ số liệu từ chungauto.vn" : '')
        . ". Khi hai nguồn nói khác nhau, theo nguồn đứng trước và không nêu con số kia.\n";
}
/** Mục "Nguồn tham khảo" cuối bài: tối đa $n nguồn đã kiểm (địa chỉ thật, khác tên miền, không có chungauto.vn). */
function auto_sources_section(array $res, int $n = 4): string {
    $l = [];
    foreach (array_slice((array)($res['sources'] ?? []), 0, $n) as $x) $l[] = '- [' . str_replace(['[', ']'], '', $x['title'] !== '' ? $x['title'] : $x['host']) . '](' . str_replace([' ', ')'], ['%20', '%29'], $x['url']) . ') – ' . $x['host'];
    return $l ? "## Nguồn tham khảo\n\n" . implode("\n", $l) . "\n" : '';
}

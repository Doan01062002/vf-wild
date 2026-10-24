<?php
/**
 * Bài tham khảo cùng chủ đề trên chungauto.vn (website cùng công ty): khi chỉ có từ khóa (không có dàn ý, không có dữ kiện),
 * tìm bài khớp nhất trong sitemap_article.xml, lấy dàn ý + nội dung làm dữ kiện, rồi dặn AI viết lại bằng lời của mình và làm tốt hơn.
 * Không gắn cứng bài nào: khớp theo từ khóa trên đường dẫn bài. Tắt được ở Cài đặt (ref_enabled).
 * Dữ liệu tạm: auto/ref-index.json (danh sách bài, làm mới mỗi 20 giờ), auto/ref/<md5>.json (nội dung bài, giữ 3 ngày).
 */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }

const AUTO_REF_SITE = 'https://chungauto.vn';
const AUTO_REF_MAX_TEXT = 16000;   // ký tự nội dung bài tham khảo đưa vào câu lệnh

function auto_ref_base(): string { $b = getenv('VFW_REF_BASE'); return rtrim(is_string($b) && $b !== '' ? $b : AUTO_REF_SITE, '/'); }
/** Tải một trang (chỉ trang của website tham khảo); lỗi hoặc không phải 200 thì trả ''. */
function auto_ref_get(string $url, int $timeout = 15): string {
    if (strpos($url, auto_ref_base() . '/') !== 0) return '';
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 0, 'header' => "User-Agent: Mozilla/5.0 (vfwild.vn content bot)\r\n"], 'ssl' => ['verify_peer' => true]]);
    $raw = @file_get_contents($url, false, $ctx, 0, 4000000);
    $code = 0;
    foreach ($http_response_header ?? [] as $h) { if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1]; }
    return ($raw !== false && $code === 200) ? $raw : '';
}
/** Danh sách đường dẫn bài của website tham khảo (từ sitemap, lưu tạm 20 giờ; tải lỗi thì dùng bản cũ). */
function auto_ref_index(string $DIR): array {
    $f = auto_dir($DIR) . '/ref-index.json';
    $c = auto_json($f, []);
    if (!empty($c['urls']) && time() - (int)($c['t'] ?? 0) < 72000 && ($c['base'] ?? '') === auto_ref_base()) return $c['urls'];
    $urls = [];
    $xml = auto_ref_get(auto_ref_base() . '/sitemap_article.xml', 20);
    if ($xml !== '' && preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#', $xml, $m)) $urls = $m[1];
    // sitemap chậm cập nhật: lấy thêm bài mới nhất ở trang tin tức và trang đầu của từng chuyên mục tin (đường dẫn bài kết thúc bằng -<số>.html, chuyên mục bằng -e<số>.html)
    $b = preg_quote(auto_ref_base(), '#');
    $news = auto_ref_get(auto_ref_base() . '/tin-tuc', 20);
    $pages = [$news];
    if ($news !== '' && preg_match_all('#href="(' . $b . '/[a-z0-9-]+-e\d+\.html)"#', $news, $mc)) { foreach (array_slice(array_unique($mc[1]), 0, 10) as $cu) $pages[] = auto_ref_get($cu, 15); }
    foreach ($pages as $pg) { if ($pg !== '' && preg_match_all('#href="(' . $b . '/[a-z0-9-]+-\d+\.html)"#', $pg, $m2)) $urls = array_merge($m2[1], $urls); }
    $urls = array_values(array_unique(array_filter($urls, fn($u) => strpos($u, auto_ref_base() . '/') === 0 && !preg_match('#-(c|e|p|hp)\d+\.html$#', $u))));
    if ($urls) { auto_save_json($f, ['t' => time(), 'base' => auto_ref_base(), 'urls' => $urls]); return $urls; }
    return $c['urls'] ?? [];
}
/** Chọn bài khớp từ khóa nhất theo chữ trên đường dẫn; null nếu không đủ khớp (thà không tham khảo còn hơn tham khảo nhầm bài). */
function auto_ref_pick(array $urls, string $kw): ?string {
    $ks = blog_slugify($kw, 0);
    $tok = auto_tok($kw);
    if (!$tok) return null;
    $wild = strpos($ks, 'wild') !== false;
    $core = implode('-', array_values(array_filter(explode('-', $ks), fn($t) => !in_array($t, ['vf', 'wild', 'vinfast'], true))));   // cụm từ khóa liền mạch (bỏ tên xe)
    $best = null; $bs = 0.0;
    foreach ($urls as $u) {
        $slug = preg_replace('/-\d+\.html$|\.html$/', '', basename(parse_url($u, PHP_URL_PATH) ?: ''));
        if ($slug === '') continue;
        $st = array_flip(explode('-', $slug));
        $hit = 0; $long = false;
        foreach ($tok as $t) { if (isset($st[$t])) { $hit++; if (strlen($t) >= 5) $long = true; } }
        $ratio = $hit / count($tok);
        if ($ratio < 0.8) continue;
        $slugWild = strpos($slug, 'wild') !== false;
        if ($wild && !$slugWild) continue;                                          // từ khóa về VF Wild thì chỉ lấy bài VF Wild (bài xe khác có số liệu khác)
        $phrase = $core !== '' && strpos('-' . $slug . '-', '-' . $core . '-') !== false;
        if ($hit < 2 && !$long && !$phrase && !($wild && $slugWild)) continue;     // một chữ ngắn khớp thì chưa đủ tin
        preg_match('/-(\d+)\.html$/', $u, $id);
        $score = $ratio * 10 + ($phrase ? 3 : 0) + ($wild && $slugWild ? 2 : 0) - 0.05 * max(0, count($st) - count($tok)) + ((int)($id[1] ?? 0)) / 1e6;
        if ($score > $bs) { $bs = $score; $best = $u; }
    }
    return $best;
}
/** Chữ thuần của một đoạn HTML. */
function auto_ref_txt(string $h): string {
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('#<br\s*/?>#i', ' ', $h) ?? $h), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}
/**
 * Bóc dàn ý và chữ của phần thân bài (bỏ menu, mục lục, chân trang). Dùng biểu thức chính quy, không cần thư viện DOM
 * (PHP trên VPS không có ext-dom). Vùng thân bài: từ khối "content" trong "body-content" (giao diện chungauto.vn), không có thì từ sau thẻ H1;
 * kết thúc ở khối "Xem thêm"/bình luận/chân trang.
 */
function auto_ref_parse(string $html, string $url): ?array {
    if ($html === '') return null;
    $title = preg_match('#<h1\b[^>]*>(.*?)</h1>#is', $html, $m) ? auto_ref_txt($m[1]) : '';
    $a = strpos($html, 'body-content');
    $a = $a !== false && ($c = strpos($html, 'class="content"', $a)) !== false ? $c : (($h = stripos($html, '</h1>')) !== false ? $h : false);
    if ($a === false) return null;
    $b = strlen($html);
    foreach (['show-more', 'id="comments"', '<footer'] as $stop) { $p = strpos($html, $stop, $a); if ($p !== false && $p < $b) $b = $p; }
    $body = preg_replace('#<(script|style|noscript)\b.*?</\1>#is', '', substr($html, $a, $b - $a)) ?? '';
    if (!preg_match_all('#<(h2|h3|h4|p|li|tr)\b[^>]*>(.*?)</\1>#is', $body, $mm, PREG_SET_ORDER)) return null;
    $outline = []; $lines = [];
    foreach ($mm as [$all, $tag, $inner]) {
        $tag = strtolower($tag);
        if (in_array($tag, ['p', 'li'], true) && preg_match('#^\s*<a\s[^>]*href="\#[^"]*"[^>]*>.*?</a>\s*$#is', $inner)) continue;   // dòng mục lục (chỉ là liên kết neo)
        if ($tag === 'li' && preg_match('#<p\b#i', $inner)) continue;                                                       // li chứa p: lấy ở p
        $t = $tag === 'tr' ? implode(' | ', array_map('auto_ref_txt', preg_match_all('#<t[hd]\b[^>]*>(.*?)</t[hd]>#is', $inner, $cells) ? $cells[1] : [])) : auto_ref_txt($inner);
        if ($t === '' || mb_strlen($t, 'UTF-8') < 3) continue;
        if (in_array($tag, ['h2', 'h3', 'h4'], true)) {
            $t = trim(preg_replace('/^\d+(\.\d+)*\s*[.):]?\s*/u', '', $t) ?? $t);
            if (preg_match('/^(mục lục|mới & nóng|xem thêm|bình luận)/iu', $t)) continue;
            if ($tag !== 'h4') $outline[] = ($tag === 'h3' ? '   - H3: ' : 'H2: ') . $t;
            $lines[] = '[' . strtoupper($tag) . '] ' . $t;
        } else $lines[] = $t;
    }
    $text = mb_substr(implode("\n", array_values(array_unique($lines))), 0, AUTO_REF_MAX_TEXT, 'UTF-8');
    if (mb_strlen($text, 'UTF-8') < 400) return null;
    return ['url' => $url, 'title' => $title, 'outline' => array_slice($outline, 0, 30), 'text' => $text];
}
/** Bài tham khảo cho một từ khóa (lưu tạm 3 ngày); null nếu không tìm thấy hoặc tải lỗi. Không bao giờ ném lỗi. */
function auto_ref_article(string $DIR, string $kw): ?array {
    try {
        $u = auto_ref_pick(auto_ref_index($DIR), $kw);
        if ($u === null) return null;
        $cd = auto_dir($DIR) . '/ref'; if (!is_dir($cd)) @mkdir($cd, 0750, true);
        $cf = $cd . '/' . md5($u) . '.json';
        $c = auto_json($cf, []);
        if (!empty($c['text']) && time() - (int)($c['t'] ?? 0) < 259200) return $c['ref'] ?? null;
        $ref = auto_ref_parse(auto_ref_get($u), $u);
        if ($ref) auto_save_json($cf, ['t' => time(), 'text' => 1, 'ref' => $ref]);
        return $ref;
    } catch (Throwable $e) { return null; }
}
/** Nội dung bài tham khảo dưới dạng một mục của bộ dữ kiện. */
function auto_ref_facts(array $ref): string {
    return '## Bài tham khảo cùng chủ đề trên chungauto.vn: ' . $ref['title'] . ' (' . $ref['url'] . ") – CHỈ LÀ DỮ KIỆN, không phải văn mẫu\n"
        . implode("\n", array_map(fn($l) => '- ' . $l, auto_ref_fact_lines($ref['text'])));
}
/**
 * Chỉ giữ phần "dữ kiện" của bài tham khảo: tiêu đề mục, dòng bảng, dòng có con số, dòng dạng "Nhãn: …", câu ngắn (tên sản phẩm, đặc điểm).
 * Bỏ các đoạn văn xuôi dài: AI không còn câu mẫu để bắt chước giọng văn chungauto.vn (vẫn đủ số liệu, quy định, tên sản phẩm).
 */
function auto_ref_fact_lines(string $text): array {
    $out = [];
    foreach (preg_split('/\n/', $text) ?: [] as $l) {
        $l = trim($l);
        if ($l === '') continue;
        $w = count(preg_split('/\s+/u', $l) ?: []);
        if (preg_match('/^\[H[234]\]/u', $l) || str_contains($l, ' | ') || preg_match('/\d/u', $l) || preg_match('/^[^.:!?]{2,50}:\s*\S/u', $l) || $w <= 14) $out[] = $l;
    }
    return $out;
}
/** Số tiêu đề mục H2 của bài trùng (gần như nguyên văn) với tiêu đề mục của bài tham khảo. */
function auto_ref_same_headings(string $body, array $ref): int {
    $norm = fn(string $s) => trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', mb_strtolower(preg_replace('/^\s*(?:H2:|-\s*H3:)?\s*(?:\d+(?:\.\d+)*\s*[.):]?\s*)?/u', '', $s) ?? $s, 'UTF-8')) ?? '') ?? '');
    $refH = array_map($norm, (array)($ref['outline'] ?? []));
    $n = 0;
    if (preg_match_all('/^##\s+(.+)$/mu', $body, $m)) {
        foreach ($m[1] as $h) { $h = $norm($h); if ($h !== '' && !preg_match('/^(câu hỏi thường gặp|kết luận)/u', $h) && in_array($h, $refH, true)) $n++; }
    }
    return $n;
}
/** Lời dặn khi có bài tham khảo: lấy dữ kiện và dàn ý, viết lại bằng lời mới, làm tốt hơn. */
function auto_ref_prompt(array $ref, bool $hasOutline): string {
    $p = "- BÀI THAM KHẢO CÙNG CHỦ ĐỀ trên chungauto.vn (website cùng công ty ChungAuto): \"{$ref['title']}\". Nội dung đã nằm trong BỘ DỮ KIỆN (mục \"Bài tham khảo cùng chủ đề\"): được dùng số liệu, mức phạt, quy định, thủ tục, tên sản phẩm và giá trong đó như dữ kiện, giữ đúng từng con số, mốc thời gian và tên văn bản pháp luật.\n"
        . "- VIẾT LẠI HOÀN TOÀN bằng lời và giọng văn của vfwild.vn, KHÁC hẳn chungauto.vn: không chép câu, không quá 6 từ liên tiếp giống bài tham khảo, không bắt chước cách mở bài, cách chuyển ý hay cách kết của bài đó. Tiêu đề bài và tiêu đề mục phải đặt theo cách nói riêng (không trùng nguyên văn tiêu đề mục của bài tham khảo), có thể đổi thứ tự, gộp hoặc tách mục. Làm tốt hơn bài tham khảo: trả lời thẳng ở đoạn đầu, gom số liệu vào bảng (ví dụ bảng mức phạt, bảng giấy tờ, bảng giá), FAQ đúng chủ đề, câu ngắn, bỏ ý lặp và lời quảng cáo.\n"
        . "- Nếu bộ dữ kiện có thông tin về VF Wild liên quan tới chủ đề (ví dụ loại đăng ký, thủ tục, giấy tờ, phụ kiện), thêm một mục ngắn áp dụng cho chủ xe VF Wild; không gượng ép khi không liên quan.\n";
    if (!$hasOutline && $ref['outline']) $p .= "- CÁC Ý MÀ BÀI THAM KHẢO ĐÃ PHỦ (chỉ để không sót ý; tự đặt tên mục và thứ tự mục theo cách của bạn, không chép nguyên văn các tiêu đề này):\n" . implode("\n", array_map(fn($l) => '  ' . $l, $ref['outline'])) . "\n";
    return $p;
}
/** Tỉ lệ cụm $n từ liên tiếp của bài viết trùng với bài tham khảo (0–1): đo mức chép câu chữ. */
function auto_copy_ratio(string $body, string $ref, int $n = 8): float {
    $norm = fn(string $s) => preg_split('/\s+/u', trim(mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s) ?? '', 'UTF-8'))) ?: [];
    $b = $norm(preg_replace('/^\s*(#|\|).*$/mu', '', $body) ?? $body); $r = $norm($ref);
    if (count($b) < $n || count($r) < $n) return 0.0;
    $set = [];
    for ($i = 0; $i + $n <= count($r); $i++) $set[implode(' ', array_slice($r, $i, $n))] = 1;
    $hit = 0; $tot = 0;
    for ($i = 0; $i + $n <= count($b); $i++) { $tot++; if (isset($set[implode(' ', array_slice($b, $i, $n))])) $hit++; }
    return $tot ? $hit / $tot : 0.0;
}

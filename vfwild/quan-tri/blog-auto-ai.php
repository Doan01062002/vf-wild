<?php
/**
 * Nhà cung cấp AI cho bộ máy tự động viết bài: Gemini, OpenAI (ChatGPT), Anthropic (Claude), OpenRouter, Groq.
 * Mỗi nhà cung cấp có khóa API riêng (auto/secret.json, quyền 0600) và model riêng (settings.json → models).
 * Biến môi trường VFW_<TÊN>_BASE (GEMINI, OPENAI, ANTHROPIC, OPENROUTER, GROQ) đổi địa chỉ gốc, chỉ dùng để kiểm thử.
 */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }

const AUTO_PROVIDERS = [
    'mock' => ['label' => 'Thử nghiệm (mock) – không dùng AI, bài chỉ lưu nháp', 'short' => 'Thử nghiệm', 'key' => '', 'model' => '', 'link' => '', 'base' => ''],
    'gemini_api' => ['label' => 'Google Gemini', 'short' => 'Gemini', 'note' => 'Có gói miễn phí', 'vision' => true, 'key' => 'gemini_api_key', 'model' => 'gemini-3.5-flash-lite', 'link' => 'aistudio.google.com/apikey', 'base' => 'https://generativelanguage.googleapis.com'],
    'openai_api' => ['label' => 'OpenAI (ChatGPT)', 'short' => 'ChatGPT', 'note' => 'Trả phí theo lượng dùng', 'vision' => true, 'key' => 'openai_api_key', 'model' => 'gpt-5-mini', 'link' => 'platform.openai.com/api-keys', 'base' => 'https://api.openai.com'],
    'anthropic_api' => ['label' => 'Anthropic (Claude)', 'short' => 'Claude', 'note' => 'Trả phí theo lượng dùng', 'vision' => true, 'key' => 'anthropic_api_key', 'model' => 'claude-sonnet-5-5', 'link' => 'console.anthropic.com/settings/keys', 'base' => 'https://api.anthropic.com'],
    'openrouter_api' => ['label' => 'OpenRouter', 'short' => 'OpenRouter', 'note' => 'Nhiều hãng trong một khóa, có model miễn phí (đuôi :free)', 'vision' => true, 'key' => 'openrouter_api_key', 'model' => '', 'link' => 'openrouter.ai/keys', 'base' => 'https://openrouter.ai/api'],
    'groq_api' => ['label' => 'Groq (Llama…)', 'short' => 'Groq', 'note' => 'Có gói miễn phí, rất nhanh', 'vision' => false, 'key' => 'groq_api_key', 'model' => 'llama-3.3-70b-versatile', 'link' => 'console.groq.com/keys', 'base' => 'https://api.groq.com/openai'],
];

function auto_prov_label(string $p): string { return (string)(AUTO_PROVIDERS[$p]['label'] ?? $p); }
function auto_prov_base(string $p): string {
    $env = getenv('VFW_' . strtoupper(explode('_', $p)[0]) . '_BASE');
    return rtrim(is_string($env) && $env !== '' ? $env : (string)AUTO_PROVIDERS[$p]['base'], '/');
}
/** Model đang chọn cho một nhà cung cấp (mặc định theo bảng trên). */
function auto_model(array $s, string $p): string {
    $m = trim((string)($s['models'][$p] ?? ''));
    return $m !== '' ? $m : (string)(AUTO_PROVIDERS[$p]['model'] ?? '');
}
function auto_model_valid(string $m): bool { return (bool)preg_match('#^[A-Za-z0-9][A-Za-z0-9._:/-]{1,100}$#', $m); }
function auto_key_valid(string $k): bool { return (bool)preg_match('/^[A-Za-z0-9._-]{20,300}$/', $k); }

/** Gửi một yêu cầu HTTP, trả về [mã HTTP, JSON đã giải mã]. */
function auto_http(string $method, string $url, array $headers, ?array $body, int $timeout): array {
    $ctx = stream_context_create(['http' => ['method' => $method, 'timeout' => $timeout, 'ignore_errors' => true, 'header' => implode("\r\n", $headers) . "\r\n",
        'content' => $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE)], 'ssl' => ['verify_peer' => true]]);
    $raw = @file_get_contents($url, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $h) { if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1]; }
    if ($raw === false || $code === 0) throw new RuntimeException('Không kết nối được tới nhà cung cấp AI (mạng hoặc hết thời gian chờ).');
    $j = json_decode($raw, true);
    return [$code, is_array($j) ? $j : []];
}
function auto_http_error(string $p, int $code, array $j, string $model): RuntimeException {
    $msg = (string)($j['error']['message'] ?? (is_string($j['error'] ?? null) ? $j['error'] : ''));
    $hint = [400 => 'Yêu cầu hoặc tên model không hợp lệ.', 401 => 'Khóa API không đúng.', 402 => 'Tài khoản hết credit, cần nạp thêm.', 403 => 'Khóa API không có quyền hoặc bị chặn.',
        404 => 'Model "' . $model . '" không tồn tại hoặc không có trong gói của bạn – bấm "Tải danh sách model" để chọn.', 429 => 'Đã hết hạn mức (theo phút, theo ngày hoặc credit) – hệ thống sẽ tự thử lại sau.',
        500 => 'Máy chủ nhà cung cấp lỗi, sẽ tự thử lại.', 503 => 'Nhà cung cấp đang quá tải, sẽ tự thử lại.'][$code] ?? 'Nhà cung cấp báo lỗi.';
    return new RuntimeException(AUTO_PROVIDERS[$p]['short'] . " lỗi $code: $hint" . ($msg !== '' ? ' (' . mb_substr($msg, 0, 160) . ')' : ''), $code);
}

/** Gọi một model và trả về văn bản thô. */
function auto_ai_call(string $DIR, string $p, string $model, string $prompt, bool $json, int $timeout = 420, array $images = []): string {
    if (!isset(AUTO_PROVIDERS[$p]) || $p === 'mock') throw new RuntimeException('Nhà cung cấp AI không hợp lệ.');
    $key = auto_secret($DIR, AUTO_PROVIDERS[$p]['key']);
    if ($key === '') throw new RuntimeException('Chưa nhập khóa API cho ' . AUTO_PROVIDERS[$p]['short'] . ' (mục Cài đặt).');
    if ($model === '') throw new RuntimeException('Chưa chọn model cho ' . AUTO_PROVIDERS[$p]['short'] . ' – bấm "Tải danh sách model" rồi chọn một model.');
    if (!auto_model_valid($model)) throw new RuntimeException('Tên model không hợp lệ.');
    $base = auto_prov_base($p);
    if (empty(AUTO_PROVIDERS[$p]['vision'])) $images = [];
    if ($images) $timeout = max($timeout, 480);
    switch ($p) {
        case 'gemini_api':
            $gc = ['temperature' => 0.7];
            if ($json) $gc['responseMimeType'] = 'application/json';
            $caps = [65536, 32768, 16384, 8192];   // không đặt trần thấp: bài dài cần nhiều token (kể cả token "suy nghĩ"); model nào không nhận mức cao thì tự hạ
            foreach ($caps as $ci => $cap) {
                $gc['maxOutputTokens'] = $cap;
                [$code, $j] = auto_http('POST', "$base/v1beta/models/" . rawurlencode($model) . ':generateContent', ['Content-Type: application/json', "x-goog-api-key: $key"],
                    ['contents' => [['parts' => array_merge(array_map(fn($im) => ['inline_data' => ['mime_type' => $im['mime'], 'data' => $im['b64']]], $images), [['text' => $prompt]])]], 'generationConfig' => $gc], $timeout);
                if ($code === 400 && $ci < count($caps) - 1 && preg_match('/token/i', (string)($j['error']['message'] ?? ''))) continue;
                break;
            }
            if ($code !== 200) throw auto_http_error($p, $code, $j, $model);
            $text = '';
            foreach ((array)($j['candidates'][0]['content']['parts'] ?? []) as $part) { if (empty($part['thought']) && isset($part['text'])) $text .= (string)$part['text']; }
            $why = (string)($j['candidates'][0]['finishReason'] ?? $j['promptFeedback']['blockReason'] ?? '?');
            break;
        case 'anthropic_api':
            $caps = [32000, 16000, 8192];
            foreach ($caps as $ci => $cap) {
                [$code, $j] = auto_http('POST', "$base/v1/messages", ['Content-Type: application/json', "x-api-key: $key", 'anthropic-version: 2023-06-01'],
                    ['model' => $model, 'max_tokens' => $cap, 'messages' => [['role' => 'user', 'content' => $images
                        ? array_merge(array_map(fn($im) => ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $im['mime'], 'data' => $im['b64']]], $images), [['type' => 'text', 'text' => $prompt]])
                        : $prompt]]], $timeout);
                if ($code === 400 && $ci < count($caps) - 1 && preg_match('/max_tokens|token/i', (string)($j['error']['message'] ?? ''))) continue;
                break;
            }
            if ($code !== 200) throw auto_http_error($p, $code, $j, $model);
            $text = '';
            foreach ((array)($j['content'] ?? []) as $part) { if (($part['type'] ?? '') === 'text') $text .= (string)($part['text'] ?? ''); }
            $why = (string)($j['stop_reason'] ?? '?');
            break;
        default:   // OpenAI, OpenRouter, Groq: giao thức "chat completions" giống nhau
            $body = ['model' => $model, 'messages' => [['role' => 'user', 'content' => $images
                ? array_merge([['type' => 'text', 'text' => $prompt]], array_map(fn($im) => ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $im['mime'] . ';base64,' . $im['b64']]], $images))
                : $prompt]]];
            if ($p === 'openai_api' && $json) $body['response_format'] = ['type' => 'json_object'];
            $caps = $p === 'openai_api' ? [0] : [32000, 16000, 8000, 4096];   // OpenAI tự đặt trần theo model; hãng khác mặc định thấp nên đặt cao rồi hạ dần
            foreach ($caps as $ci => $cap) {
                if ($cap) $body['max_tokens'] = $cap;
                [$code, $j] = auto_http('POST', "$base/v1/chat/completions", ['Content-Type: application/json', "Authorization: Bearer $key"], $body, $timeout);
                if ($code === 400 && $ci < count($caps) - 1 && preg_match('/token|context|maximum/i', (string)($j['error']['message'] ?? (is_string($j['error'] ?? null) ? $j['error'] : '')))) continue;
                break;
            }
            if ($code !== 200) throw auto_http_error($p, $code, $j, $model);
            $text = (string)($j['choices'][0]['message']['content'] ?? '');
            $why = (string)($j['choices'][0]['finish_reason'] ?? '?');
    }
    if (trim($text) === '') throw new RuntimeException(AUTO_PROVIDERS[$p]['short'] . ' không trả về nội dung (có thể hết giới hạn độ dài hoặc bị bộ lọc chặn). Lý do dừng: ' . $why);
    return $text;
}

/**
 * Đọc JSON do AI trả về: lấy từ dấu { đầu đến dấu } cuối (bỏ khung ```json và lời dẫn thừa). Nhiều model hay gõ xuống dòng / tab THẬT bên trong
 * chuỗi (không viết \n) làm json_decode báo "Control character error": trường hợp đó tự sửa lại rồi đọc tiếp. Trả null nếu vẫn hỏng.
 */
function auto_json_decode(string $text): ?array {
    $a = strpos($text, '{'); $b = strrpos($text, '}');
    if ($a === false || $b === false || $b <= $a) return null;
    $raw = substr($text, $a, $b - $a + 1);
    $j = json_decode($raw, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
    if (is_array($j)) return $j;
    $out = ''; $in = false; $esc = false;
    for ($i = 0, $n = strlen($raw); $i < $n; $i++) {
        $c = $raw[$i];
        if ($in) {
            if ($esc) { $esc = false; $out .= $c; continue; }
            if ($c === '\\') { $esc = true; $out .= $c; continue; }
            if ($c === '"') { $in = false; $out .= $c; continue; }
            $o = ord($c);
            if ($o < 0x20) { $out .= $c === "\n" ? '\n' : ($c === "\r" ? '\r' : ($c === "\t" ? '\t' : sprintf('\u%04x', $o))); continue; }
            $out .= $c; continue;
        }
        if ($c === '"') $in = true;
        $out .= $c;
    }
    $j = json_decode($out, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
    return is_array($j) ? $j : null;
}

/** Nhờ AI viết bài và đọc lại kết quả JSON. Đọc hỏng thì gọi lại một lần ngay (lỗi thoáng qua của model), đỡ phải chờ 30 phút. */
function auto_ai_article(array $s, array $ctx): array {
    $p = (string)$s['provider'];
    $imgs = (array)($ctx['images'] ?? []);
    $vision = (bool)$imgs;
    $j = null;
    for ($try = 0; $try < 2 && !is_array($j); $try++) {
        try { $text = auto_ai_call((string)$ctx['dir'], $p, auto_model($s, $p), (string)($vision ? $ctx['prompt'] : ($ctx['prompt_noimg'] ?? $ctx['prompt'])), true, 420, $vision ? $imgs : []); }
        catch (RuntimeException $e) {
            if (!$vision || $e->getCode() !== 400) throw $e;   // model không nhận ảnh: viết lại không ảnh, ảnh sẽ chèn theo vị trí cố định
            $vision = false;
            $text = auto_ai_call((string)$ctx['dir'], $p, auto_model($s, $p), (string)($ctx['prompt_noimg'] ?? $ctx['prompt']), true);
        }
        $j = auto_json_decode($text);
        if (is_array($j) && (!isset($j['title'], $j['description'], $j['body']) || !is_string($j['body']) || !is_string($j['title']) || !is_string($j['description']))) $j = null;
    }
    if (!is_array($j)) throw new RuntimeException('AI trả về sai khuôn JSON (thiếu title/description/body, hoặc bị cắt giữa chừng) sau 2 lần gọi.');
    $j['_vision'] = $vision;
    return $j;
}

/** Lấy danh sách model của nhà cung cấp (dùng khóa đã lưu) và lưu tạm để gợi ý trong ô Model. */
function auto_models_fetch(string $DIR, string $p): array {
    if (!isset(AUTO_PROVIDERS[$p]) || $p === 'mock') throw new RuntimeException('Nhà cung cấp AI không hợp lệ.');
    $key = auto_secret($DIR, AUTO_PROVIDERS[$p]['key']);
    if ($key === '') throw new RuntimeException('Hãy nhập khóa API cho ' . AUTO_PROVIDERS[$p]['short'] . ' trước.');
    $base = auto_prov_base($p);
    $ids = [];
    if ($p === 'gemini_api') {
        [$code, $j] = auto_http('GET', "$base/v1beta/models?pageSize=200", ["x-goog-api-key: $key"], null, 30);
        if ($code !== 200) throw auto_http_error($p, $code, $j, '');
        foreach ((array)($j['models'] ?? []) as $m) {
            $id = preg_replace('#^models/#', '', (string)($m['name'] ?? ''));
            if ($id !== '' && in_array('generateContent', (array)($m['supportedGenerationMethods'] ?? []), true) && !preg_match('/(tts|image|live|transcribe|embed|lyria|robotics|computer-use|deep-research|omni|antigravity|aqa)/i', $id)) $ids[] = $id;
        }
    } else {
        $h = $p === 'anthropic_api' ? ["x-api-key: $key", 'anthropic-version: 2023-06-01'] : ["Authorization: Bearer $key"];
        [$code, $j] = auto_http('GET', "$base/v1/models" . ($p === 'anthropic_api' ? '?limit=100' : ''), $h, null, 30);
        if ($code !== 200) throw auto_http_error($p, $code, $j, '');
        foreach ((array)($j['data'] ?? []) as $m) {
            $id = (string)($m['id'] ?? '');
            if ($id === '') continue;
            if ($p === 'openai_api' && !preg_match('/^(gpt|o\d|chatgpt)/i', $id)) continue;
            if (preg_match('/(whisper|tts|embed|image|audio|realtime|transcribe|moderation|dall|guard|search)/i', $id)) continue;
            $ids[] = $id;
        }
    }
    $ids = array_values(array_filter(array_unique($ids), 'auto_model_valid'));
    sort($ids, SORT_NATURAL | SORT_FLAG_CASE);
    if ($p === 'openrouter_api') usort($ids, fn($a, $b) => [!str_ends_with($a, ':free'), $a] <=> [!str_ends_with($b, ':free'), $b]);
    $f = auto_dir($DIR) . '/models.json';
    $all = auto_json($f, []);
    $all[$p] = array_slice($ids, 0, 400);
    auto_save_json($f, $all);
    return $ids;
}
function auto_models_cached(string $DIR, string $p): array {
    $l = auto_json(auto_dir($DIR) . '/models.json', [])[$p] ?? [];
    return is_array($l) ? array_values(array_filter($l, 'is_string')) : [];
}

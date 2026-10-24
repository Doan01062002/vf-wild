<?php
/**
 * Kết nối Google Sheet bằng tài khoản dịch vụ (đọc từ khóa + giờ đăng, ghi lại trạng thái).
 * Khóa tài khoản dịch vụ: file JSON ở $DIR/google-service-account.json (hoặc đường dẫn trong biến môi trường VFW_GSA_KEY).
 * Không có thư viện ngoài: tự ký JWT RS256 bằng openssl.
 */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }

function gs_key_file(string $DIR): string {
    $env = getenv('VFW_GSA_KEY');
    if (is_string($env) && $env !== '' && is_file($env)) return $env;
    return "$DIR/google-service-account.json";
}
function gs_account(string $DIR): ?array {
    $f = gs_key_file($DIR);
    $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($j) && !empty($j['client_email']) && !empty($j['private_key']) ? $j : null;
}
function gs_b64u(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

function gs_http(string $method, string $url, ?string $body, array $headers, int $timeout = 30): array {
    $ctx = stream_context_create(['http' => ['method' => $method, 'timeout' => $timeout, 'ignore_errors' => true, 'header' => implode("\r\n", $headers) . "\r\n", 'content' => $body ?? ''], 'ssl' => ['verify_peer' => true]]);
    $raw = @file_get_contents($url, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $h) { if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1]; }
    if ($raw === false || $code === 0) throw new RuntimeException('Không kết nối được tới Google (mạng hoặc hết thời gian chờ).');
    $j = json_decode($raw, true);
    return [$code, is_array($j) ? $j : []];
}

/** Lấy mã truy cập (hiệu lực ~1 giờ, lưu tạm trong auto/gs-token.json). */
function gs_token(string $DIR, string $scope = 'https://www.googleapis.com/auth/spreadsheets'): string {
    $cache = auto_dir($DIR) . ($scope === 'https://www.googleapis.com/auth/spreadsheets' ? '/gs-token.json' : '/gs-token-' . substr(md5($scope), 0, 8) . '.json');
    $c = auto_json($cache, []);
    if (!empty($c['token']) && (int)($c['exp'] ?? 0) > time() + 120) return (string)$c['token'];
    $acc = gs_account($DIR);
    if (!$acc) throw new RuntimeException('Chưa có khóa tài khoản dịch vụ Google (file google-service-account.json).');
    $now = time();
    $head = gs_b64u(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = gs_b64u(json_encode(['iss' => $acc['client_email'], 'scope' => $scope, 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3300]));
    $sig = '';
    if (!openssl_sign("$head.$claims", $sig, (string)$acc['private_key'], OPENSSL_ALGO_SHA256)) throw new RuntimeException('Khóa tài khoản dịch vụ không hợp lệ.');
    [$code, $j] = gs_http('POST', getenv('VFW_TOKEN_URL') ?: 'https://oauth2.googleapis.com/token', http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "$head.$claims." . gs_b64u($sig)]), ['Content-Type: application/x-www-form-urlencoded']);
    if ($code !== 200 || empty($j['access_token'])) throw new RuntimeException('Google từ chối đăng nhập tài khoản dịch vụ (' . $code . '): ' . (string)($j['error_description'] ?? $j['error'] ?? '?'));
    auto_save_json($cache, ['token' => $j['access_token'], 'exp' => $now + (int)($j['expires_in'] ?? 3300)]);
    @chmod($cache, 0600);
    return (string)$j['access_token'];
}

function gs_id_from_url(string $s): ?string {
    $s = trim($s);
    if (preg_match('#^https://docs\.google\.com/spreadsheets/d/([A-Za-z0-9_-]{20,})#', $s, $m)) return $m[1];
    return preg_match('/^[A-Za-z0-9_-]{20,}$/', $s) ? $s : null;
}

function gs_api(string $DIR, string $method, string $sheetId, string $path, ?array $body = null): array {
    $url = (getenv('VFW_SHEETS_BASE') ?: 'https://sheets.googleapis.com') . '/v4/spreadsheets/' . rawurlencode($sheetId) . $path;
    [$code, $j] = gs_http($method, $url, $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE), ['Authorization: Bearer ' . gs_token($DIR), 'Content-Type: application/json']);
    if ($code !== 200) {
        $hint = [403 => 'Tài khoản dịch vụ chưa được chia sẻ Sheet này (hãy chia sẻ quyền Editor cho email của nó).', 404 => 'Không tìm thấy Sheet hoặc tab.', 400 => 'Yêu cầu không hợp lệ (kiểm tra tên tab).'][$code] ?? 'Google báo lỗi.';
        throw new RuntimeException("Google Sheets lỗi $code: $hint");
    }
    return $j;
}
/** Đọc một vùng, trả về mảng các dòng (chuỗi đã định dạng như hiển thị trên Sheet). */
function gs_read(string $DIR, string $sheetId, string $range): array {
    $j = gs_api($DIR, 'GET', $sheetId, '/values/' . rawurlencode($range) . '?valueRenderOption=FORMATTED_VALUE', null);
    return array_map(fn($r) => array_map('strval', $r), (array)($j['values'] ?? []));
}
function gs_write(string $DIR, string $sheetId, string $range, array $rows): void {
    gs_api($DIR, 'PUT', $sheetId, '/values/' . rawurlencode($range) . '?valueInputOption=USER_ENTERED', ['range' => $range, 'majorDimension' => 'ROWS', 'values' => $rows]);
}
function gs_batch(string $DIR, string $sheetId, array $data): void {
    gs_api($DIR, 'POST', $sheetId, '/values:batchUpdate', ['valueInputOption' => 'USER_ENTERED', 'data' => $data]);
}
function gs_append(string $DIR, string $sheetId, string $range, array $rows, bool $raw = false): void {
    gs_api($DIR, 'POST', $sheetId, '/values/' . rawurlencode($range) . ':append?valueInputOption=' . ($raw ? 'RAW' : 'USER_ENTERED') . '&insertDataOption=INSERT_ROWS', ['values' => $rows]);
}
/** Số thứ tự cột (0 = A) → chữ cột. */
function gs_col(int $i): string { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; }

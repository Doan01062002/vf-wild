<?php
/**
 * VF Wild ChungAuto – nhận lead từ form.
 * - Bắt buộc có đồng ý xử lý dữ liệu (Luật 91/2025/QH15, NĐ 356/2025); O2O cần thêm đồng ý chia sẻ cho VinFast.
 * - Lưu CSV NGOÀI thư mục web; token Telegram để trong file cấu hình riêng, không bao giờ đưa ra trình duyệt.
 * - Trả kết quả cho khách trước, sau đó mới báo Telegram và ghi Google Sheet (Apps Script web app).
 * Cấu hình: tạo file ../vfwild-private/config.php (cùng cấp với thư mục web) trả về mảng, ví dụ:
 *   <?php return ['telegram_token' => '123:ABC', 'telegram_chat_id' => '-100123', 'telegram_full_phone' => false,
 *                 'sheet_webhook' => 'https://script.google.com/macros/s/…/exec', 'sheet_secret' => '…',
 *                 'sheet_url' => 'https://docs.google.com/spreadsheets/d/…'];
 * Mã Apps Script cho Google Sheet: server/google-sheet-apps-script.gs
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$config = [
    'csv_file'            => dirname(__DIR__) . '/vfwild-private/leads.csv',
    'telegram_token'      => '',
    'telegram_chat_id'    => '',
    'telegram_full_phone' => true,   // sales cần số để gọi lại; đổi false khi đã có CRM
    'telegram_api'        => 'https://api.telegram.org',
    'sheet_webhook'       => '',     // URL Web app của Apps Script gắn với Google Sheet (…/exec)
    'sheet_secret'        => '',     // phải trùng SECRET trong mã Apps Script
    'sheet_url'           => '',     // link Google Sheet, gửi kèm mỗi tin Telegram
    'legacy_config'       => '/opt/seo-bot/lead-config.php',   // cấu hình Telegram dùng chung với limogreen.vn
    'legacy_log'          => '/opt/seo-bot/leads.log',         // log dùng chung (giữ định dạng cũ cho báo cáo)
    // http:// chỉ để không mất lead trong lúc chuyển sang HTTPS; sau khi bật chuyển hướng https thì không còn dùng tới
    'allowed_origins'     => ['https://vfwild.vn', 'https://www.vfwild.vn', 'http://vfwild.vn', 'http://www.vfwild.vn'],
];
$private = dirname(__DIR__) . '/vfwild-private/config.php';
if (is_file($private)) {
    $config = array_merge($config, (array) require $private);
}
if ($config['telegram_token'] === '' && $config['legacy_config'] !== '' && is_file($config['legacy_config'])) {
    $legacy = @include $config['legacy_config'];
    if (is_array($legacy)) {
        $config['telegram_token']   = (string) ($legacy['token'] ?? '');
        $config['telegram_chat_id'] = (string) ($legacy['chat'] ?? '');
    }
}

function respond(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Tóm tắt nguồn truy cập cho sales đọc nhanh: utm, quảng cáo (click ID), trang giới thiệu hoặc truy cập trực tiếp. */
function source_summary(string $json): string
{
    $a = json_decode($json, true);
    if (!is_array($a)) {
        return '';
    }
    $t = is_array($a['last'] ?? null) ? $a['last'] : (is_array($a['first'] ?? null) ? $a['first'] : []);
    if (!empty($t['utm_source'])) {
        $parts = array_filter([$t['utm_source'], $t['utm_medium'] ?? '', $t['utm_campaign'] ?? ''], 'strlen');
        return mb_substr(strip_tags(implode(' / ', $parts)), 0, 150);
    }
    foreach (['gclid' => 'Google Ads', 'gbraid' => 'Google Ads', 'wbraid' => 'Google Ads', 'fbclid' => 'Facebook', 'ttclid' => 'TikTok'] as $k => $label) {
        if (!empty($t[$k])) {
            return $label;
        }
    }
    if (!empty($a['referrer'])) {
        $host = parse_url((string) $a['referrer'], PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return preg_replace('/^www\./', '', $host);
        }
    }
    return 'Truy cập trực tiếp';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'method']);
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !in_array($origin, $config['allowed_origins'], true)) {
    respond(403, ['ok' => false, 'error' => 'origin']);
}
if (!empty($_POST['website'])) {            // bẫy bot
    respond(201, ['ok' => true, 'lead_ref' => 'OK']);
}
if ((int) ($_POST['t'] ?? 0) < 3000) {      // điền form dưới 3 giây → nhiều khả năng là bot
    respond(422, ['ok' => false, 'error' => 'too_fast']);
}

$field = static function (string $key, int $max = 300): string {
    $v = (string) ($_POST[$key] ?? '');
    $v = trim(strip_tags($v));
    $v = preg_replace('/^[=+\-@]+/', '', $v);   // chặn chèn công thức khi mở CSV bằng Excel
    return mb_substr($v, 0, $max);
};

$name  = $field('name', 100);
$phone = preg_replace('/[^\d+]/', '', $field('phone', 20));
$phone = preg_replace('/^\+?84/', '0', $phone);
if ($name === '' || !preg_match('/^0(3|5|7|8|9)\d{8}$/', $phone)) {
    respond(422, ['ok' => false, 'error' => 'invalid']);
}
if (($_POST['consent_contact'] ?? '') !== '1') {
    respond(422, ['ok' => false, 'error' => 'consent']);
}
$intent = $field('intent', 20);
if ($intent === 'o2o' && ($_POST['consent_share_vinfast'] ?? '') !== '1') {
    respond(422, ['ok' => false, 'error' => 'consent_vinfast']);
}

$ref = strtoupper(bin2hex(random_bytes(4)));
$row = [
    date('c'), $ref, $name, $phone, $intent . ($field('segment_label', 80) !== '' ? ' | ' . $field('segment_label', 80) : ''),
    $field('province', 40), $field('showroom', 60), $field('use', 200), $field('note', 500),
    $field('consent_contact', 1), $field('consent_share_vinfast', 1), $field('consent_marketing', 1), $field('consent_version', 20),
    $field('form_id', 40), $field('page', 300), $field('attribution', 1500), $field('calc', 600), $field('event_id', 64),
];

$dir = dirname($config['csv_file']);
if (!is_dir($dir)) {
    @mkdir($dir, 0750, true);
}
$isNew = !is_file($config['csv_file']);
$fh = @fopen($config['csv_file'], 'a');
if (!$fh) {
    respond(500, ['ok' => false, 'error' => 'storage']);
}
flock($fh, LOCK_EX);
if ($isNew) {
    fputcsv($fh, ['thoi_gian', 'ma', 'ho_ten', 'sdt', 'nhu_cau', 'tinh', 'showroom', 'muc_dich', 'loi_nhan',
        'dong_y_lien_he', 'dong_y_chia_se_vinfast', 'dong_y_marketing', 'phien_ban_dong_y',
        'form', 'trang', 'nguon_truy_cap', 'bang_tinh', 'event_id']);
}
fputcsv($fh, $row);
flock($fh, LOCK_UN);
fclose($fh);

$intentLabels = ['bao-gia' => 'Báo giá lăn bánh', 'lai-thu' => 'Lái thử', 'tra-gop' => 'Trả góp', 'o2o' => 'Cọc online O2O',
    'uu-dai' => 'Ưu đãi', 'tu-van' => 'Tư vấn'];
$formLabels = ['dang-ky' => 'Form đăng ký', 'popup' => 'Popup (sau 3 phút trên web)'];
$subject   = 'VF Wild – ' . ($intentLabels[$intent] ?? $intent);
$segment   = $field('segment_label', 80);   // nhóm khách chọn ở popup tiên phong, vd "Công an, quân đội (730 triệu)"
$province  = $field('province_name', 60) ?: $field('province', 40);
$showroom  = $field('showroom_name', 100) ?: $field('showroom', 60);
$use       = $field('use', 200);
$note      = $field('note', 500);
$formId    = $field('form_id', 40);
$formLabel = $formLabels[$formId] ?? $formId;
$page      = $field('page', 300);
$source    = source_summary((string) ($_POST['attribution'] ?? ''));
$marketing = ($_POST['consent_marketing'] ?? '') === '1';
$shareVf   = ($_POST['consent_share_vinfast'] ?? '') === '1';

// Trả kết quả cho khách ngay; báo Telegram + ghi Google Sheet chạy tiếp phía sau để form không phải chờ
ignore_user_abort(true);
http_response_code(201);
echo json_encode(['ok' => true, 'lead_ref' => $ref], JSON_UNESCAPED_UNICODE);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$sheetOk = null;
if ($config['sheet_webhook'] !== '') {
    $ch = curl_init($config['sheet_webhook']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,   // Apps Script trả 302 sang script.googleusercontent.com
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'secret' => $config['sheet_secret'],
            'time' => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('d/m/Y H:i:s'),
            'ref' => $ref, 'name' => $name, 'phone' => $phone,
            'intent' => ($intentLabels[$intent] ?? $intent) . ($segment !== '' ? ' – ' . $segment : ''),
            'province' => $province, 'showroom' => $showroom, 'use' => $use, 'note' => $note,
            'marketing' => $marketing ? 'Có' : '', 'o2o_share' => $shareVf ? 'Có' : '',
            'form' => $formLabel, 'source' => $source, 'page' => $page,
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $resp = curl_exec($ch);
    $sheetOk = is_string($resp) && strpos($resp, '"ok":true') !== false;
    curl_close($ch);
}

$tgOk = false;
if ($config['telegram_token'] !== '' && $config['telegram_chat_id'] !== '') {
    $shownPhone = $config['telegram_full_phone'] ? $phone : substr($phone, 0, 4) . '***' . substr($phone, -3);
    $lines = [
        "🚙 LEAD VF WILD #$ref", "🎯 $subject", $segment !== '' ? "👥 Nhóm khách: $segment" : '', "👤 $name", "📞 $shownPhone",
        $province !== '' ? "📍 $province" : '',
        $showroom !== '' ? "🏢 $showroom" : '',
        $use !== '' ? "🚗 $use" : '',
        $note !== '' ? "📝 " . mb_substr($note, 0, 300) : '',
        '✅ Đồng ý liên hệ' . ($marketing ? ' + nhận ưu đãi' : '') . ($shareVf ? ' + chia sẻ VinFast (O2O)' : ''),
        "📋 $formLabel" . ($source !== '' ? " · $source" : ''),
        "🔗 $page",
        $config['sheet_url'] !== '' ? '📊 Danh sách lead (Google Sheet): ' . $config['sheet_url']
            . ($sheetOk === false ? ' – ⚠️ lead này chưa ghi được vào Sheet' : '') : '',
    ];
    $ch = curl_init(rtrim($config['telegram_api'], '/') . '/bot' . $config['telegram_token'] . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_POSTFIELDS => ['chat_id' => $config['telegram_chat_id'], 'text' => implode("\n", array_filter($lines, 'strlen')),
            'disable_web_page_preview' => 'true'],
    ]);
    $resp = curl_exec($ch);
    $tgOk = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && is_string($resp) && strpos($resp, '"ok":true') !== false;
    curl_close($ch);
}
if ($config['legacy_log'] !== '') {
    @file_put_contents($config['legacy_log'],
        date('c') . "\t" . $name . "\t" . $phone . "\t" . $subject . "\t" . $page . "\t" . ($tgOk ? 'OK' : 'FAIL') . "\n",
        FILE_APPEND);
}
// Nhật ký gửi (không chứa dữ liệu cá nhân) để kiểm tra khi Telegram/Sheet lỗi
@file_put_contents(dirname($config['csv_file']) . '/notify.log',
    date('c') . " $ref telegram=" . ($tgOk ? 'ok' : 'fail') . ' sheet=' . ($sheetOk === null ? 'off' : ($sheetOk ? 'ok' : 'fail')) . "\n",
    FILE_APPEND);

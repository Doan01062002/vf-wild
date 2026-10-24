<?php
/**
 * Trả các mã đo lường đang bật cho main.js (chỉ ID công khai, không có bí mật).
 * Mã do trang quản trị /quan-tri/ ghi vào /var/www/vfwild-private/tracking.json.
 * main.js chỉ tải các mã này sau khi khách bấm "Đồng ý" ở hộp cookie.
 */
declare(strict_types=1);

$dir = getenv('VFW_PRIVATE') ?: dirname(__DIR__) . '/vfwild-private';
// Kiểm lại đúng mẫu như trang quản trị, phòng khi file bị sửa tay sai
$patterns = [
    'gtm_id'          => '/^GTM-[A-Z0-9]{4,12}$/',
    'ga4_id'          => '/^G-[A-Z0-9]{4,16}$/',
    'gads_id'         => '/^AW-[0-9]{6,15}$/',
    'gads_lead_label' => '/^[A-Za-z0-9_-]{4,64}$/',
    'meta_pixel_id'   => '/^[0-9]{10,20}$/',
];
$raw = @file_get_contents($dir . '/tracking.json');
$data = $raw === false ? null : json_decode($raw, true);
$out = [];
foreach ($patterns as $k => $re) {
    $v = is_array($data) && isset($data[$k]) && is_string($data[$k]) ? $data[$k] : '';
    $out[$k] = preg_match($re, $v) ? $v : '';
}
if ($out['gads_id'] === '' || $out['gads_lead_label'] === '') $out['gads_id'] = $out['gads_lead_label'] = '';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');
echo json_encode($out, JSON_UNESCAPED_SLASHES);

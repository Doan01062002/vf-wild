<?php
/**
 * Google Search Console (chỉ đọc) qua tài khoản dịch vụ: lượt hiển thị, lượt nhấp, vị trí theo trang và theo truy vấn.
 * Cần thêm email tài khoản dịch vụ vào Search Console của site (Cài đặt → Người dùng và quyền, quyền "Hạn chế" là đủ).
 * Biến môi trường VFW_GSC_BASE đổi địa chỉ gốc, chỉ dùng để kiểm thử.
 */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }

const GSC_SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

function gsc_base(): string { $e = getenv('VFW_GSC_BASE'); return rtrim(is_string($e) && $e !== '' ? $e : 'https://www.googleapis.com/webmasters/v3', '/'); }
function gsc_site_valid(string $s): bool { return (bool)preg_match('#^(sc-domain:[A-Za-z0-9.-]+|https?://[A-Za-z0-9.-]+(:\d+)?/?)$#', $s); }

function gsc_call(string $DIR, string $method, string $path, ?array $body = null): array {
    [$code, $j] = gs_http($method, gsc_base() . $path, $body === null ? null : json_encode($body), ['Authorization: Bearer ' . gs_token($DIR, GSC_SCOPE), 'Content-Type: application/json'], 45);
    if ($code !== 200) {
        $hint = [403 => 'Tài khoản dịch vụ chưa được thêm vào Search Console của site này (hoặc chưa bật Search Console API).', 404 => 'Không tìm thấy site trong Search Console.', 400 => 'Yêu cầu không hợp lệ (kiểm tra tên site).'][$code] ?? 'Google báo lỗi.';
        throw new RuntimeException("Search Console lỗi $code: $hint");
    }
    return $j;
}
/** Các site tài khoản dịch vụ được phép xem. */
function gsc_sites(string $DIR): array {
    $out = [];
    foreach ((array)(gsc_call($DIR, 'GET', '/sites')['siteEntry'] ?? []) as $s) $out[] = (string)($s['siteUrl'] ?? '');
    return array_values(array_filter($out));
}
/** Lấy dữ liệu 28 ngày (trừ 3 ngày gần nhất vì Google chưa chốt số) và lưu vào auto/gsc.json. */
function gsc_refresh(string $DIR, string $site): array {
    $end = date('Y-m-d', strtotime('-3 days')); $start = date('Y-m-d', strtotime('-31 days'));
    $q = fn(array $dims) => gsc_call($DIR, 'POST', '/sites/' . rawurlencode($site) . '/searchAnalytics/query', ['startDate' => $start, 'endDate' => $end, 'dimensions' => $dims, 'rowLimit' => 1000, 'type' => 'web']);
    $pages = []; foreach ((array)($q(['page'])['rows'] ?? []) as $r) $pages[(string)$r['keys'][0]] = ['c' => (int)$r['clicks'], 'i' => (int)$r['impressions'], 'p' => round((float)$r['position'], 1)];
    $qp = []; foreach ((array)($q(['query', 'page'])['rows'] ?? []) as $r) $qp[] = ['q' => (string)$r['keys'][0], 'u' => (string)$r['keys'][1], 'c' => (int)$r['clicks'], 'i' => (int)$r['impressions'], 'p' => round((float)$r['position'], 1)];
    $d = ['at' => time(), 'site' => $site, 'from' => $start, 'to' => $end, 'pages' => $pages, 'qp' => $qp, 'error' => ''];
    auto_save_json(auto_dir($DIR) . '/gsc.json', $d);
    return $d;
}
function gsc_cached(string $DIR): array { return auto_json(auto_dir($DIR) . '/gsc.json', []); }
/** Làm mới tối đa mỗi 20 giờ; lỗi cũng ghi lại thời điểm để không thử lại mỗi phút. */
function gsc_refresh_if_due(string $DIR, array $s): void {
    $site = (string)($s['gsc_site'] ?? '');
    if ($site === '' || !gsc_site_valid($site) || !gs_account($DIR)) return;
    $c = gsc_cached($DIR);
    if (!empty($c['at']) && time() - (int)$c['at'] < 20 * 3600 && ($c['site'] ?? '') === $site) return;
    try { gsc_refresh($DIR, $site); }
    catch (Throwable $e) { auto_save_json(auto_dir($DIR) . '/gsc.json', ['at' => time(), 'site' => $site, 'pages' => [], 'qp' => [], 'error' => $e->getMessage()]); }
}
/** Số liệu của một bài (khớp theo đường dẫn /blog/<slug>/). */
function gsc_for_post(array $cache, string $slug): ?array {
    foreach ((array)($cache['pages'] ?? []) as $url => $m) { if (strpos($url, '/blog/' . $slug . '/') !== false) return $m; }
    return null;
}

<?php
/**
 * Trang quản trị vfwild.vn – cài mã đo lường: Google Tag Manager, GA4, Google Ads, Meta Pixel.
 * Địa chỉ: https://vfwild.vn/quan-tri/  (nginx chỉ chạy đúng địa chỉ này; mọi file khác trong /quan-tri/ trả 404).
 * Dữ liệu nằm ngoài thư mục web nên không bị ghi đè khi deploy (/var/www/vfwild-private/):
 *   tracking.json            mã đang dùng – web đọc qua /tracking.php (main.js)
 *   tracking-history.jsonl   lịch sử thay đổi
 *   admin.json               băm mật khẩu, khoá ký phiên, link đặt mật khẩu (quyền 600)
 *   admin-throttle.json      đếm lần nhập sai
 * Cấp link đặt / đặt lại mật khẩu (chạy trên VPS; hạn 72 giờ, dùng 1 lần):
 *   sudo -u www-data php /var/www/vfwild/quan-tri/index.php --setup-link
 */
declare(strict_types=1);
date_default_timezone_set('Asia/Ho_Chi_Minh');

const SESSION_TTL = 8 * 3600;
const SETUP_TTL = 72 * 3600;
const COOKIE_NAME = 'vfw_admin';
const MIN_PASSWORD = 10;
const MAX_FAILS = 5;       // sai 5 lần trong 15 phút → khoá IP đó 15 phút
const GLOBAL_FAILS = 30;   // tổng 30 lần sai trong 1 giờ → khoá mọi đăng nhập 30 phút
const SITE_URL = 'https://vfwild.vn';

// Mỗi mã: mẫu hợp lệ, tên hiển thị, ví dụ. /tracking.php kiểm lại đúng các mẫu này.
const FIELDS = [
    'gtm_id'          => ['/^GTM-[A-Z0-9]{4,12}$/', 'Mã Google Tag Manager', 'GTM-XXXXXXX'],
    'ga4_id'          => ['/^G-[A-Z0-9]{4,16}$/', 'Mã đo lường GA4', 'G-XXXXXXXXXX'],
    'gads_id'         => ['/^AW-[0-9]{6,15}$/', 'Google Ads – Conversion ID', 'AW-123456789'],
    'gads_lead_label' => ['/^[A-Za-z0-9_-]{4,64}$/', 'Google Ads – Conversion label', 'AbCdEfGhIjKlMn'],
    'meta_pixel_id'   => ['/^[0-9]{10,20}$/', 'Meta Pixel ID', '1234567890123456'],
];

$DIR = getenv('VFW_PRIVATE') ?: dirname(__DIR__, 2) . '/vfwild-private';
define('VFW_ADMIN', true);
require_once __DIR__ . '/blog-lib.php';
require_once __DIR__ . '/blog-views.php';   // quản lý blog (nginx không cho tải file này trực tiếp)
require_once __DIR__ . '/blog-auto.php';    // bộ máy tự động viết bài
require_once __DIR__ . '/blog-auto-sheet.php';   // kết nối Google Sheet
require_once __DIR__ . '/blog-auto-seo.php';     // mẫu bài, chấm điểm, liên kết nội bộ
require_once __DIR__ . '/blog-gsc.php';          // Search Console
require_once __DIR__ . '/blog-auto-img.php';     // ảnh theo link trong Sheet
require_once __DIR__ . '/blog-audit.php';        // kiểm tra nội dung
require_once __DIR__ . '/blog-users.php';        // tài khoản, vai trò
require_once __DIR__ . '/blog-review.php';       // review bài, sổ tay biên tập
require_once __DIR__ . '/blog-auto-ai.php';      // các nhà cung cấp AI
require_once __DIR__ . '/blog-auto-views.php';

/* ---------- Tiện ích ---------- */
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function b64u(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function sig(string $data, string $key): string { return b64u(hash_hmac('sha256', $data, $key, true)); }
function is_https(): bool { return ($_SERVER['HTTPS'] ?? '') === 'on'; }
function read_json(string $f): array {
    $r = @file_get_contents($f);
    $j = $r === false ? null : json_decode($r, true);
    return is_array($j) ? $j : [];
}
function write_json(string $f, array $d, int $mode): void {
    $tmp = $f . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Không ghi được ' . basename($f));
    }
    chmod($tmp, $mode);
    if (!rename($tmp, $f)) { @unlink($tmp); throw new RuntimeException('Không ghi được ' . basename($f)); }
}
function char_count(string $s): int { return (int)preg_match_all('/./us', $s); }
function password_problem(string $p1, string $p2): string {
    if (char_count($p1) < MIN_PASSWORD) return 'Mật khẩu cần ít nhất ' . MIN_PASSWORD . ' ký tự.';
    if (strlen($p1) > 72) return 'Mật khẩu quá dài (tối đa khoảng 70 ký tự).';
    if ($p1 !== $p2) return 'Hai lần nhập mật khẩu không khớp.';
    return '';
}
function redirect(string $ok = '', string $p = ''): void {
    $q = [];
    if ($p !== '') $q[] = 'p=' . $p;
    if ($ok !== '') $q[] = 'ok=' . rawurlencode($ok);
    header('Location: /quan-tri/' . ($q ? '?' . implode('&', $q) : ''), true, 303);
    exit;
}

/* ---------- Chống dò mật khẩu: đếm lần sai theo IP (lưu băm IP) và tổng ---------- */
function throttle(string $op): int {   // $op: check | fail | reset → trả về số giây còn bị khoá
    global $DIR;
    $h = @fopen("$DIR/admin-throttle.json", 'c+');
    if (!$h) return 0;
    flock($h, LOCK_EX);
    $t = json_decode((string)stream_get_contents($h), true);
    if (!is_array($t)) $t = [];
    $now = time();
    foreach ($t as $k => $e) {
        if (!is_array($e) || (($e['until'] ?? 0) < $now && ($e['first'] ?? 0) < $now - 3600)) unset($t[$k]);
    }
    $ip = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '-') . '|vfwild-admin'), 0, 16);
    $blank = ['n' => 0, 'first' => $now, 'until' => 0];
    $e = $t[$ip] ?? $blank;
    $g = $t['*'] ?? $blank;
    if ($op === 'fail') {
        if ($now - $e['first'] > 900) $e = $blank;
        if (++$e['n'] >= MAX_FAILS) $e['until'] = $now + 900;
        if ($now - $g['first'] > 3600) $g = $blank;
        if (++$g['n'] >= GLOBAL_FAILS) $g['until'] = $now + 1800;
        $t[$ip] = $e;
        $t['*'] = $g;
    } elseif ($op === 'reset') {
        unset($t[$ip]);
    }
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode($t));
    fflush($h);
    flock($h, LOCK_UN);
    fclose($h);
    return $op === 'reset' ? 0 : max(0, $e['until'] - $now, $g['until'] - $now);
}
function locked_message(int $wait): string {
    return 'Nhập sai nhiều lần nên tạm khoá. Thử lại sau ' . max(1, (int)ceil($wait / 60)) . ' phút.';
}

/* ---------- Phiên đăng nhập: cookie ký HMAC, không lưu phiên trên máy chủ ---------- */
function session_get(array $A): ?array {
    $c = $_COOKIE[COOKIE_NAME] ?? '';
    if (!is_string($c) || empty($A['secret']) || empty($A['password_hash'])
        || !preg_match('/^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/', $c, $m)) return null;
    if (!hash_equals(sig($m[1], $A['secret']), $m[2])) return null;
    $d = json_decode((string)base64_decode(strtr($m[1], '-_', '+/')), true);
    if (!is_array($d) || !isset($d['exp'], $d['n']) || $d['exp'] < time()) return null;
    return $d;
}
function session_issue(array $A, ?string $nonce = null, string $uid = 'owner', string $pv = ''): array {
    $d = ['exp' => time() + SESSION_TTL, 'n' => $nonce ?? b64u(random_bytes(12)), 'u' => $uid, 'pv' => $pv];
    $p = b64u(json_encode($d));
    $v = $p . '.' . sig($p, $A['secret']);
    setcookie(COOKIE_NAME, $v, ['expires' => $d['exp'], 'path' => '/quan-tri/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Strict']);
    $_COOKIE[COOKIE_NAME] = $v;
    return $d;
}
function session_clear(): void {
    setcookie(COOKIE_NAME, '', ['expires' => time() - 3600, 'path' => '/quan-tri/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Strict']);
}
function csrf_token(array $A, array $S): string { return sig('csrf|' . $S['n'], $A['secret']); }

/* ---------- Mã đo lường: nhận cả khi dán nguyên đoạn mã, tự lấy đúng ID ---------- */
function extract_ids(array $in, array &$errors): array {
    $out = [];
    $raw = function (string $k) use ($in): string { return trim(str_replace(["\u{00A0}", "\t"], ' ', (string)($in[$k] ?? ''))); };
    $v = $raw('gtm_id');
    $out['gtm_id'] = $v === '' ? '' : (preg_match('/\bGTM-[A-Z0-9]{4,12}\b/i', $v, $m) ? strtoupper($m[0]) : null);
    $v = $raw('ga4_id');
    $out['ga4_id'] = $v === '' ? '' : (preg_match('/\bG-[A-Z0-9]{4,16}\b/i', $v, $m) ? strtoupper($m[0]) : null);
    // Google Ads: nhận "AW-123", "AW-123/nhãn" hoặc nguyên đoạn mã, dán vào ô nào cũng tự tách đúng
    $idRaw = $raw('gads_id');
    $labelRaw = $raw('gads_lead_label');
    $adsId = null;
    $adsLabel = null;
    foreach ([$idRaw, $labelRaw] as $src) {
        if (preg_match('/\bAW-([0-9]{6,15})(?:\/([A-Za-z0-9_-]{4,64}))?/i', $src, $m)) {
            $adsId = $adsId ?? 'AW-' . $m[1];
            if (!empty($m[2])) $adsLabel = $adsLabel ?? $m[2];
        }
    }
    $out['gads_id'] = $idRaw === '' ? ($adsId ?? '') : $adsId;
    if ($adsLabel === null) $adsLabel = trim($labelRaw, " '\"");
    $out['gads_lead_label'] = $adsLabel === '' ? ''
        : (preg_match(FIELDS['gads_lead_label'][0], $adsLabel) && stripos($adsLabel, 'AW-') !== 0 ? $adsLabel : null);
    $v = preg_replace('/\s+/', '', $raw('meta_pixel_id'));
    $out['meta_pixel_id'] = $v === '' ? '' : (preg_match('/[0-9]{10,20}/', $v, $m) ? $m[0] : null);

    foreach (FIELDS as $k => $f) {
        if ($out[$k] === null) {
            $errors[] = $f[1] . ' chưa đúng dạng (ví dụ ' . $f[2] . ').';
            $out[$k] = $raw($k);
        } elseif ($out[$k] !== '' && !preg_match($f[0], $out[$k])) {
            $errors[] = $f[1] . ' chưa đúng dạng (ví dụ ' . $f[2] . ').';
        }
    }
    if (!$errors && ($out['gads_id'] === '') !== ($out['gads_lead_label'] === '')) {
        $errors[] = 'Google Ads cần đủ cả Conversion ID và Conversion label (hoặc để trống cả hai).';
    }
    return $out;
}
function history(int $n = 12): array {
    global $DIR;
    $f = "$DIR/tracking-history.jsonl";
    if (!is_file($f)) return [];
    $out = [];
    foreach (array_slice(file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -$n) as $l) {
        $j = json_decode($l, true);
        if (is_array($j)) $out[] = $j;
    }
    return array_reverse($out);
}

/* ---------- Dòng lệnh: cấp link đặt mật khẩu ---------- */
if (PHP_SAPI === 'cli') {
    if (($argv[1] ?? '') === '--rebuild-blog') {   // tạo lại toàn bộ trang blog từ các bài đã lưu
        $r = blog_rebuild($DIR);
        echo 'Đã tạo ' . $r['posts'] . " bài đã đăng.\n";
        exit(0);
    }
    if (($argv[1] ?? '') === '--auto-run') {   // cron: mỗi lần viết tối đa 1 bài nếu đến giờ (--force: bỏ qua lịch)
        echo auto_run($DIR, in_array('--force', $argv, true)) . "\n";
        exit(0);
    }
    if (($argv[1] ?? '') !== '--setup-link') { fwrite(STDERR, "Dùng: php index.php --setup-link | --rebuild-blog | --auto-run [--force]\n"); exit(1); }
    $A = read_json("$DIR/admin.json");
    $token = b64u(random_bytes(24));
    $A['setup_hash'] = hash('sha256', $token);
    $A['setup_exp'] = time() + SETUP_TTL;
    if (empty($A['secret'])) $A['secret'] = bin2hex(random_bytes(32));
    write_json("$DIR/admin.json", $A, 0600);
    echo SITE_URL . "/quan-tri/#k=$token\n";
    echo 'Link dùng 1 lần, hết hạn ' . date('H:i d/m/Y', $A['setup_exp']) . "\n";
    exit(0);
}

/* ---------- Web ---------- */
$NONCE = b64u(random_bytes(16));
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; img-src 'self' data: blob:; connect-src 'self'; style-src 'nonce-$NONCE'; script-src 'nonce-$NONCE'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$A = read_json("$DIR/admin.json");
$S = session_get($A);
$ME = me_resolve($DIR, $A, $S);   // người đang đăng nhập (null nếu tài khoản đã bị khóa hoặc đổi mật khẩu)
if ($S && !$ME) $S = null;
$GLOBALS['VFW_ME'] = $ME;
$error = '';
$form = null;   // giữ lại dữ liệu vừa nhập khi có lỗi
$blogForm = null;   // bài viết đang soạn (khi xem trước hoặc có lỗi khi lưu)
$flashMap = blog_flash() + auto_flash() + [
    'saved' => 'Đã lưu. Web áp dụng mã mới trong khoảng 1 phút (khách đang mở trang sẽ nhận ở lần tải sau).',
    'nochange' => 'Không có thay đổi nào.',
    'setup' => 'Đã đặt mật khẩu. Bạn đang đăng nhập.',
    'password' => 'Đã đổi mật khẩu. Các thiết bị khác đã bị đăng xuất.',
    'out' => 'Đã đăng xuất.', 'user_ok' => 'Đã cập nhật người dùng.', 'rv_ok' => 'Đã ghi nhận: bài không cần sửa.', 'rv_edit' => 'Đã ghi nhận chỉnh sửa và lý do. Cảm ơn bạn!', 'rv_rule' => 'Đã cập nhật sổ tay biên tập.',
];
$flash = $flashMap[(string)($_GET['ok'] ?? '')] ?? '';
if (($_GET['ok'] ?? '') === 'audit_links') $flash = (int)($_GET['n'] ?? 0) > 0 ? 'Đã gắn ' . (int)$_GET['n'] . ' liên kết nội bộ mới giữa các bài (bản cũ được lưu trong lịch sử phiên bản).' : 'Không có liên kết nào cần thêm: các bài đã liên kết đủ hoặc chưa tìm thấy cụm từ phù hợp.';
if (($_GET['ok'] ?? '') === 'audit_gsc') $flash = 'Đã cập nhật dữ liệu Search Console (' . (int)($_GET['n'] ?? 0) . ' trang có số liệu).';
if (($_GET['ok'] ?? '') === 'audit_kw') $flash = (int)($_GET['n'] ?? 0) > 0 ? 'Đã thêm ' . (int)$_GET['n'] . ' từ khóa vào Sheet với trạng thái “Ý tưởng”.' : 'Không thêm từ khóa nào (đã có trong Sheet hoặc không hợp lệ).';
if (($_GET['ok'] ?? '') === 'auto_keyok') $flash = 'Kết nối ' . (string)($_GET['b'] ?? '') . ' thành công – khóa và model dùng được.';
if (($_GET['ok'] ?? '') === 'auto_models') $flash = 'Đã tải ' . (int)($_GET['n'] ?? 0) . ' model của ' . (string)($_GET['b'] ?? '') . ' – chọn trong ô Model (bấm vào ô để xổ danh sách).';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $act = (string)($_POST['action'] ?? '');
        if ($act === 'login') {
            $wait = throttle('check');
            $lu = users_login_candidate($DIR, $A, (string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
            if ($wait > 0) $error = locked_message($wait);
            elseif (empty($A['password_hash'])) $error = 'Trang quản trị chưa được kích hoạt.';
            elseif ($lu) {
                throttle('reset');
                users_touch_login($DIR, (string)$lu['id']);
                session_issue($A, null, (string)$lu['id'], users_pv($lu));
                redirect();
            } elseif ((function () use ($A): bool {
                $userOk = empty($A['username']) || hash_equals(mb_strtolower((string)$A['username']), mb_strtolower(trim((string)($_POST['username'] ?? ''))));
                $passOk = password_verify((string)($_POST['password'] ?? ''), $A['password_hash']);
                return $userOk && $passOk;
            })()) {
                throttle('reset');
                if (password_needs_rehash($A['password_hash'], PASSWORD_DEFAULT)) {
                    $A['password_hash'] = password_hash((string)$_POST['password'], PASSWORD_DEFAULT);
                    write_json("$DIR/admin.json", $A, 0600);
                }
                session_issue($A);
                redirect();
            } else {
                $wait = throttle('fail');
                usleep(400000);
                $error = $wait > 0 ? locked_message($wait) : (empty($A['username']) ? 'Mật khẩu không đúng.' : 'Email hoặc mật khẩu không đúng.');
            }
        } elseif ($act === 'setup') {
            $wait = throttle('check');
            $tok = (string)($_POST['k'] ?? '');
            $p1 = (string)($_POST['password'] ?? '');
            $p2 = (string)($_POST['password2'] ?? '');
            if ($wait > 0) $error = locked_message($wait);
            elseif (empty($A['setup_hash']) || ($A['setup_exp'] ?? 0) < time() || !hash_equals($A['setup_hash'], hash('sha256', $tok))) {
                throttle('fail');
                $error = 'Link đặt mật khẩu không đúng, đã dùng hoặc đã hết hạn. Liên hệ kỹ thuật để lấy link mới.';
            } elseif (($u = trim((string)($_POST['username'] ?? ''))) !== '' && !filter_var($u, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email đăng nhập chưa đúng dạng.';
            } elseif (($p = password_problem($p1, $p2)) !== '') {
                $error = $p;
            } else {
                if ($u !== '') $A['username'] = mb_strtolower($u);
                $A['password_hash'] = password_hash($p1, PASSWORD_DEFAULT);
                $A['password_set_at'] = date('c');
                $A['secret'] = bin2hex(random_bytes(32));   // mọi phiên cũ hết hiệu lực
                unset($A['setup_hash'], $A['setup_exp']);
                write_json("$DIR/admin.json", $A, 0600);
                throttle('reset');
                session_issue($A);
                redirect('setup');
            }
        } else {
            // Các thao tác còn lại cần đăng nhập và mã chống giả mạo (CSRF)
            if (!$S || !hash_equals(csrf_token($A, $S), (string)($_POST['csrf'] ?? ''))) {
                $error = 'Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại.';
                $S = null;
            } elseif (!role_can((string)($ME['role'] ?? ''), action_cap($act))) {
                $error = 'Bạn không có quyền thực hiện thao tác này.';
            } elseif (strncmp($act, 'blog_', 5) === 0) {
                $r = blog_post($act, $DIR);
                if (!empty($r['to'])) { header('Location: ' . $r['to'], true, 303); exit; }
                $error = (string)($r['error'] ?? '');
                if (isset($r['form'])) $blogForm = $r;
            } elseif ($act === 'save') {
                $errors = [];
                $new = extract_ids($_POST, $errors);
                if ($errors) {
                    $error = implode(' ', $errors);
                    $form = $new;
                } else {
                    $old = read_json("$DIR/tracking.json");
                    $changes = [];
                    foreach (array_keys(FIELDS) as $k) {
                        if ((string)($old[$k] ?? '') !== $new[$k]) $changes[$k] = [(string)($old[$k] ?? ''), $new[$k]];
                    }
                    if (!$changes) redirect('nochange', 'tracking');
                    write_json("$DIR/tracking.json", $new + ['updated_at' => date('c')], 0640);
                    $ip = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '-') . '|vfwild-admin'), 0, 10);
                    file_put_contents("$DIR/tracking-history.jsonl", json_encode(['t' => date('c'), 'ip' => $ip, 'changes' => $changes], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
                    redirect('saved', 'tracking');
                }
            } elseif ($act === 'password' && empty($ME['owner'])) {
                $wait = throttle('check');
                $users = users_all($DIR); $ix = null;
                foreach ($users as $i => $u) { if (($u['id'] ?? '') === $ME['id']) $ix = $i; }
                if ($wait > 0) $error = locked_message($wait);
                elseif ($ix === null || !password_verify((string)($_POST['current'] ?? ''), (string)$users[$ix]['hash'])) { throttle('fail'); $error = 'Mật khẩu hiện tại không đúng.'; }
                elseif (($p = password_problem((string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? ''))) !== '') $error = $p;
                else {
                    $users[$ix]['hash'] = password_hash((string)$_POST['password'], PASSWORD_DEFAULT);
                    users_write($DIR, $users);
                    session_issue($A, null, (string)$ME['id'], users_pv($users[$ix]));
                    redirect('password', 'account');
                }
            } elseif ($act === 'password') {
                $wait = throttle('check');
                if ($wait > 0) $error = locked_message($wait);
                elseif (!password_verify((string)($_POST['current'] ?? ''), $A['password_hash'])) {
                    throttle('fail');
                    $error = 'Mật khẩu hiện tại không đúng.';
                } elseif (($p = password_problem((string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? ''))) !== '') {
                    $error = $p;
                } else {
                    $A['password_hash'] = password_hash((string)$_POST['password'], PASSWORD_DEFAULT);
                    $A['password_set_at'] = date('c');
                    $A['secret'] = bin2hex(random_bytes(32));
                    write_json("$DIR/admin.json", $A, 0600);
                    session_issue($A);
                    redirect('password', 'account');
                }
            } elseif ($act === 'logout') {
                session_clear();
                redirect('out');
            }
        }
    } elseif ($S && $S['exp'] - time() < SESSION_TTL / 2) {
        $S = session_issue($A, $S['n'], (string)($S['u'] ?? 'owner'), (string)($S['pv'] ?? ''));   // đang dùng thì gia hạn phiên
    }
} catch (Throwable $e) {
    error_log('vfwild admin: ' . $e->getMessage());
    $error = 'Có lỗi khi lưu dữ liệu trên máy chủ. Vui lòng thử lại hoặc báo kỹ thuật.';
}

/* ---------- Giao diện ---------- */
$T = read_json("$DIR/tracking.json");
$V = $form ?? $T;
$hasPassword = !empty($A['password_hash']);
$pq = (string)($_GET['p'] ?? '');
$postAct = (string)($_POST['action'] ?? '');
if ($blogForm !== null) $view = 'blog';
elseif ($form !== null || $postAct === 'save') $view = 'tracking';
elseif ($postAct === 'password') $view = 'account';
elseif (strncmp($postAct, 'blog_auto_', 10) === 0) $view = 'auto';
elseif (strncmp($postAct, 'blog_audit_', 11) === 0) $view = 'audit';
elseif (strncmp($postAct, 'blog_review_', 12) === 0) $view = 'review';
elseif (strncmp($postAct, 'blog_user_', 10) === 0) $view = 'users';
else $view = in_array($pq, ['tracking', 'account', 'media', 'cats', 'auto', 'audit', 'review', 'users'], true) ? $pq : 'blog';
$titles = ['blog' => 'Bài viết', 'media' => 'Thư viện ảnh', 'cats' => 'Chuyên mục', 'auto' => 'Tự động viết bài', 'audit' => 'Kiểm tra nội dung', 'review' => 'Review và học', 'users' => 'Người dùng', 'tracking' => 'Mã đo lường', 'account' => 'Tài khoản'];
$editing = $view === 'blog' && ($blogForm !== null || isset($_GET['edit']));
$denied = $S && $ME && (!role_can($ME['role'], view_cap($view)) || ($editing && !role_can($ME['role'], 'blog.write')));
$can = fn(string $cap): bool => (bool)($ME && role_can($ME['role'], $cap));

function field(string $k, array $V, string $help): string {
    $f = FIELDS[$k];
    return '<label class="f"><span>' . h($f[1]) . '</span><input name="' . h($k) . '" value="' . h($V[$k] ?? '') . '" placeholder="' . h($f[2]) . '" autocomplete="off" spellcheck="false" autocapitalize="off"><small>' . $help . '</small></label>';
}
function chip(string $label, string $value): string {
    return '<span class="chip ' . ($value !== '' ? 'on' : 'off') . '"><b>' . h($label) . '</b> ' . ($value !== '' ? h($value) : 'chưa cài') . '</span>';
}
function pwfield(string $label, string $name, string $auto, bool $min = false, bool $autofocus = false): string {
    return '<label class="f"><span>' . h($label) . '</span><span class="pw"><input type="password" name="' . h($name) . '" autocomplete="' . h($auto) . '"'
        . ($min ? ' minlength="' . MIN_PASSWORD . '"' : '') . ' required' . ($autofocus ? ' autofocus' : '') . '><button type="button" class="lnk" data-pw>Hiện</button></span></label>';
}

if ($S && $view === 'blog' && $_SERVER['REQUEST_METHOD'] === 'GET') { try { blog_rebuild_if_due($DIR); } catch (Throwable $e) { error_log('vfwild admin: ' . $e->getMessage()); } }

ob_start();
if (!$S) { ?>
  <div class="authwrap"><section class="card auth">
    <div class="auth__logo"><img src="/img/logo-chungauto.jpg" alt="ChungAuto"></div>
    <?php if ($hasPassword) { ?>
      <form method="post" id="login" autocomplete="on">
        <h2>Đăng nhập quản trị</h2>
        <input type="hidden" name="action" value="login">
        <?php if (!empty($A['username']) || users_all($DIR)) { ?>
          <label class="f"><span>Email đăng nhập</span><input type="email" name="username" autocomplete="username" required autofocus></label>
          <?= pwfield('Mật khẩu', 'password', 'current-password') ?>
        <?php } else { ?>
          <input type="text" name="username" value="quan-tri" autocomplete="username" hidden>
          <?= pwfield('Mật khẩu', 'password', 'current-password', false, true) ?>
        <?php } ?>
        <button class="btn block" type="submit">Đăng nhập</button>
        <p class="muted small">Quên mật khẩu? Báo kỹ thuật để nhận link đặt lại.</p>
      </form>
    <?php } else { ?>
      <div id="inactive">
        <h2>Trang quản trị chưa kích hoạt</h2>
        <p class="muted">Mở link kích hoạt được gửi cho bạn để tạo tài khoản.</p>
      </div>
    <?php } ?>
    <form method="post" id="setup" hidden>
      <h2><?= $hasPassword ? 'Đặt lại mật khẩu' : 'Tạo tài khoản quản trị' ?></h2>
      <input type="hidden" name="action" value="setup">
      <input type="hidden" name="k" value="<?= h($_POST['k'] ?? '') ?>">
      <label class="f"><span>Email đăng nhập</span><input type="email" name="username" autocomplete="username" value="<?= h((string)($_POST['username'] ?? ($A['username'] ?? ''))) ?>" required></label>
      <?= pwfield('Mật khẩu mới (ít nhất ' . MIN_PASSWORD . ' ký tự)', 'password', 'new-password', true) ?>
      <?= pwfield('Nhập lại mật khẩu mới', 'password2', 'new-password', true) ?>
      <button class="btn block" type="submit">Lưu tài khoản và vào trang quản trị</button>
      <p class="muted small">Link chỉ dùng được 1 lần. Hãy lưu mật khẩu vào trình quản lý mật khẩu của bạn.</p>
    </form>
  </section></div>

<?php } else {
    $csrf = h(csrf_token($A, $S));
    if ($denied) { echo '<div class="page"><div class="pagehead"><h1>Không có quyền</h1></div><section class="card"><p>Vai trò <b>' . h(ROLES[$ME['role']]) . '</b> của bạn không được mở mục này. Nếu cần, hãy nhờ Quản trị cấp quyền.</p></section></div>'; }
    elseif ($view === 'blog') { blog_view($DIR, $csrf, $NONCE, $blogForm); }
    elseif ($view === 'media') { blog_view_media($DIR, $csrf, $NONCE); }
    elseif ($view === 'cats') { blog_view_cats($DIR, $csrf); }
    elseif ($view === 'auto') { blog_view_auto($DIR, $csrf); }
    elseif ($view === 'audit') { blog_view_audit($DIR, $csrf); }
    elseif ($view === 'review') { blog_view_review($DIR, $csrf, $ME); }
    elseif ($view === 'users') { blog_view_users($DIR, $csrf, $A, $ME); }
    elseif ($view === 'account') { ?>
  <div class="page">
    <div class="pagehead"><h1>Tài khoản</h1></div>
    <section class="card narrow2">
      <h2>Thông tin đăng nhập</h2>
      <p class="muted">Đăng nhập bằng: <b><?= h($ME['owner'] ? (string)($A['username'] ?? 'chỉ dùng mật khẩu') : $ME['email']) ?></b> (vai trò <?= h(ROLES[$ME['role']]) ?>). Phiên đăng nhập tự hết sau 8 giờ không dùng.</p>
    </section>
    <form method="post" class="card narrow2" id="pw">
      <h2>Đổi mật khẩu</h2>
      <input type="hidden" name="action" value="password">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="text" name="username" value="<?= h($ME['owner'] ? (string)($A['username'] ?? 'quan-tri') : $ME['email']) ?>" autocomplete="username" aria-label="Tên đăng nhập" hidden>
      <?= pwfield('Mật khẩu hiện tại', 'current', 'current-password') ?>
      <?= pwfield('Mật khẩu mới (ít nhất ' . MIN_PASSWORD . ' ký tự)', 'password', 'new-password', true) ?>
      <?= pwfield('Nhập lại mật khẩu mới', 'password2', 'new-password', true) ?>
      <button class="btn" type="submit">Đổi mật khẩu</button>
      <p class="muted small">Đổi mật khẩu sẽ đăng xuất các thiết bị khác.</p>
    </form>
  </div>
<?php } else { ?>
  <div class="page">
    <div class="pagehead"><h1>Mã đo lường</h1></div>
  <section class="card">
    <div class="rowhead"><div><h2>Mã đo lường</h2><p class="muted small">Cài GTM, GA4, Google Ads, Meta Pixel cho web mà không cần sửa code.</p></div></div>
    <div class="chips">
      <?= chip('GTM', (string)($T['gtm_id'] ?? '')) ?>
      <?= chip('GA4', (string)($T['ga4_id'] ?? '')) ?>
      <?= chip('Google Ads', ($T['gads_id'] ?? '') !== '' ? $T['gads_id'] . ' / ' . ($T['gads_lead_label'] ?? '') : '') ?>
      <?= chip('Meta Pixel', (string)($T['meta_pixel_id'] ?? '')) ?>
    </div>
    <p class="muted small">Cập nhật lần cuối: <?= !empty($T['updated_at']) ? h(date('H:i d/m/Y', strtotime((string)$T['updated_at']))) : '—' ?></p>
    <p class="note">Các mã chỉ chạy <b>sau khi khách bấm “Đồng ý”</b> ở hộp cookie (đúng luật bảo vệ dữ liệu cá nhân). Để trống tất cả thì web không tải mã nào và không hiện hộp cookie.</p>
  </section>

  <form method="post" class="card" id="codes">
    <h2>Cập nhật mã</h2>
    <p class="note"><b>Mỗi công cụ chỉ cài ở một nơi</b> – hoặc điền ở đây, hoặc cài trong GTM – để không bị đếm đôi. Có thể dán nguyên đoạn mã, trang tự lấy đúng ID. Để trống một ô = tắt mã đó.</p>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <fieldset>
      <legend>Google Tag Manager <em>khuyên dùng</em></legend>
      <?= field('gtm_id', $V, 'Trong GTM: góc trên bên phải, mã dạng GTM-XXXXXXX. Có GTM thì nên cài GA4, Google Ads, TikTok, Zalo… ngay trong GTM.') ?>
    </fieldset>
    <fieldset>
      <legend>Google Analytics 4 <em>cài trực tiếp</em></legend>
      <?= field('ga4_id', $V, 'Chỉ điền nếu <b>không</b> cài GA4 trong GTM. Web tự gửi mọi sự kiện sang GA4.') ?>
    </fieldset>
    <fieldset>
      <legend>Google Ads – chuyển đổi đăng ký <em>cài trực tiếp</em></legend>
      <div class="two">
        <?= field('gads_id', $V, 'Google Ads → Mục tiêu → Chuyển đổi → chọn chuyển đổi → Thiết lập thẻ → “Tự cài đặt”.') ?>
        <?= field('gads_lead_label', $V, 'Phần sau dấu “/” trong <code>send_to: \'AW-…/nhãn\'</code>.') ?>
      </div>
      <small class="hint">Chỉ điền nếu <b>không</b> làm chuyển đổi Google Ads trong GTM. Web tự ghi 1 chuyển đổi mỗi khi khách gửi form thành công (dùng mã lead làm Transaction ID nên không đếm trùng).</small>
    </fieldset>
    <fieldset>
      <legend>Meta Pixel (Facebook)</legend>
      <?= field('meta_pixel_id', $V, 'Web tự gửi PageView và CompleteRegistration (khi gửi form thành công). Nếu chuyển Meta Pixel vào GTM thì xóa ô này.') ?>
    </fieldset>
    <button class="btn" type="submit">Lưu thay đổi</button>
  </form>

  <details class="card guide">
    <summary>Hướng dẫn: các sự kiện có sẵn trên web (dùng làm trình kích hoạt trong GTM)</summary>
    <p class="muted small">Web đẩy các sự kiện này vào <code>dataLayer</code>. Trong GTM tạo trình kích hoạt “Sự kiện tùy chỉnh” với đúng tên sự kiện. Không sự kiện nào chứa họ tên hay số điện thoại.</p>
    <div class="tbl"><table>
      <tr><th>Tên sự kiện</th><th>Khi nào</th><th>Tham số</th></tr>
      <tr class="key"><td><code>generate_lead</code></td><td><b>Khách gửi form thành công</b> (popup, form cuối trang, form ở các trang con) – dùng làm chuyển đổi chính</td><td>lead_type, lead_ref, event_id, currency</td></tr>
      <tr><td><code>form_submit_quote</code> · <code>form_submit_test_drive</code> · <code>form_submit_finance</code> · <code>form_submit_o2o</code> · <code>form_submit_offer</code> · <code>form_submit_consult</code></td><td>Cùng lúc với generate_lead, tách theo nhu cầu: báo giá, lái thử, trả góp, O2O, ưu đãi, tư vấn</td><td>form_id, lead_type, lead_ref, event_id, province_code</td></tr>
      <tr><td><code>form_start</code></td><td>Khách bắt đầu điền form</td><td>form_id, intent</td></tr>
      <tr><td><code>click_call</code> · <code>click_zalo</code></td><td>Bấm gọi hotline / chat Zalo</td><td>placement</td></tr>
      <tr><td><code>click_buy_now</code></td><td>Bấm “Cọc Tiên Phong” ở thanh dưới màn hình</td><td>placement</td></tr>
      <tr><td><code>popup_view</code> · <code>popup_close</code></td><td>Popup đăng ký hiện ra / bị đóng. popup_id cho biết từ đâu: <code>bao-gia-3-phut</code> (tự bật), <code>dau-trang</code>, <code>sau-bang-gia</code>, <code>sau-so-sanh</code> (bấm nút Nhận báo giá), <code>mua-ngay</code> (thanh dưới)</td><td>popup_id</td></tr>
      <tr><td><code>quicknav_click</code></td><td>Bấm mục lục nhanh dưới phần đầu trang</td><td>target</td></tr>
      <tr><td><code>view_offer</code></td><td>Khối ưu đãi hiện trên màn hình</td><td>placement, variant</td></tr>
      <tr><td><code>open_calculator</code></td><td>Mở máy tính lăn bánh / trả góp / chi phí</td><td>calculator</td></tr>
      <tr><td><code>video_play</code> · <code>gallery_open</code> · <code>compare_select</code></td><td>Xem video · mở ảnh cỡ lớn · chọn xe để so sánh</td><td>video_id · photo · model</td></tr>
    </table></div>
  </details>

  <details class="card guide">
    <summary>Hướng dẫn: cài chuyển đổi trong GTM (5 bước)</summary>
    <?php if (($T['ga4_id'] ?? '') !== '') { ?><p class="note">GA4 <b><?= h($T['ga4_id']) ?></b> đang cài trực tiếp ở trang này (web tự gửi lượt xem trang và mọi sự kiện). Trong GTM <b>không tạo thêm</b> “Thẻ Google”/“Sự kiện GA4” cho mã này để tránh đếm đôi – chỉ cần làm chuyển đổi Google Ads (bước 1, 2, 4, 5).</p><?php } ?>
    <ol class="steps">
      <li><b>Biến:</b> Biến → Biến do người dùng xác định → Mới → “Biến lớp dữ liệu”: tạo 3 biến <code>lead_ref</code>, <code>lead_type</code>, <code>event_id</code>.</li>
      <li><b>Trình kích hoạt:</b> Mới → “Sự kiện tùy chỉnh”, tên sự kiện <code>generate_lead</code>.</li>
      <li><b>Thẻ Google:</b> “Thẻ Google” với mã GA4 (G-…), kích hoạt “Initialization – All Pages”; thêm thẻ “Trình liên kết chuyển đổi” (Conversion Linker) cho All Pages.</li>
      <li><b>Thẻ chuyển đổi:</b> “Theo dõi lượt chuyển đổi Google Ads” – nhập Conversion ID + Label, Transaction ID = <code>{{lead_ref}}</code>, kích hoạt <code>generate_lead</code>. Thêm “Sự kiện GA4” tên <code>generate_lead</code> (thông số lead_type = <code>{{lead_type}}</code>), rồi trong GA4 đánh dấu generate_lead là <i>Sự kiện chính</i>.</li>
      <li><b>Xuất bản:</b> bấm “Gửi” → “Xuất bản”. Nếu đã điền Meta Pixel ở trang này thì <b>không</b> tạo thêm thẻ Meta trong GTM.</li>
    </ol>
    <h3>Kiểm tra sau khi cài</h3>
    <ol class="steps">
      <li>Mở <a href="https://tagassistant.google.com/" target="_blank" rel="noopener">tagassistant.google.com</a> → nhập <code><?= h(SITE_URL) ?></code> → Connect.</li>
      <li>Ở cửa sổ web vừa mở, bấm <b>“Đồng ý”</b> trên hộp cookie (GTM chỉ chạy sau khi đồng ý).</li>
      <li>Gửi 1 form thử (tên ghi “TEST”) → trong Tag Assistant thấy <code>generate_lead</code> và các thẻ đã kích hoạt. Meta: Trình quản lý sự kiện → Kiểm tra sự kiện. Lead thử vẫn về Telegram – báo nhóm bỏ qua.</li>
    </ol>
    <p class="note warn"><b>Lưu ý pháp lý:</b> chính sách bảo mật của web đang cam kết <b>không gửi họ tên, số điện thoại cho Google/Meta</b>. Vì vậy không bật “Chuyển đổi nâng cao” (Google Ads) hay “Đối sánh nâng cao tự động” (Meta) nếu chúng tự lấy SĐT/email từ form. Muốn bật thì báo kỹ thuật cập nhật chính sách và ô đồng ý trước. Thêm công cụ mới trong GTM (TikTok, Zalo…) cũng cần báo để bổ sung tên vào chính sách bảo mật.</p>
  </details>

  <?php $hist = history(); if ($hist) { ?>
  <section class="card">
    <h2>Lịch sử thay đổi</h2>
    <div class="tbl"><table>
      <tr><th>Thời gian</th><th>Thay đổi</th></tr>
      <?php foreach ($hist as $r) { ?>
        <tr><td class="nowrap"><?= h(date('H:i d/m/Y', strtotime((string)($r['t'] ?? '')))) ?></td><td>
          <?php foreach ((array)($r['changes'] ?? []) as $k => $c) { ?>
            <div><?= h(FIELDS[$k][1] ?? $k) ?>: <s><?= h($c[0] !== '' ? $c[0] : 'trống') ?></s> → <b><?= h($c[1] !== '' ? $c[1] : 'trống') ?></b></div>
          <?php } ?>
        </td></tr>
      <?php } ?>
    </table></div>
  </section>
  <?php } ?>

  </div>
<?php } }
$body = ob_get_clean();
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $S ? h($titles[$view]) . ' ‹ ' : '' ?>Quản trị vfwild.vn</title>
<style nonce="<?= $NONCE ?>"><?= file_get_contents(__DIR__ . '/admin.css') ?></style>
</head>
<body>
<?php if ($S) { ?>
<header class="adminbar">
  <button type="button" class="burger" id="burger" aria-label="Mở menu" aria-expanded="false" aria-controls="side">☰</button>
  <a class="brand" href="/quan-tri/"><img src="/img/logo-chungauto.jpg" alt="ChungAuto"><span class="hide-m">Quản trị vfwild.vn</span></a>
  <?php if ($can('blog.write')) { ?><a class="hide-m" href="/quan-tri/?p=blog&amp;edit=">+ Viết bài mới</a><?php } ?>
  <span class="grow"></span>
  <?php if ($ME && $ME['email'] !== '') { ?><span class="who" title="Tài khoản đang đăng nhập"><?= h($ME['email']) ?> · <?= h(ROLES[$ME['role']]) ?></span><?php } ?>
  <a href="/" target="_blank" rel="noopener">Xem website ↗</a>
  <form method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="<?= h(csrf_token($A, $S)) ?>"><button type="submit">Đăng xuất</button></form>
</header>
<nav class="side" id="side" aria-label="Menu quản trị">
  <a href="/quan-tri/?p=blog"<?= in_array($view, ['blog', 'cats'], true) ? ' class="on"' : '' ?>><i>✎</i> Bài viết</a>
  <?php if (in_array($view, ['blog', 'cats'], true)) { ?><div class="sub">
    <a href="/quan-tri/?p=blog"<?= $view === 'blog' && !$editing ? ' class="on"' : '' ?>>Tất cả bài viết</a>
    <?php if ($can('blog.write')) { ?><a href="/quan-tri/?p=blog&amp;edit="<?= $editing ? ' class="on"' : '' ?>>Viết bài mới</a>
    <a href="/quan-tri/?p=cats"<?= $view === 'cats' ? ' class="on"' : '' ?>>Chuyên mục</a><?php } ?>
  </div><?php } ?>
  <?php if ($can('media')) { ?><a href="/quan-tri/?p=media"<?= $view === 'media' ? ' class="on"' : '' ?>><i>▣</i> Thư viện ảnh</a><?php } ?>
  <?php if ($can('audit.read')) { ?><a href="/quan-tri/?p=audit"<?= $view === 'audit' ? ' class="on"' : '' ?>><i>✓</i> Kiểm tra nội dung</a><?php } ?>
  <?php if ($can('review.read')) { ?><a href="/quan-tri/?p=review"<?= $view === 'review' ? ' class="on"' : '' ?>><i>☑</i> Review và học<?php $pend = review_pending_count($DIR); if ($pend > 0) { ?> <span class="badge"><?= $pend ?></span><?php } ?></a><?php } ?>
  <?php if ($can('auto')) { ?><a href="/quan-tri/?p=auto"<?= $view === 'auto' ? ' class="on"' : '' ?>><i>⚙</i> Tự động viết bài</a><?php } ?>
  <div class="sep"></div>
  <?php if ($can('users')) { ?><a href="/quan-tri/?p=users"<?= $view === 'users' ? ' class="on"' : '' ?>><i>☻</i> Người dùng</a><?php } ?>
  <?php if ($can('tracking')) { ?><a href="/quan-tri/?p=tracking"<?= $view === 'tracking' ? ' class="on"' : '' ?>><i>◎</i> Mã đo lường</a><?php } ?>
  <a href="/quan-tri/?p=account"<?= $view === 'account' ? ' class="on"' : '' ?>><i>☺</i> Tài khoản</a>
  <div class="sep"></div>
  <a href="/" target="_blank" rel="noopener"><i>↗</i> Xem website</a>
</nav>
<?php } ?>
<div class="toasts" role="status" aria-live="polite">
  <?php if ($flash !== '' && $error === '') { ?><div class="toast ok" data-auto><span><?= h($flash) ?></span><button type="button" aria-label="Đóng" data-close>×</button></div><?php } ?>
  <?php if ($error !== '') { ?><div class="toast err" role="alert"><span><?= h($error) ?></span><button type="button" aria-label="Đóng" data-close>×</button></div><?php } ?>
</div>
<div<?= $S ? ' class="wrap"' : '' ?> id="wrap">
<?= $body ?>
</div>
<script nonce="<?= $NONCE ?>">
(function () {
  // thông báo: tự ẩn sau 6 giây (lỗi thì giữ lại), nút đóng
  document.querySelectorAll('.toast').forEach(function (t) {
    t.querySelector('[data-close]').addEventListener('click', function () { t.remove(); });
    if (t.hasAttribute('data-auto')) setTimeout(function () { t.remove(); }, 6000);
  });
  // xác nhận trước thao tác xóa (form hoặc nút)
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) { if (!window.confirm(f.getAttribute('data-confirm'))) ev.preventDefault(); });
  });
  document.querySelectorAll('button[data-confirm]').forEach(function (b) {
    b.addEventListener('click', function (ev) { if (!window.confirm(b.getAttribute('data-confirm'))) ev.preventDefault(); });
  });
  document.querySelectorAll('[data-confirm-if]').forEach(function (b) {
    b.addEventListener('click', function (ev) {
      var sel = b.form && b.form.elements['bulk'], list = b.getAttribute('data-confirm-if').split(',');
      if (sel && list.indexOf(sel.value) !== -1 && !window.confirm('Áp dụng hành động này cho các bài đã chọn?')) ev.preventDefault();
    });
  });
  // chọn tất cả trong danh sách
  document.querySelectorAll('[data-checkall]').forEach(function (c) {
    c.addEventListener('change', function () { document.querySelectorAll('input[name="slugs[]"]').forEach(function (x) { x.checked = c.checked; }); });
  });
  // menu trên điện thoại
  var burger = document.getElementById('burger');
  if (burger) {
    burger.addEventListener('click', function (e) { e.stopPropagation(); var o = document.body.classList.toggle('menu-open'); burger.setAttribute('aria-expanded', o ? 'true' : 'false'); });
    document.getElementById('wrap').addEventListener('click', function () { document.body.classList.remove('menu-open'); burger.setAttribute('aria-expanded', 'false'); });
  }
  // hiện / ẩn mật khẩu
  document.querySelectorAll('[data-pw]').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = b.parentNode.querySelector('input'); var show = i.type === 'password';
      i.type = show ? 'text' : 'password'; b.textContent = show ? 'Ẩn' : 'Hiện';
    });
  });
  // Link đặt mật khẩu có dạng /quan-tri/#k=... (phần sau # không gửi lên máy chủ, không nằm trong log)
  var setup = document.getElementById('setup');
  if (!setup) return;
  var m = location.hash.match(/[#&]k=([A-Za-z0-9_-]{20,})/);
  var k = setup.querySelector('input[name="k"]');
  if (m) { k.value = m[1]; history.replaceState(null, '', location.pathname); }
  if (k.value) {
    setup.hidden = false;
    ['login', 'inactive'].forEach(function (id) { var el = document.getElementById(id); if (el) el.hidden = true; });
    var first = setup.querySelector('input[name="username"]'); if (first) first.focus();
  }
})();
</script>
</body>
</html>

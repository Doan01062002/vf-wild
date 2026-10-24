<?php
/**
 * Ảnh cho bài viết tự động: đọc link ảnh ở cột "Ảnh (link)" của Sheet (Google Drive công khai hoặc link ảnh https), tải về, kiểm tra an toàn,
 * lưu 3 cỡ (800/1200/1600) vào thư viện ảnh của blog, và chèn vào bài ở chỗ AI chọn sau khi xem ảnh.
 * Đặt tên tệp theo mục: "2-pin-sac.jpg" (số đầu tên dính liền dấu - _ . = số thứ tự mục H2, vd "muc2-…", "02_…", "2a-…") thì ảnh được đặt đúng mục H2 số 2;
 * "0-bia.jpg", "00-…", "bia-…", "cover-…" hoặc "banner-…" là ảnh bìa. Ảnh không đặt số thì AI chọn mục như trước; ảnh bìa mặc định là ảnh không số đầu tiên.
 * Biến môi trường VFW_IMG_ALLOW_LOCAL=1 cho phép địa chỉ nội bộ (chỉ dùng khi kiểm thử).
 */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }

const IMG_MAX_BYTES = 8388608;   // 8 MB mỗi ảnh tải về
const IMG_DEFAULT_MAX = 6;       // số ảnh mỗi bài mặc định (chỉnh trong Cài đặt → Nâng cao)
const IMG_HARD_MAX = 30;         // trần cứng để không quá tải bộ nhớ, thời gian và dung lượng gửi cho AI
const IMG_SIZES = [800, 1200, 1600];
const IMG_DRIVE_SCOPE = 'https://www.googleapis.com/auth/drive.readonly';

/**
 * Đọc quy ước đặt tên tệp ảnh. Trả về ['cover' => bool, 'section' => ?int].
 *  - ảnh bìa: tên bắt đầu bằng 0 / 00 ("0-xe.jpg", "00_vf-wild.jpg") hoặc bia / cover / banner / dai-dien ("bia-vf-wild.jpg", "Anh banner.jpg")
 *  - mục H2: số 1–2 chữ số đứng đầu tên và DÍNH LIỀN dấu phân cách - _ . ) hoặc hết tên ("2-pin-sac.jpg", "02_pin.jpg", "2b-pin.jpg" cho ảnh thứ hai cùng mục),
 *    hoặc có chữ muc / phan / h2 đứng trước số ("muc 3 - gia.jpg", "muc3-gia.jpg").
 * Số đi với dấu cách ("3 mau tham.jpg" = ba mẫu), số dài (ngày tháng, IMG_1234, "20261023-…") và "4x4" KHÔNG bị hiểu là số mục.
 */
function img_name_info(string $name): array {
    $raw = mb_strtolower(trim((string)preg_replace('/\.[A-Za-z0-9]{2,5}$/', '', trim($name))), 'UTF-8');
    if ($raw === '') return ['cover' => false, 'section' => null];
    if (preg_match('/^0+(?=[\s._)\-]|$)/u', $raw)) return ['cover' => true, 'section' => null];
    $tok = preg_split('/[\s._)\-]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $t0 = $tok ? blog_slugify($tok[0]) : ''; $t1 = isset($tok[1]) ? blog_slugify($tok[1]) : '';
    $cov = ['bia', 'cover', 'banner'];
    if (in_array($t0, $cov, true) || (in_array($t0, ['anh', 'hinh'], true) && in_array($t1, $cov, true)) || ($t0 === 'dai' && $t1 === 'dien')) return ['cover' => true, 'section' => null];
    if (preg_match('/^0*(\d{1,2})([a-e])?(?=[._)\-]|$)/u', $raw, $m) && (int)$m[1] >= 1) return ['cover' => false, 'section' => (int)$m[1]];
    if (preg_match('/^(\S{1,6}?)[\s._\-]*(\d{1,2})([a-e])?(?=[\s._)\-]|$)/u', $raw, $m) && in_array(blog_slugify($m[1]), ['muc', 'phan', 'h2'], true) && (int)$m[2] >= 1) return ['cover' => false, 'section' => (int)$m[2]];
    return ['cover' => false, 'section' => null];
}
/**
 * Chủ đề gợi ý từ chữ trong tên tệp của ảnh KHÔNG đánh số ("pin-sac-nhanh.jpg" → "pin sac nhanh"). Rỗng nếu tên chỉ là mã máy ảnh / số
 * (IMG_1234, DSC0012, Screenshot…) hoặc không có chữ có nghĩa.
 */
function img_topic(string $name): string {
    $base = preg_replace('/\.[A-Za-z0-9]{2,5}$/', '', trim($name)) ?? '';
    $junk = ['img', 'dsc', 'dscn', 'dscf', 'pxl', 'image', 'photo', 'picture', 'screenshot', 'anh', 'hinh', 'copy', 'edited', 'final', 'new', 'untitled', 'zalo', 'fb', 'jpg', 'jpeg', 'png', 'webp', 'infographic', 'info', 'thumb', 'thumbnail', 'poster', 'compressed', 'resize'];   // chữ về dạng ảnh, không phải chủ đề
    $words = [];
    foreach (explode('-', blog_slugify($base)) as $w) {
        if ($w === '' || ctype_digit($w) || in_array($w, $junk, true) || preg_match('/^\d+[a-z]?$/', $w) || preg_match('/^[a-z]{1,6}\d{2,}$/', $w)) continue;   // bỏ mã máy ảnh dạng dsc0012, img1234
        $words[] = $w;
    }
    return count($words) >= 1 && strlen(implode('', $words)) >= 3 ? implode(' ', $words) : '';
}
/** Tên tệp của một ảnh Drive (cần robot đọc được tệp); rỗng nếu không lấy được. */
function img_drive_name(string $DIR, string $id): string {
    try {
        if (!gs_account($DIR)) return '';
        [$code, $j] = gs_http('GET', img_drive_api() . '/files/' . rawurlencode($id) . '?fields=name&supportsAllDrives=true', null, ['Authorization: Bearer ' . gs_token($DIR, IMG_DRIVE_SCOPE)], 15);
        return $code === 200 ? (string)($j['name'] ?? '') : '';
    } catch (Throwable $e) { return ''; }
}

/** Tách ô thành danh sách link (cách nhau bằng dấu ; hoặc xuống dòng hoặc khoảng trắng). */
function img_parse_cell(string $cell): array {
    $out = [];
    foreach (preg_split('/[;\s]+/u', trim($cell), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $u) {
        $u = trim($u, " \t\"'<>");
        if ($u !== '' && !in_array($u, $out, true)) $out[] = $u;
    }
    return array_slice($out, 0, 60);   // số link tối đa trong một ô (thư mục sẽ được mở ra và cắt theo số ảnh tối đa)
}
function img_drive_id(string $u): ?string {
    if (preg_match('#^https://(?:drive|docs)\.google\.com/(?:file/d/|document/d/)([A-Za-z0-9_-]{10,})#', $u, $m)) return $m[1];
    if (preg_match('#^https://(?:drive|docs)\.google\.com/(?:open|uc)\?[^\#]*\bid=([A-Za-z0-9_-]{10,})#', $u, $m)) return $m[1];
    if (preg_match('#^https://lh3\.googleusercontent\.com/d/([A-Za-z0-9_-]{10,})#', $u, $m)) return $m[1];
    return null;
}
function img_host_public(string $host): bool {
    if (getenv('VFW_IMG_ALLOW_LOCAL') === '1') return true;
    if ($host === '' || preg_match('/[^A-Za-z0-9.-]/', $host) || strpos($host, '.') === false) return false;
    $ips = @gethostbynamel($host) ?: [];
    if (!$ips) return false;
    foreach ($ips as $ip) { if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false; }
    return true;
}
/** Tải một địa chỉ, tự theo chuyển hướng nhưng kiểm tra từng bước chỉ ra máy chủ công cộng; trả về [dữ liệu, loại nội dung]. */
function img_http_get(string $url, int $timeout = 25): array {
    for ($hop = 0; $hop < 5; $hop++) {
        $p = parse_url($url);
        $local = getenv('VFW_IMG_ALLOW_LOCAL') === '1';
        if (!$p || !isset($p['host']) || isset($p['user']) || isset($p['pass']) || !in_array($p['scheme'] ?? '', $local ? ['http', 'https'] : ['https'], true) || (!$local && isset($p['port']) && (int)$p['port'] !== 443)) throw new RuntimeException('Link ảnh không hợp lệ (chỉ nhận https).');
        if (!img_host_public((string)$p['host'])) throw new RuntimeException('Link ảnh trỏ tới địa chỉ không được phép.');
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => $timeout, 'follow_location' => 0, 'ignore_errors' => true, 'header' => "User-Agent: vfwild-auto/1.0\r\nAccept: image/*,*/*;q=0.5\r\n"], 'ssl' => ['verify_peer' => true]]);
        $fp = @fopen($url, 'rb', false, $ctx);
        if (!$fp) throw new RuntimeException('Không tải được ảnh (mạng hoặc link hỏng).');
        $hdr = $http_response_header ?? [];
        $code = 0; $ctype = ''; $loc = '';
        foreach ($hdr as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) { $code = (int)$m[1]; $ctype = ''; $loc = ''; }   // lấy khối phản hồi cuối
            elseif (stripos($h, 'content-type:') === 0) $ctype = strtolower(trim(substr($h, 13)));
            elseif (stripos($h, 'location:') === 0) $loc = trim(substr($h, 9));
        }
        if (in_array($code, [301, 302, 303, 307, 308], true) && $loc !== '') {
            fclose($fp);
            $url = preg_match('#^https?://#i', $loc) ? $loc : (($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . (strpos($loc, '/') === 0 ? $loc : '/' . $loc));
            continue;
        }
        if ($code !== 200) { fclose($fp); throw new RuntimeException("Tải ảnh thất bại (mã $code)."); }
        $data = '';
        while (!feof($fp)) {
            $data .= (string)fread($fp, 65536);
            if (strlen($data) > IMG_MAX_BYTES) { fclose($fp); throw new RuntimeException('Ảnh lớn hơn 8 MB.'); }
        }
        fclose($fp);
        return [$data, $ctype];
    }
    throw new RuntimeException('Link ảnh chuyển hướng quá nhiều lần.');
}
/** Tải ảnh Drive theo mã tệp: thử đường công khai trước, không được thì dùng Drive API (cần tệp chia sẻ công khai hoặc cho tài khoản robot). */
function img_fetch_drive(string $DIR, string $id): string {
    $web = img_drive_web();
    $why = '';
    try {
        [$d, $ct] = img_http_get($web . '/uc?export=download&id=' . $id);
        if (strpos($ct, 'text/html') !== false) [$d, $ct] = img_http_get(str_replace('https://drive.google.com', 'https://drive.usercontent.google.com', $web) . '/download?id=' . $id . '&export=download&confirm=t');
        if (strpos($ct, 'text/html') === false) return $d;
        $why = 'chưa chia sẻ công khai';
    } catch (Throwable $e) { $why = $e->getMessage(); }
    if ($DIR !== '' && gs_account($DIR)) {
        try { return img_api_download($DIR, $id); } catch (Throwable $e) { $why .= ' / Drive API: ' . $e->getMessage(); }
    }
    throw new RuntimeException('Không tải được ảnh Drive (' . $why . '). Hãy để chế độ "Bất kỳ ai có đường liên kết" (Người xem).');
}
function img_drive_api(): string { $e = getenv('VFW_DRIVE_BASE'); return rtrim(is_string($e) && $e !== '' ? $e : 'https://www.googleapis.com/drive/v3', '/'); }
function img_drive_web(): string { $e = getenv('VFW_DRIVE_WEB'); return rtrim(is_string($e) && $e !== '' ? $e : 'https://drive.google.com', '/'); }
function img_api_download(string $DIR, string $id): string {
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 40, 'ignore_errors' => true, 'header' => 'Authorization: Bearer ' . gs_token($DIR, IMG_DRIVE_SCOPE) . "\r\n"], 'ssl' => ['verify_peer' => true]]);
    $fp = @fopen(img_drive_api() . '/files/' . rawurlencode($id) . '?alt=media&supportsAllDrives=true', 'rb', false, $ctx);
    if (!$fp) throw new RuntimeException('không kết nối được');
    $code = 0;
    foreach ($http_response_header ?? [] as $h) { if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1]; }
    if ($code !== 200) { fclose($fp); throw new RuntimeException($code === 404 || $code === 403 ? 'tài khoản robot không có quyền xem tệp này' : "mã $code"); }
    $d = '';
    while (!feof($fp)) { $d .= (string)fread($fp, 65536); if (strlen($d) > IMG_MAX_BYTES) { fclose($fp); throw new RuntimeException('Ảnh lớn hơn 8 MB.'); } }
    fclose($fp);
    return $d;
}
/** Tải ảnh từ link Drive hoặc link https thường. */
function img_fetch(string $link, string $DIR = ''): string {
    if ($id = img_drive_id($link)) return img_fetch_drive($DIR, $id);
    if (!preg_match('#^https?://#i', $link)) throw new RuntimeException('Link ảnh không hợp lệ.');
    return img_http_get($link)[0];
}

/* ---------- Thư mục Drive ---------- */
function img_folder_id(string $u): ?string {
    if (preg_match('#^https://drive\.google\.com/(?:drive/(?:u/\d+/)?folders/|folderview\?id=)([A-Za-z0-9_-]{10,})#', $u, $m)) return $m[1];
    return null;
}
/** Liệt kê ảnh trong thư mục bằng Drive API (thư mục công khai hoặc đã chia sẻ cho robot). Sắp theo tên tệp. */
function img_folder_list(string $DIR, string $fid): array {
    if (!gs_account($DIR)) throw new RuntimeException('chưa có khóa tài khoản dịch vụ Google');
    $q = "'$fid' in parents and trashed=false and mimeType contains 'image/'";
    $url = img_drive_api() . '/files?' . http_build_query(['q' => $q, 'fields' => 'files(id,name,mimeType,size)', 'orderBy' => 'name', 'pageSize' => 100, 'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true']);
    [$code, $j] = gs_http('GET', $url, null, ['Authorization: Bearer ' . gs_token($DIR, IMG_DRIVE_SCOPE)], 30);
    if ($code !== 200) throw new RuntimeException($code === 403 ? 'Drive API chưa bật hoặc không có quyền (mã 403)' : "Drive API mã $code");
    $out = [];
    foreach ((array)($j['files'] ?? []) as $f) {
        if (!in_array($f['mimeType'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true) || (int)($f['size'] ?? 0) > IMG_MAX_BYTES) continue;
        $out[] = ['id' => (string)$f['id'], 'name' => (string)$f['name']];
    }
    usort($out, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    return $out;
}
/** Dự phòng khi Drive API không dùng được: đọc trang xem thư mục công khai của Google (có thể hỏng nếu Google đổi giao diện). */
function img_folder_scrape(string $fid): array {
    [$html] = img_http_get(img_drive_web() . '/embeddedfolderview?id=' . $fid);
    $out = [];
    if (preg_match_all('#<div class="flip-entry" id="entry-([A-Za-z0-9_-]{10,})".*?<div class="flip-entry-title">([^<]*)</div>#s', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) { $n = html_entity_decode($x[2], ENT_QUOTES, 'UTF-8'); if (preg_match('/\.(jpe?g|png|webp)$/i', $n)) $out[] = ['id' => $x[1], 'name' => $n]; }
    }
    usort($out, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    return $out;
}
/** Mở các link trong ô thành danh sách ảnh cụ thể (thư mục → các ảnh trong thư mục). */
function img_sources(string $DIR, string $cell, array &$errors, int $max = IMG_DEFAULT_MAX): array {
    $out = []; $seen = [];
    foreach (img_parse_cell($cell) as $link) {
        if ($fid = img_folder_id($link)) {
            try { $files = img_folder_list($DIR, $fid); }
            catch (Throwable $e) {
                try { $files = img_folder_scrape($fid); }
                catch (Throwable $e2) { $errors[] = 'Thư mục Drive không đọc được: ' . $e->getMessage() . '.'; continue; }
            }
            if (!$files) { $errors[] = 'Thư mục Drive không có ảnh JPG/PNG/WebP nào (hoặc chưa chia sẻ công khai).'; continue; }
            foreach ($files as $f) { if (!isset($seen[$f['id']])) { $seen[$f['id']] = 1; $out[] = ['link' => 'drive:' . $f['id'], 'id' => $f['id'], 'name' => $f['name']]; } }
        } else {
            $id = img_drive_id($link);
            $k = $id ?? $link;
            if (!isset($seen[$k])) {
                $seen[$k] = 1;
                $name = $id ? img_drive_name($DIR, $id) : rawurldecode(basename((string)parse_url($link, PHP_URL_PATH)));   // tên tệp để đọc số mục
                $out[] = ['link' => $id ? 'drive:' . $id : $link, 'id' => $id, 'name' => $name, 'raw' => $link];
            }
        }
    }
    $max = max(1, min(IMG_HARD_MAX, $max));
    if (count($out) > $max) { $errors[] = 'Có ' . count($out) . ' ảnh, chỉ dùng ' . $max . ' ảnh đầu (theo thứ tự tên tệp; tăng "Số ảnh tối đa mỗi bài" trong Cài đặt nếu cần).'; $out = array_slice($out, 0, $max); }
    return $out;
}

/* ---------- Xử lý ảnh ---------- */
function img_gd(): bool { return function_exists('imagecreatefromstring') && function_exists('imagejpeg') && function_exists('imagecopyresampled'); }
/** Hướng xoay từ EXIF của ảnh JPEG (ảnh chụp bằng điện thoại thường mang thẻ này); 1 = không xoay. */
function img_exif_orientation(string $b): int {
    if (substr($b, 0, 2) !== "\xFF\xD8") return 1;
    $i = 2; $n = strlen($b);
    while ($i + 4 < $n) {
        if ($b[$i] !== "\xFF") return 1;
        $mk = ord($b[$i + 1]); $len = (ord($b[$i + 2]) << 8) | ord($b[$i + 3]);
        if ($mk === 0xE1 && substr($b, $i + 4, 6) === "Exif\0\0") {
            $t = $i + 10; $le = substr($b, $t, 2) === 'II';
            $rd = fn(int $o, int $l) => $le ? hexdec(bin2hex(strrev(substr($b, $o, $l)))) : hexdec(bin2hex(substr($b, $o, $l)));
            $ifd = $t + $rd($t + 4, 4); $cnt = $rd($ifd, 2);
            for ($k = 0; $k < min($cnt, 40); $k++) { $e = $ifd + 2 + $k * 12; if ($rd($e, 2) === 0x0112) return max(1, min(8, (int)$rd($e + 8, 2))); }
            return 1;
        }
        if ($mk === 0xDA || $len < 2) return 1;
        $i += 2 + $len;
    }
    return 1;
}
/** Kiểm tra là ảnh thật và tạo 3 cỡ JPEG. Trả về ['id','files'=>[cỡ=>nhị phân],'w','h','note']. */
/**
 * Tên tệp ảnh theo nội dung (thay mã ngẫu nhiên b8c339…): "<chữ gợi ý không dấu>-<6 ký tự mã>" → "ghe-dien-honda-city-quy-trinh-3f9a12".
 * Chữ gợi ý cắt ở ranh giới từ, tối đa 60 ký tự; mã ngắn lấy từ $uniq (mã nội dung ảnh) để hai ảnh không trùng tên. Rỗng gợi ý thì "anh".
 */
function img_seo_id(string $hint, string $uniq): string {
    $s = blog_slugify($hint, 0);
    if (strlen($s) > 60) { $s = substr($s, 0, 61); $s = substr($s, 0, (int)strrpos($s, '-') ?: 60); }
    $s = trim($s, '-');
    $u = substr(preg_replace('/[^a-z0-9]/', '', strtolower($uniq)) ?: md5($uniq), -6);
    return ($s !== '' ? $s : 'anh') . '-' . $u;
}
/** Chữ gợi ý cho tên ảnh: từ khóa / tiêu đề bài + chủ đề trong tên tệp gốc (nếu tên tệp có nghĩa và chưa nằm trong từ khóa). */
function img_name_hint(string $base, string $fileName): string {
    $bt = blog_slugify($base, 0);
    if (strlen($bt) > 40) { $bt = substr($bt, 0, 41); $bt = substr($bt, 0, (int)strrpos($bt, '-') ?: 40); }   // phần tiêu đề ngắn lại để còn chỗ cho chủ đề ảnh
    $info = img_name_info($fileName);
    $topic = $info['cover'] ? '' : img_topic($fileName);   // "bia", "cover" chỉ là quy ước đặt tên, không phải nội dung ảnh
    if ($topic === '') return $bt;
    $tt = blog_slugify($topic, 0);
    return $bt !== '' && str_contains('-' . $bt . '-', '-' . $tt . '-') ? $bt : trim($bt . '-' . $tt, '-');
}
function img_process(string $bytes): array {
    $info = @getimagesizefromstring($bytes);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) throw new RuntimeException('Tệp không phải ảnh JPG, PNG hoặc WebP.');
    [$w, $h] = [(int)$info[0], (int)$info[1]];
    if ($w < 300 || $h < 200) throw new RuntimeException("Ảnh quá nhỏ ({$w}×{$h}, cần từ 300×200).");
    if ($w * $h > 40000000) throw new RuntimeException('Ảnh quá lớn về kích thước điểm ảnh.');
    $id = 'b' . substr(sha1($bytes), 0, 12);
    if (!img_gd()) {
        if ($info[2] !== IMAGETYPE_JPEG || strlen($bytes) > 700000 || $w > 2400) throw new RuntimeException('Máy chủ chưa bật GD nên chỉ nhận ảnh JPG nhỏ hơn 700 KB, rộng tối đa 2400 px (hãy nén ảnh nhỏ lại).');
        return ['id' => $id, 'files' => [800 => $bytes, 1200 => $bytes, 1600 => $bytes], 'w' => $w, 'h' => $h, 'note' => ''];
    }
    $im = @imagecreatefromstring($bytes);
    if (!$im) throw new RuntimeException('Không đọc được ảnh (tệp hỏng).');
    if ($info[2] === IMAGETYPE_JPEG && function_exists('imagerotate')) {
        $deg = [3 => 180, 6 => -90, 8 => 90][img_exif_orientation($bytes)] ?? 0;
        if ($deg !== 0 && ($r = imagerotate($im, $deg, 0))) { $im = $r; $w = imagesx($im); $h = imagesy($im); }
    }
    $files = [];
    foreach (IMG_SIZES as $sz) {
        $tw = min($sz, $w); $th = (int)round($h * $tw / $w);
        $c = imagecreatetruecolor($tw, $th);
        imagefill($c, 0, 0, imagecolorallocate($c, 255, 255, 255));   // PNG trong suốt → nền trắng
        imagecopyresampled($c, $im, 0, 0, 0, 0, $tw, $th, $w, $h);
        ob_start(); imagejpeg($c, null, 82); $files[$sz] = (string)ob_get_clean();
        imagedestroy($c);
    }
    imagedestroy($im);
    return ['id' => $id, 'files' => $files, 'w' => $w, 'h' => $h, 'note' => ''];
}
/** Lưu 3 cỡ vào <web>/img/blog (hiện trong Thư viện ảnh như ảnh tải lên). Trả về [đường dẫn ảnh bìa (800), đường dẫn ảnh trong bài (1200)]. */
function img_store(array $p): array {
    $dir = blog_web() . '/img/blog';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('Không tạo được thư mục ảnh.');
    foreach (IMG_SIZES as $sz) {
        $f = "$dir/{$p['id']}-$sz.jpg";
        if (!is_file($f)) { blog_atomic($f, $p['files'][$sz]); @chmod($f, 0644); }
    }
    return ["/img/blog/{$p['id']}-800.jpg", "/img/blog/{$p['id']}-1200.jpg"];
}
/**
 * Xếp ảnh theo quy ước đặt tên: ảnh bìa đứng đầu (ảnh có tên bìa; không có thì ảnh không số đầu tiên; không có nữa thì ảnh đầu tiên),
 * các ảnh còn lại theo thứ tự cũ (ảnh cùng mục giữ thứ tự tên tệp).
 */
function img_order(array $images, array &$notes): array {
    if (count($images) < 2) { if ($images) { $images[0]['section'] = null; } return $images; }
    $ci = null;
    foreach ($images as $i => $im) { if (!empty($im['is_cover'])) { $ci = $i; break; } }
    if ($ci === null) { foreach ($images as $i => $im) { if (($im['section'] ?? null) === null) { $ci = $i; break; } } }
    if ($ci === null) {
        $ci = 0;
        $notes[] = 'Mọi ảnh đều đã gán số mục nên không có ảnh bìa riêng: tạm dùng ảnh “' . ($images[0]['name'] ?: 'đầu tiên') . '” làm ảnh bìa. Muốn chọn ảnh bìa, đặt tên bắt đầu bằng 0 hoặc “bia”.';
    }
    $cover = $images[$ci]; $cover['section'] = null;
    unset($images[$ci]);
    return array_merge([$cover], array_values($images));
}
/** Tải và xử lý toàn bộ ảnh ở một ô. Trả về ['images' => [['cover','insert','b64','mime','src']…], 'errors' => […]]. */
function img_prepare(string $DIR, string $cell, int $max = IMG_DEFAULT_MAX, string $hint = ''): array {
    $errors = [];
    $sources = img_sources($DIR, $cell, $errors, $max);
    $vis = count($sources) > 8 ? 800 : 1200;   // nhiều ảnh thì gửi bản nhỏ hơn cho AI để yêu cầu không quá nặng
    $map = auto_json(auto_dir($DIR) . '/imgmap.json', []);
    $images = [];
    foreach ($sources as $i => $src) {
        $label = 'Ảnh ' . ($i + 1) . ($src['name'] !== '' ? ' (' . $src['name'] . ')' : '');
        try {
            $key = sha1($src['link']);
            $id = $map[$key] ?? '';
            if ($id === '' || !is_file(blog_web() . "/img/blog/$id-1200.jpg")) {
                $proc = img_process($src['id'] ? img_fetch_drive($DIR, $src['id']) : img_fetch($src['raw'] ?? $src['link'], $DIR));
                if ($hint !== '') $proc['id'] = img_seo_id(img_name_hint($hint, (string)$src['name']), $proc['id']);   // tên tệp theo nội dung: tải ảnh về có tên đúng chủ đề, tốt cho SEO ảnh
                img_store($proc);
                $id = $proc['id']; $map[$key] = $id;
            }
            $f = blog_web() . "/img/blog/$id-$vis.jpg";
            $raw = (string)@file_get_contents($f);
            $info = img_name_info((string)$src['name']);
            $images[] = ['cover' => "/img/blog/$id-800.jpg", 'insert' => "/img/blog/$id-1200.jpg", 'b64' => base64_encode($raw), 'mime' => 'image/jpeg', 'src' => $src['link'], 'bytes' => strlen($raw),
                'name' => (string)$src['name'], 'section' => $info['section'], 'is_cover' => $info['cover'],
                'topic' => ($info['cover'] || $info['section'] !== null) ? '' : img_topic((string)$src['name'])];
        } catch (Throwable $e) { $errors[] = "$label: " . $e->getMessage(); }
    }
    if ($sources) auto_save_json(auto_dir($DIR) . '/imgmap.json', array_slice($map, -500, null, true));
    $images = img_order($images, $errors);
    return ['images' => $images, 'errors' => $errors];
}
/* ---------- Chèn ảnh vào bài ---------- */
/** Tiêu đề/chú thích ảnh: ngắn, không có ký tự làm hỏng cú pháp Markdown. Rỗng nếu AI không đưa. */
function img_clean_title(string $s): string {
    $s = trim(preg_replace('/\s+/u', ' ', str_replace(['[', ']', '(', ')', '!', '<', '>', '"', '`', '|'], '', $s)) ?? '');
    return mb_substr($s, 0, 90, 'UTF-8');
}
function img_clean_alt(string $s, string $fallback): string {
    $s = trim(preg_replace('/\s+/u', ' ', str_replace(['[', ']', '(', ')', '!', '<', '>', '"', '`', '|'], '', $s)) ?? '');
    return $s === '' ? $fallback : mb_substr($s, 0, 140, 'UTF-8');
}
/**
 * Chèn các ảnh vào thân bài. $plan[n] = ['after' => tiêu đề mục AI chọn, 'alt' => mô tả]; n = số thứ tự ảnh (ảnh 1 là ảnh bìa nên không chèn).
 * Ảnh có số mục trong tên tệp ($images[k]['section']) được đặt đúng mục H2 thứ N (đếm mọi H2 từ 1, kể cả mục câu hỏi và kết bài).
 * Ảnh không có số nhưng tên có chữ ("pin-sac-nhanh.jpg"): đặt vào mục H2 có tiêu đề khớp các chữ đó (khớp từ 60% trở lên).
 * Ảnh còn lại: mỗi mục nội dung tối đa một ảnh; ảnh không có chỗ do AI chọn được rải đều theo các mục còn trống.
 * Cảnh báo (số mục vượt quá số H2 của bài…) được thêm vào $warn.
 */
function img_place(string $body, array $images, array $plan, array &$warn = [], string $altPrefix = 'VinFast VF Wild – '): array {
    $inline = array_slice($images, 1);
    if (!$inline) return [$body, 0];
    $lines = preg_split('/\r?\n/', $body) ?: [];
    $heads = [];   // mọi H2: [chỉ số dòng, chữ tiêu đề (bỏ số đầu), là mục nội dung (không phải câu hỏi / kết bài)]
    foreach ($lines as $i => $ln) {
        if (preg_match('/^##\s+(.*)$/u', $ln, $m)) $heads[] = [$i, trim(preg_replace('/^\d{1,2}\s*[.):]\s+/u', '', trim($m[1])) ?? $m[1]), !preg_match('/câu hỏi|kết bài|kết luận|xem thêm/iu', $m[1])];
    }
    if (!$heads) $heads[] = [-1, 'bài viết', true];   // bài không có mục H2: coi cả bài là một mục
    $H = count($heads);
    $content = [];
    foreach ($heads as $hi => $h) { if ($h[2]) $content[] = $hi; }
    if (!$content) $content = range(0, $H - 1);
    $C = count($content);
    $cap = max(1, (int)ceil(count($inline) / $C));   // mỗi mục một ảnh; nhiều ảnh hơn số mục thì mỗi mục nhận nhiều ảnh
    $cnt = array_fill(0, $H, 0); $assign = []; $done = [];
    foreach ($inline as $k => $img) {   // 1) ảnh đã đặt số mục trong tên tệp: đúng mục đó, không bàn cãi
        $n = $img['section'] ?? null;
        if ($n === null) continue;
        $hi = $n - 1;
        if ($hi >= $H) {
            $hi = $content[$C - 1];
            $warn[] = 'Ảnh “' . ($img['name'] !== '' ? $img['name'] : ('ảnh ' . ($k + 2))) . '” ghi mục ' . $n . ' nhưng bài chỉ có ' . $H . ' mục H2: đã đặt vào mục ' . ($hi + 1) . '.';
        }
        $assign[$hi][] = $k; $cnt[$hi]++; $done[$k] = true;
    }
    foreach ($inline as $k => $img) {   // 1b) ảnh không số nhưng tên tệp nêu chủ đề: mục có tiêu đề khớp nhất (biên tập chủ ý đặt tên nên không bị giới hạn một ảnh mỗi mục)
        if (isset($done[$k]) || (string)($img['topic'] ?? '') === '') continue;
        $tok = auto_tok((string)$img['topic']);
        if (!$tok) continue;
        $best = -1; $bs = 0.0;
        foreach ($content as $hi) {
            $sc = auto_hit($tok, $heads[$hi][1]);
            if ($sc > $bs || ($sc === $bs && $best >= 0 && $cnt[$hi] < $cnt[$best])) { $bs = $sc; $best = $hi; }
        }
        if ($best >= 0 && $bs >= 0.6) { $assign[$best][] = $k; $cnt[$best]++; $done[$k] = true; }
    }
    foreach ($inline as $k => $img) {   // 2) ảnh chưa có chỗ: theo mục AI chọn
        if (isset($done[$k])) continue;
        $after = (string)($plan[$k + 2]['after'] ?? '');
        if ($after === '') continue;
        $after = trim(preg_replace('/^\d{1,2}\s*[.):]\s+/u', '', $after) ?? $after);
        $tok = auto_tok($after); $best = -1; $bs = 0.0;
        foreach ($content as $hi) {
            if ($cnt[$hi] >= $cap) continue;
            $ht = $heads[$hi][1];
            $sc = $tok ? auto_hit($tok, $ht) : 0.0;
            if (mb_stripos($ht, $after, 0, 'UTF-8') !== false || mb_stripos($after, $ht, 0, 'UTF-8') !== false) $sc = max($sc, 1.0);
            if ($sc > $bs || ($sc === $bs && $best >= 0 && $cnt[$hi] < $cnt[$best])) { $bs = $sc; $best = $hi; }
        }
        if ($best >= 0 && $bs >= 0.5) { $assign[$best][] = $k; $cnt[$best]++; $done[$k] = true; }
    }
    $rest = array_values(array_filter(array_keys($inline), fn($k) => !isset($done[$k])));
    foreach ($rest as $j => $k) {   // 3) ảnh còn lại: rải đều dọc theo các mục nội dung
        $ideal = (int)floor(($j + 0.5) * $C / max(1, count($rest)));
        $bestH = -1; $bd = PHP_INT_MAX;
        foreach ($content as $ci => $hi) { if ($cnt[$hi] < $cap && abs($ci - $ideal) < $bd) { $bd = abs($ci - $ideal); $bestH = $hi; } }
        if ($bestH < 0) $bestH = $content[$C - 1];
        $assign[$bestH][] = $k; $cnt[$bestH]++;
    }
    $ins = [];   // [chỉ số dòng, thứ tự ảnh, dòng chèn]
    foreach ($assign as $hi => $ks) {
        sort($ks);
        $start = $heads[$hi][0] + 1; $stop = count($lines);
        for ($i = $start; $i < count($lines); $i++) { if (preg_match('/^##\s/', $lines[$i])) { $stop = $i; break; } }
        while ($stop > $start && trim($lines[$stop - 1]) === '') $stop--;
        $ends = [];   // vị trí ngay sau mỗi đoạn văn thường của mục
        for ($i = $start; $i < $stop;) {
            if (trim($lines[$i]) === '') { $i++; continue; }
            $isText = !preg_match('/^\s*(#|\||[-*]\s|\d+\.\s|>|!\[)/u', $lines[$i]);
            $j = $i; while ($j < $stop && trim($lines[$j]) !== '') $j++;
            if ($isText) $ends[] = $j;
            $i = $j;
        }
        foreach ($ks as $n => $k) {
            $alt = img_clean_alt((string)($plan[$k + 2]['alt'] ?? ''), $altPrefix . $heads[$hi][1]);
            $ti = img_clean_title((string)($plan[$k + 2]['title'] ?? ''));
            $ins[] = [$ends[$n] ?? $stop, $k, '![' . $alt . '](' . $inline[$k]['insert'] . ($ti !== '' ? ' "' . $ti . '"' : '') . ')'];
        }
    }
    usort($ins, fn($a, $b) => [$b[0], $b[1]] <=> [$a[0], $a[1]]);   // chèn từ dưới lên để không lệch chỉ số dòng
    foreach ($ins as [$at, , $md]) array_splice($lines, $at, 0, ['', $md, '']);
    return [implode("\n", $lines), count($ins)];
}
require_once __DIR__ . '/blog-attach.php';   // nút "Gắn ảnh vào bài" trong trang Sửa bài

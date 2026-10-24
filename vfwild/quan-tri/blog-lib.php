<?php
/**
 * Quản lý blog cho trang quản trị vfwild.vn (được index.php nạp vào; nginx không cho truy cập file này trực tiếp).
 * Bài viết lưu ở  $DIR/blog/<slug>.md  (Markdown + phần đầu khai báo) – ngoài thư mục web nên deploy không ghi đè.
 * Khi lưu, trang HTML tĩnh được tạo vào  <web>/blog/  cùng blog/latest.json, sitemap.xml  (nginx chỉ phục vụ file tĩnh).
 * Ảnh tải lên nằm ở  <web>/img/blog/.
 */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }   // chỉ được nạp từ index.php

const BLOG_SITE = 'https://vfwild.vn';
const BLOG_OG_DEFAULT = BLOG_SITE . '/img/og/vf-wild-chungauto.jpg';
const BLOG_CATS = ['Giá & ưu đãi', 'Công nghệ', 'Chi phí', 'So sánh', 'Thủ tục', 'Tin tức', 'Hướng dẫn'];
const BLOG_KEYS = ['title', 'seo_title', 'slug', 'description', 'date', 'updated', 'category', 'tags', 'cover', 'cover_alt', 'cover_title', 'video', 'status'];
const BLOG_MAX_IMG = 700 * 1024;

function blog_web(): string { return rtrim(getenv('VFW_WEBROOT') ?: dirname(__DIR__), '/\\'); }
function blog_dir(string $DIR): string {
    $d = "$DIR/blog";
    foreach ([$d, "$d/_trash", "$d/_versions"] as $x) { if (!is_dir($x)) @mkdir($x, 0750, true); }
    return $d;
}
function blog_atomic(string $file, string $data): void {
    $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $data, LOCK_EX) === false) throw new RuntimeException('Không ghi được ' . basename($file));
    @chmod($tmp, 0644);
    if (!rename($tmp, $file)) { @unlink($tmp); throw new RuntimeException('Không ghi được ' . basename($file)); }
}
function blog_len(string $s): int { return (int)preg_match_all('/./us', $s); }

/* ---------- Đường dẫn (slug) từ tiếng Việt ---------- */
function blog_slugify(string $s, int $max = 80): string {
    $s = preg_replace('/\p{Mn}+/u', '', mb_strtolower(trim($s), 'UTF-8')) ?? '';   // bỏ dấu rời (chuỗi NFD dán từ macOS/Word)
    $groups = [
        'a' => 'àáạảãâầấậẩẫăằắặẳẵ', 'e' => 'èéẹẻẽêềếệểễ', 'i' => 'ìíịỉĩ',
        'o' => 'òóọỏõôồốộổỗơờớợởỡ', 'u' => 'ùúụủũưừứựửữ', 'y' => 'ỳýỵỷỹ', 'd' => 'đ',
    ];
    $map = [];
    foreach ($groups as $to => $chars) { foreach (mb_str_split($chars) as $c) $map[$c] = $to; }
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return $max > 0 ? trim(substr(trim($s, '-'), 0, $max), '-') : trim($s, '-');
}

/* ---------- Đọc / ghi bài ---------- */
function blog_parse(string $raw): ?array {
    if (!preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n?(.*)\z/s', $raw, $m)) return null;
    $meta = [];
    foreach (preg_split('/\r?\n/', $m[1]) ?: [] as $ln) {
        if (preg_match('/^(\w+):[ \t]*(.*)$/u', $ln, $k)) $meta[$k[1]] = trim($k[2]);
    }
    $meta['body'] = ltrim($m[2], "\r\n");
    foreach (BLOG_KEYS as $k) $meta[$k] = $meta[$k] ?? '';
    if ($meta['status'] === '') $meta['status'] = 'published';
    return $meta;
}
function blog_serialize(array $p): string {
    $out = "---\n";
    foreach (BLOG_KEYS as $k) {
        $v = trim(str_replace(["\r", "\n"], ' ', (string)($p[$k] ?? '')));
        if ($v !== '' || in_array($k, ['title', 'slug', 'date', 'status'], true)) $out .= "$k: $v\n";
    }
    return $out . "---\n\n" . rtrim(str_replace("\r\n", "\n", (string)($p['body'] ?? ''))) . "\n";
}
function blog_load(string $DIR, string $slug): ?array {
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $slug)) return null;
    $f = blog_dir($DIR) . "/$slug.md";
    $raw = is_file($f) ? file_get_contents($f) : false;
    return $raw === false ? null : blog_parse($raw);
}
function blog_all(string $DIR): array {
    $out = [];
    foreach (glob(blog_dir($DIR) . '/*.md') ?: [] as $f) {
        $p = blog_parse((string)file_get_contents($f));
        if ($p) { $p['slug'] = basename($f, '.md'); $p['words'] = blog_words($p['body']); $out[] = $p; }
    }
    usort($out, fn($a, $b) => [$b['date'], $a['slug']] <=> [$a['date'], $b['slug']]);
    return $out;
}
function blog_words(string $body): int { return (int)preg_match_all('/\p{L}[\p{L}\p{N}]*|\p{N}+/u', $body); }
function blog_log(string $DIR, string $action, string $slug): void {
    $ip = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '-') . '|vfwild-admin'), 0, 10);
    @file_put_contents(blog_dir($DIR) . '/history.jsonl',
        json_encode(['t' => date('c'), 'ip' => $ip, 'u' => function_exists('me_label') ? me_label() : '', 'action' => $action, 'slug' => $slug], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}
/** Thời điểm tạo từng bài (giây Unix) lấy từ nhật ký "create"; bài cũ chưa có nhật ký thì dùng giờ sửa tệp. slug => thời điểm. */
function blog_created_map(string $DIR): array {
    $f = blog_dir($DIR) . '/history.jsonl';
    $m = [];
    if (!is_file($f)) return $m;
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
        $j = json_decode($l, true);
        if (!is_array($j) || ($j['action'] ?? '') !== 'create' || !isset($j['slug'], $j['t'])) continue;
        $ts = strtotime((string)$j['t']);
        if ($ts && (!isset($m[$j['slug']]) || $ts < $m[$j['slug']])) $m[(string)$j['slug']] = $ts;
    }
    return $m;
}
function blog_history(string $DIR, int $n = 10): array {
    $f = blog_dir($DIR) . '/history.jsonl';
    if (!is_file($f)) return [];
    $out = [];
    foreach (array_slice(file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -$n) as $l) { $j = json_decode($l, true); if (is_array($j)) $out[] = $j; }
    return array_reverse($out);
}
function blog_versions(string $DIR, string $slug): array {
    $files = glob(blog_dir($DIR) . "/_versions/$slug/*.md") ?: [];
    rsort($files);
    return array_map('basename', array_slice($files, 0, 8));
}

/* ---------- Kiểm tra SEO ---------- */
function blog_effective_title(array $p): string {
    $t = trim($p['seo_title'] ?? '') !== '' ? trim($p['seo_title']) : trim($p['title'] ?? '');
    return blog_len($t) + 20 <= 60 ? $t . ' | VF Wild ChungAuto' : $t;
}
function blog_seo_issues(array $p): array {
    $i = [];
    $t = blog_effective_title($p);
    if (blog_len($t) > 60) $i[] = 'Tiêu đề trên Google dài ' . blog_len($t) . ' ký tự (nên ≤ 60).';
    $d = blog_len($p['description'] ?? '');
    if ($d < 70 || $d > 160) $i[] = 'Mô tả ' . $d . ' ký tự (nên 70–160).';
    $w = blog_words((string)($p['body'] ?? ''));
    if ($w < 600) $i[] = 'Bài mới ' . $w . ' từ (nên từ 600 trở lên).';
    if (preg_match_all('/(?<!!)\[[^\]]*\]\(\/(?!\/)/u', (string)($p['body'] ?? '')) < 2) $i[] = 'Cần ít nhất 2 liên kết nội bộ.';
    if (preg_match_all('/^##\s/m', (string)($p['body'] ?? '')) < 3) $i[] = 'Nên có từ 3 tiêu đề phụ (##).';
    if (trim($p['cover'] ?? '') === '' || trim($p['cover_alt'] ?? '') === '') $i[] = 'Thiếu ảnh bìa hoặc mô tả ảnh (alt).';
    return $i;
}

/* ---------- Markdown tối giản (an toàn: mọi HTML thô bị escape) ---------- */
function blog_url_ok(string $u): bool {
    return (bool)preg_match('#^(/(?!/)|https?://|mailto:|tel:|\#)#i', $u) && !preg_match('/[\s"<>\\\\]/', $u);
}
function blog_img_html(string $alt, string $url, string $title = ''): ?string {
    if (!blog_url_ok($url) || stripos($url, 'mailto:') === 0 || stripos($url, 'tel:') === 0) return null;
    $dim = '';
    if (strncmp($url, '/img/', 5) === 0 && strpos($url, '..') === false) {
        $f = blog_web() . $url;
        $s = is_file($f) ? @getimagesize($f) : false;
        if ($s) $dim = ' width="' . (int)$s[0] . '" height="' . (int)$s[1] . '"';
    }
    return '<img class="post__img" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"' . ($title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"' : '') . $dim . ' loading="lazy" decoding="async">';
}
function blog_inline(string $t): string {
    $t = htmlspecialchars($t, ENT_NOQUOTES, 'UTF-8');
    $t = preg_replace('/\*\*(.+?)\*\*/us', '<strong>$1</strong>', $t) ?? $t;
    $t = preg_replace('/~~(.+?)~~/us', '<del>$1</del>', $t) ?? $t;
    $t = preg_replace('/(?<!\*)\*(?![\s*])(.+?)(?<![\s*])\*(?!\*)/us', '<em>$1</em>', $t) ?? $t;
    $t = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/u', function ($m) {
        $img = blog_img_html(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'), html_entity_decode($m[3] ?? '', ENT_QUOTES, 'UTF-8'));
        return $img ?? $m[0];
    }, $t) ?? $t;
    $t = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function ($m) {
        $u = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        if (!blog_url_ok($u)) return $m[0];
        $host = strtolower((string)parse_url($u, PHP_URL_HOST));
        $ext = preg_match('#^https?://#i', $u) && !in_array($host, ['vfwild.vn', 'www.vfwild.vn'], true);
        return '<a href="' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . '"' . ($ext ? ' target="_blank" rel="noopener nofollow"' : '') . '>' . $m[1] . '</a>';
    }, $t) ?? $t;
    return $t;
}
/** Nhận dạng link video: YouTube, Vimeo hoặc tệp .mp4/.webm. Trả về null nếu không hợp lệ. */
function blog_video_parse(string $u): ?array {
    $u = trim($u);
    if ($u === '' || preg_match('/[\s"<>\\\\]/', $u)) return null;
    if (preg_match('#^/(?!/)[A-Za-z0-9/_.-]+\.(mp4|webm)$#i', $u) && strpos($u, '..') === false) return ['type' => 'file', 'url' => $u, 'mime' => strtolower(substr($u, -4)) === 'webm' ? 'video/webm' : 'video/mp4'];
    if (!preg_match('#^https://#i', $u)) { if (preg_match('#^(www\.|m\.)?(youtube\.com|youtu\.be|vimeo\.com)/#i', $u)) $u = 'https://' . $u; else return null; }
    $p = parse_url($u);
    if (!is_array($p) || empty($p['host'])) return null;
    $host = preg_replace('/^(www|m)\./', '', strtolower((string)$p['host']));
    $path = (string)($p['path'] ?? '');
    parse_str((string)($p['query'] ?? ''), $q);
    $yt = null;
    if ($host === 'youtu.be') $yt = trim($path, '/');
    elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
        if (isset($q['v'])) $yt = (string)$q['v'];
        elseif (preg_match('#^/(?:embed|shorts|live|v)/([^/?]+)#', $path, $m)) $yt = $m[1];
    }
    if ($yt !== null) {
        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $yt)) return null;
        return ['type' => 'youtube', 'id' => $yt, 'url' => 'https://www.youtube.com/watch?v=' . $yt, 'embed' => 'https://www.youtube-nocookie.com/embed/' . $yt . '?rel=0', 'thumb' => 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg'];
    }
    if ($host === 'vimeo.com' || $host === 'player.vimeo.com') {
        if (preg_match('#/(?:video/)?(\d{6,12})(?:/([0-9a-f]{8,}))?/?$#', $path, $m)) {
            $h = $m[2] ?? ($q['h'] ?? '');
            return ['type' => 'vimeo', 'id' => $m[1], 'url' => 'https://vimeo.com/' . $m[1], 'embed' => 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1' . (preg_match('/^[0-9a-f]{8,}$/', (string)$h) ? '&h=' . $h : ''), 'thumb' => ''];
        }
        return null;
    }
    if (preg_match('#\.(mp4|webm)$#i', $path)) return ['type' => 'file', 'url' => $u, 'mime' => strtolower(substr($path, -4)) === 'webm' ? 'video/webm' : 'video/mp4'];
    return null;
}
function blog_video_label(?array $v): string { return $v === null ? '' : ['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'file' => 'Tệp video'][$v['type']]; }
/** Khối video đặt trong bài: iframe tải lười (không chặn tốc độ), tỉ lệ 16:9. */
function blog_video_html(array $v, string $title, string $poster = ''): string {
    if ($v['type'] === 'file') {
        return '<figure class="post__video"><video controls preload="metadata" playsinline' . ($poster !== '' ? ' poster="' . blog_e($poster) . '"' : '') . ' aria-label="' . blog_e($title) . '"><source src="' . blog_e($v['url']) . '" type="' . $v['mime'] . '"></video></figure>';
    }
    return '<figure class="post__video"><div class="post__video-frame"><iframe src="' . blog_e($v['embed']) . '" title="' . blog_e('Video: ' . $title) . '" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div></figure>';
}
/** Đặt video: đúng chỗ dòng [video] trong bài; không có dấu thì ngay sau đoạn mở đầu. Không có video thì xóa dấu thừa. */
function blog_video_place(string $html, ?string $video): string {
    $mark = '<p>[video]</p>';
    if (strpos($html, $mark) !== false) {
        if ($video === null) return str_replace($mark, '', $html);
        $html = substr_replace($html, $video, strpos($html, $mark), strlen($mark));
        return str_replace($mark, '', $html);
    }
    return $video === null ? $html : blog_video_insert($html, $video);
}
/** Chèn video ngay sau đoạn mở đầu của bài. */
function blog_video_insert(string $html, string $video): string {
    $pos = strpos($html, '</p>');
    return $pos === false ? $video . $html : substr_replace($html, '</p>' . $video, $pos, 4);
}
function blog_md(string $text): string {
    $out = [];
    $para = [];
    $ids = [];
    $lines = preg_split('/\r?\n/', trim($text)) ?: [];
    $flush = function () use (&$out, &$para) {
        if ($para) {
            $text = implode(' ', $para);
            $img = preg_match('/^!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)$/u', $text, $m) ? blog_img_html($m[1], $m[2], $m[3] ?? '') : null;
            if ($img !== null) {
                $cap = trim(($m[3] ?? '') !== '' ? $m[3] : $m[1]);   // chú thích: tiêu đề ảnh nếu có, không thì mô tả (alt)
                $out[] = '<figure class="post__fig">' . $img . ($cap !== '' ? '<figcaption>' . htmlspecialchars($cap, ENT_NOQUOTES, 'UTF-8') . '</figcaption>' : '') . '</figure>';
            } else {
                $out[] = '<p>' . blog_inline($text) . '</p>';
            }
            $para = [];
        }
    };
    $n = count($lines);
    for ($i = 0; $i < $n;) {
        $ln = rtrim($lines[$i]);
        if (trim($ln) === '') { $flush(); $i++; continue; }
        if (preg_match('/^\\\\([#>|*-]|\d+[.)]\s)/', $ln)) { $para[] = substr(trim($ln), 1); $i++; continue; }   // \# \- \1. = ký tự thường, không phải định dạng
        if (preg_match('/^(#{2,4})\s+(.*)$/u', $ln, $m)) {
            $flush(); $lv = strlen($m[1]);
            $hid = blog_slugify(preg_replace('/[*_`]+/', '', $m[2]) ?? $m[2]);
            if ($hid === '') $hid = 'muc';
            for ($k = 2, $base = $hid; isset($ids[$hid]); $k++) $hid = "$base-$k";
            $ids[$hid] = true;
            $out[] = "<h$lv id=\"$hid\">" . blog_inline($m[2]) . "</h$lv>"; $i++; continue;
        }
        if (preg_match('/^-{3,}$/', trim($ln))) { $flush(); $out[] = '<hr>'; $i++; continue; }
        if (ltrim($ln)[0] === '|') {
            $flush(); $rows = [];
            while ($i < $n && trim($lines[$i]) !== '' && ltrim($lines[$i])[0] === '|') {
                $rows[] = array_map('trim', explode('|', trim(trim($lines[$i]), '|'))); $i++;
            }
            $head = $rows[0] ?? [];
            $body = array_slice($rows, 2);
            $t = '<div class="tablewrap"><table><thead><tr>';
            foreach ($head as $c) $t .= '<th>' . blog_inline($c) . '</th>';
            $t .= '</tr></thead><tbody>';
            foreach ($body as $r) { $t .= '<tr>'; foreach ($r as $c) $t .= '<td>' . blog_inline($c) . '</td>'; $t .= '</tr>'; }
            $out[] = $t . '</tbody></table></div>'; continue;
        }
        if (preg_match('/^(-|\*|\d+\.)\s+/', $ln)) {
            $flush(); $ordered = ctype_digit($ln[0]); $items = [];
            while ($i < $n && preg_match('/^(-|\*|\d+\.)\s+/', $lines[$i])) {
                $it = preg_replace('/^(-|\*|\d+\.)\s+/', '', rtrim($lines[$i])); $i++;
                while ($i < $n && preg_match('/^\s{2,}\S/', $lines[$i]) && !preg_match('/^\s*(-|\*|\d+\.)\s+/', $lines[$i])) { $it .= ' ' . trim($lines[$i]); $i++; }
                $items[] = $it;
            }
            $tag = $ordered ? 'ol' : 'ul';
            $out[] = "<$tag>" . implode('', array_map(fn($x) => '<li>' . blog_inline($x) . '</li>', $items)) . "</$tag>"; continue;
        }
        if ($ln[0] === '>') {
            $flush(); $q = [];
            while ($i < $n && ($lines[$i][0] ?? '') === '>') { $q[] = trim(ltrim($lines[$i], '> ')); $i++; }
            $out[] = '<blockquote>' . blog_inline(implode(' ', $q)) . '</blockquote>'; continue;
        }
        $para[] = trim($ln); $i++;
    }
    $flush();
    return implode("\n", $out);
}

/* ---------- Khung trang (lấy từ trang chính sách bảo mật để giống hệt giao diện site) ---------- */
function blog_template(): array {
    $f = blog_web() . '/chinh-sach-bao-mat/index.html';
    $t = is_file($f) ? (string)file_get_contents($f) : '';
    $a = strpos($t, '<main id="main">');
    $b = strpos($t, '</main>');
    if ($a === false || $b === false) throw new RuntimeException('Không tìm thấy khung trang mẫu (chinh-sach-bao-mat).');
    return [substr($t, 0, $a), substr($t, $b + strlen('</main>'))];
}
function blog_e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
/** Ngày chỉnh sửa gần nhất (nếu có và sau ngày đăng), không thì ngày đăng. */
function blog_mod(array $p): string {
    $u = (string)($p['updated'] ?? '');
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $u) && $u > $p['date'] ? $u : $p['date'];
}
function blog_vn_date(string $iso): string {
    $p = explode('-', $iso);
    return count($p) === 3 ? ((int)$p[2] . '/' . (int)$p[1] . '/' . $p[0]) : $iso;
}
function blog_head(string $prefix, string $title, string $desc, string $path, string $ogType, string $ogImage, array $ld, string $seoTitle = '', string $extraMeta = ''): string {
    $t = $seoTitle !== '' ? $seoTitle : $title;
    if (blog_len($t) + 20 <= 60) $t .= ' | VF Wild ChungAuto';
    $url = BLOG_SITE . $path;
    $sub = function (string $pattern, string $repl) use (&$prefix) {
        $prefix = preg_replace_callback($pattern, fn() => $repl, $prefix, 1) ?? $prefix;
    };
    $sub('#<title>.*?</title>#s', '<title>' . blog_e($t) . '</title>');
    $sub('#<meta name="description" content="[^"]*">#', '<meta name="description" content="' . blog_e($desc) . '">');
    $sub('#<link rel="canonical" href="[^"]*">#', '<link rel="canonical" href="' . $url . '"><link rel="alternate" type="application/rss+xml" title="Blog VF Wild ChungAuto" href="/blog/feed.xml">');
    $sub('#<meta property="og:type" content="[^"]*">#', '<meta property="og:type" content="' . $ogType . '">');
    $sub('#<meta property="og:title" content="[^"]*">#', '<meta property="og:title" content="' . blog_e($title) . '">');
    $sub('#<meta property="og:description" content="[^"]*">#', '<meta property="og:description" content="' . blog_e($desc) . '">');
    $sub('#<meta property="og:url" content="[^"]*">#', '<meta property="og:url" content="' . $url . '">' . $extraMeta);
    $sub('#<meta property="og:image" content="[^"]*">#', '<meta property="og:image" content="' . blog_e($ogImage) . '">');
    if ($ogImage !== BLOG_OG_DEFAULT) {   // kích thước và mô tả của ảnh mặc định không đúng với ảnh riêng của bài
        $prefix = preg_replace('#<meta property="og:image:(width|height|alt)" content="[^"]*">#', '', $prefix) ?? $prefix;
    }
    if (preg_match('#(<script type="application/ld\+json">)(.*?)(</script>)#s', $prefix, $m, PREG_OFFSET_CAPTURE)) {
        $data = json_decode($m[2][0], true);
        if (is_array($data) && isset($data['@graph'])) {
            $data['@graph'] = array_values(array_filter($data['@graph'], fn($n) => ($n['@type'] ?? '') !== 'BreadcrumbList'));
            foreach ($ld as $n) $data['@graph'][] = $n;
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
            $prefix = substr($prefix, 0, $m[2][1]) . $json . substr($prefix, $m[2][1] + strlen($m[2][0]));
        }
    }
    return $prefix;
}
function blog_crumbs(array $items): array {
    $l = [];
    foreach ($items as $i => [$n, $u]) $l[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $n, 'item' => BLOG_SITE . $u];
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $l];
}
function blog_card(array $p, bool $lead = false): string {
    $img = $p['cover'] !== '' ? $p['cover'] : '/img/og/vf-wild-chungauto.jpg';
    $mins = max(1, (int)ceil(($p['words'] ?? blog_words((string)$p['body'])) / 200));
    return '<a class="blog-card' . ($lead ? ' blog-card--lead' : '') . '" href="/blog/' . $p['slug'] . '/"><span class="blog-card__img"><img src="' . blog_e($img) . '" alt="' . blog_e($p['cover_alt'] !== '' ? $p['cover_alt'] : $p['title']) . '" width="800" height="500" loading="' . ($lead ? 'eager' : 'lazy') . '" decoding="async"></span>'
        . '<span class="blog-card__body"><span class="blog-card__meta">' . blog_e($p['category']) . ' · ' . blog_vn_date($p['date']) . ' · ' . $mins . ' phút đọc</span><h3>' . blog_e($p['title']) . '</h3><p>' . blog_e($p['description']) . '</p>'
        . '<span class="blog-card__more">Đọc bài →</span></span></a>';
}

/* ---------- Trang danh sách: phân trang + trang chuyên mục ---------- */
const BLOG_RESERVED = ['trang', 'chuyen-muc'];   // không được dùng làm đường dẫn bài viết
function blog_per_page(): int { $e = getenv('VFW_BLOG_PER_PAGE'); return is_string($e) && ctype_digit($e) && (int)$e >= 1 ? (int)$e : 12; }
function blog_cat_slug(string $c): string { $s = blog_slugify($c); return $s !== '' ? $s : 'khac'; }
function blog_cat_url(string $c): string { return '/blog/chuyen-muc/' . blog_cat_slug($c) . '/'; }
/** Địa chỉ trang $n của một danh sách (trang 1 là địa chỉ gốc). */
function blog_page_url(string $base, int $n): string { return $n <= 1 ? $base : $base . 'trang/' . $n . '/'; }
function blog_rm_tree(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$dir/$f";
        if (is_dir($p) && !is_link($p)) blog_rm_tree($p); else @unlink($p);
    }
    @rmdir($dir);
}
function blog_pager_html(string $base, int $page, int $pages): string {
    if ($pages <= 1) return '';
    $show = [];
    for ($i = 1; $i <= $pages; $i++) { if ($i === 1 || $i === $pages || abs($i - $page) <= 2) $show[] = $i; }
    $h = '<nav class="blog-pager" aria-label="Phân trang">';
    if ($page > 1) $h .= '<a rel="prev" href="' . blog_page_url($base, $page - 1) . '">← Trước</a>';
    $last = 0;
    foreach ($show as $i) {
        if ($last && $i - $last > 1) $h .= '<span class="gap">…</span>';
        $h .= $i === $page ? '<span aria-current="page">' . $i . '</span>' : '<a href="' . blog_page_url($base, $i) . '">' . $i . '</a>';
        $last = $i;
    }
    if ($page < $pages) $h .= '<a rel="next" href="' . blog_page_url($base, $page + 1) . '">Sau →</a>';
    return $h . '</nav>';
}
/** Thanh chọn chuyên mục ở đầu trang danh sách. $counts = [tên chuyên mục => số bài]. */
function blog_cat_nav(array $counts, int $total, string $active): string {
    $h = '<nav class="blog-cats" aria-label="Chuyên mục"><a href="/blog/"' . ($active === '' ? ' class="is-on" aria-current="page"' : '') . '>Tất cả <span>(' . $total . ')</span></a>';
    foreach ($counts as $c => $n) $h .= '<a href="' . blog_cat_url($c) . '"' . ($active === $c ? ' class="is-on" aria-current="page"' : '') . '>' . blog_e($c) . ' <span>(' . $n . ')</span></a>';
    return $h . '</nav>';
}
/** Tạo trang /blog/, các trang phân trang và trang từng chuyên mục. Trả về danh sách địa chỉ (không gồm /blog/) để đưa vào sơ đồ trang web. */
function blog_list_pages(string $prefix, string $suffix, string $web, array $posts): array {
    $per = blog_per_page();
    foreach (['trang', 'chuyen-muc'] as $d) blog_rm_tree("$web/blog/$d");   // hai thư mục này do hệ thống quản lý, tạo lại hoàn toàn
    $byCat = [];
    foreach ($posts as $p) { $c = trim((string)$p['category']) !== '' ? trim((string)$p['category']) : 'Khác'; $byCat[$c][] = $p; }
    $counts = array_map('count', $byCat);
    arsort($counts); $counts = array_slice($counts, 0, 12, true);
    $groups = [['name' => '', 'base' => '/blog/', 'posts' => $posts]];
    foreach ($counts as $c => $n) $groups[] = ['name' => $c, 'base' => blog_cat_url($c), 'posts' => $byCat[$c]];
    $urls = [];
    foreach ($groups as $g) {
        $total = count($g['posts']); $pages = max(1, (int)ceil($total / $per));
        for ($n = 1; $n <= $pages; $n++) {
            $slice = array_slice($g['posts'], ($n - 1) * $per, $per);
            $path = blog_page_url($g['base'], $n);
            $name = $g['name'];
            $suf = $n > 1 ? " – trang $n" : '';
            $title = ($name === '' ? 'Blog VinFast VF Wild – giá, so sánh, thủ tục' : $name . ' – Blog VinFast VF Wild') . $suf;
            $desc = $name === ''
                ? 'Tin tức, giá, so sánh và hướng dẫn mua VinFast VF Wild từ ChungAuto: giá sau ưu đãi, chi phí vận hành, thủ tục đăng ký.'
                : "Các bài viết chuyên mục $name về VinFast VF Wild từ ChungAuto: số liệu rõ nguồn, cập nhật thường xuyên.";
            if ($n > 1) $desc = "Trang $n/$pages. " . $desc;
            $crumbs = [['Trang chủ', '/'], ['Blog', '/blog/']];
            if ($name !== '') $crumbs[] = [$name, $g['base']];
            $items = [];
            foreach ($slice as $i => $p) $items[] = ['@type' => 'ListItem', 'position' => ($n - 1) * $per + $i + 1, 'url' => BLOG_SITE . '/blog/' . $p['slug'] . '/'];
            $extra = ($n > 1 ? '<link rel="prev" href="' . BLOG_SITE . blog_page_url($g['base'], $n - 1) . '">' : '') . ($n < $pages ? '<link rel="next" href="' . BLOG_SITE . blog_page_url($g['base'], $n + 1) . '">' : '');
            $head = blog_head($prefix, $title, $desc, $path, 'website', BLOG_OG_DEFAULT, [blog_crumbs($crumbs), ['@type' => 'ItemList', 'itemListElement' => $items]], '', $extra);
            $cards = '';
            foreach ($slice as $i => $p) $cards .= blog_card($p, $n === 1 && $i === 0 && count($slice) >= 3);
            $crumbHtml = '<a href="/">Trang chủ</a> › ' . ($name === '' ? 'Blog' : '<a href="/blog/">Blog</a> › ' . blog_e($name));
            $h1 = $name === '' ? 'Blog VinFast VF Wild' : blog_e($name);
            $intro = $name === '' ? 'Giá, so sánh, chi phí vận hành và thủ tục mua xe – ngắn gọn, số liệu rõ nguồn.' : "$total bài viết trong chuyên mục $name.";
            $body = "\n<main id=\"main\">\n\n<section class=\"page-hero\"><div class=\"container\"><p class=\"breadcrumb\">$crumbHtml</p><h1>$h1</h1><p>" . blog_e($intro) . ($n > 1 ? " (Trang $n/$pages)" : '') . '</p></div></section>' . "\n\n"
                . '<section class="section"><div class="container">' . blog_cat_nav($counts, count($posts), $name)
                . ($cards !== '' ? '<div class="blog-grid">' . $cards . '</div>' : '<p>Chưa có bài viết.</p>') . blog_pager_html($g['base'], $n, $pages) . '</div></section>' . "\n\n</main>";
            $file = $web . $path . 'index.html';
            if (!is_dir(dirname($file))) @mkdir(dirname($file), 0755, true);
            blog_atomic($file, $head . $body . $suffix);
            if (!($g['base'] === '/blog/' && $n === 1)) $urls[] = $path;
        }
    }
    return $urls;
}

/* ---------- Gợi ý SEO: mục lục, câu hỏi thường gặp (FAQ), bài liên quan, lời kêu gọi theo chủ đề ---------- */
/** Chèn mục lục (các H2) ngay sau đoạn mở đầu nếu bài có từ 3 mục H2 trở lên. */
function blog_toc_insert(string $html): string {
    if (!preg_match_all('#<h2 id="([^"]+)">(.*?)</h2>#su', $html, $m, PREG_SET_ORDER) || count($m) < 3) return $html;
    $li = '';
    foreach ($m as $x) $li .= '<li><a href="#' . $x[1] . '">' . strip_tags($x[2]) . '</a></li>';
    $toc = '<nav class="post__toc" aria-label="Mục lục"><strong>Mục lục</strong><ol>' . $li . '</ol></nav>';
    $pos = strpos($html, '</p>');
    return $pos === false ? $toc . $html : substr_replace($html, "</p>\n" . $toc, $pos, 4);
}
/** Từ phần "## Câu hỏi thường gặp" (mỗi câu hỏi là một "### …" kèm đoạn trả lời) tạo dữ liệu FAQPage cho Google. */
function blog_faq_ld(string $body): ?array {
    $lines = preg_split('/\r?\n/', $body) ?: [];
    $in = false; $q = null; $a = []; $items = [];
    $plain = fn(string $s) => trim(preg_replace('/\s+/u', ' ', preg_replace(['/!?\[([^\]]*)\]\([^)]*\)/u', '/[*_`>#|]+/u'], ['$1', ' '], strip_tags($s)) ?? $s) ?? $s);
    $flush = function () use (&$q, &$a, &$items, $plain) {
        if ($q !== null && $a) { $ans = mb_substr($plain(implode(' ', $a)), 0, 700); if ($ans !== '') $items[] = ['@type' => 'Question', 'name' => $plain($q), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $ans]]; }
        $q = null; $a = [];
    };
    foreach ($lines as $ln) {
        if (preg_match('/^##\s+(.*)$/u', $ln, $m)) { $flush(); $in = (bool)preg_match('/câu hỏi thường gặp|hỏi đáp|\bfaq\b/iu', $m[1]); continue; }
        if (!$in) continue;
        if (preg_match('/^###\s+(.*)$/u', $ln, $m)) { $flush(); $q = $m[1]; continue; }
        if (preg_match('/^\*\*(?:\d+[.)]\s*)?(.+?\?)\*\*\s*$/u', $ln, $m)) { $flush(); $q = $m[1]; continue; }
        if (preg_match('/^[-*]\s+\*\*(.+?)\*\*\s*(.*)$/u', $ln, $m)) { $flush(); $q = $m[1]; if (trim($m[2]) !== '') $a[] = $m[2]; continue; }
        if ($q !== null && trim($ln) !== '') $a[] = $ln;
    }
    $flush();
    return count($items) >= 2 ? ['@type' => 'FAQPage', 'mainEntity' => array_slice($items, 0, 10)] : null;
}
/** Bài liên quan: ưu tiên cùng chủ đề (trùng từ khóa trong tiêu đề, thẻ, chuyên mục), không đủ thì lấy bài mới nhất. */
function blog_related(array $p, array $posts, int $n = 3): array {
    $stop = ['vf', 'wild', 'vinfast', 'xe', 'la', 'va', 'cua', 'cho', 'nhung', 'cac', 'mot', 'co', 'khong', 'nao', 'gi', 'bao', 'nhieu'];
    $tok = function (array $x) use ($stop): array {
        $w = array_unique(explode('-', blog_slugify($x['title'] . ' ' . ($x['tags'] ?? ''))));
        return array_values(array_filter($w, fn($t) => strlen($t) > 2 && !in_array($t, $stop, true)));
    };
    $a = $tok($p);
    $scored = [];
    foreach ($posts as $i => $q) {
        if ($q['slug'] === $p['slug']) continue;
        $b = $tok($q);
        $u = count(array_unique(array_merge($a, $b)));
        $s = $u ? count(array_intersect($a, $b)) / $u : 0.0;
        if (($q['category'] ?? '') === ($p['category'] ?? '') && $p['category'] !== '') $s += 0.1;
        $scored[] = [$s, -$i, $q];   // cùng điểm thì bài mới hơn (đứng trước trong $posts) lên trước
    }
    usort($scored, fn($x, $y) => [$y[0], $y[1]] <=> [$x[0], $x[1]]);
    return array_map(fn($r) => $r[2], array_slice($scored, 0, $n));
}
/** Lời kêu gọi cuối bài theo chuyên mục (bài phụ kiện nhận cả theo tiêu đề, vì có bài nằm ở "Tin tức"): [tiêu đề, mô tả, nhãn nút, mục đích form, [liên kết phụ…]]. */
function blog_cta(string $category, string $title = ''): array {
    $c = mb_strtolower($category, 'UTF-8');
    $acc = '/phụ kiện|độ xe|thảm|ốp |nắp thùng|lót thùng|lều nóc|tời |ống thở|giảm xóc|mâm|body ?kit|thanh thể thao|bảo vệ pin|phim cách nhiệt|dán phim|ppf|camera|cảm biến|decal|độ đèn|bi led|bi gầm|độ loa|bọc ghế|ghế điện|vô lăng|android box|màn hình android|hud/u';
    if (preg_match('/phụ kiện|độ xe/u', $c) || (!preg_match('/giá|chi phí|thủ tục|so sánh/u', $c) && preg_match($acc, mb_strtolower($title, 'UTF-8')))) return [$title === '' || preg_match('/vf\s*wild/iu', $title) || !preg_match('/honda|toyota|hyundai|kia|mazda|ford|mitsubishi|nissan|suzuki|mg |vf\s?[3-9]\b|limo|minio/iu', $title) ? 'Nhận báo giá phụ kiện cho VF Wild' : 'Nhận báo giá phụ kiện và lắp đặt','Tư vấn viên ChungAuto gửi mẫu phù hợp, báo giá lắp đặt và hẹn lịch lắp tại showroom gần bạn.', 'Nhận báo giá lắp đặt →', 'tu-van', [['Xem phụ kiện ô tô tại chungauto.vn', 'https://chungauto.vn/'], ['Danh sách showroom ChungAuto', '/lai-thu-vf-wild/']]];
    if (preg_match('/chi phí|thủ tục/u', $c)) return ['Tính trả góp và chi phí lăn bánh', 'Tư vấn viên ChungAuto gửi bảng trả góp và tổng chi phí lăn bánh theo tỉnh của bạn.', 'Nhận bảng tính →', 'tra-gop', [['Xem trang tính trả góp', '/tra-gop-vf-wild/'], ['Giá lăn bánh theo tỉnh', '/gia-lan-banh-vf-wild/']]];
    if (preg_match('/so sánh|công nghệ/u', $c)) return ['Đặt lịch lái thử VF Wild', 'Tự cảm nhận vận hành REEV và thùng hàng ngay tại showroom ChungAuto gần bạn.', 'Đăng ký lái thử →', 'tu-van', [['Xem trang lái thử', '/lai-thu-vf-wild/'], ['Giá lăn bánh theo tỉnh', '/gia-lan-banh-vf-wild/']]];
    return ['Nhận báo giá VF Wild', 'Tư vấn viên ChungAuto gửi giá sau ưu đãi, chi phí lăn bánh và phương án trả góp.', 'Nhận báo giá →', 'bao-gia', [['Cách đặt cọc online', '/dat-coc-vf-wild/']]];
}

function blog_post_html(array $p, array $posts, string $prefix, string $suffix, bool $preview = false): string {
    $p['words'] = $p['words'] ?? blog_words((string)$p['body']);
    $path = '/blog/' . $p['slug'] . '/';
    $cover = BLOG_SITE . $p['cover'];
    $ogImg = $p['cover'] !== '' ? BLOG_SITE . preg_replace('/-800\.jpg$/', '-1600.jpg', $p['cover']) : BLOG_OG_DEFAULT;
    $mins = max(1, (int)ceil($p['words'] / 200));
    $ld = [
        ['@type' => 'BlogPosting', 'headline' => $p['title'], 'description' => $p['description'], 'datePublished' => $p['date'],
         'dateModified' => blog_mod($p), 'inLanguage' => 'vi', 'image' => $ogImg, 'mainEntityOfPage' => BLOG_SITE . $path,
         'author' => ['@type' => 'Organization', 'name' => 'ChungAuto', 'url' => BLOG_SITE . '/ve-chung-toi/'],
         'publisher' => ['@type' => 'Organization', 'name' => 'Công ty Cổ phần Chung Group']],
        blog_crumbs(array_merge([['Trang chủ', '/'], ['Blog', '/blog/']], $p['category'] !== '' ? [[$p['category'], blog_cat_url($p['category'])]] : [], [[$p['title'], $path]])),
    ];
    if ($faq = blog_faq_ld((string)$p['body'])) $ld[] = $faq;
    $vid = blog_video_parse((string)($p['video'] ?? ''));
    if ($vid) {
        $vd = ['@type' => 'VideoObject', 'name' => $p['title'], 'description' => $p['description'] !== '' ? $p['description'] : $p['title'], 'thumbnailUrl' => [($vid['thumb'] ?? '') !== '' ? $vid['thumb'] : $ogImg],
               'uploadDate' => $p['date'] . 'T08:00:00+07:00'];
        if ($vid['type'] === 'file') $vd['contentUrl'] = strncmp($vid['url'], '/', 1) === 0 ? BLOG_SITE . $vid['url'] : $vid['url']; else $vd['embedUrl'] = $vid['embed'];
        $ld[] = $vd;
    }
    $meta = '<meta property="article:published_time" content="' . $p['date'] . 'T08:00:00+07:00"><meta property="article:modified_time" content="' . blog_mod($p) . 'T08:00:00+07:00"><meta property="article:section" content="' . blog_e($p['category']) . '">';
    $tags = blog_tags_list($p);
    foreach ($tags as $tg) $meta .= '<meta property="article:tag" content="' . blog_e($tg) . '">';
    if ($tags) $ld[0]['keywords'] = implode(', ', $tags);
    $head = blog_head($prefix, $p['title'], $p['description'], $path, 'article', $ogImg, $ld, $p['seo_title'], $meta);
    $related = '';
    foreach (blog_related($p, $posts, 3) as $q) $related .= blog_card($q);
    [$ctaT, $ctaD, $ctaB, $ctaI, $ctaL] = blog_cta((string)$p['category'], (string)$p['title']);
    $ctaMore = '';
    foreach ($ctaL as [$lt, $lu]) $ctaMore .= '<a class="post__more" href="' . $lu . '">' . blog_e($lt) . ' →</a>';
    $alt = blog_e($p['cover_alt'] !== '' ? $p['cover_alt'] : $p['title']);
    $main = "\n<main id=\"main\">\n\n<section class=\"page-hero\"><div class=\"container\"><p class=\"breadcrumb\"><a href=\"/\">Trang chủ</a> › <a href=\"/blog/\">Blog</a> › " . ($p['category'] !== '' ? '<a href="' . blog_cat_url($p['category']) . '">' . blog_e($p['category']) . '</a> › ' : '') . blog_e($p['title']) . '</p><h1>' . blog_e($p['title']) . '</h1>'
        . '<p class="post__meta">' . ($p['category'] !== '' ? '<a href="' . blog_cat_url($p['category']) . '">' . blog_e($p['category']) . '</a>' : '') . ' · ' . blog_vn_date($p['date']) . (blog_mod($p) !== $p['date'] ? ' · Cập nhật ' . blog_vn_date(blog_mod($p)) : '') . ' · ' . $mins . ' phút đọc</p><p class="post__by">Tác giả: <a href="/ve-chung-toi/">ChungAuto</a></p></div></section>' . "\n\n"
        . '<section class="section"><div class="container"><article class="prose post">'
        . ($p['cover'] !== '' ? '<img class="post__cover" src="' . blog_e($p['cover']) . '" alt="' . $alt . '"' . (($p['cover_title'] ?? '') !== '' ? ' title="' . blog_e($p['cover_title']) . '"' : '') . ' width="800" height="500" decoding="async">' : '') . "\n"
        . blog_toc_insert(blog_video_place(blog_md($p['body']), $vid ? blog_video_html($vid, $p['title'], $p['cover'] !== '' ? $p['cover'] : '') : null)) . "\n"
        . ($tags ? '<p class="post__tags"><span>Thẻ:</span> ' . implode(' ', array_map(fn($tg) => '<span class="tag">' . blog_e($tg) . '</span>', $tags)) . '</p>' : '')
        . '<div class="post__cta"><h2>' . blog_e($ctaT) . '</h2><p>' . blog_e($ctaD) . '</p>'
        . '<a href="#dang-ky" class="btn btn--primary" data-open-lead="blog" data-set-intent="' . $ctaI . '">' . blog_e($ctaB) . '</a>' . $ctaMore . '</div></article>'
        . ($related !== '' ? '<aside class="post__related"><h2>Bài viết khác</h2><div class="blog-grid">' . $related . '</div></aside>' : '')
        . "</div></section>\n\n</main>";
    $html = $head . $main . $suffix;
    if ($preview) $html = preg_replace('#<meta name="robots" content="[^"]*">#', '<meta name="robots" content="noindex, nofollow">', $html, 1) ?? $html;
    return $html;
}

/** Nguồn cấp RSS 2.0 cho 20 bài mới nhất. */
function blog_rss(array $posts): string {
    $x = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $items = '';
    foreach (array_slice($posts, 0, 20) as $p) {
        $u = BLOG_SITE . '/blog/' . $p['slug'] . '/';
        $items .= '<item><title>' . $x($p['title']) . '</title><link>' . $u . '</link><guid isPermaLink="true">' . $u . '</guid>'
            . '<pubDate>' . gmdate('D, d M Y H:i:s', strtotime($p['date'] . ' 08:00:00 +0700')) . ' GMT</pubDate>'
            . ($p['category'] !== '' ? '<category>' . $x($p['category']) . '</category>' : '')
            . '<description>' . $x($p['description']) . '</description></item>' . "\n";
    }
    $built = $posts ? gmdate('D, d M Y H:i:s', strtotime(blog_mod($posts[0]) . ' 08:00:00 +0700')) . ' GMT' : gmdate('D, d M Y H:i:s') . ' GMT';
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>' . "\n"
        . '<title>Blog VF Wild ChungAuto</title><link>' . BLOG_SITE . '/blog/</link>'
        . '<description>Giá, chi phí lăn bánh, so sánh và kinh nghiệm mua VinFast VF Wild do ChungAuto biên soạn.</description>'
        . '<language>vi</language><lastBuildDate>' . $built . '</lastBuildDate>'
        . '<atom:link href="' . BLOG_SITE . '/blog/feed.xml" rel="self" type="application/rss+xml"/>' . "\n" . $items . '</channel></rss>' . "\n";
}

/* ---------- Tạo lại toàn bộ trang blog ---------- */
function blog_rebuild(string $DIR): array {
    [$prefix, $suffix] = blog_template();
    $web = blog_web();
    $posts = array_values(array_filter(blog_all($DIR), fn($p) => blog_state($p) === 'published' && $p['title'] !== ''));
    $root = "$web/blog";
    if (!is_dir($root)) @mkdir($root, 0755, true);

    // trang danh sách, phân trang và trang chuyên mục
    $listUrls = blog_list_pages($prefix, $suffix, $web, $posts);

    // từng bài
    foreach ($posts as $p) {
        $d = "$root/" . $p['slug'];
        if (!is_dir($d)) @mkdir($d, 0755, true);
        blog_atomic("$d/index.html", blog_post_html($p, $posts, $prefix, $suffix));
    }

    // xóa trang của bài đã gỡ / chuyển nháp / xóa
    $keep = array_column($posts, 'slug');
    foreach (glob("$root/*", GLOB_ONLYDIR) ?: [] as $dir) {
        $s = basename($dir);
        if (!in_array($s, $keep, true) && !in_array($s, BLOG_RESERVED, true) && preg_match('/^[a-z0-9][a-z0-9-]*$/', $s) && is_file("$dir/index.html")) {
            @unlink("$dir/index.html");
            @rmdir($dir);
        }
    }

    // latest.json (trang chủ đọc file này để hiện dải "Bài viết mới")
    $latest = array_map(fn($p) => ['slug' => $p['slug'], 'title' => $p['title'], 'category' => $p['category'], 'date' => $p['date']], array_slice($posts, 0, 6));
    blog_atomic("$root/latest.json", json_encode($latest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

    // RSS (bản tin cho trình đọc tin và công cụ AI)
    blog_atomic("$root/feed.xml", blog_rss($posts));

    // sitemap
    $sm = "$web/sitemap.xml";
    if (is_file($sm)) {
        $x = (string)file_get_contents($sm);
        $x = preg_replace('#<url><loc>' . preg_quote(BLOG_SITE, '#') . '/blog/.*?</url>#s', '', $x) ?? $x;
        $urls = '';
        if ($posts) {
            $newest = max(array_map('blog_mod', $posts));
            $urls .= '<url><loc>' . BLOG_SITE . '/blog/</loc><lastmod>' . $newest . '</lastmod></url>';
            foreach ($listUrls as $lu) $urls .= '<url><loc>' . BLOG_SITE . $lu . '</loc><lastmod>' . $newest . '</lastmod></url>';
            foreach ($posts as $p) $urls .= '<url><loc>' . BLOG_SITE . '/blog/' . $p['slug'] . '/</loc><lastmod>' . blog_mod($p) . '</lastmod></url>';
        }
        blog_atomic($sm, str_replace('</urlset>', $urls . '</urlset>', $x));
    }
    return ['posts' => count($posts)];
}

/* ---------- Ảnh tải lên (đã được trình duyệt resize; máy chủ không có thư viện GD) ---------- */
function blog_upload(): array {
    $name = (string)($_POST['name'] ?? '');
    $f = $_FILES['file'] ?? null;
    if (!preg_match('/^[a-z0-9](?:[a-z0-9]|-(?!-)){5,90}-(800|1200|1600)$/', $name)) return ['error' => 'Tên ảnh không hợp lệ.'];   // tên theo nội dung (ghe-dien-honda-city-3f9a12-1200) hoặc mã cũ (b8c339…-1200); chỉ chữ thường, số, gạch ngang
    if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return ['error' => 'Không nhận được ảnh (có thể ảnh quá lớn).'];
    if ($f['size'] > BLOG_MAX_IMG) return ['error' => 'Ảnh sau khi nén vẫn lớn hơn 700 KB.'];
    $info = @getimagesize($f['tmp_name']);
    if (!$info || $info[2] !== IMAGETYPE_JPEG) return ['error' => 'Chỉ nhận ảnh JPEG.'];
    if ($info[0] > 2400 || $info[1] > 2400 || $info[0] < 16 || $info[1] < 16) return ['error' => 'Kích thước ảnh không hợp lệ.'];
    $dir = blog_web() . '/img/blog';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return ['error' => 'Không tạo được thư mục ảnh.'];
    $dest = "$dir/$name.jpg";
    if (!move_uploaded_file($f['tmp_name'], $dest)) return ['error' => 'Không lưu được ảnh.'];
    @chmod($dest, 0644);
    return ['ok' => true, 'url' => "/img/blog/$name.jpg", 'w' => $info[0], 'h' => $info[1]];
}

/* ---------- Xem trước: dựng đúng trang thật, bọc trong khung chuyển Máy tính / Điện thoại ---------- */
function blog_preview_wrapper(string $pageHtml, string $title): string {
    $src = htmlspecialchars($pageHtml, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $css = 'html,body{height:100%}body{margin:0;background:#1f2a25;font:14px/1.4 -apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#fff}'
        . '.pv{position:sticky;top:0;z-index:5;height:52px;display:flex;align-items:center;gap:1rem;padding:0 16px;background:#0c1f17;border-bottom:1px solid #2f4a3d}'
        . '.pv__l{display:flex;flex-direction:column;min-width:0;flex:1}.pv__l b{font-size:14px}.pv__l span{font-size:12px;color:#9fb8ab;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
        . '.pv__m{display:flex;gap:4px;background:#17332a;padding:3px;border-radius:10px}'
        . '.pv button{font:inherit;font-weight:600;color:#c9dcd2;background:none;border:0;border-radius:8px;padding:.4rem .9rem;cursor:pointer}'
        . '.pv__m button.on{background:#fff;color:#06633f}.pv .pv__x{border:1px solid #2f4a3d;color:#fff}'
        . '.stage{display:flex;justify-content:center;height:calc(100% - 52px)}'
        . 'iframe{width:100%;height:100%;border:0;background:#fff;transition:width .2s}'
        . '.stage.fit iframe{box-shadow:0 0 0 1px #2f4a3d,0 20px 60px -20px #000;margin:12px 0;height:calc(100% - 24px);border-radius:12px}'
        . '@media(max-width:720px){.pv__l span{display:none}.pv button{padding:.4rem .6rem}}';
    $js = "(function(){var f=document.getElementById('f'),st=document.getElementById('st');"
        . "document.querySelectorAll('[data-w]').forEach(function(b){b.addEventListener('click',function(){"
        . "document.querySelectorAll('[data-w]').forEach(function(x){x.classList.toggle('on',x===b)});"
        . "var w=b.getAttribute('data-w');f.style.width=w;st.classList.toggle('fit',w!=='100%');f.style.maxWidth='100%';});});"
        . "document.getElementById('x').addEventListener('click',function(){window.close();});})();";
    return '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>Xem trước – ' . blog_e($title) . '</title><style>' . $css . '</style></head><body>'
        . '<div class="pv"><div class="pv__l"><b>Xem trước bài viết</b><span>Bản chưa đăng · ' . blog_e($title) . '</span></div>'
        . '<div class="pv__m"><button type="button" class="on" data-w="100%">Máy tính</button><button type="button" data-w="820px">Máy tính bảng</button><button type="button" data-w="390px">Điện thoại</button></div>'
        . '<button type="button" class="pv__x" id="x">Đóng</button></div>'
        . '<div class="stage" id="st"><iframe id="f" title="Xem trước bài viết" srcdoc="' . $src . '"></iframe></div>'
        . '<script>' . $js . '</script></body></html>';
}

/** Kiểm tra dữ liệu một bài; trả về danh sách lỗi (rỗng = hợp lệ). */
function blog_validate_post(array $in, string $orig, string $dir): array {
    $errs = [];
    if ($in['title'] === '' || blog_len($in['title']) > 140) $errs[] = 'Tiêu đề bắt buộc, tối đa 140 ký tự.';
    if ($in['slug'] === '' && $in['title'] !== '') $errs[] = 'Đường dẫn không hợp lệ (hãy dùng chữ và số).';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['date']) || !strtotime($in['date'])) $errs[] = 'Ngày đăng không hợp lệ.';
    if (blog_len($in['description']) > 300) $errs[] = 'Mô tả quá dài.';
    if ($in['cover'] !== '' && (!preg_match('#^/img/[A-Za-z0-9/_.-]+\.(jpg|jpeg|png|webp)$#', $in['cover']) || strpos($in['cover'], '..') !== false)) $errs[] = 'Ảnh đại diện phải nằm trong /img/.';
    if (trim($in['body']) === '') $errs[] = 'Nội dung bài viết đang trống.';
    if (in_array($in['slug'], BLOG_RESERVED, true)) $errs[] = 'Đường dẫn "' . $in['slug'] . '" được dành riêng cho hệ thống, hãy đặt tên khác.';
    if ($in['slug'] !== '' && $in['slug'] !== $orig && is_file("$dir/{$in['slug']}.md")) $errs[] = 'Đường dẫn "' . $in['slug'] . '" đã có bài khác dùng.';
    if ($in['status'] === 'published' && $in['category'] === '') $errs[] = 'Chọn chuyên mục trước khi đăng.';
    if (trim((string)($in['video'] ?? '')) !== '' && blog_video_parse((string)$in['video']) === null) $errs[] = 'Link video chưa đúng: dùng link YouTube, Vimeo hoặc tệp .mp4/.webm (https).';
    if (blog_len((string)($in['cover_title'] ?? '')) > 160) $errs[] = 'Tiêu đề ảnh đại diện quá dài (tối đa 160 ký tự).';
    return $errs;
}

/** Ghi bài (giữ phiên bản cũ, đổi tên nếu đổi đường dẫn, thêm chuyên mục mới), tạo lại trang tĩnh và ghi nhật ký. */
function blog_store_post(string $DIR, array $in, string $orig = '', bool $touch = true): void {
    $dir = blog_dir($DIR);
    $in['updated'] = '';
    if ($orig !== '' && is_file("$dir/$orig.md")) {   // ngày cập nhật: giữ nguyên, chỉ đổi khi nội dung thật sự đổi (không tính liên kết nội bộ tự chèn)
        $old = blog_parse((string)file_get_contents("$dir/$orig.md")) ?: [];
        $in['updated'] = (string)($old['updated'] ?? '');
        if ($touch && ($old['status'] ?? '') !== 'draft' && $in['status'] !== 'draft') {
            foreach (['title', 'seo_title', 'description', 'body', 'cover', 'video'] as $k) {
                if (trim(str_replace("\r\n", "\n", (string)($old[$k] ?? ''))) !== trim(str_replace("\r\n", "\n", (string)($in[$k] ?? '')))) { $in['updated'] = date('Y-m-d'); break; }
            }
        }
    }
    if ($in['updated'] !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['updated']) || $in['updated'] <= $in['date'])) $in['updated'] = '';
    if ($orig !== '' && is_file("$dir/$orig.md")) {
        $vd = "$dir/_versions/$orig";
        if (!is_dir($vd)) @mkdir($vd, 0750, true);
        @copy("$dir/$orig.md", "$vd/" . date('Ymd-His') . '.md');
        foreach (array_slice(glob("$vd/*.md") ?: [], 0, -10) as $old) @unlink($old);
    }
    blog_atomic("$dir/{$in['slug']}.md", blog_serialize($in));
    if ($orig !== '' && $orig !== $in['slug'] && is_file("$dir/$orig.md")) {
        @unlink("$dir/$orig.md");
        if (is_dir("$dir/_versions/$orig") && !is_dir("$dir/_versions/{$in['slug']}")) @rename("$dir/_versions/$orig", "$dir/_versions/{$in['slug']}");
    }
    $stored = blog_cats_stored($DIR);
    if ($stored === null) blog_cats_save($DIR, blog_cats($DIR));
    elseif ($in['category'] !== '' && !in_array($in['category'], $stored, true)) { $stored[] = $in['category']; blog_cats_save($DIR, $stored); }
    blog_rebuild($DIR);
    blog_log($DIR, $orig === '' ? 'create' : 'update', $in['slug']);
    if (blog_state($in) === 'published') blog_indexnow($DIR, ['/blog/' . $in['slug'] . '/', '/blog/', '/blog/feed.xml']);
}

/** Báo cho Bing và các công cụ hỗ trợ IndexNow biết trang vừa đổi (cần tệp khóa <private>/indexnow.key + <web>/<khóa>.txt; thiếu thì bỏ qua). Lỗi mạng không ảnh hưởng việc lưu bài. */
function blog_indexnow(string $DIR, array $paths): void {
    $kf = "$DIR/indexnow.key";
    $key = is_file($kf) ? trim((string)file_get_contents($kf)) : '';
    if (!preg_match('/^[a-f0-9]{32}$/', $key) || !is_file(blog_web() . "/$key.txt")) return;
    $body = json_encode(['host' => parse_url(BLOG_SITE, PHP_URL_HOST), 'key' => $key, 'keyLocation' => BLOG_SITE . "/$key.txt",
        'urlList' => array_values(array_unique(array_map(fn($p) => BLOG_SITE . $p, $paths)))], JSON_UNESCAPED_SLASHES);
    $url = getenv('VFW_INDEXNOW_URL') ?: 'https://api.indexnow.org/indexnow';
    $code = 0;
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3]);
        curl_exec($c);
        $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json; charset=utf-8\r\n", 'content' => $body, 'timeout' => 5, 'ignore_errors' => true]]);
        @file_get_contents($url, false, $ctx);
        $code = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) ? (int)$m[1] : 0;
    }
    @file_put_contents(blog_dir($DIR) . '/indexnow.log', date('c') . " $code " . implode(' ', $paths) . "
", FILE_APPEND | LOCK_EX);
}

/* ---------- Xử lý thao tác POST (đã kiểm tra đăng nhập + CSRF ở index.php) ---------- */
/* ---------- Thẻ (tags) ---------- */
function blog_tags_norm(string $s): string {
    $out = [];
    foreach (preg_split('/[,\n]+/u', $s) ?: [] as $t) {
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? '');
        if ($t === '' || in_array(mb_strtolower($t, 'UTF-8'), array_map(fn($x) => mb_strtolower($x, 'UTF-8'), $out), true)) continue;
        $out[] = mb_substr($t, 0, 40);
        if (count($out) >= 10) break;
    }
    return implode(', ', $out);
}
function blog_tags_list(array $p): array {
    $s = trim((string)($p['tags'] ?? ''));
    return $s === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $s))));
}
function blog_tag_counts(array $posts): array {
    $c = [];
    foreach ($posts as $p) foreach (blog_tags_list($p) as $t) $c[$t] = ($c[$t] ?? 0) + 1;
    arsort($c);
    return $c;
}

/* ---------- Chuyên mục ---------- */
function blog_cats(string $DIR): array {
    $f = blog_dir($DIR) . '/categories.json';
    $list = [];
    if (is_file($f)) {
        $j = json_decode((string)file_get_contents($f), true);
        if (is_array($j)) $list = array_values(array_filter(array_map('strval', $j), fn($x) => $x !== ''));
    } else {
        $list = BLOG_CATS;
    }
    foreach (blog_all($DIR) as $p) { if ($p['category'] !== '' && !in_array($p['category'], $list, true)) $list[] = $p['category']; }
    return $list;
}
function blog_cats_stored(string $DIR): ?array {
    $f = blog_dir($DIR) . '/categories.json';
    if (!is_file($f)) return null;
    $j = json_decode((string)file_get_contents($f), true);
    return is_array($j) ? array_values(array_filter(array_map('strval', $j), fn($x) => $x !== '')) : null;
}
function blog_cats_save(string $DIR, array $list): void {
    blog_atomic(blog_dir($DIR) . '/categories.json', json_encode(array_values($list), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
}
function blog_cat_counts(string $DIR): array {
    $c = [];
    foreach (blog_all($DIR) as $p) if ($p['category'] !== '') $c[$p['category']] = ($c[$p['category']] ?? 0) + 1;
    return $c;
}

/* ---------- Trạng thái bài: Đã đăng / Lên lịch / Nháp ---------- */
function blog_state(array $p): string {
    if ($p['status'] === 'draft') return 'draft';
    return $p['date'] > date('Y-m-d') ? 'scheduled' : 'published';
}

/* ---------- Thư viện ảnh ---------- */
function blog_media_items(string $DIR): array {
    $web = blog_web();
    $groups = [];
    $scan = function (string $rel, bool $uploaded) use (&$groups, $web) {
        foreach (glob("$web$rel/*.jpg") ?: [] as $f) {
            if (!preg_match('/^(.+)-(800|1200|1600)\.jpg$/', basename($f), $m)) continue;
            $key = ($uploaded ? 'u:' : 'g:') . $m[1];
            if (!isset($groups[$key])) $groups[$key] = ['id' => $m[1], 'uploaded' => $uploaded, 'sizes' => [], 'mtime' => 0, 'bytes' => 0];
            $groups[$key]['sizes'][(int)$m[2]] = "$rel/" . basename($f);
            $groups[$key]['mtime'] = max($groups[$key]['mtime'], (int)filemtime($f));
            $groups[$key]['bytes'] += (int)filesize($f);
        }
    };
    $scan('/img/blog', true);
    $scan('/img/vf-wild/thuc-te', false);
    $posts = blog_all($DIR);
    foreach (glob(blog_dir($DIR) . '/_trash/*.md') ?: [] as $tf) { $tp = blog_parse((string)file_get_contents($tf)); if ($tp) $posts[] = $tp; }
    $items = [];
    foreach ($groups as $g) {
        ksort($g['sizes']);
        $urls = array_values($g['sizes']);
        $large = end($urls);
        $dim = @getimagesize($web . $large) ?: [0, 0];
        $used = 0;
        foreach ($posts as $p) {
            foreach ($urls as $u) { if ($p['cover'] === $u || strpos($p['body'], $u) !== false) { $used++; break; } }
        }
        $items[] = [
            'id' => $g['id'], 'uploaded' => $g['uploaded'],
            'label' => $g['uploaded'] ? 'Ảnh tải lên ' . date('d/m/Y H:i', $g['mtime']) : ucfirst(str_replace('-', ' ', $g['id'])),
            'thumb' => $g['sizes'][800] ?? $urls[0],
            'insert' => $g['sizes'][1200] ?? ($g['sizes'][1600] ?? $urls[0]),
            'cover' => $g['sizes'][800] ?? $urls[0],
            'w' => (int)$dim[0], 'h' => (int)$dim[1], 'bytes' => $g['bytes'], 'mtime' => $g['mtime'], 'used' => $used,
        ];
    }
    usort($items, fn($a, $b) => [$b['uploaded'], $b['mtime'], $a['id']] <=> [$a['uploaded'], $a['mtime'], $b['id']]);
    return $items;
}

/** Tạo lại blog nếu danh sách bài nên hiện khác với các trang đang có (bài lên lịch đến hạn). Rẻ: chỉ so sánh tên thư mục. */
function blog_rebuild_if_due(string $DIR): void {
    $want = [];
    foreach (blog_all($DIR) as $p) { if (blog_state($p) === 'published' && $p['title'] !== '') $want[] = $p['slug']; }
    $have = [];
    foreach (glob(blog_web() . '/blog/*', GLOB_ONLYDIR) ?: [] as $d) { if (is_file("$d/index.html")) $have[] = basename($d); }
    sort($want); sort($have);
    if ($want !== $have) blog_rebuild($DIR);
}

/* ---------- Dữ liệu bài gửi từ form ---------- */
function blog_input_from_post(): array {
    $in = [];
    foreach (BLOG_KEYS as $k) $in[$k] = trim((string)($_POST[$k] ?? ''));
    $in['body'] = str_replace("\r\n", "\n", (string)($_POST['body'] ?? ''));
    if ($in['video'] !== '' && ($vv = blog_video_parse($in['video']))) $in['video'] = $vv['type'] === 'file' ? $vv['url'] : $vv['url'];   // lưu dạng link chuẩn
    $in['slug'] = blog_slugify($in['slug'] !== '' ? $in['slug'] : $in['title']);
    $in['tags'] = blog_tags_norm($in['tags']);
    $newCat = trim((string)($_POST['new_category'] ?? ''));
    if ($newCat !== '') $in['category'] = mb_substr(preg_replace('/\s+/u', ' ', $newCat) ?? $newCat, 0, 40);
    $in['status'] = ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'published';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['date']) || !strtotime($in['date'])) $in['date'] = $in['date'] === '' ? date('Y-m-d') : $in['date'];
    return $in;
}
function blog_trash_file(string $dir, string $slug): string {
    $ts = time();
    while (is_file("$dir/_trash/$slug--" . date('Ymd-His', $ts) . '.md')) $ts++;
    return "$dir/_trash/$slug--" . date('Ymd-His', $ts) . '.md';
}
function blog_back(string $default = '/quan-tri/?p=blog'): string {
    $b = (string)($_POST['back'] ?? '');
    return (strncmp($b, '/quan-tri/?p=blog', 17) === 0 && !preg_match('/[\r\n]/', $b)) ? $b : $default;
}
function blog_with_ok(string $url, string $ok): string {
    $url = preg_replace('/([?&])ok=[^&]*&?/', '$1', $url) ?? $url;
    return rtrim($url, '?&') . (strpos($url, '?') === false ? '?' : '&') . 'ok=' . $ok;
}

/* ---------- Xử lý thao tác POST (đã kiểm tra đăng nhập + CSRF ở index.php) ---------- */
function blog_post(string $act, string $DIR): array {
    $dir = blog_dir($DIR);
    if (strncmp($act, 'blog_auto_', 10) === 0) return auto_post($act, $DIR);
    if (strncmp($act, 'blog_audit_', 11) === 0) return audit_post($act, $DIR);
    if (strncmp($act, 'blog_review_', 12) === 0) return review_post($act, $DIR);
    if (strncmp($act, 'blog_user_', 10) === 0) return users_post($act, $DIR);
    if (strncmp($act, 'blog_attach_', 12) === 0) return attach_post($act, $DIR);   // Gắn ảnh vào bài (blog-attach.php)
    $json = function (array $d) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit; };

    if ($act === 'blog_upload') $json(blog_upload());
    if ($act === 'blog_render') $json(['html' => blog_md((string)($_POST['body'] ?? ''))]);
    if ($act === 'blog_media_list') $json(['items' => blog_media_items($DIR)]);

    if ($act === 'blog_preview_page') {
        $in = blog_input_from_post();
        if ($in['slug'] === '') $in['slug'] = 'xem-truoc';
        if ($in['title'] === '') $in['title'] = '(Chưa có tiêu đề)';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['date'])) $in['date'] = date('Y-m-d');
        if ($in['category'] === '') $in['category'] = 'Chưa chọn chuyên mục';
        if ($in['cover'] !== '' && (!preg_match('#^/img/[A-Za-z0-9/_.-]+\.(jpg|jpeg|png|webp)$#', $in['cover']) || strpos($in['cover'], '..') !== false)) $in['cover'] = '';
        $in['words'] = blog_words($in['body']);
        $others = array_values(array_filter(blog_all($DIR), fn($q) => blog_state($q) === 'published' && $q['slug'] !== $in['slug']));
        [$prefix, $suffix] = blog_template();
        $html = blog_post_html($in, $others, $prefix, $suffix, true);
        header_remove('Content-Security-Policy');   // trang xem trước cần nạp CSS/JS của web
        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: SAMEORIGIN');
        echo blog_preview_wrapper($html, $in['title']);
        exit;
    }
    if ($act === 'blog_rebuild') {
        blog_rebuild($DIR);
        blog_log($DIR, 'rebuild', '*');
        return ['to' => '/quan-tri/?p=blog&ok=blog_rebuilt'];
    }

    /* --- hành động hàng loạt trên danh sách --- */
    if ($act === 'blog_bulk') {
        $slugs = array_values(array_filter(array_map('strval', (array)($_POST['slugs'] ?? [])), fn($s) => preg_match('/^[a-z0-9][a-z0-9-]{0,99}$/', $s)));
        $what = (string)($_POST['bulk'] ?? '');
        if (!empty($_POST['rowact'])) {   // dạng "hành_động:slug" từ liên kết dưới tiêu đề
            $ra = explode(':', (string)$_POST['rowact'], 2);
            $what = $ra[0];
            $slugs = preg_match('/^[a-z0-9][a-z0-9-]{0,119}$/', $ra[1] ?? '') ? [$ra[1]] : [];
        }
        $back = blog_back();
        if (!$slugs || !in_array($what, ['trash', 'publish', 'draft', 'restore', 'purge'], true)) return ['to' => blog_with_ok($back, 'blog_bulk_none')];
        $n = 0; $skipped = 0;
        foreach ($slugs as $s) {
            if ($what === 'restore' || $what === 'purge') {
                $f = basename($s);   // ở thùng rác, "slug" là tên file không có .md
                $file = "$dir/_trash/$f.md";
                if (!is_file($file)) continue;
                if ($what === 'purge') { @unlink($file); $n++; continue; }
                $base = preg_replace('/--\d{8}-\d{6}$/', '', $f);
                if (!is_file("$dir/$base.md")) { rename($file, "$dir/$base.md"); $n++; } else { $skipped++; }
                continue;
            }
            $p = blog_load($DIR, $s);
            if (!$p) continue;
            if ($what === 'trash') rename("$dir/$s.md", blog_trash_file($dir, $s));
            else {
                if ($what === 'publish' && $p['category'] === '') { $skipped++; continue; }   // bài chưa có chuyên mục thì chưa đăng được
                $p['status'] = $what === 'publish' ? 'published' : 'draft'; blog_atomic("$dir/$s.md", blog_serialize($p));
            }
            $n++;
        }
        blog_rebuild($DIR);
        blog_log($DIR, 'bulk-' . $what, $n . ' bài');
        return ['to' => blog_with_ok($back, $skipped > 0 ? 'blog_bulk_skipped' : 'blog_bulk_done')];
    }
    if ($act === 'blog_empty_trash') {
        foreach (glob("$dir/_trash/*.md") ?: [] as $f) @unlink($f);
        blog_log($DIR, 'purge', '*');
        return ['to' => '/quan-tri/?p=blog&ok=blog_purged'];
    }

    /* --- chuyên mục --- */
    if ($act === 'blog_cat_add' || $act === 'blog_cat_rename' || $act === 'blog_cat_delete') {
        $cats = blog_cats($DIR);
        $name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($_POST['name'] ?? '')) ?? ''), 0, 40);
        if ($act === 'blog_cat_add') {
            if ($name === '') return ['error' => 'Hãy nhập tên chuyên mục.'];
            if (in_array(mb_strtolower($name, 'UTF-8'), array_map(fn($c) => mb_strtolower($c, 'UTF-8'), $cats), true)) return ['to' => '/quan-tri/?p=cats&ok=cat_exists'];
            $cats[] = $name; blog_cats_save($DIR, $cats); blog_log($DIR, 'cat-add', $name);
            return ['to' => '/quan-tri/?p=cats&ok=cat_added'];
        }
        $old = (string)($_POST['old'] ?? '');
        if (!in_array($old, $cats, true)) return ['error' => 'Không tìm thấy chuyên mục.'];
        if ($act === 'blog_cat_delete') {
            if ((blog_cat_counts($DIR)[$old] ?? 0) > 0) return ['error' => 'Chuyên mục còn bài viết, hãy chuyển các bài sang chuyên mục khác trước.'];
            blog_cats_save($DIR, array_values(array_diff($cats, [$old]))); blog_log($DIR, 'cat-delete', $old);
            return ['to' => '/quan-tri/?p=cats&ok=cat_deleted'];
        }
        if ($name === '') return ['error' => 'Hãy nhập tên mới cho chuyên mục.'];
        if (mb_strtolower($name, 'UTF-8') !== mb_strtolower($old, 'UTF-8') && in_array(mb_strtolower($name, 'UTF-8'), array_map(fn($c) => mb_strtolower($c, 'UTF-8'), $cats), true)) return ['error' => 'Đã có chuyên mục tên này.'];
        foreach (blog_all($DIR) as $p) {
            if ($p['category'] === $old) { $p['category'] = $name; blog_atomic("$dir/{$p['slug']}.md", blog_serialize($p)); }
        }
        foreach (glob("$dir/_trash/*.md") ?: [] as $tf) {
            $tp = blog_parse((string)file_get_contents($tf));
            if ($tp && $tp['category'] === $old) { $tp['category'] = $name; blog_atomic($tf, blog_serialize($tp)); }
        }
        blog_cats_save($DIR, array_map(fn($c) => $c === $old ? $name : $c, $cats));
        blog_rebuild($DIR); blog_log($DIR, 'cat-rename', "$old → $name");
        return ['to' => '/quan-tri/?p=cats&ok=cat_renamed'];
    }

    /* --- ảnh --- */
    if ($act === 'blog_media_delete') {
        $id = (string)($_POST['id'] ?? '');
        if (!preg_match('/^[a-z0-9](?:[a-z0-9]|-(?!-)){5,90}$/', $id)) return ['error' => 'Chỉ xóa được ảnh đã tải lên.'];
        foreach (blog_media_items($DIR) as $it) {
            if ($it['uploaded'] && $it['id'] === $id) {
                if ($it['used'] > 0) return ['error' => 'Ảnh đang được dùng trong ' . $it['used'] . ' bài, hãy gỡ khỏi bài trước.'];
                foreach ([800, 1200, 1600] as $sz) { $f = blog_web() . "/img/blog/$id-$sz.jpg"; if (is_file($f)) @unlink($f); }
                blog_log($DIR, 'media-delete', $id);
                $mp = (int)($_POST['mp'] ?? 1); $mper = (int)($_POST['mper'] ?? 0);   // quay lại đúng trang đang xem của thư viện
                return ['to' => '/quan-tri/?p=media' . ($mp > 1 ? '&mp=' . $mp : '') . (in_array($mper, [24, 96], true) ? '&mper=' . $mper : '') . '&ok=media_deleted'];
            }
        }
        return ['error' => 'Không tìm thấy ảnh.'];
    }

    /* --- từng bài: đăng / về nháp / xóa / khôi phục --- */
    if ($act === 'blog_delete' || $act === 'blog_restore' || $act === 'blog_purge' || $act === 'blog_toggle') {
        $slug = (string)($_POST['slug'] ?? '');
        $back = blog_back();
        if ($act === 'blog_restore') {
            $f = basename((string)($_POST['file'] ?? ''));
            if (!preg_match('/^[a-z0-9-]+--\d{8}-\d{6}\.md$/', $f) || !is_file("$dir/_trash/$f")) return ['error' => 'Không tìm thấy bản trong thùng rác.'];
            $s = preg_replace('/--\d{8}-\d{6}\.md$/', '', $f);
            $target = "$dir/$s.md";
            if (is_file($target)) return ['error' => 'Đã có bài khác dùng đường dẫn này; hãy xóa hoặc đổi tên trước.'];
            rename("$dir/_trash/$f", $target);
            blog_rebuild($DIR); blog_log($DIR, 'restore', $s);
            return ['to' => blog_with_ok($back, 'blog_restored')];
        }
        if ($act === 'blog_purge') {
            $f = basename((string)($_POST['file'] ?? ''));
            if (preg_match('/^[a-z0-9-]+--\d{8}-\d{6}\.md$/', $f) && is_file("$dir/_trash/$f")) { @unlink("$dir/_trash/$f"); blog_log($DIR, 'purge', $f); }
            return ['to' => blog_with_ok($back, 'blog_purged')];
        }
        $p = blog_load($DIR, $slug);
        if (!$p) return ['error' => 'Không tìm thấy bài viết.'];
        if ($act === 'blog_toggle') {
            $p['status'] = $p['status'] === 'published' ? 'draft' : 'published';
            blog_atomic("$dir/$slug.md", blog_serialize($p));
            blog_rebuild($DIR); blog_log($DIR, $p['status'] === 'published' ? 'publish' : 'unpublish', $slug);
            return ['to' => blog_with_ok($back, $p['status'] === 'published' ? 'blog_published' : 'blog_drafted')];
        }
        rename("$dir/$slug.md", blog_trash_file($dir, $slug));
        blog_rebuild($DIR); blog_log($DIR, 'delete', $slug);
        return ['to' => ($slug !== '' && strpos($back, 'edit=') !== false) ? '/quan-tri/?p=blog&ok=blog_deleted' : blog_with_ok($back, 'blog_deleted')];
    }

    /* --- lưu bài --- */
    if ($act === 'blog_save') {
        $in = blog_input_from_post();
        $orig = trim((string)($_POST['orig_slug'] ?? ''));
        if ($orig !== '' && (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $orig) || !is_file("$dir/$orig.md"))) $orig = '';   // chỉ chấp nhận bài có thật
        $errs = blog_validate_post($in, $orig, $dir);
        if ($errs) return ['error' => implode(' ', $errs), 'form' => $in, 'orig' => $orig];

        blog_store_post($DIR, $in, $orig);
        $ok = $in['status'] === 'draft' ? 'blog_saved_draft' : ($in['date'] > date('Y-m-d') ? 'blog_scheduled' : 'blog_saved');
        return ['to' => '/quan-tri/?p=blog&edit=' . rawurlencode($in['slug']) . '&ok=' . $ok];
    }
    return ['error' => 'Thao tác không hợp lệ.'];
}

function blog_flash(): array {
    return [
        'blog_saved' => 'Đã lưu và đăng bài. Trang blog đã được cập nhật.',
        'blog_saved_draft' => 'Đã lưu bản nháp (chưa hiện trên web).',
        'blog_scheduled' => 'Đã lên lịch. Bài sẽ hiện trên web vào ngày đã chọn.',
        'blog_published' => 'Đã đăng bài.',
        'blog_drafted' => 'Đã chuyển bài về nháp, bài không còn hiện trên web.',
        'blog_deleted' => 'Đã chuyển bài vào thùng rác. Bạn có thể khôi phục ở mục Thùng rác.',
        'blog_restored' => 'Đã khôi phục bài từ thùng rác.',
        'blog_purged' => 'Đã xóa vĩnh viễn.',
        'blog_rebuilt' => 'Đã tạo lại toàn bộ trang blog.',
        'blog_bulk_done' => 'Đã áp dụng cho các bài đã chọn.',
        'blog_bulk_skipped' => 'Đã áp dụng, nhưng một số bài bị bỏ qua (chưa có chuyên mục hoặc trùng đường dẫn với bài đang có).',
        'blog_bulk_none' => 'Hãy chọn hành động và ít nhất một bài.',
        'cat_added' => 'Đã thêm chuyên mục.',
        'cat_exists' => 'Chuyên mục này đã có.',
        'cat_renamed' => 'Đã đổi tên chuyên mục (các bài liên quan đã được cập nhật).',
        'cat_deleted' => 'Đã xóa chuyên mục.',
        'media_deleted' => 'Đã xóa ảnh.',
    ];
}

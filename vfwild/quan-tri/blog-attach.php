<?php
/**
 * "Gắn ảnh vào bài" trong trang Sửa bài: chọn ảnh (tải từ máy hoặc link Drive) → hệ thống đề xuất mục, alt, chú thích
 * (tên tệp được ưu tiên; AI xem ảnh để viết alt và chọn mục cho ảnh tên vô nghĩa) → biên tập duyệt → chèn vào bài, KHÔNG viết lại chữ.
 * Kèm "Đặt lại tên ảnh theo nội dung" cho ảnh cũ mang mã ngẫu nhiên (b8c339…-1200.jpg).
 * Mọi thao tác làm trên Markdown đang có trong trình soạn thảo (gửi lên, trả về), bài chỉ được lưu khi biên tập bấm Lưu nháp / Đăng.
 * Mỗi lệnh xử lý ít ảnh (Drive: 1 ảnh; phân tích: tối đa 4 ảnh) để không vượt giới hạn 30–60 giây của máy chủ web.
 */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }

const ATT_ID_RE = '/^[a-z0-9](?:[a-z0-9]|-(?!-)){5,90}$/';

/** Các mục H2 của bài: [chỉ số 1…n => tiêu đề (bỏ số đầu)]. */
function att_headings(string $md): array {
    $out = []; $code = false;
    foreach (preg_split('/\r?\n/', $md) ?: [] as $ln) {
        if (preg_match('/^\s*```/', $ln)) $code = !$code;
        if (!$code && preg_match('/^##\s+(.*)$/u', $ln, $m)) $out[count($out) + 1] = trim(preg_replace('/^\d{1,2}\s*[.):]\s+/u', '', trim($m[1])) ?? $m[1]);
    }
    return $out;
}
/** Phần đầu tiêu đề bài dùng làm gợi ý tên ảnh ("Ghế điện Honda City: Hướng dẫn…" → "Ghế điện Honda City"). */
function att_title_core(string $title): string {
    $t = trim(preg_split('/[:|–]/u', $title)[0] ?? $title);
    return mb_substr($t !== '' ? $t : $title, 0, 60, 'UTF-8');
}
/** Có phải ảnh đã tải lên của site (3 cỡ trong /img/blog) không. */
function att_has(string $id): bool { return preg_match(ATT_ID_RE, $id) === 1 && is_file(blog_web() . "/img/blog/$id-1200.jpg"); }

/** Đề xuất chỗ đặt cho một nhóm ảnh: tên tệp trước (số mục, chữ khớp tiêu đề mục, tên ảnh bìa), AI xem ảnh để viết alt/chú thích và chọn mục cho ảnh còn lại. */
function att_analyze(string $DIR, string $md, string $title, array $imgs, bool $vision): array {
    $heads = att_headings($md);
    $altPre = preg_match('/vf\s*wild/iu', $title) ? 'VinFast VF Wild – ' : '';
    $out = []; $notes = [];
    foreach ($imgs as $i => $im) {
        $name = (string)($im['name'] ?? '');
        $info = img_name_info($name);
        $target = 'auto'; $by = 'chia đều';
        if ($info['cover']) { $target = 'cover'; $by = 'tên tệp'; }
        elseif ($info['section'] !== null && isset($heads[$info['section']])) { $target = $info['section']; $by = 'số trong tên tệp'; }
        elseif (($tp = img_topic($name)) !== '' && ($tok = auto_tok($tp))) {
            $best = 0; $bs = 0.0;
            foreach ($heads as $hi => $h) { $sc = auto_hit($tok, $h); if ($sc > $bs) { $bs = $sc; $best = $hi; } }
            if ($best > 0 && $bs >= 0.6) { $target = $best; $by = 'chữ trong tên tệp'; }
        }
        $out[$i] = ['id' => (string)$im['id'], 'name' => $name, 'target' => $target, 'by' => $by, 'alt' => is_int($target) ? $altPre . $heads[$target] : $altPre . att_title_core($title), 'title' => ''];
    }
    $s = auto_settings($DIR);
    $p = (string)$s['provider'];
    if ($vision && $out && $p !== 'mock' && !empty(AUTO_PROVIDERS[$p]['vision'])) {
        $list = []; $pics = [];
        foreach (array_values($out) as $k => $o) {
            $raw = (string)@file_get_contents(blog_web() . "/img/blog/{$o['id']}-800.jpg");
            if ($raw === '') continue;
            $pics[] = ['mime' => 'image/jpeg', 'b64' => base64_encode($raw)];
            $fixed = $o['target'] === 'cover' ? ' (biên tập đã chọn làm ẢNH BÌA qua tên tệp)' : (is_int($o['target']) ? ' (biên tập đã gán vào mục ' . $o['target'] . ' qua tên tệp, giữ nguyên)' : '');
            $list[] = 'Ảnh ' . count($pics) . ': tên tệp "' . $o['name'] . '"' . $fixed;
        }
        $hl = implode("\n", array_map(fn($k, $h) => "$k. $h", array_keys($heads), $heads));
        $prompt = "Bạn là biên tập ảnh cho bài blog \"$title\". Các mục H2 của bài:\n$hl\n\nCó " . count($pics) . " ảnh đính kèm, theo thứ tự:\n" . implode("\n", $list) . "\n\n"
            . "Với từng ảnh: nhìn kỹ ảnh và đọc tên tệp (tên tệp do biên tập đặt, ƯU TIÊN tên tệp khi nó nói rõ chủ đề). Chọn số mục H2 hợp nhất (0 = hợp làm ảnh bìa đầu bài, -1 = không hợp mục nào); ảnh đã được biên tập gán thì giữ nguyên số đó. "
            . "Viết alt: mô tả ngắn, chính xác bằng tiếng Việt, tối đa 120 ký tự, chỉ nói điều thấy rõ trong ảnh (không đoán màu, phiên bản, giá). Viết title: chú thích ngắn dưới ảnh, tối đa 80 ký tự, gắn với nội dung mục. Tên riêng viết hoa đúng (Honda City, VF Wild…).\n"
            . "Chỉ trả JSON: {\"images\":[{\"n\":1,\"muc\":2,\"alt\":\"...\",\"title\":\"...\"}]}";
        try {
            $j = auto_json_decode(auto_ai_call($DIR, $p, auto_model($s, $p), $prompt, true, 50, $pics));
            $keys = array_keys($out);
            foreach ((array)($j['images'] ?? []) as $it) {
                $k = (int)($it['n'] ?? 0) - 1;
                if (!is_array($it) || !isset($keys[$k])) continue;
                $o = &$out[$keys[$k]];
                $muc = isset($it['muc']) ? (int)$it['muc'] : null;
                if ($o['target'] === 'auto' && $muc !== null) {
                    if ($muc === 0) { $o['target'] = 'cover'; $o['by'] = 'AI xem ảnh'; }
                    elseif ($muc > 0 && isset($heads[$muc])) { $o['target'] = $muc; $o['by'] = 'AI xem ảnh'; }
                    elseif ($muc === -1) { $o['target'] = 'skip'; $o['by'] = 'AI: không hợp mục nào'; }
                }
                $a = img_clean_alt(auto_fix_names((string)($it['alt'] ?? ''), $title), ''); if ($a !== '') $o['alt'] = $a;
                $o['title'] = img_clean_title(auto_fix_names((string)($it['title'] ?? ''), $title));
                unset($o);
            }
        } catch (Throwable $e) { $notes[] = 'AI chưa xem được ảnh (' . $e->getMessage() . '), đã đề xuất theo tên tệp.'; }
    }
    return ['items' => array_values($out), 'headings' => $heads, 'notes' => $notes];
}

/**
 * Chèn ảnh vào Markdown theo lựa chọn của biên tập. $items: [['id','target' => 'cover'|'auto'|'skip'|số mục,'alt','title']].
 * Ảnh vào sau đoạn văn của mục (dùng chung img_place với luồng tự viết), $replace = gỡ ảnh /img/blog cũ trong thân bài trước.
 */
function att_apply(string $md, string $title, array $items, bool $replace): array {
    $md = str_replace("\r\n", "\n", $md);
    if ($replace) $md = trim(preg_replace("/\n*^!\[[^\]\n]*\]\(\/img\/blog\/[^)\s]+(?:\s+\"[^\"]*\")?\)\s*$\n*/mu", "\n\n", $md) ?? $md);
    $heads = att_headings($md);
    $altPre = preg_match('/vf\s*wild/iu', $title) ? 'VinFast VF Wild – ' : '';
    $cover = null; $inline = []; $plan = [];
    foreach ($items as $it) {
        $id = (string)($it['id'] ?? '');
        if (!att_has($id)) continue;
        $t = $it['target'] ?? 'auto';
        if ($t === 'skip') continue;
        $alt = img_clean_alt((string)($it['alt'] ?? ''), $altPre . att_title_core($title));
        $ti = img_clean_title((string)($it['title'] ?? ''));
        if ($t === 'cover') { if ($cover === null) $cover = ['cover' => "/img/blog/$id-800.jpg", 'alt' => $alt, 'title' => $ti]; continue; }
        $sec = is_numeric($t) && isset($heads[(int)$t]) ? (int)$t : null;
        $inline[] = ['insert' => "/img/blog/$id-1200.jpg", 'name' => $id, 'section' => $sec, 'topic' => ''];
        $plan[count($inline) + 1] = ['after' => '', 'alt' => $alt, 'title' => $ti];
    }
    $warn = []; $placed = 0;
    if ($inline) [$md, $placed] = img_place(trim($md), array_merge([['insert' => '', 'name' => '', 'section' => null, 'topic' => '']], $inline), $plan, $warn, $altPre);
    return ['md' => $md, 'cover' => $cover, 'placed' => $placed, 'warn' => $warn];
}

/**
 * Đặt lại tên ảnh mã ngẫu nhiên (b8c339…) theo nội dung: ảnh bìa theo tiêu đề bài, ảnh trong bài theo tiêu đề bài + tiêu đề mục chứa ảnh.
 * Chép sang tên mới (giữ tệp cũ vì có thể bài khác còn dùng), thay đường dẫn trong Markdown và ảnh bìa.
 */
function att_rename(string $md, string $cover, string $title): array {
    $md = str_replace("\r\n", "\n", $md);
    $core = att_title_core($title);
    $map = []; $sec = '';
    $hintOf = [];
    foreach (preg_split('/\n/', $md) ?: [] as $ln) {
        if (preg_match('/^##\s+(.*)$/u', $ln, $m)) { $sec = trim(preg_replace('/^\d{1,2}\s*[.):]\s+/u', '', trim($m[1])) ?? $m[1]); continue; }
        if (preg_match_all('#/img/blog/(b[0-9a-z]{6,24})-(?:800|1200|1600)\.jpg#', $ln, $mm)) foreach ($mm[1] as $old) $hintOf[$old] ??= trim($core . ' ' . $sec);
    }
    if (preg_match('#/img/blog/(b[0-9a-z]{6,24})-(?:800|1200|1600)\.jpg#', $cover, $mc)) $hintOf[$mc[1]] ??= $core;
    $dir = blog_web() . '/img/blog'; $n = 0; $miss = 0;
    foreach ($hintOf as $old => $hint) {
        $has = array_filter(IMG_SIZES, fn($sz) => is_file("$dir/$old-$sz.jpg"));
        if (!$has) { $miss++; continue; }
        $new = img_seo_id($hint, $old);
        foreach (IMG_SIZES as $sz) { if (is_file("$dir/$old-$sz.jpg") && !is_file("$dir/$new-$sz.jpg")) { @copy("$dir/$old-$sz.jpg", "$dir/$new-$sz.jpg"); @chmod("$dir/$new-$sz.jpg", 0644); } }
        if (!array_filter($has, fn($sz) => is_file("$dir/$new-$sz.jpg"))) continue;
        $map["/img/blog/$old-"] = "/img/blog/$new-"; $n++;
    }
    return ['md' => strtr($md, $map), 'cover' => strtr($cover, $map), 'renamed' => $n, 'missing' => $miss];
}

/** Một ảnh Drive / link: tải, nén 3 cỡ, đặt tên theo nội dung. */
function att_fetch_one(string $DIR, string $link, string $name, string $hint): array {
    $bytes = preg_match('/^drive:([A-Za-z0-9_-]{10,})$/', $link, $m) ? img_fetch_drive($DIR, $m[1]) : img_fetch($link, $DIR);
    $proc = img_process($bytes);
    $proc['id'] = img_seo_id(img_name_hint($hint, $name), $proc['id']);
    img_store($proc);
    return ['id' => $proc['id'], 'name' => $name, 'thumb' => "/img/blog/{$proc['id']}-800.jpg"];
}

function attach_post(string $act, string $DIR): array {
    @set_time_limit(90);
    $json = function (array $d) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit; };
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 200, 'UTF-8');
    $md = (string)($_POST['body'] ?? '');
    if (strlen($md) > 400000) $json(['error' => 'Bài quá dài.']);
    try {
        if ($act === 'blog_attach_drive_list') {
            $link = trim((string)($_POST['link'] ?? ''));
            if ($link === '' || strlen($link) > 2000) $json(['error' => 'Hãy dán link thư mục hoặc link ảnh Google Drive.']);
            $err = [];
            $src = img_sources($DIR, $link, $err, IMG_HARD_MAX);
            $json(['ok' => true, 'files' => array_map(fn($x) => ['link' => (string)$x['link'], 'name' => (string)$x['name']], $src), 'errors' => $err]);
        }
        if ($act === 'blog_attach_drive_fetch') {
            $link = trim((string)($_POST['link'] ?? ''));
            if (!preg_match('/^(drive:[A-Za-z0-9_-]{10,}|https:\/\/\S{8,1900})$/', $link) && !(getenv('VFW_IMG_ALLOW_LOCAL') && preg_match('#^http://127\.0\.0\.1:\d+/\S+$#', $link))) $json(['error' => 'Link ảnh không hợp lệ.']);   // http nội bộ chỉ khi chạy thử
            $json(['ok' => true] + att_fetch_one($DIR, $link, mb_substr((string)($_POST['name'] ?? ''), 0, 160, 'UTF-8'), att_title_core($title)));
        }
        if ($act === 'blog_attach_analyze') {
            $imgs = array_values(array_filter((array)json_decode((string)($_POST['images'] ?? '[]'), true), fn($x) => is_array($x) && att_has((string)($x['id'] ?? ''))));
            if (!$imgs) $json(['error' => 'Chưa có ảnh hợp lệ.']);
            $json(['ok' => true] + att_analyze($DIR, $md, $title, array_slice($imgs, 0, 4), ($_POST['vision'] ?? '1') === '1'));
        }
        if ($act === 'blog_attach_apply') {
            $items = (array)json_decode((string)($_POST['items'] ?? '[]'), true);
            $r = att_apply($md, $title, array_slice(array_filter($items, 'is_array'), 0, IMG_HARD_MAX), ($_POST['replace'] ?? '') === '1');
            blog_log($DIR, 'attach-images', (string)($_POST['slug'] ?? ''));
            $json(['ok' => true] + $r);
        }
        if ($act === 'blog_attach_rename') {
            $r = att_rename($md, (string)($_POST['cover'] ?? ''), $title);
            $json(['ok' => true] + $r);
        }
    } catch (Throwable $e) { $json(['error' => $e->getMessage()]); }
    $json(['error' => 'Thao tác không hợp lệ.']);
}

<?php
/**
 * Kiểm tra nội dung blog (mượn ý tưởng từ SEO Content OS: kiểm kê nội dung, liên kết nội bộ toàn site, vòng phản hồi Search Console).
 *  - kiểm kê: bài ngắn, bài cũ, số liệu không còn trong bộ dữ kiện, bài mồ côi, điểm SEO;
 *  - tự gắn liên kết từ bài cũ sang bài mới ở đúng cụm từ có sẵn trong bài (có lưu phiên bản cũ để khôi phục);
 *  - Search Console: hiệu quả từng bài, cơ hội nâng hạng và gợi ý từ khóa mới đưa vào Google Sheet.
 */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }

/* ---------- Liên kết nội bộ tự động ---------- */
/** Các cụm từ có thể dùng làm chữ liên kết tới một bài: thẻ trước, rồi các cụm 3–6 từ trong tiêu đề (dài trước). */
function audit_phrases(array $target): array {
    $out = [];
    foreach (blog_tags_list($target) as $t) { if (mb_strlen($t, 'UTF-8') >= 8) $out[] = $t; }
    $segs = preg_split('/[:?!–—|]+/u', (string)$target['title']) ?: [];
    if (preg_match_all('/^##\s+(?:\d+[.)]\s*)?(.+)$/mu', (string)$target['body'], $hm)) { foreach ($hm[1] as $h) { if (!preg_match('/câu hỏi|kết bài|kết luận|xem thêm/iu', $h)) $segs[] = $h; } }
    foreach ($segs as $seg) {
        $w = preg_split('/\s+/u', trim($seg), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        for ($n = min(6, count($w)); $n >= 2; $n--) {
            for ($i = 0; $i + $n <= count($w); $i++) $out[] = trim(implode(' ', array_slice($w, $i, $n)), " ,.;");
        }
    }
    $seen = []; $res = [];
    foreach ($out as $p) { $k = mb_strtolower($p, 'UTF-8'); if ($p !== '' && !isset($seen[$k]) && count(auto_tok($p)) >= 2) { $seen[$k] = 1; $res[] = $p; } }
    return array_slice($res, 0, 120);
}
/** Biến lần xuất hiện đầu tiên của cụm trong đoạn văn thường (không phải tiêu đề, bảng, trích dẫn, ảnh, liên kết có sẵn) thành liên kết. */
function audit_inject_link(string $body, string $phrase, string $url): array {
    $lines = preg_split('/\r?\n/', $body) ?: [];
    $re = '/(?<![\p{L}\p{N}])' . preg_quote($phrase, '/') . '(?![\p{L}\p{N}])/iu';
    foreach ($lines as $i => $ln) {
        if ($ln === '' || preg_match('/^\s*(#|\||>|!\[|```|---)/u', $ln)) continue;
        $parts = preg_split('/(!?\[[^\]]*\]\([^)]*\)|`[^`]*`|<[^>]+>|https?:\/\/\S+)/u', $ln, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$ln];
        foreach ($parts as $k => $part) {
            if ($k % 2 === 1) continue;   // phần được giữ lại từ dấu phân tách (liên kết, mã…)
            if (preg_match($re, $part, $m, PREG_OFFSET_CAPTURE)) {
                $parts[$k] = substr($part, 0, $m[0][1]) . '[' . $m[0][0] . '](' . $url . ')' . substr($part, $m[0][1] + strlen($m[0][0]));
                $lines[$i] = implode('', $parts);
                return [implode("\n", $lines), $m[0][0]];
            }
        }
    }
    return [$body, ''];
}
/** Gắn liên kết từ các bài liên quan (đã đăng) tới bài mới: mỗi bài nguồn tối đa 1 liên kết, tối đa $max bài. Trả về [[bài nguồn, chữ liên kết]…]. */
function audit_link_new_post(string $DIR, string $slug, int $max = 4): array {
    $target = blog_load($DIR, $slug);
    if (!$target || blog_state($target) !== 'published') return [];
    $target['slug'] = $slug;
    $url = '/blog/' . $slug . '/';
    $phr = audit_phrases($target);
    $tt = auto_tok($target['title'] . ' ' . $target['tags']);
    $cands = [];
    foreach (blog_all($DIR) as $src) {
        if ($src['slug'] === $slug || blog_state($src) !== 'published' || strpos($src['body'], '](' . $url) !== false) continue;
        $cands[] = [auto_hit($tt, $src['title'] . ' ' . $src['tags'] . ' ' . $src['body']) + ($src['category'] === $target['category'] ? 0.1 : 0), $src];
    }
    usort($cands, fn($a, $b) => $b[0] <=> $a[0]);
    $done = [];
    foreach ($cands as [$score, $src]) {
        if (count($done) >= $max) break;
        if ($score < 0.15) break;
        foreach ($phr as $ph) {
            [$nb, $used] = audit_inject_link($src['body'], $ph, $url);
            if ($used === '') continue;
            $src['body'] = $nb;
            blog_store_post($DIR, $src, $src['slug'], false);   // false: chèn liên kết nội bộ không tính là cập nhật nội dung. Lưu phiên bản cũ, dựng lại trang tĩnh, ghi lịch sử
            $done[] = [$src['slug'], $used];
            break;
        }
    }
    return $done;
}
/** Chạy cho mọi bài đã đăng (cũ trước): bổ sung các liên kết còn thiếu, tối đa $cap lượt sửa. */
function audit_link_all(string $DIR, int $cap = 12): array {
    $all = array_reverse(array_values(array_filter(blog_all($DIR), fn($p) => blog_state($p) === 'published')));
    $done = [];
    foreach ($all as $p) {
        if (count($done) >= $cap) break;
        foreach (audit_link_new_post($DIR, $p['slug'], min(3, $cap - count($done))) as $d) $done[] = [$d[0], $p['slug'], $d[1]];
    }
    return $done;
}

/* ---------- Kiểm kê ---------- */
function audit_set(string $text): array {
    $stop = ['vf', 'wild', 'vinfast', 'xe', 'la', 'va', 'cua', 'cho', 'nhung', 'cac', 'mot', 'co', 'nao', 'gi', 'bao', 'nhieu', 'khong', 'duoc', 'the', 'nay', 'khi', 'voi', 'tai', 'trong', 'cung', 'nhu', 'cac'];
    $w = array_unique(array_filter(explode('-', blog_slugify(mb_substr($text, 0, 12000, 'UTF-8'))), fn($t) => strlen($t) > 2 && !in_array($t, $stop, true)));
    return array_flip($w);
}
/** Phân tích từng bài đã đăng. Mỗi bài: điểm SEO, danh sách vấn đề [mức, nội dung], số bài liên kết tới, số liệu Search Console. */
function audit_analyze(string $DIR, array $s): array {
    $posts = array_values(array_filter(blog_all($DIR), fn($p) => blog_state($p) === 'published'));
    $facts = auto_facts($DIR);
    $gsc = gsc_cached($DIR);
    $sets = []; $inbound = [];
    foreach ($posts as $p) { $sets[$p['slug']] = audit_set($p['title'] . ' ' . $p['body']); $inbound[$p['slug']] = 0; }
    foreach ($posts as $q) { foreach ($posts as $p) { if ($q['slug'] !== $p['slug'] && strpos($q['body'], '](/blog/' . $p['slug'] . '/') !== false) $inbound[$p['slug']]++; } }
    $rows = [];
    foreach ($posts as $p) {
        $issues = [];
        $minw = (int)$s['min_words'];
        if ($p['words'] < $minw * 0.5) $issues[] = ['high', "Quá ngắn: {$p['words']} từ (nên ≥ $minw)."]; elseif ($p['words'] < $minw) $issues[] = ['med', "Hơi ngắn: {$p['words']} từ (nên ≥ $minw)."];
        $age = (int)floor((time() - strtotime($p['date'])) / 86400);
        if ($age > 180) $issues[] = ['med', "Đã $age ngày chưa cập nhật – kiểm tra lại giá và ưu đãi."];
        $unk = auto_unknown_numbers($p['body'], $facts);
        if ($unk) $issues[] = ['med', 'Số liệu không có trong bộ dữ kiện hiện tại: ' . implode('; ', array_slice($unk, 0, 4)) . ' – có thể đã cũ hoặc cần xác nhận.'];
        if (count($posts) >= 3 && $inbound[$p['slug']] === 0) $issues[] = ['low', 'Chưa có bài nào liên kết tới bài này.'];
        $noAlt = (int)preg_match_all('/!\[\s*\]\(/u', (string)$p['body']);
        if ($noAlt > 0) $issues[] = ['med', "$noAlt ảnh trong bài chưa có mô tả (alt) – thêm mô tả để tốt cho SEO và người khiếm thị."];
        if ($p['cover'] !== '' && trim((string)$p['cover_alt']) === '') $issues[] = ['med', 'Ảnh đại diện chưa có mô tả (alt).'];
        if (trim((string)($p['video'] ?? '')) !== '' && !blog_video_parse((string)$p['video'])) $issues[] = ['med', 'Link video không nhận dạng được – kiểm tra lại link YouTube/Vimeo/.mp4.'];
        $tags = blog_tags_list($p);
        $row = ['type' => auto_type_for((string)$p['category'], '', (string)$p['title']), 'keyword' => $tags[0] ?? $p['title'], 'sub' => implode(';', array_slice($tags, 1))];
        $sc = auto_score($p, $s, $facts, $row);
        $skip = ['title', 'keyword', 'secondary', 'cover'];   // các mục phụ thuộc từ khóa chính của Sheet nên bỏ khi kiểm bài đã đăng
        $earn = $tot = 0;
        foreach ($sc['checks'] as $c) {
            if (in_array($c['name'], $skip, true)) continue;
            $tot += $c['weight']; if ($c['ok']) $earn += $c['weight']; else $issues[] = ['low', $c['detail']];
        }
        $sc['score'] = $tot ? (int)round($earn / $tot * 100) : 0;
        $g = gsc_for_post($gsc, $p['slug']);
        if ($g && $g['i'] >= 30 && $g['p'] >= 4 && $g['p'] <= 20) $issues[] = ['opp', "Cơ hội: {$g['i']} lượt hiển thị nhưng vị trí trung bình {$g['p']} – bổ sung nội dung để lên trang đầu."];
        $order = ['high' => 0, 'med' => 1, 'opp' => 2, 'low' => 3];
        usort($issues, fn($a, $b) => $order[$a[0]] <=> $order[$b[0]]);
        $rows[] = ['post' => $p, 'score' => $sc['score'], 'issues' => $issues, 'inbound' => $inbound[$p['slug']], 'gsc' => $g];
    }
    usort($rows, fn($a, $b) => [count(array_filter($b['issues'], fn($i) => in_array($i[0], ['high', 'med'], true))), $a['score']] <=> [count(array_filter($a['issues'], fn($i) => in_array($i[0], ['high', 'med'], true))), $b['score']]);
    return $rows;
}
/** Từ Search Console: truy vấn xếp hạng 4–20 (cơ hội), tiêu đề/mô tả kém hấp dẫn (CTR thấp dù trong top 10) và từ khóa mới chưa có bài. */
function audit_gsc_ideas(string $DIR, array $posts, array $sheetKw): array {
    $c = gsc_cached($DIR);
    $wins = []; $lowctr = []; $agg = []; $served = [];
    foreach ((array)($c['qp'] ?? []) as $r) {
        preg_match('#/blog/([a-z0-9-]+)/#', $r['u'], $m); $slug = $m[1] ?? '';
        if ($slug !== '' && $r['i'] >= 30 && $r['p'] >= 4 && $r['p'] <= 20) $wins[] = $r + ['slug' => $slug];
        if ($slug !== '' && $r['i'] >= 50 && $r['p'] <= 10 && $r['c'] / max(1, $r['i']) < 0.02) $lowctr[] = $r + ['slug' => $slug];
        if ($slug !== '') { $served[$r['q']] = true; continue; }   // truy vấn đã dẫn tới một bài blog thì không phải từ khóa mới
        $a = $agg[$r['q']] ?? ['q' => $r['q'], 'i' => 0, 'c' => 0, 'p' => 0.0]; $a['i'] += $r['i']; $a['c'] += $r['c']; $a['p'] = $r['p']; $agg[$r['q']] = $a;
    }
    usort($wins, fn($a, $b) => $b['i'] <=> $a['i']); usort($lowctr, fn($a, $b) => $b['i'] <=> $a['i']);
    $ideas = [];
    foreach ($agg as $a) {
        if (isset($served[$a['q']]) || $a['i'] < 10 || !preg_match('/vf|wild|vinfast/i', $a['q']) || mb_strlen($a['q'], 'UTF-8') < 6) continue;
        $tok = auto_tok($a['q']);
        $have = false;
        foreach ($posts as $p) { if (auto_hit($tok, $p['title'] . ' ' . $p['tags']) >= 0.7) { $have = true; break; } }
        foreach ($sheetKw as $k) { if (!$have && auto_hit($tok, $k) >= 0.8) $have = true; }
        if (!$have) $ideas[] = $a;
    }
    usort($ideas, fn($a, $b) => $b['i'] <=> $a['i']);
    return ['wins' => array_slice($wins, 0, 15), 'lowctr' => array_slice($lowctr, 0, 10), 'ideas' => array_slice($ideas, 0, 20)];
}
/** Thêm từ khóa mới vào cuối tab "Từ khóa" của Sheet với trạng thái "Ý tưởng" (chưa có giờ đăng nên chưa tự viết). */
function audit_add_ideas(string $DIR, array $s, array $queries): int {
    $id = auto_sheet_id($s);
    if (!$id) throw new RuntimeException('Chưa nhập link Google Sheet.');
    $have = array_map(fn($r) => blog_slugify($r['keyword']), auto_sheet_rows($DIR, $s));
    $catOf = ['gia' => 'Giá & ưu đãi', 'so-sanh' => 'So sánh', 'thu-tuc' => 'Thủ tục', 'cong-nghe' => 'Công nghệ', 'chi-phi' => 'Chi phí', 'huong-dan' => 'Hướng dẫn', 'tin-tuc' => 'Tin tức'];
    $lay = auto_sheet_layout($DIR, $s); $width = max($lay) + 1;
    $rows = [];
    foreach ($queries as $q) {
        $q = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$q) ?? ''), 0, 120, 'UTF-8');
        if (mb_strlen($q, 'UTF-8') < 6 || preg_match('/[<>=@]/', $q) || in_array(blog_slugify($q), $have, true)) continue;
        $r = array_fill(0, $width, '');
        $r[$lay['keyword']] = $q; $r[$lay['status']] = 'Ý tưởng';
        if (isset($lay['category'])) $r[$lay['category']] = $catOf[auto_detect_type($q)] ?? 'Hướng dẫn';
        if (isset($lay['note'])) $r[$lay['note']] = 'Gợi ý từ Search Console (' . date('d/m/Y') . ')';
        $rows[] = $r; $have[] = blog_slugify($q);
        if (count($rows) >= 20) break;
    }
    if ($rows) gs_append($DIR, $id, "'" . AUTO_TAB . "'!A:" . gs_col($width - 1), $rows);
    return count($rows);
}

/* ---------- Thao tác POST ---------- */
function audit_post(string $act, string $DIR): array {
    $back = '/quan-tri/?p=audit';
    $s = auto_settings($DIR);
    try {
        switch ($act) {
            case 'blog_audit_links': {
                $n = count(audit_link_all($DIR, 12));
                return ['to' => "$back&ok=audit_links&n=$n"];
            }
            case 'blog_audit_gsc': {
                $site = (string)$s['gsc_site'];
                if ($site === '' || !gsc_site_valid($site)) return ['error' => 'Hãy nhập site Search Console ở Tự động viết bài → Cài đặt → Nâng cao (ví dụ sc-domain:vfwild.vn).'];
                $d = gsc_refresh($DIR, $site);
                return ['to' => "$back&ok=audit_gsc&n=" . count($d['pages'])];
            }
            case 'blog_audit_addkw': {
                $q = array_slice((array)($_POST['kw'] ?? []), 0, 20);
                $n = audit_add_ideas($DIR, $s, $q);
                return ['to' => "$back&ok=audit_kw&n=$n"];
            }
        }
    } catch (Throwable $e) { return ['error' => $e->getMessage()]; }
    return ['error' => 'Thao tác không hợp lệ.'];
}

/* ---------- Giao diện ---------- */
function blog_view_audit(string $DIR, string $csrf): void {
    $s = auto_settings($DIR);
    $rows = audit_analyze($DIR, $s);
    $gsc = gsc_cached($DIR);
    $hasSite = (string)$s['gsc_site'] !== '';
    $acc = gs_account($DIR);
    $tot = ['high' => 0, 'med' => 0, 'opp' => 0, 'low' => 0];
    foreach ($rows as $r) foreach ($r['issues'] as [$lv]) $tot[$lv]++;
    $words = $rows ? (int)round(array_sum(array_map(fn($r) => $r['post']['words'], $rows)) / count($rows)) : 0;
    $ideas = ['wins' => [], 'lowctr' => [], 'ideas' => []];
    $sheetErr = '';
    if (!empty($gsc['qp'])) {
        $kws = [];
        if (auto_sheet_id($s) && $acc) { try { $kws = array_map(fn($r) => $r['keyword'], auto_sheet_rows($DIR, $s)); } catch (Throwable $e) { $sheetErr = $e->getMessage(); } }
        $ideas = audit_gsc_ideas($DIR, array_column($rows, 'post'), $kws);
    }
    $lab = ['high' => ['Nặng', 'bad'], 'med' => ['Cần xem', 'off'], 'opp' => ['Cơ hội', 'on'], 'low' => ['Gợi ý', 'off']];
    ?>
  <div class="page">
    <div class="pagehead"><h1>Kiểm tra nội dung</h1></div>
    <section class="card">
      <div class="rowhead"><h2>Tổng quan</h2>
        <form method="post" class="inline" data-confirm="Hệ thống sẽ thêm liên kết nội bộ vào các bài cũ (đúng cụm từ có sẵn trong bài, mỗi bài tối đa 1 liên kết mới, tối đa 12 lần sửa). Bản cũ của mỗi bài được lưu để khôi phục. Tiếp tục?"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_audit_links"><button class="btn btn--ghost" type="submit">Tự gắn liên kết nội bộ giữa các bài</button></form></div>
      <div class="statgrid">
        <div><span class="muted small">Bài đã đăng</span><b><?= count($rows) ?> bài · TB <?= $words ?> từ</b></div>
        <div><span class="muted small">Vấn đề nặng</span><b><?= $tot['high'] ?></b></div>
        <div><span class="muted small">Cần xem lại</span><b><?= $tot['med'] ?></b></div>
        <div><span class="muted small">Cơ hội từ Search Console</span><b><?= $tot['opp'] ?></b></div>
      </div>
      <p class="muted small">Chỉ để xem và gợi ý, trang này không tự sửa nội dung bài (trừ nút gắn liên kết nội bộ ở trên). Bấm “Sửa” ở từng bài để chỉnh.</p>
    </section>

    <section class="card">
      <h2>Từng bài viết</h2>
      <?php if (!$rows) { ?><div class="empty"><p>Chưa có bài đã đăng để kiểm tra.</p></div><?php } else { ?>
      <div class="tbl"><table class="wp-list">
        <thead><tr><th>Bài viết</th><th>Điểm SEO</th><th>Search Console (28 ngày)</th><th>Vấn đề cần xem</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r) { $p = $r['post']; ?>
          <tr>
            <td class="title"><b><?= h($p['title']) ?></b><div class="rowact small"><a href="/quan-tri/?p=blog&amp;edit=<?= h(rawurlencode($p['slug'])) ?>">Sửa</a> · <a href="/blog/<?= h($p['slug']) ?>/" target="_blank" rel="noopener">Xem</a> · <?= (int)$p['words'] ?> từ · <?= (int)$r['inbound'] ?> bài liên kết tới</div></td>
            <td><span class="st <?= $r['score'] >= 80 ? 'on' : ($r['score'] >= 60 ? 'off' : 'bad') ?>"><?= (int)$r['score'] ?>/100</span></td>
            <td class="small nowrap"><?= $r['gsc'] ? (int)$r['gsc']['c'] . ' nhấp · ' . (int)$r['gsc']['i'] . ' hiển thị<br>vị trí ' . h((string)$r['gsc']['p']) : '<span class="muted">—</span>' ?></td>
            <td class="small"><?php if (!$r['issues']) { ?><span class="muted">Ổn</span><?php } else { foreach (array_slice($r['issues'], 0, 6) as [$lv, $tx]) { ?><div><span class="st <?= h($lab[$lv][1]) ?>"><?= h($lab[$lv][0]) ?></span> <?= h($tx) ?></div><?php } } ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table></div>
      <?php } ?>
    </section>

    <section class="card">
      <div class="rowhead"><h2>Search Console</h2>
        <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_audit_gsc"><button class="btn btn--ghost" type="submit">Cập nhật dữ liệu</button></form></div>
      <?php if (!$hasSite) { ?>
        <div class="note warn">Chưa kết nối. Vào <b>Tự động viết bài → Cài đặt → Nâng cao</b>, nhập site Search Console (ví dụ <code>sc-domain:vfwild.vn</code>), rồi thêm email <code><?= h($acc ? (string)$acc['client_email'] : '(tài khoản dịch vụ Google)') ?></code> vào Search Console của site: Cài đặt → Người dùng và quyền → Thêm người dùng (quyền “Hạn chế” là đủ).</div>
      <?php } elseif (!empty($gsc['error'])) { ?>
        <div class="note warn"><?= h((string)$gsc['error']) ?></div>
      <?php } elseif (empty($gsc['at'])) { ?>
        <p class="muted">Chưa có dữ liệu – bấm “Cập nhật dữ liệu”. Hệ thống cũng tự làm mới mỗi ngày.</p>
      <?php } else { ?>
        <p class="muted small">Dữ liệu từ <?= h((string)$gsc['from']) ?> đến <?= h((string)$gsc['to']) ?> · cập nhật <?= h(auto_ago((int)$gsc['at'])) ?> · <?= count($gsc['pages']) ?> trang có số liệu.</p>
        <?php if ($sheetErr !== '') { ?><div class="note warn"><?= h($sheetErr) ?></div><?php } ?>
        <?php if ($ideas['wins']) { ?><h3>Cơ hội nâng hạng (vị trí 4–20)</h3><div class="tbl"><table><?php foreach ($ideas['wins'] as $w) { ?><tr><td><?= h($w['q']) ?></td><td class="nowrap small"><?= (int)$w['i'] ?> hiển thị · vị trí <?= h((string)$w['p']) ?></td><td class="small">bài <a href="/quan-tri/?p=blog&amp;edit=<?= h(rawurlencode($w['slug'])) ?>"><?= h($w['slug']) ?></a></td></tr><?php } ?></table></div><?php } ?>
        <?php if ($ideas['lowctr']) { ?><h3>Hiển thị cao nhưng ít người bấm (nên viết lại tiêu đề, mô tả)</h3><div class="tbl"><table><?php foreach ($ideas['lowctr'] as $w) { ?><tr><td><?= h($w['q']) ?></td><td class="nowrap small"><?= (int)$w['i'] ?> hiển thị · <?= (int)$w['c'] ?> nhấp · vị trí <?= h((string)$w['p']) ?></td><td class="small">bài <a href="/quan-tri/?p=blog&amp;edit=<?= h(rawurlencode($w['slug'])) ?>"><?= h($w['slug']) ?></a></td></tr><?php } ?></table></div><?php } ?>
        <?php if ($ideas['ideas']) { ?>
          <h3>Từ khóa mới người dùng đang tìm (chưa có bài)</h3>
          <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_audit_addkw">
            <div class="tbl"><table><?php foreach ($ideas['ideas'] as $w) { ?><tr><td><label><input type="checkbox" name="kw[]" value="<?= h($w['q']) ?>"> <?= h($w['q']) ?></label></td><td class="nowrap small"><?= (int)$w['i'] ?> hiển thị · vị trí <?= h((string)$w['p']) ?></td></tr><?php } ?></table></div>
            <p class="muted small">Từ khóa được chọn sẽ thêm vào Sheet với trạng thái “Ý tưởng” (chưa có giờ đăng nên chưa tự viết). Bạn đặt giờ và chuyển sang “Sẵn sàng” khi muốn đăng.</p>
            <button class="btn" type="submit">Thêm các từ khóa đã chọn vào Sheet</button></form>
        <?php } ?>
        <?php if (!$ideas['wins'] && !$ideas['lowctr'] && !$ideas['ideas']) { ?><p class="muted">Chưa có gợi ý nào (site còn ít dữ liệu tìm kiếm).</p><?php } ?>
      <?php } ?>
    </section>
  </div>
<?php }

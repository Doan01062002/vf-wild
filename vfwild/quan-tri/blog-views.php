<?php
/** Giao diện quản lý blog (kiểu WordPress): danh sách bài, soạn bài, thư viện ảnh, chuyên mục. Nạp từ index.php. */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }

function blog_state_label(string $s): string {
    return ['published' => 'Đã đăng', 'scheduled' => 'Lên lịch', 'draft' => 'Bản nháp'][$s] ?? $s;
}
function blog_qs(array $over = []): string {
    $q = ['p' => 'blog'];
    foreach (['status', 'cat', 's', 'sort', 'per', 'paged'] as $k) { if (isset($_GET[$k]) && $_GET[$k] !== '') $q[$k] = (string)$_GET[$k]; }
    foreach ($over as $k => $v) { if ($v === null || $v === '') unset($q[$k]); else $q[$k] = $v; }
    return '/quan-tri/?' . http_build_query($q);
}

/* ---------- Phân trang dùng chung cho các danh sách trong trang quản trị ---------- */
/** Số mục mỗi trang lấy từ tham số $key (chỉ nhận các giá trị trong $opts). */
function admin_per(string $key, array $opts, int $def): int { $v = (int)($_GET[$key] ?? $def); return in_array($v, $opts, true) ? $v : $def; }
/** Cắt một trang của danh sách: [các mục của trang, trang hiện tại, số trang]; số trang lấy từ tham số $key. */
function admin_slice(array $items, string $key, int $per): array {
    $pages = max(1, (int)ceil(count($items) / $per));
    $page = min($pages, max(1, (int)($_GET[$key] ?? 1)));
    return [array_slice($items, ($page - 1) * $per, $per, true), $page, $pages];
}
/** Địa chỉ trang hiện tại, đổi vài tham số (null = bỏ), giữ các tham số khác; $anchor đưa về đúng bảng sau khi bấm. */
function admin_url(array $over, string $anchor = ''): string {
    $q = [];
    foreach ($_GET as $k => $v) { if (is_string($v) && $v !== '') $q[$k] = $v; }
    foreach ($over as $k => $v) { if ($v === null || $v === '') unset($q[$k]); else $q[$k] = (string)$v; }
    return '/quan-tri/?' . http_build_query($q) . ($anchor !== '' ? '#' . $anchor : '');
}
/**
 * Thanh phân trang: "Trang x/y · n mục · Hiện 10 20 50 mỗi trang" và ‹ Trước 1 2 … Sau ›.
 * $key: tham số số trang; $perKey, $perOpts, $perDef: tham số số mục mỗi trang ('' = không cho chọn); $url: hàm tạo địa chỉ (mặc định admin_url).
 */
function admin_pager(int $page, int $pages, int $total, int $per, string $key, string $perKey = '', array $perOpts = [], int $perDef = 0, ?callable $url = null, string $anchor = ''): string {
    if ($total <= 0) return '';
    $url ??= fn(array $o) => admin_url($o, $anchor);
    $link = fn(int $i, string $label) => '<a href="' . h($url([$key => $i > 1 ? $i : null])) . '">' . $label . '</a>';
    $h = '<div class="pagebar" aria-label="Phân trang"><span class="muted small">Trang ' . $page . '/' . $pages . ' · ' . $total . ' mục';
    if ($perKey !== '' && $perOpts) {
        $h .= ' · Hiện ';
        foreach ($perOpts as $pp) $h .= ($pp === $per ? '<b>' . $pp . '</b>' : '<a href="' . h($url([$perKey => $pp === $perDef ? null : $pp, $key => null])) . '">' . $pp . '</a>') . ' ';
        $h .= 'mỗi trang';
    }
    $h .= '</span>';
    if ($pages > 1) {
        $show = array_unique(array_filter([1, 2, $page - 1, $page, $page + 1, $pages - 1, $pages], fn($x) => $x >= 1 && $x <= $pages));
        sort($show);
        $h .= '<div class="pager">' . ($page > 1 ? $link($page - 1, '‹ Trước') : '<span class="off">‹ Trước</span>');
        $prev = 0;
        foreach ($show as $i) { if ($prev && $i > $prev + 1) $h .= '<span class="gap">…</span>'; $h .= $i === $page ? '<span class="on">' . $i . '</span>' : $link($i, (string)$i); $prev = $i; }
        $h .= ($page < $pages ? $link($page + 1, 'Sau ›') : '<span class="off">Sau ›</span>') . '</div>';
    }
    return $h . '</div>';
}
function blog_script_files(array $names): string {
    $s = '';
    foreach ($names as $n) $s .= file_get_contents(__DIR__ . '/' . $n) . "\n";
    return $s;
}

function blog_view(string $DIR, string $csrf, string $nonce, ?array $formState): void {
    if ($formState !== null || isset($_GET['edit'])) { blog_view_edit($DIR, $csrf, $nonce, $formState); return; }
    blog_view_list($DIR, $csrf);
}

/* ================= Danh sách bài viết ================= */
function blog_view_list(string $DIR, string $csrf): void {
    $all = blog_all($DIR);
    $trashFiles = array_reverse(glob(blog_dir($DIR) . '/_trash/*.md') ?: []);
    $cnt = ['all' => count($all), 'published' => 0, 'scheduled' => 0, 'draft' => 0, 'trash' => count($trashFiles)];
    foreach ($all as $p) $cnt[blog_state($p)]++;
    $status = (string)($_GET['status'] ?? 'all');
    if (!in_array($status, ['all', 'published', 'scheduled', 'draft', 'trash'], true)) $status = 'all';
    $cat = (string)($_GET['cat'] ?? '');
    $q = trim((string)($_GET['s'] ?? ''));
    $cats = blog_cats($DIR);
    $back = blog_qs();

    $rows = [];
    if ($status !== 'trash') {
        foreach ($all as $p) {
            if ($status !== 'all' && blog_state($p) !== $status) continue;
            if ($cat !== '' && $p['category'] !== $cat) continue;
            if ($q !== '' && mb_stripos($p['title'] . ' ' . $p['slug'] . ' ' . $p['tags'], $q, 0, 'UTF-8') === false) continue;
            $rows[] = $p;
        }
    }
    // thời điểm tạo + sắp xếp (mặc định: bài mới tạo nhất lên đầu)
    $createdMap = blog_created_map($DIR);
    foreach ($rows as $i => $p) $rows[$i]['_ts'] = $createdMap[$p['slug']] ?? (int)@filemtime(blog_dir($DIR) . '/' . $p['slug'] . '.md');
    $sorts = ['new' => 'Mới tạo nhất', 'old' => 'Cũ nhất', 'date_desc' => 'Ngày đăng: mới → cũ', 'date_asc' => 'Ngày đăng: cũ → mới', 'title_asc' => 'Tiêu đề A → Z', 'title_desc' => 'Tiêu đề Z → A', 'seo' => 'Nhiều gợi ý SEO nhất'];
    $sort = (string)($_GET['sort'] ?? 'new');
    if (!isset($sorts[$sort])) $sort = 'new';
    $seoN = [];
    if ($sort === 'seo') foreach ($rows as $p) $seoN[$p['slug']] = count(blog_seo_issues($p));
    $tt = fn(string $x) => mb_strtolower($x, 'UTF-8');
    usort($rows, function ($a, $b) use ($sort, $seoN, $tt) {
        $byNew = [$b['_ts'], $a['slug']] <=> [$a['_ts'], $b['slug']];
        switch ($sort) {
            case 'old': return -$byNew;
            case 'date_desc': return ([$b['date'], $b['_ts']] <=> [$a['date'], $a['_ts']]) ?: $byNew;
            case 'date_asc': return ([$a['date'], $a['_ts']] <=> [$b['date'], $b['_ts']]) ?: $byNew;
            case 'title_asc': return strnatcmp($tt($a['title']), $tt($b['title'])) ?: $byNew;
            case 'title_desc': return strnatcmp($tt($b['title']), $tt($a['title'])) ?: $byNew;
            case 'seo': return (($seoN[$b['slug']] ?? 0) <=> ($seoN[$a['slug']] ?? 0)) ?: $byNew;
            default: return $byNew;
        }
    });
    $per = (int)($_GET['per'] ?? 20);
    if (!in_array($per, [10, 20, 50, 100], true)) $per = 20;
    $total = $status === 'trash' ? count($trashFiles) : count($rows);
    $pages = max(1, (int)ceil($total / $per));
    $page = min($pages, max(1, (int)($_GET['paged'] ?? 1)));
    $rows = array_slice($rows, ($page - 1) * $per, $per);
    $trashPage = array_slice($trashFiles, ($page - 1) * $per, $per);
    $labels = ['all' => 'Tất cả', 'published' => 'Đã đăng', 'scheduled' => 'Lên lịch', 'draft' => 'Bản nháp', 'trash' => 'Thùng rác'];
    ?>
  <div class="page">
    <div class="pagehead"><h1>Bài viết</h1><a class="btn btn--ghost sm" href="/quan-tri/?p=blog&amp;edit=">Viết bài mới</a></div>

    <div class="listbar">
      <ul class="subsubsub">
        <?php foreach ($labels as $k => $lab) { if (($k === 'scheduled' || $k === 'trash') && $cnt[$k] === 0 && $status !== $k) continue; ?>
          <li><a href="<?= h(blog_qs(['status' => $k === 'all' ? null : $k, 'paged' => null])) ?>"<?= $status === $k ? ' class="on"' : '' ?>><?= h($lab) ?> <span class="c">(<?= (int)$cnt[$k] ?>)</span></a></li>
        <?php } ?>
      </ul>
      <form method="get">
        <input type="hidden" name="p" value="blog"><?php if ($status !== 'all') { ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php } ?><?php if ($per !== 20) { ?><input type="hidden" name="per" value="<?= (int)$per ?>"><?php } ?>
        <?php if ($status !== 'trash') { ?><select name="sort" aria-label="Sắp xếp"><?php foreach ($sorts as $sk => $sl) { ?><option value="<?= h($sk) ?>"<?= $sk === $sort ? ' selected' : '' ?>><?= h($sl) ?></option><?php } ?></select><?php } ?>
        <select name="cat" aria-label="Lọc theo chuyên mục"><option value="">Tất cả chuyên mục</option><?php foreach ($cats as $c) { ?><option value="<?= h($c) ?>"<?= $c === $cat ? ' selected' : '' ?>><?= h($c) ?></option><?php } ?></select>
        <input type="search" name="s" value="<?= h($q) ?>" placeholder="Tìm bài viết…" aria-label="Tìm bài viết">
        <button class="btn btn--ghost sm" type="submit">Lọc / sắp xếp</button>
      </form>
    </div>

    <?php if ($status === 'trash' && $cnt['trash'] > 0) { ?>
      <form method="post" class="inline" data-confirm="Xóa vĩnh viễn toàn bộ thùng rác? Không khôi phục được."><input type="hidden" name="action" value="blog_empty_trash"><input type="hidden" name="csrf" value="<?= $csrf ?>"><button type="submit" class="btn btn--ghost sm danger">Dọn sạch thùng rác</button></form>
    <?php } ?>

    <form method="post" id="bulk-form">
      <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_bulk"><input type="hidden" name="back" value="<?= h($back) ?>">
      <div class="listbar">
        <div class="inline">
          <select name="bulk" aria-label="Hành động hàng loạt">
            <option value="">Hành động hàng loạt</option>
            <?php if ($status === 'trash') { ?><option value="restore">Khôi phục</option><option value="purge">Xóa vĩnh viễn</option>
            <?php } else { ?><option value="publish">Đăng</option><option value="draft">Chuyển về nháp</option><option value="trash">Chuyển vào thùng rác</option><?php } ?>
          </select>
          <button type="submit" class="btn btn--ghost sm" data-confirm-if="trash,purge">Áp dụng</button>
        </div>
        <span class="muted small"><?= (int)$total ?> mục</span>
      </div>

      <?php if ($total === 0) { ?>
        <div class="wp-list empty"><p><?= $status === 'trash' ? 'Thùng rác trống.' : ($q !== '' || $cat !== '' ? 'Không có bài nào khớp bộ lọc.' : 'Chưa có bài viết nào. Bấm “Viết bài mới” để bắt đầu.') ?></p></div>
      <?php } elseif ($status === 'trash') { ?>
      <table class="wp-list">
        <thead><tr><th class="cb"><input type="checkbox" data-checkall aria-label="Chọn tất cả"></th><th>Bài viết đã xóa</th><th>Thời gian xóa</th></tr></thead>
        <tbody>
        <?php foreach ($trashPage as $t) { $f = basename($t, '.md'); $slugT = preg_replace('/--\d{8}-\d{6}$/', '', $f); ?>
          <tr>
            <td class="cb"><input type="checkbox" name="slugs[]" value="<?= h($f) ?>" aria-label="Chọn bài đã xóa: <?= h($slugT) ?>"></td>
            <td class="title"><b><?= h($slugT) ?></b>
              <div class="rowact"><button type="submit" name="rowact" value="restore:<?= h($f) ?>" class="lnk">Khôi phục</button><span class="sep">|</span><button type="submit" name="rowact" value="purge:<?= h($f) ?>" class="lnk danger" data-confirm="Xóa vĩnh viễn bài này? Không khôi phục được.">Xóa vĩnh viễn</button></div></td>
            <td class="dt"><?= h(date('H:i d/m/Y', (int)filemtime($t))) ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
      <?php } else { ?>
      <table class="wp-list">
        <?php $thl = fn(string $label, string $asc, string $desc) => '<a class="sortlink" href="' . h(blog_qs(['sort' => $sort === $asc ? $desc : $asc, 'paged' => null])) . '">' . h($label) . ($sort === $asc ? ' ▲' : ($sort === $desc ? ' ▼' : '')) . '</a>'; ?>
        <thead><tr><th class="cb"><input type="checkbox" data-checkall aria-label="Chọn tất cả"></th><th><?= $thl('Tiêu đề', 'title_asc', 'title_desc') ?></th><th>Chuyên mục</th><th>Thẻ</th><th>SEO</th><th><?= $thl('Ngày đăng', 'date_asc', 'date_desc') ?></th><th><?= $thl('Tạo lúc', 'old', 'new') ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $p) { $iss = blog_seo_issues($p); $st = blog_state($p); $tags = blog_tags_list($p); ?>
          <tr>
            <td class="cb"><input type="checkbox" name="slugs[]" value="<?= h($p['slug']) ?>" aria-label="Chọn bài: <?= h($p['title']) ?>"></td>
            <td class="title"><a class="t" href="/quan-tri/?p=blog&amp;edit=<?= h(rawurlencode($p['slug'])) ?>"><?= h($p['title']) ?></a><?php if ($st !== 'published') { ?> <span class="flag">— <?= h(blog_state_label($st)) ?></span><?php } ?>
              <div class="rowact">
                <a href="/quan-tri/?p=blog&amp;edit=<?= h(rawurlencode($p['slug'])) ?>">Sửa</a>
                <?php if ($st === 'published') { ?><span class="sep">|</span><a href="/blog/<?= h($p['slug']) ?>/" target="_blank" rel="noopener">Xem</a><?php } ?>
                <span class="sep">|</span><button type="submit" name="rowact" value="<?= $p['status'] === 'published' ? 'draft' : 'publish' ?>:<?= h($p['slug']) ?>" class="lnk"><?= $p['status'] === 'published' ? 'Về nháp' : 'Đăng' ?></button>
                <span class="sep">|</span><button type="submit" name="rowact" value="trash:<?= h($p['slug']) ?>" class="lnk danger" data-confirm="Chuyển bài này vào thùng rác? Bạn vẫn khôi phục lại được.">Thùng rác</button>
              </div></td>
            <td class="cat"><?= $p['category'] !== '' ? '<a href="' . h(blog_qs(['cat' => $p['category'], 'paged' => null])) . '">' . h($p['category']) . '</a>' : '—' ?></td>
            <td class="tg"><?= $tags ? h(implode(', ', $tags)) : '—' ?></td>
            <td class="sgc"><?php if (!$iss) { ?><span class="chip on">Tốt</span><?php } else { ?><details class="sg"><summary><span class="chip warn"><?= count($iss) ?> gợi ý</span></summary><ul><?php foreach ($iss as $i) { ?><li><?= h($i) ?></li><?php } ?></ul></details><?php } ?></td>
            <td class="dt nowrap"><?= h(blog_state_label($st)) ?><small><?= h(blog_vn_date($p['date'])) ?></small></td>
            <td class="dt nowrap"><?= $p['_ts'] > 0 ? h(date('H:i', (int)$p['_ts'])) . '<small>' . h(date('d/m/Y', (int)$p['_ts'])) . '</small>' : '—' ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
      <?php } ?>
    </form>

    <?= admin_pager($page, $pages, $total, $per, 'paged', 'per', [10, 20, 50, 100], 20, fn(array $o) => blog_qs($o)) ?>

    <div class="rowhead muted small">
      <span>Mỗi lần lưu, bài được tạo lại thành trang tĩnh nên web luôn nhanh.</span>
      <form method="post" class="inline" data-confirm="Tạo lại toàn bộ trang blog từ các bài đã đăng?"><input type="hidden" name="action" value="blog_rebuild"><input type="hidden" name="csrf" value="<?= $csrf ?>"><button type="submit" class="lnk">Tạo lại toàn bộ trang blog</button></form>
    </div>
  </div>
<?php }

/* ================= Hộp chọn ảnh (dùng khi soạn bài) ================= */
function blog_media_modal(): void { ?>
  <dialog id="mm" class="mm" aria-labelledby="mm-title"><div class="mm__in">
    <div class="mm__h"><h2 id="mm-title">Thêm ảnh</h2><button type="button" class="lnk" id="mm-x">✕ Đóng</button></div>
    <div class="mm__tabs" role="tablist" aria-label="Nguồn ảnh"><button type="button" role="tab" aria-selected="true" data-mmtab="lib" class="on">Thư viện ảnh</button><button type="button" role="tab" aria-selected="false" data-mmtab="up">Tải ảnh lên</button></div>
    <div class="mm__body">
      <div class="mm__main" id="mm-lib"><div class="mgrid" id="mm-grid"></div></div>
      <div class="mm__main" id="mm-up" hidden><div class="drop" id="mm-drop"><p><b>Kéo thả ảnh vào đây</b></p><p class="muted">hoặc</p><label class="btn">Chọn ảnh từ máy<input type="file" id="mm-file" class="vh" accept="image/jpeg,image/png,image/webp" multiple></label><p class="small muted" id="mm-upmsg" role="status" aria-live="polite"></p><p class="small muted">Ảnh được tự nén (JPG) và tạo 3 cỡ để web tải nhanh.</p></div></div>
      <aside class="mm__side" id="mm-side">
        <img id="mm-prev" alt="" hidden>
        <div id="mm-info" class="small muted">Chọn một ảnh để xem chi tiết.</div>
        <label class="f"><span>Mô tả ảnh (alt)</span><input id="mm-alt" maxlength="160"><small>Mô tả ngắn nội dung ảnh, giúp SEO và người khiếm thị.</small></label>
        <label class="f"><span>Tiêu đề ảnh (title)</span><input id="mm-imgtitle" maxlength="120"><small>Hiện khi rê chuột và làm chú thích dưới ảnh. Để trống thì chú thích lấy theo mô tả.</small></label>
      </aside>
    </div>
    <div class="mm__f"><button type="button" class="btn btn--ghost" id="mm-cancel">Hủy</button><button type="button" class="btn" id="mm-ok" disabled>Chèn vào bài</button></div>
  </div></dialog>
<?php }

/** Popup "Gắn ảnh vào bài": 1. chọn ảnh (máy / Drive) → 2. duyệt mục, alt, chú thích → 3. chèn (attach.js). */
function blog_attach_modal(): void { ?>
  <dialog id="att" class="mm att" aria-labelledby="att-t"><div class="mm__in">
    <div class="mm__h"><h2 id="att-t">Tự động gắn ảnh vào bài</h2><button type="button" class="lnk" id="att-x">✕ Đóng</button></div>
    <ol class="att__steps" aria-label="Các bước"><li class="on" data-step="1">1. Chọn ảnh</li><li data-step="2">2. Duyệt chỗ đặt</li><li data-step="3">3. Chèn vào bài</li></ol>
    <div class="att__body">
      <section id="att-s1">
        <div class="drop" id="att-drop"><p><b>Kéo thả ảnh vào đây</b></p><label class="btn">Chọn ảnh từ máy<input type="file" id="att-file" class="vh" accept="image/jpeg,image/png,image/webp" multiple></label>
          <p class="small muted">Đặt tên tệp theo nội dung để ảnh vào đúng mục: <code>bia.jpg</code> = ảnh đại diện, <code>2-gia.jpg</code> = mục 2, <code>quy-trinh-lap.jpg</code> = mục có chữ "quy trình lắp". Ảnh tên vô nghĩa (IMG_1234…) thì AI xem ảnh để chọn mục.</p></div>
        <div class="att__drive"><input id="att-link" placeholder="hoặc dán link thư mục / ảnh Google Drive (chia sẻ: Bất kỳ ai có đường liên kết)" autocomplete="off" spellcheck="false" aria-label="Link Google Drive"><button type="button" class="btn btn--ghost sm" id="att-drive">Lấy ảnh từ Drive</button></div>
        <p class="small" id="att-msg1" role="status" aria-live="polite"></p>
        <div class="att__grid" id="att-grid"></div>
      </section>
      <section id="att-s2" hidden>
        <label class="att__chk"><input type="checkbox" id="att-replace"> Gỡ các ảnh đang có trong thân bài rồi mới chèn ảnh mới (bỏ chọn = thêm cạnh ảnh cũ)</label>
        <div class="tablewrap"><table class="att__tbl"><thead><tr><th>Ảnh</th><th>Đặt vào</th><th>Mô tả ảnh (alt) và chú thích</th></tr></thead><tbody id="att-rows"></tbody></table></div>
        <p class="small muted" id="att-msg2" role="status" aria-live="polite"></p>
      </section>
    </div>
    <div class="mm__f"><label class="small att__ai"><input type="checkbox" id="att-vision" checked> Cho AI xem ảnh để viết mô tả và chọn mục cho ảnh tên vô nghĩa</label><span class="grow"></span>
      <button type="button" class="btn btn--ghost" id="att-back" hidden>← Chọn lại ảnh</button><button type="button" class="btn btn--ghost" id="att-cancel">Hủy</button>
      <button type="button" class="btn" id="att-next" disabled>Phân tích →</button><button type="button" class="btn" id="att-ok" hidden>Chèn vào bài</button></div>
  </div></dialog>
<?php }

/* ================= Soạn / sửa bài ================= */
function blog_view_edit(string $DIR, string $csrf, string $nonce, ?array $formState): void {
    $slug = (string)($_GET['edit'] ?? '');
    $orig = '';
    $loadedVersion = '';
    if ($formState !== null) {
        $p = $formState['form'];
        $orig = (string)($formState['orig'] ?? '');
    } elseif ($slug !== '' && ($loaded = blog_load($DIR, $slug))) {
        $p = $loaded; $orig = $slug;
        $ver = (string)($_GET['ver'] ?? '');
        if ($ver !== '' && preg_match('/^\d{8}-\d{6}\.md$/', $ver) && is_file(blog_dir($DIR) . "/_versions/$slug/$ver")) {
            $v = blog_parse((string)file_get_contents(blog_dir($DIR) . "/_versions/$slug/$ver"));
            if ($v) { $p = $v; $loadedVersion = $ver; }
        }
    } elseif ($slug !== '') {
        echo '<div class="page"><div class="pagehead"><h1>Không tìm thấy bài viết</h1></div><div class="card"><p>Bài viết <code>' . h($slug) . '</code> không tồn tại, có thể đã bị xóa hoặc đổi đường dẫn.</p>'
            . '<p><a class="btn" href="/quan-tri/?p=blog">← Về danh sách bài viết</a> <a class="btn btn--ghost" href="/quan-tri/?p=blog&amp;status=trash">Xem thùng rác</a></p></div></div>';
        return;
    } else {
        $p = array_fill_keys(BLOG_KEYS, '');
        $p['body'] = '';
        $p['date'] = date('Y-m-d');
        $p['status'] = 'draft';
    }
    $p += array_fill_keys(BLOG_KEYS, '');
    $cats = blog_cats($DIR);
    if ($p['category'] !== '' && !in_array($p['category'], $cats, true)) $cats[] = $p['category'];
    $tagCounts = array_slice(blog_tag_counts(blog_all($DIR)), 0, 12, true);
    $versions = $orig !== '' ? blog_versions($DIR, $orig) : [];
    $state = $orig !== '' ? blog_state($p) : 'draft';
    $bodyHtml = trim($p['body']) !== '' ? blog_md($p['body']) : '<p><br></p>';
    $isPublishedStatus = $p['status'] === 'published' ? 'published' : 'draft';
    ?>
  <div class="page">
    <div class="pagehead">
      <h1><?= $orig !== '' ? 'Sửa bài viết' : 'Viết bài mới' ?></h1>
      <?php if ($orig !== '') { ?><a class="btn btn--ghost sm" href="/quan-tri/?p=blog&amp;edit=">Viết bài mới</a><?php } ?>
      <span class="grow"></span>
      <?php if ($orig !== '' && $state === 'published') { ?><a class="small" href="/blog/<?= h($orig) ?>/" target="_blank" rel="noopener">Xem bài trên web ↗</a><?php } ?>
    </div>
    <?php if ($loadedVersion !== '') { ?><p class="note warn">Đang xem <b>phiên bản cũ</b> (<?= h($loadedVersion) ?>). Bấm “Lưu”/“Cập nhật” nếu muốn dùng lại bản này.</p><?php } ?>

  <form method="post" id="post-form">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="orig_slug" value="<?= h($orig) ?>">
    <input type="hidden" name="status" id="f-status" value="<?= h($isPublishedStatus) ?>">
    <button type="submit" name="action" value="blog_save" id="default-save" class="vh" tabindex="-1" aria-hidden="true">Lưu</button>
    <input type="hidden" name="cover" id="f-cover" value="<?= h($p['cover']) ?>">
    <div class="edit-grid">
      <div class="edit-main">
        <div class="titlewrap">
          <input name="title" id="f-title" value="<?= h($p['title']) ?>" maxlength="140" required placeholder="Nhập tiêu đề bài viết" aria-label="Tiêu đề bài viết">
          <div class="permalink">Đường dẫn: <span>vfwild.vn/blog/</span><b id="slug-text"><?= h($p['slug']) ?></b><span>/</span>
            <button type="button" class="lnk" id="slug-edit">Sửa</button>
            <input name="slug" id="f-slug" value="<?= h($p['slug']) ?>" maxlength="80" spellcheck="false" hidden aria-label="Đường dẫn">
            <?php if ($orig !== '') { ?><span class="small">(đổi đường dẫn sẽ làm link cũ không còn dùng được)</span><?php } ?>
          </div>
        </div>

        <div>
          <div class="editor-head">
            <button type="button" class="btn btn--ghost sm" id="add-media">🖼 Thêm ảnh</button>
            <button type="button" class="btn btn--ghost sm" id="att-open" title="Tải nhiều ảnh lên, hệ thống đề xuất mục, mô tả và chú thích cho từng ảnh để bạn duyệt rồi chèn vào bài">📷 Tự động gắn ảnh vào bài</button>
            <button type="button" class="lnk small" id="att-rename" title="Đổi tên các ảnh đang mang mã ngẫu nhiên (b8c339…) thành tên theo nội dung bài và mục chứa ảnh">Đặt lại tên ảnh theo nội dung</button>
            <span class="small" id="att-note" role="status" aria-live="polite"></span>
            <div class="tabs2" role="tablist" aria-label="Chế độ soạn thảo"><button type="button" class="on" data-mode="visual" role="tab" aria-selected="true">Trực quan</button><button type="button" data-mode="md" role="tab" aria-selected="false">Văn bản</button></div>
          </div>
          <div class="rte" id="rte">
            <div class="rte__bar" id="rte-bar" role="toolbar" aria-label="Công cụ soạn thảo">
              <select id="rte-block" aria-label="Kiểu đoạn"><option value="p">Đoạn văn</option><option value="h2">Tiêu đề mục (H2)</option><option value="h3">Tiêu đề nhỏ (H3)</option><option value="blockquote">Trích dẫn</option></select>
              <span class="sep"></span>
              <button type="button" aria-pressed="false" data-cmd="bold" title="In đậm (Ctrl+B)" aria-label="In đậm"><b>B</b></button>
              <button type="button" aria-pressed="false" data-cmd="italic" title="In nghiêng (Ctrl+I)" aria-label="In nghiêng"><i>I</i></button>
              <button type="button" aria-pressed="false" data-cmd="strikeThrough" title="Gạch ngang" aria-label="Gạch ngang"><s>S</s></button>
              <span class="sep"></span>
              <button type="button" aria-pressed="false" data-cmd="insertUnorderedList" title="Danh sách dấu chấm">• Danh sách</button>
              <button type="button" aria-pressed="false" data-cmd="insertOrderedList" title="Danh sách đánh số">1. Danh sách</button>
              <span class="sep"></span>
              <button type="button" data-act="link" title="Chèn liên kết (Ctrl+K)">Liên kết</button>
              <button type="button" data-act="unlink" title="Gỡ liên kết">Gỡ link</button>
              <button type="button" data-act="table" title="Chèn bảng">Bảng</button>
              <button type="button" data-act="hr" title="Đường kẻ ngang">―</button>
              <button type="button" data-act="video" title="Đặt video của bài tại vị trí con trỏ (cần điền Link video ở cột bên phải)">▶ Video</button>
              <span class="sep"></span>
              <button type="button" data-cmd="undo" title="Hoàn tác (Ctrl+Z)" aria-label="Hoàn tác">↶</button>
              <button type="button" data-cmd="redo" title="Làm lại (Ctrl+Y)" aria-label="Làm lại">↷</button>
              <button type="button" data-act="clear" title="Xóa định dạng phần đang chọn">Xóa định dạng</button>
            </div>
            <div class="rte__ctx" id="tbl-tools" hidden>
              <span>Bảng:</span>
              <button type="button" class="lnk" data-act="row+">+ Hàng</button><button type="button" class="lnk" data-act="col+">+ Cột</button>
              <button type="button" class="lnk" data-act="row-">− Hàng</button><button type="button" class="lnk" data-act="col-">− Cột</button>
              <button type="button" class="lnk danger" data-act="tbl-del">Xóa bảng</button>
            </div>
            <div class="rte__ctx" id="img-tools" hidden>
              <span>Ảnh đã chọn:</span>
              <button type="button" class="lnk" data-act="img-alt">Sửa mô tả ảnh</button>
              <button type="button" class="lnk danger" data-act="img-del">Xóa ảnh</button>
            </div>
            <div class="rte__area" id="wys" contenteditable="true" spellcheck="true" role="textbox" aria-multiline="true" aria-label="Nội dung bài viết" data-placeholder="Bắt đầu viết bài ở đây… Gõ trực tiếp, dùng thanh công cụ phía trên để định dạng, hoặc dán nội dung từ Word / Google Docs."><?= $bodyHtml ?></div>
          </div>
          <textarea name="body" id="f-body" rows="22" hidden spellcheck="false" aria-label="Nội dung (Markdown)"><?= h($p['body']) ?></textarea>
          <div class="editor-foot"><span id="wc"></span><span>Nhấn “Văn bản” để xem/sửa mã Markdown</span></div>
        </div>

        <div class="box">
          <div class="box__h">SEO – hiển thị trên Google</div>
          <div class="box__b">
            <div class="serp" aria-label="Xem trước kết quả Google"><div class="serp__url" id="serp-url"></div><div class="serp__title" id="serp-title"></div><div class="serp__desc" id="serp-desc"></div></div>
            <label class="f"><span>Tiêu đề trên Google <small>(bỏ trống = dùng tiêu đề bài)</small></span><input name="seo_title" id="f-seo" value="<?= h($p['seo_title']) ?>" maxlength="90"><small id="c-seo"></small></label>
            <label class="f"><span>Tóm tắt – mô tả ngắn <small>(70–160 ký tự)</small></span><textarea name="description" id="f-desc" rows="3" maxlength="300"><?= h($p['description']) ?></textarea><small id="c-desc"></small></label>
          </div>
        </div>
      </div>

      <aside class="edit-side">
        <div class="box" id="publish">
          <div class="box__h">Đăng</div>
          <div class="box__b">
            <div class="pub-row"><span>Trạng thái:</span><b><?= $orig !== '' ? h(blog_state_label($state)) : 'Chưa lưu' ?></b></div>
            <div class="pub-row"><span>Hiển thị:</span><b>Công khai</b></div>
            <label class="pub-row"><span>Ngày đăng:</span><input type="date" name="date" id="f-date" value="<?= h($p['date']) ?>"></label>
            <small class="muted">Chọn ngày trong tương lai để lên lịch.</small>
            <div class="pub-row danger small" id="dirty" hidden><span>● Có thay đổi chưa lưu</span></div>
            <div class="pub-btns">
              <button type="submit" name="action" value="blog_save" id="draft-btn" class="btn btn--ghost sm">Lưu nháp</button>
              <button type="submit" name="action" value="blog_preview_page" formtarget="_blank" id="preview-btn" class="btn btn--ghost sm" title="Mở bản xem trước giống hệt trang thật ở tab mới">Xem trước ↗</button>
            </div>
          </div>
          <div class="box__f">
            <?php if ($orig !== '') { ?><button type="submit" form="trash-form" class="lnk danger">Chuyển vào thùng rác</button><?php } else { ?><span></span><?php } ?>
            <button type="submit" name="action" value="blog_save" id="save-btn" class="btn">Đăng</button>
          </div>
        </div>

        <div class="box">
          <div class="box__h">Chuyên mục</div>
          <div class="box__b">
            <div class="catlist">
              <?php foreach ($cats as $c) { ?><label><input type="radio" name="category" value="<?= h($c) ?>"<?= $c === $p['category'] ? ' checked' : '' ?>> <?= h($c) ?></label><?php } ?>
            </div>
            <input type="text" name="new_category" id="new-cat" placeholder="+ Thêm chuyên mục mới" maxlength="40" autocomplete="off" aria-label="Thêm chuyên mục mới">
          </div>
        </div>

        <div class="box">
          <div class="box__h">Thẻ</div>
          <div class="box__b">
            <input type="text" name="tags" id="f-tags" value="<?= h($p['tags']) ?>" placeholder="Ngăn cách bằng dấu phẩy" autocomplete="off" aria-label="Thẻ">
            <?php if ($tagCounts) { ?><div class="small muted">Chọn từ các thẻ hay dùng:</div><div class="tagchips"><?php foreach ($tagCounts as $t => $n) { ?><button type="button" data-tag="<?= h((string)$t) ?>"><?= h((string)$t) ?></button><?php } ?></div><?php } ?>
          </div>
        </div>

        <div class="box featured">
          <div class="box__h">Ảnh đại diện</div>
          <div class="box__b">
            <img id="cover-prev" alt="" <?= $p['cover'] !== '' ? 'src="' . h($p['cover']) . '"' : 'hidden' ?>>
            <div class="acts"><button type="button" class="lnk" id="cover-set">Đặt ảnh đại diện</button><button type="button" class="lnk danger" id="cover-rm" hidden>Xóa ảnh đại diện</button></div>
            <label class="f" id="cover-alt-wrap"><span>Mô tả ảnh (alt)</span><input name="cover_alt" id="f-alt" value="<?= h($p['cover_alt']) ?>" maxlength="160"><small>Giúp SEO và người khiếm thị.</small></label>
            <label class="f" id="cover-title-wrap"><span>Tiêu đề ảnh (title)</span><input name="cover_title" id="f-ctitle" value="<?= h($p['cover_title'] ?? '') ?>" maxlength="160"><small>Hiện khi rê chuột lên ảnh. Có thể để trống.</small></label>
          </div>
        </div>

        <div class="box">
          <div class="box__h">Video</div>
          <div class="box__b">
            <label class="f"><span>Link video</span><input name="video" id="f-video" value="<?= h($p['video'] ?? '') ?>" placeholder="Dán link YouTube, Vimeo hoặc tệp .mp4" autocomplete="off" spellcheck="false" inputmode="url"><small id="video-note">Mặc định video hiện sau đoạn mở đầu. Muốn đặt chỗ khác: đưa con trỏ tới đoạn muốn rồi bấm nút “▶ Video” trên thanh công cụ. Để trống nếu không có video.</small></label>
          </div>
        </div>

        <div class="box">
          <div class="box__h"><span>Kiểm tra SEO</span><span class="score" id="score"></span></div>
          <div class="box__b"><div class="checks" id="checks" aria-live="polite"></div></div>
        </div>

        <?php if ($versions) { ?>
        <div class="box">
          <div class="box__h">Phiên bản cũ</div>
          <div class="box__b"><p class="muted small">Mỗi lần lưu, bản trước đó được giữ lại (tối đa 10).</p>
            <div class="chips"><?php foreach ($versions as $v) { ?><a class="chip" href="/quan-tri/?p=blog&amp;edit=<?= h(rawurlencode($orig)) ?>&amp;ver=<?= h($v) ?>"><?= h(preg_replace('/^(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})\.md$/', '$3/$2/$1 $4:$5', $v)) ?></a><?php } ?></div></div>
        </div>
        <?php } ?>
      </aside>
    </div>
    <button type="submit" name="action" value="blog_save" id="save-fab" class="btn fab">Lưu</button>
  </form>
  <?php if ($orig !== '') { ?>
  <form id="trash-form" method="post" hidden data-confirm="Chuyển bài này vào thùng rác? Bạn vẫn khôi phục lại được.">
    <input type="hidden" name="action" value="blog_delete"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="slug" value="<?= h($orig) ?>"><input type="hidden" name="back" value="/quan-tri/?p=blog&amp;edit=x">
  </form>
  <?php } ?>
  </div>

  <dialog id="dlg" class="dlg" aria-labelledby="dlg-t">
    <form method="dialog">
      <h3 id="dlg-t"></h3>
      <label class="f"><span id="dlg-l"></span><input id="dlg-i" autocomplete="off"></label>
      <label class="f" id="dlg-w2" hidden><span id="dlg-l2"></span><input id="dlg-i2" autocomplete="off"></label>
      <div class="dlg__b"><button type="button" class="btn btn--ghost sm" id="dlg-c">Hủy</button><button type="submit" class="btn sm" value="ok">Đồng ý</button></div>
    </form>
  </dialog>
  <?php blog_media_modal(); blog_attach_modal(); ?>

  <script nonce="<?= h($nonce) ?>">window.VFW = <?= json_encode(['csrf' => $csrf, 'status' => $isPublishedStatus, 'orig' => $orig], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
  <script nonce="<?= h($nonce) ?>">
<?= blog_script_files(['media.js', 'editor.js', 'attach.js']) ?>
  </script>
<?php }

/* ================= Thư viện ảnh ================= */
function blog_view_media(string $DIR, string $csrf, string $nonce): void {
    $items = blog_media_items($DIR);
    $nUp = count(array_filter($items, fn($i) => $i['uploaded']));
    $mPer = admin_per('mper', [24, 48, 96], 48);
    [$pageItems, $mPage, $mPages] = admin_slice($items, 'mp', $mPer);
    ?>
  <div class="page">
    <div class="pagehead"><h1>Thư viện ảnh</h1><label class="btn btn--ghost sm" id="add-new">Thêm ảnh mới<input type="file" id="media-file" class="vh" accept="image/jpeg,image/png,image/webp" multiple></label><span class="muted small" id="media-msg" role="status" aria-live="polite"></span></div>
    <p class="muted small"><?= count($items) ?> ảnh (<?= (int)$nUp ?> ảnh bạn đã tải lên, còn lại là ảnh có sẵn của trang). Chỉ xóa được ảnh đã tải lên và chưa dùng trong bài nào.</p>
    <?php if (!$items) { ?><div class="wp-list empty"><p>Chưa có ảnh nào. Bấm “Thêm ảnh mới” để tải ảnh đầu tiên.</p></div><?php } ?>
    <div class="mpage">
      <?php foreach ($pageItems as $it) { ?>
        <div class="mcard">
          <img src="<?= h($it['thumb']) ?>" alt="" loading="lazy">
          <div class="mcard__b">
            <b><?= h($it['label']) ?></b>
            <span class="muted"><?= (int)$it['w'] ?>×<?= (int)$it['h'] ?> px · <?= h($it['bytes'] > 1048576 ? round($it['bytes'] / 1048576, 1) . ' MB' : round($it['bytes'] / 1024) . ' KB') ?></span>
            <span class="muted"><?= $it['used'] > 0 ? 'Đang dùng trong ' . (int)$it['used'] . ' bài' : 'Chưa dùng' ?></span>
            <span class="rowact"><a href="<?= h($it['insert']) ?>" target="_blank" rel="noopener">Xem ảnh</a>
              <?php if ($it['uploaded'] && $it['used'] === 0) { ?><span class="sep">|</span>
                <form method="post" class="inline" data-confirm="Xóa ảnh này vĩnh viễn?"><input type="hidden" name="action" value="blog_media_delete"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="id" value="<?= h($it['id']) ?>"><input type="hidden" name="mp" value="<?= (int)$mPage ?>"><input type="hidden" name="mper" value="<?= (int)$mPer ?>"><button type="submit" class="lnk danger">Xóa</button></form>
              <?php } ?></span>
          </div>
        </div>
      <?php } ?>
    </div>
    <?= admin_pager($mPage, $mPages, count($items), $mPer, 'mp', 'mper', [24, 48, 96], 48) ?>
  </div>
  <script nonce="<?= h($nonce) ?>">window.VFW = <?= json_encode(['csrf' => $csrf], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
  <script nonce="<?= h($nonce) ?>">
<?= blog_script_files(['media.js']) ?>
  (function () {
    var inp = document.getElementById('media-file'), msg = document.getElementById('media-msg');
    inp.addEventListener('change', function () {
      var files = Array.prototype.filter.call(this.files, function (f) { return /^image\//.test(f.type); }), i = 0;
      if (!files.length) return;
      (function next() {
        if (i >= files.length) { msg.textContent = 'Xong, đang tải lại…'; location.href = '/quan-tri/?p=media'; return; }
        msg.textContent = 'Đang nén và tải ảnh ' + (i + 1) + '/' + files.length + '…';
        VFWM.uploadFile(files[i++]).then(next).catch(function (e) { msg.textContent = 'Lỗi: ' + e.message; });
      })();
    });
  })();
  </script>
<?php }

/* ================= Chuyên mục ================= */
function blog_view_cats(string $DIR, string $csrf): void {
    $cats = blog_cats($DIR);
    $counts = blog_cat_counts($DIR);
    ?>
  <div class="page">
    <div class="pagehead"><h1>Chuyên mục</h1></div>
    <div class="cats-grid">
      <form method="post" class="card">
        <h2>Thêm chuyên mục mới</h2>
        <input type="hidden" name="action" value="blog_cat_add"><input type="hidden" name="csrf" value="<?= $csrf ?>">
        <label class="f"><span>Tên chuyên mục</span><input name="name" maxlength="40" required placeholder="Ví dụ: Khuyến mãi"><small>Hiện ở danh sách bài và trên thẻ bài viết.</small></label>
        <button class="btn" type="submit">Thêm chuyên mục</button>
      </form>
      <div class="tbl">
        <table class="wp-list">
          <thead><tr><th>Tên</th><th>Số bài</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($cats as $c) { $n = $counts[$c] ?? 0; ?>
            <tr>
              <td class="title"><b><?= h($c) ?></b>
                <details class="guide"><summary class="small">Đổi tên</summary>
                  <form method="post" class="inline"><input type="hidden" name="action" value="blog_cat_rename"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="old" value="<?= h($c) ?>">
                    <input name="name" value="<?= h($c) ?>" maxlength="40" aria-label="Tên mới"> <button class="btn btn--ghost sm" type="submit">Lưu tên</button></form>
                </details></td>
              <td><?= $n > 0 ? '<a href="/quan-tri/?p=blog&amp;cat=' . h(rawurlencode($c)) . '">' . (int)$n . '</a>' : '0' ?></td>
              <td class="rowact"><?php if ($n === 0) { ?><form method="post" class="inline" data-confirm="Xóa chuyên mục này?"><input type="hidden" name="action" value="blog_cat_delete"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="old" value="<?= h($c) ?>"><button type="submit" class="lnk danger">Xóa</button></form><?php } else { ?><span class="muted small">Đang có bài</span><?php } ?></td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php }

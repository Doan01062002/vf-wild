<?php
/** Trang quản lý "Tự động viết bài": lịch đăng lấy từ Google Sheet, cài đặt tối giản. */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }

function auto_ago(int $ts): string { return $ts > 0 ? date('H:i d/m', $ts) : '—'; }

/** [nhãn, lớp màu] cho một dòng trong Sheet. */
function auto_row_label(array $r, array $s, int $now): array {
    switch ($r['status']) {
        case 'Đã đăng': return ['Đã đăng', 'on'];
        case 'Lỗi': return ['Lỗi', 'bad'];
        case 'Tạm dừng': return ['Tạm dừng', 'off'];
        case 'Đang xử lý': return ['Đang viết…', 'off'];
    }
    if ($r['status'] === 'Ý tưởng') return ['Ý tưởng – chưa duyệt, sẽ không đăng', 'off'];
    if (!in_array($r['status'], AUTO_READY, true)) return [$r['status'] !== '' ? $r['status'] : 'Chưa có trạng thái', 'off'];
    if ($r['when'] === null) return [$r['when_raw'] === '' ? 'Chưa có giờ đăng' : 'Giờ đăng không đọc được', 'bad'];
    if ($r['attempts'] >= (int)$s['max_attempts']) return ['Hết lượt thử', 'bad'];
    return $r['when'] <= $now ? ['Đến giờ – sắp viết', 'on'] : ['Chờ đến giờ', 'off'];
}

function blog_view_auto(string $DIR, string $csrf): void {
    $s = auto_settings($DIR);
    $st = auto_state($DIR);
    $now = time();
    $mock = $s['provider'] === 'mock';
    $sheetId = auto_sheet_id($s);
    $acc = gs_account($DIR);
    $rows = []; $sheetErr = '';
    if ($sheetId && $acc) {
        try { $rows = auto_sheet_rows($DIR, $s); } catch (Throwable $e) { $sheetErr = $e->getMessage(); }
    }
    $upcoming = array_values(array_filter($rows, fn($r) => in_array($r['status'], AUTO_READY, true) && $r['attempts'] < (int)$s['max_attempts']));
    usort($upcoming, fn($a, $b) => [$a['when'] ?? PHP_INT_MAX, $a['row']] <=> [$b['when'] ?? PHP_INT_MAX, $b['row']]);
    $upRows = array_column($upcoming, 'row');
    $others = array_values(array_filter($rows, fn($r) => !in_array($r['row'], $upRows, true)));
    usort($others, fn($a, $b) => [$b['updated'], $b['row']] <=> [$a['updated'], $a['row']]);
    $show = array_slice(array_merge($upcoming, $others), 0, 60);
    $next = $upcoming[0] ?? null;
    $nextText = !$s['enabled'] ? 'Đang tắt' : (!$next ? 'Chưa có bài nào chờ' : ($next['when'] === null ? 'Dòng kế tiếp chưa có giờ đăng' : date('H:i d/m/Y', $next['when']) . ' – ' . $next['keyword']));
    $facts = auto_facts($DIR);
    $sample = ['keyword' => 'VF Wild giá lăn bánh tại Hà Nội', 'type' => 'thu-tuc', 'form' => 'Hướng dẫn', 'intent' => 'Mua hàng', 'sub' => 'chi phí lăn bánh vf wild', 'note' => '', 'category' => 'Giá & ưu đãi'];
    $promptPreview = auto_prompt($s, $sample, $facts, auto_link_candidates(blog_all($DIR)), blog_cats($DIR), review_playbook_text($DIR));
    $log = auto_log_tail($DIR, 30);
    ?>
  <div class="page">
    <div class="pagehead"><h1>Tự động viết bài</h1>
      <span class="chip <?= $s['enabled'] ? 'on' : 'off' ?>"><?= $s['enabled'] ? '● Đang BẬT' : '○ Đang TẮT' ?></span></div>

    <?php if ($mock) { ?>
      <div class="note warn"><b>Đang dùng bộ viết thử nghiệm.</b> Hệ thống ghép bài từ bộ dữ kiện để kiểm tra luồng, nên bài chỉ được <b>lưu nháp</b>, chưa tự đăng. Chọn nhà cung cấp AI thật ở mục Cài đặt để bài tự đăng.</div>
    <?php } elseif ($s['mode'] === 'draft') { ?>
      <div class="note warn"><b>Đang ở chế độ thử:</b> bài chỉ được lưu nháp để bạn xem, chưa tự đăng. Tắt “Chỉ lưu nháp” trong Cài đặt → Nâng cao để tự đăng.</div>
    <?php } ?>

    <section class="card">
      <div class="rowhead">
        <h2>Trạng thái</h2>
        <div class="inline">
          <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_auto_toggle"><input type="hidden" name="enabled" value="<?= $s['enabled'] ? '0' : '1' ?>">
            <button type="submit" class="btn <?= $s['enabled'] ? 'btn--ghost' : '' ?>"><?= $s['enabled'] ? 'Tắt tự động' : 'Bật tự động' ?></button></form>
          <form method="post" class="inline" data-confirm="Viết ngay dòng kế tiếp trong Sheet, bỏ qua giờ đăng? Với AI thật việc này có thể mất 1–3 phút."><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_auto_run"><button type="submit" class="btn btn--ghost">Chạy ngay 1 bài</button></form>
        </div>
      </div>
      <div class="statgrid">
        <div><span class="muted small">Bài kế tiếp</span><b><?= h($nextText) ?></b></div>
        <div><span class="muted small">Hôm nay</span><b><?= (int)$st['day_count'] ?> bài (không giới hạn)</b></div>
        <div><span class="muted small">Nhà cung cấp AI</span><b><?= h(AUTO_PROVIDERS[$s['provider']]['short']) ?><?= $s['provider'] !== 'mock' && auto_model($s, $s['provider']) !== '' ? ' · ' . h(auto_model($s, $s['provider'])) : '' ?></b></div>
        <div><span class="muted small">Google Sheet</span><b><?= !$sheetId ? 'Chưa nhập link' : (!$acc ? 'Thiếu khóa tài khoản dịch vụ' : ($sheetErr !== '' ? 'Lỗi kết nối' : 'Đã kết nối – ' . count($rows) . ' từ khóa')) ?></b></div>
      </div>
      <?php if ($st['last_run']) { ?><p class="small"><span class="muted">Lần chạy cuối <?= h(auto_ago((int)$st['last_run'])) ?>:</span> <?= h((string)$st['last_msg']) ?></p><?php } ?>
      <details class="guide"><summary>Cài lịch chạy tự động trên VPS (làm một lần)</summary>
        <p class="muted small">Cron gọi lệnh dưới đây mỗi 5 phút, không cần mở máy hay mở trang này. Mỗi lần chỉ viết tối đa một bài đã đến giờ trong Sheet.</p>
        <p><code>*/5 * * * * www-data php /var/www/vfwild/quan-tri/index.php --auto-run</code></p>
      </details>
    </section>

    <section class="card">
      <div class="rowhead"><h2>Lịch đăng (lấy từ Google Sheet)</h2>
        <?php if ($sheetId) { ?><a class="btn btn--ghost" href="https://docs.google.com/spreadsheets/d/<?= h($sheetId) ?>/edit" target="_blank" rel="noopener">Mở Google Sheet ↗</a><?php } ?></div>
      <p class="muted small">Cách dùng nhanh: chỉ cần điền <b>Từ khóa chính</b>, giờ đăng và đổi Trạng thái sang “Sẵn sàng” — <b>AI tự điền các ô còn trống</b> (Chuyên mục, Dạng bài, Ý định, Ưu tiên, Từ khóa phụ, Ghi chú / góc viết kèm dàn ý H1/H2) rồi mới viết; ô bạn đã nhập được giữ nguyên (bật/tắt ở Cài đặt → “AI điền cột còn trống”). Chi tiết: ở tab “Từ khóa”, ghi <b>giờ đăng vào cột “Ngày đăng mong muốn”</b> (ví dụ <code>2026-11-01 09:30</code>; chỉ ghi ngày thì dùng giờ mặc định <?= (int)$s['default_hour'] ?>:00) và đổi <b>cột “Trạng thái” sang “Sẵn sàng”</b>. Muốn dùng ảnh riêng, dán link ảnh (Google Drive để “Bất kỳ ai có đường liên kết”, hoặc link ảnh https) vào <b>cột “Ảnh (link)”</b>, nhiều ảnh cách nhau dấu <code>;</code> (hoặc dán link thư mục Drive). <b>Đặt tên tệp theo mục để ảnh vào đúng chỗ</b>: tiêu đề H2 của bài được tự đánh số 1., 2., 3.…; tệp <code>2-pin-sac-nhanh.jpg</code> (số đầu tên = số mục; hợp lệ: <code>2-…</code>, <code>02_…</code>, <code>muc2-…</code>, nhiều ảnh cùng mục thì <code>2a-…</code>, <code>2b-…</code>) sẽ nằm ở mục số 2, và AI viết mục đó đúng chủ đề tên tệp gợi ý. Ảnh bìa: tên bắt đầu bằng <code>0</code> hoặc <code>bia</code> (vd <code>0-vf-wild.jpg</code>); không đặt thì lấy ảnh không số đầu tiên. Không muốn đánh số thì chỉ cần <b>đặt tên tệp theo chủ đề</b> (vd <code>pin-sac-nhanh.jpg</code>, <code>bao-hanh.jpg</code>): ảnh được đặt vào mục có tiêu đề khớp chữ trong tên (từ 60% chữ trở lên), AI cũng được dặn viết mục đó; ảnh nào tên không khớp mục nào thì AI xem ảnh rồi tự chèn đúng chỗ. <b>Cột “Ghi chú / góc viết”</b> nhận cả dàn ý hoàn chỉnh: dòng <code>H1:</code> là tiêu đề bài, <code>H2:</code> / <code>H3:</code> là các mục (AI giữ đúng thứ tự và tên), <code>Từ khóa:</code> trong mỗi mục là từ khóa lồng vào mục đó, <code>Mở bài:</code> là chỉ dẫn viết đoạn mở đầu; ghi “tối ưu 1200 từ” thì bài dài khoảng 1200 từ (không ghi thì không giới hạn tối đa); yêu cầu như “bỏ mục …”, “sửa địa chỉ …” được làm đúng, các mục còn lại tự đánh số lại. Số liệu ghi trong ghi chú được dùng như dữ kiện. Chỉ dòng “Sẵn sàng” mới được đăng, đúng vào giờ đã ghi; dòng “Ý tưởng” không bao giờ tự đăng. Kết quả được ghi lại ở các cột Trạng thái, Mã, Tiêu đề bài, Link bài… Muốn hoãn một bài: đổi Trạng thái thành “Tạm dừng”.</p>
      <?php if ($sheetErr !== '') { ?><div class="note warn"><?= h($sheetErr) ?></div><?php } ?>
      <?php if (!$show) { ?>
        <div class="empty"><p><?= $sheetId && $acc && $sheetErr === '' ? 'Sheet chưa có từ khóa nào.' : 'Nhập link Google Sheet và cấp quyền cho tài khoản dịch vụ ở mục Cài đặt bên dưới để hiện lịch đăng.' ?></p></div>
      <?php } else { ?>
      <div class="tbl"><table class="wp-list">
        <thead><tr><th>Từ khóa</th><th>Giờ đăng</th><th>Trạng thái</th><th>Kết quả</th></tr></thead>
        <tbody>
        <?php foreach ($show as $r) { [$lab, $cls] = auto_row_label($r, $s, $now); ?>
          <tr>
            <td class="title"><b><?= h($r['keyword']) ?></b><?php if ($r['category'] !== '') { ?> <span class="chip"><?= h($r['category']) ?></span><?php } ?><?php if ($r['images_cell'] !== '') { ?> <span class="chip on"><?= preg_match('#/folders/|folderview#', $r['images_cell']) ? 'thư mục ảnh' : count(img_parse_cell($r['images_cell'])) . ' ảnh' ?></span><?php } ?></td>
            <td class="nowrap small"><?= $r['when'] !== null ? h(date('H:i d/m/Y', $r['when'])) : h($r['when_raw'] !== '' ? $r['when_raw'] : '—') ?></td>
            <td><span class="st <?= h($cls) ?>"><?= h($lab) ?></span><?php if ($r['attempts'] > 0 && $r['status'] !== 'Đã đăng') { ?><small class="muted"> · thử lần <?= (int)$r['attempts'] ?></small><?php } ?></td>
            <td class="small"><?php if (preg_match('#^https://#', $r['link'])) { ?><a href="<?= h($r['link']) ?>" target="_blank" rel="noopener">Xem bài ↗</a><?php if ($r['error'] !== '') { ?><br><span class="muted"><?= h(mb_substr($r['error'], 0, 160)) ?></span><?php } } elseif ($r['error'] !== '') { ?><span class="<?= $r['status'] === 'Lỗi' ? 'danger' : 'muted' ?>"><?= h(mb_substr($r['error'], 0, 160)) ?></span><?php } else { ?>—<?php } ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table></div>
      <?php } ?>
    </section>

    <section class="card">
      <h2>Cài đặt</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_auto_settings">
        <label class="f"><span>Link Google Sheet</span>
          <input name="sheet_url" value="<?= h($s['sheet_url']) ?>" placeholder="https://docs.google.com/spreadsheets/d/…" autocomplete="off">
          <small><?php if ($acc) { ?>Chia sẻ Sheet với quyền <b>Chỉnh sửa</b> cho <code><?= h((string)$acc['client_email']) ?></code> để hệ thống đọc lịch và ghi lại kết quả.<?php } else { ?>Chưa thấy khóa tài khoản dịch vụ Google: đặt file <code>google-service-account.json</code> vào thư mục dữ liệu riêng của trang.<?php } ?></small></label>
        <label class="f"><span>Giờ đăng mặc định (khi Sheet chỉ ghi ngày)</span><input type="number" name="default_hour" min="0" max="23" value="<?= (int)$s['default_hour'] ?>"></label>

        <fieldset class="provs"><legend>Nhà cung cấp AI – chọn một để viết bài</legend>
          <?php foreach (AUTO_PROVIDERS as $pk => $pi) { $on = $s['provider'] === $pk; ?>
          <div class="prov<?= $on ? ' on' : '' ?>">
            <label class="prov__pick"><input type="radio" name="provider" value="<?= h($pk) ?>"<?= $on ? ' checked' : '' ?>> <b><?= h($pi['label']) ?></b><?php if (!empty($pi['note'])) { ?> <small class="muted">· <?= h($pi['note']) ?></small><?php } ?></label>
            <?php if ($pk !== 'mock') { $kh = auto_secret_hint($DIR, $pi['key']); $cached = auto_models_cached($DIR, $pk); ?>
            <div class="two">
              <label class="f"><span>Model</span><input name="model[<?= h($pk) ?>]" value="<?= h(auto_model($s, $pk)) ?>" list="ml-<?= h($pk) ?>" autocomplete="off" spellcheck="false" placeholder="<?= $pk === 'openrouter_api' ? 'Bấm “Tải danh sách model” rồi chọn' : '' ?>"><datalist id="ml-<?= h($pk) ?>"><?php foreach ($cached as $mid) { ?><option value="<?= h($mid) ?>"><?php } ?></datalist></label>
              <div class="f"><label for="k-<?= h($pk) ?>">Khóa API <?php if ($kh !== '') { ?><span class="chip on">đã có <?= h($kh) ?></span><?php } ?></label><input id="k-<?= h($pk) ?>" type="password" name="key[<?= h($pk) ?>]" value="" placeholder="<?= $kh !== '' ? 'Để trống để giữ khóa hiện tại' : 'Dán khóa từ ' . h($pi['link']) ?>" autocomplete="new-password" spellcheck="false"><?php if ($kh !== '') { ?><small><input id="kc-<?= h($pk) ?>" type="checkbox" name="keyclear[<?= h($pk) ?>]" value="1"> <label for="kc-<?= h($pk) ?>">Xóa khóa đã lưu</label></small><?php } ?></div>
            </div>
            <?php } ?>
          </div>
          <?php } ?>
          <p class="muted small">Khóa chỉ lưu trên máy chủ, không hiển thị lại. Hai nút dưới sẽ lưu cài đặt trước rồi mới thử, và chỉ thử nhà cung cấp đang được chọn.</p>
        </fieldset>
        <details class="guide"><summary>Nâng cao</summary>
          <div class="two">
            <div class="f"><span>Chế độ thử</span><span><input id="dro" type="checkbox" name="draft_only" value="1"<?= $s['mode'] === 'draft' ? ' checked' : '' ?>> <label for="dro">Chỉ lưu nháp, không tự đăng</label></span><small>Bài vào mục Bản nháp để bạn đọc trước.</small></div>
          </div>
          <div class="two">
            <label class="f"><span>Số từ tối thiểu</span><input type="number" name="min_words" min="300" max="10000" value="<?= (int)$s['min_words'] ?>"></label>
            <label class="f"><span>Điểm chất lượng tối thiểu (50–100)</span><input type="number" name="min_score" min="50" max="100" value="<?= (int)$s['min_score'] ?>"></label>
          </div>
          <div class="f"><span>Học từ review</span><span><input id="rw" type="checkbox" name="review_weekly" value="1"<?= !empty($s['review_weekly']) ? ' checked' : '' ?>> <label for="rw">Mỗi tuần tự đề xuất quy tắc từ các bài nhân viên đã sửa (cần từ 3 bài)</label></span><small>Quy tắc chỉ có hiệu lực sau khi Quản trị duyệt ở mục Review và học.</small></div>
          <label class="f"><span>Số ảnh tối đa mỗi bài (từ link trong Sheet)</span><input type="number" name="max_images" min="1" max="<?= IMG_HARD_MAX ?>" value="<?= (int)($s['max_images'] ?? IMG_DEFAULT_MAX) ?>"><small>Thư mục có nhiều ảnh hơn mức này thì lấy theo thứ tự tên tệp. Nhiều ảnh thì bài chèn nhiều ảnh hơn, và mỗi ảnh tốn thêm token khi AI xem (trần cứng <?= IMG_HARD_MAX ?>).</small></label>
          <div class="f"><span>Cho AI xem ảnh</span><span><input id="iv" type="checkbox" name="img_vision_always" value="1"<?= !empty($s['img_vision_always']) ? ' checked' : '' ?>> <label for="iv">Luôn gửi ảnh cho AI xem (tốn token hơn)</label></span><small>Mặc định tắt: khi MỌI ảnh trong bài đã đặt tên nhận dạng được (số mục như <code>2-pin.jpg</code> hoặc chủ đề như <code>pin-sac-nhanh.jpg</code>) thì ảnh không gửi cho AI, vị trí do tên tệp quyết định và mô tả ảnh lấy từ tiêu đề mục, dành token cho việc viết bài. Chỉ cần một ảnh tên vô nghĩa (IMG_1234…) thì cả bộ ảnh vẫn được gửi để AI xem.</small></div>
          <div class="f"><span>Bài tham khảo</span><span><input id="rf" type="checkbox" name="ref_enabled" value="1"<?= !empty($s['ref_enabled']) ? ' checked' : '' ?>> <label for="rf">Tìm bài cùng chủ đề trên chungauto.vn để lấy dàn ý và dữ kiện</label></span><small>Bật (mặc định): khi chỉ có từ khóa, hệ thống tìm bài khớp từ khóa nhất trên chungauto.vn (theo sitemap), dùng dàn ý của bài đó làm gợi ý (nếu ô Ghi chú chưa có dàn ý) và nội dung làm dữ kiện, rồi dặn AI viết lại bằng lời mới và làm tốt hơn. Bài chép câu chữ từ 30% trở lên bị bắt viết lại; từ 10% thì có nhắc trong cột Thông báo. Không tìm thấy bài đủ khớp thì viết như bình thường.</small></div>
          <div class="f"><span>Ảnh khi dòng không có link</span><span><input id="ac" type="checkbox" name="auto_cover" value="1"<?= !empty($s['auto_cover']) ? ' checked' : '' ?>> <label for="ac">Tự chọn ảnh có sẵn của website làm ảnh bìa</label></span><small>Mặc định tắt: dòng nào không dán link ảnh trong Sheet thì bài không có ảnh. Ảnh bìa gắn trong trình soạn bài vẫn thêm tay được.</small></div>
          <div class="f"><span>AI điền cột còn trống</span><span><input id="ap" type="checkbox" name="auto_plan" value="1"<?= !empty($s['auto_plan']) ? ' checked' : '' ?>> <label for="ap">Khi dòng ở trạng thái “Sẵn sàng”, AI tự điền các ô còn trống (Chuyên mục, Dạng bài, Ý định, Ưu tiên, Từ khóa phụ)</label></span><small>Chỉ cần điền Từ khóa chính, Ngày đăng và đổi Trạng thái sang “Sẵn sàng”. AI chỉ điền ô đang trống, không đè ô bạn đã nhập. Ô “Ghi chú / góc viết” (dàn ý, góc nhìn) do bạn tự điền, AI không điền. Cần chọn nhà cung cấp AI thật (không phải “Thử nghiệm”).</small></div>
          <div class="f"><span>AI lập dàn ý trước</span><span><input id="apn" type="checkbox" name="auto_plan_note" value="1"<?= !empty($s['auto_plan_note']) ? ' checked' : '' ?>> <label for="apn">Viết dàn ý vào ô “Ghi chú / góc viết” trước, rồi mới viết bài theo dàn ý đó</label></span><small>Mặc định bật: khi ô Ghi chú trống, AI lập dàn ý (H1, mở bài, các mục H2 kèm ý chi tiết, từ khóa từng mục, ảnh nào vào mục nào nếu có link thư mục ảnh, câu hỏi thường gặp, kết luận) dựa trên bộ dữ kiện và bài cùng chủ đề trên chungauto.vn, ghi vào Sheet, rồi bài viết bám đúng dàn ý. Dòng có giờ đăng còn ở tương lai được lập dàn ý sớm (tối đa 2 dòng mỗi lượt) để bạn xem và sửa trước giờ viết. Ô Ghi chú bạn đã tự điền thì không bị ghi đè. Tắt nếu muốn tự viết toàn bộ ô này.</small></div>
          <div class="two">
            <div class="f"><span>Liên kết nội bộ</span><span><input id="bl" type="checkbox" name="backlink" value="1"<?= !empty($s['backlink']) ? ' checked' : '' ?>> <label for="bl">Sau khi đăng bài mới, tự gắn liên kết từ các bài cũ liên quan</label></span><small>Chỉ thêm liên kết vào cụm từ có sẵn trong bài cũ; bản cũ được lưu lại để khôi phục.</small></div>
            <label class="f"><span>Site Search Console (tùy chọn)</span><input name="gsc_site" value="<?= h((string)$s['gsc_site']) ?>" placeholder="sc-domain:vfwild.vn" autocomplete="off" spellcheck="false"><small>Để xem hiệu quả từng bài và gợi ý từ khóa mới ở trang Kiểm tra nội dung. Cần thêm email tài khoản dịch vụ vào Search Console.</small></label>
          </div>
          <label class="f"><span>Số lần thử lại tối đa cho mỗi bài</span><input type="number" name="max_attempts" min="1" max="5" value="<?= (int)$s['max_attempts'] ?>"></label>
          <label class="f"><span>Cụm từ không được xuất hiện (mỗi dòng một cụm)</span><textarea name="forbidden" rows="4"><?= h($s['forbidden']) ?></textarea></label>
          <label class="f"><span>Hướng dẫn viết (giọng văn, cấu trúc)</span><textarea name="tone" rows="7"><?= h($s['tone']) ?></textarea></label>
        </details>
        <div class="btnrow">
          <button class="btn" type="submit">Lưu cài đặt</button>
          <button class="btn btn--ghost" type="submit" name="do" value="test">Lưu và thử kết nối</button>
          <button class="btn btn--ghost" type="submit" name="do" value="models">Lưu và tải danh sách model</button>
        </div>
      </form>
    </section>

    <details class="card guide">
      <summary>Bộ dữ kiện (giá, thông số, chính sách) – nguồn số liệu duy nhất của bài viết</summary>
      <p class="note">AI chỉ được dùng số liệu trong bộ dữ kiện. Nếu bài có con số có đơn vị (triệu, km, kWh, %, năm…) không nằm ở đây, bài vẫn được đăng nhưng cột “Kết quả” trong Sheet ghi chú “Cần kiểm tra số liệu…” để bạn đọc lại. Khi giá hoặc ưu đãi thay đổi, hãy sửa ở đây trước.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_auto_facts">
        <label class="f"><span>Nội dung (mỗi mục bắt đầu bằng <code>## Tên mục</code>, mỗi dữ kiện một dòng bắt đầu bằng <code>- </code>)</span><textarea name="facts" rows="22" class="mono"><?= h($facts) ?></textarea></label>
        <button class="btn" type="submit">Lưu bộ dữ kiện</button>
      </form>
    </details>

    <details class="card guide">
      <summary>Xem câu lệnh gửi cho AI (ví dụ)</summary>
      <p class="muted small">Nội dung hệ thống gửi cho AI thật với một dòng mẫu, để bạn kiểm tra cách giao việc.</p>
      <textarea rows="14" readonly class="mono" aria-label="Câu lệnh mẫu"><?= h($promptPreview) ?></textarea>
    </details>

    <details class="card guide">
      <summary>Nhật ký (30 lần gần nhất)</summary>
      <?php if (!$log) { ?><p class="muted">Chưa có hoạt động nào.</p><?php } else { ?>
      <div class="tbl"><table>
        <?php foreach ($log as $e) { ?><tr><td class="nowrap"><?= h(date('H:i d/m', (int)strtotime((string)($e['t'] ?? '')))) ?></td><td><?= h((string)($e['keyword'] ?? '')) ?></td><td><b><?= h((string)($e['result'] ?? '')) ?></b></td><td class="small"><?= h(mb_substr((string)($e['msg'] ?? ''), 0, 200)) ?></td></tr><?php } ?>
      </table></div>
      <?php } ?>
    </details>
  </div>
<?php }

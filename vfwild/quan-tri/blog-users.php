<?php
/**
 * Tài khoản riêng và vai trò cho trang quản trị.
 *  - Chủ tài khoản (đăng nhập cũ trong admin.json) luôn là "Quản trị" và không bị khóa ngoài.
 *  - Người dùng thêm (users.json): Quản trị, Biên tập SEO, Chỉ xem.
 */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }

const ROLES = ['admin' => 'Quản trị', 'editor' => 'Biên tập SEO', 'viewer' => 'Chỉ xem'];
const ROLE_DESC = [
    'admin' => 'Toàn quyền: khóa API, Sheet, cài đặt tự động, mã đo lường, người dùng, duyệt quy tắc học.',
    'editor' => 'Xem và sửa bài, thư viện ảnh, review bài (không sửa cài đặt, khóa hay công tắc tự động).',
    'viewer' => 'Chỉ xem danh sách bài, kiểm tra nội dung và báo cáo review.',
];
const ROLE_CAPS = [
    'admin' => ['*'],
    'editor' => ['blog.read', 'blog.write', 'media', 'audit.read', 'review.read', 'review', 'self'],
    'viewer' => ['blog.read', 'audit.read', 'review.read', 'self'],
];

function role_can(string $role, string $cap): bool { $c = ROLE_CAPS[$role] ?? []; return in_array('*', $c, true) || in_array($cap, $c, true); }
/** Quyền cần có cho một thao tác POST (thao tác lạ → chỉ Quản trị). */
function action_cap(string $act): string {
    if ($act === 'password' || $act === 'logout') return 'self';
    if ($act === 'save') return 'tracking';
    if (strncmp($act, 'blog_auto_', 10) === 0) return 'auto';
    if (strncmp($act, 'blog_audit_', 11) === 0) return 'audit.write';
    if (strncmp($act, 'blog_user_', 10) === 0) return 'users';
    if (in_array($act, ['blog_review_rule_on', 'blog_review_rule_off', 'blog_review_rule_edit', 'blog_review_rule_del', 'blog_review_digest', 'blog_review_rule_facts'], true)) return 'review.rules';
    if (strncmp($act, 'blog_review_', 12) === 0) return 'review';
    if (strncmp($act, 'blog_', 5) === 0) return 'blog.write';
    return 'users';
}
function view_cap(string $view): string {
    return ['blog' => 'blog.read', 'cats' => 'blog.write', 'media' => 'media', 'audit' => 'audit.read', 'review' => 'review.read', 'auto' => 'auto', 'users' => 'users', 'tracking' => 'tracking', 'account' => 'self'][$view] ?? 'users';
}

function users_all(string $DIR): array {
    $j = json_decode((string)@file_get_contents("$DIR/users.json"), true);
    return is_array($j) ? array_values(array_filter($j, 'is_array')) : [];
}
function users_write(string $DIR, array $list): void {
    $f = "$DIR/users.json";
    $tmp = $f . '.tmp' . getmypid();
    file_put_contents($tmp, json_encode(array_values($list), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n", LOCK_EX);
    @chmod($tmp, 0600);
    rename($tmp, $f);
}
function users_find_email(array $users, string $email): ?array {
    $email = mb_strtolower(trim($email));
    foreach ($users as $u) { if (mb_strtolower((string)($u['email'] ?? '')) === $email) return $u; }
    return null;
}
function users_pv(array $u): string { return substr(sha1((string)($u['hash'] ?? '')), 0, 10); }
/** Người dùng thêm đăng nhập đúng email và mật khẩu (trả về bản ghi hoặc null). */
function users_login_candidate(string $DIR, array $A, string $email, string $password): ?array {
    $email = mb_strtolower(trim($email));
    if ($email === '' || $email === mb_strtolower((string)($A['username'] ?? ''))) return null;
    $u = users_find_email(users_all($DIR), $email);
    if (!$u || !empty($u['disabled']) || empty($u['hash']) || !password_verify($password, (string)$u['hash'])) return null;
    return $u;
}
function users_touch_login(string $DIR, string $id): void {
    $all = users_all($DIR);
    foreach ($all as &$u) { if (($u['id'] ?? '') === $id) $u['last_login'] = date('c'); }
    unset($u);
    users_write($DIR, $all);
}
/** Người đang đăng nhập theo phiên (null nếu tài khoản đã bị khóa/xóa hoặc mật khẩu đã đổi). */
function me_resolve(string $DIR, array $A, ?array $S): ?array {
    if (!$S) return null;
    $uid = (string)($S['u'] ?? 'owner');
    if ($uid === 'owner') return ['id' => 'owner', 'email' => (string)($A['username'] ?? ''), 'name' => 'Chủ tài khoản', 'role' => 'admin', 'owner' => true];
    foreach (users_all($DIR) as $u) {
        if (($u['id'] ?? '') === $uid && empty($u['disabled']) && hash_equals(users_pv($u), (string)($S['pv'] ?? '')) && isset(ROLES[$u['role'] ?? ''])) {
            return ['id' => $uid, 'email' => (string)$u['email'], 'name' => (string)($u['name'] ?? ''), 'role' => (string)$u['role'], 'owner' => false];
        }
    }
    return null;
}
/** Tên người đang thao tác (ghi vào nhật ký). */
function me_label(): string { $m = $GLOBALS['VFW_ME'] ?? null; return $m ? ((string)$m['email'] !== '' ? (string)$m['email'] : 'chủ tài khoản') : ''; }

/* ---------- Thao tác POST của trang Người dùng ---------- */
function users_post(string $act, string $DIR): array {
    $back = '/quan-tri/?p=users';
    $all = users_all($DIR);
    $A = json_decode((string)@file_get_contents("$DIR/admin.json"), true) ?: [];
    $id = (string)($_POST['id'] ?? '');
    $idx = null;
    foreach ($all as $i => $u) { if (($u['id'] ?? '') === $id) $idx = $i; }
    switch ($act) {
        case 'blog_user_add': {
            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 60);
            $role = (string)($_POST['role'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['error' => 'Email chưa đúng dạng.'];
            if (!isset(ROLES[$role])) return ['error' => 'Vai trò không hợp lệ.'];
            if (empty($A['username'])) return ['error' => 'Hãy đặt email đăng nhập cho tài khoản chủ ở mục Tài khoản trước khi thêm người dùng.'];
            if ($email === mb_strtolower((string)$A['username']) || users_find_email($all, $email)) return ['error' => 'Email này đã có tài khoản.'];
            if (count($all) >= 30) return ['error' => 'Tối đa 30 tài khoản.'];
            if (($p = password_problem((string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? ''))) !== '') return ['error' => $p];
            $all[] = ['id' => 'u' . bin2hex(random_bytes(5)), 'email' => $email, 'name' => $name, 'role' => $role, 'hash' => password_hash((string)$_POST['password'], PASSWORD_DEFAULT), 'created' => date('c'), 'last_login' => '', 'disabled' => false];
            users_write($DIR, $all);
            blog_log($DIR, 'user_add', $email . ':' . $role);
            return ['to' => "$back&ok=user_ok"];
        }
        case 'blog_user_set': {
            if ($idx === null) return ['error' => 'Không tìm thấy người dùng.'];
            $role = (string)($_POST['role'] ?? $all[$idx]['role']);
            if (!isset(ROLES[$role])) return ['error' => 'Vai trò không hợp lệ.'];
            $all[$idx]['role'] = $role;
            $all[$idx]['disabled'] = ($_POST['disabled'] ?? '') === '1';
            users_write($DIR, $all);
            blog_log($DIR, 'user_set', $all[$idx]['email'] . ':' . $role . ($all[$idx]['disabled'] ? ':khoa' : ''));
            return ['to' => "$back&ok=user_ok"];
        }
        case 'blog_user_pw': {
            if ($idx === null) return ['error' => 'Không tìm thấy người dùng.'];
            if (($p = password_problem((string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? ''))) !== '') return ['error' => $p];
            $all[$idx]['hash'] = password_hash((string)$_POST['password'], PASSWORD_DEFAULT);   // đổi hash → mọi phiên cũ của người này hết hiệu lực
            users_write($DIR, $all);
            blog_log($DIR, 'user_pw', (string)$all[$idx]['email']);
            return ['to' => "$back&ok=user_ok"];
        }
        case 'blog_user_del': {
            if ($idx === null) return ['error' => 'Không tìm thấy người dùng.'];
            $em = (string)$all[$idx]['email'];
            array_splice($all, $idx, 1);
            users_write($DIR, $all);
            blog_log($DIR, 'user_del', $em);
            return ['to' => "$back&ok=user_ok"];
        }
    }
    return ['error' => 'Thao tác không hợp lệ.'];
}

/* ---------- Giao diện ---------- */
function blog_view_users(string $DIR, string $csrf, array $A, array $me): void {
    $users = users_all($DIR);
    $hasOwnerEmail = !empty($A['username']);
    ?>
  <div class="page">
    <div class="pagehead"><h1>Người dùng</h1></div>
    <?php if (!$hasOwnerEmail) { ?><div class="note warn">Tài khoản chủ chưa có email đăng nhập. Vào <a href="/quan-tri/?p=account">Tài khoản</a> để đặt email, rồi mới thêm được người dùng khác.</div><?php } ?>
    <section class="card">
      <h2>Vai trò</h2>
      <ul class="small"><?php foreach (ROLES as $k => $lab) { ?><li><b><?= h($lab) ?></b> – <?= h(ROLE_DESC[$k]) ?></li><?php } ?></ul>
    </section>
    <section class="card">
      <h2>Danh sách (<?= count($users) + 1 ?>)</h2>
      <div class="tbl"><table class="wp-list">
        <thead><tr><th>Người dùng</th><th>Vai trò</th><th>Đăng nhập gần nhất</th><th>Thao tác</th></tr></thead>
        <tbody>
          <tr><td class="title"><b><?= h((string)($A['username'] ?? 'Chủ tài khoản')) ?></b> <span class="chip on">Chủ tài khoản</span></td><td>Quản trị</td><td class="small muted">—</td><td class="small muted">Đổi mật khẩu ở mục Tài khoản</td></tr>
        <?php foreach ($users as $u) { ?>
          <tr>
            <td class="title"><b><?= h((string)$u['email']) ?></b><?php if (!empty($u['name'])) { ?> <span class="muted small">· <?= h((string)$u['name']) ?></span><?php } ?><?php if (!empty($u['disabled'])) { ?> <span class="st bad">Đã khóa</span><?php } ?></td>
            <td>
              <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_user_set"><input type="hidden" name="id" value="<?= h((string)$u['id']) ?>">
                <select name="role" aria-label="Vai trò"><?php foreach (ROLES as $k => $lab) { ?><option value="<?= h($k) ?>"<?= ($u['role'] ?? '') === $k ? ' selected' : '' ?>><?= h($lab) ?></option><?php } ?></select>
                <label class="small"><input type="checkbox" name="disabled" value="1"<?= !empty($u['disabled']) ? ' checked' : '' ?>> Khóa</label>
                <button class="lnk" type="submit">Lưu</button></form>
            </td>
            <td class="small nowrap"><?= !empty($u['last_login']) ? h(date('H:i d/m/Y', (int)strtotime((string)$u['last_login']))) : '—' ?></td>
            <td class="small">
              <details><summary class="lnk">Đặt lại mật khẩu</summary>
                <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_user_pw"><input type="hidden" name="id" value="<?= h((string)$u['id']) ?>">
                  <input type="password" name="password" placeholder="Mật khẩu mới (≥ <?= MIN_PASSWORD ?> ký tự)" autocomplete="new-password" required minlength="<?= MIN_PASSWORD ?>">
                  <input type="password" name="password2" placeholder="Nhập lại" autocomplete="new-password" required minlength="<?= MIN_PASSWORD ?>">
                  <button class="btn btn--ghost" type="submit">Lưu mật khẩu mới</button></form></details>
              <form method="post" class="inline" data-confirm="Xóa tài khoản này? Người dùng sẽ bị đăng xuất ngay."><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_user_del"><input type="hidden" name="id" value="<?= h((string)$u['id']) ?>"><button class="lnk danger" type="submit">Xóa</button></form>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table></div>
    </section>
    <section class="card narrow2">
      <h2>Thêm người dùng</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_user_add">
        <div class="two">
          <label class="f"><span>Email đăng nhập</span><input type="email" name="email" required autocomplete="off"></label>
          <label class="f"><span>Tên hiển thị</span><input name="name" maxlength="60" autocomplete="off"></label>
        </div>
        <label class="f"><span>Vai trò</span><select name="role"><?php foreach (ROLES as $k => $lab) { ?><option value="<?= h($k) ?>"<?= $k === 'editor' ? ' selected' : '' ?>><?= h($lab) ?></option><?php } ?></select></label>
        <div class="two">
          <?= pwfield('Mật khẩu (ít nhất ' . MIN_PASSWORD . ' ký tự)', 'password', 'new-password', true) ?>
          <?= pwfield('Nhập lại mật khẩu', 'password2', 'new-password', true) ?>
        </div>
        <button class="btn" type="submit">Thêm người dùng</button>
        <p class="muted small">Gửi mật khẩu cho người dùng qua kênh riêng; họ nên đổi lại ở mục Tài khoản sau khi đăng nhập.</p>
      </form>
    </section>
  </div>
<?php }

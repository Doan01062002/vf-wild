<?php
/**
 * Review bài tự động và vòng học của hệ thống:
 *  1) Bài AI viết xong được lưu bản gốc và vào hàng "Chờ review".
 *  2) Nhân viên SEO bấm "Không cần sửa" hoặc "Đã sửa" (chọn lý do); hệ thống so bản AI với bản hiện tại và lưu khác biệt.
 *  3) Mỗi tuần (hoặc bấm tay) AI đọc các bài đã sửa, đề xuất quy tắc ngắn; Quản trị duyệt; quy tắc đã duyệt nằm trong "Sổ tay biên tập"
 *     và được gắn vào câu lệnh viết các bài sau. Không huấn luyện lại mô hình AI.
 * Dữ liệu ở <private>/review/: reviews.json, edits.jsonl, playbook.json, digest.json, orig/<slug>.md
 */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }

const REVIEW_TAGS = ['so_lieu' => 'Sai số liệu', 'chinh_ta' => 'Sai chính tả / ngữ pháp', 'van_phong' => 'Văn phong chưa tự nhiên', 'thieu_y' => 'Thiếu ý / thiếu nội dung',
    'sai_y' => 'Nội dung sai hoặc gây hiểu nhầm', 'tieu_de' => 'Tiêu đề / mô tả', 'cau_truc' => 'Cấu trúc bài', 'anh' => 'Ảnh / chú thích', 'lien_ket' => 'Liên kết', 'khac' => 'Khác'];
const REVIEW_KINDS = ['style' => 'Cách viết', 'fact' => 'Dữ kiện / số liệu', 'forbidden' => 'Cụm từ không dùng'];
const REVIEW_MAX_RULES = 20;

/* ---------- Lưu trữ ---------- */
function review_dir(string $DIR): string { $d = "$DIR/review"; if (!is_dir($d)) @mkdir($d, 0750, true); if (!is_dir("$d/orig")) @mkdir("$d/orig", 0750, true); return $d; }
function review_get(string $DIR, string $name): array { return auto_json(review_dir($DIR) . "/$name.json", []); }
/** Đọc – sửa – ghi một tệp JSON trong khóa để hai người thao tác cùng lúc không đè nhau. */
function review_mutate(string $DIR, string $name, callable $fn) {
    $h = fopen(review_dir($DIR) . '/.lock', 'c');
    flock($h, LOCK_EX);
    try {
        $d = review_get($DIR, $name);
        $r = $fn($d);
        auto_save_json(review_dir($DIR) . "/$name.json", $d);
        return $r;
    } finally { flock($h, LOCK_UN); fclose($h); }
}
function review_slug_ok(string $s): bool { return (bool)preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $s); }

/* ---------- Đăng ký bài vào hàng chờ review ---------- */
function review_register(string $DIR, string $slug, array $meta): void {
    if (!review_slug_ok($slug)) return;
    $f = blog_dir($DIR) . "/$slug.md";
    if (!is_file($f)) return;
    @copy($f, review_dir($DIR) . "/orig/$slug.md");   // bản gốc do AI viết
    review_mutate($DIR, 'reviews', function (array &$d) use ($slug, $meta) {
        $d[$slug] = ['status' => 'pending', 'source' => 'auto', 'at' => time(), 'provider' => (string)($meta['provider'] ?? ''), 'model' => (string)($meta['model'] ?? ''),
            'type' => (string)($meta['type'] ?? ''), 'score' => (int)($meta['score'] ?? 0), 'keyword' => (string)($meta['keyword'] ?? '')];
    });
}
function review_pending_count(string $DIR): int {
    static $n = null;
    if ($n === null) $n = count(array_filter(review_get($DIR, 'reviews'), fn($e) => ($e['status'] ?? '') === 'pending'));
    return $n;
}

/* ---------- So sánh bản AI với bản hiện tại ---------- */
function review_text(?array $p): string {
    return $p ? ('TIÊU ĐỀ: ' . $p['title'] . "\nMÔ TẢ: " . $p['description'] . "\nẢNH BÌA: " . $p['cover'] . "\n\n" . trim(str_replace("\r\n", "\n", (string)$p['body']))) : '';
}
/** Khác biệt theo dòng (LCS). Trả về danh sách [dấu, nội dung] với dấu ' ' (giữ), '-' (AI viết, đã bị xóa), '+' (người sửa thêm). */
function review_diff(string $a, string $b): array {
    $x = array_slice(preg_split('/\n/', $a) ?: [], 0, 700); $y = array_slice(preg_split('/\n/', $b) ?: [], 0, 700);
    $n = count($x); $m = count($y);
    $L = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
    for ($i = $n - 1; $i >= 0; $i--) for ($j = $m - 1; $j >= 0; $j--) $L[$i][$j] = $x[$i] === $y[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
    $out = []; $i = $j = 0;
    while ($i < $n && $j < $m) {
        if ($x[$i] === $y[$j]) { $out[] = [' ', $x[$i]]; $i++; $j++; }
        elseif ($L[$i + 1][$j] >= $L[$i][$j + 1]) { $out[] = ['-', $x[$i++]]; }
        else { $out[] = ['+', $y[$j++]]; }
    }
    while ($i < $n) $out[] = ['-', $x[$i++]];
    while ($j < $m) $out[] = ['+', $y[$j++]];
    return $out;
}
/** Chỉ giữ các đoạn thay đổi cùng 1 dòng ngữ cảnh. */
function review_hunks(array $diff, int $cap = 140): array {
    $keep = array_fill(0, count($diff), false);
    foreach ($diff as $i => [$op]) { if ($op !== ' ') { for ($k = max(0, $i - 1); $k <= min(count($diff) - 1, $i + 1); $k++) $keep[$k] = true; } }
    $out = []; $gap = false;
    foreach ($diff as $i => [$op, $t]) {
        if (!$keep[$i]) { $gap = true; continue; }
        if ($gap && $out) $out[] = ['…', ''];
        $gap = false;
        $out[] = [$op, mb_substr($t, 0, 400, 'UTF-8')];
        if (count($out) >= $cap) break;
    }
    return $out;
}
function review_change(string $DIR, string $slug): array {
    $o = is_file(review_dir($DIR) . "/orig/$slug.md") ? blog_parse((string)file_get_contents(review_dir($DIR) . "/orig/$slug.md")) : null;
    $c = blog_load($DIR, $slug);
    if (!$o || !$c) return ['changed' => false, 'added' => 0, 'removed' => 0, 'pct' => 0, 'hunks' => []];
    $d = review_diff(review_text($o), review_text($c));
    $add = count(array_filter($d, fn($r) => $r[0] === '+')); $rem = count(array_filter($d, fn($r) => $r[0] === '-')); $tot = max(1, count($d));
    return ['changed' => $add + $rem > 0, 'added' => $add, 'removed' => $rem, 'pct' => (int)round(($add + $rem) / $tot * 100), 'hunks' => review_hunks($d)];
}
function review_diff_html(array $hunks): string {
    $h = '<div class="diff">';
    foreach ($hunks as [$op, $t]) {
        if ($op === '…') $h .= '<span class="muted">…</span>';
        elseif ($op === '+') $h .= '<span class="add">+ ' . h($t) . '</span>';
        elseif ($op === '-') $h .= '<span class="del">- ' . h($t) . '</span>';
        else $h .= '<span>  ' . h($t) . '</span>';
    }
    return $h . '</div>';
}

/* ---------- Ghi nhận review ---------- */
function review_mark(string $DIR, string $slug, string $by, string $status, array $tags, string $note): ?string {
    if (!review_slug_ok($slug)) return 'Bài không hợp lệ.';
    $e = review_get($DIR, 'reviews')[$slug] ?? null;
    if (!$e) return 'Bài này không nằm trong hàng chờ review.';
    if (($e['status'] ?? '') !== 'pending') return 'Bài này đã được review rồi.';
    $note = mb_substr(trim(preg_replace('/\s+/u', ' ', $note) ?? ''), 0, 300, 'UTF-8');
    $tags = array_values(array_intersect(array_keys(REVIEW_TAGS), $tags));
    $stats = [];
    if ($status === 'edited') {
        if (!$tags) return 'Hãy chọn ít nhất một lý do sửa.';
        $ch = review_change($DIR, $slug);
        if (!$ch['changed']) return 'Chưa thấy chỗ nào khác so với bản AI viết. Hãy sửa bài trước (nút “Sửa”), hoặc chọn “Không cần sửa”.';
        $stats = ['added' => $ch['added'], 'removed' => $ch['removed'], 'pct' => $ch['pct']];
        @file_put_contents(review_dir($DIR) . '/edits.jsonl', json_encode(['t' => date('c'), 'slug' => $slug, 'by' => $by, 'tags' => $tags, 'note' => $note, 'provider' => $e['provider'] ?? '', 'model' => $e['model'] ?? '',
            'type' => $e['type'] ?? '', 'keyword' => $e['keyword'] ?? '', 'stats' => $stats, 'hunks' => $ch['hunks']], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    }
    review_mutate($DIR, 'reviews', function (array &$d) use ($slug, $by, $status, $tags, $note, $stats) {
        if (isset($d[$slug])) { $d[$slug]['status'] = $status; $d[$slug]['by'] = $by; $d[$slug]['rev_at'] = time(); $d[$slug]['tags'] = $tags; $d[$slug]['note'] = $note; $d[$slug]['stats'] = $stats; }
    });
    blog_log($DIR, $status === 'ok' ? 'review_ok' : 'review_edited', $slug);
    return null;
}
function review_edits(string $DIR, int $n = 200): array {
    $f = review_dir($DIR) . '/edits.jsonl';
    if (!is_file($f)) return [];
    $out = [];
    foreach (array_slice(file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -$n) as $l) { $j = json_decode($l, true); if (is_array($j)) $out[] = $j; }
    return $out;
}
function review_stats(string $DIR, int $days = 30): array {
    $since = time() - $days * 86400;
    $rows = array_filter(review_get($DIR, 'reviews'), fn($e) => in_array($e['status'] ?? '', ['ok', 'edited'], true) && (int)($e['rev_at'] ?? 0) >= $since);
    $by = []; $ty = []; $tags = []; $pend = 0;
    foreach (review_get($DIR, 'reviews') as $e) { if (($e['status'] ?? '') === 'pending') $pend++; }
    foreach ($rows as $e) {
        foreach ([['by', trim(($e['provider'] ?? '') . ' · ' . ($e['model'] ?? ''), ' ·')], ['ty', (string)($e['type'] ?? '')]] as [$k, $key]) {
            if ($key === '') $key = '(không rõ)';
            $arr = $k === 'by' ? $by : $ty;
            $arr[$key] = $arr[$key] ?? ['n' => 0, 'edit' => 0, 'pct' => 0];
            $arr[$key]['n']++; if ($e['status'] === 'edited') { $arr[$key]['edit']++; $arr[$key]['pct'] += (int)($e['stats']['pct'] ?? 0); }
            if ($k === 'by') $by = $arr; else $ty = $arr;
        }
        foreach ((array)($e['tags'] ?? []) as $t) $tags[$t] = ($tags[$t] ?? 0) + 1;
    }
    arsort($tags);
    return ['n' => count($rows), 'edited' => count(array_filter($rows, fn($e) => $e['status'] === 'edited')), 'pending' => $pend, 'by' => $by, 'ty' => $ty, 'tags' => $tags];
}

/* ---------- Sổ tay biên tập ---------- */
function review_playbook(string $DIR): array { $d = review_get($DIR, 'playbook'); return is_array($d['rules'] ?? null) ? $d['rules'] : []; }
function review_rules_mutate(string $DIR, callable $fn) {
    return review_mutate($DIR, 'playbook', function (array &$d) use ($fn) { $d['rules'] = array_values($d['rules'] ?? []); return $fn($d['rules']); });
}
/** Khối chữ gắn vào câu lệnh viết bài: các quy tắc đang dùng và tối đa 2 ví dụ trước/sau. */
function review_playbook_text(string $DIR): string {
    $act = array_values(array_filter(review_playbook($DIR), fn($r) => ($r['status'] ?? '') === 'active'));
    if (!$act) return '';
    $t = "SỔ TAY BIÊN TẬP (rút ra từ các bài đã được nhân viên SEO sửa lại; bắt buộc tuân thủ):\n";
    foreach (array_slice($act, 0, REVIEW_MAX_RULES) as $i => $r) $t .= ($i + 1) . '. ' . $r['text'] . "\n";
    $ex = array_values(array_filter($act, fn($r) => !empty($r['before']) && !empty($r['after'])));
    if ($ex) { $t .= "Ví dụ sửa (trước → sau):\n"; foreach (array_slice($ex, 0, 2) as $r) $t .= '- "' . mb_substr($r['before'], 0, 180, 'UTF-8') . '" → "' . mb_substr($r['after'], 0, 180, 'UTF-8') . "\"\n"; }
    return $t . "\n";
}
function review_rule_clean(string $s): string { return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($s)) ?? ''), 0, 300, 'UTF-8'); }
function review_rule_add(string $DIR, string $text, string $kind, string $src, string $by, array $x = []): bool {
    $text = review_rule_clean($text);
    if (mb_strlen($text, 'UTF-8') < 8 || !isset(REVIEW_KINDS[$kind])) return false;
    return (bool)review_rules_mutate($DIR, function (array &$rules) use ($text, $kind, $src, $by, $x) {
        $tok = auto_tok($text);
        foreach ($rules as $r) { if (($r['status'] ?? '') !== 'retired' && $tok && auto_hit($tok, (string)$r['text']) >= 0.85 && auto_hit(auto_tok((string)$r['text']), $text) >= 0.85) return false; }
        $rules[] = ['id' => 'r' . bin2hex(random_bytes(4)), 'text' => $text, 'kind' => $kind, 'status' => 'proposed', 'src' => $src, 'by' => $by, 'created' => time(),
            'reason' => review_rule_clean((string)($x['reason'] ?? '')), 'before' => review_rule_clean((string)($x['before'] ?? '')), 'after' => review_rule_clean((string)($x['after'] ?? ''))];
        return true;
    });
}
/** Đổi trạng thái / nội dung quy tắc. Trả về thông báo lỗi hoặc null. */
function review_rule_act(string $DIR, string $id, string $act, string $text = ''): ?string {
    $err = null;
    review_rules_mutate($DIR, function (array &$rules) use ($DIR, $id, $act, $text, &$err) {
        foreach ($rules as $i => &$r) {
            if (($r['id'] ?? '') !== $id) continue;
            if ($act === 'on') {
                if (count(array_filter($rules, fn($x) => ($x['status'] ?? '') === 'active')) >= REVIEW_MAX_RULES) { $err = 'Đã đủ ' . REVIEW_MAX_RULES . ' quy tắc đang dùng, hãy gỡ bớt trước.'; return; }
                $r['status'] = 'active'; $r['activated'] = time();
                if (($r['kind'] ?? '') === 'forbidden') {   // cụm cấm: thêm luôn vào danh sách cụm không được dùng khi chấm điểm
                    $s = auto_settings($DIR); $ph = mb_substr(trim((string)$r['text']), 0, 60, 'UTF-8');
                    if ($ph !== '' && mb_stripos((string)$s['forbidden'], $ph, 0, 'UTF-8') === false) { $s['forbidden'] = trim((string)$s['forbidden']) . "\n" . $ph; auto_save_json(auto_dir($DIR) . '/settings.json', $s); }
                }
            } elseif ($act === 'off') { $r['status'] = 'retired'; }
            elseif ($act === 'edit') { $t = review_rule_clean($text); if (mb_strlen($t, 'UTF-8') < 8) { $err = 'Nội dung quy tắc quá ngắn.'; return; } $r['text'] = $t; }
            elseif ($act === 'del') { unset($rules[$i]); }
            elseif ($act === 'facts') {
                $f = auto_dir($DIR) . '/facts.md'; $cur = is_file($f) ? (string)file_get_contents($f) : AUTO_FACTS_DEFAULT;
                if (stripos($cur, '## Ghi chú biên tập') === false) $cur = rtrim($cur) . "\n\n## Ghi chú biên tập\n";
                blog_atomic($f, rtrim($cur) . "\n- " . $r['text'] . "\n");
                $r['status'] = 'active'; $r['activated'] = time(); $r['in_facts'] = true;
            }
            return;
        }
        $err = 'Không tìm thấy quy tắc.';
    });
    return $err;
}

/** AI đọc các bài đã sửa và đề xuất quy tắc. Trả về [số quy tắc đề xuất, thông báo]. */
function review_digest(string $DIR, array $s, bool $auto = false): array {
    $p = (string)$s['provider'];
    if ($p === 'mock') return [0, 'Hãy chọn nhà cung cấp AI thật (không phải “Thử nghiệm”) rồi tạo đề xuất.'];
    $dg = review_get($DIR, 'digest');
    $since = (int)($dg['at'] ?? (time() - 14 * 86400));
    $edits = array_values(array_filter(review_edits($DIR, 120), fn($e) => (int)strtotime((string)($e['t'] ?? '')) >= $since));
    if (count($edits) < ($auto ? 3 : 1)) return [0, 'Chưa đủ bài đã sửa để học (cần ' . ($auto ? 3 : 1) . ' bài trở lên từ lần tạo đề xuất trước).'];
    $body = ''; $used = 0;
    foreach ($edits as $e) {
        $blk = 'BÀI "' . mb_substr((string)($e['keyword'] ?? ''), 0, 80, 'UTF-8') . '" (loại ' . ($e['type'] ?? '?') . ', AI: ' . ($e['model'] ?? '?') . ") — lý do: " . implode(', ', array_map(fn($t) => REVIEW_TAGS[$t] ?? $t, (array)($e['tags'] ?? []))) . (!empty($e['note']) ? '; ghi chú: ' . $e['note'] : '') . "\n";
        foreach (array_slice((array)($e['hunks'] ?? []), 0, 30) as [$op, $t]) { if ($op === '+' || $op === '-') $blk .= $op . ' ' . mb_substr((string)$t, 0, 220, 'UTF-8') . "\n"; }
        if (strlen($body) + strlen($blk) > 14000) break;
        $body .= $blk . "\n"; $used++;
    }
    $existing = implode("\n", array_map(fn($r) => '- ' . $r['text'], array_filter(review_playbook($DIR), fn($r) => ($r['status'] ?? '') !== 'retired')));
    $prompt = "Bạn là trưởng nhóm biên tập nội dung cho blog VinFast VF Wild của ChungAuto. Dưới đây là các bài do AI viết rồi nhân viên SEO sửa lại (dòng bắt đầu bằng '-' là chữ AI viết, '+' là chữ người sửa).\n"
        . "Hãy rút ra TỐI ĐA 8 quy tắc NGẮN, tổng quát để AI viết các bài sau ít phải sửa hơn. Chỉ rút quy tắc khi thấy mẫu lặp lại hoặc lỗi rõ ràng; không biến một lần sửa giá hay ưu đãi nhất thời thành quy tắc cố định.\n"
        . "Phân loại: \"style\" (cách viết, cấu trúc, văn phong), \"fact\" (dữ kiện hoặc số liệu cần có trong bộ dữ kiện), \"forbidden\" (cụm từ không nên dùng, chỉ ghi chính cụm từ).\n"
        . "Chỉ trả JSON: {\"rules\":[{\"text\":\"quy tắc ngắn, mệnh lệnh, tiếng Việt, tối đa 200 ký tự\",\"kind\":\"style|fact|forbidden\",\"reason\":\"vì sao\",\"example_before\":\"câu AI viết (nếu có)\",\"example_after\":\"câu sau khi sửa (nếu có)\"}]}\n"
        . ($existing !== '' ? "KHÔNG lặp lại các quy tắc đã có:\n$existing\n" : '') . "\nCÁC BÀI ĐÃ SỬA:\n$body";
    $text = auto_ai_call($DIR, $p, auto_model($s, $p), $prompt, true, 240);
    $j = auto_json_decode($text);
    if (!is_array($j) || !is_array($j['rules'] ?? null)) throw new RuntimeException('AI trả về sai khuôn (không đọc được danh sách quy tắc).');
    $added = 0;
    foreach (array_slice($j['rules'], 0, 8) as $r) {
        if (is_array($r) && review_rule_add($DIR, (string)($r['text'] ?? ''), (string)($r['kind'] ?? 'style'), 'ai', 'AI', ['reason' => $r['reason'] ?? '', 'before' => $r['example_before'] ?? '', 'after' => $r['example_after'] ?? ''])) $added++;
    }
    review_mutate($DIR, 'digest', function (array &$d) use ($added, $used) { $d = ['at' => time(), 'edits' => $used, 'added' => $added]; });
    return [$added, $added > 0 ? "Đã đề xuất $added quy tắc từ $used bài đã sửa." : 'AI không thấy mẫu lỗi nào đủ rõ để đề xuất quy tắc mới.'];
}
/** Gọi trong --auto-run: mỗi tuần một lần, nếu có từ 3 bài đã sửa trở lên. */
function review_weekly_if_due(string $DIR, array $s): void {
    if (empty($s['review_weekly']) || $s['provider'] === 'mock') return;
    $dg = review_get($DIR, 'digest');
    if (time() - (int)($dg['at'] ?? 0) < 7 * 86400) return;
    try {
        [$n, $msg] = review_digest($DIR, $s, true);
        if ($n > 0 || strpos($msg, 'Chưa đủ') !== 0) auto_log($DIR, ['keyword' => '(sổ tay biên tập)', 'result' => $n > 0 ? 'đề xuất quy tắc' : 'học', 'msg' => $msg]);
    } catch (Throwable $e) {
        review_mutate($DIR, 'digest', function (array &$d) { $d['at'] = time() - 6 * 86400; });   // lỗi thì thử lại sau 1 ngày
        auto_log($DIR, ['keyword' => '(sổ tay biên tập)', 'result' => 'lỗi', 'msg' => $e->getMessage()]);
    }
}

/* ---------- Thao tác POST ---------- */
function review_post(string $act, string $DIR): array {
    $back = '/quan-tri/?p=review';
    $by = me_label();
    $slug = (string)($_POST['slug'] ?? '');
    switch ($act) {
        case 'blog_review_ok': {
            $e = review_mark($DIR, $slug, $by, 'ok', [], '');
            return $e ? ['error' => $e] : ['to' => "$back&ok=rv_ok"];
        }
        case 'blog_review_edited': {
            $e = review_mark($DIR, $slug, $by, 'edited', (array)($_POST['tags'] ?? []), (string)($_POST['note'] ?? ''));
            return $e ? ['error' => $e] : ['to' => "$back&ok=rv_edit"];
        }
        case 'blog_review_rule_add': {
            $ok = review_rule_add($DIR, (string)($_POST['text'] ?? ''), (string)($_POST['kind'] ?? 'style'), 'manual', $by);
            return $ok ? ['to' => "$back&t=rules&ok=rv_rule"] : ['error' => 'Không thêm được: nội dung quá ngắn hoặc đã có quy tắc tương tự.'];
        }
        case 'blog_review_rule_on': case 'blog_review_rule_off': case 'blog_review_rule_edit': case 'blog_review_rule_del': case 'blog_review_rule_facts': {
            $e = review_rule_act($DIR, (string)($_POST['id'] ?? ''), substr($act, strlen('blog_review_rule_')), (string)($_POST['text'] ?? ''));
            return $e ? ['error' => $e] : ['to' => "$back&t=rules&ok=rv_rule"];
        }
        case 'blog_review_digest': {
            try { [$n, $msg] = review_digest($DIR, auto_settings($DIR)); }
            catch (Throwable $e) { return ['error' => 'Không tạo được đề xuất: ' . $e->getMessage()]; }
            return $n > 0 ? ['to' => "$back&t=rules&ok=rv_rule"] : ['error' => $msg];
        }
    }
    return ['error' => 'Thao tác không hợp lệ.'];
}

/* ---------- Giao diện ---------- */
function blog_view_review(string $DIR, string $csrf, array $me): void {
    $t = (string)($_GET['t'] ?? 'queue');
    if (!in_array($t, ['queue', 'stats', 'rules', 'history'], true)) $t = 'queue';
    $canAct = role_can($me['role'], 'review');
    $canRules = role_can($me['role'], 'review.rules');
    $canEdit = role_can($me['role'], 'blog.write');
    $reviews = review_get($DIR, 'reviews');
    $rules = review_playbook($DIR);
    $nProp = count(array_filter($rules, fn($r) => ($r['status'] ?? '') === 'proposed'));
    $tabs = ['queue' => 'Chờ review (' . review_pending_count($DIR) . ')', 'stats' => 'Thống kê', 'rules' => 'Sổ tay biên tập' . ($nProp ? " ($nProp đề xuất)" : ''), 'history' => 'Lịch sử sửa'];
    ?>
  <div class="page">
    <div class="pagehead"><h1>Review và học</h1></div>
    <nav class="tabs" aria-label="Mục"><?php foreach ($tabs as $k => $lab) { ?><a href="/quan-tri/?p=review&amp;t=<?= h($k) ?>"<?= $t === $k ? ' class="on"' : '' ?>><?= h($lab) ?></a><?php } ?></nav>

    <?php if ($t === 'queue') {
        $pending = array_filter($reviews, fn($e) => ($e['status'] ?? '') === 'pending');
        uasort($pending, fn($a, $b) => ($b['at'] ?? 0) <=> ($a['at'] ?? 0));
        $done = array_filter($reviews, fn($e) => in_array($e['status'] ?? '', ['ok', 'edited'], true));
        uasort($done, fn($a, $b) => ($b['rev_at'] ?? 0) <=> ($a['rev_at'] ?? 0));
        ?>
      <section class="card">
        <h2>Bài AI vừa viết, chờ nhân viên SEO xem</h2>
        <p class="muted small">Mở bài, đọc kỹ rồi chọn: <b>Không cần sửa</b> hoặc sửa bài (nút “Sửa”) xong bấm <b>Đã sửa</b> và chọn lý do. Hệ thống tự so với bản AI viết ban đầu để rút kinh nghiệm cho các bài sau.</p>
        <?php if (!$pending) { ?><div class="empty"><p>Không có bài nào chờ review.</p></div><?php } else { foreach ($pending as $slug => $e) { $p = blog_load($DIR, (string)$slug); if (!$p) continue; $ch = review_change($DIR, (string)$slug); ?>
          <div class="card" style="margin:.4rem 0">
            <div class="rowhead"><div><b><?= h($p['title']) ?></b><div class="muted small"><?= h((string)($e['keyword'] ?? '')) ?> · <?= h(trim(($e['provider'] ?? '') . ' ' . ($e['model'] ?? ''))) ?> · điểm <?= (int)($e['score'] ?? 0) ?> · <?= h(auto_ago((int)($e['at'] ?? 0))) ?></div></div>
              <div class="inline"><a class="btn btn--ghost" href="/blog/<?= h((string)$slug) ?>/" target="_blank" rel="noopener">Xem bài ↗</a><?php if ($canEdit) { ?><a class="btn btn--ghost" href="/quan-tri/?p=blog&amp;edit=<?= h(rawurlencode((string)$slug)) ?>">Sửa</a><?php } ?></div></div>
            <?php if ($ch['changed']) { ?><p class="small"><span class="st on">Đã chỉnh <?= (int)$ch['pct'] ?>% so với bản AI</span> <span class="muted">(+<?= (int)$ch['added'] ?> / −<?= (int)$ch['removed'] ?> dòng)</span> – nhớ bấm “Đã sửa” và chọn lý do.</p><details><summary class="lnk">Xem phần đã sửa</summary><?= review_diff_html($ch['hunks']) ?></details><?php } ?>
            <?php if ($canAct) { ?>
            <div class="inline">
              <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_review_ok"><input type="hidden" name="slug" value="<?= h((string)$slug) ?>"><button class="btn" type="submit"<?= $ch['changed'] ? ' data-confirm="Bài đã bị sửa so với bản AI. Vẫn đánh dấu “Không cần sửa”?"' : '' ?>>Không cần sửa</button></form>
              <details><summary class="btn btn--ghost" style="display:inline-block">Đã sửa…</summary>
                <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_review_edited"><input type="hidden" name="slug" value="<?= h((string)$slug) ?>">
                  <div class="tags"><?php foreach (REVIEW_TAGS as $k => $lab) { ?><label class="small"><input type="checkbox" name="tags[]" value="<?= h($k) ?>"> <?= h($lab) ?></label><?php } ?></div>
                  <input name="note" maxlength="300" placeholder="Ghi chú ngắn (tùy chọn): AI hay sai chỗ nào?" autocomplete="off">
                  <button class="btn" type="submit">Lưu ghi nhận</button></form></details>
            </div>
            <?php } ?>
          </div>
        <?php } } ?>
      </section>
      <?php if ($done) { ?><section class="card"><h2>Đã review gần đây</h2><div class="tbl"><table class="wp-list"><thead><tr><th>Bài</th><th>Kết quả</th><th>Lý do</th><th>Người review</th></tr></thead><tbody>
        <?php foreach (array_slice($done, 0, 15, true) as $slug => $e) { ?><tr>
          <td class="title"><a href="/blog/<?= h((string)$slug) ?>/" target="_blank" rel="noopener"><?= h((string)$slug) ?></a></td>
          <td><span class="st <?= $e['status'] === 'ok' ? 'on' : 'off' ?>"><?= $e['status'] === 'ok' ? 'Không cần sửa' : 'Đã sửa ' . (int)($e['stats']['pct'] ?? 0) . '%' ?></span></td>
          <td class="small"><?= h(implode(', ', array_map(fn($x) => REVIEW_TAGS[$x] ?? $x, (array)($e['tags'] ?? [])))) ?><?= !empty($e['note']) ? ' – ' . h((string)$e['note']) : '' ?></td>
          <td class="small"><?= h((string)($e['by'] ?? '')) ?> · <?= h(auto_ago((int)($e['rev_at'] ?? 0))) ?></td></tr><?php } ?>
      </tbody></table></div></section><?php } ?>

    <?php } elseif ($t === 'stats') { $st = review_stats($DIR, 30); ?>
      <section class="card">
        <h2>30 ngày gần nhất</h2>
        <div class="statgrid">
          <div><span class="muted small">Đã review</span><b><?= $st['n'] ?> bài</b></div>
          <div><span class="muted small">Cần sửa</span><b><?= $st['n'] ? round($st['edited'] / $st['n'] * 100) : 0 ?>% (<?= $st['edited'] ?> bài)</b></div>
          <div><span class="muted small">Đang chờ review</span><b><?= $st['pending'] ?> bài</b></div>
        </div>
        <?php foreach ([['Theo nhà cung cấp và model AI', $st['by']], ['Theo loại bài', $st['ty']]] as [$ttl, $grp]) { if (!$grp) continue; ?>
          <h3><?= h($ttl) ?></h3><div class="tbl"><table><thead><tr><th>Nhóm</th><th>Số bài</th><th>Cần sửa</th><th>Mức sửa TB</th></tr></thead><tbody>
          <?php foreach ($grp as $k => $g) { ?><tr><td><?= h($k) ?></td><td><?= $g['n'] ?></td><td><?= round($g['edit'] / max(1, $g['n']) * 100) ?>%</td><td><?= $g['edit'] ? round($g['pct'] / $g['edit']) . '%' : '—' ?></td></tr><?php } ?></tbody></table></div>
        <?php } ?>
        <?php if ($st['tags']) { ?><h3>Lý do sửa hay gặp</h3><div class="tbl"><table><?php foreach ($st['tags'] as $k => $n) { ?><tr><td><?= h(REVIEW_TAGS[$k] ?? $k) ?></td><td><?= $n ?> lần</td></tr><?php } ?></table></div><?php } ?>
        <?php if (!$st['n']) { ?><p class="muted">Chưa có bài nào được review trong 30 ngày qua.</p><?php } ?>
      </section>

    <?php } elseif ($t === 'rules') { $dg = review_get($DIR, 'digest'); $act = array_filter($rules, fn($r) => ($r['status'] ?? '') === 'active'); ?>
      <section class="card">
        <div class="rowhead"><h2>Sổ tay biên tập</h2>
          <?php if ($canRules) { ?><form method="post" class="inline" data-confirm="AI sẽ đọc các bài đã sửa và đề xuất quy tắc (một lần gọi AI). Tiếp tục?"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_review_digest"><button class="btn btn--ghost" type="submit">Tạo đề xuất từ các bài đã sửa</button></form><?php } ?></div>
        <p class="muted small">Quy tắc <b>đang dùng</b> (tối đa <?= REVIEW_MAX_RULES ?>) được gắn vào câu lệnh viết mọi bài sau. Mỗi tuần hệ thống tự đề xuất quy tắc mới nếu có từ 3 bài đã sửa; chỉ Quản trị duyệt mới có hiệu lực.<?= !empty($dg['at']) ? ' Lần đề xuất gần nhất: ' . h(auto_ago((int)$dg['at'])) . '.' : '' ?></p>
      </section>
      <?php foreach ([['proposed', 'Đề xuất chờ duyệt'], ['active', 'Đang dùng (' . count($act) . '/' . REVIEW_MAX_RULES . ')'], ['retired', 'Đã gỡ']] as [$stt, $ttl]) {
          $list = array_filter($rules, fn($r) => ($r['status'] ?? '') === $stt); if (!$list && $stt === 'retired') continue; ?>
        <section class="card"><h3><?= h($ttl) ?></h3>
          <?php if (!$list) { ?><p class="muted small">Chưa có.</p><?php } foreach ($list as $r) { ?>
            <div class="note" style="margin:.4rem 0">
              <b><?= h((string)$r['text']) ?></b> <span class="chip"><?= h(REVIEW_KINDS[$r['kind'] ?? 'style'] ?? '') ?></span><?= !empty($r['in_facts']) ? ' <span class="chip on">đã vào bộ dữ kiện</span>' : '' ?>
              <?php if (!empty($r['reason'])) { ?><div class="muted small">Lý do: <?= h((string)$r['reason']) ?></div><?php } ?>
              <?php if (!empty($r['before']) && !empty($r['after'])) { ?><div class="small">Ví dụ: “<?= h((string)$r['before']) ?>” → “<?= h((string)$r['after']) ?>”</div><?php } ?>
              <div class="muted small"><?= ($r['src'] ?? '') === 'ai' ? 'AI đề xuất' : 'Thêm bởi ' . h((string)($r['by'] ?? '')) ?> · <?= h(auto_ago((int)($r['created'] ?? 0))) ?></div>
              <?php if ($canRules) { ?><div class="inline">
                <?php foreach ([['proposed', 'on', 'Duyệt và dùng', 'btn'], ['active', 'off', 'Gỡ', 'btn btn--ghost'], ['retired', 'on', 'Dùng lại', 'btn btn--ghost']] as [$sf, $ac, $lb, $cl]) { if ($sf !== $stt) continue; ?>
                  <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_review_rule_<?= $ac ?>"><input type="hidden" name="id" value="<?= h((string)$r['id']) ?>"><button class="<?= $cl ?>" type="submit"><?= $lb ?></button></form>
                <?php } ?>
                <?php if (($r['kind'] ?? '') === 'fact' && empty($r['in_facts']) && $stt !== 'retired') { ?><form method="post" class="inline"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_review_rule_facts"><input type="hidden" name="id" value="<?= h((string)$r['id']) ?>"><button class="lnk" type="submit">Thêm vào bộ dữ kiện</button></form><?php } ?>
                <details><summary class="lnk">Sửa chữ</summary><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_review_rule_edit"><input type="hidden" name="id" value="<?= h((string)$r['id']) ?>"><textarea name="text" rows="2" maxlength="300"><?= h((string)$r['text']) ?></textarea><button class="btn btn--ghost" type="submit">Lưu</button></form></details>
                <form method="post" class="inline" data-confirm="Xóa hẳn quy tắc này?"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_review_rule_del"><input type="hidden" name="id" value="<?= h((string)$r['id']) ?>"><button class="lnk danger" type="submit">Xóa</button></form>
              </div><?php } ?>
            </div>
          <?php } ?>
        </section>
      <?php } ?>
      <?php if ($canAct) { ?><section class="card narrow2"><h3>Đề xuất một quy tắc</h3>
        <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="blog_review_rule_add">
          <label class="f"><span>Quy tắc (ngắn, dạng mệnh lệnh)</span><input name="text" maxlength="300" required placeholder="Ví dụ: Luôn ghi cả số tiền trả trước khi nói về tỉ lệ vay"></label>
          <label class="f"><span>Loại</span><select name="kind"><?php foreach (REVIEW_KINDS as $k => $lab) { ?><option value="<?= h($k) ?>"><?= h($lab) ?></option><?php } ?></select></label>
          <button class="btn" type="submit">Gửi đề xuất</button><p class="muted small">Quy tắc chỉ có hiệu lực sau khi Quản trị duyệt.</p></form></section><?php } ?>

    <?php } else { $ed = array_reverse(review_edits($DIR, 30)); ?>
      <section class="card"><h2>Các lần sửa gần đây (so với bản AI viết)</h2>
        <?php if (!$ed) { ?><p class="muted">Chưa có bài nào được ghi nhận “Đã sửa”.</p><?php } foreach ($ed as $e) { ?>
          <details class="note" style="margin:.4rem 0"><summary><b><?= h((string)($e['slug'] ?? '')) ?></b> · <?= h(implode(', ', array_map(fn($x) => REVIEW_TAGS[$x] ?? $x, (array)($e['tags'] ?? [])))) ?> · <?= (int)($e['stats']['pct'] ?? 0) ?>% · <?= h((string)($e['by'] ?? '')) ?> · <?= h(date('d/m H:i', (int)strtotime((string)($e['t'] ?? '')))) ?></summary>
            <?= !empty($e['note']) ? '<p class="small">Ghi chú: ' . h((string)$e['note']) . '</p>' : '' ?><?= review_diff_html((array)($e['hunks'] ?? [])) ?></details>
        <?php } ?>
      </section>
    <?php } ?>
  </div>
<?php }

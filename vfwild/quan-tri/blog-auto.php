<?php
/**
 * Bộ máy tự động viết và đăng bài blog (nạp từ index.php; chạy theo cron: php index.php --auto-run).
 * Dữ liệu nằm ở $DIR/auto/: settings.json, queue.json, facts.md, state.json, log.jsonl.
 * Luồng: hàng đợi chủ đề → viết (nhà cung cấp AI) → chấm điểm + kiểm tra số liệu → chọn ảnh → lưu và đăng.
 * Nhà cung cấp AI cắm vào auto_generate(); hiện chỉ có "mock" (bộ viết thử từ bộ dữ kiện, chỉ lưu nháp).
 */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }

const AUTO_TYPES = ['tin-tuc' => 'Tin tức', 'gia' => 'Giá & ưu đãi', 'so-sanh' => 'So sánh', 'thu-tuc' => 'Thủ tục', 'cong-nghe' => 'Công nghệ', 'chi-phi' => 'Chi phí', 'huong-dan' => 'Hướng dẫn', 'phu-kien' => 'Phụ kiện & độ xe', 'kien-thuc' => 'Kiến thức & luật'];
const AUTO_PAGES = [
    ['Giá lăn bánh VF Wild theo tỉnh', '/gia-lan-banh-vf-wild/'],
    ['Tính trả góp VF Wild', '/tra-gop-vf-wild/'],
    ['Đặt cọc và kênh O2O', '/dat-coc-vf-wild/'],
    ['Đăng ký lái thử VF Wild', '/lai-thu-vf-wild/'],
];
const AUTO_FACTS_DEFAULT = <<<'TXT'
# Bộ dữ kiện VF Wild – bài viết CHỈ được dùng số liệu trong file này
# Mỗi mục bắt đầu bằng "## Tên mục", mỗi dữ kiện là một dòng bắt đầu bằng "- ".
# Khi giá, ưu đãi hoặc chính sách đổi, hãy sửa file này TRƯỚC khi để hệ thống viết bài mới.

## Giá niêm yết và ưu đãi
- Giá niêm yết VF Wild (đã gồm pin và VAT) là 860 triệu cho màu Đen, Đỏ, Trắng và 872 triệu cho màu Bạc.
- Theo bảng dự tính ngày 19/9/2026, khách có xe xăng hoặc xe VinFast được giảm 5% (chương trình "Vì tương lai xanh"), tương đương 43 triệu.
- Mua qua kênh O2O của VinFast được giảm thêm 2% trên phần giá còn lại, tương đương 16,34 triệu.
- Giá dự kiến sau ưu đãi của bản Đen, Đỏ, Trắng là 800,66 triệu, tổng tiết kiệm 59,34 triệu.
- Giá chỉ mang tính tham khảo, chưa gồm phí lăn bánh; báo giá bằng văn bản của tư vấn viên là con số cuối cùng.
- Xe được miễn phí 20 lần sạc mỗi tháng đến ngày 10/02/2029.
- ChungAuto tặng bộ thảm sàn Nappa Clear cao cấp trị giá 8,5 triệu.

## Công nghệ REEV và pin
- VF Wild dùng hệ truyền động REEV: mô-tơ điện dẫn động bánh xe, động cơ xăng 1,5 L chỉ làm máy phát nạp điện cho pin.
- Pin LFP dung lượng khả dụng 46,4 kWh.
- Quãng đường thuần điện trên 250 km, quãng đường kết hợp xăng và điện trên 1.000 km (chuẩn NEDC); bình xăng 56 L.
- Công suất 160 kW (khoảng 215 mã lực), mô-men xoắn 280 Nm, dẫn động cầu trước (FWD).
- Sạc AC tối đa 6,6 kW, sạc DC tối đa 90 kW; sạc nhanh 10–70% dưới 32 phút.
- Chức năng V2L cho phép lấy điện từ xe cấp cho thiết bị khi cắm trại hoặc làm việc ngoài trời.

## Kích thước và thùng hàng
- Kích thước dài × rộng × cao là 5.376 × 2.069 × 1.873 mm, chiều dài cơ sở 3.258 mm, khoảng sáng gầm 222 mm.
- Thùng hàng 1.574 × 1.512 × 509 mm, tải trọng 750 kg, xe 5 chỗ ngồi.
- Màn hình trung tâm 10 inch, la-zăng 18 inch.

## Bảo hành
- Bảo hành xe 6 năm hoặc 150.000 km, bảo hành pin 8 năm hoặc 160.000 km (tùy điều kiện nào đến trước).

## Thủ tục và chi phí lăn bánh
- Xe REEV thuộc nhóm xe điện theo Thông tư 45/2026 nên lệ phí trước bạ 0%, ưu đãi kéo dài đến hết năm 2030.
- VF Wild đăng ký là ô tô con pick-up, không bị giới hạn niên hạn sử dụng 25 năm như bán tải đăng ký xe tải.
- Khách có thể đặt cọc tại showroom hoặc đặt cọc online qua kênh O2O của VinFast.
- Ví dụ trả góp: vay 70% giá xe trong 7 năm tức trả trước 30% (khoảng 240 triệu), kỳ trả đầu khoảng 10,6 triệu mỗi tháng (lãi suất giả định).

## Chi phí vận hành
- Giả định tính toán: bán tải dầu 8 lít/100 km với giá dầu 30.000đ/lít, tương đương 2.400đ/km; VF Wild 20 kWh/100 km với giá điện 3.000đ/kWh, tương đương 600đ/km.
- Với 3.000 km mỗi tháng: chi phí nhiên liệu của bán tải dầu là 7,2 triệu, của VF Wild chạy điện là 1,8 triệu, tiết kiệm 5,4 triệu mỗi tháng.
- Tiết kiệm khoảng 64,8 triệu mỗi năm theo giả định trên; đây chỉ là ước tính tiền năng lượng, chưa tính bảo dưỡng.

## So sánh bán tải
- Giá niêm yết các bán tải so sánh (số liệu tháng 9/2026): Ranger XLS 2.0L AT 4x4 là 776 triệu, Hilux Trailhunter 903 triệu, Triton Athlete 924 triệu, D-Max Type Z 942 triệu, Navara VL 936 triệu.
- VF Wild dẫn động cầu trước, trong khi các bản so sánh của đối thủ là 2 cầu (4x4).
- Khoảng sáng gầm VF Wild là 222 mm, Hilux Trailhunter cao nhất bảng với 312 mm.

## ChungAuto và liên hệ
- ChungAuto (Công ty Cổ phần Chung Group) là đại lý ủy quyền phân phối VinFast VF Wild; website này không phải website chính thức của VinFast.
- Tổng đài miễn phí 1800 8282, hotline VF Wild 0784 368 314, giờ làm việc 8:30–17:30 tất cả các ngày.
- Hệ thống có 11 điểm ChungAuto và 3 điểm đối tác.

## Showroom ChungAuto và điểm đối tác (địa chỉ, điện thoại; lấy từ trang Đăng ký lái thử)
- Hà Nội – Mỹ Đình: 495 Hoàng Quốc Việt, Cổ Nhuế, Bắc Từ Liêm; tổng đài 1800 8282, bấm phím 1.
- Hà Nội – Long Biên: 170 Sài Đồng, Long Biên; tổng đài 1800 8282, bấm phím 2.
- Hà Nội – Hoài Đức: Thượng Thụy, Đức Thượng, Hoài Đức; tổng đài 1800 8282, bấm phím 3.
- TP. Hồ Chí Minh: 440 Kinh Dương Vương, An Lạc, Bình Tân; điện thoại 0981 217 488.
- Đà Nẵng: 698 Nguyễn Hữu Thọ, Khuê Trung, Cẩm Lệ; điện thoại 0965 694 789.
- Nghệ An: A28 Đại lộ 72M, P. Quán Bàu, TP. Vinh; điện thoại 0918 083 378.
- Hà Tĩnh: 753 Trần Phú, Thạch Trung; điện thoại 0918 083 378.
- Lào Cai: 1071 Đường Lê Thanh, Nam Cường; điện thoại 0922 768 999.
- Ninh Bình: 376 phố Phúc Trì, đường 30 tháng 6, Hoa Lư; điện thoại 0922 952 666.
- Vĩnh Phúc (Phú Thọ) và Thái Nguyên: gọi để nhận địa chỉ, lần lượt 0961 198 296 và 0925 318 666.
- Điểm đối tác: Bắc Ninh (Vòng xuyến Dương Lôi, Tân Hồng, Từ Sơn; 0913 259 779), Tuyên Quang – Đăng Dương Auto (51 Lý Thái Tổ, P. Minh Xuân; 0989 966 657), Tuyên Quang – Thanh Đông Auto (Số 90 tổ 18, P. Hà Giang 1; 0336 555 525).
TXT;

const AUTO_TONE_OLD = <<<'TXT'
Viết tiếng Việt tự nhiên, rõ ràng, thân thiện với người mua xe lần đầu; xưng "bạn", không dùng giọng quảng cáo ồn ào.
Chỉ dùng số liệu có trong bộ dữ kiện; nếu thiếu số liệu thì nói rõ cần hỏi tư vấn viên, tuyệt đối không tự suy đoán giá hay chính sách.
Bài có phần mở đầu ngắn, 4–6 mục H2 có thông tin hữu ích, một bảng nếu có nhiều số liệu, mục câu hỏi thường gặp, và kết bài mời xem các trang giá lăn bánh, trả góp.
Có ít nhất 2 liên kết nội bộ lấy từ danh sách được cung cấp. Không hứa hẹn "rẻ nhất", "số 1", "cam kết 100%".
TXT;
const AUTO_TONE_PREV = <<<'TXT'
Viết tiếng Việt tự nhiên, rõ ràng, thân thiện với người mua xe lần đầu; xưng "bạn", không dùng giọng quảng cáo ồn ào.
Chỉ dùng số liệu có trong bộ dữ kiện; thiếu số liệu thì bỏ ý đó (không ghi chữ giữ chỗ), tuyệt đối không tự suy đoán giá hay chính sách.
Bài có phần mở đầu ngắn, 4–6 mục H2 có thông tin hữu ích, một bảng nếu có nhiều số liệu, mục câu hỏi thường gặp, và kết bài mời xem các trang giá lăn bánh, trả góp.
Có ít nhất 2 liên kết nội bộ lấy từ danh sách được cung cấp. Không hứa hẹn "rẻ nhất", "số 1", "cam kết 100%".
TXT;
const AUTO_TONE_DEFAULT = <<<'TXT'
Viết tiếng Việt tự nhiên, rõ ràng, như một tư vấn viên giàu kinh nghiệm đang giải thích cho người mua xe lần đầu; gọi người đọc là "bạn", người viết là "ChungAuto" hoặc "chúng tôi". Giọng điềm tĩnh, không quảng cáo ồn ào, không dùng dấu chấm than.
Chỉ dùng số liệu có trong bộ dữ kiện; thiếu số liệu thì bỏ ý đó (không ghi chữ giữ chỗ), tuyệt đối không tự suy đoán giá, chính sách, điều kiện hay thời hạn. Mỗi con số đi kèm đơn vị, và nói rõ áp dụng cho phiên bản hoặc màu nào khi có.
Mở bài trả lời thẳng câu hỏi của từ khóa trong 2–3 câu. Mỗi mục H2 mở bằng một câu nêu ý chính rồi mới giải thích. Dùng bảng và danh sách khi có nhiều số liệu; có ví dụ tính cụ thể (giá sau ưu đãi, tiền trả mỗi tháng, chi phí mỗi km) khi dữ kiện cho phép.
Số mục H2 và độ dài tùy lượng dữ kiện: ít nhất 4 mục, viết đủ sâu mọi ý có thông tin, không giới hạn tối đa nhưng không độn chữ.
Khi so sánh hoặc đánh giá, nêu cả ưu điểm lẫn hạn chế và nói rõ phù hợp nhóm khách hàng nào; không công kích hãng khác. Với chương trình hoặc quy định của VinFast, ghi "theo VinFast" và nhắc con số cuối cùng là báo giá bằng văn bản của tư vấn viên.
Có mục câu hỏi thường gặp, câu hỏi viết như người mua thật sẽ gõ. Kết bài bằng một câu mời hành động hợp chủ đề (xem giá lăn bánh, tính trả góp, đăng ký lái thử) và có ít nhất 2 liên kết nội bộ lấy từ danh sách được cung cấp.
Không dùng các cụm quảng cáo tuyệt đối như "rẻ nhất", "số 1", "cam kết 100%".
TXT;
/** Cụm không được xuất hiện: bản trước (để tự nâng cấp nếu chưa chỉnh tay) và bản mới (thêm các cách nói "nhất", "số một", cam kết lợi nhuận…). */
const AUTO_FORBIDDEN_OLD = "rẻ nhất\nsố 1\nduy nhất\ncam kết 100%\nđảm bảo giá thấp nhất";
const AUTO_FORBIDDEN_DEFAULT = "rẻ nhất\ngiá rẻ nhất\nsố 1\nsố một\ntốt nhất thị trường\nbán chạy nhất\nuy tín nhất\nduy nhất\ncam kết 100%\nđảm bảo giá thấp nhất\nđảm bảo lợi nhuận\nkhông rủi ro\ntuyệt đối an toàn\nchắc chắn rẻ hơn";

/* ---------- Lưu trữ ---------- */
function auto_dir(string $DIR): string { $d = "$DIR/auto"; if (!is_dir($d)) @mkdir($d, 0750, true); return $d; }
function auto_json(string $f, array $default): array {
    $r = @file_get_contents($f);
    $j = $r === false ? null : json_decode($r, true);
    return is_array($j) ? $j : $default;
}
function auto_save_json(string $f, array $d): void {
    blog_atomic($f, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}
function auto_defaults(): array {
    return [
        'enabled' => false, 'mode' => 'publish', 'default_hour' => 9,
        'provider' => 'mock', 'models' => [], 'gsc_site' => '', 'backlink' => true, 'auto_cover' => false, 'review_weekly' => true, 'auto_plan' => true, 'auto_plan_note' => true, 'img_vision_always' => false, 'ref_enabled' => true, 'web_research' => true, 'max_images' => 6, 'min_words' => 600, 'min_score' => 70, 'max_attempts' => 3,
        'sheet_url' => '',
        'forbidden' => AUTO_FORBIDDEN_DEFAULT,
        'tone' => AUTO_TONE_DEFAULT,
    ];
}
/** Khóa API: lưu ở auto/secret.json (0600), nếu chưa có thì lấy từ biến môi trường GEMINI_API_KEY; không bao giờ hiển thị lại trên trang. */
function auto_secret(string $DIR, string $name): string {
    $saved = trim((string)(auto_json(auto_dir($DIR) . '/secret.json', [])[$name] ?? ''));   // khóa nhập ở trang quản trị được ưu tiên
    if ($saved !== '') return $saved;
    $env = getenv(strtoupper($name));
    return is_string($env) ? trim($env) : '';
}
function auto_secret_set(string $DIR, string $name, string $value): void {
    $f = auto_dir($DIR) . '/secret.json';
    $d = auto_json($f, []);
    if ($value === '') unset($d[$name]); else $d[$name] = $value;
    auto_save_json($f, $d);
    @chmod($f, 0600);
}
function auto_secret_hint(string $DIR, string $name): string {
    $k = auto_secret($DIR, $name);
    return $k === '' ? '' : '••••' . substr($k, -4);
}
function auto_settings(string $DIR): array {
    $saved = auto_json(auto_dir($DIR) . '/settings.json', []);
    if (isset($saved['gemini_model']) && empty($saved['models']['gemini_api'])) $saved['models']['gemini_api'] = (string)$saved['gemini_model'];   // bản cũ
    unset($saved['gemini_model']);
    $s = array_replace(auto_defaults(), $saved);
    $norm = fn(string $x) => trim(str_replace("\r\n", "\n", $x));
    if (in_array($norm((string)($saved['tone'] ?? '')), [$norm(AUTO_TONE_OLD), $norm(AUTO_TONE_PREV)], true)) $s['tone'] = AUTO_TONE_DEFAULT;   // chưa tự chỉnh thì dùng bản hướng dẫn mới
    if ($norm((string)($saved['forbidden'] ?? '')) === $norm(AUTO_FORBIDDEN_OLD)) $s['forbidden'] = AUTO_FORBIDDEN_DEFAULT;   // chưa tự chỉnh thì dùng danh sách mới
    if (!isset(AUTO_PROVIDERS[$s['provider']])) $s['provider'] = 'mock';
    return $s;
}
function auto_state(string $DIR): array {
    $s = auto_json(auto_dir($DIR) . '/state.json', []) + ['last_run' => 0, 'last_ok' => 0, 'last_fail' => 0, 'day' => '', 'day_count' => 0, 'last_msg' => ''];
    if ($s['day'] !== date('Y-m-d')) { $s['day'] = date('Y-m-d'); $s['day_count'] = 0; }
    return $s;
}
function auto_facts(string $DIR): string { $f = auto_dir($DIR) . '/facts.md'; return is_file($f) ? (string)file_get_contents($f) : AUTO_FACTS_DEFAULT; }
function auto_facts_sections(string $facts): array {
    $out = []; $cur = null;
    foreach (preg_split('/\r?\n/', $facts) ?: [] as $ln) {
        if (preg_match('/^##\s+(.+)$/u', $ln, $m)) { $cur = trim($m[1]); $out[$cur] = []; }
        elseif ($cur !== null && preg_match('/^-\s+(.+)$/u', $ln, $m)) $out[$cur][] = trim($m[1]);
    }
    return $out;
}
function auto_log(string $DIR, array $e): void {
    $f = auto_dir($DIR) . '/log.jsonl';
    @file_put_contents($f, json_encode(['t' => date('c')] + $e, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    if (@filesize($f) > 300000) { $l = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []; blog_atomic($f, implode("\n", array_slice($l, -300)) . "\n"); }
}
function auto_log_tail(string $DIR, int $n = 30): array {
    $f = auto_dir($DIR) . '/log.jsonl';
    if (!is_file($f)) return [];
    $out = [];
    foreach (array_slice(file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -$n) as $l) { $j = json_decode($l, true); if (is_array($j)) $out[] = $j; }
    return array_reverse($out);
}

/* ---------- Đoán loại bài từ từ khóa (dùng cho bộ viết thử) ---------- */
function auto_detect_type(string $kw): string {
    $n = blog_slugify($kw);
    $rules = [
        'phu-kien' => '/(^|-)(phu-kien|do-xe|tham(?!-(khao|gia|quan|du|dinh))|op|boc|dan-phim|phim-cach-nhiet|ppf|ceramic|camera|cam-bien|ghe-da|vo-lang|bi-led|bi-gam|bi-laser|led|loa|cach-am|hud|android-box|tran-sao|cua-hit|decal|nap-thung|nap-cuon|bac-len-xuong|baga|gia-noc|nuoc-hoa|smart-key|smartkey|cruise-control|gap-guong|mam|la-zang|tpms)(-|$)/',
        'so-sanh' => '/(^|-)(so-sanh|vs|khac-gi)(-|$)/',
        'thu-tuc' => '/(tra-gop|thu-tuc|dang-ky|dang-kiem|dat-coc|truoc-ba|bien-so|giay-to|lan-banh|o2o)/',
        'chi-phi' => '/(chi-phi|tiet-kiem|nhien-lieu|tieu-hao|bao-duong|tien-dien|tien-xang|van-hanh)/',
        'cong-nghe' => '/(^|-)(pin|sac|reev|v2l|dong-co|cong-nghe|quang-duong|tram-sac)(-|$)/',
        'kien-thuc' => '/(^|-)(la-gi|muc-phat|bi-phat|phat-bao-nhieu|phat-nguoi|luat|quy-dinh|nghi-dinh|thong-tu|bang-lai|giay-phep-lai-xe|ca-vet|cavet|giay-dang-ky|bien-bao|nong-do-con|toc-do-toi-da|lan-duong|vuot-den|kien-thuc)(-|$)/',
        'gia' => '/(^|-)(gia|bao-nhieu|uu-dai|khuyen-mai|mua)(-|$)/',
    ];
    foreach ($rules as $t => $re) { if (preg_match($re, $n)) return $t; }
    return 'huong-dan';
}
/* ---------- Ngữ cảnh, câu lệnh viết ---------- */
/** Đặt dòng [video] sau đoạn văn đầu tiên của mục (H2) khớp với $hint; không khớp thì để mặc định (sau phần mở đầu) và báo. */
function auto_video_mark(string $body, string $hint, string &$warn): string {
    $hint = trim($hint);
    if ($hint === '') return $body;
    $lines = preg_split('/\r?\n/', $body) ?: [];
    $tok = auto_tok($hint); $best = -1; $bs = 0.0;
    foreach ($lines as $i => $ln) {
        if (!preg_match('/^##\s+(.*)$/u', $ln, $m)) continue;
        $sc = mb_stripos($m[1], $hint, 0, 'UTF-8') !== false ? 1.0 : auto_hit($tok, $m[1]);
        if ($sc >= 0.6 && $sc > $bs) { $best = $i; $bs = $sc; }
    }
    if ($best < 0) { $warn = 'Không tìm thấy mục “' . $hint . '” trong bài nên video đặt sau phần mở đầu.'; return $body; }
    $n = count($lines); $j = $best + 1;
    while ($j < $n && trim($lines[$j]) === '') $j++;
    if ($j < $n && !preg_match('/^\s*(#|\||[-*]\s|\d+\.\s|>|!\[)/u', $lines[$j])) { while ($j < $n && trim($lines[$j]) !== '') $j++; }   // sau hết đoạn văn đầu của mục
    else $j = $best + 1;                                                                                                          // mục bắt đầu bằng bảng/danh sách: ngay dưới tiêu đề
    array_splice($lines, $j, 0, ['', '[video]', '']);
    return implode("\n", $lines);
}
/** Phần câu lệnh dặn AI xem ảnh đính kèm và chọn chỗ đặt. $imgs là danh sách ảnh (có 'name', 'section'); truyền số nguyên thì coi như ảnh chưa gán mục (tương thích cũ). */
function auto_images_prompt($imgs): string {
    $list = is_int($imgs) ? array_fill(0, $imgs, ['name' => '', 'section' => null]) : array_values($imgs);
    $n = count($list);
    $fixed = [];
    foreach ($list as $i => $im) {
        if ($i === 0 || ($im['section'] ?? null) === null) continue;
        $fixed[] = '- Ảnh ' . ($i + 1) . (($im['name'] ?? '') !== '' ? ' (tên tệp "' . $im['name'] . '")' : '') . ': GÁN CỐ ĐỊNH vào mục H2 số ' . $im['section'] . '. Hãy viết mục H2 thứ ' . $im['section'] . ' (đếm từ 1 theo thứ tự xuất hiện, không tính phần mở đầu) về đúng nội dung ảnh này; hệ thống tự đặt ảnh vào mục đó, bạn không ghi "after" cho ảnh này.';
    }
    $named = [];
    foreach ($list as $i => $im) {
        if ($i === 0 || ($im['section'] ?? null) !== null || (string)($im['topic'] ?? '') === '') continue;
        $named[] = '- Ảnh ' . ($i + 1) . ' (tên tệp "' . ($im['name'] ?? '') . '"): biên tập đặt tên theo chủ đề "' . $im['topic'] . '"; hãy có một mục H2 nói về chủ đề đó, hệ thống sẽ đặt ảnh vào mục có tiêu đề khớp tên tệp.';
    }
    $p = "\nẢNH ĐÍNH KÈM: có $n ảnh thật của xe, đánh số 1 đến $n theo thứ tự đính kèm. Hãy nhìn kỹ từng ảnh. Ảnh 1 làm ảnh bìa (chỉ cần mô tả). Các ảnh còn lại sẽ được chèn trong thân bài.\n";
    if ($named) $p .= "Một số ảnh được biên tập đặt tên theo chủ đề:\n" . implode("\n", $named) . "\n";
    if ($fixed) {
        $p .= "Một số ảnh đã được biên tập gán sẵn mục (qua số đầu tên tệp):\n" . implode("\n", $fixed) . "\nBài phải có đủ số mục H2 để mọi số mục nêu trên đều tồn tại. ";
    }
    $p .= "Với mỗi ảnh CHƯA gán mục, chọn tiêu đề H2 của bài mà ảnh hợp nhất (ghi đúng chữ tiêu đề H2 bạn dùng, không kèm số thứ tự; mỗi tiêu đề một ảnh, chỉ khi có nhiều ảnh hơn số mục mới dồn nhiều ảnh vào một mục, tối đa 3 ảnh mỗi mục). "
        . "Trả thêm khóa JSON \"images\": mảng các đối tượng {\"n\": số ảnh, \"after\": \"tiêu đề H2 (để trống với ảnh 1 và ảnh đã gán mục)\", \"title\": \"chú thích ngắn (tối đa 80 ký tự) hiện dưới ảnh, để trống với ảnh 1\", \"alt\": \"mô tả ngắn chính xác bằng tiếng Việt, tối đa 120 ký tự, chỉ nói những gì thấy rõ trong ảnh; không đoán màu sắc hay phiên bản nếu không chắc\"}. Không tự viết ảnh dạng markdown trong body.\n";
    return $p;
}
/** Lời dặn khi AI không xem được ảnh (model không nhận ảnh): chỉ báo mục gán sẵn qua tên tệp để viết đúng chủ đề mục đó. */
function auto_images_hint(array $imgs): string {
    $fixed = [];
    foreach (array_values($imgs) as $i => $im) {
        if ($i === 0 || ($im['section'] ?? null) === null) continue;
        $fixed[] = '- Mục H2 số ' . $im['section'] . ': biên tập đã gán ảnh tên tệp "' . ($im['name'] ?? '') . '" vào mục này; hãy viết mục đó đúng chủ đề mà tên tệp gợi ý.';
    }
    $named = [];
    foreach (array_values($imgs) as $i => $im) {
        if ($i === 0 || ($im['section'] ?? null) !== null || (string)($im['topic'] ?? '') === '') continue;
        $named[] = '- Ảnh tên tệp "' . ($im['name'] ?? '') . '": biên tập đặt tên theo chủ đề "' . $im['topic'] . '"; hãy có một mục H2 nói về chủ đề đó, hệ thống sẽ đặt ảnh vào mục có tiêu đề khớp.';
    }
    $out = $fixed ? "\nẢNH ĐÃ GÁN MỤC (bạn không xem được ảnh, chỉ biết tên tệp; mục H2 đếm từ 1 theo thứ tự xuất hiện, không tính phần mở đầu):\n" . implode("\n", $fixed) . "\nBài phải có đủ số mục H2 để mọi số mục nêu trên đều tồn tại.\n" : '';
    if ($named) $out .= "\nẢNH ĐẶT TÊN THEO CHỦ ĐỀ (bạn không xem được ảnh):\n" . implode("\n", $named) . "\n";
    return $out;
}
/**
 * Có cần cho AI xem ảnh không? Không cần khi mọi ảnh trong bài đã được đặt tên nhận dạng được (số mục "2-…" hoặc chủ đề "pin-sac-nhanh…"):
 * vị trí do tên tệp quyết định, mô tả ảnh lấy từ tiêu đề mục. Còn ảnh tên vô nghĩa (IMG_1234…) hoặc bật "Luôn cho AI xem ảnh" thì vẫn gửi.
 */
function auto_images_need_look(array $imgs, array $s): bool {
    if (!empty($s['img_vision_always'])) return true;
    foreach (array_slice(array_values($imgs), 1) as $im) {
        if (($im['section'] ?? null) === null && (string)($im['topic'] ?? '') === '') return true;
    }
    return false;
}
/**
 * Bài kết thúc bằng mục câu hỏi thường gặp mà sau câu trả lời cuối còn đoạn kết (AI quên tiêu đề "Kết luận"):
 * tách các đoạn sau đoạn trả lời đầu tiên thành mục "## Kết luận", để đoạn kết không bị tính là câu trả lời FAQ.
 */
function auto_ensure_conclusion(string $body): string {
    $lines = preg_split('/\r?\n/', $body) ?: [];
    $h2 = []; foreach ($lines as $i => $ln) { if (preg_match('/^##\s+(.*)$/u', $ln, $m)) $h2[] = [$i, $m[1]]; }
    if (!$h2 || !preg_match('/câu hỏi|hỏi đáp|faq|thắc mắc/iu', end($h2)[1])) return $body;
    $last3 = null;
    for ($i = count($lines) - 1; $i > end($h2)[0]; $i--) { if (preg_match('/^###\s/u', $lines[$i])) { $last3 = $i; break; } }
    if ($last3 === null) return $body;
    $paras = []; $cur = null;   // các đoạn sau câu hỏi cuối: [dòng bắt đầu]
    for ($i = $last3 + 1; $i < count($lines); $i++) {
        if (trim($lines[$i]) === '') { $cur = null; continue; }
        if ($cur === null) { $cur = $i; $paras[] = $i; }
    }
    if (count($paras) < 2) return $body;
    $tail = implode("\n", array_slice($lines, $paras[1]));   // chỉ tách khi phần sau đúng là lời kết (không phải đoạn thứ hai của câu trả lời)
    if (!preg_match('/^\s*(hy vọng|tóm lại|nhìn chung|như vậy|tổng kết|nói tóm lại|để |nếu bạn|liên hệ)/iu', $lines[$paras[1]]) && !preg_match('/\]\(\/|chungauto|hotline|1800/iu', $tail)) return $body;
    array_splice($lines, $paras[1], 0, ['## Kết luận', '']);
    return implode("\n", $lines);
}
/** Khóa so sánh tên tệp ảnh: bỏ đuôi, bỏ dấu, chữ thường, bỏ mọi dấu phân cách ("Ảnh Bìa_01.JPG" = "anhbia01" = "anh-bia-01.jpg"). */
function auto_img_key(string $name): string { return str_replace('-', '', blog_slugify((string)preg_replace('/\.[A-Za-z0-9]{2,5}$/', '', trim(basename(str_replace('\\', '/', $name)))), 0)); }
/**
 * Ảnh ghi trong dàn ý ở ô Ghi chú: dòng "Ảnh bìa: <tên>" và dòng "Ảnh: <tên>[; <tên>]" nằm dưới mục "H2: …" thứ N.
 * Trả về ['cover' => khóa tên hoặc '', 'sections' => [khóa tên => N]].
 */
function auto_outline_images(string $note): array {
    $out = ['cover' => '', 'sections' => []]; $h2 = 0;
    foreach (preg_split('/\r?\n/', $note) ?: [] as $ln) {
        if (preg_match('/^\s*[-*]?\s*H2\s*[:：]/iu', $ln)) { $h2++; continue; }
        if (preg_match('/^\s*[-*]?\s*Ảnh\s*bìa\s*[:：]\s*(.+)$/iu', $ln, $m)) { $k = auto_img_key(trim(explode(';', $m[1])[0])); if ($k !== '') $out['cover'] = $k; continue; }
        if ($h2 > 0 && preg_match('/^\s*[-*]?\s*Ảnh\s*[:：]\s*(.+)$/iu', $ln, $m)) {
            foreach (preg_split('/\s*[;,]\s*/u', trim($m[1])) ?: [] as $nm) { $k = auto_img_key($nm); if ($k !== '' && !isset($out['sections'][$k])) $out['sections'][$k] = $h2; }
        }
    }
    return $out;
}
/**
 * Áp dàn ý lên bộ ảnh đã tải: ảnh có tên khớp dòng "Ảnh bìa" lên đầu làm ảnh bìa, ảnh khớp dòng "Ảnh:" của mục N được gán cố định vào mục N
 * (ưu tiên hơn số trong tên tệp). Ảnh không được dàn ý nhắc tới giữ cách đặt cũ (theo số/chủ đề trong tên tệp). $hits = số ảnh khớp dàn ý.
 */
function auto_apply_outline_images(array $imgs, string $note, int &$hits = 0): array {
    $hits = 0;
    if (count($imgs) < 1 || trim($note) === '') return $imgs;
    $o = auto_outline_images($note);
    if ($o['cover'] === '' && !$o['sections']) return $imgs;
    $ci = null;
    foreach ($imgs as $i => $im) { if ($o['cover'] !== '' && auto_img_key((string)($im['name'] ?? '')) === $o['cover']) { $ci = $i; break; } }
    if ($ci !== null && $ci !== 0) { $c = $imgs[$ci]; unset($imgs[$ci]); array_unshift($imgs, $c); $imgs = array_values($imgs); }
    if ($ci !== null) { $imgs[0]['section'] = null; $imgs[0]['topic'] = ''; $hits++; }
    foreach ($imgs as $i => $im) {
        if ($i === 0) continue;
        $k = auto_img_key((string)($im['name'] ?? ''));
        if ($k !== '' && isset($o['sections'][$k])) { $imgs[$i]['section'] = $o['sections'][$k]; $imgs[$i]['topic'] = ''; $hits++; }
    }
    return $imgs;
}
/** Đánh số tiêu đề H2 theo thứ tự xuất hiện ("1. …", "2. …"); bỏ số AI tự gõ. Không đụng vào dòng trong khối mã. */
function auto_number_headings(string $body): string {
    $n = 0; $code = false; $out = [];
    foreach (preg_split('/\r?\n/', $body) ?: [] as $ln) {
        if (preg_match('/^\s*```/', $ln)) $code = !$code;
        if (!$code && preg_match('/^##\s+(.*)$/u', $ln, $m)) {
            $ln = '## ' . (++$n) . '. ' . trim(preg_replace('/^\d{1,2}\s*[.):]\s+/u', '', trim($m[1])) ?? $m[1]);
        }
        $out[] = $ln;
    }
    return implode("\n", $out);
}
/** Bỏ dòng tiêu đề H1 ("# …") AI lỡ viết trong thân bài (tiêu đề bài đã có ở trường title; thân bài không được có H1). Không đụng vào khối mã. */
function auto_strip_h1(string $body): string {
    $code = false; $out = [];
    foreach (preg_split('/\r?\n/', $body) ?: [] as $ln) {
        if (preg_match('/^\s*```/', $ln)) $code = !$code;
        if (!$code && preg_match('/^#\s+\S/u', $ln)) continue;
        $out[] = $ln;
    }
    return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $out)) ?? '');
}
/** Câu lệnh đầy đủ gửi cho AI (mock không dùng; các nhà cung cấp AI thật sẽ dùng). */
function auto_prompt(array $s, array $item, string $facts, array $links, array $cats, string $playbook = ''): string {
    $lk = implode("\n", array_map(fn($l) => '- [' . $l[0] . '](' . $l[1] . ')', array_slice($links, 0, 12)));
    $info = '';
    foreach ([['Dạng bài', $item['form'] ?? ''], ['Ý định tìm kiếm của người đọc', $item['intent'] ?? ''], ['Từ khóa phụ cần lồng ghép tự nhiên (không nhồi nhét)', $item['sub'] ?? ''], ['GHI CHÚ BIÊN TẬP (dàn ý và yêu cầu bắt buộc; thông tin trong đây dùng như dữ kiện)', $item['note'] ?? '']] as [$k, $v]) {
        if (trim((string)$v) !== '') $info .= "- $k: " . trim((string)$v) . "\n";
    }
    $cat = ($item['category'] ?? '') !== '' ? "category phải đúng là \"{$item['category']}\"" : 'category (một trong: ' . implode(', ', $cats) . ')';
    return "Bạn là biên tập viên nội dung của ChungAuto (đại lý VinFast VF Wild). Hãy viết MỘT bài blog chuẩn SEO cho từ khóa: \"{$item['keyword']}\".\n"
        . ($info !== '' ? "\nTHÔNG TIN BIÊN TẬP:\n$info" : '') . "\n"
        . "HƯỚNG DẪN VIẾT:\n{$s['tone']}\n\n"
        . auto_blueprint_prompt($item, $s) . "\n" . $playbook
        . "YÊU CẦU ĐỊNH DẠNG: trả về JSON gồm các khóa title, seo_title (≤ 58 ký tự), description (110–155 ký tự), $cat, tags (mảng 3–5 thẻ), body (Markdown, tiêu đề mục dùng ##, không dùng H1, không HTML, bảng dùng dấu |). \n"
        . (auto_kw_other_car((string)$item['keyword'])
            ? ($lk !== '' ? "LIÊN KẾT NỘI BỘ CÓ THỂ DÙNG (chỉ chèn khi thật sự liên quan, không bắt buộc; KHÔNG chèn các trang về VF Wild vào bài này):\n{$lk}\n\n" : "LIÊN KẾT NỘI BỘ: bài này viết cho xe khác VF Wild, không chèn liên kết tới các trang VF Wild.\n\n")
            : "LIÊN KẾT NỘI BỘ CÓ THỂ DÙNG (dùng ít nhất 2):\n{$lk}\n\n")
        . "TỪ/CỤM KHÔNG ĐƯỢC DÙNG:\n" . trim($s['forbidden']) . "\n\n"
        . "BỘ DỮ KIỆN (chỉ được dùng số liệu trong này và trong ghi chú biên tập):\n{$facts}\n";
}

/* ---------- Nhà cung cấp AI ---------- */
function auto_generate(array $s, array $item, array $ctx): array {
    switch ($s['provider']) {
        case 'mock': return auto_mock($item, $ctx);
        default: return auto_ai_article($s, $ctx);
    }
}
/** Bộ viết thử: ghép bài từ bộ dữ kiện để kiểm tra trọn luồng (không phải nội dung AI thật). */
function auto_mock(array $item, array $ctx): array {
    $sections = auto_facts_sections($ctx['facts']);
    $plan = [
        'gia' => ['Giá niêm yết', 'Thủ tục và chi phí', 'Chi phí vận hành', 'ChungAuto'],
        'so-sanh' => ['So sánh', 'Kích thước', 'Công nghệ', 'Giá niêm yết'],
        'thu-tuc' => ['Thủ tục và chi phí', 'Giá niêm yết', 'Bảo hành', 'ChungAuto'],
        'cong-nghe' => ['Công nghệ', 'Kích thước', 'Bảo hành', 'Chi phí vận hành'],
        'chi-phi' => ['Chi phí vận hành', 'Giá niêm yết', 'Công nghệ', 'Thủ tục và chi phí'],
        'huong-dan' => ['Giá niêm yết', 'Công nghệ', 'Thủ tục và chi phí', 'Kích thước'],
        'phu-kien' => ['Phụ kiện', 'Kích thước', 'Showroom', 'ChungAuto'],
    ][$item['type']] ?? [];
    $picked = [];
    foreach ($plan as $want) {
        foreach ($sections as $h => $bul) { if ($bul && stripos($h, $want) === 0 && !isset($picked[$h])) { $picked[$h] = $bul; break; } }
    }
    if (count($picked) < 3) { foreach ($sections as $h => $bul) { if ($bul && !isset($picked[$h])) $picked[$h] = $bul; if (count($picked) >= 4) break; } }
    $kw = $item['keyword'];
    $kwU = mb_strtoupper(mb_substr($kw, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($kw, 1, null, 'UTF-8');
    $b = "Bài viết này tổng hợp thông tin về **$kw** dựa trên số liệu ChungAuto đã kiểm tra. Mục đích là giúp bạn nắm nhanh những điểm quan trọng trước khi quyết định, không thay thế báo giá bằng văn bản của tư vấn viên.\n\n";
    $b .= "## Tóm tắt nhanh\n\n";
    foreach ($picked as $bul) { $b .= '- ' . $bul[0] . "\n"; }
    foreach ($picked as $h => $bul) {
        $b .= "\n## " . $h . "\n\nPhần này tóm lược các số liệu liên quan đến " . mb_strtolower($h, 'UTF-8') . " mà bạn nên biết.\n\n";
        foreach ($bul as $x) $b .= '- ' . $x . "\n";
    }
    $b .= "\n## Câu hỏi thường gặp\n";
    $n = 0;
    foreach ($picked as $h => $bul) {
        if (++$n > 3) break;
        $b .= "\n### " . $h . ' cần lưu ý điều gì?' . "\n\n" . ($bul[1] ?? $bul[0]) . "\n";
    }
    $b .= "\n## Bước tiếp theo\n\nĐể có con số chính xác nhất cho trường hợp của bạn, hãy xem thêm:\n\n";
    foreach (array_slice($ctx['links'], 0, 5) as $l) $b .= '- [' . $l[0] . '](' . $l[1] . ")\n";
    $b .= "\n> Số liệu có thể thay đổi theo từng thời điểm; hãy xác nhận lại với tư vấn viên ChungAuto trước khi quyết định.\n";
    $title = $kwU;
    return [
        'title' => $title, 'seo_title' => mb_substr($title, 0, 58, 'UTF-8'),
        'description' => 'Tổng hợp thông tin về ' . $kw . ': số liệu, ưu đãi và lưu ý khi mua VinFast VF Wild do ChungAuto kiểm tra.',
        'category' => AUTO_TYPES[$item['type']], 'tags' => ['vf wild', mb_strtolower(AUTO_TYPES[$item['type']], 'UTF-8')], 'body' => $b,
    ];
}

function auto_pick_cover(string $DIR): array {
    $items = array_values(array_filter(blog_media_items($DIR), fn($i) => !$i['uploaded']));
    if (!$items) return ['', ''];
    $min = min(array_column($items, 'used'));
    $pool = array_values(array_filter($items, fn($i) => $i['used'] === $min));
    $it = $pool[random_int(0, count($pool) - 1)];
    return [$it['cover'], 'VinFast VF Wild – ' . $it['label']];
}

/** Làm sạch ô Ghi chú: bỏ dấu ngoặc kép bao quanh (hay dính khi dán từ nơi khác) và thống nhất xuống dòng. */
function auto_clean_note(string $n): string {
    return trim(trim(str_replace("\r\n", "\n", $n)), "\"“”");
}
/* ---------- Google Sheet: nguồn chủ đề + giờ đăng ---------- */
const AUTO_TAB = 'Từ khóa';
const AUTO_READY = ['Sẵn sàng'];   // chỉ dòng đã chuyển sang "Sẵn sàng" và có giờ đăng (cột "Ngày đăng mong muốn") đã đến mới được tự viết và đăng

function auto_sheet_id(array $s): ?string { return gs_id_from_url((string)$s['sheet_url']); }

/** "2026-11-01", "2026-11-01 09:30", "01/11/2026 9:30" → mốc thời gian; chỉ có ngày thì dùng giờ mặc định. */
function auto_parse_time(string $v, int $defaultHour): ?int {
    $v = trim($v);
    if ($v === '') return null;
    $h = $defaultHour; $mi = 0;
    if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T]+(\d{1,2})[:h](\d{2}))?#u', $v, $m)) { [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]]; }
    elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})(?:[ T]+(\d{1,2})[:h](\d{2}))?#u', $v, $m)) { [$d, $mo, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]]; }
    else return null;
    if (isset($m[4]) && $m[4] !== '') { $h = (int)$m[4]; $mi = (int)$m[5]; }
    if (!checkdate($mo, $d, $y) || $h > 23 || $mi > 59) return null;
    return mktime($h, $mi, 0, $mo, $d, $y) ?: null;
}
/** Tên cột (đã bỏ dấu) → trường. Hệ thống tìm cột theo dòng tiêu đề nên bạn đổi chỗ, thêm hay bớt cột khác đều được. */
const AUTO_COLS = [
    'keyword' => ['tu-khoa-chinh', 'tu-khoa'], 'status' => ['trang-thai'], 'category' => ['chuyen-muc'], 'form' => ['dang-bai'],
    'intent' => ['y-dinh-tim-kiem'], 'prio' => ['uu-tien'], 'sub' => ['tu-khoa-phu'], 'images' => ['anh-link', 'anh', 'link-anh', 'hinh-anh'],
    'note' => ['ghi-chu-goc-viet', 'ghi-chu'], 'video' => ['video-link', 'link-video', 'video'], 'when' => ['ngay-dang-mong-muon', 'gio-dang-mong-muon', 'gio-dang'],
    'code' => ['ma'], 'title' => ['tieu-de-bai'], 'link' => ['link-bai'], 'date' => ['ngay-dang-thuc-te'], 'score' => ['diem-seo'],
    'words' => ['so-tu'], 'updated' => ['cap-nhat-lan-cuoi'], 'error' => ['thong-bao-loi'], 'attempts' => ['so-lan-thu'],
];
const AUTO_RESULT_FIELDS = ['code', 'title', 'link', 'date', 'score', 'words', 'updated', 'error', 'attempts'];
const AUTO_COL_LABEL = ['keyword' => 'Từ khóa chính', 'status' => 'Trạng thái', 'when' => 'Ngày đăng mong muốn'];

function auto_layout_from_header(array $hdr): array {
    $slugs = array_map(fn($h) => blog_slugify((string)$h), $hdr);
    $map = [];
    foreach (AUTO_COLS as $f => $names) {
        foreach ($names as $nm) { $i = array_search($nm, $slugs, true); if ($i !== false) { $map[$f] = (int)$i; continue 2; } }
    }
    return $map;
}
/** Vị trí các cột của tab "Từ khóa" (trường → số thứ tự cột, 0 = A). Truyền $hdr khi đã có sẵn hàng tiêu đề. */
function auto_sheet_layout(string $DIR, array $s, ?array $hdr = null): array {
    static $cache = [];
    $id = (string)auto_sheet_id($s);
    if ($hdr !== null) $cache[$id] = auto_layout_from_header($hdr);
    elseif (!isset($cache[$id])) $cache[$id] = auto_layout_from_header(gs_read($DIR, $id, "'" . AUTO_TAB . "'!A1:AD1")[0] ?? []);
    $miss = array_diff(['keyword', 'status', 'when'], array_keys($cache[$id]));
    if ($miss) throw new RuntimeException('Sheet thiếu cột bắt buộc: ' . implode(', ', array_map(fn($f) => AUTO_COL_LABEL[$f], $miss)) . ' (kiểm tra dòng tiêu đề ở hàng 1 của tab "' . AUTO_TAB . '").');
    return $cache[$id];
}
function auto_sheet_rows(string $DIR, array $s): array {
    $id = auto_sheet_id($s);
    if (!$id) throw new RuntimeException('Chưa nhập link Google Sheet.');
    $all = gs_read($DIR, $id, "'" . AUTO_TAB . "'!A1:AD1000");
    $lay = auto_sheet_layout($DIR, $s, $all[0] ?? []);
    $out = [];
    foreach ($all as $i => $r) {
        if ($i === 0) continue;
        $g = fn(string $f): string => isset($lay[$f]) ? trim((string)($r[$lay[$f]] ?? '')) : '';
        $kw = $g('keyword');
        if ($kw === '') continue;
        $out[] = ['row' => $i + 1, 'keyword' => mb_substr(preg_replace('/\s+/u', ' ', $kw) ?? $kw, 0, 120), 'status' => $g('status'), 'category' => $g('category'), 'form' => $g('form'),
            'intent' => $g('intent'), 'prio' => $g('prio'), 'sub' => $g('sub'), 'note' => auto_clean_note($g('note')), 'when_raw' => $g('when'), 'when' => auto_parse_time($g('when'), (int)$s['default_hour']),
            'code' => $g('code'), 'link' => $g('link'), 'updated' => $g('updated'), 'error' => $g('error'), 'attempts' => (int)$g('attempts'), 'images_cell' => $g('images'), 'video' => $g('video'),
            'type' => auto_type_for($g('category'), $g('form'), $kw)];
    }
    return $out;
}
/** Trạng thái hiển thị + dòng này có được viết ở lượt này không. */
function auto_row_ready(array $r, array $s, int $now, bool $manual): bool {
    $stale = $r['status'] === 'Đang xử lý' && ($t = strtotime($r['updated'])) && $now - $t > 3600;   // bị ngắt giữa chừng
    if (!in_array($r['status'], AUTO_READY, true) && !$stale) return false;
    if ($r['when'] === null && !$manual) return false;
    if ($r['when'] !== null && $r['when'] > $now && !$manual) return false;
    if ($r['attempts'] >= (int)$s['max_attempts']) return false;
    if ($r['error'] !== '' && ($t = strtotime($r['updated'])) && $now - $t < 1800 && !$manual) return false;   // chờ 30 phút sau lần lỗi
    return true;
}
function auto_next_row(array $rows, array $s, int $now, bool $manual): ?array {
    $c = array_values(array_filter($rows, fn($r) => auto_row_ready($r, $s, $now, $manual)));
    usort($c, fn($a, $b) => [$a['when'] ?? PHP_INT_MAX, $a['row']] <=> [$b['when'] ?? PHP_INT_MAX, $b['row']]);
    return $c[0] ?? null;
}
/** Ghi trạng thái và các cột kết quả (Mã … Số lần thử) của một dòng, tìm cột theo tên ở hàng tiêu đề. */
function auto_row_set(string $DIR, array $s, int $row, ?string $status, ?array $lt): void {
    $id = auto_sheet_id($s);
    if (!$id) return;
    $lay = auto_sheet_layout($DIR, $s);
    $data = [];
    if ($status !== null) $data[] = ['range' => "'" . AUTO_TAB . "'!" . gs_col($lay['status']) . $row, 'values' => [[$status]]];
    if ($lt !== null) { foreach (AUTO_RESULT_FIELDS as $k => $f) { if (isset($lay[$f])) $data[] = ['range' => "'" . AUTO_TAB . "'!" . gs_col($lay[$f]) . $row, 'values' => [[$lt[$k] ?? '']]]; } }
    if ($data) gs_batch($DIR, $id, $data);
}
function auto_sheet_log(string $DIR, array $s, string $code, string $kw, string $act, string $res, string $detail): void {
    try { if ($id = auto_sheet_id($s)) gs_append($DIR, $id, "'Nhật ký'!A:F", [[date('Y-m-d H:i:s'), $code, $kw, $act, $res, mb_substr($detail, 0, 300)]], true); } catch (Throwable $e) { /* nhật ký phụ, bỏ qua */ }
}
function auto_posted(string $DIR): array { return auto_json(auto_dir($DIR) . '/posted.json', []); }

/* ---------- Xử lý một dòng ---------- */
/**
 * Bộ dữ kiện cho một dòng (dùng chung cho bước lập dàn ý và bước viết): bộ dữ kiện của site + kiến thức phụ kiện (bài phụ kiện)
 * + nội dung bài cùng chủ đề trên chungauto.vn (nếu bật và tìm thấy). Trả về [chữ dữ kiện, bài tham khảo hoặc null].
 */
function auto_row_facts(string $DIR, array $s, array $r): array {
    $kw = (string)$r['keyword'];
    $facts = auto_facts($DIR);
    $type = (string)($r['type'] ?? auto_type_for((string)($r['category'] ?? ''), (string)($r['form'] ?? ''), $kw));
    if ($type === 'phu-kien') $facts .= "\n\n" . (auto_kw_other_car($kw) ? AUTO_ACC_GENERAL : auto_accessory_facts($kw));   // kiến thức phụ kiện học từ chungauto.vn (nhóm khớp từ khóa); bài cho xe khác chỉ lấy phần chung, vì số liệu trong các nhóm là của VF Wild
    $res = auto_research($DIR, $s, $kw, $type);   // tra cứu web (nguồn chính thức, không lấy chungauto.vn): đứng trước bài tham khảo
    if ($res) $facts .= "\n\n" . auto_research_facts($res);
    $ref = !empty($s['ref_enabled']) && $s['provider'] !== 'mock' ? auto_ref_article($DIR, $kw) : null;   // bài cùng chủ đề trên chungauto.vn: ý cần phủ + dữ kiện của ChungAuto
    if ($ref) $facts .= "\n\n" . auto_ref_facts($ref);
    return [$facts, $ref, $res];
}
function auto_process(string $DIR, array $r, array $s): array {
    $attempts = $r['attempts'] + 1;
    $now = date('Y-m-d H:i:s');
    $kw = $r['keyword'];
    $ledger = auto_posted($DIR);
    $key = blog_slugify($kw);
    $r['prev'] = strpos($r['error'], 'Điểm') === 0 ? $r['error'] : '';   // lần trước bị loại vì gì thì dặn AI sửa đúng chỗ đó
    try {
        if (!empty($ledger[$key]['pending'])) {   // chỉ khi lần trước đã lưu bài mà chưa kịp ghi lại Sheet (không phải trùng từ khóa)
            $p = $ledger[$key];
            auto_row_set($DIR, $s, $r['row'], $p['state'] === 'published' ? 'Đã đăng' : 'Tạm dừng', [$p['slug'], $p['title'], $p['state'] === 'published' ? SITE_URL . '/blog/' . $p['slug'] . '/' : '', $p['date'], $p['score'], $p['words'], $now, '', $attempts]);
            $ledger[$key]['pending'] = false; auto_save_json(auto_dir($DIR) . '/posted.json', array_slice($ledger, -500, null, true));
            return [true, 'Đã ghi lại kết quả của bài "' . $kw . '" vào Sheet.'];
        }
        auto_row_set($DIR, $s, $r['row'], 'Đang xử lý', ['', '', '', '', '', '', $now, '', $attempts]);
        $posts = blog_all($DIR);
        [$facts, $r['ref'], $r['research']] = auto_row_facts($DIR, $s, $r);
        $r['related'] = auto_related_existing($posts, $kw);   // bài cùng chủ đề đã có trên vfwild.vn: bài mới chọn góc khác, không lặp
        $gap = $s['provider'] === 'mock' ? null : auto_facts_gap($kw, $r, $facts);
        if ($gap !== null) {   // bộ dữ kiện không nói gì về chủ đề: viết ra chỉ là chữ độn, nên dừng và báo cho biên tập bổ sung
            $why = 'Thiếu dữ kiện về "' . $gap . '". Hãy thêm thông tin này vào Bộ dữ kiện (hoặc ghi dữ kiện vào cột Ghi chú) rồi đặt lại trạng thái "Sẵn sàng".';
            auto_row_set($DIR, $s, $r['row'], 'Tạm dừng', ['', '', '', '', '', '', $now, $why, $attempts]);
            auto_log($DIR, ['keyword' => $kw, 'result' => 'bỏ qua', 'msg' => $why]);
            auto_sheet_log($DIR, $s, '', $kw, 'Kiểm tra dữ kiện', 'Bỏ qua', $why);
            return [true, 'Bỏ qua "' . $kw . '": ' . $why];
        }
        $links = auto_link_candidates($posts, $kw);
        $cats = blog_cats($DIR);
        $imgRes = $r['images_cell'] !== '' ? img_prepare($DIR, $r['images_cell'], (int)($s['max_images'] ?? IMG_DEFAULT_MAX), auto_kw_core($kw) ?: $kw) : ['images' => [], 'errors' => []];
        $imgHits = 0;
        $imgs = auto_apply_outline_images($imgRes['images'], (string)$r['note'], $imgHits);   // dàn ý ở ô Ghi chú ghi ảnh nào vào mục nào
        $look =$imgs && auto_images_need_look($imgs, $s);   // ảnh đã đặt tên nhận dạng được (số mục hoặc chủ đề) thì không gửi ảnh cho AI: tiết kiệm token, dành cho việc viết bài
        $vision = $look && !empty(AUTO_PROVIDERS[$s['provider']]['vision']);
        $basePrompt = auto_prompt($s, $r, $facts, $links, $cats, review_playbook_text($DIR));
        $gen = auto_generate($s, $r, ['dir' => $DIR, 'facts' => $facts, 'links' => $links, 'categories' => $cats, 'prompt' => $basePrompt . ($vision ? auto_images_prompt($imgs) : auto_images_hint($imgs)), 'prompt_noimg' => $basePrompt . auto_images_hint($imgs), 'images' => $vision ? $imgs : []]);
        $seen = $vision && ($gen['_vision'] ?? false);
        $gen['body'] = auto_strip_h1((string)$gen['body']);   // AI hay chép dòng "H1: …" của dàn ý thành "# …" trong thân bài
        $plan = [];
        foreach ((array)($gen['images'] ?? []) as $it) { if (is_array($it) && isset($it['n'])) $plan[(int)$it['n']] = ['after' => (string)($it['after'] ?? ''), 'alt' => (string)($it['alt'] ?? ''), 'title' => (string)($it['title'] ?? '')]; }
        $placed = 0; $coverTitle = '';
        if ($imgs) {
            $cover = $imgs[0]['cover'];
            $coverTitle = img_clean_title((string)($plan[1]['title'] ?? ''));
            $altPre = preg_match('/vf\s*wild/iu', $kw . ' ' . $gen['title']) ? 'VinFast VF Wild – ' : '';   // bài không nói về VF Wild (luật, giấy tờ…) thì alt không gắn tên xe
            $alt = img_clean_alt((string)($plan[1]['alt'] ?? ''), $altPre . trim($gen['title']));
            $imgWarn = [];
            [$gen['body'], $placed] = img_place(trim(str_replace("\r\n", "\n", $gen['body'])), $imgs, $seen ? $plan : [], $imgWarn, $altPre);   // AI chưa xem ảnh thì bỏ kế hoạch/mô tả của AI; số mục trong tên tệp vẫn được dùng
            if ($imgWarn) $imgRes['errors'] = array_merge($imgRes['errors'], $imgWarn);
        } elseif (!empty($s['auto_cover'])) [$cover, $alt] = auto_pick_cover($DIR);
        else [$cover, $alt] = ['', ''];   // không có link ảnh thì bài không có ảnh (trừ khi bật tự chọn ảnh có sẵn)
        [$video, $videoHint] = array_pad(array_map('trim', explode('|', (string)($r['video'] ?? ''), 2)), 2, ''); $videoWarn = '';   // ô Sheet: link  hoặc  link | tên mục muốn đặt video
        if ($video !== '') { $vp = blog_video_parse($video); if ($vp) $video = $vp['url']; else { $videoWarn = 'Link video chưa nhận dạng được (dùng YouTube, Vimeo hoặc .mp4) nên đã bỏ qua.'; $video = ''; } }
        $accCat = $r['type'] === 'phu-kien' ? (array_values(array_filter($cats, fn($c) => mb_stripos($c, 'phụ kiện', 0, 'UTF-8') !== false))[0] ?? '') : '';   // bài phụ kiện để trống chuyên mục: vào chuyên mục "Phụ kiện…" nếu site có
        $cat = $r['category'] !== '' ? $r['category'] : ($accCat !== '' ? $accCat : (in_array($gen['category'] ?? '', $cats, true) ? $gen['category'] : AUTO_TYPES[$r['type']]));
        // đường dẫn ngắn theo từ khóa chính (tốt cho SEO: "ca-vet-xe-la-gi" thay vì cả câu tiêu đề); đã có bài dùng thì lấy theo tiêu đề (rút gọn), rồi mới thêm -2, -3…
        $slugBase = blog_slugify($kw);
        if ($slugBase === '' || is_file(blog_dir($DIR) . "/$slugBase.md")) { $t = blog_slugify(trim(preg_split('/[:|–]/u', (string)$gen['title'])[0] ?? '')); $slugBase = $t !== '' && !is_file(blog_dir($DIR) . "/$t.md") ? $t : ($slugBase !== '' ? $slugBase : blog_slugify($gen['title'])); }
        $slug = $slugBase;
        for ($n = 2; is_file(blog_dir($DIR) . "/$slug.md") && $n < 20; $n++) $slug = $slugBase . '-' . $n;
        $mock = $s['provider'] === 'mock';
        $bodyFinal = auto_ensure_links(auto_ensure_conclusion(trim(str_replace("\r\n", "\n", $gen['body']))), $links);
        if (!empty($r['research']) && !preg_match('/^##\s+(\d+\.\s*)?(nguồn tham khảo|tài liệu tham khảo)/miu', $bodyFinal)) $bodyFinal = rtrim($bodyFinal) . "\n\n" . auto_sources_section($r['research']);   // nguồn chính thức đã tra (E-E-A-T, chatbot AI trích dẫn)
        if ($video !== '' && $videoHint !== '') { $vw = ''; $bodyFinal = auto_video_mark($bodyFinal, $videoHint, $vw); if ($vw !== '') $videoWarn = trim($videoWarn . ' ' . $vw); }
        $bodyFinal = auto_fix_names(auto_unbold_subs($bodyFinal, auto_subs($r), $kw), $kw);   // bỏ in đậm từ khóa phụ; viết hoa đúng tên xe, tên hãng
        $bodyFinal = auto_bold_keyword($bodyFinal, $kw);   // in đậm từ khóa chính ở đoạn mở đầu và mỗi mục nếu AI quên
        foreach (['title', 'seo_title', 'description'] as $f) { if (isset($gen[$f])) $gen[$f] = auto_fix_names((string)$gen[$f], $kw); }
        $gen['tags'] = array_map(fn($x) => auto_fix_names((string)$x, $kw), (array)($gen['tags'] ?? []));
        $bodyFinal = auto_number_headings($bodyFinal);   // "1. …", "2. …": số mục khớp với số đầu tên tệp ảnh
        $in = [
            'title' => trim($gen['title']), 'seo_title' => trim((string)($gen['seo_title'] ?? '')), 'slug' => $slug, 'description' => trim($gen['description']),
            'date' => date('Y-m-d'), 'category' => $cat, 'tags' => blog_tags_norm(implode(', ', (array)($gen['tags'] ?? []))), 'cover' => $cover, 'cover_alt' => $alt, 'cover_title' => $coverTitle, 'video' => $video,
            'status' => ($s['mode'] === 'publish' && !$mock) ? 'published' : 'draft', 'body' => $bodyFinal,
        ];
        $sc = auto_score($in, $s, $facts, $r);
        if ($r['ref']) {   // viết từ bài tham khảo: đo mức chép câu chữ (chép nhiều thì bắt viết lại, chép ít thì nhắc biên tập)
            $cp = auto_copy_ratio($bodyFinal, $r['ref']['text'], 6);   // cụm 6 từ liên tiếp: bắt cả kiểu chép đổi vài chữ
            $sameH = auto_ref_same_headings($bodyFinal, $r['ref']); $refH = count(array_filter((array)$r['ref']['outline'], fn($l) => str_starts_with($l, 'H2:')));
            if ($cp >= 0.15) { array_unshift($sc['issues'], 'Chép câu chữ của bài tham khảo ' . round($cp * 100) . '% (phải viết lại bằng lời và giọng văn riêng, không quá 6 từ liên tiếp giống bài tham khảo).'); $sc['score'] = min($sc['score'], 49); $sc['force'] = true; }
            elseif ($cp >= 0.05) $sc['warnings'][] = 'Có ' . round($cp * 100) . '% câu chữ giống bài tham khảo ' . $r['ref']['url'] . ', nên đọc lại.';
            if ($sameH >= 3 && $sameH * 2 >= max(1, $refH)) { array_unshift($sc['issues'], "Có $sameH tiêu đề mục trùng nguyên văn bài chungauto.vn: hãy đặt tên mục theo cách nói riêng."); $sc['score'] = min($sc['score'], 49); $sc['force'] = true; }
            elseif ($sameH > 0) $sc['warnings'][] = "Có $sameH tiêu đề mục trùng bài tham khảo.";
            $sc['warnings'][] = 'Dùng bài tham khảo: ' . $r['ref']['url'] . '.';
        }
        if (!$mock) {   // không lặp câu chữ, tiêu đề, dàn ý của mọi bài đã có trên vfwild.vn: các lần đầu bắt viết lại góc khác; lần thử cuối vẫn lưu (kèm cảnh báo) để cùng từ khóa luôn viết được
            [$ri, $rw] = auto_site_overlap($bodyFinal, $in['title'], $posts, $facts);
            if ($ri && $attempts < (int)$s['max_attempts']) { $sc['issues'] = array_merge($ri, $sc['issues']); $sc['score'] = min($sc['score'], 49); $sc['force'] = true; }
            elseif ($ri) $rw = array_merge($ri, $rw);
            $sc['warnings'] = array_merge($sc['warnings'], $rw);
        }
        if (!empty($r['research']) && !$mock) {   // không chép câu chữ, dàn ý của các trang web nguồn đã tra (như với bài chungauto.vn: chép thì luôn viết lại)
            [$wi, $ww] = auto_web_overlap($bodyFinal, auto_research_pages($DIR, $r['research']));
            if ($wi) { $sc['issues'] = array_merge($wi, $sc['issues']); $sc['score'] = min($sc['score'], 49); $sc['force'] = true; }
            $sc['warnings'] = array_merge($sc['warnings'], $ww);
        }
        if (!empty($r['research'])) $sc['warnings'][] = 'Đã tra cứu ' . count($r['research']['facts']) . ' dữ kiện trên web (' . implode(', ', array_slice(array_column((array)$r['research']['sources'], 'host'), 0, 4)) . ').';
        if ($sc['score'] < (int)$s['min_score'] || !empty($sc['force'])) {   // force: lỗi buộc viết lại (cụm cấm, chép, trùng bài) dù điểm tối thiểu đặt thấp
            $msg = 'Điểm ' . $sc['score'] . '/100: ' . implode(' ', $sc['issues']);
            $final = $attempts >= (int)$s['max_attempts'];
            auto_row_set($DIR, $s, $r['row'], $final ? 'Lỗi' : 'Sẵn sàng', ['', '', '', '', $sc['score'], $sc['words'], $now, mb_substr($msg, 0, 300), $attempts]);
            auto_log($DIR, ['keyword' => $kw, 'result' => $final ? 'lỗi' : 'viết lại', 'msg' => $msg]);
            auto_sheet_log($DIR, $s, '', $kw, 'Chấm điểm', $final ? 'Lỗi' : 'Viết lại', $msg);
            return [false, 'Chưa đạt chất lượng (' . $sc['score'] . '/100): ' . implode(' ', $sc['issues'])];
        }
        $errs = blog_validate_post($in, '', blog_dir($DIR));
        if ($errs) throw new RuntimeException(implode(' ', $errs));
        blog_store_post($DIR, $in, '');
        $ledger[$key] = ['slug' => $slug, 'title' => $in['title'], 'state' => $in['status'], 'date' => $in['date'], 'score' => $sc['score'], 'words' => $sc['words'], 'pending' => true];
        auto_save_json(auto_dir($DIR) . '/posted.json', array_slice($ledger, -500, null, true));
        try { review_register($DIR, $slug, ['provider' => $s['provider'], 'model' => $mock ? 'mock' : auto_model($s, (string)$s['provider']), 'type' => $r['type'], 'score' => $sc['score'], 'keyword' => $kw]); } catch (Throwable $e) { /* review là phụ, không chặn bài */ }
        $pub = $in['status'] === 'published';
        $note = trim(($pub ? '' : 'Đã lưu NHÁP (chế độ thử hoặc bộ viết thử nghiệm), chưa đăng lên blog. ') . implode(' ', $sc['warnings']));
        if ($imgs) $note = trim($note . ' Ảnh: bìa + ' . $placed . ' ảnh trong bài' . ($imgHits > 0 ? ' (' . $imgHits . ' ảnh đặt theo dàn ý ở ô Ghi chú)' : '') . ($seen ? ' (AI đã xem ảnh).' : (!$look ? ' (đặt theo dàn ý/tên tệp, không gửi ảnh cho AI nên tiết kiệm token).' : ' (chèn theo vị trí cố định).')));
        if ($imgRes['errors']) $note = trim($note . ' ' . implode(' ', $imgRes['errors']));
        if ($videoWarn !== '') $note = trim($note . ' ' . $videoWarn);
        if ($sc['issues']) $note = trim($note . ' Gợi ý cải thiện: ' . implode(' ', $sc['issues']));
        $note = mb_substr($note, 0, 450);
        try { auto_row_set($DIR, $s, $r['row'], $pub ? 'Đã đăng' : 'Tạm dừng', [$slug, $in['title'], $pub ? SITE_URL . '/blog/' . $slug . '/' : '', $in['date'], $sc['score'], $sc['words'], $now, $note, $attempts]); $ledger[$key]['pending'] = false; auto_save_json(auto_dir($DIR) . '/posted.json', array_slice($ledger, -500, null, true)); }
        catch (Throwable $e) { auto_log($DIR, ['keyword' => $kw, 'result' => 'cảnh báo', 'msg' => 'Đã đăng nhưng chưa ghi được Sheet: ' . $e->getMessage()]); }
        auto_log($DIR, ['keyword' => $kw, 'result' => $pub ? 'đã đăng' : 'bản nháp', 'msg' => $slug . ' – điểm ' . $sc['score'] . ', ' . $sc['words'] . ' từ' . ($sc['warnings'] ? ' – ' . implode(' ', $sc['warnings']) : '')]);
        auto_sheet_log($DIR, $s, $slug, $kw, $pub ? 'Đăng bài' : 'Lưu nháp', 'Thành công', 'Điểm ' . $sc['score'] . ', ' . $sc['words'] . ' từ');
        if ($pub && $s['backlink']) {   // bài cũ liên quan trỏ liên kết sang bài mới
            try { $bl = audit_link_new_post($DIR, $slug, 4); if ($bl) auto_log($DIR, ['keyword' => $kw, 'result' => 'liên kết nội bộ', 'msg' => 'Đã gắn liên kết từ ' . count($bl) . ' bài cũ: ' . implode(', ', array_column($bl, 0))]); }
            catch (Throwable $e) { auto_log($DIR, ['keyword' => $kw, 'result' => 'cảnh báo', 'msg' => 'Chưa gắn được liên kết nội bộ: ' . $e->getMessage()]); }
        }
        return [true, ($pub ? 'Đã đăng: ' : 'Đã lưu nháp: ') . $in['title'] . ' (điểm ' . $sc['score'] . ')' . ($sc['warnings'] ? ' – ' . implode(' ', $sc['warnings']) : '')];
    } catch (Throwable $e) {
        $final = $attempts >= (int)$s['max_attempts'];
        try { auto_row_set($DIR, $s, $r['row'], $final ? 'Lỗi' : 'Sẵn sàng', ['', '', '', '', '', '', $now, mb_substr($e->getMessage(), 0, 300), $attempts]); } catch (Throwable $e2) { /* không ghi được Sheet */ }
        auto_log($DIR, ['keyword' => $kw, 'result' => 'lỗi', 'msg' => $e->getMessage()]);
        auto_sheet_log($DIR, $s, '', $kw, 'Viết bài', 'Lỗi', $e->getMessage());
        return [false, 'Lỗi: ' . $e->getMessage()];
    }
}

/* ---------- AI tự điền các cột còn trống ---------- */
const AUTO_PLAN_FIELDS = ['category' => 'Chuyên mục', 'form' => 'Dạng bài', 'intent' => 'Ý định tìm kiếm', 'prio' => 'Ưu tiên', 'sub' => 'Từ khóa phụ', 'note' => 'Ghi chú / góc viết'];
const AUTO_FORMS = ['Hướng dẫn', 'So sánh', 'Danh sách', 'Hỏi đáp', 'Tin tức', 'Giải thích khái niệm', 'Bảng số liệu', 'Phụ kiện / độ xe'];
const AUTO_INTENTS = ['Tìm hiểu thông tin', 'So sánh đánh giá', 'Mua hàng', 'Tìm địa điểm'];
const AUTO_PRIOS = ['Cao', 'Trung bình', 'Thấp'];

/** Các ô AI được phép điền của một dòng: những ô đang để trống. Ô "Ghi chú / góc viết" do biên tập tự điền, chỉ đưa vào khi bật auto_plan_note. */
function auto_plan_blank(array $r, bool $withNote = false): array {
    return array_values(array_filter(array_keys(AUTO_PLAN_FIELDS), fn($f) => ($withNote || $f !== 'note') && trim((string)($r[$f] ?? '')) === ''));
}
function auto_plan_key(array $r): string { return $r['row'] . '|' . blog_slugify((string)$r['keyword']); }
function auto_plan_state(string $DIR): array { return auto_json(auto_dir($DIR) . '/plan.json', []); }
/** Một dòng chỉ được thử lập kế hoạch tối đa 3 lần, cách nhau 10 phút; hết lượt thì cứ viết bài với các ô đang có. */
function auto_plan_can_try(string $DIR, array $r): bool {
    $e = auto_plan_state($DIR)[auto_plan_key($r)] ?? null;
    return !$e || ((int)$e['n'] < 3 && time() - (int)$e['t'] >= 180);
}
/** Số lần lập kế hoạch đã lỗi của một dòng (0 = chưa lỗi hoặc đã thành công). */
function auto_plan_fails(string $DIR, array $r): int { return (int)(auto_plan_state($DIR)[auto_plan_key($r)]['n'] ?? 0);
}
function auto_plan_record(string $DIR, array $r, bool $ok): void {
    $a = auto_plan_state($DIR); $k = auto_plan_key($r);
    if ($ok) unset($a[$k]); else $a[$k] = ['t' => time(), 'n' => (int)($a[$k]['n'] ?? 0) + 1];
    auto_save_json(auto_dir($DIR) . '/plan.json', array_slice($a, -200, null, true));
}
function auto_plan_prompt(array $r, array $blank, array $posts, array $cats, string $facts, ?array $ref = null, array $imgNames = []): string {
    $given = '';
    foreach (AUTO_PLAN_FIELDS as $f => $label) { $v = trim((string)($r[$f] ?? '')); if ($v !== '') $given .= "- $label: $v\n"; }
    $titles = implode("\n", array_map(fn($p) => '- ' . $p['title'], array_slice($posts, 0, 40)));
    $type = (string)($r['type'] ?? auto_type_for((string)($r['category'] ?? ''), (string)($r['form'] ?? ''), (string)$r['keyword']));
    $bp = AUTO_BLUEPRINTS[$type] ?? AUTO_BLUEPRINTS['huong-dan'];
    $note = in_array('note', $blank, true);
    // ảnh: có thư mục ảnh thật thì dàn ý ghi đúng tên tệp (hệ thống đặt ảnh theo dàn ý); chưa có ảnh thì chỉ gợi ý tên để biên tập đặt
    $imgRule = $imgNames
        ? "dòng \"Ảnh bìa: <một tên tệp trong danh sách ảnh>\"; "
        : "dòng \"Ảnh bìa: 0-bia-<tên gợi ý không dấu>.jpg\"; ";
    $imgSec = $imgNames
        ? "một dòng \"Ảnh: <tên tệp>\" lấy ĐÚNG NGUYÊN tên trong DANH SÁCH ẢNH bên dưới, chọn ảnh hợp nội dung mục (mỗi ảnh dùng đúng một lần trong cả dàn ý, mục không có ảnh hợp thì bỏ dòng Ảnh, có thể ghi 2 tên cách nhau bằng dấu ; nếu mục cần 2 ảnh; hệ thống sẽ đặt ảnh vào đúng mục theo dòng này)"
        : "một dòng \"Ảnh: <số thứ tự mục>-<tên gợi ý không dấu>.jpg\" (số = thứ tự của mục H2 trong dàn ý, mục đầu tiên là 1; đây chỉ là tên tệp gợi ý để biên tập đặt tên ảnh, không phải nội dung bài)";
    return "KẾ HOẠCH BÀI: Bạn là biên tập SEO của ChungAuto (VinFast VF Wild). Hãy lập kế hoạch cho MỘT bài blog với từ khóa chính: \"{$r['keyword']}\".\n"
        . ($given !== '' ? "Biên tập đã điền sẵn (giữ nguyên và bám theo):\n$given" : '')
        . 'Chỉ cần điền các mục còn trống: ' . implode(', ', array_map(fn($f) => AUTO_PLAN_FIELDS[$f], $blank)) . ".\n"
        . ($note ? "KHUNG BÀI CHO LOẠI \"" . (AUTO_TYPES[$type] ?? $type) . "\" (dàn ý bám khung này, chỉ giữ các mục có dữ kiện): "
            . ($type === 'phu-kien' && auto_kw_other_car((string)$r['keyword']) ? str_replace([' (dựa vào đặc điểm thật của xe: bán tải điện, thùng hàng, đi phố và đi dã ngoại)', 'VF Wild'], ['', 'xe trong từ khóa'], $bp['outline']) . " Bài này cho xe trong từ khóa, không gắn dữ kiện VF Wild." : $bp['outline']) . "\n" : '')
        . ($note && $ref && $ref['outline'] ? "CÁC Ý MÀ BÀI CÙNG CHỦ ĐỀ trên chungauto.vn ĐÃ PHỦ (chỉ để không sót ý; TỰ ĐẶT tên mục H1/H2 theo cách nói riêng của vfwild.vn, KHÔNG chép nguyên văn các tiêu đề này, được đổi thứ tự, gộp, tách hoặc thêm mục; số liệu của bài này nằm ở mục \"Bài tham khảo cùng chủ đề\" trong bộ dữ kiện):\n" . implode("\n", array_map(fn($l) => '  ' . $l, $ref['outline'])) . "\n" : '')
        . ($note && $imgNames ? "DANH SÁCH ẢNH biên tập đã chuẩn bị (tên tệp, theo thứ tự):\n" . implode("\n", array_map(fn($n) => '- ' . $n, $imgNames)) . "\n" : '')
        . "Số liệu ưu tiên lấy từ bộ dữ kiện của site và mục \"Dữ kiện tra cứu trên web\" (nguồn chính thức); số liệu của bài tham khảo chungauto.vn chỉ dùng cho giá, sản phẩm, dịch vụ của ChungAuto.\n"
        . "BỘ DỮ KIỆN của website (chỉ chọn mục/ý mà bộ dữ kiện này có thông tin):\n" . mb_substr($facts, 0, 30000, 'UTF-8') . "\n"
        . ($titles !== '' ? "Các bài đã có trên blog (chỉ để chọn góc viết khác và liên kết ý, không bắt buộc):\n$titles\n" : '')
        . ($note && !empty($r['related']) ? auto_related_prompt($r['related']) : '')
        . "\nTrả về JSON đúng khuôn: {\"category\": một trong [" . implode(', ', $cats) . "], \"form\": một trong [" . implode(', ', AUTO_FORMS) . "], \"intent\": một trong [" . implode(', ', AUTO_INTENTS) . "], \"priority\": một trong [" . implode(', ', AUTO_PRIOS) . "], "
        . "\"sub_keywords\": mảng 4–8 cụm từ khóa phụ người mua thật sự gõ tìm (viết thường trừ tên riêng viết hoa đúng như Honda City, VF Wild, VinFast; tiếng Việt CÓ DẤU đầy đủ và đúng chính tả, 2–6 từ, cùng ý định với từ khóa chính, không trùng từ khóa chính)" . ($note ? (", "
        . "\"angle\": chuỗi nhiều dòng là DÀN Ý CHI TIẾT cho ô Ghi chú (trong chuỗi này KHÔNG dùng dấu ngoặc kép, cần trích dẫn thì dùng ngoặc đơn; xuống dòng bằng \\n), đúng cú pháp sau (mỗi dòng một ý):dòng \"H1: tiêu đề bài (25–60 ký tự, chứa từ khóa chính)\"; dòng \"Đối tượng: người đọc là ai và đang cần quyết định gì\"; dòng \"Góc khác biệt: bài này hơn các bài khác ở điểm nào\"; dòng bắt đầu bằng \"Mở bài:\" rồi 2–3 câu chỉ dẫn nêu sẵn câu trả lời ngắn cho từ khóa chính bằng dữ kiện có thật (viết thẳng nội dung, không lặp lại câu hướng dẫn này); giọng trung tính, không dùng từ quảng cáo như độc quyền, hấp dẫn, tuyệt vời; " . $imgRule
        . "rồi các mục nội dung, mỗi mục gồm dòng \"H2: Tên mục\" (tiếng Việt có dấu, không chép chữ trong tên tệp ảnh như infographic, banner), một dòng \"Trình bày: …\" (chọn một: \"đoạn ngắn + gạch đầu dòng có nhãn in đậm\", \"bảng\", \"các bước đánh số\", \"đoạn ngắn\"; ít nhất 2 mục dùng gạch đầu dòng hoặc các bước), 3–5 dòng \"- ý cần triển khai\" CỤ THỂ (nói rõ dùng dữ kiện nào trong bộ dữ kiện, ghi luôn con số hoặc quy định cần dùng; nên có bảng hoặc ví dụ tính khi dữ kiện cho phép; tránh ý chung chung kiểu \"giới thiệu…\", \"nêu lợi ích…\"), một dòng \"Từ khóa: cụm 1; cụm 2\" (chọn từ sub_keywords) và " . $imgSec . "; có thể thêm dòng \"- H3: Tên tiểu mục\" khi cần. "
        . "Gồm 5–7 mục nội dung (ít hơn nếu bộ dữ kiện ít thông tin, nhưng mỗi mục phải sâu), rồi \"H2: Câu hỏi thường gặp\" với 4–5 dòng \"- Câu hỏi người mua thật sự gõ? → 2–3 ý trả lời cụ thể lấy từ dữ kiện (con số, thời gian, điều kiện, lưu ý)\", cuối cùng \"H2: Kết luận\" với 2–3 dòng gạch đầu dòng (tóm tắt ý chính và lời mời hành động hợp chủ đề: bài về xe thì xem giá lăn bánh, tính trả góp, đăng ký lái thử; bài phụ kiện hoặc độ xe thì lắp ở showroom ChungAuto nào và nhận báo giá qua hotline hoặc Zalo). Chỉ nêu các ý/chủ đề mà bộ dữ kiện có thông tin; TUYỆT ĐỐI không viết con số, giá, thông số, địa chỉ hay điều kiện chưa có trong bộ dữ kiện (dòng ghi chú này sẽ được coi là dữ kiện biên tập, nên không được bịa)") : '') . "}.\n"
        . "Chỉ trả JSON, không giải thích thêm.\n";
}
/**
 * Cứu kết quả lập kế hoạch khi JSON hỏng: lấy từng ô bằng biểu thức chính quy (ô đơn giản), mảng từ khóa phụ,
 * và dàn ý "angle" từ sau "angle": " tới dấu " cuối cùng trước } (chịu được dấu ngoặc kép chưa thoát bên trong). null nếu không cứu được gì.
 */
function auto_plan_salvage(string $text): ?array {
    $out = [];
    foreach (['category', 'form', 'intent', 'priority'] as $k) { if (preg_match('/"' . $k . '"\s*:\s*"([^"\n]{1,80})"/u', $text, $m)) $out[$k] = $m[1]; }
    if (preg_match('/"sub_keywords"\s*:\s*\[(.*?)\]/su', $text, $m) && preg_match_all('/"([^"\n]{2,80})"/u', $m[1], $mm)) $out['sub_keywords'] = $mm[1];
    if (preg_match('/"angle"\s*:\s*"(.*)"\s*\}?\s*(```)?\s*$/su', trim($text), $m)) {
        $a = str_replace(['\\n', '\\"', '\\t', '\\\\'], ["\n", '"', ' ', '\\'], $m[1]);
        if (mb_strlen($a, 'UTF-8') > 50) $out['angle'] = $a;
    }
    return count($out) >= 2 ? $out : null;
}
/** Chuẩn hóa kết quả AI: chỉ nhận giá trị nằm trong các danh sách của Sheet; trả về trường → giá trị (bỏ trường rỗng). */
function auto_plan_validate(array $j, array $cats, string $kw): array {
    $pick = function ($v, array $list): string {
        $v = trim((string)$v);
        if ($v === '') return '';
        foreach ($list as $x) { if (mb_strtolower($x, 'UTF-8') === mb_strtolower($v, 'UTF-8')) return $x; }
        foreach ($list as $x) { if (mb_stripos($x, $v, 0, 'UTF-8') !== false || mb_stripos($v, $x, 0, 'UTF-8') !== false) return $x; }
        return '';
    };
    $out = [];
    $out['category'] = $pick($j['category'] ?? '', $cats);
    $out['form'] = $pick($j['form'] ?? '', AUTO_FORMS);
    $out['intent'] = $pick($j['intent'] ?? '', AUTO_INTENTS);
    $out['prio'] = $pick($j['priority'] ?? ($j['prio'] ?? ''), AUTO_PRIOS) ?: 'Trung bình';
    $raw = $j['sub_keywords'] ?? ($j['sub'] ?? []);
    if (is_string($raw)) $raw = preg_split('/[;\n,]+/u', $raw) ?: [];
    $subs = []; $kwn = mb_strtolower(trim($kw), 'UTF-8');
    foreach ((array)$raw as $x) {
        $x = trim((string)preg_replace('/\s+/u', ' ', str_replace([';', "\n", "\r"], ' ', (string)$x)));
        $xl = mb_strtolower($x, 'UTF-8');
        if ($x === '' || mb_strlen($x, 'UTF-8') > 60 || $xl === $kwn || in_array($xl, array_map(fn($y) => mb_strtolower($y, 'UTF-8'), $subs), true)) continue;
        if (!preg_match('/[^\x00-\x7F]/', $x) && preg_match('/[^\x00-\x7F]/', $kw) && count(array_diff(explode(' ', $xl), ['vf', 'wild', 'vinfast', 'ranger', 'suv', 'ev', 'o2o', 'reev'])) >= 2) continue;   // từ khóa phụ không dấu (AI viết "quy dinh moi nhat") làm bài sai chính tả: bỏ
        $subs[] = auto_fix_names($x, $kw);   // "ghế điện honda city" → "ghế điện Honda City"
        if (count($subs) >= 8) break;
    }
    $out['sub'] = implode('; ', $subs);
    $angle = is_array($j['angle'] ?? null) ? implode("\n", array_map('strval', $j['angle'])) : (string)($j['angle'] ?? ($j['note'] ?? ''));
    $angle = trim(str_replace(["\r\n", "\r"], "\n", preg_replace('/[*`#]+/u', '', $angle) ?? $angle));   // giữ dấu _ vì tên tệp ảnh hay có (img_1234.jpg)
    $out['note'] = mb_substr(preg_replace("/\n{3,}/", "\n\n", $angle) ?? $angle, 0, 5000, 'UTF-8');
    return array_filter($out, fn($v) => $v !== '');
}
/** Ghi các ô vào Sheet, nhưng chỉ ô đang trống TẠI THỜI ĐIỂM GHI (không đè thứ biên tập vừa gõ). Trả về các trường đã ghi. */
function auto_cells_set(string $DIR, array $s, int $row, array $vals): array {
    $id = auto_sheet_id($s);
    if (!$id) return $vals;
    $lay = auto_sheet_layout($DIR, $s);
    $cur = gs_read($DIR, $id, "'" . AUTO_TAB . "'!A$row:AD$row")[0] ?? [];
    $data = []; $done = [];
    foreach ($vals as $f => $v) {
        if (!isset($lay[$f])) continue;
        if (trim((string)($cur[$lay[$f]] ?? '')) !== '') continue;
        $data[] = ['range' => "'" . AUTO_TAB . "'!" . gs_col($lay[$f]) . $row, 'values' => [[$v]]];
        $done[$f] = $v;
    }
    if ($data) gs_batch($DIR, $id, $data);
    return $done;
}
/** Nhờ AI điền các ô còn trống của một dòng, ghi vào Sheet và trả về dòng đã bổ sung. Lỗi thì ném ngoại lệ. */
function auto_plan_row(string $DIR, array $s, array $r, array $posts): array {
    $blank = auto_plan_blank($r, !empty($s['auto_plan_note']));
    if (!$blank) return $r;
    $p = (string)$s['provider'];
    [$facts, $ref, $res] = auto_row_facts($DIR, $s, $r);
    $r['related'] = auto_related_existing($posts, (string)$r['keyword']);   // dàn ý chọn góc khác các bài cùng chủ đề đã có
    $imgNames = [];   // tên tệp ảnh thật (chỉ đọc danh sách, chưa tải ảnh) để dàn ý ghi đúng ảnh nào vào mục nào
    if (in_array('note', $blank, true) && trim((string)($r['images_cell'] ?? '')) !== '') {
        $ie = [];
        try { foreach (img_sources($DIR, (string)$r['images_cell'], $ie, (int)($s['max_images'] ?? IMG_DEFAULT_MAX)) as $src) { if ((string)$src['name'] !== '') $imgNames[] = (string)$src['name']; } } catch (Throwable $e) { /* không đọc được ảnh: dàn ý vẫn lập, ảnh đặt theo tên tệp như cũ */ }
    }
    $prompt = auto_plan_prompt($r, $blank, $posts, blog_cats($DIR), $facts, $ref, $imgNames);
    $j = null;
    for ($try = 1; $try <= 2 && !is_array($j); $try++) {   // AI thỉnh thoảng trả JSON hỏng (dấu ngoặc kép trong dàn ý, bị cắt…): đọc khoan dung, cứu từng ô, hỏng hẳn thì hỏi lại một lần
        $text = auto_ai_call($DIR, $p, auto_model($s, $p), $prompt, true, 300);
        $j = auto_json_decode($text);
        if (!is_array($j)) $j = auto_plan_salvage($text);
    }
    if (!is_array($j)) {
        @file_put_contents(auto_dir($DIR) . '/plan-bad.txt', date('c') . ' ' . $r['keyword'] . "\n" . mb_substr((string)$text, 0, 20000, 'UTF-8'));   // giữ lại để dò lỗi
        throw new RuntimeException('AI trả về sai khuôn JSON khi lập kế hoạch (đã thử 2 lần).');
    }
    $vals = array_intersect_key(auto_plan_validate($j, blog_cats($DIR), (string)$r['keyword']), array_flip($blank));
    if (!$vals) throw new RuntimeException('AI không đưa được nội dung hợp lệ cho các ô còn trống.');
    $dups = isset($vals['note']) ? auto_outline_dups($vals['note'], $posts, $ref, $res ? auto_research_pages($DIR, $res) : []) : [];
    if ($dups) {   // dàn ý giống bài đã có / bài chungauto.vn / trang nguồn: lập lại một lần với lời dặn đổi các mục đó (bài viết bám dàn ý nên phải sửa từ đây)
        try { $t2 = auto_ai_call($DIR, $p, auto_model($s, $p), $prompt . "\nDÀN Ý LẦN TRƯỚC BỊ TRÙNG DÀN Ý CỦA NGUỒN KHÁC (Google coi là nội dung sao chép):\n" . implode("\n", array_map(fn($d) => '- ' . $d, $dups)) . "\nHãy lập dàn ý MỚI theo góc khác: đặt lại tên các mục này theo cách nói riêng, đổi thứ tự, gộp hoặc tách mục, thêm mục mà các nguồn kia chưa có.\n", true, 300); $j2 = auto_json_decode($t2) ?? auto_plan_salvage($t2); }
        catch (Throwable $e) { $j2 = null; }   // lập lại lỗi: giữ dàn ý đầu
        $v2 =is_array($j2) ? array_intersect_key(auto_plan_validate($j2, blog_cats($DIR), (string)$r['keyword']), array_flip($blank)) : [];
        if (isset($v2['note'])) $vals = $v2 + $vals;
        auto_log($DIR, ['keyword' => $r['keyword'], 'result' => 'cảnh báo', 'msg' => 'Dàn ý đầu tiên trùng ' . implode(' | ', $dups) . (isset($v2['note']) ? ' – đã lập lại dàn ý theo góc khác.' : ' – lập lại không được, giữ dàn ý cũ.')]);
    }
    $written = auto_cells_set($DIR, $s, (int)$r['row'], $vals);
    foreach ($written as $f => $v) $r[$f] = $v;
    $r['type'] = auto_type_for((string)$r['category'], (string)$r['form'], (string)$r['keyword']);
    $names = implode(', ', array_map(fn($f) => AUTO_PLAN_FIELDS[$f], array_keys($written)));
    auto_log($DIR, ['keyword' => $r['keyword'], 'result' => 'lập kế hoạch', 'msg' => 'AI đã điền: ' . ($names !== '' ? $names : 'không có ô nào (biên tập vừa điền)')]);
    auto_sheet_log($DIR, $s, '', (string)$r['keyword'], 'Lập kế hoạch', 'Thành công', 'Đã điền: ' . $names);
    return $r;
}
/** Lập kế hoạch an toàn cho một dòng: có giới hạn số lần thử, lỗi không chặn việc viết bài. */
function auto_plan_try(string $DIR, array $s, array $r, array $posts): array {
    if (empty($s['auto_plan']) || $s['provider'] === 'mock' || $r['status'] !== 'Sẵn sàng' || !auto_plan_blank($r, !empty($s['auto_plan_note'])) || !auto_plan_can_try($DIR, $r)) return $r;
    // Chủ đề bộ dữ kiện không nói gì: không lập kế hoạch (nếu AI điền Ghi chú thì kiểm tra thiếu dữ kiện sẽ bị lách qua, bài thành chữ độn)
    if (auto_facts_gap((string)$r['keyword'], $r, auto_row_facts($DIR, $s, $r)[0]) !== null) return $r;
    try { $r = auto_plan_row($DIR, $s, $r, $posts); auto_plan_record($DIR, $r, true); }
    catch (Throwable $e) {
        auto_plan_record($DIR, $r, false);
        auto_log($DIR, ['keyword' => $r['keyword'], 'result' => 'cảnh báo', 'msg' => 'Chưa lập được kế hoạch cho các ô trống: ' . $e->getMessage()]);
    }
    return $r;
}

/* ---------- Lịch chạy ---------- */
function auto_run(string $DIR, bool $manual = false): string {
    @set_time_limit(600);
    $lk = fopen(auto_dir($DIR) . '/.runlock', 'c');
    if (!flock($lk, LOCK_EX | LOCK_NB)) { fclose($lk); return 'Đang có một lần chạy khác, bỏ qua.'; }
    try {
        $s = auto_settings($DIR); $st = auto_state($DIR); $now = time();
        gsc_refresh_if_due($DIR, $s);   // dữ liệu Search Console làm mới mỗi ngày (nếu đã kết nối)
        review_weekly_if_due($DIR, $s);   // mỗi tuần AI đề xuất quy tắc từ các bài đã sửa
        if (!$manual) {
            if (!$s['enabled']) return 'Bỏ qua: công tắc đang tắt.';   // không còn giới hạn số bài mỗi ngày: mỗi lượt chạy (mỗi phút) xử lý một dòng đến giờ
        }
        try { $rows = auto_sheet_rows($DIR, $s); }
        catch (Throwable $e) { $msg = 'Không đọc được Sheet: ' . $e->getMessage(); $st['last_run'] = $now; $st['last_msg'] = $msg; auto_save_json(auto_dir($DIR) . '/state.json', $st); return $msg; }
        $row = auto_next_row($rows, $s, $now, $manual);
        if ($row) {   // dòng sắp viết: AI điền các ô còn trống (kể cả dàn ý) trước khi viết
            $row = auto_plan_try($DIR, $s, $row, blog_all($DIR));
            $fails = auto_plan_fails($DIR, $row);
            if (!empty($s['auto_plan']) && !empty($s['auto_plan_note']) && $s['provider'] !== 'mock' && trim((string)$row['note']) === '' && $fails > 0 && $fails < 3) {
                // dàn ý trước rồi mới viết: lập dàn ý lỗi thì CHƯA viết, chờ lượt sau thử lại (sau 3 lần lỗi mới viết không dàn ý)
                $msg = 'Chưa lập được dàn ý cho "' . $row['keyword'] . '" (lỗi lần ' . $fails . '/3), sẽ thử lại sau khoảng 3 phút rồi mới viết bài.';
                try { auto_row_set($DIR, $s, (int)$row['row'], null, ['', '', '', '', '', '', '', $msg, $row['attempts']]); } catch (Throwable $e) { /* không ghi được Sheet */ }   // để trống "Cập nhật lần cuối": đây không phải lỗi viết bài nên không bị chờ 30 phút
                $st['last_run'] = $now; $st['last_msg'] = $msg; auto_save_json(auto_dir($DIR) . '/state.json', $st);
                return $msg;
            }
            if ($fails >= 3 && trim((string)$row['note']) === '') auto_log($DIR, ['keyword' => $row['keyword'], 'result' => 'cảnh báo', 'msg' => 'Lập dàn ý lỗi 3 lần, viết bài không có dàn ý.']);
        }
        else {   // chưa có dòng nào đến giờ: lập trước kế hoạch cho tối đa 2 dòng "Sẵn sàng" để biên tập xem, sửa và đặt tên ảnh theo dàn ý
            $pre = 0; $all = blog_all($DIR);
            foreach ($rows as $pr) { if ($pre >= 2) break; if ($pr['status'] === 'Sẵn sàng' && auto_plan_blank($pr, !empty($s['auto_plan_note'])) && auto_plan_can_try($DIR, $pr)) { auto_plan_try($DIR, $s, $pr, $all); $pre++; } }
        }
        if (!$row) { $st['last_run'] = $now; $st['last_msg'] = 'Chưa có bài nào đến giờ.'; auto_save_json(auto_dir($DIR) . '/state.json', $st); return 'Chưa có bài nào đến giờ.'; }
        [$ok, $msg] = auto_process($DIR, $row, $s);
        $st = auto_state($DIR);
        $st['last_run'] = $now; $st['last_msg'] = $msg;
        if ($ok && strpos($msg, 'Bỏ qua') !== 0) { $st['last_ok'] = $now; $st['day_count']++; } elseif (!$ok) $st['last_fail'] = $now;
        auto_save_json(auto_dir($DIR) . '/state.json', $st);
        return $msg;
    } finally { flock($lk, LOCK_UN); fclose($lk); }
}

/* ---------- Thao tác từ trang quản trị ---------- */
function auto_flash(): array {
    return [
        'auto_on' => 'Đã BẬT tự động viết bài.', 'auto_off' => 'Đã TẮT tự động viết bài.',
        'auto_settings' => 'Đã lưu cài đặt.', 'auto_facts' => 'Đã lưu bộ dữ kiện.',
        'auto_keyok' => 'Kết nối thành công – khóa và model dùng được.', 'auto_ran' => 'Đã chạy một lượt – xem kết quả ở khung trạng thái.',
    ];
}
function auto_post(string $act, string $DIR): array {
    $back = '/quan-tri/?p=auto';
    switch ($act) {
        case 'blog_auto_toggle': {
            $s = auto_settings($DIR); $s['enabled'] = ($_POST['enabled'] ?? '') === '1';
            auto_save_json(auto_dir($DIR) . '/settings.json', $s);
            return ['to' => "$back&ok=" . ($s['enabled'] ? 'auto_on' : 'auto_off')];
        }
        case 'blog_auto_settings': {
            $s = auto_settings($DIR);
            $prov = (string)($_POST['provider'] ?? 'mock');
            if (!isset(AUTO_PROVIDERS[$prov])) return ['error' => 'Nhà cung cấp AI không hợp lệ.'];
            $sheet = trim((string)($_POST['sheet_url'] ?? ''));
            if ($sheet !== '' && gs_id_from_url($sheet) === null) return ['error' => 'Link Google Sheet không đúng (phải có dạng https://docs.google.com/spreadsheets/d/…).'];
            $gscSite = trim((string)($_POST['gsc_site'] ?? ''));
            if ($gscSite !== '' && !gsc_site_valid($gscSite)) return ['error' => 'Site Search Console không đúng: dùng dạng sc-domain:vfwild.vn hoặc https://vfwild.vn/'];
            $models = (array)($s['models'] ?? []);
            foreach (array_keys(AUTO_PROVIDERS) as $p) {
                if ($p === 'mock') continue;
                $m = trim((string)($_POST['model'][$p] ?? ($models[$p] ?? '')));
                if ($m === '') unset($models[$p]); elseif (auto_model_valid($m)) $models[$p] = $m; else return ['error' => 'Tên model của ' . AUTO_PROVIDERS[$p]['short'] . ' không hợp lệ.'];
            }
            $s = [
                'provider' => $prov, 'models' => $models,
                'gsc_site' => $gscSite, 'backlink' => ($_POST['backlink'] ?? '') === '1', 'auto_plan' => ($_POST['auto_plan'] ?? '') === '1', 'auto_plan_note' => ($_POST['auto_plan_note'] ?? '') === '1', 'img_vision_always' => ($_POST['img_vision_always'] ?? '') === '1', 'ref_enabled' => ($_POST['ref_enabled'] ?? '') === '1', 'web_research' => ($_POST['web_research'] ?? '') === '1','auto_cover' => ($_POST['auto_cover'] ?? '') === '1', 'review_weekly' => ($_POST['review_weekly'] ?? '') === '1', 'max_images' => max(1, min(IMG_HARD_MAX, (int)($_POST['max_images'] ?? 6))),
                'sheet_url' => $sheet, 'default_hour' => max(0, min(23, (int)($_POST['default_hour'] ?? 9))),
                'mode' => ($_POST['draft_only'] ?? '') === '1' ? 'draft' : 'publish',
                'min_words' => max(300, min(10000, (int)($_POST['min_words'] ?? $s['min_words']))), 'min_score' => max(50, min(100, (int)($_POST['min_score'] ?? $s['min_score']))),
                'max_attempts' => max(1, min(5, (int)($_POST['max_attempts'] ?? $s['max_attempts']))),
                'forbidden' => mb_substr(trim((string)($_POST['forbidden'] ?? $s['forbidden'])), 0, 2000), 'tone' => mb_substr(trim((string)($_POST['tone'] ?? $s['tone'])), 0, 4000),
            ] + $s;
            auto_save_json(auto_dir($DIR) . '/settings.json', $s);
            foreach (AUTO_PROVIDERS as $p => $info) {
                if ($p === 'mock') continue;
                $newKey = trim((string)($_POST['key'][$p] ?? ''));
                if (($_POST['keyclear'][$p] ?? '') === '1') auto_secret_set($DIR, $info['key'], '');
                elseif ($newKey !== '') {
                    if (!auto_key_valid($newKey)) return ['error' => 'Khóa API của ' . $info['short'] . ' trông không đúng (chỉ gồm chữ, số, dấu chấm, gạch, gạch dưới). Các cài đặt khác đã được lưu.'];
                    auto_secret_set($DIR, $info['key'], $newKey);
                }
            }
            $do = (string)($_POST['do'] ?? '');
            if ($do === 'test' || $do === 'models') {
                if ($prov === 'mock') return ['error' => 'Hãy chọn một nhà cung cấp AI thật (không phải “Thử nghiệm”) rồi bấm lại.'];
                try {
                    if ($do === 'test') {
                        auto_ai_call($DIR, $prov, auto_model($s, $prov), 'Chỉ trả lời đúng một từ: OK', false, 60);
                        return ['to' => "$back&ok=auto_keyok&b=" . urlencode(AUTO_PROVIDERS[$prov]['short'])];
                    }
                    $n = count(auto_models_fetch($DIR, $prov));
                    return ['to' => "$back&ok=auto_models&n=$n&b=" . urlencode(AUTO_PROVIDERS[$prov]['short'])];
                } catch (Throwable $e) { return ['error' => ($do === 'test' ? 'Thử kết nối thất bại: ' : 'Không tải được danh sách model: ') . $e->getMessage()]; }
            }
            return ['to' => "$back&ok=auto_settings"];
        }
        case 'blog_auto_facts': {
            $f = str_replace("\r\n", "\n", (string)($_POST['facts'] ?? ''));
            if (strlen($f) > 60000) return ['error' => 'Bộ dữ kiện quá dài (tối đa 60 KB).'];
            if (!auto_facts_sections($f)) return ['error' => 'Bộ dữ kiện cần có ít nhất một mục "## Tên mục" kèm các dòng bắt đầu bằng "- ".'];
            blog_atomic(auto_dir($DIR) . '/facts.md', $f);
            return ['to' => "$back&ok=auto_facts"];
        }
        case 'blog_auto_run': {
            auto_run($DIR, true);
            return ['to' => "$back&ok=auto_ran"];
        }
    }
    return ['error' => 'Thao tác không hợp lệ.'];
}

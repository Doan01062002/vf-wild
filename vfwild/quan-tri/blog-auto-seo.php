<?php
/**
 * Gợi ý SEO cho bộ máy tự động viết bài, mượn ý tưởng từ dự án SEO Content OS (chungauto.vn) và chỉnh cho vfwild:
 *  - mẫu cấu trúc bài theo từng loại (giá, so sánh, thủ tục, công nghệ, chi phí, hướng dẫn);
 *  - thang chấm 100 điểm theo loại bài (tiêu đề, mô tả, từ khóa chính/phụ, đề mục, bảng, FAQ, liên kết nội bộ…);
 *  - chọn liên kết nội bộ theo độ liên quan và tự bổ sung "Xem thêm" khi AI quên chèn.
 */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }
require_once __DIR__ . '/blog-auto-acc.php';   // kiến thức phụ kiện học từ chungauto.vn
require_once __DIR__ . '/blog-auto-ref.php';   // bài tham khảo cùng chủ đề trên chungauto.vn
require_once __DIR__ . '/blog-auto-research.php';   // tra cứu dữ kiện trên web (nguồn chính thức, không lấy chungauto.vn)

/** len = hệ số nhân với "số từ tối thiểu" trong cài đặt; h2/h3 = số đề mục tối thiểu; facets = các khía cạnh chuyên môn cần chạm tới. */
const AUTO_BLUEPRINTS = [
    'gia' => ['len' => 1.0, 'h2' => 4, 'h3' => 0, 'min_facets' => 3, 'table' => 'bảng giá theo màu và giá sau ưu đãi',
        'open' => "Trả lời thẳng câu hỏi ngay đoạn đầu: giá niêm yết và giá sau ưu đãi (nếu có trong dữ kiện)",
        'outline' => "Trả lời nhanh ở đoạn đầu (giá niêm yết, giá sau ưu đãi); bảng giá theo màu hoặc phiên bản; ưu đãi đang có và cách tính giá sau ưu đãi (có một ví dụ tính cụ thể); giá lăn bánh gồm những khoản nào (khoản nào có số trong dữ kiện mới ghi số); nên chốt ở kênh nào và cần chuẩn bị gì; câu hỏi thường gặp; kết bài mời xem trang giá lăn bánh và trả góp.",
        'facets' => [['giá niêm yết', 'giá bán', 'triệu'], ['ưu đãi', 'giảm', 'o2o', 'khuyến mãi'], ['lăn bánh', 'phí', 'biển số', 'trước bạ'], ['tư vấn', 'chungauto', 'showroom', 'hotline']]],
    'so-sanh' => ['len' => 1.3, 'h2' => 5, 'h3' => 0, 'min_facets' => 3, 'table' => 'bảng so sánh thông số và giá',
        'open' => "Nêu luôn kết luận: xe nào hơn ở điểm nào và nhóm khách hàng nào hợp xe nào",
        'outline' => "Kết luận nhanh ngay đầu bài (ai hợp xe nào); bảng so sánh chỉ gồm tiêu chí CÓ số liệu cho cả hai xe; các nhóm tiêu chí (vận hành, không gian, chi phí sử dụng), mỗi nhóm kết luận rõ xe nào hơn và vì sao; ưu và nhược điểm của mỗi bên; chọn xe nào theo nhu cầu cụ thể (đi phố, đi tỉnh, gia đình); câu hỏi thường gặp; kết luận.",
        'facets' => [['thông số', 'kích thước', 'công suất', 'gầm'], ['ưu điểm', 'nhược điểm', 'hạn chế', 'lợi thế'], ['giá', 'chi phí', 'triệu'], ['kết luận', 'nên chọn', 'phù hợp']]],
    'thu-tuc' => ['len' => 1.2, 'h2' => 4, 'h3' => 0, 'min_facets' => 3, 'table' => 'bảng các khoản chi phí hoặc các bước thủ tục',
        'open' => "Nói luôn làm được không, cần những gì, tổng chi phí hoặc thời gian ở mức tổng quan",
        'outline' => "Trả lời nhanh: điều kiện, thời gian, chi phí tổng quan; giấy tờ cần có (danh sách gạch đầu dòng dạng checklist); các bước theo thứ tự (danh sách đánh số, mỗi bước 1–2 câu: làm gì, ở đâu, lưu ý gì); chi phí (bảng, chỉ ghi khoản có số liệu); lỗi hay gặp và cách tránh; câu hỏi thường gặp; kết bài mời liên hệ tư vấn.",
        'facets' => [['bước', 'hồ sơ', 'giấy tờ', 'thủ tục'], ['chi phí', 'lệ phí', 'trả trước', 'phí'], ['lưu ý', 'kinh nghiệm'], ['tư vấn', 'showroom', 'o2o', 'đặt cọc']]],
    'cong-nghe' => ['len' => 1.2, 'h2' => 4, 'h3' => 0, 'min_facets' => 3, 'table' => 'bảng thông số kỹ thuật chính',
        'open' => "Giải thích công nghệ bằng một câu dễ hiểu và nói lợi ích cụ thể cho người lái",
        'outline' => "Công nghệ là gì (một câu dễ hiểu) và lợi ích cụ thể; hoạt động ra sao (từng bước, có ví dụ đời thường); bảng thông số chính (chỉ số có trong dữ kiện); trải nghiệm thực tế khi dùng (đi phố, đường dài, địa hình); so với xe xăng và xe điện thuần: điểm mạnh và hạn chế thật; ai phù hợp, ai chưa phù hợp; câu hỏi thường gặp; kết bài mời lái thử.",
        'facets' => [['là gì', 'hoạt động', 'nguyên lý', 'reev'], ['pin', 'sạc', 'kwh', 'quãng đường'], ['vận hành', 'động cơ', 'công suất', 'mô-men'], ['lái thử', 'showroom', 'tư vấn']]],
    'chi-phi' => ['len' => 1.1, 'h2' => 4, 'h3' => 0, 'min_facets' => 3, 'table' => 'bảng chi phí theo tháng và theo năm',
        'open' => "Nêu luôn kết quả tính (mỗi tháng hoặc mỗi năm) kèm giả định cụ thể",
        'outline' => "Kết quả tính nhanh và giả định (quãng đường mỗi tháng, giá nhiên liệu, giá điện) ngay đầu bài; bảng chi phí theo tháng và theo năm, có phép tính chứ không chỉ kết quả; chi phí bảo dưỡng, pin, bảo hiểm; tổng kết theo năm và điều kiện để con số đúng; nhắc đây là ước tính; câu hỏi thường gặp; kết bài mời nhận báo giá.",
        'facets' => [['giả định', 'ước tính', 'tính toán'], ['nhiên liệu', 'điện', 'xăng', 'dầu'], ['tiết kiệm', 'mỗi tháng', 'mỗi năm'], ['bảo dưỡng', 'bảo hành', 'pin']]],
    'tin-tuc' => ['len' => 1.0, 'h2' => 4, 'h3' => 0, 'min_facets' => 3, 'table' => 'bảng tóm tắt các điểm mới hoặc so sánh nhanh',
        'open' => "Đoạn đầu trả lời đủ: chuyện gì, khi nào, ảnh hưởng đến ai (kim tự tháp ngược, ý quan trọng nhất đứng đầu)",
        'outline' => "Tóm tắt tin ở đoạn đầu (chuyện gì, khi nào, ảnh hưởng ai); những điểm mới đáng chú ý, ý quan trọng nhất đứng trước (kèm bảng); tác động tới người mua và chi phí; nhận định của ChungAuto (nêu cả ưu và nhược); câu hỏi thường gặp; kết bài mời xem báo giá.",
        'facets' => [['mới', 'cập nhật', 'ra mắt', 'chính sách'], ['giá', 'chi phí', 'triệu', 'ưu đãi'], ['nhận định', 'đánh giá', 'góc nhìn', 'ưu điểm'], ['chungauto', 'tư vấn', 'showroom']]],
    'huong-dan' => ['len' => 1.3, 'h2' => 4, 'h3' => 0, 'min_facets' => 2, 'table' => 'bảng tóm tắt hoặc danh sách kiểm tra',
        'open' => "Trả lời thẳng câu hỏi của từ khóa ngay đoạn đầu rồi mới giải thích",
        'outline' => "Trả lời nhanh ngay đoạn đầu; các bước hoặc nội dung chính theo thứ tự (đánh số nếu là quy trình); lưu ý và sai lầm hay gặp; bảng tóm tắt hoặc danh sách kiểm tra; câu hỏi thường gặp; kết bài mời xem trang liên quan.",
        'facets' => [['bước', 'cách', 'hướng dẫn'], ['lưu ý', 'kinh nghiệm', 'cần'], ['tư vấn', 'chungauto', 'showroom', 'liên hệ']]],
    // kiến thức chung về xe, giấy tờ, luật giao thông, mức phạt (khung hay gặp ở mục "Kiến thức ô tô" / "Luật giao thông" của chungauto.vn)
    'kien-thuc' => ['len' => 1.2, 'h2' => 5, 'h3' => 0, 'min_facets' => 3, 'table' => 'bảng tóm tắt (mức phạt theo lỗi, giấy tờ cần có, hoặc so sánh các loại)',
        'open' => "Định nghĩa hoặc trả lời thẳng câu hỏi của từ khóa trong 1–2 câu, rồi nói ngay điều người đọc cần nhất (mức phạt, việc phải làm)",
        'outline' => "Là gì (định nghĩa ngắn, tên gọi khác nếu có); gồm những thông tin hoặc thành phần gì; tác dụng và vì sao quan trọng; phân loại hoặc cách nhận biết (thật và giả, đúng và sai); quy định và mức phạt hiện hành (bảng: lỗi, mức phạt, căn cứ pháp luật; chỉ ghi khi dữ kiện có); thủ tục hoặc các bước cần làm (danh sách đánh số, giấy tờ cần có, thời gian, lệ phí nếu dữ kiện có); thay đổi mới hoặc bản điện tử nếu có; lưu ý hay gặp; áp dụng cho chủ xe VF Wild nếu dữ kiện có liên quan; câu hỏi thường gặp; kết luận.",
        'facets' => [['là gì', 'được hiểu', 'tên gọi', 'khái niệm'], ['quy định', 'luật', 'nghị định', 'thông tư', 'mức phạt', 'phạt'], ['thủ tục', 'hồ sơ', 'giấy tờ', 'các bước', 'cách'], ['lưu ý', 'kinh nghiệm', 'cần']]],
    // học từ ~2.100 bài trên chungauto.vn (đa số là "phụ kiện X cho xe Y"): giữ khung hay dùng (là gì → lợi ích → các loại → giá → lưu ý → quy trình lắp → địa chỉ),
    // bỏ các điểm yếu ở đó (không FAQ, không bảng, không link sang trang sản phẩm, FAQ lạc đề)
    'phu-kien' => ['len' => 1.0, 'h2' => 5, 'h3' => 0, 'min_facets' => 4, 'table' => 'bảng so sánh các loại hoặc chất liệu (ưu điểm, hạn chế, hợp với ai)',
        'open' => "Nói ngay phụ kiện này là gì, giải quyết việc gì trên VF Wild và ai nên lắp; có giá trong dữ kiện thì nêu luôn mức giá",
        'outline' => "Trả lời nhanh: phụ kiện là gì và có nên lắp cho VF Wild không (dựa vào đặc điểm thật của xe: bán tải điện, thùng hàng, đi phố và đi dã ngoại), có thể kèm danh sách gợi ý chọn nhanh; lợi ích cụ thể khi dùng trên VF Wild; các loại, mẫu hoặc chất liệu (bảng so sánh); chọn loại nào theo nhu cầu (đi phố, chở hàng, dã ngoại, gia đình); giá và chi phí (chỉ ghi số có trong dữ kiện hoặc ghi chú, không có thì nói rõ báo giá theo mẫu và mời nhận báo giá); băn khoăn lớn nhất khi lắp: có phải khoan đục không, có ảnh hưởng hệ thống điện hay bảo hành xe không, có cần đăng kiểm hay đúng quy định không (trả lời theo dữ kiện, dữ kiện không nói thì khuyên hỏi kỹ thuật viên trước khi lắp); quy trình lắp tại ChungAuto và cách bảo quản, vệ sinh; câu hỏi thường gặp; kết bài: lắp ở đâu (showroom ChungAuto trong dữ kiện) và mời nhận báo giá qua hotline hoặc Zalo.",
        'facets' => [['loại', 'mẫu', 'chất liệu', 'dòng'], ['giá', 'chi phí', 'báo giá'], ['lắp', 'thi công', 'quy trình', 'lắp đặt'], ['lưu ý', 'bảo hành', 'vừa', 'kinh nghiệm'], ['chungauto', 'showroom', 'hotline', 'zalo']]],
];
/** Câu hỏi mẫu cho mục FAQ theo loại bài (để AI không hỏi lạc sang chủ đề khác). */
const AUTO_FAQ_EG = ['gia' => 'VF Wild giá lăn bánh tại Hà Nội khoảng bao nhiêu?', 'so-sanh' => 'VF Wild hay Ranger hợp chở hàng hơn?', 'thu-tuc' => 'VF Wild trả góp tối đa bao nhiêu năm?', 'cong-nghe' => 'VF Wild hết pin còn chạy được không?', 'chi-phi' => 'Chạy VF Wild mỗi tháng tốn bao nhiêu tiền điện?', 'tin-tuc' => 'Chính sách mới áp dụng cho VF Wild từ khi nào?', 'huong-dan' => 'Đặt cọc VF Wild online cần những gì?', 'phu-kien' => 'Lắp nắp thùng VF Wild có phải khoan vào thùng xe không?', 'kien-thuc' => 'Quên mang giấy đăng ký xe ô tô bị phạt bao nhiêu?'];

/** Trang sản phẩm trên chungauto.vn theo nhóm phụ kiện: [mẫu (khớp trên từ khóa đã bỏ dấu), tên, link]. Dùng cho bài phụ kiện VF Wild. */
const AUTO_SHOP_LINKS = [
    // bài tư vấn phụ kiện riêng cho VF Wild trên chungauto.vn (ưu tiên vì đúng xe)
    ['/nap-thung|nap-cuon/', 'Tư vấn nắp thùng VF Wild trên chungauto.vn', 'https://chungauto.vn/nap-thung-vf-wild-chon-loai-nao-mau-dep-va-gia-moi-nhat-2755.html'],
    ['/lot-thung/', 'Tư vấn lót thùng VF Wild trên chungauto.vn', 'https://chungauto.vn/lot-thung-vf-wild-bao-ve-long-thung-chong-tray-xuoc-hieu-qua-2768.html'],
    ['/leu-noc|leu-tren-noc/', 'Tư vấn lều nóc VF Wild trên chungauto.vn', 'https://chungauto.vn/leu-noc-vf-wild-loai-nao-tot-tu-van-kinh-nghiem-va-chi-phi-lap-dat-2744.html'],
    ['/(^|-)toi(-|$)/', 'Tư vấn tời kéo VF Wild trên chungauto.vn', 'https://chungauto.vn/toi-vf-wild-giai-phap-keo-xe-khi-di-dia-hinh-kho-2783.html'],
    ['/ong-tho/', 'Tư vấn ống thở VF Wild trên chungauto.vn', 'https://chungauto.vn/ong-tho-vf-wild-khi-nao-nen-lap-va-chon-loai-nao-phu-hop-2803.html'],
    ['/giam-xoc|phuoc/', 'Tư vấn giảm xóc VF Wild trên chungauto.vn', 'https://chungauto.vn/giam-xoc-vf-wild-gia-loai-phuoc-va-kinh-nghiem-lua-chon-2772.html'],
    ['/(^|-)(?<!cua-|suat-)lop(-|$)|vo-xe/', 'Tư vấn chọn lốp VF Wild trên chungauto.vn', 'https://chungauto.vn/lop-vf-wild-cam-nang-chon-lop-theo-nhu-cau-va-ngan-sach-2754.html'],
    ['/(^|-)mam(-|$)|la-zang|vanh/', 'Tư vấn độ mâm VF Wild trên chungauto.vn', 'https://chungauto.vn/mam-vf-wild-do-mam-cho-dong-xe-ban-tai-vinfast-2742.html'],
    ['/body-?kit/', 'Tư vấn body kit VF Wild trên chungauto.vn', 'https://chungauto.vn/body-kit-vf-wild-thay-doi-dien-mao-tang-chat-rieng-cho-xe-2828.html'],
    ['/thanh-the-thao|roll-bar/', 'Tư vấn thanh thể thao VF Wild trên chungauto.vn', 'https://chungauto.vn/thanh-the-thao-vf-wild-nang-dien-mao-tang-chat-manh-me-cho-xe-2802.html'],
    ['/bao-ve-pin|giap-gam|op-gam/', 'Tư vấn bảo vệ pin VF Wild trên chungauto.vn', 'https://chungauto.vn/bao-ve-pin-vf-wild-giam-rui-ro-va-dap-khi-van-hanh-dia-hinh-2827.html'],
    ['/(^|-)op-cua-lop|cua-lop/', 'Tư vấn ốp cua lốp VF Wild trên chungauto.vn', 'https://chungauto.vn/op-cua-lop-vf-wild-chon-mau-nao-dep-va-phu-hop-cho-xe-2784.html'],
    ['/(^|-)tham(?!-(khao|gia|quan|du|dinh))/', 'Tư vấn thảm lót sàn VF Wild trên chungauto.vn', 'https://chungauto.vn/tham-lot-san-vf-wild-chon-mau-nao-phu-hop-2816.html'],
    ['/phim-cach-nhiet|dan-phim(?!-ppf)|dan-kinh/', 'Tư vấn dán phim cách nhiệt VF Wild trên chungauto.vn', 'https://chungauto.vn/phim-cach-nhiet-vf-wild-tranh-sai-khi-dan-va-bao-gia-2702.html'],
    ['/camera-360/', 'Tư vấn camera 360 VF Wild trên chungauto.vn', 'https://chungauto.vn/camera-360-vf-wild-toan-bo-thong-tin-can-biet-truoc-khi-lap-2818.html'],
    ['/camera-hanh-trinh|cam-hanh-trinh/', 'Tư vấn camera hành trình VF Wild trên chungauto.vn', 'https://chungauto.vn/cam-hanh-trinh-vf-wild-top-4-giai-phap-ghi-hinh-an-toan-2705.html'],
    ['/ap-suat-lop|tpms/', 'Tư vấn cảm biến áp suất lốp VF Wild trên chungauto.vn', 'https://chungauto.vn/cam-bien-ap-suat-lop-vf-wild-top-4-dong-phu-hop-nhat-2710.html'],
    ['/cam-bien-va-cham|cam-bien-lui/', 'Tư vấn cảm biến va chạm VF Wild trên chungauto.vn', 'https://chungauto.vn/cam-bien-va-cham-vf-wild-co-thuc-su-can-thiet-cho-xe-ban-tai-2834.html'],
    ['/decal|dan-tem/', 'Mẫu decal VF Wild trên chungauto.vn', 'https://chungauto.vn/decal-vf-wild-mau-dep-ca-tinh-cho-vinfast-vf-wild-2732.html'],
    ['/do-den|bi-led|bi-laser|bi-gam/', 'Tư vấn độ đèn VF Wild trên chungauto.vn', 'https://chungauto.vn/do-den-vf-wild-het-bao-nhieu-tien-2788.html'],
    ['/(^|-)loa(-|$)|am-thanh/', 'Tư vấn độ loa VF Wild trên chungauto.vn', 'https://chungauto.vn/do-loa-vf-wild-khong-can-qua-dat-neu-biet-chon-dung-cau-hinh-2845.html'],
    ['/ghe-dien/', 'Tư vấn ghế điện VF Wild trên chungauto.vn', 'https://chungauto.vn/ghe-dien-vf-wild-nang-cap-trai-nghiem-ngoi-lai-tien-nghi-hon-2815.html'],
    ['/android-box/', 'Android box cho VF Wild trên chungauto.vn', 'https://chungauto.vn/giai-phap-nang-cap-android-box-vf-wild-2712.html'],
    ['/man-hinh-android/', 'Màn hình Android cho VF Wild trên chungauto.vn', 'https://chungauto.vn/man-hinh-android-vf-wild-texpad-tx24-limited-13-inch-2975.html'],
    ['/(^|-)do-xe|nang-cap|goi-do|(^|-)do-vf/', 'Các gói độ VF Wild trên chungauto.vn', 'https://chungauto.vn/do-vf-wild-sao-cho-dung-cac-goi-nang-cap-va-bao-gia-moi-nhat-2-2696.html'],
    // trang danh mục sản phẩm
    ['/(^|-)tham(?!-(khao|gia|quan|du|dinh))/', 'Thảm lót sàn ô tô tại ChungAuto', 'https://chungauto.vn/tham-lot-san-c69.html'],
    ['/phim-cach-nhiet|dan-phim|dan-kinh/', 'Dán phim cách nhiệt ô tô tại ChungAuto', 'https://chungauto.vn/phim-cach-nhiet-c8.html'],
    ['/camera-hanh-trinh|cam-hanh-trinh/', 'Camera hành trình tại ChungAuto', 'https://chungauto.vn/camera-hanh-trinh-c23.html'],
    ['/camera-360/', 'Camera 360 ô tô tại ChungAuto', 'https://chungauto.vn/camera-360-c24.html'],
    ['/camera-3-mat/', 'Camera 3 mắt tại ChungAuto', 'https://chungauto.vn/camera-3-mat-oto-c99.html'],
    ['/ap-suat-lop|tpms/', 'Cảm biến áp suất lốp tại ChungAuto', 'https://chungauto.vn/cam-bien-ap-suat-lop-c15.html'],
    ['/cam-bien-lui|cam-bien-va-cham|cam-bien-do-xe/', 'Cảm biến lùi ô tô tại ChungAuto', 'https://chungauto.vn/cam-bien-lui-c14.html'],
    ['/ghe-da|boc-ghe|ghe-xe|boc-da/', 'Bọc ghế da ô tô tại ChungAuto', 'https://chungauto.vn/boc-ghe-da-oto-c87.html'],
    ['/vo-lang/', 'Phụ kiện vô lăng và trang trí nội thất tại ChungAuto', 'https://chungauto.vn/trang-tri-noi-that-o-to-c68.html'],
    ['/(^|-)loa(-|$)|am-thanh|(^|-)sub(-|$)/', 'Độ loa ô tô tại ChungAuto', 'https://chungauto.vn/loa-o-to-c7.html'],
    ['/cach-am|chong-on/', 'Cách âm chống ồn ô tô tại ChungAuto', 'https://chungauto.vn/cach-am-xe-hoi-c83.html'],
    ['/(^|-)hud(-|$)|hien-thi-kinh-lai/', 'Thiết bị hiển thị HUD tại ChungAuto', 'https://chungauto.vn/thiet-bi-hien-thi-hud-c71.html'],
    ['/android-box/', 'Android box ô tô tại ChungAuto', 'https://chungauto.vn/android-box-o-to-c75.html'],
    ['/man-hinh-android|man-hinh-o-to/', 'Màn hình Android ô tô tại ChungAuto', 'https://chungauto.vn/man-hinh-o-to-c5.html'],
    ['/bi-gam|den-gam|den-suong-mu/', 'Bi gầm ô tô tại ChungAuto', 'https://chungauto.vn/bi-gam-c19.html'],
    ['/bi-laser/', 'Bi laser ô tô tại ChungAuto', 'https://chungauto.vn/do-bi-laser-c11.html'],
    ['/bi-led|(^|-)den(-|$)|do-den/', 'Độ đèn ô tô tại ChungAuto', 'https://chungauto.vn/den-o-to-c10.html'],
    ['/led-noi-that|den-noi-that|led-am-tran/', 'Độ LED nội thất ô tô tại ChungAuto', 'https://chungauto.vn/led-noi-that-o-to-p658.html'],
    ['/tran-sao/', 'Độ trần sao ô tô tại ChungAuto', 'https://chungauto.vn/tran-sao-o-to-c80.html'],
    ['/diem-mu/', 'Cảnh báo điểm mù tại ChungAuto', 'https://chungauto.vn/canh-bao-diem-mu-o-to-c73.html'],
    ['/cua-hit/', 'Cửa hít ô tô tại ChungAuto', 'https://chungauto.vn/cua-hit-c21.html'],
    ['/bac-len-xuong|bac-buoc/', 'Bậc lên xuống ô tô tại ChungAuto', 'https://chungauto.vn/bac-len-xuong-o-to-c20.html'],
    ['/decal|dan-tem/', 'Dán decal ô tô tại ChungAuto', 'https://chungauto.vn/dan-decal-oto-c114.html'],
    ['/ppf|ceramic|phu-gam|duong-son|ve-sinh-ngoai/', 'Chăm sóc ngoại thất ô tô tại ChungAuto', 'https://chungauto.vn/cham-soc-ngoai-that-c53.html'],
    ['/ve-sinh-noi-that|khu-mui|cham-soc-noi-that/', 'Chăm sóc nội thất ô tô tại ChungAuto', 'https://chungauto.vn/cham-soc-noi-that-c52.html'],
    ['/smart-?key|chia-khoa/', 'Smart key ô tô tại ChungAuto', 'https://chungauto.vn/smartkey-c30.html'],
    ['/cruise|ga-tu-dong/', 'Cruise control tại ChungAuto', 'https://chungauto.vn/do-cruise-control-c48.html'],
    ['/gap-guong/', 'Gập gương ô tô tại ChungAuto', 'https://chungauto.vn/do-gap-guong-c46.html'],
    ['/bom-lop/', 'Bơm lốp ô tô tại ChungAuto', 'https://chungauto.vn/bom-lop-o-to-c93.html'],
    ['/(^|-)op-|nap-thung|thung-xe|baga|gia-noc|ngoai-that|cua-lop|than-xe/', 'Phụ kiện ngoại thất ô tô tại ChungAuto', 'https://chungauto.vn/ngoai-that-o-to-c9.html'],
    ['/noi-that/', 'Phụ kiện nội thất ô tô tại ChungAuto', 'https://chungauto.vn/noi-that-o-to-c4.html'],
];
/** Từ khóa có nói về VF Wild không (bài về xe khác như Honda City thì không gắn dữ kiện, liên kết, lời dặn riêng của VF Wild). */
function auto_kw_wild(string $kw): bool { return strpos(blog_slugify($kw, 0), 'wild') !== false; }
/** Từ khóa nêu tên một mẫu xe khác VF Wild (Honda City, Ranger, VF6…). */
function auto_kw_other_car(string $kw): bool {
    return !auto_kw_wild($kw) && (bool)preg_match('/(^|-)(honda|city|civic|crv|cr-v|toyota|vios|camry|fortuner|innova|hilux|hyundai|accent|tucson|santafe|kia|seltos|sonet|morning|carnival|mazda|cx-?\d|ford|ranger|everest|mitsubishi|xpander|pajero|triton|mg|zs|nissan|navara|suzuki|lynk|omoda|byd|vf-?[3-9]|vf[3-9]|limo|minio|herio|nerio|ec-van|fadil|lux)(-|$)/', blog_slugify($kw, 0));
}
/** Tối đa $n trang sản phẩm chungauto.vn hợp với từ khóa (bài phụ kiện). */
function auto_shop_links(string $kw, int $n = 2): array {
    $k = blog_slugify($kw, 0); $out = []; $other = auto_kw_other_car($kw);
    foreach (AUTO_SHOP_LINKS as [$re, $t, $u]) {
        if ($other && strpos($u, 'vf-wild') !== false) continue;   // bài về xe khác (Honda City…): không trỏ sang bài tư vấn riêng cho VF Wild
        if (preg_match($re, $k)) $out[] = [$t, $u];
        if (count($out) >= $n) break;
    }
    return $out;
}

/**
 * Loại bài: ưu tiên chuyên mục (cột C) và dạng bài (cột D) trong Sheet, không rõ thì đoán theo từ khóa.
 * Riêng bài phụ kiện / độ xe nhận theo từ khóa trước (dù chuyên mục là "Tin tức" hay "Giá"), vì khung bài và lời dặn khác hẳn bài về xe.
 */
function auto_type_for(string $category, string $form, string $kw): string {
    foreach ([$category, $form] as $x) { if (preg_match('/phụ kiện|độ xe/iu', $x)) return 'phu-kien'; }
    $det = auto_detect_type($kw);
    if ($det === 'phu-kien') return 'phu-kien';
    if ($det === 'kien-thuc' && ($category === '' || preg_match('/tin tức|hướng dẫn|kiến thức|luật|thủ tục/iu', $category))) return 'kien-thuc';   // "Tin tức"/"Hướng dẫn"/"Thủ tục" chỉ là ngăn chứa; bài luật, giấy tờ cần khung riêng
    $map = ['tin tức' => 'tin-tuc', 'giá' => 'gia', 'công nghệ' => 'cong-nghe', 'chi phí' => 'chi-phi', 'so sánh' => 'so-sanh', 'thủ tục' => 'thu-tuc', 'hướng dẫn' => 'huong-dan'];
    foreach ($map as $needle => $type) { if (mb_stripos($category, $needle, 0, 'UTF-8') !== false) return $type; }
    if (mb_stripos($form, 'so sánh', 0, 'UTF-8') !== false) return 'so-sanh';
    return auto_detect_type($kw);
}

/** Các từ có nghĩa trong một cụm (bỏ dấu, bỏ từ quá chung như vf/wild/xe). */
function auto_tok(string $s): array {
    $stop = ['vf', 'wild', 'vinfast', 'xe', 'la', 'va', 'cua', 'cho', 'nhung', 'cac', 'mot', 'co', 'nao', 'gi', 'bao', 'nhieu', 'the', 'khong', 'duoc', 'o', 'tai'];
    $w = array_unique(array_filter(explode('-', blog_slugify($s)), fn($t) => $t !== '' && (strlen($t) > 1 || ctype_digit($t)) && !in_array($t, $stop, true)));
    return array_values($w);
}
/** Tỉ lệ các từ của cụm xuất hiện trong văn bản (0–1). */
function auto_hit(array $tokens, string $text): float {
    if (!$tokens) return 1.0;
    $hay = ' ' . str_replace('-', ' ', blog_slugify($text, 0)) . ' ';   // không cắt độ dài: cần xét cả đoạn văn dài
    $n = 0;
    foreach ($tokens as $t) { if (strpos($hay, ' ' . $t . ' ') !== false) $n++; }
    return $n / count($tokens);
}

/**
 * Liên kết nội bộ gợi ý: các trang chính luôn có, các bài đã đăng xếp theo độ liên quan với từ khóa.
 * Bài cho xe khác (Honda City…): không gợi ý các trang VF Wild, chỉ bài đã đăng thật sự liên quan (khớp từ 50% trở lên).
 */
function auto_link_candidates(array $posts, string $kw = ''): array {
    $out = [];
    $other = auto_kw_other_car($kw);
    if (!$other) foreach (AUTO_PAGES as [$t, $u]) $out[] = [$t, $u];
    $tok = auto_tok($kw);
    $ranked = [];
    foreach ($posts as $i => $p) {
        if (blog_state($p) !== 'published') continue;
        $hit = auto_hit($tok, $p['title'] . ' ' . ($p['tags'] ?? ''));
        if ($other && $hit < 0.5) continue;
        $ranked[] = [$hit, -$i, [$p['title'], '/blog/' . $p['slug'] . '/']];
    }
    usort($ranked, fn($a, $b) => [$b[0], $b[1]] <=> [$a[0], $a[1]]);
    foreach ($ranked as $r) $out[] = $r[2];
    return $out;
}
/** Nếu AI chèn dưới 2 liên kết nội bộ thì thêm mục "Xem thêm" với các liên kết liên quan nhất. */
function auto_ensure_links(string $body, array $links): string {
    $have = preg_match_all('/(?<!!)\[[^\]]*\]\(\/(?!\/)/u', $body);
    if ($have >= 2) return $body;
    $add = [];
    foreach ($links as [$t, $u]) {
        if (strpos($body, '](' . $u . ')') !== false) continue;
        $add[] = '- [' . str_replace(['[', ']'], '', $t) . '](' . $u . ')';
        if (count($add) >= 3) break;
    }
    return $add ? rtrim($body) . "\n\n## Xem thêm\n\n" . implode("\n", $add) . "\n" : $body;
}

/** Đoạn hướng dẫn cấu trúc bài theo loại, đưa vào câu lệnh gửi AI. */
/** Quy tắc chất lượng luôn gắn vào câu lệnh (không phụ thuộc phần "Hướng dẫn viết" đã lưu trong Cài đặt). */
const AUTO_QUALITY_RULES = <<<'TXT'
QUY TẮC CHẤT LƯỢNG (bắt buộc):
- Mỗi mục H2 phải đem lại thông tin mới cho người đọc: một con số (khi dữ kiện có), một phép tính ví dụ, một so sánh hoặc một lời khuyên chọn lựa. Không viết mục chỉ để cho đủ số lượng.
- Không khẳng định điều bộ dữ kiện và ghi chú biên tập không nói: số lượng hay phạm vi cơ sở ("trên toàn quốc"), tay nghề hay chất lượng thợ, chính sách bảo hành hay đổi trả của phụ kiện, khả năng tự lắp, tính tương thích. Chỗ nào thiếu thì nói thận trọng ("tùy trường hợp, hãy hỏi tư vấn viên") hoặc bỏ ý đó.
- Mỗi con số hay dữ kiện chỉ nêu đầy đủ MỘT lần trong phần thân bài; chỗ khác dẫn lại bằng từ ngắn ("mức giá trên"). Ngoại lệ: bảng và đoạn mở đầu.
- Cấm câu sáo rỗng như "trong bối cảnh", "không thể phủ nhận", "hơn bao giờ hết", "đóng vai trò quan trọng", "hãy cùng tìm hiểu", "bài viết này sẽ". Đi thẳng vào thông tin.
- Bảng: mỗi ô phải có dữ liệu thật lấy từ bộ dữ kiện. Dòng hoặc cột nào thiếu dữ liệu thì BỎ đi; tuyệt đối không ghi "Tham khảo tư vấn viên", "Liên hệ", "Đang cập nhật", "Tùy phiên bản", "N/A" vào ô bảng.
- Thiếu số liệu cho một ý thì bỏ ý đó. Chỉ khi thật cần mới nói một câu duy nhất trong cả bài rằng con số thay đổi theo thời điểm nên hỏi tư vấn viên.
- Câu ngắn (dưới 25 từ), đoạn 2–4 câu; số liệu đặt vào bảng hoặc gạch đầu dòng.
- Chỉ viết về điều bộ dữ kiện và ghi chú biên tập có nói tới. Chủ đề nào thiếu dữ kiện thì viết ngắn phần có thông tin, không viết chung chung cho đủ chữ, không bịa.
- Khi đánh giá hay so sánh phải nêu cả ưu điểm lẫn hạn chế, và nói rõ kết luận dành cho nhóm khách hàng nào.

TXT;
/** Số từ mục tiêu biên tập ghi trong ô Ghi chú ("tối ưu 1200 từ", "khoảng 1.500 từ"); 0 nếu không có. */
function auto_target_words(string $note): int {
    if (preg_match_all('/(\d{1,2}[.,]\d{3}|\d{3,5})\s*(?:từ|chữ)(?![\p{L}])(?!\s*khóa)/iu', $note, $m)) {
        foreach ($m[1] as $x) { $v = (int)str_replace(['.', ','], '', $x); if ($v >= 300 && $v <= 10000) return $v; }
    }
    return 0;
}
/** Các tiêu đề H2 của dàn ý trong ô Ghi chú (dòng bắt đầu bằng "H2:"). */
function auto_outline_h2(string $note): array {
    $out = [];
    foreach (preg_split('/\r?\n/', $note) ?: [] as $ln) {
        if (preg_match('/^\s*[-*]?\s*H2\s*[:：]\s*(.+)$/iu', $ln, $m)) { $t = trim(preg_replace('/^\d{1,2}\s*[.):]\s+/u', '', trim($m[1])) ?? $m[1]); if ($t !== '') $out[] = $t; }
    }
    return $out;
}
/** Quy tắc bám dàn ý khi ô Ghi chú có H1/H2/H3. */
function auto_outline_rules(): string {
    return "- GHI CHÚ BIÊN TẬP CÓ DÀN Ý (H1/H2/H3): đây là yêu cầu BẮT BUỘC, ưu tiên hơn \"cấu trúc bài gợi ý\" ở trên. Dòng H1 là tiêu đề bài (title). Giữ đúng thứ tự và chủ đề từng H2, H3: không gộp, không bỏ, không đổi chỗ, không chuyển một mục thành câu hỏi thường gặp. Triển khai đủ các ý gạch đầu dòng của mỗi mục, bám đúng tên tiêu đề. Dòng \"Từ khóa:\" của một mục là các từ khóa lồng ghép tự nhiên vào đúng mục đó, không nhồi nhét. Dòng \"Mở bài:\" là chỉ dẫn viết đoạn mở đầu, không chép nguyên văn. Các dòng \"Đối tượng:\" và \"Góc khác biệt:\" là định hướng: viết đúng cho người đọc đó và nhấn đúng góc khác biệt đó. Các dòng \"Ảnh bìa:\" và \"Ảnh:\" chỉ là tên tệp để hệ thống đặt ảnh: KHÔNG chép tên tệp hay chữ \"Ảnh\" vào bài. Dòng \"Trình bày:\" của một mục là cách trình bày bắt buộc cho mục đó (gạch đầu dòng có nhãn in đậm, bảng, các bước đánh số hoặc đoạn ngắn); không chép chữ \"Trình bày\" vào bài. Trong mục \"Câu hỏi thường gặp\", mỗi dòng gạch đầu dòng dạng \"câu hỏi? → ý trả lời\" là một câu hỏi (viết thành tiêu đề H3) kèm câu trả lời triển khai từ ý sau dấu mũi tên. Mọi yêu cầu đặc biệt ở cuối ghi chú (bỏ mục, đổi mục, số từ, sửa địa chỉ…) phải làm đúng; các mục còn lại sẽ được hệ thống đánh số lại liền mạch. Mục \"Câu hỏi thường gặp\" đặt ngay trước mục kết luận, mục kết luận là H2 cuối cùng theo dàn ý. Thông tin trong ghi chú biên tập được dùng như dữ kiện; nơi ghi chú dặn \"chỉ điền khi đã có báo giá thực tế\" mà không có số thì KHÔNG điền số, viết bằng lời.\n";
}
/**
 * Cách trình bày học từ các bài trên chungauto.vn (mỗi mục: câu dẫn → gạch đầu dòng có nhãn in đậm → câu chốt; in đậm từ khóa và con số quan trọng),
 * nhưng tiết chế hơn (chungauto in đậm từ khóa tới 15 lần một bài, trông như nhồi). $kw = từ khóa chính.
 */
function auto_format_rules(string $kw): string {
    return "TRÌNH BÀY (bắt buộc, dễ đọc lướt):\n"
        . "- Mỗi mục H2: mở bằng 1–2 câu nêu ý chính; khi mục có từ 3 ý song song trở lên (đặc điểm, lợi ích, hạn chế, loại, lưu ý, kinh nghiệm, giấy tờ) thì trình bày bằng danh sách gạch đầu dòng, mỗi dòng mở bằng nhãn ngắn in đậm kết thúc bằng dấu hai chấm rồi 1–2 câu giải thích, ví dụ \"- **Giữ màn hình zin:** kết nối qua cổng USB nên không phải thay màn hình.\"; nếu cần câu chốt cuối mục thì phải có thông tin mới (một lời khuyên chọn, một con số), và mỗi mục chốt theo một cách khác nhau.\n"
        . "- Quy trình, các bước, thủ tục: danh sách đánh số \"1. **Tên bước:** làm gì, ở đâu, mất bao lâu\". So sánh nhiều mẫu, nhiều mức giá hoặc mức phạt: dùng bảng.\n"
        . "- In đậm (**…**) có chọn lọc: từ khóa chính \"$kw\" ở lần xuất hiện đầu tiên trong đoạn mở đầu và tối đa một lần trong mỗi mục; con số quan trọng (giá, mức phạt, thời gian, dung lượng); nhãn đầu mỗi gạch đầu dòng; chữ \"**Lưu ý:**\" khi nhắc điều cần cẩn thận. Không in đậm cả câu dài, không in đậm trong tiêu đề H2/H3, trung bình không quá một cụm in đậm cho mỗi 40 chữ.\n"
        . "- Đoạn văn tối đa 3 câu và không quá 70 chữ; ý dài thì tách đoạn hoặc chuyển thành gạch đầu dòng. Cả bài có ít nhất 2 danh sách gạch đầu dòng hoặc đánh số.\n"
        . "- Từ khóa phụ: mỗi cụm dùng tối đa 1–2 lần, chỉ ở câu mà nó đọc tự nhiên; KHÔNG in đậm từ khóa phụ; không ghép nhiều từ khóa phụ trong một câu, không viết câu chỉ để chứa từ khóa. Đặc biệt không kết mục bằng câu kiểu \"Những kinh nghiệm … này giúp bạn …\", \"Tuân thủ đúng quy trình … sẽ …\", \"Tham khảo … giúp bạn chủ động …\": câu chốt phải có thông tin mới, không có thì bỏ. Đọc lên phải như người viết thật.\n"
        . "- Tên riêng viết hoa đúng: Honda City, VinFast VF Wild, VF6, Toyota, Apple CarPlay… (kể cả khi từ khóa phụ trong Sheet viết thường).\n"
        . "- Liên kết nội bộ chỉ chèn vào câu khi đúng chủ đề câu đó; không có trang liên quan thì đừng gượng ép (hệ thống tự thêm mục \"Xem thêm\" cuối bài).\n"
        . auto_voice_rules()
        . auto_onpage_rules($kw);
}
/** SEO trên trang: tiêu đề Google, mô tả, tiêu đề mục, câu trả lời ngắn cho đoạn trích nổi bật và chatbot AI. */
function auto_onpage_rules(string $kw): string {
    return "SEO TRÊN TRANG (bắt buộc):\n"
        . "- seo_title: từ khóa chính \"$kw\" nằm trong 5 chữ đầu, 45–58 ký tự, nêu lợi ích hoặc điểm khác biệt của bài (với chủ đề giá, quy định, chính sách thì thêm năm " . date('Y') . "); khác tiêu đề các bài cùng chủ đề đã có.\n"
        . "- description: 140–155 ký tự, chứa từ khóa chính, có một con số hoặc lợi ích cụ thể, kết bằng một lời mời nhẹ (xem bảng, xem cách tính…), không lặp nguyên văn câu mở bài.\n"
        . "- 2–3 tiêu đề H2 chứa từ khóa phụ hoặc biến thể tự nhiên của từ khóa chính (không lặp y hệt một cụm ở mọi mục). Có ít nhất một H2 viết dạng câu hỏi người dùng hay gõ.\n"
        . "- Ngay dưới mỗi H2 dạng câu hỏi, câu đầu tiên trả lời trực tiếp trong 1–2 câu (40–50 chữ), đủ ý khi đứng riêng, để Google lấy làm đoạn trích nổi bật và chatbot AI trích dẫn.\n"
        . "- Mỗi bảng có một câu dẫn ngay trên bảng nói bảng so sánh gì; cột đầu là tên mục rõ ràng.\n";
}
/**
 * Cụm "văn mẫu" lặp ở hàng trăm tới hơn nghìn bài chungauto.vn (thống kê 2.137 bài, 24/10/2026) và các cụm sáo hay gặp ở bài AI.
 * Bài vfwild.vn không dùng để giọng văn không bị giống và không lặp.
 */
const AUTO_CLICHES = ['trong bài viết này', 'bài viết dưới đây', 'các chuyên gia tại chungauto', 'đội ngũ chuyên gia', 'để được tư vấn chi tiết', 'hãy liên hệ ngay', 'liên hệ ngay với', 'trung tâm độ xe uy tín', 'địa chỉ uy tín', 'giàu kinh nghiệm', 'tay nghề cao', 'cam kết chính hãng', 'là một trong những', 'dưới đây là', 'an toàn và nhanh chóng', 'nhanh chóng và chuyên nghiệp', 'trên thị trường hiện nay', 'chi tiết nhất', 'tại sao nên', 'nhìn chung', 'giải pháp tối ưu', 'vượt trội', 'nâng tầm', 'đẳng cấp', 'hoàn hảo', 'mang lại trải nghiệm', 'trải nghiệm tuyệt vời', 'không thể bỏ qua', 'lựa chọn hàng đầu', 'xu hướng'];
/** Giọng văn riêng của vfwild.vn (khác chungauto.vn), gắn vào câu lệnh viết bài. */
function auto_voice_rules(): string {
    return "GIỌNG VĂN RIÊNG CỦA VFWILD.VN (khác chungauto.vn, không lặp):\n"
        . "- Viết như một tư vấn viên đang trả lời trực tiếp một khách cụ thể: đi thẳng vào câu trả lời, dùng tình huống thật (đi phố, chở hàng, đi tỉnh, gia đình có trẻ nhỏ), so sánh cụ thể thay cho tính từ chung chung.\n"
        . "- Thay đổi cách mở câu: không mở nhiều câu (hay nhiều mục) bằng cùng một cụm; không lặp cùng một cụm từ quá 2 lần trong bài (trừ từ khóa chính và tên riêng); mỗi mục mở bằng một kiểu câu khác.\n"
        . "- KHÔNG dùng các cụm văn mẫu sau (giống hàng nghìn bài khác): " . implode(', ', array_map(fn($x) => "\"$x\"", AUTO_CLICHES)) . ".\n"
        . "- Không mở bài bằng \"Trong bài viết này…\"/\"Bạn đang tìm…\", không kết bằng \"Hãy liên hệ ngay…\"; lời mời liên hệ chỉ một câu ở kết luận, nói rõ được gì (báo giá theo mẫu, lịch lắp, showroom gần nhất).\n";
}
/** Phần cốt của từ khóa để in đậm ("Cà Vẹt Xe Là Gì?" → "Cà Vẹt Xe"); rỗng nếu quá ngắn. */
function auto_kw_core(string $kw): string {
    $k = trim((string)preg_replace('/\s*(là gì|là sao|bao nhiêu(\s+tiền)?|có nên\b.*|như thế nào|thế nào|ra sao|ở đâu|giá bao nhiêu)?\s*[?!.]*\s*$/iu', '', trim($kw)));
    return count(preg_split('/\s+/u', $k) ?: []) >= 2 ? $k : '';
}
/**
 * In đậm từ khóa chính ở lần xuất hiện đầu tiên của đoạn mở đầu và của mỗi mục H2 (trừ mục câu hỏi thường gặp) nếu AI quên.
 * Không đụng tiêu đề, bảng, ảnh, trích dẫn, chữ trong liên kết, chỗ đã in đậm.
 */
function auto_bold_keyword(string $body, string $kw): string {
    $k = auto_kw_core($kw);
    if ($k === '') return $body;
    $re = '/(?<![\p{L}\p{N}*])' . preg_quote($k, '/') . '(?![\p{L}\p{N}*])/iu';
    $lines = preg_split('/\r?\n/', $body) ?: [];
    $secs = [[]]; $skip = [false];   // chỉ số dòng theo từng phần (phần 0 = mở đầu)
    foreach ($lines as $i => $ln) {
        if (preg_match('/^##\s+(.*)$/u', $ln, $m)) { $secs[] = []; $skip[] = (bool)preg_match('/câu hỏi|hỏi đáp|faq/iu', $m[1]); continue; }
        $secs[count($secs) - 1][] = $i;
    }
    foreach ($secs as $si => $idx) {
        if ($skip[$si]) continue;
        $has = false;
        foreach ($idx as $i) { if (mb_stripos($lines[$i], '**' . $k, 0, 'UTF-8') !== false) { $has = true; break; } }
        if ($has) continue;
        foreach ($idx as $i) {
            $ln = $lines[$i];
            if (preg_match('/^\s*(#|\||!\[|>|```)/u', $ln) || !preg_match($re, $ln, $mm, PREG_OFFSET_CAPTURE)) continue;
            $pos = $mm[0][1]; $pre = substr($ln, 0, $pos);
            if (substr_count($pre, '[') > substr_count($pre, ']') || substr_count($pre, '**') % 2 === 1) continue;   // nằm trong chữ liên kết hoặc đã trong cụm đậm
            $lines[$i] = $pre . '**' . $mm[0][0] . '**' . substr($ln, $pos + strlen($mm[0][0]));
            break;
        }
    }
    return implode("\n", $lines);
}
/** Từ khóa phụ của dòng Sheet (cột G, cách nhau bằng dấu ;). */
function auto_subs(array $row): array { return array_values(array_filter(array_map('trim', explode(';', (string)($row['sub'] ?? ''))), fn($x) => $x !== '')); }
/** Bỏ in đậm ở từ khóa phụ (in đậm chỉ dành cho từ khóa chính, nhãn gạch đầu dòng, con số): "**độ ghế điện honda city**" → "độ ghế điện honda city". */
function auto_unbold_subs(string $body, array $subs, string $kw): string {
    $core = mb_strtolower(auto_kw_core($kw) ?: $kw, 'UTF-8');
    foreach ($subs as $x) {
        if (mb_strtolower($x, 'UTF-8') === $core || mb_strlen($x, 'UTF-8') < 4) continue;
        $body = preg_replace('/\*\*(' . preg_quote($x, '/') . ')\*\*/iu', '$1', $body) ?? $body;
    }
    return $body;
}
/** Tên riêng cần viết hoa đúng: hãng, mẫu xe phổ biến, cộng các cụm viết hoa trong từ khóa chính ("Ghế điện Honda City" → "Honda City"). Khóa = chữ thường. */
function auto_proper_names(string $kw): array {
    $names = ['VF Wild', 'VinFast', 'ChungAuto', 'Honda City', 'Honda', 'Toyota', 'Hyundai', 'Mazda', 'Ford', 'Mitsubishi', 'Nissan', 'Suzuki', 'Ranger', 'Everest', 'Vios', 'Camry', 'Xpander', 'Accent', 'Tucson', 'Seltos', 'Fadil', 'Apple CarPlay', 'Android Auto', 'YouTube', 'Google Maps', 'Vietmap', 'Zalo'];
    $w = preg_split('/\s+/u', trim((string)preg_replace('/[?!.,:;]+/u', ' ', $kw))) ?: [];
    $isCap = fn(string $x) => $x !== '' && mb_strtoupper(mb_substr($x, 0, 1, 'UTF-8'), 'UTF-8') === mb_substr($x, 0, 1, 'UTF-8') && preg_match('/\p{L}/u', mb_substr($x, 0, 1, 'UTF-8'));
    $caps = count(array_filter($w, $isCap));
    if ($w && $caps / count($w) <= 0.6) {   // từ khóa viết kiểu câu (không phải Viết Hoa Mọi Chữ): cụm viết hoa sau chữ đầu là tên riêng
        $run = [];
        foreach ($w as $i => $x) {
            if ($i > 0 && $isCap($x)) { $run[] = $x; continue; }
            if (count($run) >= 1) $names[] = implode(' ', $run);
            $run = [];
        }
        if ($run) $names[] = implode(' ', $run);
    }
    $out = [];
    foreach ($names as $n) { if (mb_strlen($n, 'UTF-8') >= 3) $out[mb_strtolower($n, 'UTF-8')] = $n; }
    uksort($out, fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));   // cụm dài khớp trước ("honda city" trước "honda")
    return $out;
}
/** Viết hoa đúng tên riêng trong bài ("honda city" → "Honda City", "vf6" → "VF6"); không đụng đường dẫn trong liên kết và ảnh. */
function auto_fix_names(string $text, string $kw): string {
    $map = auto_proper_names($kw);
    $alt = implode('|', array_map(fn($k) => preg_quote($k, '/'), array_keys($map)));
    return preg_replace_callback('/(\]\([^)]*\))|(?<![\p{L}\p{N}\/._-])(' . $alt . '|vf\s?[3-9])(?![\p{L}\p{N}])/iu', function ($m) use ($map) {
        if (($m[1] ?? '') !== '') return $m[1];
        $l = mb_strtolower($m[2], 'UTF-8');
        if (preg_match('/^vf\s?([3-9])$/u', $l, $v)) return 'VF' . (str_contains($l, ' ') ? ' ' : '') . $v[1];
        return $map[$l] ?? $m[2];
    }, $text) ?? $text;
}
/**
 * Dấu hiệu nhồi từ khóa phụ: một cụm lặp hơn 3 lần, một câu chứa từ 3 cụm (hoặc 2 cụm nối bằng "hoặc"), câu "chèn cho có" kiểu
 * "Những kinh nghiệm … này giúp bạn …", "Tuân thủ đúng quy trình … sẽ …", "Tham khảo … giúp bạn chủ động …". Trả về danh sách vấn đề.
 */
function auto_stuffing(string $body, array $subs): array {
    $text = mb_strtolower(implode("\n", array_filter(preg_split('/\r?\n/', $body) ?: [], fn($l) => !preg_match('/^\s*(#|\||!\[)/u', $l))), 'UTF-8');
    $text = preg_replace('/\]\([^)]*\)/u', ']', str_replace('**', '', $text)) ?? $text;
    $subs = array_values(array_filter(array_map(fn($x) => mb_strtolower($x, 'UTF-8'), $subs), fn($x) => mb_strlen($x, 'UTF-8') >= 6));
    $out = [];
    foreach ($subs as $x) { $n = substr_count($text, $x); if ($n > 3) $out[] = "cụm \"$x\" lặp $n lần"; }
    foreach (preg_split('/(?<=[.!?])\s+|\n+/u', $text) ?: [] as $sen) {
        $hit = array_values(array_filter($subs, fn($x) => str_contains($sen, $x)));
        $hit = array_values(array_filter($hit, fn($x) => !array_filter($hit, fn($y) => $y !== $x && str_contains($y, $x))));   // cụm nằm trong cụm dài hơn ("độ ghế điện…" trong "quy trình độ ghế điện…") không tính riêng
        if (count($hit) >= 3 || (count($hit) === 2 && str_contains($sen, ' hoặc '))) { $out[] = 'một câu nhồi ' . count($hit) . ' từ khóa phụ ("' . mb_substr(trim($sen), 0, 60, 'UTF-8') . '…")'; continue; }   // 3 cụm một câu, hoặc 2 cụm nối bằng "hoặc" ("android box ô tô hoặc android box cho xe hơi")
        if ($hit && blog_words($sen) <= 30 && preg_match('/^\s*(những|tuân thủ|tham khảo|việc tìm hiểu|nắm rõ|hiểu rõ|hy vọng)\b/u', $sen)) $out[] = 'câu chèn từ khóa cho có ("' . mb_substr(trim($sen), 0, 60, 'UTF-8') . '…")';
    }
    return array_values(array_unique($out));
}
/** Số câu trả lời trong mục "Câu hỏi thường gặp" ngắn hơn $min chữ. */
function auto_faq_short(string $body, int $min = 30): int {
    $lines = preg_split('/\r?\n/', $body) ?: []; $in = false; $ans = null; $short = 0; $buf = '';
    $close = function () use (&$ans, &$buf, &$short, $min) { if ($ans !== null && blog_words(trim($buf)) < $min) $short++; $ans = null; $buf = ''; };
    foreach ($lines as $ln) {
        if (preg_match('/^##\s+(.*)$/u', $ln, $m)) { $close(); $in = (bool)preg_match('/câu hỏi|hỏi đáp|faq/iu', $m[1]); continue; }
        if (!$in) continue;
        if (preg_match('/^###\s+/u', $ln)) { $close(); $ans = 1; continue; }
        if ($ans !== null) $buf .= ' ' . $ln;
    }
    $close();
    return $short;
}
/**
 * Các bài đã có trên vfwild.vn cùng chủ đề với từ khóa (đã đăng và nháp, khớp từ 60% chữ của từ khóa ở tiêu đề / thẻ / tiêu đề mục):
 * để bài mới chọn góc khác, không lặp nội dung, và liên kết tới bài cũ. Trả về tối đa $n bài: ['title','slug','state','heads','body'].
 */
function auto_related_existing(array $posts, string $kw, int $n = 4): array {
    $tok = auto_tok($kw);
    if (!$tok) return [];
    $rank = [];
    foreach ($posts as $p) {
        $st = blog_state($p);
        if (!in_array($st, ['published', 'draft', 'scheduled'], true)) continue;
        preg_match_all('/^##\s+(.+)$/mu', (string)$p['body'], $hm);
        $heads = array_map(fn($h) => trim(preg_replace('/^\d{1,2}\s*[.):]\s+/u', '', $h) ?? $h), $hm[1] ?? []);
        $sc = max(auto_hit($tok, $p['title'] . ' ' . ($p['tags'] ?? '')), auto_hit($tok, implode(' ', $heads)) * 0.9);
        foreach (explode(',', (string)($p['tags'] ?? '')) as $tag) {   // thẻ của bài cũ nằm trọn trong từ khóa mới ("thảm vf wild" ⊂ "thảm lót sàn vf wild") = cùng chủ đề
            $tt = auto_tok($tag);
            if ($tt && strlen(implode('', $tt)) >= 4 && !array_diff($tt, $tok)) { $sc = max($sc, 1.0); break; }
        }
        if ($sc >= 0.6) $rank[] = [$sc, $p['date'] ?? '', ['title' => (string)$p['title'], 'slug' => (string)$p['slug'], 'state' => $st, 'heads' => array_slice($heads, 0, 10), 'body' => (string)$p['body']]];
    }
    usort($rank, fn($a, $b) => [$b[0], $b[1]] <=> [$a[0], $a[1]]);
    return array_column(array_slice($rank, 0, $n), 2);
}
/** Lời dặn tránh trùng với các bài cùng chủ đề đã có trên vfwild.vn. */
function auto_related_prompt(array $rel): string {
    if (!$rel) return '';
    $l = [];
    foreach ($rel as $p) $l[] = '  - "' . $p['title'] . '"' . ($p['state'] === 'published' ? ' (đã đăng: /blog/' . $p['slug'] . '/)' : ' (bản nháp)') . ($p['heads'] ? ' – các mục: ' . implode('; ', array_filter($p['heads'], fn($h) => !preg_match('/^(câu hỏi|kết luận|xem thêm|nguồn)/iu', $h))) : '');
    return "- BÀI CÙNG CHỦ ĐỀ ĐÃ CÓ TRÊN VFWILD.VN (bài mới KHÔNG được lặp lại các bài này):\n" . implode("\n", $l) . "\n"
        . "  Chọn góc khác (đối tượng người đọc, tình huống sử dụng, một khía cạnh đào sâu hơn, thông tin mới hơn); tiêu đề bài và tiêu đề mục khác hẳn; ý nào bài cũ đã viết kỹ thì chỉ nhắc một câu và chèn liên kết tới bài cũ (chỉ bài đã đăng); không chép câu, bảng hay câu hỏi thường gặp của bài cũ.\n";
}
/** So bài mới với các bài cùng chủ đề đã có: [vấn đề buộc viết lại, cảnh báo]. */
function auto_related_overlap(string $body, string $title, array $rel): array {
    $issues = []; $warns = [];
    $norm = fn(string $s) => trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', mb_strtolower($s, 'UTF-8')) ?? '') ?? '');
    preg_match_all('/^##\s+(.+)$/mu', $body, $hm);
    $myH = array_map(fn($h) => $norm(preg_replace('/^\d{1,2}\s*[.):]\s+/u', '', $h) ?? $h), $hm[1] ?? []);
    foreach ($rel as $p) {
        $cp = auto_copy_ratio($body, $p['body'], 6);
        $sameH = count(array_filter(array_map($norm, $p['heads']), fn($h) => $h !== '' && !preg_match('/^(câu hỏi thường gặp|kết luận|xem thêm|nguồn tham khảo)/u', $h) && in_array($h, $myH, true)));
        if ($cp >= 0.12) $issues[] = 'Trùng ' . round($cp * 100) . '% câu chữ với bài đã có "' . $p['title'] . '": viết góc khác, không lặp nội dung.';
        elseif ($cp >= 0.05) $warns[] = 'Có ' . round($cp * 100) . '% câu chữ giống bài "' . $p['title'] . '".';
        if ($norm($title) === $norm($p['title'])) $issues[] = 'Tiêu đề trùng bài đã có "' . $p['title'] . '".';
        if ($sameH >= 3) $warns[] = "Có {$sameH} tiêu đề mục trùng bài \"" . $p['title'] . '".';
    }
    return [$issues, $warns];
}
/** Lời dặn riêng cho bài phụ kiện / độ xe VF Wild (rút ra từ cách viết và các lỗi hay gặp trên chungauto.vn). */
function auto_accessory_rules(string $kw): string {
    $wild = !auto_kw_other_car($kw);   // từ khóa không nêu xe nào khác: mặc định là phụ kiện cho VF Wild (website vfwild.vn)
    $car = $wild ? 'VF Wild' : '<tên xe trong từ khóa>';
    $p = "- BÀI PHỤ KIỆN: tiêu đề bài theo dạng \"<phụ kiện> $car: <lợi ích hoặc loại chính>, giá và lưu ý khi lắp\" hoặc là câu hỏi người mua hay gõ; seo_title cùng từ khóa và cùng góc với tiêu đề bài (không viết hai ý khác nhau). Tên phụ kiện kèm tên xe có mặt trong phần lớn tiêu đề H2 một cách tự nhiên.\n"
        . ($wild
            ? "- Giải thích lợi ích gắn với đặc điểm thật của VF Wild trong dữ kiện (bán tải điện, thùng hàng, kích thước, cabin 5 chỗ…), không viết lợi ích chung chung áp cho mọi xe. Kiến thức chung về phụ kiện (loại, chất liệu, cách hoạt động, quy trình lắp) lấy từ mục \"Phụ kiện\" trong bộ dữ kiện; KHÔNG suy ra giá, thông số hay mức bảo hành riêng cho VF Wild từ đó.\n"
            : "- Bài này viết cho xe trong từ khóa, KHÔNG phải VF Wild: không gắn tên VF Wild, thông số, giá hay dữ kiện của VF Wild vào bài; chỉ dùng dữ kiện về đúng xe đó (mục \"Bài tham khảo cùng chủ đề\" nếu có) và kiến thức chung về phụ kiện.\n")
        . "- Giá phụ kiện: chỉ ghi khi dữ kiện hoặc ghi chú biên tập có số; không có thì bảng so sánh bỏ cột giá và nói rõ giá tùy mẫu, mời nhận báo giá qua hotline hoặc Zalo. Không khẳng định lắp phụ kiện \"không ảnh hưởng bảo hành xe\" hay \"vừa khít 100%\" nếu dữ kiện không nói.\n"
        . "- Không tự khen \"uy tín\", \"chuẩn hãng\", \"tốt nhất\", \"top\" khi không có căn cứ; mục cuối đặt tên kiểu \"Lắp <phụ kiện> $car ở đâu\" và liệt kê showroom theo dữ kiện.\n";
    $shop = auto_shop_links($kw);
    if ($shop) $p .= "- Chèn 1 liên kết sang trang sản phẩm của ChungAuto ở mục các loại hoặc mục giá (đây là liên kết ngoài, ngoài 2 liên kết nội bộ bắt buộc):\n" . implode("\n", array_map(fn($l) => '  - [' . $l[0] . '](' . $l[1] . ')', $shop)) . "\n";
    return $p;
}
function auto_blueprint_prompt(array $item, array $s): string {
    $bp = AUTO_BLUEPRINTS[$item['type'] ?? 'huong-dan'] ?? AUTO_BLUEPRINTS['huong-dan'];
    $words = (int)round((int)$s['min_words'] * $bp['len']);
    $note = (string)($item['note'] ?? '');
    $target = auto_target_words($note);
    $kwi = (string)($item['keyword'] ?? '');
    $outline = $bp['outline'];
    if (auto_kw_other_car($kwi) && ($item['type'] ?? '') === 'phu-kien') $outline = str_replace([' (dựa vào đặc điểm thật của xe: bán tải điện, thùng hàng, đi phố và đi dã ngoại)', 'VF Wild'], ['', 'xe trong từ khóa'], $outline);   // phụ kiện cho xe khác
    $p = "CẤU TRÚC BÀI GỢI Ý: {$outline}\n"
        . "- Dùng ít nhất {$bp['h2']} tiêu đề H2 (##); từ khóa chính xuất hiện trong tiêu đề, trong đoạn mở đầu và trong ít nhất một tiêu đề H2.\n"
        . "- Có ít nhất một bảng markdown ({$bp['table']}).\n"
        . "- Mục \"Câu hỏi thường gặp\" (H2) gồm ít nhất 3 câu: mỗi câu là một tiêu đề H3 (###) rồi một đoạn trả lời ngắn; không gạch đầu dòng. Câu hỏi viết như người mua thật sẽ gõ (có tên xe, ví dụ \"" . (AUTO_FAQ_EG[$item['type'] ?? ''] ?? AUTO_FAQ_EG['huong-dan']) . "\") và PHẢI xoay quanh đúng chủ đề của từ khóa chính \"" . ($item['keyword'] ?? '') . "\" (băn khoăn thật của người mua về chủ đề đó), không hỏi sang chuyện khác của xe chỉ vì dữ kiện có sẵn; câu đầu tiên của phần trả lời là đáp án trực tiếp, đọc riêng vẫn hiểu; mỗi câu trả lời BẮT BUỘC dài 40–60 chữ (2–3 câu), có chi tiết cụ thể từ dữ kiện (con số, thời gian, điều kiện hoặc lưu ý), không trả lời cụt một câu, không chỉ bảo \"liên hệ để được tư vấn\".\n"
        . "- Mỗi mục H2 mở đầu bằng một câu nêu thẳng ý chính của mục (đọc riêng câu đó vẫn hiểu được), rồi mới giải thích. "
        . (auto_kw_other_car($kwi)
            ? "Bài viết cho xe trong từ khóa, không phải VF Wild: không gắn tên hay dữ kiện VF Wild vào bài.\n"
            : "Lần đầu nhắc tên xe viết đầy đủ \"VinFast VF Wild\".\n")
        . "- Ghi mốc cập nhật đúng MỘT lần, lồng vào một câu ở đoạn mở đầu hoặc ngay trên bảng đầu tiên (không để thành một dòng riêng), theo dạng \"" . (($item['type'] ?? '') === 'kien-thuc' ? 'thông tin cập nhật theo quy định đến tháng ' : 'số liệu ChungAuto tổng hợp tháng ') . date('n/Y') . "\". Không tự ghi mốc cập nhật nào khác; ngày hiệu lực của quy định hay chương trình có trong dữ kiện thì vẫn ghi đúng.\n"
        . "- Từ khóa phụ luôn viết bằng tiếng Việt có dấu đúng chính tả, kể cả khi trong Sheet được ghi không dấu (ví dụ \"quy dinh moi nhat\" phải viết \"quy định mới nhất\"); lồng tự nhiên, không chép nguyên cụm gượng ép vào tiêu đề.\n"
        . "- Đoạn mở đầu và mục H2 đầu tiên không lặp lại cùng một ý bằng câu gần giống nhau. Mục cuối cùng của bài là H2 \"Kết luận\" đặt sau mục câu hỏi thường gặp; không viết đoạn kết ngay dưới câu hỏi cuối.\n"
        . "- Tiêu đề H2 KHÔNG tự gõ số thứ tự (hệ thống tự đánh số 1., 2., 3. theo thứ tự xuất hiện, kể cả sau khi bỏ hoặc đổi mục).\n"
        . (auto_outline_h2($note) !== [] ? auto_outline_rules() : '')
        . ($target > 0
            ? "- Không tự viết mục lục (hệ thống tự thêm). ĐỘ DÀI MỤC TIÊU theo ghi chú biên tập: khoảng $target từ (trong khoảng " . (int)round($target * 0.9) . "–" . (int)round($target * 1.1) . " từ); không viết dài hơn nhiều, nén ý cho gọn, đủ ý.\n"
            : "- Không tự viết mục lục (hệ thống tự thêm). Tối thiểu $words từ và KHÔNG có mức tối đa: hãy viết đầy đủ, chi tiết và sâu nhất có thể với mọi dữ kiện liên quan trong bộ dữ kiện (thêm mục H2, ví dụ tính, bảng, câu hỏi thường gặp), miễn là mỗi đoạn đều có thông tin mới, không độn chữ.\n")
        . "- ĐOẠN MỞ ĐẦU (30–70 từ, chứa từ khóa chính): {$bp['open']}. Không mở bài bằng câu chung chung về xu hướng hay thị trường.\n"
        . (($item['type'] ?? '') === 'phu-kien' ? auto_accessory_rules((string)($item['keyword'] ?? '')) : '')
        . (!empty($item['ref']) ? auto_ref_prompt($item['ref'], auto_outline_h2($note) !== []) : '')
        . (!empty($item['research']) ? auto_research_rules(!empty($item['ref'])) : '')
        . auto_related_prompt((array)($item['related'] ?? []))
        . auto_format_rules(auto_kw_core($kwi) !== '' ? auto_kw_core($kwi) : $kwi)
        . AUTO_QUALITY_RULES;
    $prev = trim((string)($item['prev'] ?? ''));
    if ($prev !== '') $p .= "- LẦN VIẾT TRƯỚC BỊ LOẠI VÌ: $prev Hãy sửa đúng các điểm này.\n";
    return $p;
}

/** Các con số có đơn vị trong văn bản mà bộ dữ kiện không có (hiểu 860.000.000đ = 860 triệu). */
function auto_unknown_numbers(string $body, string $facts): array {
    $norm = fn(string $x) => str_replace([' ', "\u{00a0}"], '', mb_strtolower($x, 'UTF-8'));
    $fn = $norm($facts); $unknown = [];
    if (!preg_match_all('/(\d[\d.,]*)\s*(%|(?:triệu|tỷ|km|kwh|kw|nm|mm|lít|đ|năm|phút|lần|chỗ|inch|mã lực)(?![\p{L}]))/iu', $body, $m, PREG_SET_ORDER)) return [];
    foreach ($m as $x) {
        $unit = mb_strtolower($x[2], 'UTF-8');
        $variants = [$norm($x[1] . $unit)];
        if ($unit === 'đ') {
            $v = (int)str_replace(['.', ','], '', $x[1]);
            if ($v >= 1000000) $variants[] = str_replace('.', ',', rtrim(rtrim(number_format($v / 1e6, 2, '.', ''), '0'), '.')) . 'triệu';
            if ($v >= 1000000000) $variants[] = str_replace('.', ',', rtrim(rtrim(number_format($v / 1e9, 2, '.', ''), '0'), '.')) . 'tỷ';
        }
        $found = false;
        foreach ($variants as $vt) { if (strpos($fn, $vt) !== false) { $found = true; break; } }
        if (!$found) $unknown[$variants[0]] = $x[0];
    }
    return array_values($unknown);
}

/** Cụm cấm có trong văn bản: khớp nguyên cụm (không dính vào số hoặc chữ liền kề), không phân biệt hoa thường. "số 1" không bắt nhầm "số 1800" hay "số 15". */
function auto_forbidden_hits(string $text, string $list): array {
    $hits = [];
    foreach (preg_split('/\r?\n/', $list) ?: [] as $ph) {
        $ph = trim($ph);
        if ($ph === '') continue;
        $re = '/(?<![\p{L}\p{N}])' . preg_replace('/\s+/u', '\s+', preg_quote($ph, '/')) . '(?![\p{L}\p{N}]|[.,]\p{N})/iu';
        if (preg_match($re, $text) === 1) $hits[] = $ph;
    }
    return $hits;
}
/** Chấm 100 điểm theo loại bài; trả về điểm, các điểm cần cải thiện, cảnh báo số liệu và số từ. */
/** Cụm sáo rỗng làm bài loãng (không bị coi là cụm cấm, chỉ trừ điểm). */
const AUTO_FLUFF = ['trong bối cảnh', 'không thể phủ nhận', 'ngày càng phát triển', 'hơn bao giờ hết', 'trong thời đại ngày nay', 'đóng vai trò quan trọng', 'đóng vai trò then chốt', 'mang đến trải nghiệm', 'hãy cùng tìm hiểu', 'hãy cùng khám phá', 'bài viết này sẽ', 'bức tranh toàn cảnh', 'có thể nói rằng', 'như chúng ta đã biết', 'nâng tầm trải nghiệm', 'đáp ứng mọi nhu cầu', 'một cách toàn diện', 'tựu chung lại'];
/** Ô bảng "cho có": không chứa dữ liệu thật. */
function auto_placeholder_cells(string $body): int {
    $n = 0;
    foreach (preg_split('/\r?\n/', $body) ?: [] as $ln) {
        $ln = trim($ln);
        if ($ln === '' || $ln[0] !== '|' || preg_match('/^\|?[\s:\-|]+\|?$/', $ln)) continue;
        $cells = array_map('trim', explode('|', trim($ln, '|')));
        foreach (array_slice($cells, 1) as $c) {   // ô đầu là tên tiêu chí nên bỏ qua
            $c = trim(preg_replace('/[*_`]/u', '', $c));
            if (preg_match('/^(tham khảo|liên hệ|đang cập nhật|chưa (có|công bố|rõ|cập nhật)|tùy (phiên bản|thời điểm|chính sách|nhu cầu|khu vực)|n\/?a|—|–|-|…|\.\.\.|\?)/iu', $c) || (mb_stripos($c, 'tư vấn viên', 0, 'UTF-8') !== false && blog_len($c) < 45)) $n++;
        }
    }
    return $n;
}
/** Số liệu có đơn vị bị nhắc đi nhắc lại ngoài bảng, và câu trùng nhau. */
function auto_repeats(string $body, string $kw = ''): array {
    $lines = array_filter(preg_split('/\r?\n/', $body) ?: [], fn($l) => !preg_match('/^\s*(\||#|!\[)/u', $l));
    $text = implode("\n", $lines);
    $out = [];
    if (preg_match_all('/(\d[\d.,]*)\s*(%|(?:triệu|tỷ|km|kwh|kw|nm|mm|lít|đ|năm|tháng|ngày|phút|lần|chỗ|inch|mã lực)(?![\p{L}]))/iu', $text, $m, PREG_SET_ORDER)) {
        $cnt = []; $raw = [];
        foreach ($m as $x) {
            $unit = mb_strtolower($x[2], 'UTF-8');
            $val = (float)str_replace(',', '.', str_replace('.', '', $x[1]));
            if ($val < 10 && in_array($unit, ['năm', 'tháng', 'ngày', 'phút', 'lần', 'chỗ'], true)) continue;   // số nhỏ đi với đơn vị thời gian lặp là tự nhiên
            if ($kw !== '' && preg_match('/(?<![\d.,])' . preg_quote(trim($x[1], '.,'), '/') . '(?![\d])/u', $kw)) continue;   // con số nằm trong từ khóa thì nhắc nhiều là tự nhiên
            $k = str_replace([' ', "\u{00a0}"], '', mb_strtolower($x[1], 'UTF-8')) . $unit;
            $cnt[$k] = ($cnt[$k] ?? 0) + 1; $raw[$k] = $x[0];
        }
        foreach ($cnt as $k => $c) { if ($c >= 4) $out[] = "\"{$raw[$k]}\" nhắc $c lần"; }
    }
    $seen = [];
    foreach (preg_split('/(?<=[.!?])\s+|\n+/u', $text) ?: [] as $sent) {
        $sent = trim($sent);
        if (blog_words($sent) < 8) continue;
        $k = mb_strtolower(preg_replace('/\W+/u', ' ', $sent), 'UTF-8');
        if (isset($seen[$k])) { $out[] = 'câu lặp nguyên văn: "' . mb_substr($sent, 0, 50, 'UTF-8') . '…"'; break; }
        $seen[$k] = 1;
    }
    // văn lặp kiểu mẫu: nhiều câu mở bằng cùng 2 chữ ("Nhìn chung, …", "Việc lắp …"), một cụm 4 chữ dùng đi dùng lại (trừ từ khóa chính)
    $kwTok = array_flip(explode(' ', mb_strtolower(trim((string)preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', auto_kw_core($kw) ?: $kw)), 'UTF-8')));
    $plain = mb_strtolower(preg_replace(['/\]\([^)]*\)/u', '/[*_`\[\]]+/u', '/^\s*([-*]|\d+\.)\s+/mu'], [' ', '', ''], $text) ?? $text, 'UTF-8');
    $open = [];
    foreach (preg_split('/(?<=[.!?:])\s+|\n+/u', $plain) ?: [] as $sent) {
        $w = preg_split('/[^\p{L}\p{N}]+/u', trim($sent), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($w) < 5 || (isset($kwTok[$w[0]]) && isset($kwTok[$w[1]]))) continue;   // câu mở bằng từ khóa chính là tự nhiên
        $o = $w[0] . ' ' . $w[1]; $open[$o] = ($open[$o] ?? 0) + 1;
    }
    arsort($open);
    foreach ($open as $o => $c) { if ($c >= 4) $out[] = "{$c} câu mở đầu bằng \"{$o}…\""; break; }
    $w = preg_split('/[^\p{L}\p{N}]+/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $g = [];
    for ($i = 0; $i + 4 <= count($w); $i++) {
        $gram = array_slice($w, $i, 4);
        if (array_filter($gram, fn($x) => isset($kwTok[$x]) || ctype_digit($x))) continue;
        $k = implode(' ', $gram); $g[$k] = ($g[$k] ?? 0) + 1;
    }
    arsort($g);
    foreach (array_slice($g, 0, 2, true) as $k => $c) { if ($c >= max(5, (int)ceil(count($w) / 180))) $out[] = "cụm \"{$k}\" lặp {$c} lần"; }   // thuật ngữ của chủ đề lặp theo độ dài bài là tự nhiên
    return $out;
}
/** Các cụm văn mẫu (AUTO_CLICHES) có trong bài. */
function auto_cliche_hits(string $body): array {
    $t = mb_strtolower(str_replace('**', '', $body), 'UTF-8'); $hit = [];
    foreach (AUTO_CLICHES as $c) { if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($c, '/') . '(?![\p{L}\p{N}])/u', $t)) $hit[] = $c; }
    return $hit;
}
/** Số danh sách (gạch đầu dòng / đánh số), số cụm in đậm, số đoạn văn thường dài quá 70 chữ (không tính bảng, tiêu đề, ảnh). */
function auto_format_stats(string $body): array {
    $lists = 0; $inList = false; $long = 0;
    foreach (preg_split('/\r?\n/', $body) ?: [] as $ln) {
        $isItem = (bool)preg_match('/^\s*([-*]|\d+\.)\s+\S/u', $ln);
        if ($isItem && !$inList) $lists++;
        $inList = $isItem || ($inList && preg_match('/^\s{2,}\S/', $ln));
    }
    foreach (preg_split('/\n\s*\n/', preg_replace('/^#{1,6}\s.*$/mu', '', $body) ?? $body) ?: [] as $para) {   // bỏ dòng tiêu đề trước (đoạn văn có thể dính ngay dưới tiêu đề)
        $para = trim($para);
        if ($para === '' || preg_match('/^(#|\||!\[|>|[-*]\s|\d+\.\s)/u', $para)) continue;
        if (blog_words($para) > 70) $long++;
    }
    return [$lists, (int)preg_match_all('/\*\*[^*\n]{2,120}\*\*/u', $body), $long];
}
/** Chủ đề của từ khóa có nằm trong bộ dữ kiện / ghi chú không? Trả về từ khóa thiếu dữ kiện, hoặc null nếu đủ. */
function auto_facts_gap(string $kw, array $row, string $facts): ?string {
    $generic = ['review', 'huong', 'dieu', 'kinh', 'nghiem', 'sanh', 'danh', 'chinh', 'hang', 'thuc', 'khach', 'nguoi', 'ngay', 'nhat', 'tiet', 'chuan', 'trong', 'nhung', 'nhieu', 'duoc', 'khong', 'thang', 'nam', 'moi', 'tot', 'nen', 'mua', 'ban', 'cach', 'dung', 'chon', 'cung', 'thanh', 'huyen', 'tinh', 'toan', 'quoc', 'phien', 'phan', 'giua', 'sau', 'truoc', 'hien', 'nay', 'vinfast', 'chungauto'];
    $note = trim((string)($row['note'] ?? ''));
    if (blog_len($note) >= 60) return null;   // biên tập đã tự ghi dữ kiện vào ghi chú
    $long = array_values(array_filter(auto_tok($kw), fn($t) => strlen($t) >= 5 && !ctype_digit($t) && !in_array($t, $generic, true)));
    if (!$long) return null;
    if (auto_hit($long, $facts . ' ' . $note) > 0.0) return null;
    return implode(', ', $long);
}
function auto_score(array $post, array $s, string $facts, array $row = []): array {
    $type = $row['type'] ?? 'huong-dan';
    $bp = AUTO_BLUEPRINTS[$type] ?? AUTO_BLUEPRINTS['huong-dan'];
    $kw = (string)($row['keyword'] ?? '');
    $ktok = auto_tok($kw);
    $body = (string)$post['body'];
    $checks = [];
    $check = function (string $name, bool $ok, int $weight, string $detail) use (&$checks) { $checks[] = ['name' => $name, 'ok' => $ok, 'weight' => $weight, 'detail' => $detail]; };

    $eff = blog_effective_title($post); $tl = blog_len($eff);
    $check('title', $tl >= 25 && $tl <= 60 && auto_hit($ktok, $eff) >= 0.6, 8, "Tiêu đề Google nên dài 25–60 ký tự và chứa từ khóa chính (hiện $tl ký tự).");
    $d = blog_len((string)$post['description']);
    $dKw = auto_hit($ktok, (string)$post['description']) >= 0.6;   // mô tả trên Google phải có từ khóa chính (Google in đậm chữ khớp, tăng tỉ lệ bấm)
    $check('meta', $d >= 70 && $d <= 160 && $dKw, 6, "Mô tả $d ký tự (nên 70–160)" . ($dKw ? '' : ', chưa chứa từ khóa chính') . '.');
    $check('no_h1', !preg_match('/^#\s/m', $body), 3, 'Thân bài không được có tiêu đề H1.');
    $paras = array_values(array_filter(preg_split('/\n\s*\n/', trim($body)) ?: [], fn($x) => !preg_match('/^(#|\||[-*]\s|\d+\.\s)/', ltrim($x))));
    preg_match_all('/^##\s+(.*)$/mu', $body, $hm);
    $h2s = implode(' ', $hm[1] ?? []);
    $check('keyword', auto_hit($ktok, $paras[0] ?? '') >= 0.7 && auto_hit($ktok, $h2s) >= 0.5, 9, 'Từ khóa chính cần có trong đoạn mở đầu và ít nhất một tiêu đề H2.');
    $subs = array_values(array_filter(array_map('trim', explode(';', (string)($row['sub'] ?? '')))));
    if ($subs) {
        $m = 0; foreach ($subs as $x) { if (auto_hit(auto_tok($x), $body) >= 0.7) $m++; }
        $stuff = auto_stuffing($body, $subs);   // có mặt đủ nhưng không được nhồi
        $check('secondary', ($m >= min(3, count($subs)) || $m / count($subs) >= 0.4) && !$stuff, 5, $stuff ? 'Từ khóa phụ bị chèn gượng: ' . implode('; ', array_slice($stuff, 0, 3)) . '. Chỉ dùng ở câu đọc tự nhiên, bỏ câu chỉ để nhắc từ khóa.' : "Từ khóa phụ mới xuất hiện $m/" . count($subs) . '.');
    } else $check('secondary', true, 5, '');
    $links = (int)preg_match_all('/(?<!!)\[[^\]]*\]\(\/(?!\/)/u', $body);
    $h2 = (int)preg_match_all('/^##\s/m', $body); $h3 = (int)preg_match_all('/^###\s/m', $body);
    $other = auto_kw_other_car($kw);   // bài cho xe khác: không bắt chèn liên kết tới các trang VF Wild
    $check('links', ($other || $links >= 2) && $h2 >= 3, 7, $other ? "Cần ít nhất 3 mục H2 để có mục lục." : "Cần ít nhất 2 liên kết nội bộ (hiện $links) và 3 mục H2 để có mục lục.");
    $w = blog_words($body); $req = (int)round((int)$s['min_words'] * $bp['len']);
    $tw = auto_target_words((string)($row['note'] ?? ''));   // biên tập ghi số từ mục tiêu trong ô Ghi chú: coi đó là độ dài đúng, không quá ngắn cũng không quá dài
    if ($tw > 0) { $lo = (int)round($tw * 0.85); $hi = (int)round($tw * 1.3); $check('length', $w >= $lo && $w <= $hi, 7, "Bài $w từ (mục tiêu $tw từ theo ghi chú; chấp nhận {$lo}–{$hi} từ)."); }
    else $check('length', $w >= $req, 7, "Bài $w từ (loại này nên từ $req từ).");
    $check('headings', $h2 >= $bp['h2'] && $h3 >= $bp['h3'], 6, "Có $h2 mục H2 (nên ≥ {$bp['h2']}).");
    [$nl, $nb, $nlong] = auto_format_stats($body);   // trình bày dễ đọc như chungauto.vn: gạch đầu dòng, in đậm, đoạn ngắn
    $check('format', $nl >= 2 && $nb >= 4 && $nlong === 0, 6, "Trình bày: nên có ít nhất 2 danh sách gạch đầu dòng hoặc đánh số (hiện $nl), ít nhất 4 cụm in đậm cho ý quan trọng (hiện $nb), không đoạn văn nào dài quá 70 chữ (đang có $nlong đoạn).");
    $text = mb_strtolower($body, 'UTF-8'); $hit = 0;
    foreach ($bp['facets'] as $needles) { foreach ($needles as $n) { if (mb_strpos($text, $n, 0, 'UTF-8') !== false) { $hit++; break; } } }
    $check('facets', $hit >= $bp['min_facets'], 8, "Mới chạm $hit/" . count($bp['facets']) . ' khía cạnh chuyên môn của loại bài này.');
    $check('table', (bool)preg_match('/^\|.*\|\s*$/m', $body), 5, 'Nên có ít nhất 1 bảng (' . $bp['table'] . ').');
    $fs = auto_faq_short($body);
    $check('faq', blog_faq_ld($body) !== null && $fs === 0, 8, $fs > 0 ? "Có $fs câu trả lời trong mục câu hỏi thường gặp quá ngắn (dưới 30 chữ): mỗi câu trả lời cần 40–60 chữ, 2–3 câu, có chi tiết cụ thể." : 'Nên có mục "Câu hỏi thường gặp" với ít nhất 2 câu hỏi (mỗi câu là một tiêu đề H3).');
    $check('cover', ($post['cover'] !== '' && $post['cover_alt'] !== '') || ($post['cover'] === '' && empty($s['auto_cover'])), 3, 'Thiếu ảnh đại diện hoặc mô tả ảnh.');   // không ảnh là có chủ ý khi tắt tự chọn ảnh

    $first = ''; foreach ($paras as $pp) { if (!preg_match('/^!\[/', ltrim($pp))) { $first = $pp; break; } }
    $fw = blog_words($first); $needNum = in_array($type, ['gia', 'chi-phi'], true);   // bài giá/chi phí phải mở bằng con số; bài thủ tục, tin tức, phụ kiện không bắt buộc
    $okOpen = $fw >= 20 && $fw <= 90 && (!$needNum || preg_match('/\d/', $first)) && ($type !== 'so-sanh' || preg_match('/\d|hơn|nên chọn|phù hợp|nhỉnh|trội|thắng/iu', $first));
    $check('opening', (bool)$okOpen, 5, "Đoạn mở đầu ($fw từ) cần trả lời thẳng câu hỏi trong 20–90 từ" . ($needNum ? ', kèm con số cụ thể' : '') . ($type === 'so-sanh' ? ', nêu luôn kết luận' : '') . '.');
    $ph = auto_placeholder_cells($body);
    $check('placeholder', $ph < 2, 6, "Bảng có $ph ô \"cho có\" (Tham khảo tư vấn viên, Liên hệ…): bỏ dòng thiếu dữ liệu thay vì ghi chữ giữ chỗ.");
    $fl = []; $tl2 = mb_strtolower($body, 'UTF-8');
    foreach (AUTO_FLUFF as $f) { if (mb_strpos($tl2, $f, 0, 'UTF-8') !== false) $fl[] = $f; }
    $cl = auto_cliche_hits($body);   // cụm văn mẫu của chungauto.vn / bài AI: dùng 3 cụm trở lên là giọng văn bị "đúc khuôn"
    $check('fluff', count($fl) < 2 && count($cl) < 3, 4, 'Có câu sáo rỗng hoặc văn mẫu: ' . implode(', ', array_slice(array_merge($fl, $cl), 0, 5)) . '. Viết bằng giọng riêng, đi thẳng vào thông tin.');
    $rp = auto_repeats($body, $kw);
    $check('repeat', !$rp, 4, 'Nội dung lặp lại: ' . implode('; ', array_slice($rp, 0, 3)) . '. Mỗi dữ kiện chỉ nên nêu đầy đủ một lần.');

    $score = 0; $issues = [];
    foreach ($checks as $c) { if ($c['ok']) $score += $c['weight']; else $issues[] = $c['detail']; }

    $warnings = [];   // số liệu lạ chỉ cảnh báo để biên tập đọc lại, không chặn đăng
    $unknown = auto_unknown_numbers($body, $facts . "\n" . (string)($row['note'] ?? ''));   // số do biên tập ghi trong ô Ghi chú cũng là dữ kiện
    if ($unknown) $warnings[] = 'Cần kiểm tra số liệu chưa có trong bộ dữ kiện: ' . implode('; ', array_slice($unknown, 0, 5)) . '.';
    $ol = auto_outline_h2((string)($row['note'] ?? ''));   // bài có bám dàn ý biên tập không (chỉ cảnh báo: biên tập có thể đã dặn bỏ mục)
    if ($ol) {
        $gh = array_map(fn($x) => trim(preg_replace('/^\d{1,2}\s*[.):]\s+/u', '', $x) ?? $x), $hm[1] ?? []);
        $miss = [];
        foreach ($ol as $o) {
            $tk = auto_tok($o); $best = 0.0;
            foreach ($gh as $g) { $best = max($best, $tk ? auto_hit($tk, $g) : 0.0); }
            if ($best < 0.5) $miss[] = '“' . mb_substr($o, 0, 60, 'UTF-8') . '”';
        }
        if ($miss) $warnings[] = 'Chưa thấy các mục theo dàn ý: ' . implode('; ', array_slice($miss, 0, 4)) . ' (bỏ qua nếu bạn đã dặn bỏ mục này).';
    }

    $bad = [];   // cụm cấm (quảng cáo quá đà) là lỗi nặng: không cho đăng
    $bad = auto_forbidden_hits($post['title'] . ' ' . $body, (string)$s['forbidden']);
    if ($bad) { $issues[] = 'Có cụm không được dùng: ' . implode(', ', $bad) . '.'; $score = min($score, 49); }
    return ['score' => $score, 'issues' => $issues, 'words' => $w, 'warnings' => $warnings, 'checks' => $checks, 'force' => (bool)$bad];
}

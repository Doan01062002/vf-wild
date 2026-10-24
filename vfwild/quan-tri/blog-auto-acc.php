<?php
/**
 * Kiến thức phụ kiện cho bài "Phụ kiện & độ xe" VF Wild, rút từ các bài trên chungauto.vn (tạo bằng scratchpad ca_build_acc.py ngày 23/10/2026).
 * Chỉ nạp nhóm khớp từ khóa (tối đa 2 nhóm) + phần chung, nối vào bộ dữ kiện khi viết bài phụ kiện. Muốn sửa dữ kiện thì sửa ở đây hoặc ghi vào ô Ghi chú của dòng Sheet.
 */
declare(strict_types=1);

if (!defined('VFW_ADMIN')) { http_response_code(404); exit; }

const AUTO_ACC_GENERAL = <<<'TXT'
## Phụ kiện và độ xe tại ChungAuto (theo chungauto.vn)
- ChungAuto (chungauto.vn) bán và lắp đặt phụ kiện ô tô, gồm: màn hình Android, Android box, camera hành trình, camera 360, cảm biến áp suất lốp, cảm biến lùi, dán phim cách nhiệt, dán PPF, độ loa, cách âm chống ồn, độ đèn (bi LED, bi laser, bi gầm), LED nội thất, trần sao, bọc ghế da, thảm lót sàn, cửa hít, smart key, cruise control, HUD, dán decal, bậc lên xuống, chăm sóc nội thất và ngoại thất.
- chungauto.vn có các bài tư vấn phụ kiện riêng cho VF Wild: nắp thùng, lót thùng, lều nóc, tời, ống thở, giảm xóc, lốp, mâm, body kit, thanh thể thao, bảo vệ pin, ốp cua lốp, thảm lót sàn, phim cách nhiệt, camera 360, camera hành trình, cảm biến áp suất lốp, cảm biến va chạm, decal, độ đèn, độ loa, ghế điện, Android box, màn hình Android và các gói độ.
- Khách nhận báo giá phụ kiện qua tổng đài miễn phí 1800 8282 hoặc nhắn Zalo (nút Nhận báo giá qua Zalo trên chungauto.vn); phụ kiện được lắp tại các showroom ChungAuto.
TXT;

/** nhóm => [mẫu khớp trên từ khóa đã bỏ dấu, dữ kiện]; thứ tự là thứ tự ưu tiên. */
const AUTO_ACC_FACTS = [
    'nap-thung' => ['/nap-thung|nap-cuon/', <<<'TXT'
## Phụ kiện: Nắp thùng (theo chungauto.vn)
- Nắp thùng được lắp phía trên thùng hàng của xe bán tải, giúp che chắn hàng hóa trước bụi bẩn, nước mưa và tác động từ môi trường tùy thiết kế, đồng thời giúp khu vực thùng xe trông gọn gàng, liền mạch hơn.
- Theo chungauto.vn, nắp thùng VF Wild đáng cân nhắc nếu thường dùng thùng xe để chứa hành lý, đồ camping hoặc hàng hóa; nếu thường chở đồ cồng kềnh và cần lấy hàng liên tục thì nên ưu tiên nắp cuộn hoặc nắp gập để thao tác linh hoạt.
- Nắp thùng cuộn có thể cuộn về phía trước hoặc phía sau để mở một phần hoặc toàn bộ thùng, phù hợp người thường xuyên chở hàng mà vẫn muốn giữ ngoại hình xe gọn.
- Nắp thùng thấp có thiết kế tương đối sát thành thùng, giữ kiểu dáng bán tải nguyên bản, phù hợp người ưu tiên thẩm mỹ và dùng xe hằng ngày.
- Nắp thùng cao tạo thêm không gian chứa đồ theo chiều cao, hợp với người mang nhiều hành lý hoặc thiết bị camping, nhưng làm thay đổi đáng kể diện mạo phía sau xe.
- Nắp thùng cứng dùng kết cấu cứng, chắc chắn và che chắn tốt hơn tùy thiết kế; nắp thùng mềm dùng vật liệu mềm hoặc dạng bạt, trọng lượng nhẹ, thao tác đơn giản, phù hợp nhu cầu che chắn cơ bản.
- Nắp thùng điện tích hợp mô-tơ và hệ thống điều khiển để đóng mở thuận tiện hơn, và thường có giá cao hơn nắp cơ hoặc nắp gập.
- Giá nắp thùng phụ thuộc vào kiểu thiết kế, thương hiệu, vật liệu và cơ cấu đóng mở.
- Lắp nắp thùng sẽ bổ sung trọng lượng cho xe và thay đổi một phần thiết kế; mỗi loại nắp có trọng lượng khác nhau, và một số loại có thể giới hạn chiều cao hàng hóa chở trong thùng.
- Không phải loại nắp thùng nào cũng chống nước tuyệt đối; khả năng chống nước phụ thuộc vào thiết kế, gioăng và kỹ thuật lắp đặt.
- Tùy sản phẩm và bộ gá, nắp thùng có phương án lắp đặt khác nhau nên cần kiểm tra trước khi thi công; nơi lắp cần kiểm tra cấu hình xe trước khi lắp, căn chỉnh nắp chính xác và kiểm tra đóng mở sau thi công.
- Nguồn: https://chungauto.vn/nap-thung-vf-wild-chon-loai-nao-mau-dep-va-gia-moi-nhat-2755.html
TXT],
    'lot-thung' => ['/lot-thung/', <<<'TXT'
## Phụ kiện: Lót thùng (theo chungauto.vn)
- Lót thùng VF Wild là lớp phủ được thiết kế theo kích thước lòng thùng xe, giúp hạn chế tác động trực tiếp lên bề mặt thùng nguyên bản; chất liệu thường là nhựa, cao su hoặc vật liệu chống trượt.
- Lót thùng giúp hạn chế trầy xước lòng thùng khi đặt hoặc kéo hàng, giảm bụi bẩn và dễ vệ sinh, hạn chế nước và bùn đất tiếp xúc trực tiếp với thùng, tăng độ bám để hạn chế hàng hóa trượt khi xe di chuyển.
- Lót thùng bằng nhựa HDPE phổ biến nhờ trọng lượng tương đối nhẹ, chịu va đập tốt, dễ vệ sinh và có độ bền cao, phù hợp nhu cầu chở hàng thường xuyên.
- Lót thùng bằng cao su mềm và tạo ma sát, hợp với người thường chở vật dụng dễ xê dịch; khi chọn cần chú ý độ dày, khả năng thoát nước và độ bền của vật liệu.
- Lót thùng chống trượt có bề mặt vân hoặc hoa văn tăng ma sát; lót thùng chống nước hạn chế nước bẩn tiếp xúc với lòng thùng và dễ vệ sinh sau khi chở đồ hoặc đi thời tiết xấu.
- Theo chungauto.vn (tháng 10/2026), lót thùng VF Wild có giá tham khảo khoảng 3,3 – 4,4 triệu đồng: Maxliner Overrail (tràn viền, HDPE) 3.300.000 – 3.800.000 đồng, Maxliner Underrail (cắt viền, giấu viền, HDPE) 3.500.000 – 4.400.000 đồng, Aeroklas Bedliner (có viền hoặc không viền, HDPE dẻo) 3.300.000 – 3.500.000 đồng.
- Theo chungauto.vn, lót thùng Maxliner Overrail, Maxliner Underrail và Aeroklas Bedliner cho VF Wild đều được bảo hành 12 tháng.
- Khi chọn lót thùng cần kiểm tra kích thước có vừa lòng thùng VF Wild, chất liệu chịu va đập và thời tiết, bề mặt chống trượt, có ảnh hưởng đến móc cố định hàng hóa hay phụ kiện khác không, phương pháp lắp có cần khoan cắt thùng nguyên bản không, và chính sách bảo hành.
- Nếu thường chở hàng nặng nên ưu tiên vật liệu cứng, chịu va đập tốt; nếu thường chở đồ camping, hành lý, vật dụng nhỏ nên ưu tiên loại chống trượt; nếu đề cao thẩm mỹ thì mẫu Underrail giấu viền cho cảm giác gọn và liền mạch hơn.
- Nên chọn lót thùng thiết kế riêng cho VF Wild thay vì loại kích thước chung để lắp chính xác và hạn chế ảnh hưởng đến chi tiết nguyên bản.
- Việc lắp lót thùng thường khá nhanh và không cần can thiệp sâu vào kết cấu xe.
- Lót thùng cần được chọn sao cho không che khuất các vị trí thoát nước hoặc điểm cố định hàng hóa trong thùng.
- Nguồn: https://chungauto.vn/lot-thung-vf-wild-bao-ve-long-thung-chong-tray-xuoc-hieu-qua-2768.html
TXT],
    'leu-noc' => ['/(^|-)leu(-|$)/', <<<'TXT'
## Phụ kiện: Lều nóc (theo chungauto.vn)
- Lều nóc là loại lều lắp phía trên nóc xe thông qua hệ thống giá đỡ hoặc thanh giá nóc phù hợp; khi đến điểm nghỉ chân, lều được mở ra thành không gian nghỉ ngơi riêng mà không cần tìm mặt bằng để dựng.
- So với lều cắm trại thông thường, lều nóc không cần mặt bằng phẳng, tiết kiệm thời gian dựng và thu gọn, tận dụng không gian phía trên xe và tiết kiệm không gian bên trong khoang xe.
- Tùy thiết kế, lều nóc có thể mở và thu gọn tương đối nhanh; người dùng đỗ xe ở vị trí phù hợp rồi mở lều theo hướng dẫn của nhà sản xuất.
- Lều nóc dạng vỏ cứng có phần thân bảo vệ bên ngoài, khi đóng tạo thành khối tương đối gọn trên nóc xe, thao tác đóng mở thuận tiện.
- Lều nóc dạng vải gấp có thể mở rộng không gian khi dựng, phù hợp người cần không gian nghỉ rộng hơn nhưng vẫn muốn thu gọn khi di chuyển.
- Lều nóc có thể kết hợp với thang, mái che, baga và nhiều phụ kiện dã ngoại khác để tạo thành bộ setup camping hoàn chỉnh.
- Lều nóc có các cỡ cho 1 – 2 người, 2 – 3 người và loại lớn cho gia đình; lều càng lớn càng cần chú ý kích thước, trọng lượng tổng thể và hệ thống giá đỡ phù hợp.
- Khi chọn lều nóc không nên chỉ nhìn diện tích sử dụng mà cần kiểm tra đồng thời trọng lượng lều, kích thước khi đóng, tải trọng của hệ thống giá đỡ và các thông số kỹ thuật liên quan.
- Theo chungauto.vn (tháng 10/2026), lều nóc cho VF Wild có giá tham khảo từ 85.000.000 đồng/bộ với iKamper Skycamp 3.0 (sức chứa 3 – 4 người lớn) và từ 29.500.000 đồng/bộ với Wild Land Desert Cruiser 120 (sức chứa 2 người lớn); giá có thể thay đổi theo thời điểm, phiên bản và phụ kiện đi kèm.
- Giá lều nóc phụ thuộc vào thương hiệu, thiết kế, kích thước, sức chứa và trang bị đi kèm.
- Khi lắp lều nóc, nơi thi công cần kiểm tra xe trước khi lắp, kiểm tra hệ thống giá đỡ, thi công đúng kỹ thuật và kiểm tra lại các điểm cố định sau khi lắp.
- Nguồn: https://chungauto.vn/leu-noc-vf-wild-loai-nao-tot-tu-van-kinh-nghiem-va-chi-phi-lap-dat-2744.html
TXT],
    'toi' => ['/(^|-)toi(-|$)/', <<<'TXT'
## Phụ kiện: Tời kéo (theo chungauto.vn)
- Tời là thiết bị dùng mô-tơ và dây cáp hoặc dây tổng hợp để tạo lực kéo, thường lắp ở phía trước xe; khi xe bị mắc kẹt, tời kết hợp với điểm neo chắc chắn để hỗ trợ đưa xe ra khỏi vị trí khó.
- Tời được dùng để kéo xe khi sa lầy trong bùn hoặc cát, hỗ trợ vượt rãnh, dốc hoặc địa hình khó, cứu hộ khi đi off-road theo nhóm, và giúp tự xử lý sự cố ở khu vực ít phương tiện cứu hộ.
- Theo chungauto.vn, tời đáng cân nhắc nếu VF Wild thường đi đường đất, bùn, cát, camping xa khu dân cư, đi cung đường off-road hoặc nơi khó tiếp cận xe cứu hộ; nếu xe chủ yếu chạy phố, cao tốc, đường nhựa và hiếm khi đi địa hình thì lắp tời có thể chưa thực sự cần thiết.
- Tời điện dùng mô-tơ để cuộn dây và tạo lực kéo, người dùng điều khiển quá trình kéo qua bộ điều khiển đi kèm; tời cơ dùng cơ cấu truyền động để tạo lực kéo và yêu cầu hệ thống lắp đặt phù hợp với từng mẫu xe.
- Dây tời có hai loại thường gặp: cáp thép có độ bền cơ học cao, phù hợp môi trường khắc nghiệt nhưng nặng hơn; dây tổng hợp nhẹ, dễ thao tác và thường được chọn cho các bộ tời hiện đại.
- Không nên chỉ chọn tời có lực kéo càng lớn càng tốt; lực kéo cần cân nhắc theo trọng lượng thực tế của xe, tải trọng hàng hóa và phụ kiện trên xe, mức độ sa lầy, độ dốc và bề mặt địa hình, góc kéo, điểm neo và cấu trúc lắp đặt.
- Theo chungauto.vn (tháng 10/2026), tời Warn VR EVO 10-S (cáp mềm) cho VF Wild có giá tham khảo 21.800.000 – 22.500.000 đồng; thương hiệu Mỹ, lắp ráp tại Trung Quốc, sức kéo tối đa 10.000 lbs (khoảng 4.536 kg), cáp mềm Synthetic dài 27,4 m.
- Theo chungauto.vn (tháng 10/2026), tời Runva 9500 lbs (dòng EWX) cho VF Wild có giá tham khảo 7.500.000 – 9.000.000 đồng tùy chọn cáp cứng hoặc cáp mềm; thương hiệu Trung Quốc, sức kéo tối đa 9.500 lbs (khoảng 4.309 kg), dùng cáp thép (bản tiêu chuẩn) hoặc cáp mềm (bản cao cấp).
- Giá tời phụ thuộc vào thương hiệu, lực kéo, loại dây, mô-tơ, bộ điều khiển và phụ kiện đi kèm, và có thể thay đổi theo cấu hình xe và phương án lắp đặt; khi mua nên kiểm tra nguồn gốc, thông số kỹ thuật, chính sách bảo hành và phụ kiện đi kèm.
- Khi dùng tời không vượt quá giới hạn tải kéo, kiểm tra dây hoặc cáp trước mỗi lần dùng, đảm bảo điểm neo đủ chắc, không đứng trong đường kéo của dây, dùng phụ kiện đúng tải trọng và giữ khoảng cách an toàn với dây kéo.
- Nên vệ sinh và kiểm tra tời sau khi đi bùn, cát hoặc nước.
- Không tự ý thay đổi kết cấu hoặc hệ thống điện của xe khi lắp tời nếu không có chuyên môn; nơi lắp cần kiểm tra trực tiếp cấu hình xe, chọn bộ gá phù hợp và kiểm tra hệ thống sau khi hoàn thiện.
- Nguồn: https://chungauto.vn/toi-vf-wild-giai-phap-keo-xe-khi-di-dia-hinh-kho-2783.html
TXT],
    'ong-tho' => ['/ong-tho/', <<<'TXT'
## Phụ kiện: Ống thở (snorkel) (theo chungauto.vn)
- Theo chungauto.vn, ống thở là phụ kiện lắp từ khu vực đường khí nạp lên vị trí cao hơn trên thân xe, giúp cửa hút khí nằm cao hơn so với đường hút gió nguyên bản.
- Theo chungauto.vn, khi lắp đúng kỹ thuật, ống thở có thể hỗ trợ giảm nguy cơ nước xâm nhập vào đường hút khí khi xe đi qua khu vực có nước, và hữu ích trong môi trường nhiều bụi, đường bùn đất.
- Ống thở không đồng nghĩa với việc xe có thể lội nước ở mọi điều kiện; khả năng lội nước còn phụ thuộc vào thiết kế xe, độ sâu nước, tốc độ di chuyển và nhiều bộ phận khác.
- Theo chungauto.vn, độ ống thở phù hợp với chủ VF Wild thường đi rừng, đường bùn đất, khu vực có nước hoặc cung đường off-road; nếu xe chủ yếu chạy trong thành phố, đường bằng phẳng thì ống thở không phải nhu cầu bắt buộc.
- Các kiểu ống thở phổ biến gồm loại thiết kế theo form VF Wild, phong cách off-road, đầu hút khí tiêu chuẩn và các mẫu có đầu hút đặc trưng; nên ưu tiên sản phẩm có kích thước và vị trí lắp phù hợp với VF Wild thay vì chỉ nhìn kiểu dáng.
- Ống thở có thể làm từ nhựa ABS, composite hoặc vật liệu khác tùy nhà sản xuất; khi chọn cần xem cả độ hoàn thiện, độ khớp với xe, bộ phụ kiện đi kèm và khả năng cố định chắc chắn.
- Theo chungauto.vn (tháng 10/2026), ống thở Safari Armax Snorkel cho VF Wild có giá tham khảo 11.000.000 – 13.500.000 đồng; sản phẩm nhập khẩu từ Úc, nhựa Polyethylene chống tia UV (UV20 – UV24), đầu hút kiểu Air Ram vuông hướng thẳng về phía trước.
- Theo chungauto.vn (tháng 10/2026), ống thở TJM Airtec Snorkel cho VF Wild có giá tham khảo 7.500.000 – 9.500.000 đồng; sản phẩm nhập khẩu từ Úc, nhựa Polyethylene nhẹ, chịu va đập (có tùy chọn bản Inox), đầu hút Wedgetail dẹt vuốt ngược theo cột A giúp giảm rít gió hoặc đầu Standard truyền thống.
- Ngoài tiền sản phẩm, nên hỏi rõ chi phí lắp ống thở nếu sản phẩm cần khoan, cắt hoặc điều chỉnh vị trí lắp để dự toán chi phí chính xác.
- Việc lắp ống thở liên quan trực tiếp đến vị trí đường khí nạp, quy trình có thể thay đổi tùy thiết kế sản phẩm, nên không tự ý khoan hoặc can thiệp vào xe khi chưa xác định chính xác phương án lắp.
- Hiệu quả của ống thở phụ thuộc vào thiết kế sản phẩm, khả năng tương thích và chất lượng thi công; trước khi lắp nên kiểm tra kỹ sản phẩm, phương án lắp đặt và hệ thống khí nạp của xe.
- Bài trên chungauto.vn chưa nói rõ ống thở nối với bộ phận nào trên hệ truyền động REEV của VF Wild (động cơ xăng chỉ làm máy phát) và giúp xe lội nước sâu tới đâu; phần này cần kỹ thuật viên ChungAuto xác nhận trước khi lắp.
- Nguồn: https://chungauto.vn/ong-tho-vf-wild-khi-nao-nen-lap-va-chon-loai-nao-phu-hop-2803.html
TXT],
    'giam-xoc' => ['/giam-xoc|phuoc|nang-gam/', <<<'TXT'
## Phụ kiện: Giảm xóc (phuộc) (theo chungauto.vn)
- Giảm xóc là bộ phận thuộc hệ thống treo, có nhiệm vụ kiểm soát dao động của lò xo và bánh xe khi xe đi qua mặt đường không bằng phẳng; giảm xóc hoạt động cùng lò xo để duy trì sự ổn định của thân xe và giúp bánh xe duy trì độ bám mặt đường.
- Theo chungauto.vn, có thể cân nhắc nâng cấp giảm xóc VF Wild khi thường đi đường xấu nhiều ổ gà, chở nhiều hành lý hoặc thiết bị, đi camping hay địa hình gồ ghề, muốn thay đổi độ cứng mềm, muốn tăng kiểm soát dao động thân xe, hoặc khi giảm xóc nguyên bản đã xuống cấp; nếu xe vẫn vận hành ổn định thì không nhất thiết phải thay.
- Giảm xóc nguyên bản giữ đặc tính vận hành theo thiết kế ban đầu; giảm xóc độ hướng đến người muốn thay đổi đặc tính hệ thống treo, tùy sản phẩm có thể điều chỉnh độ cứng mềm.
- Phuộc monotube dùng thiết kế ống đơn, thường được chọn trong hệ thống treo nâng cấp nhờ khả năng kiểm soát dao động và tản nhiệt; phuộc có bình dầu phụ có cấu tạo phức tạp hơn, hướng đến điều kiện sử dụng khắc nghiệt hơn, bình dầu phụ có thể bố trí bên ngoài.
- Giảm xóc trước kiểm soát dao động ở cầu trước và ảnh hưởng đến cảm giác đánh lái; giảm xóc sau chịu tác động lớn từ tải trọng khu vực thùng xe, đặc biệt khi thường chở hàng hoặc mang nhiều thiết bị.
- Theo chungauto.vn (tháng 10/2026), bộ phuộc Profender Monotube 2.0 (điều chỉnh 8 nấc cứng mềm) cho VF Wild có giá tham khảo 9.000.000 – 11.000.000 đồng chưa gồm lò xo, hoặc khoảng 31.000.000 đồng trọn bộ gồm lò xo và bình dầu phụ.
- Theo chungauto.vn (tháng 10/2026), bộ phuộc Profender Queen Series (ống đơn, chỉnh 16 nấc độc lập) cho VF Wild có giá tham khảo từ 25.000.000 đồng.
- Theo chungauto.vn (tháng 10/2026), bộ phuộc ARB Old Man Emu BP-51 (Internal Bypass, chỉnh 10 nấc nén và 10 nấc xả) cho VF Wild có giá tham khảo 55.000.000 – 73.500.000 đồng trọn bộ gồm phuộc, lò xo trước và pát gắn bình dầu.
- Theo chungauto.vn, phuộc Profender Monotube 2.0 và Profender Queen Series được bảo hành 24 tháng; ARB Old Man Emu BP-51 được bảo hành 3 năm không giới hạn km.
- Dấu hiệu giảm xóc cần kiểm tra gồm xe dao động nhiều sau gờ hoặc ổ gà, thân xe nhún bất thường, xe không ổn định khi vào cua, rò rỉ dầu ở thân phuộc, lốp mòn không đều; các dấu hiệu này không phải lúc nào cũng do giảm xóc nên cần kiểm tra tổng thể hệ thống treo trước khi thay.
- Nếu xe chủ yếu đi phố thì không nhất thiết dùng bộ phuộc cấu hình quá cao; xe thường chở hàng cần chọn phuộc và lò xo phù hợp tải trọng thực tế; nên chọn phuộc trước và sau đồng bộ thay vì chỉ thay một bộ phận.
- Nếu nâng cấp hệ thống treo theo hướng thay đổi chiều cao xe, cần kiểm tra sự tương thích với các bộ phận liên quan và thiết lập lại hệ thống sau khi lắp; phuộc tốt nhưng lắp đặt hoặc căn chỉnh không phù hợp vẫn có thể ảnh hưởng đến trải nghiệm vận hành.
- Thay giảm xóc có thể làm thay đổi đặc tính vận hành của hệ thống treo, mức độ phụ thuộc vào loại phuộc, lò xo, chiều cao xe và thiết lập của từng bộ sản phẩm.
- Nguồn: https://chungauto.vn/giam-xoc-vf-wild-gia-loai-phuoc-va-kinh-nghiem-lua-chon-2772.html
TXT],
    'op-cua-lop' => ['/cua-lop/', <<<'TXT'
## Phụ kiện: Ốp cua lốp (theo chungauto.vn)
- Ốp cua lốp là phụ kiện lắp quanh khu vực vòm bánh, tạo điểm nhấn cho phần hông xe và giúp tổng thể ngoại thất trông rộng, khỏe hơn; tùy thiết kế có phong cách nguyên bản, thể thao hoặc hầm hố cho xe đi địa hình.
- Ốp cua lốp giúp xe trông mạnh mẽ hơn, tăng điểm nhấn khu vực bánh xe, hỗ trợ bảo vệ mép vè trước tác động nhỏ từ đất đá, bùn bắn, và hoàn thiện ngoại hình khi nâng cấp mâm, lốp.
- Theo chungauto.vn, VF Wild đi phố với cấu hình nguyên bản có thể chọn ốp thiết kế gọn; xe theo phong cách camping, địa hình hoặc đã nâng cấp mâm lốp thì ốp bản rộng hoặc thiết kế góc cạnh giúp tổng thể đồng bộ hơn.
- Ốp kiểu nguyên bản có thiết kế gọn, ít thay đổi đường nét thân xe; ốp phong cách off-road góc cạnh, khỏe khoắn hơn, hợp với xe đã nâng cấp mâm, lốp hoặc phụ kiện địa hình.
- Ốp vè cua lốp bản rộng giúp vòm bánh nổi bật hơn, nhưng cần kiểm tra kích thước trước khi lắp để không ảnh hưởng đến khoảng hở giữa ốp và lốp.
- Các kiểu dáng ốp cua lốp gồm bản nhỏ (gọn, hợp đi phố), bản rộng (cơ bắp, hợp xe độ mâm lốp), góc cạnh (hầm hố, hợp off-road), sơn đen (dễ phối ngoại thất) và loại có chi tiết trang trí (xe độ cá nhân); cần kiểm tra độ tương thích với vè xe và kích thước lốp trước khi chọn.
- Theo chungauto.vn (tháng 10/2026), ốp cua lốp RBS bản lớn có đinh cho VF Wild có giá vật tư tham khảo 3.800.000 – 4.500.000 đồng cho bộ 4 bánh, chi phí lắp đặt dự kiến 400.000 – 600.000 đồng; sản phẩm của hãng RBS, xuất xứ Thái Lan, nhựa ABS chịu lực, bề mặt nhám đen.
- Theo chungauto.vn (tháng 10/2026), ốp cua lốp thể thao Victor (Raptor Style) cho VF Wild có giá vật tư tham khảo 3.200.000 – 4.200.000 đồng cho bộ 4 bánh, chi phí lắp đặt dự kiến 400.000 – 600.000 đồng; xuất xứ Thái Lan, nhựa ABS, bề mặt sơn đen mờ hoặc sơn theo màu xe.
- Khi mua ốp cua lốp nên hỏi rõ giá đã bao gồm chi phí lắp đặt hay chưa.
- Tùy thiết kế, ốp cua lốp có thể lắp bằng ngàm, vị trí cố định sẵn hoặc vít và bộ phụ kiện đi kèm; không nên mặc định mọi mẫu đều không cần khoan hoặc đều phải khoan, cần kiểm tra phương án lắp cụ thể trước khi thi công.
- Nơi lắp nên kiểm tra trực tiếp cấu hình mâm, lốp của xe trước khi lắp ốp cua lốp.
- Nguồn: https://chungauto.vn/op-cua-lop-vf-wild-chon-mau-nao-dep-va-phu-hop-cho-xe-2784.html
TXT],
    'lop-mam' => ['/(^|-)(?<!cua-|suat-)lop(-|$)|(^|-)mam(-|$)|la-zang|(^|-)vanh(-|$)/', <<<'TXT'
## Phụ kiện: Lốp và mâm (theo chungauto.vn)
- Lốp là bộ phận tiếp xúc trực tiếp với mặt đường, đảm nhiệm truyền lực, phanh và giữ ổn định cho xe; với mẫu bán tải như VF Wild, khi chọn lốp cần chú ý khả năng chịu tải và điều kiện vận hành thực tế.
- Các thông số lốp cần quan tâm gồm chiều rộng lốp (ảnh hưởng diện tích tiếp xúc mặt đường), tỷ lệ thành lốp (liên quan độ êm và hấp thụ dao động), đường kính mâm (phải tương thích với mâm), chỉ số tải trọng và chỉ số tốc độ.
- Lốp HT (Highway Terrain) hướng đến vận hành trên đường nhựa, gai lốp ưu tiên độ êm và tiếng ồn thấp hơn lốp chuyên địa hình; lốp AT (All-Terrain) có gai đa dụng cho cả đường nhựa lẫn đường đất, sỏi, địa hình nhẹ.
- Lốp R/T (Rugged Terrain) nằm giữa AT và MT, thiên về địa hình nhưng vẫn dùng được trên đường nhựa; lốp MT (Mud Terrain) có gai lớn, khoảng cách giữa các khối gai rộng, bám đường trong bùn đất, đá, địa hình khó, nhưng có đặc tính vận hành và tiếng ồn khác lốp đường trường nên không tối ưu nếu chủ yếu đi phố.
- Theo chungauto.vn, chọn lốp cho VF Wild theo nhu cầu: đi phố, cao tốc dùng HT; đi phố kết hợp camping dùng AT; đi phố kèm địa hình thường xuyên dùng AT hoặc R/T; địa hình khó, bùn đất dùng MT; ưu tiên ngoại hình hầm hố có thể chọn AT, R/T hoặc MT.
- Nếu VF Wild dùng hằng ngày và chỉ thỉnh thoảng đi địa hình, nên ưu tiên sự cân bằng giữa độ êm, tiếng ồn và độ bám đường thay vì chọn lốp quá thiên địa hình chỉ vì ngoại hình.
- Theo chungauto.vn (tháng 10/2026), lốp BFGoodrich All-Terrain T/A KO2 có giá tham khảo mỗi quả: mâm 16 inch từ 5.400.000 đến 6.100.000 VNĐ, mâm 17 inch từ 5.000.000 đến 6.400.000 VNĐ, mâm 18 inch từ 6.200.000 đến 7.400.000 VNĐ, mâm 20 inch từ 8.500.000 đến 10.500.000 VNĐ trở lên.
- Theo chungauto.vn (tháng 10/2026), lốp Radar Renegade R/T có giá tham khảo mỗi quả: mâm 16 inch từ 2.900.000 đến 3.300.000 VNĐ, mâm 17 inch từ 3.400.000 đến 3.900.000 VNĐ, mâm 18 inch từ 3.800.000 đến 4.500.000 VNĐ, mâm 20 inch từ 4.600.000 đến 5.800.000 VNĐ.
- Các loại mâm cho VF Wild gồm mâm nguyên bản theo xe, mâm đúc, mâm hợp kim và mâm độ theo phong cách thể thao hoặc off-road; độ mâm là thay bộ mâm nguyên bản bằng bộ mâm có thiết kế, chất liệu, màu sắc hoặc kích thước khác.
- Các phong cách mâm cho VF Wild gồm thể thao (đa chấu, chấu chữ Y, góc cạnh), off-road (thiết kế 5 đến 6 chấu hoặc dạng hình học mạnh), tối giản (ít chi tiết), và mâm màu đen dễ kết hợp với nhiều màu thân xe và phụ kiện ngoại thất.
- Mâm 18 inch có thể đi với lốp thành cao hơn, ngoại hình cân đối và chi phí thường dễ tiếp cận hơn; mâm 20 inch đi với lốp thành thấp hơn, tạo diện mạo thể thao, nổi bật hơn, chi phí có thể cao hơn và cần kiểm tra kỹ độ tương thích.
- Thông số mâm cần kiểm tra gồm đường kính, chiều rộng, PCD (khoảng cách giữa các vị trí bắt bu-lông, bắt buộc phải tương thích), offset ET (vị trí bánh so với thân xe, sai có thể khiến bánh nhô ra hoặc thụt vào quá nhiều), Center Bore (phải phù hợp moay-ơ) và tải trọng mâm, yếu tố đặc biệt quan trọng với xe bán tải.
- Cần thận trọng khi tăng kích thước mâm quá nhiều, chọn mâm không đủ tải trọng, PCD hoặc ET không phù hợp, hoặc kết hợp mâm và lốp không tương thích; kích thước mâm và lốp cần đối chiếu với thông số kỹ thuật của phiên bản VF Wild trước khi thay đổi.
- Theo chungauto.vn (tháng 10/2026), mâm phong cách bán tải, off-road có giá tham khảo: Fuel Off-Road Fuel Covert D694 (17 hoặc 18 inch) từ 8.500.000đ/cái, Black Rhino Boxer (17 hoặc 18 inch) từ 7.800.000đ/cái, Lenso Venom (18 hoặc 20 inch) từ 6.500.000đ/cái.
- Khi thay lốp hoặc mâm, nơi thi công cần có thiết bị tháo lắp chuyên dụng, cân bằng động sau khi thay, kiểm tra áp suất và độ kín, và tư vấn rõ thông số trước khi lắp.
- Nguồn: https://chungauto.vn/lop-vf-wild-cam-nang-chon-lop-theo-nhu-cau-va-ngan-sach-2754.html; https://chungauto.vn/mam-vf-wild-do-mam-cho-dong-xe-ban-tai-vinfast-2742.html
TXT],
    'body-kit' => ['/body-?kit|can-truoc|can-sau/', <<<'TXT'
## Phụ kiện: Body kit (theo chungauto.vn)
- Body kit là tập hợp các chi tiết ngoại thất được thiết kế để thay đổi hoặc tạo điểm nhấn cho kiểu dáng nguyên bản của xe, có thể gồm cản trước, cản sau, ốp thân, ốp cua lốp, lip và các chi tiết trang trí khác.
- Khác với thay một phụ kiện riêng lẻ, body kit hướng đến sự đồng bộ kiểu dáng để phần đầu, thân và đuôi xe tạo thành một tổng thể liền mạch; ca lăng cũng là chi tiết thường được chủ VF Wild quan tâm khi nâng cấp phần đầu xe.
- Độ body kit cho VF Wild phù hợp chủ xe muốn diện mạo khác biệt so với nguyên bản, với các phong cách thể thao, hầm hố, cá tính hoặc tối giản.
- Một bộ body kit cho VF Wild có thể gồm cản hoặc lip trước, cản sau hoặc chi tiết điểm nhấn phần đuôi, ốp hông, ốp thân, ốp cua lốp, lip hoặc cánh gió, chi tiết trang trí đầu xe và ca lăng tùy thiết kế; không phải bộ nào cũng có đủ, nên khi mua cần kiểm tra rõ danh sách chi tiết.
- Body kit trước tập trung vào khu vực đầu xe như cản, lip và chi tiết mở rộng, thường kết hợp cùng ca lăng; body kit sau tập trung vào cản sau và chi tiết phần đuôi.
- Body kit full xe cho VF Wild nâng cấp đồng thời phần trước, sau, hai bên thân và chi tiết trang trí theo cùng một phong cách; không nhất thiết độ full ngay từ đầu, nếu ngân sách giới hạn có thể ưu tiên phần đầu hoặc khu vực muốn thay đổi nhiều nhất rồi nâng cấp thêm sau.
- Body kit trên thị trường có thể làm từ ABS, PU, FRP/composite hoặc vật liệu khác tùy nhà sản xuất, mỗi loại khác nhau về độ cứng, trọng lượng, khả năng hoàn thiện và chi phí; không nên chỉ dựa vào tên vật liệu mà cần xem cả độ hoàn thiện, form sản phẩm và phương án lắp.
- Với body kit chính hãng cho VF Wild, nên kiểm tra nguồn gốc, thông tin sản phẩm và chính sách bảo hành trước khi chọn.
- Giá body kit VF Wild phụ thuộc số lượng chi tiết, chất liệu, thương hiệu và phương án thi công; tại thời điểm bài viết, chungauto.vn chưa công bố giá niêm yết chính thức.
- Khi độ body kit cần cân nhắc nhu cầu sử dụng thực tế, ngân sách và các phụ kiện đã lắp trước đó để tránh chi tiết không đồng nhất, và việc lắp cần do kỹ thuật viên có kinh nghiệm thực hiện theo từng bước để các chi tiết khớp với thân xe.
- Nguồn: https://chungauto.vn/body-kit-vf-wild-thay-doi-dien-mao-tang-chat-rieng-cho-xe-2828.html
TXT],
    'thanh-the-thao' => ['/thanh-the-thao|roll-bar/', <<<'TXT'
## Phụ kiện: Thanh thể thao (roll bar) (theo chungauto.vn)
- Thanh thể thao là phụ kiện lắp phía sau cabin, trên khu vực thùng xe bán tải, tạo điểm nhấn cho ngoại hình và giúp xe mang phong cách thể thao, cá tính hơn.
- Khi chọn thanh thể thao cần quan tâm chất liệu, kích thước, vị trí lắp và khả năng kết hợp với các phụ kiện khác như nắp thùng, lót thùng hoặc baga.
- Nếu thường dùng thùng để chở hàng cồng kềnh, nên cân nhắc thiết kế thanh và khoảng không còn lại trong thùng; nếu xe đã có nắp thùng, cần kiểm tra tương thích trước khi lắp để tránh ảnh hưởng việc đóng mở.
- Các kiểu thanh thể thao phổ biến gồm dạng vai bò (đường cong hoặc phần vai mở rộng hai bên), dạng chữ U (gọn, đường nét đơn giản) và dạng bản lớn (kích thước nổi bật, hợp xe phong cách mạnh mẽ); tên gọi giữa các đơn vị có thể khác nhau nên cần xem hình ảnh và thông số thực tế.
- Thanh thể thao thường làm bằng thép sơn tĩnh điện (độ cứng tốt), inox (bề mặt sáng, chống oxy hóa tốt) hoặc hợp kim (gọn, nhẹ hơn); ngoài chất liệu cần kiểm tra lớp hoàn thiện bề mặt, độ dày và bộ gá đi kèm.
- Theo chungauto.vn (tháng 10/2026), thanh thể thao Hamer Titanium Series HR1902 cho VF Wild có giá tham khảo 17.500.000 – 17.900.000 đồng; thương hiệu Hamer4x4 nhập khẩu nguyên bộ từ Thái Lan, thép ống phi 76 mm dày 2,5 – 3 mm, sơn tĩnh điện đen mịn.
- Theo chungauto.vn (tháng 10/2026), thanh thể thao Option4x4 Roll Bar (dòng MT) cho VF Wild có giá tham khảo 7.500.000 – 8.900.000 đồng tùy phiên bản mã MT; thương hiệu liên doanh lắp ráp tại Việt Nam, thép chịu lực dạng ống hoặc hộp dày 2 – 3 mm, sơn tĩnh điện đen mờ.
- Theo chungauto.vn, thanh thể thao Hamer Titanium Series HR1902 và Option4x4 Roll Bar (dòng MT) đều được bảo hành chính hãng 12 tháng.
- Giá thanh thể thao phụ thuộc vào kiểu dáng, chất liệu, thương hiệu và phương án lắp đặt; một số sản phẩm có bộ gá riêng nên chi phí có thể khác nhau, và xe đã có nắp thùng hoặc phụ kiện trên thùng cần xác định khả năng tương thích trước khi báo giá lắp.
- Việc lắp thanh thể thao nên thực hiện tại xưởng có kinh nghiệm để thanh cân đối, chắc chắn và hạn chế ảnh hưởng đến thùng xe.
- Nguồn: https://chungauto.vn/thanh-the-thao-vf-wild-nang-dien-mao-tang-chat-manh-me-cho-xe-2802.html
TXT],
    'bao-ve-pin' => ['/bao-ve-pin|giap-gam|op-gam|chan-gam/', <<<'TXT'
## Phụ kiện: Tấm bảo vệ pin (theo chungauto.vn)
- Tấm bảo vệ pin VF Wild là tấm giáp lắp tại khu vực bên dưới cụm pin, tạo thêm một lớp che chắn trước những tác động có thể xuất hiện từ mặt đường.
- Khi đi đường xấu, các tình huống thường gặp dưới gầm xe gồm đá dăm bị hất lên, vật cứng nằm trên đường hoặc phần gầm tiếp cận gần mặt đường khi qua đoạn gồ ghề; mức độ tác động phụ thuộc vào tốc độ, địa hình và cách người lái vượt chướng ngại.
- Tấm bảo vệ pin chỉ đóng vai trò bổ sung lớp che chắn, không biến khu vực gầm thành bộ phận không thể bị tác động; người lái vẫn cần chủ động chọn đường và tốc độ phù hợp.
- Tấm bảo vệ pin còn giúp hạn chế nước, bùn đất và bụi bẩn bám vào pin, giữ pin khô ráo và tránh tình trạng oxy hóa.
- Theo chungauto.vn, chọn tấm bảo vệ pin cho VF Wild nên theo cách sử dụng: đi phố có thể ưu tiên phương án gọn; hay đi tỉnh, đường đất nên chú ý phạm vi che chắn; hay đi camping nên kiểm tra khả năng bảo vệ gầm trước cung đường có đá, đất; nếu off-road cần xem xét toàn bộ phần gầm chứ không chỉ pin.
- Trước khi mua tấm bảo vệ pin VF Wild nên kiểm tra thiết kế có dành riêng cho VF Wild hay không, tấm giáp bảo vệ đến khu vực nào, vị trí bắt cố định có phù hợp kết cấu xe, khoảng sáng gầm sau khi lắp, khả năng tháo lắp khi cần kiểm tra hoặc bảo dưỡng, và kết cấu có hợp với điều kiện sử dụng thực tế.
- Nhu cầu bảo vệ gầm pin VF Wild không chỉ dành cho xe off-road mà còn cho xe đi công trường, vùng nông thôn hay qua đường đất, đường đang thi công, và xe đi du lịch dài ngày.
- Quy trình lắp tấm bảo vệ pin VF Wild tại xưởng tập trung vào kiểm tra trước rồi mới cố định tấm giáp; điều quan trọng là tấm bảo vệ phải nằm đúng vị trí và không cản trở các bộ phận xung quanh.
- Theo chungauto.vn (tháng 10/2026), tấm bảo vệ pin Kata cho VF Wild có giá tham khảo 3.590.000 VNĐ; giá phụ thuộc mẫu sản phẩm, vật liệu, phạm vi bảo vệ và chi phí lắp đặt.
- Tấm bảo vệ pin Kata cho VF Wild được bảo hành tại xưởng 12 tháng.
- Theo chungauto.vn, nếu VF Wild chỉ dùng hằng ngày trong đô thị thì tấm bảo vệ pin là hạng mục nâng cấp tùy nhu cầu; nếu xe thường đi đường đất, công trường, camping hoặc off-road thì việc lắp tấm giáp đáng cân nhắc hơn.
- Theo chungauto.vn (tháng 10/2026), tấm bảo vệ pin cho VinFast VF7 có giá tham khảo 3.600.000 – 3.800.000 VNĐ, tùy thương hiệu giáp gầm.
- Nguồn: https://chungauto.vn/bao-ve-pin-vf-wild-giam-rui-ro-va-dap-khi-van-hanh-dia-hinh-2827.html; https://chungauto.vn/boc-ghe-da-cho-vinfast-vf7-tu-van-chi-tiet-bao-gia-2458.html; https://chungauto.vn/dan-phim-ppf-cho-xe-vinfast-vf7-2450.html
TXT],
    'tham' => ['/(^|-)tham(?!-(khao|gia|quan|du|dinh))/', <<<'TXT'
## Phụ kiện: Thảm lót sàn (theo chungauto.vn)
- Thảm lót sàn VF Wild là lớp thảm phủ lên sàn khoang cabin, giúp bảo vệ sàn nguyên bản trước bụi bẩn, nước, đất cát và các tác động trong quá trình sử dụng.
- Một bộ thảm lót sàn phù hợp cần đúng form, nằm ổn định trên sàn, không gây vướng khu vực bàn đạp và không cản trở chuyển động của ghế.
- Cho VF Wild, chungauto.vn giới thiệu 3 dòng thảm: thảm da 360 Lavor Luxury (thiết kế da, phủ rộng, chú trọng thẩm mỹ nội thất), Kata Pro ép khuôn (ép khuôn theo form sàn, chú trọng độ vừa vặn) và Kardo TPV nguyên sinh (vật liệu TPV, chống nước, dễ vệ sinh).
- Theo chungauto.vn, VF Wild chủ yếu đi phố, đi làm hằng ngày hợp với Lavor Luxury; muốn thảm vừa form, gọn sàn hợp với Kata Pro ép khuôn; thường xuyên chạy dưới mưa, đường đất, nhiều bụi bẩn hợp với Kardo TPV nguyên sinh.
- Khi chọn thảm da, ngoài bề mặt và màu sắc nên kiểm tra phần đế, vị trí cố định và độ ôm sàn để hạn chế xê dịch khi sử dụng.
- Theo chungauto.vn (tháng 10/2026), thảm lót sàn cho VF Wild có giá tham khảo 2.500.000 – 4.500.000 đồng với thảm da 360 Lavor Luxury, 2.500.000 – 3.500.000 đồng với Kata Pro ép khuôn và 1.800.000 – 2.700.000 đồng với Kardo TPV nguyên sinh.
- Khi tham khảo giá thảm nên hỏi rõ bộ thảm gồm những vị trí nào và chi phí đã bao gồm công lắp đặt hay chưa.
- Sau khi lắp thảm cần kiểm tra thảm nằm đúng form sàn, mép thảm không cuộn hoặc nhô lên, không che hoặc cản trở bàn đạp, ghế tiến lùi bình thường, thảm được cố định chắc và không có khoảng hở lớn giữa thảm và sàn.
- Nên vệ sinh thảm định kỳ, đặc biệt sau những chuyến đi mưa hoặc đường đất; thảm da cần được vệ sinh bằng phương pháp phù hợp với bề mặt.
- Khi tháo thảm ra vệ sinh nên kiểm tra phần sàn bên dưới để phát hiện nước hoặc bụi bẩn lọt xuống, và cần để thảm khô trước khi lắp lại.
- Có 3 cách trang bị sàn xe: chỉ dùng thảm lót sàn (tiện lợi, nhanh, chi phí rẻ hơn); trải sàn da rồi đặt thêm thảm lót lên trên (sàn sạch, không ngấm nước, nội thất đẹp hơn nhưng chi phí cao hơn và thời gian hoàn thiện lâu hơn); dùng khuôn đúc tràn viền chuẩn form sàn và đặt thêm tấm thảm rối cước lên trên (dễ vệ sinh, lắp đặt nhanh).
- Chungauto.vn khuyên ưu tiên thảm lót sàn bằng nhựa dẻo TPE vì độ bền cao, chống bụi tốt và không gây mùi khó chịu khi dùng lâu.
- Các hãng thảm được chungauto.vn nêu gồm Catrend (chuyên thảm đúc khuôn riêng cho từng dòng xe, chất liệu nhựa dẻo TPE), Lavor (chuyên may sàn da theo kích thước xe), Kata và Vasic (tấm thảm lót sàn).
- Theo chungauto.vn (tháng 10/2026), cho xe Ford Ranger, thảm lót sàn khuôn đúc có giá tham khảo 2.700.000 đồng, thảm lót sàn riêng 600.000 – 3.300.000 đồng, thảm trải sàn 3D kèm thảm lót sàn 3.400.000 – 3.600.000 đồng, thảm trải sàn da kèm thảm lót sàn 4.800.000 – 5.000.000 đồng.
- Nguồn: https://chungauto.vn/tham-lot-san-vf-wild-chon-mau-nao-phu-hop-2816.html; https://chungauto.vn/tham-lot-san-xe-ford-ranger-1170.html
TXT],
    'phim-cach-nhiet' => ['/phim-cach-nhiet|dan-phim(?!-ppf)|dan-kinh/', <<<'TXT'
## Phụ kiện: Phim cách nhiệt (theo chungauto.vn)
- Phim cách nhiệt là lớp màng polyester đa lớp dán vào mặt trong kính xe, giúp ngăn tia UV và tia hồng ngoại (IR), hạ nhiệt cabin, bảo vệ mắt và da người ngồi trong xe và giảm tải cho điều hòa; trên VF Wild, việc giảm tải điều hòa giúp tối ưu quãng đường di chuyển của xe điện.
- Theo chungauto.vn (tháng 10/2026), dán phim cách nhiệt cho VF Wild có giá tham khảo 14.500.000 – 17.500.000 VNĐ với 3M Crystalline, 10.500.000 – 13.000.000 VNĐ với Inmax Bio Ceramic và 8.000.000 – 11.000.000 VNĐ với Ceramax Diamond; giá đã gồm vật tư, vệ sinh kính và công lắp đặt.
- Theo bài phim cách nhiệt VF Wild trên chungauto.vn, 3M Crystalline bảo hành 10 năm, Inmax Bio Ceramic bảo hành 12 năm và Ceramax Diamond bảo hành 10 năm.
- 3M Crystalline có kết cấu phi kim loại từ hơn 200 lớp phim siêu mỏng, cản UV 99.9% và IR 97%; mã kính lái CR60 giảm chói đến 77% và có độ xuyên sáng trên 60%, còn mã kính sườn CR20 tối màu giúp tăng tính riêng tư.
- Inmax Bio Ceramic là thương hiệu Mỹ dùng công nghệ gốm sinh học phản xạ nhiệt, cản UV 99% và IR 95%, kết cấu phi kim loại 100% nên không chặn hay gây nhiễu sóng GPS, sóng 4G và thẻ VETC/ePass.
- Ceramax Diamond là phim gốm nano (Nano Ceramic) của nhà sản xuất Ntech (Hàn Quốc), cản UV 99% và có tỷ lệ lọc quang phổ nhiệt 90%.
- Thời gian dán phim cách nhiệt trên VF Wild là 2,5 – 3 tiếng với 3M Crystalline, 2 – 2,5 tiếng với Inmax Bio Ceramic và 2 tiếng với Ceramax Diamond.
- Theo chungauto.vn, VF Wild có radar, GPS và ví điện tử VETC/ePass nên nên chọn phim phi kim loại; phim chứa gốc kim loại có thể cản sóng, làm điện thoại sụt vạch sóng và gây nhiễu định vị của các thiết bị như camera hành trình.
- Không nên chọn phim nhuộm màu giá rẻ vì cản UV và IR kém, hấp thụ nhiệt khi đỗ xe ngoài nắng làm kính nóng rát, dễ gây nứt kính hoặc làm giảm tuổi thọ linh kiện taplo.
- Phim kính lái có độ xuyên sáng dưới 50% làm giảm khả năng quan sát khi đi ban đêm hoặc trời mưa lớn; chungauto.vn khuyên kính lái chọn độ xuyên sáng khoảng 60% – 70%, kính sườn trước khoảng 20 – 40% để nhìn rõ 2 gương, kính sườn sau và kính hậu khoảng 20% trở xuống để tăng riêng tư.
- Quy trình dán phim cách nhiệt cho VF Wild gồm 6 bước trong phòng phim kín: đo thông số kính nguyên bản bằng máy quang phổ; phủ khăn bảo vệ taplo, tapi cửa và chèn dây hút nước khe kính; vệ sinh sâu hai mặt kính; sấy phom phim bằng máy khò nhiệt cho phim bo theo độ cong kính; dán phim vào mặt trong và gạt sạch nước, bọt khí; kiểm tra độ trong, đo lại thông số cách nhiệt và bàn giao.
- Theo bài về VinFast VF5, phim 3M cản UV 99.9%, IR 97%, chống chói 78%, dùng công nghệ Polyester Nano-Carbon 200 lớp, loại bỏ năng lượng 62% và bảo hành chính hãng 10 năm; dòng cao cấp là 3M Crystalline.
- Phim X-Care của công ty Cổ phần Catrend Việt Nam dày 3mil, cản UV 99.9%, IR 99.5%, chống chói 65%, dùng công nghệ Polyester Nano-Carbon và phún xạ kim loại, bảo hành chính hãng 15 năm; X-Care có 2 dòng là phim phản xạ nhiệt và phim Nano-Ceramic.
- Phim Nano X dùng công nghệ Nano Ceramic và Dual Nano Ceramic, cản tia cực tím và tia hồng ngoại 99%, bảo hành ít nhất 10 năm, riêng gói cao cấp nhất có bảo hành trọn đời.
- Theo chungauto.vn (tháng 10/2026), dán phim cách nhiệt cho VinFast VF5 có giá tham khảo 2.990.000 – 10.660.000 VNĐ với X-Care, 7.040.000 – 12.000.000 VNĐ với 3M và 3.960.000 – 6.390.000 VNĐ với Nano X.
- Các mã phim được chungauto.vn gợi ý theo vị trí kính: 3M CR70 (truyền sáng 69%) cho kính lái; X-Care X40 (truyền sáng 45%) hoặc X-Care XC40 (38%) cho kính sườn trước; X-Care X10 hoặc X20 (truyền sáng 08 – 18%) hoặc X-Care XC20 (23%) cho kính sườn sau và kính hậu.
- Nguồn: https://chungauto.vn/phim-cach-nhiet-vf-wild-tranh-sai-khi-dan-va-bao-gia-2702.html; https://chungauto.vn/dan-phim-cach-nhiet-xe-vinfast-vf5-1567.html
TXT],
    'ppf-ceramic' => ['/ppf|ceramic|phu-gam|duong-son/', <<<'TXT'
## Phụ kiện: Phim bảo vệ sơn PPF và phủ ceramic (theo chungauto.vn)
- Dán phim PPF giúp bảo vệ lớp sơn nguyên bản của xe, hạn chế trầy xước do đá văng, va chạm; ngoài ra phim còn chống nước, chống bụi, chống ố vàng, có khả năng tự phục hồi vết xước và giảm độ bám của chất bẩn giúp xe dễ vệ sinh hơn.
- Các thương hiệu phim PPF được chungauto.vn nêu gồm XCare (thuộc thương hiệu Catrend, phát triển riêng cho thị trường Việt Nam), 3M (Mỹ, có dòng 3M Scotgard Pro Series), Xpel (Mỹ), Teckwrap (sản xuất tại Mỹ) và CCC NextGen (xuất xứ Anh Quốc, công nghệ Đức).
- Theo chungauto.vn (tháng 10/2026), dán PPF full xe cho VinFast VF7 có giá tham khảo 20.000.000 – 36.000.000 VNĐ với XCare, 25.000.000 – 40.000.000 VNĐ với CCC NextGen, 35.000.000 – 45.000.000 VNĐ với Teckwrap, 40.000.000 – 50.000.000 VNĐ với Xpel và 55.000.000 – 81.000.000 VNĐ với 3M.
- Các vị trí nên dán PPF gồm cản trước, nắp capo, gương chiếu hậu, tay nắm cửa, cánh cửa, chân bệ cửa, đèn pha và đèn hậu; để bảo vệ đầy đủ thì nên dán toàn bộ bề mặt sơn xe.
- Quy trình dán PPF tại ChungAuto gồm 5 bước: kiểm tra và vệ sinh bề mặt sơn; đánh bóng (xe mới) hoặc hiệu chỉnh sơn nếu có vết xước (xe đã qua sử dụng); đo đạc và cắt phim bằng tay hoặc bằng máy; dán phim với dung dịch bôi trơn chuyên dụng, gạt bỏ dung dịch thừa và cắt phần phim dư; kiểm tra hoàn thiện và bàn giao.
- Với VinFast VF7, thời gian dán PPF là 24 – 48 tiếng (đã gồm thời gian đợi keo khô), tùy loại phim, vị trí dán và tay nghề thợ.
- Khi dán PPF nên chọn phim chính hãng, vì đại lý phân phối chính hãng có giấy chứng nhận đại lý mà khách có thể yêu cầu kiểm tra; tay nghề thợ quyết định phim có bị bong tróc hay đọng bong bóng khí hay không.
- Phủ ceramic (phủ gốm) là kỹ thuật dùng dung dịch gốm có gốc vô cơ (như titan dioxit, silic dioxit) phủ lên bề mặt, sau đó dung dịch liên kết với bề mặt tạo thành lớp bảo vệ trong suốt; các vị trí nên phủ là vỏ sơn xe, kính xe và nội thất.
- Phủ ceramic giúp tăng độ bóng sơn, chống thấm nước và chống oxy hóa, chống tia UV, giúp xe dễ vệ sinh và hạn chế vết xước nhẹ, nhưng khả năng chống xước của ceramic không tốt bằng phim PPF.
- Theo chungauto.vn (tháng 10/2026), phủ ceramic có giá tham khảo 4 triệu – 6 triệu – 10 triệu VNĐ cho xe 5 chỗ và 5 triệu – 8 triệu – 12 triệu VNĐ cho xe 7 chỗ (lần lượt với gói Tiêu chuẩn, Cao cấp, Đặc biệt), phủ thêm lớp thứ 2 thêm 2 triệu – 3 triệu – 4 triệu VNĐ; giá phụ thuộc dòng xe, kích thước xe, loại ceramic và số lớp phủ.
- Các gói phủ ceramic Tiêu chuẩn, Cao cấp, Đặc biệt tại ChungAuto có độ cứng lớp phủ lần lượt 9H, 10H, 12H, bảo hành 12, 18, 24 tháng, độ bền lớp phủ 2, 3, 5 năm, chăm sóc sau phủ 4, 6, 8 lần; chỉ gói Đặc biệt có kèm gói phủ bảo vệ kính.
- Thời gian phủ ceramic là 20 – 48 tiếng; quy trình gồm kiểm tra xe và tư vấn gói, rửa và vệ sinh bề mặt, đánh bóng hiệu chỉnh sơn, phủ ceramic (chờ 2 tiếng cho lớp trước khô rồi mới phủ lớp tiếp), sấy bằng đèn hồng ngoại chuyên dụng, kiểm tra và bàn giao.
- Chỉ nên phủ ceramic 2 – 4 lớp, vì quá nhiều lớp làm độ kết dính kém đi và dễ bong tróc; không nên tự phủ tại nhà vì cần không gian kín không bụi, máy đánh bóng, đèn sấy hồng ngoại và kỹ thuật tốt.
- Sau khi phủ ceramic không nên để xe tiếp xúc với nước và bụi bẩn trong 24 tiếng, không dùng chất tẩy rửa nồng độ cao trong 5 ngày, và nên chăm sóc lớp phủ định kỳ 3 – 6 tháng/lần; độ bền lớp phủ là 2 – 5 năm tùy thương hiệu và cách bảo quản.
- Các thương hiệu ceramic được chungauto.vn nêu gồm CarPro CQuartz (dòng UK 3.0, Finest Reserve, Lite; 70% SiO2, bảo vệ lên đến 3 năm), Chemical Guys (Mỹ, bảo vệ lên đến 2 năm), System-X của Element 119 (Mỹ), IGL Coatings (Malaysia; IGL Quartz bền đến 5 năm, IGL Kenzo đến 3 năm, IGL Ecocoat khoảng 1 năm) và Gyeon (Hàn Quốc; Q² Mohs, Can Coat, Infinite Base).
- Nguồn: https://chungauto.vn/dan-phim-ppf-cho-xe-vinfast-vf7-2450.html; https://chungauto.vn/phu-ceramic-o-to-1280.html
TXT],
    'camera-360' => ['/camera-360/', <<<'TXT'
## Phụ kiện: Camera 360 (theo chungauto.vn)
- Camera 360 là hệ thống gồm nhiều mắt camera góc rộng lắp quanh xe, thường ở phía trước, phía sau và hai bên gương chiếu hậu; hình ảnh từ các camera được xử lý và ghép lại thành góc nhìn toàn cảnh 2D, 3D hoặc từ trên xuống.
- Theo chungauto.vn, VF Wild bản tiêu chuẩn ban đầu chưa được trang bị camera 360 nguyên bản; chủ xe có thể lắp thêm nếu cần mở rộng góc quan sát.
- Camera 360 giúp quan sát toàn cảnh quanh xe, hỗ trợ đỗ xe (căn khoảng cách với tường, cột, xe bên cạnh), hỗ trợ quay đầu trong đường hẹp, hạn chế điểm mù và hỗ trợ căn đầu, đuôi xe; một số dòng có tích hợp chức năng ghi hình hành trình.
- Camera 360 chỉ hỗ trợ người lái quan sát, không thay thế việc kiểm tra trực tiếp xung quanh xe trước và trong khi di chuyển.
- Các dòng camera 360 cho VF Wild được chungauto.vn nêu gồm TexPad Zone 4 Plus (truyền hình ảnh qua HDMI), Safeview (nhiều lựa chọn camera 360 3D), Gotech GP6 (tập trung mô phỏng hình ảnh 3D toàn cảnh), Zestech Z311 (hỗ trợ ghi hình 24/24) và DCT (các phiên bản T1, T2, T3 và Pro).
- Theo chungauto.vn (tháng 10/2026), camera 360 cho VF Wild có giá tham khảo từ 7 triệu đến 28,8 triệu đồng: TexPad Zone 4 Plus 13,8 triệu đồng, Safeview 10,8 – 25 triệu đồng, Gotech GP6 12,5 triệu đồng, Zestech Z311 15 triệu đồng, DCT 7 – 28,8 triệu đồng; mức giá có thể thay đổi tùy phiên bản và chương trình tại thời điểm lắp.
- Theo chungauto.vn, TexPad Zone 4 Plus và Zestech Z311 là hai dòng đáng cân nhắc nếu ưu tiên bộ camera 360 cho VF Wild có cấu hình tương đối đầy đủ; Safeview và DCT phù hợp với người muốn nhiều phiên bản để chọn theo cấu hình và ngân sách.
- Do cấu hình VF Wild thương mại thực tế có thể khác nhau, trước khi lắp camera 360 cần kiểm tra khả năng tương thích với màn hình nguyên bản, chuẩn tín hiệu, vị trí đặt mắt camera và phương án đi dây.
- Trước khi lắp nên kiểm tra xe đã có sẵn camera nào để tránh lắp trùng hoặc bổ sung không cần thiết.
- Bốn mắt camera trước, sau và hai bên gương cần đặt đúng góc để hạn chế sai lệch khi ghép hình, và sau khi lắp cần hiệu chỉnh toàn cảnh để hình ảnh 360 độ hiển thị chính xác, liền mạch.
- Khi lắp camera 360 nên ưu tiên cắm giắc zin, hạn chế cắt dây và tránh tác động đến hệ thống điện nguyên bản.
- Camera 360 TexPad Zone 4 cung cấp tới 24 góc nhìn; camera 360 Safeview có độ phân giải HD/AHD; camera 360 DCT có các tính năng hỗ trợ lùi xe và lưu dữ liệu video.
- Theo chungauto.vn, các mẫu camera 360 Safeview Lux 300, Safeview LD980H, Safeview LD900H được bảo hành 2 năm; camera 360 DCT được bảo hành 2 năm, 1 đổi 1 trong năm đầu nếu có lỗi từ nhà sản xuất.
- Các tiện ích camera 360 được chungauto.vn nêu gồm tự động hiển thị camera khi xi nhan hoặc đánh lái, tự động mở camera khi lái ở tốc độ chậm, vạch đánh lái theo vô lăng và mắt camera có khả năng chống nước, chống bụi.
- Nguồn: https://chungauto.vn/camera-360-vf-wild-toan-bo-thong-tin-can-biet-truoc-khi-lap-2818.html; https://chungauto.vn/camera-360-cho-vinfast-vf7-thong-tin-chi-tiet-2451.html
TXT],
    'camera-hanh-trinh' => ['/camera-hanh-trinh|cam-hanh-trinh/', <<<'TXT'
## Phụ kiện: Camera hành trình (theo chungauto.vn)
- Camera hành trình là thiết bị ghi và lưu trữ video cuốn chiếu liên tục suốt đường đi, cung cấp bằng chứng đối chiếu khi xảy ra va chạm; các tính năng thường gặp gồm ghi hình trước và sau, ghi hình vòng lặp, định vị GPS, cảnh báo ADAS, giám sát 24/24 và ghi hình Time-Lapse.
- Trên VF Wild, khi thùng sau chất đồ cồng kềnh làm gương chiếu hậu trong cabin bị che khuất, camera hành trình có mắt cam phía sau giúp mở rộng tầm nhìn, hỗ trợ lùi xe và chuyển làn.
- Theo chungauto.vn, VF Wild tăng tốc nhanh nên tài xế dễ quá chân ga ở cung đường lạ; camera hành trình có dữ liệu cảnh báo giao thông giúp nhắc tài xế đi đúng tốc độ và hạn chế rủi ro bị phạt nguội.
- Theo chungauto.vn (tháng 10/2026), camera hành trình cho VF Wild có giá tham khảo 4.990.000 – 5.990.000 VNĐ với Vietmap SpeedMap M1, 4.190.000 VNĐ với Vietmap KC01, 4.500.000 VNĐ với 70mai A810 và 2.500.000 VNĐ với 70mai A500S; mức giá này đã gồm thiết bị, thẻ nhớ và công lắp đặt đi dây giấu kín.
- Vietmap SpeedMap M1 luôn hiển thị và cảnh báo tốc độ giới hạn của đoạn đường đang đi bằng giọng nói tiếng Việt, mắt cam trước chuẩn 2K; Vietmap KC01 có thiết kế dáng thỏi son nhỏ gọn giấu sau gương chiếu hậu, ghi hình kép trước sau và tải video về điện thoại qua Wi-Fi.
- 70mai A500S ghi hình 2K, có GPS lưu tọa độ vào video và tính năng ADAS cảnh báo lệch làn; 70mai A810 ghi hình 4K HDR với cảm biến Sony Starvis 2 và có AI hỗ trợ giám sát khi xe đang đỗ.
- Thời gian lắp camera hành trình trên VF Wild là 40 – 60 phút với Vietmap SpeedMap M1 và Vietmap KC01, 30 – 45 phút với 70mai A500S và 45 – 60 phút với 70mai A810.
- Quy trình lắp camera hành trình cho VF Wild gồm 6 bước: kiểm tra hệ thống điện và xác định vị trí đặt mắt cam; chọn phương pháp lấy nguồn an toàn; đi dây âm trần giấu kín; lắp cố định mắt cam trước và sau, chỉnh góc quay; kết nối nguồn, khởi động và căn chỉnh thấu kính; kích hoạt, hướng dẫn kết nối ứng dụng điện thoại và bàn giao.
- Khi lắp trên VF Wild, chungauto.vn ưu tiên lấy nguồn từ cổng tẩu 12V nguyên bản hoặc bộ dây cắm cầu chì (Fuse Cable), không cắt chích dây điện zin và không can thiệp vào pin chính của xe; tính tương thích vẫn cần kiểm tra theo từng phiên bản xe thực tế.
- Dây camera được đi âm trần dọc theo các giăng cao su của cột A, B, C, được bọc lót, không lộ ra khoang cabin và không cản trở hoạt động của hệ thống túi khí rèm nguyên bản.
- 70mai A510 là bản nâng cấp của 70mai A500S, ghi hình trước và sau đến 3K, cảm biến Sony STARVIS 2 IMX675, góc ống kính 130° – 140°, bảo hành 12 tháng; 70mai A800SE ghi hình trước và sau đến 4K, góc ống kính 140°, bảo hành 12 tháng.
- 70mai A810 ghi hình trước và sau đến 4K, cảm biến Sony STARVIS IMX678, có phát hiện chuyển động AI, bảo hành 12 tháng.
- Vietmap M1 và Vietmap M2 ghi hình trước và sau đến 2K, cảnh báo giao thông bằng giọng nói tiếng Việt dựa trên GPS nhờ bản đồ Vietmap Live Plus, có G-Sensor, chế độ trông xe, giám sát trực tuyến và lưu trữ đám mây; M1 có góc ống kính 150° – 170°, M2 dùng cảm biến Sony Starvis 2 IMX675 với góc 150°, cả hai bảo hành 12 tháng.
- Theo chungauto.vn (tháng 10/2026), camera hành trình cho Ford Ranger (đã gồm lắp đặt) có giá tham khảo 680.000 VNĐ với Texpad X2, 2.890.000 – 3.190.000 VNĐ với 70mai A510, 2.950.000 – 3.250.000 VNĐ với 70mai A800SE, 6.190.000 VNĐ với Vietmap M1 và 8.980.000 VNĐ với Vietmap M2.
- Nguồn: https://chungauto.vn/cam-hanh-trinh-vf-wild-top-4-giai-phap-ghi-hinh-an-toan-2705.html; https://chungauto.vn/camera-hanh-trinh-cho-ford-ranger-2473.html
TXT],
    'ap-suat-lop' => ['/ap-suat-lop|tpms/', <<<'TXT'
## Phụ kiện: Cảm biến áp suất lốp (theo chungauto.vn)
- Cảm biến áp suất lốp, hay TPMS (Tire Pressure Monitoring System), là hệ thống dùng cảm biến tại từng bánh xe để theo dõi áp suất và nhiệt độ lốp, truyền dữ liệu về bộ thu để hiển thị và cảnh báo khi thông số bất thường.
- Trên VF Wild, cảm biến áp suất lốp theo dõi liên tục áp suất và nhiệt độ từng bánh và cảnh báo khi phát hiện xì hơi, dính đinh hoặc quá nhiệt.
- Có hai loại cảm biến phổ biến là van ngoài và van trong, khác nhau chủ yếu ở vị trí lắp đặt, độ ổn định và mức độ bảo vệ cảm biến.
- Cảm biến van ngoài gắn trực tiếp vào đầu van bánh xe nên lắp nhanh, không cần tháo lốp, nhưng dễ chịu tác động của nước, bụi bẩn, va quệt và có nguy cơ rơi, hư hỏng khi xe di chuyển.
- Cảm biến van trong được lắp bên trong lốp, thay thế cho van nguyên bản; theo chungauto.vn, với VF Wild đi đa địa hình, loại van trong giữ nguyên phom mâm zin, đạt chuẩn chống nước IP67 và không lo mất trộm hay gãy van khi chạy off-road.
- Lắp cảm biến van trong cho VF Wild cần tháo lốp, ra vào lốp và cân bằng động tại xưởng, thời gian thi công từ 40 đến 60 phút.
- Chungauto.vn giới thiệu 4 dòng cảm biến áp suất lốp cho VF Wild gồm TexPad TX900, Steelmate TP-MT11, Steelmate TP-MT30 và Vietmap V1i/V2.
- TexPad TX900 dùng chip cảm biến Infineon SP40 của Đức, thời lượng pin được công bố khoảng 5 năm, và có 4 phiên bản cùng chức năng giám sát áp suất, nhiệt độ 4 bánh, chỉ khác cách hiển thị: màn hình Android, màn hình độc lập năng lượng mặt trời, dạng nút chờ và gắn gương.
- Theo chungauto.vn, cảm biến TexPad TX900 được bảo hành 36 tháng.
- Steelmate TP-MT11 dùng van trong chống bụi nước IP67, hộp màn hình tự sạc bằng năng lượng mặt trời, không đấu nối điện xe, và báo động bằng âm thanh kèm đèn nháy khi áp suất lốp thay đổi.
- Steelmate TP-MT30 có đầu thu cắm qua cổng USB của màn hình Android phụ hoặc Android Box, phát cảnh báo bằng giọng nói tiếng Việt qua loa của xe.
- Cảm biến Vietmap có thể đồng bộ dữ liệu lốp lên màn hình hiển thị kính lái HUD của Vietmap, hiển thị chung với tốc độ và biển báo giao thông.
- Theo chungauto.vn, do VF Wild dùng hệ điều hành đóng, các dòng cảm biến hoạt động độc lập như Steelmate TP-MT11 hoặc TexPad TX900 phù hợp vì không cần cắt trích dây điện của xe, giúp bảo vệ chính sách bảo hành hệ thống điện của xe.
- Theo chungauto.vn (tháng 10/2026), cảm biến áp suất lốp cho VF Wild có giá tham khảo: TexPad TX900 từ 2.500.000 đến 3.200.000 VNĐ, Steelmate TP-MT11 2.800.000 VNĐ, Steelmate TP-MT30 2.600.000 VNĐ, Vietmap V1i/V2 từ 2.200.000 đến 3.000.000 VNĐ; mức giá trọn gói tại xưởng gồm kiểm tra lốp, lắp đặt và cân bằng động.
- Khi chọn cảm biến áp suất lốp, cần kiểm tra khả năng tương thích với xe và hệ thống hiển thị (với loại hiển thị trên màn hình Android cần kiểm tra khả năng kết nối), ưu tiên sản phẩm có thông tin rõ về tuổi thọ pin, độ ổn định tín hiệu và bảo hành, và đặt vị trí hiển thị dễ quan sát, không ảnh hưởng tầm nhìn lái xe.
- Nên kiểm tra cảm biến khi một bánh không hiển thị áp suất, cảm biến thường xuyên mất tín hiệu, thông số bất thường, hệ thống báo liên tục dù lốp đã bơm đúng, sau khi thay mâm, thay lốp hoặc tháo lốp để vá, và khi cảm biến dùng nhiều năm có dấu hiệu pin yếu; khi có cảnh báo, trước tiên nên đo áp suất thực tế của lốp, nếu áp suất bình thường mà vẫn báo sai thì mới kiểm tra cảm biến, pin, bộ thu và kết nối.
- Nguồn: https://chungauto.vn/cam-bien-ap-suat-lop-vf-wild-top-4-dong-phu-hop-nhat-2710.html; https://chungauto.vn/cam-bien-ap-suat-lop-xe-vf6-gia-phan-loai-va-kinh-nghiem-lua-chon-2667.html
TXT],
    'cam-bien-va-cham' => ['/cam-bien-va-cham|cam-bien-lui|cam-bien-do-xe/', <<<'TXT'
## Phụ kiện: Cảm biến va chạm (theo chungauto.vn)
- Cảm biến va chạm là thiết bị dùng cảm biến để nhận biết vật cản trong phạm vi hoạt động và phát cảnh báo theo khoảng cách, giúp người lái chủ động giảm tốc, dừng xe hoặc điều chỉnh hướng khi tiến, lùi hoặc đỗ.
- Một bộ cảm biến va chạm gồm mắt cảm biến lắp trên cản xe (từ 4 đến 8 mắt), bộ điều khiển trung tâm xử lý khoảng cách, thiết bị hiển thị (màn hình LED đặt trên taplo hoặc hiển thị trên màn hình xe), còi cảnh báo và dây kết nối với nguồn điện của xe.
- Khi xe tiến càng gần vật cản, âm thanh cảnh báo thường phát nhanh và liên tục hơn.
- Theo số mắt, loại 4 mắt lắp ở cản sau hỗ trợ lùi và đỗ xe; loại 6 mắt thường gồm 4 mắt sau và 2 mắt trước; loại 8 mắt gồm 4 mắt trước và 4 mắt sau, cảnh báo ở cả hai đầu xe.
- Texpad Sensor có bản 4 mắt và 8 mắt; Steelmate có bản 4, 6 và 8 mắt.
- Theo cách hiển thị, có loại chỉ cảnh báo bằng âm thanh; loại có màn hình riêng hiển thị khoảng cách, vị trí vật cản kèm còi hoặc giọng nói (Steelmate); và loại hiển thị màu xanh, vàng, đỏ trên ODO, màn hình nguyên bản hoặc màn hình Android tương thích (Texpad Sensor).
- Texpad Sensor tích hợp 4 cổng sạc và tự động kích hoạt khi xe bật đèn lùi hoặc đạp phanh; Steelmate là thương hiệu đến từ Trung Quốc, tự động kích hoạt khi đạp phanh.
- Theo chungauto.vn (tháng 10/2026), cảm biến va chạm cho VF Wild có giá tham khảo 4.200.000 – 5.200.000 đồng với Texpad Sensor và 1.550.000 – 3.600.000 đồng với Steelmate.
- Theo chungauto.vn, cho VF Wild: Steelmate 4 hoặc 6 mắt phù hợp nhu cầu cơ bản khi lùi và đỗ; Steelmate 8 mắt hoặc Texpad Sensor 8 mắt phù hợp xe thường đi hầm, ngõ nhỏ, nhiều vật cản; Texpad Sensor phù hợp nếu muốn cảnh báo trên màn hình xe mà không cần thêm màn hình LED rời trên taplo.
- Khi cảnh báo ở mức cao, người lái nên giảm tốc độ và kết hợp quan sát trực tiếp, gương chiếu hậu hoặc camera để xử lý an toàn.
- Cảm biến lùi thường chỉ cảnh báo vật cản phía sau, còn cảm biến va chạm có thể bố trí ở phía trước, phía sau hoặc cả hai đầu xe tùy cấu hình.
- Cảm biến va chạm không thay thế camera 360: cảm biến đưa ra cảnh báo vật cản, camera 360 cung cấp hình ảnh trực quan quanh xe, và hai thiết bị có thể kết hợp khi đỗ và di chuyển trong không gian hẹp.
- Nguồn: https://chungauto.vn/cam-bien-va-cham-vf-wild-co-thuc-su-can-thiet-cho-xe-ban-tai-2834.html; https://chungauto.vn/cam-bien-va-cham-cho-vinfast-vf7-2468.html
TXT],
    'decal' => ['/decal|dan-tem|doi-mau-xe/', <<<'TXT'
## Phụ kiện: Decal (theo chungauto.vn)
- Decal là lớp vật liệu dán lên một phần hoặc toàn bộ bề mặt ngoại thất xe để trang trí, thay đổi màu sắc hoặc tạo phong cách riêng; so với sơn lại xe, decal dễ thay đổi diện mạo và có nhiều lựa chọn màu sắc, họa tiết.
- Các loại decal thường dùng cho VF Wild gồm tem decal phong cách thể thao, decal họa tiết off-road, decal trang trí theo thiết kế riêng, decal đổi màu, và decal logo, chữ hoặc graphic cá nhân hóa.
- Decal đổi màu cho VF Wild có thể chọn bề mặt bóng, mờ, satin hoặc nhiều màu khác nhau tùy chất liệu; decal đổi màu có hai chất liệu là TPH và TPU, với các hiệu ứng bề mặt như bóng, nhám, bán bóng, chrome, carbon, chuyển màu.
- Gợi ý chọn decal cho VF Wild theo phong cách: mạnh mẽ chọn decal thể thao hoặc off-road; tối giản chọn sọc hoặc logo nhỏ; cá tính chọn graphic thiết kế riêng; muốn đổi màu xe chọn decal đổi màu; ưu tiên bảo vệ sơn chọn phim PPF TPU.
- Lợi ích của dán decal gồm đổi màu xe mà không cần sơn lại, bảo vệ lớp sơn gốc khỏi trầy xước và đá văng, chống thấm nước, giảm bám bụi và thời gian thi công nhanh.
- Các thương hiệu decal cho VF Wild được chungauto.vn nêu gồm TeckWrap, 3M và Avery Dennison.
- Theo chungauto.vn (tháng 10/2026), decal đổi màu hoặc phim PPF TPU bảo vệ toàn xe cho VF Wild có giá tham khảo từ 15.000.000 đến 35.000.000 VNĐ, thời gian thi công 2 đến 3 ngày làm việc; giá phụ thuộc thương hiệu, chất liệu, diện tích thi công và phương án lựa chọn.
- Theo chungauto.vn, decal đổi màu hoặc PPF TPU toàn xe cho VF Wild với các thương hiệu TeckWrap, 3M, Avery Dennison được bảo hành 3 đến 5 năm, phạm vi bảo hành gồm bong tróc, nổ keo và bay màu sản phẩm.
- Decal nên được dán trong phòng tiêu chuẩn, chống bụi mịn, đủ ánh sáng, vì tay nghề thợ quyết định việc phim không bị bong tróc hay đọng bong bóng khí.
- Quy trình dán decal gồm 5 bước: kiểm tra ngoại thất và vệ sinh bề mặt sơn; đánh bóng (xe mới) hoặc hiệu chỉnh lớp sơn (xe cũ có vết xước); đo đạc và cắt phim bằng tay hoặc bằng máy; dán phim với dung dịch bôi trơn chuyên dụng, gạt bỏ dung dịch thừa và cắt phim dư; kiểm tra hoàn thiện rồi bàn giao.
- Theo chungauto.vn, thời gian dán decal từ 1 đến 48 tiếng, đã gồm thời gian đợi keo khô, tùy loại phim, vị trí dán và tay nghề thợ.
- Decal sticker chỉ dán một phần nhỏ như nóc, capo, hông, gương, cửa để tạo điểm nhấn, có các chất liệu PVC thường, cán bóng hoặc nhám, và in kỹ thuật số; decal sticker chất lượng kém dễ bạc màu, bong mép.
- Decal quảng cáo, dịch vụ dùng thân xe để truyền tải thông điệp thương hiệu, có các chất liệu PVC thường, cán bóng hoặc nhám, decal đúc và decal phản quang; ưu điểm là dễ thay đổi nội dung, nhược điểm là tuổi thọ không cao.
- Khi chọn decal nên ưu tiên phim chính hãng của các thương hiệu như XCare, 3M, CCC Next Gen, Xpel, và có thể yêu cầu xem giấy chứng nhận đại lý để kiểm tra.
- Decal có thể tháo khi cần đổi phong cách hoặc trả xe về diện mạo ban đầu, nhưng nên do kỹ thuật viên có kinh nghiệm thực hiện.
- Theo chungauto.vn (tháng 10/2026), dán decal cho Ford Everest có giá tham khảo: decal sticker 900.000 VNĐ, decal đổi màu toàn xe từ 20.000.000 đến 81.000.000 VNĐ, decal quảng cáo, dịch vụ từ 500.000 đến 1.500.000 VNĐ.
- Nguồn: https://chungauto.vn/decal-vf-wild-mau-dep-ca-tinh-cho-vinfast-vf-wild-2732.html; https://chungauto.vn/dan-decal-cho-ford-everest-2368.html
TXT],
    'led-noi-that' => ['/led-noi-that|den-noi-that|tran-sao|led-am/', <<<'TXT'
## Phụ kiện: Đèn LED nội thất (theo chungauto.vn)
- LED nội thất là hệ thống đèn LED lắp bổ sung trong khoang cabin, giúp tăng cường ánh sáng tại một số khu vực và tạo điểm nhấn cho không gian nội thất.
- LED nội thất bổ sung ánh sáng tại chân ghế, tapi cửa, hộc chứa đồ và các vị trí dễ bị tối vào ban đêm, giúp người dùng quan sát và thao tác thuận tiện hơn.
- Ngoài chiếu sáng, LED nội thất làm nổi bật đường nét nội thất và cho phép chủ xe cá nhân hóa cabin bằng cách chọn cấu hình, vị trí lắp đặt, mức độ chiếu sáng và màu sắc ánh sáng.
- Tại ChungAuto, bộ LED nội thất có các cấu hình 18, 22 và 24 chi tiết theo số lượng vị trí lắp đặt: 18 chi tiết là cấu hình cơ bản, 22 chi tiết giúp đèn bố trí đồng đều hơn, 24 chi tiết có nhiều vị trí nhất.
- LED zin được thiết kế theo từng mẫu xe và bố trí phù hợp với kết cấu nội thất nguyên bản, cho độ hoàn thiện và tính đồng bộ cao nhưng chi phí thường cao hơn các bộ LED phổ thông.
- Các vị trí lắp LED nội thất gồm: LED viền dọc taplo, tapi cửa, hộc đựng đồ hoặc màn loa; LED gầm taplo và gầm ghế cho khu vực để chân; LED khu vực cửa và tay nắm; LED trần sao.
- Không phải bộ LED nào cũng bao gồm toàn bộ các vị trí lắp; số vị trí thực tế phụ thuộc vào cấu hình 18, 22, 24 chi tiết hoặc loại LED zin được chọn.
- LED trần sao dùng nhiều điểm sáng trên trần xe để tạo hiệu ứng bầu trời sao hoặc sao rơi; có thể kết hợp bọc trần da lộn để trần tối và ít phản sáng hơn, giúp hiệu ứng nổi bật hơn.
- Trần sao băng có chi phí cao hơn bộ LED nội thất thông thường do thi công phức tạp hơn, cần bố trí nhiều điểm sáng và xử lý trực tiếp phần trần xe.
- Theo chungauto.vn (tháng 10/2026), bộ LED nội thất 18, 22, 24 chi tiết và LED zin cho VinFast VF6 có giá tham khảo 3,6 – 20 triệu đồng.
- Theo chungauto.vn (tháng 10/2026), lắp đèn trần sao băng cho VinFast VF6 có giá tham khảo khoảng 5,5 – 7,5 triệu đồng, tùy cấu hình và yêu cầu thi công.
- Trước khi chọn LED nội thất cần xác định đúng phiên bản xe, đặc biệt với các bộ LED được thiết kế riêng theo từng dòng xe.
- Khi thi công LED nội thất, dây kết nối cần được bố trí gọn và đèn lắp đúng vị trí để đảm bảo thẩm mỹ và hạn chế ảnh hưởng đến các chi tiết nội thất nguyên bản.
- Nguồn: https://chungauto.vn/led-noi-that-vf6-gia-bao-nhieu-cac-lua-chon-pho-bien-tren-thi-truong-hien-nay-2673.html
TXT],
    'den' => ['/(^|-)den(-|$)|bi-led|bi-laser|bi-gam|do-den/', <<<'TXT'
## Phụ kiện: Đèn (theo chungauto.vn)
- Độ đèn là bổ sung hoặc nâng cấp thiết bị chiếu sáng nhằm cải thiện khả năng quan sát khi xe vận hành trong điều kiện thiếu sáng; VF Wild nguyên bản dùng cụm đèn LED/projector ở phía trước.
- Trên VF Wild có 3 phương án độ đèn phổ biến: bi gầm bổ sung vùng sáng thấp phía trước xe, Bi-LED nâng cấp projector dùng nguồn sáng LED cho hệ thống chiếu sáng chính, và Bi Laser nâng cấp projector có công nghệ laser hỗ trợ chiếu xa.
- Bi gầm dùng projector để gom sáng, hỗ trợ quan sát mặt đường và khu vực gần đầu xe, phù hợp xe thường đi đường tối, đường đèo hoặc trong điều kiện mưa, sương.
- Bi-LED được nâng cấp tại cụm đèn pha, dùng nguồn sáng LED kết hợp projector để tạo vùng sáng tập trung, phù hợp người thường đi đường trường hoặc tuyến đường thiếu ánh sáng.
- Bi Laser được nâng cấp tại cụm đèn pha, có thêm nguồn laser hỗ trợ chiếu xa, phù hợp người thường chạy đường dài, cao tốc ban đêm; Bi Laser khác bi gầm Laser, vì bi gầm Laser được lắp ở vị trí đèn gầm phía trước xe.
- Theo chungauto.vn (tháng 10/2026), độ đèn cho VF Wild có giá tham khảo: bi gầm khoảng 3,9 đến 5,5 triệu đồng, Bi-LED khoảng 6,4 đến 9 triệu đồng, Bi Laser khoảng 10,5 đến 17,7 triệu đồng.
- Bi gầm và bi gầm Laser lắp ở khu vực cản hoặc đèn gầm; nếu có pát và vị trí lắp tương thích thì có thể hạn chế khoan cắt, còn nếu phải tạo điểm cố định hoặc hốc mới thì có thể cần khoan, cắt cản.
- Bi-LED và Bi Laser lắp bên trong cụm đèn pha nguyên bản, thường cần tháo và xử lý cụm đèn để thay projector, nhưng không đồng nghĩa với việc phải khoan cản và không phải kiểu gắn thêm đèn lên thân xe.
- Trước khi độ đèn VF Wild cần kiểm tra kích thước đèn, vị trí lắp, pát cố định, cách kết nối điện, và hỏi rõ có cần khoan, cắt cản hay mở cụm đèn hay không.
- Khi độ đèn VF Wild nên ưu tiên dùng giắc cắm phù hợp, hạn chế cắt nối dây và không tác động đến pin cao áp, mô-tơ điện hay hệ thống điều khiển nguyên bản.
- Sau khi lắp cần căn chỉnh ánh sáng, kiểm tra đường cắt sáng, góc chiếu và khả năng chuyển pha, cos để đảm bảo đèn hoạt động đúng.
- Độ bi LED hoặc bi Laser là thay cụm bi nguyên bản bằng cụm bi mới có công suất chiếu sáng lớn hơn, đòi hỏi kỹ thuật viên tay nghề cao, thao tác cẩn thận và thời gian thi công lâu; theo chungauto.vn, đèn bi LED/bi Laser chiếu sáng gấp 3 đến 5 lần đèn halogen nguyên bản.
- Theo chungauto.vn, tuổi thọ bi LED/bi Laser khoảng 30.000 đến 50.000 giờ, còn đèn bi gầm khoảng 20.000 đến 30.000 giờ.
- Các sản phẩm đèn được chungauto.vn gợi ý gồm bi LED/bi Laser Nexway Vision LS600, Xlight V20, Titan Platinum Plus và bi gầm Nexway Vision V7 Plus, V7 Platinum, V7 Laser.
- Theo chungauto.vn (tháng 10/2026), giá độ đèn đã gồm lắp đặt cho VinFast VF7: bi gầm Nexway Vision V7 Plus 3.900.000 VNĐ, V7 Platinum 4.500.000 VNĐ, V7 Laser 5.500.000 VNĐ, bi LED Nexway Vision LS600 6.500.000 VNĐ, bi LED Xlight V20 6.800.000 VNĐ, bi Laser Titan Platinum Plus 15.200.000 VNĐ.
- Nguồn: https://chungauto.vn/do-den-vf-wild-het-bao-nhieu-tien-2788.html; https://chungauto.vn/do-den-cho-xe-vinfast-vf7-2454.html
TXT],
    'loa' => ['/(^|-)loa(-|$)|am-thanh|(^|-)sub(-|$)/', <<<'TXT'
## Phụ kiện: Loa và âm thanh (theo chungauto.vn)
- Độ loa là nâng cấp hoặc bổ sung loa cánh, loa treble, loa sub và amply để cải thiện chất lượng âm thanh; nên cân nhắc khi thường xuyên nghe nhạc, đi đường dài, còn nếu chỉ nghe radio, podcast và hài lòng với loa zin thì chưa cần độ.
- Loa cánh là nguồn phát chính, đảm nhiệm phần lớn dải trung và một phần dải cao; loa mid (loa cánh) thường được lắp ở phần dưới tapi cửa hai cánh trước.
- Loa cánh có hai loại: loa đồng trục có treble tích hợp trên củ loa bass, giá dễ tiếp cận; loa phân tần có loa bass/mid và treble tách riêng kèm bộ phân tần, cho âm thanh chi tiết và tách lớp tốt hơn.
- Loa cánh là hạng mục nên ưu tiên khi muốn cải thiện tổng thể âm thanh và có thể kết hợp chống ồn cánh cửa để giảm rung và tạp âm.
- Loa treble tái tạo dải cao giúp giọng hát và nhạc cụ rõ, sáng hơn, thường đặt ở trụ A hoặc gần gương chiếu hậu; không bắt buộc nếu loa phân tần đã có treble riêng, và nếu bổ sung cần căn chỉnh vị trí, âm lượng để tránh tiếng quá gắt.
- Loa sub đảm nhiệm dải bass thấp mà loa cánh khó tái tạo đầy đủ; với VF Wild, sub gầm ghế hoặc sub điện phù hợp vì gọn và không chiếm nhiều diện tích.
- Amply cung cấp công suất giúp âm thanh mạnh và ổn định khi mở lớn, nhưng không phải hệ thống nào cũng cần amply rời; sub điện thường đã tích hợp sẵn amply.
- Theo chungauto.vn (tháng 10/2026), combo độ loa VF Wild có giá tham khảo: gói cơ bản 7.850.000đ gồm loa cánh DB Drive ES7 6C và sub điện Texpad Evobass M6; gói tầm trung 9.800.000đ gồm loa cánh Rainbow SL C6.2 và sub điện Texpad Evobass M6.
- Theo chungauto.vn (tháng 10/2026), combo độ loa VF Wild có giá tham khảo: gói cao hơn 15.100.000đ gồm loa cánh Rainbow SL C6.2 và sub điện Audison APBX 10DS, ưu tiên bass sâu, chắc; gói cao cấp 18.100.000đ gồm loa cánh Rainbow SL C6.2 và sub điện Focal IBUS 20.
- Gợi ý theo ngân sách cho VF Wild: dưới khoảng 5 triệu đồng nên thay loa cánh đồng trục và chống ồn cánh cửa cơ bản (rõ tiếng hơn nhưng bass chưa thay đổi nhiều); khoảng 8 đến 10 triệu đồng chọn gói cơ bản hoặc tầm trung để nâng cấp cả loa cánh và bass; khoảng 15 đến 18 triệu đồng chọn gói cao hơn nếu nghe nhạc thường xuyên, đi xa nhiều.
- Thứ tự độ loa nên là chống ồn và loa cánh trước, rồi đến sub, sau đó mới đến loa treble hoặc amply riêng; có thể làm từng bước nhưng nên chọn thiết bị đồng bộ để tránh phải thay mới sau này.
- Loa center tập trung tái tạo giọng nói, lời thoại ở dải trung tâm; các thương hiệu được gợi ý gồm JBL, Bose, Quartorigo, và loa center không tạo được âm trầm sâu nếu không kết hợp loa sub.
- Theo bài về Ford Ranger trên chungauto.vn, tuổi thọ loa mid và loa center thường từ 10 đến 20 năm, loa sub lên đến 10 năm, tùy thương hiệu và tần suất sử dụng.
- Theo chungauto.vn (tháng 10/2026), giá lẻ đã gồm lắp đặt cho Ford Ranger: sub điện Texpad Evobass M6 3.200.000 VNĐ, loa cánh DB Drive ES7 6C 4.650.000 VNĐ, loa cánh JB C1-650 4.800.000 VNĐ, loa cánh Rainbow SL C6.2 6.600.000 VNĐ, sub điện Audison APBX 10DS 8.500.000 VNĐ, sub điện Focal IBUS 20 11.500.000 VNĐ, dán cách âm từ 3.000.000 đến 30.000.000 VNĐ.
- Với xe điện, chungauto.vn khuyên chọn nơi lắp đặt có kinh nghiệm và có bảo hành thi công, và nên nghe thử trước khi mua để chọn đúng gu âm nhạc.
- Nguồn: https://chungauto.vn/do-loa-vf-wild-khong-can-qua-dat-neu-biet-chon-dung-cau-hinh-2845.html; https://chungauto.vn/do-loa-cho-ford-ranger-2513.html
TXT],
    'ghe' => ['/(^|-)ghe(-|$)|boc-ghe|ghe-da|ghe-dien/', <<<'TXT'
## Phụ kiện: Ghế chỉnh điện và bọc ghế da (theo chungauto.vn)
- Ghế điện VF Wild sử dụng mô-tơ và cơ cấu truyền động điện để điều chỉnh vị trí ghế; tùy cấu hình, người dùng có thể thay đổi tiến – lùi, độ cao, độ nghiêng mặt ghế hoặc góc tựa lưng bằng các nút điều khiển bên hông.
- Các chức năng phổ biến của ghế chỉnh điện cho VF Wild gồm điều chỉnh tiến – lùi, nâng – hạ độ cao, chỉnh góc nghiêng mặt ghế và góc tựa lưng; một số cấu hình có thêm chức năng nhớ vị trí, nhưng không phải mẫu nào cũng có đủ các tính năng này nên cần kiểm tra cấu hình thực tế trước khi chọn.
- Các phương án ghế điện cho VF Wild gồm: nâng cấp cơ cấu chỉnh điện (giữ thiết kế ghế hiện tại, bổ sung cơ cấu điện), ghế điện cơ bản, ghế điện đa hướng và ghế điện cao cấp tích hợp thêm tiện nghi; việc lựa chọn cần dựa trên kích thước, kết cấu ghế và khả năng tương thích với VF Wild.
- Theo chungauto.vn (tháng 10/2026), ghế chỉnh điện 4 hướng cho VF Wild (thường dùng cho ghế phụ, chỉnh tiến – lùi và nâng – hạ lòng ghế) có giá tham khảo 6.000.000 – 6.800.000 VNĐ.
- Theo chungauto.vn (tháng 10/2026), ghế chỉnh điện 6 hướng cho VF Wild (thường dùng cho ghế lái, thêm nâng – hạ cao thấp toàn bộ ghế) có giá tham khảo 6.500.000 – 8.500.000 VNĐ, và độ thêm tính năng nhớ vị trí ghế (lưu 2–3 vị trí ngồi) có giá tham khảo phụ thu 3.000.000 VNĐ.
- Khi độ ghế điện cho VF Wild cần kiểm tra ghế phù hợp với không gian và vị trí bắt ghế trên xe, mô-tơ và ray ghế hoạt động ổn định, hành trình trơn tru, khung, chân và điểm cố định được lắp chắc chắn, và thử toàn bộ chức năng, hành trình, dây điện trước khi bàn giao.
- Ghế điện cần nguồn điện để vận hành nên phần đấu nối phải đúng kỹ thuật, không nên tự ý lấy nguồn hoặc đấu vào các đường dây không phù hợp; sau khi lắp cần kiểm tra để dây điện không bị kéo căng, cọ xát hoặc mắc vào cơ cấu khi ghế di chuyển.
- Chungauto.vn nêu 3 loại da bọc ghế ô tô: da công nghiệp (da PU), da Nappa và da tự nhiên; riêng cho VF3, ChungAuto có thêm dòng da Nappa Lux thuộc phân khúc cao cấp.
- Da công nghiệp (da PU) được sản xuất từ phần xơ bên dưới của da thật trộn với chất kết dính và phụ gia, phủ lớp Polyurethane và dập nổi bề mặt; ưu điểm là giá rẻ, khá mềm mịn, không thấm nước, dễ vệ sinh, nhược điểm là độ bền khá kém, dễ bục nứt và bạc màu.
- Da Nappa được làm từ nguyên liệu cao cấp với công nghệ thuộc da tiên tiến, chất lượng gần ngang da tự nhiên, mềm mịn, độ bền cao và rẻ hơn da tự nhiên nhưng cần bảo dưỡng thường xuyên; da tự nhiên làm từ da bò, da cừu hoặc da dê, giá bán cao và cũng cần bảo dưỡng thường xuyên.
- Theo chungauto.vn (tháng 10/2026), bọc ghế da cho VinFast VF7 có giá tham khảo 5.000.000 – 9.000.000 VNĐ với da công nghiệp, 8.000.000 – 22.000.000 VNĐ với da Nappa và 20.000.000 – 100.000.000 VNĐ với da tự nhiên.
- Theo chungauto.vn (tháng 10/2026), ghế da cho VinFast VF3 có giá tham khảo 5.500.000 đồng với da công nghiệp, 7.500.000 đồng với da Nappa và 9.500.000 đồng với da Nappa Lux.
- Quy trình bọc ghế da tại ChungAuto gồm 6 bước: đánh giá xe, tư vấn và cắt may lớp áo da; lột bỏ lớp bọc cũ; tháo ghế; bọc bộ da mới lên ghế; sấy ghế và vuốt tay làm phẳng các vết gấp da; lắp lại ghế, kiểm tra và bàn giao; với VinFast VF7, thời gian bọc là 6 – 20 tiếng (gồm cả may áo da), hoặc 3 – 5 tiếng nếu đặt may trước.
- Phương pháp bọc tháo nỉ (bọc kẹp chết) là tháo bỏ lớp nỉ rồi bọc lớp da mới vào; cách này chi phí cao hơn và thi công lâu hơn nhưng ghế căng mịn, form đẹp hơn.
- Khi bọc ghế da không được để lớp bọc mới ảnh hưởng đến dây đai an toàn, cơ cấu gập trượt và các trang bị an toàn của ghế; nếu xe có túi khí bên hông ghế phải dùng giải pháp tương thích được xác nhận.
- Nên vệ sinh ghế da định kỳ 1 tuần/lần tại nhà bằng khăn sạch và dung dịch vệ sinh chuyên dụng không chứa axit, đồng thời hạn chế đỗ xe ngoài nắng để ghế da không bị nứt nẻ hoặc bạc màu.
- Nguồn: https://chungauto.vn/ghe-dien-vf-wild-nang-cap-trai-nghiem-ngoi-lai-tien-nghi-hon-2815.html; https://chungauto.vn/boc-ghe-da-cho-vinfast-vf7-tu-van-chi-tiet-bao-gia-2458.html; https://chungauto.vn/ghe-da-vf3-dep-tong-hop-mau-hot-va-bao-gia-moi-nhat-3019.html
TXT],
    'vo-lang' => ['/vo-lang/', <<<'TXT'
## Phụ kiện: Vô lăng (theo chungauto.vn)
- Vô lăng là chi tiết người lái tiếp xúc thường xuyên nhất; sau một thời gian sử dụng, bề mặt có thể trơn, bám bẩn hoặc không còn mang lại cảm giác cầm nắm phù hợp.
- Nâng cấp vô lăng không nhất thiết phải thay toàn bộ vô lăng; phần lớn khách hàng chọn xử lý bề mặt bên ngoài bằng cách bọc, khâu da hoặc thay đổi một số chi tiết trang trí.
- Bọc hoặc khâu da vô lăng giúp tăng độ bám, hạn chế trơn tay khi lái lâu, giảm tác động của mồ hôi tay và ma sát lên bề mặt nguyên bản, che vết trầy, và đồng bộ màu với ghế da, tapi cửa hoặc chi tiết nội thất khác.
- Có thể nâng cấp vô lăng ngay khi mới nhận xe hoặc sau một thời gian, thường khi cầm chưa chắc tay, bề mặt trơn hoặc xước nhẹ, muốn đổi phong cách nội thất, hoặc đã bọc ghế da và muốn phối màu đồng bộ.
- Ốp bọc vô lăng là sản phẩm làm sẵn theo kích thước vành vô lăng, lắp bao quanh bên ngoài nên không cần khâu, thời gian thực hiện nhanh, làm vành dày hơn, có thể tháo ra hoặc thay mới và có nhiều kiểu bề mặt, màu sắc, họa tiết.
- Ốp bọc vô lăng có độ ôm sát và cảm giác liền khối không bằng khâu da; chọn loại quá dày có thể làm kích thước vành thay đổi đáng kể, còn chọn loại không vừa có thể kém chắc tay và dễ xê dịch khi đánh lái.
- Khâu da vô lăng dùng lớp da được đo, cắt theo form vành vô lăng rồi khâu trực tiếp quanh bề mặt, giúp da ôm sát, chỉ tăng độ dày vừa phải, hạn chế cộm hoặc xê dịch, và cho phép chọn màu da, màu chỉ, kiểu đường may.
- Khâu da cần nhiều thời gian và kỹ thuật hơn, chi phí cao hơn bọc, vì thợ phải căn da, xử lý điểm nối và kiểm soát độ căng; nếu cắt hoặc căn da không chuẩn, bề mặt có thể bị nếp nhăn hoặc đường nối không đều.
- Theo chungauto.vn, ốp bọc vô lăng phù hợp người cần chi phí thấp, lắp nhanh, dễ tháo hoặc dùng xe trong thời gian ngắn, còn khâu da phù hợp người dùng xe lâu dài, muốn độ bám tốt và bề mặt hoàn thiện gọn hơn.
- Các chất liệu khâu da vô lăng được chungauto.vn nêu gồm da Nappa mềm mại, da đục lỗ thông thoáng và các kiểu phối Carbon, mỗi loại có mức giá và độ bền khác nhau.
- Quy trình khâu da vô lăng tại ChungAuto được thực hiện thủ công, gồm xử lý bề mặt, cắt rập, khâu và hoàn thiện.
- Khi khâu da vô lăng, da chỉ nên được khâu quanh vành vô lăng và chân chấu, tuyệt đối không bọc hoặc khâu đè lên nắp túi khí để đảm bảo túi khí bung được khi xảy ra va chạm.
- Có thể kết hợp khâu da vô lăng với bọc ghế da hoặc đổi màu nội thất để đồng bộ khoang cabin, và thi công nhiều hạng mục cùng lúc giúp tiết kiệm thời gian.
- Theo chungauto.vn (tháng 10/2026), nâng cấp vô lăng cho VinFast VF5 có giá tham khảo từ 250.000đ đến 550.000đ với dạng bọc và từ 800.000đ đến 1.200.000đ với dạng khâu da; giá thực tế thay đổi theo loại da, kiểu chỉ, phạm vi carbon và thiết kế.
- Trước khi nâng cấp vô lăng, nên xác nhận rõ vật liệu, phạm vi thi công và tổng chi phí.
- Nguồn: https://chungauto.vn/vo-lang-vf5-nen-boc-hay-khau-da-bang-gia-va-cach-chon-phu-hop-2777.html; https://chungauto.vn/vo-lang-minio-green-nen-boc-da-hay-khau-da-2690.html
TXT],
    'man-hinh-android' => ['/android|man-hinh/', <<<'TXT'
## Phụ kiện: Màn hình Android và Android Box (theo chungauto.vn)
- Theo chungauto.vn, VF Wild được trang bị màn hình trung tâm 10 inch; hệ thống giải trí nguyên bản giới hạn ở các ứng dụng cơ bản, chưa hỗ trợ các ứng dụng giải trí trực tuyến phổ biến và thiếu công cụ cảnh báo giao thông chuyên dụng cho người Việt.
- Có hai hướng nâng cấp cho VF Wild được chungauto.vn nêu: lắp Android Box cắm vào xe và giữ nguyên màn hình zin, hoặc lắp màn hình Android như TexPad TX24 Limited 13 inch.
- Android Box kết nối qua giao thức CarPlay/Android Auto có sẵn trên xe, giúp màn hình zin chuyển sang hệ điều hành Android sau khoảng 15 – 30 giây kết nối mà không cần tháo gỡ taplo.
- Các thương hiệu Android Box cho VF Wild được nêu gồm TexPad, Zestech, Elliview, Oled Pro, Gotech và Vietmap; Vietmap mạnh về bản đồ cảnh báo giao thông, Elliview có giao diện widget cá nhân hóa, Oled Pro là tùy chọn tối ưu chi phí.
- Các mã Android Box cho VF Wild được nêu: TexPad TP650 Plus (RAM 4GB, hỗ trợ chia đôi màn hình bản đồ và nghe nhạc), TexPad TP850 Plus (phân khúc cao cấp của TexPad, vỏ nhôm tản nhiệt) và Zestech DX265 Plus (trợ lý Kiki, cập nhật phần mềm tự động OTA).
- Android Box hỗ trợ xem YouTube, nghe nhạc Spotify, Zing MP3, xem phim trực tuyến; cảnh báo camera phạt nguội, camera bắn tốc độ và hiển thị tốc độ giới hạn qua bản đồ dẫn đường; trợ lý ảo ra lệnh giọng nói nghe hiểu giọng 3 miền.
- Theo chungauto.vn, sau khi kết nối Android Box, phím bấm chỉnh nhạc và nút ra lệnh giọng nói trên vô lăng nguyên bản của VF Wild vẫn hoạt động bình thường.
- Quy trình lắp Android Box cho VF Wild gồm 3 bước: kỹ thuật viên kiểm tra phiên bản phần mềm trên xe để cấu hình dòng điện nuôi box, cắm giắc kết nối thiết bị với cổng USB/Type-C trên xe, rồi kích hoạt phần mềm bản quyền và hướng dẫn chủ xe sử dụng; thời gian hoàn thiện dự kiến từ 15 đến 30 phút.
- Theo chungauto.vn, Android Box lắp bằng phương pháp cắm giắc kết nối, không cắt trích dây điện, giữ tính nguyên bản và không ảnh hưởng đến chế độ bảo hành chính hãng.
- Theo chungauto.vn, Android Box cho VF Wild được bảo hành chính hãng từ 2 đến 5 năm tùy nhà sản xuất, và tùy gói nâng cấp có thể tặng kèm bản đồ dẫn đường bản quyền và SIM 4G.
- Thông số và tính năng Android Box trên VF Wild hiện chủ yếu mang tính tham khảo và cần được kỹ thuật viên kiểm tra theo từng phiên bản xe thực tế.
- Màn hình Android TexPad TX24 Limited có kích thước 13 inch, có phiên bản RAM 4GB hoặc 8GB, dung lượng lưu trữ tùy gói và có các gói tích hợp camera.
- Theo chungauto.vn (tháng 10/2026), màn hình Android TexPad TX24 Limited 13 inch có giá tham khảo 13.500.000 đồng (RAM 4GB/ROM 64GB), 15.500.000 đồng (RAM 4GB/ROM 64GB kèm camera 360) và 20.000.000 đồng (RAM 8GB/ROM 256GB tích hợp camera); đây là giá theo cấu hình sản phẩm, chưa xác nhận là chi phí lắp hoàn thiện riêng cho VF Wild.
- Trước khi lắp màn hình Android cho VF Wild cần kiểm tra mặt dưỡng, giắc kết nối và kích thước màn hình có phù hợp với xe, xác nhận các tính năng điều khiển và hiển thị nguyên bản vẫn hoạt động sau nâng cấp, và hạn chế can thiệp không cần thiết vào hệ thống điện; không nên chọn màn hình chỉ dựa vào kích thước hoặc cấu hình.
- Nguồn: https://chungauto.vn/giai-phap-nang-cap-android-box-vf-wild-2712.html; https://chungauto.vn/man-hinh-android-vf-wild-texpad-tx24-limited-13-inch-2975.html
TXT],
    'hud' => ['/(^|-)hud(-|$)/', <<<'TXT'
## Phụ kiện: Màn hình hiển thị kính lái HUD (theo chungauto.vn)
- HUD là hệ thống hiển thị thông tin lái xe trong tầm nhìn của tài xế, giúp theo dõi tốc độ và các thông số cần thiết mà không phải cúi mắt xuống màn hình trung tâm quá thường xuyên.
- HUD giúp dễ quan sát tốc độ đang chạy để điều chỉnh phù hợp với giới hạn trên từng cung đường, hạn chế việc đi quá tốc độ.
- Tùy thiết bị, HUD có thể hiển thị chỉ dẫn di chuyển khi đi đường lạ, mức pin và các thông số vận hành khác của xe.
- Độ rõ của HUD phụ thuộc vào thiết bị, vị trí lắp đặt và điều kiện ánh sáng, nên kiểm tra khả năng hiển thị dưới trời nắng trước khi mua.
- Có hai phương án trang bị HUD: HUD chính hãng lắp từ hãng xe và HUD phụ kiện lắp thêm bên ngoài; HUD chính hãng có chi phí cao hơn đáng kể nhưng đồng bộ với xe và được kết nối phù hợp với hệ thống nguyên bản.
- Theo chungauto.vn (tháng 10/2026), HUD chính hãng cho VinFast VF6 (tùy chọn lắp tại nhà máy) có giá tham khảo khoảng 9 triệu đồng, còn HUD phụ kiện cho VF6 có giá tham khảo từ 2 triệu đồng.
- Khi lắp HUD cần chọn vị trí không che khuất tầm nhìn và căn chỉnh khi đã ngồi đúng tư thế lái; nên thử hiển thị cả ban ngày lẫn ban đêm để chỉnh độ sáng phù hợp.
- Thiết bị HUD và tấm kính đi kèm cần được cố định chắc chắn để tránh rung lắc khi xe di chuyển.
- Khi lắp HUD phụ kiện nên hỏi rõ cách cấp nguồn và kết nối, hạn chế can thiệp vào hệ thống điện nguyên bản; nên hỏi đơn vị lắp về bảo hành thiết bị và hỏi đại lý VinFast về ảnh hưởng đến bảo hành xe.
- Khi lái xe chỉ nên liếc nhanh thông tin trên HUD, không nhìn lâu để tránh mất tập trung.
- Nguồn: https://chungauto.vn/hud-vf6-giup-nguoi-lai-quan-sat-toc-do-thuan-tien-ra-sao-3018.html
TXT],
    'goi-do-xe' => ['/(^|-)do-xe|(^|-)do-vf|nang-cap|goi-do|phu-kien-(vf|can|nen)/', <<<'TXT'
## Phụ kiện: Gói độ xe (theo chungauto.vn)
- Độ xe VF Wild là nâng cấp, bổ sung hoặc thay thế phụ kiện để tối ưu công năng sử dụng và tăng thẩm mỹ; các hạng mục phổ biến gồm dán phim cách nhiệt, thảm sàn, cảm biến áp suất lốp, độ đèn, độ âm thanh, nắp thùng và phụ kiện bảo vệ.
- Theo chungauto.vn, khi độ VF Wild, ChungAuto ưu tiên giải pháp cắm giắc zin, không cắt trích dây điện, không can thiệp vào pin cao áp, mô tơ điện hay hệ thống điều khiển nguyên bản của xe.
- Gói thiết yếu nên làm ngay khi nhận VF Wild gồm dán phim cách nhiệt, thảm lót sàn, camera hành trình, cảm biến áp suất lốp và tấm ốp bảo vệ pin, dành cho nhu cầu bảo vệ xe và tăng an toàn khi đi hằng ngày.
- Trong gói thiết yếu cho VF Wild, phim cách nhiệt gốm (ceramic) giúp giảm nhiệt cabin có diện tích kính lớn, tiết kiệm điện cho điều hòa và không cản trở tín hiệu GPS, sóng cảm biến; các dòng được giới thiệu là 3M, Inmax, Ceramax.
- Thảm lót sàn cho VF Wild nên là loại tràn viền hoặc thảm đúc đúng khuôn xe, vì thảm xô lệch có thể vướng chân ga hoặc chân phanh; các dòng được giới thiệu là Catrend, Uban, Kata.
- Camera hành trình cho VF Wild nên chọn mẫu nhỏ gọn, giấu dây, lấy nguồn qua cổng USB, tẩu sạc hoặc bộ chuyển đổi riêng cho xe; các dòng được giới thiệu là Texpad, Vietmap và Xiaomi 70mai; cảm biến áp suất lốp cho VF Wild có các dòng TexPad, Steelmate, Careud.
- Tấm ốp bảo vệ pin được chungauto.vn khuyến nghị cho VF Wild vì pin đặt dưới gầm xe chịu tác động trực tiếp của đá văng, ổ gà và ngập nước.
- Gói hỗ trợ quan sát cho VF Wild gồm camera 360 toàn cảnh hoặc cảm biến cảnh báo va chạm, dành cho người thường đỗ xe trong phố, bãi hẹp hoặc đi đường địa hình; các dòng camera 360 được giới thiệu là TexPad, Zestech, Safeview, Gotech, DCT.
- Gói giải trí cho VF Wild gồm nâng cấp âm thanh (loa cánh, loa treble, loa sub gầm ghế, DSP hoặc ampli, dán cách âm) và Android Box cắm cổng USB zin để mở rộng ứng dụng như YouTube, Vietmap, Spotify mà vẫn giữ màn hình và tính năng nguyên bản; thiết bị âm thanh cần chọn công suất phù hợp để không ảnh hưởng hệ thống điện 12V.
- Gói đèn trợ sáng cho VF Wild gồm bi LED, bi gầm và bi laser, dành cho người hay đi đường dài, đi đêm qua khu vực thiếu đèn hoặc đi địa hình; khi lắp cần căn chỉnh góc chiếu để tránh gây chói xe đối diện; các dòng được giới thiệu là Platinum và Xlight.
- Gói tiện nghi ghế cho VF Wild gồm bọc ghế da, nâng cấp ghế chỉnh điện, thông gió làm mát ghế, và với xe gia đình có thể thêm nhớ vị trí hoặc massage, tùy phiên bản xe.
- Gói thùng xe và ngoại thất cho VF Wild gồm bậc lên xuống, nắp thùng cuộn điện hoặc cuộn cơ, khay lót thùng chống trầy, thanh thể thao, giá nóc và giá chở đồ, vách ngăn hoặc khay chia đồ, đèn khoang thùng; thanh thể thao nên làm từ hợp kim thép sơn tĩnh điện chống gỉ, lắp chuẩn chân zin để không phải khoan đục thân xe.
- Gói dã ngoại và off-road cho VF Wild gồm giá nóc hoặc lều nóc (chọn hợp kim nhôm hoặc thép chịu lực, chú ý tải trọng an toàn), tời điện có sức kéo phù hợp tải trọng xe để tự cứu hộ khi sa lầy, ống thở, ốp cua lốp, body kit, cùng phim PPF và decal để bảo vệ sơn và trang trí.
- Gói nâng cấp kết cấu cho VF Wild gồm phuộc giảm xóc giúp êm hơn và cân bằng tốt khi chở nặng hoặc đi địa hình, và mâm thể thao kết hợp lốp đa địa hình AT; không nên thay mâm có thông số quá khác nguyên bản vì có thể ảnh hưởng vận hành, hệ thống treo và đăng kiểm.
- Thứ tự độ VF Wild được chungauto.vn đề xuất: phim cách nhiệt, thảm sàn, lót thùng và cảm biến áp suất lốp; sau đó camera hành trình, camera 360; rồi Android Box và âm thanh; đèn tăng sáng; nắp thùng, bậc lên xuống và phụ kiện thùng; cuối cùng là mâm lốp đa địa hình và phụ kiện bảo vệ; các thiết bị điện như màn hình, camera, loa, cảm biến nên thi công cùng một đợt để đỡ tháo taplo, tapi cửa và đi dây nhiều lần.
- Theo chungauto.vn, nếu lắp thiết bị bằng cắm giắc zin, không can thiệp hệ thống điện nguyên bản hay bộ pin cao áp thì VF Wild vẫn được bảo hành bình thường; độ loa hoặc sub điện dùng điện từ ắc quy 12V phụ trợ, không gây hao hụt pin cao áp và không ảnh hưởng tầm vận hành.
- Nguồn: https://chungauto.vn/do-vf-wild-sao-cho-dung-cac-goi-nang-cap-va-bao-gia-moi-nhat-2-2696.html
TXT],
];

/** Dữ kiện phụ kiện cho một từ khóa: tối đa $n nhóm khớp nhất + phần chung về ChungAuto. */
function auto_accessory_facts(string $kw, int $n = 2): string {
    $k = blog_slugify($kw, 0); $out = [];
    foreach (AUTO_ACC_FACTS as [$re, $txt]) { if (preg_match($re, $k)) $out[] = $txt; if (count($out) >= $n) break; }
    $out[] = AUTO_ACC_GENERAL;
    return implode("\n\n", $out);
}

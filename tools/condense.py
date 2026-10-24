# Rút gọn chữ trên index.html, giữ nguyên toàn bộ các mục. Luôn chạy từ index.orig.html.
import re, json, html as H

D = 'D:/Chungauto/vfwild/vfwild/'
s = open('D:/Chungauto/vfwild/backup/index.orig.html', encoding='utf-8').read()
fails = []


def rep(a, b, label=None):
    global s
    if a not in s:
        fails.append(label or a[:50])
    s = s.replace(a, b, 1)


def regex(pattern, repl, label=None):
    global s
    s, n = re.subn(pattern, lambda m: repl, s, count=1, flags=re.S)
    if n != 1:
        fails.append(label or pattern[:50])


def p_after_h3(h3, newp):
    global s
    pat = re.compile(r'(<h3>' + re.escape(h3) + r'</h3><p>).*?(</p>)', re.S)
    s, n = pat.subn(lambda m: m.group(1) + newp + m.group(2), s, count=1)
    if n != 1:
        fails.append('h3:' + h3)


# hero + trust
regex(r'<span class="hero__sub-more">.*?</span>', '', 'hero sub')
regex(r'<p class="trust__text">.*?</p>',
      '<p class="trust__text">Giá tính theo bảng dự tính 19/9/2026 · <strong>thông số theo công bố VinFast</strong></p>', 'trust')

# giá & ưu đãi
regex(r'<p>Chọn màu và cách mua\..*?</p>', '<p>Chọn màu và cách mua – ưu đãi tính đúng thứ tự trong bảng dự tính giá.</p>', 'uu-dai head')
rep('* Mức giá ưu đãi chỉ mang tính chất tham khảo tại thời điểm báo giá. Chương trình áp dụng có thể thay đổi. Giá chưa gồm phí lăn bánh.',
    '* Giá tham khảo, chưa gồm phí lăn bánh; chương trình có thể thay đổi.')

# REEV
rep('<p>Chạy điện hằng ngày. Khi pin thấp, máy phát xăng <b>tự nạp điện</b> để bạn tiếp tục hành trình.</p>',
    '<p>Chạy điện hằng ngày; pin thấp thì máy phát xăng <b>tự nạp điện</b>.</p>')

# ba hành trình
for k, v in {
    'Chở hàng, đi công trình': 'Thùng 1.574 × 1.512 × 509 mm, tải 750 kg.',
    'Đi cùng gia đình': 'Cabin kép 5 chỗ, vận hành êm, màn hình 10 inch.',
    'Dã ngoại có điện': 'V2L cấp điện cho thiết bị khi cắm trại.',
    'Pin LFP bền bỉ': 'LFP 46,4 kWh, hợp khí hậu nóng ẩm.',
    'Chi phí rõ từng đồng': 'Giá ưu đãi, lăn bánh, trả góp tính ngay trên trang.',
    'Trang bị tại ChungAuto': 'Thảm sàn, phụ kiện, độ xe tại ChungAuto.',
}.items():
    p_after_h3(k, v)
rep('<p class="note center">Thông số theo thông tin VinFast công bố ngày 19/9/2026 – xem đầy đủ tại <a href="#thong-so">bảng thông số</a>.</p>',
    '<p class="note center">Xem đầy đủ tại <a href="#thong-so">bảng thông số</a>.</p>')

# thông số
rep('<p>Theo tài liệu VinFast công bố ngày 19/9/2026. Mục “Chưa công bố” sẽ được cập nhật khi có thông tin chính thức.</p>', '')
drop_rows = {'Quãng đường thuần điện', 'Tổng quãng đường kết hợp', 'Bình nhiên liệu', 'Chiều dài cơ sở', 'Hệ thống treo',
             'Phanh trước / sau', 'La-zăng / lốp', 'Khả năng kéo', 'Camera sau', 'Ga tự động (Cruise control)',
             'Nội thất', 'Sạc không dây', 'Chế độ cắm trại', 'Trợ lý ảo', 'Sạc miễn phí', 'Tải trọng'}
i = s.index('<div class="spectable">')
j = s.index('<p class="note center">', i)
seg = re.sub(r'<tr><th>(.*?)</th><td>.*?</td></tr>',
             lambda m: '' if m.group(1) in drop_rows else m.group(0), s[i:j], flags=re.S)
s = s[:i] + seg + s[j:]
regex(r'<p class="note center">Bảo hành xe và pin:.*?</p>',
      '<p class="note center">Bảo hành tính theo điều kiện đến trước. Một số tính năng nâng cao thuộc gói VF eStore có thu phí. '
      'Xe VinFast công bố là bản tiền thương mại, thông số có thể thay đổi.</p>', 'spec note')

# lợi thế đặc biệt
for k, v in {
    'Không giới hạn niên hạn sử dụng': 'Đăng ký ô tô con pick-up, không bị giới hạn 25 năm như bán tải đăng ký xe tải.',
    'Miễn lệ phí trước bạ (0%)': 'Xe REEV thuộc nhóm xe điện (Thông tư 45/2026), trước bạ 0% đến hết năm 2030.',
    'Lấy điện từ xe ra dùng (V2L)': 'Cấp điện cho thiết bị khi cắm trại, làm việc ngoài trời.',
    'Khung gầm liền khối – êm như xe con': 'Thân liền khối, treo trước tay đòn kép, sau đa liên kết – êm như xe con.',
}.items():
    p_after_h3(k, v)
rep('<p>👉 Một chiếc bán tải êm ái: đi cà phê trong phố nhẹ nhàng, cuối tuần chở cả nhà đi cắm trại – quá phù hợp cho gia đình ở các thành phố lớn.</p>',
    '<p>👉 Bán tải êm ái, hợp gia đình ở thành phố lớn.</p>')

# chi phí vận hành, hình ảnh, màu
rep('<p>Kéo thanh trượt theo quãng đường của bạn. Giả định giá dầu, giá điện và mức tiêu hao đều chỉnh được.</p>',
    '<p>Kéo thanh trượt theo quãng đường của bạn; các giả định đều chỉnh được.</p>')
rep('Ước tính khi chạy hoàn toàn bằng điện trong quãng đường thuần điện. Mức tiêu hao thực tế phụ thuộc tải, tốc độ và đường đi.',
    'Ước tính khi chạy hoàn toàn bằng điện; thực tế phụ thuộc tải, tốc độ, đường đi.')
rep('<p>Video và ảnh thực tế do ChungAuto quay chụp tại showroom. Bấm vào ảnh để xem cỡ lớn.</p>',
    '<p>Ảnh và video thực tế tại showroom ChungAuto. Bấm ảnh để xem lớn.</p>')
rep('Ảnh chụp ngày 22/9/2026. Xe trưng bày là phiên bản tiền thương mại VinFast đưa về đại lý; bản thương mại chính thức có thể khác một số chi tiết.',
    'Ảnh chụp 22/9/2026; xe trưng bày là bản tiền thương mại, bản chính thức có thể khác chi tiết.')
rep('<p>Đen, Đỏ, Trắng cùng mức giá niêm yết; màu Bạc có giá riêng. Ảnh minh họa – màu thực tế có thể khác do màn hình.</p>',
    '<p>Đen, Đỏ, Trắng cùng giá niêm yết; Bạc có giá riêng. Ảnh minh họa, màu thực tế có thể khác.</p>')

# so sánh
regex(r'(?<=<span class="eyebrow">So sánh</span>)(\s*<h2>.*?</h2>\s*)<p>.*?</p>', '', 'cmp head') if False else None
s, n = re.subn(r'(<span class="eyebrow">So sánh</span>\s*<h2>.*?</h2>\s*)<p>.*?</p>',
               lambda m: m.group(1) + '<p>Giá niêm yết, sức mạnh, thùng hàng, an toàn, bảo hành – số liệu công bố tháng 9/2026.</p>',
               s, count=1, flags=re.S)
if n != 1:
    fails.append('cmp head')
for k, v in {
    'Giá nằm giữa phân khúc': '860 triệu: thấp hơn Hilux, Triton, Navara, D-Max; cao hơn Ranger XLS 4x4 (776 triệu). Chưa trừ ưu đãi.',
    'Điện thay cho dầu': 'Chạy điện trên 250 km mỗi lần sạc; máy xăng 1,5 L chỉ phát điện, tổng trên 1.000 km (NEDC).',
    'Xe rộng, thùng lớn, bảo hành 6 năm': 'Rộng 2.069 mm, thùng 1.574 × 1.512 mm, tải 750 kg; bảo hành xe 6 năm, pin 8 năm.',
    'Cần cân nhắc': 'VF Wild dẫn động cầu trước, đối thủ là 2 cầu (4x4). Gầm 222 mm, Hilux cao nhất bảng (312 mm).',
}.items():
    p_after_h3(k, v)
keep = {'Giá niêm yết bản so sánh', 'Công suất', 'Mô-men xoắn', 'Dẫn động', 'Khoảng sáng gầm',
        'Thùng hàng (D × R × C)', 'Tải trọng', 'Bảo hành', 'Lệ phí trước bạ'}
i = s.index('id="so-sanh"')
j = s.index('id="cach-mua"')
seg = re.sub(r'<tr><th scope="row">(.*?)</th>.*?</tr>',
             lambda m: m.group(0) if m.group(1) in keep else '', s[i:j], flags=re.S)
seg, n = re.subn(r'<p class="compare__note">Giá niêm yết \(đã gồm VAT\).*?</p>',
                 '<p class="compare__note">Giá niêm yết đã gồm VAT theo bảng giá hãng (kiểm tra 24/9/2026), chưa gồm khuyến mãi. '
                 'Xe điện ghi công suất kW, xe dầu ghi PS, kèm quy đổi mã lực. Nguồn: <a href="/nguon-du-lieu/">trang Nguồn dữ liệu</a>.</p>',
                 seg, count=1, flags=re.S)
if n != 1:
    fails.append('cmp footnote')
seg = seg.replace('<b>Đang cân nhắc VF Wild hay bán tải dầu?</b> Tư vấn viên so sánh chi phí thực tế theo quãng đường bạn chạy mỗi tháng.',
                  '<b>Đang cân nhắc VF Wild hay bán tải dầu?</b> Tư vấn viên so sánh chi phí theo quãng đường bạn chạy.')
s = s[:i] + seg + s[j:]

# 3 cách mua + hành trình mua xe
for a, b in [
    ('<li>Bảng lăn bánh chi tiết theo tỉnh</li><li>Quà thảm sàn Nappa Clear từ ChungAuto</li><li>Hỗ trợ thủ tục đăng ký, ra biển</li>',
     '<li>Bảng lăn bánh theo tỉnh</li><li>Quà thảm sàn Nappa Clear</li><li>Hỗ trợ đăng ký, ra biển</li>'),
    ('<li>So sánh phương án vay theo nhu cầu</li><li>Hướng dẫn hồ sơ trực tiếp với tư vấn viên</li><li>Không thu giấy tờ, thu nhập trên website</li>',
     '<li>So sánh phương án vay</li><li>Tư vấn hồ sơ trực tiếp</li><li>Website không thu giấy tờ, thu nhập</li>'),
    ('<li>Báo giá theo số lượng &amp; tiến độ giao</li><li>Hồ sơ hóa đơn doanh nghiệp</li><li>Tư vấn trang bị theo công việc</li>',
     '<li>Báo giá theo số lượng</li><li>Hóa đơn doanh nghiệp</li><li>Tư vấn trang bị theo việc</li>'),
    ('* Giá tham khảo tại thời điểm báo giá, chưa gồm phí lăn bánh. Báo giá bằng văn bản của tư vấn viên là con số cuối cùng.',
     '* Giá tham khảo, chưa gồm phí lăn bánh; báo giá bằng văn bản của tư vấn viên là con số cuối cùng.'),
    ('<small>Nhận bảng giá lăn bánh bằng văn bản theo tỉnh của bạn.</small>', '<small>Nhận bảng giá lăn bánh bằng văn bản.</small>'),
    ('<small>Tại showroom khi có xe trưng bày/lái thử.</small>', '<small>Tại showroom khi có xe.</small>'),
    ('<small>Tại showroom hoặc online qua nền tảng O2O của VinFast.</small>', '<small>Tại showroom hoặc online qua O2O.</small>'),
    ('<small>Chọn ngân hàng, hoàn thiện hồ sơ với tư vấn viên.</small>', '<small>Chọn ngân hàng, hoàn thiện hồ sơ.</small>'),
    ('<small>ChungAuto hướng dẫn và hỗ trợ thủ tục.</small>', '<small>ChungAuto hỗ trợ thủ tục.</small>'),
    ('<small>Lắp thảm sàn Nappa Clear miễn phí tại ChungAuto.</small>', '<small>Lắp thảm sàn miễn phí tại ChungAuto.</small>'),
]:
    rep(a, b, 'cach-mua:' + a[:40])

# showroom + liên hệ
rep('11 điểm ChungAuto và 3 điểm đối tác · Mở cửa 8:30 – 17:30 tất cả các ngày.', '11 điểm ChungAuto, 3 điểm đối tác · 8:30 – 17:30 mỗi ngày.')
rep('<p>Để lại thông tin, tư vấn viên ChungAuto gọi lại để gửi bảng giá lăn bánh, phương án trả góp, lịch xem xe và hướng dẫn đặt cọc.</p>',
    '<p>Để lại thông tin, tư vấn viên gọi lại gửi giá lăn bánh, trả góp và lịch xem xe.</p>')

# Hỏi đáp: giữ đủ 14 câu, viết ngắn lại; đồng bộ cả JSON-LD FAQPage
faq = {
    'Giá VinFast VF Wild 2026 bao nhiêu?': 'Niêm yết 860 triệu (Đen, Đỏ, Trắng), 872 triệu (Bạc), đã gồm VAT. Giảm 5% rồi giảm thêm 2% nếu mua qua O2O của VinFast: giá dự kiến 800.660.000đ (bản Đen, Đỏ, Trắng). Giá tham khảo, thay đổi theo chương trình.',
    'VF Wild là xe điện hay xe xăng?': 'REEV là xe điện mở rộng phạm vi: mô-tơ điện dẫn động bánh xe, động cơ xăng chỉ làm máy phát nạp điện cho pin khi đi xa.',
    'Hết pin thì sao, có phải lúc nào cũng đổ xăng?': 'Pin LFP 46,4 kWh chạy điện trên 250 km (NEDC). Pin thấp, máy phát xăng 1,5 L nạp điện, tổng quãng đường trên 1.000 km (NEDC).',
    'Bảo hành và sạc miễn phí của VF Wild thế nào?': 'Xe 6 năm/150.000 km, pin 8 năm/160.000 km (tùy điều kiện nào đến trước). Miễn phí 20 lần sạc mỗi tháng đến 10/02/2029.',
    'VF Wild có được miễn lệ phí trước bạ không?': 'Có. Xe REEV thuộc nhóm xe điện (Thông tư 45/2026), lệ phí trước bạ 0% đến hết năm 2030.',
    'VF Wild đăng ký là xe con hay xe tải?': 'Đăng ký là ô tô con pick-up: không giới hạn niên hạn 25 năm như bán tải xe tải, không bị cấm giờ xe tải tại Hà Nội; phí biển số và phí đường bộ theo mức xe con.',
    'Khi nào nhận được xe?': 'VinFast chưa công bố lịch giao chính thức; báo chí đưa tin từ tháng 10/2026. Thời hạn cụ thể ghi trong hợp đồng.',
    'Tiền đặt cọc có được hoàn lại không?': 'Theo điều khoản chung của VinFast: không hoàn nếu khách không ký hợp đồng hoặc không nhận xe đúng hạn; hoàn nếu bên bán không ký hợp đồng hoặc không giao xe (trừ lỗi của khách). Tư vấn viên gửi văn bản điều kiện riêng khi mở cọc.',
    'Các ưu đãi có được cộng dồn không?': 'Chỉ cộng dồn khi văn bản của VinFast cho phép, tính đúng thứ tự quy định. Khi chưa có văn bản, ChungAuto hiển thị từng ưu đãi riêng, không cộng thành tổng.',
    'Mua qua O2O là gì, có được giảm 2% không?': 'O2O là kênh bán hàng trực tuyến của VinFast (vinfastauto.com): đặt cọc, thanh toán, ký hợp đồng điện tử, nhận xe tại Vincom hoặc tại nhà. Mua qua O2O được giảm 2% trên giá còn lại; ChungAuto hướng dẫn từng bước.',
    'Thùng xe chở được bao nhiêu?': 'Thùng 1.574 × 1.512 × 509 mm, tải 750 kg, gầm 222 mm. Khả năng kéo moóc VinFast chưa công bố.',
    'Đã có xe lái thử chưa?': 'Lịch lái thử khác nhau theo showroom. Để lại số điện thoại và showroom gần nhất, ChungAuto báo khi có xe.',
    'Mua trả góp cần chuẩn bị gì?': 'Dùng máy tính trả góp trên trang để ước tính. Hồ sơ vay (giấy tờ tùy thân, chứng minh thu nhập) do tư vấn viên hướng dẫn trực tiếp; website không thu thập giấy tờ hay thu nhập.',
    'ChungAuto có phải là VinFast không?': 'Không. ChungAuto (Công ty CP Chung Group) là đại lý ủy quyền phân phối VinFast VF Wild. Thông tin chính thức tại vinfastauto.com.',
}
for q, a in faq.items():
    pat = re.compile(r'(<summary>' + re.escape(H.escape(q, quote=False)) + r'</summary><p>).*?(</p>)', re.S)
    s, n = pat.subn(lambda m: m.group(1) + H.escape(a, quote=False) + m.group(2), s, count=1)
    if n != 1:
        fails.append('faq:' + q)


def fix_ld(m):
    data = json.loads(m.group(2))
    hit = 0
    for nd in data.get('@graph', [data]):
        if nd.get('@type') == 'FAQPage':
            for e in nd.get('mainEntity', []):
                if e.get('name') in faq:
                    e['acceptedAnswer']['text'] = faq[e['name']]
                    hit += 1
    if hit != len(faq):
        fails.append('ld-faq hits %d/%d' % (hit, len(faq)))
    return m.group(1) + json.dumps(data, ensure_ascii=False) + m.group(3)


s = re.sub(r'(<script type="application/ld\+json"[^>]*>)(.*?)(</script>)', fix_ld, s, count=1, flags=re.S)
open(D + 'index.html', 'w', encoding='utf-8').write(s)
print('FAILS:', fails)

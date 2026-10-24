# Tối ưu trang /lai-thu-vf-wild/: tiêu đề + mô tả, dữ liệu có cấu trúc từng showroom (AutoDealer + địa chỉ + điện thoại), mục Hỏi đáp (FAQPage).
# Chỉ dùng thông tin đã có trên chính trang này. Chạy lặp lại không bị trùng.
import json
import re

F = 'D:/Chungauto/vfwild/vfwild/lai-thu-vf-wild/index.html'
URL = 'https://vfwild.vn/lai-thu-vf-wild/'
TITLE = 'Đăng ký lái thử VinFast VF Wild | 14 điểm lái thử ChungAuto'
DESC = 'Đăng ký lái thử VinFast VF Wild tại 14 điểm (Hà Nội, TP.HCM, Đà Nẵng, Nghệ An…). Chúng tôi báo khi showroom có xe, không hẹn lịch khi chưa có xe.'

h = open(F, encoding='utf-8').read()

# ---- tiêu đề, mô tả
h = re.sub(r'<title>.*?</title>', '<title>' + TITLE + '</title>', h, count=1, flags=re.S)
h = re.sub(r'<meta name="description" content="[^"]*">', '<meta name="description" content="' + DESC + '">', h, count=1)
h = re.sub(r'<meta property="og:title" content="[^"]*">', '<meta property="og:title" content="' + TITLE + '">', h, count=1)
h = re.sub(r'<meta property="og:description" content="[^"]*">', '<meta property="og:description" content="' + DESC + '">', h, count=1)

# ---- đọc danh sách showroom ngay từ trang
rooms = []
for m in re.finditer(r'<article class="sr"[^>]*>(.*?)</article>', h, re.S):
    a = m.group(1)
    name = re.sub(r'<small.*?</small>', '', re.search(r'<h3>(.*?)</h3>', a, re.S).group(1)).strip()
    partner = 'sr__tag--partner' in a
    addr = re.sub(r'<[^>]+>', '', re.search(r'<p>(.*?)</p>', a, re.S).group(1)).strip()
    tel = re.search(r'href="tel:(\d+)"', a).group(1)
    rooms.append((name, partner, addr, tel))
assert len(rooms) == 14, len(rooms)

LOC = {'Hà Nội': 'Hà Nội', 'TP. Hồ Chí Minh': 'TP. Hồ Chí Minh', 'Đà Nẵng': 'Đà Nẵng', 'Nghệ An': 'Nghệ An', 'Hà Tĩnh': 'Hà Tĩnh',
       'Lào Cai': 'Lào Cai', 'Ninh Bình': 'Ninh Bình'}


def phone(t):
    return '+84-18008282' if t == '18008282' else '+84' + t.lstrip('0')


nodes = []
for name, partner, addr, tel in rooms:
    if partner or addr.startswith('Gọi để nhận'):
        continue   # chỉ khai báo điểm của ChungAuto đã có địa chỉ cụ thể
    city = next((v for k, v in LOC.items() if name.startswith(k)), None)
    street = re.sub(r',\s*(Hà Nội|TP\. HCM|Đà Nẵng|Nghệ An|Hà Tĩnh|Lào Cai|Ninh Bình)\s*$', '', addr)
    nodes.append({'@type': 'AutoDealer', 'name': 'ChungAuto – ' + name, 'url': URL, 'telephone': phone(tel),
                  'address': {'@type': 'PostalAddress', 'streetAddress': street, 'addressLocality': city or '', 'addressCountry': 'VN'},
                  'parentOrganization': {'@id': 'https://vfwild.vn/#dealer'}})

FAQ = [
    ('Đăng ký lái thử VF Wild như thế nào?',
     'Bạn điền họ tên, số điện thoại và chọn showroom gần nhất ở biểu mẫu trên trang này, hoặc gọi tổng đài miễn phí 1800 8282. Tư vấn viên gọi lại trong giờ làm việc (8:30 – 17:30 tất cả các ngày).'),
    ('Đăng ký rồi có lái thử ngay được không?',
     'Chưa chắc. Lịch có xe lái thử khác nhau theo từng showroom, và ChungAuto không hẹn lịch khi chưa có xe. Tư vấn viên sẽ báo lại khi showroom bạn chọn có xe.'),
    ('ChungAuto có những điểm lái thử VF Wild nào?',
     'Có 11 showroom ChungAuto: Hà Nội (Mỹ Đình, Long Biên, Hoài Đức), TP. Hồ Chí Minh, Đà Nẵng, Nghệ An, Hà Tĩnh, Lào Cai, Ninh Bình, Vĩnh Phúc (Phú Thọ) và Thái Nguyên, cùng 3 điểm đối tác ở Bắc Ninh và Tuyên Quang. Địa chỉ và số điện thoại từng điểm ở phần Showroom bên trên.'),
    ('Khi đi lái thử VF Wild cần mang theo giấy tờ gì?',
     'Theo hướng dẫn của VinFast, bạn cần mang CMND/CCCD và giấy phép lái xe còn hiệu lực. Phiếu đăng ký lái thử và cam kết lái xe an toàn do VinFast chuẩn bị theo mẫu có sẵn tại điểm lái thử.'),
    ('Lái thử VF Wild có mất phí không?',
     'Theo trang đăng ký của VinFast, lái thử ô tô điện VinFast là hoàn toàn miễn phí. Với VF Wild tại showroom ChungAuto, tư vấn viên xác nhận điều kiện cụ thể khi hẹn lịch với bạn.'),
    ('Lái thử xong có thể đặt cọc không?',
     'Có. Bạn có thể đặt cọc tại showroom hoặc đặt cọc online qua kênh O2O của VinFast. Xem hướng dẫn ở trang Đặt cọc &amp; O2O.'),
]

# ---- dữ liệu có cấu trúc
m = re.search(r'(<script type="application/ld\+json">)(.*?)(</script>)', h, re.S)
d = json.loads(m.group(2))
g = [n for n in d['@graph'] if n.get('@type') not in ('FAQPage',) and not (n.get('@type') == 'AutoDealer' and n.get('@id') is None)]
faq_ld = {'@type': 'FAQPage', 'mainEntity': [{'@type': 'Question', 'name': q, 'acceptedAnswer': {'@type': 'Answer', 'text': re.sub(r'&amp;', '&', a)}} for q, a in FAQ]}
d['@graph'] = g + nodes + [faq_ld]
h = h[:m.start(2)] + json.dumps(d, ensure_ascii=False, separators=(', ', ': ')) + h[m.end(2):]

# ---- mục Hỏi đáp trước </main>
h = re.sub(r'<section class="section" id="hoi-dap">.*?</section>\s*', '', h, flags=re.S)   # dựng lại mục Hỏi đáp mỗi lần chạy
if True:
    items = '\n'.join('<details class="faq__item"%s><summary>%s</summary><p>%s</p></details>' % (' open' if i == 0 else '', q, a) for i, (q, a) in enumerate(FAQ))
    sec = ('<section class="section" id="hoi-dap">\n  <div class="container container--narrow">\n    <div class="section__head"><span class="eyebrow">Hỏi đáp</span>'
           '<h2>Giải đáp về <em>lái thử VF Wild</em></h2></div>\n    <div class="faq">' + items + '</div>\n  </div>\n</section>\n\n')
    h = h.replace('</main>', sec + '</main>', 1)

open(F, 'w', encoding='utf-8', newline='').write(h)
print('showroom khai báo:', len(nodes), '| FAQ:', len(FAQ))

# Dựng trang /ve-chung-toi/ từ khung trang Điều khoản, thêm vào sitemap và gắn link "Về ChungAuto" vào chân trang mọi trang HTML.
# Chạy lặp lại không bị trùng. Chỉ dùng thông tin đã có sẵn trên website (không thêm số liệu mới).
import glob
import json
import os
import re

SITE = 'D:/Chungauto/vfwild/vfwild/'
URL = 'https://vfwild.vn/ve-chung-toi/'
TITLE = 'Về ChungAuto – đơn vị vận hành vfwild.vn'
DESC = 'ChungAuto (Công ty CP Chung Group) là đơn vị vận hành vfwild.vn: tư vấn mua VinFast VF Wild, giá lăn bánh theo tỉnh, trả góp, thủ tục và phụ kiện. Thông tin pháp lý, liên hệ, cách ghi nguồn và cập nhật nội dung.'

t = open(SITE + 'dieu-khoan/index.html', encoding='utf-8').read()
head_end = t.index('<main id="main">')
tail_start = t.index('</main>')
head, tail = t[:head_end], t[tail_start:]

# ----- phần đầu trang: tiêu đề, mô tả, canonical, og
head = re.sub(r'<title>.*?</title>', '<title>' + TITLE + '</title>', head, count=1, flags=re.S)
head = re.sub(r'<meta name="description" content="[^"]*">', '<meta name="description" content="' + DESC + '">', head, count=1)
head = head.replace('https://vfwild.vn/dieu-khoan/', URL)
head = re.sub(r'<meta property="og:title" content="[^"]*">', '<meta property="og:title" content="' + TITLE + '">', head, count=1)
head = re.sub(r'<meta property="og:description" content="[^"]*">', '<meta property="og:description" content="' + DESC + '">', head, count=1)

# ----- dữ liệu có cấu trúc: đơn vị + trang giới thiệu + đường dẫn
m = re.search(r'(<script type="application/ld\+json">)(.*?)(</script>)', head, re.S)
data = json.loads(m.group(2))
dealer = data['@graph'][0]
dealer['sameAs'] = ['https://www.facebook.com/ChungAuto.vncom/', 'https://chungauto.vn']
crumbs = {'@type': 'BreadcrumbList', 'itemListElement': [
    {'@type': 'ListItem', 'position': 1, 'name': 'Trang chủ', 'item': 'https://vfwild.vn/'},
    {'@type': 'ListItem', 'position': 2, 'name': 'Về ChungAuto', 'item': URL}]}
about = {'@type': 'AboutPage', '@id': URL + '#page', 'url': URL, 'name': TITLE, 'description': DESC, 'inLanguage': 'vi', 'about': {'@id': 'https://vfwild.vn/#dealer'}}
data['@graph'] = [dealer, about, crumbs]
head = head[:m.start(2)] + json.dumps(data, ensure_ascii=False, separators=(', ', ': ')) + head[m.end(2):]

main = '''<main id="main">

<section class="page-hero">
  <div class="container">
    <p class="breadcrumb"><a href="/">Trang chủ</a> › Về ChungAuto</p>
    <h1>Về ChungAuto – đơn vị vận hành vfwild.vn</h1>
  </div>
</section>
<section class="section">
  <div class="container prose">
    <h2>Chúng tôi là ai</h2>
    <p>vfwild.vn do <strong>Công ty Cổ phần Chung Group</strong> (thương hiệu <strong>ChungAuto</strong>) vận hành. Website tập trung vào một chủ đề: tư vấn và hỗ trợ mua <strong>VinFast VF Wild</strong> — giá sau ưu đãi, chi phí lăn bánh theo tỉnh, phương án trả góp, thủ tục đăng ký và phụ kiện tại hệ thống ChungAuto.</p>

    <h2>Nội dung trên website và blog</h2>
    <p>Các bài trong <a href="/blog/">Blog</a> do ChungAuto biên soạn nhằm giúp người mua hiểu rõ giá, chi phí và thủ tục trước khi quyết định. Số liệu về giá, ưu đãi, thông số và phí lăn bánh được ghi nguồn tại trang <a href="/nguon-du-lieu/">Nguồn dữ liệu</a>.</p>
    <p>Khi một bài được chỉnh sửa nội dung, bài hiển thị thêm ngày “Cập nhật” bên cạnh ngày đăng. Giá và ưu đãi chỉ mang tính tham khảo tại thời điểm cập nhật; con số cuối cùng là báo giá bằng văn bản và hợp đồng mua bán.</p>
    <p>Bạn thấy thông tin chưa chính xác hoặc đã cũ? Hãy báo cho chúng tôi qua hotline hoặc Zalo bên dưới để được kiểm tra và chỉnh sửa.</p>

    <h2>Thông tin doanh nghiệp</h2>
    <ul>
      <li>Tên đơn vị: Công ty Cổ phần Chung Group</li>
      <li>Mã số thuế: 0110297480 (cấp ngày 24/03/2023, Sở Tài chính TP Hà Nội)</li>
      <li>Trụ sở: Đội 4, Xã Mỹ Đức, TP Hà Nội, Việt Nam</li>
      <li>Website của công ty: <a href="https://chungauto.vn" rel="noopener">chungauto.vn</a></li>
      <li>Fanpage: <a href="https://www.facebook.com/ChungAuto.vncom/" target="_blank" rel="noopener">Facebook ChungAuto</a></li>
    </ul>

    <h2>Liên hệ</h2>
    <ul>
      <li>Tổng đài miễn phí: <a href="tel:18008282">1800 8282</a></li>
      <li>Hotline VF Wild: <a href="tel:0784368314">0784 368 314</a> · <a href="https://zalo.me/0784368314" target="_blank" rel="noopener">Chat Zalo</a></li>
      <li>Giờ làm việc: 8:30 – 17:30 tất cả các ngày</li>
    </ul>

    <h2>Lưu ý</h2>
    <p>Đây không phải website chính thức của VinFast. Thông tin chính thức của hãng xem tại vinfastauto.com. “VinFast”, “VF Wild” là nhãn hiệu của chủ sở hữu tương ứng. Xem thêm <a href="/dieu-khoan/">Điều khoản sử dụng</a> và <a href="/chinh-sach-bao-mat/">Chính sách bảo mật</a>.</p>
  </div>
</section>

'''
os.makedirs(SITE + 've-chung-toi', exist_ok=True)
open(SITE + 've-chung-toi/index.html', 'w', encoding='utf-8', newline='\n').write(head + main + tail)

# ----- sitemap
sm = open(SITE + 'sitemap.xml', encoding='utf-8').read()
if URL not in sm:
    entry = '<url><loc>' + URL + '</loc><lastmod>2026-10-22</lastmod></url>'
    k = sm.index('<url><loc>https://vfwild.vn/blog/</loc>')
    sm = sm[:k] + entry + sm[k:]
    open(SITE + 'sitemap.xml', 'w', encoding='utf-8', newline='').write(sm)

# ----- chân trang
A = '<a href="/nguon-du-lieu/">Nguồn dữ liệu</a>'
B = A + '\n      <a href="/ve-chung-toi/">Về ChungAuto</a>'
changed = 0
for f in glob.glob(SITE + '**/*.html', recursive=True):
    if '.orig' in f or '.v1-' in f:
        continue
    s = open(f, encoding='utf-8').read()
    a = s.find('<footer')
    if a < 0 or '/ve-chung-toi/' in s[a:]:
        continue
    foot = s[a:]
    if A not in foot:
        continue
    open(f, 'w', encoding='utf-8', newline='').write(s[:a] + foot.replace(A, B, 1))
    changed += 1
print('Đã gắn link chân trang vào', changed, 'trang')

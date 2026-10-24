# Gắn link "Blog" vào menu đầu trang và chân trang của mọi trang HTML; tăng số phiên bản CSS để trình duyệt tải style mới.
# Chạy lặp lại không bị trùng. Chạy SAU condense.py / condense2.py / build_blog.py.
import glob
import re

SITE = 'D:/Chungauto/vfwild/vfwild/'
import hashlib
# Số phiên bản CSS lấy theo nội dung file: sửa CSS là trình duyệt tự tải bản mới
CSS_NEW = 'style.css?v=' + hashlib.md5(open(SITE + 'css/style.css', 'rb').read()).hexdigest()[:10]

NAV_A = '<a href="/#showroom">Showroom</a>'
NAV_B = NAV_A + '\n      <a href="/blog/">Blog</a>'
FOOT_A = '<a href="/nguon-du-lieu/">Nguồn dữ liệu</a>'
FOOT_B = FOOT_A + '\n      <a href="/blog/">Blog</a>'

changed = 0
for f in glob.glob(SITE + '**/*.html', recursive=True):
    if '.orig' in f or '.v1-' in f:
        continue
    s = open(f, encoding='utf-8').read()
    o = s
    if '<a href="/blog/">Blog</a>' not in s:
        s = s.replace(NAV_A, NAV_B, 1).replace(FOOT_A, FOOT_B, 1)
    s = re.sub(r'\s*<a class="sb-cart".*?</a>', '', s, flags=re.S)   # bỏ biểu tượng giỏ hàng ở thanh dưới trên điện thoại
    s = re.sub(r'style\.css\?v=[0-9a-zA-Z]+', CSS_NEW, s)
    if s != o:
        open(f, 'w', encoding='utf-8').write(s)
        changed += 1
print('Đã cập nhật', changed, 'trang')

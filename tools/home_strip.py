# Chèn dải "Bài viết mới" (bản dự phòng không cần JS) vào index.html, lấy từ blog/latest.json do trang quản trị tạo.
# Trang chủ sau đó tự cập nhật qua js/blogstrip.js, nên chỉ cần chạy lại script này khi dựng lại index.html từ condense.py.
import html
import json
import re

SITE = 'D:/Chungauto/vfwild/vfwild/'
VER = '202610201'

latest = json.load(open(SITE + 'blog/latest.json', encoding='utf-8'))[:3]
s = open(SITE + 'index.html', encoding='utf-8').read()
s = re.sub(r'<!-- blog-strip -->.*?<!-- /blog-strip -->\n*', '', s, flags=re.S)
if latest:
    items = ''.join(
        '<a class="blogstrip__item" href="/blog/%s/"><span class="blogstrip__cat">%s</span><strong>%s</strong><span class="blogstrip__more">Đọc bài →</span></a>'
        % (p['slug'], html.escape(p['category']), html.escape(p['title'])) for p in latest)
    strip = ('<!-- blog-strip -->\n<section class="blogstrip" id="bai-viet" aria-label="Bài viết mới"><div class="container">'
             '<div class="blogstrip__head"><h2>Bài viết mới</h2><a href="/blog/">Xem tất cả →</a></div>'
             '<div class="blogstrip__list" data-blogstrip>' + items + '</div></div></section>\n'
             '<script src="/js/blogstrip.js?v=' + VER + '" defer></script>\n<!-- /blog-strip -->\n\n')
    marker = '<section class="section section--alt" id="showroom">'
    assert marker in s, 'không tìm thấy mục showroom'
    s = s.replace(marker, strip + marker, 1)
open(SITE + 'index.html', 'w', encoding='utf-8').write(s)
print('Dải bài viết mới:', len(latest), 'bài')

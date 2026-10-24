# ĐÃ THAY THẾ: blog nay được tạo bởi trang quản trị (vfwild/quan-tri/blog-lib.php, lệnh: php quan-tri/index.php --rebuild-blog).
# File này chỉ còn để tham khảo, KHÔNG chạy: nó sẽ ghi đè các trang blog do trang quản trị tạo.
# Tạo blog tĩnh cho vfwild.vn từ các file Markdown trong blog_src/.
# Đầu ra: vfwild/blog/index.html và vfwild/blog/<slug>/index.html, cập nhật sitemap.xml.
# Dùng khung (header, footer, popup, script) của trang chinh-sach-bao-mat để giống hệt giao diện site.
import glob
import html
import json
import math
import os
import re
from datetime import date

ROOT = 'D:/Chungauto/vfwild/'
SITE = ROOT + 'vfwild/'
SRC = ROOT + 'blog_src/'
BASE = 'https://vfwild.vn'
OG_DEFAULT = BASE + '/img/og/vf-wild-chungauto.jpg'
CSS_VER = '202610201'

tpl = open(SITE + 'chinh-sach-bao-mat/index.html', encoding='utf-8').read()
i_main = tpl.index('<main id="main">')
i_end = tpl.index('</main>') + len('</main>')
PREFIX, SUFFIX = tpl[:i_main], tpl[i_end:]
PREFIX = PREFIX.replace('style.css?v=202609300903', 'style.css?v=' + CSS_VER)


def esc(x):
    return html.escape(x, quote=True)


# ---------- Markdown tối giản (đủ cho bài blog) ----------
def inline(t):
    t = html.escape(t, quote=False)
    t = re.sub(r'\*\*(.+?)\*\*', r'<strong>\1</strong>', t)
    t = re.sub(r'\[([^\]]+)\]\(([^)\s]+)\)', lambda m: '<a href="%s">%s</a>' % (esc(m.group(2)), m.group(1)), t)
    return t


def md_to_html(text):
    out, para, lines = [], [], text.strip().split('\n')

    def flush():
        if para:
            out.append('<p>' + inline(' '.join(para)) + '</p>')
            para.clear()

    i = 0
    while i < len(lines):
        ln = lines[i].rstrip()
        if not ln.strip():
            flush(); i += 1; continue
        m = re.match(r'(#{2,3})\s+(.*)', ln)
        if m:
            flush(); n = len(m.group(1))
            out.append('<h%d>%s</h%d>' % (n, inline(m.group(2)), n)); i += 1; continue
        if ln.lstrip().startswith('|'):
            flush(); rows = []
            while i < len(lines) and lines[i].lstrip().startswith('|'):
                rows.append([c.strip() for c in lines[i].strip().strip('|').split('|')]); i += 1
            head, body = rows[0], [r for r in rows[2:]]
            t = '<div class="tablewrap"><table><thead><tr>' + ''.join('<th>%s</th>' % inline(c) for c in head) + '</tr></thead><tbody>'
            for r in body:
                t += '<tr>' + ''.join('<td>%s</td>' % inline(c) for c in r) + '</tr>'
            out.append(t + '</tbody></table></div>'); continue
        if re.match(r'(-|\d+\.)\s+', ln):
            flush(); ordered = ln[0].isdigit(); items = []
            while i < len(lines) and re.match(r'(-|\d+\.)\s+', lines[i]):
                items.append(re.sub(r'^(-|\d+\.)\s+', '', lines[i].rstrip())); i += 1
            tag = 'ol' if ordered else 'ul'
            out.append('<%s>' % tag + ''.join('<li>%s</li>' % inline(x) for x in items) + '</%s>' % tag); continue
        if ln.startswith('>'):
            flush(); q = []
            while i < len(lines) and lines[i].startswith('>'):
                q.append(lines[i].lstrip('> ').rstrip()); i += 1
            out.append('<blockquote>' + inline(' '.join(q)) + '</blockquote>'); continue
        para.append(ln.strip()); i += 1
    flush()
    return '\n'.join(out)


def load_posts():
    posts = []
    for f in sorted(glob.glob(SRC + '*.md')):
        raw = open(f, encoding='utf-8').read()
        m = re.match(r'---\n(.*?)\n---\n(.*)', raw, re.S)
        meta = dict(re.findall(r'^(\w+):\s*(.*)$', m.group(1), re.M))
        body = m.group(2)
        meta['body_html'] = md_to_html(body)
        meta['words'] = len(re.findall(r'\w+', body))
        meta['mins'] = max(1, math.ceil(meta['words'] / 200))
        for k in ('title', 'slug', 'description', 'date', 'category', 'cover'):
            assert k in meta, '%s thiếu %s' % (f, k)
        posts.append(meta)
    posts.sort(key=lambda p: p['date'], reverse=True)
    return posts


def vn_date(iso):
    y, m, d = iso.split('-')
    return '%d/%d/%s' % (int(d), int(m), y)


def head_for(title, desc, path, og_type, og_image, extra_ld, seo_title=None, extra_meta=''):
    h = PREFIX
    t = seo_title or title
    if len(t) + len(' | VF Wild ChungAuto') <= 60:   # Google cắt tiêu đề ~60 ký tự: chỉ thêm tên site khi còn chỗ
        t += ' | VF Wild ChungAuto'
    full_title = esc(t)
    og_title = esc(title)
    url = BASE + path
    h = re.sub(r'<title>.*?</title>', '<title>%s</title>' % full_title, h, count=1, flags=re.S)
    h = re.sub(r'<meta name="description" content="[^"]*">', '<meta name="description" content="%s">' % esc(desc), h, count=1)
    h = re.sub(r'<link rel="canonical" href="[^"]*">', '<link rel="canonical" href="%s">' % url, h, count=1)
    h = re.sub(r'<meta property="og:type" content="[^"]*">', '<meta property="og:type" content="%s">' % og_type, h, count=1)
    h = re.sub(r'<meta property="og:title" content="[^"]*">', '<meta property="og:title" content="%s">' % og_title, h, count=1)
    h = re.sub(r'<meta property="og:description" content="[^"]*">', '<meta property="og:description" content="%s">' % esc(desc), h, count=1)
    h = re.sub(r'<meta property="og:url" content="[^"]*">', '<meta property="og:url" content="%s">' % url + extra_meta, h, count=1)
    h = re.sub(r'<meta property="og:image" content="[^"]*">', '<meta property="og:image" content="%s">' % og_image, h, count=1)
    # JSON-LD: giữ thông tin đại lý sẵn có, thêm bài viết + breadcrumb
    m = re.search(r'(<script type="application/ld\+json">)(.*?)(</script>)', h, re.S)
    data = json.loads(m.group(2))
    data['@graph'] = [n for n in data['@graph'] if n.get('@type') != 'BreadcrumbList']  # bỏ breadcrumb của trang mẫu
    data['@graph'].extend(extra_ld)
    h = h[:m.start()] + m.group(1) + json.dumps(data, ensure_ascii=False) + m.group(3) + h[m.end():]
    return h


def crumbs(items):
    return {'@type': 'BreadcrumbList', 'itemListElement': [
        {'@type': 'ListItem', 'position': i + 1, 'name': n, 'item': BASE + u} for i, (n, u) in enumerate(items)]}


def card(p):
    return ('<a class="blog-card" href="/blog/%s/"><span class="blog-card__img"><img src="%s" alt="%s" width="800" height="500" loading="lazy" decoding="async"></span>'
            '<span class="blog-card__body"><span class="blog-card__meta">%s · %s</span><h3>%s</h3><p>%s</p>'
            '<span class="blog-card__more">Đọc bài →</span></span></a>') % (
        p['slug'], esc(p['cover']), esc(p.get('cover_alt', p['title'])), esc(p['category']), vn_date(p['date']), esc(p['title']), esc(p['description']))


def inject_home_strip(posts):
    """Dải "Bài viết mới" gọn trên trang chủ: 3 bài mới nhất, chỉ chữ, đặt sau Hỏi đáp."""
    f = SITE + 'index.html'
    s = open(f, encoding='utf-8').read()
    s = re.sub(r'<!-- blog-strip -->.*?<!-- /blog-strip -->\n*', '', s, flags=re.S)
    items = ''.join(
        '<a class="blogstrip__item" href="/blog/%s/"><span class="blogstrip__cat">%s</span><strong>%s</strong><span class="blogstrip__more">Đọc bài →</span></a>'
        % (p['slug'], esc(p['category']), esc(p['title'])) for p in posts[:3])
    strip = ('<!-- blog-strip -->\n<section class="blogstrip" id="bai-viet" aria-label="Bài viết mới"><div class="container">'
             '<div class="blogstrip__head"><h2>Bài viết mới</h2><a href="/blog/">Xem tất cả →</a></div>'
             '<div class="blogstrip__list">' + items + '</div></div></section>\n<!-- /blog-strip -->\n\n')
    marker = '<section class="section section--alt" id="showroom">'
    assert marker in s, 'không tìm thấy mục showroom'
    s = s.replace(marker, strip + marker, 1)
    open(f, 'w', encoding='utf-8').write(s)


def build():
    posts = load_posts()
    os.makedirs(SITE + 'blog', exist_ok=True)

    # trang danh sách
    desc = 'Tin tức, giá, so sánh và hướng dẫn mua VinFast VF Wild từ ChungAuto: giá sau ưu đãi, chi phí vận hành, thủ tục đăng ký.'
    head = head_for('Blog VinFast VF Wild – giá, so sánh, thủ tục', desc, '/blog/', 'website', OG_DEFAULT,
                    [crumbs([('Trang chủ', '/'), ('Blog', '/blog/')])])
    body = ('\n<main id="main">\n\n<section class="page-hero"><div class="container">'
            '<p class="breadcrumb"><a href="/">Trang chủ</a> › Blog</p><h1>Blog VinFast VF Wild</h1>'
            '<p>Giá, so sánh, chi phí vận hành và thủ tục mua xe – ngắn gọn, số liệu rõ nguồn.</p></div></section>\n\n'
            '<section class="section"><div class="container"><div class="blog-grid">' + ''.join(card(p) for p in posts) +
            '</div></div></section>\n\n</main>')
    open(SITE + 'blog/index.html', 'w', encoding='utf-8').write(head + body + SUFFIX)

    # từng bài
    for p in posts:
        path = '/blog/%s/' % p['slug']
        cover_abs = BASE + p['cover']
        ld = [
            {'@type': 'BlogPosting', 'headline': p['title'], 'description': p['description'], 'datePublished': p['date'],
             'dateModified': p['date'], 'inLanguage': 'vi', 'image': og_img if False else cover_abs.replace('-800.jpg', '-1600.jpg'), 'mainEntityOfPage': BASE + path,
             'author': {'@type': 'Organization', 'name': 'ChungAuto'},
             'publisher': {'@type': 'Organization', 'name': 'Công ty Cổ phần Chung Group'}},
            crumbs([('Trang chủ', '/'), ('Blog', '/blog/'), (p['title'], path)]),
        ]
        og_img = BASE + p['cover'].replace('-800.jpg', '-1600.jpg')
        meta_dates = ('<meta property="article:published_time" content="%sT08:00:00+07:00">'
                      '<meta property="article:modified_time" content="%sT08:00:00+07:00">'
                      '<meta property="article:section" content="%s">') % (p['date'], p['date'], esc(p['category']))
        head = head_for(p['title'], p['description'], path, 'article', og_img, ld, p.get('seo_title'), meta_dates)
        related = [q for q in posts if q['slug'] != p['slug']][:3]
        main = ('\n<main id="main">\n\n<section class="page-hero"><div class="container">'
                '<p class="breadcrumb"><a href="/">Trang chủ</a> › <a href="/blog/">Blog</a> › %s</p><h1>%s</h1>'
                '<p class="post__meta">%s · %s · %d phút đọc</p></div></section>\n\n'
                '<section class="section"><div class="container"><article class="prose post">'
                '<img class="post__cover" src="%s" alt="%s" width="800" height="500" decoding="async">\n%s\n'
                '<div class="post__cta"><h2>Nhận báo giá VF Wild</h2><p>Tư vấn viên ChungAuto gửi giá sau ưu đãi, chi phí lăn bánh và phương án trả góp.</p>'
                '<a href="#dang-ky" class="btn btn--primary" data-open-lead="blog" data-set-intent="bao-gia">Nhận báo giá →</a></div>'
                '</article>'
                '<aside class="post__related"><h2>Bài viết khác</h2><div class="blog-grid">%s</div></aside>'
                '</div></section>\n\n</main>') % (
            esc(p['title']), esc(p['title']), esc(p['category']), vn_date(p['date']), p['mins'],
            esc(p['cover']), esc(p.get('cover_alt', p['title'])), p['body_html'], ''.join(card(q) for q in related))
        d = SITE + 'blog/%s/' % p['slug']
        os.makedirs(d, exist_ok=True)
        open(d + 'index.html', 'w', encoding='utf-8').write(head + main + SUFFIX)

    # sitemap: thêm /blog/ và các bài (không đụng các URL có sẵn)
    sm = open(SITE + 'sitemap.xml', encoding='utf-8').read()
    sm = re.sub(r'<url><loc>https://vfwild\.vn/blog/.*?</url>', '', sm, flags=re.S)
    today = date.today().isoformat()
    urls = '<url><loc>%s/blog/</loc><lastmod>%s</lastmod></url>' % (BASE, posts[0]['date'])
    urls += ''.join('<url><loc>%s/blog/%s/</loc><lastmod>%s</lastmod></url>' % (BASE, p['slug'], p['date']) for p in posts)
    sm = sm.replace('</urlset>', urls + '</urlset>')
    open(SITE + 'sitemap.xml', 'w', encoding='utf-8').write(sm)

    # CSS cho blog (chỉ thêm một lần)
    css = open(SITE + 'css/style.css', encoding='utf-8').read()
    if '/* blog */' not in css:
        css += '''
/* blog */
.blog-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.4rem}
.blog-card{display:flex;flex-direction:column;background:#fff;border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;color:inherit;text-decoration:none;transition:.25s var(--ease-out)}
.blog-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-sm);border-color:var(--green)}
.blog-card__img{display:block;aspect-ratio:16/10;background:var(--bg-alt);overflow:hidden}
.blog-card__img img{width:100%;height:100%;object-fit:cover;display:block}
.blog-card__body{display:flex;flex-direction:column;gap:.5rem;padding:1.1rem 1.2rem 1.3rem;flex:1}
.blog-card__meta{font-size:.8rem;color:var(--green-t);font-weight:700;text-transform:uppercase;letter-spacing:.03em}
.blog-card h3{font-family:var(--display);font-size:1.12rem;line-height:1.35;margin:0}
.blog-card p{color:var(--muted);font-size:.92rem;margin:0}
.blog-card__more{margin-top:auto;color:var(--green);font-weight:700;font-size:.92rem}
.post__meta{color:var(--muted);font-size:.9rem}
.post__cover{width:100%;height:auto;aspect-ratio:16/10;object-fit:cover;border-radius:var(--radius);margin:0 0 1.6rem;display:block}
.prose .tablewrap{overflow-x:auto;margin:0 0 1.4rem;border:1px solid var(--line);border-radius:12px}
.prose table{border-collapse:collapse;width:100%;min-width:480px;font-size:.95rem}
.prose th,.prose td{padding:.7rem .9rem;text-align:left;border-bottom:1px solid var(--line);vertical-align:top}
.prose th,.prose td{min-width:7.5rem}
.prose td:first-child,.prose th:first-child{min-width:9rem;font-weight:700}
.prose thead th{background:var(--bg-alt);font-family:var(--display)}
.prose tbody tr:last-child td{border-bottom:0}
.prose blockquote{margin:0 0 1.4rem;padding:.9rem 1.2rem;border-left:4px solid var(--green);background:var(--bg-alt);border-radius:0 12px 12px 0;color:var(--muted);font-size:.95rem}
.post__cta{margin:2.2rem 0 0;padding:1.6rem;background:linear-gradient(160deg,#eafaf1,#f4f8f5);border:1px solid var(--line);border-radius:var(--radius)}
.post__cta h2{margin:0 0 .4rem}
.post__cta .btn{margin-top:.4rem;text-decoration:none;color:#fff}
.post__related{margin-top:3rem}
.post__related h2{font-family:var(--display);font-size:1.3rem;margin:0 0 1.2rem}

/* blogstrip */
.blogstrip{padding:2rem 0 2.4rem;border-top:1px solid var(--line)}
.blogstrip__head{display:flex;justify-content:space-between;align-items:baseline;gap:1rem;margin-bottom:1rem}
.blogstrip__head h2{font-family:var(--display);font-size:1.25rem;margin:0}
.blogstrip__head a{color:var(--green);font-weight:700;font-size:.92rem;white-space:nowrap}
.blogstrip__list{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem}
.blogstrip__item{display:flex;flex-direction:column;gap:.3rem;padding:.9rem 1.1rem;background:#fff;border:1px solid var(--line);border-radius:14px;color:inherit;text-decoration:none;transition:.2s}
.blogstrip__item:hover{border-color:var(--green);box-shadow:var(--shadow-sm)}
.blogstrip__cat{font-size:.74rem;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:var(--green-t)}
.blogstrip__item strong{font-family:var(--display);font-size:.98rem;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.blogstrip__more{font-size:.85rem;font-weight:700;color:var(--green);margin-top:auto}
@media(max-width:760px){.blogstrip__list{grid-template-columns:1fr;gap:.6rem}.blogstrip__item{flex-direction:row;align-items:center;gap:.7rem;padding:.75rem .95rem}.blogstrip__cat{display:none}.blogstrip__item strong{flex:1;font-size:.93rem}.blogstrip__more{white-space:nowrap;margin:0;font-size:.8rem}}
'''
        open(SITE + 'css/style.css', 'w', encoding='utf-8').write(css)
    inject_home_strip(posts)
    print('Đã tạo %d bài + trang danh sách + dải trên trang chủ' % len(posts))


if __name__ == '__main__':
    build()

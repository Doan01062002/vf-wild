# Giai đoạn 2: gọn bố cục các mục chữ. Ảnh/video giữ nguyên.
# Chạy sau condense.py (đọc và ghi lại index.html).
import re, json

D = 'D:/Chungauto/vfwild/vfwild/'
s = open(D + 'index.html', encoding='utf-8').read()
fails = []


def rep(a, b, label=None):
    global s
    if a not in s:
        fails.append(label or a[:50])
    s = s.replace(a, b, 1)


def regex(pattern, repl, label=None, count=1):
    global s
    s, n = re.subn(pattern, repl if callable(repl) else (lambda m: repl), s, count=count, flags=re.S)
    if n != count:
        fails.append((label or pattern[:50]) + ' n=%d' % n)


# 1. Giá & ưu đãi: bỏ khối "Dịch vụ đi kèm"
regex(r'\s*<div class="gifts__group gifts__group--svc">.*?</div>\n', '\n', 'svc')

# 2. REEV: bỏ 3 thẻ lặp lại sơ đồ phía trên
regex(r'\s*<div class="cards cards--short">.*?</div>\n(?=\s*<div class="section__cta">)', '\n', 'reev cards')

# 3. Ba hành trình: đúng 3 thẻ như tiêu đề (bỏ Pin LFP, Chi phí, Trang bị — đã có ở mục khác)
for h in ['Pin LFP bền bỉ', 'Chi phí rõ từng đồng', 'Trang bị tại ChungAuto']:
    regex(r'<article class="card reveal">(?:(?!</article>).)*?<h3>' + re.escape(h) + r'</h3>.*?</article>\s*', '', 'card ' + h)

# 4. Thông số: gập 3 bảng sau nút
rep('<div class="reveal"><div class="spectable">', '<details class="reveal moretoggle"><summary>Xem bảng thông số chi tiết</summary><div class="spectable">')
i = s.index('<details class="reveal moretoggle">')
j = s.index('<p class="note center">', i)
k = s.rfind('</div></div>', i, j)
s = s[:k] + '</div></details>' + s[k + len('</div></div>'):]

# 5. Lợi thế đặc biệt: bỏ câu kết, giữ nút
regex(r'\s*<p>👉 Bán tải êm ái, hợp gia đình ở thành phố lớn\.</p>', '', 'verdict')

# 6. So sánh: bỏ 2 thẻ nhận xét trùng với bảng/hero, giữ "giá" và "cần cân nhắc"
for h in ['Điện thay cho dầu', 'Xe rộng, thùng lớn, bảo hành 6 năm']:
    regex(r'<article class="card cmp__insight"><h3>' + re.escape(h) + r'</h3>.*?</article>', '', 'insight ' + h)

# 7. Ba cách mua: mỗi gói 2 ý; hành trình mua xe: chỉ giữ tiêu đề bước
i = s.index('class="pricing reveal"')
j = s.index('</div>\n    <p class="note center">', i)
seg = s[i:j]
seg = re.sub(r'<ul>(<li>.*?</li>)(<li>.*?</li>)<li>.*?</li></ul>', r'<ul>\1\2</ul>', seg, flags=re.S)
s = s[:i] + seg + s[j:]
i = s.index('<ol class="steps reveal">')
j = s.index('</ol>', i)
s = s[:i] + re.sub(r'<small>.*?</small>', '', s[i:j], flags=re.S) + s[j:]

# 8. Hỏi đáp: bỏ 5 câu đã có ở mục khác (kể cả trong JSON-LD FAQPage)
drop_q = ['VF Wild đăng ký là xe con hay xe tải?', 'Các ưu đãi có được cộng dồn không?', 'Thùng xe chở được bao nhiêu?',
          'Đã có xe lái thử chưa?', 'Mua trả góp cần chuẩn bị gì?']
for q in drop_q:
    regex(r'<details class="faq__item"><summary>' + re.escape(q) + r'</summary>.*?</details>\n?', '', 'faq ' + q)


def fix_ld(m):
    data = json.loads(m.group(2))
    for nd in data.get('@graph', [data]):
        if nd.get('@type') == 'FAQPage':
            before = len(nd['mainEntity'])
            nd['mainEntity'] = [e for e in nd['mainEntity'] if e.get('name') not in drop_q]
            if before - len(nd['mainEntity']) != len(drop_q):
                fails.append('ld drop %d' % (before - len(nd['mainEntity'])))
    return m.group(1) + json.dumps(data, ensure_ascii=False) + m.group(3)


s = re.sub(r'(<script type="application/ld\+json"[^>]*>)(.*?)(</script>)', fix_ld, s, count=1, flags=re.S)

# 9. Showroom: hiện 4 điểm lớn, còn lại gập
i = s.index('<div class="showrooms">')
j = s.index('</div>\n  </div>\n</section>', i)
arts = re.findall(r'<article class="sr".*?</article>', s[i + len('<div class="showrooms">'):j], flags=re.S)
first = [a for a in arts if any(k in a for k in ('Mỹ Đình', 'Long Biên', 'TP. Hồ Chí Minh', 'Đà Nẵng'))]
rest = [a for a in arts if a not in first]
if len(first) != 4:
    fails.append('showroom first=%d' % len(first))
new = ('<div class="showrooms">' + ''.join(first) + '</div>\n    <details class="moretoggle"><summary>Xem thêm %d showroom</summary>'
       '<div class="showrooms">' % len(rest) + ''.join(rest) + '</div></details>')
s = s[:i] + new + s[j + len('</div>'):]

# 10. Thanh dưới cùng trên điện thoại: bỏ biểu tượng giỏ hàng (nút "Mua ngay" đã đủ)
regex(r'\s*<a class="sb-cart".*?</a>', '', 'sb-cart')

open(D + 'index.html', 'w', encoding='utf-8').write(s)

css = open(D + 'css/style.css', encoding='utf-8').read()
if 'moretoggle' not in css:
    css += '''
/* Nút gập "Xem thêm" cho thông số và showroom */
.moretoggle>summary{cursor:pointer;list-style:none;display:block;width:max-content;max-width:100%;margin:1.2rem auto;padding:.75rem 1.6rem;font-weight:700;font-family:var(--display);color:var(--green);border:1.5px solid var(--green);border-radius:999px;transition:.2s}
.moretoggle>summary::-webkit-details-marker{display:none}
.moretoggle>summary:hover{background:var(--green);color:#fff}
.moretoggle[open]>summary{margin-bottom:1.6rem}
'''
    open(D + 'css/style.css', 'w', encoding='utf-8').write(css)
print('FAILS:', fails)

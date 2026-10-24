/* Trang soạn bài kiểu WordPress: soạn thảo trực quan <-> Markdown, kiểm tra SEO, hộp đăng bài, chuyên mục, thẻ, ảnh đại diện. */
(function () {
  var C = window.VFW || {};
  var $ = function (id) { return document.getElementById(id); };
  var suffix = ' | VF Wild ChungAuto';
  var form = $('post-form'), ta = $('f-body'), wys = $('wys'), rte = $('rte');
  var mode = 'visual', edited = false, dirty = false, submitting = false, saved = null, selImg = null;
  function len(s) { return Array.from(s).length; }
  function words(s) { return (s.match(/[\p{L}\p{N}]+/gu) || []).length; }
  function slugify(s) {
    s = s.toLowerCase().replace(/đ/g, 'd').normalize('NFD').replace(/[̀-ͯ]/g, '');
    return s.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80).replace(/-+$/, '');
  }

  /* ===== HTML của trình soạn thảo  ->  Markdown (định dạng lưu bài) ===== */
  function okUrl(u) { return /^(\/(?!\/)|https?:\/\/|mailto:|tel:|#)/i.test(u) && !/[\s"<>]/.test(u); }
  function sp(s) { return s.replace(/ /g, ' ').replace(/\s+/g, ' '); }
  function clean(s) { return sp(s).trim(); }
  function encPar(u) { return u.replace(/\(/g, '%28').replace(/\)/g, '%29'); }
  function imgMd(im) {
    var src = encPar(im.getAttribute('src') || '');
    if (!okUrl(src) || /^(mailto|tel):/i.test(src)) return '';
    var ti = (im.getAttribute('title') || '').replace(/["\[\]\n]/g, ' ').replace(/\s+/g, ' ').trim();
    return '![' + (im.getAttribute('alt') || '').replace(/[\[\]\n]/g, ' ').trim() + '](' + src + (ti ? ' "' + ti + '"' : '') + ')';
  }
  function wrap(inner, m) {
    var t = inner.trim(); if (!t || inner.indexOf(m) !== -1) return inner;
    return (/^\s/.test(inner) ? ' ' : '') + m + t + m + (/\s$/.test(inner) ? ' ' : '');
  }
  var BLOCK = /^(h[1-6]|p|div|ul|ol|table|figure|blockquote|hr|section|article|pre|thead|tbody|tr)$/;
  function inlOne(c) {
    if (c.nodeType === 3) return sp(c.nodeValue);
    if (c.nodeType !== 1) return '';
    var t = c.tagName.toLowerCase();
    if (t === 'br') return ' ';
    if (t === 'img') return imgMd(c);
    if (BLOCK.test(t)) return ' ' + inl(c) + ' ';
    var inner = inl(c);
    if (t === 'strong' || t === 'b') return wrap(inner, '**');
    if (t === 'em' || t === 'i') return wrap(inner, '*');
    if (t === 's' || t === 'strike' || t === 'del') return wrap(inner, '~~');
    if (t === 'a') {
      var h = c.getAttribute('href') || '', tx = inner.trim();
      return (okUrl(h) && tx) ? (/^\s/.test(inner) ? ' ' : '') + '[' + tx.replace(/[\[\]]/g, '') + '](' + encPar(h) + ')' + (/\s$/.test(inner) ? ' ' : '') : inner;
    }
    return inner;
  }
  function inl(node) { var s = ''; node.childNodes.forEach(function (c) { s += inlOne(c); }); return s; }
  function esc(t) { return /^(#{1,6}\s|>|\||[-*]\s|-{3,}$|\d+[.)]\s)/.test(t) ? '\\' + t : t; }
  function tableMd(tb) {
    var rows = [];
    tb.querySelectorAll('tr').forEach(function (tr) {
      var cells = [];
      Array.prototype.forEach.call(tr.children, function (td) { if (/^(td|th)$/i.test(td.tagName)) cells.push(clean(inl(td)).replace(/\|/g, '/')); });
      if (cells.length) rows.push(cells);
    });
    if (!rows.length) return '';
    var n = Math.max.apply(null, rows.map(function (r) { return r.length; }));
    rows.forEach(function (r) { while (r.length < n) r.push(''); });
    var line = function (r) { return '| ' + r.join(' | ') + ' |'; };
    return [line(rows[0]), '|' + new Array(n + 1).join('---|')].concat(rows.slice(1).map(line)).join('\n');
  }
  function toMd(root) {
    var out = [], buf = '';
    function flush() { var t = clean(buf); if (t) out.push(esc(t)); buf = ''; }
    (function walk(node) {
      node.childNodes.forEach(function (c) {
        if (c.nodeType === 3) { buf += sp(c.nodeValue); return; }
        if (c.nodeType !== 1) return;
        var t = c.tagName.toLowerCase();
        if (!BLOCK.test(t)) { buf += inlOne(c); return; }
        flush();
        if (t === 'h1' || t === 'h2') { var a = clean(inl(c)); if (a) out.push('## ' + a); }
        else if (t === 'h3') { var b = clean(inl(c)); if (b) out.push('### ' + b); }
        else if (/^h[4-6]$/.test(t)) { var b4 = clean(inl(c)); if (b4) out.push('#### ' + b4); }
        else if (t === 'p') {
          if (c.querySelector('figure,table,ul,ol')) walk(c);
          else { var d = clean(inl(c)); if (d) out.push(esc(d)); }
        }
        else if (t === 'blockquote') { var q = clean(inl(c)); if (q) out.push('> ' + q); }
        else if (t === 'ul' || t === 'ol') {
          var lines = [], ord = t === 'ol';
          (function items(list) {
            Array.prototype.forEach.call(list.children, function (li) {
              if (!/^li$/i.test(li.tagName)) return;
              var own = '';
              li.childNodes.forEach(function (x) { if (!(x.nodeType === 1 && /^(ul|ol)$/i.test(x.tagName))) own += inlOne(x); });
              var tx = clean(own); if (tx) lines.push((ord ? (lines.length + 1) + '. ' : '- ') + tx);
              li.querySelectorAll(':scope > ul, :scope > ol').forEach(items);
            });
          })(c);
          if (lines.length) out.push(lines.join('\n'));
        }
        else if (t === 'table') { var tm = tableMd(c); if (tm) out.push(tm); }
        else if (t === 'figure') { var im = c.querySelector('img'); if (im) { var m = imgMd(im); if (m) out.push(m); } }
        else if (t === 'hr') out.push('---');
        else walk(c);
      });
    })(root);
    flush();
    return out.join('\n\n');
  }
  function syncMd() { if (mode === 'visual' && edited) ta.value = toMd(wys); }

  /* ===== Hộp Đăng, nhãn nút ===== */
  var initial = C.status || 'draft', isNew = !C.orig;
  function today() { var d = new Date(); return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
  function labels() {
    var future = $('f-date').value && $('f-date').value > today();
    $('save-btn').textContent = future ? 'Lên lịch' : (isNew || initial === 'draft' ? 'Đăng' : 'Cập nhật');
    $('draft-btn').textContent = initial === 'published' && !isNew ? 'Chuyển về nháp' : 'Lưu nháp';
    $('save-fab').textContent = $('save-btn').textContent;
  }
  $('save-btn').addEventListener('click', function () { $('f-status').value = 'published'; });
  $('save-fab').addEventListener('click', function () { $('f-status').value = 'published'; });
  $('draft-btn').addEventListener('click', function () { $('f-status').value = 'draft'; });

  /* ===== Kiểm tra SEO, xem trước Google ===== */
  function slugNow() { return slugify($('f-slug').value.trim() || $('f-title').value); }
  function check() {
    syncMd();
    var title = $('f-seo').value.trim() || $('f-title').value.trim();
    var eff = len(title) + suffix.length <= 60 ? title + suffix : title;
    var desc = $('f-desc').value.trim(), d = len(desc), body = ta.value, w = words(body);
    var h2 = (body.match(/^##\s/gm) || []).length, internal = (body.match(/(?<!!)\[[^\]]*\]\(\/(?!\/)/g) || []).length;
    var slug = slugNow();
    $('c-seo').textContent = len(eff) + ' ký tự' + (len(eff) > 60 ? ' – quá dài, Google sẽ cắt bớt' : ' – tốt');
    $('c-desc').textContent = d + ' ký tự' + (d < 70 ? ' – hơi ngắn' : d > 160 ? ' – quá dài' : ' – tốt');
    $('slug-text').textContent = slug || '…';
    $('serp-url').textContent = 'vfwild.vn › blog › ' + (slug || '…');
    $('serp-title').textContent = len(eff) > 60 ? Array.from(eff).slice(0, 58).join('') + '…' : (eff || 'Tiêu đề bài viết');
    $('serp-desc').textContent = d > 160 ? Array.from(desc).slice(0, 158).join('') + '…' : (desc || 'Mô tả ngắn sẽ hiện ở đây.');
    $('wc').textContent = 'Số từ: ' + w;
    var noAlt = 0; wys.querySelectorAll('figure img').forEach(function (im) { if (!(im.getAttribute('alt') || '').trim()) noAlt++; });
    var items = [
      [len(eff) <= 60 && len(eff) >= 20, 'Tiêu đề Google ≤ 60 ký tự (' + len(eff) + ')'],
      [d >= 70 && d <= 160, 'Mô tả 70–160 ký tự (' + d + ')'],
      [w >= 600, 'Từ 600 từ trở lên (' + w + ')'],
      [h2 >= 3, 'Có ít nhất 3 tiêu đề mục (' + h2 + ')'],
      [internal >= 2, 'Có ít nhất 2 liên kết nội bộ (' + internal + ')'],
      [$('f-cover').value !== '' && $('f-alt').value.trim() !== '', 'Có ảnh đại diện và mô tả ảnh'],
      [noAlt === 0, noAlt === 0 ? 'Mọi ảnh trong bài đều có mô tả (alt)' : noAlt + ' ảnh trong bài chưa có mô tả (alt)']
    ];
    var box = $('checks'); box.textContent = ''; var ok = 0;
    items.forEach(function (it) {
      if (it[0]) ok++;
      var r = document.createElement('div'); r.className = 'chk ' + (it[0] ? 'ok' : 'no');
      var i = document.createElement('i'); i.textContent = it[0] ? '✓' : '!';
      var s = document.createElement('span'); s.textContent = it[1];
      r.appendChild(i); r.appendChild(s); box.appendChild(r);
    });
    $('score').textContent = ok + '/' + items.length;
    labels();
  }
  function markDirty() { if (!dirty) { dirty = true; $('dirty').hidden = false; } }
  form.addEventListener('input', function (e) { if (e.target === wys || wys.contains(e.target)) edited = true; check(); if (e.target.type !== 'file') markDirty(); });
  form.addEventListener('change', function (e) { check(); if (e.target.type !== 'file') markDirty(); });
  form.addEventListener('submit', function (e) {
    syncMd();
    var pv = e.submitter && e.submitter.value === 'blog_preview_page';
    if (!pv) submitting = true;
  });
  window.addEventListener('beforeunload', function (e) { if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; } });
  document.addEventListener('submit', function (e) { if (e.target.matches && e.target.matches('form[data-confirm]') && !e.defaultPrevented) submitting = true; });
  form.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'submit') e.preventDefault(); });

  // đường dẫn (permalink) có thể sửa
  $('slug-edit').addEventListener('click', function () {
    var inp = $('f-slug'), edit = inp.hidden;
    if (edit) { inp.value = inp.value || slugNow(); inp.hidden = false; inp.focus(); this.textContent = 'Xong'; }
    else { inp.value = slugify(inp.value); inp.hidden = true; this.textContent = 'Sửa'; check(); }
  });

  // chuyên mục mới: gõ tên thì bỏ chọn các chuyên mục có sẵn
  var newCat = $('new-cat');
  newCat.addEventListener('input', function () { if (this.value.trim()) form.querySelectorAll('input[name=category]').forEach(function (r) { r.checked = false; }); });
  form.querySelectorAll('input[name=category]').forEach(function (r) { r.addEventListener('change', function () { newCat.value = ''; }); });
  // thẻ: bấm gợi ý để thêm
  document.querySelectorAll('[data-tag]').forEach(function (b) {
    b.addEventListener('click', function () {
      var inp = $('f-tags'), cur = inp.value.split(',').map(function (x) { return x.trim(); }).filter(Boolean), t = b.getAttribute('data-tag');
      if (cur.map(function (x) { return x.toLowerCase(); }).indexOf(t.toLowerCase()) === -1) cur.push(t);
      inp.value = cur.join(', '); inp.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });

  // ảnh đại diện
  function setCover(url, alt) {
    $('f-cover').value = url || '';
    var im = $('cover-prev'); if (url) { im.src = url; im.hidden = false; } else { im.removeAttribute('src'); im.hidden = true; }
    if (alt !== undefined && alt !== null && alt !== '') $('f-alt').value = alt;
    $('cover-set').textContent = url ? 'Đổi ảnh đại diện' : 'Đặt ảnh đại diện';
    $('cover-rm').hidden = !url; $('cover-alt-wrap').hidden = !url; $('cover-title-wrap').hidden = !url;
    form.dispatchEvent(new Event('change', { bubbles: true }));
  }
  $('cover-set').addEventListener('click', function () {
    VFWM.openMedia({ title: 'Chọn ảnh đại diện', confirm: 'Đặt ảnh đại diện', alt: $('f-alt').value }).then(function (r) { if (r) setCover(r.item.cover, r.alt || $('f-alt').value || r.item.label); });
  });
  $('cover-rm').addEventListener('click', function () { setCover('', ''); $('f-alt').value = ''; $('f-ctitle').value = ''; });

  // link video: báo ngay link có nhận dạng được không (khớp quy tắc của máy chủ)
  var vin = $('f-video'), vnote = $('video-note');
  if (vin && vnote) {
    var vdef = vnote.textContent;
    var videoKind = function (u) {
      u = u.trim(); if (!u) return '';
      if (/^\/(?!\/)[\w\/.-]+\.(mp4|webm)$/i.test(u) && u.indexOf('..') === -1) return 'Tệp video';
      if (!/^https:\/\//i.test(u)) { if (/^(www\.|m\.)?(youtube\.com|youtu\.be|vimeo\.com)\//i.test(u)) u = 'https://' + u; else return null; }
      var a; try { a = new URL(u); } catch (e) { return null; }
      var h = a.hostname.toLowerCase().replace(/^(www|m)\./, ''), p = a.pathname, m;
      if (h === 'youtu.be') return /^\/[\w-]{11}$/.test(p) ? 'YouTube' : null;
      if (h === 'youtube.com' || h === 'youtube-nocookie.com') {
        var v = a.searchParams.get('v'); if (v) return /^[\w-]{11}$/.test(v) ? 'YouTube' : null;
        m = p.match(/^\/(?:embed|shorts|live|v)\/([^\/?]+)/); return m && /^[\w-]{11}$/.test(m[1]) ? 'YouTube' : null;
      }
      if (h === 'vimeo.com' || h === 'player.vimeo.com') return /\/(?:video\/)?\d{6,12}(?:\/[0-9a-f]{8,})?\/?$/.test(p) ? 'Vimeo' : null;
      return /\.(mp4|webm)$/i.test(p) ? 'Tệp video' : null;
    };
    var videoUpdate = function () {
      var k = videoKind(vin.value);
      vnote.classList.remove('ok', 'no');
      if (k === '') vnote.textContent = vdef;
      else if (k === null) { vnote.textContent = 'Chưa nhận dạng được. Dùng link YouTube, Vimeo hoặc tệp .mp4/.webm (https).'; vnote.classList.add('no'); }
      else { vnote.textContent = '✓ Nhận dạng: ' + k + '. Video sẽ hiện ngay sau đoạn mở đầu của bài.'; vnote.classList.add('ok'); }
    };
    vin.addEventListener('input', videoUpdate); videoUpdate();
  }

  /* ===== Trình soạn thảo ===== */
  try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}
  function fixFigures() {
    wys.querySelectorAll('figure').forEach(function (f) { f.setAttribute('contenteditable', 'false'); f.setAttribute('tabindex', '0'); f.setAttribute('role', 'group'); f.setAttribute('aria-label', 'Ảnh: ' + ((f.querySelector('img') || {}).alt || 'chưa có mô tả') + '. Nhấn Enter để sửa mô tả, Delete để xóa.'); });
    if (!wys.firstChild) wys.innerHTML = '<p><br></p>';
    togglePlaceholder();
  }
  function togglePlaceholder() {
    var empty = wys.textContent.trim() === '' && !wys.querySelector('img,table');
    wys.classList.toggle('is-empty', empty);
  }
  function emit() { edited = true; wys.dispatchEvent(new Event('input', { bubbles: true })); }
  document.addEventListener('selectionchange', function () {
    var s = window.getSelection();
    if (s.rangeCount && wys.contains(s.anchorNode)) { saved = s.getRangeAt(0).cloneRange(); updateState(); }
  });
  wys.addEventListener('input', togglePlaceholder);
  function restore() {
    wys.focus();
    if (saved) { var s = window.getSelection(); s.removeAllRanges(); s.addRange(saved); }
  }
  function exec(cmd, val) { restore(); document.execCommand(cmd, false, val); emit(); updateState(); }
  function cellOf() {
    var n = saved ? saved.startContainer : null;
    while (n && n !== wys) { if (n.nodeType === 1 && /^(td|th)$/i.test(n.tagName)) return n; n = n.parentNode; }
    return null;
  }
  function updateState() {
    ['bold', 'italic', 'strikeThrough', 'insertUnorderedList', 'insertOrderedList'].forEach(function (c) {
      var b = rte.querySelector('[data-cmd="' + c + '"]'); if (b) { var on = false; try { on = document.queryCommandState(c); } catch (e) {} b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); }
    });
    var v = ''; try { v = (document.queryCommandValue('formatBlock') || '').toLowerCase().replace(/[<>]/g, ''); } catch (e) {}
    $('rte-block').value = /^(h2|h3|blockquote)$/.test(v) ? v : (v === 'h1' ? 'h2' : 'p');
    $('tbl-tools').hidden = !cellOf();
  }
  $('rte-block').addEventListener('change', function () { exec('formatBlock', '<' + this.value + '>'); });
  $('rte-bar').addEventListener('mousedown', function (e) { if (e.target.closest('button')) e.preventDefault(); });
  rte.addEventListener('mousedown', function (e) { if (e.target.closest('.rte__ctx button')) e.preventDefault(); });

  // hộp thoại nhập liệu
  var dlg = $('dlg');
  function ask(title, label, value, label2, value2) {
    return new Promise(function (resolve) {
      var two = label2 !== undefined;
      $('dlg-t').textContent = title; $('dlg-l').textContent = label; $('dlg-i').value = value || '';
      $('dlg-w2').hidden = !two; if (two) { $('dlg-l2').textContent = label2; $('dlg-i2').value = value2 || ''; }
      var done = false;
      function fin(v) { if (done) return; done = true; dlg.close(); resolve(v); }
      dlg.onclose = function () { if (!done) resolve(null); };
      $('dlg-c').onclick = function () { fin(null); };
      dlg.querySelector('form').onsubmit = function (e) { e.preventDefault(); fin(two ? [$('dlg-i').value, $('dlg-i2').value] : $('dlg-i').value); };
      dlg.showModal(); $('dlg-i').focus(); $('dlg-i').select();
    });
  }
  function normUrl(u) {
    u = u.trim(); if (!u) return '';
    if (/^(\/(?!\/)|https?:\/\/|mailto:|tel:|#)/i.test(u)) return u;
    return /^[\w.-]+\.[a-z]{2,}/i.test(u) ? 'https://' + u : '';
  }
  function link() {
    restore();
    var sel = window.getSelection(), a = null, n = sel.anchorNode;
    while (n && n !== wys) { if (n.nodeType === 1 && n.tagName === 'A') { a = n; break; } n = n.parentNode; }
    var collapsed = sel.isCollapsed, range = saved;
    ask('Chèn liên kết', 'Địa chỉ (ví dụ /blog/ cho trang trong web, hoặc https://…)', a ? a.getAttribute('href') : '').then(function (v) {
      if (v === null) return;
      var url = normUrl(v);
      saved = range; restore();
      if (v.trim() === '') { document.execCommand('unlink'); emit(); return; }
      if (!url) { window.alert('Địa chỉ chưa hợp lệ. Hãy nhập /đường-dẫn hoặc https://…'); return; }
      if (collapsed && !a) document.execCommand('insertHTML', false, '<a href="' + url.replace(/"/g, '') + '">' + url.replace(/[<>&]/g, '') + '</a>');
      else document.execCommand('createLink', false, url);
      emit();
    });
  }
  // chèn khối (ảnh, bảng) ngay sau đoạn đang đặt con trỏ
  function topBlock() {
    var n = saved ? saved.startContainer : null;
    while (n && n.parentNode !== wys) n = n.parentNode;
    return n && n.parentNode === wys ? n : null;
  }
  function insertBlockNode(node) {
    var b = topBlock(), para = document.createElement('p'); para.innerHTML = '<br>';
    if (!b) { wys.appendChild(node); wys.appendChild(para); }
    else if (b.nodeName === 'P' && b.textContent.trim() === '' && !b.querySelector('img')) { wys.replaceChild(node, b); node.after(para); }
    else { b.after(node); node.after(para); }
    var r = document.createRange(); r.setStart(para, 0); r.collapse(true);
    var s = window.getSelection(); s.removeAllRanges(); s.addRange(r); saved = r.cloneRange();
    emit();
  }
  // đánh dấu vị trí video: một đoạn chỉ chứa chữ [video]; hiển thị thành khung nét đứt để dễ thấy
  function markVideo() { wys.querySelectorAll('p').forEach(function (p) { p.classList.toggle('vmark', p.textContent.trim() === '[video]'); }); }
  function insertVideoMark() {
    if (!$('f-video').value.trim()) { window.alert('Hãy điền Link video ở cột bên phải trước, rồi bấm lại nút này.'); $('f-video').focus(); return; }
    wys.querySelectorAll('p.vmark').forEach(function (p) { p.remove(); });   // mỗi bài chỉ một video
    restore();
    var p = document.createElement('p'); p.className = 'vmark'; p.textContent = '[video]';
    insertBlockNode(p); markVideo();
  }
  wys.addEventListener('input', markVideo);
  function figureNode(url, alt, title) {
    var f = document.createElement('figure'); f.className = 'post__fig'; f.setAttribute('contenteditable', 'false'); f.setAttribute('tabindex', '0');
    var im = document.createElement('img'); im.className = 'post__img'; im.src = url; im.alt = alt || ''; if (title) im.title = title;
    f.appendChild(im);
    var cap = title || alt;
    if (cap) { var cp = document.createElement('figcaption'); cp.textContent = cap; f.appendChild(cp); }
    return f;
  }
  function tableNode() {
    var w = document.createElement('div'); w.className = 'tablewrap';
    var h = '<table><thead><tr><th>Tiêu đề 1</th><th>Tiêu đề 2</th><th>Tiêu đề 3</th></tr></thead><tbody>';
    for (var i = 0; i < 2; i++) h += '<tr><td><br></td><td><br></td><td><br></td></tr>';
    w.innerHTML = h + '</tbody></table>'; return w;
  }
  function tableOps(act) {
    var cell = cellOf(); if (!cell) return;
    var row = cell.parentNode, tbl = row.closest('table'), idx = Array.prototype.indexOf.call(row.children, cell);
    if (act === 'row+') {
      var nr = document.createElement('tr'); for (var i = 0; i < row.children.length; i++) nr.appendChild(Object.assign(document.createElement('td'), { innerHTML: '<br>' }));
      var inHead = row.parentNode.tagName === 'THEAD', host = inHead ? tbl.tBodies[0] : row.parentNode;
      host.insertBefore(nr, inHead ? host.firstChild : row.nextSibling);
    } else if (act === 'row-') { if (row.parentNode.tagName !== 'THEAD' && tbl.rows.length > 2) row.remove(); }
    else if (act === 'col+') { tbl.querySelectorAll('tr').forEach(function (r) { var c = document.createElement(r.parentNode.tagName === 'THEAD' ? 'th' : 'td'); c.innerHTML = r.parentNode.tagName === 'THEAD' ? 'Tiêu đề' : '<br>'; r.insertBefore(c, r.children[idx + 1] || null); }); }
    else if (act === 'col-') { if (row.children.length > 1) tbl.querySelectorAll('tr').forEach(function (r) { if (r.children[idx]) r.children[idx].remove(); }); }
    else if (act === 'tbl-del') { var wrapEl = tbl.parentNode.classList.contains('tablewrap') ? tbl.parentNode : tbl; wrapEl.remove(); fixFigures(); }
    emit(); updateState();
  }

  // làm sạch HTML khi dán từ Word / Google Docs / web
  function cleanHtml(n) {
    if (n.nodeType === 3) return n.nodeValue.replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; });
    if (n.nodeType !== 1) return '';
    var t = n.tagName.toLowerCase(), st = n.getAttribute('style') || '';   // đọc thẳng thuộc tính (CSSOM có thể bị CSP chặn)
    if (/^(script|style|meta|link|head|title|iframe|object|svg|noscript)$/.test(t)) return '';
    var inner = Array.prototype.map.call(n.childNodes, cleanHtml).join('');
    var bold = (t === 'b' && !/font-weight\s*:\s*(normal|400)/i.test(st)) || t === 'strong' || (t === 'span' && /font-weight\s*:\s*(bold|[6-9]00)/i.test(st));
    var ital = t === 'i' || t === 'em' || (t === 'span' && /font-style\s*:\s*italic/i.test(st));
    var strike = t === 's' || t === 'strike' || t === 'del' || (t === 'span' && /line-through/i.test(st));
    if (strike) inner = '<s>' + inner + '</s>';
    if (bold && ital) return '<strong><em>' + inner + '</em></strong>';
    if (bold) return '<strong>' + inner + '</strong>';
    if (ital) return '<em>' + inner + '</em>';
    if (strike && t !== 'span') return inner;
    switch (t) {
      case 'h1': case 'h2': return '<h2>' + inner + '</h2>';
      case 'h3': case 'h4': case 'h5': case 'h6': return '<h3>' + inner + '</h3>';
      case 'p': return '<p>' + inner + '</p>';
      case 'div': return /<(p|h2|h3|ul|ol|table|blockquote)/.test(inner) ? inner : '<p>' + inner + '</p>';
      case 'ul': case 'ol': case 'li': case 'blockquote': case 'table': case 'thead': case 'tbody': case 'tr': case 'th': case 'td': return '<' + t + '>' + inner + '</' + t + '>';
      case 'br': return '<br>';
      case 'a': var h = n.getAttribute('href') || ''; return okUrl(h) ? '<a href="' + h.replace(/"/g, '&quot;') + '">' + inner + '</a>' : inner;
      case 'img': var s = n.getAttribute('src') || ''; return (/^(\/|https:\/\/)/.test(s) && !/^\/\//.test(s)) ? '<img src="' + s.replace(/"/g, '&quot;') + '" alt="' + (n.getAttribute('alt') || '').replace(/"/g, '&quot;') + '"' + (n.getAttribute('title') ? ' title="' + n.getAttribute('title').replace(/"/g, '&quot;') + '"' : '') + '>' : '';
      default: return inner;
    }
  }
  wys.addEventListener('paste', function (e) {
    var cd = e.clipboardData; if (!cd) return;
    var html = cd.getData('text/html'), plain = cd.getData('text/plain');
    if (!html && !plain && cd.files && cd.files.length && /^image\//.test(cd.files[0].type)) { e.preventDefault(); addImageFile(cd.files[0]); return; }
    e.preventDefault();
    if (html) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      document.execCommand('insertHTML', false, cleanHtml(doc.body));
    } else {
      document.execCommand('insertText', false, cd.getData('text/plain'));
    }
    emit();
  });
  wys.addEventListener('drop', function (e) {
    var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
    if (f && /^image\//.test(f.type)) { e.preventDefault(); addImageFile(f); }
  });
  wys.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); link(); }
  });

  // chọn ảnh trong bài để sửa mô tả / xóa
  wys.addEventListener('click', function (e) {
    var im = e.target.closest ? e.target.closest('img') : null;
    if (selImg) selImg.classList.remove('sel');
    selImg = im && wys.contains(im) ? im : null;
    if (selImg) selImg.classList.add('sel');
    $('img-tools').hidden = !selImg;
  });
  wys.addEventListener('focusin', function (e) {
    var fig = e.target.closest ? e.target.closest('figure') : null;
    if (!fig || !wys.contains(fig)) return;
    if (selImg) selImg.classList.remove('sel');
    selImg = fig.querySelector('img'); if (selImg) selImg.classList.add('sel'); $('img-tools').hidden = !selImg;
  });
  wys.addEventListener('keydown', function (e) {
    var fig = e.target.closest ? e.target.closest('figure') : null;
    if (!fig || !wys.contains(fig)) return;
    if (e.key === 'Enter') { e.preventDefault(); imageAlt(); }
    else if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); imageDel(); }
  });
  function imageAlt() {
    if (!selImg) return;
    ask('Ảnh trong bài', 'Mô tả ảnh (alt) – mô tả ngắn nội dung ảnh, giúp SEO và người khiếm thị', selImg.getAttribute('alt') || '',
        'Tiêu đề ảnh (title) – hiện khi rê chuột và làm chú thích dưới ảnh; để trống thì chú thích lấy theo mô tả', selImg.getAttribute('title') || '').then(function (v) {
      if (v === null || !selImg) return;
      var alt = v[0].trim(), ti = v[1].replace(/"/g, ' ').trim();
      selImg.setAttribute('alt', alt);
      if (ti) selImg.setAttribute('title', ti); else selImg.removeAttribute('title');
      var cap = ti || alt;
      var fig = selImg.closest('figure');
      if (fig) { var cp = fig.querySelector('figcaption'); if (!cp && cap) { cp = document.createElement('figcaption'); fig.appendChild(cp); } if (cp) { cp.textContent = cap; if (!cap) cp.remove(); } }
      emit();
    });
  }
  function imageDel() {
    if (!selImg) return; var fig = selImg.closest('figure') || selImg; fig.remove(); selImg = null; $('img-tools').hidden = true; fixFigures(); emit();
  }
  // thêm ảnh: từ thư viện, hoặc dán / kéo thả tệp ảnh
  function addFromLibrary() {
    var range = saved;
    VFWM.openMedia({ title: 'Thêm ảnh vào bài', confirm: 'Chèn vào bài' }).then(function (r) {
      if (!r) return;
      saved = range; insertBlockNode(figureNode(r.item.insert, r.alt, r.title));
    });
  }
  function addImageFile(file) {
    var range = saved;
    VFWM.uploadFile(file).then(function (a) {
      return ask('Ảnh trong bài', 'Mô tả ảnh (alt) – mô tả ngắn nội dung ảnh, giúp SEO và người khiếm thị', '', 'Tiêu đề ảnh (title) – hiện khi rê chuột và làm chú thích dưới ảnh (có thể để trống)', '').then(function (v) {
        var alt = v ? v[0] : '', ti = v ? v[1] : '';
        saved = range; insertBlockNode(figureNode(a.insert, (alt || '').trim(), (ti || '').replace(/"/g, ' ').trim()));
      });
    }).catch(function (e) { window.alert('Lỗi: ' + e.message); });
  }

  // thanh công cụ
  rte.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b || !rte.contains(b)) return;
    var cmd = b.getAttribute('data-cmd'), act = b.getAttribute('data-act');
    if (cmd) { exec(cmd); return; }
    if (act === 'link') link();
    else if (act === 'unlink') exec('unlink');
    else if (act === 'image') addFromLibrary();
    else if (act === 'table') { restore(); insertBlockNode(tableNode()); }
    else if (act === 'hr') { restore(); insertBlockNode(document.createElement('hr')); }
    else if (act === 'video') insertVideoMark();
    else if (act === 'clear') { exec('removeFormat'); exec('formatBlock', '<p>'); }
    else if (act === 'img-alt') imageAlt();
    else if (act === 'img-del') imageDel();
    else if (act) tableOps(act);
  });
  $('add-media').addEventListener('click', function () { if (mode === 'visual') addFromLibrary(); else mdInsertImage(); });

  // chế độ Văn bản (Markdown)
  function mdInsertImage() {
    var pos = ta.selectionStart;
    VFWM.openMedia({ title: 'Thêm ảnh vào bài', confirm: 'Chèn vào bài' }).then(function (r) {
      if (!r) return;
      var ti = (r.title || '').replace(/["\[\]]/g, ' ').trim(); var add = '\n\n![' + r.alt.replace(/[\[\]]/g, '') + '](' + r.item.insert + (ti ? ' "' + ti + '"' : '') + ')\n\n';
      ta.value = ta.value.slice(0, pos) + add + ta.value.slice(pos); form.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }
  document.querySelectorAll('[data-mode]').forEach(function (b) {
    b.addEventListener('click', function () {
      var to = b.getAttribute('data-mode'); if (to === mode) return;
      function mark() { document.querySelectorAll('[data-mode]').forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-selected', x === b ? 'true' : 'false'); }); }
      if (to === 'md') { syncMd(); mode = 'md'; rte.hidden = true; ta.hidden = false; mark(); }
      else {
        var fd = new FormData(); fd.append('action', 'blog_render'); fd.append('csrf', C.csrf); fd.append('body', ta.value);
        fetch('/quan-tri/', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
          wys.innerHTML = j.html || '<p><br></p>'; fixFigures(); mode = 'visual'; edited = false; ta.hidden = true; rte.hidden = false; mark();
        }).catch(function () { window.alert('Không chuyển được. Hãy thử lại.'); });
      }
    });
  });

  // giữ phiên đăng nhập khi soạn bài lâu (tránh mất bài vì hết phiên): gọi nhẹ mỗi 10 phút
  setInterval(function () { fetch('/quan-tri/?p=account&hb=1', { credentials: 'same-origin', cache: 'no-store' }).catch(function () {}); }, 10 * 60 * 1000);

  fixFigures();
  setCover($('f-cover').value, null);
  markVideo();
  dirty = false; $('dirty').hidden = true;
  check();
})();

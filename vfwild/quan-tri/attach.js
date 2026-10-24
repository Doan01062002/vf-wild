/* "Gắn ảnh vào bài" (trang Sửa bài): 1. chọn ảnh từ máy hoặc link Drive → 2. hệ thống đề xuất mục, alt, chú thích (tên tệp ưu tiên, AI xem ảnh) để duyệt → 3. chèn vào bài.
   Làm trên nội dung đang soạn (VFWE từ editor.js); bài chỉ được lưu khi bấm Lưu nháp / Đăng. Cần media.js (VFWM.uploadFile nén ảnh 3 cỡ ngay trong trình duyệt). */
(function () {
  var C = window.VFW || {};
  var $ = function (id) { return document.getElementById(id); };
  var dlg = $('att'); if (!dlg || !window.VFWE || !window.VFWM) return;
  var imgs = [], heads = {}, busy = 0;

  function post(data) {
    var fd = new FormData(); fd.append('csrf', C.csrf);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch('/quan-tri/', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (j.error) throw new Error(j.error); return j; });
  }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }
  function msg(id, t, bad) { var m = $(id); m.textContent = t; m.classList.toggle('danger', !!bad); }
  function ready() { return imgs.filter(function (x) { return x.id; }); }
  function sync() { $('att-next').disabled = busy > 0 || !ready().length; }

  /* ---- bước 1: chọn ảnh ---- */
  function tile(name, thumb) {
    var it = { id: '', name: name, node: el('div', 'att__tile') };
    var im = el('img'); im.alt = ''; if (thumb) im.src = thumb;
    var cap = el('div', 'att__cap', name), st = el('div', 'att__st small muted', 'Đang tải…'), rm = el('button', 'att__rm', '✕');
    rm.type = 'button'; rm.title = 'Bỏ ảnh này'; rm.setAttribute('aria-label', 'Bỏ ảnh ' + name);
    rm.addEventListener('click', function () { imgs.splice(imgs.indexOf(it), 1); it.node.remove(); sync(); });
    it.node.append(im, cap, st, rm); it.img = im; it.st = st;
    imgs.push(it); $('att-grid').append(it.node); return it;
  }
  function done(it, r) { it.id = r.id; it.img.src = r.thumb || ('/img/blog/' + r.id + '-800.jpg'); it.st.textContent = 'Đã tải'; it.st.classList.remove('muted'); }
  function fail(it, e) { it.st.textContent = 'Lỗi: ' + e.message; it.st.classList.add('danger'); }
  function addFiles(list) {
    var files = Array.prototype.filter.call(list, function (f) { return /^image\/(jpeg|png|webp)$/.test(f.type); });
    if (!files.length) { msg('att-msg1', 'Chỉ nhận ảnh JPG, PNG hoặc WebP.', true); return; }
    var chain = Promise.resolve();
    files.forEach(function (f) {
      var it = tile(f.name, URL.createObjectURL(f)); busy++; sync();
      chain = chain.then(function () { return VFWM.uploadFile(f).then(function (r) { done(it, { id: r.id, thumb: r.cover }); }).catch(function (e) { fail(it, e); }).then(function () { busy--; sync(); }); });
    });
    msg('att-msg1', 'Đang nén và tải ' + files.length + ' ảnh…');
    chain.then(function () { msg('att-msg1', 'Đã có ' + ready().length + ' ảnh. Bấm “Phân tích” để xem đề xuất chỗ đặt.'); });
  }
  $('att-file').addEventListener('change', function () { addFiles(this.files); this.value = ''; });
  var drop = $('att-drop');
  ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('over'); }); });
  drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) addFiles(e.dataTransfer.files); });
  $('att-drive').addEventListener('click', function () {
    var link = $('att-link').value.trim(); if (!link) { msg('att-msg1', 'Hãy dán link thư mục hoặc ảnh Google Drive.', true); return; }
    var btn = this; btn.disabled = true; busy++; sync(); msg('att-msg1', 'Đang đọc danh sách ảnh trên Drive…');
    post({ action: 'blog_attach_drive_list', link: link }).then(function (j) {
      if (!j.files.length) throw new Error((j.errors || []).join(' ') || 'Không thấy ảnh nào (thư mục đã chia sẻ “Bất kỳ ai có đường liên kết” chưa?).');
      var chain = Promise.resolve(), n = 0;
      j.files.forEach(function (f) {
        var it = tile(f.name || 'Ảnh Drive', '');
        chain = chain.then(function () {
          msg('att-msg1', 'Đang tải ảnh ' + (++n) + '/' + j.files.length + ' từ Drive…');
          return post({ action: 'blog_attach_drive_fetch', link: f.link, name: f.name, title: VFWE.title() }).then(function (r) { done(it, r); }).catch(function (e) { fail(it, e); });
        });
      });
      return chain.then(function () { msg('att-msg1', 'Đã có ' + ready().length + ' ảnh. Bấm “Phân tích” để xem đề xuất chỗ đặt.'); });
    }).catch(function (e) { msg('att-msg1', 'Lỗi: ' + e.message, true); }).then(function () { btn.disabled = false; busy--; sync(); });
  });

  /* ---- bước 2: phân tích, duyệt ---- */
  function step(n) {
    dlg.querySelectorAll('[data-step]').forEach(function (li) { li.classList.toggle('on', +li.getAttribute('data-step') === n); });
    $('att-s1').hidden = n !== 1; $('att-s2').hidden = n !== 2;
    $('att-next').hidden = n !== 1; $('att-back').hidden = n !== 2; $('att-ok').hidden = n !== 2; $('att-vision').parentNode.hidden = n !== 1;
  }
  function row(p, it) {
    var tr = el('tr'), td1 = el('td'), td2 = el('td'), td3 = el('td');
    var im = el('img', 'att__th'); im.alt = ''; im.src = '/img/blog/' + p.id + '-800.jpg';
    td1.append(im, el('div', 'small', it.name), el('div', 'small muted', 'Theo: ' + p.by));
    var sel = el('select'); sel.setAttribute('aria-label', 'Đặt ảnh ' + it.name + ' vào');
    [['cover', 'Ảnh đại diện (bìa)']].concat(Object.keys(heads).map(function (k) { return [k, k + '. ' + heads[k]]; })).concat([['auto', 'Tự chọn mục còn trống'], ['skip', 'Không dùng ảnh này']]).forEach(function (o) {
      var op = el('option', '', o[1]); op.value = o[0]; if (String(p.target) === o[0]) op.selected = true; sel.append(op);
    });
    td2.append(sel);
    var alt = el('input'); alt.maxLength = 140; alt.value = p.alt || ''; alt.placeholder = 'Mô tả ngắn nội dung ảnh'; alt.setAttribute('aria-label', 'Mô tả ảnh (alt)');
    var ti = el('input'); ti.maxLength = 90; ti.value = p.title || ''; ti.placeholder = 'Chú thích dưới ảnh (có thể để trống)'; ti.setAttribute('aria-label', 'Chú thích ảnh');
    td3.append(alt, ti);
    tr.append(td1, td2, td3);
    tr._get = function () { return { id: p.id, target: sel.value, alt: alt.value.trim(), title: ti.value.trim() }; };
    $('att-rows').append(tr);
  }
  $('att-next').addEventListener('click', function () {
    var list = ready(); if (!list.length) return;
    var md = VFWE.getMd(), title = VFWE.title(), vision = $('att-vision').checked ? '1' : '0', props = {}, notes = [];
    if (!/^##\s/m.test(md)) { msg('att-msg1', 'Bài chưa có mục H2 nào để đặt ảnh.', true); return; }
    var btn = this; btn.disabled = true; busy++;
    var batches = []; for (var i = 0; i < list.length; i += 3) batches.push(list.slice(i, i + 3));
    var chain = Promise.resolve(), n = 0;
    batches.forEach(function (b) {
      chain = chain.then(function () {
        msg('att-msg1', 'Đang phân tích ảnh ' + (n + 1) + '–' + (n + b.length) + '/' + list.length + (vision === '1' ? ' (AI đang xem ảnh)…' : '…'));
        return post({ action: 'blog_attach_analyze', body: md, title: title, vision: vision, images: JSON.stringify(b.map(function (x) { return { id: x.id, name: x.name }; })) }).then(function (j) {
          heads = j.headings || heads; (j.items || []).forEach(function (p) { props[p.id] = p; }); notes = notes.concat(j.notes || []); n += b.length;
        });
      });
    });
    chain.then(function () {
      $('att-rows').innerHTML = '';
      list.forEach(function (it) { if (props[it.id]) row(props[it.id], it); });
      msg('att-msg2', (notes.length ? notes.join(' ') + ' ' : '') + 'Kiểm tra cột “Đặt vào” và mô tả, sửa nếu cần rồi bấm “Chèn vào bài”. Chữ trong bài không bị viết lại.');
      step(2);
    }).catch(function (e) { msg('att-msg1', 'Lỗi: ' + e.message, true); }).then(function () { btn.disabled = false; busy--; sync(); });
  });
  $('att-back').addEventListener('click', function () { step(1); });

  /* ---- bước 3: chèn ---- */
  $('att-ok').addEventListener('click', function () {
    var items = Array.prototype.map.call($('att-rows').children, function (tr) { return tr._get(); });
    var btn = this; btn.disabled = true; msg('att-msg2', 'Đang chèn ảnh…');
    post({ action: 'blog_attach_apply', body: VFWE.getMd(), title: VFWE.title(), items: JSON.stringify(items), replace: $('att-replace').checked ? '1' : '0', slug: C.orig || '' }).then(function (j) {
      return VFWE.setMd(j.md).then(function () {
        if (j.cover) VFWE.setCover(j.cover.cover, j.cover.alt, j.cover.title);
        close(true);
        note('Đã chèn ' + j.placed + ' ảnh vào bài' + (j.cover ? ' và đặt ảnh đại diện' : '') + '. ' + (j.warn && j.warn.length ? j.warn.join(' ') + ' ' : '') + 'Xem lại rồi bấm Lưu nháp hoặc Đăng.');
      });
    }).catch(function (e) { msg('att-msg2', 'Lỗi: ' + e.message, true); }).then(function () { btn.disabled = false; });
  });

  /* ---- mở / đóng ---- */
  function note(t, bad) { var n = $('att-note'); n.textContent = t; n.classList.toggle('danger', !!bad); }
  function reset() { imgs = []; heads = {}; $('att-grid').innerHTML = ''; $('att-rows').innerHTML = ''; $('att-link').value = ''; msg('att-msg1', ''); msg('att-msg2', ''); $('att-replace').checked = false; step(1); sync(); }
  function close(keep) { if (busy && !keep && !window.confirm('Ảnh đang được tải, vẫn đóng?')) return; dlg.close(); }
  $('att-open').addEventListener('click', function () { reset(); dlg.showModal(); });
  $('att-x').addEventListener('click', function () { close(false); });
  $('att-cancel').addEventListener('click', function () { close(false); });
  dlg.addEventListener('cancel', function (e) { if (busy) { e.preventDefault(); close(false); } });

  /* ---- đặt lại tên ảnh cũ theo nội dung ---- */
  $('att-rename').addEventListener('click', function () {
    var md = VFWE.getMd(), cover = VFWE.cover();
    if (!/\/img\/blog\/b[0-9a-z]{6,24}-(800|1200|1600)\.jpg/.test(md + ' ' + cover)) { note('Ảnh trong bài đều đã có tên theo nội dung.'); return; }
    if (!window.confirm('Đổi tên các ảnh mang mã ngẫu nhiên trong bài này thành tên theo nội dung (tiêu đề bài + mục chứa ảnh)? Ảnh cũ vẫn được giữ trong thư viện.')) return;
    var btn = this; btn.disabled = true; note('Đang đặt lại tên ảnh…');
    post({ action: 'blog_attach_rename', body: md, cover: cover, title: VFWE.title() }).then(function (j) {
      if (!j.renamed) { note(j.missing ? 'Không tìm thấy tệp của ' + j.missing + ' ảnh trên máy chủ nên chưa đổi tên được.' : 'Không có ảnh nào cần đổi tên.', !!j.missing); return; }
      return VFWE.setMd(j.md).then(function () { if (j.cover !== cover) VFWE.setCover(j.cover, null); note('Đã đặt lại tên ' + j.renamed + ' ảnh. Bấm Lưu nháp hoặc Đăng để giữ thay đổi.'); });
    }).catch(function (e) { note('Lỗi: ' + e.message, true); }).then(function () { btn.disabled = false; });
  });
})();

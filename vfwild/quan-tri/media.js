/* Thư viện ảnh dùng chung: nén ảnh ngay trong trình duyệt (máy chủ không có GD), tải lên, hộp chọn ảnh kiểu WordPress. */
(function () {
  var C = window.VFW || {};
  var $ = function (id) { return document.getElementById(id); };

  function shrinkOnce(file, maxW, q) {
    return new Promise(function (ok, bad) {
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () {
        var sc = Math.min(1, maxW / img.width, 2400 / img.height), c = document.createElement('canvas');
        c.width = Math.round(img.width * sc); c.height = Math.round(img.height * sc);
        var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(function (b) { b ? ok(b) : bad(new Error('Không nén được ảnh')); }, 'image/jpeg', q);
      };
      img.onerror = function () { URL.revokeObjectURL(url); bad(new Error('Không đọc được ảnh (chỉ nhận JPG, PNG, WebP)')); };
      img.src = url;
    });
  }
  // nén lại với chất lượng thấp dần cho tới khi ≤ 650 KB (máy chủ nhận tối đa 700 KB)
  function shrink(file, maxW, q) {
    var qs = [q, 0.7, 0.6, 0.5, 0.4];
    function step(i) {
      return shrinkOnce(file, maxW, qs[i]).then(function (b) { return (b.size <= 650 * 1024 || i >= qs.length - 1) ? b : step(i + 1); });
    }
    return step(0);
  }
  function send(blob, name) {
    var fd = new FormData(); fd.append('action', 'blog_upload'); fd.append('csrf', C.csrf); fd.append('name', name); fd.append('file', blob, name + '.jpg');
    return fetch('/quan-tri/', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j.ok) throw new Error(j.error || 'Tải ảnh lỗi'); return j; });
  }
  function token() { return 'b' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); }
  // mỗi ảnh được lưu 3 cỡ (800 / 1200 / 1600) để dùng làm ảnh đại diện hoặc ảnh trong bài
  function uploadFile(file) {
    var base = token(), sizes = [800, 1200, 1600], chain = Promise.resolve();
    sizes.forEach(function (s) { chain = chain.then(function () { return shrink(file, s, 0.82).then(function (b) { return send(b, base + '-' + s); }); }); });
    return chain.then(function () { return { id: base, insert: '/img/blog/' + base + '-1200.jpg', cover: '/img/blog/' + base + '-800.jpg' }; }).catch(function (e) {
      // gỡ các cỡ đã tải lên dở dang để thư viện không bị ảnh thiếu cỡ
      var fd = new FormData(); fd.append('action', 'blog_media_delete'); fd.append('csrf', C.csrf); fd.append('id', base);
      return fetch('/quan-tri/', { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {}).then(function () { throw e; });
    });
  }
  function listMedia() {
    var fd = new FormData(); fd.append('action', 'blog_media_list'); fd.append('csrf', C.csrf);
    return fetch('/quan-tri/', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { return j.items || []; });
  }
  function fmtBytes(n) { return n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.round(n / 1024) + ' KB'; }

  /* ----- hộp chọn ảnh ----- */
  var mm = $('mm'), state = { items: [], sel: null, resolve: null, opts: {} };
  function setTab(t) {
    if (!mm) return;
    mm.querySelectorAll('[data-mmtab]').forEach(function (b) { var on = b.getAttribute('data-mmtab') === t; b.classList.toggle('on', on); b.setAttribute('aria-selected', on ? 'true' : 'false'); });
    $('mm-up').hidden = t !== 'up'; $('mm-lib').hidden = t !== 'lib';
    $('mm-side').hidden = t !== 'lib'; mm.querySelector('.mm__body').classList.toggle('noside', t !== 'lib');
  }
  function renderGrid() {
    var g = $('mm-grid'); g.textContent = '';
    if (!state.items.length) { var e = document.createElement('p'); e.className = 'muted'; e.textContent = 'Chưa có ảnh. Sang tab “Tải ảnh lên” để thêm.'; g.appendChild(e); return; }
    state.items.forEach(function (it) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'mitem' + (state.sel === it ? ' sel' : ''); b.setAttribute('aria-pressed', state.sel === it ? 'true' : 'false');
      var im = document.createElement('img'); im.src = it.thumb; im.alt = ''; im.loading = 'lazy';
      var s = document.createElement('span'); s.textContent = it.label;
      b.appendChild(im); b.appendChild(s);
      b.addEventListener('click', function () { select(it); });
      g.appendChild(b);
    });
  }
  function select(it) {
    state.sel = it;
    renderGrid();
    $('mm-prev').src = it.thumb; $('mm-prev').hidden = false;
    $('mm-info').textContent = it.label + ' · ' + it.w + '×' + it.h + ' px · ' + fmtBytes(it.bytes) + (it.used ? ' · đang dùng trong ' + it.used + ' bài' : '');
    $('mm-ok').disabled = false;
  }
  function load(selectId) {
    $('mm-grid').textContent = 'Đang tải…';
    return listMedia().then(function (items) {
      state.items = items; state.sel = null; $('mm-ok').disabled = true; $('mm-prev').hidden = true; $('mm-info').textContent = 'Chọn một ảnh để xem chi tiết.';
      var hit = selectId ? items.filter(function (i) { return i.id === selectId; })[0] : null;
      renderGrid(); if (hit) select(hit);
    }).catch(function () { $('mm-grid').textContent = 'Không tải được thư viện ảnh.'; });
  }
  function handleFiles(files) {
    files = Array.prototype.filter.call(files, function (f) { return /^image\//.test(f.type); });
    if (!files.length) { $('mm-upmsg').textContent = 'Hãy chọn tệp ảnh (JPG, PNG, WebP).'; return; }
    var last = null, i = 0;
    function next() {
      if (i >= files.length) { $('mm-upmsg').textContent = 'Đã tải xong.'; setTab('lib'); return load(last); }
      $('mm-upmsg').textContent = 'Đang nén và tải ảnh ' + (i + 1) + '/' + files.length + '…';
      return uploadFile(files[i++]).then(function (r) { last = r.id; return next(); });
    }
    next().catch(function (e) { $('mm-upmsg').textContent = 'Lỗi: ' + e.message; });
  }
  function finish(v) { var r = state.resolve; state.resolve = null; if (mm.open) mm.close(); if (r) r(v); }
  if (mm) {
    mm.querySelectorAll('[data-mmtab]').forEach(function (b) { b.addEventListener('click', function () { setTab(b.getAttribute('data-mmtab')); }); });
    $('mm-x').addEventListener('click', function () { finish(null); });
    $('mm-cancel').addEventListener('click', function () { finish(null); });
    mm.addEventListener('close', function () { finish(null); });
    $('mm-ok').addEventListener('click', function () { if (state.sel) finish({ item: state.sel, alt: $('mm-alt').value.trim(), title: $('mm-imgtitle').value.trim() }); });
    $('mm-file').addEventListener('change', function () { handleFiles(this.files); this.value = ''; });
    var drop = $('mm-drop');
    ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('over'); }); });
    drop.addEventListener('drop', function (e) { handleFiles(e.dataTransfer.files); });
  }
  // opts: { title, confirm, alt }
  function openMedia(opts) {
    return new Promise(function (resolve) {
      state.resolve = resolve; state.opts = opts || {};
      $('mm-title').textContent = state.opts.title || 'Thêm ảnh';
      $('mm-ok').textContent = state.opts.confirm || 'Chèn vào bài';
      $('mm-alt').value = state.opts.alt || '';
      $('mm-imgtitle').value = state.opts.imgtitle || '';
      $('mm-upmsg').textContent = '';
      mm.showModal(); setTab('lib'); load();
    });
  }
  window.VFWM = { uploadFile: uploadFile, openMedia: openMedia, token: token };
})();

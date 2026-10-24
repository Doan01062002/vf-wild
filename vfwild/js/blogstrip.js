/* Dải "Bài viết mới" trên trang chủ: đọc /blog/latest.json (do trang quản trị tạo mỗi lần đăng bài).
   Nếu tải lỗi thì giữ nguyên danh sách có sẵn trong HTML. */
(function () {
  var list = document.querySelector('[data-blogstrip]');
  var box = document.getElementById('bai-viet');
  if (!list || !box || !window.fetch) return;
  fetch('/blog/latest.json', { cache: 'no-cache' })
    .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('http')); })
    .then(function (posts) {
      if (!Array.isArray(posts)) return;
      posts = posts.filter(function (p) { return p && /^[a-z0-9-]+$/.test(p.slug || '') && p.title; }).slice(0, 3);
      if (!posts.length) { box.hidden = true; return; }
      list.textContent = '';
      posts.forEach(function (p) {
        var a = document.createElement('a');
        a.className = 'blogstrip__item';
        a.href = '/blog/' + p.slug + '/';
        var cat = document.createElement('span'); cat.className = 'blogstrip__cat'; cat.textContent = p.category || '';
        var t = document.createElement('strong'); t.textContent = p.title;
        var more = document.createElement('span'); more.className = 'blogstrip__more'; more.textContent = 'Đọc bài →';
        a.appendChild(cat); a.appendChild(t); a.appendChild(more);
        list.appendChild(a);
      });
    })
    .catch(function () { /* giữ danh sách có sẵn */ });
})();

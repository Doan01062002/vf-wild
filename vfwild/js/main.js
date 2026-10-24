/* VF Wild ChungAuto – giao diện: menu, bậc giá, máy tính, form lead, đo lường (dataLayer), đồng ý cookie. */
(function () {
  'use strict';
  var E = window.VFWEngine;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  document.documentElement.classList.remove('no-js');

  var DATA = {};
  try { DATA = JSON.parse($('#vfw-data').textContent); } catch (e) { DATA = {}; }
  var STAGING = DATA.mode !== 'production';
  var SITE = DATA.site || {};

  // Bản nháp: ?at=2026-10-01T00:00:00+07:00 để xem trang tại thời điểm khác (kiểm tra hết hạn ưu đãi).
  var AT = null;
  if (STAGING) { try { var q = new URLSearchParams(location.search).get('at'); if (q && !isNaN(Date.parse(q))) AT = Date.parse(q); } catch (e) {} }
  function now() { return AT || Date.now(); }

  function store(kind) { try { return window[kind]; } catch (e) { return null; } }
  function sGet(k) { var s = store('sessionStorage'); try { return s ? s.getItem(k) : null; } catch (e) { return null; } }
  function sSet(k, v) { var s = store('sessionStorage'); try { if (s) s.setItem(k, v); } catch (e) {} }
  function lGet(k) { var s = store('localStorage'); try { return s ? s.getItem(k) : null; } catch (e) { return null; } }
  function lSet(k, v) { var s = store('localStorage'); try { if (s) s.setItem(k, v); } catch (e) {} }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  /* ---------------- Đo lường: chỉ đẩy sự kiện vào dataLayer, không chứa dữ liệu cá nhân ---------------- */
  window.dataLayer = window.dataLayer || [];
  var TRK = {};   // mã Google đã tải sau khi khách đồng ý: ga4, ads (+ adsLabel) – để gửi sự kiện, chuyển đổi trực tiếp
  function gtag() { window.dataLayer.push(arguments); }
  function track(event, params) {
    var o = { event: event }; if (params) for (var k in params) o[k] = params[k];
    window.dataLayer.push(o);                       // GTM đọc sự kiện này
    if (TRK.ga4) gtag('event', event, params || {});   // GA4 cài trực tiếp ở trang quản trị
  }
  var debounced = {};
  // Meta Pixel (mã chính thức của Meta) – chỉ gọi sau khi khách đồng ý cookie
  function loadMetaPixel(id) {
    !function (f, b, e, v, n, t, s) {
      if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
      if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = [];
      t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
    }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
    window.fbq('init', id);
    window.fbq('track', 'PageView');
  }
  // Google tag (gtag.js) cho GA4 / Google Ads cài trực tiếp – chỉ gọi sau khi khách đồng ý cookie
  function loadGtag(ga4, ads) {
    var head = window.__vfwGa4;   // gtag.js đã chạy từ <head> (khách đồng ý từ lần trước) → chỉ thêm cấu hình còn thiếu
    if (!head) {
      var s = document.createElement('script'); s.async = true;
      s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga4 || ads);
      document.head.appendChild(s);
      gtag('js', new Date());
    }
    if (ga4 && ga4 !== head) gtag('config', ga4);
    if (ads) gtag('config', ads);
    TRK.ga4 = ga4 || ''; TRK.ads = ads || '';
  }
  // Gửi sự kiện Meta khi pixel đã tải; không kèm họ tên/SĐT. eventID để khử trùng với Conversions API sau này.
  function pixelTrack(name, params, eventId) {
    if (typeof window.fbq === 'function') window.fbq('track', name, params || {}, eventId ? { eventID: eventId } : {});
  }
  /* Hộp thoại (popup, máy tính chi phí): mở/đóng mờ dần, trượt nhẹ */
  var REDUCED = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  function openDialog(d) {
    if (d.open) return;
    if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
    document.body.classList.add('modal-open');
    void d.offsetWidth;   // áp trạng thái đầu rồi mới chạy hiệu ứng
    d.classList.add('is-shown');
  }
  function closeDialog(d) {
    if (!d.open || d.__closing) return;
    d.__closing = true; d.classList.remove('is-shown');
    setTimeout(function () { d.__closing = false; if (typeof d.close === 'function') d.close(); else { d.removeAttribute('open'); d.dispatchEvent(new Event('close')); } }, REDUCED ? 0 : 240);
  }
  function wireDialog(d) {   // phím Esc / bấm ra ngoài → đóng có hiệu ứng
    d.addEventListener('cancel', function (e) { e.preventDefault(); closeDialog(d); });
    d.addEventListener('click', function (e) { if (e.target === d) closeDialog(d); });
    d.addEventListener('close', function () { d.classList.remove('is-shown'); document.body.classList.remove('modal-open'); });
  }
  function trackLater(event, params) { clearTimeout(debounced[event]); debounced[event] = setTimeout(function () { track(event, params); }, 1200); }

  /* ---------------- Nguồn truy cập (UTM, click ID) – lưu trong phiên, chỉ gửi kèm khi khách gửi form ---------------- */
  var ATTR = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid'];
  (function captureAttribution() {
    var q; try { q = new URLSearchParams(location.search); } catch (e) { return; }
    var touch = {}, has = false;
    ATTR.forEach(function (k) { var v = q.get(k); if (v) { touch[k] = v.slice(0, 200); has = true; } });
    if (has) {
      touch.landing = location.pathname; touch.ts = new Date().toISOString();
      if (!sGet('vfw_first')) sSet('vfw_first', JSON.stringify(touch));
      sSet('vfw_last', JSON.stringify(touch));
    }
    if (!sGet('vfw_landing')) sSet('vfw_landing', (location.pathname + location.search).slice(0, 300));
    if (!sGet('vfw_ref') && document.referrer && document.referrer.indexOf(location.host) < 0) sSet('vfw_ref', document.referrer.slice(0, 300));
  })();
  function attribution() {
    return JSON.stringify({ first: JSON.parse(sGet('vfw_first') || 'null'), last: JSON.parse(sGet('vfw_last') || 'null'), landing: sGet('vfw_landing'), referrer: sGet('vfw_ref') });
  }

  /* ---------------- Menu, header, hiệu ứng ---------------- */
  var burger = $('#hamburger'), nav = $('#nav');
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var open = nav.classList.toggle('open'); burger.classList.toggle('open', open); burger.setAttribute('aria-expanded', String(open));
    });
    $$('a', nav).forEach(function (a) { a.addEventListener('click', function () { nav.classList.remove('open'); burger.classList.remove('open'); burger.setAttribute('aria-expanded', 'false'); }); });
  }

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (es) {
      var n = 0;
      es.forEach(function (e) {
        if (!e.isIntersecting) return;
        var el = e.target, delay = Math.min(n++ * 70, 280);   // các khối cùng lúc hiện so le 70 ms
        io.unobserve(el);
        el.style.setProperty('--rd', delay + 'ms');
        el.classList.add('in');
        setTimeout(function () { el.classList.remove('reveal', 'in'); el.style.removeProperty('--rd'); }, 900 + delay);   // xong hiệu ứng → dùng lại transition gốc của khối
      });
    }, { rootMargin: '0px 0px -6% 0px' });
    $$('.reveal').forEach(function (el) { io.observe(el); });
  } else { $$('.reveal').forEach(function (el) { el.classList.add('in'); }); }

  $$('.yt[data-id]').forEach(function (box) {
    function play() {
      if ($('iframe', box)) return;
      var f = document.createElement('iframe');
      f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(box.getAttribute('data-id')) + '?autoplay=1&rel=0';
      f.title = box.getAttribute('data-title') || 'Video VF Wild';
      f.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture'; f.allowFullscreen = true;
      box.appendChild(f); track('video_play', { video_id: box.getAttribute('data-id') });
    }
    box.addEventListener('click', play);
    box.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); play(); } });
  });

  /* ---------------- Trạng thái dùng chung: màu xe + kênh mua ---------------- */
  var variants = DATA.variants || [];
  var STATE = { variant: variants.length ? variants[0].id : null, o2o: true, owner: true, cls: 'con', customerType: 'thong-thuong' };
  function variantById(id) { for (var i = 0; i < variants.length; i++) if (variants[i].id === id) return variants[i]; return null; }
  function priceCtx() { return { variant: STATE.variant, channel: STATE.o2o ? 'o2o' : 'showroom', customerType: STATE.customerType, flags: { owner: STATE.owner } }; }
  function bestCtx(vid) { return { variant: vid, channel: 'o2o', customerType: STATE.customerType, flags: { owner: true } }; }
  function currentLadder() {
    var v = variantById(STATE.variant);
    if (!v) return null;
    return E.ladder(v.list_price, DATA.offers || [], priceCtx(), now());
  }
  function vehiclePrice() { var L = currentLadder(); if (!L) return null; return L.total !== null ? L.total : L.list; }

  function srcLinks(ids) { // chỉ hiện ở staging để rà soát; bản chính thức không hiện
    if (!STAGING || !ids || !ids.length) return '';
    return '<a class="srcref" href="/nguon-du-lieu/#' + esc(ids[0]) + '" title="' + ids.length + ' nguồn – xem trang Nguồn dữ liệu">nguồn</a>';
  }
  function pendingBadge(item) { return (STAGING && item && item.status === 'pending') ? ' <span class="vbadge">Chưa xác minh</span>' : ''; }
  function fmtDate(iso) { var d = new Date(Date.parse(iso)); return d.toLocaleDateString('vi-VN', { day: 'numeric', month: 'numeric', year: 'numeric', timeZone: 'Asia/Ho_Chi_Minh' }); }
  function countdown(endIso) {
    var left = Date.parse(endIso) - now();
    if (left <= 0 || left > 72 * 3600e3) return '';
    var h = Math.floor(left / 3600e3), m = Math.floor(left % 3600e3 / 60e3);
    return 'Còn ' + (h >= 24 ? Math.floor(h / 24) + ' ngày ' + (h % 24) + ' giờ' : h + ' giờ ' + m + ' phút');
  }

  /* ---------------- Bậc giá ---------------- */
  // Các mức giá riêng (công an / quân đội, voucher khác…) theo phiên bản; tự ẩn khi ưu đãi đi kèm hết hạn
  function tierList(vid) {
    var T = DATA.price_tiers || {}, v = variantById(vid), out = [], missing = false;
    (T.items || []).forEach(function (t) {
      var o = t.requires_offer ? (DATA.offers || []).filter(function (x) { return x.id === t.requires_offer; })[0] : null;
      if (t.requires_offer && (!o || E.offerState(o, now()) === 'expired')) return;
      var val = null;
      if (t.compute === 'best') { if (v) val = E.ladder(v.list_price, DATA.offers || [], bestCtx(vid), now()).total; }
      else if (t.amounts) { val = t.amounts[vid] != null ? t.amounts[vid] : null; if (val === null) missing = true; }
      if (val !== null && val !== undefined) out.push({ t: t, val: val });
    });
    out.missing = missing;
    return out;
  }
  function renderLadders() {
    var L = currentLadder();
    $$('[data-ladder-rows]').forEach(function (box) {
      if (!L) { box.innerHTML = '<p class="muted">Giá đang cập nhật – gọi ' + esc(SITE.hotline && SITE.hotline.display) + ' để nhận báo giá.</p>'; return; }
      var v = variantById(STATE.variant), html = [], terms = [];
      html.push('<div class="lad__row lad__row--list"><span>Giá niêm yết<small>Đã gồm pin &amp; VAT</small></span><b>' + E.vnd(L.list) + '</b>' + pendingBadge(v) + '</div>');
      L.lines.forEach(function (ln) {
        var o = ln.offer, cd = o.end ? countdown(o.end) : '';
        var chip = cd ? '<em class="lad__chip lad__chip--hot">' + cd + '</em>' : (o.chip ? '<em class="lad__chip">' + esc(o.chip) + '</em>' : '');
        var name = '<span class="lad__name">' + esc(o.label || o.name) + chip + pendingBadge(o) + '</span>';
        if (ln.off) {
          var why = ln.off === 'channel' ? 'Khi mua qua O2O' : ln.off === 'requires' ? 'Chưa đủ điều kiện' : 'Không áp dụng';
          html.push('<div class="lad__row lad__row--off">' + name + '<span class="lad__off">' + why + '</span></div>');
        } else {
          html.push('<div class="lad__row">' + name + '<b class="lad__minus">−' + E.vnd(ln.amount) + '</b></div>');
        }
        terms.push('<li><b>' + esc(o.name) + ':</b> ' + esc(o.conditions || '') + (o.window_label ? ' <i>' + esc(o.window_label) + '.</i>' : '') + ' ' + srcLinks(o.sources) + '</li>');
      });
      L.info.forEach(function (it) {
        html.push('<div class="lad__row lad__row--off"><span class="lad__name">' + esc(it.offer.label || it.offer.name) + pendingBadge(it.offer) + '</span><span class="lad__off">Đang xác minh</span></div>');
        terms.push('<li><b>' + esc(it.offer.name) + ':</b> ' + esc(it.offer.conditions || '') + '</li>');
      });
      if (L.total !== null) {
        if (L.saving > 0) html.push('<div class="lad__badge">🔥 Tiết kiệm hơn ' + Math.floor(L.saving / 1e6).toLocaleString('vi-VN') + ' triệu</div>');
        html.push('<div class="lad__total"><span>Giá sau ưu đãi</span><b>' + E.vnd(L.total) + '</b></div>');
        if (L.saving > 0) html.push('<p class="lad__save">Tiết kiệm: ' + E.vnd(L.saving) + '</p>');
        var tiers = tierList(STATE.variant).filter(function (x) { return x.t.compute !== 'best'; });
        if (tiers.length) {
          html.push('<div class="lad__tiers">' + tiers.map(function (x) {
            if (x.t.conditions) terms.push('<li><b>' + esc(x.t.label) + ':</b> ' + esc(x.t.conditions) + ' ' + srcLinks(x.t.sources) + '</li>');
            return '<div class="lad__tier"><span>' + esc(x.t.label) + pendingBadge(x.t) + '</span><b>' + E.vnd(x.val) + '</b></div>';
          }).join('') + '</div>');
        }
        (DATA.perks || []).forEach(function (pk) { html.push('<p class="lad__perk">⚡ Kèm theo: ' + esc(pk.text) + pendingBadge(pk) + '</p>'); });
      } else {
        html.push('<div class="callout">Chưa xác nhận cách áp dụng đồng thời các ưu đãi – tư vấn viên sẽ gửi báo giá bằng văn bản.</div>');
      }
      if (terms.length) {
        html.push('<details class="lad__terms"><summary>Xem điều kiện</summary><ul>' + terms.join('') +
          '<li>Giá tham khảo tại thời điểm báo giá, chưa gồm phí lăn bánh. Ưu đãi 2% chỉ áp dụng khi mua qua kênh O2O của VinFast (hợp đồng ký với VinFast).</li></ul></details>');
      }
      var h = html.join('');
      if (box.__h !== h) { box.__h = h; box.innerHTML = h; }
    });
    $$('[data-price-final]').forEach(function (el) {
      var L2 = currentLadder(); if (!L2) return;
      var best = E.ladder(L2.list, DATA.offers || [], bestCtx(STATE.variant), now());
      el.textContent = best.total !== null ? E.mil(best.total, 2) : E.mil(best.list, 0);
    });
    $$('[data-price-final-num]').forEach(function (el) {
      var v0 = variants[0]; if (!v0) return;
      var best = E.ladder(v0.list_price, DATA.offers || [], bestCtx(v0.id), now());
      var val = best.total !== null ? best.total : best.list;
      el.innerHTML = (val / 1e6).toLocaleString('vi-VN', { maximumFractionDigits: 2 }) + '<span>triệu</span>';
    });
    $$('[data-offer-active]').forEach(function (el) {
      var id = el.getAttribute('data-offer-active'), o = (DATA.offers || []).filter(function (x) { return x.id === id; })[0];
      el.hidden = !o || E.offerState(o, now()) === 'expired';
    });
    $$('[data-offer-expired]').forEach(function (el) {
      var id = el.getAttribute('data-offer-expired'), o = (DATA.offers || []).filter(function (x) { return x.id === id; })[0];
      el.hidden = !!o && E.offerState(o, now()) !== 'expired';
    });
  }

  function setVariant(id) {
    STATE.variant = id;
    $$('.vtab').forEach(function (b) { var on = b.getAttribute('data-variant') === id; b.classList.toggle('is-active', on); b.setAttribute('aria-pressed', String(on)); });
    $$('[data-f="variant"]').forEach(function (s) { s.value = id; });
    refreshAll();
  }
  function setO2O(on) { STATE.o2o = on; $$('[data-o2o]').forEach(function (c) { c.checked = on; }); refreshAll(); }
  function setOwner(on) { STATE.owner = on; $$('[data-owner]').forEach(function (c) { c.checked = on; }); refreshAll(); }
  $$('.vtab').forEach(function (b) { b.addEventListener('click', function () { setVariant(b.getAttribute('data-variant')); }); });
  $$('[data-o2o]').forEach(function (c) { c.addEventListener('change', function () { setO2O(c.checked); }); });
  $$('[data-owner]').forEach(function (c) { c.addEventListener('change', function () { setOwner(c.checked); }); });

  if ('IntersectionObserver' in window) {
    var seen = false, vo = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting && !seen) { seen = true; track('view_offer', { placement: e.target.getAttribute('data-placement') || 'offer', variant: STATE.variant }); } });
    }, { threshold: .5 });
    $$('[data-ladder-rows]').forEach(function (el) { vo.observe(el); });
  }

  /* ---------------- Lăn bánh ---------------- */
  function monthOptions(sel) {
    if (sel.options.length) return;
    var d = new Date(now()), y = d.getFullYear(), m = d.getMonth() + 2;
    for (var i = 0; i < 12; i++, m++) {
      var yy = y + Math.floor((m - 1) / 12), mm = ((m - 1) % 12) + 1;
      var val = yy + '-' + (mm < 10 ? '0' : '') + mm;
      sel.add(new Option('Tháng ' + mm + '/' + yy, val));
    }
    var first = DATA.fees && DATA.fees.registration_tax && DATA.fees.registration_tax.ev_exemption;
    sel.value = sel.options[Math.min(2, sel.options.length - 1)].value;
    if (first && first.until) sel.setAttribute('data-until', first.until);
  }
  function renderOnRoad(box) {
    var fees = DATA.fees; if (!fees) return;
    var vSel = $('[data-f="variant"]', box), pSel = $('[data-f="province"]', box), mSel = $('[data-f="regmonth"]', box), cSel = $('[data-f="cls"]', box);
    if (cSel) { if (box.contains(document.activeElement) && document.activeElement === cSel) STATE.cls = cSel.value; else cSel.value = STATE.cls; }
    if (vSel && vSel.value !== STATE.variant) vSel.value = STATE.variant;
    monthOptions(mSel);
    var v = variantById(STATE.variant); if (!v) return;
    var price = vehiclePrice();
    var R = E.onRoad(price, v.list_price, { province: pSel.value, regMonth: mSel.value, cls: STATE.cls }, fees);
    var ex = R.exemption || {};
    var rows = ['<tr class="bd__sub"><td>Giá xe dự kiến<small>' + (STATE.o2o ? 'Đã trừ ưu đãi, mua qua O2O' : 'Đã trừ ưu đãi, mua tại showroom') + '</small></td><td>' + E.vnd(price) + '</td></tr>'];
    rows.push('<tr><td>Lệ phí trước bạ<small>' + esc(ex.claim || ex.label || '') + '</small></td><td>' + (R.A.ok ? '0đ' : 'Xem kịch bản') + pendingBadge(ex) + '</td></tr>');
    R.rows.forEach(function (r) {
      var note = r.id === 'road' ? '' : (r.item.note || '');
      rows.push('<tr><td>' + esc(r.label) + (note ? '<small>' + esc(note) + '</small>' : '') + srcLinks(r.item.sources) + '</td><td>' + (r.amount === null ? '<span class="muted">Đang cập nhật</span>' : E.vnd(r.amount)) + pendingBadge(r.item) + '</td></tr>');
    });
    $('[data-f="rows"]', box).innerHTML = rows.join('');
    var sc = [];
    sc.push('<div class="scen__card scen__card--main"><span>' + (R.A.ok ? 'Tổng chi phí lăn bánh (trước bạ 0%)' : 'Miễn trước bạ') + '</span><b>' +
      (R.A.ok ? E.vnd(R.A.total) : 'Không áp dụng') + '</b><small>' + (R.A.ok ? (R.complete ? '' : 'Chưa gồm khoản đang cập nhật. ') + 'Chưa gồm bảo hiểm thân vỏ & phí dịch vụ.' : 'Tháng đăng ký sau ' + fmtDate(ex.until + 'T12:00:00+07:00') + ' – ngoài thời hạn miễn hiện hành.') + '</small></div>');
    if (R.A.ok) { /* được miễn trước bạ → không cần kịch bản "không được miễn" */ }
    else if (R.B.totalMin !== null && R.B.totalMax !== null) {
      var rate = R.B.rMin === R.B.rMax ? R.B.rMin + '%' : R.B.rMin + '–' + R.B.rMax + '%';
      var tot = R.B.totalMin === R.B.totalMax ? E.mil(R.B.totalMin, 1) : E.mil(R.B.totalMin, 1) + ' – ' + E.mil(R.B.totalMax, 1);
      sc.push('<div class="scen__card"><span>Nếu không được miễn trước bạ</span><b>' + tot + '</b><small>Trước bạ ' + rate + (R.B.ratio === 0.6 ? ' × 60% (pick-up chở hàng)' : ' (xe con pick-up)') +
        ' = ' + (R.B.min === R.B.max ? E.mil(R.B.min, 1) : E.mil(R.B.min, 1) + ' – ' + E.mil(R.B.max, 1)) + ', tạm tính trên giá niêm yết.</small></div>');
    } else {
      sc.push('<div class="scen__card"><span>Nếu không được miễn trước bạ</span><b>Đang cập nhật</b><small>Chưa có mức thu đã xác minh cho tỉnh này.</small></div>');
    }
    $('[data-f="scenarios"]', box).innerHTML = sc.join('');
    var pName = pSel.options[pSel.selectedIndex] ? pSel.options[pSel.selectedIndex].text : '';
    var cNote = $('[data-f="cls-note"]', box);
    if (cNote && cSel) { var ci = (fees.classes || []).filter(function (c) { return c.id === STATE.cls; })[0]; cNote.textContent = ci ? ci.note : ''; }
    box.setAttribute('data-summary', JSON.stringify({ province: pSel.value, variant: STATE.variant, o2o: STATE.o2o, owner: STATE.owner, cls: STATE.cls, vehicle: price, onroad_a: R.A.ok ? R.A.total : null }));
    trackLater('calc_rolling_cost', { province_code: pSel.value, variant: STATE.variant, reg_month: mSel.value, lptb_scenario: R.A.ok ? 'A' : 'B', channel: STATE.o2o ? 'o2o' : 'showroom' });
    var t = $('[data-f="province-name"]', box); if (t) t.textContent = pName;
  }

  /* ---------------- Trả góp ---------------- */
  function num(el, def) { var v = el ? parseFloat(String(el.value).replace(',', '.')) : NaN; return isNaN(v) ? def : v; }
  function renderLoan(box) {
    var F = (DATA.finance && DATA.finance.defaults) || {};
    var srcSel = $('[data-f="price-src"]', box);
    var v = variantById(STATE.variant); if (!v) return;
    var price = srcSel && srcSel.value === 'list' ? v.list_price : vehiclePrice();
    var o = {
      price: price, downPct: num($('[data-f="down"]', box), F.down_pct), years: num($('[data-f="years"]', box), F.term_years),
      ratePromo: num($('[data-f="rate-promo"]', box), F.rate_promo), promoMonths: num($('[data-f="promo-months"]', box), F.promo_months),
      rateAfter: num($('[data-f="rate-after"]', box), F.rate_after), method: ($('[data-f="method"]', box) || {}).value || F.method
    };
    var R = E.loan(o);
    var set = function (k, t) { var el = $('[data-o="' + k + '"]', box); if (el) el.textContent = t; };
    set('down-out', o.downPct + '%'); set('years-out', o.years + ' năm');
    set('price', E.vnd(price)); set('down', E.vnd(R.down)); set('loan', E.vnd(R.loan));
    set('first', E.vnd(R.first)); set('after', R.firstAfterPromo ? E.vnd(R.firstAfterPromo) : '–');
    set('interest', E.vnd(R.totalInterest)); set('total', E.vnd(R.totalPaid));
    var body = $('[data-o="sched"]', box);
    if (body && !body.closest('[hidden]')) {
      body.innerHTML = R.rows.map(function (r) {
        return '<tr' + (r.promo ? ' class="is-promo"' : '') + '><td>' + r.m + '</td><td>' + E.vnd(r.principal) + '</td><td>' + E.vnd(r.interest) + '</td><td>' + E.vnd(r.pay) + '</td><td>' + E.vnd(r.bal) + '</td></tr>';
      }).join('');
    }
    box.setAttribute('data-summary', JSON.stringify({ price: price, down_pct: o.downPct, years: o.years, first: Math.round(R.first) }));
    trackLater('calc_installment', { loan_pct: 100 - o.downPct, term_months: R.months, method: o.method, rate_pct: o.ratePromo });
  }

  /* ---------------- Chi phí vận hành ---------------- */
  function renderFuel(box) {
    var km = num($('[data-f="km"]', box), 2000), dp = num($('[data-f="diesel-price"]', box), 20000), dc = num($('[data-f="diesel-cons"]', box), 8);
    var ep = num($('[data-f="elec-price"]', box), 3858), ec = num($('[data-f="ev-cons"]', box), 20);
    var dKm = dc * dp / 100, eKm = ec * ep / 100;   // đ/km
    var diesel = km * dKm, ev = km * eKm, save = Math.max(0, diesel - ev);
    var set = function (k, t) { var el = $('[data-o="' + k + '"]', box); if (el) el.textContent = t; };
    set('km', km.toLocaleString('vi-VN') + ' km'); set('diesel', E.vnd(diesel)); set('ev', E.vnd(ev)); set('save', E.vnd(save)); set('save-year', E.mil(save * 12, 1) + '/năm');
    set('diesel-km', Math.round(dKm).toLocaleString('vi-VN') + 'đ/km · ' + dc.toLocaleString('vi-VN') + ' lít/100 km × ' + E.vnd(dp) + '/lít');
    set('ev-km', Math.round(eKm).toLocaleString('vi-VN') + 'đ/km · ' + ec.toLocaleString('vi-VN') + ' kWh/100 km × ' + E.vnd(ep) + '/kWh');
    var top = Math.max(diesel, ev) || 1;
    $$('[data-bar]', box).forEach(function (b) { b.style.width = Math.max(2, (b.getAttribute('data-bar') === 'ev' ? ev : diesel) / top * 100) + '%'; });
    var tb = $('[data-o="table"]', box);
    if (tb) tb.innerHTML = [['1 km', dKm, eKm, 'vnd'], ['1 tháng (' + km.toLocaleString('vi-VN') + ' km)', diesel, ev], ['1 năm', diesel * 12, ev * 12], ['5 năm', diesel * 60, ev * 60]]
      .map(function (r) {
        var f = r[3] === 'vnd' ? function (x) { return Math.round(x).toLocaleString('vi-VN') + 'đ'; } : function (x) { return E.mil(x, x >= 1e8 ? 1 : 2); };
        return '<tr><td>' + r[0] + '</td><td>' + f(r[1]) + '</td><td>' + f(r[2]) + '</td><td>' + f(Math.max(0, r[1] - r[2])) + '</td></tr>';
      }).join('');
  }

  function refreshAll() {
    renderLadders();
    $$('[data-calc="onroad"]').forEach(renderOnRoad);
    $$('[data-calc="loan"]').forEach(renderLoan);
    renderPlans();
  }
  function renderPlans() {
    var v0 = variants[0]; if (!v0 || !E) return;
    $$('[data-plan-loan]').forEach(function (el) {
      var F = (DATA.finance && DATA.finance.defaults) || {};
      var best = E.ladder(v0.list_price, DATA.offers || [], bestCtx(v0.id), now());
      var price = best.total !== null ? best.total : best.list;
      var R = E.loan({ price: price, downPct: F.down_pct, years: F.term_years, ratePromo: F.rate_promo, promoMonths: F.promo_months, rateAfter: F.rate_after, method: F.method });
      el.textContent = E.mil(R.down, 0);
      var plan = el.closest('.plan'), s = plan && plan.querySelector('[data-plan-first]'); if (s) s.textContent = E.mil(R.first, 1);
    });
  }

  $$('[data-calc]').forEach(function (box) {
    var type = box.getAttribute('data-calc');
    var fn = type === 'onroad' ? renderOnRoad : type === 'loan' ? renderLoan : renderFuel;
    box.addEventListener('input', function (e) {
      if (e.target.matches('[data-f="variant"]')) { setVariant(e.target.value); return; }
      if (e.target.matches('[data-o2o],[data-owner],[data-f="cls"]')) return;
      fn(box);
    });
    box.addEventListener('change', function (e) {
      if (e.target.matches('[data-f="cls"]')) { STATE.cls = e.target.value; refreshAll(); return; }
      if (e.target.matches('select')) fn(box);
    });
    var tgl = $('[data-toggle-sched]', box);
    if (tgl) tgl.addEventListener('click', function () {
      var wrap = $('.sched', box); wrap.hidden = !wrap.hidden; tgl.textContent = wrap.hidden ? 'Xem lịch trả nợ từng tháng' : 'Ẩn lịch trả nợ'; renderLoan(box);
    });
    if (type === 'fuel') renderFuel(box);
  });
  var urlQ; try { urlQ = new URLSearchParams(location.search); } catch (e) { urlQ = null; }
  if (urlQ && urlQ.get('tinh')) $$('[data-f="province"]').forEach(function (s) { s.value = urlQ.get('tinh'); });
  refreshAll();
  setInterval(function () { renderLadders(); renderTiers(); }, 60000);

  /* ---------------- Hộp thoại máy tính (giống limogreen) ---------------- */
  var modal = $('#calc-modal');
  function openCalc(tab) {
    if (!modal) { location.href = tab === 'loan' ? '/tra-gop-vf-wild/' : '/gia-lan-banh-vf-wild/'; return; }
    $$('.tabs__btn', modal).forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-tab') === tab); });
    $$('.tabs__panel', modal).forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-panel') === tab); });
    openDialog(modal);
    refreshAll();
    track('open_calculator', { calculator: tab });
  }
  $$('[data-open-calc]').forEach(function (b) { b.addEventListener('click', function () { openCalc(b.getAttribute('data-open-calc')); }); });
  if (modal) {
    $$('.tabs__btn', modal).forEach(function (b) { b.addEventListener('click', function () { openCalc(b.getAttribute('data-tab')); }); });
    var close = function () { closeDialog(modal); };
    wireDialog(modal);
    $$('[data-close-modal]', modal).forEach(function (b) { b.addEventListener('click', close); });
    $$('a[href^="#"]', modal).forEach(function (a) { a.addEventListener('click', close); });
  }

  /* ---------------- Màu xe ---------------- */
  var colorVariant = null;   // phiên bản của màu đang chọn (màu Bạc có giá riêng)
  function renderTiers() {
    var box = $('[data-price-tiers]'), T = DATA.price_tiers;
    if (!box || !T) return;
    var vid = colorVariant || (variants[0] && variants[0].id), list = tierList(vid), rows = [], terms = [], missing = list.missing;
    list.forEach(function (x) {
      var t = x.t;
      rows.push('<li><span>' + esc(t.label) + pendingBadge(t) + '</span><b>' + E.mil(x.val, 2) + '</b></li>');
      if (t.conditions) terms.push('<li><b>' + esc(t.label) + ':</b> ' + esc(t.conditions) + ' ' + srcLinks(t.sources) + '</li>');
    });
    box.hidden = !rows.length;
    var h = !rows.length ? '' : '<ul class="tiers">' + rows.join('') + '</ul>' +
      '<p class="tiers__note">' + esc(T.note || '') + (missing && T.note_missing ? ' ' + esc(T.note_missing) : '') + '</p>' +
      (terms.length ? '<details class="tiers__terms"><summary>Điều kiện từng mức giá</summary><ul>' + terms.join('') + '</ul></details>' : '');
    if (box.__h !== h) { box.__h = h; box.innerHTML = h; }
  }
  $$('.swatch').forEach(function (s) {
    s.addEventListener('click', function () {
      $$('.swatch').forEach(function (x) { x.classList.toggle('is-active', x === s); });
      var stage = $('.colors__stage'); if (stage) stage.style.setProperty('--c', getComputedStyle(s).getPropertyValue('--c'));
      var img = $('[data-color-img]'); if (img && s.getAttribute('data-image')) { img.src = s.getAttribute('data-image'); img.alt = 'VinFast VF Wild màu ' + s.getAttribute('data-color') + ' – ảnh minh họa'; }
      var name = $('[data-color-name]'); if (name) name.textContent = s.getAttribute('data-color');
      var price = $('[data-color-price]'); if (price) price.textContent = s.getAttribute('data-price');
      colorVariant = s.getAttribute('data-variant') || colorVariant; renderTiers();
    });
  });
  renderTiers();

  /* ---------------- Giờ làm việc ---------------- */
  (function hours() {
    var h = SITE.hours || {}; if (!h.open) return;
    var parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Ho_Chi_Minh', hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date()).split(':');
    var cur = +parts[0] * 60 + +parts[1], o = h.open.split(':'), c = h.close.split(':');
    var openNow = cur >= +o[0] * 60 + +o[1] && cur < +c[0] * 60 + +c[1];
    if (!openNow) $$('[data-hours-hint]').forEach(function (el) { el.textContent = 'Ngoài giờ làm việc: tư vấn viên sẽ gọi lại từ ' + h.open + ' sáng hôm sau. Cần gấp? Nhắn Zalo.'; });
  })();

  /* ---------------- Form lead ---------------- */
  var INTENT_EVENT = { 'bao-gia': 'form_submit_quote', 'lai-thu': 'form_submit_test_drive', 'tra-gop': 'form_submit_finance', 'o2o': 'form_submit_o2o', 'uu-dai': 'form_submit_offer', 'tu-van': 'form_submit_consult' };
  var PHONE_RE = /^0(3|5|7|8|9)\d{8}$/;
  function normPhone(v) { v = String(v || '').replace(/[\s.\-()]/g, ''); if (/^\+?84/.test(v)) v = '0' + v.replace(/^\+?84/, ''); return v; }
  function uuid() { try { return crypto.randomUUID(); } catch (e) { return 'e' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10); } }

  $$('[data-lead-form]').forEach(function (form) {
    var t0 = Date.now(), started = false;
    var intentSel = $('[data-intent]', form);
    var intentOf = function () { return intentSel ? intentSel.value : 'bao-gia'; };
    function sync() {
      var it = intentOf();
      $$('[data-show-for]', form).forEach(function (el) {
        var on = el.getAttribute('data-show-for').split(' ').indexOf(it) >= 0;
        el.hidden = !on;
        var cb = $('input[name="consent_share_vinfast"]', el); if (cb) { cb.required = on; if (!on) cb.checked = false; }
      });
    }
    if (intentSel) intentSel.addEventListener('change', sync);
    if (urlQ) {
      if (urlQ.get('y') && intentSel && $('option[value="' + urlQ.get('y') + '"]', intentSel)) intentSel.value = urlQ.get('y');
      var sr = urlQ.get('showroom'), srSel = $('select[name="showroom"]', form);
      if (sr && srSel && $('option[value="' + sr + '"]', srSel)) srSel.value = sr;
    }
    sync();
    form.addEventListener('focusin', function () { if (!started) { started = true; track('form_start', { form_id: form.getAttribute('data-form-id'), intent: intentOf() }); } });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = $('.form-msg', form), btn = $('button[type="submit"]', form);
      var nameEl = form.elements.name, phoneEl = form.elements.phone, cc = form.elements.consent_contact, cv = form.elements.consent_share_vinfast;
      var phone = normPhone(phoneEl.value), ok = true;
      [nameEl, phoneEl].forEach(function (el) { el.classList.remove('err'); });
      $$('.check', form).forEach(function (el) { el.classList.remove('err'); });
      if (!nameEl.value.trim()) { nameEl.classList.add('err'); ok = false; }
      if (!PHONE_RE.test(phone)) { phoneEl.classList.add('err'); ok = false; }
      if (!cc.checked) { cc.closest('.check').classList.add('err'); ok = false; }
      if (cv && cv.required && !cv.checked) { cv.closest('.check').classList.add('err'); ok = false; }
      if (!ok) { msg.className = 'form-msg is-err'; msg.textContent = 'Vui lòng nhập họ tên, số điện thoại hợp lệ và tích ô đồng ý.'; return; }

      var intent = intentOf(), eventId = uuid();
      var fd = new FormData();
      ['name', 'province', 'showroom', 'note', 'website', 'intent'].forEach(function (k) { if (form.elements[k]) fd.append(k, form.elements[k].value || ''); });
      ['province', 'showroom'].forEach(function (k) {   // tên hiển thị để sales đọc trên Telegram/Google Sheet
        var s = form.elements[k]; if (s && s.selectedIndex > 0) fd.append(k + '_name', s.options[s.selectedIndex].text);
      });
      fd.append('phone', phone);
      var seg = $('input[name="segment"]:checked', form);   // nhóm khách ở popup tiên phong
      if (seg) { fd.append('segment', seg.value); fd.append('segment_label', seg.getAttribute('data-label') || seg.value); }
      fd.append('use', $$('input[name="use"]:checked', form).map(function (c) { return c.value; }).join('; '));
      fd.append('consent_contact', cc.checked ? '1' : '0');
      fd.append('consent_share_vinfast', cv && cv.checked ? '1' : '0');
      fd.append('consent_marketing', form.elements.consent_marketing && form.elements.consent_marketing.checked ? '1' : '0');
      fd.append('consent_version', '2026-09-24b');
      fd.append('form_id', form.getAttribute('data-form-id'));
      fd.append('page', location.href.slice(0, 300));
      fd.append('attribution', attribution());
      var calc = $('[data-calc="onroad"][data-summary]') || $('[data-calc="loan"][data-summary]');
      fd.append('calc', JSON.stringify({ variant: STATE.variant, o2o: STATE.o2o, vehicle: vehiclePrice(), detail: calc ? JSON.parse(calc.getAttribute('data-summary')) : null }));
      fd.append('event_id', eventId);
      fd.append('t', String(Date.now() - t0));

      btn.disabled = true; msg.className = 'form-msg'; msg.textContent = 'Đang gửi…';
      fetch(SITE.lead_endpoint || '/lead.php', { method: 'POST', headers: { Accept: 'application/json' }, body: fd })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok || !j.ok) throw new Error(j.error || r.status); return j; }); })
        .then(function (j) {
          var params = { form_id: form.getAttribute('data-form-id'), lead_type: intent, lead_ref: j.lead_ref || '', event_id: eventId, province_code: (form.elements.province || {}).value || '' };
          track(INTENT_EVENT[intent] || 'form_submit_consult', params);
          track('generate_lead', { lead_type: intent, lead_ref: j.lead_ref || '', currency: 'VND', event_id: eventId });
          if (TRK.ads && TRK.adsLabel) gtag('event', 'conversion', { send_to: TRK.ads + '/' + TRK.adsLabel, transaction_id: j.lead_ref || eventId });
          pixelTrack('CompleteRegistration', { content_name: form.getAttribute('data-form-id'), status: true, lead_type: intent }, eventId);
          lSet('vfw_lead_sent', String(Date.now()));   // đã để lại thông tin → không mở popup nữa
          var thanks = form.hasAttribute('data-thanks-inline') && $('[data-popup-thanks]', form.parentNode);
          if (thanks) {   // popup: cảm ơn ngay trong popup
            form.hidden = true; thanks.hidden = false;
            var q = form.closest('[data-quick]'); if (q) q.classList.add('is-done');
            var closeBtn = $('button', thanks); if (closeBtn) closeBtn.focus();
            return;
          }
          msg.className = 'form-msg is-ok'; msg.textContent = '✓ Đã nhận thông tin. Đang chuyển trang…';
          setTimeout(function () { location.href = '/cam-on/?y=' + encodeURIComponent(intent); }, 500);
        })
        .catch(function () {
          msg.className = 'form-msg is-err';
          msg.innerHTML = 'Chưa gửi được. Vui lòng gọi <a href="tel:' + esc(SITE.hotline.tel) + '">' + esc(SITE.hotline.display) + '</a> hoặc nhắn <a href="' + esc(SITE.zalo.url) + '" target="_blank" rel="noopener">Zalo</a>.';
        })
        .finally(function () { btn.disabled = false; });
    });
  });

  /* ---------------- Popup nhận báo giá: tự mở 1 lần khi khách đã ở trên web 3 phút ---------------- */
  var openLead = null;   // mở popup đăng ký (dùng cho nút "Mua ngay")
  (function leadPopup() {
    var pop = $('#lead-popup'), cfg = SITE.lead_popup || {};
    if (!pop || typeof pop.showModal !== 'function') return;
    wireDialog(pop);
    $$('[data-close-popup]', pop).forEach(function (b) { b.addEventListener('click', function () { closeDialog(pop); }); });
    pop.addEventListener('close', function () {
      document.body.classList.remove('modal-open');
      if (!lGet('vfw_lead_sent')) { lSet('vfw_popup_closed', String(Date.now())); track('popup_close', { popup_id: 'bao-gia-3-phut' }); }
    });
    openLead = function (src) { openDialog(pop); sSet('vfw_popup_seen', '1'); track('popup_view', { popup_id: src || 'bao-gia-3-phut' }); };
    if (cfg.enabled === false) return;
    var POPUP_AFTER = (+cfg.after_seconds || 180) * 1000;
    var start = +sGet('vfw_t0') || Date.now(); sSet('vfw_t0', String(start));   // tính từ lúc vào web, cộng dồn qua các trang
    var test = !!(urlQ && urlQ.has('popup'));                                     // ?popup=1 → mở sau 2 giây để xem thử
    if (!test && (/^\/(cam-on|chinh-sach-bao-mat|dieu-khoan)\//.test(location.pathname)
      || lGet('vfw_lead_sent') || sGet('vfw_popup_seen')                            // đã gửi form / đã thấy popup trong phiên này
      || Date.now() - (+lGet('vfw_popup_closed') || 0) < 864e5)) return;            // đã đóng popup trong 24 giờ qua
    setTimeout(tryOpen, test ? 2000 : Math.max(POPUP_AFTER - (Date.now() - start), 4000));
    function tryOpen() {
      var a = document.activeElement;
      // Chờ khi khách đang ở tab khác, đang mở máy tính chi phí hoặc đang gõ vào một ô nhập
      if (document.hidden || document.body.classList.contains('modal-open') || (a && /^(INPUT|SELECT|TEXTAREA)$/.test(a.tagName))) {
        setTimeout(tryOpen, 3000); return;
      }
      openLead('bao-gia-3-phut');
    }
  })();

  /* ---------------- Thanh mua xe (điện thoại): Mua ngay → popup đăng ký; Video → phát video đầu trang ---------------- */
  $$('[data-sb-buy]').forEach(function (b) {
    b.addEventListener('click', function () {
      track('click_buy_now', { placement: 'stickybar' });
      if (openLead) openLead('mua-ngay'); else { var f = $('#dang-ky'); if (f) f.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
  });
  $$('[data-sb-video]').forEach(function (b) {
    b.addEventListener('click', function () {
      var yt = $('.hero__media .yt[data-id]') || $('.yt[data-id]');
      if (!yt) return;
      yt.click();   // phát ngay trong cú chạm của khách (trình duyệt cho tự phát)
      yt.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });

  /* ---------------- Nút "Nhận báo giá" ở đầu trang và giữa trang: mở popup đăng ký (không có popup thì cuộn tới form cuối trang) ---------------- */
  (function () {
    var pop = $('#lead-popup'), intentInput = pop && $('input[name="intent"]', pop);
    var base = intentInput ? intentInput.value : 'bao-gia';
    // Popup nhận đúng nhu cầu của nút (trả góp, doanh nghiệp…); đóng popup thì trả về nhu cầu mặc định
    if (pop) pop.addEventListener('close', function () { if (intentInput) intentInput.value = base; });
    $$('[data-open-lead]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        if (!openLead) return;
        e.preventDefault();
        if (intentInput) intentInput.value = a.getAttribute('data-set-intent') || base;
        openLead(a.getAttribute('data-open-lead'));
      });
    });
  })();

  /* ---------------- Mục lục nhanh ---------------- */
  $$('[data-qnav]').forEach(function (a) {
    a.addEventListener('click', function () { track('quicknav_click', { target: (a.getAttribute('href') || '').replace('#', '') }); });
  });

  /* ---------------- Nút Zalo/Gọi nổi: ẩn khi đang xem phần đầu trang trên điện thoại để không che tiêu đề, giá và nút đăng ký ---------------- */
  (function () {
    var hero = $('#hero');
    if (!hero || !('IntersectionObserver' in window)) return;
    document.body.classList.add('at-hero');
    new IntersectionObserver(function (es) {
      document.body.classList.toggle('at-hero', es[0].isIntersecting && es[0].intersectionRatio > 0.35);
    }, { threshold: [0, 0.35, 0.6, 1] }).observe(hero);
  })();

  /* ---------------- So sánh: trên điện thoại chỉ hiện VF Wild + 1 xe chọn bằng nút ---------------- */
  $$('.cmp').forEach(function (box) {
    $$('[data-cmp]', box).forEach(function (b) {
      b.addEventListener('click', function () {
        var id = b.getAttribute('data-cmp');
        $$('[data-cmp]', box).forEach(function (x) { var on = x === b; x.classList.toggle('is-active', on); x.setAttribute('aria-pressed', String(on)); });
        $$('[data-m]', box).forEach(function (c) { if (!c.classList.contains('compare__win')) c.classList.toggle('is-on', c.getAttribute('data-m') === id); });
        track('compare_select', { model: id });
      });
    });
  });

  // Nút CTA chọn sẵn nhu cầu cho form cuối trang
  $$('[data-set-intent]').forEach(function (a) {
    a.addEventListener('click', function () {
      var target = $('#dang-ky [data-intent]') || $('#dang-ky input[name="intent"]');
      if (target) { target.value = a.getAttribute('data-set-intent'); target.dispatchEvent(new Event('change')); }
    });
  });

  /* ---------------- Thư viện ảnh: bấm ảnh → xem cỡ lớn, vuốt hoặc phím mũi tên để chuyển ảnh ---------------- */
  (function gallery() {
    var links = $$('.gallery [data-lb]');
    if (!links.length || typeof HTMLDialogElement !== 'function') return;   // trình duyệt cũ: mở thẳng file ảnh
    var lb = document.createElement('dialog');
    lb.className = 'lb';
    lb.setAttribute('aria-label', 'Xem ảnh VF Wild cỡ lớn');
    lb.innerHTML = '<figure class="lb__fig"><img class="lb__img" alt=""></figure><p class="lb__cap" aria-live="polite"></p>' +
      '<button type="button" class="lb__btn lb__prev" aria-label="Ảnh trước">‹</button>' +
      '<button type="button" class="lb__btn lb__next" aria-label="Ảnh sau">›</button>' +
      '<button type="button" class="lb__close" aria-label="Đóng">×</button>';
    document.body.appendChild(lb);
    wireDialog(lb);
    var img = $('.lb__img', lb), cap = $('.lb__cap', lb), cur = 0, opener = null;
    img.addEventListener('load', function () { img.classList.remove('is-loading'); });
    function show(i) {
      cur = (i + links.length) % links.length;
      var a = links[cur], thumb = $('img', a);
      if (img.getAttribute('src') !== a.getAttribute('href')) { img.classList.add('is-loading'); img.src = a.getAttribute('href'); }
      img.alt = thumb ? thumb.alt : '';
      cap.innerHTML = '<b>' + (cur + 1) + '/' + links.length + '</b> ' + esc(a.getAttribute('data-cap') || '');
      [cur + 1, cur - 1].forEach(function (j) { (new Image()).src = links[(j + links.length) % links.length].getAttribute('href'); });
    }
    links.forEach(function (a, i) {
      a.addEventListener('click', function (e) {
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.button > 0) return;
        e.preventDefault(); opener = a; show(i); openDialog(lb);
        track('gallery_open', { photo: i + 1 });
      });
    });
    $('.lb__prev', lb).addEventListener('click', function () { show(cur - 1); });
    $('.lb__next', lb).addEventListener('click', function () { show(cur + 1); });
    $('.lb__close', lb).addEventListener('click', function () { closeDialog(lb); });
    lb.addEventListener('click', function (e) { if (e.target.classList.contains('lb__fig')) closeDialog(lb); });
    lb.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { e.preventDefault(); show(cur + 1); }
      else if (e.key === 'ArrowLeft') { e.preventDefault(); show(cur - 1); }
    });
    var x0 = null, y0 = 0;
    lb.addEventListener('touchstart', function (e) { x0 = e.touches.length === 1 ? e.touches[0].clientX : null; if (x0 !== null) y0 = e.touches[0].clientY; }, { passive: true });
    lb.addEventListener('touchend', function (e) {
      if (x0 === null || (window.visualViewport && window.visualViewport.scale > 1.01)) { x0 = null; return; }   // đang phóng to bằng 2 ngón: không chuyển ảnh
      var dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0; x0 = null;
      if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.3) show(cur + (dx < 0 ? 1 : -1));
    }, { passive: true });
    lb.addEventListener('close', function () { if (opener) { try { opener.focus({ preventScroll: true }); } catch (e) {} } });
  })();

  /* ---------------- Thanh liên hệ mobile: ẩn khi bàn phím mở ---------------- */
  document.addEventListener('focusin', function (e) { if (e.target.matches('input,select,textarea')) document.body.classList.add('kb-open'); });
  document.addEventListener('focusout', function () { document.body.classList.remove('kb-open'); });

  /* ---------------- Theo dõi bấm gọi / Zalo ---------------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href^="tel:"],a[href*="zalo.me"]');
    if (!a) return;
    track(a.href.indexOf('tel:') === 0 ? 'click_call' : 'click_zalo', { placement: a.getAttribute('data-placement') || '' });
  });

  /* ---------------- Trang cảm ơn ---------------- */
  var ty = $('[data-thanks]');
  if (ty && urlQ) {
    var map = { 'bao-gia': 'báo giá lăn bánh', 'lai-thu': 'đăng ký lái thử', 'tra-gop': 'tư vấn trả góp', 'o2o': 'tìm hiểu cọc online (O2O)', 'uu-dai': 'kiểm tra ưu đãi', 'tu-van': 'tư vấn' };
    var y = urlQ.get('y'); if (map[y]) ty.textContent = map[y];
  }

  /* ---------------- Mã đo lường (GTM, GA4, Google Ads, Meta Pixel): cài ở trang quản trị /quan-tri/, web đọc qua /tracking.php.
     Chỉ tải sau khi khách bấm "Đồng ý" ở hộp cookie. ---------------- */
  (function consent() {
    var box = $('#cookie'), root = document.documentElement;
    function ask() {
      if (!box) return;
      box.hidden = false; void box.offsetWidth; box.classList.add('is-shown');
      root.style.scrollPaddingTop = (box.offsetHeight + 24) + 'px';   // bấm link neo (#...) thì nội dung không bị banner che
    }
    function done() {
      box.classList.remove('is-shown'); root.style.scrollPaddingTop = '';
      setTimeout(function () { box.hidden = true; }, REDUCED ? 0 : 320);
    }
    function start(T) {
      T = T || {};
      var gtm = T.gtm_id, pixel = T.meta_pixel_id, ga4 = T.ga4_id, ads = T.gads_id;
      TRK.adsLabel = T.gads_lead_label || '';
      lSet('vfw_gtm', gtm || ''); lSet('vfw_ga4', ga4 || '');   // thẻ trong <head> chỉ chạy khi trùng mã đang bật ở trang quản trị
      var headGtm = window.__vfwGtm;   // GTM đã chạy từ <head> (khách đồng ý từ lần trước) → không tải lại
      if (!gtm && !pixel && !ga4 && !ads) return;   // chưa cài mã nào → không tải gì, không hỏi cookie
      var google = gtm || ga4 || ads;
      if (google && !headGtm && !window.__vfwGa4) gtag('consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied' });
      var loaded = false;
      function load() {
        if (loaded) return; loaded = true;
        if (google) gtag('consent', 'update', { ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted', analytics_storage: 'granted' });
        if (gtm && !headGtm) {
          window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
          var s = document.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(gtm); document.head.appendChild(s);
        }
        if (ga4 || ads) loadGtag(ga4, ads);
        if (pixel) loadMetaPixel(pixel);
      }
      var choice = lGet('vfw_consent');
      if (choice === 'grant') load(); else if (choice !== 'deny') ask();
      if (box) $$('[data-consent]', box).forEach(function (b) {
        b.addEventListener('click', function () {
          var v = b.getAttribute('data-consent');
          lSet('vfw_consent', v); done();
          if (v === 'grant') load(); else if (loaded) location.reload();   // rút lại đồng ý → tải lại trang, không còn tag nào chạy
        });
      });
      $$('[data-cookie-settings]').forEach(function (el) { el.addEventListener('click', function (e) { e.preventDefault(); ask(); }); });
    }
    // Bản chạy thật chỉ dùng mã từ /tracking.php (lỗi mạng → không tải mã nào). Bản nháp trên máy dùng mã trong site.json.
    var fallback = STAGING ? (SITE.tracking || {}) : {};
    if (!window.fetch) { start(fallback); return; }
    fetch('/tracking.php', { credentials: 'omit' })
      .then(function (r) { if (!r.ok) throw new Error('tracking ' + r.status); return r.json(); })
      .then(start, function () { start(fallback); });
  })();
})();

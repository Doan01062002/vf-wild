/* VF Wild – engine tính giá (hàm thuần, không đụng DOM).
   ladder(): bậc giá theo đúng thứ tự văn bản · onRoad(): lăn bánh 2 kịch bản · loan(): trả góp.
   Kiểm tra: 860.000.000 − 5% MSRP − 61.000.000 − 2% O2O trên phần còn lại = 740.880.000. */
(function (root) {
  'use strict';

  function ts(v) { return v ? Date.parse(v) : null; }

  function offerState(o, now) {
    var s = ts(o.start), e = ts(o.end);
    if (e !== null && now > e) return 'expired';
    if (s !== null && now < s) return 'upcoming';
    return 'active';
  }

  function eligible(o, ctx) {
    if (o.variants && o.variants.length && o.variants.indexOf(ctx.variant) < 0) return 'variant';
    if (o.channel && o.channel !== ctx.channel) return 'channel';
    if (o.requires && !(ctx.flags && ctx.flags[o.requires])) return 'requires';
    if (o.customer_types && o.customer_types.length && ctx.customerType && o.customer_types.indexOf(ctx.customerType) < 0) return 'customer';
    return '';
  }

  /* Ưu đãi cùng stage dùng chung một cơ sở tính (giá đầu stage); base 'list_price' luôn tính trên giá niêm yết.
     Ưu đãi 'upcoming' vẫn được tính (giá khi đặt cọc trong thời gian ưu đãi) và đánh dấu hasUpcoming. */
  function ladder(listPrice, offers, ctx, now) {
    var lines = [], info = [], subtotal = listPrice, hasUpcoming = false, unknownStack = false, applied = 0;
    var live = (offers || []).filter(function (o) { return offerState(o, now) !== 'expired'; });
    var stages = {};
    live.forEach(function (o) { var k = o.stage || 0; (stages[k] = stages[k] || []).push(o); });
    Object.keys(stages).map(Number).sort(function (a, b) { return a - b; }).forEach(function (k) {
      var stageBase = subtotal, cut = 0;
      stages[k].forEach(function (o) {
        var state = offerState(o, now);
        if (!o.show_amount || o.value == null || o.type === 'unknown') { info.push({ offer: o, state: state }); return; }
        var why = eligible(o, ctx);
        if (why) { lines.push({ offer: o, state: state, amount: 0, off: why }); return; }
        var base = o.base === 'running_subtotal' ? stageBase : listPrice;
        var amt = o.type === 'percent' ? Math.round(base * o.value / 100) : Number(o.value);
        if (o.cap) amt = Math.min(amt, o.cap);
        if (state === 'upcoming') hasUpcoming = true;
        if (o.stacking === 'unknown') unknownStack = true;
        cut += amt; applied += 1;
        lines.push({ offer: o, state: state, amount: amt, base: base, off: '' });
      });
      subtotal = stageBase - cut;
    });
    var total = (unknownStack && applied > 1) ? null : subtotal;
    return { list: listPrice, lines: lines, info: info, total: total, saving: total === null ? null : listPrice - total, hasUpcoming: hasUpcoming };
  }

  function pick(map, code) { return map ? (map[code] || map._default || null) : null; }

  /* Lăn bánh theo phân loại đăng ký (ctx.cls: 'con' = ô tô con pick-up, 'tai' = pick-up chở hàng cabin kép) và khu vực biển số.
     A = được miễn lệ phí trước bạ (nếu đăng ký trong thời hạn miễn); B = không được miễn: mức tỉnh × hệ số phân loại.
     Cơ sở tính trước bạ tạm lấy giá niêm yết (bảng giá tỉnh cho VF Wild chưa có). Khoản nào thiếu dữ liệu → null ("đang cập nhật"). */
  function onRoad(vehiclePrice, listPrice, ctx, fees) {
    var cls = ctx.cls || 'con', rows = [], other = 0;
    var zone = (fees.zone1 || []).indexOf(ctx.province) >= 0 ? 'zone1' : 'zone2';
    function add(id, label, amount, item) { rows.push({ id: id, label: label, amount: amount, item: item || {} }); if (amount !== null) other += amount; }
    var plate = fees.plate && fees.plate[cls] ? fees.plate[cls][zone] : null;
    add('plate', 'Phí cấp biển số (' + (zone === 'zone1' ? 'Hà Nội / TP.HCM' : 'tỉnh, thành khác') + ')', plate === undefined ? null : plate, fees.plate);
    var road = fees.road && fees.road[cls] != null ? fees.road[cls] : null;
    add('road', 'Phí sử dụng đường bộ (12 tháng)', road, fees.road);
    add('tnds', 'Bảo hiểm TNDS bắt buộc (1 năm)', fees.tnds ? fees.tnds.amount : null, fees.tnds);
    add('inspection', 'Phí đăng kiểm (ước tính)', fees.inspection ? fees.inspection.amount : null, fees.inspection);

    var rt = fees.registration_tax || {}, ex = rt.ev_exemption || {};
    var regTs = Date.parse((ctx.regMonth || '2026-12') + '-01T00:00:00+07:00');
    var untilTs = ex.until ? Date.parse(ex.until + 'T23:59:59+07:00') : null;
    var aOk = untilTs !== null && regTs <= untilTs;
    var r = pick(rt.rates, ctx.province);
    var rMin = r ? (r.rate != null ? r.rate : r.min) : null;
    var rMax = r ? (r.rate != null ? r.rate : r.max) : null;
    var ratio = rt.class_ratio && rt.class_ratio[cls] != null ? rt.class_ratio[cls] : null;
    var bMin = (rMin != null && ratio != null) ? Math.round(listPrice * rMin / 100 * ratio) : null;
    var bMax = (rMax != null && ratio != null) ? Math.round(listPrice * rMax / 100 * ratio) : null;
    var complete = rows.every(function (x) { return x.amount !== null; });
    return {
      vehicle: vehiclePrice, rows: rows, other: other, complete: complete, zone: zone, cls: cls,
      A: { ok: aOk, lptb: 0, total: vehiclePrice + other },
      B: { min: bMin, max: bMax, totalMin: bMin === null ? null : vehiclePrice + other + bMin, totalMax: bMax === null ? null : vehiclePrice + other + bMax, rMin: rMin, rMax: rMax, ratio: ratio },
      exemption: ex
    };
  }

  /* Trả góp: 'declining' = gốc chia đều, lãi trên dư nợ giảm dần; 'annuity' = trả đều (tính lại khi đổi lãi suất). */
  function loan(o) {
    var n = Math.max(1, Math.round(o.years * 12));
    var P = Math.round(o.price * (100 - o.downPct) / 100);
    var bal = P, rows = [], totalInt = 0, principalFixed = P / n;
    for (var m = 1; m <= n; m++) {
      var annual = m <= o.promoMonths ? o.ratePromo : o.rateAfter;
      var r = annual / 100 / 12, interest = bal * r, principal;
      if (o.method === 'annuity') {
        var left = n - m + 1;
        var pay = r ? bal * r / (1 - Math.pow(1 + r, -left)) : bal / left;
        principal = pay - interest;
      } else {
        principal = principalFixed;
      }
      principal = Math.min(principal, bal);
      bal = Math.max(0, bal - principal);
      totalInt += interest;
      rows.push({ m: m, principal: principal, interest: interest, pay: principal + interest, bal: bal, promo: m <= o.promoMonths });
    }
    var afterPromo = rows[Math.min(o.promoMonths, n - 1)];
    return { loan: P, down: o.price - P, months: n, rows: rows, first: rows[0].pay, firstAfterPromo: afterPromo ? afterPromo.pay : null, totalInterest: totalInt, totalPaid: P + totalInt };
  }

  function vnd(n) { return (n === null || n === undefined || isNaN(n)) ? '–' : Math.round(n).toLocaleString('vi-VN') + 'đ'; }
  function mil(n, digits) {
    if (n === null || n === undefined || isNaN(n)) return '–';
    var v = n / 1e6;
    return v.toLocaleString('vi-VN', { maximumFractionDigits: digits === undefined ? 2 : digits }) + ' triệu';
  }

  var api = { offerState: offerState, ladder: ladder, onRoad: onRoad, loan: loan, vnd: vnd, mil: mil };
  if (typeof module !== 'undefined' && module.exports) module.exports = api; else root.VFWEngine = api;
})(typeof window !== 'undefined' ? window : this);

// Chụp từng cảnh của video: trang quản trị thật (đã đăng nhập) hoặc trang minh họa, kèm khung đỏ đánh số + phụ đề.
// Dùng: node vshots.mjs [id,id,...]   (không có đối số = chụp tất cả)
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const HERE = path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1'));
const { base, scenes } = JSON.parse(fs.readFileSync(path.join(HERE, 'scenes.json'), 'utf8'));
// tài khoản admin của bản chạy thử local (private-local/TEST_LOGIN.txt), đặt qua biến môi trường để không ghi mật khẩu vào mã
const LOGIN_USER = process.env.VFW_TEST_USER || '';
const LOGIN_PASS = process.env.VFW_TEST_PASS || '';
if (!LOGIN_USER || !LOGIN_PASS) { console.error('Cần đặt VFW_TEST_USER và VFW_TEST_PASS (xem private-local/TEST_LOGIN.txt).'); process.exit(1); }
const only = process.argv[2] ? new Set(process.argv[2].split(',')) : null;
const OUT = path.join(HERE, 'frames');
fs.mkdirSync(OUT, { recursive: true });
const PORT = 9333 + Math.floor(Math.random() * 500);
const profile = path.join(HERE, '_profile-' + PORT);
const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${profile}`, '--no-first-run', '--disable-gpu', '--hide-scrollbars', '--allow-file-access-from-files', 'about:blank'], { stdio: 'ignore' });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function cdpTarget() {
  for (let i = 0; i < 60; i++) {
    try { const j = await (await fetch(`http://127.0.0.1:${PORT}/json`)).json(); const p = j.find((t) => t.type === 'page'); if (p) return p.webSocketDebuggerUrl; } catch {}
    await sleep(250);
  }
  throw new Error('Không kết nối được Chrome');
}
const ws = new WebSocket(await cdpTarget());
await new Promise((r) => (ws.onopen = r));
let id = 0; const pending = new Map();
ws.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && pending.has(d.id)) { pending.get(d.id)(d); pending.delete(d.id); } };
const send = (method, params = {}) => new Promise((res, rej) => { const i = ++id; pending.set(i, (d) => (d.error ? rej(new Error(method + ': ' + JSON.stringify(d.error))) : res(d.result))); ws.send(JSON.stringify({ id: i, method, params })); });
const evalJs = async (expr) => { const r = await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true }); if (r.exceptionDetails) throw new Error(JSON.stringify(r.exceptionDetails).slice(0, 300)); return r.result?.value; };

// ---- trang bên trong trình duyệt: tìm phần tử, vẽ khung, phụ đề
const PAGE_LIB = `
window.__fn = {
  sheetUrl: () => document.querySelector('input[value*="docs.google.com"]'),
  defaultHour: () => { const l = [...document.querySelectorAll('label')].find(x => x.textContent.includes('Giờ đăng mặc định')); return l && l.parentElement; },
  fieldset: () => document.querySelector('fieldset'),
  tiles: () => { const t = [...document.querySelectorAll('*')].find(e => e.children.length === 0 && e.textContent.trim() === 'Bài kế tiếp'); return t && t.parentElement.parentElement; },
  reviewCard: () => { const h = [...document.querySelectorAll('h2,h3')].find(x => x.textContent.includes('Bài AI vừa viết')); return h && h.parentElement; },
  rolesCard: () => { const h = [...document.querySelectorAll('h2')].find(x => x.textContent.trim() === 'Vai trò'); return h && h.parentElement; },
  addUserCard: () => { const h = [...document.querySelectorAll('h2')].find(x => x.textContent.trim() === 'Thêm người dùng'); return h && h.parentElement; },
  gtmField: () => { const l = [...document.querySelectorAll('label')].find(x => x.textContent.includes('Mã Google Tag Manager')); return l && l.parentElement; },
};
window.__find = (sel) => {
  if (sel.startsWith('fn:')) return [window.__fn[sel.slice(3)]()].filter(Boolean);
  if (sel.startsWith('parent:')) { const e = document.querySelector(sel.slice(7)); return e && e.parentElement ? [e.parentElement] : []; }
  if (sel.startsWith('all:')) return [...document.querySelectorAll(sel.slice(4))];
  if (sel.startsWith('text:')) { const t = sel.slice(5); const e = [...document.querySelectorAll('a,button,h1,h2,h3,label,span,b,summary,th')].find(x => x.textContent.trim().startsWith(t)); return e ? [e] : []; }
  const e = document.querySelector(sel); return e ? [e] : [];
};
window.__mark = (marks) => {
  const miss = [];
  for (const [sel, n, pos] of marks) {
    const els = window.__find(sel);
    if (!els.length) { miss.push(sel); continue; }
    let l = 1e9, t = 1e9, r = -1e9, b = -1e9;
    for (const e of els) { const q = e.getBoundingClientRect(); l = Math.min(l, q.left); t = Math.min(t, q.top); r = Math.max(r, q.right); b = Math.max(b, q.bottom); }
    const box = document.createElement('div');
    Object.assign(box.style, { position: 'fixed', left: (l - 5) + 'px', top: (t - 5) + 'px', width: (r - l + 10) + 'px', height: (b - t + 10) + 'px', border: '3px solid #e5322d', borderRadius: '9px', pointerEvents: 'none', zIndex: 99998, boxSizing: 'border-box', boxShadow: '0 0 0 4px rgba(229,50,45,.18)' });
    const bd = document.createElement('div');
    bd.textContent = n;
    Object.assign(bd.style, { position: 'fixed', left: (pos === 'r' ? r - 14 : l - 20) + 'px', top: (t - 20) + 'px', width: '30px', height: '30px', borderRadius: '50%', background: '#e5322d', color: '#fff', font: '700 17px/30px system-ui,sans-serif', textAlign: 'center', zIndex: 99999, boxShadow: '0 2px 8px rgba(0,0,0,.35)' });
    document.body.append(box, bd);
  }
  return miss;
};
window.__caption = (tag, cap) => {
  if (tag) {
    const p = document.createElement('div'); p.textContent = tag;
    Object.assign(p.style, { position: 'fixed', left: '24px', bottom: '96px', background: '#0a7a4e', color: '#fff', font: '700 15px/1 system-ui,sans-serif', padding: '9px 16px', borderRadius: '999px', zIndex: 100000, boxShadow: '0 4px 14px rgba(0,0,0,.3)' });
    document.body.append(p);
  }
  const c = document.createElement('div'); c.textContent = cap;
  Object.assign(c.style, { position: 'fixed', left: '24px', right: '24px', bottom: '20px', background: 'rgba(10,28,20,.92)', color: '#fff', font: '600 20px/1.4 "Segoe UI",system-ui,sans-serif', padding: '14px 22px', borderRadius: '14px', zIndex: 100000, textAlign: 'center', boxShadow: '0 6px 20px rgba(0,0,0,.35)' });
  document.body.append(c);
};
window.__mask = () => {
  const reps = [[/[A-Za-z0-9._-]+@[A-Za-z0-9-]+\\.iam\\.gserviceaccount\\.com/g, 'robot-dang-bai@ten-du-an.iam.gserviceaccount.com'], [new RegExp('docs[.]google[.]com[/]spreadsheets[/]d[/][A-Za-z0-9_-]{15,}', 'g'), 'docs.google.com/spreadsheets/d/ma-so-sheet-cua-ban'], [/\\u2022{2,}[A-Za-z0-9]+/g, '\\u2022\\u2022\\u2022\\u2022\\u2022\\u2022']];
  const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let n;
  while ((n = w.nextNode())) for (const [re, to] of reps) { if (re.test(n.nodeValue)) { re.lastIndex = 0; n.nodeValue = n.nodeValue.replace(re, to); } re.lastIndex = 0; }
  document.querySelectorAll('input,textarea').forEach((e) => { for (const [re, to] of reps) { if (re.test(e.value)) { re.lastIndex = 0; e.value = e.value.replace(re, to); } re.lastIndex = 0; } });
  document.querySelectorAll('[data-copy]').forEach((e) => e.setAttribute('data-copy', 'robot-dang-bai@ten-du-an.iam.gserviceaccount.com'));
  document.querySelectorAll('.savebar').forEach((e) => { e.style.position = 'static'; });
};
`;

let state = null;
for (const s of scenes) {
  if (only && !only.has(s.id)) continue;
  const key = s.kind === 'html' ? 'html' : s.nologin ? 'anon' : 'auth';
  await send('Page.enable'); await send('Network.enable');
  if (key !== state) {
    await send('Network.clearBrowserCookies');
    if (key === 'auth') {
      await send('Page.navigate', { url: base + '/quan-tri/' }); await sleep(800);
      await evalJs(`(async () => { const r = await fetch('/quan-tri/', { method: 'POST', body: new URLSearchParams({ action: 'login', username: ${JSON.stringify(LOGIN_USER)}, password: ${JSON.stringify(LOGIN_PASS)} }), redirect: 'follow' }); await r.text(); })()`);
    }
    state = key;
  }
  await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 720, deviceScaleFactor: 1.5, mobile: false });
  const url = s.kind === 'html' ? pathToFileURL(path.join(HERE, s.html)).href : base + s.path;
  await send('Page.navigate', { url });
  await sleep(s.wait || (s.kind === 'html' ? 1200 : 1100));
  await evalJs(PAGE_LIB);
  await evalJs('window.__mask()');
  if (s.js) await evalJs(s.js);
  if (s.scroll) await evalJs(`document.querySelector(${JSON.stringify(s.scroll)})?.scrollIntoView({block:'start'}); window.scrollBy(0, ${s.scrollBy || 0})`);
  await sleep(350);
  let miss = [];
  if (s.marks) miss = await evalJs(`window.__mark(${JSON.stringify(s.marks)})`);
  await evalJs(`window.__caption(${JSON.stringify(s.tag || '')}, ${JSON.stringify(s.cap)})`);
  await sleep(150);
  const shot = await send('Page.captureScreenshot', { format: 'png' });
  fs.writeFileSync(path.join(OUT, s.id + '.png'), Buffer.from(shot.data, 'base64'));
  console.log('✔', s.id, miss && miss.length ? 'THIẾU: ' + miss.join(' | ') : '');
}
ws.close();
chrome.kill();
await sleep(600);
try { fs.rmSync(profile, { recursive: true, force: true }); } catch {}

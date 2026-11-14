'use strict';
// Runs the real public/assets/*.js files inside jsdom with controllable timers, fetch and Notification.
const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');

const UI_MS = [120, 200, 6000, 8000];
const ASSETS = path.join(__dirname, '..', '..', 'public', 'assets');
const src = (name) => fs.readFileSync(path.join(ASSETS, name), 'utf8');

/**
 * @param {object} o
 * @param {string} o.html       body HTML
 * @param {string} [o.url]
 * @param {string[]} [o.scripts] asset file names to run, in order, AFTER the DOM is ready
 */
function boot(o) {
  const dom = new JSDOM(`<!doctype html><html><head><title>Inbox</title></head>${o.html}</html>`, { url: o.url || 'https://mail.test/mail/inbox', runScripts: 'outside-only', pretendToBeVisual: true });
  const w = dom.window;
  const timers = []; let tid = 0; w.Math.random = () => 0.5; // jitter factor 1.0
  w.setTimeout = (fn, ms) => { const t = { id: ++tid, fn, ms }; timers.push(t); return t.id; };
  w.clearTimeout = (id) => { const i = timers.findIndex((t) => t.id === id); if (i >= 0) timers.splice(i, 1); };
  const calls = { fetch: [], notifications: [], alerts: [], prompts: [], exec: [] };
  const queue = (o.responses || []).slice();
  w.fetch = (url, opts) => {
    calls.fetch.push({ url: String(url), opts });
    const next = queue.length ? queue.shift() : { status: 200, body: {} };
    if (next instanceof Error) return Promise.reject(next);
    if (typeof next === 'function') return next(url);
    return Promise.resolve({ ok: next.status >= 200 && next.status < 300, status: next.status, json: () => Promise.resolve(next.body) });
  };
  w.alert = (m) => calls.alerts.push(m); w.prompt = () => (o.promptAnswer === undefined ? null : o.promptAnswer);
  w.focus = () => {};
  if (o.notification) {
    const perm = { value: o.notification.permission };
    w.Notification = function (title, opts) { calls.notifications.push({ title, opts, inst: this }); this.close = () => {}; };
    Object.defineProperty(w.Notification, 'permission', { get: () => perm.value });
    w.Notification.requestPermission = (cb) => { calls.requested = (calls.requested || 0) + 1; const p = o.notification.grant; perm.value = p; if (cb) cb(p); return Promise.resolve(p); };
  }
  w.document.execCommand = (cmd, ui, val) => { calls.exec.push([cmd, val]); return true; };
  if (o.hidden) { Object.defineProperty(w.document, 'hidden', { value: true, configurable: true }); }
  // Capture DOMContentLoaded handlers so they run exactly once, on demand (jsdom would otherwise also fire its own event a tick later).
  const domHandlers = []; const origAdd = w.document.addEventListener.bind(w.document);
  w.document.addEventListener = (type, fn, opts) => { if (type === 'DOMContentLoaded') { domHandlers.push(fn); } else { origAdd(type, fn, opts); } };
  const flush = () => new Promise((r) => setImmediate(r)).then(() => new Promise((r) => setImmediate(r)));
  const run = (name) => w.eval(src(name));
  const api = {
    w, d: w.document, timers, calls, flush, run,
    /** fire the next POLL timer (>= 30 s; short UI timers such as toast auto-dismiss are left alone) and let promises settle */
    async tick() { timers.sort((a, b) => a.ms - b.ms); const i = timers.findIndex((t) => !UI_MS.includes(t.ms)); const t = i >= 0 ? timers.splice(i, 1)[0] : null; if (t) t.fn(); await flush(); return t; },
    /** the scheduled polls (every timer that is not one of the fixed UI delays) */
    polls() { return timers.filter((t) => !UI_MS.includes(t.ms)); },
    /** run the short UI timers (toast auto-dismiss, suggestion debounce, blur delay, flash auto-dismiss) */
    async ui() { for (const t of timers.filter((x) => UI_MS.includes(x.ms))) { timers.splice(timers.indexOf(t), 1); t.fn(); } await flush(); },
    setHidden(v) { Object.defineProperty(w.document, 'hidden', { value: v, configurable: true }); w.document.dispatchEvent(new w.Event('visibilitychange')); },
    ready() { domHandlers.splice(0).forEach((fn) => fn.call(w.document, new w.Event('DOMContentLoaded'))); },
  };
  for (const s of o.scripts || []) run(s);
  if (o.domReady) api.ready();
  return api;
}

const SIDEBAR = `<nav id="sidebar"><a class="nav-item" data-slug="inbox" href="/mail/inbox"><span class="nav-label">Inbox</span><span class="nav-count">7</span></a>
<a class="nav-item" data-slug="spam" href="/mail/spam"><span class="nav-label">Spam</span></a><a class="nav-item" data-slug="trash" href="/mail/trash"><span class="nav-label">Trash</span></a></nav>`;
const NOTIFY_BODY = (attrs = 'data-notify data-inbox-total="37" data-inbox-unseen="7" data-path="inbox"') => `<body ${attrs}>${SIDEBAR}<button data-notify-toggle hidden aria-pressed="false"></button><main id="main"><p>list</p></main></body>`;

module.exports = { boot, SIDEBAR, NOTIFY_BODY };

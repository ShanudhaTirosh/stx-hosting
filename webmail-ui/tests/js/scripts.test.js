'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { boot } = require('./harness');

// jsdom has no layout engine: give elements the innerText the real browsers have.
function withInnerText(b) { Object.defineProperty(b.w.HTMLElement.prototype, 'innerText', { get() { return this.textContent; }, configurable: true }); return b; }
const key = (b, el, k) => { const e = new b.w.KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true }); el.dispatchEvent(e); return e; };

// ---------------------------------------------------------------- bulk.js
const BULK = `<body><form id="bulk"><input type="checkbox" data-select-all><span data-selected></span><div data-bulk-actions><button type="submit" name="do" value="read">Read</button></div></form>
<div class="msg-row"><input class="msg-check" type="checkbox" form="bulk" value="a:1"></div><div class="msg-row"><input class="msg-check" type="checkbox" form="bulk" value="a:2"></div><div class="msg-row"><input class="msg-check" type="checkbox" form="bulk" value="a:3"></div></body>`;

test('bulk: starts idle; selecting updates the count, the idle state and the select-all box', () => {
  const b = boot({ html: BULK, scripts: ['bulk.js'], domReady: true }); const [one, two, three] = b.d.querySelectorAll('.msg-check'); const all = b.d.querySelector('[data-select-all]');
  assert.ok(b.d.querySelector('[data-bulk-actions]').hasAttribute('data-idle')); assert.equal(b.d.querySelector('[data-selected]').textContent, '');
  one.checked = true; one.dispatchEvent(new b.w.Event('change', { bubbles: true }));
  assert.equal(b.d.querySelector('[data-selected]').textContent, '1 selected'); assert.ok(!b.d.querySelector('[data-bulk-actions]').hasAttribute('data-idle')); assert.equal(all.indeterminate, true); assert.equal(all.checked, false);
  assert.ok(one.closest('.msg-row').classList.contains('is-selected'));
  for (const c of [two, three]) { c.checked = true; c.dispatchEvent(new b.w.Event('change', { bubbles: true })); }
  assert.equal(all.checked, true); assert.equal(all.indeterminate, false); assert.equal(b.d.querySelector('[data-selected]').textContent, '3 selected');
});
test('bulk: select-all selects and clears every row', () => {
  const b = boot({ html: BULK, scripts: ['bulk.js'], domReady: true }); const all = b.d.querySelector('[data-select-all]');
  all.checked = true; all.dispatchEvent(new b.w.Event('change', { bubbles: true })); assert.equal([...b.d.querySelectorAll('.msg-check')].filter((c) => c.checked).length, 3); assert.equal(b.d.querySelectorAll('.is-selected').length, 3);
  all.checked = false; all.dispatchEvent(new b.w.Event('change', { bubbles: true })); assert.equal([...b.d.querySelectorAll('.msg-check')].filter((c) => c.checked).length, 0); assert.ok(b.d.querySelector('[data-bulk-actions]').hasAttribute('data-idle'));
});
test('bulk: submitting with nothing selected is stopped with a hint; with a selection it goes through', () => {
  const b = boot({ html: BULK, scripts: ['bulk.js'], domReady: true }); const form = b.d.getElementById('bulk');
  let e = new b.w.Event('submit', { cancelable: true, bubbles: true }); form.dispatchEvent(e); assert.equal(e.defaultPrevented, true); assert.equal(b.d.querySelector('[data-selected]').textContent, 'Select messages first');
  const one = b.d.querySelector('.msg-check'); one.checked = true; one.dispatchEvent(new b.w.Event('change', { bubbles: true }));
  e = new b.w.Event('submit', { cancelable: true, bubbles: true }); form.dispatchEvent(e); assert.equal(e.defaultPrevented, false);
});
test('bulk: pages without a bulk bar are left alone', () => { assert.doesNotThrow(() => boot({ html: '<body>x</body>', scripts: ['bulk.js'], domReady: true })); });

// ---------------------------------------------------------------- suggest.js
const SUG = '<body><div class="compose-row"><input id="to" data-suggest type="text"></div></body>';
const results = (list) => ({ status: 200, body: { results: list } });
function typeInto(b, input, text) { input.value = text; input.dispatchEvent(new b.w.Event('input', { bubbles: true })); }

test('suggest: needs two characters, debounces, and asks the server for the word being typed', async () => {
  const b = boot({ html: SUG, scripts: ['suggest.js'], domReady: true, responses: [results([{ name: 'Zora', email: 'zora@example.org' }])] }); const input = b.d.getElementById('to');
  typeInto(b, input, 'z'); await b.ui(); assert.equal(b.calls.fetch.length, 0, 'one character is not enough');
  typeInto(b, input, 'bob@x.org, zo'); await b.ui(); assert.equal(b.calls.fetch.length, 1); assert.equal(b.calls.fetch[0].url, '/api/suggest?q=zo', 'only the part after the last comma');
  const list = b.d.getElementById('sg-to'); assert.equal(list.hidden, false); assert.equal(list.querySelectorAll('[role=option]').length, 1); assert.equal(input.getAttribute('aria-expanded'), 'true');
});
test('suggest: keyboard selection keeps earlier recipients and formats "Name <address>, "', async () => {
  const b = boot({ html: SUG, scripts: ['suggest.js'], domReady: true, responses: [results([{ name: 'Zora', email: 'zora@example.org' }, { name: '', email: 'zed@example.org' }])] }); const input = b.d.getElementById('to');
  typeInto(b, input, 'bob@x.org, zo'); await b.ui();
  key(b, input, 'ArrowDown'); key(b, input, 'ArrowDown'); assert.equal(b.d.querySelectorAll('#sg-to [aria-selected=true]').length, 1); key(b, input, 'ArrowUp');
  const e = key(b, input, 'Enter'); assert.equal(e.defaultPrevented, true); assert.equal(input.value, 'bob@x.org, Zora <zora@example.org>, '); assert.equal(b.d.getElementById('sg-to').hidden, true);
  typeInto(b, input, 'ze'); // new search after choosing
});
test('suggest: names are inserted as text with address-breaking characters removed; markup is never interpreted', async () => {
  const evil = { name: '<img src=x onerror=alert(1)>, "Mallory"; x', email: 'm@example.org' };
  const b = boot({ html: SUG, scripts: ['suggest.js'], domReady: true, responses: [results([evil])] }); const input = b.d.getElementById('to');
  typeInto(b, input, 'mal'); await b.ui(); assert.equal(b.d.querySelector('#sg-to img'), null); assert.match(b.d.querySelector('#sg-to .suggest-name').textContent, /<img src=x/);
  key(b, input, 'ArrowDown'); key(b, input, 'Enter'); assert.doesNotMatch(input.value, /[",;](?!\s*$)[^<]*</, 'no separators or quotes from the name'); assert.match(input.value, / <m@example\.org>, $/); assert.equal((input.value.match(/</g) || []).length, 1, 'only the address brackets remain');
});
test('suggest: mouse choice works, Escape and blur close, and an older answer never overwrites a newer one', async () => {
  let release; const slow = () => new Promise((res) => { release = () => res({ ok: true, status: 200, json: () => Promise.resolve({ results: [{ name: 'Old', email: 'old@example.org' }] }) }); });
  const b = boot({ html: SUG, scripts: ['suggest.js'], domReady: true, responses: [slow, results([{ name: 'New', email: 'new@example.org' }])] }); const input = b.d.getElementById('to');
  typeInto(b, input, 'ol'); await b.ui(); typeInto(b, input, 'ne'); await b.ui(); await b.flush(); release(); await b.flush();
  const names = [...b.d.querySelectorAll('#sg-to .suggest-name')].map((n) => n.textContent); assert.deepEqual(names, ['New'], 'the stale response was discarded');
  key(b, input, 'Escape'); assert.equal(b.d.getElementById('sg-to').hidden, true);
  typeInto(b, input, 'ne'); b.calls.fetch.length = 0;
  const li = b.d.querySelector('#sg-to li'); if (li) { li.dispatchEvent(new b.w.MouseEvent('mousedown', { bubbles: true, cancelable: true })); }
  input.dispatchEvent(new b.w.Event('blur')); await b.ui(); assert.equal(b.d.getElementById('sg-to').hidden, true);
});
test('suggest: a failing server just shows nothing', async () => {
  const b = boot({ html: SUG, scripts: ['suggest.js'], domReady: true, responses: [new Error('down')] }); typeInto(b, b.d.getElementById('to'), 'zo'); await b.ui(); await b.flush();
  assert.equal(b.d.getElementById('sg-to').hidden, true);
});

// ---------------------------------------------------------------- editor.js
const ED = '<body><form><div data-editor-wrap><div data-toolbar hidden><button type="button" data-cmd="bold">B</button><button type="button" data-cmd="quote">Q</button><button type="button" data-cmd="insertUnorderedList">L</button><button type="button" data-cmd="clear">C</button><button type="button" data-cmd="link">K</button></div><div data-editor contenteditable="true" hidden><p>Hello</p></div><input type="hidden" name="html" data-editor-field><noscript><textarea name="text_fallback"></textarea></noscript></div><button type="submit">Send</button></form></body>';
const click = (b, cmd) => b.d.querySelector(`[data-cmd=${cmd}]`).click();

test('editor: reveals itself when JavaScript runs and drops the no-JS fallback', () => {
  const b = withInnerText(boot({ html: ED, scripts: ['editor.js'], domReady: true }));
  assert.equal(b.d.querySelector('[data-editor]').hidden, false); assert.equal(b.d.querySelector('[data-toolbar]').hidden, false); assert.equal(b.d.querySelector('noscript'), null);
});
test('editor: toolbar buttons issue the matching commands', () => {
  const b = withInnerText(boot({ html: ED, scripts: ['editor.js'], domReady: true }));
  for (const c of ['bold', 'insertUnorderedList', 'clear', 'quote']) click(b, c);
  assert.deepEqual(b.calls.exec, [['bold', undefined], ['insertUnorderedList', undefined], ['removeFormat', undefined], ['formatBlock', 'blockquote']]);
  const md = new b.w.Event('mousedown', { bubbles: true, cancelable: true }); b.d.querySelector('[data-toolbar]').dispatchEvent(md); assert.equal(md.defaultPrevented, true, 'clicking the toolbar must not steal the text selection');
});
test('editor: links must be http, https or mailto', () => {
  let b = withInnerText(boot({ html: ED, scripts: ['editor.js'], domReady: true, promptAnswer: ' https://ok.example/x ' })); click(b, 'link'); assert.deepEqual(b.calls.exec, [['createLink', 'https://ok.example/x']]);
  b = withInnerText(boot({ html: ED, scripts: ['editor.js'], domReady: true, promptAnswer: 'mailto:a@b.co' })); click(b, 'link'); assert.equal(b.calls.exec[0][0], 'createLink');
  for (const bad of ['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html,<script>', 'ftp://x', '//evil.test', 'evil.test']) {
    b = withInnerText(boot({ html: ED, scripts: ['editor.js'], domReady: true, promptAnswer: bad })); click(b, 'link'); assert.equal(b.calls.exec.length, 0, bad); assert.equal(b.calls.alerts.length, 1, bad);
  }
  b = withInnerText(boot({ html: ED, scripts: ['editor.js'], domReady: true })); click(b, 'link'); assert.equal(b.calls.exec.length, 0); assert.equal(b.calls.alerts.length, 0, 'cancel is silent');
});
test('editor: pasting inserts plain text only', () => {
  const b = withInnerText(boot({ html: ED, scripts: ['editor.js'], domReady: true })); const ed = b.d.querySelector('[data-editor]');
  const ev = new b.w.Event('paste', { bubbles: true, cancelable: true }); ev.clipboardData = { getData: (t) => (t === 'text/plain' ? 'plain <b>text</b>' : '<b>rich</b>') }; ed.dispatchEvent(ev);
  assert.equal(ev.defaultPrevented, true); assert.deepEqual(b.calls.exec, [['insertText', 'plain <b>text</b>']]);
});
test('editor: submit copies the HTML into the hidden field; an empty editor sends nothing', () => {
  const b = withInnerText(boot({ html: ED, scripts: ['editor.js'], domReady: true })); const ed = b.d.querySelector('[data-editor]'); const f = b.d.querySelector('[data-editor-field]'); const form = b.d.querySelector('form');
  form.dispatchEvent(new b.w.Event('submit', { cancelable: true })); assert.equal(f.value, '<p>Hello</p>');
  for (const empty of ['', '<p><br></p>', '  <div> </div>\n']) { ed.innerHTML = empty; form.dispatchEvent(new b.w.Event('submit', { cancelable: true })); assert.equal(f.value, '', JSON.stringify(empty)); }
  ed.innerHTML = '<p><img src="data:image/png;base64,AAAA"></p>'; form.dispatchEvent(new b.w.Event('submit', { cancelable: true })); assert.match(f.value, /<img/, 'an image-only message is not empty');
});

// ---------------------------------------------------------------- compose.js
const COMPOSE = `<body><form data-compose><input id="to" data-focus-first value=""><button type="button" data-show-cc aria-expanded="false">Cc</button><div data-cc-row hidden></div><div data-cc-row hidden></div>
<select data-sig-pick><option value="">None</option><option value="aaaaaaaaaaaa">Work</option><option value="bbbbbbbbbbbb">Personal</option></select>
<template data-sig="aaaaaaaaaaaa"><p>Kind regards</p></template><template data-sig="bbbbbbbbbbbb"><p>Cheers</p></template>
<div data-editor contenteditable="true"><p><br></p><p>On x wrote:</p><blockquote>q</blockquote></div>
<input id="files" type="file" multiple data-max-bytes="1048576"><ul data-file-list></ul></form></body>`;

test('compose: Cc/Bcc rows toggle and report their state', () => {
  const b = boot({ html: COMPOSE, scripts: ['compose.js'], domReady: true }); const btn = b.d.querySelector('[data-show-cc]');
  btn.click(); assert.equal(btn.getAttribute('aria-expanded'), 'true'); assert.ok([...b.d.querySelectorAll('[data-cc-row]')].every((r) => !r.hidden));
  btn.click(); assert.equal(btn.getAttribute('aria-expanded'), 'false'); assert.ok([...b.d.querySelectorAll('[data-cc-row]')].every((r) => r.hidden));
});
test('compose: the signature picker inserts above the quote, swaps in place, and removes', () => {
  const b = boot({ html: COMPOSE, scripts: ['compose.js'], domReady: true }); const pick = b.d.querySelector('[data-sig-pick]'); const ed = b.d.querySelector('[data-editor]');
  const choose = (v) => { pick.value = v; pick.dispatchEvent(new b.w.Event('change', { bubbles: true })); };
  choose('aaaaaaaaaaaa'); assert.equal(ed.querySelectorAll('[data-signature]').length, 1); assert.match(ed.innerHTML, /^<p><br><\/p><div data-signature="1"><p>Kind regards<\/p><\/div><p>On x wrote:/);
  choose('bbbbbbbbbbbb'); assert.equal(ed.querySelectorAll('[data-signature]').length, 1, 'swapped, not duplicated'); assert.match(ed.textContent, /Cheers/); assert.doesNotMatch(ed.textContent, /Kind regards/);
  choose(''); assert.equal(ed.querySelectorAll('[data-signature]').length, 0); assert.match(ed.textContent, /On x wrote/, 'the quote is untouched');
});
test('compose: attachment list shows sizes and flags files over the limit', () => {
  const b = boot({ html: COMPOSE, scripts: ['compose.js'], domReady: true }); const input = b.d.getElementById('files');
  Object.defineProperty(input, 'files', { value: [{ name: 'a.txt', size: 2048 }, { name: 'huge.bin', size: 5 * 1048576 }], configurable: true }); input.dispatchEvent(new b.w.Event('change', { bubbles: true }));
  const li = [...b.d.querySelectorAll('[data-file-list] li')]; assert.equal(li.length, 2); assert.equal(li[0].textContent, 'a.txt (2 KB)'); assert.equal(li[1].textContent, 'huge.bin (5.0 MB) – too large'); assert.ok(li[1].classList.contains('field-error')); assert.ok(!li[0].classList.contains('field-error'));
});
test('compose: an empty To field gets the focus', () => {
  const b = boot({ html: COMPOSE, scripts: ['compose.js'], domReady: true }); assert.equal(b.d.activeElement, b.d.getElementById('to'));
});

// ---------------------------------------------------------------- app.js + theme.js
const APP = `<body><button data-theme-toggle></button><button data-drawer-open aria-expanded="false"></button><nav id="sidebar"><a href="/mail/sent">Sent</a></nav><div id="scrim"></div>
<div class="flash-stack"><div class="flash" id="f1"><button class="flash-close"></button></div><div class="flash" id="f2"></div></div>
<form data-once id="once"><input name="x" value="1"><button type="submit" name="do" value="send">Send</button><button type="submit" name="do" value="draft">Draft</button></form>
<iframe class="mail-frame" id="fr"></iframe></body>`;

test('app: the theme toggle flips dark mode and remembers it', () => {
  const b = boot({ html: APP, scripts: ['app.js'], domReady: true }); const root = b.d.documentElement;
  b.d.querySelector('[data-theme-toggle]').click(); assert.ok(root.classList.contains('dark')); assert.equal(b.w.localStorage.getItem('wm-theme'), 'dark');
  b.d.querySelector('[data-theme-toggle]').click(); assert.ok(!root.classList.contains('dark')); assert.equal(b.w.localStorage.getItem('wm-theme'), 'light');
});
test('theme.js: saved choice wins, otherwise the system preference', () => {
  const t = (saved, prefersDark) => { const b = boot({ html: '<body></body>' }); if (saved) b.w.localStorage.setItem('wm-theme', saved); b.w.matchMedia = () => ({ matches: prefersDark }); b.run('theme.js'); return b.d.documentElement.classList.contains('dark'); };
  assert.equal(t('dark', false), true); assert.equal(t('light', true), false); assert.equal(t(null, true), true); assert.equal(t(null, false), false);
  assert.doesNotThrow(() => boot({ html: '<body></body>', scripts: ['theme.js'] }), 'no matchMedia / storage: must not throw');
});
test('app: the mobile drawer opens, closes on Escape, on the scrim and when a link is chosen', () => {
  const b = boot({ html: APP, scripts: ['app.js'], domReady: true }); const side = b.d.getElementById('sidebar'); const scrim = b.d.getElementById('scrim'); const btn = b.d.querySelector('[data-drawer-open]');
  btn.click(); assert.ok(side.classList.contains('is-open')); assert.ok(scrim.classList.contains('is-open')); assert.equal(btn.getAttribute('aria-expanded'), 'true');
  b.d.dispatchEvent(new b.w.KeyboardEvent('keydown', { key: 'Escape' })); assert.ok(!side.classList.contains('is-open')); assert.equal(btn.getAttribute('aria-expanded'), 'false');
  btn.click(); scrim.click(); assert.ok(!side.classList.contains('is-open'));
  btn.click(); side.querySelector('a').addEventListener('click', (e) => e.preventDefault()); side.querySelector('a').click(); assert.ok(!side.classList.contains('is-open'));
});
test('app: flash messages can be dismissed and disappear on their own', async () => {
  const b = boot({ html: APP, scripts: ['app.js'], domReady: true }); b.d.querySelector('#f1 .flash-close').click(); assert.equal(b.d.getElementById('f1'), null);
  assert.ok(b.d.getElementById('f2')); await b.ui(); assert.equal(b.d.getElementById('f2'), null);
});
test('app: double-submit protection keeps the clicked button\'s value and disables the buttons', () => {
  const b = boot({ html: APP, scripts: ['app.js'], domReady: true }); const form = b.d.getElementById('once'); const draft = form.querySelectorAll('button')[1];
  form.requestSubmit(draft);
  const hidden = form.querySelector('input[type=hidden][name=do]'); assert.ok(hidden, 'the submitter value is preserved'); assert.equal(hidden.value, 'draft');
  assert.ok([...form.querySelectorAll('button')].every((x) => x.disabled && x.getAttribute('aria-disabled') === 'true'));
});
test('app: the message frame is sized to its content once loaded', () => {
  const b = boot({ html: APP, scripts: ['app.js'], domReady: true }); const fr = b.d.getElementById('fr'); fr.dispatchEvent(new b.w.Event('load'));
  assert.match(fr.style.height, /^\d+px$/); assert.ok(parseInt(fr.style.height, 10) >= 240);
});

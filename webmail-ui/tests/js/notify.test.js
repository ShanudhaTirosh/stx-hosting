'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { boot, NOTIFY_BODY } = require('./harness');

const ok = (body) => ({ status: 200, body });

test('does nothing on pages without data-notify (e.g. the sign-in page)', async () => {
  const b = boot({ html: '<body><main>login</main></body>', scripts: ['notify.js'] });
  assert.equal(b.timers.length, 0); assert.equal(b.calls.fetch.length, 0); assert.equal(b.d.title, 'Inbox');
});

test('sets the unread count in the tab title and schedules the first poll about 45 seconds out', () => {
  const b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'] });
  assert.equal(b.d.title, '(7) Inbox'); assert.equal(b.polls().length, 1); assert.equal(b.polls()[0].ms, 45000);
});

test('polls with the last known totals, then updates title, folder badges (creating and removing them)', async () => {
  const b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'], responses: [ok({ inbox: { unseen: 3, total: 37 }, folders: { inbox: 3, spam: 2, trash: 9 }, new: [] }), ok({ inbox: { unseen: 0, total: 37 }, folders: { inbox: 0, spam: 0, trash: 9 }, new: [] })] });
  await b.tick();
  assert.equal(b.calls.fetch[0].url, '/api/notify?known=37&unseen=7'); assert.equal(b.calls.fetch[0].opts.credentials, 'same-origin');
  assert.equal(b.d.title, '(3) Inbox'); assert.equal(b.d.querySelector('[data-slug=inbox] .nav-count').textContent, '3');
  assert.equal(b.d.querySelector('[data-slug=spam] .nav-count').textContent, '2', 'a missing badge is created');
  assert.equal(b.d.querySelector('[data-slug=trash] .nav-count'), null, 'Trash never gets a badge, like the server');
  await b.tick(); assert.equal(b.calls.fetch[1].url, '/api/notify?known=37&unseen=3', 'the next poll reports the new baseline');
  assert.equal(b.d.title, 'Inbox'); assert.equal(b.d.querySelector('[data-slug=inbox] .nav-count'), null, 'zero removes the badge'); assert.equal(b.d.querySelector('[data-slug=spam] .nav-count'), null);
});

test('new mail: toast with a link, inbox refresh banner (once), nothing more', async () => {
  const n = { id: 50, mailbox: 'abc123', from: 'Eve', subject: 'Lunch?' };
  const b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'], responses: [ok({ inbox: { unseen: 8, total: 38 }, folders: { inbox: 8 }, new: [n] }), ok({ inbox: { unseen: 9, total: 39 }, folders: { inbox: 9 }, new: [n] })] });
  await b.tick();
  const toast = b.d.querySelector('[data-notify-toast]'); assert.ok(toast); assert.match(toast.textContent, /New mail from Eve: Lunch\?/); assert.equal(toast.querySelector('a').getAttribute('href'), '/mail/abc123/50');
  assert.equal(b.d.querySelectorAll('[data-new-banner]').length, 1); assert.equal(b.d.querySelector('[data-new-banner] a').getAttribute('href'), '/mail/inbox');
  await b.tick(); assert.equal(b.d.querySelectorAll('[data-new-banner]').length, 1, 'no duplicate banner');
  assert.equal(b.d.querySelectorAll('[data-notify-toast]').length, 2);
});

test('several new messages are summarised; the banner only appears on the inbox page', async () => {
  const two = [{ id: 2, mailbox: 'm', from: 'A', subject: 's2' }, { id: 1, mailbox: 'm', from: 'B', subject: 's1' }];
  const b = boot({ html: NOTIFY_BODY('data-notify data-inbox-total="37" data-inbox-unseen="7" data-path="settings-profile"'), scripts: ['notify.js'], responses: [ok({ inbox: { unseen: 9, total: 39 }, folders: {}, new: two })] });
  await b.tick(); assert.match(b.d.querySelector('[data-notify-toast]').textContent, /2 new messages — latest from A/); assert.equal(b.d.querySelector('[data-new-banner]'), null);
});

test('sender and subject are inserted as text, never as markup', async () => {
  const evil = { id: 1, mailbox: 'm', from: '<img src=x onerror=alert(1)>', subject: '<script>alert(2)</script>' };
  const b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'], responses: [ok({ inbox: { unseen: 8, total: 38 }, folders: {}, new: [evil] })] });
  await b.tick(); assert.equal(b.d.querySelector('[data-notify-toast] img'), null); assert.equal(b.d.querySelector('[data-notify-toast] script'), null); assert.match(b.d.querySelector('[data-notify-toast]').textContent, /<img src=x/);
});

test('a 401 stops polling for good and tells the user, linking to sign-in', async () => {
  const b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'], responses: [{ status: 401, body: { error: 'unauthenticated' } }] });
  await b.tick(); assert.equal(b.polls().length, 0, 'no further polls'); const t = b.d.querySelector('[data-notify-toast]'); assert.match(t.textContent, /session has expired/); assert.equal(t.querySelector('a').getAttribute('href'), '/login');
  const realNow = b.w.Date.now; b.w.Date.now = () => realNow() + 600000; const before = b.calls.fetch.length;
  try { b.setHidden(true); b.setHidden(false); await b.flush(); } finally { b.w.Date.now = realNow; }
  assert.equal(b.calls.fetch.length, before, 'returning to the tab after a sign-out must not poll again'); assert.equal(b.polls().length, 0);
});

test('failures back off 45s -> 90s -> 180s -> 300s (cap), and one success resets the pace', async () => {
  const b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'], responses: [new Error('x'), new Error('x'), { status: 503, body: {} }, new Error('x'), new Error('x'), ok({ inbox: { unseen: 7, total: 37 }, folders: {}, new: [] })] });
  const delays = [b.polls()[0].ms];
  for (let i = 0; i < 6; i++) { await b.tick(); delays.push(b.polls()[0].ms); }
  assert.deepEqual(delays, [45000, 90000, 180000, 300000, 300000, 300000, 45000]);
});

test('hidden tabs poll slowly; coming back polls at once unless it just polled', async () => {
  const b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'], responses: [ok({ inbox: { unseen: 7, total: 37 }, folders: {}, new: [] }), ok({ inbox: { unseen: 7, total: 37 }, folders: {}, new: [] })] });
  await b.tick(); assert.equal(b.polls()[0].ms, 45000);
  b.setHidden(true); await b.flush(); assert.equal(b.polls()[0].ms, 120000, 'slow rate while hidden');
  const before = b.calls.fetch.length; b.setHidden(false); await b.flush(); assert.equal(b.calls.fetch.length, before, 'polled moments ago: no immediate request');
  const realNow = b.w.Date.now; b.w.Date.now = () => realNow() + 20000; try { b.setHidden(true); b.setHidden(false); await b.flush(); } finally { b.w.Date.now = realNow; }
  assert.equal(b.calls.fetch.length, before + 1, 'after a pause the tab refreshes immediately');
});

test('only one request is in flight at a time', async () => {
  let release; const slow = () => new Promise((res) => { release = () => res({ ok: true, status: 200, json: () => Promise.resolve({ inbox: { unseen: 7, total: 37 }, folders: {}, new: [] }) }); });
  const b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'], responses: [slow] });
  const realNow = b.w.Date.now; b.w.Date.now = () => realNow() + 60000;
  try { await b.tick(); b.setHidden(true); b.setHidden(false); await b.flush(); assert.equal(b.calls.fetch.length, 1, 'the second trigger waits for the first request'); release(); await b.flush(); } finally { b.w.Date.now = realNow; }
});

test('desktop notifications: only when opted in, permitted and the tab is hidden', async () => {
  const n = [{ id: 5, mailbox: 'mb', from: 'Eve', subject: 'Lunch?' }];
  const resp = () => ok({ inbox: { unseen: 8, total: 38 }, folders: {}, new: n });
  // opted out
  let b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'], responses: [resp()], notification: { permission: 'granted' }, hidden: true }); await b.tick(); assert.equal(b.calls.notifications.length, 0, 'preference off');
  // opted in, hidden -> shown
  b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'], responses: [resp()], notification: { permission: 'granted' }, hidden: true }); b.w.localStorage.setItem('wm-desktop-notify', '1'); await b.tick();
  assert.equal(b.calls.notifications.length, 1); assert.equal(b.calls.notifications[0].title, 'New mail from Eve'); assert.equal(b.calls.notifications[0].opts.body, 'Lunch?');
  // opted in but visible -> toast only
  b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'], responses: [resp()], notification: { permission: 'granted' } }); b.w.localStorage.setItem('wm-desktop-notify', '1'); await b.tick(); assert.equal(b.calls.notifications.length, 0, 'visible tab uses the in-page toast');
  // permission denied -> nothing
  b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'], responses: [resp()], notification: { permission: 'denied' }, hidden: true }); b.w.localStorage.setItem('wm-desktop-notify', '1'); await b.tick(); assert.equal(b.calls.notifications.length, 0);
});

test('the bell opts in and out, asks permission once, and explains a refusal', async () => {
  let b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'], notification: { permission: 'default', grant: 'granted' } });
  const bell = b.d.querySelector('[data-notify-toggle]'); assert.equal(bell.hidden, false, 'shown when the browser supports notifications'); assert.equal(bell.getAttribute('aria-pressed'), 'false');
  bell.click(); await b.flush(); assert.equal(b.calls.requested, 1); assert.equal(b.w.localStorage.getItem('wm-desktop-notify'), '1'); assert.equal(bell.getAttribute('aria-pressed'), 'true'); assert.match(b.d.querySelector('[data-notify-toast]').textContent, /are on/);
  bell.click(); await b.flush(); assert.equal(b.w.localStorage.getItem('wm-desktop-notify'), '0'); assert.equal(bell.getAttribute('aria-pressed'), 'false'); assert.equal(b.calls.requested, 1, 'no second permission prompt');
  b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'], notification: { permission: 'default', grant: 'denied' } }); b.d.querySelector('[data-notify-toggle]').click(); await b.flush();
  assert.equal(b.w.localStorage.getItem('wm-desktop-notify'), '0'); assert.match(b.d.querySelector('[data-notify-toast]').textContent, /blocked/);
  b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'] }); assert.equal(b.d.querySelector('[data-notify-toggle]').hidden, true, 'stays hidden without the Notification API');
});

const MULTI = '<body data-notify data-multi data-inbox-total="37" data-inbox-unseen="7" data-path="inbox"><span data-others-total hidden></span><span data-account-badge="acc1" hidden></span><span data-account-badge="acc2" hidden></span><main id="main"></main></body>';

test('several accounts: the first poll comes quickly and the other accounts get unread badges plus a total', async () => {
  const b = boot({ html: MULTI, scripts: ['notify.js'], responses: [ok({ inbox: { unseen: 7, total: 37 }, folders: {}, others: { acc1: 3, acc2: 0 }, new: [] }), ok({ inbox: { unseen: 7, total: 37 }, folders: {}, others: {}, new: [] })] });
  assert.equal(b.polls()[0].ms, 3000, 'with several accounts the first look is soon, not after 45 seconds');
  await b.tick(); const one = b.d.querySelector('[data-account-badge=acc1]'); const two = b.d.querySelector('[data-account-badge=acc2]'); const total = b.d.querySelector('[data-others-total]');
  assert.equal(one.textContent, '3'); assert.equal(one.hidden, false); assert.equal(two.hidden, true, 'zero unread: no badge'); assert.equal(total.textContent, '3'); assert.equal(total.hidden, false); assert.match(total.getAttribute('title'), /3 unread in your other accounts/);
  assert.equal(b.polls()[0].ms, 45000, 'then the normal pace');
  await b.tick(); assert.equal(one.hidden, true, 'an account that is gone from the answer loses its badge'); assert.equal(total.hidden, true);
});
test('several accounts: a response without "others" (older server, one account) is harmless', async () => {
  const b = boot({ html: MULTI, scripts: ['notify.js'], responses: [ok({ inbox: { unseen: 7, total: 37 }, folders: {}, new: [] })] });
  await b.tick(); assert.equal(b.d.querySelector('[data-others-total]').hidden, true); assert.equal(b.d.title, '(7) Inbox');
});
test('a single account keeps the 45 second first poll', () => {
  const b = boot({ html: NOTIFY_BODY(), scripts: ['notify.js'] }); assert.equal(b.polls()[0].ms, 45000);
});

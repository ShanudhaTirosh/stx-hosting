// New-mail indicator: polls /api/notify, updates the tab title and sidebar counts, shows an in-page notice and,
// if the user opted in, a desktop notification. Dependency-free, CSP-safe (no eval, DOM text only via textContent).
(function () {
  'use strict';
  var body = document.body;
  if (!body || !body.hasAttribute('data-notify')) return;

  var VISIBLE_MS = 45000, HIDDEN_MS = 120000, MAX_BACKOFF_MS = 300000, MIN_GAP_MS = 15000;
  var state = {
    total: parseInt(body.getAttribute('data-inbox-total'), 10),
    unseen: parseInt(body.getAttribute('data-inbox-unseen'), 10),
    last: 0, fails: 0, timer: null, stopped: false, inflight: false
  };
  var baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
  var PREF = 'wm-desktop-notify';

  function pref() { try { return localStorage.getItem(PREF) === '1'; } catch (e) { return false; } }
  function setPref(on) { try { localStorage.setItem(PREF, on ? '1' : '0'); } catch (e) {} }

  function setTitle(n) { document.title = (n > 0 ? '(' + n + ') ' : '') + baseTitle; }
  setTitle(state.unseen || 0);

  function updateCounts(folders) {
    document.querySelectorAll('a.nav-item[data-slug]').forEach(function (a) {
      var n = folders[a.getAttribute('data-slug')];
      if (typeof n !== 'number') return;
      var span = a.querySelector('.nav-count');
      var skip = a.getAttribute('data-slug') === 'trash'; // the server never shows a Trash badge
      if (n > 0 && !skip) {
        if (!span) { span = document.createElement('span'); span.className = 'nav-count'; a.appendChild(span); }
        span.textContent = String(n);
      } else if (span) { span.remove(); }
    });
  }

  function updateOthers(others) {
    var total = 0;
    document.querySelectorAll('[data-account-badge]').forEach(function (el) {
      var n = others[el.getAttribute('data-account-badge')];
      if (typeof n !== 'number') { el.hidden = true; return; }
      total += n; el.textContent = String(n); el.hidden = n <= 0;
    });
    var t = document.querySelector('[data-others-total]');
    if (t) { t.textContent = String(total); t.hidden = total <= 0; t.setAttribute('title', total + ' unread in your other accounts'); }
  }

  function stack() {
    var s = document.querySelector('.flash-stack');
    if (!s) { s = document.createElement('div'); s.className = 'flash-stack'; s.setAttribute('role', 'status'); s.setAttribute('aria-live', 'polite'); document.body.appendChild(s); }
    return s;
  }
  function toast(text, href) {
    var box = document.createElement('div'); box.className = 'flash flash-info'; box.setAttribute('data-notify-toast', '');
    var msg = document.createElement('div'); msg.className = 'flash-body';
    if (href) { var a = document.createElement('a'); a.href = href; a.textContent = text; msg.appendChild(a); } else { msg.textContent = text; }
    var close = document.createElement('button'); close.type = 'button'; close.className = 'flash-close'; close.setAttribute('aria-label', 'Dismiss'); close.textContent = '×';
    close.addEventListener('click', function () { box.remove(); });
    box.appendChild(msg); box.appendChild(close);
    stack().appendChild(box);
    setTimeout(function () { box.remove(); }, 8000);
  }

  function inboxBanner() { // on the inbox list, offer a refresh instead of moving rows under the user's cursor
    if (body.getAttribute('data-path') !== 'inbox' || document.querySelector('[data-new-banner]')) return;
    var main = document.getElementById('main'); if (!main) return;
    var b = document.createElement('div'); b.className = 'notice'; b.setAttribute('data-new-banner', '');
    var a = document.createElement('a'); a.href = '/mail/inbox'; a.className = 'button'; a.textContent = 'Refresh';
    b.appendChild(document.createTextNode('New messages have arrived. ')); b.appendChild(a);
    main.insertBefore(b, main.firstChild);
  }

  function desktop(list) {
    if (!pref() || typeof Notification === 'undefined' || Notification.permission !== 'granted' || !document.hidden) return;
    var m = list[0];
    var n = new Notification(list.length > 1 ? list.length + ' new messages' : 'New mail from ' + m.from, { body: list.length > 1 ? m.from + ': ' + m.subject : m.subject, tag: 'wm-new-mail' });
    n.onclick = function () { try { window.focus(); } catch (e) {} window.location.href = '/mail/' + encodeURIComponent(m.mailbox) + '/' + encodeURIComponent(m.id); try { n.close(); } catch (e) {} };
  }

  function schedule(ms) {
    clearTimeout(state.timer);
    if (state.stopped) return;
    var jitter = ms * (0.9 + Math.random() * 0.2);
    state.timer = setTimeout(poll, jitter);
  }
  function nextDelay() {
    var base = document.hidden ? HIDDEN_MS : VISIBLE_MS;
    return Math.min(MAX_BACKOFF_MS, base * Math.pow(2, state.fails));
  }

  function poll() {
    if (state.stopped || state.inflight) return;
    state.inflight = true; state.last = Date.now();
    var q = isNaN(state.total) ? '' : '?known=' + state.total + '&unseen=' + (state.unseen || 0);
    fetch('/api/notify' + q, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
      .then(function (r) {
        if (r.status === 401) { state.stopped = true; toast('Your session has expired. Sign in again to keep receiving updates.', '/login'); return null; }
        if (!r.ok) throw new Error('http ' + r.status);
        return r.json();
      })
      .then(function (d) {
        state.inflight = false;
        if (!d) return;
        state.fails = 0;
        var inbox = d.inbox || {};
        if (typeof inbox.total === 'number') state.total = inbox.total;
        if (typeof inbox.unseen === 'number') { state.unseen = inbox.unseen; setTitle(inbox.unseen); }
        if (d.folders && typeof d.folders === 'object') updateCounts(d.folders);
        if (d.others && typeof d.others === 'object') updateOthers(d.others);
        if (Array.isArray(d.new) && d.new.length) {
          var m = d.new[0];
          toast(d.new.length > 1 ? d.new.length + ' new messages — latest from ' + m.from : 'New mail from ' + m.from + ': ' + m.subject, '/mail/' + encodeURIComponent(m.mailbox) + '/' + encodeURIComponent(m.id));
          inboxBanner(); desktop(d.new);
        }
        schedule(nextDelay());
      })
      .catch(function () { state.inflight = false; state.fails = Math.min(state.fails + 1, 6); schedule(nextDelay()); });
  }

  document.addEventListener('visibilitychange', function () {
    if (state.stopped) return;
    if (!document.hidden && Date.now() - state.last > MIN_GAP_MS) { clearTimeout(state.timer); poll(); } else { schedule(nextDelay()); }
  });

  // Opt-in desktop notifications (the bell button is hidden when the browser has no Notification API).
  var bell = document.querySelector('[data-notify-toggle]');
  if (bell && typeof Notification !== 'undefined') {
    bell.hidden = false;
    var sync = function () { bell.setAttribute('aria-pressed', pref() && Notification.permission === 'granted' ? 'true' : 'false'); };
    sync();
    bell.addEventListener('click', function () {
      if (pref() && Notification.permission === 'granted') { setPref(false); sync(); toast('Desktop notifications are off.'); return; }
      var done = function (p) {
        if (p === 'granted') { setPref(true); toast('Desktop notifications are on for this browser.'); } else { setPref(false); toast('Notifications are blocked. Allow them in your browser’s site settings to turn this on.'); }
        sync();
      };
      var r = Notification.requestPermission(done); if (r && typeof r.then === 'function') r.then(done);
    });
  }

  // With several accounts, look at the others soon after the page opens so their badges are not blank for 45 seconds.
  schedule(body.hasAttribute('data-multi') ? 3000 : VISIBLE_MS);
})();

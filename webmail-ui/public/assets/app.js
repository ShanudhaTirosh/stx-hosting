// Dependency-free and CSP-safe (no eval, no inline handlers).
document.addEventListener('DOMContentLoaded', function () {
  // Mobile drawer
  var side = document.getElementById('sidebar');
  var scrim = document.getElementById('scrim');
  function setDrawer(open) {
    if (!side || !scrim) return;
    side.classList.toggle('is-open', open);
    scrim.classList.toggle('is-open', open);
    document.querySelectorAll('[data-drawer-open]').forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
  }
  document.querySelectorAll('[data-drawer-open]').forEach(function (b) { b.addEventListener('click', function () { setDrawer(!side.classList.contains('is-open')); }); });
  if (scrim) scrim.addEventListener('click', function () { setDrawer(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setDrawer(false); });
  if (side) side.addEventListener('click', function (e) { if (e.target.closest('a')) setDrawer(false); });

  // Theme toggle
  document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var dark = !document.documentElement.classList.contains('dark');
      document.documentElement.classList.toggle('dark', dark);
      try { localStorage.setItem('wm-theme', dark ? 'dark' : 'light'); } catch (e) {}
    });
  });
  // Flash auto-dismiss + close
  document.querySelectorAll('.flash').forEach(function (f) {
    var t = setTimeout(function () { f.remove(); }, 6000);
    var c = f.querySelector('.flash-close');
    if (c) c.addEventListener('click', function () { clearTimeout(t); f.remove(); });
  });
  // Auto-size the sandboxed message frame to its content.
  document.querySelectorAll('iframe.mail-frame').forEach(function (fr) {
    function fit() {
      try {
        var d = fr.contentDocument;
        if (d && d.documentElement) fr.style.height = Math.max(240, d.documentElement.scrollHeight + 8) + 'px';
      } catch (e) {}
    }
    fr.addEventListener('load', function () { fit(); setTimeout(fit, 300); });
  });
  // Disable submit buttons after first submit (no double sends).
  document.querySelectorAll('form[data-once]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      var sub = e.submitter;
      if (sub && sub.name) { var h = document.createElement('input'); h.type = 'hidden'; h.name = sub.name; h.value = sub.value; f.appendChild(h); }
      f.querySelectorAll('button[type=submit]').forEach(function (b) { b.setAttribute('aria-disabled', 'true'); b.disabled = true; });
    });
  });
});

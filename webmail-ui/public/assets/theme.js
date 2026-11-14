// Runs synchronously in <head>: applies the saved/system theme before first paint (no flash).
(function () {
  try {
    var saved = localStorage.getItem('wm-theme');
    var dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.classList.toggle('dark', dark);
  } catch (e) {}
})();

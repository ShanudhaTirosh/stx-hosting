// Recipient suggestions for inputs marked data-suggest. CSP-safe; suggestion text is always set via textContent.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('input[data-suggest]').forEach(function (input) {
    var list = document.createElement('ul');
    list.className = 'suggest'; list.setAttribute('role', 'listbox'); list.hidden = true;
    list.id = 'sg-' + input.id;
    input.parentNode.style.position = 'relative';
    input.parentNode.insertBefore(list, input.nextSibling);
    input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', list.id); input.setAttribute('aria-expanded', 'false');

    var timer = null, seq = 0, items = [], active = -1;

    function token() { // the text after the last comma/semicolon is the one being typed
      var v = input.value, i = Math.max(v.lastIndexOf(','), v.lastIndexOf(';'));
      return { head: v.slice(0, i + 1), text: v.slice(i + 1).trim() };
    }
    function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; }
    function mark(i) {
      active = i;
      Array.prototype.forEach.call(list.children, function (li, n) { li.setAttribute('aria-selected', n === i ? 'true' : 'false'); });
    }
    function choose(i) {
      var it = items[i]; if (!it) return;
      var t = token();
      var shown = it.name ? it.name.replace(/[<>",;]/g, ' ').trim() + ' <' + it.email + '>' : it.email;
      input.value = (t.head ? t.head.trim() + ' ' : '') + shown + ', ';
      close(); input.focus();
    }
    function render() {
      list.textContent = '';
      items.forEach(function (it, n) {
        var li = document.createElement('li'); li.setAttribute('role', 'option'); li.setAttribute('aria-selected', 'false');
        var a = document.createElement('span'); a.className = 'suggest-name'; a.textContent = it.name || it.email;
        li.appendChild(a);
        if (it.name) { var b = document.createElement('span'); b.className = 'suggest-mail'; b.textContent = it.email; li.appendChild(b); }
        li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(n); });
        list.appendChild(li);
      });
      list.hidden = items.length === 0;
      input.setAttribute('aria-expanded', items.length ? 'true' : 'false');
      active = -1;
    }
    function fetchNow() {
      var q = token().text;
      if (q.length < 2) { items = []; render(); return; }
      var mine = ++seq;
      fetch('/api/suggest?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : { results: [] }; })
        .then(function (d) { if (mine !== seq) return; items = Array.isArray(d.results) ? d.results.slice(0, 8) : []; render(); })
        .catch(function () { if (mine === seq) { items = []; render(); } });
    }
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(fetchNow, 200); });
    input.addEventListener('keydown', function (e) {
      if (list.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); mark((active + 1) % items.length); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); mark((active - 1 + items.length) % items.length); }
      else if ((e.key === 'Enter' || e.key === 'Tab') && active >= 0) { e.preventDefault(); choose(active); }
      else if (e.key === 'Escape') { close(); }
    });
    input.addEventListener('blur', function () { setTimeout(close, 120); });
  });
});

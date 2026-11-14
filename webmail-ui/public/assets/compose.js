// Compose screen extras (the editor itself lives in editor.js): file list, Cc/Bcc toggle, focus, signature picker.
document.addEventListener('DOMContentLoaded', function () {
  var form = document.querySelector('form[data-compose]');
  if (!form) return;
  var editor = form.querySelector('[data-editor]');

  // Signature picker: swap the [data-signature] block inside the body.
  var pick = form.querySelector('[data-sig-pick]');
  if (pick) {
    pick.addEventListener('change', function () {
      var block = editor.querySelector('[data-signature]');
      var tpl = pick.value ? form.querySelector('template[data-sig="' + pick.value + '"]') : null;
      if (!tpl) { if (block) block.remove(); return; }
      if (!block) {
        block = document.createElement('div'); block.setAttribute('data-signature', '1');
        var first = editor.firstElementChild;
        if (first && first.tagName === 'P' && first.textContent.trim() === '') first.after(block); else editor.prepend(block);
      }
      block.innerHTML = tpl.innerHTML;
    });
  }

  // Cc / Bcc
  var ccBtn = form.querySelector('[data-show-cc]');
  ccBtn.addEventListener('click', function () {
    var open = ccBtn.getAttribute('aria-expanded') !== 'true';
    ccBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    form.querySelectorAll('[data-cc-row]').forEach(function (r) { r.hidden = !open; });
  });

  // Attachment list + client-side size hint (the server enforces the real limits)
  var input = document.getElementById('files');
  var list = form.querySelector('[data-file-list]');
  var max = parseInt(input.getAttribute('data-max-bytes'), 10) || 0;
  function size(n) { return n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }
  input.addEventListener('change', function () {
    list.textContent = '';
    Array.prototype.forEach.call(input.files, function (f) {
      var li = document.createElement('li');
      li.textContent = f.name + ' (' + size(f.size) + ')' + (max && f.size > max ? ' – too large' : '');
      if (max && f.size > max) li.className = 'field-error';
      list.appendChild(li);
    });
  });

  var first = form.querySelector('[data-focus-first]');
  if (first && !first.value) first.focus(); else if (first) editor.focus();
});

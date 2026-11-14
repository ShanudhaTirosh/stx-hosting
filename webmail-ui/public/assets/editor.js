// Rich-text editor (dependency-free, CSP-safe). Any <div data-editor-wrap> containing a toolbar,
// a [data-editor] contenteditable and an [data-editor-field] hidden input becomes an editor.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-editor-wrap]').forEach(function (wrap) {
    var editor = wrap.querySelector('[data-editor]');
    var toolbar = wrap.querySelector('[data-toolbar]');
    var field = wrap.querySelector('[data-editor-field]');
    var form = wrap.closest('form');
    if (!editor || !toolbar || !field || !form) return;

    editor.hidden = false; toolbar.hidden = false;
    var fallback = wrap.querySelector('noscript'); if (fallback) fallback.remove();

    function run(cmd) {
      editor.focus();
      if (cmd === 'quote') { document.execCommand('formatBlock', false, 'blockquote'); return; }
      if (cmd === 'clear') { document.execCommand('removeFormat'); return; }
      if (cmd === 'link') {
        var url = window.prompt('Link address (https://…)');
        if (!url) return;
        url = url.trim();
        if (!/^(https?:\/\/|mailto:)/i.test(url)) { window.alert('Only http, https and mailto links are allowed.'); return; }
        document.execCommand('createLink', false, url);
        return;
      }
      document.execCommand(cmd);
    }
    toolbar.addEventListener('mousedown', function (e) { e.preventDefault(); }); // keep the selection
    toolbar.addEventListener('click', function (e) { var b = e.target.closest('[data-cmd]'); if (b) run(b.getAttribute('data-cmd')); });
    // Paste as plain text: pasted web/Word HTML is a common source of broken markup and hidden content.
    editor.addEventListener('paste', function (e) {
      e.preventDefault();
      document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text/plain'));
    });
    form.addEventListener('submit', function () {
      field.value = editor.innerText.trim() === '' && !editor.querySelector('img') ? '' : editor.innerHTML;
    });
  });
});

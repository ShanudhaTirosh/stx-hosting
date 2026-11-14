// Message list selection: select-all, live count, and idle state for the bulk toolbar (works without JS too).
document.addEventListener('DOMContentLoaded', function () {
  var bar = document.getElementById('bulk');
  if (!bar) return;
  var boxes = Array.prototype.slice.call(document.querySelectorAll('input.msg-check'));
  var all = bar.querySelector('[data-select-all]');
  var count = bar.querySelector('[data-selected]');
  var actions = bar.querySelector('[data-bulk-actions]');

  function update() {
    var n = boxes.filter(function (b) { return b.checked; }).length;
    count.textContent = n ? n + ' selected' : '';
    if (n) actions.removeAttribute('data-idle'); else actions.setAttribute('data-idle', '');
    all.checked = n > 0 && n === boxes.length;
    all.indeterminate = n > 0 && n < boxes.length;
    boxes.forEach(function (b) { b.closest('.msg-row').classList.toggle('is-selected', b.checked); });
  }
  all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); update(); });
  boxes.forEach(function (b) { b.addEventListener('change', update); });
  update();

  // Don't submit an empty selection; tell the user instead (server also validates).
  bar.addEventListener('submit', function (e) {
    if (!boxes.some(function (b) { return b.checked; })) { e.preventDefault(); count.textContent = 'Select messages first'; }
  });
});

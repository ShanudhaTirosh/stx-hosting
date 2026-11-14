<?php /** @var \Webmail\WildDuck\Model\Label $label @var string $csrf */ ?>
<div class="page-head"><h1 class="page-title">Delete label</h1></div>
<div class="card"><div class="card-well">
  <p>Delete the label <span class="lbl lbl-<?= e($label->color) ?>"><?= e($label->name) ?></span>?</p>
  <p class="page-summary" style="margin:8px 0 16px">It will be removed from <?= e($label->total) ?> message<?= $label->total === 1 ? '' : 's' ?>. The messages themselves are not deleted.</p>
  <form method="post" action="/labels/<?= e($label->id) ?>/delete" data-once class="card-actions">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <a class="button" href="/labels">Cancel</a>
    <button class="button button-danger" type="submit">Delete label</button>
  </form>
</div></div>

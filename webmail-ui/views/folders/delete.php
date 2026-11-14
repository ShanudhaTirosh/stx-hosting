<?php /** @var \Webmail\WildDuck\Model\Mailbox $f @var string $csrf */ ?>
<div class="page-head"><h1 class="page-title">Delete folder</h1></div>
<div class="card"><div class="card-well">
  <p>Delete <strong><?= e($f->path) ?></strong>?</p>
  <p class="page-summary" style="margin:8px 0 16px">This folder holds <?= e($f->total) ?> message<?= $f->total === 1 ? '' : 's' ?>. <strong>Deleting the folder permanently deletes everything in it.</strong> This can’t be undone.</p>
  <form method="post" action="/folders/<?= e($f->id) ?>/delete" data-once class="card-actions">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <a class="button" href="/folders">Cancel</a>
    <button class="button button-danger" type="submit"><?= icon('trash-2') ?> Delete folder and messages</button>
  </form>
</div></div>

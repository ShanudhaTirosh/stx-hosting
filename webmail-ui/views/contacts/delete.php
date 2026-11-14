<?php /** @var array{id:string,name:string,email:string,note:string} $c @var string $csrf */ ?>
<div class="page-head"><h1 class="page-title">Delete contact</h1></div>
<div class="card"><div class="card-well">
  <p>Remove <strong><?= e($c['name'] !== '' ? $c['name'] . ' · ' : '') ?><?= e($c['email']) ?></strong> from your contacts?</p>
  <p class="page-summary" style="margin:8px 0 16px">Past messages are not affected.</p>
  <form method="post" action="/contacts/<?= e($c['id']) ?>/delete" data-once class="card-actions">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <a class="button" href="/contacts">Cancel</a><button class="button button-danger" type="submit">Delete contact</button>
  </form>
</div></div>

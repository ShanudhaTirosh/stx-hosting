<?php /** @var array{id:string,name:string,html:string} $s @var string $csrf */ ?>
<div class="page-head"><h1 class="page-title">Delete signature</h1></div>
<div class="card"><div class="card-well">
  <p>Delete the signature <strong><?= e($s['name']) ?></strong>?</p>
  <div class="sig-preview" style="margin:12px 0"><?= $s['html'] ?></div>
  <form method="post" action="/signatures/<?= e($s['id']) ?>/delete" data-once class="card-actions">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <a class="button" href="/signatures">Cancel</a><button class="button button-danger" type="submit">Delete signature</button>
  </form>
</div></div>

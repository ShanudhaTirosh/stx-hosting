<?php /** @var list<\Webmail\WildDuck\Model\Mailbox> $custom @var list<string> $errors @var list<\Webmail\WildDuck\Model\Mailbox> $allFolders @var string $csrf */ ?>
<div class="page-head"><div><h1 class="page-title">Folders</h1><p class="page-summary">Organise mail into your own folders. Use “/” to nest, e.g. Work/2026.</p></div></div>
<?php if ($errors): ?><div class="field-error" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <div class="card-header"><h2 class="card-title">New folder</h2></div>
  <div class="card-well">
    <form class="inline-fields" method="post" action="/folders" data-once>
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <label class="sr-only" for="new-folder">Folder name</label>
      <input class="input" id="new-folder" name="path" type="text" maxlength="200" placeholder="Folder name" required>
      <button class="button button-primary" type="submit">Create folder</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2 class="card-title">Your folders</h2></div>
  <div class="card-well is-flush">
  <?php if (!$custom): ?>
    <div class="empty"><div class="empty-icon"><?= icon('folder', 20) ?></div><h3 class="empty-title">No custom folders yet</h3><p class="empty-text">Create one above to start organising.</p></div>
  <?php else: foreach ($custom as $f): ?>
    <div class="manage-row">
      <span class="manage-name"><?= icon('folder') ?> <?= e($f->path) ?> <small><?= e($f->total) ?> message<?= $f->total === 1 ? '' : 's' ?></small></span>
      <form class="inline-fields" method="post" action="/folders/<?= e($f->id) ?>/rename" data-once>
        <input type="hidden" name="_token" value="<?= e($csrf) ?>">
        <label class="sr-only" for="rn-<?= e($f->id) ?>">Rename <?= e($f->path) ?></label>
        <input class="input" id="rn-<?= e($f->id) ?>" name="path" type="text" value="<?= e($f->path) ?>" maxlength="200" required>
        <button class="button" type="submit">Rename</button>
        <a class="button button-danger" href="/folders/<?= e($f->id) ?>/delete"><?= icon('trash-2') ?> Delete</a>
      </form>
    </div>
  <?php endforeach; endif; ?>
  </div>
</div>
<p class="page-summary" style="margin-top:12px">Inbox, Drafts, Sent, Archive, Spam and Trash are built in and can’t be renamed or deleted.</p>

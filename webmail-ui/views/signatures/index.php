<?php /** @var list<array{id:string,name:string,html:string}> $sigs @var ?string $default @var list<string> $errors @var bool $unavailable @var int $max @var string $csrf @var \Webmail\Core\View $view */ ?>
<div class="page-head"><div><h1 class="page-title">Signatures</h1><p class="page-summary">Added to new messages, replies and forwards. You can switch or remove it while writing.</p></div></div>
<?php if ($unavailable): ?><div class="field-error" role="alert">Signatures could not be loaded from the mail server right now. Try again in a moment.</div><?php endif; ?>
<?php if ($errors): ?><div class="field-error" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<?php if (count($sigs) < $max): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-header"><h2 class="card-title">New signature</h2></div>
  <div class="card-well is-flush">
    <form method="post" action="/signatures" data-once>
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <div class="compose-row"><label class="label" for="sig-name">Name</label><input class="input" id="sig-name" name="name" type="text" maxlength="60" placeholder="e.g. Work" required></div>
      <?= $view->render('partials.editor', ['html' => '', 'label' => 'Signature text']) ?>
      <div class="compose-actions"><label class="att"><input type="checkbox" name="make_default" value="1"> <span>Use for new messages</span></label><span class="topbar-spacer"></span><button class="button button-primary" type="submit">Add signature</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><h2 class="card-title">Your signatures</h2>
    <?php if ($sigs): ?>
    <form class="inline-fields" method="post" action="/signatures/default" data-once>
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <label class="sr-only" for="sig-default">Default signature</label>
      <select class="input input-sm" id="sig-default" name="id"><option value="">No default</option>
        <?php foreach ($sigs as $s): ?><option value="<?= e($s['id']) ?>"<?= $s['id'] === $default ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
      </select><button class="button" type="submit">Set default</button>
    </form>
    <?php endif; ?>
  </div>
  <div class="card-well is-flush">
  <?php if (!$sigs): ?>
    <div class="empty"><div class="empty-icon"><?= icon('pen-line', 20) ?></div><h3 class="empty-title">No signatures yet</h3><p class="empty-text">Create one above and it will be added to your messages automatically.</p></div>
  <?php else: foreach ($sigs as $s): ?>
    <div class="manage-row">
      <span class="manage-name"><?= icon('pen-line') ?> <?= e($s['name']) ?> <?php if ($s['id'] === $default): ?><span class="badge">Default</span><?php endif; ?></span>
      <span class="card-actions"><a class="button button-danger" href="/signatures/<?= e($s['id']) ?>/delete"><?= icon('trash-2') ?> Delete</a></span>
      <div class="sig-preview"><?= $s['html'] ?></div>
      <details class="manage-edit">
        <summary class="button">Edit</summary>
        <form method="post" action="/signatures/<?= e($s['id']) ?>/update" data-once>
          <input type="hidden" name="_token" value="<?= e($csrf) ?>">
          <div class="compose-row"><label class="label" for="sn-<?= e($s['id']) ?>">Name</label><input class="input" id="sn-<?= e($s['id']) ?>" name="name" type="text" maxlength="60" value="<?= e($s['name']) ?>" required></div>
          <?= $view->render('partials.editor', ['html' => $s['html'], 'label' => 'Signature text']) ?>
          <div class="compose-actions"><span class="topbar-spacer"></span><button class="button button-primary" type="submit">Save signature</button></div>
        </form>
      </details>
    </div>
  <?php endforeach; endif; ?>
  </div>
</div>

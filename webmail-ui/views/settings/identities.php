<?php /** @var list<\Webmail\WildDuck\Model\Identity> $identities @var list<string> $errors @var bool $unavailable @var string $csrf @var \Webmail\Core\View $view */ ?>
<div class="page-head"><div><h1 class="page-title">Settings</h1><p class="page-summary">Addresses you can send from. Choose one in the “From” menu when writing.</p></div></div>
<?= $view->render('partials.settings-tabs', ['active' => 'settings-identities']) ?>
<?php if ($unavailable): ?><div class="field-error" role="alert">Your addresses could not be loaded from the mail server right now.</div><?php endif; ?>
<?php if ($errors): ?><div class="field-error" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="card"><div class="card-header"><h2 class="card-title">Your addresses</h2></div>
  <div class="card-well is-flush">
  <?php if (!$identities): ?>
    <div class="empty"><div class="empty-icon"><?= icon('mail', 20) ?></div><h3 class="empty-title">No addresses to show</h3></div>
  <?php else: foreach ($identities as $i): ?>
    <div class="manage-row">
      <span class="manage-name"><?= icon('mail') ?> <?= e($i->address) ?> <?php if ($i->main): ?><span class="badge">Main</span><?php endif; ?></span>
      <form class="inline-fields" method="post" action="/settings/identities/<?= e($i->id) ?>" data-once>
        <input type="hidden" name="_token" value="<?= e($csrf) ?>">
        <label class="sr-only" for="in-<?= e($i->id) ?>">Sender name for <?= e($i->address) ?></label>
        <input class="input" id="in-<?= e($i->id) ?>" name="name" type="text" maxlength="100" value="<?= e($i->name) ?>" placeholder="Sender name (optional)">
        <button class="button" type="submit">Save</button>
      </form>
    </div>
  <?php endforeach; endif; ?>
  </div>
</div>
<p class="page-summary" style="margin-top:12px">New addresses are added by your mail administrator.</p>

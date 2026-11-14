<?php /** @var string $name @var string $address @var bool $unavailable @var list<string> $nameErrors @var list<string> $pwErrors @var string $csrf @var \Webmail\Core\View $view */ ?>
<div class="page-head"><div><h1 class="page-title">Settings</h1><p class="page-summary">Signed in as <strong><?= e($address) ?></strong></p></div></div>
<?= $view->render('partials.settings-tabs', ['active' => 'settings-profile']) ?>
<?php if ($unavailable): ?><div class="field-error" role="alert">Your profile could not be loaded from the mail server right now.</div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <div class="card-header"><div><h2 class="card-title">Your name</h2><p class="card-description">Shown to people you write to, unless a sender address has its own name.</p></div></div>
  <div class="card-well">
    <?php foreach ($nameErrors as $er): ?><p class="field-error" role="alert"><?= e($er) ?></p><?php endforeach; ?>
    <form class="inline-fields" method="post" action="/settings/profile" data-once>
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <label class="sr-only" for="p-name">Name</label>
      <input class="input" id="p-name" name="name" type="text" maxlength="100" value="<?= e($name) ?>" autocomplete="name" placeholder="Your name">
      <button class="button button-primary" type="submit">Save name</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><div><h2 class="card-title">Change password</h2><p class="card-description">Use at least 8 characters. You will stay signed in on this device.</p></div></div>
  <div class="card-well">
    <?php foreach ($pwErrors as $er): ?><p class="field-error" role="alert"><?= e($er) ?></p><?php endforeach; ?>
    <form method="post" action="/settings/password" data-once autocomplete="off">
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <div class="field"><label class="label" for="pw-cur">Current password</label><input class="input" id="pw-cur" name="current" type="password" autocomplete="current-password" maxlength="256" required></div>
      <div class="field"><label class="label" for="pw-new">New password</label><input class="input" id="pw-new" name="new" type="password" autocomplete="new-password" minlength="8" maxlength="256" required></div>
      <div class="field"><label class="label" for="pw-conf">Confirm new password</label><input class="input" id="pw-conf" name="confirm" type="password" autocomplete="new-password" minlength="8" maxlength="256" required></div>
      <button class="button button-primary" type="submit">Change password</button>
    </form>
  </div>
</div>

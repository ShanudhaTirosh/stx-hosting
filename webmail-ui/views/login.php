<?php /** @var string $csrf @var string $username @var ?string $error @var array $flash @var bool $adding @var string $action */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $adding ? 'Add account' : 'Sign in' ?> · STX Webmail</title>
<meta name="robots" content="noindex,nofollow">
<script src="/assets/theme.js"></script>
<link rel="stylesheet" href="/assets/app.css">
<script src="/assets/app.js" defer></script>
</head>
<body>
<main class="auth">
  <div class="auth-card">
    <div class="auth-brand" style="display:flex;align-items:center;justify-content:center;gap:0.5rem">
      <img src="/assets/STKcdr.svg" width="24" height="24" alt="STX"> STX Webmail
    </div>
    <div class="card">
      <div class="card-header">
        <div><h1 class="card-title"><?= $adding ? 'Add another account' : 'Sign in to your mailbox' ?></h1><p class="card-description"><?= $adding ? 'Sign in to a second mailbox. You can switch between your accounts from the menu at the bottom of the sidebar.' : 'Use your full email address and password.' ?></p></div>
      </div>
      <div class="card-well">
        <form method="post" action="<?= e($action) ?>" data-once novalidate>
          <input type="hidden" name="_token" value="<?= e($csrf) ?>">
          <div class="field">
            <label class="label" for="username">Email address</label>
            <input class="input" id="username" name="username" type="email" value="<?= e($username) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus maxlength="254"<?= $error ? ' aria-invalid="true" aria-describedby="login-error"' : '' ?>>
          </div>
          <div class="field">
            <label class="label" for="password">Password</label>
            <input class="input" id="password" name="password" type="password" autocomplete="current-password" required maxlength="256">
            <?php if ($error): ?><p class="field-error" id="login-error" role="alert"><?= e($error) ?></p><?php endif; ?>
          </div>
          <button type="submit" class="button button-primary button-block"><?= $adding ? 'Add account' : 'Sign in' ?></button>
        </form>
      </div>
    </div>
    <p class="auth-foot">
      <?= $adding ? '<a href="/mail/inbox">Cancel</a>' : 'Your session is encrypted and ends when you sign out.' ?>
      <span style="display:block;margin-top:0.75rem;font-size:0.8rem">
        <a href="https://app.<?= e(getenv('DOMAIN') ?: 'stxapp.io') ?>/portal/mail" style="color:var(--ui-fg-dim);text-decoration:none">← Return to STX Portal</a>
      </span>
    </p>
  </div>
</main>
<?= $view->render('partials.flash', ['flash' => $flash ?? []]) ?>
</body>
</html>

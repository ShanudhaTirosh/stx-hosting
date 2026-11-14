<?php /** @var string $csrf @var string $address @var ?string $error @var bool $adding @var array $flash @var \Webmail\Core\View $view */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Verification code · Webmail</title>
<meta name="robots" content="noindex,nofollow">
<script src="/assets/theme.js"></script>
<link rel="stylesheet" href="/assets/app.css">
<script src="/assets/app.js" defer></script>
</head>
<body>
<main class="auth">
  <div class="auth-card">
    <div class="auth-brand"><?= icon('mail', 22) ?> Webmail</div>
    <div class="card">
      <div class="card-header"><div><h1 class="card-title">Enter your verification code</h1><p class="card-description">Open your authenticator app and type the 6-digit code for <strong><?= e($address) ?></strong>.</p></div></div>
      <div class="card-well">
        <form method="post" action="/login/2fa" data-once novalidate>
          <input type="hidden" name="_token" value="<?= e($csrf) ?>">
          <div class="field">
            <label class="label" for="code">6-digit code</label>
            <input class="input" id="code" name="code" type="text" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" maxlength="10" required autofocus<?= $error ? ' aria-invalid="true" aria-describedby="code-error"' : '' ?>>
            <?php if ($error): ?><p class="field-error" id="code-error" role="alert"><?= e($error) ?></p><?php endif; ?>
          </div>
          <button type="submit" class="button button-primary button-block">Verify and sign in</button>
        </form>
      </div>
    </div>
    <p class="auth-foot"><?= $adding ? '<a href="/mail/inbox">Cancel</a>' : '<a href="/login">Use a different account</a>' ?></p>
  </div>
</main>
<?= $view->render('partials.flash', ['flash' => $flash ?? []]) ?>
</body>
</html>

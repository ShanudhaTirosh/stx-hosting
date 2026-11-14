<?php /** @var list<array{id:string,address:string,active:bool}> $accounts @var bool $autoreplyOn @var list<\Webmail\WildDuck\Model\Label> $labels @var list<string> $scripts @var string $title @var string $content @var array $folders @var string $active @var string $address @var string $csrf @var string $q @var array $flash */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> · STX Webmail</title>
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex,nofollow">
<script src="/assets/theme.js"></script>
<link rel="stylesheet" href="/assets/app.css">
<script src="/assets/app.js" defer></script>
<script src="/assets/notify.js" defer></script>
<?php foreach ($scripts ?? [] as $sc): ?><script src="/assets/<?= e($sc) ?>.js" defer></script><?php endforeach; ?>
</head>
<?php $ib = null; foreach ($folders as $f) { if ($f->specialUse === '\\Inbox') { $ib = $f; } } ?>
<body data-notify<?= count($accounts) > 1 ? ' data-multi' : '' ?><?= $ib ? ' data-inbox-total="' . e($ib->total) . '" data-inbox-unseen="' . e($ib->unseen) . '"' : '' ?> data-path="<?= e($active) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<div class="app">
  <a class="app-brand" href="/mail/inbox" style="display:flex;align-items:center;gap:0.4rem">
    <img src="/assets/STKcdr.svg" width="20" height="20" alt="STX"> STX Webmail
  </a>

  <header class="app-topbar">
    <button type="button" class="button button-icon menu-btn" data-drawer-open aria-controls="sidebar" aria-expanded="false" aria-label="Open menu"><?= icon('menu') ?></button>
    <form class="topbar-search" action="/search" method="get" role="search">
      <?= icon('search') ?>
      <label class="sr-only" for="q">Search mail</label>
      <input id="q" class="input" type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Search mail" maxlength="200" autocomplete="off">
    </form>
    <span class="topbar-spacer"></span>
    <?php if (!empty($autoreplyOn)): ?><a class="badge badge-notice" href="/settings/autoreply" title="Automatic replies are being sent"><span class="badge-dot"></span> Auto-reply on</a><?php endif; ?>
    <a href="https://app.<?= e(getenv('DOMAIN') ?: 'stxapp.io') ?>/portal/mail" class="button" style="font-size:0.75rem;padding:0.25rem 0.6rem;text-decoration:none" title="Return to STX Hosting Client Portal">STX Portal</a>
    <button type="button" class="button button-icon" data-notify-toggle aria-pressed="false" aria-label="Desktop notifications for new mail" title="Desktop notifications for new mail" hidden><?= icon('bell') ?></button>
    <button type="button" class="button button-icon" data-theme-toggle aria-label="Toggle dark mode" title="Toggle dark mode"><?= icon('sun') ?></button>
  </header>

  <nav id="sidebar" class="app-sidebar" aria-label="Mailboxes">
    <a class="button button-primary sidebar-compose" href="/compose"><?= icon('pen-line') ?> Compose</a>
    <?php $system = array_filter($folders, fn ($f) => $f->isSystem()); $custom = array_filter($folders, fn ($f) => !$f->isSystem());
    $icons = ['inbox' => 'inbox', 'drafts' => 'file-pen', 'sent' => 'send', 'archive' => 'archive', 'spam' => 'shield-alert', 'trash' => 'trash-2']; ?>
    <?php foreach ($system as $f): ?>
      <a class="nav-item<?= $active === $f->slug() ? ' is-active' : '' ?>" data-slug="<?= e($f->slug()) ?>" href="/mail/<?= e($f->slug()) ?>"<?= $active === $f->slug() ? ' aria-current="page"' : '' ?>>
        <span class="nav-tile"><?= icon($icons[$f->slug()] ?? 'folder') ?></span>
        <span class="nav-label"><?= e($f->label()) ?></span>
        <?php if ($f->unseen > 0 && $f->slug() !== 'trash'): ?><span class="nav-count"><?= e($f->unseen) ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
    <?php if ($custom): ?>
      <div class="nav-section">Folders</div>
      <?php foreach ($custom as $f): ?>
        <a class="nav-item<?= $active === $f->slug() ? ' is-active' : '' ?>" data-slug="<?= e($f->slug()) ?>" href="/mail/<?= e($f->slug()) ?>"<?= $active === $f->slug() ? ' aria-current="page"' : '' ?>>
          <span class="nav-tile"><?= icon('folder') ?></span>
          <span class="nav-label"><?= e($f->label()) ?></span>
          <?php if ($f->unseen > 0): ?><span class="nav-count"><?= e($f->unseen) ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($labels): ?>
      <div class="nav-section">Labels</div>
      <?php foreach ($labels as $l): $a = 'label:' . $l->id; ?>
        <a class="nav-item<?= $active === $a ? ' is-active' : '' ?>" href="/label/<?= e($l->id) ?>"<?= $active === $a ? ' aria-current="page"' : '' ?>>
          <span class="nav-tile"><span class="lbl-dot lbl-<?= e($l->color) ?>"></span></span>
          <span class="nav-label"><?= e($l->name) ?></span>
          <?php if ($l->unseen > 0): ?><span class="nav-count"><?= e($l->unseen) ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
    <div class="nav-section">Organize</div>
    <a class="nav-item nav-manage<?= $active === 'manage-folders' ? ' is-active' : '' ?>" href="/folders"<?= $active === 'manage-folders' ? ' aria-current="page"' : '' ?>><span class="nav-tile"><?= icon('folder') ?></span><span class="nav-label">Folders</span></a>
    <a class="nav-item nav-manage<?= $active === 'manage-labels' ? ' is-active' : '' ?>" href="/labels"<?= $active === 'manage-labels' ? ' aria-current="page"' : '' ?>><span class="nav-tile"><?= icon('tag') ?></span><span class="nav-label">Labels</span></a>
    <a class="nav-item nav-manage<?= $active === 'manage-rules' ? ' is-active' : '' ?>" href="/rules"<?= $active === 'manage-rules' ? ' aria-current="page"' : '' ?>><span class="nav-tile"><?= icon('shield-check') ?></span><span class="nav-label">Rules</span></a>
    <a class="nav-item nav-manage<?= $active === 'manage-contacts' ? ' is-active' : '' ?>" href="/contacts"<?= $active === 'manage-contacts' ? ' aria-current="page"' : '' ?>><span class="nav-tile"><?= icon('users') ?></span><span class="nav-label">Contacts</span></a>
    <a class="nav-item nav-manage<?= $active === 'manage-signatures' ? ' is-active' : '' ?>" href="/signatures"<?= $active === 'manage-signatures' ? ' aria-current="page"' : '' ?>><span class="nav-tile"><?= icon('pen-line') ?></span><span class="nav-label">Signatures</span></a>
    <a class="nav-item nav-manage<?= str_starts_with((string) $active, 'settings') ? ' is-active' : '' ?>" href="/settings/profile"<?= str_starts_with((string) $active, 'settings') ? ' aria-current="page"' : '' ?>><span class="nav-tile"><?= icon('settings') ?></span><span class="nav-label">Settings</span></a>
    <div class="sidebar-foot">
      <details class="account-menu">
        <summary class="account-chip" title="<?= e($address) ?>"><?= icon('shield-check') ?><span><?= e($address) ?></span><span class="nav-count" data-others-total hidden></span></summary>
        <div class="account-list" role="group" aria-label="Accounts">
          <?php foreach ($accounts as $ac): ?>
            <?php if ($ac['active']): ?>
              <div class="account-row is-active" aria-current="true"><?= icon('circle-check', 14) ?><span><?= e($ac['address']) ?></span></div>
            <?php else: ?>
              <form method="post" action="/accounts/switch"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e($ac['id']) ?>">
                <button class="account-row" type="submit"><span><?= e($ac['address']) ?></span><span class="nav-count" data-account-badge="<?= e($ac['id']) ?>" hidden></span></button></form>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if (count($accounts) < \Webmail\Core\Auth::MAX_ACCOUNTS): ?><a class="account-row" href="/accounts/add"><?= icon('pen-line', 14) ?><span>Add another account</span></a><?php endif; ?>
        </div>
      </details>
      <form method="post" action="/logout">
        <input type="hidden" name="_token" value="<?= e($csrf) ?>">
        <button type="submit" class="button button-block"><?= icon('log-out') ?> <?= count($accounts) > 1 ? 'Sign out of this account' : 'Sign out' ?></button>
      </form>
      <?php if (count($accounts) > 1): ?>
      <form method="post" action="/logout/all"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><button type="submit" class="button button-block button-danger">Sign out of all accounts</button></form>
      <?php endif; ?>
    </div>
  </nav>
  <div id="scrim" class="drawer-scrim"></div>

  <main id="main" class="app-main"><?= $content ?></main>
</div>
<?= $view->render('partials.flash', ['flash' => $flash ?? []]) ?>
</body>
</html>

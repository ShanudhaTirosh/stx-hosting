<?php /** @var string $active */
$tabs = ['settings-profile' => ['/settings/profile', 'Profile & password'], 'settings-identities' => ['/settings/identities', 'Sender addresses'], 'settings-autoreply' => ['/settings/autoreply', 'Auto-reply']]; ?>
<nav class="tabs" aria-label="Settings">
  <?php foreach ($tabs as $k => [$href, $label]): ?>
    <a class="tab<?= $active === $k ? ' is-active' : '' ?>" href="<?= e($href) ?>"<?= $active === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
  <a class="tab" href="/rules">Rules</a><a class="tab" href="/signatures">Signatures</a><a class="tab" href="/contacts">Contacts</a>
</nav>

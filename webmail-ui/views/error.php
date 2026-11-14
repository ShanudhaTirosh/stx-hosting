<?php /** @var int $code @var string $heading @var string $text */ ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($heading) ?> · STX Webmail</title><meta name="robots" content="noindex">
<script src="/assets/theme.js"></script><link rel="stylesheet" href="/assets/app.css"></head>
<body><main class="auth"><div class="auth-card"><div class="card"><div class="card-well empty">
  <div class="empty-icon"><?= icon('circle-alert', 20) ?></div>
  <h1 class="empty-title"><?= e($code) ?> · <?= e($heading) ?></h1>
  <p class="empty-text"><?= e($text) ?></p>
  <p><a class="button" href="/">Back to mail</a></p>
</div></div></div></main></body></html>

<?php /** @var array $flash */ if (!$flash) { return; } ?>
<div class="flash-stack" role="status" aria-live="polite">
<?php foreach ($flash as $f): $t = in_array($f['type'], ['success', 'error'], true) ? $f['type'] : 'info'; ?>
  <div class="flash flash-<?= e($t) ?>">
    <span class="flash-icon"><?= icon($t === 'success' ? 'circle-check' : ($t === 'error' ? 'circle-alert' : 'info'), 14) ?></span>
    <div class="flash-body"><?= e($f['message']) ?></div>
    <button type="button" class="flash-close" aria-label="Dismiss"><?= icon('x', 14) ?></button>
  </div>
<?php endforeach; ?>
</div>

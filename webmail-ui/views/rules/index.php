<?php /** @var list<\Webmail\WildDuck\Model\Rule> $rules @var list<\Webmail\WildDuck\Model\Rule> $blocked @var int $allowed @var int $used @var bool $unavailable
 *  @var list<string> $errors @var string $senderValue @var array<string,array{if:list<string>,then:list<string>}> $descriptions @var string $csrf */ ?>
<div class="page-head"><div><h1 class="page-title">Rules</h1><p class="page-summary">Sort incoming mail automatically. Rules apply to new messages, not to mail already in your folders.</p></div>
  <div class="card-actions"><a class="button button-primary" href="/rules/new"><?= icon('pen-line') ?> New rule</a></div></div>
<?php if ($unavailable): ?><div class="field-error" role="alert">Your rules could not be loaded from the mail server right now.</div><?php endif; ?>
<?php if ($errors): ?><div class="field-error" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <div class="card-header"><div><h2 class="card-title">Blocked senders</h2><p class="card-description">Mail from these addresses or domains goes straight to Spam.</p></div></div>
  <div class="card-well">
    <form class="inline-fields" method="post" action="/blocked" data-once>
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <label class="sr-only" for="b-sender">Address or domain to block</label>
      <input class="input" id="b-sender" name="sender" type="text" maxlength="254" placeholder="name@example.com or example.com" value="<?= e($senderValue) ?>" required autocomplete="off" spellcheck="false">
      <button class="button button-primary" type="submit">Block</button>
    </form>
  </div>
  <?php if ($blocked): ?>
  <div class="card-well is-flush" style="margin-top:6px">
    <?php foreach ($blocked as $b): ?>
      <div class="manage-row">
        <span class="manage-name"><?= icon('shield-alert') ?> <?= e($b->blockedPattern()) ?><?php if ($b->disabled): ?> <span class="badge">Off</span><?php endif; ?></span>
        <span class="card-actions"><a class="button" href="/rules/<?= e($b->id) ?>/delete">Unblock</a></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header"><h2 class="card-title">Your rules</h2><?php if ($allowed > 0): ?><span class="page-summary"><?= e($used) ?> of <?= e($allowed) ?> used</span><?php endif; ?></div>
  <div class="card-well is-flush">
  <?php if (!$rules): ?>
    <div class="empty"><div class="empty-icon"><?= icon('shield-check', 20) ?></div><h3 class="empty-title">No rules yet</h3><p class="empty-text">Create one to label, move or mark messages automatically.</p></div>
  <?php else: foreach ($rules as $x): $d = $descriptions[$x->id]; $simple = $x->isSimple(); ?>
    <div class="manage-row">
      <span class="manage-name"><?= icon('shield-check') ?> <?= e($x->name) ?>
        <?php if ($x->disabled): ?><span class="badge">Off</span><?php endif; ?>
        <?php if (!$simple): ?><span class="badge" title="Uses options that only your mail administrator can change">Advanced</span><?php endif; ?></span>
      <span class="card-actions">
        <?php if ($simple): ?><a class="button" href="/rules/<?= e($x->id) ?>/edit">Edit</a><?php endif; ?>
        <form class="inline-form" method="post" action="/rules/<?= e($x->id) ?>/toggle" data-once><input type="hidden" name="_token" value="<?= e($csrf) ?>"><button class="button" type="submit"><?= $x->disabled ? 'Turn on' : 'Turn off' ?></button></form>
        <a class="button button-danger" href="/rules/<?= e($x->id) ?>/delete"><?= icon('trash-2') ?> Delete</a>
      </span>
      <p class="rule-summary"><strong>If</strong> <?= $d['if'] ? e(implode(' and ', $d['if'])) : 'a message arrives' ?> <strong>then</strong> <?= $d['then'] ? e(implode(', ', $d['then'])) : 'do nothing' ?></p>
    </div>
  <?php endforeach; endif; ?>
  </div>
</div>

<?php
/** @var \Webmail\WildDuck\Model\MessageDetail $m @var string $slug @var string $csrf @var bool $imagesAllowed @var int $blockedImages
 *  @var bool $hasHtml @var string $folderLabel @var bool $inTrash */
$href = '/mail/' . rawurlencode($m->mailboxId) . '/' . $m->id;
$fmt = static fn (array $l): string => implode(', ', array_map(static fn ($a) => $a['name'] !== '' ? $a['name'] . ' <' . $a['address'] . '>' : $a['address'], $l));
$act = static function (string $do, string $label, string $icon, string $cls = '') use ($csrf, $href): string {
    return '<form class="inline-form" method="post" action="' . e($href) . '/action"><input type="hidden" name="_token" value="' . e($csrf) . '"><button class="button ' . $cls . '" name="do" value="' . e($do) . '" type="submit">' . icon($icon) . ' ' . e($label) . '</button></form>';
};
?>
<div class="page-head">
  <a class="button" href="/mail/<?= e($slug) ?>"><?= icon('arrow-left') ?> <?= e($folderLabel) ?></a>
  <div class="card-actions">
    <?php if ($m->draft): ?>
      <a class="button button-primary" href="<?= e($href) ?>/edit"><?= icon('pen-line') ?> Edit draft</a>
    <?php else: ?>
      <a class="button" href="<?= e($href) ?>/reply"><?= icon('reply') ?> Reply</a>
      <a class="button" href="<?= e($href) ?>/reply-all"><?= icon('reply-all') ?> Reply all</a>
      <a class="button" href="<?= e($href) ?>/forward"><?= icon('forward') ?> Forward</a>
    <?php endif; ?>
    <?= $act($m->seen ? 'unread' : 'read', $m->seen ? 'Mark unread' : 'Mark read', 'mail') ?>
    <?= $act($m->flagged ? 'unstar' : 'star', $m->flagged ? 'Unstar' : 'Star', 'star') ?>
    <?php if ($slug !== 'archive'): ?><?= $act('archive', 'Archive', 'archive') ?><?php endif; ?>
    <?php if ($slug !== 'spam'): ?><?= $act('spam', 'Spam', 'shield-alert') ?><?php endif; ?>
    <?= $act($inTrash ? 'delete' : 'trash', $inTrash ? 'Delete forever' : 'Delete', 'trash-2', 'button-danger') ?>
  </div>
</div>

<article class="card"><div class="card-well is-flush">
  <header class="msg-head">
    <h1 class="msg-title"><?= e($m->subject !== '' ? $m->subject : '(no subject)') ?></h1>
    <dl class="msg-people">
      <dt>From</dt><dd><?= e($m->fromName !== '' ? $m->fromName . ' <' . $m->fromAddress . '>' : $m->fromAddress) ?></dd>
      <?php if ($m->to): ?><dt>To</dt><dd><?= e($fmt($m->to)) ?></dd><?php endif; ?>
      <?php if ($m->cc): ?><dt>Cc</dt><dd><?= e($fmt($m->cc)) ?></dd><?php endif; ?>
      <?php if ($m->date): ?><dt>Date</dt><dd><time datetime="<?= e($m->date->format(DATE_ATOM)) ?>"><?= e($m->date->format('D, j M Y H:i')) ?></time></dd><?php endif; ?>
    </dl>
    <?php if (!empty($canAddContact) && $m->fromAddress !== ''): ?>
    <form class="inline-fields msg-addcontact" method="post" action="/contacts/quick" data-once>
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="name" value="<?= e($m->fromName) ?>"><input type="hidden" name="email" value="<?= e($m->fromAddress) ?>">
      <input type="hidden" name="return" value="<?= e($href) ?>">
      <button class="button" type="submit"><?= icon('users') ?> Add sender to contacts</button>
    </form>
    <?php endif; ?>
    <?php if ($m->fromAddress !== '' && strcasecmp($m->fromAddress, $address ?? '') !== 0): ?>
    <form class="inline-fields msg-addcontact" method="post" action="/blocked/quick" data-once>
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="sender" value="<?= e($m->fromAddress) ?>"><input type="hidden" name="return" value="<?= e($href) ?>">
      <input type="hidden" name="src_mailbox" value="<?= e($m->mailboxId) ?>"><input type="hidden" name="src_id" value="<?= e($m->id) ?>">
      <button class="button" type="submit" title="Future mail from this sender goes to Spam"><?= icon('shield-alert') ?> Block sender</button>
    </form>
    <?php endif; ?>
    <?php if ($m->labels || $labels): ?>
    <div class="msg-labels">
      <?php foreach ($m->labels as $ln): ?><span class="lbl lbl-<?= e($labelColors[$ln] ?? 'gray') ?>"><?= e($ln) ?></span><?php endforeach; ?>
      <?php if ($labels): ?>
      <form class="inline-fields" method="post" action="<?= e($href) ?>/action" data-once>
        <input type="hidden" name="_token" value="<?= e($csrf) ?>">
        <label class="sr-only" for="msg-label">Label</label>
        <select class="input input-sm" id="msg-label" name="label"><option value="">Label…</option>
          <?php foreach ($labels as $l): ?><option value="<?= e($l->id) ?>"><?= e($l->name) ?><?= in_array($l->name, $m->labels, true) ? ' ✓' : '' ?></option><?php endforeach; ?>
        </select>
        <button class="button" name="do" value="label_add" type="submit">Add</button>
        <button class="button" name="do" value="label_remove" type="submit">Remove</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </header>

  <?php if ($m->attachments): $shown = array_filter($m->attachments, fn ($a) => !$a['related']); if ($shown): ?>
  <div class="msg-attachments" aria-label="Attachments">
    <?php foreach ($shown as $a): ?>
      <a class="att" href="<?= e($href) ?>/attachment/<?= e($a['id']) ?>" download>
        <?= icon('paperclip', 14) ?><span><?= e($a['filename']) ?></span><small><?= e(human_size($a['size'])) ?></small>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; endif; ?>

  <?php if ($hasHtml && $blockedImages > 0 && !$imagesAllowed): ?>
  <div class="notice"><?= icon('image-off', 14) ?> Remote images are blocked to protect your privacy.
    <a class="button" href="<?= e($href) ?>?images=1">Show images</a></div>
  <?php endif; ?>

  <?php if ($hasHtml): ?>
    <iframe class="mail-frame" title="Message content" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" src="<?= e($href) ?>/body<?= $imagesAllowed ? '?images=1' : '' ?>"></iframe>
  <?php else: ?>
    <pre class="plain-body"><?= e($m->text !== '' ? $m->text : '(This message has no content.)') ?></pre>
  <?php endif; ?>
</div></article>

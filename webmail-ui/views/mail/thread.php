<?php
/** @var list<\Webmail\WildDuck\Model\MessageSummary> $items @var array<string,\Webmail\WildDuck\Model\Mailbox> $owned @var list<bool> $wasUnseen
 *  @var string $subject @var string $csrf @var string $self @var array<string,string> $labelColors */
$self = '/thread/' . $threadId;
$lastIdx = array_key_last($items);
$act = static function (string $do, string $label, string $ic, string $cls = '') use ($items, $csrf): string {
    $h = '<form class="inline-form" method="post" action="/mail/bulk"><input type="hidden" name="_token" value="' . e($csrf) . '">';
    foreach ($items as $m) { $h .= '<input type="hidden" name="items[]" value="' . e($m->mailboxId . ':' . $m->id) . '">'; }
    return $h . '<input type="hidden" name="return" value="/mail/inbox"><button class="button ' . $cls . '" name="do" value="' . e($do) . '" type="submit">' . icon($ic) . ' ' . e($label) . '</button></form>';
};
?>
<div class="page-head">
  <a class="button" href="/mail/inbox"><?= icon('arrow-left') ?> Inbox</a>
  <div class="card-actions">
    <?= $act('unread', 'Mark unread', 'mail') ?>
    <?= $act('archive', 'Archive', 'archive') ?>
    <?= $act('spam', 'Spam', 'shield-alert') ?>
    <?= $act('trash', 'Trash', 'trash-2', 'button-danger') ?>
  </div>
</div>
<h1 class="page-title thread-title"><?= e($subject !== '' ? $subject : '(no subject)') ?></h1>
<p class="page-summary"><?= count($items) ?> message<?= count($items) === 1 ? '' : 's' ?> in this conversation</p>

<div class="thread">
<?php foreach ($items as $i => $m):
  $href = '/mail/' . rawurlencode($m->mailboxId) . '/' . $m->id;
  $box = $owned[$m->mailboxId] ?? null; ?>
  <details class="card thread-msg"<?= $i === $lastIdx || ($wasUnseen[$i] ?? false) ? ' open' : '' ?>>
    <summary class="thread-head">
      <span class="thread-who"><?= ($wasUnseen[$i] ?? false) ? '<span class="badge-dot unread-dot" title="Was unread"></span>' : '' ?><strong><?= e($m->senderLabel()) ?></strong></span>
      <span class="thread-intro"><?= e($m->intro) ?></span>
      <span class="msg-meta"><?php if ($box): ?><span class="badge"><?= e($box->label()) ?></span><?php endif; ?><?php if ($m->hasAttachments): ?><?= icon('paperclip', 14) ?><?php endif; ?><time datetime="<?= e($m->date?->format(DATE_ATOM)) ?>"><?= e($m->date?->format('j M Y, H:i')) ?></time></span>
    </summary>
    <div class="card-well is-flush">
      <div class="thread-actions">
        <?php if ($m->draft): ?>
          <a class="button button-primary" href="<?= e($href) ?>/edit"><?= icon('pen-line') ?> Edit draft</a>
        <?php else: ?>
          <a class="button" href="<?= e($href) ?>/reply"><?= icon('reply') ?> Reply</a>
          <a class="button" href="<?= e($href) ?>/reply-all"><?= icon('reply-all') ?> Reply all</a>
          <a class="button" href="<?= e($href) ?>/forward"><?= icon('forward') ?> Forward</a>
        <?php endif; ?>
        <a class="button" href="<?= e($href) ?>">Open message</a>
      </div>
      <iframe class="mail-frame" title="Message from <?= e($m->senderLabel()) ?>" loading="lazy" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" src="<?= e($href) ?>/body"></iframe>
    </div>
  </details>
<?php endforeach; ?>
</div>

<?php
/** @var string $title @var string $summary @var \Webmail\WildDuck\Model\Page $page @var string $slug @var string $csrf
 *  @var string $base @var array $keep @var bool $unseenOnly @var bool $isSearch @var ?string $cursor @var string $self
 *  @var list<\Webmail\WildDuck\Model\Label> $labels @var array<string,string> $labelColors @var list<\Webmail\WildDuck\Model\Mailbox> $allFolders */
$inTrash = $inTrash ?? false;
$currentBox = $currentBox ?? '';
$qs = static fn (array $extra): string => http_build_query(array_filter($extra + $keep, static fn ($v) => $v !== null && $v !== '' && $v !== false));
?>
<div class="page-head">
  <div><h1 class="page-title"><?= e($title) ?></h1><p class="page-summary"><?= e($summary) ?></p></div>
  <div class="card-actions">
    <?php if (!$isSearch): ?>
      <a class="button" href="<?= e($base . '?' . $qs(['unseen' => $unseenOnly ? null : '1'])) ?>" aria-pressed="<?= $unseenOnly ? 'true' : 'false' ?>"><?= icon('mail-open') ?> <?= $unseenOnly ? 'Show all' : 'Unread only' ?></a>
    <?php endif; ?>
    <a class="button" href="<?= e($self) ?>" aria-label="Refresh"><?= icon('refresh-cw') ?> Refresh</a>
  </div>
</div>

<div class="card"><div class="card-well is-flush">
<?php if (!$page->items): ?>
  <div class="empty">
    <div class="empty-icon"><?= icon($isSearch ? 'search' : 'inbox', 20) ?></div>
    <h2 class="empty-title"><?= e($emptyTitle ?? ($isSearch ? 'No messages found' : ($unseenOnly ? 'No unread messages' : 'Nothing here yet'))) ?></h2>
    <p class="empty-text"><?= e($emptyText ?? ($isSearch ? 'Try different words or remove a filter.' : ($unseenOnly ? 'You are all caught up.' : 'Messages in this folder will show up here.'))) ?></p>
  </div>
<?php else: ?>
  <form id="bulk" class="bulk-bar" method="post" action="/mail/bulk">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="return" value="<?= e($self) ?>">
    <label class="bulk-all"><input type="checkbox" data-select-all> <span class="sr-only">Select all messages on this page</span></label>
    <span class="bulk-count" data-selected aria-live="polite"></span>
    <div class="bulk-actions" data-bulk-actions>
      <button class="button" name="do" value="read" type="submit"><?= icon('mail-open') ?> Read</button>
      <button class="button" name="do" value="unread" type="submit"><?= icon('mail') ?> Unread</button>
      <button class="button" name="do" value="star" type="submit"><?= icon('star') ?> Star</button>
      <button class="button" name="do" value="archive" type="submit"><?= icon('archive') ?> Archive</button>
      <button class="button" name="do" value="spam" type="submit"><?= icon('shield-alert') ?> Spam</button>
      <?php if ($inTrash): ?>
        <button class="button button-danger" name="do" value="delete" type="submit"><?= icon('trash-2') ?> Delete forever</button>
      <?php else: ?>
        <button class="button button-danger" name="do" value="trash" type="submit"><?= icon('trash-2') ?> Trash</button>
      <?php endif; ?>
      <span class="bulk-group">
        <label class="sr-only" for="bulk-folder">Move to folder</label>
        <select class="input" id="bulk-folder" name="folder"><option value="">Move to…</option>
          <?php foreach ($allFolders as $f): if ($f->id === $currentBox || in_array($f->specialUse, ['\\Drafts', '\\Sent'], true)) { continue; } ?>
            <option value="<?= e($f->id) ?>"><?= e($f->label()) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="button" name="do" value="move" type="submit">Move</button>
      </span>
      <?php if ($labels): ?>
      <span class="bulk-group">
        <label class="sr-only" for="bulk-label">Label</label>
        <select class="input" id="bulk-label" name="label"><option value="">Label…</option>
          <?php foreach ($labels as $l): ?><option value="<?= e($l->id) ?>"><?= e($l->name) ?></option><?php endforeach; ?>
        </select>
        <button class="button" name="do" value="label_add" type="submit">Add</button>
        <button class="button" name="do" value="label_remove" type="submit">Remove</button>
      </span>
      <?php endif; ?>
    </div>
  </form>

<?php foreach ($page->items as $m):
  $href = '/mail/' . rawurlencode($m->mailboxId) . '/' . $m->id;
  $open = $m->draft ? $href . '/edit' : ($m->threadCount > 1 && $m->threadId !== '' ? '/thread/' . $m->threadId : $href); ?>
  <div class="msg-row<?= $m->seen ? '' : ' is-unread' ?>">
    <input class="msg-check" type="checkbox" name="items[]" value="<?= e($m->mailboxId . ':' . $m->id) ?>" form="bulk" aria-label="Select: <?= e($m->subject !== '' ? $m->subject : 'no subject') ?>">
    <form class="inline-form" method="post" action="<?= e($href) ?>/action">
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="return" value="<?= e($self) ?>">
      <button class="msg-star<?= $m->flagged ? ' is-on' : '' ?>" name="do" value="<?= $m->flagged ? 'unstar' : 'star' ?>" aria-label="<?= $m->flagged ? 'Remove star' : 'Star message' ?>" aria-pressed="<?= $m->flagged ? 'true' : 'false' ?>"><?= icon('star') ?></button>
    </form>
    <span class="msg-from"><?= e($m->senderLabel()) ?></span>
    <span class="msg-text">
      <span class="msg-subject"><?= e($m->subject !== '' ? $m->subject : '(no subject)') ?></span>
      <?php foreach (array_slice($m->labels, 0, 3) as $ln): ?><span class="lbl lbl-<?= e($labelColors[$ln] ?? 'gray') ?>"><?= e($ln) ?></span><?php endforeach; ?>
      <span class="msg-intro"><?= e($m->intro) ?></span>
    </span>
    <span class="msg-meta">
      <?php if ($m->threadCount > 1): ?><span class="badge" title="Messages in conversation"><?= e($m->threadCount) ?></span><?php endif; ?>
      <?php if ($m->hasAttachments): ?><?= icon('paperclip', 14) ?><span class="sr-only">Has attachments</span><?php endif; ?>
      <time datetime="<?= e($m->date?->format(DATE_ATOM)) ?>"><?= e(short_date($m->date)) ?></time>
    </span>
    <a class="msg-link" href="<?= e($open) ?>" aria-label="<?= $m->draft ? 'Edit draft' : ($m->threadCount > 1 ? 'Open conversation' : 'Open') ?>: <?= e($m->subject !== '' ? $m->subject : 'no subject') ?> from <?= e($m->senderLabel()) ?>"></a>
  </div>
<?php endforeach; ?>
  <?php if ($page->next || $page->previous): ?>
  <nav class="pager" aria-label="Pagination">
    <span><?= e($page->total) ?> message<?= $page->total === 1 ? '' : 's' ?></span>
    <span class="card-actions">
      <?php if ($page->previous): ?><a class="button" href="<?= e($base . '?' . $qs(['cursor' => $page->previous, 'dir' => 'prev'])) ?>"><?= icon('chevron-left') ?> Newer</a><?php endif; ?>
      <?php if ($page->next): ?><a class="button" href="<?= e($base . '?' . $qs(['cursor' => $page->next, 'dir' => 'next'])) ?>">Older <?= icon('chevron-right') ?></a><?php endif; ?>
    </span>
  </nav>
  <?php endif; ?>
<?php endif; ?>
</div></div>

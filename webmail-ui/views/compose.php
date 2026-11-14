<?php
/** @var \Webmail\Mail\ComposeForm $f @var list<string> $errors @var int $maxFileMb @var string $csrf @var list<\Webmail\WildDuck\Model\Identity> $identities @var list<array{id:string,name:string,html:string}> $signatures @var string $sigSelected */
$hid = static fn (string $n, mixed $v): string => '<input type="hidden" name="' . e($n) . '" value="' . e($v) . '">';
?>
<div class="page-head">
  <div><h1 class="page-title"><?= e($f->heading()) ?></h1></div>
</div>

<form method="post" action="/compose" enctype="multipart/form-data" data-once data-compose novalidate>
  <?= $hid('_token', $csrf) ?><?= $hid('compose_id', $f->composeId) ?><?= $hid('mode', $f->mode) ?>
  <?php if ($f->draft): ?><?= $hid('draft_mailbox', $f->draft['mailbox']) ?><?= $hid('draft_id', $f->draft['id']) ?><?php endif; ?>
  <?php if ($f->reference): ?><?= $hid('ref_mailbox', $f->reference['mailbox']) ?><?= $hid('ref_id', $f->reference['id']) ?><?= $hid('ref_action', $f->reference['action']) ?><?php endif; ?>
  <?php if ($f->source): ?><?= $hid('src_mailbox', $f->source['mailbox']) ?><?= $hid('src_id', $f->source['id']) ?><?php endif; ?>

  <div class="card">
    <?php if ($errors): ?>
    <div class="card-header"><div class="field-error" role="alert" id="compose-errors">
      <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
    </div></div>
    <?php endif; ?>
    <div class="card-well is-flush">
      <?php if (count($identities) > 1): ?>
      <div class="compose-row compose-from">
        <label class="label" for="from">From</label>
        <select class="input" id="from" name="from">
          <?php foreach ($identities as $i): $sel = $f->from !== '' ? strcasecmp($f->from, $i->address) === 0 : $i->main; ?>
            <option value="<?= e($i->address) ?>"<?= $sel ? ' selected' : '' ?>><?= e($i->name !== '' ? $i->name . ' <' . $i->address . '>' : $i->address) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="compose-row">
        <label class="label" for="to">To</label>
        <input class="input" id="to" name="to" type="text" value="<?= e($f->to) ?>" autocomplete="off" spellcheck="false" maxlength="5000" placeholder="name@example.com, …" data-focus-first data-suggest>
        <button type="button" class="button" data-show-cc aria-expanded="<?= ($f->cc !== '' || $f->bcc !== '') ? 'true' : 'false' ?>">Cc / Bcc</button>
      </div>
      <div class="compose-row" data-cc-row<?= ($f->cc === '' && $f->bcc === '') ? ' hidden' : '' ?>>
        <label class="label" for="cc">Cc</label>
        <input class="input" id="cc" name="cc" type="text" value="<?= e($f->cc) ?>" autocomplete="off" spellcheck="false" maxlength="5000" data-suggest>
      </div>
      <div class="compose-row" data-cc-row<?= ($f->cc === '' && $f->bcc === '') ? ' hidden' : '' ?>>
        <label class="label" for="bcc">Bcc</label>
        <input class="input" id="bcc" name="bcc" type="text" value="<?= e($f->bcc) ?>" autocomplete="off" spellcheck="false" maxlength="5000" data-suggest>
      </div>
      <div class="compose-row">
        <label class="label" for="subject">Subject</label>
        <input class="input" id="subject" name="subject" type="text" value="<?= e($f->subject) ?>" maxlength="998">
      </div>

      <?php if ($signatures): ?>
      <div class="compose-row compose-sig">
        <label class="label" for="sig-pick">Signature</label>
        <select class="input input-sm" id="sig-pick" name="sig" data-sig-pick>
          <option value="">No signature</option>
          <?php foreach ($signatures as $sg): ?><option value="<?= e($sg['id']) ?>"<?= $sg['id'] === $sigSelected ? ' selected' : '' ?>><?= e($sg['name']) ?></option><?php endforeach; ?>
        </select>
        <?php foreach ($signatures as $sg): ?><template data-sig="<?= e($sg['id']) ?>"><?= $sg['html'] ?></template><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?= $view->render('partials.editor', ['html' => $f->html, 'label' => 'Message body']) ?>

      <?php if ($f->carry): ?>
      <fieldset class="carry">
        <legend class="label">Attachments from the original message</legend>
        <?php foreach ($f->carry as $c): ?>
          <label class="att"><input type="checkbox" name="keep[]" value="<?= e($c['id']) ?>"<?= $c['keep'] ? ' checked' : '' ?>> <span><?= e($c['filename']) ?></span> <small><?= e(human_size($c['size'])) ?></small></label>
        <?php endforeach; ?>
      </fieldset>
      <?php endif; ?>

      <div class="compose-row compose-files">
        <label class="button" for="files"><?= icon('paperclip') ?> Attach files</label>
        <input id="files" class="sr-only" type="file" name="attachments[]" multiple data-max-bytes="<?= e($maxFileMb * 1048576) ?>">
        <ul class="file-list" data-file-list aria-live="polite"></ul>
      </div>
    </div>
    <div class="compose-actions">
      <a class="button" href="/mail/inbox">Discard</a>
      <span class="topbar-spacer"></span>
      <button type="submit" class="button" name="do" value="draft"><?= icon('file-pen') ?> Save draft</button>
      <button type="submit" class="button button-primary" name="do" value="send"><?= icon('send') ?> Send</button>
    </div>
  </div>
</form>

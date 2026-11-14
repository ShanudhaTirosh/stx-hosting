<?php /** @var array{status:bool,name:string,subject:string,text:string,start:?string,end:?string} $a @var list<string> $errors @var bool $unavailable @var string $startDay @var string $endDay @var string $csrf @var \Webmail\Core\View $view */ ?>
<div class="page-head"><div><h1 class="page-title">Settings</h1><p class="page-summary">Reply automatically while you are away.</p></div></div>
<?= $view->render('partials.settings-tabs', ['active' => 'settings-autoreply']) ?>
<?php if ($unavailable): ?><div class="field-error" role="alert">Your auto-reply settings could not be loaded from the mail server right now.</div><?php endif; ?>
<?php if ($errors): ?><div class="field-error" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="card"><div class="card-well">
  <form method="post" action="/settings/autoreply" data-once>
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <div class="field"><label class="att"><input type="checkbox" name="status" value="1"<?= $a['status'] ? ' checked' : '' ?>> <span>Send automatic replies</span></label></div>
    <div class="field"><label class="label" for="ar-name">Sender name (optional)</label><input class="input" id="ar-name" name="name" type="text" maxlength="100" value="<?= e($a['name']) ?>"></div>
    <div class="field"><label class="label" for="ar-subject">Subject (leave empty to reuse the original subject)</label><input class="input" id="ar-subject" name="subject" type="text" maxlength="200" value="<?= e($a['subject']) ?>"></div>
    <div class="field"><label class="label" for="ar-text">Message</label><textarea class="input" id="ar-text" name="text" rows="7" maxlength="5000" style="height:auto;padding:8px 12px"><?= e($a['text']) ?></textarea></div>
    <div class="inline-fields field">
      <div><label class="label" for="ar-start">First day (UTC, optional)</label><input class="input" id="ar-start" name="start" type="date" value="<?= e($startDay) ?>"></div>
      <div><label class="label" for="ar-end">Last day (UTC, optional)</label><input class="input" id="ar-end" name="end" type="date" value="<?= e($endDay) ?>"></div>
    </div>
    <button class="button button-primary" type="submit">Save auto-reply</button>
  </form>
</div></div>

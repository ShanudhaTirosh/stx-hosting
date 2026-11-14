<?php /** @var ?\Webmail\WildDuck\Model\Rule $rule @var array<string,mixed> $v @var list<string> $errors @var list<\Webmail\WildDuck\Model\Label> $labels @var list<\Webmail\WildDuck\Model\Mailbox> $allFolders @var string $csrf */
$val = static fn (string $k): string => is_string($v[$k] ?? null) ? $v[$k] : '';
$chk = static fn (string $k): string => ($v[$k] ?? '') === '1' ? ' checked' : '';
$moveTo = $val('move_to'); $sizeOp = $val('size_op');
$picked = array_map('strval', array_filter((array) ($v['labels'] ?? []), 'is_string')); ?>
<div class="page-head"><h1 class="page-title"><?= $rule ? 'Edit rule' : 'New rule' ?></h1></div>
<?php if ($errors): ?><div class="field-error" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="post" action="<?= $rule ? '/rules/' . e($rule->id) : '/rules' ?>" data-once>
  <input type="hidden" name="_token" value="<?= e($csrf) ?>">
  <div class="card" style="margin-bottom:16px">
    <div class="card-header"><div><h2 class="card-title">When a message arrives that…</h2><p class="card-description">All the conditions you fill in must match. Matching is not case-sensitive.</p></div></div>
    <div class="card-well">
      <div class="rule-grid">
        <div class="field"><label class="label" for="r-from">From contains</label><input class="input" id="r-from" name="from" type="text" maxlength="200" value="<?= e($val('from')) ?>" placeholder="billing@ or @shop.com"></div>
        <div class="field"><label class="label" for="r-to">To or Cc contains</label><input class="input" id="r-to" name="to" type="text" maxlength="200" value="<?= e($val('to')) ?>"></div>
        <div class="field"><label class="label" for="r-subject">Subject contains</label><input class="input" id="r-subject" name="subject" type="text" maxlength="200" value="<?= e($val('subject')) ?>"></div>
        <div class="field"><label class="label" for="r-list">List-ID contains</label><input class="input" id="r-list" name="list_id" type="text" maxlength="200" value="<?= e($val('list_id')) ?>" placeholder="newsletter.example.com"></div>
        <div class="field"><label class="label" for="r-text">Text contains</label><input class="input" id="r-text" name="text" type="text" maxlength="500" value="<?= e($val('text')) ?>" placeholder="invoice OR receipt"></div>
        <div class="field"><span class="label">Size</span><div class="inline-fields">
          <label class="sr-only" for="r-sizeop">Size comparison</label>
          <select class="input input-sm" id="r-sizeop" name="size_op"><option value="">Any size</option><option value="larger"<?= $sizeOp === 'larger' ? ' selected' : '' ?>>Larger than</option><option value="smaller"<?= $sizeOp === 'smaller' ? ' selected' : '' ?>>Smaller than</option></select>
          <label class="sr-only" for="r-sizemb">Size in megabytes</label><input class="input input-sm" id="r-sizemb" name="size_mb" type="text" inputmode="decimal" value="<?= e($val('size_mb')) ?>" placeholder="MB" style="min-width:80px;width:90px">
        </div></div>
      </div>
      <label class="att"><input type="checkbox" name="has_attachment" value="1"<?= $chk('has_attachment') ?>> <span>Has an attachment</span></label>
    </div>
  </div>

  <div class="card" style="margin-bottom:16px">
    <div class="card-header"><div><h2 class="card-title">Then…</h2><p class="card-description">Forwarding and permanent deletion are not offered here, to keep your mailbox safe.</p></div></div>
    <div class="card-well">
      <div class="field"><label class="label" for="r-move">Put it in</label>
        <select class="input" id="r-move" name="move_to"><option value="">Leave it where it arrives</option>
          <option value="spam"<?= $moveTo === 'spam' ? ' selected' : '' ?>>Spam</option>
          <?php foreach ($allFolders as $f): if ($f->specialUse !== null && in_array($f->specialUse, ['\\Drafts', '\\Sent', '\\Junk'], true)) { continue; } ?>
            <option value="<?= e($f->id) ?>"<?= $moveTo === $f->id ? ' selected' : '' ?>><?= e($f->label()) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label class="att"><input type="checkbox" name="act_seen" value="1"<?= $chk('act_seen') ?>> <span>Mark as read</span></label> <label class="att"><input type="checkbox" name="act_flag" value="1"<?= $chk('act_flag') ?>> <span>Star it</span></label></div>
      <?php if ($labels): ?>
      <fieldset class="carry" style="border:0;padding:0"><legend class="label">Add labels</legend>
        <?php foreach ($labels as $l): ?><label class="att"><input type="checkbox" name="labels[]" value="<?= e($l->id) ?>"<?= in_array($l->id, $picked, true) ? ' checked' : '' ?>> <span class="lbl lbl-<?= e($l->color) ?>"><?= e($l->name) ?></span></label><?php endforeach; ?>
      </fieldset>
      <?php endif; ?>
    </div>
  </div>

  <div class="card"><div class="card-well">
    <div class="field"><label class="label" for="r-name">Rule name (optional)</label><input class="input" id="r-name" name="name" type="text" maxlength="100" value="<?= e($val('name')) ?>" placeholder="Made from the first condition if left empty"></div>
    <div class="card-actions"><a class="button" href="/rules">Cancel</a><button class="button button-primary" type="submit"><?= $rule ? 'Save rule' : 'Create rule' ?></button></div>
  </div></div>
</form>

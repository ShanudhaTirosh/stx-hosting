<?php /** @var list<\Webmail\WildDuck\Model\Label> $labels @var list<string> $errors @var list<string> $colors @var string $csrf */
$colorSelect = static function (string $name, string $id, string $current) use ($colors): string {
    $o = '';
    foreach ($colors as $c) { $o .= '<option value="' . e($c) . '"' . ($c === $current ? ' selected' : '') . '>' . e(ucfirst($c)) . '</option>'; }
    return '<label class="sr-only" for="' . e($id) . '">Colour</label><select class="input input-sm" id="' . e($id) . '" name="' . e($name) . '">' . $o . '</select>';
}; ?>
<div class="page-head"><div><h1 class="page-title">Labels</h1><p class="page-summary">Tag messages in any folder. A message can have several labels; deleting a label never deletes messages.</p></div></div>
<?php if ($errors): ?><div class="field-error" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <div class="card-header"><h2 class="card-title">New label</h2></div>
  <div class="card-well">
    <form class="inline-fields" method="post" action="/labels" data-once>
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <label class="sr-only" for="new-label">Label name</label>
      <input class="input" id="new-label" name="name" type="text" maxlength="64" placeholder="Label name" required>
      <?= $colorSelect('color', 'new-color', 'blue') ?>
      <button class="button button-primary" type="submit">Create label</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2 class="card-title">Your labels</h2></div>
  <div class="card-well is-flush">
  <?php if (!$labels): ?>
    <div class="empty"><div class="empty-icon"><?= icon('tag', 20) ?></div><h3 class="empty-title">No labels yet</h3><p class="empty-text">Create a label above, then apply it from the message list or a message.</p></div>
  <?php else: foreach ($labels as $l): ?>
    <div class="manage-row">
      <span class="manage-name"><a class="lbl lbl-<?= e($l->color) ?>" href="/label/<?= e($l->id) ?>"><?= e($l->name) ?></a> <small><?= e($l->total) ?> message<?= $l->total === 1 ? '' : 's' ?><?= $l->unseen ? ', ' . e($l->unseen) . ' unread' : '' ?></small></span>
      <form class="inline-fields" method="post" action="/labels/<?= e($l->id) ?>/update" data-once>
        <input type="hidden" name="_token" value="<?= e($csrf) ?>">
        <label class="sr-only" for="ln-<?= e($l->id) ?>">Rename <?= e($l->name) ?></label>
        <input class="input" id="ln-<?= e($l->id) ?>" name="name" type="text" value="<?= e($l->name) ?>" maxlength="64" required>
        <?= $colorSelect('color', 'lc-' . $l->id, $l->color) ?>
        <button class="button" type="submit">Save</button>
        <a class="button button-danger" href="/labels/<?= e($l->id) ?>/delete"><?= icon('trash-2') ?> Delete</a>
      </form>
    </div>
  <?php endforeach; endif; ?>
  </div>
</div>

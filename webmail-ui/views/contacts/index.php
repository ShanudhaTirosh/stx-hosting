<?php /** @var list<array{id:string,name:string,email:string,note:string}> $contacts @var int $total @var int $page @var int $pages @var string $q
 *  @var list<string> $errors @var bool $unavailable @var array $old @var string $csrf */
$val = static fn (string $k): string => is_string($old[$k] ?? null) ? $old[$k] : ''; ?>
<div class="page-head"><div><h1 class="page-title">Contacts</h1><p class="page-summary">People you write to often. They appear as suggestions when you type a recipient.</p></div></div>
<?php if ($unavailable): ?><div class="field-error" role="alert">Contacts could not be loaded from the mail server right now. Try again in a moment.</div><?php endif; ?>
<?php if ($errors): ?><div class="field-error" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <div class="card-header"><h2 class="card-title">New contact</h2></div>
  <div class="card-well">
    <form class="inline-fields" method="post" action="/contacts" data-once>
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <label class="sr-only" for="c-name">Name</label><input class="input" id="c-name" name="name" type="text" maxlength="100" placeholder="Name" value="<?= e($val('name')) ?>">
      <label class="sr-only" for="c-email">Email</label><input class="input" id="c-email" name="email" type="email" maxlength="254" placeholder="Email address" required value="<?= e($val('email')) ?>">
      <label class="sr-only" for="c-note">Note</label><input class="input" id="c-note" name="note" type="text" maxlength="200" placeholder="Note (optional)" value="<?= e($val('note')) ?>">
      <button class="button button-primary" type="submit">Add contact</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title"><?= e($total) ?> contact<?= $total === 1 ? '' : 's' ?></h2>
    <form class="inline-fields" method="get" action="/contacts" role="search">
      <label class="sr-only" for="c-search">Search contacts</label>
      <input class="input" id="c-search" name="q" type="search" value="<?= e($q) ?>" placeholder="Search contacts" maxlength="100">
      <button class="button" type="submit">Search</button>
    </form>
  </div>
  <div class="card-well is-flush">
  <?php if (!$contacts): ?>
    <div class="empty"><div class="empty-icon"><?= icon('users', 20) ?></div>
      <h3 class="empty-title"><?= $q !== '' ? 'No matching contacts' : 'No contacts yet' ?></h3>
      <p class="empty-text"><?= $q !== '' ? 'Try a different name or address.' : 'Add one above, or use “Add to contacts” on any message.' ?></p></div>
  <?php else: foreach ($contacts as $c): ?>
    <div class="manage-row">
      <span class="manage-name"><?= icon('users') ?> <span><?= e($c['name'] !== '' ? $c['name'] : $c['email']) ?> <?php if ($c['name'] !== ''): ?><small><?= e($c['email']) ?></small><?php endif; ?><?php if ($c['note'] !== ''): ?><small> · <?= e($c['note']) ?></small><?php endif; ?></span></span>
      <span class="card-actions">
        <a class="button" href="/compose?to=<?= e(rawurlencode(($c['name'] !== '' ? $c['name'] . ' ' : '') . '<' . $c['email'] . '>')) ?>"><?= icon('send') ?> Write</a>
        <a class="button button-danger" href="/contacts/<?= e($c['id']) ?>/delete"><?= icon('trash-2') ?> Delete</a>
      </span>
      <details class="manage-edit">
        <summary class="button">Edit</summary>
        <form class="inline-fields" method="post" action="/contacts/<?= e($c['id']) ?>/update" data-once>
          <input type="hidden" name="_token" value="<?= e($csrf) ?>">
          <label class="sr-only" for="n-<?= e($c['id']) ?>">Name</label><input class="input" id="n-<?= e($c['id']) ?>" name="name" type="text" maxlength="100" value="<?= e($c['name']) ?>" placeholder="Name">
          <label class="sr-only" for="e-<?= e($c['id']) ?>">Email</label><input class="input" id="e-<?= e($c['id']) ?>" name="email" type="email" maxlength="254" value="<?= e($c['email']) ?>" required>
          <label class="sr-only" for="o-<?= e($c['id']) ?>">Note</label><input class="input" id="o-<?= e($c['id']) ?>" name="note" type="text" maxlength="200" value="<?= e($c['note']) ?>" placeholder="Note">
          <button class="button button-primary" type="submit">Save</button>
        </form>
      </details>
    </div>
  <?php endforeach; endif; ?>
  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Pagination"><span>Page <?= e($page) ?> of <?= e($pages) ?></span>
      <span class="card-actions">
        <?php if ($page > 1): ?><a class="button" href="/contacts?<?= e(http_build_query(['q' => $q, 'page' => $page - 1])) ?>"><?= icon('chevron-left') ?> Previous</a><?php endif; ?>
        <?php if ($page < $pages): ?><a class="button" href="/contacts?<?= e(http_build_query(['q' => $q, 'page' => $page + 1])) ?>">Next <?= icon('chevron-right') ?></a><?php endif; ?>
      </span></nav>
  <?php endif; ?>
  </div>
</div>

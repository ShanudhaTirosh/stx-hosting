<?php /** @var \Webmail\WildDuck\Model\Rule $rule @var array{if:list<string>,then:list<string>} $desc @var string $csrf */ $b = $rule->blockedPattern(); ?>
<div class="page-head"><h1 class="page-title"><?= $b !== null ? 'Unblock sender' : 'Delete rule' ?></h1></div>
<div class="card"><div class="card-well">
  <?php if ($b !== null): ?>
    <p>Stop sending mail from <strong><?= e($b) ?></strong> to Spam?</p>
  <?php else: ?>
    <p>Delete the rule <strong><?= e($rule->name) ?></strong>?</p>
    <p class="rule-summary"><strong>If</strong> <?= $desc['if'] ? e(implode(' and ', $desc['if'])) : 'a message arrives' ?> <strong>then</strong> <?= $desc['then'] ? e(implode(', ', $desc['then'])) : 'do nothing' ?></p>
    <p class="page-summary" style="margin:8px 0 16px">Messages already delivered are not affected.</p>
  <?php endif; ?>
  <form method="post" action="/rules/<?= e($rule->id) ?>/delete" data-once class="card-actions">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <a class="button" href="/rules">Cancel</a><button class="button button-danger" type="submit"><?= $b !== null ? 'Unblock' : 'Delete rule' ?></button>
  </form>
</div></div>

<?php
declare(strict_types=1);

use Webmail\Mail\RuleSpec;
use Webmail\WildDuck\Model\{Label, Mailbox, Rule};

const RULE_INV = 'f100000000000000000000a1';
const RULE_FWD = 'f100000000000000000000a2';
const RULE_DEL = 'f100000000000000000000a3';

function filtersOf(): array { return Servers::wdState()['filters'][UID_A] ?? []; }
function filterNamed(string $n): ?array { foreach (filtersOf() as $f) { if ($f['name'] === $n) { return $f; } } return null; }
function rulePost(Browser $b, string $path, array $f, string $from = '/rules'): array { return $b->post($path, $f + ['_token' => $b->pageToken($from)], false); }
function specFolders(): array { $o = []; foreach (['INBOX' => '\\Inbox', 'Drafts' => '\\Drafts', 'Sent Mail' => '\\Sent', 'Archive' => '\\Archive', 'Junk' => '\\Junk', 'Trash' => '\\Trash', 'Projects' => null] as $n => $u) { $id = inboxId(UID_A, $n); $o[$id] = Mailbox::fromArray(['id' => $id, 'name' => $n, 'path' => $n] + ($u ? ['specialUse' => $u] : [])); } return $o; }
function specLabels(): array { $o = []; foreach (['Work' => 'blue', 'Personal' => 'green'] as $n => $c) { $id = labelId($n); $o[$id] = Label::fromArray(['id' => $id, 'name' => $n, 'metaData' => ['color' => $c]]); } return $o; }

test('unit: RuleSpec builds exact API filters from form input', function () {
    $r = RuleSpec::fromInput(['from' => '  Billing@ ', 'subject' => "inv\r\noice", 'has_attachment' => '1', 'size_op' => 'larger', 'size_mb' => '2,5', 'act_seen' => '1', 'move_to' => inboxId(UID_A, 'Archive'), 'labels' => [labelId('Work'), labelId('Work')]], specFolders(), specLabels());
    eq($r['errors'], []); eq($r['spec']['query'], ['from' => 'Billing@', 'subject' => 'inv oice', 'ha' => true, 'size' => 2621440]);
    eq($r['spec']['action'], ['seen' => true, 'mailbox' => inboxId(UID_A, 'Archive'), 'labels' => [labelId('Work')]]); eq($r['spec']['name'], 'Rule: Billing@');
    $r = RuleSpec::fromInput(['text' => 'a OR b', 'size_op' => 'smaller', 'size_mb' => '1', 'move_to' => 'spam', 'act_flag' => '1', 'name' => 'Mine'], specFolders(), specLabels());
    eq($r['spec']['query'], ['text' => 'a OR b', 'size' => -1048576]); eq($r['spec']['action'], ['flag' => true, 'spam' => true]); eq($r['spec']['name'], 'Mine');
});
test('unit: RuleSpec rejects incomplete, foreign and oversized input', function () {
    $bad = [
        [[], 'at least one condition'], [['from' => 'x'], 'at least one thing'], [['from' => 'x', 'move_to' => 'nope'], 'folder from the list'],
        [['from' => 'x', 'move_to' => inboxId(UID_A, 'Drafts')], 'folder from the list'], [['from' => 'x', 'move_to' => inboxId(UID_A, 'Sent Mail')], 'folder from the list'],
        [['from' => 'x', 'move_to' => inboxId('b2b2b2b2b2b2b2b2b2b2b2b2')], 'folder from the list'], [['from' => 'x', 'act_seen' => '1', 'labels' => [labelId('Nope')]], 'labels from the list'],
        [['from' => str_repeat('a', 201), 'act_seen' => '1'], 'up to 200'], [['text' => str_repeat('a', 501), 'act_seen' => '1'], 'up to 500'], [['from' => 'x', 'act_seen' => '1', 'name' => str_repeat('n', 101)], 'up to 100'],
        [['from' => 'x', 'act_seen' => '1', 'size_op' => 'larger', 'size_mb' => '0'], 'between 0.01 and 1000'], [['from' => 'x', 'act_seen' => '1', 'size_op' => 'larger', 'size_mb' => '1001'], 'between 0.01 and 1000'],
        [['from' => 'x', 'act_seen' => '1', 'size_op' => 'sideways', 'size_mb' => '1'], 'between 0.01 and 1000'], [['from' => 'x', 'act_seen' => '1', 'size_op' => 'larger', 'size_mb' => 'abc'], 'between 0.01 and 1000'],
    ];
    foreach ($bad as [$in, $msg]) { $r = RuleSpec::fromInput($in, specFolders(), specLabels()); eq($r['spec'], null, json_encode($in)); has(implode(' ', $r['errors']), $msg, json_encode($in)); }
    $many = array_map(fn ($i) => sprintf('%024x', $i), range(1, 11)); $labels = []; foreach ($many as $id) { $labels[$id] = Label::fromArray(['id' => $id, 'name' => "L$id"]); }
    $r = RuleSpec::fromInput(['from' => 'x', 'labels' => $many], specFolders(), $labels); has(implode(' ', $r['errors']), 'up to 10');
    $r = RuleSpec::fromInput(['from' => 'x', 'act_seen' => '1', 'act_delete' => '1', 'delete' => '1', 'targets' => ['evil@x.y'], 'action' => ['delete' => true]], specFolders(), specLabels());
    eq($r['spec']['action'], ['seen' => true], 'forwarding/permanent delete can never be created through the form');
});
test('unit: Rule classification and descriptions', function () {
    $mk = fn (string $name, array $q, array $a) => Rule::fromArray(['id' => 'f100000000000000000000aa', 'name' => $name, 'originalQuery' => $q, 'originalAction' => $a]);
    check($mk('r', ['from' => 'x'], ['seen' => true, 'labels' => ['a']])->isSimple());
    foreach ([['targets' => ['a@b.c']], ['delete' => true], ['unknown' => true], ['seen' => 'yes']] as $a) { check(!$mk('r', ['from' => 'x'], $a)->isSimple(), json_encode($a)); }
    check(!$mk('r', ['weird' => 1], ['seen' => true])->isSimple());
    eq($mk('Blocked sender: a@b.co', ['from' => 'a@b.co'], ['spam' => true])->blockedPattern(), 'a@b.co');
    foreach ([['Blocked sender: a', ['from' => 'a', 'to' => 'b'], ['spam' => true]], ['Blocked sender: a', ['from' => 'a'], ['spam' => true, 'seen' => true]], ['Other', ['from' => 'a'], ['spam' => true]]] as [$n, $q, $a]) { eq($mk($n, $q, $a)->blockedPattern(), null, $n); }
    $d = RuleSpec::describe($mk('r', ['from' => 'x', 'ha' => true, 'size' => -1048576], ['seen' => true, 'mailbox' => inboxId(UID_A, 'Archive'), 'labels' => [labelId('Work'), 'gone'], 'targets' => ['a@b.c'], 'delete' => true]), specFolders(), specLabels());
    eq($d['if'], ['From contains “x”', 'has an attachment', 'smaller than 1.0 MB']); eq($d['then'], ['mark as read', 'move to Archive', 'add label “Work”', 'add label (deleted label)', 'delete permanently', 'forward to a@b.c']);
    has(implode(',', RuleSpec::describe($mk('r', ['from' => 'x'], ['mailbox' => 'f' . str_repeat('0', 23)]), [], [])['then']), 'a folder that no longer exists');
});
test('unit: editing round-trips (form values -> same filter)', function () {
    $r = Rule::fromArray(['id' => 'f100000000000000000000aa', 'name' => 'Invoices', 'originalQuery' => ['from' => 'billing@', 'ha' => true, 'size' => 3145728], 'originalAction' => ['seen' => true, 'flag' => true, 'mailbox' => inboxId(UID_A, 'Archive'), 'labels' => [labelId('Work')]]]);
    $out = RuleSpec::fromInput(RuleSpec::toForm($r), specFolders(), specLabels());
    eq($out['errors'], []); eq($out['spec'], ['name' => 'Invoices', 'query' => $r->query, 'action' => $r->action]);
    $r2 = Rule::fromArray(['id' => 'f100000000000000000000ab', 'name' => 'S', 'originalQuery' => ['text' => 'x', 'size' => -524288], 'originalAction' => ['spam' => true]]);
    eq(RuleSpec::toForm($r2)['size_mb'], '0.5'); eq(RuleSpec::fromInput(RuleSpec::toForm($r2), specFolders(), specLabels())['spec']['query'], ['text' => 'x', 'size' => -524288]);
});
test('unit: block patterns', function () {
    foreach (['Bob@Example.COM' => 'bob@example.com', '@Evil.com' => '@evil.com', 'evil.com' => '@evil.com', ' sub.evil.co.uk ' => '@sub.evil.co.uk'] as $in => $out) { eq(RuleSpec::blockPattern($in), $out, $in); }
    foreach (['', 'x', 'localhost', 'a b@c.de', '<a@b.co>', 'a@', '@', 'a,b@c.de', 'evil.c', '-x.com', str_repeat('a', 250) . '.com', "a@b.co\nBcc: x"] as $bad) { eq(RuleSpec::blockPattern($bad), null, json_encode($bad)); }
});

test('rules: page lists rules with plain-language summaries and flags advanced ones', function () {
    $b = loggedIn(); $r = $b->get('/rules'); eq($r['status'], 200); $h = $r['body'];
    has($h, 'Invoices'); has($h, 'From contains “billing@”'); has($h, 'mark as read'); has($h, 'move to Archive'); has($h, 'add label “Work”');
    has($h, 'Audit copy'); has($h, 'forward to audit@example.net'); has($h, 'Purge promos'); has($h, 'delete permanently'); eq(substr_count($h, '>Advanced<'), 2);
    has($h, '3 of 50 used'); has($h, '/rules/' . RULE_INV . '/edit'); lacks($h, '/rules/' . RULE_FWD . '/edit'); lacks($h, '/rules/' . RULE_DEL . '/edit');
    has($h, 'href="/rules"'); has($b->get('/mail/inbox')['body'], 'href="/rules"'); eq(wdCalls('GET /users/' . UID_A . '/filters'), 1, 'one filters call per page');
    foreach (['/rules', '/rules/new', '/rules/' . RULE_INV . '/edit', '/rules/' . RULE_INV . '/delete'] as $p) { eq(Browser::raw('GET', Servers::APP . $p)['status'], 303, $p); }
    foreach (['/rules', '/rules/' . RULE_INV, '/rules/' . RULE_INV . '/toggle', '/rules/' . RULE_INV . '/delete', '/blocked', '/blocked/quick'] as $p) { eq($b->post($p, ['from' => 'x'], false)['status'], 419, $p); }
    eq(count(filtersOf()), 3, 'nothing changed');
});
test('rules: create - exact filter sent, name auto-made, listed afterwards', function () {
    $b = loggedIn(); $arch = inboxId(UID_A, 'Archive');
    $r = rulePost($b, '/rules', ['from' => '@shop.com', 'has_attachment' => '1', 'size_op' => 'larger', 'size_mb' => '1', 'act_seen' => '1', 'move_to' => $arch, 'labels' => [labelId('Personal')]], '/rules/new');
    eq($r['status'], 303); eq($r['headers']['location'], '/rules');
    $f = filterNamed('Rule: @shop.com'); eq($f['originalQuery'], ['from' => '@shop.com', 'ha' => true, 'size' => 1048576]); eq($f['originalAction'], ['seen' => true, 'mailbox' => $arch, 'labels' => [labelId('Personal')]]); eq($f['disabled'], false);
    $h = $b->get('/rules')['body']; has($h, 'Rule “Rule: @shop.com” created.'); has($h, 'larger than 1.0 MB'); has($h, '4 of 50 used');
    rulePost($b, '/rules', ['subject' => 'promo', 'move_to' => 'spam', 'name' => 'Promos'], '/rules/new'); eq(filterNamed('Promos')['originalAction'], ['spam' => true]);
    rulePost($b, '/rules', ['subject' => 'old', 'move_to' => inboxId(UID_A, 'Trash'), 'name' => 'To trash'], '/rules/new'); eq(filterNamed('To trash')['originalAction'], ['mailbox' => inboxId(UID_A, 'Trash')]);
});
test('rules: invalid or forged submissions create nothing and keep what was typed', function () {
    $b = loggedIn(); $n = count(filtersOf());
    foreach ([['act_seen' => '1'], ['from' => 'x'], ['from' => 'x', 'move_to' => inboxId('b2b2b2b2b2b2b2b2b2b2b2b2')], ['from' => 'x', 'move_to' => inboxId(UID_A, 'Drafts')], ['from' => 'x', 'act_seen' => '1', 'labels' => [labelId('Mallory')]], ['from' => 'x', 'act_seen' => '1', 'size_op' => 'larger', 'size_mb' => 'x']] as $f) {
        $r = rulePost($b, '/rules', $f, '/rules/new'); eq($r['status'], 422, json_encode($f)); has($r['body'], 'role="alert"');
    }
    $x = '"><script>alert(1)</script>'; $r = rulePost($b, '/rules', ['from' => $x, 'subject' => 'keep me'], '/rules/new'); eq($r['status'], 422); lacks($r['body'], '<script>alert(1)'); has($r['body'], 'value="keep me"');
    $r = rulePost($b, '/rules', ['from' => 'x', 'act_seen' => '1', 'delete' => '1', 'act_delete' => '1', 'targets' => ['evil@x.y'], 'forward' => 'evil@x.y', 'name' => 'Sneaky'], '/rules/new'); eq($r['status'], 303);
    eq(filterNamed('Sneaky')['originalAction'], ['seen' => true], 'no forwarding or deletion can be smuggled in'); eq(count(filtersOf()), $n + 1);
});
test('rules: edit simple rules; advanced rules are protected', function () {
    $b = loggedIn(); $arch = inboxId(UID_A, 'Archive');
    $f = $b->get('/rules/' . RULE_INV . '/edit'); eq($f['status'], 200); $h = $f['body'];
    has($h, 'value="billing@"'); has($h, 'value="Invoices"'); check(str_contains($h, 'value="' . $arch . '" selected'), 'folder preselected'); check(preg_match('#value="' . labelId('Work') . '" checked#', $h) === 1, 'label checked'); has($h, 'name="act_seen" value="1" checked');
    $r = rulePost($b, '/rules/' . RULE_INV, ['from' => 'billing@', 'subject' => 'invoice', 'act_flag' => '1', 'move_to' => 'spam', 'name' => 'Invoices v2'], '/rules/' . RULE_INV . '/edit'); eq($r['status'], 303);
    $x = filterNamed('Invoices v2'); eq($x['originalQuery'], ['from' => 'billing@', 'subject' => 'invoice']); eq($x['originalAction'], ['flag' => true, 'spam' => true]); eq($x['id'], RULE_INV);
    eq(rulePost($b, '/rules/' . RULE_INV, ['act_seen' => '1'], '/rules/' . RULE_INV . '/edit')['status'], 422, 'edit validation');
    foreach ([RULE_FWD, RULE_DEL] as $adv) {
        $before = json_encode(array_values(array_filter(filtersOf(), fn ($x) => $x['id'] === $adv)));
        $r = $b->get("/rules/$adv/edit"); eq($r['status'], 303); has($b->get('/rules')['body'], 'managed by your mail administrator');
        eq(rulePost($b, "/rules/$adv", ['from' => 'x', 'act_seen' => '1', 'name' => 'Rewritten'], '/rules')['status'], 404, 'advanced rules are never rewritten');
        eq(json_encode(array_values(array_filter(filtersOf(), fn ($x) => $x['id'] === $adv))), $before, 'unchanged');
    }
    foreach (['f' . str_repeat('9', 23), 'nothex', 'f100000000000000000000a1x'] as $bad) { eq($b->get("/rules/$bad/edit")['status'], 404, $bad); eq(rulePost($b, "/rules/$bad", ['from' => 'x', 'act_seen' => '1'])['status'], 404, $bad); }
});
test('rules: turn on/off and delete, including advanced rules', function () {
    $b = loggedIn();
    foreach ([RULE_INV, RULE_FWD] as $id) {
        eq(rulePost($b, "/rules/$id/toggle", [])['status'], 303); $d = array_values(array_filter(filtersOf(), fn ($x) => $x['id'] === $id))[0]; eq($d['disabled'], true, "off $id");
        has($b->get('/rules')['body'], 'Rule turned off.'); eq(rulePost($b, "/rules/$id/toggle", [])['status'], 303); eq(array_values(array_filter(filtersOf(), fn ($x) => $x['id'] === $id))[0]['disabled'], false);
    }
    $c = $b->get('/rules/' . RULE_FWD . '/delete'); eq($c['status'], 200); has($c['body'], 'forward to audit@example.net'); has($c['body'], 'Audit copy');
    eq(rulePost($b, '/rules/' . RULE_FWD . '/delete', [])['status'], 303); eq(count(filtersOf()), 2); eq($b->get('/rules/' . RULE_FWD . '/delete')['status'], 404);
    eq(rulePost($b, '/rules/' . RULE_DEL . '/delete', [])['status'], 303); has($b->get('/rules')['body'], 'Rule deleted.');
    foreach (['f' . str_repeat('9', 23), 'nothex'] as $bad) { eq(rulePost($b, "/rules/$bad/toggle", [])['status'], 404, $bad); eq(rulePost($b, "/rules/$bad/delete", [])['status'], 404, $bad); }
});
test('rules: the account rule limit is respected', function () {
    $b = loggedIn(); flag('smallFilterLimit'); has($b->get('/rules')['body'], '3 of 3 used');
    $r = rulePost($b, '/rules', ['from' => 'x', 'act_seen' => '1'], '/rules/new'); eq($r['status'], 422); has($r['body'], 'used all 3'); eq(count(filtersOf()), 3);
    $r = rulePost($b, '/blocked', ['sender' => 'a@evil.com']); eq($r['status'], 422); has($r['body'], 'used all 3'); eq(count(filtersOf()), 3);
});

test('blocked: add by address or domain, listed separately, unblock', function () {
    $b = loggedIn();
    eq(rulePost($b, '/blocked', ['sender' => 'Spammer@Evil.COM'])['status'], 303); $f = filterNamed('Blocked sender: spammer@evil.com');
    eq($f['originalQuery'], ['from' => 'spammer@evil.com']); eq($f['originalAction'], ['spam' => true]);
    rulePost($b, '/blocked', ['sender' => 'bad-domain.org']); check(filterNamed('Blocked sender: @bad-domain.org') !== null, 'bare domain becomes @domain');
    $h = $b->get('/rules')['body']; has($h, 'Blocked @bad-domain.org.'); preg_match('#Blocked senders.*?Your rules#s', $h, $sec); has($sec[0], 'spammer@evil.com'); has($sec[0], '@bad-domain.org');
    preg_match('#Your rules.*#s', $h, $rest); lacks($rest[0], 'spammer@evil.com'); has($rest[0], 'Invoices');
    $n = count(filtersOf()); rulePost($b, '/blocked', ['sender' => 'SPAMMER@evil.com']); eq(count(filtersOf()), $n, 'duplicate not added'); has($b->get('/rules')['body'], 'already blocked');
    $id = $f['id']; $c = $b->get("/rules/$id/delete"); has($c['body'], 'Unblock sender'); has($c['body'], 'spammer@evil.com');
    rulePost($b, "/rules/$id/delete", []); eq(filterNamed('Blocked sender: spammer@evil.com'), null); has($b->get('/rules')['body'], 'Unblocked spammer@evil.com.');
    $e = $b->get("/rules/$id/edit"); eq($e['status'], 404);
});
test('blocked: invalid input, own addresses and look-alike rules', function () {
    $b = loggedIn(); $n = count(filtersOf());
    foreach (['', 'nonsense', 'a b@c.de', '<x@y.zz>'] as $bad) { $r = rulePost($b, '/blocked', ['sender' => $bad]); eq($r['status'], 422, json_encode($bad)); has($r['body'], 'Enter an email address'); }
    foreach (['alice@example.com', 'ALICE@example.com', 'sales@example.com', '@example.com', 'example.com'] as $own) { $r = rulePost($b, '/blocked', ['sender' => $own]); eq($r['status'], 422, $own); has($r['body'], 'your own address'); }
    eq(count(filtersOf()), $n, 'nothing created');
    rulePost($b, '/rules', ['name' => 'Blocked sender: x@y.zz', 'from' => 'x@y.zz', 'move_to' => 'spam', 'act_seen' => '1'], '/rules/new');
    $h = $b->get('/rules')['body']; preg_match('#Blocked senders.*?Your rules#s', $h, $sec); lacks($sec[0], 'x@y.zz'); preg_match('#Your rules.*#s', $h, $rest); has($rest[0], 'Blocked sender: x@y.zz');
});
test('blocked: "Block sender" on a message also moves it to Spam', function () {
    $b = loggedIn(); $inbox = inboxId();
    $h = $b->get('/mail/inbox/5')['body']; has($h, 'Block sender'); has($h, 'name="sender" value="bot@example.org"'); lacks($b->get('/mail/' . inboxId(UID_A, 'Projects') . '/3')['body'], 'Block sender');
    $t = $b->pageToken();
    $r = $b->post('/blocked/quick', ['sender' => 'bot@example.org', 'src_mailbox' => $inbox, 'src_id' => '5', 'return' => '/mail/inbox/5', '_token' => $t], false);
    eq($r['status'], 303); eq($r['headers']['location'], '/mail/spam'); check(filterNamed('Blocked sender: bot@example.org') !== null);
    $s = $b->get('/mail/spam')['body']; has($s, 'Weekly report #5'); has($s, 'Blocked bot@example.org.'); has($s, 'moved to Spam');
    $r = $b->post('/blocked/quick', ['sender' => 'bot@example.org', 'src_mailbox' => $inbox, 'src_id' => '6', 'return' => '//evil.test', '_token' => $t], false);
    has($b->get('/mail/spam')['body'], 'was already blocked'); has($b->get('/mail/spam')['body'] . $b->get('/mail/spam')['body'], 'Weekly report #6');
    $bob = inboxId('b2b2b2b2b2b2b2b2b2b2b2b2'); $r = $b->post('/blocked/quick', ['sender' => 'x@evil.test', 'src_mailbox' => $bob, 'src_id' => '1', 'return' => '/mail/inbox', '_token' => $t], false);
    eq($r['status'], 303); eq($r['headers']['location'], '/mail/inbox', 'foreign mailbox ids move nothing'); check(filterNamed('Blocked sender: x@evil.test') !== null);
    $r = $b->post('/blocked/quick', ['sender' => 'alice@example.com', 'return' => '/mail/inbox', '_token' => $t], false); has($b->get('/mail/inbox')['body'], 'your own address');
    foreach (['//evil.test', 'https://evil.test/x', '/\\evil.test'] as $bad) {
        $r = $b->post('/blocked/quick', ['sender' => 'nomove@evil.test', 'return' => $bad, '_token' => $t], false); eq($r['status'], 303); eq($r['headers']['location'], '/rules', 'unsafe return ignored: ' . $bad);
    }
    $r = $b->post('/blocked/quick', ['sender' => 'nomove2@evil.test', 'return' => '/mail/inbox/5', '_token' => $t], false); eq($r['headers']['location'], '/mail/inbox/5', 'safe return honoured');
    $junk = inboxId(UID_A, 'Junk'); $r = $b->post('/blocked/quick', ['sender' => 'again@evil.test', 'src_mailbox' => $junk, 'src_id' => '1', 'return' => '/mail/spam', '_token' => $t], false); eq($r['headers']['location'], '/mail/spam');
});
test('rules: server refusals and outages are reported honestly', function () {
    $b = loggedIn(); flag('failFilterWrite');
    $r = rulePost($b, '/rules', ['from' => 'x', 'act_seen' => '1', 'subject' => 'keep'], '/rules/new'); eq($r['status'], 422); has($r['body'], 'nothing was saved'); has($r['body'], 'may not be allowed'); has($r['body'], 'value="keep"');
    $r = rulePost($b, '/rules/' . RULE_INV . '/toggle', []); eq($r['status'], 422); has($r['body'], 'nothing was saved'); $r = rulePost($b, '/rules/' . RULE_INV . '/delete', []); eq($r['status'], 422);
    $r = rulePost($b, '/blocked', ['sender' => 'a@evil.com']); eq($r['status'], 422); has($r['body'], 'nothing was saved');
    flag('failFilterWrite', false); flag('failFilters');
    $g = $b->get('/rules'); eq($g['status'], 200); has($g['body'], 'could not be loaded'); eq($b->get('/rules/new')['status'], 200);
    eq(rulePost($b, '/rules/' . RULE_INV . '/toggle', [])['status'], 503); eq($b->get('/mail/inbox')['status'], 200, 'mail keeps working');
    flag('failFilters', false); eq(count(filtersOf()), 3);
});

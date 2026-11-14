<?php
declare(strict_types=1);

const ADDR_MAIN = 'ad0000000000000000000001';
const ADDR_SALES = 'ad0000000000000000000002';
const ADDR_WORK = 'ad0000000000000000000003';
const ADDR_BOB = 'bd0000000000000000000001';

function setPost(Browser $b, string $path, array $f, string $from = '/settings/profile'): array { return $b->post($path, $f + ['_token' => $b->pageToken($from)], false); }
function wdState2(): array { return Servers::wdState(); }
function ar(): array { return wdState2()['autoreply'][UID_A] ?? []; }
function fromOf(array $submit): array { return $submit['from']; }

test('settings: pages render with tabs and are protected', function () {
    $b = loggedIn();
    $r = $b->get('/settings'); eq($r['status'], 303); eq($r['headers']['location'], '/settings/profile');
    $h = $b->get('/settings/profile')['body'];
    has($h, 'value="Test User"'); has($h, 'alice@example.com'); has($h, 'Sender addresses'); has($h, 'Auto-reply'); has($h, 'href="/settings/profile"'); has($h, 'aria-current="page"');
    foreach (['/settings/profile', '/settings/identities', '/settings/autoreply', '/settings'] as $p) { eq(Browser::raw('GET', Servers::APP . $p)['status'], 303, $p); }
    foreach (['/settings/profile', '/settings/password', '/settings/autoreply', '/settings/identities/' . ADDR_SALES] as $p) { eq($b->post($p, ['name' => 'x'], false)['status'], 419, $p); }
    foreach (['/settings/identities', '/settings/autoreply'] as $p) { eq($b->get($p)['status'], 200, $p); }
});
test('settings: display name - save, clear, validate, escape', function () {
    $b = loggedIn();
    $r = setPost($b, '/settings/profile', ['name' => 'Alice <b>Lee</b>']); eq($r['status'], 303);
    $names = wdState2()['names']; check(!str_contains($names[UID_A], '<'), 'angle brackets are not stored'); has($names[UID_A], 'Alice');
    has($b->get('/settings/profile')['body'], 'Your name was saved.');
    setPost($b, '/settings/profile', ['name' => '']); eq(wdState2()['names'][UID_A], '');
    eq(setPost($b, '/settings/profile', ['name' => str_repeat('n', 101)])['status'], 422); eq(wdState2()['names'][UID_A], '');
    setPost($b, '/settings/profile', ['name' => '"><img src=x onerror=alert(1)>']); $h = $b->get('/settings/profile')['body']; lacks($h, '<img src=x'); check(!str_contains($h, 'value=""><img'), 'attribute cannot be broken out of');
    setPost($b, '/settings/profile', ['name' => 'Keep']); flag('failProfile');
    $r = setPost($b, '/settings/profile', ['name' => 'Changed']); eq($r['status'], 422); has($r['body'], 'nothing was saved'); has($r['body'], 'may not be allowed'); flag('failProfile', false);
    eq(wdState2()['names'][UID_A], 'Keep');
});
test('settings: password change - success, then only the new password works', function () {
    $b = loggedIn(); $pre = $b->get('/settings/profile'); preg_match('/wm_sid=(\w+)/', $pre['headers']['set-cookie'] ?? '', $m1);
    $r = setPost($b, '/settings/password', ['current' => 'correct-pass', 'new' => 'brand-new-pass-1', 'confirm' => 'brand-new-pass-1']);
    eq($r['status'], 303); eq($r['headers']['location'], '/settings/profile');
    eq(wdState2()['users']['alice@example.com']['pass'], 'brand-new-pass-1');
    has($b->get('/settings/profile')['body'], 'Your password was changed.'); eq($b->get('/mail/inbox')['status'], 200, 'stays signed in');
    eq((new Browser())->login('alice@example.com', 'correct-pass')['status'], 401, 'old password rejected');
    eq((new Browser())->login('alice@example.com', 'brand-new-pass-1')['status'], 303, 'new password accepted');
});
test('settings: password change - validation, wrong current password, and secrets never echoed', function () {
    $b = loggedIn('pwtest@example.com', 'pw-pass'); $u = 'pwtest@example.com';
    $cases = [
        ['current' => '', 'new' => 'long-enough-1', 'confirm' => 'long-enough-1', 'msg' => 'current password'],
        ['current' => 'pw-pass', 'new' => 'short', 'confirm' => 'short', 'msg' => '8 to 256'],
        ['current' => 'pw-pass', 'new' => str_repeat('x', 257), 'confirm' => str_repeat('x', 257), 'msg' => '8 to 256'],
        ['current' => 'pw-pass', 'new' => 'long-enough-1', 'confirm' => 'long-enough-2', 'msg' => 'do not match'],
        ['current' => 'pw-pass-long', 'new' => 'pw-pass-long', 'confirm' => 'pw-pass-long', 'msg' => 'different'],
    ];
    foreach ($cases as $c) {
        $msg = array_pop($c); $r = setPost($b, '/settings/password', $c); eq($r['status'], 422, $msg); has($r['body'], $msg);
        foreach (['long-enough-1', 'long-enough-2', 'pw-pass-long'] as $secret) { lacks($r['body'], $secret, 'password echoed back'); }
    }
    eq(wdState2()['users'][$u]['pass'], 'pw-pass', 'nothing changed');
    $r = setPost($b, '/settings/password', ['current' => 'wrong-current', 'new' => 'long-enough-1', 'confirm' => 'long-enough-1']);
    eq($r['status'], 422); has($r['body'], 'current password is incorrect'); lacks($r['body'], 'wrong-current'); lacks($r['body'], 'PasswordVerificationFailed'); eq(wdState2()['users'][$u]['pass'], 'pw-pass');
    flag('failProfile'); $r = setPost($b, '/settings/password', ['current' => 'pw-pass', 'new' => 'long-enough-1', 'confirm' => 'long-enough-1']); eq($r['status'], 422); flag('failProfile', false);
});
test('settings: password guessing is rate limited, even with the right password', function () {
    $b = loggedIn('pwtest@example.com', 'pw-pass'); eq(wdState2()['users']['pwtest@example.com']['pass'], 'pw-pass');
    for ($i = 0; $i < 5; $i++) { setPost($b, '/settings/password', ['current' => "guess$i", 'new' => 'long-enough-1', 'confirm' => 'long-enough-1']); }
    $r = setPost($b, '/settings/password', ['current' => 'pw-pass', 'new' => 'long-enough-1', 'confirm' => 'long-enough-1']);
    eq($r['status'], 429); check((int) ($r['headers']['retry-after'] ?? 0) > 0, 'Retry-After'); has($r['body'], 'Too many attempts'); eq(wdState2()['users']['pwtest@example.com']['pass'], 'pw-pass');
});

test('identities: list, rename, validate and ownership', function () {
    $b = loggedIn(); $h = $b->get('/settings/identities')['body'];
    foreach (['alice@example.com', 'sales@example.com', 'alice.work@example.org', 'value="Sales Team"'] as $x) { has($h, $x); }
    check(preg_match('#alice@example.com\s*<span class="badge">Main#', $h) === 1, 'main badge on the main address');
    eq(setPost($b, '/settings/identities/' . ADDR_SALES, ['name' => 'Sales'], '/settings/identities')['status'], 303);
    $a = array_column(wdState2()['addresses'][UID_A], 'name', 'id'); eq($a[ADDR_SALES], 'Sales'); has($b->get('/settings/identities')['body'], 'Sender name saved.');
    setPost($b, '/settings/identities/' . ADDR_WORK, ['name' => 'A"B<C>'], '/settings/identities'); $w = array_column(wdState2()['addresses'][UID_A], 'name', 'id')[ADDR_WORK]; check(!preg_match('/["<>]/', $w), "unsafe characters stripped: $w");
    eq(setPost($b, '/settings/identities/' . ADDR_SALES, ['name' => str_repeat('x', 101)], '/settings/identities')['status'], 422);
    foreach ([ADDR_BOB, str_repeat('0', 24), 'nothex', '%2e%2e', 'a%2Fb'] as $bad) { eq(setPost($b, "/settings/identities/$bad", ['name' => 'Hacked'], '/settings/identities')['status'], 404, $bad); }
    eq(wdState2()['addresses']['b2b2b2b2b2b2b2b2b2b2b2b2'][0]['name'], '', "bob's identity untouched");
    flag('failProfile'); $r = setPost($b, '/settings/identities/' . ADDR_SALES, ['name' => 'X'], '/settings/identities'); eq($r['status'], 422); has($r['body'], 'nothing was saved'); flag('failProfile', false);
    flag('failAddresses'); $r = $b->get('/settings/identities'); eq($r['status'], 200); has($r['body'], 'could not be loaded');
});

test('compose from: select appears only with several addresses and defaults to the main one', function () {
    $b = loggedIn(); $h = $b->get('/compose')['body'];
    has($h, 'id="from"'); preg_match('#<select class="input" id="from".*?</select>#s', $h, $m);
    has($m[0], 'Sales Team &lt;sales@example.com&gt;'); has($m[0], '>alice.work@example.org<'); check(preg_match('#value="alice@example.com" selected#', $m[0]) === 1, 'main selected');
    $bob = loggedIn('bob@example.com', 'bob-pass'); lacks($bob->get('/compose')['body'], 'id="from"');
});
test('compose from: sends as the chosen address with the right display name', function () {
    $b = loggedIn(); sendForm($b, ['to' => 'x@example.org', 'from' => 'sales@example.com']);
    eq(fromOf(lastSubmit()), ['name' => 'Sales Team', 'address' => 'sales@example.com']);
    sendForm($b, ['to' => 'x@example.org', 'from' => 'ALICE.WORK@example.org']);
    eq(fromOf(lastSubmit()), ['name' => 'Test User', 'address' => 'alice.work@example.org'], 'identity without a name falls back to the profile name (case-insensitive match)');
    sendForm($b, ['to' => 'x@example.org', 'from' => '']);
    eq(fromOf(lastSubmit()), ['name' => 'Test User', 'address' => 'alice@example.com']);
});
test('compose from: a forged From address can never be used', function () {
    $b = loggedIn(); $n = submitCount();
    foreach (['ceo@evil.test', 'bob@example.com', "sales@example.com\r\nBcc: x@y.z", 'alice@example.com <ceo@evil.test>'] as $forged) {
        $r = sendForm($b, ['to' => 'x@example.org', 'from' => $forged]); eq($r['status'], 303, json_encode($forged));
        eq(lastSubmit()['from']['address'], 'alice@example.com', 'forged: ' . json_encode($forged));
    }
    eq(submitCount(), $n + 4);
});
test('compose from: replies use the alias the message was sent to', function () {
    $b = loggedIn(); $proj = inboxId(UID_A, 'Projects');
    $h = $b->get("/mail/$proj/4/reply")['body']; preg_match('#<select class="input" id="from".*?</select>#s', $h, $m); check(preg_match('#value="sales@example.com" selected#', $m[0]) === 1, 'sales preselected for a message sent to Sales@Example.com');
    sendForm($b, ['to' => 'frank@example.org'], "/mail/$proj/4/reply"); eq(fromOf(lastSubmit())['address'], 'sales@example.com');
    preg_match('#<select class="input" id="from".*?</select>#s', $b->get('/mail/inbox/5/reply')['body'], $m2); check(preg_match('#value="alice@example.com" selected#', $m2[0]) === 1, 'ordinary mail replies from the main address');
    preg_match('#<select class="input" id="from".*?</select>#s', $b->get("/mail/$proj/4/forward")['body'], $m3); check(str_contains($m3[0], 'value="sales@example.com" selected'), 'forward too');
});
test('compose from: choice survives validation errors and drafts', function () {
    $b = loggedIn();
    $r = sendForm($b, ['to' => '', 'from' => 'sales@example.com']); eq($r['status'], 422); check(str_contains($r['body'], 'value="sales@example.com" selected'), 're-render keeps From');
    sendForm($b, ['do' => 'draft', 'to' => '', 'subject' => 'D', 'from' => 'sales@example.com']); eq(fromOf(lastSubmit())['address'], 'sales@example.com');
    $drafts = inboxId(UID_A, 'Drafts'); $e = $b->get("/mail/$drafts/1/edit")['body']; check(str_contains($e, 'value="sales@example.com" selected'), 'draft reopens with its From');
});
test('compose from: an unavailable address list falls back to the main address', function () {
    $b = loggedIn(); flag('failAddresses'); $h = $b->get('/compose'); eq($h['status'], 200); lacks($h['body'], 'id="from"');
    $r = sendForm($b, ['to' => 'x@example.org', 'from' => 'sales@example.com']); eq($r['status'], 303);
    eq(fromOf(lastSubmit()), ['name' => 'Test User', 'address' => 'alice@example.com'], 'cannot verify the alias, so the main address is used');
});

test('autoreply: enable with dates, see it indicated everywhere, turn off keeping the text', function () {
    $b = loggedIn(); $h = $b->get('/settings/autoreply')['body']; lacks($h, 'name="status" value="1" checked'); lacks($b->get('/mail/inbox')['body'], 'Auto-reply on');
    $r = setPost($b, '/settings/autoreply', ['status' => '1', 'name' => 'Alice (away)', 'subject' => 'Out of office', 'text' => "I am away.\nBack soon <b>x</b>", 'start' => '2026-12-24', 'end' => '2026-12-31'], '/settings/autoreply');
    eq($r['status'], 303); $a = ar();
    eq($a['status'], true); eq($a['subject'], 'Out of office'); eq($a['name'], 'Alice (away)'); eq($a['start'], '2026-12-24T00:00:00.000Z'); eq($a['end'], '2026-12-31T23:59:59.999Z');
    has($a['html'], '&lt;b&gt;x&lt;/b&gt;'); lacks($a['html'], '<b>x</b>'); has($a['html'], '<br>'); has($a['text'], 'Back soon <b>x</b>');
    $page = $b->get('/settings/autoreply')['body']; has($page, 'checked'); has($page, 'value="2026-12-24"'); has($page, 'value="2026-12-31"'); has($page, 'Auto-reply is on.'); has($page, 'Back soon &lt;b&gt;x&lt;/b&gt;');
    $inbox = $b->get('/mail/inbox')['body']; has($inbox, 'Auto-reply on'); has($inbox, 'href="/settings/autoreply"');
    setPost($b, '/settings/autoreply', ['name' => 'Alice (away)', 'subject' => 'Out of office', 'text' => 'I am away.', 'start' => '', 'end' => ''], '/settings/autoreply');
    $a = ar(); eq($a['status'], false); eq($a['text'], 'I am away.', 'text kept when turned off'); eq($a['raw'], ['start' => false, 'end' => false], 'empty dates send false (no check)');
    lacks($b->get('/mail/inbox')['body'], 'Auto-reply on'); has($b->get('/settings/autoreply')['body'], 'I am away.');
});
test('autoreply: validation and escaping', function () {
    $b = loggedIn(); $post = fn (array $f) => setPost($b, '/settings/autoreply', $f + ['status' => '1', 'text' => 'Away'], '/settings/autoreply');
    has($post(['text' => '   '])['body'], 'Write the message');
    foreach (['2026-13-40', '24/12/2026', 'tomorrow', '2026-02-30', '1999-01-01'] as $bad) { $r = $post(['start' => $bad]); eq($r['status'], 422, $bad); has($r['body'], 'Dates must look like'); }
    $r = $post(['start' => '2026-12-31', 'end' => '2026-12-24']); eq($r['status'], 422); has($r['body'], 'must not be before');
    eq($post(['text' => str_repeat('x', 5001)])['status'], 422); eq($post(['subject' => str_repeat('s', 201)])['status'], 422); eq($post(['name' => str_repeat('n', 101)])['status'], 422);
    eq(ar(), [], 'nothing stored by invalid submissions');
    $r = $post(['start' => '2026-12-24']); eq($r['status'], 303); eq(ar()['raw']['end'], false); eq(ar()['start'], '2026-12-24T00:00:00.000Z');
    $r = $post(['text' => '<script>alert(1)</script>', 'subject' => "Line\r\nBcc: evil@x.y"]); eq($r['status'], 303);
    lacks(ar()['html'], '<script>'); has(ar()['html'], '&lt;script&gt;'); lacks(ar()['subject'], "\n");
    $keep = $post(['text' => 'keep', 'start' => 'bad']); has($keep['body'], 'value="bad"'); has($keep['body'], 'keep');
});
test('autoreply: server refusals and outages are reported, not hidden', function () {
    $b = loggedIn(); flag('failAutoreply');
    $r = setPost($b, '/settings/autoreply', ['status' => '1', 'text' => 'Away'], '/settings/autoreply'); eq($r['status'], 422); has($r['body'], 'nothing was saved'); has($r['body'], 'Away');
    $g = $b->get('/settings/autoreply'); eq($g['status'], 200); has($g['body'], 'could not be loaded'); flag('failAutoreply', false);
});
test('profile data is fetched once per window and refreshed after changes', function () {
    $b = loggedIn(); $u = 'GET /users/' . UID_A;
    $b->get('/mail/inbox'); $b->get('/mail/sent'); $b->get('/settings/profile'); $b->get('/contacts'); $b->get('/compose');
    eq(wdCalls($u), 1, 'one profile read serves several pages (name, prefs, auto-reply flag)');
    setPost($b, '/settings/profile', ['name' => 'Zed']); $page = $b->get('/settings/profile')['body']; eq(wdCalls($u), 2, 'a write clears the cache'); has($page, 'value="Zed"');
});
test('profile outage does not break mail pages or hammer the server', function () {
    $b = loggedIn(); flag('failUserGet'); $u = 'GET /users/' . UID_A;
    foreach (['/mail/inbox', '/mail/sent', '/compose', '/settings/profile', '/mail/inbox/5'] as $p) { eq($b->get($p)['status'], 200, $p); }
    lacks($b->get('/mail/inbox')['body'], 'Auto-reply on'); has($b->get('/settings/profile')['body'], 'could not be loaded');
    check(wdCalls($u) === 0, 'failed calls are not logged by the fake; the point is that pages kept working');
});

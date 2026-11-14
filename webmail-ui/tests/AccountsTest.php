<?php
declare(strict_types=1);

function acctMenu(string $html): string { preg_match('#<details class="account-menu">.*?</details>#s', $html, $m); return $m[0] ?? ''; }
/** @return array<string,string> address => session-local id, for the accounts that are NOT active */
function acctIds(string $html): array
{
    preg_match_all('#name="id" value="([A-Za-z0-9]+)">\s*<button class="account-row" type="submit"><span>([^<]+)</span>#', acctMenu($html), $m, PREG_SET_ORDER);
    $o = []; foreach ($m as $x) { $o[html_entity_decode($x[2])] = $x[1]; } return $o;
}
function activeAddr(string $html): string { preg_match('#class="account-row is-active" aria-current="true">.*?<span>([^<]+)</span>#s', acctMenu($html), $m); return html_entity_decode($m[1] ?? ''); }
function addAcct(Browser $b, string $u, string $p, string $tokenFrom = '/mail/inbox'): array { return $b->post('/accounts/add', ['username' => $u, 'password' => $p, '_token' => $b->pageToken($tokenFrom)], false); }
function switchTo(Browser $b, string $id): array { return $b->post('/accounts/switch', ['id' => $id, '_token' => $b->pageToken('/mail/inbox')], false); }
function twoAccounts(): Browser { $b = loggedIn(); eq(addAcct($b, 'bob@example.com', 'bob-pass')['status'], 303); return $b; }

test('accounts: adding an account needs its own credentials and makes it the active one', function () {
    $b = loggedIn(); $g = $b->get('/accounts/add'); eq($g['status'], 200); has($g['body'], 'action="/accounts/add"'); has($g['body'], 'Add another account'); has($g['body'], 'href="/mail/inbox">Cancel');
    $r = addAcct($b, 'bob@example.com', 'wrong'); eq($r['status'], 401); has($r['body'], 'Incorrect email address or password'); eq(activeAddr($b->get('/mail/inbox')['body']), 'alice@example.com'); eq(count(acctIds($b->get('/mail/inbox')['body'])), 0, 'nothing was added');
    $r = addAcct($b, 'bob@example.com', 'bob-pass'); eq($r['status'], 303); eq($r['headers']['location'], '/mail/inbox');
    $h = $b->get('/mail/inbox')['body']; has($h, 'Added bob@example.com. You are now using it.'); eq(activeAddr($h), 'bob@example.com'); eq(array_keys(acctIds($h)), ['alice@example.com']);
    has($h, 'Bob secret'); lacks($h, 'Weekly report'); has($h, 'href="/accounts/add"'); has($h, 'Sign out of this account'); has($h, 'Sign out of all accounts');
});
test('accounts: everything is isolated per account, even within the cache window', function () {
    $b = loggedIn(); contactForm($b, ['name' => 'Ann', 'email' => 'ann@example.org']); setPost($b, '/settings/autoreply', ['status' => '1', 'text' => 'Away'], '/settings/autoreply');
    $a = $b->get('/mail/inbox')['body']; has($a, 'href="/label/' . labelId('Work') . '"'); has($a, 'Auto-reply on'); has($b->get('/compose')['body'], 'id="from"');
    addAcct($b, 'bob@example.com', 'bob-pass'); $h = $b->get('/mail/inbox')['body'];
    lacks($h, '/label/'); lacks($h, 'Auto-reply on'); lacks($b->get('/compose')['body'], 'id="from"'); has($b->get('/contacts')['body'], 'No contacts yet'); lacks($b->get('/contacts')['body'], 'ann@example.org');
    eq($b->get('/mail/' . inboxId() . '/5')['status'], 404, "alice's mailbox ids do not exist for bob"); eq($b->get('/thread/' . THREAD)['status'], 404);
    $id = acctIds($h)['alice@example.com']; $r = switchTo($b, $id); eq($r['status'], 303);
    $h = $b->get('/mail/inbox')['body']; eq(activeAddr($h), 'alice@example.com'); has($h, 'Now using alice@example.com.'); has($h, 'href="/label/' . labelId('Work') . '"'); has($h, 'Auto-reply on'); has($b->get('/contacts')['body'], 'ann@example.org'); has($b->get('/mail/inbox/5')['body'], 'Weekly report #5');
});
test('accounts: mail is sent as the active account with its own token', function () {
    $b = twoAccounts(); sendForm($b, ['to' => 'x@example.org', 'subject' => 'from bob']); $s = lastSubmit(); eq($s['from']['address'], 'bob@example.com');
    $id = acctIds($b->get('/mail/inbox')['body'])['alice@example.com']; switchTo($b, $id); sendForm($b, ['to' => 'x@example.org', 'subject' => 'from alice']); eq(lastSubmit()['from']['address'], 'alice@example.com');
    has($b->get('/mail/sent')['body'], 'from alice'); lacks($b->get('/mail/sent')['body'], 'from bob');
});
test('accounts: switching only accepts accounts of this very session, and rotates the session id', function () {
    $b = twoAccounts(); $other = loggedIn('alice@example.com', 'correct-pass'); $bad = $b->get('/mail/inbox'); $alice = acctIds($bad['body'])['alice@example.com'];
    $t = $b->pageToken('/mail/inbox');
    foreach (['', 'nope', str_repeat('a', 40), '../x', '0', 'a' . str_repeat('0', 12)] as $forged) { $r = $b->post('/accounts/switch', ['id' => $forged, '_token' => $t], false); eq($r['status'], 303); has($b->get('/mail/inbox')['body'], 'not signed in here'); eq(activeAddr($b->get('/mail/inbox')['body']), 'bob@example.com', json_encode($forged)); }
    eq($b->get('/accounts/switch')['status'], 405); eq($b->post('/accounts/switch', ['id' => $alice], false)['status'], 419);
    $before = $b->get('/login')['headers']['set-cookie'] ?? ''; $r = $b->post('/accounts/switch', ['id' => $alice, '_token' => $t], false);
    check(preg_match('/wm_sid=\w+/', $r['headers']['set-cookie'] ?? '') === 1, 'a new session id is issued when the identity changes'); eq(activeAddr($b->get('/mail/inbox')['body']), 'alice@example.com');
    $stranger = new Browser(); eq($stranger->post('/accounts/switch', ['id' => $alice], false)['status'], 419); eq($stranger->get('/accounts/add')['status'], 303);
});
test('accounts: adding the same account again refreshes it instead of duplicating it', function () {
    $b = twoAccounts(); $r = addAcct($b, 'alice@example.com', 'correct-pass'); eq($r['status'], 303); $h = $b->get('/mail/inbox')['body'];
    has($h, 'Refreshed the sign-in for alice@example.com.'); eq(activeAddr($h), 'alice@example.com'); eq(array_keys(acctIds($h)), ['bob@example.com']);
});
test('accounts: at most five accounts per session; the sixth is refused and its token is revoked', function () {
    $b = loggedIn(); foreach ([['bob@example.com', 'bob-pass'], ['pwtest@example.com', 'pw-pass'], ['custom2fa@example.com', 'custom-pass'], ['acct5@example.com', 'acct-pass']] as [$u, $p]) { eq(addAcct($b, $u, $p)['status'], 303, $u); }
    $h = $b->get('/mail/inbox')['body']; eq(count(acctIds($h)), 4); lacks($h, 'href="/accounts/add"'); eq(activeAddr($h), 'acct5@example.com');
    $g = $b->get('/accounts/add'); eq($g['status'], 303); eq($g['headers']['location'], '/mail/inbox'); has($b->get('/mail/inbox')['body'], 'up to 5 accounts');
    $r = addAcct($b, 'acct6@example.com', 'acct-pass'); eq($r['status'], 303); has($b->get('/mail/inbox')['body'], 'up to 5 accounts'); eq(activeAddr($b->get('/mail/inbox')['body']), 'acct5@example.com');
    check(in_array('user-token-acct6', Servers::wdState()['revoked'], true), 'the unused token must not be left valid');
    eq(addAcct($b, 'alice@example.com', 'correct-pass')['status'], 303); has($b->get('/mail/inbox')['body'], 'Refreshed', 'an account that is already signed in can still be refreshed at the cap');
});
test('accounts: a 2FA account is added only after its code; cancelling leaves nothing behind', function () {
    $b = loggedIn(); Servers::resetWd(); $b = new Browser(); $b->login(); $r = addAcct($b, 'twofa@example.com', 'twofa-pass'); eq($r['status'], 303); eq($r['headers']['location'], '/login/2fa');
    $p = $b->get('/login/2fa'); eq($p['status'], 200); has($p['body'], 'href="/mail/inbox">Cancel'); eq(activeAddr($b->get('/mail/inbox')['body']), 'alice@example.com', 'still alice until the code is verified'); eq(count(acctIds($b->get('/mail/inbox')['body'])), 0);
    eq(codeStep($b, '000000')['status'], 401); $r = codeStep($b, '123456'); eq($r['status'], 303); eq($r['headers']['location'], '/mail/inbox');
    $h = $b->get('/mail/inbox')['body']; eq(activeAddr($h), 'twofa@example.com'); eq(array_keys(acctIds($h)), ['alice@example.com']); has($h, 'Added twofa@example.com');
    Servers::resetWd(); $c = new Browser(); $c->login(); addAcct($c, 'twofa@example.com', 'twofa-pass'); $c->get('/accounts/add'); $n = count(attempts()); $r = Browser::raw('POST', Servers::APP . '/login/2fa', ['code' => '123456', '_token' => $c->pageToken('/mail/inbox')], [], (new ReflectionProperty($c, 'jar'))->getValue($c)); eq($r['headers']['location'], '/mail/inbox', 'going back to the add form abandons the pending sign-in'); eq(count(attempts()), $n, 'WildDuck was not asked to verify anything'); eq(count(acctIds($c->get('/mail/inbox')['body'])), 0, 'no second account appeared');
    $d = new Browser(); $d->login(); $r = addAcct($d, 'webauthn@example.com', 'wa-pass'); eq($r['status'], 403); has($r['body'], 'security key'); eq(count(acctIds($d->get('/mail/inbox')['body'])), 0);
});
test('accounts: sign out of one account keeps the others signed in', function () {
    $b = twoAccounts(); $r = $b->post('/logout', ['_token' => $b->pageToken()], false); eq($r['status'], 303); eq($r['headers']['location'], '/mail/inbox');
    check(in_array('user-token-bob', Servers::wdState()['revoked'], true), "bob's token is revoked"); $h = $b->get('/mail/inbox')['body']; has($h, 'Signed out of bob@example.com. You are now using alice@example.com.'); eq(activeAddr($h), 'alice@example.com'); eq(count(acctIds($h)), 0); check(preg_match('#<button[^>]*>\\s*<svg.*?</svg> Sign out</button>#s', $h) === 1, 'plain Sign out button');
    addAcct($b, 'bob@example.com', 'bob-pass'); $alice = acctIds($b->get('/mail/inbox')['body'])['alice@example.com'];
    $r = $b->post("/accounts/$alice/signout", ['_token' => $b->pageToken()], false); eq($r['status'], 303); check(in_array('user-token-alice', Servers::wdState()['revoked'], true), 'a non-active account can be signed out too'); eq(activeAddr($b->get('/mail/inbox')['body']), 'bob@example.com'); eq(count(acctIds($b->get('/mail/inbox')['body'])), 0);
    $r = $b->post('/accounts/zzzz/signout', ['_token' => $b->pageToken()], false); has($b->get('/mail/inbox')['body'], 'not signed in here'); eq($b->post("/accounts/$alice/signout", [], false)['status'], 419);
    $r = $b->post('/logout', ['_token' => $b->pageToken()], false); eq($r['headers']['location'], '/login'); eq($b->get('/mail/inbox')['status'], 303, 'the last account signs the session out');
});
test('accounts: "sign out of all" revokes every token and ends the session', function () {
    $b = twoAccounts(); $r = $b->post('/logout/all', ['_token' => $b->pageToken()], false); eq($r['status'], 303); eq($r['headers']['location'], '/login');
    $rev = Servers::wdState()['revoked']; check(in_array('user-token-alice', $rev, true) && in_array('user-token-bob', $rev, true), 'both tokens revoked'); eq($b->get('/mail/inbox')['status'], 303); has($b->get('/login')['body'], 'signed out of all accounts');
    eq($b->post('/logout/all', [], false)['status'], 419);
});
test('accounts: one account expiring does not end the others', function () {
    $b = twoAccounts(); Browser::raw('DELETE', Servers::WD . '/authenticate', null, ['X-Access-Token: user-token-bob']);
    $r = $b->get('/mail/inbox'); eq($r['status'], 303); eq($r['headers']['location'], '/mail/inbox', 'falls back to the other account instead of signing everything out');
    $h = $b->get('/mail/inbox')['body']; has($h, 'session for bob@example.com has expired'); eq(activeAddr($h), 'alice@example.com'); has($h, 'Weekly report'); eq(count(acctIds($h)), 0);
    addAcct($b, 'bob@example.com', 'bob-pass'); Browser::raw('DELETE', Servers::WD . '/authenticate', null, ['X-Access-Token: user-token-alice']); // the NON-active one dies
    $alice = acctIds($b->get('/mail/inbox')['body'])['alice@example.com']; eq(activeAddr($b->get('/mail/inbox')['body']), 'bob@example.com'); switchTo($b, $alice);
    $r = $b->get('/mail/inbox'); eq($r['status'], 303); $h = $b->get('/mail/inbox')['body']; has($h, 'session for alice@example.com has expired'); eq(activeAddr($h), 'bob@example.com'); has($h, 'Bob secret');
});
test('accounts: the poll reports unread counts of the other accounts without leaking ids or tokens', function () {
    $b = twoAccounts(); $h = $b->get('/mail/inbox')['body']; $alice = acctIds($h)['alice@example.com']; $d = pj(poll($b));
    eq($d['inbox'], ['unseen' => 1, 'total' => 1]); eq($d['others'], [$alice => 7]); $raw = poll($b)['body']; foreach ([UID_A, 'a1a1a1', 'user-token', 'alice@example.com'] as $leak) { lacks($raw, $leak); }
    switchTo($b, $alice); $bob = acctIds($b->get('/mail/inbox')['body'])['bob@example.com']; eq(pj(poll($b))['others'], [$bob => 1]);
    $mb = 'GET /users/' . UID_A . '/mailboxes'; $bobMb = 'GET /users/b2b2b2b2b2b2b2b2b2b2b2b2/mailboxes'; $a0 = wdCalls($mb); $b0 = wdCalls($bobMb); poll($b); eq(wdCalls($mb) - $a0, 1); eq(wdCalls($bobMb) - $b0, 1, 'one call per account per poll');
    Browser::raw('DELETE', Servers::WD . '/authenticate', null, ['X-Access-Token: user-token-bob']); $r = poll($b); eq($r['status'], 200); eq(pj($r)['others'], [], 'an expired other account is left out');
    $single = loggedIn(); eq(pj(poll($single))['others'], [], 'no others with one account');
});
test('accounts: the poll looks at four other accounts at most', function () {
    $b = loggedIn(); foreach ([['bob@example.com', 'bob-pass'], ['pwtest@example.com', 'pw-pass'], ['custom2fa@example.com', 'custom-pass'], ['acct5@example.com', 'acct-pass']] as [$u, $p]) { addAcct($b, $u, $p); }
    $d = pj(poll($b)); eq(count($d['others']), 4); eq(count(array_unique(array_keys($d['others']))), 4);
});
test('accounts: pages show the switcher state correctly', function () {
    $b = loggedIn(); $h = $b->get('/mail/inbox')['body']; lacks($h, 'data-multi'); lacks($h, 'data-account-badge'); check(preg_match('#<button[^>]*>\\s*<svg.*?</svg> Sign out</button>#s', $h) === 1, 'plain Sign out button'); lacks($h, 'Sign out of all accounts'); has($h, 'href="/accounts/add"');
    addAcct($b, 'bob@example.com', 'bob-pass'); $h = $b->get('/mail/inbox')['body']; has($h, '<body data-notify data-multi'); has($h, 'data-account-badge="'); has($h, 'data-others-total');
    foreach ([UID_A, 'b2b2b2b2b2b2b2b2b2b2b2b2', 'user-token'] as $leak) { lacks($h, $leak); }
    preg_match_all('#<form method="post" action="/accounts/switch"><input type="hidden" name="_token" value="([a-f0-9]{64})">#', $h, $m); eq(count($m[1]), 1, 'switch forms carry a CSRF token');
    eq(Browser::raw('GET', Servers::APP . '/accounts/add')['status'], 303); eq(Browser::raw('GET', Servers::APP . '/login/2fa')['status'], 303);
});
test('accounts: the CSRF token is renewed whenever a sign-in happens (login and add-account)', function () {
    Servers::resetWd(); $b = new Browser(); $pre = $b->csrf('/login'); $b->post('/login', ['username' => 'alice@example.com', 'password' => 'correct-pass', '_token' => $pre], false);
    $afterLogin = $b->pageToken('/mail/inbox'); check($pre !== $afterLogin, 'the token seen before signing in must not survive the sign-in');
    $r = $b->post('/accounts/add', ['username' => 'bob@example.com', 'password' => 'bob-pass', '_token' => $afterLogin], false); eq($r['status'], 303);
    check($afterLogin !== $b->pageToken('/mail/inbox'), 'adding an account also renews it');
    eq($b->post('/accounts/switch', ['id' => 'x', '_token' => $pre], false)['status'], 419, 'the pre-login token is dead');
});

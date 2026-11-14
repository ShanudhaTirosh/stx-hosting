<?php
declare(strict_types=1);

use Webmail\Core\{Auth, Crypto, Session};

function pwStep(Browser $b, string $u = 'twofa@example.com', string $p = 'twofa-pass'): array { Servers::resetWd(); return $b->post('/login', ['username' => $u, 'password' => $p]); }
function pwStepKeep(Browser $b, string $u, string $p): array { return $b->post('/login', ['username' => $u, 'password' => $p]); }
function codeStep(Browser $b, string $code): array { return $b->post('/login/2fa', ['code' => $code], true, '/login/2fa'); }
function attempts(): array { return Servers::wdState()['totpAttempts']; }
function nonceOf(string $uid): string { return Servers::wdState()['nonces'][$uid]['n']; }
const UID_2FA = 'c3c3c3c3c3c3c3c3c3c3c3c3';

test('unit: pending 2FA state is server-side, encrypted, and expires after five minutes', function () {
    $_SESSION = []; $a = new Auth(new Session(sys_get_temp_dir(), 60, false), new Crypto(base64_encode(str_repeat('z', 32))));
    $a->startTwoFactor('a1a1a1a1a1a1a1a1a1a1a1a1', 'x@example.com', 'secretnonce123', 1000);
    lacks(json_encode($_SESSION), 'secretnonce123'); eq($a->pendingTwoFactor(1299)['nonce'], 'secretnonce123'); eq($a->pendingTwoFactor(1299)['address'], 'x@example.com');
    eq($a->twoFactorFailed(), 1); eq($a->twoFactorFailed(), 2); eq($a->pendingTwoFactor(1300)['fails'], 2);
    eq($a->pendingTwoFactor(1301), null, 'expired'); eq($a->pendingTwoFactor(1000), null, 'and removed'); check(!$a->check(), 'never authenticated');
    $a->startTwoFactor('a1a1a1a1a1a1a1a1a1a1a1a1', 'x@example.com', null, 5); eq($a->pendingTwoFactor(6)['nonce'], null); $a->clearTwoFactor(); eq($a->pendingTwoFactor(6), null);
    $_SESSION = [];
});

test('2fa: the password step leads to the code page, not to a session', function () {
    $b = new Browser(); $r = pwStep($b); eq($r['status'], 303); eq($r['headers']['location'], '/login/2fa');
    eq($b->get('/mail/inbox')['status'], 303); eq($b->get('/mail/inbox')['headers']['location'], '/login');
    $p = $b->get('/login/2fa'); eq($p['status'], 200); $h = $p['body'];
    has($h, 'autocomplete="one-time-code"'); has($h, 'inputmode="numeric"'); has($h, 'twofa@example.com'); has($h, 'name="_token"');
    $nonce = nonceOf(UID_2FA); foreach ([$nonce, 'user-token', 'svc-token'] as $secret) { lacks($h, $secret); }
    lacks($p['cookies'], $nonce); lacks($p['cookies'], 'user-token');
    check(!preg_match('/<script(?![^>]*src=)/i', $h), 'no inline script'); has($p['headers']['content-security-policy'], "default-src 'none'");
});
test('2fa: the right code signs in (spaces tolerated) with the real token', function () {
    $b = new Browser(); pwStep($b); $nonce = nonceOf(UID_2FA);
    $r = codeStep($b, '123 456'); eq($r['status'], 303); eq($r['headers']['location'], '/mail/inbox');
    eq($b->get('/mail/inbox')['status'], 200); has($b->get('/mail/inbox')['body'], 'twofa@example.com');
    $all = attempts(); $a = end($all); eq($a['token'], '123456', 'spaces stripped'); eq($a['nonce'], $nonce, 'the nonce from step 1 is sent'); eq($b->get('/login/2fa')['headers']['location'], '/mail/inbox');
    $t = $b->pageToken(); eq($b->post('/logout', ['_token' => $t], false)['status'], 303); check(in_array('user-token-2fa', Servers::wdState()['revoked'], true), 'logout still revokes');
});
test('2fa: wrong or malformed codes are refused, and malformed ones never reach WildDuck', function () {
    $b = new Browser(); pwStep($b); $n = count(attempts());
    foreach (['', '12345', '1234567', 'abcdef', '12 34 5x', '١٢٣٤٥٦'] as $bad) { $r = codeStep($b, $bad); eq($r['status'], 422, json_encode($bad)); has($r['body'], '6-digit code'); }
    eq(count(attempts()), $n, 'no WildDuck call for malformed codes');
    $r = codeStep($b, '654321'); eq($r['status'], 401); has($r['body'], 'not accepted'); lacks($r['body'], '654321'); lacks($r['body'], 'TotpFailed'); eq($b->get('/mail/inbox')['status'], 303);
    $r = codeStep($b, '123456'); eq($r['status'], 303, 'a retry in the same sign-in works'); eq($b->get('/mail/inbox')['status'], 200);
});
test('2fa: a stale nonce (a newer sign-in started elsewhere) cannot be completed', function () {
    $a = new Browser(); pwStep($a); $b = new Browser(); pwStepKeep($b, 'twofa@example.com', 'twofa-pass');
    $r = codeStep($a, '123456'); eq($r['status'], 401, 'the first sign-in lost its nonce'); eq($a->get('/mail/inbox')['status'], 303);
    eq(codeStep($b, '123456')['status'], 303); eq($b->get('/mail/inbox')['status'], 200);
});
test('2fa: an unverified token from a compatibility-mode server is never used', function () {
    $b = new Browser(); Servers::resetWd(); flag('compat2fa');
    $r = $b->post('/login', ['username' => 'twofa@example.com', 'password' => 'twofa-pass']); eq($r['status'], 303); eq($r['headers']['location'], '/login/2fa');
    eq($b->get('/mail/inbox')['status'], 303, 'password alone must not open the mailbox even though WildDuck returned a token');
    eq(codeStep($b, '123456')['status'], 303); eq($b->get('/mail/inbox')['status'], 200);
});
test('2fa: security-key-only accounts are told why; mixed accounts can use a code; custom-only needs no code', function () {
    $b = new Browser(); $r = pwStep($b, 'webauthn@example.com', 'wa-pass'); eq($r['status'], 403); has($r['body'], 'security key'); eq($b->get('/login/2fa')['headers']['location'], '/login');
    $b = new Browser(); $r = pwStep($b, 'multi@example.com', 'multi-pass'); eq($r['status'], 303); eq($r['headers']['location'], '/login/2fa');
    $b = new Browser(); $r = pwStep($b, 'custom2fa@example.com', 'custom-pass'); eq($r['status'], 303); eq($r['headers']['location'], '/mail/inbox'); eq($b->get('/mail/inbox')['status'], 200);
    $b = new Browser(); eq(pwStep($b, 'twofa@example.com', 'wrong')['status'], 401); eq($b->get('/login/2fa')['status'], 303, 'wrong password never reaches the code step');
});
test('2fa: the code page is only reachable mid sign-in, needs CSRF, and the password page resets it', function () {
    $b = new Browser(); Servers::resetWd(); $r = $b->get('/login/2fa'); eq($r['status'], 303); eq($r['headers']['location'], '/login'); has($b->get('/login')['body'], 'sign-in expired');
    eq($b->post('/login/2fa', ['code' => '123456'], true, '/login')['status'], 303, 'no pending sign-in'); eq(count(attempts()), 0);
    pwStep($b); eq($b->post('/login/2fa', ['code' => '123456'], false)['status'], 419);
    $b->get('/login'); $r = codeStep2($b); eq($r['status'], 303); eq($r['headers']['location'], '/login'); eq(count(attempts()), 0, 'going back to the password form abandons the pending sign-in');
    $other = new Browser(); pwStep($b); eq($other->post('/login/2fa', ['code' => '123456'], true, '/login')['status'], 303); eq($other->get('/mail/inbox')['status'], 303, "another browser cannot use someone else's pending sign-in");
    eq(Browser::raw('GET', Servers::APP . '/login/2fa')['status'], 303);
});
function codeStep2(Browser $b): array { return Browser::raw('POST', Servers::APP . '/login/2fa', ['code' => '123456', '_token' => $b->csrf('/login')], [], (new ReflectionProperty($b, 'jar'))->getValue($b)); }
test('2fa: five wrong codes in one sign-in end it; guessing across sessions is limited per account', function () {
    $a = new Browser(); pwStep($a, 'multi@example.com', 'multi-pass');
    for ($i = 1; $i <= 4; $i++) { eq(codeStep($a, '00000' . $i)['status'], 401, "attempt $i"); }
    $r = codeStep($a, '000009'); eq($r['status'], 303); eq($r['headers']['location'], '/login'); has($a->get('/login')['body'], 'Too many incorrect codes');
    eq(codeStep2($a)['headers']['location'], '/login', 'the pending sign-in is gone');
    Servers::resetWd(); $x = new Browser(); pwStepKeep($x, 'lock2fa@example.com', 'lock-pass'); $y = new Browser(); pwStepKeep($y, 'lock2fa@example.com', 'lock-pass');
    for ($i = 1; $i <= 3; $i++) { eq(codeStep($x, "11111$i")['status'], 401); } for ($i = 1; $i <= 2; $i++) { eq(codeStep($y, "22222$i")['status'], 401); }
    $z = new Browser(); pwStepKeep($z, 'lock2fa@example.com', 'lock-pass'); $r = codeStep($z, '123456');
    eq($r['status'], 429, 'five failures spread over sessions lock the account step, even for the right code'); check((int) ($r['headers']['retry-after'] ?? 0) > 0, 'Retry-After'); has($r['body'], 'Too many incorrect codes'); eq($z->get('/mail/inbox')['status'], 303);
    $before = count(attempts()); eq(codeStep($x, '123456')['status'], 429); eq($x->get('/mail/inbox')['status'], 303); eq(count(attempts()), $before, 'locked: WildDuck is not asked at all');
});
test('2fa: an outage during verification is reported without losing the sign-in', function () {
    $b = new Browser(); pwStep($b); flag('failTotp'); $r = codeStep($b, '123456'); eq($r['status'], 503); has($r['body'], 'temporarily unavailable'); lacks($r['body'], 'InternalError');
    flag('failTotp', false); eq(codeStep($b, '123456')['status'], 303); eq($b->get('/mail/inbox')['status'], 200);
});

test('unit: AuthService never hands out a token before the second factor, even if WildDuck does', function () {
    Servers::resetWd(); flag('compat2fa');
    $svc = new Webmail\WildDuck\AuthService(new Webmail\WildDuck\Client(Servers::WD, 'svc-token', 3));
    $r = $svc->login('twofa@example.com', 'twofa-pass', '127.0.0.1', 'sess');
    eq($r['token'], null, 'the unverified token is dropped'); eq($r['twoFactor']['methods'], ['totp']); check(strlen((string) $r['twoFactor']['nonce']) > 8, 'nonce kept for step 2');
    flag('compat2fa', false); $r = $svc->login('alice@example.com', 'correct-pass', '127.0.0.1', 'sess'); eq($r['twoFactor'], null); eq($r['token'], 'user-token-alice');
    $r = $svc->login('custom2fa@example.com', 'custom-pass', '127.0.0.1', 'sess'); eq($r['twoFactor'], null, '"custom" 2FA is verified by WildDuck itself'); eq($r['token'], 'user-token-custom');
    $t = $svc->verifyTotp(UID_2FA, '123456', $svc->login('twofa@example.com', 'twofa-pass', '127.0.0.1', 's')['twoFactor']['nonce'], '127.0.0.1', 's'); eq($t, 'user-token-2fa');
    try { $svc->verifyTotp(UID_2FA, '123456', 'wrong-nonce', '127.0.0.1', 's'); check(false, 'wrong nonce accepted'); } catch (Webmail\WildDuck\AuthFailedException) {}
});

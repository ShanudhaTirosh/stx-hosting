<?php
declare(strict_types=1);

function inboxId(string $user = 'a1a1a1a1a1a1a1a1a1a1a1a1', string $name = 'INBOX'): string { return substr(hash('sha1', $user . $name), 0, 24); }
function loggedIn(string $u = 'alice@example.com', string $p = 'correct-pass'): Browser
{
    Servers::resetWd();
    $b = new Browser();
    $r = $b->login($u, $p);
    eq($r['status'], 303, 'login should redirect');
    return $b;
}
function navCounts(string $html): array
{
    preg_match_all('#<a class="nav-item[^"]*"[^>]*>.*?<span class="nav-label">([^<]+)</span>\s*(?:<span class="nav-count">(\d+)</span>)?\s*</a>#s', $html, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $x) { $out[] = [$x[1], isset($x[2]) && $x[2] !== '' ? (int) $x[2] : 0]; }
    return $out;
}

test('flow: health endpoint is public and reveals nothing', function () {
    $r = (new Browser())->get('/health');
    eq($r['status'], 200); eq($r['body'], '{"ok":true}');
});
test('flow: unknown path is a friendly 404; protected pages redirect to login', function () {
    $b = new Browser();
    eq($b->get('/nope')['status'], 404);
    $r = $b->get('/mail/inbox'); eq($r['status'], 303); eq($r['headers']['location'], '/login');
    eq($b->get('/search?q=a')['status'], 303);
    eq($b->get('/mail/inbox/1/body')['status'], 303);
    eq($b->get('/mail/inbox/1/attachment/ATT00001')['status'], 303);
});
test('flow: security headers on every page', function () {
    $r = (new Browser())->get('/login');
    $h = $r['headers'];
    has($h['content-security-policy'], "default-src 'none'"); has($h['content-security-policy'], "frame-ancestors 'none'");
    lacks($h['content-security-policy'], 'unsafe-inline'); lacks($h['content-security-policy'], 'unsafe-eval');
    eq($h['x-content-type-options'], 'nosniff'); eq($h['x-frame-options'], 'DENY'); eq($h['referrer-policy'], 'no-referrer');
    has($h['cache-control'], 'no-store');
    has($h['set-cookie'] ?? '', 'HttpOnly'); has($h['set-cookie'] ?? '', 'SameSite=Lax');
    check(!preg_match('/<script(?![^>]*src=)/i', $r['body']), 'no inline scripts allowed under CSP');
    check(!preg_match('/\son\w+="/i', $r['body']), 'no inline event handlers');
});
test('flow: login without CSRF token is rejected (419)', function () {
    $b = new Browser();
    eq($b->post('/login', ['username' => 'alice@example.com', 'password' => 'correct-pass'], false)['status'], 419);
    $r = Browser::raw('POST', Servers::APP . '/login', ['username' => 'alice@example.com', 'password' => 'correct-pass', '_token' => str_repeat('0', 64)], [], null);
    eq($r['status'], 419);
});
test('flow: wrong password gives a generic 401 and no session', function () {
    Servers::resetWd();
    $b = new Browser();
    $r = $b->post('/login', ['username' => 'alice@example.com', 'password' => 'nope']);
    eq($r['status'], 401); has($r['body'], 'Incorrect email address or password.');
    $r2 = $b->post('/login', ['username' => 'ghost@example.com', 'password' => 'nope']);
    eq($r2['status'], 401);
    eq(preg_replace('/value="[^"]*"/', '', $r['body']) === preg_replace('/value="[^"]*"/', '', $r2['body']), true, 'must not reveal whether the account exists');
    eq($b->get('/mail/inbox')['status'], 303);
});
test('flow: empty and oversize credentials are validated', function () {
    $b = new Browser();
    eq($b->post('/login', ['username' => '', 'password' => ''])['status'], 422);
    eq($b->post('/login', ['username' => str_repeat('a', 300) . '@x.com', 'password' => 'x'])['status'], 422);
});
test('flow: successful login rotates the session id (fixation) and redirects to inbox', function () {
    Servers::resetWd();
    $b = new Browser();
    $pre = $b->get('/login');
    preg_match('/wm_sid=(\w+)/', $pre['headers']['set-cookie'] ?? '', $m1);
    $tok = $b->csrf();
    $jar = (new ReflectionProperty($b, 'jar'))->getValue($b);
    $r = Browser::raw('POST', Servers::APP . '/login', ['username' => 'alice@example.com', 'password' => 'correct-pass', '_token' => $tok], [], $jar);
    eq($r['status'], 303); eq($r['headers']['location'], '/mail/inbox');
    preg_match('/wm_sid=(\w+)/', $r['headers']['set-cookie'] ?? '', $m2);
    check(isset($m1[1], $m2[1]) && $m1[1] !== $m2[1], 'session id must change on login');
    eq($b->get('/mail/inbox')['status'], 200, 'new session id is the authenticated one');
});
test('flow: inbox lists newest first with 30 per page and cursor pagination', function () {
    $b = loggedIn();
    $r = $b->get('/mail/inbox'); eq($r['status'], 200);
    has($r['body'], '>Invoice attached<'); has($r['body'], '>XSS probe<'); has($r['body'], '>Weekly report #8<');
    lacks($r['body'], '>Weekly report #7<');
    eq(substr_count($r['body'], 'class="msg-row'), 30);
    preg_match('#href="(/mail/inbox\?[^"]*dir=next[^"]*)"#', html_entity_decode($r['body']), $m);
    check(isset($m[1]), 'Older link present');
    $p2 = $b->get($m[1]); eq($p2['status'], 200);
    has($p2['body'], '>Weekly report #7<'); has($p2['body'], '>Weekly report #1<'); lacks($p2['body'], '>Invoice attached<');
    eq(substr_count($p2['body'], 'class="msg-row'), 7);
    has(html_entity_decode($p2['body']), 'dir=prev');
});
test('flow: system folders are in a fixed order with correct unread badges', function () {
    $b = loggedIn();
    $c = navCounts($b->get('/mail/inbox')['body']);
    eq(array_slice(array_column($c, 0), 0, 7), ['Inbox', 'Drafts', 'Sent', 'Archive', 'Spam', 'Trash', 'Projects']);
    eq($c[0][1], 7, 'inbox unread');
});
test('flow: unread-only filter', function () {
    $b = loggedIn();
    $r = $b->get('/mail/inbox?unseen=1');
    has($r['body'], '>XSS probe<'); lacks($r['body'], '>Weekly report #10<');
    eq(substr_count($r['body'], 'class="msg-row is-unread"'), 7);
});
test('flow: empty folder shows empty state', function () {
    $b = loggedIn();
    $r = $b->get('/mail/sent'); eq($r['status'], 200); has($r['body'], 'Nothing here yet');
});
test('flow: opening a message marks it read and refreshes the badge', function () {
    $b = loggedIn();
    $r = $b->get('/mail/inbox/1'); eq($r['status'], 200);
    has($r['body'], 'Weekly report #1');
    eq(navCounts($r['body'])[0][1], 6);
});
test('flow: message page never exposes API internals or secrets', function () {
    $b = loggedIn();
    $inbox = inboxId();
    $r = $b->get("/mail/inbox/37");
    foreach (['/users/', 'a1a1a1a1a1a1a1a1a1a1a1a1', 'user-token-alice', 'svc-token', '18080', 'X-Access-Token'] as $leak) { lacks($r['body'], $leak, "leak: $leak"); }
    lacks($r['cookies'], 'user-token-alice'); lacks($r['cookies'], 'a1a1a1a1a1a1a1a1a1a1a1a1');
    has($r['body'], 'invoice.pdf'); has($r['body'], 'evil.html'); lacks($r['body'], 'logo.png');
    has($r['body'], 'sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"');
    has($r['body'], "/mail/$inbox/37/body");
});
test('flow: message body is sanitised, sandboxed and CSP-locked; images blocked until allowed', function () {
    $b = loggedIn();
    $inbox = inboxId();
    $r = $b->get("/mail/$inbox/36/body"); eq($r['status'], 200);
    $csp = $r['headers']['content-security-policy'];
    has($csp, 'sandbox'); has($csp, "default-src 'none'"); lacks($csp, 'script-src'); lacks($csp, 'https:');
    foreach (['<script', 'onclick', 'onerror', 'javascript:', '<iframe', '<form', '<input', 'tracker.evil.test', 'url('] as $bad) { lacks(strtolower($r['body']), $bad, "body leaked $bad"); }
    has($r['body'], 'data-blocked');
    $allowed = $b->get("/mail/$inbox/36/body?images=1");
    has($allowed['headers']['content-security-policy'], 'img-src');
    has($allowed['headers']['content-security-policy'], 'https:');
    has($allowed['body'], 'tracker.evil.test'); lacks(strtolower($allowed['body']), 'onerror');
    // the host page offers an explicit opt-in
    has($b->get('/mail/inbox/36')['body'], 'Remote images are blocked');
});
test('flow: inline cid images are served through our proxy', function () {
    $b = loggedIn(); $inbox = inboxId();
    $body = $b->get("/mail/$inbox/37/body")['body'];
    has($body, "/mail/$inbox/37/attachment/ATT00001?inline=1"); lacks($body, '/users/');
    $img = $b->get("/mail/$inbox/37/attachment/ATT00001?inline=1");
    eq($img['status'], 200); eq($img['headers']['content-type'], 'image/png'); has($img['headers']['content-disposition'], 'inline');
});
test('flow: attachments download safely (never rendered as html)', function () {
    $b = loggedIn(); $inbox = inboxId();
    $pdf = $b->get("/mail/$inbox/37/attachment/ATT00002");
    eq($pdf['status'], 200); eq($pdf['headers']['content-type'], 'application/octet-stream');
    has($pdf['headers']['content-disposition'], 'attachment'); has($pdf['headers']['content-disposition'], 'invoice.pdf');
    eq($pdf['headers']['x-content-type-options'], 'nosniff'); eq($pdf['body'], '%PDF-1.4 fake');
    $html = $b->get("/mail/$inbox/37/attachment/ATT00003");
    eq($html['headers']['content-type'], 'application/octet-stream'); has($html['headers']['content-disposition'], 'attachment');
    has($html['headers']['content-security-policy'], 'sandbox');
    has($b->get("/mail/$inbox/37/attachment/ATT00001")['headers']['content-disposition'], 'attachment');
    eq($b->get("/mail/$inbox/37/attachment/ATT99999")['status'], 404);
});
test('flow: identifiers are validated; other users mailboxes are unreachable', function () {
    $b = loggedIn();
    $bobInbox = inboxId('b2b2b2b2b2b2b2b2b2b2b2b2');
    eq($b->get("/mail/$bobInbox")['status'], 404);
    eq($b->get("/mail/$bobInbox/1")['status'], 404);
    eq($b->get("/mail/$bobInbox/1/body")['status'], 404);
    eq($b->get("/mail/$bobInbox/1/attachment/ATT00001")['status'], 404);
    foreach (['/mail/inbox/abc', '/mail/inbox/0', '/mail/inbox/-1', '/mail/inbox/99999999999', '/mail/nonexistent', '/mail/inbox/1/attachment/ATT1x', '/mail/inbox/1/attachment/..%2f..%2fx'] as $p) {
        eq($b->get($p)['status'], 404, $p);
    }
    $t = $b->pageToken();
    $r = $b->post("/mail/$bobInbox/1/action", ['do' => 'trash', '_token' => $t]);
    eq($r['status'], 404);
    has(Browser::raw('GET', Servers::WD . '/__state')['body'], 'log'); // fake still alive
});
test('flow: star toggles and open-redirect via return is ignored', function () {
    $b = loggedIn(); $inbox = inboxId();
    $t = $b->pageToken();
    lacks($b->get('/mail/inbox')['body'], 'msg-star is-on');
    $r = $b->post("/mail/$inbox/8/action", ['do' => 'star', 'return' => '/mail/inbox', '_token' => $t]);
    eq($r['status'], 303); eq($r['headers']['location'], '/mail/inbox');
    has($b->get('/mail/inbox')['body'], 'msg-star is-on');
    foreach (['//evil.test', 'https://evil.test/x', '/\\evil.test'] as $bad) {
        $r = $b->post("/mail/$inbox/8/action", ['do' => 'unstar', 'return' => $bad, '_token' => $t]);
        eq($r['headers']['location'], '/mail/inbox/8', 'return=' . $bad);
    }
});
test('flow: actions require a valid CSRF token', function () {
    $b = loggedIn(); $inbox = inboxId();
    $r = Browser::raw('POST', Servers::APP . "/mail/$inbox/8/action", ['do' => 'trash'], [], tempnam(sys_get_temp_dir(), 'x'));
    eq($r['status'], 419);
    eq($b->post("/mail/$inbox/8/action", ['do' => 'trash', '_token' => 'bad'], false)['status'], 419);
    eq($b->post('/logout', ['_token' => 'bad'], false)['status'], 419);
    eq(navCounts($b->get('/mail/inbox')['body'])[0][1], 7, 'nothing changed');
});
test('flow: archive, spam, trash, and permanent delete rules', function () {
    $b = loggedIn(); $inbox = inboxId(); $t = $b->pageToken();
    $r = $b->post("/mail/$inbox/2/action", ['do' => 'archive', '_token' => $t]); eq($r['status'], 303); eq($r['headers']['location'], '/mail/inbox');
    has($b->get('/mail/archive')['body'], 'Weekly report #2');
    $b->post("/mail/$inbox/3/action", ['do' => 'spam', '_token' => $t]);
    has($b->get('/mail/spam')['body'], 'Weekly report #3');
    $b->post("/mail/$inbox/4/action", ['do' => 'trash', '_token' => $t]);
    $trash = $b->get('/mail/trash'); has($trash['body'], 'Weekly report #4');
    // delete forever is refused outside Trash
    $b->post("/mail/$inbox/5/action", ['do' => 'delete', '_token' => $t]);
    has($b->get('/mail/inbox/5')['body'], 'Weekly report #5');
    // and allowed in Trash
    $trashId = inboxId('a1a1a1a1a1a1a1a1a1a1a1a1', 'Trash');
    $r = $b->post("/mail/$trashId/1/action", ['do' => 'trash', '_token' => $t]); eq($r['status'], 303);
    eq($b->get('/mail/trash')['status'], 200); has($b->get('/mail/trash')['body'], 'Nothing here yet');
});
test('flow: mark unread returns to list and badge goes up', function () {
    $b = loggedIn(); $inbox = inboxId(); $t = $b->pageToken();
    $b->post("/mail/$inbox/20/action", ['do' => 'unread', '_token' => $t]);
    eq(navCounts($b->get('/mail/inbox')['body'])[0][1], 8);
});
test('flow: search finds messages and escapes the query', function () {
    $b = loggedIn();
    $r = $b->get('/search?q=report'); eq($r['status'], 200); has($r['body'], '35 results');
    $r = $b->get('/search?q=zzzznothing'); has($r['body'], 'No messages found');
    $xss = '<script>alert(1)</script>';
    $r = $b->get('/search?q=' . rawurlencode($xss));
    lacks($r['body'], $xss); has($r['body'], '&lt;script&gt;alert(1)&lt;/script&gt;');
    eq($b->get('/search')['status'], 200);
});
test('flow: revoked or expired WildDuck token sends the user back to login', function () {
    $b = loggedIn();
    Browser::raw('DELETE', Servers::WD . '/authenticate', null, ['X-Access-Token: user-token-alice']);
    $r = $b->get('/mail/inbox'); eq($r['status'], 303); eq($r['headers']['location'], '/login');
    has($b->get('/login')['body'], 'session has expired');
    eq($b->get('/mail/inbox')['status'], 303, 'session was destroyed');
});
test('flow: logout revokes the WildDuck token and ends the session', function () {
    $b = loggedIn();
    $t = $b->pageToken();
    $r = $b->post('/logout', ['_token' => $t], false); eq($r['status'], 303); eq($r['headers']['location'], '/login');
    check(in_array('user-token-alice', Servers::wdState()['revoked'], true), 'token must be revoked at WildDuck');
    eq($b->get('/mail/inbox')['status'], 303);
    eq(Browser::raw('GET', Servers::WD . '/users/a1a1a1a1a1a1a1a1a1a1a1a1/mailboxes', null, ['X-Access-Token: user-token-alice'])['status'], 401);
});
test('flow: GET /logout is not allowed (no logout CSRF)', function () {
    $b = loggedIn(); eq($b->get('/logout')['status'], 405); eq($b->get('/mail/inbox')['status'], 200);
});
test('flow: accounts that only have security keys get a clear message, no session', function () {
    Servers::resetWd();
    $b = new Browser();
    $r = $b->post('/login', ['username' => 'webauthn@example.com', 'password' => 'wa-pass']);
    eq($r['status'], 403); has($r['body'], 'security key'); eq($b->get('/mail/inbox')['status'], 303); eq($b->get('/login/2fa')['status'], 303);
});
test('flow: repeated failures are rate limited with Retry-After, even for the right password', function () {
    Servers::resetWd();
    $b = new Browser();
    for ($i = 0; $i < 8; $i++) { eq($b->post('/login', ['username' => 'locked@example.com', 'password' => 'wrong' . $i])['status'], 401); }
    $r = $b->post('/login', ['username' => 'locked@example.com', 'password' => 'locked-pass']);
    eq($r['status'], 429); check(isset($r['headers']['retry-after']) && (int) $r['headers']['retry-after'] > 0, 'Retry-After');
    $r2 = (new Browser())->post('/login', ['username' => 'LOCKED@example.com', 'password' => 'locked-pass']);
    eq($r2['status'], 429, 'case-insensitive username key');
});
test('flow: WildDuck outage shows a friendly 503 without leaking the server address', function () {
    $b = new Browser(Servers::APP_DOWN);
    $r = $b->post('/login', ['username' => 'alice@example.com', 'password' => 'correct-pass']);
    eq($r['status'], 503); has($r['body'], 'temporarily unavailable'); lacks($r['body'], '18999'); lacks($r['body'], '127.0.0.1');
});
test('flow: pages render responsive shell, skip link and accessible landmarks', function () {
    $b = loggedIn();
    $h = $b->get('/mail/inbox')['body'];
    has($h, 'name="viewport"'); has($h, 'viewport-fit=cover'); has($h, 'class="skip-link"'); has($h, '<main id="main"'); has($h, 'aria-label="Mailboxes"');
    has($h, 'data-drawer-open'); has($h, 'aria-current="page"'); has($h, 'role="search"');
    foreach (['/assets/app.css', '/assets/app.js', '/assets/theme.js', '/assets/fonts/Geist-Regular.woff2'] as $a) { eq(Browser::raw('GET', Servers::APP . $a)['status'], 200, $a); }
});

test('flow: sign in followed IMMEDIATELY by a request always works (no session race across workers)', function () {
    Servers::resetWd(); $bad = [];
    for ($i = 0; $i < 60; $i++) {
        $b = new Browser(); $r = $b->login();
        if ($r['status'] !== 303) { $bad[] = "#$i login={$r['status']}"; continue; }
        $g = $b->get('/mail/inbox');
        if ($g['status'] !== 200) {
            $jar = (new ReflectionProperty($b, 'jar'))->getValue($b); usleep(50000);
            $bad[] = "#$i inbox={$g['status']} loc=" . ($g['headers']['location'] ?? '-') . ' | login Set-Cookie=' . ($r['headers']['set-cookie'] ?? '-') . ' | jar=' . preg_replace('/\s+/', ' ', (string) @file_get_contents($jar));
        }
    }
    eq($bad, [], 'requests right after login must see the new session');
});

<?php
declare(strict_types=1);

use Webmail\Core\{Crypto, RateLimiter, Router};
use Webmail\Support\{HtmlSanitizer, Url};
use Webmail\WildDuck\{ApiException, Client, Ids};

test('unit: sanitizer strips script, handlers, iframes, forms, javascript: links', function () {
    $r = HtmlSanitizer::clean('<p onclick="x()">a</p><script>alert(1)</script><iframe src="//e"></iframe><form><input></form><a href="javascript:alert(1)">l</a><a href=" jAvAsCrIpT:alert(1)">m</a><img src=x onerror=alert(1)>');
    $h = strtolower($r['html']);
    foreach (['<script', 'onclick', 'onerror', '<iframe', '<form', '<input', 'javascript:'] as $bad) { lacks($h, $bad, "sanitizer left $bad"); }
    has($r['html'], '<p>a</p>');
    $r = HtmlSanitizer::clean('<p>keep</p><script>alert(2)</script><style>.x{}secretcss</style>');
    lacks($r['html'], 'alert(2)', 'script content must be dropped, not shown as text');
    lacks($r['html'], 'secretcss', 'style content must be dropped');
});
test('unit: sanitizer blocks remote images by default and counts them', function () {
    $r = HtmlSanitizer::clean('<img src="https://t.example/p.gif"><img src="http://t.example/q.gif">');
    eq($r['blockedImages'], 2);
    lacks($r['html'], 't.example');
});
test('unit: sanitizer allows https images only when opted in; never http', function () {
    $r = HtmlSanitizer::clean('<img src="https://ok.example/a.png"><img src="http://no.example/b.png">', true);
    has($r['html'], 'https://ok.example/a.png');
    lacks($r['html'], 'no.example');
});
test('unit: sanitizer keeps data: raster images and our own cid proxy path, drops svg data', function () {
    $r = HtmlSanitizer::clean('<img src="data:image/png;base64,AAAA"><img src="/mail/aaaaaaaaaaaaaaaaaaaaaaaa/5/attachment/ATT00001?inline=1"><img src="data:image/svg+xml;base64,PHN2Zz4=">');
    has($r['html'], 'data:image/png;base64,AAAA');
    has($r['html'], '/mail/aaaaaaaaaaaaaaaaaaaaaaaa/5/attachment/ATT00001?inline=1');
    lacks($r['html'], 'svg');
    eq($r['blockedImages'], 1, 'only the svg data URI is blocked');
    $r = HtmlSanitizer::clean('<img src="/mail/aaaaaaaaaaaaaaaaaaaaaaaa/5/attachment/../../x"><img src="/other/path.png">');
    eq($r['blockedImages'], 2, 'other same-origin paths must not be loadable');
});
test('unit: sanitizer css filter removes url(), expression, unknown props', function () {
    $r = HtmlSanitizer::clean('<div style="color:red;background:url(https://e/x);position:fixed;width:expression(alert(1))">x</div>');
    has($r['html'], 'color:red');
    foreach (['url(', 'position', 'expression'] as $bad) { lacks($r['html'], $bad); }
});
test('unit: sanitizer adds rel/target to links and drops style/meta/base', function () {
    $r = HtmlSanitizer::clean('<style>*{display:none}</style><base href="//e"><meta http-equiv=refresh content="0;url=//e"><a href="https://ok.example">x</a>');
    has($r['html'], 'noopener noreferrer');
    foreach (['<style', '<base', '<meta', 'display:none'] as $bad) { lacks($r['html'], $bad); }
});
test('unit: Url::safeReturn blocks open redirects', function () {
    foreach (['//evil.test', 'https://evil.test', '/\\evil.test', '/login', '', "/mail/x\r\nSet-Cookie:a=b", 'javascript:alert(1)'] as $bad) {
        eq(Url::safeReturn($bad, '/fallback'), '/fallback', 'for ' . json_encode($bad));
    }
    eq(Url::safeReturn('/mail/inbox?cursor=abc', '/f'), '/mail/inbox?cursor=abc');
    eq(Url::safeReturn('/search?q=a', '/f'), '/search?q=a');
});
test('unit: Url::cursor accepts opaque tokens only', function () {
    eq(Url::cursor('abc_DEF-12=+/.'), 'abc_DEF-12=+/.');
    eq(Url::cursor("x y"), null); eq(Url::cursor('a&b=c'), null); eq(Url::cursor(str_repeat('a', 513)), null);
});
test('unit: Ids validate path segments', function () {
    check(Ids::objectId('a1a1a1a1a1a1a1a1a1a1a1a1'));
    foreach (['../x', 'A1A1A1A1A1A1A1A1A1A1A1A1', 'a1', ''] as $b) { check(!Ids::objectId($b), $b); }
    check(Ids::uid('12')); check(!Ids::uid('0')); check(!Ids::uid('1/2')); check(!Ids::uid('-1'));
    check(Ids::attachment('ATT00001')); check(!Ids::attachment('../ATT1'));
    try { Ids::require('x/../y', 'uid'); check(false, 'should throw'); } catch (ApiException $e) { eq($e->httpStatus, 404); }
});
test('unit: Crypto round-trips and rejects tampering', function () {
    $c = new Crypto(base64_encode(random_bytes(32)));
    $blob = $c->encrypt('secret-token');
    eq($c->decrypt($blob), 'secret-token');
    lacks($blob, 'secret-token');
    $raw = base64_decode($blob); $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] ^ "\x01";
    eq($c->decrypt(base64_encode($raw)), null);
    eq($c->decrypt('not base64 !!'), null);
    try { new Crypto(base64_encode('short')); check(false, 'short key accepted'); } catch (RuntimeException) {}
});
test('unit: RateLimiter blocks after max hits and clears', function () {
    $dir = sys_get_temp_dir() . '/rl-' . bin2hex(random_bytes(3)); mkdir($dir);
    $rl = new RateLimiter($dir);
    for ($i = 0; $i < 3; $i++) { check(!$rl->tooMany('k', 3, 60)); $rl->hit('k', 60); }
    check($rl->tooMany('k', 3, 60)); check(!$rl->tooMany('other', 3, 60));
    $rl->clear('k'); check(!$rl->tooMany('k', 3, 60));
    exec('rm -rf ' . escapeshellarg($dir));
});
test('unit: Router matches params, 404 and 405', function () {
    $r = new Router(); $r->add('GET', '/mail/{box}/{uid}', fn () => 1);
    $m = $r->match('GET', '/mail/inbox/5'); check(is_array($m)); eq($m[1], ['box' => 'inbox', 'uid' => '5']);
    eq($r->match('POST', '/mail/inbox/5'), 405); eq($r->match('GET', '/nope'), 404);
    $m = $r->match('GET', '/mail/a%20b/5'); eq($m[1]['box'], 'a b');
});
test('unit: Client maps errors, hides server error text, never leaks token in URL', function () {
    Servers::resetWd();
    $c = new Client(Servers::WD, 'user-token-alice', 3);
    try { $c->json('GET', '/users/a1a1a1a1a1a1a1a1a1a1a1a1/mailboxes/ffffffffffffffffffffffff/messages'); check(false); }
    catch (ApiException $e) { eq($e->httpStatus, 404); eq($e->apiCode, 'NoSuchMailbox'); check($e->isNotFound()); }
    try { (new Client(Servers::WD, 'bad'))->json('GET', '/users/a1a1a1a1a1a1a1a1a1a1a1a1/mailboxes'); check(false); }
    catch (ApiException $e) { check($e->isUnauthorized()); }
    try { (new Client('http://127.0.0.1:18999', 'x', 2))->json('GET', '/users/a1a1a1a1a1a1a1a1a1a1a1a1/mailboxes'); check(false); }
    catch (ApiException $e) { check($e->isUnavailable()); lacks($e->userMessage(), '18999'); }
    try { $c->json('GET', '/users/b2b2b2b2b2b2b2b2b2b2b2b2/mailboxes'); check(false); }
    catch (ApiException $e) { eq($e->httpStatus, 403, 'user token must not reach another user'); }
});
test('unit: Client download enforces size cap', function () {
    Servers::resetWd();
    $c = new Client(Servers::WD, 'user-token-alice', 3);
    $box = substr(hash('sha1', 'a1a1a1a1a1a1a1a1a1a1a1a1INBOX'), 0, 24);
    $inv = '/users/a1a1a1a1a1a1a1a1a1a1a1a1/mailboxes/' . $box . '/messages/37/attachments/ATT00002';
    $ok = $c->download($inv, [], 1000); eq($ok['body'], '%PDF-1.4 fake');
    try { $c->download($inv, [], 5); check(false, 'cap ignored'); } catch (ApiException $e) { eq($e->httpStatus, 413); }
});

test('unit: view data can never be shadowed by the renderer (regression: a "name" value rendered as the view name)', function () {
    $dir = sys_get_temp_dir() . '/views-' . bin2hex(random_bytes(3)); mkdir($dir);
    file_put_contents($dir . '/probe.php', '<?= e($name) ?>|<?= e($data) ?>|<?= e($file) ?>|<?= get_class($view) ?>');
    $out = (new Webmail\Core\View($dir))->render('probe', ['name' => 'Alice', 'data' => 'D', 'file' => 'F']);
    eq($out, 'Alice|D|F|Webmail\Core\View');
    exec('rm -rf ' . escapeshellarg($dir));
});

test('unit: client IP honours X-Forwarded-For only from trusted proxies', function () {
    $ip = fn (array $s, string $t) => Webmail\Core\Request::clientIp($s, $t);
    eq($ip(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4'], ''), '203.0.113.9', 'no trusted proxies configured');
    eq($ip(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4'], '10.0.0.0/8'), '203.0.113.9', 'an untrusted peer cannot choose its address');
    eq($ip(['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'], '10.0.0.0/8'), '198.51.100.7');
    eq($ip(['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.7, 10.9.9.9'], '10.0.0.0/8'), '198.51.100.7', 'spoofed leading entries are ignored; right-most untrusted wins');
    eq($ip(['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => 'garbage'], '10.0.0.0/8'), '10.1.2.3', 'malformed header: do not guess');
    eq($ip(['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7, <script>'], '10.0.0.0/8'), '10.1.2.3');
    eq($ip(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '2001:db8::5'], '127.0.0.1, ::1'), '2001:db8::5', 'IPv6 client behind an IPv4 proxy');
    eq($ip(['REMOTE_ADDR' => '2001:db8:1::1', 'HTTP_X_FORWARDED_FOR' => '192.0.2.50'], '2001:db8::/32'), '192.0.2.50', 'IPv6 CIDR');
    eq($ip(['REMOTE_ADDR' => '172.16.5.4', 'HTTP_X_FORWARDED_FOR' => '192.0.2.50'], '172.16.0.0/12'), '192.0.2.50'); eq($ip(['REMOTE_ADDR' => '172.32.5.4', 'HTTP_X_FORWARDED_FOR' => '192.0.2.50'], '172.16.0.0/12'), '172.32.5.4', 'just outside the /12');
    eq($ip(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '192.0.2.50'], '10.0.0.0/33, junk, 10.0.0.1'), '192.0.2.50', 'bad entries are skipped, good ones still work');
    eq($ip([], ''), '0.0.0.0'); eq($ip(['REMOTE_ADDR' => 'not-an-ip'], ''), '0.0.0.0');
});
test('unit: storage pruning removes only expired files and keeps dot-files', function () {
    $d = sys_get_temp_dir() . '/prune-' . bin2hex(random_bytes(3)); mkdir($d);
    foreach (['old', 'fresh', '.gitkeep'] as $f) { file_put_contents("$d/$f", 'x'); }
    touch("$d/old", 1000); touch("$d/.gitkeep", 1000); touch("$d/fresh", 5000);
    eq(Webmail\Support\Storage::prune($d, 3600, 5000), 1); check(!is_file("$d/old") && is_file("$d/fresh") && is_file("$d/.gitkeep"));
    eq(Webmail\Support\Storage::prune($d . '/missing', 1), 0); exec('rm -rf ' . escapeshellarg($d));
});

test('unit: Env treats empty values as unset (an empty SESSION_COOKIE_SECURE must not disable the Secure flag)', function () {
    $f = tempnam(sys_get_temp_dir(), 'env'); file_put_contents($f, "# c\nT_SECURE=\nT_ON=true\nT_OFF=false\nT_NUM=\nT_N=42\nT_QUOTED=\"hello world\"\nT_SPACED = padded \n");
    Webmail\Core\Env::load($f); @unlink($f);
    eq(Webmail\Core\Env::bool('T_SECURE', true), true, 'empty -> default true'); eq(Webmail\Core\Env::bool('T_SECURE', false), false);
    eq(Webmail\Core\Env::bool('T_ON', false), true); eq(Webmail\Core\Env::bool('T_OFF', true), false); eq(Webmail\Core\Env::bool('T_MISSING', true), true);
    eq(Webmail\Core\Env::int('T_NUM', 7), 7); eq(Webmail\Core\Env::int('T_N', 7), 42); eq(Webmail\Core\Env::get('T_QUOTED'), 'hello world'); eq(Webmail\Core\Env::get('T_SPACED'), 'padded');
});

test('static: no PHP variable in the codebase has a non-ASCII name (e.g. "$name” inside a string silently reads a different variable)', function () {
    $bad = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $p = (string) $f;
        if (!str_ends_with($p, '.php') || str_contains($p, '/node_modules/')) { continue; }
        foreach (token_get_all((string) file_get_contents($p)) as $t) {
            if (is_array($t) && $t[0] === T_VARIABLE && preg_match('/[^\x00-\x7f]/', $t[1])) { $bad[] = substr($p, strlen(dirname(__DIR__)) + 1) . ':' . $t[2] . ' ' . $t[1]; }
        }
    }
    eq($bad, []);
});

test('unit: the session is persisted before the response is handed back (login -> redirect race)', function () {
    $tmp = sys_get_temp_dir() . '/app-' . bin2hex(random_bytes(3)); mkdir($tmp);
    foreach (['APP_KEY' => base64_encode(random_bytes(32)), 'STORAGE_PATH' => $tmp, 'WILDDUCK_API_URL' => Servers::WD, 'WILDDUCK_API_TOKEN' => 'x'] as $k => $v) { Webmail\Core\Env::set($k, $v); }
    $app = new Webmail\App(dirname(__DIR__));
    check(!$app->session->isClosed(), 'open before handling');
    foreach (['/health', '/login', '/nope', '/mail/inbox'] as $path) {
        $app = new Webmail\App(dirname(__DIR__));
        $res = $app->handle(new Webmail\Core\Request('GET', $path, [], [], [], '127.0.0.1'));
        check($app->session->isClosed(), "session must be closed (written) before the response for $path is returned (status {$res->status})");
    }
    foreach (['APP_KEY', 'STORAGE_PATH', 'WILDDUCK_API_URL', 'WILDDUCK_API_TOKEN'] as $k) { Webmail\Core\Env::set($k, ''); }
    exec('rm -rf ' . escapeshellarg($tmp));
});

test('unit: account ids are never all digits, and numeric keys already in a session are still read (PHP turns "123" array keys into ints)', function () {
    $mk = fn () => new Webmail\Core\Auth(new Webmail\Core\Session(sys_get_temp_dir(), 60, false), new Webmail\Core\Crypto(base64_encode(str_repeat('q', 32))));
    $digitIds = 0;
    for ($i = 0; $i < 4000; $i++) { $_SESSION = []; $id = $mk()->login('a1a1a1a1a1a1a1a1a1a1a1a1', 'x@example.com', 't'); if (ctype_digit($id)) { $digitIds++; } eq($mk()->user()['address'], 'x@example.com', 'the account must survive a re-read'); }
    eq($digitIds, 0, 'ids must contain a letter');
    // a session written by an older build (or hand-edited) with an all-digit id
    $_SESSION = ['wm_accounts' => [138267050977 => ['u' => 'a1a1a1a1a1a1a1a1a1a1a1a1', 'a' => 'old@example.com', 't' => null]], 'wm_active' => '138267050977'];
    $a = $mk(); eq($a->user()['address'], 'old@example.com'); eq($a->accounts(), [['id' => '138267050977', 'address' => 'old@example.com', 'active' => true]]); check($a->switchTo('138267050977'));
    $_SESSION = [];
});
test('unit: multi-account session - add, switch, duplicate, cap, remove, encryption', function () {
    $_SESSION = []; $crypto = new Webmail\Core\Crypto(base64_encode(str_repeat('k', 32))); $a = new Webmail\Core\Auth(new Webmail\Core\Session(sys_get_temp_dir(), 60, false), $crypto);
    check(!$a->check()); $one = $a->login('a1a1a1a1a1a1a1a1a1a1a1a1', 'one@example.com', 'tok-one'); $two = $a->login('b2b2b2b2b2b2b2b2b2b2b2b2', 'two@example.com', 'tok-two');
    eq($a->user()['address'], 'two@example.com', 'the newly added account becomes active'); eq(array_column($a->accounts(), 'address'), ['one@example.com', 'two@example.com']); eq($a->token(), 'tok-two'); eq($a->tokenFor($one), 'tok-one');
    lacks(json_encode($_SESSION), 'tok-one'); lacks(json_encode($_SESSION), 'tok-two');
    eq($a->login('a1a1a1a1a1a1a1a1a1a1a1a1', 'one@example.com', 'tok-one-new'), $one, 'the same WildDuck account is refreshed, not duplicated'); eq(count($a->accounts()), 2); eq($a->token(), 'tok-one-new');
    check($a->switchTo($two)); eq($a->user()['address'], 'two@example.com'); check(!$a->switchTo('does-not-exist')); check(!$a->switchTo('')); eq($a->user()['address'], 'two@example.com', 'a failed switch changes nothing');
    for ($i = 3; $i <= Webmail\Core\Auth::MAX_ACCOUNTS; $i++) { $a->login(sprintf('%024x', $i), "u$i@example.com", "t$i"); }
    check(!$a->canAdd(sprintf('%024x', 99)), 'cap reached'); check($a->canAdd('a1a1a1a1a1a1a1a1a1a1a1a1'), 'an account that is already signed in can always be refreshed');
    $active = $a->activeId(); check($a->remove($active)); check($a->activeId() !== null && $a->activeId() !== $active, 'another account takes over'); eq(count($a->accounts()), Webmail\Core\Auth::MAX_ACCOUNTS - 1);
    foreach ($a->accounts() as $acc) { $last = $a->remove($acc['id']); } check(!$last, 'removing the last account leaves nobody signed in'); check(!$a->check()); eq($a->token(), null);
    $_SESSION = [];
});

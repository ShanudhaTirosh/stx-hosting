<?php
declare(strict_types=1);

use Webmail\Mail\Prefs;
use Webmail\WildDuck\{Client, ProfileService};

function profileSvc(): ProfileService { return new ProfileService(new Client(Servers::WD, 'user-token-alice', 3), UID_A); }
function wdMeta(): array { return Servers::wdState()['meta'][UID_A]; }
function flag(string $name, bool $on = true): void { Browser::raw('GET', Servers::WD . '/__flag?name=' . $name . '&value=' . ($on ? '1' : '0')); }
function seedMeta(array $webmail): void { profileSvc()->saveMetaData(['other' => ['keep' => 1], 'webmail' => $webmail]); }
function sigForm(Browser $b, array $f, string $path = '/signatures'): array { return $b->post($path, $f + ['_token' => $b->pageToken('/signatures')], false); }
function contactForm(Browser $b, array $f, string $path = '/contacts'): array { return $b->post($path, $f + ['_token' => $b->pageToken('/contacts')], false); }
function contactsOf(): array { return wdMeta()['webmail']['contacts'] ?? []; }
function sigsOf(): array { return wdMeta()['webmail']['signatures'] ?? []; }

test('unit: Prefs drops corrupt entries, sanitises signatures and survives garbage', function () {
    $p = Prefs::fromMeta(['webmail' => [
        'signatures' => [['id' => 'aaaaaaaaaaaa', 'name' => 'Work', 'html' => '<p onclick="x">Hi</p><script>alert(1)</script>'], ['id' => 'bad', 'name' => 'x', 'html' => 'y'], 'junk', ['id' => 'bbbbbbbbbbbb', 'name' => 5, 'html' => 'z']],
        'defaultSignature' => 'cccccccccccc',
        'contacts' => [['id' => 'aaaaaaaaaaaa', 'name' => "Bob\n<b>", 'email' => 'bob@example.org', 'note' => ''], ['id' => 'bbbbbbbbbbbb', 'email' => 'BOB@example.org'], ['id' => 'cccccccccccc', 'email' => 'not-an-email'], ['id' => 'zz', 'email' => 'ok@example.org'], 'junk', ['id' => 'dddddddddddd', 'email' => 'ok@example.org', 'name' => 'Ok']],
    ]]);
    eq(count($p->signatures), 1); lacks($p->signatures[0]['html'], 'script'); lacks($p->signatures[0]['html'], 'onclick'); eq($p->defaultSignature, null, 'default must reference an existing signature');
    eq(array_column($p->contacts, 'email'), ['bob@example.org', 'ok@example.org']); lacks($p->contacts[0]['name'], '<');
    foreach ([[], ['webmail' => 'str'], ['webmail' => ['contacts' => 'x', 'signatures' => 5]]] as $garbage) { $q = Prefs::fromMeta($garbage); eq([$q->signatures, $q->contacts, $q->defaultSignature], [[], [], null]); }
    $many = ['webmail' => ['contacts' => array_map(fn ($i) => ['id' => sprintf('%012x', $i), 'email' => "u$i@example.org"], range(1, Prefs::MAX_CONTACTS + 50))]];
    eq(count(Prefs::fromMeta($many)->contacts), Prefs::MAX_CONTACTS, 'bounded');
});
test('unit: Prefs::suggest puts contacts first, de-duplicates and limits', function () {
    $p = Prefs::fromMeta(['webmail' => ['contacts' => [['id' => 'aaaaaaaaaaaa', 'name' => 'Zed Zebra Jr', 'email' => 'zjr@example.org'], ['id' => 'bbbbbbbbbbbb', 'name' => '', 'email' => 'bob@example.org']]]]);
    $r = $p->suggest('z', [['name' => 'Zed Zebra', 'address' => 'zed@example.org'], ['name' => 'Dup', 'address' => 'ZJR@example.org']]);
    eq(array_column($r, 'email'), ['zjr@example.org', 'zed@example.org']);
    eq(count($p->suggest('x', array_map(fn ($i) => ['name' => '', 'address' => "u$i@x.org"], range(1, 20)))), 8);
    eq($p->suggest('', []), []); eq(array_column($p->suggest('BOB', []), 'email'), ['bob@example.org']);
});
test('unit: signature insertion goes above the quote and never duplicates markers', function () {
    $h = Prefs::insertSignature('<p><br></p><p>On x wrote:</p><blockquote>q</blockquote>', '<p>Bye</p>');
    check(strpos($h, 'data-signature') < strpos($h, 'wrote:'), 'above quote'); eq(substr_count($h, '<p><br></p>'), 1);
    eq(Prefs::insertSignature('<p>x</p>', ''), '<p>x</p>'); has(Prefs::insertSignature('<p>x</p>', '<p>S</p>'), '<p><br></p><div data-signature="1"><p>S</p></div><p>x</p>');
});
test('unit: Prefs::update keeps foreign metaData keys and refuses oversized data', function () {
    Servers::resetWd();
    Prefs::update(profileSvc(), fn (Prefs $p) => ['signatures' => [], 'defaultSignature' => null, 'contacts' => [['id' => 'aaaaaaaaaaaa', 'name' => 'A', 'email' => 'a@example.org', 'note' => '']]]);
    eq(wdMeta()['other'], ['keep' => 1], "another app's metaData must survive"); eq(wdMeta()['webmail']['contacts'][0]['email'], 'a@example.org');
    $big = array_map(fn ($i) => ['id' => sprintf('%012x', $i), 'name' => str_repeat('n', 100), 'email' => "user$i@example.org", 'note' => str_repeat('x', 200)], range(1, 2000));
    try { Prefs::update(profileSvc(), fn () => ['signatures' => [], 'defaultSignature' => null, 'contacts' => $big]); check(false, 'oversized accepted'); } catch (InvalidArgumentException $e) { has($e->getMessage(), 'too large'); }
    eq(count(contactsOf()), 1, 'refused write must not change stored data');
});

test('contacts: add, list, search, edit, delete; foreign metaData untouched', function () {
    $b = loggedIn(); has($b->get('/contacts')['body'], 'No contacts yet');
    $r = contactForm($b, ['name' => 'Ann Lee', 'email' => 'ann@example.org', 'note' => 'Design']); eq($r['status'], 303);
    contactForm($b, ['name' => '', 'email' => 'zack@example.org']);
    $h = $b->get('/contacts')['body']; has($h, 'Contact added.'); has($h, 'Ann Lee'); has($h, 'ann@example.org'); has($h, 'Design'); has($h, '2 contacts');
    eq(wdMeta()['other'], ['keep' => 1]); eq(count(contactsOf()), 2);
    $s = $b->get('/contacts?q=zack')['body']; has($s, '1 contact'); lacks($s, 'ann@example.org"'); has($b->get('/contacts?q=nobody')['body'], 'No matching contacts');
    $id = array_values(array_filter(contactsOf(), fn ($c) => $c['email'] === 'ann@example.org'))[0]['id'];
    $r = contactForm($b, ['name' => 'Ann L.', 'email' => 'ann.lee@example.org', 'note' => ''], "/contacts/$id/update"); eq($r['status'], 303); has($b->get('/contacts')['body'], 'ann.lee@example.org');
    $c = $b->get("/contacts/$id/delete"); eq($c['status'], 200); has($c['body'], 'ann.lee@example.org');
    eq(contactForm($b, [], "/contacts/$id/delete")['status'], 303); eq(count(contactsOf()), 1); eq($b->get("/contacts/$id/delete")['status'], 404);
    eq(contactForm($b, ['name' => 'x', 'email' => 'x@example.org'], "/contacts/$id/update")['status'], 404, 'updating a deleted contact');
});
test('contacts: validation, duplicates and XSS', function () {
    $b = loggedIn();
    contactForm($b, ['name' => 'A', 'email' => 'dup@example.org']);
    $r = contactForm($b, ['name' => 'B', 'email' => 'DUP@example.org']); eq($r['status'], 422); has($r['body'], 'already in your contacts'); has($r['body'], 'value="B"');
    foreach (['', 'x', 'a b@c.de', 'a@b', '<x>@y.zz', 'a@b.cc,d@e.ff', str_repeat('a', 250) . '@x.org'] as $bad) { eq(contactForm($b, ['name' => 'n', 'email' => $bad])['status'], 422, json_encode($bad)); }
    eq(contactForm($b, ['name' => str_repeat('n', 101), 'email' => 'long@example.org'])['status'], 422); eq(contactForm($b, ['name' => 'n', 'email' => 'long@example.org', 'note' => str_repeat('x', 201)])['status'], 422);
    $x = '<img src=x onerror=alert(1)>';
    contactForm($b, ['name' => $x, 'email' => 'xss@example.org', 'note' => '"><script>alert(2)</script>']);
    $h = $b->get('/contacts')['body']; lacks($h, '<img src=x'); lacks($h, '<script>alert(2)'); has($h, 'xss@example.org');
    eq(count(array_filter(contactsOf(), fn ($c) => str_contains($c['name'] . $c['note'], '<'))), 0, 'angle brackets are not stored');
    eq(count(contactsOf()), 2);
});
test('contacts: CSRF and authentication', function () {
    $b = loggedIn();
    eq($b->post('/contacts', ['name' => 'n', 'email' => 'n@example.org'], false)['status'], 419); eq($b->post('/contacts/quick', ['email' => 'n@example.org'], false)['status'], 419);
    eq(count(contactsOf()), 0);
    foreach (['/contacts', '/contacts/aaaaaaaaaaaa/delete', '/signatures', '/signatures/aaaaaaaaaaaa/delete'] as $p) { eq(Browser::raw('GET', Servers::APP . $p)['status'], 303, $p); }
    eq(Browser::raw('GET', Servers::APP . '/api/suggest?q=zz')['status'], 401, 'JSON endpoint answers 401, not a login redirect');
});
test('contacts: pagination and corrupt stored entries', function () {
    $b = loggedIn();
    $rows = array_map(fn ($i) => ['id' => sprintf('%012x', $i), 'name' => sprintf('Person %03d', $i), 'email' => "p$i@example.org", 'note' => ''], range(1, 75));
    $rows[] = ['id' => 'nothex', 'email' => 'bad@example.org']; $rows[] = 'junk'; $rows[] = ['id' => 'ffffffffffff', 'email' => 'not-an-email'];
    seedMeta(['contacts' => $rows]);
    $p1 = $b->get('/contacts')['body']; has($p1, '75 contacts'); has($p1, 'Person 001'); lacks($p1, 'Person 075'); has($p1, 'Page 1 of 2'); lacks($p1, 'bad@example.org');
    $p2 = $b->get('/contacts?page=2')['body']; has($p2, 'Person 075'); lacks($p2, 'Person 001');
    eq($b->get('/contacts?page=999')['status'], 200); eq($b->get('/contacts?page=-3')['status'], 200);
});
test('contacts: "Write" link pre-fills compose; sender can be added from a message', function () {
    $b = loggedIn(); $inbox = inboxId();
    contactForm($b, ['name' => 'Ann Lee', 'email' => 'ann@example.org']);
    preg_match('#href="(/compose\?to=[^"]+)"#', html_entity_decode($b->get('/contacts')['body']), $m);
    has($b->get($m[1])['body'], 'value="Ann Lee &lt;ann@example.org&gt;"');
    $h = $b->get('/mail/inbox/5')['body']; has($h, 'Add sender to contacts');
    $r = $b->post('/contacts/quick', ['name' => 'Reports Bot', 'email' => 'bot@example.org', 'return' => '/mail/inbox/5', '_token' => $b->pageToken()], false);
    eq($r['status'], 303); eq($r['headers']['location'], '/mail/inbox/5');
    $h = $b->get('/mail/inbox/5')['body']; has($h, 'Added Reports Bot to your contacts.'); lacks($h, 'Add sender to contacts');
    $r = $b->post('/contacts/quick', ['name' => 'Again', 'email' => 'BOT@example.org', 'return' => '//evil.test', '_token' => $b->pageToken()], false);
    eq($r['headers']['location'], '/contacts', 'unsafe return ignored'); has($b->get('/contacts')['body'], 'already in your contacts'); eq(count(contactsOf()), 2);
    eq($b->post('/contacts/quick', ['email' => 'nope', '_token' => $b->pageToken()], false)['status'], 303); eq(count(contactsOf()), 2);
});
test('contacts: storage refusal is reported and nothing is lost or half-saved', function () {
    $b = loggedIn(); contactForm($b, ['name' => 'Keep', 'email' => 'keep@example.org']);
    flag('failMeta');
    $r = contactForm($b, ['name' => 'New', 'email' => 'new@example.org']); eq($r['status'], 422); has($r['body'], 'nothing was saved'); has($r['body'], 'may not be allowed'); has($r['body'], 'value="new@example.org"');
    flag('failMeta', false); eq(count(contactsOf()), 1); has($b->get('/contacts')['body'], 'keep@example.org');
});
test('contacts: an unreadable profile degrades gracefully instead of breaking pages', function () {
    $b = loggedIn(); flag('failUserGet');
    $c = $b->get('/contacts'); eq($c['status'], 200); has($c['body'], 'could not be loaded');
    eq($b->get('/signatures')['status'], 200); $comp = $b->get('/compose'); eq($comp['status'], 200); lacks($comp['body'], 'sig-pick');
    $m = $b->get('/mail/inbox/5'); eq($m['status'], 200); lacks($m['body'], 'Add sender to contacts');
    eq($b->get('/mail/inbox')['status'], 200);
});

test('suggest: contacts first, merged with the address register, de-duplicated, safe JSON', function () {
    $b = loggedIn();
    contactForm($b, ['name' => 'Bobby', 'email' => 'bob@example.org']); contactForm($b, ['name' => 'Zora', 'email' => 'zora@example.org']);
    $r = $b->get('/api/suggest?q=bo'); eq($r['status'], 200); has($r['headers']['content-type'], 'application/json'); eq($r['headers']['x-content-type-options'], 'nosniff'); has($r['headers']['cache-control'], 'no-store');
    $d = json_decode($r['body'], true); eq(array_column($d['results'], 'email'), ['bob@example.org'], 'same address appears once, from contacts');
    $d = json_decode($b->get('/api/suggest?q=z')['body'], true); eq(array_column($d['results'], 'email'), ['zora@example.org', 'zed@example.org', 'zoe@example.org']);
    $raw = $b->get('/api/suggest?q=zoe')['body']; lacks($raw, '<script>'); eq(json_decode($raw, true)['results'][0]['name'], 'Zoe <script>', 'round-trips exactly once decoded');
    eq(json_decode($b->get('/api/suggest?q=')['body'], true), ['results' => []]); eq(json_decode($b->get('/api/suggest')['body'], true), ['results' => []]);
    eq(json_decode($b->get('/api/suggest?q=' . str_repeat('a', 500))['body'], true), ['results' => []]);
    eq(json_decode($b->get('/api/suggest?q=newsletter')['body'], true)['results'][0], ['name' => '', 'email' => 'newsletter@example.net']);
});
test('suggest: works when the address register fails; reuses cached contacts between keystrokes', function () {
    $b = loggedIn(); contactForm($b, ['name' => 'Zora', 'email' => 'zora@example.org']);
    flag('failRegister'); $d = json_decode($b->get('/api/suggest?q=zo')['body'], true); eq(array_column($d['results'], 'email'), ['zora@example.org']); flag('failRegister', false);
    $user = 'GET /users/' . UID_A; $before = wdCalls($user);
    $b->get('/api/suggest?q=zo'); $b->get('/api/suggest?q=zor'); $b->get('/api/suggest?q=zora');
    check(wdCalls($user) - $before <= 1, 'profile is read at most once for several keystrokes, got ' . (wdCalls($user) - $before));
    check(wdCalls('GET /users/' . UID_A . '/addressregister') >= 3, 'register is queried per keystroke');
});

test('signatures: create, sanitise, default handling, edit and delete', function () {
    $b = loggedIn(); has($b->get('/signatures')['body'], 'No signatures yet');
    $r = sigForm($b, ['name' => 'Work', 'html' => '<p onclick="x()">Regards,<br><b>Alice</b></p><script>alert(1)</script><img src="https://t.evil/p.gif">']); eq($r['status'], 303);
    $s = sigsOf()[0]; has($s['html'], 'Regards'); foreach (['script', 'onclick', 'img', 't.evil'] as $bad) { lacks($s['html'], $bad); }
    $h = $b->get('/signatures')['body']; has($h, 'Signature added.'); has($h, 'Default'); eq(wdMeta()['webmail']['defaultSignature'], $s['id'], 'first signature becomes the default');
    sigForm($b, ['name' => 'Personal', 'html' => '<p>Cheers</p>']); eq(wdMeta()['webmail']['defaultSignature'], $s['id'], 'second one is not default');
    $p = array_values(array_filter(sigsOf(), fn ($x) => $x['name'] === 'Personal'))[0];
    sigForm($b, ['id' => $p['id']], '/signatures/default'); eq(wdMeta()['webmail']['defaultSignature'], $p['id']);
    sigForm($b, ['name' => 'Work 2', 'html' => '<p>Hi</p>', 'make_default' => '1']); eq(count(sigsOf()), 3);
    sigForm($b, ['id' => ''], '/signatures/default'); eq(wdMeta()['webmail']['defaultSignature'], null);
    $r = sigForm($b, ['name' => 'Personal v2', 'html' => '<p>Cheers!</p>'], "/signatures/{$p['id']}/update"); eq($r['status'], 303); has($b->get('/signatures')['body'], 'Personal v2');
    sigForm($b, ['id' => $p['id']], '/signatures/default');
    $c = $b->get("/signatures/{$p['id']}/delete"); eq($c['status'], 200); has($c['body'], 'Personal v2');
    eq(sigForm($b, [], "/signatures/{$p['id']}/delete")['status'], 303); eq(count(sigsOf()), 2); eq(wdMeta()['webmail']['defaultSignature'], null, 'deleting the default clears it');
    eq(wdMeta()['other'], ['keep' => 1]); eq($b->get("/signatures/{$p['id']}/delete")['status'], 404);
});
test('signatures: validation, limits and CSRF', function () {
    $b = loggedIn();
    foreach ([['name' => '', 'html' => '<p>x</p>'], ['name' => str_repeat('n', 61), 'html' => '<p>x</p>'], ['name' => 'Empty', 'html' => '<p><br></p>'], ['name' => 'Scripty', 'html' => '<script>alert(1)</script>'], ['name' => 'Big', 'html' => '<p>' . str_repeat('x', 21000) . '</p>']] as $bad) {
        eq(sigForm($b, $bad)['status'], 422, json_encode(array_keys($bad)) . $bad['name']);
    }
    $r = sigForm($b, ['name' => 'Fallback', 'html' => '', 'text_fallback' => "Line <b>1</b>\nLine 2"]); eq($r['status'], 303); has(sigsOf()[0]['html'], 'Line &lt;b&gt;1&lt;/b&gt;'); lacks(sigsOf()[0]['html'], '<b>1</b>');
    $r = sigForm($b, ['name' => 'fallback', 'html' => '<p>dup</p>']); eq($r['status'], 422); has($r['body'], 'already exists');
    for ($i = 2; $i <= Prefs::MAX_SIGNATURES; $i++) { eq(sigForm($b, ['name' => "S$i", 'html' => "<p>$i</p>"])['status'], 303); }
    $r = sigForm($b, ['name' => 'One too many', 'html' => '<p>x</p>']); eq($r['status'], 422); has($r['body'], 'up to 10'); eq(count(sigsOf()), 10);
    eq($b->post('/signatures', ['name' => 'N', 'html' => '<p>x</p>'], false)['status'], 419); eq(sigForm($b, ['id' => 'ffffffffffff'], '/signatures/default')['status'], 404);
    eq(sigForm($b, ['name' => 'x', 'html' => '<p>x</p>'], '/signatures/ffffffffffff/update')['status'], 404);
    flag('failMeta'); $r = sigForm($b, ['name' => 'Nope', 'html' => '<p>x</p>'], '/signatures/' . sigsOf()[0]['id'] . '/update'); eq($r['status'], 422); has($r['body'], 'nothing was saved'); flag('failMeta', false);
});
test('signatures: stored markup is re-sanitised on every read', function () {
    $b = loggedIn(); seedMeta(['signatures' => [['id' => 'aaaaaaaaaaaa', 'name' => 'Evil', 'html' => '<p>ok</p><script>alert(1)</script><img src=x onerror=alert(2)><iframe src="//evil"></iframe></template><script>alert(3)</script>']], 'defaultSignature' => 'aaaaaaaaaaaa']);
    foreach (['/signatures', '/compose', '/signatures/aaaaaaaaaaaa/delete'] as $p) {
        $h = $b->get($p)['body']; foreach (['<script>alert', 'onerror', '<iframe'] as $bad) { lacks($h, $bad, "$p leaked $bad"); } has($h, 'ok');
    }
});
test('signatures: compose inserts the default above the quote, offers a picker, keeps the choice on errors', function () {
    $b = loggedIn(); $inbox = inboxId();
    sigForm($b, ['name' => 'Work', 'html' => '<p>Kind regards,<br>Alice</p>']); sigForm($b, ['name' => 'Personal', 'html' => '<p>Cheers</p>']);
    $work = sigsOf()[0]['id']; $pers = sigsOf()[1]['id'];
    $n = $b->get('/compose')['body']; has($n, 'data-signature="1"'); has($n, 'Kind regards'); has($n, 'id="sig-pick"'); has($n, 'value="' . $work . '" selected'); has($n, '<template data-sig="' . $pers . '">'); has($n, '<template data-sig="' . $work . '">');
    $r = $b->get('/mail/inbox/5/reply')['body']; check(strpos($r, 'Kind regards') < strpos($r, 'wrote:'), 'signature above the quote'); eq(substr_count($r, 'data-signature="1"'), 1);
    $f = $b->get('/mail/inbox/37/forward')['body']; check(strpos($f, 'Kind regards') < strpos($f, 'Forwarded message'), 'forward too');
    $drafts = inboxId(UID_A, 'Drafts'); sendForm($b, ['do' => 'draft', 'subject' => 'D', 'html' => '<p>draft body</p>']);
    $e = $b->get("/mail/$drafts/1/edit")['body']; lacks($e, 'data-signature="1"'); lacks($e, 'value="' . $work . '" selected');
    $r = sendForm($b, ['to' => '', 'sig' => $pers]); eq($r['status'], 422); has($r['body'], 'value="' . $pers . '" selected');
    $r = sendForm($b, ['to' => '', 'sig' => 'ffffffffffff']); preg_match('#<select[^>]*id="sig-pick".*?</select>#s', $r['body'], $sm); lacks($sm[0], ' selected', 'unknown signature id must not select anything');
});
test('signatures: the signature is part of the sent message', function () {
    $b = loggedIn(); sigForm($b, ['name' => 'Work', 'html' => '<p>Kind regards,<br>Alice</p>']);
    $r = sendForm($b, ['to' => 'bob@example.org', 'subject' => 'Hello']); eq($r['status'], 303);
    $s = lastSubmit(); has($s['html'], 'Kind regards'); has($s['text'], 'Kind regards'); has($s['text'], 'Alice');
});
test('signatures: no signatures means no picker and no empty block', function () {
    $b = loggedIn(); $h = $b->get('/compose')['body']; lacks($h, 'sig-pick'); lacks($h, 'data-signature');
    $r = $b->get('/mail/inbox/5/reply')['body']; lacks($r, 'data-signature');
});
test('suggest/editor scripts are served and compose loads them', function () {
    $b = loggedIn(); $h = $b->get('/compose')['body'];
    foreach (['editor', 'compose', 'suggest'] as $s) { has($h, "/assets/$s.js"); eq(Browser::raw('GET', Servers::APP . "/assets/$s.js")['status'], 200); }
    has($h, 'data-suggest'); eq(substr_count($h, 'data-suggest'), 3); has($b->get('/signatures')['body'], '/assets/editor.js');
});

<?php
declare(strict_types=1);

/**
 * TEST-ONLY stand-in for the WildDuck REST API (router script for `php -S`).
 * Response shapes are verified against the pinned OpenAPI spec by tests/ContractTest.php.
 * It is never used by the application itself.
 */

const SVC = 'svc-token';
$stateFile = getenv('FAKE_WD_STATE') ?: sys_get_temp_dir() . '/fake_wd_state.json';

function boxId(string $u, string $n): string { return substr(hash('sha1', $u . $n), 0, 24); }

function seed(): array
{
    $users = [
        'alice@example.com' => ['id' => 'a1a1a1a1a1a1a1a1a1a1a1a1', 'pass' => 'correct-pass', 'token' => 'user-token-alice'],
        'bob@example.com' => ['id' => 'b2b2b2b2b2b2b2b2b2b2b2b2', 'pass' => 'bob-pass', 'token' => 'user-token-bob'],
        'locked@example.com' => ['id' => 'd4d4d4d4d4d4d4d4d4d4d4d4', 'pass' => 'locked-pass', 'token' => 'user-token-locked'],
        'pwtest@example.com' => ['id' => 'e5e5e5e5e5e5e5e5e5e5e5e5', 'pass' => 'pw-pass', 'token' => 'user-token-pw'],
        'twofa@example.com' => ['id' => 'c3c3c3c3c3c3c3c3c3c3c3c3', 'pass' => 'twofa-pass', 'token' => 'user-token-2fa', 'twofa' => ['totp']],
        'webauthn@example.com' => ['id' => 'f6f6f6f6f6f6f6f6f6f6f6f6', 'pass' => 'wa-pass', 'token' => 'user-token-wa', 'twofa' => ['webauthn']],
        'multi@example.com' => ['id' => 'f7f7f7f7f7f7f7f7f7f7f7f7', 'pass' => 'multi-pass', 'token' => 'user-token-multi', 'twofa' => ['webauthn', 'totp']],
        'lock2fa@example.com' => ['id' => 'f8f8f8f8f8f8f8f8f8f8f8f8', 'pass' => 'lock-pass', 'token' => 'user-token-lock', 'twofa' => ['totp']],
        'acct5@example.com' => ['id' => 'a5a5a5a5a5a5a5a5a5a5a5a5', 'pass' => 'acct-pass', 'token' => 'user-token-acct5'],
        'acct6@example.com' => ['id' => 'a6a6a6a6a6a6a6a6a6a6a6a6', 'pass' => 'acct-pass', 'token' => 'user-token-acct6'],
        'custom2fa@example.com' => ['id' => 'f9f9f9f9f9f9f9f9f9f9f9f9', 'pass' => 'custom-pass', 'token' => 'user-token-custom', 'twofa' => ['custom']],
    ];
    $special = ['INBOX' => '\\Inbox', 'Drafts' => '\\Drafts', 'Sent Mail' => '\\Sent', 'Archive' => '\\Archive', 'Junk' => '\\Junk', 'Trash' => '\\Trash', 'Projects' => null];
    $boxes = [];
    $msgs = [];
    foreach ($users as $addr => $u) {
        foreach ($special as $name => $use) {
            $boxes[$u['id']][] = ['id' => boxId($u['id'], $name), 'name' => $name === 'INBOX' ? 'INBOX' : $name, 'path' => $name, 'specialUse' => $use];
        }
    }
    $inbox = boxId('a1a1a1a1a1a1a1a1a1a1a1a1', 'INBOX');
    $uid = 1;
    for ($i = 1; $i <= 35; $i++) {
        $msgs[] = ['uid' => $uid++, 'user' => 'a1a1a1a1a1a1a1a1a1a1a1a1', 'mailbox' => $inbox, 'subject' => "Weekly report #$i", 'from' => ['name' => 'Reports Bot', 'address' => 'bot@example.org'],
            'intro' => "Numbers for week $i", 'html' => ["<p>Hello, week $i is <b>done</b>.</p>"], 'text' => "Hello, week $i is done.", 'seen' => $i > 5, 'flagged' => $i === 3, 'date' => sprintf('2026-10-%02dT10:00:00.000Z', min(28, $i)), 'attachments' => []];
    }
    $msgs[] = ['uid' => $uid++, 'user' => 'a1a1a1a1a1a1a1a1a1a1a1a1', 'mailbox' => $inbox, 'subject' => 'XSS probe', 'from' => ['name' => 'Mallory', 'address' => 'm@evil.test'], 'intro' => 'probe',
        'html' => ['<p onclick="alert(1)">hi</p><script>alert(2)</script><img src="https://tracker.evil.test/p.gif"><a href="javascript:alert(3)">x</a><iframe src="https://evil.test"></iframe><img src=x onerror=alert(4)><div style="background:url(https://evil.test/a);color:red">styled</div><form action="https://evil.test"><input name=pw></form>'],
        'text' => 'probe', 'seen' => false, 'flagged' => false, 'date' => '2026-10-30T09:00:00.000Z', 'attachments' => []];
    $msgs[] = ['uid' => $uid++, 'user' => 'a1a1a1a1a1a1a1a1a1a1a1a1', 'mailbox' => $inbox, 'subject' => 'Invoice attached', 'from' => ['name' => 'Billing', 'address' => 'billing@example.org'], 'intro' => 'see attached',
        'html' => ['<p>Logo: <img src="attachment:deadbeef/ATT00001"></p>'], 'text' => 'see attached', 'seen' => false, 'flagged' => false, 'date' => '2026-10-30T11:00:00.000Z',
        'attachments' => [
            ['id' => 'ATT00001', 'filename' => 'logo.png', 'contentType' => 'image/png', 'related' => true, 'content' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='],
            ['id' => 'ATT00002', 'filename' => 'invoice.pdf', 'contentType' => 'application/pdf', 'related' => false, 'content' => base64_encode('%PDF-1.4 fake')],
            ['id' => 'ATT00003', 'filename' => 'evil.html', 'contentType' => 'text/html', 'related' => false, 'content' => base64_encode('<script>alert(1)</script>')],
        ]];
    $msgs[] = ['uid' => 1, 'user' => 'a1a1a1a1a1a1a1a1a1a1a1a1', 'mailbox' => boxId('a1a1a1a1a1a1a1a1a1a1a1a1', 'Projects'), 'subject' => 'Team lunch', 'from' => ['name' => 'Erin', 'address' => 'erin@example.org'],
        'to' => [['name' => '', 'address' => 'Alice@Example.com'], ['name' => 'Carol', 'address' => 'carol@example.org']], 'cc' => [['name' => '', 'address' => 'dave@example.org'], ['name' => '', 'address' => 'erin@example.org']],
        'intro' => 'Where shall we eat?', 'html' => ['<p>Where shall we eat?</p><script>alert(1)</script>'], 'text' => 'Where shall we eat?', 'seen' => true, 'flagged' => false, 'date' => '2026-10-27T12:00:00.000Z', 'attachments' => []];
    $msgs[] = ['uid' => 4, 'user' => 'a1a1a1a1a1a1a1a1a1a1a1a1', 'mailbox' => boxId('a1a1a1a1a1a1a1a1a1a1a1a1', 'Projects'), 'subject' => 'Sales inquiry', 'from' => ['name' => 'Frank', 'address' => 'frank@example.org'],
        'to' => [['name' => 'Sales', 'address' => 'Sales@Example.com']], 'intro' => 'Do you ship abroad?', 'html' => ['<p>Do you ship abroad?</p>'], 'text' => 'Do you ship abroad?', 'seen' => true, 'flagged' => false, 'date' => '2026-10-26T12:00:00.000Z', 'attachments' => []];
    $msgs[] = ['uid' => 1, 'user' => 'b2b2b2b2b2b2b2b2b2b2b2b2', 'mailbox' => boxId('b2b2b2b2b2b2b2b2b2b2b2b2', 'INBOX'), 'subject' => 'Bob secret', 'from' => ['name' => 'Boss', 'address' => 'boss@example.org'], 'intro' => 'bob only',
        'html' => ['<p>secret</p>'], 'text' => 'secret', 'seen' => false, 'flagged' => false, 'date' => '2026-10-29T09:00:00.000Z', 'attachments' => []];
    foreach ($msgs as &$mm) { if ($mm['user'] === 'a1a1a1a1a1a1a1a1a1a1a1a1' && $mm['mailbox'] === $inbox) { if ($mm['uid'] === 8) { $mm['labels'] = ['Work']; } if ($mm['uid'] === 9) { $mm['labels'] = ['Work', 'Personal']; } } } unset($mm);
    $T = 'f0f0f0f0f0f0f0f0f0f0f0f0';
    $msgs[] = ['uid' => 2, 'user' => 'a1a1a1a1a1a1a1a1a1a1a1a1', 'mailbox' => boxId('a1a1a1a1a1a1a1a1a1a1a1a1', 'Projects'), 'thread' => $T, 'subject' => 'Kickoff', 'from' => ['name' => 'Carol', 'address' => 'carol@example.org'],
        'intro' => 'Can we start Monday?', 'html' => ['<p>Can we start Monday?</p>'], 'text' => 'Can we start Monday?', 'seen' => false, 'flagged' => false, 'date' => '2026-10-20T09:00:00.000Z', 'attachments' => []];
    $msgs[] = ['uid' => 3, 'user' => 'a1a1a1a1a1a1a1a1a1a1a1a1', 'mailbox' => boxId('a1a1a1a1a1a1a1a1a1a1a1a1', 'Projects'), 'thread' => $T, 'subject' => 'Re: Kickoff', 'from' => ['name' => '', 'address' => 'alice@example.com'],
        'to' => [['name' => 'Carol', 'address' => 'carol@example.org']], 'intro' => 'Monday works', 'html' => ['<p>Monday works</p>'], 'text' => 'Monday works', 'seen' => true, 'flagged' => false, 'date' => '2026-10-20T10:00:00.000Z', 'attachments' => []];
    $msgs[] = ['uid' => 1, 'user' => 'a1a1a1a1a1a1a1a1a1a1a1a1', 'mailbox' => boxId('a1a1a1a1a1a1a1a1a1a1a1a1', 'Archive'), 'thread' => $T, 'subject' => 'Re: Kickoff', 'from' => ['name' => 'Bob', 'address' => 'bob@example.org'],
        'intro' => 'Count me in', 'html' => ['<p>Count me in</p>'], 'text' => 'Count me in', 'seen' => true, 'flagged' => false, 'date' => '2026-10-21T08:00:00.000Z', 'attachments' => []];
    $labels = ['a1a1a1a1a1a1a1a1a1a1a1a1' => [['id' => substr(sha1('label-Work'), 0, 24), 'name' => 'Work', 'color' => 'blue'], ['id' => substr(sha1('label-Personal'), 0, 24), 'name' => 'Personal', 'color' => 'green']], 'b2b2b2b2b2b2b2b2b2b2b2b2' => [], 'c3c3c3c3c3c3c3c3c3c3c3c3' => [], 'd4d4d4d4d4d4d4d4d4d4d4d4' => [], 'e5e5e5e5e5e5e5e5e5e5e5e5' => []];
    $st = ['users' => $users, 'boxes' => $boxes, 'msgs' => $msgs, 'labels' => $labels, 'meta' => ['a1a1a1a1a1a1a1a1a1a1a1a1' => ['other' => ['keep' => 1]], 'b2b2b2b2b2b2b2b2b2b2b2b2' => [], 'c3c3c3c3c3c3c3c3c3c3c3c3' => [], 'd4d4d4d4d4d4d4d4d4d4d4d4' => [], 'e5e5e5e5e5e5e5e5e5e5e5e5' => []],
        'register' => ['a1a1a1a1a1a1a1a1a1a1a1a1' => [['id' => 'r1', 'name' => 'Zed Zebra', 'address' => 'zed@example.org'], ['id' => 'r2', 'name' => 'Bob Builder', 'address' => 'bob@example.org'], ['id' => 'r3', 'name' => '', 'address' => 'newsletter@example.net'], ['id' => 'r4', 'name' => 'Zoe <script>', 'address' => 'zoe@example.org']]],
        'names' => ['a1a1a1a1a1a1a1a1a1a1a1a1' => 'Test User', 'b2b2b2b2b2b2b2b2b2b2b2b2' => 'Bob', 'c3c3c3c3c3c3c3c3c3c3c3c3' => '', 'd4d4d4d4d4d4d4d4d4d4d4d4' => '', 'e5e5e5e5e5e5e5e5e5e5e5e5' => 'PW Tester'],
        'addresses' => ['a1a1a1a1a1a1a1a1a1a1a1a1' => [['id' => 'ad0000000000000000000001', 'address' => 'alice@example.com', 'name' => '', 'main' => true], ['id' => 'ad0000000000000000000002', 'address' => 'sales@example.com', 'name' => 'Sales Team', 'main' => false], ['id' => 'ad0000000000000000000003', 'address' => 'alice.work@example.org', 'name' => '', 'main' => false]],
            'b2b2b2b2b2b2b2b2b2b2b2b2' => [['id' => 'bd0000000000000000000001', 'address' => 'bob@example.com', 'name' => '', 'main' => true]],
            'e5e5e5e5e5e5e5e5e5e5e5e5' => [['id' => 'ed0000000000000000000001', 'address' => 'pwtest@example.com', 'name' => '', 'main' => true]]],
        'autoreply' => [], 'filterSeq' => 100,
        'filters' => ['a1a1a1a1a1a1a1a1a1a1a1a1' => [
            ['id' => 'f100000000000000000000a1', 'name' => 'Invoices', 'disabled' => false, 'originalQuery' => ['from' => 'billing@'], 'originalAction' => ['seen' => true, 'labels' => [substr(sha1('label-Work'), 0, 24)], 'mailbox' => boxId('a1a1a1a1a1a1a1a1a1a1a1a1', 'Archive')]],
            ['id' => 'f100000000000000000000a2', 'name' => 'Audit copy', 'disabled' => false, 'originalQuery' => ['from' => 'ceo@'], 'originalAction' => ['targets' => ['audit@example.net']]],
            ['id' => 'f100000000000000000000a3', 'name' => 'Purge promos', 'disabled' => false, 'originalQuery' => ['subject' => 'promo'], 'originalAction' => ['delete' => true]],
        ]],
        'flags' => [], 'revoked' => [], 'log' => []];
    foreach ($users as $addr => $u) { // sensible defaults for every account
        $st['labels'][$u['id']] ??= []; $st['meta'][$u['id']] ??= []; $st['names'][$u['id']] ??= '';
        $st['addresses'][$u['id']] ??= [['id' => 'ee' . substr($u['id'], 0, 22), 'address' => $addr, 'name' => '', 'main' => true]];
    }
    return $st;
}

function load(string $f): array
{
    if (!is_file($f)) { file_put_contents($f, json_encode(seed(), JSON_THROW_ON_ERROR)); }
    return json_decode((string) file_get_contents($f), true);
}
function save(string $f, array $s): void { file_put_contents($f, json_encode($s, JSON_THROW_ON_ERROR), LOCK_EX); }
function out(int $status, array $body): never
{
    http_response_code($status); header('Content-Type: application/json'); echo json_encode($body); exit;
}
function fail(int $status, string $msg, string $code): never { out($status, ['error' => $msg, 'code' => $code]); }

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$token = $_SERVER['HTTP_X_ACCESS_TOKEN'] ?? '';
$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
$state = load($stateFile);
$GLOBALS['__state'] = &$state;

if ($path === '/__state') { out(200, ['revoked' => $state['revoked'], 'log' => $state['log'], 'submits' => $state['submits'] ?? [], 'labels' => $state['labels'] ?? [], 'meta' => $state['meta'] ?? [], 'names' => $state['names'] ?? [], 'addresses' => $state['addresses'] ?? [], 'autoreply' => $state['autoreply'] ?? [], 'filters' => $state['filters'] ?? [], 'totpAttempts' => $state['totpAttempts'] ?? [], 'nonces' => $state['nonces'] ?? [], 'users' => array_map(fn ($u) => ['pass' => $u['pass']], $state['users'])]); }
if ($path === '/__reset') { @unlink($stateFile); out(200, ['success' => true]); }
if ($path === '/__deliver') {   // simulate new mail arriving in alice's inbox
    $u = 'a1a1a1a1a1a1a1a1a1a1a1a1'; $ib = boxId($u, 'INBOX'); $nu = 0; foreach ($state['msgs'] as $y) { if ($y['user'] === $u && $y['mailbox'] === $ib) { $nu = max($nu, $y['uid']); } }
    for ($i = 0; $i < max(1, (int) ($_GET['count'] ?? 1)); $i++) {
        $nu++; $state['msgs'][] = ['uid' => $nu, 'user' => $u, 'mailbox' => $ib, 'subject' => ($_GET['subject'] ?? 'Delivered') . (($_GET['count'] ?? 1) > 1 ? " #$i" : ''), 'from' => ['name' => $_GET['name'] ?? 'Sender', 'address' => 'sender@example.org'],
            'intro' => 'new', 'html' => ['<p>new</p>'], 'text' => 'new', 'seen' => ($_GET['seen'] ?? '0') === '1', 'flagged' => false, 'date' => gmdate('Y-m-d\TH:i:s.000\Z'), 'attachments' => []];
    }
    save($stateFile, $state); out(200, ['success' => true, 'uid' => $nu]);
}
if ($path === '/__flag') { $state['flags'][$_GET['name'] ?? ''] = ($_GET['value'] ?? '1') === '1'; save($stateFile, $state); out(200, ['success' => true]); }

$state['log'][] = $method . ' ' . $path;

if ($path === '/authenticate' && $method === 'POST') {
    if ($token !== SVC) { fail(401, 'Invalid accessToken value', 'InvalidToken'); }
    $u = $state['users'][strtolower($in['username'] ?? '')] ?? null;
    if (!$u || !hash_equals($u['pass'], (string) ($in['password'] ?? ''))) { save($stateFile, $state); fail(403, 'Authentication failed', 'AuthFailed'); }
    save($stateFile, $state);
    // Like real WildDuck, every login gets a FRESH token: a revoked one is never handed out again.
    foreach ($state['users'] as $ua => $uu) { if ($uu['id'] === $u['id'] && in_array($uu['token'], $state['revoked'], true)) { $state['users'][$ua]['token'] = $uu['token'] . 'x'; $u['token'] = $state['users'][$ua]['token']; save($stateFile, $state); } }
    $methods = $u['twofa'] ?? [];
    $res = ['success' => true, 'id' => $u['id'], 'username' => explode('@', strtolower($in['username']))[0], 'address' => strtolower($in['username']), 'scope' => $in['scope'] ?? 'master', 'require2fa' => $methods ?: false, 'requirePasswordChange' => false];
    $pending = array_values(array_diff($methods, ['custom']));
    if ($pending) {
        $nonce = bin2hex(random_bytes(8)); $state['nonces'][$u['id']] = ['n' => $nonce, 'used' => false]; save($stateFile, $state);
        $res['twoFactorNonce'] = $nonce; if (in_array('totp', $pending, true)) { $res['totpNonce'] = $nonce; }
        if (!empty($state['flags']['compat2fa']) && !empty($in['token'])) { $res['token'] = $u['token']; } // unverified token (compat mode): the app must ignore it
    } elseif (!empty($in['token'])) { $res['token'] = $u['token']; }
    out(200, $res);
}
if ($path === '/authenticate' && $method === 'DELETE') {
    $state['revoked'][] = $token; save($stateFile, $state);
    out(200, ['success' => true]);
}

// All other routes need a live user token (or the service token) 
$owner = null;
foreach ($state['users'] as $u) { if ($u['token'] === $token && !in_array($token, $state['revoked'], true)) { $owner = $u['id']; } }
if ($owner === null && $token !== SVC) { fail(401, 'Invalid accessToken value', 'InvalidToken'); }

if (!preg_match('#^/users/([a-f0-9]{24})(/.*)?$#', $path, $m)) { fail(404, 'Not found', 'NotFound'); }
[$_, $uid, $rest] = [$m[0], $m[1], $m[2] ?? ''];
if ($owner !== null && $owner !== $uid) { save($stateFile, $state); fail(403, 'Access denied', 'AccessDenied'); }
if (!isset($state['boxes'][$uid])) { fail(404, 'User not found', 'UserNotFound'); }

function boxOf(array $s, string $uid, string $id): ?array { foreach ($s['boxes'][$uid] as $b) { if ($b['id'] === $id) { return $b; } } return null; }
function counters(array $s, string $uid, string $id): array
{
    $t = 0; $un = 0;
    foreach ($s['msgs'] as $m) { if ($m['user'] === $uid && $m['mailbox'] === $id) { $t++; if (!$m['seen']) { $un++; } } }
    return [$t, $un];
}
function summary(array $m): array
{
    $th = $m['thread'] ?? substr(hash('sha1', (string) $m['subject']), 0, 24);
    $cnt = 0; foreach ($GLOBALS['__state']['msgs'] as $y) { if ($y['user'] === $m['user'] && ($y['thread'] ?? substr(hash('sha1', (string) $y['subject']), 0, 24)) === $th) { $cnt++; } }
    return ['id' => $m['uid'], 'mailbox' => $m['mailbox'], 'thread' => $th, 'threadMessageCount' => $cnt, 'from' => $m['from'], 'to' => $m['to'] ?? [['name' => '', 'address' => 'alice@example.com']],
        'cc' => $m['cc'] ?? [], 'bcc' => $m['bcc'] ?? [], 'messageId' => '<' . $m['uid'] . '@example.org>', 'subject' => $m['subject'], 'date' => $m['date'], 'idate' => $m['date'], 'intro' => $m['intro'],
        'attachments' => !empty($m['attachments']), 'size' => 1234, 'seen' => $m['seen'], 'deleted' => false, 'flagged' => $m['flagged'], 'draft' => $m['draft'] ?? false, 'answered' => false, 'forwarded' => false, 'labels' => $m['labels'] ?? []];
}
function paged(array $list, array $q): array
{
    $limit = max(1, min(250, (int) ($q['limit'] ?? 20)));
    $cur = $q['next'] ?? $q['previous'] ?? null;
    $offset = $cur !== null ? (int) substr((string) $cur, 1) : 0;
    if (isset($q['previous'])) { $offset = max(0, $offset - $limit); }
    $slice = array_slice($list, $offset, $limit);
    return ['success' => true, 'total' => count($list), 'page' => intdiv($offset, $limit) + 1, 'previousCursor' => $offset > 0 ? 'p' . ($offset) : false,
        'nextCursor' => $offset + $limit < count($list) ? 'n' . ($offset + $limit) : false, 'results' => array_map('summary', $slice)];
}
function sortDesc(array $l): array { usort($l, fn ($a, $b) => $b['uid'] <=> $a['uid']); return $l; }

if ($rest === '/mailboxes' && $method === 'GET') {
    if (!empty($state['flags']['slowMailboxes'])) { sleep(2); }
    if (!empty($state['flags']['failMailboxes'])) { fail(500, 'Internal error', 'InternalError'); }
    $res = [];
    foreach ($state['boxes'][$uid] as $b) {
        [$t, $un] = counters($state, $uid, $b['id']);
        $row = ['id' => $b['id'], 'name' => basename($b['path']), 'path' => $b['path'], 'modifyIndex' => 1, 'subscribed' => true, 'hidden' => false, 'total' => $t, 'unseen' => $un];
        if ($b['specialUse']) { $row['specialUse'] = $b['specialUse']; }
        $res[] = $row;
    }
    shuffle($res); // real servers make no ordering promise
    save($stateFile, $state);
    out(200, ['success' => true, 'results' => $res]);
}
if (preg_match('#^/mailboxes/([a-f0-9]{24})/messages$#', $rest, $mm) && $method === 'GET') {
    if (!boxOf($state, $uid, $mm[1])) { fail(404, 'This mailbox does not exist', 'NoSuchMailbox'); }
    $l = array_filter($state['msgs'], fn ($x) => $x['user'] === $uid && $x['mailbox'] === $mm[1] && (empty($_GET['unseen']) || $_GET['unseen'] === 'false' || !$x['seen']));
    save($stateFile, $state);
    $r = paged(sortDesc(array_values($l)), $_GET); $r['query'] = ''; $r['specialUse'] = 'x';
    out(200, $r);
}
if ($rest === '/search' && $method === 'GET') {
    $qq = strtolower((string) ($_GET['q'] ?? ''));
    $lab = null;
    if (isset($_GET['label'])) { foreach ($state['labels'][$uid] as $lb) { if ($lb['id'] === $_GET['label'] || $lb['name'] === $_GET['label']) { $lab = $lb['name']; } } }
    $l = array_filter($state['msgs'], function ($x) use ($uid, $qq, $lab) {
        if ($x['user'] !== $uid) { return false; }
        if ($qq !== '' && !str_contains(strtolower($x['subject'] . ' ' . $x['intro'] . ' ' . $x['from']['address']), $qq)) { return false; }
        if (isset($_GET['label']) && ($lab === null || !in_array($lab, $x['labels'] ?? [], true))) { return false; }
        if (isset($_GET['thread']) && ($x['thread'] ?? substr(hash('sha1', (string) $x['subject']), 0, 24)) !== $_GET['thread']) { return false; }
        if (isset($_GET['mailbox']) && $x['mailbox'] !== $_GET['mailbox']) { return false; }
        if (($_GET['unseen'] ?? '') === 'true' && $x['seen']) { return false; }
        if (($_GET['flagged'] ?? '') === 'true' && !$x['flagged']) { return false; }
        return true;
    });
    save($stateFile, $state);
    $l = array_values($l);
    if (($_GET['order'] ?? 'desc') === 'asc') { usort($l, fn ($a, $b) => [$a['date'], $a['uid']] <=> [$b['date'], $b['uid']]); } else { $l = sortDesc($l); }
    $r = paged($l, $_GET); $r['query'] = $qq;
    out(200, $r);
}
// ---- 2FA second step
if ($rest === '/2fa/totp/check' && $method === 'POST') {
    if (!empty($state['flags']['failTotp'])) { fail(500, 'Internal error', 'InternalError'); }
    $state['totpAttempts'][] = ['user' => $uid, 'token' => $in['token'] ?? null, 'nonce' => $in['totpNonce'] ?? null];
    $n = $state['nonces'][$uid] ?? null;
    $ok = $n && !$n['used'] && hash_equals($n['n'], (string) ($in['totpNonce'] ?? '')) && empty($state['flags']['expireNonce']) && ($in['token'] ?? '') === '123456';
    if (!$ok) { save($stateFile, $state); fail(403, 'Failed to validate TOTP token', 'TotpFailed'); }
    $state['nonces'][$uid]['used'] = true; $tok = ''; foreach ($state['users'] as $uu) { if ($uu['id'] === $uid) { $tok = $uu['token']; } }
    save($stateFile, $state); out(200, ['success' => true, 'token' => $tok]);
}
// ---- user profile + address register
if ($rest === '' && $method === 'GET') {
    if (!empty($state['flags']['failUserGet'])) { fail(500, 'Internal error', 'InternalError'); }
    $addr = ''; foreach ($state['users'] as $a => $uu) { if ($uu['id'] === $uid) { $addr = $a; } }
    save($stateFile, $state);
    out(200, ['success' => true, 'id' => $uid, 'username' => explode('@', $addr)[0], 'name' => $state['names'][$uid] ?? '', 'address' => $addr, 'enabled2fa' => [], 'autoreply' => !empty($state['autoreply'][$uid]['status']), 'metaData' => $state['meta'][$uid] ?: new stdClass(),
        'targets' => [], 'spamLevel' => 50, 'tags' => [], 'hasPasswordSet' => true, 'activated' => true, 'disabled' => false, 'suspended' => false]);
}
if ($rest === '' && $method === 'PUT') {
    if (!empty($state['flags']['failMeta'])) { fail(403, 'Access denied', 'AccessDenied'); }
    if (!empty($state['flags']['failProfile']) && (array_key_exists('name', $in) || array_key_exists('password', $in))) { fail(403, 'Access denied', 'AccessDenied'); }
    if (array_key_exists('password', $in)) {
        $addr = ''; foreach ($state['users'] as $a => $uu) { if ($uu['id'] === $uid) { $addr = $a; } }
        if (!isset($in['existingPassword']) || !hash_equals($state['users'][$addr]['pass'], (string) $in['existingPassword'])) { save($stateFile, $state); fail(403, 'Password verification failed', 'PasswordVerificationFailed'); }
        $state['users'][$addr]['pass'] = (string) $in['password'];
    }
    if (array_key_exists('name', $in)) { $state['names'][$uid] = (string) $in['name']; }
    if (array_key_exists('metaData', $in)) { $state['meta'][$uid] = (array) $in['metaData']; }
    save($stateFile, $state); out(200, ['success' => true]);
}
if ($rest === '/addressregister' && $method === 'GET') {
    if (!empty($state['flags']['failRegister'])) { fail(500, 'Internal error', 'InternalError'); }
    $q = strtolower((string) ($_GET['query'] ?? '')); $lim = max(1, (int) ($_GET['limit'] ?? 20));
    $res = array_values(array_filter($state['register'][$uid] ?? [], fn ($r) => $q === '' || str_contains(strtolower($r['name'] . ' ' . $r['address']), $q)));
    save($stateFile, $state); out(200, ['success' => true, 'results' => array_slice($res, 0, $lim)]);
}
// ---- filters
if ($rest === '/filters' || preg_match('#^/filters/([a-f0-9]{24})$#', $rest, $fm)) {
    if (!empty($state['flags']['failFilters'])) { fail(500, 'Internal error', 'InternalError'); }
    $list = &$state['filters'][$uid];
    $list = $list ?? [];
    $allowed = !empty($state['flags']['smallFilterLimit']) ? 3 : 50;
    if ($rest === '/filters' && $method === 'GET') {
        $res = array_map(fn ($f) => $f + ['created' => '2026-01-01T00:00:00.000Z', 'query' => array_map(fn ($k, $v) => $k . ':' . json_encode($v), array_keys($f['originalQuery']), $f['originalQuery']), 'action' => array_map(fn ($k, $v) => $k . ':' . json_encode($v), array_keys($f['originalAction']), $f['originalAction'])], $list);
        save($stateFile, $state); out(200, ['success' => true, 'limits' => ['allowed' => $allowed, 'used' => count($list)], 'results' => $res]);
    }
    if ($rest === '/filters' && $method === 'POST') {
        if (!empty($state['flags']['failFilterWrite'])) { fail(403, 'Access denied', 'AccessDenied'); }
        if (count($list) >= $allowed) { fail(400, 'Filter limit reached', 'LimitReached'); }
        $q = (array) ($in['query'] ?? []); $a = (array) ($in['action'] ?? []);
        if (!is_string($in['name'] ?? null) || $in['name'] === '' || !$q || !$a) { fail(400, 'Invalid filter', 'InputValidationError'); }
        $state['filterSeq']++; $id = 'f1' . str_pad(dechex($state['filterSeq']), 22, '0', STR_PAD_LEFT);
        $list[] = ['id' => $id, 'name' => $in['name'], 'disabled' => (bool) ($in['disabled'] ?? false), 'originalQuery' => $q, 'originalAction' => $a];
        save($stateFile, $state); out(200, ['success' => true, 'id' => $id]);
    }
    $idx = null; foreach ($list as $i => $f) { if ($f['id'] === ($fm[1] ?? '')) { $idx = $i; } }
    if ($idx === null) { fail(404, 'Filter not found', 'FilterNotFound'); }
    if ($method === 'PUT') {
        if (!empty($state['flags']['failFilterWrite'])) { fail(403, 'Access denied', 'AccessDenied'); }
        foreach (['name', 'disabled'] as $k) { if (array_key_exists($k, $in)) { $list[$idx][$k] = $in[$k]; } }
        if (array_key_exists('query', $in)) { $list[$idx]['originalQuery'] = (array) $in['query']; }
        if (array_key_exists('action', $in)) { $list[$idx]['originalAction'] = (array) $in['action']; }
        save($stateFile, $state); out(200, ['success' => true]);
    }
    if ($method === 'DELETE') { if (!empty($state['flags']['failFilterWrite'])) { fail(403, 'Access denied', 'AccessDenied'); } array_splice($list, $idx, 1); save($stateFile, $state); out(200, ['success' => true]); }
}
// ---- addresses + autoreply
if ($rest === '/addresses' && $method === 'GET') {
    if (!empty($state['flags']['failAddresses'])) { fail(500, 'Internal error', 'InternalError'); }
    save($stateFile, $state);
    out(200, ['success' => true, 'results' => array_map(fn ($a) => $a + ['created' => '2026-01-01T00:00:00.000Z', 'tags' => []], $state['addresses'][$uid] ?? [])]);
}
if (preg_match('#^/addresses/([a-f0-9]{24})$#', $rest, $mm) && $method === 'PUT') {
    if (!empty($state['flags']['failProfile'])) { fail(403, 'Access denied', 'AccessDenied'); }
    foreach ($state['addresses'][$uid] ?? [] as $i => $a) { if ($a['id'] === $mm[1]) { $state['addresses'][$uid][$i]['name'] = (string) ($in['name'] ?? $a['name']); save($stateFile, $state); out(200, ['success' => true]); } }
    fail(404, 'Address not found', 'AddressNotFound');
}
if ($rest === '/autoreply' && $method === 'GET') {
    if (!empty($state['flags']['failAutoreply'])) { fail(500, 'Internal error', 'InternalError'); }
    $a = $state['autoreply'][$uid] ?? ['status' => false, 'name' => '', 'subject' => '', 'text' => '', 'html' => ''];
    $res = ['success' => true, 'status' => (bool) $a['status'], 'name' => $a['name'], 'subject' => $a['subject'], 'text' => $a['text'], 'html' => $a['html']];
    if (!empty($a['start'])) { $res['start'] = $a['start']; } if (!empty($a['end'])) { $res['end'] = $a['end']; }
    save($stateFile, $state); out(200, $res);
}
if ($rest === '/autoreply' && $method === 'PUT') {
    if (!empty($state['flags']['failAutoreply'])) { fail(403, 'Access denied', 'AccessDenied'); }
    $state['autoreply'][$uid] = array_intersect_key($in, array_flip(['status', 'name', 'subject', 'text', 'html'])) + ['start' => $in['start'] ?? false, 'end' => $in['end'] ?? false];
    $state['autoreply'][$uid]['start'] = is_string($in['start'] ?? false) ? $in['start'] : ''; $state['autoreply'][$uid]['end'] = is_string($in['end'] ?? false) ? $in['end'] : '';
    $state['autoreply'][$uid]['raw'] = ['start' => $in['start'] ?? null, 'end' => $in['end'] ?? null];
    save($stateFile, $state); out(200, ['success' => true]);
}
// ---- labels
if ($rest === '/labels' && $method === 'GET') {
    $res = [];
    foreach ($state['labels'][$uid] as $lb) {
        $row = ['id' => $lb['id'], 'name' => $lb['name'], 'metaData' => ['color' => $lb['color']]];
        if (($_GET['counters'] ?? '') === 'true') {
            $t = 0; $un = 0; $skip = [boxId($uid, 'Trash'), boxId($uid, 'Junk')];
            foreach ($state['msgs'] as $x) { if ($x['user'] === $uid && !in_array($x['mailbox'], $skip, true) && in_array($lb['name'], $x['labels'] ?? [], true)) { $t++; if (!$x['seen']) { $un++; } } }
            $row['total'] = $t; $row['unseen'] = $un;
        }
        $res[] = $row;
    }
    save($stateFile, $state); out(200, ['success' => true, 'labels' => $res]);
}
if ($rest === '/labels' && $method === 'POST') {
    $name = (string) ($in['name'] ?? '');
    if ($name === '') { fail(400, 'Invalid label name', 'InputValidationError'); }
    foreach ($state['labels'][$uid] as $lb) { if ($lb['name'] === $name) { out(200, ['success' => true, 'id' => $lb['id'], 'name' => $name, 'metaData' => ['color' => $lb['color']]]); } }
    $id = substr(sha1('label-' . $name . count($state['labels'][$uid])), 0, 24);
    $state['labels'][$uid][] = ['id' => $id, 'name' => $name, 'color' => $in['metaData']['color'] ?? 'gray'];
    save($stateFile, $state); out(200, ['success' => true, 'id' => $id, 'name' => $name, 'metaData' => $in['metaData'] ?? new stdClass()]);
}
if (preg_match('#^/labels/([a-f0-9]{24})$#', $rest, $mm) && in_array($method, ['PUT', 'DELETE'], true)) {
    $idx = null; foreach ($state['labels'][$uid] as $i => $lb) { if ($lb['id'] === $mm[1]) { $idx = $i; } }
    if ($idx === null) { fail(404, 'Label not found', 'LabelNotFound'); }
    $old = $state['labels'][$uid][$idx]['name'];
    if ($method === 'PUT') {
        $new = (string) ($in['name'] ?? $old);
        $state['labels'][$uid][$idx]['name'] = $new; $state['labels'][$uid][$idx]['color'] = $in['metaData']['color'] ?? $state['labels'][$uid][$idx]['color'];
        foreach ($state['msgs'] as &$x) { if ($x['user'] === $uid && !empty($x['labels'])) { $x['labels'] = array_map(fn ($n) => $n === $old ? $new : $n, $x['labels']); } } unset($x);
        save($stateFile, $state); out(200, ['success' => true, 'id' => $mm[1], 'name' => $new, 'metaData' => ['color' => $state['labels'][$uid][$idx]['color']]]);
    }
    foreach ($state['msgs'] as &$x) { if ($x['user'] === $uid && !empty($x['labels'])) { $x['labels'] = array_values(array_diff($x['labels'], [$old])); } } unset($x);
    array_splice($state['labels'][$uid], $idx, 1); save($stateFile, $state);
    out(200, ['success' => true, 'scheduled' => 'task1', 'existing' => false]);
}
// ---- mailboxes: create / rename / delete
if ($rest === '/mailboxes' && $method === 'POST') {
    $path = (string) ($in['path'] ?? '');
    foreach ($state['boxes'][$uid] as $b) { if (strcasecmp($b['path'], $path) === 0) { fail(400, 'Mailbox already exists', 'MailboxExistsError'); } }
    $state['boxes'][$uid][] = ['id' => boxId($uid, $path), 'name' => basename($path), 'path' => $path, 'specialUse' => null];
    save($stateFile, $state); out(200, ['success' => true, 'id' => boxId($uid, $path)]);
}
if (preg_match('#^/mailboxes/([a-f0-9]{24})$#', $rest, $mm) && in_array($method, ['PUT', 'DELETE'], true)) {
    $b = boxOf($state, $uid, $mm[1]); if (!$b) { fail(404, 'This mailbox does not exist', 'NoSuchMailbox'); }
    if ($b['specialUse'] || $b['path'] === 'INBOX') { fail(400, 'Special use mailboxes cannot be changed', 'DisallowedMailboxMethod'); }
    foreach ($state['boxes'][$uid] as $i => $x) { if ($x['id'] === $mm[1]) { $bi = $i; } }
    if ($method === 'PUT') { $state['boxes'][$uid][$bi]['path'] = (string) ($in['path'] ?? $b['path']); $state['boxes'][$uid][$bi]['name'] = basename($state['boxes'][$uid][$bi]['path']); }
    else { array_splice($state['boxes'][$uid], $bi, 1); $state['msgs'] = array_values(array_filter($state['msgs'], fn ($x) => !($x['user'] === $uid && $x['mailbox'] === $mm[1]))); }
    save($stateFile, $state); out(200, ['success' => true]);
}
// ---- bulk message update
if (preg_match('#^/mailboxes/([a-f0-9]{24})/messages$#', $rest, $mm) && $method === 'PUT') {
    if (!boxOf($state, $uid, $mm[1])) { fail(404, 'This mailbox does not exist', 'NoSuchMailbox'); }
    $set = [];
    foreach (explode(',', (string) ($in['message'] ?? '')) as $part) {
        if (preg_match('/^(\d+):(\d+)$/', $part, $r)) { for ($i = (int) $r[1]; $i <= (int) $r[2]; $i++) { $set[$i] = true; } } elseif (ctype_digit($part)) { $set[(int) $part] = true; }
    }
    if (!$set) { fail(400, 'Invalid message set', 'InputValidationError'); }
    $moved = []; $updated = 0;
    if (!empty($in['moveTo']) && !boxOf($state, $uid, $in['moveTo'])) { fail(404, 'Target mailbox does not exist', 'NoSuchMailbox'); }
    foreach ($state['msgs'] as &$x) {
        if ($x['user'] !== $uid || $x['mailbox'] !== $mm[1] || !isset($set[$x['uid']])) { continue; }
        foreach (['seen', 'flagged'] as $k) { if (array_key_exists($k, $in)) { $x[$k] = (bool) $in[$k]; } }
        foreach ((array) ($in['addLabels'] ?? []) as $n) { $x['labels'] = array_values(array_unique(array_merge($x['labels'] ?? [], [$n]))); }
        foreach ((array) ($in['removeLabels'] ?? []) as $n) { $x['labels'] = array_values(array_diff($x['labels'] ?? [], [$n])); }
        if (!empty($in['moveTo'])) {
            $nu = 0; foreach ($GLOBALS['__state']['msgs'] as $y) { if ($y['user'] === $uid && $y['mailbox'] === $in['moveTo']) { $nu = max($nu, $y['uid']); } }
            $moved[] = [$x['uid'], $nu + 1]; $x['mailbox'] = $in['moveTo']; $x['uid'] = $nu + 1;
        }
        $updated++;
    } unset($x);
    foreach ((array) ($in['addLabels'] ?? []) as $n) { $known = false; foreach ($state['labels'][$uid] as $lb) { if ($lb['name'] === $n) { $known = true; } } if (!$known) { $state['labels'][$uid][] = ['id' => substr(sha1('label-' . $n . 'auto'), 0, 24), 'name' => $n, 'color' => 'gray']; } }
    save($stateFile, $state);
    out(200, $moved ? ['success' => true, 'id' => $moved, 'mailbox' => $in['moveTo']] : ['success' => true, 'updated' => $updated]);
}
if (preg_match('#^/mailboxes/([a-f0-9]{24})/messages/([0-9]+)$#', $rest, $mm)) {
    if (!boxOf($state, $uid, $mm[1])) { fail(404, 'This mailbox does not exist', 'NoSuchMailbox'); }
    $idx = null;
    foreach ($state['msgs'] as $i => $x) { if ($x['user'] === $uid && $x['mailbox'] === $mm[1] && $x['uid'] === (int) $mm[2]) { $idx = $i; } }
    if ($idx === null) { fail(404, 'Message was not found', 'MessageNotFound'); }
    $x = &$state['msgs'][$idx];
    if ($method === 'GET') {
        if (($_GET['markAsSeen'] ?? '') === 'true') { $x['seen'] = true; }
        $html = array_map(fn ($h) => preg_replace('#attachment:([a-f0-9]+)/(ATT\d+)#', '/users/' . $uid . '/mailboxes/' . $mm[1] . '/messages/' . $mm[2] . '/attachments/$2', $h), $x['html']);
        $d = summary($x);
        unset($d['intro'], $d['idate'], $d['threadMessageCount']);
        $d += ['user' => $uid, 'envelope' => ['from' => $x['from']['address'], 'rcpt' => []], 'replyTo' => $x['replyTo'] ?? [], 'html' => $html, 'text' => $x['text'],
            'attachments' => array_map(fn ($a) => ['id' => $a['id'], 'filename' => $a['filename'], 'contentType' => $a['contentType'], 'related' => $a['related'], 'size' => strlen((string) base64_decode($a['content'])), 'sizeKb' => 1], $x['attachments'])];
        unset($d['attachments']); $d['attachments'] = array_map(fn ($a) => ['id' => $a['id'], 'filename' => $a['filename'], 'contentType' => $a['contentType'], 'related' => $a['related'], 'size' => strlen((string) base64_decode($a['content'])), 'sizeKb' => 1], $x['attachments']);
        $d['success'] = true; $d['id'] = $x['uid'];
        save($stateFile, $state);
        out(200, $d);
    }
    if ($method === 'PUT') {
        foreach (['seen', 'flagged'] as $k) { if (array_key_exists($k, $in)) { $x[$k] = (bool) $in[$k]; } }
        $moved = $mm[1];
        if (!empty($in['moveTo'])) {
            if (!boxOf($state, $uid, $in['moveTo'])) { fail(404, 'Target mailbox does not exist', 'NoSuchMailbox'); }
            $nu = 0; foreach ($state['msgs'] as $y) { if ($y['user'] === $uid && $y['mailbox'] === $in['moveTo']) { $nu = max($nu, $y['uid']); } }
            $x['mailbox'] = $in['moveTo']; $x['uid'] = $nu + 1; $moved = $in['moveTo'];
        }
        save($stateFile, $state);
        out(200, ['success' => true, 'id' => [$x['uid']], 'mailbox' => $moved, 'updated' => 1]);
    }
    if ($method === 'DELETE') { array_splice($state['msgs'], $idx, 1); save($stateFile, $state); out(200, ['success' => true]); }
}
if (preg_match('#^/mailboxes/([a-f0-9]{24})/messages/([0-9]+)/attachments/(ATT\d+)$#', $rest, $mm) && $method === 'GET') {
    foreach ($state['msgs'] as $x) {
        if ($x['user'] === $uid && $x['mailbox'] === $mm[1] && $x['uid'] === (int) $mm[2]) {
            foreach ($x['attachments'] as $a) {
                if ($a['id'] === $mm[3]) {
                    header('Content-Type: ' . $a['contentType']);
                    header('Content-Disposition: attachment; filename="' . $a['filename'] . '"');
                    echo base64_decode($a['content']); exit;
                }
            }
        }
    }
    fail(404, 'Attachment not found', 'AttachmentNotFound');
}
if ($rest === '/submit' && $method === 'POST') {
    $addrs = fn ($k) => array_values(array_filter((array) ($in[$k] ?? []), 'is_array'));
    $all = array_merge($addrs('to'), $addrs('cc'), $addrs('bcc'));
    $draft = !empty($in['isDraft']);
    if (!$draft && !$all) { fail(400, 'Missing recipients', 'InputValidationError'); }
    foreach ($all as $a) {
        $ad = strtolower($a['address'] ?? '');
        if ($ad === 'reject@example.com') { fail(400, 'Recipient address rejected', 'RecipientRejected'); }
        if ($ad === 'limit@example.com') { fail(429, 'Sending limit reached', 'RateLimitedError'); }
        if ($ad === 'boom@example.com') { fail(500, 'Internal error with secret details', 'InternalError'); }
    }
    $okFrom = array_column($state['addresses'][$uid] ?? [], 'address');
    if (!$draft && $okFrom && !in_array(strtolower($in['from']['address'] ?? ''), array_map('strtolower', $okFrom), true)) { fail(400, 'From address is not allowed', 'SenderNotAllowed'); }
    $boxName = $draft ? 'Drafts' : 'Sent Mail';
    $boxId = boxId($uid, $boxName);
    $nu = 0; foreach ($state['msgs'] as $y) { if ($y['user'] === $uid && $y['mailbox'] === $boxId) { $nu = max($nu, $y['uid']); } }
    if (!empty($in['draft'])) {
        foreach ($state['msgs'] as $i => $y) { if ($y['user'] === $uid && $y['mailbox'] === $in['draft']['mailbox'] && $y['uid'] === (int) $in['draft']['id']) { array_splice($state['msgs'], $i, 1); break; } }
    }
    $state['submits'][] = $in;
    $state['msgs'][] = ['uid' => $nu + 1, 'user' => $uid, 'mailbox' => $boxId, 'subject' => (string) ($in['subject'] ?? ''), 'from' => ['name' => '', 'address' => $in['from']['address'] ?? ''],
        'to' => $addrs('to'), 'cc' => $addrs('cc'), 'bcc' => $addrs('bcc'), 'intro' => mb_substr((string) ($in['text'] ?? ''), 0, 120), 'html' => [(string) ($in['html'] ?? '')], 'text' => (string) ($in['text'] ?? ''),
        'seen' => true, 'flagged' => false, 'draft' => $draft, 'date' => gmdate('Y-m-d\TH:i:s.000\Z'),
        'attachments' => array_map(fn ($a, $k) => ['id' => sprintf('ATT%05d', $k + 1), 'filename' => $a['filename'], 'contentType' => $a['contentType'], 'related' => false, 'content' => $a['content']], $in['attachments'] ?? [], array_keys($in['attachments'] ?? []))];
    save($stateFile, $state);
    out(200, ['success' => true, 'message' => ['id' => $nu + 1, 'mailbox' => $boxId, 'queueId' => 'q' . ($nu + 1)]]);
}
fail(404, 'Not found', 'NotFound');

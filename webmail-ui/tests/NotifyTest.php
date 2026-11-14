<?php
declare(strict_types=1);

function poll(Browser $b, string $q = ''): array { return $b->get('/api/notify' . ($q !== '' ? '?' . $q : '')); }
function deliver(array $p = []): void { Browser::raw('GET', Servers::WD . '/__deliver?' . http_build_query($p)); }
function pj(array $r): array { return json_decode($r['body'], true) ?? []; }

test('notify: only signed-in sessions can poll; an expired WildDuck session answers JSON 401, not a redirect', function () {
    Servers::resetWd(); $r = Browser::raw('GET', Servers::APP . '/api/notify'); eq($r['status'], 401); has($r['headers']['content-type'], 'application/json');
    $b = loggedIn(); Browser::raw('DELETE', Servers::WD . '/authenticate', null, ['X-Access-Token: user-token-alice']);
    $r = poll($b); eq($r['status'], 401); has($r['headers']['content-type'], 'application/json'); eq($b->get('/mail/inbox')['status'], 303, 'the session was ended');
});
test('notify: reports unread counts per folder, fresh on every poll, with safe headers', function () {
    $b = loggedIn(); $r = poll($b); eq($r['status'], 200); $d = pj($r);
    eq($d['inbox'], ['unseen' => 7, 'total' => 37]); eq($d['new'], []); eq($d['folders']['inbox'], 7); foreach (['drafts', 'sent', 'archive', 'spam', 'trash'] as $slug) { check(array_key_exists($slug, $d['folders']), $slug); }
    has($r['headers']['content-type'], 'application/json'); has($r['headers']['cache-control'], 'no-store'); eq($r['headers']['x-content-type-options'], 'nosniff');
    foreach (['user-token', UID_A, 'svc-token'] as $leak) { lacks($r['body'], $leak); }
    bulk($b, 'read', items(inboxId(), [1, 2, 3])); eq(pj(poll($b))['inbox']['unseen'], 4, 'no stale cache');
    $proj = inboxId(UID_A, 'Projects'); eq(pj(poll($b))['folders'][$proj], 1, 'custom folders are keyed by their id');
});
test('notify: new mail is reported once, with sender and subject, newest first and at most three', function () {
    $b = loggedIn(); deliver(['count' => 2, 'subject' => 'Hello', 'name' => 'Eve']);
    $d = pj(poll($b, 'known=37&unseen=7')); eq($d['inbox'], ['unseen' => 9, 'total' => 39]); eq(count($d['new']), 2);
    eq($d['new'][0]['subject'], 'Hello #1'); eq($d['new'][0]['from'], 'Eve'); eq($d['new'][0]['mailbox'], inboxId()); eq($d['new'][1]['subject'], 'Hello #0'); check(is_int($d['new'][0]['id']));
    eq(pj(poll($b, 'known=39&unseen=9'))['new'], [], 'nothing new when the client is up to date');
    deliver(['count' => 5]); eq(count(pj(poll($b, 'known=39&unseen=9'))['new']), 3, 'capped at three');
});
test('notify: no false alarms for moves, already-read mail, or missing/garbled parameters', function () {
    $b = loggedIn(); deliver(['seen' => '1']); eq(pj(poll($b, 'known=37&unseen=7'))['new'], [], 'a message that arrives already read is not announced');
    foreach (['', 'known=37', 'unseen=7', 'known=abc&unseen=7', 'known=-1&unseen=7', 'known=1e9&unseen=7', 'known=99999999999&unseen=7', 'known=37&unseen=%27'] as $q) {
        $r = poll($b, $q); eq($r['status'], 200, $q); eq(pj($r)['new'], [], "no announcement for: $q");
    }
    deliver(['subject' => 'Real']); eq(count(pj(poll($b, 'known=38&unseen=7'))['new']), 1);
});
test('notify: subjects and names are cleaned and cannot carry markup', function () {
    $b = loggedIn(); deliver(['subject' => "<script>alert(1)</script>\r\nBcc: x" . str_repeat('y', 400), 'name' => "Mal\x01lory <b>x</b>"]);
    $r = poll($b, 'known=37&unseen=7'); lacks($r['body'], '<script>'); lacks($r['body'], '<b>'); $n = pj($r)['new'][0];
    check(mb_strlen($n['subject']) <= 120 && !preg_match('/[\r\n\x01]/', $n['subject'] . $n['from']), 'cleaned and truncated'); has($n['subject'], '<script>alert(1)</script>', 'round-trips as text once decoded');
});
test('notify: a steady poll costs one WildDuck call; a poll with new mail costs two', function () {
    $b = loggedIn(); $mb = 'GET /users/' . UID_A . '/mailboxes'; $list = 'GET /users/' . UID_A . '/mailboxes/' . inboxId() . '/messages'; poll($b);
    $m0 = wdCalls($mb); $l0 = wdCalls($list); $all0 = count(Servers::wdState()['log']);
    poll($b, 'known=37&unseen=7'); poll($b, 'known=37&unseen=7'); eq(wdCalls($mb) - $m0, 2); eq(wdCalls($list) - $l0, 0); eq(count(Servers::wdState()['log']) - $all0, 2, 'nothing else is requested');
    deliver(); $all1 = count(Servers::wdState()['log']); poll($b, 'known=37&unseen=7'); eq(count(Servers::wdState()['log']) - $all1 - 1, 1, 'one extra call for the message list (the deliver call itself is logged first)');
});
test('notify: an upstream outage answers JSON 503 so the page can back off quietly', function () {
    $b = loggedIn(); flag('failMailboxes'); $r = poll($b); eq($r['status'], 503); has($r['headers']['content-type'], 'application/json'); eq(pj($r), ['error' => 'unavailable']); lacks($r['body'], 'InternalError');
    flag('failMailboxes', false); eq(poll($b)['status'], 200);
});
test('notify: a slow poll never blocks the user\'s other requests (session lock is released first)', function () {
    $b = loggedIn(); flag('slowMailboxes'); $jar = (new ReflectionProperty($b, 'jar'))->getValue($b);
    $mk = function (string $path) use ($jar) { $c = curl_init(Servers::APP . $path); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20]); return $c; };
    $slow = $mk('/api/notify'); $fast = $mk('/login'); $mh = curl_multi_init(); curl_multi_add_handle($mh, $slow);
    $t0 = microtime(true); $fastDone = null; $slowDone = null; $started = false;
    do {
        curl_multi_exec($mh, $running);
        if (!$started && microtime(true) - $t0 > 0.4) { curl_multi_add_handle($mh, $fast); $started = true; $tf = microtime(true); }
        while ($info = curl_multi_info_read($mh)) { $now = microtime(true); if ($info['handle'] === $fast) { $fastDone = $now - $tf; } else { $slowDone = $now - $t0; } }
        if ($running) { curl_multi_select($mh, 0.05); }
    } while ($running || !$started);
    check($slowDone !== null && $slowDone >= 1.8, 'the poll really was slow: ' . round((float) $slowDone, 2)); check($fastDone !== null && $fastDone < 1.0, 'another request finished quickly while the poll was waiting: ' . round((float) $fastDone, 2) . 's');
    flag('slowMailboxes', false); curl_multi_close($mh);
});
test('notify: authenticated pages carry the hooks; sign-in pages do not poll', function () {
    $b = loggedIn(); $h = $b->get('/mail/inbox')['body'];
    has($h, '<body data-notify data-inbox-total="37" data-inbox-unseen="7" data-path="inbox">'); has($h, '/assets/notify.js'); has($h, 'data-notify-toggle'); has($h, 'data-slug="inbox"'); has($h, 'data-slug="spam"');
    has($b->get('/settings/profile')['body'], 'data-path="settings-profile"'); eq(Browser::raw('GET', Servers::APP . '/assets/notify.js')['status'], 200);
    lacks((new Browser())->get('/login')['body'], 'notify.js'); $t = new Browser(); pwStep($t); lacks($t->get('/login/2fa')['body'], 'notify.js');
    check(!preg_match('/<script(?![^>]*src=)/i', $h), 'still no inline scripts');
});

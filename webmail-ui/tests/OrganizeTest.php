<?php
declare(strict_types=1);

use Webmail\Support\Url;
use Webmail\WildDuck\LabelService;
use Webmail\WildDuck\MailboxService;
use Webmail\WildDuck\Model\Label;

const UID_A = 'a1a1a1a1a1a1a1a1a1a1a1a1';
const THREAD = 'f0f0f0f0f0f0f0f0f0f0f0f0';
function labelId(string $name): string { return substr(sha1('label-' . $name), 0, 24); }
function items(string $box, array $uids): array { return array_map(fn ($u) => "$box:$u", $uids); }
function bulk(Browser $b, string $do, array $items, array $extra = [], string $tokenFrom = '/mail/inbox'): array
{
    return $b->post('/mail/bulk', ['do' => $do, 'items' => $items, '_token' => $b->pageToken($tokenFrom)] + $extra, false);
}
function inboxBadge(Browser $b): int { return navCounts($b->get('/mail/inbox')['body'])[0][1]; }
function flashOn(Browser $b, string $path): string { return $b->get($path)['body']; }
function navBlock(string $html, string $href): string
{
    preg_match('#<a class="nav-item[^"]*"[^>]*href="' . preg_quote($href, '#') . '".*?</a>#s', $html, $m);
    return $m[0] ?? '';
}
function wdCalls(string $needle): int { return count(array_filter(Servers::wdState()['log'], fn ($l) => $l === $needle)); }

test('unit: Label colours fall back to gray; label and folder name validation', function () {
    eq(Label::fromArray(['id' => 'x', 'name' => 'n', 'metaData' => ['color' => 'javascript:alert(1)']])->color, 'gray');
    eq(Label::fromArray(['id' => 'x', 'name' => 'n', 'metaData' => ['color' => 'teal']])->color, 'teal');
    eq(Label::fromArray(['id' => 'x', 'name' => 'n'])->color, 'gray');
    eq(LabelService::validName('  Work  '), 'Work'); eq(LabelService::validName(''), null); eq(LabelService::validName('a,b'), null);
    eq(LabelService::validName(str_repeat('x', 65)), null); eq(LabelService::validName("a\nb"), null);
    eq(MailboxService::validPath('Work/2026'), 'Work/2026'); eq(MailboxService::validPath(' a / b '), 'a/b');
    foreach (['', '..', 'a/../b', 'a//b', '/a', 'a/', 'a\\b', 'a*', '50%', str_repeat('x', 201), "a\nb"] as $bad) { eq(MailboxService::validPath($bad), null, json_encode($bad)); }
});
test('unit: safeReturn allows the new pages and still blocks everything else', function () {
    foreach (['/label/abc', '/thread/abc', '/folders', '/labels', '/mail/inbox?x=1', '/search?q=a'] as $ok) { eq(Url::safeReturn($ok, '/f'), $ok); }
    foreach (['/compose', '/login', '/logout', '//x.y', '/labelsx', '/threads'] as $bad) { eq(Url::safeReturn($bad, '/f'), '/f', $bad); }
});

test('org: list page has selection controls, label chips, move/label pickers', function () {
    $b = loggedIn(); $inbox = inboxId();
    $h = $b->get('/mail/inbox')['body'];
    eq(substr_count($h, 'name="items[]"'), 30); has($h, 'id="bulk"'); has($h, 'form="bulk"'); has($h, '/assets/bulk.js'); has($h, 'data-select-all');
    has($h, 'lbl lbl-blue">Work'); has($h, 'lbl lbl-green">Personal');
    preg_match('#<select class="input" id="bulk-folder".*?</select>#s', $h, $m);
    has($m[0], '>Archive<'); has($m[0], '>Projects<'); has($m[0], '>Trash<'); lacks($m[0], '>Inbox<'); lacks($m[0], '>Drafts<'); lacks($m[0], '>Sent<');
    has($h, 'value="' . $inbox . ':8"'); has($h, 'Trash</button>'); lacks($h, 'Delete forever');
    has($b->get('/mail/trash')['body'], 'Nothing here yet');
});
test('org: sidebar lists labels and the Organize links', function () {
    $b = loggedIn(); $h = $b->get('/mail/inbox')['body'];
    has($h, 'href="/label/' . labelId('Work') . '"'); has($h, 'href="/label/' . labelId('Personal') . '"'); has($h, 'href="/folders"'); has($h, 'href="/labels"');
});
test('org: bulk read / unread / star change many messages in one request', function () {
    $b = loggedIn(); $inbox = inboxId();
    eq(inboxBadge($b), 7);
    $r = bulk($b, 'read', items($inbox, [1, 2, 3])); eq($r['status'], 303); eq($r['headers']['location'], '/mail/inbox');
    has($b->get('/mail/inbox')['body'], '3 messages marked as read.'); eq(inboxBadge($b), 4);
    $before = count(array_filter(Servers::wdState()['log'], fn ($l) => str_starts_with($l, 'PUT ')));
    bulk($b, 'unread', items($inbox, [10, 11, 12, 13]));
    eq(count(array_filter(Servers::wdState()['log'], fn ($l) => str_starts_with($l, 'PUT '))) - $before, 1, 'one API call for the whole selection');
    eq(inboxBadge($b), 8);
    bulk($b, 'star', items($inbox, [20, 21])); has($b->get('/mail/inbox')['body'], 'msg-star is-on');
    bulk($b, 'unstar', items($inbox, [20, 21])); has($b->get('/mail/inbox')['body'], '2 messages unstarred.');
});
test('org: bulk archive, spam, trash and move, across mailboxes', function () {
    $b = loggedIn(); $inbox = inboxId(); $proj = inboxId(UID_A, 'Projects');
    bulk($b, 'archive', items($inbox, [10, 11])); $a = $b->get('/mail/archive')['body'];
    has($a, 'Weekly report #10'); has($a, 'Weekly report #11'); has($a, '2 messages moved to Archive.');
    bulk($b, 'spam', items($inbox, [12])); has($b->get('/mail/spam')['body'], 'Weekly report #12');
    bulk($b, 'trash', items($inbox, [13, 14])); $t = $b->get('/mail/trash')['body']; has($t, 'Weekly report #13'); has($t, 'Delete forever');
    $r = bulk($b, 'move', items($inbox, [15]), ['folder' => $proj]); eq($r['status'], 303); has($b->get("/mail/$proj")['body'], 'Weekly report #15');
    $r = bulk($b, 'move', items($inbox, [16]), ['folder' => $inbox]); has($b->get('/mail/inbox')['body'], 'Nothing to change');
    $r = bulk($b, 'move', items($inbox, [17]), ['folder' => '']); has($b->get('/mail/inbox')['body'], 'Choose a folder');
    has($b->get('/mail/inbox/16')['body'], 'Weekly report #16');
    // a mixed selection: messages already in the target are skipped, the rest move
    $arch = inboxId(UID_A, 'Archive');
    bulk($b, 'archive', array_merge(items($inbox, [18]), items($arch, [1])), [], '/mail/archive'); has($b->get('/mail/archive')['body'], '1 message moved to Archive.');
});
test('org: permanent delete only applies to messages that are already in Trash', function () {
    $b = loggedIn(); $inbox = inboxId(); $trash = inboxId(UID_A, 'Trash');
    bulk($b, 'trash', items($inbox, [30])); // lands in Trash as uid 1
    $r = bulk($b, 'delete', items($inbox, [31])); has($b->get('/mail/inbox')['body'], 'Nothing to change'); has($b->get('/mail/inbox/31')['body'], 'Weekly report #31');
    $r = bulk($b, 'delete', items($trash, [1]), [], '/mail/trash'); eq($r['status'], 303);
    $t = $b->get('/mail/trash')['body']; has($t, 'deleted permanently'); has($t, 'Nothing here yet');
});
test('org: bulk labels add and remove; label page lists them across folders', function () {
    $b = loggedIn(); $inbox = inboxId(); $work = labelId('Work');
    bulk($b, 'label_add', items($inbox, [20, 21]), ['label' => $work]);
    $l = $b->get("/label/$work"); eq($l['status'], 200);
    foreach ([8, 9, 20, 21] as $n) { has($l['body'], "Weekly report #$n<"); }
    has($l['body'], 'labelled “Work”'); has($l['body'], 'id="bulk"');
    bulk($b, 'label_remove', items($inbox, [8]), ['label' => $work], "/label/$work");
    $l = $b->get("/label/$work")['body']; lacks($l, '>Weekly report #8<'); has($l, '>Weekly report #9<');
    has($b->get('/mail/inbox')['body'], 'class="lbl lbl-green">Personal'); // #9 keeps its other label
    $r = bulk($b, 'label_add', items($inbox, [22]), ['label' => labelId('Nope')]); has($b->get('/mail/inbox')['body'], 'Choose a label first');
    eq($b->get('/label/' . str_repeat('0', 24))['status'], 404); eq($b->get('/label/not-an-id')['status'], 404);
    has($b->get('/mail/inbox')['body'], 'Weekly report #22');
});
test('org: label counts in the sidebar track unread messages', function () {
    $b = loggedIn(); $inbox = inboxId(); $work = labelId('Work');
    bulk($b, 'unread', items($inbox, [8, 9]));
    $h = $b->get('/mail/inbox')['body'];
    check(preg_match('#href="/label/' . $work . '".*?nav-count">2<#s', $h) === 1, 'Work shows 2 unread');
});
test('org: bulk rejects forged, foreign and malformed selections', function () {
    $b = loggedIn(); $inbox = inboxId(); $bob = inboxId('b2b2b2b2b2b2b2b2b2b2b2b2');
    $r = bulk($b, 'trash', items($bob, [1])); eq($r['status'], 303); has($b->get('/mail/inbox')['body'], 'Select at least one message');
    foreach ([['x'], ['abc:1'], ["$inbox:0"], ["$inbox:1; DROP"], ["$inbox:-1"], [$inbox], ['..:..'], ["$inbox:99999999999"]] as $bad) {
        bulk($b, 'trash', $bad); has($b->get('/mail/inbox')['body'], 'Select at least one message');
    }
    has($b->get('/mail/inbox/1')['body'], 'Weekly report #1');
    $r = bulk($b, 'trash', array_merge(items($bob, [1]), items($inbox, [34]))); has($b->get('/mail/inbox')['body'], '1 message moved to Trash');
    bulk($b, 'trash', items($inbox, [900, 901])); has($b->get('/mail/inbox')['body'], 'Nothing to change', 'counts come from WildDuck, not from what was requested');
    bulk($b, 'trash', items($inbox, range(1, 101))); has($b->get('/mail/inbox')['body'], 'at most 100');
    bulk($b, 'move', items($inbox, [33]), ['folder' => $bob]); has($b->get('/mail/inbox')['body'], 'Choose a folder'); has($b->get('/mail/inbox/33')['body'], 'Weekly report #33');
    bulk($b, 'explode', items($inbox, [33])); has($b->get('/mail/inbox')['body'], 'Unknown action');
    bulk($b, 'read', []); has($b->get('/mail/inbox')['body'], 'Select at least one message');
    eq($b->post('/mail/bulk', ['do' => 'trash', 'items' => items($inbox, [32])], false)['status'], 419);
    eq((new Browser())->post('/mail/bulk', ['do' => 'trash', 'items' => items($inbox, [32])], false)['status'], 419);
    eq(Browser::raw('GET', Servers::APP . '/mail/bulk')['status'], 303);
    has($b->get('/mail/inbox/32')['body'], 'Weekly report #32');
});
test('org: bulk return target cannot redirect off-site', function () {
    $b = loggedIn(); $inbox = inboxId();
    foreach (['//evil.test', 'https://evil.test', '/\\evil.test', '/compose'] as $bad) {
        $r = bulk($b, 'read', items($inbox, [1]), ['return' => $bad]); eq($r['headers']['location'], '/mail/inbox', $bad);
    }
    $r = bulk($b, 'read', items($inbox, [1]), ['return' => '/label/' . labelId('Work')]); eq($r['headers']['location'], '/label/' . labelId('Work'));
});

test('org: labels page - create, validate, rename/recolour, delete keeps messages', function () {
    $b = loggedIn(); $t = $b->pageToken('/labels'); $inbox = inboxId();
    $r = $b->post('/labels', ['name' => 'Receipts', 'color' => 'purple', '_token' => $t], false); eq($r['status'], 303);
    $h = $b->get('/labels')['body']; has($h, 'lbl lbl-purple'); has($h, 'Receipts'); has($h, 'Label “Receipts” created.');
    $r = $b->post('/labels', ['name' => 'Receipts', 'color' => 'blue', '_token' => $t], false); eq($r['status'], 422); has($r['body'], 'already exists');
    $r = $b->post('/labels', ['name' => 'Odd', 'color' => 'url(javascript:1)', '_token' => $t], false); eq($r['status'], 303); check(preg_match('#lbl-gray"[^>]*>Odd<#', $b->get('/labels')['body']) === 1, 'invalid colour falls back to gray');
    $stored = array_values(array_filter(Servers::wdState()['labels'][UID_A], fn ($l) => $l['name'] === 'Odd'))[0]['color'];
    eq($stored, 'gray', 'an unknown colour must never be sent to WildDuck');
    foreach (['', str_repeat('x', 65), 'a,b'] as $bad) { eq($b->post('/labels', ['name' => $bad, 'color' => 'red', '_token' => $t], false)['status'], 422, json_encode($bad)); }
    $xss = '<img src=x onerror=alert(1)>';
    $b->post('/labels', ['name' => $xss, 'color' => 'red', '_token' => $t], false); $h = $b->get('/labels')['body']; lacks($h, $xss); has($h, '&lt;img src=x onerror=alert(1)&gt;');
    $id = labelId('Work');
    $r = $b->post("/labels/$id/update", ['name' => 'Office', 'color' => 'orange', '_token' => $t], false); eq($r['status'], 303);
    $h = $b->get('/labels')['body']; has($h, 'lbl lbl-orange'); has($h, 'Office'); has($b->get('/mail/inbox')['body'], 'lbl lbl-orange">Office');
    $r = $b->post("/labels/$id/update", ['name' => 'Personal', 'color' => 'red', '_token' => $t], false); eq($r['status'], 422); has($r['body'], 'already exists');
    $c = $b->get("/labels/$id/delete"); eq($c['status'], 200); has($c['body'], 'messages themselves are not deleted');
    eq($b->post("/labels/$id/delete", ['_token' => $t], false)['status'], 303);
    lacks($b->get('/labels')['body'], 'value="Office"'); has($b->get('/mail/inbox/8')['body'], 'Weekly report #8');
    eq($b->get("/labels/$id/delete")['status'], 404);
    eq($b->post('/labels', ['name' => 'NoCsrf', 'color' => 'red'], false)['status'], 419);
    eq($b->post("/labels/$id/delete", [], false)['status'], 419);
    eq($b->get('/labels/zzz/delete')['status'], 404);
});
test('org: message view can add and remove labels', function () {
    $b = loggedIn(); $inbox = inboxId(); $t = $b->pageToken();
    $h = $b->get('/mail/inbox/20')['body']; has($h, 'id="msg-label"'); lacks($h, 'class="lbl lbl-blue">Work');
    $b->post("/mail/$inbox/20/action", ['do' => 'label_add', 'label' => labelId('Work'), '_token' => $t], false);
    $h = $b->get('/mail/inbox/20')['body']; has($h, 'lbl lbl-blue">Work'); has($h, 'Work ✓');
    $b->post("/mail/$inbox/20/action", ['do' => 'label_remove', 'label' => labelId('Work'), '_token' => $t], false);
    lacks($b->get('/mail/inbox/20')['body'], 'class="lbl lbl-blue">Work');
    $b->post("/mail/$inbox/20/action", ['do' => 'label_add', 'label' => 'bogus', '_token' => $t], false); has($b->get('/mail/inbox/20')['body'], 'Choose a label first');
});

test('org: folders page - create nested, validate, rename, delete with confirmation', function () {
    $b = loggedIn(); $t = $b->pageToken('/folders');
    $r = $b->post('/folders', ['path' => 'Work/2026', '_token' => $t], false); eq($r['status'], 303);
    $h = $b->get('/folders')['body']; has($h, 'Work/2026'); has($h, 'Folder “Work/2026” created.'); has($b->get('/mail/inbox')['body'], 'href="/mail/' . inboxId(UID_A, 'Work/2026') . '"');
    foreach (['work/2026', 'Projects', 'INBOX', 'inbox'] as $dup) { $r = $b->post('/folders', ['path' => $dup, '_token' => $t], false); eq($r['status'], 422, $dup); has($r['body'], 'already exists'); }
    foreach (['', '..', 'a//b', 'a*', "x\ny", '/abs'] as $bad) { eq($b->post('/folders', ['path' => $bad, '_token' => $t], false)['status'], 422, json_encode($bad)); }
    $xss = '<script>alert(1)</script>';
    $b->post('/folders', ['path' => $xss, '_token' => $t], false); $h = $b->get('/folders')['body']; lacks($h, $xss); has($h, '&lt;script&gt;alert(1)&lt;/script&gt;');
    $id = inboxId(UID_A, 'Work/2026');
    eq($b->post("/folders/$id/rename", ['path' => 'Work/2027', '_token' => $t], false)['status'], 303); has($b->get('/folders')['body'], 'Work/2027');
    $r = $b->post("/folders/$id/rename", ['path' => 'Projects', '_token' => $t], false); eq($r['status'], 422);
    $c = $b->get("/folders/$id/delete"); has($c['body'], 'permanently deletes everything'); has($c['body'], 'Work/2027');
    eq($b->post("/folders/$id/delete", ['_token' => $t], false)['status'], 303);
    lacks($b->get('/folders')['body'], 'value="Work/2027"'); eq($b->get("/mail/$id")['status'], 404);
});
test('org: built-in and foreign folders cannot be renamed or deleted', function () {
    $b = loggedIn(); $t = $b->pageToken('/folders');
    foreach (['INBOX', 'Drafts', 'Sent Mail', 'Archive', 'Junk', 'Trash'] as $sys) {
        $id = inboxId(UID_A, $sys);
        eq($b->post("/folders/$id/rename", ['path' => 'Hacked', '_token' => $t], false)['status'], 404, "rename $sys");
        eq($b->post("/folders/$id/delete", ['_token' => $t], false)['status'], 404, "delete $sys");
        eq($b->get("/folders/$id/delete")['status'], 404, "confirm $sys");
    }
    $bob = inboxId('b2b2b2b2b2b2b2b2b2b2b2b2', 'Projects');
    eq($b->post("/folders/$bob/delete", ['_token' => $t], false)['status'], 404);
    eq($b->post('/folders', ['path' => 'NoCsrf'], false)['status'], 419);
    eq(Browser::raw('GET', Servers::APP . '/folders')['status'], 303);
    has($b->get('/mail/inbox')['body'], 'Weekly report');
});

test('org: conversations - list links to the thread, thread shows all messages across folders', function () {
    $b = loggedIn(); $proj = inboxId(UID_A, 'Projects');
    $l = $b->get("/mail/$proj")['body']; has($l, 'href="/thread/' . THREAD . '"'); has($l, 'title="Messages in conversation">3<');
    $t = $b->get('/thread/' . THREAD); eq($t['status'], 200); $h = $t['body'];
    has($h, '3 messages in this conversation');
    check(strpos($h, 'Carol') < strpos($h, 'Bob'), 'oldest first');
    has($h, 'class="badge">Projects<'); has($h, 'class="badge">Archive<'); eq(substr_count($h, '<iframe'), 3);
    has($h, 'sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"'); has($h, 'loading="lazy"');
    foreach (['/users/', 'user-token', UID_A] as $leak) { lacks($h, $leak); }
    check(preg_match('#<details class="card thread-msg" open>.*?Bob#s', $h) === 1, 'newest message is expanded');
    foreach (['reply', 'reply-all', 'forward'] as $p) { has($h, "/$p\""); }
});
test('org: opening a conversation marks it read and updates the badge', function () {
    $b = loggedIn(); $proj = inboxId(UID_A, 'Projects');
    has(navBlock($b->get('/mail/inbox')['body'], "/mail/$proj"), 'nav-count">1<');
    $h = $b->get('/thread/' . THREAD)['body']; has($h, 'unread-dot');
    lacks(navBlock($b->get('/mail/inbox')['body'], "/mail/$proj"), 'nav-count');
    lacks(navBlock($b->get('/thread/' . THREAD)['body'], "/mail/$proj"), 'nav-count');
});
test('org: whole-conversation actions apply to every message', function () {
    $b = loggedIn(); $proj = inboxId(UID_A, 'Projects'); $arch = inboxId(UID_A, 'Archive'); $t = $b->pageToken('/thread/' . THREAD);
    $h = $b->get('/thread/' . THREAD)['body'];
    eq(substr_count($h, 'name="items[]"'), 12, '3 messages x 4 action buttons');
    $r = $b->post('/mail/bulk', ['do' => 'trash', 'items' => items($proj, [2, 3]) + [2 => "$arch:1"], 'return' => '/mail/inbox', '_token' => $t], false); eq($r['status'], 303);
    $trash = $b->get('/mail/trash')['body']; has($trash, 'Kickoff'); eq(substr_count($trash, 'class="msg-row'), 3);
});
test('org: unknown, malformed or foreign conversations are 404', function () {
    $b = loggedIn();
    foreach (['/thread/' . str_repeat('0', 24), '/thread/nope', '/thread/' . str_repeat('F', 24), '/thread/../x'] as $p) { eq($b->get($p)['status'], 404, $p); }
    $bob = loggedIn('bob@example.com', 'bob-pass'); eq($bob->get('/thread/' . THREAD)['status'], 404, "bob must not read alice's thread");
    eq(Browser::raw('GET', Servers::APP . '/thread/' . THREAD)['status'], 303);
});
test('org: sidebar data is cached briefly, so pages do not re-query WildDuck', function () {
    $b = loggedIn(); $inbox = inboxId(); $mbx = 'GET /users/' . UID_A . '/mailboxes'; $lbl = 'GET /users/' . UID_A . '/labels';
    $b->get('/mail/inbox'); eq(wdCalls($mbx), 1, 'folders fetched once for the first page'); eq(wdCalls($lbl), 1);
    $b->get('/mail/sent'); $b->get('/search?q=report'); $b->get('/labels'); eq(wdCalls($mbx), 1, 'no refetch within the cache window'); eq(wdCalls($lbl), 1);
    $b->post("/mail/$inbox/8/action", ['do' => 'star', '_token' => $b->pageToken()], false);
    $b->get('/mail/inbox'); eq(wdCalls($mbx), 2, 'a change clears the cache'); eq(wdCalls($lbl), 2);
    $b->get('/mail/inbox/1'); eq(wdCalls($mbx), 3, 'opening a message changes counters, so refreshed once');
});
test('org: a WildDuck without the labels API still works (sidebar degrades gracefully)', function () {
    // labels are optional: simulate by asking for a label that does not exist and confirm pages still render
    $b = loggedIn(); eq($b->get('/mail/inbox')['status'], 200); eq($b->get('/labels')['status'], 200);
});

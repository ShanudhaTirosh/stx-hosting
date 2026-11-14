<?php
declare(strict_types=1);

use Webmail\Mail\{AddressParser, HtmlText, Prefill};
use Webmail\Support\HtmlSanitizer;
use Webmail\WildDuck\Model\MessageDetail;

/** Open a compose-type page, return [html, hiddenFields]. */
function composePage(Browser $b, string $path = '/compose'): array
{
    $r = $b->get($path);
    eq($r['status'], 200, "GET $path");
    preg_match_all('#<input type="hidden" name="([^"]+)" value="([^"]*)">#', $r['body'], $m, PREG_SET_ORDER);
    $h = [];
    foreach ($m as $x) { $h[$x[1]] = html_entity_decode($x[2], ENT_QUOTES); }
    // Like a browser: visible text inputs, the editor's HTML (compose.js copies it into `html`), ticked carry-over boxes.
    preg_match_all('#<input class="input" id="\w+" name="(\w+)" type="text" value="([^"]*)"#', $r['body'], $t, PREG_SET_ORDER);
    foreach ($t as $x) { $h[$x[1]] = html_entity_decode($x[2], ENT_QUOTES); }
    if (preg_match('#data-editor hidden>(.*?)</div>\s*<input type="hidden" name="html" data-editor-field#s', $r['body'], $e)) { $h['html'] = $e[1]; }
    // <select>s: the selected option, or the first one when none is marked (what a browser submits).
    preg_match_all('#<select[^>]*name="(\w+)"[^>]*>(.*?)</select>#s', $r['body'], $sel, PREG_SET_ORDER);
    foreach ($sel as $x) {
        if (preg_match('#<option value="([^"]*)"[^>]*\sselected#', $x[2], $o) || preg_match('#<option value="([^"]*)"#', $x[2], $o)) { $h[$x[1]] = html_entity_decode($o[1], ENT_QUOTES); }
    }
    preg_match_all('#name="keep\[\]" value="([^"]+)" checked#', $r['body'], $k);
    if ($k[1]) { $h['keep'] = $k[1]; }
    return [$r['body'], $h];
}
/** Submit the compose form the way a browser would (hidden fields + overrides). */
function sendForm(Browser $b, array $over = [], string $page = '/compose', array $hidden = null): array
{
    $hidden ??= composePage($b, $page)[1];
    $form = $over + $hidden + ['do' => 'send'];
    return Browser::raw('POST', Servers::APP . '/compose', $form, [], (new ReflectionProperty($b, 'jar'))->getValue($b));
}
function lastSubmit(): array { $s = Servers::wdState()['submits']; return $s ? end($s) : []; }
function submitCount(): int { return count(Servers::wdState()['submits']); }
function box(string $name, string $user = 'a1a1a1a1a1a1a1a1a1a1a1a1'): string { return inboxId($user, $name); }

test('unit: AddressParser handles names, quotes, separators, duplicates and junk', function () {
    $r = AddressParser::parse('"Doe, John" <john@example.com>; Jane <jane@example.org>, JANE@example.org, bob@example.net,,');
    eq(array_column($r['list'], 'address'), ['john@example.com', 'jane@example.org', 'bob@example.net']);
    eq($r['list'][0]['name'], 'Doe, John'); eq($r['invalid'], []);
    $r = AddressParser::parse("evil@example.com\r\nBcc: x@y.z, not-an-email, a@b, <>, \"x\"<q@w.e>");
    foreach ($r['list'] as $a) { check(!preg_match('/[\r\n]/', $a['address'] . $a['name'])); }
    check(count($r['invalid']) >= 2, 'invalid tokens reported');
    eq(AddressParser::format([['name' => 'A', 'address' => 'a@b.co'], ['name' => '', 'address' => 'c@d.co']]), 'A <a@b.co>, c@d.co');
    eq(AddressParser::parse('')['list'], []);
});
test('unit: HtmlText produces a readable plain-text alternative', function () {
    $t = HtmlText::toText('<p>Hello <b>world</b></p><ul><li>one</li><li>two</li></ul><blockquote><p>quoted</p></blockquote><p><a href="https://x.example">site</a></p>');
    has($t, 'Hello world'); has($t, '- one'); has($t, '- two'); has($t, '> quoted'); has($t, 'site (https://x.example)');
    eq(HtmlText::toText(''), '');
});
test('unit: outgoing sanitiser drops every non-embedded image and active content', function () {
    $h = HtmlSanitizer::cleanOutgoing('<p>a</p><img src="https://t.evil/p.gif"><img src="/mail/aaaaaaaaaaaaaaaaaaaaaaaa/5/attachment/ATT00001?inline=1"><img src="data:image/png;base64,AAAA"><script>x()</script>');
    has($h, 'data:image/png;base64,AAAA'); lacks($h, 't.evil'); lacks($h, '/mail/'); lacks($h, '<script'); lacks($h, 'data-blocked'); has($h, '<p>a</p>');
});
test('unit: Prefill reply adds Re: once, excludes own address, keeps reply-all recipients', function () {
    $m = MessageDetail::fromArray(['id' => 5, 'mailbox' => 'a1a1a1a1a1a1a1a1a1a1a1a1', 'from' => ['name' => 'Erin', 'address' => 'erin@example.org'], 'subject' => 'RE: hi',
        'to' => [['address' => 'ME@example.com'], ['address' => 'carol@example.org']], 'cc' => [['address' => 'dave@example.org'], ['address' => 'carol@example.org']], 'html' => ['<p>x</p>'], 'text' => 'x', 'attachments' => []]);
    $f = Prefill::reply($m, 'me@example.com', true);
    eq($f->subject, 'RE: hi'); eq($f->to, 'Erin <erin@example.org>, carol@example.org'); eq($f->cc, 'dave@example.org');
    eq(Prefill::reply($m, 'me@example.com', false)->to, 'Erin <erin@example.org>');
    eq(Prefill::forward($m)->subject, 'Fwd: RE: hi');
    eq(Prefill::reply($m, 'me@example.com', true)->reference['action'], 'replyAll');
});

test('compose: page renders a working form shell', function () {
    $b = loggedIn(); [$html, $h] = composePage($b);
    has($html, 'enctype="multipart/form-data"'); has($html, 'contenteditable="true"'); has($html, 'name="attachments[]"');
    has($html, 'name="do" value="send"'); has($html, 'name="do" value="draft"'); has($html, '/assets/compose.js'); has($html, 'href="/compose"');
    check(preg_match('/^[a-f0-9]{32}$/', $h['compose_id'] ?? '') === 1, 'compose id'); check(strlen($h['_token'] ?? '') === 64, 'csrf');
    check(!preg_match('/<script(?![^>]*src=)/i', $html), 'no inline script');
    eq(Browser::raw('GET', Servers::APP . '/assets/compose.js')['status'], 200);
    eq((new Browser())->get('/compose')['status'], 303);
});
test('compose: mailto-style prefill is validated and escaped', function () {
    $b = loggedIn();
    $r = $b->get('/compose?to=' . rawurlencode('x@example.org, "><script>alert(1)</script>') . '&subject=' . rawurlencode("Hi\r\nBcc: z@z.z"));
    has($r['body'], 'value="x@example.org"'); lacks($r['body'], '<script>alert(1)'); lacks($r['body'], "Hi\r\n");
});
test('compose: send delivers sanitised HTML + text via WildDuck and lands in Sent', function () {
    $b = loggedIn();
    $r = sendForm($b, ['to' => 'Bob <bob@example.org>, carol@example.org', 'cc' => 'dave@example.org', 'subject' => "Plan\r\nBcc: evil@x.y",
        'html' => '<p onclick="x()">Hi <b>there</b></p><script>alert(1)</script><img src="https://t.evil/p.gif">']);
    eq($r['status'], 303); eq($r['headers']['location'], '/mail/sent');
    $s = lastSubmit();
    eq($s['from']['address'], 'alice@example.com');
    eq($s['to'], [['name' => 'Bob', 'address' => 'bob@example.org'], ['address' => 'carol@example.org']]);
    eq($s['cc'], [['address' => 'dave@example.org']]); eq($s['bcc'], []);
    eq($s['subject'], 'Plan Bcc: evil@x.y', 'CR/LF collapsed so no header injection');
    foreach (['<script', 'onclick', 't.evil', '<img'] as $bad) { lacks(strtolower($s['html']), $bad); }
    has($s['html'], 'Hi <b>there</b>'); eq($s['text'], 'Hi there');
    check(!isset($s['mailbox']) && !isset($s['reference']) && !isset($s['draft']) && !isset($s['isDraft']), 'plain send has no mailbox/reference/draft');
    $sent = $b->get('/mail/sent'); has($sent['body'], 'Plan Bcc: evil@x.y'); has($sent['body'], 'Message sent.');
});
test('compose: validation keeps what you typed and sends nothing', function () {
    $b = loggedIn();
    $r = sendForm($b, ['to' => '', 'subject' => 'Keep me', 'html' => '<p>Body text</p>']);
    eq($r['status'], 422); has($r['body'], 'Add at least one recipient.'); has($r['body'], 'value="Keep me"'); has($r['body'], 'Body text');
    $r = sendForm($b, ['to' => 'ok@example.org, not-an-email', 'subject' => 's']);
    eq($r['status'], 422); has($r['body'], 'invalid address'); has($r['body'], 'not-an-email');
    $many = implode(', ', array_map(fn ($i) => "u$i@example.org", range(1, 101)));
    $r = sendForm($b, ['to' => $many]); eq($r['status'], 422); has($r['body'], 'at most 100 recipients');
    $r = sendForm($b, ['to' => '"><script>alert(1)</script>@x.y']); eq($r['status'], 422); lacks($r['body'], '<script>alert(1)');
    eq(submitCount(), 0, 'nothing may have been submitted');
});
test('compose: no-JS fallback textarea is turned into safe HTML', function () {
    $b = loggedIn();
    sendForm($b, ['to' => 'a@example.org', 'html' => '', 'text_fallback' => "line <b>1</b>\nline 2"]);
    $s = lastSubmit(); has($s['html'], 'line &lt;b&gt;1&lt;/b&gt;<br>'); check(preg_match('#<br>\s*line 2#', $s['html']) === 1, 'newline became <br>'); lacks($s['html'], '<b>1</b>'); has($s['text'], 'line <b>1</b>');
});
test('compose: drafts - save, reopen, re-save replaces, send removes the draft', function () {
    $b = loggedIn();
    $r = sendForm($b, ['do' => 'draft', 'to' => '', 'subject' => 'Work in progress', 'html' => '<p>half done</p>']);
    eq($r['status'], 303); eq($r['headers']['location'], '/mail/drafts');
    check(lastSubmit()['isDraft'] === true, 'isDraft flag'); check(!isset(lastSubmit()['draft']));
    $list = $b->get('/mail/drafts'); has($list['body'], 'Work in progress'); has($list['body'], 'Draft saved.'); has($list['body'], '/edit"');
    $drafts = box('Drafts');
    [$html, $h] = composePage($b, "/mail/$drafts/1/edit");
    has($html, 'Edit draft'); has($html, 'value="Work in progress"'); has($html, 'half done');
    eq($h['draft_mailbox'], $drafts); eq($h['draft_id'], '1');
    sendForm($b, ['do' => 'draft', 'subject' => 'Work in progress v2'], "/mail/$drafts/1/edit", $h);
    eq(lastSubmit()['draft'], ['mailbox' => $drafts, 'id' => 1]);
    $l = $b->get('/mail/drafts'); has($l['body'], 'v2'); eq(substr_count($l['body'], 'class="msg-row'), 1, 'old draft was replaced, not duplicated');
    [, $h2] = composePage($b, "/mail/$drafts/2/edit");
    $r = sendForm($b, ['do' => 'send', 'to' => 'bob@example.org'], "/mail/$drafts/2/edit", $h2); eq($r['status'], 303); eq($r['headers']['location'], '/mail/sent');
    has($b->get('/mail/drafts')['body'], 'Nothing here yet'); has($b->get('/mail/sent')['body'], 'Work in progress v2');
});
test('compose: opening a non-draft via /edit just shows it', function () {
    $b = loggedIn(); $r = $b->get('/mail/inbox/5/edit'); eq($r['status'], 303); eq($r['headers']['location'], '/mail/inbox/5');
});
test('compose: a forged draft reference cannot delete other messages', function () {
    $b = loggedIn(); $inbox = box('INBOX'); $bob = box('INBOX', 'b2b2b2b2b2b2b2b2b2b2b2b2');
    $n = submitCount();
    sendForm($b, ['to' => 'x@example.org', 'draft_mailbox' => $inbox, 'draft_id' => '5']);
    eq(submitCount(), $n + 1, 'send must go through');
    check(!isset(lastSubmit()['draft']), 'inbox message must not be passed as draft');
    has($b->get('/mail/inbox/5')['body'], 'Weekly report #5');
    sendForm($b, ['to' => 'y@example.org', 'draft_mailbox' => $bob, 'draft_id' => '1', 'ref_mailbox' => $bob, 'ref_id' => '1', 'ref_action' => 'reply']);
    eq(submitCount(), $n + 2, 'send must go through');
    check(!isset(lastSubmit()['draft']) && !isset(lastSubmit()['reference']), 'other users mailbox references are ignored');
    sendForm($b, ['to' => 'z@example.org', 'ref_mailbox' => $inbox, 'ref_id' => '5', 'ref_action' => 'drop-table']);
    eq(submitCount(), $n + 3, 'send must go through');
    check(!isset(lastSubmit()['reference']), 'unknown reference action ignored');
});
test('compose: reply prefills recipient, subject and quote; threading reference is sent', function () {
    $b = loggedIn(); $inbox = box('INBOX');
    [$html, $h] = composePage($b, '/mail/inbox/5/reply');
    has($html, 'value="Reports Bot &lt;bot@example.org&gt;"'); has($html, 'value="Re: Weekly report #5"'); has($html, 'wrote:'); has($html, '<blockquote>');
    eq($h['ref_action'], 'reply');
    sendForm($b, [], '/mail/inbox/5/reply', $h);
    eq(lastSubmit()['reference'], ['mailbox' => $inbox, 'id' => 5, 'action' => 'reply']);
    eq(lastSubmit()['to'], [['name' => 'Reports Bot', 'address' => 'bot@example.org']]);
});
test('compose: reply-all excludes you, dedupes, and strips active content from the quote', function () {
    $b = loggedIn(); $proj = box('Projects');
    [$html, $h] = composePage($b, "/mail/$proj/1/reply-all");
    preg_match('#id="to" name="to" type="text" value="([^"]*)"#', $html, $to); preg_match('#id="cc" name="cc" type="text" value="([^"]*)"#', $html, $cc);
    eq(html_entity_decode($to[1]), 'Erin <erin@example.org>, Carol <carol@example.org>'); eq(html_entity_decode($cc[1]), 'dave@example.org');
    lacks($html, '<script>alert(1)'); has($html, 'Where shall we eat?'); has($html, 'value="Re: Team lunch"');
    eq($h['ref_action'], 'replyAll');
    preg_match('#id="to" name="to" type="text" value="([^"]*)"#', $b->get("/mail/$proj/1/reply")['body'], $one); eq(html_entity_decode($one[1]), 'Erin <erin@example.org>');
});
test('compose: forward offers original attachments and carries only the selected ones', function () {
    $b = loggedIn(); $inbox = box('INBOX');
    [$html, $h] = composePage($b, '/mail/inbox/37/forward');
    has($html, 'value="Fwd: Invoice attached"'); has($html, 'Forwarded message'); has($html, 'invoice.pdf'); has($html, 'evil.html'); lacks($html, 'logo.png');
    eq(substr_count($html, 'name="keep[]"'), 2);
    $r = sendForm($b, ['to' => 'boss@example.org', 'keep' => ['ATT00002', 'ATT00009']], '/mail/inbox/37/forward', $h); eq($r['status'], 303);
    $s = lastSubmit();
    eq(count($s['attachments']), 1); eq($s['attachments'][0]['filename'], 'invoice.pdf'); eq($s['attachments'][0]['contentType'], 'application/pdf');
    eq(base64_decode($s['attachments'][0]['content']), '%PDF-1.4 fake'); eq($s['reference'], ['mailbox' => $inbox, 'id' => 37, 'action' => 'forward']);
    check(!str_contains($s['html'], '/mail/' . $inbox), 'no internal proxy paths in outgoing html');
});
test('compose: uploads are validated (type, size, name) and sent as base64', function () {
    $b = loggedIn(); [, $h] = composePage($b);
    $tmp = tempnam(sys_get_temp_dir(), 'up'); file_put_contents($tmp, "hello notes\n");
    $r = sendForm($b, ['to' => 'a@example.org', 'attachments' => [new CURLFile($tmp, 'application/x-evil', '../../notes.txt')]], '/compose', $h);
    eq($r['status'], 303); $a = lastSubmit()['attachments'][0];
    eq($a['filename'], 'notes.txt', 'path stripped'); eq($a['contentType'], 'text/plain', 'type sniffed, not trusted from client'); eq(base64_decode($a['content']), "hello notes\n"); eq($a['contentDisposition'], 'attachment');
    $before = submitCount();
    $r = sendForm($b, ['to' => 'a@example.org', 'subject' => 'keepme', 'attachments' => [new CURLFile($tmp, 'text/plain', 'setup.exe')]], '/compose', composePage($b)[1]);
    eq($r['status'], 422); has($r['body'], 'cannot be attached'); has($r['body'], '“setup.exe”', 'the file name must appear in the message'); has($r['body'], 'value="keepme"'); eq(submitCount(), $before);
    $big = tempnam(sys_get_temp_dir(), 'big'); file_put_contents($big, str_repeat('A', 3 * 1048576));
    $r = sendForm($b, ['to' => 'a@example.org', 'attachments' => [new CURLFile($big, 'text/plain', 'big.txt')]], '/compose', composePage($b)[1]);
    eq($r['status'], 422); has($r['body'], '“big.txt” is larger than this server allows'); eq(submitCount(), $before);
    $huge = tempnam(sys_get_temp_dir(), 'huge'); file_put_contents($huge, str_repeat('B', 9 * 1048576));
    $r = sendForm($b, ['to' => 'a@example.org', 'attachments' => [new CURLFile($huge, 'text/plain', 'huge.txt')]], '/compose', composePage($b)[1]);
    eq($r['status'], 413); has($r['body'], 'Upload too large'); eq(submitCount(), $before);
    @unlink($tmp); @unlink($big); @unlink($huge);
});
test('compose: double submit sends once', function () {
    $b = loggedIn(); [, $h] = composePage($b);
    sendForm($b, ['to' => 'a@example.org', 'subject' => 'once'], '/compose', $h);
    $r = sendForm($b, ['to' => 'a@example.org', 'subject' => 'once'], '/compose', $h);
    eq($r['status'], 303); eq(submitCount(), 1);
    has($b->get('/mail/sent')['body'], 'already sent');
});
test('compose: WildDuck rejections are shown without losing the message; internals stay hidden', function () {
    $b = loggedIn();
    $r = sendForm($b, ['to' => 'reject@example.com', 'subject' => 'S1', 'html' => '<p>draft text</p>']);
    eq($r['status'], 422); has($r['body'], 'Recipient address rejected'); has($r['body'], 'value="S1"'); has($r['body'], 'draft text');
    $r = sendForm($b, ['to' => 'limit@example.com', 'subject' => 'S2']); eq($r['status'], 422); has($r['body'], 'Too many requests');
    $r = sendForm($b, ['to' => 'boom@example.com', 'subject' => 'S3']);
    eq($r['status'], 503); has($r['body'], 'not sent'); has($r['body'], 'value="S3"'); lacks($r['body'], 'secret details'); lacks($r['body'], 'InternalError');
    has($b->get('/mail/sent')['body'], 'Nothing here yet');
});
test('compose: CSRF and authentication are enforced', function () {
    $b = loggedIn();
    eq($b->post('/compose', ['to' => 'a@example.org', 'do' => 'send'], false)['status'], 419);
    eq(submitCount(), 0);
    $bobInbox = box('INBOX', 'b2b2b2b2b2b2b2b2b2b2b2b2');
    foreach (['reply', 'reply-all', 'forward', 'edit'] as $p) { eq($b->get("/mail/$bobInbox/1/$p")['status'], 404, $p); }
    eq((new Browser())->post('/compose', ['to' => 'a@example.org'], false)['status'], 419);
    eq(Browser::raw('GET', Servers::APP . '/mail/inbox/5/reply')['status'], 303);
});

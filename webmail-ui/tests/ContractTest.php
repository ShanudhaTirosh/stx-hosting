<?php
declare(strict_types=1);

use Webmail\WildDuck\Client;

/**
 * Guards the fake server against drifting from the real API: every key it returns must exist in the
 * pinned WildDuck 1.55.2 OpenAPI response schema, and every key the app *reads* must exist there too.
 */
function wd_schema(string $path, string $method): array
{
    static $spec = null;
    $spec ??= json_decode((string) file_get_contents(__DIR__ . '/fixtures/wildduck-openapi-1.55.2.json'), true);
    $resolve = function (array $s) use (&$resolve, $spec): array {
        while (isset($s['$ref']) || isset($s['anyOf'])) {
            if (isset($s['$ref'])) { $s = $spec['components']['schemas'][basename($s['$ref'])]; continue; }
            // anyOf: follow the object branch (e.g. "message" is an object, or false when deduplicated)
            $obj = array_values(array_filter($s['anyOf'], fn ($o) => ($o['type'] ?? '') === 'object' || isset($o['$ref'])));
            $s = $obj[0] ?? $s['anyOf'][0];
        }
        return $s;
    };
    $node = $resolve($spec['paths'][$path][$method]['responses']['200']['content']['application/json']['schema']);
    return [$node, $resolve];
}

/** @return list<string> keys in $data that the schema does not define (recursive) */
function schema_unknown_keys(array $data, array $schema, callable $resolve, string $at = ''): array
{
    $schema = $resolve($schema);
    $bad = [];
    if (($schema['type'] ?? '') === 'object' && isset($schema['properties'])) {
        foreach ($data as $k => $v) {
            if (!isset($schema['properties'][$k])) { $bad[] = "$at.$k"; continue; }
            if (is_array($v)) { $bad = array_merge($bad, schema_unknown_keys($v, $schema['properties'][$k], $resolve, "$at.$k")); }
        }
    } elseif (($schema['type'] ?? '') === 'array' && isset($schema['items'])) {
        foreach ($data as $i => $item) { if (is_array($item)) { $bad = array_merge($bad, schema_unknown_keys($item, $schema['items'], $resolve, $at . "[]")); } }
    }
    return $bad;
}

test('contract: fake responses only contain keys defined by the real WildDuck 1.55.2 spec', function () {
    Servers::resetWd();
    $u = 'a1a1a1a1a1a1a1a1a1a1a1a1';
    $inbox = substr(hash('sha1', $u . 'INBOX'), 0, 24);
    $c = new Client(Servers::WD, 'user-token-alice', 3);
    $cases = [
        ['/users/{user}/mailboxes', 'get', $c->json('GET', "/users/$u/mailboxes", ['counters' => true])],
        ['/users/{user}/mailboxes/{mailbox}/messages', 'get', $c->json('GET', "/users/$u/mailboxes/$inbox/messages", ['limit' => 3])],
        ['/users/{user}/mailboxes/{mailbox}/messages/{message}', 'get', $c->json('GET', "/users/$u/mailboxes/$inbox/messages/37", ['replaceCidLinks' => true])],
        ['/users/{user}/search', 'get', $c->json('GET', "/users/$u/search", ['q' => 'report', 'limit' => 2])],
        ['/users/{user}/mailboxes/{mailbox}/messages/{message}', 'put', $c->json('PUT', "/users/$u/mailboxes/$inbox/messages/1", [], ['seen' => true])],
    ];
    foreach ($cases as [$path, $m, $data]) {
        [$schema, $resolve] = wd_schema($path, $m);
        $bad = schema_unknown_keys($data, $schema, $resolve);
        eq($bad, [], "fake $m $path drifted from spec");
    }
    $lab = $c->json('POST', "/users/$u/labels", [], ['name' => 'ContractLabel', 'metaData' => ['color' => 'red']]);
    $box = $c->json('POST', "/users/$u/mailboxes", [], ['path' => 'ContractBox']);
    $cases2 = [
        ['/users/{user}', 'get', $c->json('GET', "/users/$u")],
        ['/users/{user}', 'put', $c->json('PUT', "/users/$u", [], ['metaData' => ['webmail' => ['v' => 1]]])],
        ['/users/{user}/addressregister', 'get', $c->json('GET', "/users/$u/addressregister", ['query' => 'z', 'limit' => 5])],
        ['/users/{user}/filters', 'get', $c->json('GET', "/users/$u/filters")],
        ['/users/{user}/filters', 'post', $fl = $c->json('POST', "/users/$u/filters", [], ['name' => 'C', 'query' => ['from' => 'x'], 'action' => ['seen' => true], 'disabled' => false])],
        ['/users/{user}/filters/{filter}', 'put', $c->json('PUT', "/users/$u/filters/{$fl['id']}", [], ['disabled' => true])],
        ['/users/{user}/filters/{filter}', 'delete', $c->json('DELETE', "/users/$u/filters/{$fl['id']}")],
        ['/users/{user}/addresses', 'get', $c->json('GET', "/users/$u/addresses")],
        ['/users/{user}/addresses/{id}', 'put', $c->json('PUT', "/users/$u/addresses/ad0000000000000000000002", [], ['name' => 'Sales'])],
        ['/users/{user}/autoreply', 'put', $c->json('PUT', "/users/$u/autoreply", [], ['status' => true, 'name' => 'A', 'subject' => 'S', 'text' => 't', 'html' => '<p>t</p>', 'start' => '2026-12-24T00:00:00.000Z', 'end' => false])],
        ['/users/{user}/autoreply', 'get', $c->json('GET', "/users/$u/autoreply")],
        ['/users/{user}/labels', 'get', $c->json('GET', "/users/$u/labels", ['counters' => true])],
        ['/users/{user}/labels', 'post', $lab],
        ['/users/{user}/labels/{label}', 'put', $c->json('PUT', "/users/$u/labels/{$lab['id']}", [], ['name' => 'ContractLabel2', 'metaData' => ['color' => 'blue']])],
        ['/users/{user}/mailboxes', 'post', $box],
        ['/users/{user}/mailboxes/{mailbox}', 'put', $c->json('PUT', "/users/$u/mailboxes/{$box['id']}", [], ['path' => 'ContractBox2'])],
        ['/users/{user}/mailboxes/{mailbox}/messages', 'put', $c->json('PUT', "/users/$u/mailboxes/$inbox/messages", [], ['message' => '1,2', 'seen' => true, 'addLabels' => ['Work']])],
        ['/users/{user}/mailboxes/{mailbox}/messages', 'put', $c->json('PUT', "/users/$u/mailboxes/$inbox/messages", [], ['message' => '3', 'moveTo' => $box['id']])],
        ['/users/{user}/labels/{label}', 'delete', $c->json('DELETE', "/users/$u/labels/{$lab['id']}")],
        ['/users/{user}/mailboxes/{mailbox}', 'delete', $c->json('DELETE', "/users/$u/mailboxes/{$box['id']}")],
        ['/users/{user}/search', 'get', $c->json('GET', "/users/$u/search", ['thread' => 'f0f0f0f0f0f0f0f0f0f0f0f0', 'order' => 'asc', 'threadCounters' => true])],
    ];
    foreach ($cases2 as [$path, $m, $data]) {
        [$schema, $resolve] = wd_schema($path, $m);
        eq(schema_unknown_keys($data, $schema, $resolve), [], "fake $m $path drifted from spec");
    }
    $sub = $c->json('POST', "/users/$u/submit", [], ['from' => ['address' => 'alice@example.com'], 'to' => [['address' => 'x@example.org']], 'subject' => 's', 'html' => '<p>a</p>', 'text' => 'a']);
    [$schema, $resolve] = wd_schema('/users/{user}/submit', 'post');
    eq(schema_unknown_keys($sub, $schema, $resolve), [], 'fake submit drifted from spec');
    $auth = (new Client(Servers::WD, 'svc-token', 3))->json('POST', '/authenticate', [], ['username' => 'alice@example.com', 'password' => 'correct-pass', 'token' => true]);
    [$schema, $resolve] = wd_schema('/authenticate', 'post');
    eq(schema_unknown_keys($auth, $schema, $resolve), [], 'fake /authenticate drifted from spec');
    $svc = new Client(Servers::WD, 'svc-token', 3);
    $tf = $svc->json('POST', '/authenticate', [], ['username' => 'twofa@example.com', 'password' => 'twofa-pass', 'token' => true]);
    eq(schema_unknown_keys($tf, $schema, $resolve), [], 'fake 2FA /authenticate drifted from spec');
    $chk = $svc->json('POST', "/users/{$tf['id']}/2fa/totp/check", [], ['token' => '123456', 'totpNonce' => $tf['totpNonce'], 'sess' => 's', 'ip' => '127.0.0.1']);
    [$schema, $resolve] = wd_schema('/users/{user}/2fa/totp/check', 'post');
    eq(schema_unknown_keys($chk, $schema, $resolve), [], 'fake totp/check drifted from spec');
});

test('contract: every API field the app reads exists in the real spec', function () {
    $reads = [
        ['/users/{user}/mailboxes', 'get', ['results', 'results.id', 'results.name', 'results.path', 'results.specialUse', 'results.total', 'results.unseen', 'results.hidden']],
        ['/users/{user}/mailboxes/{mailbox}/messages', 'get', ['results', 'total', 'nextCursor', 'previousCursor', 'results.id', 'results.mailbox', 'results.thread', 'results.threadMessageCount', 'results.from', 'results.to', 'results.subject', 'results.intro', 'results.date', 'results.attachments', 'results.seen', 'results.flagged', 'results.draft', 'results.answered', 'results.labels']],
        ['/users/{user}/submit', 'post', ['message', 'message.id', 'message.mailbox']],
        ['/users/{user}/labels', 'get', ['labels', 'labels.id', 'labels.name', 'labels.metaData', 'labels.total', 'labels.unseen']],
        ['/users/{user}', 'get', ['metaData', 'name', 'autoreply']],
        ['/users/{user}/filters', 'get', ['limits', 'limits.allowed', 'limits.used', 'results', 'results.id', 'results.name', 'results.disabled', 'results.originalQuery', 'results.originalAction']],
        ['/users/{user}/filters', 'post', ['id']],
        ['/users/{user}/addresses', 'get', ['results', 'results.id', 'results.address', 'results.name', 'results.main']],
        ['/users/{user}/autoreply', 'get', ['status', 'name', 'subject', 'text', 'start', 'end']],
        ['/users/{user}/addressregister', 'get', ['results', 'results.name', 'results.address']],
        ['/users/{user}/labels', 'post', ['id']],
        ['/users/{user}/mailboxes', 'post', ['id']],
        ['/users/{user}/mailboxes/{mailbox}/messages', 'put', ['id', 'mailbox', 'updated']],
        ['/users/{user}/mailboxes/{mailbox}/messages/{message}', 'get', ['id', 'mailbox', 'thread', 'from', 'to', 'cc', 'bcc', 'replyTo', 'subject', 'messageId', 'date', 'html', 'text', 'attachments', 'seen', 'flagged', 'draft', 'labels', 'list']],
        ['/authenticate', 'post', ['id', 'username', 'address', 'token', 'require2fa', 'requirePasswordChange', 'twoFactorNonce', 'totpNonce']],
        ['/users/{user}/2fa/totp/check', 'post', ['token']],
    ];
    foreach ($reads as [$path, $m, $keys]) {
        [$schema, $resolve] = wd_schema($path, $m);
        foreach ($keys as $key) {
            $node = $schema;
            foreach (explode('.', $key) as $seg) {
                $node = $resolve($node);
                if (($node['type'] ?? '') === 'array') { $node = $resolve($node['items']); }
                check(isset($node['properties'][$seg]), "app reads '$key' but spec ($m $path) has no '$seg'");
                $node = $node['properties'][$seg];
            }
        }
    }
});

test('contract: request params the app sends exist in the real spec', function () {
    $spec = json_decode((string) file_get_contents(__DIR__ . '/fixtures/wildduck-openapi-1.55.2.json'), true);
    $params = fn (string $p, string $m): array => array_column($spec['paths'][$p][$m]['parameters'] ?? [], 'name');
    foreach (['counters'] as $q) { check(in_array($q, $params('/users/{user}/mailboxes', 'get'), true), "mailboxes.$q"); }
    foreach (['limit', 'order', 'next', 'previous', 'unseen', 'threadCounters'] as $q) { check(in_array($q, $params('/users/{user}/mailboxes/{mailbox}/messages', 'get'), true), "messages.$q"); }
    foreach (['replaceCidLinks', 'markAsSeen'] as $q) { check(in_array($q, $params('/users/{user}/mailboxes/{mailbox}/messages/{message}', 'get'), true), "message.$q"); }
    foreach (['q', 'limit', 'order', 'next', 'previous', 'mailbox', 'unseen', 'flagged', 'threadCounters', 'thread', 'label'] as $q) { check(in_array($q, $params('/users/{user}/search', 'get'), true), "search.$q"); }
    $put = $spec['paths']['/users/{user}/mailboxes/{mailbox}/messages/{message}']['put']['requestBody']['content']['application/json']['schema'];
    $put = $put['$ref'] ?? null ? $spec['components']['schemas'][basename($put['$ref'])] : $put;
    foreach (['seen', 'flagged', 'moveTo'] as $k) { check(isset($put['properties'][$k]), "PUT message.$k"); }
    $res = fn (array $s) => isset($s['$ref']) ? $spec['components']['schemas'][basename($s['$ref'])] : $s;
    $sub = $res($spec['paths']['/users/{user}/submit']['post']['requestBody']['content']['application/json']['schema']);
    foreach (['from', 'to', 'cc', 'bcc', 'subject', 'html', 'text', 'attachments', 'isDraft', 'reference', 'draft'] as $k) { check(isset($sub['properties'][$k]), "submit.$k"); }
    $att = $res($res($sub['properties']['attachments'])['items']);
    foreach (['filename', 'contentType', 'encoding', 'content', 'contentDisposition'] as $k) { check(isset($att['properties'][$k]), "submit.attachments.$k"); }
    foreach (['mailbox', 'id', 'action'] as $k) { check(isset($res($sub['properties']['reference'])['properties'][$k]), "submit.reference.$k"); }
    foreach (['mailbox', 'id'] as $k) { check(isset($res($sub['properties']['draft'])['properties'][$k]), "submit.draft.$k"); }
    foreach (['counters'] as $q) { check(in_array($q, $params('/users/{user}/labels', 'get'), true), "labels.$q"); }
    foreach (['query', 'limit'] as $q) { check(in_array($q, $params('/users/{user}/addressregister', 'get'), true), "addressregister.$q"); }
    $req = fn (string $p, string $m): array => $res($spec['paths'][$p][$m]['requestBody']['content']['application/json']['schema'])['properties'];
    foreach (['message', 'moveTo', 'seen', 'flagged', 'addLabels', 'removeLabels'] as $k) { check(isset($req('/users/{user}/mailboxes/{mailbox}/messages', 'put')[$k]), "bulk.$k"); }
    foreach (['name', 'metaData'] as $k) { check(isset($req('/users/{user}/labels', 'post')[$k]), "label.post.$k"); check(isset($req('/users/{user}/labels/{label}', 'put')[$k]), "label.put.$k"); }
    foreach (['metaData', 'name', 'password', 'existingPassword'] as $k) { check(isset($req('/users/{user}', 'put')[$k]), "user.put.$k"); }
    check(isset($req('/users/{user}/addresses/{id}', 'put')['name']), 'address.put.name');
    foreach (['name', 'query', 'action', 'disabled'] as $k) { check(isset($req('/users/{user}/filters', 'post')[$k]), "filter.post.$k"); check(isset($req('/users/{user}/filters/{filter}', 'put')[$k]), "filter.put.$k"); }
    $fq = $res($req('/users/{user}/filters', 'post')['query'])['properties']; foreach (['from', 'to', 'subject', 'listId', 'text', 'ha', 'size'] as $k) { check(isset($fq[$k]), "filter.query.$k"); }
    $fa = $res($req('/users/{user}/filters', 'post')['action'])['properties']; foreach (['seen', 'flag', 'spam', 'mailbox', 'labels'] as $k) { check(isset($fa[$k]), "filter.action.$k"); }
    foreach (['status', 'name', 'subject', 'text', 'html', 'start', 'end'] as $k) { check(isset($req('/users/{user}/autoreply', 'put')[$k]), "autoreply.put.$k"); }
    check(isset($req('/users/{user}/mailboxes', 'post')['path']), 'mailbox.post.path'); check(isset($req('/users/{user}/mailboxes/{mailbox}', 'put')['path']), 'mailbox.put.path');
    foreach ([['/users/{user}/labels/{label}', 'put'], ['/users/{user}/labels/{label}', 'delete'], ['/users/{user}/mailboxes/{mailbox}', 'delete']] as [$pp, $mm]) { check(isset($spec['paths'][$pp][$mm]), "$mm $pp exists"); }
    foreach (['token', 'totpNonce', 'sess', 'ip'] as $k) { check(isset($req('/users/{user}/2fa/totp/check', 'post')[$k]), "totp.check.$k"); }
    $auth = $spec['paths']['/authenticate']['post']['requestBody']['content']['application/json']['schema'];
    $auth = $auth['$ref'] ?? null ? $spec['components']['schemas'][basename($auth['$ref'])] : $auth;
    foreach (['username', 'password', 'scope', 'protocol', 'token', 'sess', 'ip'] as $k) { check(isset($auth['properties'][$k]), "authenticate.$k"); }
});

<?php
declare(strict_types=1);

namespace Webmail\WildDuck;

use Webmail\WildDuck\Model\MessageDetail;
use Webmail\WildDuck\Model\MessageSummary;
use Webmail\WildDuck\Model\Page;

final class MessageService
{
    public const PAGE_SIZE = 30;

    public function __construct(private readonly Client $api, private readonly string $userId, private readonly int $maxDownloadBytes = 26214400)
    {
        Ids::require($userId, 'object');
    }

    public function list(string $mailboxId, ?string $cursor = null, bool $previous = false, bool $unseenOnly = false, int $limit = self::PAGE_SIZE): Page
    {
        Ids::require($mailboxId, 'object');
        $q = ['limit' => $limit, 'order' => 'desc', 'threadCounters' => true];
        if ($unseenOnly) {
            $q['unseen'] = true;
        }
        if ($cursor !== null && $cursor !== '') {
            $q[$previous ? 'previous' : 'next'] = $cursor;
        }
        return $this->page($this->api->json('GET', "/users/{$this->userId}/mailboxes/{$mailboxId}/messages", $q));
    }

    /**
     * Full-text / filtered search across the account.
     *
     * @param array<string,mixed> $filters keys: mailbox, from, to, subject, unseen, flagged, attachments, label, datestart, dateend
     */
    public function search(string $query, array $filters = [], ?string $cursor = null, bool $previous = false, int $limit = self::PAGE_SIZE): Page
    {
        $q = ['limit' => $limit, 'order' => 'desc', 'threadCounters' => true];
        if (trim($query) !== '') {
            $q['q'] = trim($query);
        }
        foreach (['mailbox', 'from', 'to', 'subject', 'unseen', 'flagged', 'attachments', 'label', 'thread', 'datestart', 'dateend'] as $k) {
            if (isset($filters[$k]) && $filters[$k] !== '' && $filters[$k] !== false) {
                $q[$k] = $filters[$k];
            }
        }
        foreach (['mailbox', 'label', 'thread'] as $k) {
            if (isset($q[$k]) && $k !== 'label') {
                Ids::require((string) $q[$k], 'object');
            }
        }
        if ($cursor !== null && $cursor !== '') {
            $q[$previous ? 'previous' : 'next'] = $cursor;
        }
        return $this->page($this->api->json('GET', "/users/{$this->userId}/search", $q));
    }

    public function get(string $mailboxId, string $uid, bool $markSeen = true): MessageDetail
    {
        Ids::require($mailboxId, 'object');
        Ids::require($uid, 'uid');
        $res = $this->api->json('GET', "/users/{$this->userId}/mailboxes/{$mailboxId}/messages/{$uid}", [
            'replaceCidLinks' => true,
            'markAsSeen' => $markSeen,
        ]);
        try {
            return MessageDetail::fromArray($res);
        } catch (\UnexpectedValueException) {
            throw new ApiException('Unexpected response from mail server.', 502, 'BadResponse');
        }
    }

    /** @param array{seen?:bool,flagged?:bool,deleted?:bool,moveTo?:string} $changes */
    public function update(string $mailboxId, string $uid, array $changes): void
    {
        Ids::require($mailboxId, 'object');
        Ids::require($uid, 'uid');
        $allowed = array_intersect_key($changes, array_flip(['seen', 'flagged', 'moveTo']));
        if (isset($allowed['moveTo'])) {
            Ids::require((string) $allowed['moveTo'], 'object');
        }
        if (!$allowed) {
            return;
        }
        $this->api->json('PUT', "/users/{$this->userId}/mailboxes/{$mailboxId}/messages/{$uid}", [], $allowed);
    }

    /**
     * Update many messages of ONE mailbox in a single API call.
     *
     * @param list<int> $uids
     * @param array{seen?:bool,flagged?:bool,moveTo?:string,addLabels?:list<string>,removeLabels?:list<string>} $changes
     * @return int number of messages WildDuck reports as changed (moved ones are listed in `id`, others counted in `updated`)
     */
    public function bulk(string $mailboxId, array $uids, array $changes): int
    {
        Ids::require($mailboxId, 'object');
        $uids = array_values(array_unique(array_filter($uids, static fn ($u) => is_int($u) && $u > 0)));
        $allowed = array_intersect_key($changes, array_flip(['seen', 'flagged', 'moveTo', 'addLabels', 'removeLabels']));
        if (!$uids || !$allowed) {
            return 0;
        }
        if (isset($allowed['moveTo'])) {
            Ids::require((string) $allowed['moveTo'], 'object');
        }
        $res = $this->api->json('PUT', "/users/{$this->userId}/mailboxes/{$mailboxId}/messages", [], ['message' => implode(',', $uids)] + $allowed);
        if (is_array($res['id'] ?? null)) {
            return count($res['id']);
        }
        return (int) ($res['updated'] ?? 0);
    }

    /** All messages of a conversation (across mailboxes), oldest first. */
    public function thread(string $threadId, int $limit = 100): Page
    {
        Ids::require($threadId, 'object');
        $res = $this->api->json('GET', "/users/{$this->userId}/search", ['thread' => $threadId, 'limit' => $limit, 'order' => 'asc']);
        return $this->page($res);
    }

    /** Permanently deletes. Callers decide between move-to-Trash and this. */
    public function destroy(string $mailboxId, string $uid): void
    {
        Ids::require($mailboxId, 'object');
        Ids::require($uid, 'uid');
        $this->api->json('DELETE', "/users/{$this->userId}/mailboxes/{$mailboxId}/messages/{$uid}");
    }

    /** @return array{contentType:string,disposition:string,body:string} */
    public function attachment(string $mailboxId, string $uid, string $attachmentId): array
    {
        Ids::require($mailboxId, 'object');
        Ids::require($uid, 'uid');
        Ids::require($attachmentId, 'attachment');
        $r = $this->api->download("/users/{$this->userId}/mailboxes/{$mailboxId}/messages/{$uid}/attachments/{$attachmentId}", [], $this->maxDownloadBytes);
        return ['contentType' => $r['contentType'], 'disposition' => $r['disposition'], 'body' => $r['body']];
    }

    /** @param array<string,mixed> $res */
    private function page(array $res): Page
    {
        $items = [];
        foreach (is_array($res['results'] ?? null) ? $res['results'] : [] as $row) {
            try {
                $items[] = MessageSummary::fromArray((array) $row);
            } catch (\UnexpectedValueException) {
                continue; // skip a malformed row rather than failing the whole list
            }
        }
        $next = $res['nextCursor'] ?? null;
        $prev = $res['previousCursor'] ?? null;
        return new Page($items, (int) ($res['total'] ?? count($items)), is_string($next) && $next !== '' ? $next : null, is_string($prev) && $prev !== '' ? $prev : null);
    }
}

<?php
declare(strict_types=1);

namespace Webmail\WildDuck;

use Webmail\WildDuck\Model\Mailbox;

final class MailboxService
{
    private const ORDER = ['\\Inbox' => 0, '\\Drafts' => 1, '\\Sent' => 2, '\\Archive' => 3, '\\Junk' => 4, '\\Trash' => 5];

    public function __construct(private readonly Client $api, private readonly string $userId)
    {
        Ids::require($userId, 'object');
    }

    /** @return list<Mailbox> system folders first (fixed order), then custom folders A-Z */
    public function all(): array
    {
        $res = $this->api->json('GET', "/users/{$this->userId}/mailboxes", ['counters' => true]);
        $list = [];
        foreach (is_array($res['results'] ?? null) ? $res['results'] : [] as $row) {
            if (is_array($row) && !($row['hidden'] ?? false)) {
                $list[] = Mailbox::fromArray($row);
            }
        }
        usort($list, static function (Mailbox $a, Mailbox $b): int {
            $oa = self::ORDER[$a->specialUse ?? ''] ?? 100;
            $ob = self::ORDER[$b->specialUse ?? ''] ?? 100;
            return $oa <=> $ob ?: strcasecmp($a->path, $b->path);
        });
        return $list;
    }

    /** Normalised folder path, or null when invalid. Nested folders use "/" as separator. */
    public static function validPath(string $path): ?string
    {
        $p = trim($path);
        if ($p === '' || mb_strlen($p) > 200 || preg_match('/[\x00-\x1f\x7f\\\\*%]/u', $p) === 1) {
            return null;
        }
        $parts = array_map('trim', explode('/', $p));
        foreach ($parts as $seg) {
            if ($seg === '' || $seg === '.' || $seg === '..') {
                return null;
            }
        }
        return implode('/', $parts);
    }

    public function create(string $path): string
    {
        $res = $this->api->json('POST', "/users/{$this->userId}/mailboxes", [], ['path' => $path]);
        return is_string($res['id'] ?? null) ? $res['id'] : '';
    }

    public function rename(string $mailboxId, string $path): void
    {
        Ids::require($mailboxId, 'object');
        $this->api->json('PUT', "/users/{$this->userId}/mailboxes/{$mailboxId}", [], ['path' => $path]);
    }

    public function delete(string $mailboxId): void
    {
        Ids::require($mailboxId, 'object');
        $this->api->json('DELETE', "/users/{$this->userId}/mailboxes/{$mailboxId}");
    }
}

<?php
declare(strict_types=1);

namespace Webmail\WildDuck;

use Webmail\WildDuck\Model\Identity;

/** Account settings: display name, password, send-from addresses and auto-reply. */
final class AccountService
{
    public function __construct(private readonly Client $api, private readonly string $userId)
    {
        Ids::require($userId, 'object');
    }

    /** @return list<Identity> main address first, then aliases A-Z */
    public function identities(): array
    {
        $res = $this->api->json('GET', "/users/{$this->userId}/addresses");
        $out = [];
        foreach (is_array($res['results'] ?? null) ? $res['results'] : [] as $row) {
            try {
                $out[] = Identity::fromArray((array) $row);
            } catch (\UnexpectedValueException) {
                continue;
            }
        }
        usort($out, static fn (Identity $a, Identity $b): int => [$b->main, strtolower($a->address)] <=> [$a->main, strtolower($b->address)]);
        return $out;
    }

    public function renameIdentity(string $addressId, string $name): void
    {
        Ids::require($addressId, 'object');
        $this->api->json('PUT', "/users/{$this->userId}/addresses/{$addressId}", [], ['name' => $name]);
    }

    public function setName(string $name): void
    {
        $this->api->json('PUT', "/users/{$this->userId}", [], ['name' => $name]);
    }

    /** The current password is verified by WildDuck (existingPassword); a wrong one yields an ApiException. */
    public function changePassword(string $existing, string $new): void
    {
        $this->api->json('PUT', "/users/{$this->userId}", [], ['existingPassword' => $existing, 'password' => $new]);
    }

    /** @return array{status:bool,name:string,subject:string,text:string,start:?string,end:?string} */
    public function autoreply(): array
    {
        $r = $this->api->json('GET', "/users/{$this->userId}/autoreply");
        $date = static fn (mixed $v): ?string => is_string($v) && $v !== '' ? $v : null;
        return [
            'status' => (bool) ($r['status'] ?? false),
            'name' => is_string($r['name'] ?? null) ? $r['name'] : '',
            'subject' => is_string($r['subject'] ?? null) ? $r['subject'] : '',
            'text' => is_string($r['text'] ?? null) ? $r['text'] : '',
            'start' => $date($r['start'] ?? null),
            'end' => $date($r['end'] ?? null),
        ];
    }

    /** @param array{status:bool,name:string,subject:string,text:string,start:?string,end:?string} $a */
    public function saveAutoreply(array $a): void
    {
        $this->api->json('PUT', "/users/{$this->userId}/autoreply", [], [
            'status' => $a['status'],
            'name' => $a['name'],
            'subject' => $a['subject'],
            'text' => $a['text'],
            'html' => $a['text'] === '' ? '' : '<p>' . nl2br(htmlspecialchars($a['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . '</p>',
            'start' => $a['start'] ?? false, // false = no start check
            'end' => $a['end'] ?? false,
        ]);
    }
}

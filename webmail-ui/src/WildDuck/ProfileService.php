<?php
declare(strict_types=1);

namespace Webmail\WildDuck;

/** Account-level reads/writes that are not about messages: user metaData and the address register. */
final class ProfileService
{
    public function __construct(private readonly Client $api, private readonly string $userId)
    {
        Ids::require($userId, 'object');
    }

    /**
     * One call for everything the UI needs about the account. metaData may arrive as an object or a JSON string.
     *
     * @return array{name:string,meta:array<string,mixed>,autoreply:bool}
     */
    public function details(): array
    {
        $res = $this->api->json('GET', "/users/{$this->userId}");
        $m = $res['metaData'] ?? [];
        if (is_string($m)) {
            $m = json_decode($m, true);
        }
        return [
            'name' => is_string($res['name'] ?? null) ? $res['name'] : '',
            'meta' => is_array($m) ? $m : [],
            'autoreply' => (bool) ($res['autoreply'] ?? false),
        ];
    }

    /** @return array<string,mixed> the user's complete metaData object */
    public function metaData(): array
    {
        return $this->details()['meta'];
    }

    /** Replaces the whole metaData object (WildDuck does not merge), so callers must send everything they want to keep. */
    public function saveMetaData(array $meta): void
    {
        $this->api->json('PUT', "/users/{$this->userId}", [], ['metaData' => (object) $meta]);
    }

    /**
     * Addresses WildDuck has seen in sent/received mail (typing suggestions).
     *
     * @return list<array{name:string,address:string}>
     */
    public function addressRegister(string $query, int $limit = 8): array
    {
        $res = $this->api->json('GET', "/users/{$this->userId}/addressregister", ['query' => $query, 'limit' => $limit]);
        $out = [];
        foreach (is_array($res['results'] ?? null) ? $res['results'] : [] as $row) {
            if (is_array($row) && is_string($row['address'] ?? null) && filter_var($row['address'], FILTER_VALIDATE_EMAIL) !== false) {
                $out[] = ['name' => is_string($row['name'] ?? null) ? $row['name'] : '', 'address' => $row['address']];
            }
        }
        return $out;
    }
}

<?php
declare(strict_types=1);

namespace Webmail\WildDuck;

use Webmail\WildDuck\Model\Rule;

final class FilterService
{
    public function __construct(private readonly Client $api, private readonly string $userId)
    {
        Ids::require($userId, 'object');
    }

    /** @return array{rules:list<Rule>,allowed:int,used:int} */
    public function all(): array
    {
        $res = $this->api->json('GET', "/users/{$this->userId}/filters");
        $rules = [];
        foreach (is_array($res['results'] ?? null) ? $res['results'] : [] as $row) {
            try {
                $rules[] = Rule::fromArray((array) $row);
            } catch (\UnexpectedValueException) {
                continue;
            }
        }
        $limits = is_array($res['limits'] ?? null) ? $res['limits'] : [];
        return ['rules' => $rules, 'allowed' => (int) ($limits['allowed'] ?? 0), 'used' => (int) ($limits['used'] ?? count($rules))];
    }

    /** @param array{name:string,query:array<string,mixed>,action:array<string,mixed>} $spec */
    public function create(array $spec): string
    {
        $res = $this->api->json('POST', "/users/{$this->userId}/filters", [], ['name' => $spec['name'], 'query' => (object) $spec['query'], 'action' => (object) $spec['action'], 'disabled' => false]);
        return is_string($res['id'] ?? null) ? $res['id'] : '';
    }

    /** @param array{name:string,query:array<string,mixed>,action:array<string,mixed>} $spec */
    public function update(string $id, array $spec): void
    {
        Ids::require($id, 'object');
        $this->api->json('PUT', "/users/{$this->userId}/filters/{$id}", [], ['name' => $spec['name'], 'query' => (object) $spec['query'], 'action' => (object) $spec['action']]);
    }

    public function setDisabled(string $id, bool $disabled): void
    {
        Ids::require($id, 'object');
        $this->api->json('PUT', "/users/{$this->userId}/filters/{$id}", [], ['disabled' => $disabled]);
    }

    public function delete(string $id): void
    {
        Ids::require($id, 'object');
        $this->api->json('DELETE', "/users/{$this->userId}/filters/{$id}");
    }
}

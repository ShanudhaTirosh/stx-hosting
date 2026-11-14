<?php
declare(strict_types=1);

namespace Webmail\WildDuck;

use Webmail\WildDuck\Model\Label;

final class LabelService
{
    public const MAX_NAME = 64;

    public function __construct(private readonly Client $api, private readonly string $userId)
    {
        Ids::require($userId, 'object');
    }

    /** @return list<Label> sorted A-Z (case-insensitive) */
    public function all(bool $counters = true): array
    {
        $res = $this->api->json('GET', "/users/{$this->userId}/labels", $counters ? ['counters' => true] : []);
        $out = [];
        foreach (is_array($res['labels'] ?? null) ? $res['labels'] : [] as $row) {
            try {
                $out[] = Label::fromArray((array) $row);
            } catch (\UnexpectedValueException) {
                continue;
            }
        }
        usort($out, static fn (Label $a, Label $b): int => strcasecmp($a->name, $b->name));
        return $out;
    }

    public static function validName(string $name): ?string
    {
        $n = trim($name);
        if ($n === '' || mb_strlen($n) > self::MAX_NAME || preg_match('/[\x00-\x1f\x7f,]/u', $n) === 1) {
            return null;
        }
        return $n;
    }

    public function create(string $name, string $color): string
    {
        $res = $this->api->json('POST', "/users/{$this->userId}/labels", [], ['name' => $name, 'metaData' => ['color' => $this->color($color)]]);
        return is_string($res['id'] ?? null) ? $res['id'] : '';
    }

    public function update(string $id, string $name, string $color): void
    {
        Ids::require($id, 'object');
        $this->api->json('PUT', "/users/{$this->userId}/labels/{$id}", [], ['name' => $name, 'metaData' => ['color' => $this->color($color)]]);
    }

    public function delete(string $id): void
    {
        Ids::require($id, 'object');
        $this->api->json('DELETE', "/users/{$this->userId}/labels/{$id}");
    }

    private function color(string $c): string
    {
        return in_array($c, Label::COLORS, true) ? $c : 'gray';
    }
}

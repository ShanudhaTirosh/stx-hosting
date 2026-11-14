<?php
declare(strict_types=1);

namespace Webmail\WildDuck\Model;

final class Label
{
    public const COLORS = ['gray', 'red', 'orange', 'amber', 'green', 'teal', 'blue', 'purple', 'pink'];

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $color,
        public readonly int $total,
        public readonly int $unseen,
    ) {
    }

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        if (!is_string($d['id'] ?? null) || !is_string($d['name'] ?? null)) {
            throw new \UnexpectedValueException('Malformed label.');
        }
        $c = is_array($d['metaData'] ?? null) && is_string($d['metaData']['color'] ?? null) ? $d['metaData']['color'] : 'gray';
        return new self($d['id'], $d['name'], in_array($c, self::COLORS, true) ? $c : 'gray', (int) ($d['total'] ?? 0), (int) ($d['unseen'] ?? 0));
    }
}

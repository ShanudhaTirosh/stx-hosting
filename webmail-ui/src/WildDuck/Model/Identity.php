<?php
declare(strict_types=1);

namespace Webmail\WildDuck\Model;

/** A send-from address of the account (the main address or an alias), with its display name. */
final class Identity
{
    public function __construct(
        public readonly string $id,
        public readonly string $address,
        public readonly string $name,
        public readonly bool $main,
    ) {
    }

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        if (!is_string($d['id'] ?? null) || !is_string($d['address'] ?? null) || filter_var($d['address'], FILTER_VALIDATE_EMAIL) === false) {
            throw new \UnexpectedValueException('Malformed address.');
        }
        $name = trim(preg_replace('/[\x00-\x1f\x7f<>"]+/u', ' ', (string) ($d['name'] ?? '')) ?? '');
        return new self($d['id'], $d['address'], mb_substr($name, 0, 100), (bool) ($d['main'] ?? false));
    }
}

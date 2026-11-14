<?php
declare(strict_types=1);

namespace Webmail\WildDuck;

/** Strict validation of identifiers before they are put in an API path. */
final class Ids
{
    public static function objectId(string $v): bool
    {
        return preg_match('/^[a-f0-9]{24}$/', $v) === 1;
    }

    public static function uid(string $v): bool
    {
        return preg_match('/^[1-9][0-9]{0,9}$/', $v) === 1;
    }

    public static function attachment(string $v): bool
    {
        return preg_match('/^ATT[0-9]{1,6}$/', $v) === 1;
    }

    public static function require(string $v, string $kind): string
    {
        $ok = match ($kind) {
            'object' => self::objectId($v),
            'uid' => self::uid($v),
            'attachment' => self::attachment($v),
            default => false,
        };
        if (!$ok) {
            throw new ApiException('Invalid identifier.', 404, 'InvalidId');
        }
        return $v;
    }
}

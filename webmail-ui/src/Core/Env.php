<?php
declare(strict_types=1);

namespace Webmail\Core;

final class Env
{
    /** @var array<string,string> */
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $v = trim($v);
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
                $v = substr($v, 1, -1);
            }
            self::$values[trim($k)] = $v;
        }
    }

    public static function set(string $key, string $value): void
    {
        self::$values[$key] = $value;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$values[$key] ?? (getenv($key) !== false ? (string) getenv($key) : $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        // An empty value ("KEY=") means "not set": fall back to the default instead of silently turning a secure default off.
        return $v === null || trim($v) === '' ? $default : in_array(strtolower(trim($v)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default): int
    {
        $v = self::get($key);
        return $v !== null && trim($v) !== '' && is_numeric($v) ? (int) $v : $default;
    }
}

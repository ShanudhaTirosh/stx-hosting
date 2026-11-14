<?php
declare(strict_types=1);

namespace Webmail\Mail;

final class AddressParser
{
    public const MAX = 100;

    /**
     * Parse "Name <a@b.c>, d@e.f; ..." into validated, de-duplicated addresses.
     *
     * @return array{list:list<array{name:string,address:string}>,invalid:list<string>}
     */
    public static function parse(string $raw): array
    {
        $out = [];
        $invalid = [];
        $seen = [];
        foreach (self::split($raw) as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }
            $name = '';
            $addr = $token;
            if (preg_match('/^(.*?)<([^<>]*)>\s*$/s', $token, $m) === 1) {
                $name = trim(trim($m[1]), "\"' \t");
                $addr = trim($m[2]);
            }
            $name = trim(preg_replace('/[\x00-\x1f\x7f<>"]+/u', ' ', $name) ?? '');
            if (strlen($addr) > 254 || preg_match('/[\x00-\x20\x7f<>,;"]/', $addr) === 1 || filter_var($addr, FILTER_VALIDATE_EMAIL) === false) {
                $invalid[] = mb_substr($token, 0, 80);
                continue;
            }
            $key = strtolower($addr);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['name' => mb_substr($name, 0, 100), 'address' => $addr];
        }
        return ['list' => $out, 'invalid' => $invalid];
    }

    /** @return list<string> split on , or ; outside quotes and angle brackets */
    private static function split(string $s): array
    {
        $parts = [];
        $cur = '';
        $quote = false;
        $angle = false;
        foreach (mb_str_split($s) as $ch) {
            if ($ch === '"') {
                $quote = !$quote;
            } elseif (!$quote && $ch === '<') {
                $angle = true;
            } elseif (!$quote && $ch === '>') {
                $angle = false;
            }
            if (!$quote && !$angle && ($ch === ',' || $ch === ';' || $ch === "\n")) {
                $parts[] = $cur;
                $cur = '';
                continue;
            }
            $cur .= $ch;
        }
        $parts[] = $cur;
        return $parts;
    }

    /** @param list<array{name:string,address:string}> $list */
    public static function format(array $list): string
    {
        return implode(', ', array_map(static fn (array $a): string => $a['name'] !== '' ? $a['name'] . ' <' . $a['address'] . '>' : $a['address'], $list));
    }
}

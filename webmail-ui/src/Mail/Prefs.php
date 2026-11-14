<?php
declare(strict_types=1);

namespace Webmail\Mail;

use Webmail\Support\HtmlSanitizer;
use Webmail\WildDuck\ApiException;
use Webmail\WildDuck\ProfileService;

/**
 * Signatures and contacts, stored inside the WildDuck user's metaData under the key "webmail".
 * Every other key in metaData is preserved untouched. Stored data is re-validated on every read,
 * so a corrupted or hand-edited value can never break a page or inject markup.
 */
final class Prefs
{
    public const KEY = 'webmail';
    public const MAX_SIGNATURES = 10;
    public const MAX_SIGNATURE_BYTES = 20000;
    public const MAX_CONTACTS = 2000;
    public const MAX_TOTAL_BYTES = 400000;

    /** @param array<string,mixed> $meta full metaData */
    private function __construct(
        /** @var list<array{id:string,name:string,html:string}> */
        public readonly array $signatures,
        public readonly ?string $defaultSignature,
        /** @var list<array{id:string,name:string,email:string,note:string}> */
        public readonly array $contacts,
        private readonly array $meta,
    ) {
    }

    public static function load(ProfileService $profile): self
    {
        return self::fromMeta($profile->metaData());
    }

    /** @param array<string,mixed> $meta */
    public static function fromMeta(array $meta): self
    {
        $w = is_array($meta[self::KEY] ?? null) ? $meta[self::KEY] : [];
        $sigs = [];
        foreach (is_array($w['signatures'] ?? null) ? $w['signatures'] : [] as $s) {
            if (is_array($s) && self::id($s['id'] ?? null) && is_string($s['name'] ?? null) && is_string($s['html'] ?? null) && count($sigs) < self::MAX_SIGNATURES) {
                $sigs[] = ['id' => $s['id'], 'name' => mb_substr($s['name'], 0, 60), 'html' => HtmlSanitizer::cleanOutgoing(substr($s['html'], 0, self::MAX_SIGNATURE_BYTES))];
            }
        }
        $contacts = [];
        $seen = [];
        foreach (is_array($w['contacts'] ?? null) ? $w['contacts'] : [] as $c) {
            if (!is_array($c) || !self::id($c['id'] ?? null) || !is_string($c['email'] ?? null) || filter_var($c['email'], FILTER_VALIDATE_EMAIL) === false
                || isset($seen[strtolower($c['email'])]) || count($contacts) >= self::MAX_CONTACTS) {
                continue;
            }
            $seen[strtolower($c['email'])] = true;
            $contacts[] = ['id' => $c['id'], 'name' => mb_substr(self::clean((string) ($c['name'] ?? '')), 0, 100), 'email' => $c['email'], 'note' => mb_substr(self::clean((string) ($c['note'] ?? '')), 0, 200)];
        }
        $ids = array_column($sigs, 'id');
        $def = is_string($w['defaultSignature'] ?? null) && in_array($w['defaultSignature'], $ids, true) ? $w['defaultSignature'] : null;
        return new self($sigs, $def, $contacts, $meta);
    }

    /**
     * Read-modify-write against a FRESH copy of metaData (never the cache), keeping all foreign keys.
     *
     * @param callable(self):array{signatures:list<array>,defaultSignature:?string,contacts:list<array>} $change
     * @throws \InvalidArgumentException with a user-facing message when the change is not allowed
     */
    public static function update(ProfileService $profile, callable $change): self
    {
        $cur = self::load($profile);
        $new = $change($cur);
        $meta = $cur->meta;
        $meta[self::KEY] = ['v' => 1, 'signatures' => array_values($new['signatures']), 'defaultSignature' => $new['defaultSignature'], 'contacts' => array_values($new['contacts'])];
        if (strlen((string) json_encode($meta[self::KEY])) > self::MAX_TOTAL_BYTES) {
            throw new \InvalidArgumentException('Your saved contacts and signatures are too large to store. Remove some first.');
        }
        $profile->saveMetaData($meta);
        return self::fromMeta($meta);
    }

    public function signature(string $id): ?array
    {
        foreach ($this->signatures as $s) {
            if ($s['id'] === $id) {
                return $s;
            }
        }
        return null;
    }

    public function defaultSignatureHtml(): string
    {
        return $this->defaultSignature !== null ? ($this->signature($this->defaultSignature)['html'] ?? '') : '';
    }

    public function hasContact(string $email): bool
    {
        foreach ($this->contacts as $c) {
            if (strcasecmp($c['email'], $email) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Contacts first, then address-register hits, de-duplicated by address, at most $limit.
     *
     * @param list<array{name:string,address:string}> $register
     * @return list<array{name:string,email:string}>
     */
    public function suggest(string $q, array $register, int $limit = 8): array
    {
        $q = mb_strtolower(trim($q));
        $out = [];
        $seen = [];
        if ($q !== '') {
            foreach ($this->contacts as $c) {
                if (str_contains(mb_strtolower($c['name'] . ' ' . $c['email']), $q) && !isset($seen[strtolower($c['email'])])) {
                    $seen[strtolower($c['email'])] = true;
                    $out[] = ['name' => $c['name'], 'email' => $c['email']];
                }
            }
        }
        foreach ($register as $r) {
            if (!isset($seen[strtolower($r['address'])])) {
                $seen[strtolower($r['address'])] = true;
                $out[] = ['name' => $r['name'], 'email' => $r['address']];
            }
        }
        return array_slice($out, 0, $limit);
    }

    public static function newId(): string
    {
        return bin2hex(random_bytes(6));
    }

    public static function clean(string $s): string
    {
        return trim(preg_replace('/[\x00-\x1f\x7f<>"]+/u', ' ', $s) ?? '');
    }

    /** Put a signature block into a compose body, after the first empty line when there is one (above any quote). */
    public static function insertSignature(string $bodyHtml, string $sigHtml): string
    {
        if ($sigHtml === '') {
            return $bodyHtml;
        }
        $block = '<div data-signature="1">' . $sigHtml . '</div>';
        $marker = '<p><br></p>';
        if (str_starts_with($bodyHtml, $marker)) {
            return $marker . $block . substr($bodyHtml, strlen($marker));
        }
        return $marker . $block . $bodyHtml;
    }

    private static function id(mixed $v): bool
    {
        return is_string($v) && preg_match('/^[a-f0-9]{12}$/', $v) === 1;
    }
}

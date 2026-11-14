<?php
declare(strict_types=1);

namespace Webmail\WildDuck\Model;

/** A WildDuck filter, held in the same shape the API accepts (originalQuery / originalAction). */
final class Rule
{
    public const BLOCK_PREFIX = 'Blocked sender: ';
    private const QUERY_KEYS = ['from', 'to', 'subject', 'listId', 'text', 'ha', 'size'];
    private const ACTION_KEYS = ['seen', 'flag', 'spam', 'mailbox', 'labels'];

    /** @param array<string,mixed> $query @param array<string,mixed> $action */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly bool $disabled,
        public readonly array $query,
        public readonly array $action,
    ) {
    }

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        if (!is_string($d['id'] ?? null) || !is_string($d['name'] ?? null)) {
            throw new \UnexpectedValueException('Malformed filter.');
        }
        $q = is_array($d['originalQuery'] ?? null) ? $d['originalQuery'] : [];
        $a = is_array($d['originalAction'] ?? null) ? $d['originalAction'] : [];
        return new self($d['id'], mb_substr($d['name'], 0, 200), (bool) ($d['disabled'] ?? false), $q, $a);
    }

    /**
     * True when this rule only uses conditions and actions the UI can show and edit.
     * Rules with forwarding, permanent delete or anything unknown are "advanced": they stay visible and can be
     * switched off or deleted, but are never rewritten here (a rewrite could silently drop the forwarding/delete part).
     */
    public function isSimple(): bool
    {
        if (array_diff(array_keys($this->query), self::QUERY_KEYS) || array_diff(array_keys($this->action), self::ACTION_KEYS)) {
            return false;
        }
        foreach ($this->action as $k => $v) {
            if ($k === 'labels' ? !is_array($v) : ($k === 'mailbox' ? !is_string($v) : !is_bool($v))) {
                return false;
            }
        }
        return true;
    }

    /** The blocked address/domain when this is one of our "block sender" rules, else null. */
    public function blockedPattern(): ?string
    {
        if (str_starts_with($this->name, self::BLOCK_PREFIX) && array_keys($this->query) === ['from'] && is_string($this->query['from'])
            && array_keys($this->action) === ['spam'] && $this->action['spam'] === true) {
            return $this->query['from'];
        }
        return null;
    }
}

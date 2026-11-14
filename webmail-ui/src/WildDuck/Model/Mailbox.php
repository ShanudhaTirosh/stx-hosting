<?php
declare(strict_types=1);

namespace Webmail\WildDuck\Model;

final class Mailbox
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $path,
        public readonly ?string $specialUse,
        public readonly int $total,
        public readonly int $unseen,
    ) {
    }

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        if (!is_string($d['id'] ?? null) || !is_string($d['path'] ?? null)) {
            throw new \UnexpectedValueException('Malformed mailbox.');
        }
        return new self(
            $d['id'],
            is_string($d['name'] ?? null) ? $d['name'] : $d['path'],
            $d['path'],
            is_string($d['specialUse'] ?? null) ? $d['specialUse'] : null,
            (int) ($d['total'] ?? 0),
            (int) ($d['unseen'] ?? 0),
        );
    }

    public function slug(): string
    {
        return match ($this->specialUse) {
            '\\Inbox' => 'inbox',
            '\\Sent' => 'sent',
            '\\Drafts' => 'drafts',
            '\\Archive' => 'archive',
            '\\Junk' => 'spam',
            '\\Trash' => 'trash',
            default => $this->id,
        };
    }

    public function label(): string
    {
        return match ($this->specialUse) {
            '\\Inbox' => 'Inbox',
            '\\Sent' => 'Sent',
            '\\Drafts' => 'Drafts',
            '\\Archive' => 'Archive',
            '\\Junk' => 'Spam',
            '\\Trash' => 'Trash',
            default => $this->path,
        };
    }

    public function isSystem(): bool
    {
        return $this->specialUse !== null;
    }
}

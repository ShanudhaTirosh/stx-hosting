<?php
declare(strict_types=1);

namespace Webmail\WildDuck\Model;

final class MessageSummary
{
    /** @param list<array{name:string,address:string}> $to @param list<string> $labels */
    public function __construct(
        public readonly int $id,
        public readonly string $mailboxId,
        public readonly string $threadId,
        public readonly int $threadCount,
        public readonly string $fromName,
        public readonly string $fromAddress,
        public readonly array $to,
        public readonly string $subject,
        public readonly string $intro,
        public readonly ?\DateTimeImmutable $date,
        public readonly bool $hasAttachments,
        public readonly bool $seen,
        public readonly bool $flagged,
        public readonly bool $draft,
        public readonly bool $answered,
        public readonly array $labels,
    ) {
    }

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        if (!isset($d['id'], $d['mailbox']) || !is_int($d['id']) || !is_string($d['mailbox'])) {
            throw new \UnexpectedValueException('Malformed message.');
        }
        $from = is_array($d['from'] ?? null) ? $d['from'] : [];
        return new self(
            $d['id'],
            $d['mailbox'],
            is_string($d['thread'] ?? null) ? $d['thread'] : '',
            (int) ($d['threadMessageCount'] ?? 1),
            (string) ($from['name'] ?? ''),
            (string) ($from['address'] ?? ''),
            self::addresses($d['to'] ?? []),
            (string) ($d['subject'] ?? ''),
            (string) ($d['intro'] ?? ''),
            self::date($d['date'] ?? null),
            (bool) ($d['attachments'] ?? false),
            (bool) ($d['seen'] ?? false),
            (bool) ($d['flagged'] ?? false),
            (bool) ($d['draft'] ?? false),
            (bool) ($d['answered'] ?? false),
            array_values(array_filter((array) ($d['labels'] ?? []), 'is_string')),
        );
    }

    /** @return list<array{name:string,address:string}> */
    public static function addresses(mixed $list): array
    {
        $out = [];
        foreach (is_array($list) ? $list : [] as $a) {
            if (is_array($a) && isset($a['address']) && is_string($a['address'])) {
                $out[] = ['name' => (string) ($a['name'] ?? ''), 'address' => $a['address']];
            }
        }
        return $out;
    }

    public static function date(mixed $v): ?\DateTimeImmutable
    {
        if (!is_string($v) || $v === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($v);
        } catch (\Exception) {
            return null;
        }
    }

    public function senderLabel(): string
    {
        return $this->fromName !== '' ? $this->fromName : ($this->fromAddress !== '' ? $this->fromAddress : '(unknown sender)');
    }
}

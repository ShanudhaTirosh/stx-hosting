<?php
declare(strict_types=1);

namespace Webmail\WildDuck\Model;

final class MessageDetail
{
    /**
     * @param list<array{name:string,address:string}> $to
     * @param list<array{name:string,address:string}> $cc
     * @param list<array{name:string,address:string}> $bcc
     * @param list<array{name:string,address:string}> $replyTo
     * @param list<array{id:string,filename:string,contentType:string,size:int,related:bool}> $attachments
     * @param list<string> $labels
     */
    public function __construct(
        public readonly int $id,
        public readonly string $mailboxId,
        public readonly string $threadId,
        public readonly string $fromName,
        public readonly string $fromAddress,
        public readonly array $to,
        public readonly array $cc,
        public readonly array $bcc,
        public readonly array $replyTo,
        public readonly string $subject,
        public readonly string $messageId,
        public readonly ?\DateTimeImmutable $date,
        public readonly string $html,
        public readonly string $text,
        public readonly array $attachments,
        public readonly bool $seen,
        public readonly bool $flagged,
        public readonly bool $draft,
        public readonly array $labels,
        public readonly ?string $unsubscribe,
    ) {
    }

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        if (!isset($d['id'], $d['mailbox']) || !is_int($d['id']) || !is_string($d['mailbox'])) {
            throw new \UnexpectedValueException('Malformed message.');
        }
        $from = is_array($d['from'] ?? null) ? $d['from'] : [];
        $atts = [];
        foreach (is_array($d['attachments'] ?? null) ? $d['attachments'] : [] as $a) {
            if (is_array($a) && is_string($a['id'] ?? null)) {
                $atts[] = [
                    'id' => $a['id'],
                    'filename' => (string) ($a['filename'] ?? 'attachment'),
                    'contentType' => (string) ($a['contentType'] ?? 'application/octet-stream'),
                    'size' => (int) ($a['size'] ?? 0),
                    'related' => (bool) ($a['related'] ?? false),
                ];
            }
        }
        $unsub = null;
        $list = $d['list'] ?? null;
        if (is_array($list) && is_string($list['unsubscribe'][0] ?? null)) {
            $unsub = $list['unsubscribe'][0];
        }
        return new self(
            $d['id'],
            $d['mailbox'],
            is_string($d['thread'] ?? null) ? $d['thread'] : '',
            (string) ($from['name'] ?? ''),
            (string) ($from['address'] ?? ''),
            MessageSummary::addresses($d['to'] ?? []),
            MessageSummary::addresses($d['cc'] ?? []),
            MessageSummary::addresses($d['bcc'] ?? []),
            MessageSummary::addresses($d['replyTo'] ?? []),
            (string) ($d['subject'] ?? ''),
            (string) ($d['messageId'] ?? ''),
            MessageSummary::date($d['date'] ?? null),
            implode('', array_filter((array) ($d['html'] ?? []), 'is_string')),
            (string) ($d['text'] ?? ''),
            $atts,
            (bool) ($d['seen'] ?? false),
            (bool) ($d['flagged'] ?? false),
            (bool) ($d['draft'] ?? false),
            array_values(array_filter((array) ($d['labels'] ?? []), 'is_string')),
            $unsub,
        );
    }

    public function senderLabel(): string
    {
        return $this->fromName !== '' ? $this->fromName : ($this->fromAddress !== '' ? $this->fromAddress : '(unknown sender)');
    }
}

<?php
declare(strict_types=1);

namespace Webmail\Mail;

/** State of the compose screen (what the form shows and what a submit carries). */
final class ComposeForm
{
    /** Chosen send-from address ('' = the account's main address). Always re-validated on send. */
    public string $from = '';

    /**
     * @param array{mailbox:string,id:int}|null $draft
     * @param array{mailbox:string,id:int,action:string}|null $reference
     * @param array{mailbox:string,id:int}|null $source message whose attachments may be carried over
     * @param list<array{id:string,filename:string,size:int,keep:bool}> $carry attachments offered for carry-over
     */
    public function __construct(
        public string $to = '',
        public string $cc = '',
        public string $bcc = '',
        public string $subject = '',
        public string $html = '',
        public ?array $draft = null,
        public ?array $reference = null,
        public ?array $source = null,
        public array $carry = [],
        public string $composeId = '',
        public string $mode = 'new',
    ) {
        $this->composeId = $composeId !== '' ? $composeId : bin2hex(random_bytes(16));
    }

    public function heading(): string
    {
        return match ($this->mode) {
            'reply' => 'Reply',
            'replyAll' => 'Reply all',
            'forward' => 'Forward',
            'draft' => 'Edit draft',
            default => 'New message',
        };
    }
}

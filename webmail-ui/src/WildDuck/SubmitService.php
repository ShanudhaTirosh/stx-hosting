<?php
declare(strict_types=1);

namespace Webmail\WildDuck;

final class SubmitService
{
    public function __construct(private readonly Client $api, private readonly string $userId)
    {
        Ids::require($userId, 'object');
    }

    /**
     * Send a message (stored in Sent by WildDuck) or store it as a draft.
     *
     * @param array{name:string,address:string} $from
     * @param list<array{name:string,address:string}> $to
     * @param list<array{name:string,address:string}> $cc
     * @param list<array{name:string,address:string}> $bcc
     * @param list<array{filename:string,contentType:string,content:string}> $attachments base64 content
     * @param array{mailbox:string,id:int,action:string}|null $reference reply / replyAll / forward target
     * @param array{mailbox:string,id:int}|null $draft draft this message replaces (WildDuck deletes it afterwards)
     * @return array{mailbox:?string,id:?int}  location of the stored message when WildDuck reports it
     */
    public function submit(array $from, array $to, array $cc, array $bcc, string $subject, string $html, string $text, array $attachments, bool $asDraft, ?array $reference = null, ?array $draft = null): array
    {
        $body = [
            'from' => $this->addr($from),
            'to' => array_map($this->addr(...), $to),
            'cc' => array_map($this->addr(...), $cc),
            'bcc' => array_map($this->addr(...), $bcc),
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];
        if ($attachments) {
            $body['attachments'] = array_map(static fn (array $a): array => [
                'filename' => $a['filename'],
                'contentType' => $a['contentType'],
                'encoding' => 'base64',
                'content' => $a['content'],
                'contentDisposition' => 'attachment',
            ], $attachments);
        }
        if ($asDraft) {
            $body['isDraft'] = true;
        }
        if ($reference !== null) {
            Ids::require($reference['mailbox'], 'object');
            $body['reference'] = ['mailbox' => $reference['mailbox'], 'id' => $reference['id'], 'action' => $reference['action']];
        }
        if ($draft !== null) {
            Ids::require($draft['mailbox'], 'object');
            $body['draft'] = ['mailbox' => $draft['mailbox'], 'id' => $draft['id']];
        }

        $res = $this->api->json('POST', "/users/{$this->userId}/submit", [], $body);
        $m = is_array($res['message'] ?? null) ? $res['message'] : [];
        return [
            'mailbox' => is_string($m['mailbox'] ?? null) ? $m['mailbox'] : null,
            'id' => isset($m['id']) && is_int($m['id']) ? $m['id'] : null,
        ];
    }

    /** @param array{name:string,address:string} $a @return array{name?:string,address:string} */
    private function addr(array $a): array
    {
        return $a['name'] !== '' ? ['name' => $a['name'], 'address' => $a['address']] : ['address' => $a['address']];
    }
}

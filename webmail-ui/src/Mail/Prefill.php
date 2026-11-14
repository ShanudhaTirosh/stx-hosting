<?php
declare(strict_types=1);

namespace Webmail\Mail;

use Webmail\Support\HtmlSanitizer;
use Webmail\WildDuck\Model\MessageDetail;

/** Builds the compose form for reply / reply all / forward / editing a draft. */
final class Prefill
{
    public static function reply(MessageDetail $m, string $own, bool $all): ComposeForm
    {
        $primary = $m->replyTo ?: [['name' => $m->fromName, 'address' => $m->fromAddress]];
        $to = $primary;
        $cc = [];
        if ($all) {
            $to = array_merge($primary, $m->to);
            $cc = $m->cc;
        }
        $to = self::without($to, $own);
        $cc = self::without($cc, $own, $to);
        if (!$to && $all) {
            $to = self::without($m->to, $own) ?: $primary; // replying to your own message
        }

        $f = new ComposeForm(
            AddressParser::format($to),
            AddressParser::format($cc),
            '',
            self::prefixed('Re', $m->subject),
            '<p><br></p>' . self::quote($m, 'wrote'),
            null,
            ['mailbox' => $m->mailboxId, 'id' => $m->id, 'action' => $all ? 'replyAll' : 'reply'],
            null,
            [],
            '',
            $all ? 'replyAll' : 'reply',
        );
        return $f;
    }

    public static function forward(MessageDetail $m): ComposeForm
    {
        $head = '<p><br></p><p>---------- Forwarded message ----------</p>'
            . '<p>From: ' . e(self::who($m->fromName, $m->fromAddress)) . '<br>'
            . ($m->date ? 'Date: ' . e($m->date->format('D, j M Y H:i')) . '<br>' : '')
            . 'Subject: ' . e($m->subject) . '<br>'
            . ($m->to ? 'To: ' . e(AddressParser::format($m->to)) . '<br>' : '')
            . ($m->cc ? 'Cc: ' . e(AddressParser::format($m->cc)) . '<br>' : '')
            . '</p>' . self::body($m);
        return new ComposeForm('', '', '', self::prefixed('Fwd', $m->subject), $head, null,
            ['mailbox' => $m->mailboxId, 'id' => $m->id, 'action' => 'forward'], ['mailbox' => $m->mailboxId, 'id' => $m->id], self::carry($m, true), '', 'forward');
    }

    public static function draft(MessageDetail $m): ComposeForm
    {
        return new ComposeForm(
            AddressParser::format($m->to), AddressParser::format($m->cc), AddressParser::format($m->bcc), $m->subject,
            HtmlSanitizer::cleanOutgoing($m->html !== '' ? $m->html : '<p>' . nl2br(e($m->text), false) . '</p>'),
            ['mailbox' => $m->mailboxId, 'id' => $m->id], null, ['mailbox' => $m->mailboxId, 'id' => $m->id], self::carry($m, true), '', 'draft',
        );
    }

    /** @return list<array{id:string,filename:string,size:int,keep:bool}> */
    private static function carry(MessageDetail $m, bool $keep): array
    {
        $out = [];
        foreach ($m->attachments as $a) {
            if (!$a['related']) {
                $out[] = ['id' => $a['id'], 'filename' => $a['filename'], 'size' => $a['size'], 'keep' => $keep];
            }
        }
        return $out;
    }

    private static function quote(MessageDetail $m, string $verb): string
    {
        $when = $m->date ? 'On ' . $m->date->format('D, j M Y \a\t H:i') . ', ' : '';
        return '<p>' . e($when . self::who($m->fromName, $m->fromAddress) . " $verb:") . '</p><blockquote>' . self::body($m) . '</blockquote>';
    }

    private static function body(MessageDetail $m): string
    {
        $html = trim($m->html) !== '' ? HtmlSanitizer::cleanOutgoing($m->html) : '';
        return $html !== '' ? $html : '<p>' . nl2br(e($m->text), false) . '</p>';
    }

    private static function who(string $name, string $addr): string
    {
        return $name !== '' ? "$name <$addr>" : $addr;
    }

    private static function prefixed(string $p, string $subject): string
    {
        $s = trim($subject);
        return preg_match('/^' . $p . ':\s*/i', $s) === 1 ? $s : "$p: $s";
    }

    /**
     * @param list<array{name:string,address:string}> $list
     * @param list<array{name:string,address:string}> $alsoExclude
     * @return list<array{name:string,address:string}>
     */
    private static function without(array $list, string $own, array $alsoExclude = []): array
    {
        $skip = [strtolower($own) => true];
        foreach ($alsoExclude as $a) {
            $skip[strtolower($a['address'])] = true;
        }
        $out = [];
        foreach ($list as $a) {
            $k = strtolower($a['address']);
            if (!isset($skip[$k])) {
                $skip[$k] = true;
                $out[] = $a;
            }
        }
        return $out;
    }
}

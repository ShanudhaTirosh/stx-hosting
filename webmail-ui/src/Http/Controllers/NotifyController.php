<?php
declare(strict_types=1);

namespace Webmail\Http\Controllers;

use Webmail\App;
use Webmail\Core\{Request, Response};
use Webmail\WildDuck\{ApiException, MailboxService};

/** Cheap status poll for the unread indicator. One WildDuck call per request; a second one only when new mail has arrived. */
final class NotifyController
{
    public static function poll(Request $r, App $app): Response
    {
        $known = self::int($r->string('known'));
        $knownUnseen = self::int($r->string('unseen'));
        $api = $app->mailboxes();
        $messages = $app->messages();
        // Unread counts of the OTHER signed-in accounts (at most four, short timeout each); prepared before the session is released.
        $others = [];
        foreach ($app->auth->accounts() as $a) {
            $acc = $a['active'] || count($others) >= 4 ? null : $app->auth->account($a['id']);
            $tok = $acc !== null ? $app->auth->tokenFor($a['id']) : null;
            if ($acc !== null && $tok !== null) {
                $others[$a['id']] = new MailboxService($app->clientFor($tok)->withTimeout(4), $acc['userId']);
            }
        }
        $app->session->close(); // never hold the session lock while waiting for WildDuck

        $folders = $api->all();
        $unseen = [];
        $inbox = null;
        foreach ($folders as $f) {
            $unseen[$f->slug()] = $f->unseen;
            if ($f->specialUse === '\\Inbox') {
                $inbox = $f;
            }
        }

        $new = [];
        if ($inbox !== null && $known !== null && $knownUnseen !== null && $inbox->total > $known && $inbox->unseen > $knownUnseen) {
            // Only the messages that arrived since the client's last poll (growth in total and in unread), newest first, at most three.
            $page = $messages->list($inbox->id, null, false, true, max(1, min(3, $inbox->total - $known, $inbox->unseen - $knownUnseen)));
            foreach ($page->items as $m) {
                $new[] = ['id' => $m->id, 'mailbox' => $m->mailboxId, 'from' => self::text($m->senderLabel(), 80), 'subject' => self::text($m->subject !== '' ? $m->subject : '(no subject)', 120)];
            }
        }

        $otherUnseen = [];
        foreach ($others as $id => $svc) {
            try {
                foreach ($svc->all() as $f) {
                    if ($f->specialUse === '\\Inbox') {
                        $otherUnseen[$id] = $f->unseen;
                    }
                }
            } catch (ApiException) {
                // an unreachable or expired other account is simply left out
            }
        }

        return Response::json([
            'others' => (object) $otherUnseen,
            'inbox' => ['unseen' => $inbox?->unseen ?? 0, 'total' => $inbox?->total ?? 0],
            'folders' => (object) $unseen,
            'new' => $new,
        ])->withHeader('Cache-Control', 'private, no-store');
    }

    private static function int(string $v): ?int
    {
        return preg_match('/^[0-9]{1,9}$/', $v) === 1 ? (int) $v : null;
    }

    private static function text(string $s, int $max): string
    {
        return mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $s) ?? ''), 0, $max);
    }
}

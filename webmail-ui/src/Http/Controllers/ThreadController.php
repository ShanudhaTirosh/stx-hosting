<?php
declare(strict_types=1);

namespace Webmail\Http\Controllers;

use Webmail\App;
use Webmail\Core\{Request, Response};
use Webmail\WildDuck\{ApiException, Ids};

final class ThreadController
{
    public static function show(Request $r, App $app): Response
    {
        $id = Ids::require($r->params['id'], 'object');
        $page = $app->messages()->thread($id);
        $owned = array_column(array_map(fn ($f) => ['id' => $f->id, 'f' => $f], $app->folders()), 'f', 'id');
        $items = array_values(array_filter($page->items, fn ($m) => isset($owned[$m->mailboxId])));
        if (!$items) {
            throw new ApiException('Conversation not found.', 404, 'ThreadNotFound');
        }

        // Opening a conversation marks it read (one call per mailbox).
        $unseen = [];
        foreach ($items as $m) {
            if (!$m->seen && !$m->draft) {
                $unseen[$m->mailboxId][] = $m->id;
            }
        }
        foreach ($unseen as $box => $uids) {
            $app->messages()->bulk($box, $uids, ['seen' => true]);
        }
        if ($unseen) {
            $app->forgetSidebar();
        }

        $latest = $items[array_key_last($items)];
        return $app->page('mail.thread', $latest->subject !== '' ? $latest->subject : 'Conversation', '', [
            'items' => $items, 'owned' => $owned, 'wasUnseen' => array_map(fn ($m) => !$m->seen, $items), 'threadId' => $id,
            'subject' => $latest->subject,
        ], null, '', ['bulk']);
    }
}

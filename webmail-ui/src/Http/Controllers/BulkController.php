<?php
declare(strict_types=1);

namespace Webmail\Http\Controllers;

use Webmail\App;
use Webmail\Core\{Request, Response};
use Webmail\Support\Url;
use Webmail\WildDuck\Ids;
use Webmail\WildDuck\Model\Mailbox;

/** Apply one action to many selected messages ("mailboxId:uid" pairs), possibly spread over several mailboxes. */
final class BulkController
{
    private const MAX = 100;

    public static function apply(Request $r, App $app): Response
    {
        $return = Url::safeReturn($r->string('return') ?: null, '/mail/inbox');
        $do = $r->string('do');
        $folders = $app->folders();
        $byId = [];
        foreach ($folders as $f) {
            $byId[$f->id] = $f;
        }

        // Parse + authorise the selection: only mailboxes this account owns, only well-formed ids.
        $groups = [];
        $raw = (array) ($r->post['items'] ?? []);
        foreach (array_slice($raw, 0, self::MAX + 1) as $item) {
            if (!is_string($item) || preg_match('/^([a-f0-9]{24}):([1-9][0-9]{0,9})$/', $item, $m) !== 1 || !isset($byId[$m[1]])) {
                continue;
            }
            $groups[$m[1]][(int) $m[2]] = true;
        }
        $count = array_sum(array_map('count', $groups));
        if ($count === 0) {
            $app->session->flash('error', 'Select at least one message first.');
            return Response::redirect($return);
        }
        if (count($raw) > self::MAX) {
            $app->session->flash('error', 'You can change at most ' . self::MAX . ' messages at once.');
            return Response::redirect($return);
        }

        $svc = $app->messages();
        $done = 0;
        $message = '';

        switch ($do) {
            case 'read':
            case 'unread':
            case 'star':
            case 'unstar':
                $change = in_array($do, ['read', 'unread'], true) ? ['seen' => $do === 'read'] : ['flagged' => $do === 'star'];
                foreach ($groups as $box => $uids) {
                    $done += $svc->bulk($box, array_keys($uids), $change);
                }
                $message = match ($do) { 'read' => 'marked as read', 'unread' => 'marked as unread', 'star' => 'starred', default => 'unstarred' };
                break;

            case 'archive':
            case 'spam':
            case 'trash':
            case 'move':
                $target = match ($do) {
                    'archive' => MailController::special($folders, '\\Archive'),
                    'spam' => MailController::special($folders, '\\Junk'),
                    'trash' => MailController::special($folders, '\\Trash'),
                    default => $byId[$r->string('folder')] ?? null,
                };
                if ($target === null) {
                    $app->session->flash('error', $do === 'move' ? 'Choose a folder to move to.' : 'That folder is not set up on this account.');
                    return Response::redirect($return);
                }
                foreach ($groups as $box => $uids) {
                    if ($box === $target->id) {
                        continue;
                    }
                    $done += $svc->bulk($box, array_keys($uids), ['moveTo' => $target->id]);
                }
                $message = 'moved to ' . $target->label();
                break;

            case 'delete':
                $trash = MailController::special($folders, '\\Trash');
                foreach ($groups as $box => $uids) {
                    if ($trash === null || $box !== $trash->id) {
                        continue; // permanent deletion only ever applies to messages already in Trash
                    }
                    foreach (array_keys($uids) as $uid) {
                        $svc->destroy($box, (string) $uid);
                        $done++;
                    }
                }
                $message = 'deleted permanently';
                break;

            case 'label_add':
            case 'label_remove':
                $label = null;
                foreach ($app->labels() as $l) {
                    if ($l->id === $r->string('label')) {
                        $label = $l;
                    }
                }
                if ($label === null) {
                    $app->session->flash('error', 'Choose a label first.');
                    return Response::redirect($return);
                }
                $key = $do === 'label_add' ? 'addLabels' : 'removeLabels';
                foreach ($groups as $box => $uids) {
                    $done += $svc->bulk($box, array_keys($uids), [$key => [$label->name]]);
                }
                $message = ($do === 'label_add' ? 'labelled “' : 'unlabelled “') . $label->name . '”';
                break;

            default:
                $app->session->flash('error', 'Unknown action.');
                return Response::redirect($return);
        }

        $app->session->flash($done > 0 ? 'success' : 'info', $done > 0 ? $done . ' message' . ($done === 1 ? '' : 's') . ' ' . $message . '.' : 'Nothing to change for that selection.');
        return Response::redirect($return);
    }
}

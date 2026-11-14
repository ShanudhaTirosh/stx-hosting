<?php
declare(strict_types=1);

namespace Webmail\Http\Controllers;

use Webmail\App;
use Webmail\Core\{Request, Response};
use Webmail\Support\{HtmlSanitizer, Url};
use Webmail\WildDuck\{ApiException, Ids};
use Webmail\WildDuck\Model\Mailbox;

final class MailController
{
    public static function root(Request $r, App $app): Response
    {
        return Response::redirect('/mail/inbox');
    }

    public static function list(Request $r, App $app): Response
    {
        $folders = $app->folders();
        $box = self::box($folders, $r->params['box']);
        $unseen = $r->string('unseen') === '1';
        $cursor = Url::cursor($r->string('cursor') ?: null);
        $page = $app->messages()->list($box->id, $cursor, $r->string('dir') === 'prev', $unseen);

        return $app->page('mail.list', $box->label(), $box->slug(), [
            'title' => $box->label(),
            'summary' => $box->total . ' message' . ($box->total === 1 ? '' : 's') . ($box->unseen > 0 ? ', ' . $box->unseen . ' unread' : ''),
            'page' => $page,
            'slug' => $box->slug(),
            'base' => '/mail/' . rawurlencode($box->slug()),
            'keep' => array_filter(['unseen' => $unseen ? '1' : null]),
            'unseenOnly' => $unseen,
            'isSearch' => false,
            'cursor' => $cursor,
            'self' => $r->uri(),
            'inTrash' => $box->specialUse === '\\Trash',
            'currentBox' => $box->id,
        ], $folders, '', ['bulk']);
    }

    public static function search(Request $r, App $app): Response
    {
        $q = mb_substr(trim($r->string('q')), 0, 200);
        $cursor = Url::cursor($r->string('cursor') ?: null);
        $page = $app->messages()->search($q, [], $cursor, $r->string('dir') === 'prev');
        return $app->page('mail.list', 'Search', '', [
            'title' => $q === '' ? 'Search' : 'Results for “' . $q . '”',
            'summary' => $q === '' ? 'Type something in the search box.' : $page->total . ' result' . ($page->total === 1 ? '' : 's'),
            'page' => $page,
            'slug' => '',
            'base' => '/search',
            'keep' => ['q' => $q],
            'unseenOnly' => false,
            'isSearch' => true,
            'cursor' => $cursor,
            'self' => $r->uri(),
        ], null, $q, ['bulk']);
    }

    public static function show(Request $r, App $app): Response
    {
        $uid = self::uid($r);
        $box = self::boxById($app->folders(), $r->params['box']);
        $msg = $app->messages()->get($box->id, $uid, true);
        $app->forgetSidebar(); // opening a message marks it read, so counters changed
        $folders = $app->folders();
        $hasHtml = trim($msg->html) !== '';
        $images = $r->string('images') === '1';
        $blocked = 0;
        if ($hasHtml) {
            $blocked = HtmlSanitizer::clean(self::rewriteInline($msg->html, $app->userId(), $box->id, $uid), false)['blockedImages'];
        }
        return $app->page('mail.show', $msg->subject !== '' ? $msg->subject : 'Message', $box->slug(), [
            'm' => $msg,
            'slug' => $box->slug(),
            'folderLabel' => $box->label(),
            'inTrash' => $box->specialUse === '\\Trash',
            'imagesAllowed' => $images,
            'blockedImages' => $blocked,
            'hasHtml' => $hasHtml,
            'canAddContact' => ($pr = $app->prefsOrNull()) !== null && $msg->fromAddress !== '' && !$pr->hasContact($msg->fromAddress) && strcasecmp($msg->fromAddress, $app->auth->user()['address'] ?? '') !== 0,
        ], $folders);
    }

    /** Sanitised HTML for the sandboxed iframe. Locked down by CSP even if the sanitiser were bypassed. */
    public static function body(Request $r, App $app): Response
    {
        $uid = self::uid($r);
        $box = self::boxById($app->folders(), $r->params['box']);
        $msg = $app->messages()->get($box->id, $uid, false);
        $images = $r->string('images') === '1';
        $clean = HtmlSanitizer::clean(self::rewriteInline($msg->html, $app->userId(), $box->id, $uid), $images)['html'];
        $clean = $clean !== '' ? $clean : ($msg->text !== '' ? '<pre style="white-space:pre-wrap;font:13px/1.55 ui-monospace,monospace;margin:0">' . e($msg->text) . '</pre>' : '');
        $doc = '<!doctype html><html><head><meta charset="utf-8"><base target="_blank"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>body{margin:0;padding:16px;font:14px/1.55 system-ui,sans-serif;color:#111;background:#fff;overflow-wrap:anywhere}'
            . 'img{max-width:100%;height:auto}table{max-width:100%}blockquote{margin:8px 0;padding-left:12px;border-left:3px solid #ddd;color:#555}'
            . 'img[data-blocked]{display:inline-block;min-width:24px;min-height:24px;background:#eee}a{color:#5a12c7}</style></head><body>' . $clean . '</body></html>';
        return Response::html($doc)->withHeader(
            'Content-Security-Policy',
            "sandbox allow-same-origin allow-popups allow-popups-to-escape-sandbox; default-src 'none'; style-src 'unsafe-inline'; "
            . ($images ? "img-src 'self' data: https:" : "img-src 'self' data:") . "; base-uri 'none'; form-action 'none'; frame-ancestors 'self'"
        )->withHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public static function attachment(Request $r, App $app): Response
    {
        $uid = self::uid($r);
        $att = $r->params['att'] ?? '';
        $box = self::boxById($app->folders(), $r->params['box']);
        $file = $app->messages()->attachment($box->id, $uid, Ids::require($att, 'attachment'));

        $type = strtolower(trim(explode(';', $file['contentType'])[0]));
        $safeImage = in_array($type, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
        // Only raster images requested explicitly (?inline=1, used for cid images) are shown inline; everything else downloads.
        $inline = $safeImage && $r->string('inline') === '1';
        $name = self::filename($file['disposition']);

        return new Response(200, $file['body'], [
            'Content-Type' => $safeImage ? $type : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment')
                . '; filename="' . str_replace(['"', '\\'], '', self::asciiName($name)) . '"; filename*=UTF-8\'\'' . rawurlencode($name),
            'Content-Length' => (string) strlen($file['body']),
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public static function action(Request $r, App $app): Response
    {
        $uid = self::uid($r);
        $folders = $app->folders();
        $box = self::boxById($folders, $r->params['box']);
        $svc = $app->messages();
        $do = $r->string('do');
        $listUrl = '/mail/' . rawurlencode($box->slug());
        $msgUrl = $listUrl . '/' . $uid;
        $return = Url::safeReturn($r->string('return') ?: null, $msgUrl);

        $move = static function (?Mailbox $target, string $label) use ($svc, $box, $uid, $app, $listUrl): Response {
            if ($target === null) {
                $app->session->flash('error', "No $label folder is set up on this account.");
                return Response::redirect($listUrl);
            }
            $svc->update($box->id, $uid, ['moveTo' => $target->id]);
            $app->session->flash('success', 'Moved to ' . $target->label() . '.');
            return Response::redirect($listUrl);
        };

        switch ($do) {
            case 'star':
            case 'unstar':
                $svc->update($box->id, $uid, ['flagged' => $do === 'star']);
                return Response::redirect($return);
            case 'read':
            case 'unread':
                $svc->update($box->id, $uid, ['seen' => $do === 'read']);
                $app->session->flash('success', $do === 'read' ? 'Marked as read.' : 'Marked as unread.');
                return Response::redirect($do === 'unread' ? $listUrl : $return);
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
                    return Response::redirect($msgUrl);
                }
                $svc->bulk($box->id, [(int) $uid], [$do === 'label_add' ? 'addLabels' : 'removeLabels' => [$label->name]]);
                $app->session->flash('success', ($do === 'label_add' ? 'Label “' : 'Removed label “') . $label->name . '”' . ($do === 'label_add' ? ' added.' : '.'));
                return Response::redirect($msgUrl);
            case 'archive':
                return $move(self::special($folders, '\\Archive'), 'Archive');
            case 'spam':
                return $move(self::special($folders, '\\Junk'), 'Spam');
            case 'trash':
                return $box->specialUse === '\\Trash' ? self::destroy($app, $svc, $box, $uid, $listUrl) : $move(self::special($folders, '\\Trash'), 'Trash');
            case 'delete':
                if ($box->specialUse !== '\\Trash') {
                    // Permanent deletion is only allowed from Trash.
                    $app->session->flash('error', 'Move the message to Trash first.');
                    return Response::redirect($msgUrl);
                }
                return self::destroy($app, $svc, $box, $uid, $listUrl);
        }
        $app->session->flash('error', 'Unknown action.');
        return Response::redirect($return);
    }

    private static function destroy(App $app, \Webmail\WildDuck\MessageService $svc, Mailbox $box, string $uid, string $listUrl): Response
    {
        $svc->destroy($box->id, $uid);
        $app->session->flash('success', 'Message deleted permanently.');
        return Response::redirect($listUrl);
    }

    /** @param list<Mailbox> $all */
    private static function box(array $all, string $slug): Mailbox
    {
        foreach ($all as $m) {
            if ($m->slug() === $slug) {
                return $m;
            }
        }
        throw new ApiException('Folder not found.', 404, 'MailboxNotFound');
    }

    /** @param list<Mailbox> $all  Only mailboxes the account actually owns can be addressed. */
    public static function boxById(array $all, string $slugOrId): Mailbox
    {
        foreach ($all as $m) {
            if ($m->id === $slugOrId || $m->slug() === $slugOrId) {
                return $m;
            }
        }
        throw new ApiException('Folder not found.', 404, 'MailboxNotFound');
    }

    /** @param list<Mailbox> $all */
    public static function special(array $all, string $use): ?Mailbox
    {
        foreach ($all as $m) {
            if ($m->specialUse === $use) {
                return $m;
            }
        }
        return null;
    }

    private static function uid(Request $r): string
    {
        return Ids::require($r->params['uid'] ?? '', 'uid');
    }

    /** Point WildDuck API attachment links (used for cid: images) at our own proxy so no API paths reach the browser. */
    private static function rewriteInline(string $html, string $userId, string $mailboxId, string $uid): string
    {
        return preg_replace_callback(
            '#/users/' . preg_quote(rawurlencode($userId), '#') . '/mailboxes/([a-f0-9]{24})/messages/([0-9]+)/attachments/(ATT[0-9]+)#',
            static fn (array $m): string => '/mail/' . $m[1] . '/' . $m[2] . '/attachment/' . $m[3] . '?inline=1',
            $html
        ) ?? $html;
    }

    private static function filename(string $disposition): string
    {
        $name = '';
        if (preg_match("/filename\\*\\s*=\\s*UTF-8''([^;]+)/i", $disposition, $m) === 1) {
            $name = rawurldecode($m[1]);
        } elseif (preg_match('/filename\s*=\s*"?([^";]+)"?/i', $disposition, $m) === 1) {
            $name = $m[1];
        }
        $name = preg_replace('/[\x00-\x1f\x7f\/\\\\]+/u', '', $name) ?? '';
        $name = trim($name, ". \t");
        return $name !== '' ? mb_substr($name, 0, 180) : 'attachment';
    }

    private static function asciiName(string $n): string
    {
        return preg_replace('/[^\x20-\x7e]/', '_', $n) ?? 'attachment';
    }
}

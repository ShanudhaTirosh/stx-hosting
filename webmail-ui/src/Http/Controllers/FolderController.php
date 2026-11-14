<?php
declare(strict_types=1);

namespace Webmail\Http\Controllers;

use Webmail\App;
use Webmail\Core\{Request, Response};
use Webmail\WildDuck\{ApiException, MailboxService};

final class FolderController
{
    public static function index(Request $r, App $app): Response
    {
        return $app->page('folders.index', 'Folders', 'manage-folders', ['custom' => array_values(array_filter($app->folders(), fn ($f) => !$f->isSystem())), 'errors' => []]);
    }

    public static function create(Request $r, App $app): Response
    {
        $path = MailboxService::validPath($r->string('path'));
        if ($path === null) {
            return self::fail($app, 'Enter a folder name. Use “/” for sub-folders; no empty parts, “.”, “..”, “*” or “%”.');
        }
        if (self::exists($app, $path)) {
            return self::fail($app, "A folder called “{$path}” already exists.");
        }
        try {
            $app->mailboxes()->create($path);
        } catch (ApiException $e) {
            return self::apiFail($app, $e);
        }
        $app->session->flash('success', "Folder “{$path}” created.");
        return Response::redirect('/folders');
    }

    public static function rename(Request $r, App $app): Response
    {
        $f = self::custom($app, $r->params['id']);
        $path = MailboxService::validPath($r->string('path'));
        if ($path === null) {
            return self::fail($app, 'That is not a valid folder name.');
        }
        if ($path !== $f->path && self::exists($app, $path)) {
            return self::fail($app, "A folder called “{$path}” already exists.");
        }
        try {
            $app->mailboxes()->rename($f->id, $path);
        } catch (ApiException $e) {
            return self::apiFail($app, $e);
        }
        $app->session->flash('success', 'Folder renamed.');
        return Response::redirect('/folders');
    }

    public static function confirmDelete(Request $r, App $app): Response
    {
        return $app->page('folders.delete', 'Delete folder', 'manage-folders', ['f' => self::custom($app, $r->params['id'])]);
    }

    public static function delete(Request $r, App $app): Response
    {
        $f = self::custom($app, $r->params['id']);
        try {
            $app->mailboxes()->delete($f->id);
        } catch (ApiException $e) {
            return self::apiFail($app, $e);
        }
        $app->session->flash('success', "Folder “{$f->path}” deleted.");
        return Response::redirect('/folders');
    }

    /** Only folders the account owns AND that are not system folders can be changed. */
    private static function custom(App $app, string $id): \Webmail\WildDuck\Model\Mailbox
    {
        foreach ($app->folders() as $f) {
            if ($f->id === $id && !$f->isSystem() && strcasecmp($f->path, 'INBOX') !== 0) {
                return $f;
            }
        }
        throw new ApiException('Folder not found.', 404, 'MailboxNotFound');
    }

    private static function exists(App $app, string $path): bool
    {
        foreach ($app->folders() as $f) {
            if (strcasecmp($f->path, $path) === 0) {
                return true;
            }
        }
        return false;
    }

    private static function fail(App $app, string $msg): Response
    {
        $res = $app->page('folders.index', 'Folders', 'manage-folders', ['custom' => array_values(array_filter($app->folders(), fn ($f) => !$f->isSystem())), 'errors' => [$msg]]);
        $res->status = 422;
        return $res;
    }

    private static function apiFail(App $app, ApiException $e): Response
    {
        if ($e->isUnauthorized() || $e->isUnavailable()) {
            throw $e;
        }
        return self::fail($app, $e->userMessage());
    }
}

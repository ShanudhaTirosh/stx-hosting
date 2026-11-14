<?php
declare(strict_types=1);

namespace Webmail\Http\Controllers;

use Webmail\App;
use Webmail\Core\{Request, Response};
use Webmail\Support\Url;
use Webmail\WildDuck\Model\Label;
use Webmail\WildDuck\{ApiException, Ids, LabelService};

final class LabelController
{
    public static function index(Request $r, App $app): Response
    {
        return self::render($app);
    }

    public static function create(Request $r, App $app): Response
    {
        $name = LabelService::validName($r->string('name'));
        if ($name === null) {
            return self::render($app, ['Enter a label name of up to ' . LabelService::MAX_NAME . ' characters (no commas).'], 422);
        }
        foreach ($app->labels() as $l) {
            if ($l->name === $name) {
                return self::render($app, ["A label called “{$name}” already exists."], 422);
            }
        }
        try {
            $app->labelService()->create($name, $r->string('color'));
        } catch (ApiException $e) {
            return self::apiFail($app, $e);
        }
        $app->session->flash('success', "Label “{$name}” created.");
        return Response::redirect('/labels');
    }

    public static function update(Request $r, App $app): Response
    {
        $label = self::find($app, $r->params['id']);
        $name = LabelService::validName($r->string('name'));
        if ($name === null) {
            return self::render($app, ['That is not a valid label name.'], 422);
        }
        foreach ($app->labels() as $l) {
            if ($l->id !== $label->id && $l->name === $name) {
                return self::render($app, ["A label called “{$name}” already exists."], 422);
            }
        }
        try {
            $app->labelService()->update($label->id, $name, $r->string('color'));
        } catch (ApiException $e) {
            return self::apiFail($app, $e);
        }
        $app->session->flash('success', 'Label updated.');
        return Response::redirect('/labels');
    }

    public static function confirmDelete(Request $r, App $app): Response
    {
        return $app->page('labels.delete', 'Delete label', 'manage-labels', ['label' => self::find($app, $r->params['id'])]);
    }

    public static function delete(Request $r, App $app): Response
    {
        $label = self::find($app, $r->params['id']);
        try {
            $app->labelService()->delete($label->id);
        } catch (ApiException $e) {
            return self::apiFail($app, $e);
        }
        $app->session->flash('success', "Label “{$label->name}” deleted. Your messages were not deleted.");
        return Response::redirect('/labels');
    }

    /** Messages with a label, across all folders. */
    public static function show(Request $r, App $app): Response
    {
        $label = self::find($app, $r->params['id']);
        $cursor = Url::cursor($r->string('cursor') ?: null);
        $page = $app->messages()->search('', ['label' => $label->id], $cursor, $r->string('dir') === 'prev');
        return $app->page('mail.list', $label->name, 'label:' . $label->id, [
            'title' => $label->name,
            'summary' => 'Messages labelled “' . $label->name . '”',
            'page' => $page, 'slug' => '', 'base' => '/label/' . $label->id, 'keep' => [], 'unseenOnly' => false, 'isSearch' => true, 'cursor' => $cursor, 'self' => $r->uri(),
            'emptyTitle' => 'No messages with this label', 'emptyText' => 'Select messages in any folder and choose this label to see them here.',
        ], null, '', ['bulk']);
    }

    private static function find(App $app, string $id): Label
    {
        Ids::require($id, 'object');
        foreach ($app->labels() as $l) {
            if ($l->id === $id) {
                return $l;
            }
        }
        throw new ApiException('Label not found.', 404, 'LabelNotFound');
    }

    /** @param list<string> $errors */
    private static function render(App $app, array $errors = [], int $status = 200): Response
    {
        $res = $app->page('labels.index', 'Labels', 'manage-labels', ['errors' => $errors, 'colors' => Label::COLORS]);
        $res->status = $status;
        return $res;
    }

    private static function apiFail(App $app, ApiException $e): Response
    {
        if ($e->isUnauthorized() || $e->isUnavailable()) {
            throw $e;
        }
        return self::render($app, [$e->userMessage()], 422);
    }
}

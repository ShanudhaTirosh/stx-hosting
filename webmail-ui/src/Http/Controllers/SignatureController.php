<?php
declare(strict_types=1);

namespace Webmail\Http\Controllers;

use Webmail\App;
use Webmail\Core\{Request, Response};
use Webmail\Mail\Prefs;
use Webmail\Support\HtmlSanitizer;
use Webmail\WildDuck\ApiException;

final class SignatureController
{
    public static function index(Request $r, App $app): Response
    {
        return self::render($app);
    }

    public static function create(Request $r, App $app): Response
    {
        $s = self::input($r);
        if (is_string($s)) {
            return self::render($app, [$s], 422);
        }
        $makeDefault = $r->string('make_default') === '1';
        return self::save($app, 'Signature added.', static function (Prefs $p) use ($s, $makeDefault): array {
            if (count($p->signatures) >= Prefs::MAX_SIGNATURES) {
                throw new \InvalidArgumentException('You can keep up to ' . Prefs::MAX_SIGNATURES . ' signatures.');
            }
            self::assertUnique($p, $s['name']);
            $id = Prefs::newId();
            return ['signatures' => array_merge($p->signatures, [['id' => $id] + $s]), 'defaultSignature' => ($makeDefault || $p->defaultSignature === null) ? $id : $p->defaultSignature, 'contacts' => $p->contacts];
        });
    }

    public static function update(Request $r, App $app): Response
    {
        $id = $r->params['id'];
        $s = self::input($r);
        if (is_string($s)) {
            return self::render($app, [$s], 422);
        }
        return self::save($app, 'Signature saved.', static function (Prefs $p) use ($id, $s): array {
            $found = false;
            $list = [];
            foreach ($p->signatures as $x) {
                if ($x['id'] === $id) {
                    $found = true;
                    self::assertUnique($p, $s['name'], $id);
                    $x = ['id' => $id] + $s;
                }
                $list[] = $x;
            }
            if (!$found) {
                throw new ApiException('Signature not found.', 404, 'SignatureNotFound');
            }
            return ['signatures' => $list, 'defaultSignature' => $p->defaultSignature, 'contacts' => $p->contacts];
        });
    }

    public static function setDefault(Request $r, App $app): Response
    {
        $id = $r->string('id');
        return self::save($app, $id === '' ? 'New messages will not get a signature.' : 'Default signature updated.', static function (Prefs $p) use ($id): array {
            if ($id !== '' && $p->signature($id) === null) {
                throw new ApiException('Signature not found.', 404, 'SignatureNotFound');
            }
            return ['signatures' => $p->signatures, 'defaultSignature' => $id !== '' ? $id : null, 'contacts' => $p->contacts];
        });
    }

    public static function confirmDelete(Request $r, App $app): Response
    {
        $s = $app->prefs()->signature($r->params['id']);
        if ($s === null) {
            throw new ApiException('Signature not found.', 404, 'SignatureNotFound');
        }
        return $app->page('signatures.delete', 'Delete signature', 'manage-signatures', ['s' => $s]);
    }

    public static function delete(Request $r, App $app): Response
    {
        $id = $r->params['id'];
        return self::save($app, 'Signature deleted.', static function (Prefs $p) use ($id): array {
            if ($p->signature($id) === null) {
                throw new ApiException('Signature not found.', 404, 'SignatureNotFound');
            }
            return ['signatures' => array_values(array_filter($p->signatures, static fn (array $s): bool => $s['id'] !== $id)),
                'defaultSignature' => $p->defaultSignature === $id ? null : $p->defaultSignature, 'contacts' => $p->contacts];
        });
    }

    /** @return array{name:string,html:string}|string */
    private static function input(Request $r): array|string
    {
        $name = Prefs::clean($r->string('name'));
        if ($name === '' || mb_strlen($name) > 60) {
            return 'Give the signature a name of up to 60 characters.';
        }
        $raw = $r->string('html');
        if (trim(strip_tags($raw)) === '' && !str_contains($raw, '<img') && $r->string('text_fallback') !== '') {
            $raw = '<p>' . nl2br(e($r->string('text_fallback')), false) . '</p>';
        }
        if (strlen($raw) > Prefs::MAX_SIGNATURE_BYTES * 2) {
            return 'That signature is too long.';
        }
        $html = HtmlSanitizer::cleanOutgoing($raw);
        if (trim(strip_tags($html)) === '' && !str_contains($html, '<img')) {
            return 'The signature is empty.';
        }
        if (strlen($html) > Prefs::MAX_SIGNATURE_BYTES) {
            return 'That signature is too long (limit ' . (Prefs::MAX_SIGNATURE_BYTES / 1000) . ' KB). Remove embedded images or text.';
        }
        return ['name' => $name, 'html' => $html];
    }

    private static function assertUnique(Prefs $p, string $name, ?string $exceptId = null): void
    {
        foreach ($p->signatures as $s) {
            if ($s['id'] !== $exceptId && strcasecmp($s['name'], $name) === 0) {
                throw new \InvalidArgumentException("A signature called “{$name}” already exists.");
            }
        }
    }

    /** @param callable(Prefs):array $change */
    private static function save(App $app, string $okMessage, callable $change): Response
    {
        try {
            Prefs::update($app->profile(), $change);
            $app->forgetPrefs();
        } catch (\InvalidArgumentException $e) {
            return self::render($app, [$e->getMessage()], 422);
        } catch (ApiException $e) {
            if ($e->isUnauthorized() || $e->isNotFound() || $e->isUnavailable()) {
                throw $e;
            }
            error_log('[webmail] signature save failed status=' . $e->httpStatus . ' code=' . $e->apiCode);
            return self::render($app, ['The mail server did not accept the change, so nothing was saved.' . ($e->httpStatus === 403 ? ' This account may not be allowed to store settings.' : '')], 422);
        }
        $app->session->flash('success', $okMessage);
        return Response::redirect('/signatures');
    }

    /** @param list<string> $errors */
    private static function render(App $app, array $errors = [], int $status = 200): Response
    {
        $p = $app->prefsOrNull();
        $res = $app->page('signatures.index', 'Signatures', 'manage-signatures', [
            'sigs' => $p?->signatures ?? [], 'default' => $p?->defaultSignature, 'errors' => $errors, 'unavailable' => $p === null, 'max' => Prefs::MAX_SIGNATURES,
        ], null, '', ['editor']);
        $res->status = $status;
        return $res;
    }
}

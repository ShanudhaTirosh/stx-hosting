<?php
declare(strict_types=1);

namespace Webmail\Http\Controllers;

use Webmail\App;
use Webmail\Core\{Request, Response};
use Webmail\Mail\Prefs;
use Webmail\Support\Url;
use Webmail\WildDuck\{ApiException, Ids};

final class ContactController
{
    private const PER_PAGE = 50;

    public static function index(Request $r, App $app): Response
    {
        return self::render($app, $r);
    }

    public static function create(Request $r, App $app): Response
    {
        $c = self::input($r);
        if (is_string($c)) {
            return self::render($app, $r, [$c], 422);
        }
        return self::save($app, $r, 'Contact added.', static function (Prefs $p) use ($c): array {
            self::assertFree($p, $c['email']);
            if (count($p->contacts) >= Prefs::MAX_CONTACTS) {
                throw new \InvalidArgumentException('You have reached the limit of ' . Prefs::MAX_CONTACTS . ' contacts.');
            }
            return ['signatures' => $p->signatures, 'defaultSignature' => $p->defaultSignature, 'contacts' => array_merge($p->contacts, [['id' => Prefs::newId()] + $c])];
        });
    }

    public static function update(Request $r, App $app): Response
    {
        $id = $r->params['id'];
        $c = self::input($r);
        if (is_string($c)) {
            return self::render($app, $r, [$c], 422);
        }
        return self::save($app, $r, 'Contact saved.', static function (Prefs $p) use ($id, $c): array {
            $found = false;
            $list = [];
            foreach ($p->contacts as $x) {
                if ($x['id'] === $id) {
                    $found = true;
                    self::assertFree($p, $c['email'], $id);
                    $x = ['id' => $id] + $c;
                }
                $list[] = $x;
            }
            if (!$found) {
                throw new ApiException('Contact not found.', 404, 'ContactNotFound');
            }
            return ['signatures' => $p->signatures, 'defaultSignature' => $p->defaultSignature, 'contacts' => $list];
        });
    }

    public static function confirmDelete(Request $r, App $app): Response
    {
        $p = self::prefs($app);
        foreach ($p->contacts as $c) {
            if ($c['id'] === $r->params['id']) {
                return $app->page('contacts.delete', 'Delete contact', 'manage-contacts', ['c' => $c]);
            }
        }
        throw new ApiException('Contact not found.', 404, 'ContactNotFound');
    }

    public static function delete(Request $r, App $app): Response
    {
        $id = $r->params['id'];
        return self::save($app, $r, 'Contact deleted.', static function (Prefs $p) use ($id): array {
            $list = array_values(array_filter($p->contacts, static fn (array $c): bool => $c['id'] !== $id));
            if (count($list) === count($p->contacts)) {
                throw new ApiException('Contact not found.', 404, 'ContactNotFound');
            }
            return ['signatures' => $p->signatures, 'defaultSignature' => $p->defaultSignature, 'contacts' => $list];
        });
    }

    /** One-click "add sender to contacts" from a message. */
    public static function quick(Request $r, App $app): Response
    {
        $back = Url::safeReturn($r->string('return') ?: null, '/contacts');
        $c = self::input($r);
        if (is_string($c)) {
            $app->session->flash('error', $c);
            return Response::redirect($back);
        }
        try {
            Prefs::update($app->profile(), static function (Prefs $p) use ($c): array {
                self::assertFree($p, $c['email']);
                return ['signatures' => $p->signatures, 'defaultSignature' => $p->defaultSignature, 'contacts' => array_merge($p->contacts, [['id' => Prefs::newId()] + $c])];
            });
            $app->forgetPrefs();
            $app->session->flash('success', 'Added ' . ($c['name'] !== '' ? $c['name'] : $c['email']) . ' to your contacts.');
        } catch (\InvalidArgumentException $e) {
            $app->session->flash('info', $e->getMessage());
        } catch (ApiException $e) {
            if ($e->isUnauthorized()) {
                throw $e;
            }
            $app->session->flash('error', self::storeError($e));
        }
        return Response::redirect($back);
    }

    /** JSON typing suggestions: your contacts first, then addresses WildDuck has seen. */
    public static function suggest(Request $r, App $app): Response
    {
        $q = mb_substr(trim($r->string('q')), 0, 100);
        if ($q === '') {
            return Response::json(['results' => []]);
        }
        $register = [];
        try {
            $register = $app->profile()->addressRegister($q, 8);
        } catch (ApiException $e) {
            if ($e->isUnauthorized()) {
                throw $e;
            }
        }
        $prefs = $app->prefsOrNull();
        $results = $prefs !== null ? $prefs->suggest($q, $register) : array_map(static fn (array $a): array => ['name' => $a['name'], 'email' => $a['address']], $register);
        return Response::json(['results' => $results])->withHeader('Cache-Control', 'private, no-store');
    }

    /** @return array{name:string,email:string,note:string}|string error message */
    private static function input(Request $r): array|string
    {
        $email = trim($r->string('email'));
        if ($email === '' || strlen($email) > 254 || preg_match('/[\s<>,;"]/', $email) === 1 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'Enter a valid email address.';
        }
        $name = Prefs::clean($r->string('name'));
        $note = Prefs::clean($r->string('note'));
        if (mb_strlen($name) > 100 || mb_strlen($note) > 200) {
            return 'The name may have up to 100 characters and the note up to 200.';
        }
        return ['name' => $name, 'email' => $email, 'note' => $note];
    }

    private static function assertFree(Prefs $p, string $email, ?string $exceptId = null): void
    {
        foreach ($p->contacts as $c) {
            if ($c['id'] !== $exceptId && strcasecmp($c['email'], $email) === 0) {
                throw new \InvalidArgumentException("$email is already in your contacts.");
            }
        }
    }

    /** @param callable(Prefs):array $change */
    private static function save(App $app, Request $r, string $okMessage, callable $change): Response
    {
        try {
            Prefs::update($app->profile(), $change);
            $app->forgetPrefs();
        } catch (\InvalidArgumentException $e) {
            return self::render($app, $r, [$e->getMessage()], 422);
        } catch (ApiException $e) {
            if ($e->isUnauthorized() || $e->isNotFound() || $e->isUnavailable()) {
                throw $e;
            }
            return self::render($app, $r, [self::storeError($e)], 422);
        }
        $app->session->flash('success', $okMessage);
        return Response::redirect('/contacts');
    }

    private static function storeError(ApiException $e): string
    {
        error_log('[webmail] contacts save failed status=' . $e->httpStatus . ' code=' . $e->apiCode);
        return 'The mail server did not accept the change, so nothing was saved. ' . ($e->httpStatus === 403 ? 'This account may not be allowed to store settings.' : 'Please try again.');
    }

    private static function prefs(App $app): Prefs
    {
        return $app->prefs();
    }

    /** @param list<string> $errors */
    private static function render(App $app, Request $r, array $errors = [], int $status = 200): Response
    {
        $q = mb_substr(trim($r->string('q')), 0, 100);
        $all = $app->prefsOrNull();
        $contacts = $all?->contacts ?? [];
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $contacts = array_values(array_filter($contacts, static fn (array $c): bool => str_contains(mb_strtolower($c['name'] . ' ' . $c['email'] . ' ' . $c['note']), $needle)));
        }
        usort($contacts, static fn (array $a, array $b): int => strcasecmp($a['name'] !== '' ? $a['name'] : $a['email'], $b['name'] !== '' ? $b['name'] : $b['email']));
        $total = count($contacts);
        $page = max(1, min((int) $r->string('page', '1'), max(1, (int) ceil($total / self::PER_PAGE))));
        $res = $app->page('contacts.index', 'Contacts', 'manage-contacts', [
            'contacts' => array_slice($contacts, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / self::PER_PAGE)), 'q' => $q,
            'errors' => $errors, 'unavailable' => $all === null, 'old' => $status === 422 ? $r->post : [],
        ], null, '');
        $res->status = $status;
        return $res;
    }
}

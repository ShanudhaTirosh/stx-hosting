<?php
declare(strict_types=1);

namespace Webmail\Http\Controllers;

use Webmail\App;
use Webmail\Core\{Request, Response};
use Webmail\Mail\Prefs;
use Webmail\WildDuck\{ApiException, Ids};

final class SettingsController
{
    private const PW_WINDOW = 900;
    private const PW_MAX = 5;

    public static function root(Request $r, App $app): Response
    {
        return Response::redirect('/settings/profile');
    }

    // ---- profile + password -------------------------------------------------------------

    public static function profile(Request $r, App $app): Response
    {
        return self::profilePage($app);
    }

    public static function saveName(Request $r, App $app): Response
    {
        $name = Prefs::clean($r->string('name'));
        if (mb_strlen($name) > 100) {
            return self::profilePage($app, ['nameErrors' => ['The name may have up to 100 characters.']], 422);
        }
        try {
            $app->account()->setName($name);
        } catch (ApiException $e) {
            return self::fail($app, $e, 'nameErrors', fn (array $x, int $st = 422) => self::profilePage($app, $x, $st));
        }
        $app->forgetPrefs();
        $app->session->flash('success', 'Your name was saved.');
        return Response::redirect('/settings/profile');
    }

    public static function changePassword(Request $r, App $app): Response
    {
        $key = 'pw:' . $app->userId();
        if ($app->limiter->tooMany($key, self::PW_MAX, self::PW_WINDOW)) {
            $wait = $app->limiter->retryAfter($key, self::PW_WINDOW);
            return self::profilePage($app, ['pwErrors' => ['Too many attempts. Try again in ' . (int) ceil($wait / 60) . ' minute(s).']], 429)->withHeader('Retry-After', (string) $wait);
        }
        $cur = $r->string('current');
        $new = $r->string('new');
        $err = [];
        if ($cur === '' || strlen($cur) > 256) {
            $err[] = 'Enter your current password.';
        }
        if (strlen($new) < 8 || strlen($new) > 256) {
            $err[] = 'The new password must be 8 to 256 characters long.';
        } elseif ($new !== $r->string('confirm')) {
            $err[] = 'The new password and its confirmation do not match.';
        } elseif ($new === $cur) {
            $err[] = 'The new password must be different from the current one.';
        }
        if ($err) {
            return self::profilePage($app, ['pwErrors' => $err], 422);
        }
        try {
            $app->account()->changePassword($cur, $new);
        } catch (ApiException $e) {
            if ($e->isUnauthorized() || $e->isUnavailable()) {
                throw $e;
            }
            $app->limiter->hit($key, self::PW_WINDOW);
            $wrong = in_array($e->httpStatus, [400, 403], true) && preg_match('/password|credential|authenticat/i', $e->apiCode . ' ' . $e->getMessage()) === 1;
            return self::profilePage($app, ['pwErrors' => [$wrong ? 'The current password is incorrect.' : $e->userMessage()]], 422);
        }
        $app->limiter->clear($key);
        $app->session->regenerate();
        $app->session->flash('success', 'Your password was changed.');
        return Response::redirect('/settings/profile');
    }

    /** @param array<string,mixed> $extra */
    private static function profilePage(App $app, array $extra = [], int $status = 200): Response
    {
        $info = $app->profileInfoOrNull();
        $res = $app->page('settings.profile', 'Profile', 'settings-profile', $extra + [
            'name' => $info['name'] ?? '', 'unavailable' => $info === null, 'address' => $app->auth->user()['address'] ?? '', 'nameErrors' => [], 'pwErrors' => [],
        ]);
        $res->status = $status;
        return $res;
    }

    // ---- send-from addresses ------------------------------------------------------------

    public static function identities(Request $r, App $app): Response
    {
        return self::identitiesPage($app);
    }

    public static function renameIdentity(Request $r, App $app): Response
    {
        $id = Ids::require($r->params['id'], 'object');
        $name = Prefs::clean($r->string('name'));
        if (mb_strlen($name) > 100) {
            return self::identitiesPage($app, ['The name may have up to 100 characters.'], 422);
        }
        $owned = false;
        foreach ($app->account()->identities() as $i) {
            $owned = $owned || $i->id === $id;
        }
        if (!$owned) {
            throw new ApiException('Address not found.', 404, 'AddressNotFound');
        }
        try {
            $app->account()->renameIdentity($id, $name);
        } catch (ApiException $e) {
            return self::fail($app, $e, null, fn (array $x, int $st = 422) => self::identitiesPage($app, $x, $st));
        }
        $app->forgetPrefs();
        $app->session->flash('success', 'Sender name saved.');
        return Response::redirect('/settings/identities');
    }

    /** @param list<string> $errors */
    private static function identitiesPage(App $app, array $errors = [], int $status = 200): Response
    {
        $list = [];
        $unavailable = false;
        try {
            $list = $app->account()->identities();
        } catch (ApiException $e) {
            if ($e->isUnauthorized()) {
                throw $e;
            }
            $unavailable = true;
        }
        $res = $app->page('settings.identities', 'Sender addresses', 'settings-identities', ['identities' => $list, 'errors' => $errors, 'unavailable' => $unavailable]);
        $res->status = $status;
        return $res;
    }

    // ---- auto-reply ---------------------------------------------------------------------

    public static function autoreply(Request $r, App $app): Response
    {
        return self::autoreplyPage($app, null);
    }

    public static function saveAutoreply(Request $r, App $app): Response
    {
        $on = $r->string('status') === '1';
        $a = ['status' => $on, 'name' => Prefs::clean($r->string('name')), 'subject' => self::oneLine($r->string('subject')), 'text' => str_replace("\r\n", "\n", trim($r->string('text'))), 'start' => null, 'end' => null];
        $err = [];
        if (mb_strlen($a['name']) > 100) {
            $err[] = 'The sender name may have up to 100 characters.';
        }
        if (mb_strlen($a['subject']) > 200) {
            $err[] = 'The subject may have up to 200 characters.';
        }
        if (mb_strlen($a['text']) > 5000) {
            $err[] = 'The message may have up to 5000 characters.';
        }
        if ($on && $a['text'] === '') {
            $err[] = 'Write the message that will be sent automatically.';
        }
        $start = self::day($r->string('start'));
        $end = self::day($r->string('end'));
        if ($start === false || $end === false) {
            $err[] = 'Dates must look like 2026-12-24.';
        } else {
            if ($start !== null && $end !== null && $end < $start) {
                $err[] = 'The end date must not be before the start date.';
            }
            $a['start'] = $start?->format('Y-m-d\T00:00:00.000\Z');
            $a['end'] = $end?->format('Y-m-d\T23:59:59.999\Z');
        }
        if ($err) {
            return self::autoreplyPage($app, $a, $err, 422, $r->string('start'), $r->string('end'));
        }
        try {
            $app->account()->saveAutoreply($a);
        } catch (ApiException $e) {
            return self::fail($app, $e, null, fn (array $x, int $st = 422) => self::autoreplyPage($app, $a, $x, $st, $r->string('start'), $r->string('end')));
        }
        $app->forgetPrefs();
        $app->session->flash('success', $on ? 'Auto-reply is on.' : 'Auto-reply is off.');
        return Response::redirect('/settings/autoreply');
    }

    /** @param ?array{status:bool,name:string,subject:string,text:string,start:?string,end:?string} $a @param list<string> $errors */
    private static function autoreplyPage(App $app, ?array $a, array $errors = [], int $status = 200, ?string $startRaw = null, ?string $endRaw = null): Response
    {
        $unavailable = false;
        if ($a === null) {
            try {
                $a = $app->account()->autoreply();
            } catch (ApiException $e) {
                if ($e->isUnauthorized()) {
                    throw $e;
                }
                $unavailable = true;
                $a = ['status' => false, 'name' => '', 'subject' => '', 'text' => '', 'start' => null, 'end' => null];
            }
        }
        $res = $app->page('settings.autoreply', 'Auto-reply', 'settings-autoreply', [
            'a' => $a, 'errors' => $errors, 'unavailable' => $unavailable,
            'startDay' => $startRaw ?? substr((string) $a['start'], 0, 10), 'endDay' => $endRaw ?? substr((string) $a['end'], 0, 10),
        ]);
        $res->status = $status;
        return $res;
    }

    // ---- shared ---------------------------------------------------------------------------

    /** Show a refusal from WildDuck on the same page; infrastructure/session errors keep their normal handling. */
    private static function fail(App $app, ApiException $e, ?string $key, callable $render): Response
    {
        if ($e->isUnauthorized() || $e->isUnavailable() || $e->isNotFound()) {
            throw $e;
        }
        error_log('[webmail] settings save refused status=' . $e->httpStatus . ' code=' . $e->apiCode);
        $msg = 'The mail server did not accept the change, so nothing was saved.' . ($e->httpStatus === 403 ? ' This account may not be allowed to change this setting.' : '');
        return $render($key === null ? [$msg] : [$key => [$msg]], 422);
    }

    private static function oneLine(string $s): string
    {
        return trim(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $s) ?? '');
    }

    /** @return \DateTimeImmutable|null|false  null = empty, false = invalid */
    private static function day(string $v): \DateTimeImmutable|null|false
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v, new \DateTimeZone('UTC'));
        return $d !== false && $d->format('Y-m-d') === $v && (int) $d->format('Y') >= 2000 && (int) $d->format('Y') <= 2100 ? $d : false;
    }
}

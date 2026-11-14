<?php
declare(strict_types=1);

namespace Webmail\Http\Controllers;

use Webmail\App;
use Webmail\Core\{Auth, Request, Response};
use Webmail\WildDuck\{ApiException, AuthFailedException, TwoFactorRequiredException};

final class AuthController
{
    private const WINDOW = 900; // 15 min
    private const TWOFA_WINDOW = 300; // 5 min
    private const TWOFA_MAX = 5;

    public static function showLogin(Request $r, App $app): Response
    {
        if ($app->auth->check()) {
            return Response::redirect('/mail/inbox');
        }
        $app->auth->clearTwoFactor(); // coming back to the password form abandons a half-finished 2FA sign-in
        $initialUsername = trim($r->string('email') ?: $r->string('username'));
        return self::form($app, $initialUsername, null);
    }

    /** GET /accounts/add: the same sign-in form, but the result is ADDED to this session instead of replacing it. */
    public static function showAdd(Request $r, App $app): Response
    {
        $app->auth->clearTwoFactor();
        if (count($app->auth->accounts()) >= Auth::MAX_ACCOUNTS) {
            $app->session->flash('error', 'You can be signed in to up to ' . Auth::MAX_ACCOUNTS . ' accounts at once. Sign out of one first.');
            return Response::redirect('/mail/inbox');
        }
        $initialUsername = trim($r->string('email') ?: $r->string('username'));
        return self::form($app, $initialUsername, null, 200, true);
    }

    public static function login(Request $r, App $app): Response
    {
        $adding = str_starts_with($r->path, '/accounts/');
        $username = trim($r->string('username'));
        $password = $r->string('password');
        if ($username === '' || $password === '' || strlen($username) > 254 || strlen($password) > 256) {
            return self::form($app, $username, 'Enter your email address and password.', 422, $adding);
        }

        $ipKey = 'login:ip:' . $r->ip;
        $userKey = 'login:user:' . strtolower($username);
        if ($app->limiter->tooMany($ipKey, 30, self::WINDOW) || $app->limiter->tooMany($userKey, 8, self::WINDOW)) {
            $wait = max($app->limiter->retryAfter($ipKey, self::WINDOW), $app->limiter->retryAfter($userKey, self::WINDOW));
            return self::form($app, $username, 'Too many sign-in attempts. Try again in ' . (int) ceil($wait / 60) . ' minute(s).', 429, $adding)
                ->withHeader('Retry-After', (string) $wait);
        }

        try {
            $sess = substr(hash('sha256', session_id() . 'wm'), 0, 32);
            $acct = $app->authService()->login($username, $password, $r->ip, $sess);
        } catch (AuthFailedException) {
            $app->limiter->hit($ipKey, self::WINDOW);
            $app->limiter->hit($userKey, self::WINDOW);
            return self::form($app, $username, 'Incorrect email address or password.', 401, $adding);
        } catch (TwoFactorRequiredException) {
            return self::form($app, $username, 'This account is protected with a security key or passkey, which this webmail cannot use yet. Ask your administrator for an authenticator-app code, or sign in with another client.', 403, $adding);
        } catch (ApiException $e) {
            if ($e->isUnavailable()) {
                error_log('[webmail] login: WildDuck unavailable (' . $e->apiCode . ')');
                return self::form($app, $username, 'The mail server is temporarily unavailable. Please try again shortly.', 503, $adding);
            }
            return self::form($app, $username, 'Sign-in failed. Please try again.', 400, $adding);
        }

        $app->limiter->clear($userKey);
        if ($acct['twoFactor'] !== null) {
            $app->auth->startTwoFactor($acct['userId'], $acct['address'], $acct['twoFactor']['nonce'], null, $adding);
            return Response::redirect('/login/2fa');
        }
        if ($acct['token'] === null) {
            return self::form($app, $username, 'Sign-in failed. Please try again.', 502, $adding);
        }
        return self::complete($app, $acct['userId'], $acct['address'], $acct['token'], $adding);
    }

    public static function showTwoFactor(Request $r, App $app): Response
    {
        $p = $app->auth->pendingTwoFactor();
        if ($p === null) {
            if ($app->auth->check()) {
                return Response::redirect('/mail/inbox');
            }
            $app->session->flash('error', 'Your sign-in expired. Please start again.');
            return Response::redirect('/login');
        }
        return self::twoFactorForm($app, $p['address'], null);
    }

    public static function verifyTwoFactor(Request $r, App $app): Response
    {
        $p = $app->auth->pendingTwoFactor();
        if ($p === null) {
            $app->session->flash('error', 'Your sign-in expired. Please start again.');
            return Response::redirect($app->auth->check() ? '/mail/inbox' : '/login');
        }
        $userKey = '2fa:user:' . $p['userId'];
        $ipKey = '2fa:ip:' . $r->ip;
        if ($app->limiter->tooMany($userKey, self::TWOFA_MAX, self::TWOFA_WINDOW) || $app->limiter->tooMany($ipKey, 30, self::WINDOW)) {
            $wait = max($app->limiter->retryAfter($userKey, self::TWOFA_WINDOW), $app->limiter->retryAfter($ipKey, self::WINDOW));
            $app->auth->clearTwoFactor();
            return self::form($app, '', 'Too many incorrect codes. Try again in ' . (int) ceil($wait / 60) . ' minute(s).', 429, $p['adding'])->withHeader('Retry-After', (string) $wait);
        }
        $code = preg_replace('/\s+/', '', $r->string('code')) ?? '';
        if (preg_match('/^[0-9]{6}$/', $code) !== 1) {
            return self::twoFactorForm($app, $p['address'], 'Enter the 6-digit code from your authenticator app.', 422);
        }
        try {
            $sess = substr(hash('sha256', session_id() . 'wm'), 0, 32);
            $token = $app->authService()->verifyTotp($p['userId'], $code, $p['nonce'], $r->ip, $sess);
        } catch (AuthFailedException) {
            $app->limiter->hit($userKey, self::TWOFA_WINDOW);
            $app->limiter->hit($ipKey, self::WINDOW);
            if ($app->auth->twoFactorFailed() >= self::TWOFA_MAX) {
                $app->auth->clearTwoFactor();
                $app->session->flash('error', 'Too many incorrect codes. Please sign in again.');
                return Response::redirect('/login');
            }
            return self::twoFactorForm($app, $p['address'], 'That code was not accepted. Check your authenticator app and try again; codes change every 30 seconds.', 401);
        } catch (ApiException $e) {
            if ($e->isUnavailable()) {
                error_log('[webmail] 2fa: WildDuck unavailable (' . $e->apiCode . ')');
                return self::twoFactorForm($app, $p['address'], 'The mail server is temporarily unavailable. Please try again shortly.', 503);
            }
            return self::twoFactorForm($app, $p['address'], 'Sign-in failed. Please try again.', 400);
        }
        $app->limiter->clear($userKey);
        return self::complete($app, $p['userId'], $p['address'], $token, $p['adding']);
    }

    /** Finish a sign-in: store the account (replacing nothing: it is added to the session) and go to the inbox. */
    private static function complete(App $app, string $userId, string $address, string $token, bool $adding): Response
    {
        if ($adding && !$app->auth->canAdd($userId)) {
            try {
                $app->clientFor($token)->withTimeout(4)->json('DELETE', '/authenticate'); // do not leave an unused token behind
            } catch (ApiException) {
            }
            $app->auth->clearTwoFactor();
            $app->session->flash('error', 'You can be signed in to up to ' . Auth::MAX_ACCOUNTS . ' accounts at once. Sign out of one first.');
            return Response::redirect('/mail/inbox');
        }
        $known = false;
        foreach ($app->auth->accounts() as $a) {
            $known = $known || strcasecmp($a['address'], $address) === 0;
        }
        $app->auth->login($userId, $address, $token);
        if ($adding) {
            $app->session->flash('success', $known ? "Refreshed the sign-in for $address." : "Added $address. You are now using it.");
        }
        return Response::redirect('/mail/inbox');
    }

    // ---- several accounts in one session -------------------------------------------------

    public static function switchAccount(Request $r, App $app): Response
    {
        $id = $r->string('id');
        if (!$app->auth->switchTo($id)) {
            $app->session->flash('error', 'That account is not signed in here.');
            return Response::redirect('/mail/inbox');
        }
        $app->forgetSidebar();
        $app->forgetPrefs();
        $app->session->flash('success', 'Now using ' . ($app->auth->user()['address'] ?? 'the account') . '.');
        return Response::redirect('/mail/inbox');
    }

    /** Sign out of ONE account (the others stay signed in). */
    public static function signOutAccount(Request $r, App $app): Response
    {
        $id = $r->params['id'] ?? '';
        $acct = $app->auth->account($id);
        if ($acct === null) {
            $app->session->flash('error', 'That account is not signed in here.');
            return Response::redirect('/mail/inbox');
        }
        $app->revokeAccountToken($id);
        $left = $app->auth->remove($id);
        $app->forgetSidebar();
        $app->forgetPrefs();
        if (!$left) {
            $app->auth->logout();
            $app->session->start();
            $app->session->flash('success', 'You have been signed out.');
            return Response::redirect('/login');
        }
        $app->session->flash('success', 'Signed out of ' . $acct['address'] . '. You are now using ' . ($app->auth->user()['address'] ?? 'another account') . '.');
        return Response::redirect('/mail/inbox');
    }

    public static function logoutAll(Request $r, App $app): Response
    {
        foreach ($app->auth->accounts() as $a) {
            $app->revokeAccountToken($a['id']);
        }
        $app->auth->logout();
        $app->session->start();
        $app->session->flash('success', 'You have been signed out of all accounts.');
        return Response::redirect('/login');
    }

    private static function twoFactorForm(App $app, string $address, ?string $error, int $status = 200): Response
    {
        return Response::html($app->view->render('login2fa', ['csrf' => $app->csrf->token(), 'address' => $address, 'error' => $error, 'adding' => $app->auth->pendingTwoFactor()['adding'] ?? false, 'flash' => $app->session->pullFlash()]), $status);
    }

    /** Sign out of the active account; other signed-in accounts (if any) stay signed in. */
    public static function logout(Request $r, App $app): Response
    {
        $id = $app->auth->activeId();
        return $id === null ? self::logoutAll($r, $app) : self::signOutAccount($r->withParams(['id' => $id]), $app);
    }

    private static function form(App $app, string $username, ?string $error, int $status = 200, bool $adding = false): Response
    {
        return Response::html($app->view->render('login', [
            'adding' => $adding, 'action' => $adding ? '/accounts/add' : '/login',
            'csrf' => $app->csrf->token(),
            'username' => $username,
            'error' => $error,
            'flash' => $app->session->pullFlash(),
        ]), $status);
    }
}

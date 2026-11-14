<?php
declare(strict_types=1);

namespace Webmail;

use Webmail\Core\{Auth, Crypto, Csrf, Env, RateLimiter, Request, Response, Router, Session, View};
use Webmail\WildDuck\Model\{Identity, Label, Mailbox};
use Webmail\Mail\Prefs;
use Webmail\WildDuck\{AccountService, ApiException, AuthService, Client, FilterService, LabelService, MailboxService, MessageService, ProfileService, SubmitService};

final class App
{
    public readonly Session $session;
    public readonly Csrf $csrf;
    public readonly Auth $auth;
    public readonly View $view;
    public readonly Router $router;
    public readonly RateLimiter $limiter;
    private Client $serviceClient;
    /** @var array{f:list<Mailbox>,l:list<Label>}|null per-request memo of the sidebar data */
    private ?array $side = null;
    private const SIDEBAR_TTL = 10;

    public function __construct(public readonly string $root)
    {
        Env::load($root . '/.env');
        $storage = rtrim((string) Env::get('STORAGE_PATH', $root . '/storage'), '/');
        foreach (['sessions', 'ratelimit'] as $d) {
            if (!is_dir("$storage/$d")) {
                @mkdir("$storage/$d", 0700, true);
            }
        }
        $this->session = new Session(
            $storage . '/sessions',
            Env::int('SESSION_LIFETIME_MIN', 480),
            Env::bool('SESSION_COOKIE_SECURE', true),
        );
        if (random_int(1, 100) === 1) { // ~1% of requests tidy up expired files
            $life = Env::int('SESSION_LIFETIME_MIN', 480) * 60;
            \Webmail\Support\Storage::prune($storage . '/sessions', $life + 600);
            \Webmail\Support\Storage::prune($storage . '/ratelimit', 3600);
        }
        $this->csrf = new Csrf($this->session);
        $this->auth = new Auth($this->session, new Crypto((string) Env::get('APP_KEY', '')));
        $this->view = new View($root . '/views');
        $this->router = new Router();
        $this->limiter = new RateLimiter($storage . '/ratelimit');
        $this->serviceClient = new Client(
            (string) Env::get('WILDDUCK_API_URL', ''),
            (string) Env::get('WILDDUCK_API_TOKEN', ''),
            Env::int('WILDDUCK_TIMEOUT', 15),
            Env::bool('WILDDUCK_VERIFY_TLS', true),
        );
        (require $root . '/config/routes.php')($this->router);
    }

    public function authService(): AuthService
    {
        return new AuthService($this->serviceClient);
    }

    /** Client acting as the signed-in user (user-scoped token; falls back to the service token). */
    public function userClient(): Client
    {
        $t = $this->auth->token();
        return $t !== null ? $this->serviceClient->withToken($t) : $this->serviceClient;
    }

    /** A client acting as the given token (used for other signed-in accounts). */
    public function clientFor(string $token): Client
    {
        return $this->serviceClient->withToken($token);
    }

    /** Best-effort revocation of one signed-in account's WildDuck token (the session entry is removed separately). */
    public function revokeAccountToken(string $accountId): void
    {
        $t = $this->auth->tokenFor($accountId);
        if ($t === null) {
            return;
        }
        try {
            $this->clientFor($t)->withTimeout(4)->json('DELETE', '/authenticate');
        } catch (ApiException) {
            // the session entry is removed regardless
        }
    }

    public function userId(): string
    {
        $u = $this->auth->user();
        if ($u === null) {
            throw new ApiException('Not signed in.', 401, 'NotSignedIn');
        }
        return $u['id'];
    }

    public function mailboxes(): MailboxService
    {
        return new MailboxService($this->userClient(), $this->userId());
    }

    public function messages(): MessageService
    {
        return new MessageService($this->userClient(), $this->userId(), Env::int('MAX_ATTACHMENT_MB', 20) * 1048576);
    }

    public function labelService(): LabelService
    {
        return new LabelService($this->userClient(), $this->userId());
    }

    /**
     * Folders for the signed-in account. Cached briefly in the session (and per request) so pages do not re-query
     * WildDuck every time; any state-changing request clears the cache.
     *
     * @return list<Mailbox>
     */
    public function folders(): array
    {
        return $this->sidebar()['f'];
    }

    /** @return list<Label> */
    public function labels(): array
    {
        return $this->sidebar()['l'];
    }

    public function forgetSidebar(): void
    {
        $this->side = null;
        $this->session->forget('wm_side');
    }

    /** @return array{f:list<Mailbox>,l:list<Label>} */
    private function sidebar(): array
    {
        if ($this->side !== null) {
            return $this->side;
        }
        $c = $this->session->get('wm_side');
        if (is_array($c) && ($c['t'] ?? 0) > time() - self::SIDEBAR_TTL && ($c['u'] ?? '') === $this->userId()) {
            return $this->side = [
                'f' => array_map(static fn (array $d): Mailbox => Mailbox::fromArray($d), $c['f']),
                'l' => array_map(static fn (array $d): Label => Label::fromArray(['metaData' => ['color' => $d['color']]] + $d), $c['l']),
            ];
        }
        $folders = $this->mailboxes()->all();
        $labels = [];
        try {
            $labels = $this->labelService()->all(true);
        } catch (ApiException $e) {
            if ($e->isUnauthorized()) {
                throw $e;
            }
            error_log('[webmail] labels unavailable status=' . $e->httpStatus); // older/limited servers: keep mail usable
        }
        $this->session->put('wm_side', [
            't' => time(), 'u' => $this->userId(),
            'f' => array_map(static fn (Mailbox $m): array => ['id' => $m->id, 'name' => $m->name, 'path' => $m->path, 'specialUse' => $m->specialUse, 'total' => $m->total, 'unseen' => $m->unseen], $folders),
            'l' => array_map(static fn (Label $l): array => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color, 'total' => $l->total, 'unseen' => $l->unseen], $labels),
        ]);
        return $this->side = ['f' => $folders, 'l' => $labels];
    }

    public function profile(): ProfileService
    {
        return new ProfileService($this->userClient(), $this->userId());
    }

    public function filters(): FilterService
    {
        return new FilterService($this->userClient(), $this->userId());
    }

    public function account(): AccountService
    {
        return new AccountService($this->userClient(), $this->userId());
    }

    /**
     * Display name, metaData and the auto-reply flag in one profile call, cached in the session for 30 s
     * (every write goes through a non-GET request, which clears the cache).
     *
     * @return array{name:string,meta:array<string,mixed>,autoreply:bool}
     */
    public function profileInfo(): array
    {
        $c = $this->session->get('wm_prefs');
        if (is_array($c) && ($c['t'] ?? 0) > time() - 30 && ($c['u'] ?? '') === $this->userId() && is_array($c['m'] ?? null)) {
            return ['name' => (string) ($c['n'] ?? ''), 'meta' => $c['m'], 'autoreply' => (bool) ($c['a'] ?? false)];
        }
        $d = $this->profile()->details();
        $this->session->put('wm_prefs', ['t' => time(), 'u' => $this->userId(), 'm' => $d['meta'], 'n' => $d['name'], 'a' => $d['autoreply']]);
        return $d;
    }

    /** Like profileInfo(), but never throws (except for an expired session) and does not retry a failing server on every page. */
    public function profileInfoOrNull(): ?array
    {
        if ((int) $this->session->get('wm_prefs_fail', 0) > time() - 20) {
            return null;
        }
        try {
            return $this->profileInfo();
        } catch (ApiException $e) {
            if ($e->isUnauthorized()) {
                throw $e;
            }
            $this->session->put('wm_prefs_fail', time());
            error_log('[webmail] profile unavailable status=' . $e->httpStatus . ' code=' . $e->apiCode);
            return null;
        }
    }

    /** Signatures + contacts. */
    public function prefs(): Prefs
    {
        return Prefs::fromMeta($this->profileInfo()['meta']);
    }

    /**
     * Send-from addresses, cached for 60 s. Returns [] when the server cannot list them (the account's main address is then used).
     *
     * @return list<Identity>
     */
    public function identities(): array
    {
        $c = $this->session->get('wm_idents');
        if (is_array($c) && ($c['t'] ?? 0) > time() - 60 && ($c['u'] ?? '') === $this->userId()) {
            return array_map(static fn (array $d): Identity => Identity::fromArray($d), $c['i']);
        }
        try {
            $list = $this->account()->identities();
        } catch (ApiException $e) {
            if ($e->isUnauthorized()) {
                throw $e;
            }
            error_log('[webmail] addresses unavailable status=' . $e->httpStatus);
            return [];
        }
        $this->session->put('wm_idents', ['t' => time(), 'u' => $this->userId(), 'i' => array_map(static fn (Identity $i): array => ['id' => $i->id, 'address' => $i->address, 'name' => $i->name, 'main' => $i->main], $list)]);
        return $list;
    }

    /** Like prefs(), but a failing/old server degrades to "no signatures/contacts" instead of breaking the page. */
    public function prefsOrNull(): ?Prefs
    {
        $info = $this->profileInfoOrNull();
        return $info === null ? null : Prefs::fromMeta($info['meta']);
    }

    public function forgetPrefs(): void
    {
        $this->session->forget('wm_prefs');
        $this->session->forget('wm_prefs_fail');
        $this->session->forget('wm_idents');
    }

    public function submitter(): SubmitService
    {
        return new SubmitService($this->userClient(), $this->userId());
    }

    /** Render a page inside the app shell. @param array<string,mixed> $data */
    public function page(string $view, string $title, string $active, array $data = [], ?array $folders = null, string $q = '', array $scripts = []): Response
    {
        $labels = $this->labels();
        $colors = [];
        foreach ($labels as $l) {
            $colors[$l->name] = $l->color;
        }
        $content = $this->view->render($view, $data + ['csrf' => $this->csrf->token(), 'address' => $this->auth->user()['address'] ?? '', 'labels' => $labels, 'labelColors' => $colors, 'allFolders' => $folders ?? $this->folders()]);
        return Response::html($this->view->render('layout', [
            'title' => $title,
            'content' => $content,
            'folders' => $folders ?? $this->folders(),
            'labels' => $labels,
            'active' => $active,
            'address' => $this->auth->user()['address'] ?? '',
            'csrf' => $this->csrf->token(),
            'q' => $q,
            'accounts' => $this->auth->accounts(),
            'scripts' => $scripts,
            'autoreplyOn' => ($this->profileInfoOrNull()['autoreply'] ?? false) === true,
            'flash' => $this->session->pullFlash(),
        ]));
    }

    public function error(int $code, string $heading, string $text): Response
    {
        return Response::html($this->view->render('error', compact('code', 'heading', 'text')), $code);
    }

    public function handle(Request $r): Response
    {
        $this->session->start();
        $res = $this->dispatch($r);
        // Persist the session BEFORE the response goes out. Otherwise PHP flushes the response first and writes the session at
        // shutdown, so a client that follows a redirect immediately (login -> inbox) can race the write and look signed out.
        $this->session->close();
        return $this->secure($res, $r);
    }

    private function dispatch(Request $r): Response
    {
        try {
            if ($r->method === 'POST' && self::bodyTooLarge()) {
                return $this->error(413, 'Upload too large', 'The files you tried to send are larger than this server accepts. Remove some attachments and try again.');
            }
            if (in_array($r->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !$this->csrf->verify($r)) {
                return $this->error(419, 'Page expired', 'Your form expired or was invalid. Go back, refresh the page and try again.');
            }
            $m = $this->router->match($r->method, $r->path);
            if (is_int($m)) {
                return $m === 405
                    ? $this->error(405, 'Method not allowed', 'That action is not available here.')->withHeader('Allow', 'GET, POST')
                    : $this->error(404, 'Page not found', 'The page you are looking for does not exist.');
            }
            [$handler, $params, $middleware] = $m;
            if (in_array('auth', $middleware, true) && !$this->auth->check()) {
                return str_starts_with($r->path, '/api/') ? Response::json(['error' => 'unauthenticated'], 401) : Response::redirect('/login');
            }
            $r->params = $params;
            $res = $handler($r, $this);
            if ($r->method !== 'GET') {
                $this->forgetSidebar(); // counters and folder/label lists may have changed
                $this->forgetPrefs();
            }
            return $res;
        } catch (ApiException $e) {
            return $this->fromApiException($e);
        } catch (\Throwable $t) {
            error_log(sprintf('[webmail] %s: %s at %s:%d', $t::class, $t->getMessage(), $t->getFile(), $t->getLine()));
            return $this->error(500, 'Something went wrong', 'An unexpected error occurred. Please try again.');
        }
    }

    /** PHP silently drops the whole POST body when it exceeds post_max_size; detect that instead of reporting a bogus CSRF error. */
    private static function bodyTooLarge(): bool
    {
        $max = trim((string) ini_get('post_max_size'));
        if ($max === '' || $max === '0') {
            return false;
        }
        $n = (int) $max;
        $bytes = match (strtolower(substr($max, -1))) {
            'g' => $n * 1073741824,
            'm' => $n * 1048576,
            'k' => $n * 1024,
            default => $n,
        };
        return (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $bytes;
    }

    private function fromApiException(ApiException $e): Response
    {
        if ($e->isUnauthorized()) {
            error_log('[webmail] WildDuck rejected the session token (status=' . $e->httpStatus . ' code=' . $e->apiCode . ') for ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
            $dead = $this->auth->activeId();
            $addr = $this->auth->user()['address'] ?? '';
            if ($dead !== null && count($this->auth->accounts()) > 1 && !str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                // Only this account's session ended: drop it, keep the others signed in.
                $this->auth->remove($dead);
                $this->forgetSidebar();
                $this->forgetPrefs();
                $this->session->flash('error', "Your session for $addr has expired. You are now using " . ($this->auth->user()['address'] ?? 'another account') . '.');
                return Response::redirect('/mail/inbox');
            }
            $this->auth->logout();
            $this->session->start();
            if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                return Response::json(['error' => 'unauthenticated'], 401);
            }
            $this->session->flash('error', 'Your session has expired. Please sign in again.');
            return Response::redirect('/login');
        }
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            error_log('[webmail] api upstream error status=' . $e->httpStatus . ' code=' . $e->apiCode);
            return Response::json(['error' => $e->isUnavailable() ? 'unavailable' : 'failed'], $e->isUnavailable() ? 503 : ($e->httpStatus >= 400 && $e->httpStatus < 600 ? $e->httpStatus : 400));
        }
        if ($e->isNotFound()) {
            return $this->error(404, 'Not found', 'That message or folder could not be found.');
        }
        if ($e->isUnavailable()) {
            error_log('[webmail] WildDuck unavailable: ' . $e->apiCode . ' status=' . $e->httpStatus);
            return $this->error(503, 'Mail server unavailable', 'The mail server is temporarily unavailable. Please try again in a moment.');
        }
        return $this->error($e->httpStatus >= 400 ? $e->httpStatus : 400, 'Request failed', $e->userMessage());
    }

    private function secure(Response $res, Request $r): Response
    {
        $h = &$res->headers;
        $h['X-Content-Type-Options'] = 'nosniff';
        $h['Referrer-Policy'] = 'no-referrer';
        $h['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=(), payment=()';
        $h['Cross-Origin-Opener-Policy'] = 'same-origin';
        $h['Cache-Control'] ??= 'private, no-store';
        if (!isset($h['Content-Security-Policy'])) {
            $h['Content-Security-Policy'] = "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; "
                . "connect-src 'self'; frame-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'";
        }
        $h['X-Frame-Options'] ??= 'DENY';
        if (Env::bool('SESSION_COOKIE_SECURE', true)) {
            $h['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        return $res;
    }
}

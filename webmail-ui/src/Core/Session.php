<?php
declare(strict_types=1);

namespace Webmail\Core;

final class Session
{
    private bool $started = false;
    private bool $closed = false;

    public function __construct(private readonly string $savePath, private readonly int $lifetimeMin, private readonly bool $secureCookie)
    {
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }
        $this->closed = false;
        if (PHP_SAPI !== 'cli') {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.gc_maxlifetime', (string) ($this->lifetimeMin * 60));
            session_save_path($this->savePath);
            session_name('wm_sid');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $this->secureCookie,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        $this->started = true;

        // Absolute + idle timeout.
        $now = time();
        $last = (int) ($_SESSION['_last'] ?? $now);
        if ($now - $last > $this->lifetimeMin * 60) {
            $this->destroy();
            $this->start();
            return;
        }
        $_SESSION['_last'] = $now;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string,message:string}> */
    public function pullFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    /**
     * Release the session file lock early (data stays readable in memory, further writes are not saved).
     * Used by background polling so a slow upstream call can never block the user's other requests.
     */
    public function close(): void
    {
        $this->closed = true;
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    /** Prevent session fixation: call on every privilege change (login/logout/account switch). */
    public function regenerate(): void
    {
        if (PHP_SAPI !== 'cli') {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
            session_destroy();
        }
        $this->started = false;
    }
}

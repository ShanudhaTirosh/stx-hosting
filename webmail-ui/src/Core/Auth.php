<?php
declare(strict_types=1);

namespace Webmail\Core;

/** Server-side login state. The WildDuck user id and token never leave the server. */
final class Auth
{
    public function __construct(private readonly Session $session, private readonly Crypto $crypto)
    {
    }

    public const MAX_ACCOUNTS = 5;

    /**
     * Sign an account in (or refresh it when it is already signed in) and make it the active one.
     * The session may hold several accounts; each keeps its own encrypted WildDuck token and a random per-session id.
     *
     * @return string the session-local account id
     */
    public function login(string $userId, string $address, ?string $token): string
    {
        $this->session->regenerate();
        $accounts = $this->raw();
        $id = null;
        foreach ($accounts as $aid => $a) {
            if (($a['u'] ?? '') === $userId) {
                $id = (string) $aid;
            }
        }
        // Never all digits: PHP turns numeric-string array keys into integers, which would silently drop the account on the next read.
        $id ??= 'a' . bin2hex(random_bytes(6));
        $accounts[$id] = ['u' => $userId, 'a' => $address, 't' => $token !== null ? $this->crypto->encrypt($token) : null];
        $this->session->put('wm_accounts', $accounts);
        $this->session->put('wm_active', $id);
        $this->session->forget('_csrf'); // fresh CSRF token whenever the signed-in identity changes
        $this->session->forget('wm_2fa');
        return $id;
    }

    /** Room for one more account (or the account is already signed in and would just be refreshed)? */
    public function canAdd(string $userId = ''): bool
    {
        $a = $this->raw();
        foreach ($a as $x) {
            if ($userId !== '' && ($x['u'] ?? '') === $userId) {
                return true;
            }
        }
        return count($a) < self::MAX_ACCOUNTS;
    }

    /** @return list<array{id:string,address:string,active:bool}> in the order the accounts were added */
    public function accounts(): array
    {
        $active = $this->session->get('wm_active');
        $out = [];
        foreach ($this->raw() as $id => $a) {
            $out[] = ['id' => (string) $id, 'address' => (string) ($a['a'] ?? ''), 'active' => (string) $id === $active];
        }
        return $out;
    }

    /** Make another signed-in account the active one. False when the id is not one of THIS session's accounts. */
    public function switchTo(string $accountId): bool
    {
        if (!isset($this->raw()[$accountId])) {
            return false;
        }
        $this->session->regenerate();
        $this->session->put('wm_active', $accountId);
        $this->session->forget('wm_2fa');
        return true;
    }

    public function activeId(): ?string
    {
        $id = $this->session->get('wm_active');
        return is_string($id) && isset($this->raw()[$id]) ? $id : null;
    }

    /** @return array{id:string,userId:string,address:string}|null */
    public function account(string $accountId): ?array
    {
        $a = $this->raw()[$accountId] ?? null;
        return $a === null ? null : ['id' => $accountId, 'userId' => (string) $a['u'], 'address' => (string) $a['a']];
    }

    public function tokenFor(string $accountId): ?string
    {
        $a = $this->raw()[$accountId] ?? null;
        return is_array($a) && is_string($a['t'] ?? null) ? $this->crypto->decrypt($a['t']) : null;
    }

    /**
     * Forget one account. When it was the active one, the first remaining account becomes active.
     *
     * @return bool true when at least one account is still signed in
     */
    public function remove(string $accountId): bool
    {
        $accounts = $this->raw();
        unset($accounts[$accountId]);
        $this->session->put('wm_accounts', $accounts);
        if ($this->session->get('wm_active') === $accountId || !isset($accounts[(string) $this->session->get('wm_active')])) {
            $first = array_key_first($accounts);
            $first === null ? $this->session->forget('wm_active') : $this->session->put('wm_active', (string) $first);
            $this->session->regenerate();
        }
        return $accounts !== [];
    }

    /** @return array<string,array{u:string,a:string,t:?string}> */
    private function raw(): array
    {
        $a = $this->session->get('wm_accounts');
        $out = [];
        foreach (is_array($a) ? $a : [] as $id => $x) {
            $id = (string) $id; // tolerate numeric-string keys that PHP stored as integers
            if ($id !== '' && is_array($x) && is_string($x['u'] ?? null) && is_string($x['a'] ?? null)) {
                $out[$id] = ['u' => $x['u'], 'a' => $x['a'], 't' => is_string($x['t'] ?? null) ? $x['t'] : null];
            }
        }
        return $out;
    }

    public const TWOFA_TTL = 300;

    /** Remember "password OK, code still needed" server-side only (the nonce never reaches the browser). */
    public function startTwoFactor(string $userId, string $address, ?string $nonce, ?int $now = null, bool $adding = false): void
    {
        $this->session->regenerate();
        $this->session->put('wm_2fa', ['u' => $userId, 'a' => $address, 'n' => $nonce !== null ? $this->crypto->encrypt($nonce) : null, 't' => $now ?? time(), 'f' => 0, 'ad' => $adding]);
    }

    /** @return array{userId:string,address:string,nonce:?string,fails:int,adding:bool}|null null when absent or older than five minutes */
    public function pendingTwoFactor(?int $now = null): ?array
    {
        $p = $this->session->get('wm_2fa');
        if (!is_array($p) || !is_string($p['u'] ?? null) || !is_string($p['a'] ?? null) || (($now ?? time()) - (int) ($p['t'] ?? 0)) > self::TWOFA_TTL) {
            if ($p !== null) {
                $this->session->forget('wm_2fa');
            }
            return null;
        }
        return ['userId' => $p['u'], 'address' => $p['a'], 'nonce' => is_string($p['n'] ?? null) ? $this->crypto->decrypt($p['n']) : null, 'fails' => (int) ($p['f'] ?? 0), 'adding' => (bool) ($p['ad'] ?? false)];
    }

    public function twoFactorFailed(): int
    {
        $p = $this->session->get('wm_2fa');
        if (!is_array($p)) {
            return 0;
        }
        $p['f'] = (int) ($p['f'] ?? 0) + 1;
        $this->session->put('wm_2fa', $p);
        return $p['f'];
    }

    public function clearTwoFactor(): void
    {
        $this->session->forget('wm_2fa');
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /** @return array{id:string,address:string}|null the ACTIVE account */
    public function user(): ?array
    {
        $id = $this->activeId();
        $a = $id !== null ? $this->raw()[$id] : null;
        return $a !== null ? ['id' => $a['u'], 'address' => $a['a']] : null;
    }

    public function token(): ?string
    {
        $id = $this->activeId();
        return $id !== null ? $this->tokenFor($id) : null;
    }

    /** Sign out of EVERYTHING in this session (all accounts). */
    public function logout(): void
    {
        $this->session->destroy();
    }
}

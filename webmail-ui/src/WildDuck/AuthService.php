<?php
declare(strict_types=1);

namespace Webmail\WildDuck;

final class AuthService
{
    /** @param Client $serviceClient client holding the service token (used only for /authenticate) */
    public function __construct(private readonly Client $serviceClient)
    {
    }

    /**
     * Step 1 of sign-in. When the account has two-factor authentication, `twoFactor` is set and NO usable token is
     * returned: a token that WildDuck hands out before the second factor is verified (compatibility mode) is ignored.
     *
     * @return array{userId:string,address:string,username:string,token:?string,requirePasswordChange:bool,twoFactor:?array{nonce:?string,methods:list<string>}}
     * @throws AuthFailedException wrong credentials
     * @throws TwoFactorRequiredException the account only has second factors this UI cannot handle (security keys)
     */
    public function login(string $username, string $password, string $ip, string $sessionId): array
    {
        try {
            $res = $this->serviceClient->json('POST', '/authenticate', [], [
                'username' => $username,
                'password' => $password,
                'scope' => 'master',   // main account password only; app-specific passwords are rejected
                'protocol' => 'Webmail',
                'token' => true,       // per-user scoped API token
                'sess' => $sessionId,
                'ip' => $ip,
            ]);
        } catch (ApiException $e) {
            if ($e->apiCode === 'AuthFailed' || $e->httpStatus === 403) {
                throw new AuthFailedException('Incorrect email address or password.', 403, 'AuthFailed', $e);
            }
            throw $e;
        }
        if (!is_string($res['id'] ?? null) || !Ids::objectId($res['id'])) {
            throw new ApiException('Unexpected response from mail server.', 502, 'BadResponse');
        }

        $methods = array_values(array_filter(is_array($res['require2fa'] ?? null) ? $res['require2fa'] : [], static fn ($m) => is_string($m) && $m !== 'custom'));
        $two = null;
        if ($methods) {
            if (!in_array('totp', $methods, true)) {
                throw new TwoFactorRequiredException('This account needs a security key, which this webmail does not support yet.', 403, 'Require2fa');
            }
            $nonce = $res['totpNonce'] ?? $res['twoFactorNonce'] ?? null;
            $two = ['nonce' => is_string($nonce) && $nonce !== '' ? $nonce : null, 'methods' => $methods];
        }

        return [
            'userId' => $res['id'],
            'address' => is_string($res['address'] ?? null) ? $res['address'] : $username,
            'username' => is_string($res['username'] ?? null) ? $res['username'] : $username,
            'token' => $two === null && is_string($res['token'] ?? null) && $res['token'] !== '' ? $res['token'] : null,
            'requirePasswordChange' => (bool) ($res['requirePasswordChange'] ?? false),
            'twoFactor' => $two,
        ];
    }

    /**
     * Step 2: verify an authenticator-app code and receive the real user token.
     *
     * @throws AuthFailedException the code (or nonce) was not accepted
     */
    public function verifyTotp(string $userId, string $code, ?string $nonce, string $ip, string $sessionId): string
    {
        Ids::require($userId, 'object');
        $body = ['token' => $code, 'sess' => $sessionId, 'ip' => $ip];
        if ($nonce !== null) {
            $body['totpNonce'] = $nonce;
        }
        try {
            $res = $this->serviceClient->json('POST', "/users/{$userId}/2fa/totp/check", [], $body);
        } catch (ApiException $e) {
            if (in_array($e->httpStatus, [400, 401, 403], true)) {
                throw new AuthFailedException('That code was not accepted.', 403, 'TotpFailed', $e);
            }
            throw $e;
        }
        if (!is_string($res['token'] ?? null) || $res['token'] === '') {
            throw new ApiException('Unexpected response from mail server.', 502, 'BadResponse');
        }
        return $res['token'];
    }
}

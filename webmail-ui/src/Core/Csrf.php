<?php
declare(strict_types=1);

namespace Webmail\Core;

final class Csrf
{
    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $t = $this->session->get('_csrf');
        if (!is_string($t) || $t === '') {
            $t = bin2hex(random_bytes(32));
            $this->session->put('_csrf', $t);
        }
        return $t;
    }

    public function verify(Request $r): bool
    {
        $given = $r->header('x-csrf-token') ?? $r->string('_token');
        return $given !== '' && hash_equals($this->token(), $given);
    }
}

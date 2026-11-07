<?php

namespace App\Exceptions;

use RuntimeException;

/** The panel could not reach or sign in to a customer's VPS. The message is safe to show the customer. */
class VpsAccessException extends RuntimeException
{
    public static function notSetUp(): self
    {
        return new self('Panel access is not set up for this server yet. Enter your current root password once below to enable it. We do not store it.');
    }

    public static function unreachable(): self
    {
        return new self('We could not reach your server. Check that it is running and that SSH is open, then try again.');
    }

    public static function loginFailed(): self
    {
        return new self('The server refused the login. If you changed the root password, enter the new one below.');
    }

    public static function hostKeyChanged(): self
    {
        return new self('The server identity has changed since we first connected. For your safety we stopped. If you reinstalled the server, contact support to confirm.');
    }
}

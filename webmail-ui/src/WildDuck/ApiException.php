<?php
declare(strict_types=1);

namespace Webmail\WildDuck;

/** Any failure talking to WildDuck. Never carries credentials; message is safe to log. */
class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly string $apiCode = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function isNotFound(): bool
    {
        return $this->httpStatus === 404;
    }

    public function isUnauthorized(): bool
    {
        return $this->httpStatus === 401 || $this->apiCode === 'InvalidToken';
    }

    public function isUnavailable(): bool
    {
        return $this->httpStatus === 0 || $this->httpStatus >= 500;
    }

    /** Message that is safe to show to end users (no internals). */
    public function userMessage(): string
    {
        return match (true) {
            $this->isUnavailable() => 'The mail server is temporarily unavailable. Please try again in a moment.',
            $this->isUnauthorized() => 'Your session has expired. Please sign in again.',
            $this->isNotFound() => 'That item could not be found.',
            $this->httpStatus === 429 => 'Too many requests. Please slow down and try again shortly.',
            default => $this->getMessage() !== '' ? $this->getMessage() : 'The request could not be completed.',
        };
    }
}

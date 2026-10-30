<?php

namespace App\Services;

class LimitCheckResult
{
    public function __construct(
        protected bool $allowed,
        protected ?string $reason = null,
        protected int|float $currentUsage = 0,
        protected int|float $maxLimit = 0
    ) {}

    public static function allow(int|float $currentUsage = 0, int|float $maxLimit = 0): self
    {
        return new self(true, null, $currentUsage, $maxLimit);
    }

    public static function deny(string $reason, int|float $currentUsage = 0, int|float $maxLimit = 0): self
    {
        return new self(false, $reason, $currentUsage, $maxLimit);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function currentUsage(): int|float
    {
        return $this->currentUsage;
    }

    public function maxLimit(): int|float
    {
        return $this->maxLimit;
    }
}


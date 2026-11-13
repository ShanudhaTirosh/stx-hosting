<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * A domain a client wants to serve their apps on. Ownership is proven with a DNS TXT record
 * (see CustomDomainService); until then it cannot be used on any application.
 */
class CustomDomain extends BaseModel
{
    public const STATUS_PENDING_DNS = 'pending_dns';
    public const STATUS_DNS_VERIFIED = 'dns_verified';
    public const STATUS_ISSUING_SSL = 'issuing_ssl';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'verified_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'ssl_issued_at' => 'datetime',
        'ssl_expires_at' => 'datetime',
        'check_attempts' => 'integer',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isSslActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE || $this->ssl_issued_at !== null;
    }

    public function isPendingDns(): bool
    {
        return $this->status === self::STATUS_PENDING_DNS || ($this->status === null && ! $this->isVerified());
    }

    public function isDnsVerified(): bool
    {
        return $this->status === self::STATUS_DNS_VERIFIED || $this->isVerified();
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /** Host to create at the DNS provider. */
    public function txtHost(): string
    {
        return '_stx-verify.'.$this->domain;
    }

    public function txtValue(): string
    {
        return 'stx-verify='.$this->token;
    }
}

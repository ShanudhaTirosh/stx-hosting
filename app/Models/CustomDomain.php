<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * A domain a client wants to serve their apps on. Ownership is proven with a DNS TXT record
 * (see CustomDomainService); until then it cannot be used on any application.
 */
class CustomDomain extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'verified_at' => 'datetime',
        'last_checked_at' => 'datetime',
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

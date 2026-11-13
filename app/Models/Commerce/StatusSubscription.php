<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use Illuminate\Notifications\Notifiable;

class StatusSubscription extends BaseModel
{
    use Notifiable;

    protected $table = 'status_subscriptions';

    protected $guarded = [];

    protected $casts = [
        'verified_at' => 'datetime',
        'subscribed_components' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeVerifiedAndActive($query)
    {
        return $query->where('is_active', true)->whereNotNull('verified_at');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}


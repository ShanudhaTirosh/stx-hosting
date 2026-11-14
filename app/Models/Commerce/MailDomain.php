<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MailDomain extends BaseModel
{
    protected $table = 'mail_domains';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'is_verified' => 'boolean',
        'dns_status' => 'array',
        'last_checked_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(MailSubscription::class, 'subscription_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function mailboxes(): HasMany
    {
        return $this->hasMany(MailMailbox::class, 'domain_id');
    }
}


<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MailSubscription extends BaseModel
{
    protected $table = 'mail_subscriptions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'total_storage_mb' => 'integer',
        'max_mailboxes' => 'integer',
        'max_domains' => 'integer',
        'active_addons' => 'array',
        'renews_at' => 'datetime',
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

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MailPlan::class, 'plan_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(MailDomain::class, 'subscription_id');
    }
}


<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MailPlan extends BaseModel
{
    protected $table = 'mail_plans';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'features' => 'array',
        'storage_mb' => 'integer',
        'max_mailboxes' => 'integer',
        'price_cents' => 'integer',
        'price_lkr_cents' => 'integer',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MailSubscription::class, 'plan_id');
    }
}


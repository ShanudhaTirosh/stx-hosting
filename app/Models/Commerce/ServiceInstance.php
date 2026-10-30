<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceInstance extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'network_config' => 'array',
        'activated_at' => 'datetime',
        'renews_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function getNetworkConfigAttribute($value): ?array
    {
        if ($value) {
            return is_string($value) ? json_decode($value, true) : $value;
        }

        $inventory = $this->vpsInventory;
        $cred = $this->credentials()->where('credential_type', 'root_password')->first();
        if ($inventory || $cred) {
            return [
                'provider' => $inventory?->provider ?? 'custom',
                'datacenter' => $inventory?->datacenter ?? 'unknown',
                'ssh_port' => $cred?->ssh_port ?? 22,
            ];
        }

        return null;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function vpsInventory(): BelongsTo
    {
        return $this->belongsTo(VpsInventory::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ServiceCredential::class);
    }
}

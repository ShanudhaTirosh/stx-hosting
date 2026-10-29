<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VpsInventory extends BaseModel
{
    protected $table = 'vps_inventory';
    protected $guarded = [];

    protected $casts = [
        'vcpu' => 'integer',
        'ram_mb' => 'integer',
        'disk_gb' => 'integer',
    ];

    public function serviceInstances(): HasMany
    {
        return $this->hasMany(ServiceInstance::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }
}


<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VpsAuthorizedKey extends BaseModel
{
    protected $table = 'vps_authorized_keys';
    protected $guarded = [];

    public function serviceInstance(): BelongsTo
    {
        return $this->belongsTo(ServiceInstance::class);
    }
}


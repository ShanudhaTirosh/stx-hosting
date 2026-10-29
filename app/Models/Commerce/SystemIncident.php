<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;

class SystemIncident extends BaseModel
{
    protected $table = 'system_incidents';

    protected $guarded = [];

    protected $casts = [
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}

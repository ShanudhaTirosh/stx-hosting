<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemIncident extends BaseModel
{
    protected $table = 'system_incidents';

    protected $guarded = [];

    protected $casts = [
        'resolved_at' => 'datetime',
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'affected_monitors' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function updates(): HasMany
    {
        return $this->hasMany(IncidentUpdate::class, 'system_incident_id')->orderBy('created_at', 'desc');
    }

    public function latestUpdate()
    {
        return $this->hasOne(IncidentUpdate::class, 'system_incident_id')->latestOfMany('created_at');
    }
}

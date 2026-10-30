<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorCheck extends Model
{
    protected $table = 'monitor_checks';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'checked_at' => 'datetime',
        'response_time_ms' => 'integer',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class, 'monitor_id');
    }
}

<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Model;

class VpsStatSample extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'cpu_percent' => 'float',
        'disk_used_gb' => 'float',
        'load1' => 'float',
        'collected_at' => 'datetime',
    ];
}

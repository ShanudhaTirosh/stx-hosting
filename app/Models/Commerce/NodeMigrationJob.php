<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NodeMigrationJob extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'downtime_duration_seconds' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (NodeMigrationJob $job) {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    public function serviceInstance(): BelongsTo
    {
        return $this->belongsTo(ServiceInstance::class);
    }

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(ClusterNode::class, 'source_node_id');
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(ClusterNode::class, 'target_node_id');
    }
}


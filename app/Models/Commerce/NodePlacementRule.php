<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodePlacementRule extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'label_selector' => 'array',
        'is_active' => 'boolean',
    ];

    public function clusterNode(): BelongsTo
    {
        return $this->belongsTo(ClusterNode::class);
    }
}


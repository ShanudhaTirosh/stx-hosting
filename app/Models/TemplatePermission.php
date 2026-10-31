<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TemplatePermission — Granular access control for deployment templates.
 *
 * Scope resolution order:
 *  1. Per-client team grant/deny (`team_id`)
 *  2. Per-plan binding (`hosting_plan_id`)
 *  3. Global enablement (`is_globally_enabled`)
 */
class TemplatePermission extends Model
{
    use HasFactory;

    protected $table = 'template_permissions';

    protected $fillable = [
        'template_name',
        'hosting_plan_id',
        'team_id',
        'is_globally_enabled',
        'is_allowed',
        'category',
        'metadata',
    ];

    protected $casts = [
        'hosting_plan_id' => 'integer',
        'team_id' => 'integer',
        'is_globally_enabled' => 'boolean',
        'is_allowed' => 'boolean',
        'metadata' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function hostingPlan(): BelongsTo
    {
        return $this->belongsTo(HostingPlan::class, 'hosting_plan_id');
    }
}

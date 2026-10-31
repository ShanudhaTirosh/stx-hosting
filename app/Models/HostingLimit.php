<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HostingLimit — Configurable resource quota for a client team or hosting plan.
 *
 * NULL team_id + NULL hosting_plan_id = global default limits applied to all clients.
 * Non-null team_id = per-team override (highest priority).
 * Non-null hosting_plan_id = per-plan defaults.
 *
 * All count limits are unsigned integers (-1 reserved for unlimited in application logic,
 * but the database stores NULL for unlimited to avoid magic-number ambiguity).
 */
class HostingLimit extends Model
{
    use HasFactory;

    protected $table = 'hosting_limits';

    protected $fillable = [
        'team_id',
        'hosting_plan_id',
        // Count limits
        'max_projects',
        'max_deployments',
        'max_applications',
        'max_databases',
        'max_servers',
        'max_running_containers',
        // Resource limits
        'max_cpu',
        'max_ram_mb',
        'max_storage_gb',
        'max_bandwidth_gb',
        // SMTP limits
        'max_smtp_accounts',
        'max_emails_per_day',
        // Feature flags (JSON)
        'allowed_technologies',
        'allowed_template_categories',
    ];

    protected $casts = [
        'team_id' => 'integer',
        'hosting_plan_id' => 'integer',
        'max_projects' => 'integer',
        'max_deployments' => 'integer',
        'max_applications' => 'integer',
        'max_databases' => 'integer',
        'max_servers' => 'integer',
        'max_running_containers' => 'integer',
        'max_cpu' => 'float',
        'max_ram_mb' => 'integer',
        'max_storage_gb' => 'integer',
        'max_bandwidth_gb' => 'integer',
        'max_smtp_accounts' => 'integer',
        'max_emails_per_day' => 'integer',
        'allowed_technologies' => 'array',
        'allowed_template_categories' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (HostingLimit $limit) {
            // Validate non-negative counts
            $countFields = [
                'max_projects', 'max_deployments', 'max_applications',
                'max_databases', 'max_servers', 'max_running_containers',
                'max_smtp_accounts', 'max_emails_per_day',
                'max_ram_mb', 'max_storage_gb', 'max_bandwidth_gb',
            ];

            foreach ($countFields as $field) {
                if (isset($limit->{$field}) && $limit->{$field} < 0) {
                    throw new \InvalidArgumentException("HostingLimit field '{$field}' must be >= 0, got {$limit->{$field}}.");
                }
            }

            if (isset($limit->max_cpu) && $limit->max_cpu < 0) {
                throw new \InvalidArgumentException("HostingLimit field 'max_cpu' must be >= 0.");
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function hostingPlan(): BelongsTo
    {
        return $this->belongsTo(HostingPlan::class);
    }
}

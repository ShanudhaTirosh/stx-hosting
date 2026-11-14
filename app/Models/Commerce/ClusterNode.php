<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use App\Models\Server;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ClusterNode extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'labels' => 'array',
        'allowed_templates' => 'array',
        'current_cpu_load' => 'float',
        'last_heartbeat_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ClusterNode $node) {
            if (empty($node->uuid)) {
                $node->uuid = (string) Str::uuid();
            }
            if (empty($node->enrollment_token)) {
                $node->enrollment_token = 'stx_node_'.Str::random(32);
            }
        });
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function serviceInstances(): HasMany
    {
        return $this->hasMany(ServiceInstance::class, 'cluster_node_id');
    }

    public function placementRules(): HasMany
    {
        return $this->hasMany(NodePlacementRule::class, 'cluster_node_id');
    }

    public function outgoingMigrations(): HasMany
    {
        return $this->hasMany(NodeMigrationJob::class, 'source_node_id');
    }

    public function incomingMigrations(): HasMany
    {
        return $this->hasMany(NodeMigrationJob::class, 'target_node_id');
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isDraining(): bool
    {
        return $this->status === 'draining';
    }

    public function isMaintenance(): bool
    {
        return $this->status === 'maintenance';
    }

    public function isOffline(): bool
    {
        if ($this->status === 'offline') {
            return true;
        }

        // Check if heartbeat expired (> 120 seconds)
        if ($this->last_heartbeat_at && $this->last_heartbeat_at->diffInSeconds(now()) > 120) {
            return true;
        }

        return false;
    }

    public function allocatedRamMb(): int
    {
        return (int) $this->serviceInstances()
            ->where('status', 'active')
            ->join('product_variants', 'service_instances.product_variant_id', '=', 'product_variants.id')
            ->sum('product_variants.ram_mb');
    }

    public function availableRamMb(): int
    {
        $usable = max(0, $this->total_ram_mb - $this->reserved_ram_mb);
        $allocated = $this->allocatedRamMb();

        return max(0, $usable - $allocated);
    }

    public function activeAppsCount(): int
    {
        return $this->serviceInstances()->where('status', 'active')->count();
    }

    public function canHostTemplate(string $templateName): bool
    {
        if (empty($this->allowed_templates)) {
            return true;
        }

        return in_array(strtolower($templateName), array_map('strtolower', $this->allowed_templates), true);
    }

    /**
     * Calculate multi-factor placement score (higher = better candidate).
     */
    public function calculatePlacementScore(int $appRamMb = 512, float $appVcpu = 1.0): float
    {
        if (! $this->isReady() || $this->isOffline()) {
            return -1.0;
        }

        // Hard checks
        $availableRam = $this->availableRamMb();
        if ($availableRam < $appRamMb) {
            return -1.0;
        }

        if ($this->activeAppsCount() >= $this->max_apps) {
            return -1.0;
        }

        // 1. RAM Score (Weight: 40%)
        $totalUsableRam = max(1, $this->total_ram_mb - $this->reserved_ram_mb);
        $ramScore = max(0.0, min(1.0, ($availableRam - $appRamMb) / $totalUsableRam));

        // 2. CPU Score (Weight: 30%)
        $cpuScore = max(0.0, min(1.0, 1.0 - ($this->current_cpu_load / max(1, $this->cpu_cores))));

        // 3. Disk Score (Weight: 20%)
        $diskScore = max(0.0, min(1.0, 1.0 - ($this->current_disk_used_gb / max(1, $this->total_disk_gb))));

        // 4. Density Balance Score (Weight: 10%)
        $densityScore = max(0.0, min(1.0, 1.0 - ($this->activeAppsCount() / max(1, $this->max_apps))));

        $totalScore = ($ramScore * 0.40) + ($cpuScore * 0.30) + ($diskScore * 0.20) + ($densityScore * 0.10);

        // Apply owner weight adjustment (-100 to +100 converted to -0.2 to +0.2)
        $weightBonus = ($this->weight_adjustment / 100.0) * 0.20;

        return max(0.0, $totalScore + $weightBonus);
    }
}


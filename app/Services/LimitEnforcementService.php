<?php

namespace App\Services;

use App\Models\Application;
use App\Models\HostingEntitlement;
use App\Models\HostingLimit;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandalonePostgresql;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;

class LimitEnforcementService
{
    /**
     * Resolve effective limits for a given team.
     * Hierarchy:
     * 1. Team-specific custom limit override
     * 2. Active subscription plan limit
     * 3. Global default limit
     */
    public static function getEffectiveLimits(Team $team): array
    {
        // 1. Team override
        $override = HostingLimit::where('team_id', $team->id)->first();
        if ($override) {
            return $override->toArray();
        }

        // 2. Active plan limit
        $activeEntitlement = HostingEntitlement::where('team_id', $team->id)
            ->whereNotNull('hosting_plan_id')
            ->first();

        if ($activeEntitlement) {
            $planLimit = HostingLimit::where('hosting_plan_id', $activeEntitlement->hosting_plan_id)->first();
            if ($planLimit) {
                return $planLimit->toArray();
            }
        }

        // 3. Global default
        $global = HostingLimit::whereNull('team_id')->whereNull('hosting_plan_id')->first();
        if ($global) {
            return $global->toArray();
        }

        // 4. Default baseline fallback
        return [
            'team_id'                => null,
            'hosting_plan_id'        => null,
            'max_projects'           => 5,
            'max_deployments'        => 50,
            'max_applications'       => 5,
            'max_databases'          => 3,
            'max_servers'            => 2,
            'max_running_containers' => 10,
            'max_cpu'                => 2.0,
            'max_ram_mb'             => 4096,
            'max_storage_gb'         => 50,
            'max_bandwidth_gb'       => 200,
            'max_smtp_accounts'      => 0,
            'max_emails_per_day'     => 0,
            'allowed_technologies'   => null,
            'allowed_template_categories' => null,
        ];
    }

    /**
     * Aggregate current usage metrics across all dimensions for a team.
     */
    public static function getCurrentUsage(Team $team): array
    {
        $projectCount = Project::where('team_id', $team->id)->count();

        // Applications can belong to team directly or via environment.project
        $apps = Application::where('team_id', $team->id)
            ->orWhereHas('environment.project', fn ($q) => $q->where('team_id', $team->id))
            ->get();

        $appCount = $apps->count();
        $runningContainers = $apps->where('status', 'running')->count();

        // Databases
        $dbCount = StandalonePostgresql::where('team_id', $team->id)
            ->orWhereHas('environment.project', fn ($q) => $q->where('team_id', $team->id))
            ->count();

        $serverCount = Server::where('team_id', $team->id)->count();

        // Aggregate CPU & RAM from application configs
        $totalRamMb = 0;
        $totalCpu = 0.0;

        foreach ($apps as $app) {
            if ($app->limits_memory) {
                $totalRamMb += (int) preg_replace('/[^0-9]/', '', $app->limits_memory);
            }
            if ($app->limits_cpus) {
                $totalCpu += (float) $app->limits_cpus;
            }
        }

        return [
            'projects'           => $projectCount,
            'applications'       => $appCount,
            'databases'          => $dbCount,
            'servers'            => $serverCount,
            'running_containers' => $runningContainers,
            'ram_mb'             => $totalRamMb,
            'cpu'                => $totalCpu,
        ];
    }

    /**
     * Check whether a client team is allowed to create a new resource.
     * Owners and Admins bypass all limits.
     */
    public static function canCreateResource(Team $team, string $resourceType, array $attributes = []): LimitCheckResult
    {
        $user = auth()->user();
        if ($user && RbacService::shouldBypassLimits($user)) {
            return LimitCheckResult::allow();
        }

        $limits = self::getEffectiveLimits($team);
        $usage = self::getCurrentUsage($team);

        $limitKeyMap = [
            'project'     => ['max_projects', 'projects'],
            'application' => ['max_applications', 'applications'],
            'database'    => ['max_databases', 'databases'],
            'server'      => ['max_servers', 'servers'],
        ];

        if (! isset($limitKeyMap[$resourceType])) {
            return LimitCheckResult::allow();
        }

        [$limitKey, $usageKey] = $limitKeyMap[$resourceType];
        $maxLimit = $limits[$limitKey] ?? 0;
        $currentUsage = $usage[$usageKey] ?? 0;

        if ($currentUsage >= $maxLimit) {
            return LimitCheckResult::deny(
                "Resource creation limit reached for {$resourceType} ({$currentUsage}/{$maxLimit}).",
                $currentUsage,
                $maxLimit
            );
        }

        return LimitCheckResult::allow($currentUsage, $maxLimit);
    }

    /**
     * Check whether a client team is allowed to deploy a resource (e.g. running containers quota).
     */
    public static function canDeployResource(Team $team, Model $resource): LimitCheckResult
    {
        $user = auth()->user();
        if ($user && RbacService::shouldBypassLimits($user)) {
            return LimitCheckResult::allow();
        }

        $limits = self::getEffectiveLimits($team);
        $usage = self::getCurrentUsage($team);

        $maxContainers = $limits['max_running_containers'] ?? 10;
        $runningContainers = $usage['running_containers'] ?? 0;

        if ($runningContainers >= $maxContainers) {
            return LimitCheckResult::deny(
                "Maximum running container quota reached ({$runningContainers}/{$maxContainers}).",
                $runningContainers,
                $maxContainers
            );
        }

        return LimitCheckResult::allow($runningContainers, $maxContainers);
    }

    /**
     * Clamp requested container limits to the team's maximum plan allowance.
     */
    public static function clampResourceLimits(Team $team, array $requestedLimits): array
    {
        $limits = self::getEffectiveLimits($team);

        $maxRamMb = $limits['max_ram_mb'] ?? 4096;
        $maxCpu = $limits['max_cpu'] ?? 2.0;

        $clamped = $requestedLimits;

        if (isset($clamped['limits_memory'])) {
            $val = (int) preg_replace('/[^0-9]/', '', $clamped['limits_memory']);
            if ($val > $maxRamMb) {
                $clamped['limits_memory'] = "{$maxRamMb}m";
            }
        }

        if (isset($clamped['limits_cpus'])) {
            $val = (float) $clamped['limits_cpus'];
            if ($val > $maxCpu) {
                $clamped['limits_cpus'] = (string) $maxCpu;
            }
        }

        return $clamped;
    }

    /**
     * Default cgroup runtime limits when not specified.
     */
    public static function getDefaultRuntimeLimits(Team $team): array
    {
        $limits = self::getEffectiveLimits($team);

        return [
            'limits_memory' => ($limits['max_ram_mb'] ?? 512).'m',
            'limits_cpus'   => (string) min(1.0, (float) ($limits['max_cpu'] ?? 1.0)),
        ];
    }
}


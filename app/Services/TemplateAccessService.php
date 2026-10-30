<?php

namespace App\Services;

use App\Models\HostingEntitlement;
use App\Models\Team;
use App\Models\TemplatePermission;
use Illuminate\Support\Collection;

class TemplateAccessService
{
    /**
     * Check if a team is permitted to deploy a specific template.
     */
    public static function canDeployTemplate(Team $team, string $templateName): bool
    {
        $user = auth()->user();
        if ($user && RbacService::shouldBypassLimits($user)) {
            return true;
        }

        // 1. Check explicit client team override
        $clientPerm = TemplatePermission::where('team_id', $team->id)
            ->where('template_name', $templateName)
            ->first();

        if ($clientPerm) {
            return (bool) $clientPerm->is_allowed;
        }

        // 2. Check global template enablement
        $globalPerm = TemplatePermission::whereNull('team_id')
            ->whereNull('hosting_plan_id')
            ->where('template_name', $templateName)
            ->first();

        if ($globalPerm && ! $globalPerm->is_globally_enabled) {
            return false;
        }

        // 3. Check plan-level restriction
        $planPerm = TemplatePermission::where('template_name', $templateName)
            ->whereNotNull('hosting_plan_id')
            ->first();

        if ($planPerm) {
            $hasPlan = HostingEntitlement::where('team_id', $team->id)
                ->where('hosting_plan_id', $planPerm->hosting_plan_id)
                ->active()
                ->exists();

            if (! $hasPlan) {
                return false;
            }
        }

        // 4. Default check: VIP / premium keywords require entitlement
        if (str_contains($templateName, 'enterprise') || str_contains($templateName, 'vip') || str_contains($templateName, 'premium')) {
            $hasActiveEntitlement = HostingEntitlement::where('team_id', $team->id)
                ->active()
                ->exists();

            if (! $hasActiveEntitlement) {
                return false;
            }
        }

        return true;
    }

    /**
     * Filter a list of templates to only those authorized for the team.
     */
    public static function filterAuthorizedTemplates(Team $team, Collection|array $templates): Collection
    {
        $collection = $templates instanceof Collection ? $templates : collect($templates);

        $limits = LimitEnforcementService::getEffectiveLimits($team);
        $allowedCategories = $limits['allowed_template_categories'] ?? null;

        return $collection->filter(function ($template) use ($team, $allowedCategories) {
            $name = is_array($template) ? ($template['name'] ?? '') : ($template->name ?? '');
            $category = is_array($template) ? ($template['category'] ?? null) : ($template->category ?? null);

            // Category filter if restricted
            if (is_array($allowedCategories) && $category && ! in_array($category, $allowedCategories)) {
                return false;
            }

            return self::canDeployTemplate($team, $name);
        })->values();
    }

    /**
     * Get resource requirements for a template.
     */
    public static function getTemplateRequirements(string $templateName): array
    {
        $requirements = [
            'gitlab-ce'          => ['min_ram_mb' => 4096, 'min_cpu' => 2.0, 'disk_gb' => 20],
            'wordpress'          => ['min_ram_mb' => 512,  'min_cpu' => 0.5, 'disk_gb' => 5],
            'redis'              => ['min_ram_mb' => 256,  'min_cpu' => 0.25,'disk_gb' => 1],
            'enterprise-cluster' => ['min_ram_mb' => 8192, 'min_cpu' => 4.0, 'disk_gb' => 50],
        ];

        return $requirements[$templateName] ?? ['min_ram_mb' => 512, 'min_cpu' => 0.5, 'disk_gb' => 5];
    }
}


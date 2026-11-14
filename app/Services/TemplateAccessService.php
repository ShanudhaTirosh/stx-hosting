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
        $perm = TemplatePermission::where('template_name', $templateName)
            ->whereNull('team_id')
            ->first();

        if ($perm && $perm->min_ram_mb) {
            return [
                'min_ram_mb' => $perm->min_ram_mb,
                'min_cpu' => (float) ($perm->min_vcpu ?: 1.0),
                'disk_gb' => 10,
                'default_port' => $perm->default_port ?: 80,
            ];
        }

        $requirements = [
            'gitlab-ce' => ['min_ram_mb' => 4096, 'min_cpu' => 2.0, 'disk_gb' => 20, 'default_port' => 80],
            'wordpress' => ['min_ram_mb' => 512,  'min_cpu' => 0.5, 'disk_gb' => 5, 'default_port' => 80],
            'ghost'     => ['min_ram_mb' => 512,  'min_cpu' => 0.5, 'disk_gb' => 5, 'default_port' => 2368],
            'n8n'       => ['min_ram_mb' => 1024, 'min_cpu' => 1.0, 'disk_gb' => 10, 'default_port' => 5678],
            'nextcloud' => ['min_ram_mb' => 1024, 'min_cpu' => 1.0, 'disk_gb' => 20, 'default_port' => 80],
            'redis'     => ['min_ram_mb' => 256,  'min_cpu' => 0.25, 'disk_gb' => 1, 'default_port' => 6379],
            'enterprise-cluster' => ['min_ram_mb' => 8192, 'min_cpu' => 4.0, 'disk_gb' => 50, 'default_port' => 80],
        ];

        return $requirements[$templateName] ?? ['min_ram_mb' => 512, 'min_cpu' => 0.5, 'disk_gb' => 5, 'default_port' => 80];
    }

    /**
     * Get all curated templates available for deployment by this team.
     */
    public static function getCuratedTemplates(Team $team): Collection
    {
        $perms = TemplatePermission::whereNull('team_id')
            ->where('is_globally_enabled', true)
            ->where('is_allowed', true)
            ->orderByDesc('is_featured')
            ->orderBy('display_name')
            ->get();

        return $perms->filter(fn (TemplatePermission $p) => self::canDeployTemplate($team, $p->template_name))->values();
    }

    /**
     * Get exposable services for a given template.
     *
     * @return array<int, array{
     *     service_name: string,
     *     friendly_name: string,
     *     description: string,
     *     internal_port: int,
     *     is_default: bool,
     *     subdomain_prefix: string,
     *     access_type: string,
     *     suggested: bool,
     *     env_bindings: array<int, string>
     * }>
     */
    public static function getExposableServices(string $templateName): array
    {
        $perm = TemplatePermission::where('template_name', $templateName)
            ->whereNull('team_id')
            ->first();

        if ($perm) {
            $services = $perm->getExposableServices();
            if (! empty($services)) {
                return $services;
            }
        }

        // Built-in curated profiles for top multi-container & web templates
        $curated = [
            'supabase' => [
                [
                    'service_name' => 'supabase-kong',
                    'friendly_name' => 'Main API Gateway',
                    'description' => 'Unified endpoint for REST, GraphQL, Auth, Storage, and Realtime',
                    'internal_port' => 8000,
                    'is_default' => true,
                    'subdomain_prefix' => '',
                    'access_type' => 'public',
                    'suggested' => true,
                    'env_bindings' => [
                        'SUPABASE_PUBLIC_URL',
                        'NEXT_PUBLIC_SUPABASE_URL',
                        'API_EXTERNAL_URL',
                        'GOTRUE_SITE_URL',
                    ],
                ],
                [
                    'service_name' => 'supabase-studio',
                    'friendly_name' => 'Admin Studio Dashboard',
                    'description' => 'Web console for managing tables, SQL editor, and authentication',
                    'internal_port' => 3000,
                    'is_default' => false,
                    'subdomain_prefix' => 'studio',
                    'access_type' => 'admin_only',
                    'suggested' => true,
                    'env_bindings' => [
                        'STUDIO_DEFAULT_ORGANIZATION',
                        'STUDIO_DEFAULT_PROJECT',
                    ],
                ],
            ],
            'n8n' => [
                [
                    'service_name' => 'n8n',
                    'friendly_name' => 'Workflow Canvas & Webhooks',
                    'description' => 'Main automation engine, webhooks, and visual workflow builder',
                    'internal_port' => 5678,
                    'is_default' => true,
                    'subdomain_prefix' => '',
                    'access_type' => 'public',
                    'suggested' => true,
                    'env_bindings' => [
                        'N8N_EDITOR_BASE_URL',
                        'WEBHOOK_URL',
                        'N8N_HOST',
                    ],
                ],
            ],
            'wordpress' => [
                [
                    'service_name' => 'wordpress',
                    'friendly_name' => 'WordPress Website & Admin',
                    'description' => 'Public website and wp-admin administrative backend',
                    'internal_port' => 80,
                    'is_default' => true,
                    'subdomain_prefix' => '',
                    'access_type' => 'public',
                    'suggested' => true,
                    'env_bindings' => [
                        'WP_HOME',
                        'WP_SITEURL',
                    ],
                ],
            ],
            'minio' => [
                [
                    'service_name' => 'minio',
                    'friendly_name' => 'S3 Object Storage API',
                    'description' => 'High-performance Amazon S3-compatible REST storage API',
                    'internal_port' => 9000,
                    'is_default' => true,
                    'subdomain_prefix' => 's3',
                    'access_type' => 'public',
                    'suggested' => true,
                    'env_bindings' => [
                        'MINIO_SERVER_URL',
                    ],
                ],
                [
                    'service_name' => 'minio',
                    'friendly_name' => 'MinIO Web Console',
                    'description' => 'Administrative dashboard for buckets, policies, and access keys',
                    'internal_port' => 9001,
                    'is_default' => false,
                    'subdomain_prefix' => 'console',
                    'access_type' => 'admin_only',
                    'suggested' => true,
                    'env_bindings' => [
                        'MINIO_BROWSER_REDIRECT_URL',
                    ],
                ],
            ],
            'appwrite' => [
                [
                    'service_name' => 'appwrite',
                    'friendly_name' => 'Appwrite Gateway & Console',
                    'description' => 'Unified backend-as-a-service REST/GraphQL API and developer console',
                    'internal_port' => 80,
                    'is_default' => true,
                    'subdomain_prefix' => '',
                    'access_type' => 'public',
                    'suggested' => true,
                    'env_bindings' => [
                        '_APP_DNS_EXTERNAL',
                        '_APP_CONSOLE_HOSTNAMES',
                    ],
                ],
            ],
        ];

        if (isset($curated[$templateName])) {
            return $curated[$templateName];
        }

        $defaultPort = $perm?->default_port ?? (self::getTemplateRequirements($templateName)['default_port'] ?? 80);

        return [
            [
                'service_name' => $templateName,
                'friendly_name' => $perm?->display_name ?? ucfirst(str_replace('-', ' ', $templateName)),
                'description' => $perm?->short_description ?? 'Primary web application service',
                'internal_port' => $defaultPort,
                'is_default' => true,
                'subdomain_prefix' => '',
                'access_type' => 'public',
                'suggested' => true,
                'env_bindings' => [],
            ],
        ];
    }
}

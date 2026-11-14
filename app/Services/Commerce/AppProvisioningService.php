<?php

namespace App\Services\Commerce;

use App\Exceptions\PlatformRestrictionException;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceInstance;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Services\TemplateAccessService;
use Illuminate\Support\Str;

class AppProvisioningService
{
    /**
     * Calculate strict Docker cgroups limits based on product variant specs or explicit RAM/CPU.
     */
    public static function getPlanLimits(ProductVariant|array $variantOrSpecs): array
    {
        if ($variantOrSpecs instanceof ProductVariant) {
            $ramMb = $variantOrSpecs->ram_mb ?: 512;
            $vcpu = $variantOrSpecs->vcpu ?: 1.0;
        } else {
            $ramMb = $variantOrSpecs['min_ram_mb'] ?? $variantOrSpecs['ram_mb'] ?? 512;
            $vcpu = $variantOrSpecs['min_cpu'] ?? $variantOrSpecs['vcpu'] ?? 1.0;
        }

        return [
            'limits_memory' => "{$ramMb}m",
            'limits_memory_swap' => (int) ($ramMb / 2).'m',
            'limits_cpus' => (string) $vcpu,
            'limits_cpu_shares' => (int) ($vcpu * 1024),
        ];
    }

    /**
     * Generate an instant, collision-free wildcard hostname for a deployment.
     */
    public static function generateHostname(string $appName, ?string $customSubdomain = null): string
    {
        $appsDomain = PlatformDomainService::appsDomain();
        $slug = PlatformDomainService::nextAvailableSlug($appName, null, $customSubdomain);

        return strtolower("{$slug}.{$appsDomain}");
    }

    /**
     * Pre-flight DNS verification: checks if a custom domain points to the server ingress IP.
     */
    public static function verifyDomainDns(string $domain, string $expectedIp): bool
    {
        if (! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return false;
        }

        // Check A records
        $records = @dns_get_record($domain, DNS_A);
        if ($records && is_array($records)) {
            foreach ($records as $record) {
                if (($record['ip'] ?? null) === $expectedIp) {
                    return true;
                }
            }
        }

        // CNAME records
        $cnameRecords = @dns_get_record($domain, DNS_CNAME);
        if ($cnameRecords && is_array($cnameRecords)) {
            foreach ($cnameRecords as $cname) {
                $target = rtrim((string) ($cname['target'] ?? ''), '.');
                if ($target === '' || ! filter_var($target, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                    continue;
                }
                $targetRecords = @dns_get_record($target, DNS_A);
                foreach ((array) $targetRecords as $record) {
                    if (($record['ip'] ?? null) === $expectedIp) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Provision a Custom Git Application under one of the 3 paid fixed-spec plans.
     */
    public static function provision(
        Team $team,
        User $user,
        ProductVariant $variant,
        string $appName,
        string $gitRepo,
        string $gitBranch = 'main',
        string $buildPack = 'nixpacks',
        array $envVars = [],
        ?string $customSubdomain = null,
        ?int $serverId = null
    ): ServiceInstance {
        $limits = self::getPlanLimits($variant);
        $hostname = self::generateHostname($appName, $customSubdomain);

        $clusterNode = null;
        try {
            $clusterNode = ClusterPlacementService::selectOptimalNode(
                $team,
                $appName,
                null,
                $variant,
                $serverId
            );
        } catch (\Throwable) {
            $clusterNode = null;
        }

        $server = $serverId ? Server::find($serverId) : ($clusterNode?->server ?: null);
        if (! $server && method_exists(Server::class, 'isUsable')) {
            try {
                $server = Server::isUsable()->first();
            } catch (\Throwable) {
                $server = null;
            }
        }

        $service = ServiceInstance::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'cluster_node_id' => $clusterNode?->id,
            'service_type' => 'git_app',
            'status' => 'active',
            'health_status' => 'healthy',
            'name' => $appName,
            'hostname' => $hostname,
            'ip_address' => $clusterNode?->public_ip ?: ($server?->ip ?: config('hosting.ingress_ip')),
            'os_image' => $buildPack,
            'network_config' => [
                'cluster_node_id' => $clusterNode?->id,
                'cluster_node_name' => $clusterNode?->name,
                'server_id' => $server?->id ?: $clusterNode?->server_id,
                'server_name' => $server?->name ?: $clusterNode?->name,
                'server_ip' => $server?->ip ?: $clusterNode?->public_ip,
                'git_repo' => $gitRepo,
                'git_branch' => $gitBranch,
                'build_pack' => $buildPack,
                'env_vars' => $envVars,
                'cgroup_limits' => $limits,
                'custom_domains' => [],
            ],
            'activated_at' => now(),
            'renews_at' => now()->addDays(30),
        ]);

        CommerceAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'action' => 'app.git_provisioned',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'payload' => [
                'plan_variant' => $variant->slug,
                'git_repo' => $gitRepo,
                'git_branch' => $gitBranch,
                'build_pack' => $buildPack,
                'cgroup_limits' => $limits,
                'hostname' => $hostname,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        return $service;
    }

    /**
     * Provision a 1-Click Curated Template Application.
     */
    public static function provisionTemplate(
        Team $team,
        User $user,
        string $templateName,
        string $appName,
        array $envVars = [],
        ?ProductVariant $variant = null,
        ?string $customSubdomain = null,
        array $selectedEndpoints = [],
        ?int $serverId = null
    ): ServiceInstance {
        if (! TemplateAccessService::canDeployTemplate($team, $templateName)) {
            throw new PlatformRestrictionException("Your team is not authorized to deploy template '{$templateName}'.");
        }

        $requirements = TemplateAccessService::getTemplateRequirements($templateName);
        $limits = $variant ? self::getPlanLimits($variant) : self::getPlanLimits($requirements);
        $hostname = self::generateHostname($appName);

        $clusterNode = null;
        try {
            $clusterNode = ClusterPlacementService::selectOptimalNode(
                $team,
                $appName,
                $templateName,
                $variant,
                $serverId
            );
        } catch (\Throwable) {
            $clusterNode = null;
        }

        $server = $serverId ? Server::find($serverId) : ($clusterNode?->server ?: null);
        if (! $server && method_exists(Server::class, 'isUsable')) {
            try {
                $server = Server::isUsable()->first();
            } catch (\Throwable) {
                $server = null;
            }
        }

        $service = ServiceInstance::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_variant_id' => $variant?->id,
            'cluster_node_id' => $clusterNode?->id,
            'service_type' => 'template_app',
            'template_name' => $templateName,
            'status' => 'active',
            'health_status' => 'healthy',
            'name' => $appName,
            'hostname' => $hostname,
            'ip_address' => $clusterNode?->public_ip ?: ($server?->ip ?: config('hosting.ingress_ip')),
            'os_image' => $templateName,
            'network_config' => [
                'cluster_node_id' => $clusterNode?->id,
                'cluster_node_name' => $clusterNode?->name,
                'server_id' => $server?->id ?: $clusterNode?->server_id,
                'server_name' => $server?->name ?: $clusterNode?->name,
                'server_ip' => $server?->ip ?: $clusterNode?->public_ip,
                'template_name' => $templateName,
                'port' => $requirements['default_port'] ?? 80,
                'env_vars' => $envVars,
                'cgroup_limits' => $limits,
                'custom_domains' => [],
            ],
            'activated_at' => now(),
            'renews_at' => now()->addDays(30),
        ]);

        // Automatically assign subdomains & route bindings for exposable services
        PlatformDomainService::assignTemplateSubdomains(
            $service,
            $templateName,
            $customSubdomain,
            $selectedEndpoints
        );

        $service->refresh();

        CommerceAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'action' => 'app.template_provisioned',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'payload' => [
                'template_name' => $templateName,
                'cgroup_limits' => $limits,
                'hostname' => $service->hostname,
                'assigned_endpoints' => data_get($service->network_config, 'assigned_endpoints', []),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        return $service;
    }

    /**
     * Duplicate an existing application instance with pre-populated configurations.
     */
    public static function duplicateApp(
        ServiceInstance $source,
        string $newName,
        ?string $newSubdomain = null,
        array $overrideEnvVars = [],
        ?int $newVariantId = null,
        ?string $newGitBranch = null,
        ?int $newServerId = null
    ): ServiceInstance {
        $team = $source->team;
        $user = auth()->user() ?: $source->user;
        $variant = $newVariantId ? ProductVariant::findOrFail($newVariantId) : $source->productVariant;
        $config = $source->network_config ?? [];
        $targetServerId = $newServerId ?: data_get($config, 'server_id');

        $mergedEnvVars = array_merge(
            data_get($config, 'env_vars', []),
            $overrideEnvVars
        );

        if ($source->service_type === 'template_app' && $source->template_name) {
            $selectedEndpoints = array_keys(data_get($config, 'assigned_endpoints', []));

            $newService = self::provisionTemplate(
                $team,
                $user,
                $source->template_name,
                $newName,
                $mergedEnvVars,
                $variant,
                $newSubdomain,
                $selectedEndpoints,
                $targetServerId
            );
        } else {
            $gitRepo = data_get($config, 'git_repo', '');
            $gitBranch = $newGitBranch ?: data_get($config, 'git_branch', 'main');
            $buildPack = data_get($config, 'build_pack', $source->os_image ?: 'nixpacks');

            $newService = self::provision(
                $team,
                $user,
                $variant ?: ProductVariant::first(),
                $newName,
                $gitRepo,
                $gitBranch,
                $buildPack,
                $mergedEnvVars,
                $newSubdomain,
                $targetServerId
            );
        }

        CommerceAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'action' => 'app.duplicated',
            'target_type' => ServiceInstance::class,
            'target_id' => $newService->id,
            'payload' => [
                'source_uuid' => $source->uuid,
                'source_name' => $source->name,
                'new_name' => $newName,
                'hostname' => $newService->hostname,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        return $newService;
    }
}

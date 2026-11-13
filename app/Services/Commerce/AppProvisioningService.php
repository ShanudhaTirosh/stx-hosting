<?php

namespace App\Services\Commerce;

use App\Exceptions\PlatformRestrictionException;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceInstance;
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
    public static function generateHostname(string $appName): string
    {
        $slug = Str::slug($appName);
        if (blank($slug)) {
            $slug = 'app-'.Str::lower(Str::random(6));
        }

        $appsDomain = PlatformDomainService::appsDomain();
        if ($appsDomain) {
            $baseDomain = $appsDomain;
        } else {
            $baseDomain = config('hosting.default_domain', 'apps.stxapp.io');
        }

        $candidate = "{$slug}.{$baseDomain}";

        // Ensure uniqueness if a service instance already uses this candidate
        if (ServiceInstance::where('hostname', $candidate)->exists()) {
            $candidate = "{$slug}-".Str::lower(Str::random(4)).".{$baseDomain}";
        }

        return strtolower($candidate);
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
        array $envVars = []
    ): ServiceInstance {
        $limits = self::getPlanLimits($variant);
        $hostname = self::generateHostname($appName);

        $service = ServiceInstance::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'service_type' => 'git_app',
            'status' => 'active',
            'health_status' => 'healthy',
            'name' => $appName,
            'hostname' => $hostname,
            'os_image' => $buildPack,
            'network_config' => [
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
        ?ProductVariant $variant = null
    ): ServiceInstance {
        if (! TemplateAccessService::canDeployTemplate($team, $templateName)) {
            throw new PlatformRestrictionException("Your team is not authorized to deploy template '{$templateName}'.");
        }

        $requirements = TemplateAccessService::getTemplateRequirements($templateName);
        $limits = $variant ? self::getPlanLimits($variant) : self::getPlanLimits($requirements);
        $hostname = self::generateHostname($appName);

        $service = ServiceInstance::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_variant_id' => $variant?->id,
            'service_type' => 'template_app',
            'template_name' => $templateName,
            'status' => 'active',
            'health_status' => 'healthy',
            'name' => $appName,
            'hostname' => $hostname,
            'os_image' => $templateName,
            'network_config' => [
                'template_name' => $templateName,
                'port' => $requirements['default_port'] ?? 80,
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
            'action' => 'app.template_provisioned',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'payload' => [
                'template_name' => $templateName,
                'cgroup_limits' => $limits,
                'hostname' => $hostname,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        return $service;
    }
}

<?php

namespace App\Services\Commerce;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceInstance;
use App\Models\Team;
use App\Models\User;
use App\Services\Coolify\CoolifyApiClient;
use Illuminate\Support\Facades\Log;

class AppProvisioningService
{
    /**
     * Calculate strict Docker cgroups limits based on product variant specs.
     */
    public static function getPlanLimits(ProductVariant $variant): array
    {
        $ramMb = $variant->ram_mb ?: 512;
        $vcpu = $variant->vcpu ?: 1;

        return [
            'limits_memory' => "{$ramMb}m",
            'limits_memory_swap' => (int) ($ramMb / 2) . 'm',
            'limits_cpus' => (string) $vcpu,
            'limits_cpu_shares' => $vcpu * 1024,
        ];
    }

    /**
     * Pre-flight DNS verification: checks if a custom domain points to the server ingress IP.
     */
    public static function verifyDomainDns(string $domain, string $expectedIp): bool
    {
        // Basic hostname validation
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

        // Check CNAME records
        $cnameRecords = @dns_get_record($domain, DNS_CNAME);
        if ($cnameRecords && is_array($cnameRecords)) {
            return true;
        }

        return false;
    }

    /**
     * Provision a container application service instance.
     */
    public static function provision(
        Team $team,
        User $user,
        ProductVariant $variant,
        string $appName,
        string $gitRepo,
        string $gitBranch = 'main',
        string $buildPack = 'nixpacks'
    ): ServiceInstance {
        $limits = self::getPlanLimits($variant);

        $service = ServiceInstance::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'service_type' => 'web_app',
            'status' => 'active',
            'name' => $appName,
            'hostname' => strtolower(preg_replace('/[^a-zA-Z0-9-]/', '', $appName)) . '.stxapp.io',
            'os_image' => $buildPack,
            'activated_at' => now(),
            'renews_at' => now()->addDays(30),
        ]);

        CommerceAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'action' => 'app.provisioned',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'payload' => [
                'git_repo' => $gitRepo,
                'git_branch' => $gitBranch,
                'build_pack' => $buildPack,
                'cgroup_limits' => $limits,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        return $service;
    }
}


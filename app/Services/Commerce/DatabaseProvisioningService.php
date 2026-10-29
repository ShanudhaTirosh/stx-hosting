<?php

namespace App\Services\Commerce;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceCredential;
use App\Models\Commerce\ServiceInstance;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;

class DatabaseProvisioningService
{
    /**
     * Provision a managed standalone database service instance.
     */
    public static function provision(
        Team $team,
        User $user,
        ProductVariant $variant,
        string $databaseName,
        string $engine = 'postgresql'
    ): ServiceInstance {
        $dbPassword = Str::random(32);
        $dbUser = 'dbuser_' . strtolower(Str::random(6));
        $cleanDbName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $databaseName)) ?: 'appdb';

        $port = match ($engine) {
            'mysql' => 3306,
            'redis' => 6379,
            default => 5432,
        };

        $internalHost = "db-{$engine}-{$cleanDbName}.stx-internal";

        $service = ServiceInstance::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'service_type' => 'database',
            'status' => 'active',
            'name' => "{$databaseName} (" . ucfirst($engine) . ")",
            'hostname' => $internalHost,
            'ip_address' => '127.0.0.1',
            'os_image' => "{$engine}:latest",
            'activated_at' => now(),
            'renews_at' => now()->addDays(30),
        ]);

        // Store encrypted database password
        $cred = new ServiceCredential();
        $cred->service_instance_id = $service->id;
        $cred->credential_type = 'db_password';
        $cred->username = $dbUser;
        $cred->setEncryptedSecretAttribute($dbPassword);
        $cred->ssh_port = $port;
        $cred->save();

        CommerceAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'action' => 'database.provisioned',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'payload' => [
                'engine' => $engine,
                'db_name' => $cleanDbName,
                'db_user' => $dbUser,
                'port' => $port,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        return $service;
    }
}


<?php

namespace App\Jobs\Commerce;

use App\Actions\Application\StopApplication;
use App\Actions\Database\StopDatabase;
use App\Actions\Service\StopService;
use App\Enums\EntitlementStatus;
use App\Models\Application;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\MailMailbox;
use App\Models\Commerce\MailSubscription;
use App\Models\Commerce\ServiceInstance;
use App\Models\HostingEntitlement;
use App\Models\Service;
use App\Models\User;
use App\Notifications\Commerce\ServiceSuspendedNotification;
use App\Services\Backups\DatabaseCatalog;
use App\Services\Commerce\WildDuckProvisioningService;
use App\Services\EntitlementService;
use App\Services\Notifications\StaffEvents;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOverdueSuspensionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Grace period threshold: 4 days past expiration
        $cutoff = now()->subDays(4);

        $overdue = HostingEntitlement::with('team')
            ->where('status', EntitlementStatus::ACTIVE->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $cutoff)
            ->get();

        $suspendedCount = 0;

        foreach ($overdue as $entitlement) {
            $team = $entitlement->team;
            if (! $team) {
                continue;
            }

            try {
                // 1. Transition entitlement status to SUSPENDED
                $entitlement->update([
                    'status' => EntitlementStatus::SUSPENDED->value,
                ]);

                // 2. Check if team has any other active entitlement for this product type
                $productType = $entitlement->product_type;
                $hasOtherActive = HostingEntitlement::where('team_id', $team->id)
                    ->where('id', '!=', $entitlement->id)
                    ->where('status', EntitlementStatus::ACTIVE->value)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->where('product_type', $productType)
                    ->exists();

                // 3. Stop containers if no other active entitlement covers the capability
                if (! $hasOtherActive) {
                    if (str_contains((string) $productType, 'app')) {
                        $applications = Application::whereHas('environment.project', fn ($q) => $q->where('team_id', $team->id))->get();
                        foreach ($applications as $app) {
                            if (str_starts_with((string) $app->status, 'running')) {
                                try {
                                    StopApplication::run($app);
                                } catch (\Throwable $e) {
                                    Log::warning("Failed to stop application {$app->uuid} during suspension: {$e->getMessage()}");
                                }
                            }
                        }

                        $services = Service::whereHas('environment.project', fn ($q) => $q->where('team_id', $team->id))->get();
                        foreach ($services as $svc) {
                            try {
                                StopService::run($svc);
                            } catch (\Throwable $e) {
                                Log::warning("Failed to stop service {$svc->uuid} during suspension: {$e->getMessage()}");
                            }
                        }
                    }

                    if (str_contains((string) $productType, 'database')) {
                        $databases = DatabaseCatalog::forTeam($team);
                        foreach ($databases as $db) {
                            if (DatabaseCatalog::isRunning($db)) {
                                try {
                                    StopDatabase::run($db);
                                } catch (\Throwable $e) {
                                    Log::warning("Failed to stop database {$db->uuid} during suspension: {$e->getMessage()}");
                                }
                            }
                        }
                    }
                }

                // 4. Handle VPS instances
                if (str_contains((string) $productType, 'vps')) {
                    $orderItemUuid = $entitlement->metadata['order_item_uuid'] ?? null;
                    $query = ServiceInstance::where('team_id', $team->id)->where('status', 'active');
                    if ($orderItemUuid) {
                        $query->whereHas('order.items', fn ($q) => $q->where('uuid', $orderItemUuid));
                    }
                    $vpsInstances = $query->get();
                    foreach ($vpsInstances as $vps) {
                        $vps->update([
                            'status' => 'suspended',
                            'suspended_at' => now(),
                        ]);
                    }
                }

                // 5. Handle Email Hosting instances
                if (str_contains((string) $productType, 'email') || str_contains((string) $productType, 'mail')) {
                    $mailSubs = MailSubscription::where('team_id', $team->id)->where('status', 'active')->get();
                    $wildduck = app(WildDuckProvisioningService::class);
                    foreach ($mailSubs as $sub) {
                        $sub->update(['status' => 'suspended']);
                    }
                    $boxes = MailMailbox::where('team_id', $team->id)->where('is_active', true)->get();
                    foreach ($boxes as $box) {
                        try {
                            $wildduck->updateMailboxActive($box->wildduck_user_id, false);
                            $box->update(['is_active' => false]);
                        } catch (\Throwable $e) {
                            Log::warning("Failed to suspend mailbox {$box->address}: {$e->getMessage()}");
                        }
                    }
                }

                CommerceAuditLog::create([
                    'team_id' => $team->id,
                    'user_id' => $entitlement->granted_by,
                    'action' => 'entitlement.suspended',
                    'target_type' => HostingEntitlement::class,
                    'target_id' => $entitlement->id,
                    'payload' => [
                        'reason' => 'overdue_grace_period_expired',
                        'cutoff' => $cutoff->toIso8601String(),
                        'product_type' => $productType,
                    ],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'cron/ProcessOverdueSuspensionsJob',
                    'created_at' => now(),
                ]);

                // 5. Notify customer
                $user = User::find($entitlement->granted_by) ?? $team->members()->first();
                if ($user) {
                    try {
                        $user->notify(new ServiceSuspendedNotification(
                            ucwords(str_replace('_', ' ', (string) $productType)),
                            'Payment is overdue and the 4-day grace period has elapsed.'
                        ));
                    } catch (\Throwable $e) {
                        Log::warning("Could not send ServiceSuspendedNotification to user {$user->id}: {$e->getMessage()}");
                    }
                }

                $suspendedCount++;
            } catch (\Throwable $e) {
                Log::error("Failed to suspend entitlement #{$entitlement->id}: {$e->getMessage()}");
            }
        }

        // Process direct overdue MailSubscriptions
        $overdueMailSubs = MailSubscription::with('team')
            ->where('status', 'active')
            ->whereNotNull('renews_at')
            ->where('renews_at', '<', $cutoff)
            ->get();

        $wildduck = app(WildDuckProvisioningService::class);

        foreach ($overdueMailSubs as $mailSub) {
            $team = $mailSub->team;
            if (! $team) {
                continue;
            }

            try {
                $mailSub->update(['status' => 'suspended']);

                $boxes = MailMailbox::where('team_id', $team->id)->where('is_active', true)->get();
                foreach ($boxes as $box) {
                    try {
                        $wildduck->updateMailboxActive($box->wildduck_user_id, false);
                        $box->update(['is_active' => false]);
                    } catch (\Throwable $e) {
                        Log::warning("Failed to disable mailbox {$box->address} during overdue suspension: {$e->getMessage()}");
                    }
                }

                CommerceAuditLog::create([
                    'team_id' => $team->id,
                    'user_id' => $team->members()->first()?->id,
                    'action' => 'mail_subscription.suspended',
                    'target_type' => MailSubscription::class,
                    'target_id' => $mailSub->id,
                    'payload' => [
                        'reason' => 'overdue_grace_period_expired',
                        'cutoff' => $cutoff->toIso8601String(),
                        'renews_at' => $mailSub->renews_at?->toIso8601String(),
                    ],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'cron/ProcessOverdueSuspensionsJob',
                    'created_at' => now(),
                ]);

                $user = $team->members()->first();
                if ($user) {
                    try {
                        $user->notify(new ServiceSuspendedNotification(
                            'Business Email Hosting',
                            'Payment is overdue and the 4-day grace period has elapsed. Email accounts have been paused.'
                        ));
                    } catch (\Throwable $e) {
                        Log::warning("Could not send ServiceSuspendedNotification for mail sub to user {$user->id}: {$e->getMessage()}");
                    }
                }

                $suspendedCount++;
            } catch (\Throwable $e) {
                Log::error("Failed to suspend mail subscription #{$mailSub->id}: {$e->getMessage()}");
            }
        }

        if ($suspendedCount > 0) {
            StaffEvents::notify(
                StaffEvents::SERVICE_UNHEALTHY,
                'Services suspended for non-payment',
                "Suspended {$suspendedCount} service(s)/entitlement(s) past grace period.",
                ['Suspended Count' => (string) $suspendedCount],
                'error',
                'suspensions:'.now()->toDateString()
            );
        }
    }
}


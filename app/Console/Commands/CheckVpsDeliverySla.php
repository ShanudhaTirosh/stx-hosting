<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Notifications\Commerce\VpsDeliveryOverdueNotification;
use App\Services\Commerce\VpsFulfillmentService;
use App\Services\Notifications\StaffEvents;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckVpsDeliverySla extends Command
{
    protected $signature = 'vps:check-delivery-sla';

    protected $description = 'Remind staff when a paid VPS order has waited 24, 48 or 72 hours for delivery';

    public function handle(): int
    {
        $staff = Team::find(0)?->members()->wherePivotIn('role', ['owner', 'admin'])->get() ?? collect();
        $sent = 0;

        VpsFulfillmentService::pendingQuery()->with(['invoice', 'user'])->get()->each(function ($order) use ($staff, &$sent) {
            $level = VpsFulfillmentService::alertLevel($order);

            // One reminder per level, never repeated.
            if ($level === 0 || $level <= (int) $order->delivery_alert_hours) {
                return;
            }

            $order->forceFill(['delivery_alert_hours' => $level])->save();

            StaffEvents::notify(StaffEvents::DELIVERY_DUE, $level >= 72 ? 'VPS delivery OVERDUE' : "VPS waiting {$level} hours", 'A paid VPS order has not been delivered yet.', [
                'Customer' => (string) ($order->user?->email ?? 'unknown'),
                'Order' => (string) $order->uuid,
            ], $level >= 72 ? 'error' : 'warning', "delivery:{$order->id}:{$level}");

            if ($staff->isNotEmpty()) {
                Notification::send($staff, new VpsDeliveryOverdueNotification($order, $level));
            }

            $sent++;
        });

        $this->info("Sent {$sent} delivery reminder(s).");

        return self::SUCCESS;
    }
}

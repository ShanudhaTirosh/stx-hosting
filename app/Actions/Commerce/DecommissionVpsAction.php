<?php

namespace App\Actions\Commerce;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\ServiceInstance;
use App\Models\Commerce\VpsInventory;
use App\Services\Commerce\StatusTelemetryBridge;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DecommissionVpsAction
{
    use AsAction;

    public function handle(ServiceInstance $service, string $reason = 'Customer cancellation or term expiration'): void
    {
        DB::transaction(function () use ($service, $reason) {
            $service = ServiceInstance::where('id', $service->id)->lockForUpdate()->firstOrFail();

            if ($service->status === 'terminated') {
                return;
            }

            // 1. Mark service terminated
            $service->update([
                'status' => 'terminated',
            ]);

            // 2. If tied to an inventory node, return to pool
            if ($service->vps_inventory_id) {
                $inventory = VpsInventory::find($service->vps_inventory_id);
                if ($inventory) {
                    $inventory->update([
                        'status' => 'available',
                        'admin_notes' => "Decommissioned on " . now()->toIso8601String() . " [Reason: {$reason}]",
                    ]);
                }
            }

            // 3. Decommission status telemetry probe
            if ($service->ip_address) {
                StatusTelemetryBridge::deactivateProbe($service->ip_address);
            }

            // 4. Audit Log
            CommerceAuditLog::create([
                'user_id' => auth()->id(),
                'team_id' => $service->team_id,
                'action' => 'service.decommission',
                'resource_type' => 'service_instance',
                'resource_id' => $service->id,
                'payload' => [
                    'ip_address' => $service->ip_address,
                    'reason' => $reason,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);
        });
    }
}


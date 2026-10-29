<?php

namespace App\Livewire\Admin\Operations;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\Order;
use App\Models\Commerce\OrderItem;
use App\Models\Commerce\ServiceCredential;
use App\Models\Commerce\ServiceInstance;
use App\Models\Commerce\VpsInventory;
use App\Services\Commerce\StatusTelemetryBridge;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class ProvisioningQueue extends Component
{
    use WithPagination;

    public ?int $selectedOrderId = null;
    public ?int $selectedNodeId = null;
    public string $temporaryRootPassword = '';
    public string $osImage = 'debian-12';

    public function selectOrder(int $orderId): void
    {
        $this->selectedOrderId = $orderId;
        $this->selectedNodeId = null;
        $this->temporaryRootPassword = '';
        $this->osImage = 'debian-12';
    }

    public function fulfill(int $orderId): void
    {
        $this->validate([
            'selectedNodeId' => 'required|exists:vps_inventory,id',
            'temporaryRootPassword' => 'required|string|min:8|max:64',
            'osImage' => 'required|string|max:64',
        ]);

        try {
            DB::transaction(function () use ($orderId) {
                $order = Order::with(['items.productVariant', 'user', 'team'])->findOrFail($orderId);
                $node = VpsInventory::where('id', $this->selectedNodeId)->lockForUpdate()->firstOrFail();

                if ($node->status !== 'available') {
                    throw new \Exception("Selected node ({$node->ipv4_address}) is no longer available.");
                }

                $firstItem = $order->items->first();
                $variant = $firstItem ? $firstItem->productVariant : null;

                // 1. Create or activate ServiceInstance
                $service = ServiceInstance::create([
                    'team_id' => $order->team_id,
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'product_variant_id' => $variant ? $variant->id : 1,
                    'vps_inventory_id' => $node->id,
                    'service_type' => 'vps',
                    'status' => 'active',
                    'name' => ($firstItem && $firstItem->hostname) ? $firstItem->hostname : "VPS-{$node->ipv4_address}",
                    'hostname' => ($firstItem && $firstItem->hostname) ? $firstItem->hostname : "node-{$node->id}.shanutechx.internal",
                    'ip_address' => $node->ipv4_address,
                    'ipv6_address' => $node->ipv6_address,
                    'os_image' => $this->osImage,
                    'activated_at' => now(),
                    'renews_at' => now()->addDays(30),
                ]);

                // 2. Encrypt and store root credentials
                $cred = new ServiceCredential();
                $cred->service_instance_id = $service->id;
                $cred->credential_type = 'root_password';
                $cred->username = 'root';
                $cred->setEncryptedSecretAttribute($this->temporaryRootPassword);
                $cred->ssh_port = 22;
                $cred->save();

                // 3. Update inventory node status
                $node->update(['status' => 'allocated']);

                // 4. Update order status if all fulfilled
                $order->update(['status' => 'completed']);

                // 5. Register with Status telemetry platform
                StatusTelemetryBridge::registerVpsProbe($service);

                // 6. Record audit log
                CommerceAuditLog::create([
                    'user_id' => auth()->id(),
                    'team_id' => $order->team_id,
                    'action' => 'service.vps_provisioned',
                    'target_type' => ServiceInstance::class,
                    'target_id' => $service->id,
                    'payload' => [
                        'ip' => $service->ip_address,
                        'hostname' => $service->hostname,
                        'os_image' => $service->os_image,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                ]);
            });

            $this->dispatch('success', 'VPS provisioned and credentials stored. Status probe registered!');
            $this->selectedOrderId = null;
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Fulfillment failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
        // Query orders that are completed or processing and lack an active service
        $pendingOrders = Order::with(['items.productVariant', 'user', 'invoice'])
            ->whereIn('status', ['completed', 'processing'])
            ->whereDoesntHave('services', function ($q) {
                $q->where('status', 'active');
            })
            ->latest()
            ->paginate(10);

        $selectedOrder = null;
        $matchingNodes = collect();

        if ($this->selectedOrderId) {
            $selectedOrder = Order::with(['items.productVariant.pricingTiers', 'user'])->find($this->selectedOrderId);
            if ($selectedOrder) {
                $item = $selectedOrder->items->first();
                $reqRam = $item && $item->productVariant ? $item->productVariant->ram_mb : 1024;
                $reqVcpu = $item && $item->productVariant ? $item->productVariant->vcpu : 1;

                $matchingNodes = VpsInventory::where('status', 'available')
                    ->where('ram_mb', '>=', $reqRam)
                    ->where('vcpu', '>=', $reqVcpu)
                    ->get();
            }
        }

        return view('livewire.admin.operations.provisioning-queue', [
            'pendingOrders' => $pendingOrders,
            'selectedOrder' => $selectedOrder,
            'matchingNodes' => $matchingNodes,
        ]);
    }
}

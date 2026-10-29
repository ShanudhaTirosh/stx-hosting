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
    public string $fulfillmentMode = 'direct'; // 'direct' or 'pool'

    // Direct manual entry fields
    public string $manual_ipv4 = '';
    public string $manual_ipv6 = '';
    public int $manual_ssh_port = 22;
    public string $manual_provider = 'Custom Provider';
    public string $manual_datacenter = 'Germany';

    // Pool allocation fields
    public ?int $selectedNodeId = null;

    // Common fields
    public string $temporaryRootPassword = '';
    public string $osImage = 'debian-12';

    public function selectOrder(int $orderId): void
    {
        $this->selectedOrderId = $orderId;
        $this->selectedNodeId = null;
        $this->manual_ipv4 = '';
        $this->manual_ipv6 = '';
        $this->manual_ssh_port = 22;
        $this->manual_provider = 'Custom Provider';
        $this->manual_datacenter = 'Germany';
        $this->temporaryRootPassword = '';
        $this->osImage = 'debian-12';
        $this->fulfillmentMode = 'direct';
    }

    public function fulfill(int $orderId): void
    {
        if ($this->fulfillmentMode === 'pool') {
            $this->validate([
                'selectedNodeId' => 'required|exists:vps_inventory,id',
                'temporaryRootPassword' => 'required|string|min:6|max:128',
                'osImage' => 'required|string|max:64',
            ]);
        } else {
            $this->validate([
                'manual_ipv4' => 'required|ip',
                'manual_ipv6' => 'nullable|string|max:45',
                'manual_ssh_port' => 'required|integer|min:1|max:65535',
                'manual_provider' => 'required|string|max:64',
                'manual_datacenter' => 'required|string|max:64',
                'temporaryRootPassword' => 'required|string|min:6|max:128',
                'osImage' => 'required|string|max:64',
            ]);
        }

        try {
            DB::transaction(function () use ($orderId) {
                $order = Order::with(['items.productVariant', 'user', 'team'])->findOrFail($orderId);
                $firstItem = $order->items->first();
                $variant = $firstItem ? $firstItem->productVariant : null;

                $allocatedIpv4 = '';
                $allocatedIpv6 = null;
                $sshPort = 22;
                $vpsInventoryId = null;

                if ($this->fulfillmentMode === 'pool') {
                    $node = VpsInventory::where('id', $this->selectedNodeId)->lockForUpdate()->firstOrFail();

                    if ($node->status !== 'available') {
                        throw new \Exception("Selected node ({$node->ipv4_address}) is no longer available.");
                    }

                    $allocatedIpv4 = $node->ipv4_address;
                    $allocatedIpv6 = $node->ipv6_address;
                    $vpsInventoryId = $node->id;
                    $node->update(['status' => 'allocated']);
                } else {
                    $allocatedIpv4 = $this->manual_ipv4;
                    $allocatedIpv6 = $this->manual_ipv6 ?: null;
                    $sshPort = $this->manual_ssh_port;

                    // Automatically record in inventory tracking
                    $node = VpsInventory::firstOrCreate(
                        ['ipv4_address' => $allocatedIpv4],
                        [
                            'ipv6_address' => $allocatedIpv6,
                            'provider' => $this->manual_provider,
                            'datacenter' => $this->manual_datacenter,
                            'vcpu' => $variant ? $variant->vcpu : 2,
                            'ram_mb' => $variant ? $variant->ram_mb : 4096,
                            'disk_gb' => $variant ? $variant->disk_gb : 40,
                            'status' => 'allocated',
                            'admin_notes' => "Manually ordered from {$this->manual_provider}",
                        ]
                    );
                    $vpsInventoryId = $node->id;
                }

                // 1. Create or activate ServiceInstance
                $service = ServiceInstance::create([
                    'team_id' => $order->team_id,
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'product_variant_id' => $variant ? $variant->id : 1,
                    'vps_inventory_id' => $vpsInventoryId,
                    'service_type' => 'vps',
                    'status' => 'active',
                    'name' => ($firstItem && $firstItem->hostname) ? $firstItem->hostname : "VPS-{$allocatedIpv4}",
                    'hostname' => ($firstItem && $firstItem->hostname) ? $firstItem->hostname : "node-{$allocatedIpv4}.shanutechx.internal",
                    'ip_address' => $allocatedIpv4,
                    'ipv6_address' => $allocatedIpv6,
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
                $cred->ssh_port = $sshPort;
                $cred->save();

                // 3. Update order status
                $order->update(['status' => 'completed']);

                // 4. Register with Status telemetry platform
                StatusTelemetryBridge::registerVpsProbe($service);

                // 4b. Notify customer of active VPS delivery
                if ($order->user) {
                    try {
                        $order->user->notify(new \App\Notifications\Commerce\ServiceDeliveredNotification($service));
                    } catch (\Throwable $e) {
                        logger()->warning("Could not send ServiceDeliveredNotification: {$e->getMessage()}");
                    }
                }

                // 5. Record audit log
                CommerceAuditLog::create([
                    'user_id' => auth()->id(),
                    'team_id' => $order->team_id,
                    'action' => 'service.vps_provisioned',
                    'target_type' => ServiceInstance::class,
                    'target_id' => $service->id,
                    'payload' => [
                        'mode' => $this->fulfillmentMode,
                        'ip' => $service->ip_address,
                        'port' => $sshPort,
                        'hostname' => $service->hostname,
                        'os_image' => $service->os_image,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                ]);
            });

            $this->dispatch('success', 'VPS details delivered to customer! Status probe registered.');
            $this->selectedOrderId = null;
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Fulfillment failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
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
            if ($selectedOrder && $this->fulfillmentMode === 'pool') {
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

<?php

namespace App\Livewire\Admin\Inventory;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\VpsInventory;
use Livewire\Component;
use Livewire\WithPagination;

class VpsInventoryStudio extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    // New node form state
    public bool $showCreateModal = false;

    public string $ipv4_address = '';

    public string $ipv6_address = '';

    public string $gateway = '';

    public string $subnet_mask = '';

    public string $mac_address = '';

    public int $vcpu = 2;

    public int $ram_mb = 4096;

    public int $disk_gb = 40;

    public string $datacenter = 'fsn1';

    public string $provider = 'hetzner';

    public string $admin_notes = '';

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->isInstanceAdmin() ||
                $user->isAdmin() ||
                (($teamId = currentTeam()?->id) && $user->isAdminOfTeam($teamId)) ||
                $user->teams()->wherePivotIn('role', ['admin', 'owner'])->exists()
            ),
            403
        );
    }

    public function openCreateModal(): void
    {
        $this->reset([
            'ipv4_address', 'ipv6_address', 'gateway', 'subnet_mask',
            'mac_address', 'vcpu', 'ram_mb', 'disk_gb', 'admin_notes',
        ]);
        $this->vcpu = 2;
        $this->ram_mb = 4096;
        $this->disk_gb = 40;
        $this->datacenter = 'fsn1';
        $this->provider = 'hetzner';
        $this->showCreateModal = true;
    }

    public function saveNode(): void
    {
        $this->validate([
            'ipv4_address' => 'required|ip|unique:vps_inventory,ipv4_address',
            'ipv6_address' => 'nullable|string|max:45',
            'gateway' => 'nullable|string|max:45',
            'subnet_mask' => 'nullable|string|max:45',
            'mac_address' => 'nullable|string|max:32',
            'vcpu' => 'required|integer|min:1|max:128',
            'ram_mb' => 'required|integer|min:512|max:524288',
            'disk_gb' => 'required|integer|min:10|max:10000',
            'datacenter' => 'required|string|max:64',
            'provider' => 'required|string|max:64',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $node = VpsInventory::create([
            'ipv4_address' => $this->ipv4_address,
            'ipv6_address' => $this->ipv6_address ?: null,
            'gateway' => $this->gateway ?: null,
            'subnet_mask' => $this->subnet_mask ?: null,
            'mac_address' => $this->mac_address ?: null,
            'vcpu' => $this->vcpu,
            'ram_mb' => $this->ram_mb,
            'disk_gb' => $this->disk_gb,
            'datacenter' => $this->datacenter,
            'provider' => $this->provider,
            'status' => 'available',
            'admin_notes' => $this->admin_notes ?: null,
        ]);

        CommerceAuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'vps_inventory.created',
            'target_type' => VpsInventory::class,
            'target_id' => $node->id,
            'payload' => [
                'ipv4' => $node->ipv4_address,
                'vcpu' => $node->vcpu,
                'ram_mb' => $node->ram_mb,
                'datacenter' => $node->datacenter,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->showCreateModal = false;
        $this->dispatch('success', 'VPS node registered into pre-provisioned pool.');
    }

    public function setStatus(int $nodeId, string $newStatus): void
    {
        $node = VpsInventory::findOrFail($nodeId);
        $oldStatus = $node->status;
        $node->update(['status' => $newStatus]);

        CommerceAuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'vps_inventory.status_changed',
            'target_type' => VpsInventory::class,
            'target_id' => $node->id,
            'payload' => ['old_status' => $oldStatus, 'new_status' => $newStatus],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->dispatch('success', "Node status changed to {$newStatus}.");
    }

    public function render()
    {
        $query = VpsInventory::query()->latest();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('ipv4_address', 'like', "%{$this->search}%")
                    ->orWhere('datacenter', 'like', "%{$this->search}%")
                    ->orWhere('provider', 'like', "%{$this->search}%");
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $nodes = $query->paginate(20);

        return view('livewire.admin.inventory.vps-inventory-studio', [
            'nodes' => $nodes,
        ]);
    }
}

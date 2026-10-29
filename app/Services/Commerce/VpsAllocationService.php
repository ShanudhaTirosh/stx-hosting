<?php

namespace App\Services\Commerce;

use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\VpsInventory;

class VpsAllocationService
{
    /**
     * Atomically locate and reserve the next matching available node from the inventory pool.
     */
    public function allocateNextAvailable(ProductVariant $variant, string $datacenter = 'fsn1'): VpsInventory
    {
        return VpsInventory::where('status', 'available')
            ->where('datacenter', $datacenter)
            ->where('ram_mb', '>=', $variant->ram_mb)
            ->where('vcpu', '>=', $variant->vcpu)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Mark an inventory node as allocated to a specific service.
     */
    public function markAllocated(VpsInventory $node): void
    {
        $node->update([
            'status' => 'allocated',
        ]);
    }

    /**
     * Deallocate a node back into maintenance or available status.
     */
    public function releaseNode(VpsInventory $node, string $newStatus = 'available'): void
    {
        $node->update([
            'status' => $newStatus,
        ]);
    }
}

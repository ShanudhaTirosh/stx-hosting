<div class="p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-700">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">VPS Provisioning Queue & Delivery Studio</h1>
            <p class="text-sm text-neutral-400">Allocate nodes from pre-provisioned pool and deliver credentials to customers within 24-72h SLA.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Order Queue (Left Col) -->
        <div class="{{ $selectedOrder ? 'lg:col-span-5' : 'lg:col-span-12' }} space-y-3">
            <h2 class="text-xs font-bold uppercase tracking-wider text-neutral-400">Orders Awaiting Server Allocation</h2>

            @forelse ($pendingOrders as $order)
                <div 
                    wire:click="selectOrder({{ $order->id }})"
                    class="p-4 rounded-lg border cursor-pointer transition-all {{ $selectedOrderId === $order->id ? 'bg-neutral-800 border-purple-500 shadow-md' : 'bg-neutral-900 border-neutral-800 hover:border-neutral-700' }}"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-xs font-semibold text-neutral-300">
                            {{ $order->invoice->invoice_number ?? "ORDER-{$order->uuid}" }}
                        </span>
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/20">
                            Ready to Allocate
                        </span>
                    </div>

                    <div class="mt-2 text-sm text-white font-medium">
                        {{ $order->user->email ?? 'N/A' }}
                    </div>

                    <div class="mt-1 text-xs text-neutral-400">
                        @if ($item = $order->items->first())
                            <span class="text-purple-300 font-bold">{{ $item->productVariant->name ?? 'VPS Plan' }}</span>
                            @if ($item->hostname)
                                <span class="text-neutral-500 font-mono"> ({{ $item->hostname }})</span>
                            @endif
                        @endif
                    </div>

                    <div class="mt-2 text-[11px] text-neutral-500">
                        Paid {{ $order->created_at->diffForHumans() }}
                    </div>
                </div>
            @empty
                <div class="p-8 text-center bg-neutral-900 border border-neutral-800 rounded-lg text-neutral-500">
                    No orders currently awaiting server provisioning.
                </div>
            @endforelse

            <div class="pt-2">
                {{ $pendingOrders->links() }}
            </div>
        </div>

        <!-- Allocation & Fulfillment Studio (Right Col) -->
        @if ($selectedOrder)
            <div class="lg:col-span-7 bg-neutral-900 border border-neutral-800 rounded-lg p-6 space-y-6">
                <div class="border-b border-neutral-800 pb-4">
                    <h2 class="text-lg font-bold text-white">Allocate Node for Order #{{ $selectedOrder->id }}</h2>
                    <p class="text-xs text-neutral-400">Customer: {{ $selectedOrder->user->email }}</p>
                </div>

                <!-- Customer Order Requirements -->
                <div class="bg-neutral-950 p-4 rounded border border-neutral-800 space-y-3">
                    <h3 class="text-xs font-bold uppercase text-neutral-400">Requested Specifications</h3>
                    @if ($item = $selectedOrder->items->first())
                        <div class="grid grid-cols-2 gap-3 text-xs font-mono">
                            <div>
                                <span class="text-neutral-500 block">Plan:</span>
                                <span class="text-white font-bold">{{ $item->productVariant->name ?? 'Standard' }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-500 block">Min Hardware:</span>
                                <span class="text-purple-400 font-bold">{{ $item->productVariant->vcpu ?? 1 }} vCPU / {{ ($item->productVariant->ram_mb ?? 1024)/1024 }} GB RAM</span>
                            </div>
                            <div>
                                <span class="text-neutral-500 block">Requested Hostname:</span>
                                <span class="text-white">{{ $item->hostname ?: 'node.customer.internal' }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-500 block">Preferred DC:</span>
                                <span class="text-white uppercase">{{ $item->datacenter ?: 'fsn1' }}</span>
                            </div>
                        </div>

                        @if ($item->public_ssh_key)
                            <div class="mt-2 pt-2 border-t border-neutral-800">
                                <span class="text-neutral-500 text-[11px] block">Customer Public SSH Key:</span>
                                <div class="bg-neutral-900 p-2 rounded text-[10px] font-mono text-neutral-300 break-all select-all">
                                    {{ $item->public_ssh_key }}
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                <!-- Available Inventory Matches -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase text-neutral-400">Select Available Inventory Node</h3>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        @forelse ($matchingNodes as $node)
                            <label class="flex items-center justify-between p-3 rounded border cursor-pointer {{ $selectedNodeId === $node->id ? 'bg-purple-950/30 border-purple-500' : 'bg-neutral-950 border-neutral-800 hover:border-neutral-700' }}">
                                <div class="flex items-center gap-3">
                                    <input type="radio" wire:model.live="selectedNodeId" value="{{ $node->id }}" class="radio radio-xs radio-primary" />
                                    <span class="font-mono text-xs font-bold text-white">{{ $node->ipv4_address }}</span>
                                    <span class="text-[11px] text-neutral-400">({{ $node->vcpu }} vCPU, {{ $node->ram_mb / 1024 }}GB RAM)</span>
                                </div>
                                <span class="text-[10px] uppercase font-mono px-2 py-0.5 rounded bg-neutral-800 text-neutral-300">
                                    {{ $node->datacenter }}
                                </span>
                            </label>
                        @empty
                            <div class="p-4 bg-neutral-950 border border-amber-500/20 rounded text-amber-400 text-xs">
                                No available nodes matching these hardware specs. Please register a new node in the Inventory Studio.
                            </div>
                        @endforelse
                    </div>
                    @error('selectedNodeId') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                </div>

                <!-- Credential Assignment Form -->
                <div class="grid grid-cols-2 gap-4 text-xs bg-neutral-950 p-4 rounded border border-neutral-800">
                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Temporary Root Password *</label>
                        <input 
                            type="text" 
                            wire:model="temporaryRootPassword" 
                            placeholder="Initial password for guest OS..." 
                            class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white rounded font-mono"
                        />
                        @error('temporaryRootPassword') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Installed OS Image</label>
                        <select wire:model="osImage" class="select select-sm w-full bg-neutral-900 border-neutral-700 text-white rounded">
                            <option value="debian-12">Debian 12 (Bookworm)</option>
                            <option value="ubuntu-24-04">Ubuntu 24.04 LTS (Noble)</option>
                            <option value="almalinux-9">AlmaLinux 9</option>
                            <option value="rocky-9">Rocky Linux 9</option>
                        </select>
                    </div>
                </div>

                <div class="pt-4 border-t border-neutral-800 flex justify-end">
                    <button 
                        wire:click="fulfill({{ $selectedOrder->id }})"
                        class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded shadow transition-all flex items-center gap-2 text-xs"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Activate & Deliver Service
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

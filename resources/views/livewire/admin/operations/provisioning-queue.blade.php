<div class="p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-700">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Manual VPS Delivery & Provisioning Queue</h1>
            <p class="text-sm text-neutral-400">Order from your VPS provider and manually deliver connection credentials to the customer within the 24-72h SLA.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Order Queue (Left Col) -->
        <div class="{{ $selectedOrder ? 'lg:col-span-5' : 'lg:col-span-12' }} space-y-3">
            <h2 class="text-xs font-bold uppercase tracking-wider text-neutral-400">Orders Awaiting VPS Delivery</h2>

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
                            Ready to Deliver
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

        <!-- Manual VPS Delivery Studio (Right Col) -->
        @if ($selectedOrder)
            <div class="lg:col-span-7 bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-6 shadow-2xl">
                <div class="border-b border-neutral-800 pb-4 flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-bold text-white">Manual VPS Delivery: Order #{{ $selectedOrder->id }}</h2>
                        <p class="text-xs text-neutral-400">Customer: {{ $selectedOrder->user->email }}</p>
                    </div>

                    <!-- Mode Toggle -->
                    <div class="bg-neutral-950 p-1 rounded-lg border border-neutral-800 inline-flex">
                        <button 
                            wire:click="$set('fulfillmentMode', 'direct')" 
                            class="px-3 py-1 rounded text-xs font-bold font-mono transition-all {{ $fulfillmentMode === 'direct' ? 'bg-purple-600 text-white shadow' : 'text-neutral-400 hover:text-white' }}"
                        >
                            Direct Details Entry
                        </button>
                        <button 
                            wire:click="$set('fulfillmentMode', 'pool')" 
                            class="px-3 py-1 rounded text-xs font-bold font-mono transition-all {{ $fulfillmentMode === 'pool' ? 'bg-purple-600 text-white shadow' : 'text-neutral-400 hover:text-white' }}"
                        >
                            From Inventory Pool
                        </button>
                    </div>
                </div>

                <!-- Customer Order Specs Card -->
                <div class="bg-neutral-950 p-4 rounded-lg border border-neutral-800 space-y-3">
                    <h3 class="text-xs font-bold uppercase text-neutral-400">Customer Plan & Hardware Requested</h3>
                    @if ($item = $selectedOrder->items->first())
                        <div class="grid grid-cols-2 gap-3 text-xs font-mono">
                            <div>
                                <span class="text-neutral-500 block">Plan:</span>
                                <span class="text-white font-bold">{{ $item->productVariant->name ?? 'Standard' }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-500 block">Specs:</span>
                                <span class="text-purple-400 font-bold">{{ $item->productVariant->vcpu ?? 2 }} vCPU / {{ ($item->productVariant->ram_mb ?? 4096)/1024 }} GB RAM / {{ $item->productVariant->disk_gb ?? 40 }} GB NVMe</span>
                            </div>
                            <div>
                                <span class="text-neutral-500 block">Customer Hostname:</span>
                                <span class="text-white">{{ $item->hostname ?: 'node.customer.internal' }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-500 block">Location:</span>
                                <span class="text-white uppercase">{{ $item->datacenter ?: 'Germany' }}</span>
                            </div>
                        </div>

                        @if ($item->public_ssh_key)
                            <div class="mt-2 pt-2 border-t border-neutral-800">
                                <span class="text-neutral-500 text-[11px] block">Customer Public SSH Key (inject into VPS if needed):</span>
                                <div class="bg-neutral-900 p-2 rounded text-[10px] font-mono text-neutral-300 break-all select-all">
                                    {{ $item->public_ssh_key }}
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                @if ($fulfillmentMode === 'direct')
                    <!-- Direct Manual Entry Form -->
                    <div class="bg-neutral-950 p-4 rounded-lg border border-neutral-800 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold uppercase text-purple-400 tracking-wider">Fill Purchased VPS Connection Details</h3>
                            <span class="text-[10px] text-neutral-500 font-mono">Ordered from your provider</span>
                        </div>

                        <div class="grid grid-cols-2 gap-4 text-xs">
                            <div>
                                <label class="block font-bold text-neutral-300 mb-1">Assigned Public IPv4 Address *</label>
                                <input 
                                    type="text" 
                                    wire:model="manual_ipv4" 
                                    placeholder="e.g. 198.51.100.25" 
                                    class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white font-mono rounded"
                                />
                                @error('manual_ipv4') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block font-bold text-neutral-300 mb-1">SSH Port</label>
                                <input 
                                    type="number" 
                                    wire:model="manual_ssh_port" 
                                    placeholder="22" 
                                    class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white font-mono rounded"
                                />
                            </div>

                            <div>
                                <label class="block font-bold text-neutral-300 mb-1">VPS Provider / Host</label>
                                <input 
                                    type="text" 
                                    wire:model="manual_provider" 
                                    placeholder="e.g. Leaseweb, Contabo, OVH, DigitalOcean..." 
                                    class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white rounded"
                                />
                            </div>

                            <div>
                                <label class="block font-bold text-neutral-300 mb-1">Datacenter / Country</label>
                                <input 
                                    type="text" 
                                    wire:model="manual_datacenter" 
                                    placeholder="e.g. Germany, Finland, Singapore, USA..." 
                                    class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white rounded"
                                />
                            </div>

                            <div>
                                <label class="block font-bold text-neutral-300 mb-1">Assigned Root Password *</label>
                                <input 
                                    type="text" 
                                    wire:model="temporaryRootPassword" 
                                    placeholder="Root password given by provider..." 
                                    class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white font-mono rounded"
                                />
                                @error('temporaryRootPassword') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block font-bold text-neutral-300 mb-1">Installed Operating System</label>
                                <select wire:model="osImage" class="select select-sm w-full bg-neutral-900 border-neutral-700 text-white rounded">
                                    <option value="debian-12">Debian 12 (Bookworm)</option>
                                    <option value="ubuntu-24-04">Ubuntu 24.04 LTS (Noble)</option>
                                    <option value="ubuntu-22-04">Ubuntu 22.04 LTS (Jammy)</option>
                                    <option value="almalinux-9">AlmaLinux 9</option>
                                    <option value="rocky-9">Rocky Linux 9</option>
                                    <option value="windows-server">Windows Server</option>
                                </select>
                            </div>

                            <div class="col-span-2">
                                <label class="block font-bold text-neutral-300 mb-1">Optional IPv6 Address</label>
                                <input 
                                    type="text" 
                                    wire:model="manual_ipv6" 
                                    placeholder="2a01:4f8:..." 
                                    class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white font-mono rounded"
                                />
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Inventory Pool Selection -->
                    <div class="space-y-3">
                        <h3 class="text-xs font-bold uppercase text-neutral-400">Select From Pre-Registered Pool</h3>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            @forelse ($matchingNodes as $node)
                                <label class="flex items-center justify-between p-3 rounded border cursor-pointer {{ $selectedNodeId === $node->id ? 'bg-purple-950/30 border-purple-500' : 'bg-neutral-950 border-neutral-800 hover:border-neutral-700' }}">
                                    <div class="flex items-center gap-3">
                                        <input type="radio" wire:model.live="selectedNodeId" value="{{ $node->id }}" class="radio radio-xs radio-primary" />
                                        <span class="font-mono text-xs font-bold text-white">{{ $node->ipv4_address }}</span>
                                        <span class="text-[11px] text-neutral-400">({{ $node->vcpu }} vCPU, {{ $node->ram_mb / 1024 }}GB RAM)</span>
                                    </div>
                                    <span class="text-[10px] uppercase font-mono px-2 py-0.5 rounded bg-neutral-800 text-neutral-300">
                                        {{ $node->datacenter }} ({{ $node->provider }})
                                    </span>
                                </label>
                            @empty
                                <div class="p-4 bg-neutral-950 border border-amber-500/20 rounded text-amber-400 text-xs">
                                    No pre-registered nodes available. Switch to "Direct Details Entry" above to type the purchased VPS details on the spot.
                                </div>
                            @endforelse
                        </div>
                        @error('selectedNodeId') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror

                        <div>
                            <label class="block font-bold text-neutral-300 mb-1 text-xs">Assigned Root Password *</label>
                            <input 
                                type="text" 
                                wire:model="temporaryRootPassword" 
                                placeholder="Root password for customer..." 
                                class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white font-mono rounded text-xs"
                            />
                        </div>
                    </div>
                @endif

                <div class="pt-4 border-t border-neutral-800 flex justify-end">
                    <button 
                        wire:click="fulfill({{ $selectedOrder->id }})"
                        class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded shadow transition-all flex items-center gap-2 text-xs"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Deliver VPS & Credentials to Customer
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

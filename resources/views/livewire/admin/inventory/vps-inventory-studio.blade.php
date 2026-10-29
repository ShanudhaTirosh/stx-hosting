<div class="p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-700">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">VPS Pre-Provisioned Inventory Pool</h1>
            <p class="text-sm text-neutral-400">Manage ready-to-allocate hardware nodes and virtual slices for 24-72h fulfillment.</p>
        </div>

        <div class="flex items-center gap-3">
            <button 
                wire:click="openCreateModal"
                class="px-4 py-2 text-xs font-bold text-white bg-purple-600 hover:bg-purple-500 rounded-md shadow flex items-center gap-1.5 transition-all"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Register New Node
            </button>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="flex flex-col sm:flex-row gap-4 justify-between items-center bg-neutral-900 p-4 rounded-lg border border-neutral-800">
        <div class="w-full sm:w-72">
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search by IP, DC or Provider..." 
                class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white text-xs rounded"
            />
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-neutral-400 uppercase">Status:</span>
            <select wire:model.live="statusFilter" class="select select-sm bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                <option value="all">All Statuses</option>
                <option value="available">Available</option>
                <option value="reserved">Reserved</option>
                <option value="allocated">Allocated</option>
                <option value="maintenance">Maintenance</option>
            </select>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-lg overflow-hidden shadow">
        <table class="w-full text-left text-xs text-neutral-300">
            <thead class="bg-neutral-950 text-neutral-400 uppercase text-[10px] tracking-wider border-b border-neutral-800 font-mono">
                <tr>
                    <th class="p-4">Public IPv4</th>
                    <th class="p-4">Hardware Specs</th>
                    <th class="p-4">Location / DC</th>
                    <th class="p-4">Provider</th>
                    <th class="p-4">Status</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-800">
                @forelse ($nodes as $node)
                    <tr class="hover:bg-neutral-800/50 transition-all font-mono">
                        <td class="p-4 font-bold text-white">
                            {{ $node->ipv4_address }}
                            @if ($node->ipv6_address)
                                <span class="block text-[10px] text-neutral-500 font-normal truncate max-w-[140px]">{{ $node->ipv6_address }}</span>
                            @endif
                        </td>
                        <td class="p-4">
                            <span class="text-purple-400 font-bold">{{ $node->vcpu }} vCPU</span> / 
                            <span>{{ $node->ram_mb / 1024 }} GB RAM</span> / 
                            <span>{{ $node->disk_gb }} GB NVMe</span>
                        </td>
                        <td class="p-4 uppercase">
                            <span class="px-2 py-0.5 rounded bg-neutral-800 border border-neutral-700 text-neutral-300 text-[10px]">
                                {{ $node->datacenter }}
                            </span>
                        </td>
                        <td class="p-4 uppercase text-neutral-400">
                            {{ $node->provider }}
                        </td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-full {{ 
                                $node->status === 'available' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 
                                ($node->status === 'allocated' ? 'bg-purple-500/10 text-purple-400 border border-purple-500/20' : 
                                ($node->status === 'reserved' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-neutral-700/30 text-neutral-400 border border-neutral-600')) 
                            }}">
                                {{ $node->status }}
                            </span>
                        </td>
                        <td class="p-4 text-right space-x-1">
                            @if ($node->status === 'available')
                                <button wire:click="setStatus({{ $node->id }}, 'maintenance')" class="px-2 py-1 bg-neutral-800 hover:bg-neutral-700 text-amber-400 rounded text-[10px]">
                                    Set Maintenance
                                </button>
                            @elseif ($node->status === 'maintenance')
                                <button wire:click="setStatus({{ $node->id }}, 'available')" class="px-2 py-1 bg-emerald-950 hover:bg-emerald-900 text-emerald-400 rounded text-[10px]">
                                    Make Available
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-neutral-500">
                            No VPS inventory nodes registered yet. Click "Register New Node" to add slices.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="p-4 border-t border-neutral-800">
            {{ $nodes->links() }}
        </div>
    </div>

    <!-- Registration Modal -->
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-4">
            <div class="bg-neutral-900 border border-neutral-700 rounded-xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                    <h3 class="text-lg font-bold text-white">Register VPS Inventory Node</h3>
                    <button wire:click="$set('showCreateModal', false)" class="text-neutral-400 hover:text-white">&times;</button>
                </div>

                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div class="col-span-2">
                        <label class="block font-bold text-neutral-300 mb-1">Public IPv4 Address *</label>
                        <input type="text" wire:model="ipv4_address" placeholder="198.51.100.25" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded font-mono" />
                        @error('ipv4_address') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">vCPU Cores</label>
                        <input type="number" wire:model="vcpu" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded font-mono" />
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">RAM (MB)</label>
                        <input type="number" wire:model="ram_mb" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded font-mono" />
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">NVMe Disk (GB)</label>
                        <input type="number" wire:model="disk_gb" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded font-mono" />
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Datacenter</label>
                        <select wire:model="datacenter" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded">
                            <option value="fsn1">fsn1 (Falkenstein, DE)</option>
                            <option value="nbg1">nbg1 (Nuremberg, DE)</option>
                            <option value="hel1">hel1 (Helsinki, FI)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Gateway</label>
                        <input type="text" wire:model="gateway" placeholder="198.51.100.1" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded font-mono" />
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Provider</label>
                        <select wire:model="provider" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded">
                            <option value="hetzner">Hetzner Cloud</option>
                            <option value="digitalocean">DigitalOcean</option>
                            <option value="vultr">Vultr</option>
                            <option value="custom">Custom / Dedicated</option>
                        </select>
                    </div>

                    <div class="col-span-2">
                        <label class="block font-bold text-neutral-300 mb-1">Admin Notes</label>
                        <textarea wire:model="admin_notes" rows="2" class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded" placeholder="Hypervisor host ID, rack info, or network notes..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-neutral-800">
                    <button wire:click="$set('showCreateModal', false)" class="px-4 py-2 bg-neutral-800 text-neutral-300 rounded text-xs hover:bg-neutral-700">Cancel</button>
                    <button wire:click="saveNode" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded text-xs">Save & Make Available</button>
                </div>
            </div>
        </div>
    @endif
</div>

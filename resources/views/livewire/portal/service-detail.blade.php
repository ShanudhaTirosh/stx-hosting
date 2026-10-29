<div class="max-w-5xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-800">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('portal.services') }}" class="text-xs text-purple-400 hover:text-purple-300 font-semibold">&larr; Back to My Services</a>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight mt-1">{{ $service->hostname ?: $service->name }}</h1>
            <p class="text-xs text-neutral-400 font-mono">Service UUID: {{ $service->uuid }}</p>
        </div>

        <span class="px-3 py-1 text-xs font-bold uppercase rounded-full {{ 
            $service->status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
        }}">
            {{ $service->status }}
        </span>
    </div>

    <!-- Quick SSH Connection String & Credentials Card -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4 shadow-lg">
        <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-300">SSH & Access Credentials</h2>
        
        <div class="bg-neutral-950 p-4 rounded-lg border border-neutral-800 space-y-3 font-mono text-xs">
            <div class="flex items-center justify-between">
                <span class="text-neutral-500">Connection Command:</span>
                <span class="text-purple-300 select-all font-bold">ssh root@{{ $service->ip_address }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-neutral-500">SSH Port:</span>
                <span class="text-white">22</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-neutral-500">Default Username:</span>
                <span class="text-white">root</span>
            </div>
            <div class="flex items-center justify-between pt-2 border-t border-neutral-850">
                <span class="text-neutral-500">Root Password:</span>
                @if ($revealedPassword)
                    <div class="flex items-center gap-2">
                        <span class="bg-purple-950/60 px-3 py-1 rounded text-purple-300 font-bold select-all border border-purple-500/30">
                            {{ $revealedPassword }}
                        </span>
                        <span class="text-[10px] text-neutral-400 font-sans">(Store securely)</span>
                    </div>
                @else
                    <button 
                        wire:click="openPasswordModal"
                        class="px-3 py-1 bg-neutral-800 hover:bg-neutral-700 text-white font-sans text-xs rounded transition-all"
                    >
                        Reveal Temporary Root Password &rarr;
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Technical Hardware Specifications -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-400">Allocated Hardware Resources</h3>
            <div class="divide-y divide-neutral-800 text-xs font-mono">
                <div class="py-2.5 flex justify-between">
                    <span class="text-neutral-500">vCPU Allocation:</span>
                    <span class="text-white font-bold">{{ $service->productVariant->vcpu ?? 2 }} Dedicated vCPU</span>
                </div>
                <div class="py-2.5 flex justify-between">
                    <span class="text-neutral-500">Memory (RAM):</span>
                    <span class="text-white font-bold">{{ ($service->productVariant->ram_mb ?? 4096)/1024 }} GB ECC RAM</span>
                </div>
                <div class="py-2.5 flex justify-between">
                    <span class="text-neutral-500">Storage:</span>
                    <span class="text-white font-bold">{{ $service->productVariant->disk_gb ?? 40 }} GB NVMe SSD</span>
                </div>
                <div class="py-2.5 flex justify-between">
                    <span class="text-neutral-500">Operating System:</span>
                    <span class="text-purple-300 font-bold">{{ $service->os_image }}</span>
                </div>
            </div>
        </div>

        <!-- Reverse DNS PTR Request -->
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-3 flex flex-col justify-between">
            <div class="space-y-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-400">Reverse DNS (PTR Record)</h3>
                <p class="text-xs text-neutral-400 font-sans">Set custom PTR reverse lookup for your allocated IPv4 address (`{{ $service->ip_address }}`).</p>
                <div class="pt-2">
                    <input 
                        type="text" 
                        wire:model="ptrRecord" 
                        placeholder="e.g. mail.yourdomain.com" 
                        class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white text-xs font-mono rounded"
                    />
                    @error('ptrRecord') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="pt-4 border-t border-neutral-800">
                <button 
                    wire:click="requestPtr" 
                    class="w-full py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded transition-all"
                >
                    Submit Reverse DNS Update
                </button>
            </div>
        </div>
    </div>

    <!-- Password Modal -->
    @if ($showPasswordModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="bg-neutral-900 border border-neutral-700 rounded-xl max-w-sm w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">Confirm Account Password</h3>
                    <button wire:click="$set('showPasswordModal', false)" class="text-neutral-400 hover:text-white">&times;</button>
                </div>

                <p class="text-xs text-neutral-400">
                    To decrypt and view the temporary root password for this server, please confirm your current account password:
                </p>

                <div>
                    <input 
                        type="password" 
                        wire:model="accountPassword" 
                        placeholder="Your account password..." 
                        class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white text-xs rounded"
                    />
                    @error('accountPassword') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-neutral-800">
                    <button wire:click="$set('showPasswordModal', false)" class="px-3 py-1.5 bg-neutral-800 text-neutral-300 rounded text-xs">Cancel</button>
                    <button wire:click="revealRootPassword" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded text-xs">Decrypt & Reveal</button>
                </div>
            </div>
        </div>
    @endif
</div>


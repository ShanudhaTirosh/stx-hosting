<div class="max-w-5xl mx-auto p-6 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-neutral-800">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('portal.services') }}" class="text-xs text-purple-400 hover:text-purple-300 font-semibold">&larr; Back to My Services</a>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight mt-1">{{ $service->hostname ?: $service->name }}</h1>
            <p class="text-xs text-neutral-400 font-mono">Service UUID: {{ $service->uuid }} &bull; {{ $service->productVariant->name ?? 'VPS' }}</p>
        </div>

        <div class="flex items-center gap-2">
            @if ($service->service_type === 'vps' && $service->status === 'active')
                <a href="{{ route('portal.vps', ['uuid' => $service->uuid]) }}" class="px-3.5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-lg shadow transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Terminal, Files &amp; Usage
                </a>
            @endif

            <a href="{{ route('portal.support') }}?subject={{ urlencode('Assistance with VPS ' . ($service->hostname ?: $service->ip_address)) }}" class="px-3 py-2 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 hover:text-white font-semibold text-xs rounded-lg border border-neutral-700 transition-all">
                Get Support
            </a>

            <span class="px-3 py-1 text-xs font-bold uppercase rounded-full {{ 
                $service->status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 
                ($service->status === 'suspended' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20')
            }}">
                {{ $service->status }}
            </span>
        </div>
    </div>

    @if ($service->status === 'suspended')
        <div class="p-4 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-300 text-xs flex flex-wrap items-center justify-between gap-3">
            <div>
                <strong class="font-bold text-sm block">VPS Service Suspended (Invoice Overdue)</strong>
                <p class="text-rose-200/80 mt-0.5">Your server management tools are locked due to an overdue renewal invoice. Your VM remains intact upstream.</p>
            </div>
            @if ($service->order?->invoice)
                <a href="{{ route('portal.invoices.show', ['uuid' => $service->order->invoice->uuid]) }}" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white font-bold rounded-lg text-xs shadow">
                    Pay Invoice to Reactivate &rarr;
                </a>
            @endif
        </div>
    @endif

    <!-- SSH & Access Credentials Card -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-5 shadow-lg">
        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-neutral-800">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-200">SSH & Access Credentials</h2>
                <p class="text-xs text-neutral-400">Connection strings for OpenSSH, PuTTY, FileZilla, Termius, and WinSCP.</p>
            </div>
            <button 
                wire:click="checkSshConnectivity" 
                wire:loading.attr="disabled"
                class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-purple-300 font-semibold text-xs rounded border border-neutral-700 flex items-center gap-1.5 transition-all"
            >
                <span wire:loading.remove wire:target="checkSshConnectivity">Test SSH Connection &amp; Auth</span>
                <span wire:loading wire:target="checkSshConnectivity">Testing Connection...</span>
            </button>
        </div>

        @if ($sshCheckStatus)
            <div class="p-3.5 rounded-lg text-xs font-mono border {{ 
                $sshCheckStatus === 'healthy' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' : 
                ($sshCheckStatus === 'panel_only' ? 'bg-purple-500/10 border-purple-500/20 text-purple-300' : 
                ($sshCheckStatus === 'out_of_sync' ? 'bg-amber-500/10 border-amber-500/30 text-amber-300' : 'bg-rose-500/10 border-rose-500/30 text-rose-300')) 
            }} flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm">{{ $sshCheckStatus === 'healthy' || $sshCheckStatus === 'panel_only' ? '✓' : '⚠️' }}</span>
                    <span>{{ $sshCheckMessage }}</span>
                </div>
                @if ($sshCheckStatus === 'out_of_sync')
                    <button wire:click="openUpdatePasswordModal" class="px-3 py-1 bg-amber-600 hover:bg-amber-500 text-white font-sans font-bold text-xs rounded transition-all">
                        Update Changed Password &rarr;
                    </button>
                @endif
            </div>
        @endif

        @php
            $port = $service->network_config['ssh_port'] ?? 22;
            $openSshCmd = "ssh root@{$service->ip_address}" . ($port != 22 ? " -p {$port}" : '');
            $puttyStr = "{$service->ip_address}:{$port}";
            $sftpUrl = "sftp://root@{$service->ip_address}:{$port}";
        @endphp

        <!-- Quick Connection Presets -->
        <div class="space-y-3 font-mono text-xs">
            <div class="bg-neutral-950 p-3.5 rounded-lg border border-neutral-800 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="text-neutral-500 shrink-0 font-sans font-bold text-[11px] uppercase">OpenSSH:</span>
                    <span class="text-purple-300 font-bold truncate select-all">{{ $openSshCmd }}</span>
                </div>
                <button 
                    onclick="navigator.clipboard.writeText('{{ $openSshCmd }}'); this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 2000)"
                    class="px-2.5 py-1 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-[11px] rounded font-sans shrink-0"
                >Copy</button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="bg-neutral-950 p-3 rounded-lg border border-neutral-800 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <span class="text-neutral-500 font-sans text-[10px] uppercase block">PuTTY / Termius Host:</span>
                        <span class="text-white font-bold truncate select-all">{{ $puttyStr }}</span>
                    </div>
                    <button 
                        onclick="navigator.clipboard.writeText('{{ $puttyStr }}'); this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 2000)"
                        class="px-2 py-0.5 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-[10px] rounded font-sans"
                    >Copy</button>
                </div>

                <div class="bg-neutral-950 p-3 rounded-lg border border-neutral-800 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <span class="text-neutral-500 font-sans text-[10px] uppercase block">FileZilla / WinSCP SFTP URL:</span>
                        <span class="text-white font-bold truncate select-all">{{ $sftpUrl }}</span>
                    </div>
                    <button 
                        onclick="navigator.clipboard.writeText('{{ $sftpUrl }}'); this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 2000)"
                        class="px-2 py-0.5 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-[10px] rounded font-sans"
                    >Copy</button>
                </div>
            </div>
        </div>

        <!-- Breakdown Fields -->
        <div class="bg-neutral-950 p-4 rounded-lg border border-neutral-800 divide-y divide-neutral-850 font-mono text-xs">
            <div class="py-2 flex items-center justify-between">
                <span class="text-neutral-500 font-sans">IPv4 Address:</span>
                <div class="flex items-center gap-2">
                    <span class="text-white select-all font-bold">{{ $service->ip_address ?: 'Pending' }}</span>
                    <button onclick="navigator.clipboard.writeText('{{ $service->ip_address }}')" class="text-[10px] text-purple-400 hover:text-purple-300 font-sans">Copy</button>
                </div>
            </div>

            @if (!empty($service->ipv6_address) || !empty($service->network_config['ipv6']))
                <div class="py-2 flex items-center justify-between">
                    <span class="text-neutral-500 font-sans">IPv6 Address:</span>
                    <span class="text-white select-all">{{ $service->ipv6_address ?: $service->network_config['ipv6'] }}</span>
                </div>
            @endif

            <div class="py-2 flex items-center justify-between">
                <span class="text-neutral-500 font-sans">SSH Port:</span>
                <span class="text-white">{{ $port }}</span>
            </div>

            <div class="py-2 flex items-center justify-between">
                <span class="text-neutral-500 font-sans">Default User:</span>
                <span class="text-white">root</span>
            </div>

            <div class="py-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span class="text-neutral-500 font-sans block">Root Password:</span>
                    @if ($service->is_customer_managed_credentials)
                        <span class="text-amber-400 text-xs font-sans">Customer-Managed (Not stored in vault)</span>
                    @elseif ($revealedPassword)
                        <div class="flex items-center gap-2 mt-1">
                            <span class="bg-purple-950/80 px-3 py-1 rounded text-purple-300 font-bold select-all border border-purple-500/30 text-xs">
                                {{ $revealedPassword }}
                            </span>
                            <button 
                                onclick="navigator.clipboard.writeText('{{ $revealedPassword }}'); this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 2000)"
                                class="px-2 py-1 bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-sans rounded"
                            >Copy</button>
                        </div>
                    @else
                        <span class="text-neutral-500 tracking-widest text-sm">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    @unless ($service->is_customer_managed_credentials)
                        @if (! $revealedPassword)
                            <button 
                                wire:click="openPasswordModal"
                                class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-white font-sans text-xs rounded transition-all"
                            >
                                Reveal Password &rarr;
                            </button>
                        @endif
                        <button 
                            wire:click="openUpdatePasswordModal"
                            class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-purple-300 font-sans text-xs rounded transition-all"
                        >
                            Update Password
                        </button>
                        <button 
                            wire:click="markCustomerManaged"
                            wire:confirm="Switch to Customer-Managed? This will erase the stored root password from our database. Panel tools will continue working via our panel key."
                            class="px-2.5 py-1.5 text-neutral-400 hover:text-neutral-300 font-sans text-[11px]"
                        >
                            Mark Customer-Managed
                        </button>
                    @else
                        <button 
                            wire:click="openUpdatePasswordModal"
                            class="px-3 py-1.5 bg-purple-600 hover:bg-purple-500 text-white font-sans text-xs font-bold rounded transition-all"
                        >
                            Store Updated Password in Vault
                        </button>
                    @endunless
                </div>
            </div>
        </div>
    </div>

    <!-- Authorized SSH Public Keys Section -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4 shadow-lg">
        <div class="flex items-center justify-between pb-3 border-b border-neutral-800">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-200">Authorized SSH Public Keys</h2>
                <p class="text-xs text-neutral-400">Install your own public keys into /root/.ssh/authorized_keys for passwordless SSH logins.</p>
            </div>
            <button 
                wire:click="openKeyModal"
                class="px-3 py-1.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded shadow transition-all flex items-center gap-1.5"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add SSH Key
            </button>
        </div>

        <div class="space-y-2">
            @forelse ($service->authorizedKeys as $key)
                <div class="bg-neutral-950 p-3.5 rounded-lg border border-neutral-800 flex items-center justify-between gap-3 text-xs font-mono">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-white font-bold">{{ $key->name }}</span>
                            <span class="text-[10px] text-neutral-500">SHA256:{{ $key->fingerprint_sha256 }}</span>
                        </div>
                        <span class="text-[11px] text-neutral-400 truncate max-w-md block select-all">{{ substr($key->public_key, 0, 48) }}...</span>
                    </div>
                    <button wire:click="deleteAuthorizedKey({{ $key->id }})" class="text-rose-400 hover:text-rose-300 font-sans text-xs">Remove</button>
                </div>
            @empty
                <p class="text-xs text-neutral-500">No custom SSH public keys registered. Add your public key above to sign in without typing passwords.</p>
            @endforelse
        </div>
    </div>

    <!-- Technical Hardware Specifications & Reverse DNS -->
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
                <div class="py-2.5 flex justify-between">
                    <span class="text-neutral-500">Location:</span>
                    <span class="text-neutral-300">{{ $service->network_config['datacenter'] ?? 'Global Cloud Region' }}</span>
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

    <!-- Password Reveal Modal -->
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
                    <button wire:click="revealRootPassword" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded text-xs">Decrypt &amp; Reveal</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Update Changed Password Modal (with Live Pre-Flight Auth Verification) -->
    @if ($showUpdatePasswordModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="bg-neutral-900 border border-neutral-700 rounded-xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">Update Changed Server Password</h3>
                    <button wire:click="$set('showUpdatePasswordModal', false)" class="text-neutral-400 hover:text-white">&times;</button>
                </div>

                <p class="text-xs text-neutral-400">
                    If you changed your root password directly on the server (e.g. via <code class="text-purple-300 font-mono">passwd</code>), enter the new password below. We test authentication live before updating your credentials vault.
                </p>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-neutral-300 mb-1">New Root Password</label>
                        <input 
                            type="password" 
                            wire:model="newRootPassword" 
                            placeholder="Enter current root password on the server..." 
                            class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white text-xs rounded font-mono"
                        />
                        @error('newRootPassword') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-300 mb-1">Confirm New Root Password</label>
                        <input 
                            type="password" 
                            wire:model="confirmNewRootPassword" 
                            placeholder="Confirm password..." 
                            class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white text-xs rounded font-mono"
                        />
                        @error('confirmNewRootPassword') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-neutral-800">
                    <button wire:click="$set('showUpdatePasswordModal', false)" class="px-3 py-1.5 bg-neutral-800 text-neutral-300 rounded text-xs">Cancel</button>
                    <button 
                        wire:click="updateChangedPassword" 
                        wire:loading.attr="disabled"
                        class="px-4 py-1.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white font-bold rounded text-xs flex items-center gap-2"
                    >
                        <span wire:loading.remove wire:target="updateChangedPassword">Test &amp; Save to Vault</span>
                        <span wire:loading wire:target="updateChangedPassword">Verifying with Server...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Add SSH Public Key Modal -->
    @if ($showKeyModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="bg-neutral-900 border border-neutral-700 rounded-xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">Install Public SSH Key</h3>
                    <button wire:click="$set('showKeyModal', false)" class="text-neutral-400 hover:text-white">&times;</button>
                </div>

                <p class="text-xs text-neutral-400">
                    This will append your public key directly to <code class="text-purple-300 font-mono">/root/.ssh/authorized_keys</code> on your server using our secure panel connection.
                </p>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-neutral-300 mb-1">Key Label</label>
                        <input 
                            type="text" 
                            wire:model="newKeyName" 
                            placeholder="e.g. MacBook Pro, Work Desktop..." 
                            class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white text-xs rounded"
                        />
                        @error('newKeyName') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-300 mb-1">Public Key Data (OpenSSH format)</label>
                        <textarea 
                            wire:model="newPublicKey" 
                            rows="4" 
                            placeholder="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI... or ssh-rsa AAAAB3NzaC1yc2E..." 
                            class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white text-xs font-mono rounded"
                        ></textarea>
                        @error('newPublicKey') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-neutral-800">
                    <button wire:click="$set('showKeyModal', false)" class="px-3 py-1.5 bg-neutral-800 text-neutral-300 rounded text-xs">Cancel</button>
                    <button 
                        wire:click="addAuthorizedKey" 
                        wire:loading.attr="disabled"
                        class="px-4 py-1.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white font-bold rounded text-xs flex items-center gap-2"
                    >
                        <span wire:loading.remove wire:target="addAuthorizedKey">Install Key on Server</span>
                        <span wire:loading wire:target="addAuthorizedKey">Installing...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

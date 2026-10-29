<div class="max-w-6xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-800">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Managed Cloud Databases</h1>
            <p class="text-sm text-neutral-400">Standalone PostgreSQL, MySQL, and Redis instances with automated S3 daily backups.</p>
        </div>

        <button 
            wire:click="openCreateModal"
            class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded shadow flex items-center gap-1.5 transition-all"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Deploy Database
        </button>
    </div>

    <!-- Databases Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($databases as $db)
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4 hover:border-purple-500/50 transition-all shadow-lg flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase rounded bg-neutral-800 text-purple-300 border border-purple-500/20">
                            {{ $db->os_image }}
                        </span>
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            {{ $db->status }}
                        </span>
                    </div>

                    <div>
                        <h3 class="text-base font-bold text-white tracking-tight">{{ $db->name }}</h3>
                        <p class="text-xs text-neutral-500 font-mono mt-0.5">{{ $db->hostname }}</p>
                    </div>

                    <div class="bg-neutral-950 p-3 rounded-lg border border-neutral-850 font-mono text-xs space-y-1">
                        @if ($cred = $db->credentials->first())
                            <div class="flex justify-between">
                                <span class="text-neutral-500">Username:</span>
                                <span class="text-white select-all font-bold">{{ $cred->username }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-neutral-500">Port:</span>
                                <span class="text-white">{{ $cred->ssh_port }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-neutral-500">RAM Allocation:</span>
                            <span class="text-purple-400 font-bold">{{ $db->productVariant->ram_mb ?? 1024 }} MB</span>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-neutral-800 flex items-center gap-2">
                    <button 
                        wire:click="openPasswordModal({{ $db->id }})" 
                        class="flex-1 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-white font-bold text-xs rounded transition-all"
                    >
                        Reveal Password
                    </button>
                    <button 
                        wire:click="triggerBackup({{ $db->id }})" 
                        class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-purple-300 font-bold text-xs rounded transition-all"
                        title="Trigger Instant S3 Backup"
                    >
                        Backup
                    </button>
                </div>
            </div>
        @empty
            <div class="col-span-full p-12 text-center bg-neutral-900 border border-neutral-800 rounded-xl space-y-3">
                <div class="w-12 h-12 rounded-full bg-purple-500/10 text-purple-400 flex items-center justify-center mx-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                </div>
                <h3 class="text-base font-bold text-white">No managed databases deployed</h3>
                <p class="text-xs text-neutral-400 max-w-sm mx-auto">Deploy a high-performance PostgreSQL, MySQL, or Redis engine with automatic daily backups.</p>
                <div class="pt-2">
                    <button wire:click="openCreateModal" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded shadow">
                        Deploy Cloud Database
                    </button>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Create Database Modal -->
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="bg-neutral-900 border border-neutral-700 rounded-xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                    <h3 class="text-base font-bold text-white">Deploy Managed Database</h3>
                    <button wire:click="$set('showCreateModal', false)" class="text-neutral-400 hover:text-white">&times;</button>
                </div>

                <div class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Database Identifier *</label>
                        <input type="text" wire:model="dbName" placeholder="e.g. production_db" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded font-mono" />
                        @error('dbName') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Database Engine</label>
                        <select wire:model="engine" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded">
                            <option value="postgresql">PostgreSQL 16 (Relational)</option>
                            <option value="mysql">MySQL 8.4 LTS (Relational)</option>
                            <option value="redis">Redis 7.4 (In-Memory Cache)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Engine Tier</label>
                        <select wire:model="selectedVariantId" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded">
                            @foreach ($availableVariants as $v)
                                <option value="{{ $v->id }}">{{ $v->name }} ({{ $v->ram_mb }}MB RAM, {{ $v->disk_gb }}GB NVMe)</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-neutral-800">
                    <button wire:click="$set('showCreateModal', false)" class="px-4 py-2 bg-neutral-800 text-neutral-300 rounded text-xs">Cancel</button>
                    <button wire:click="createDatabase" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded text-xs">Deploy Database</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Password Modal -->
    @if ($revealingServiceId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="bg-neutral-900 border border-neutral-700 rounded-xl max-w-sm w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">Reveal Database Password</h3>
                    <button wire:click="$set('revealingServiceId', null)" class="text-neutral-400 hover:text-white">&times;</button>
                </div>

                @if ($revealedDbPassword)
                    <div class="space-y-2">
                        <span class="text-xs text-neutral-400">Decrypted Database Password:</span>
                        <div class="bg-neutral-950 p-3 rounded border border-purple-500/30 text-purple-300 font-mono font-bold text-xs select-all break-all">
                            {{ $revealedDbPassword }}
                        </div>
                    </div>
                @else
                    <p class="text-xs text-neutral-400">
                        Confirm your account password to decrypt the database password:
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
                        <button wire:click="$set('revealingServiceId', null)" class="px-3 py-1.5 bg-neutral-800 text-neutral-300 rounded text-xs">Cancel</button>
                        <button wire:click="revealPassword" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded text-xs">Decrypt</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

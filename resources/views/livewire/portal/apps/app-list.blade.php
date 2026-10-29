<div class="max-w-6xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-800">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Cloud Web Applications</h1>
            <p class="text-sm text-neutral-400">Deploy containerized applications directly from Git repositories with cgroups resource quotas.</p>
        </div>

        <button 
            wire:click="openDeployModal"
            class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded shadow flex items-center gap-1.5 transition-all"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Deploy Git App
        </button>
    </div>

    <!-- Apps Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($apps as $app)
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4 hover:border-purple-500/50 transition-all shadow-lg flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase rounded bg-neutral-800 text-purple-300 border border-purple-500/20">
                            {{ $app->productVariant->name ?? 'Standard Container' }}
                        </span>
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            {{ $app->status }}
                        </span>
                    </div>

                    <div>
                        <h3 class="text-base font-bold text-white tracking-tight">{{ $app->name }}</h3>
                        <p class="text-xs text-neutral-400 font-mono mt-0.5 truncate select-all">{{ $app->hostname }}</p>
                    </div>

                    <div class="bg-neutral-950 p-3 rounded-lg border border-neutral-850 font-mono text-xs space-y-1">
                        <div class="flex justify-between">
                            <span class="text-neutral-500">Buildpack:</span>
                            <span class="text-white uppercase">{{ $app->os_image }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-neutral-500">Memory Quota:</span>
                            <span class="text-purple-400 font-bold">{{ $app->productVariant->ram_mb ?? 512 }} MB</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-neutral-500">CPU Limit:</span>
                            <span class="text-purple-400 font-bold">{{ $app->productVariant->vcpu ?? 1 }} vCPU</span>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-neutral-800">
                    <a href="{{ route('portal.apps.detail', ['uuid' => $app->uuid]) }}" class="block w-full py-2 bg-neutral-800 hover:bg-neutral-700 text-white font-bold text-xs rounded text-center transition-all">
                        Build Logs & Settings &rarr;
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full p-12 text-center bg-neutral-900 border border-neutral-800 rounded-xl space-y-3">
                <div class="w-12 h-12 rounded-full bg-purple-500/10 text-purple-400 flex items-center justify-center mx-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                </div>
                <h3 class="text-base font-bold text-white">No applications deployed</h3>
                <p class="text-xs text-neutral-400 max-w-sm mx-auto">You have not deployed any web applications yet. Click "Deploy Git App" to launch a repository.</p>
                <div class="pt-2">
                    <button wire:click="openDeployModal" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded shadow">
                        Deploy First Application
                    </button>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Deploy Modal -->
    @if ($showDeployModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="bg-neutral-900 border border-neutral-700 rounded-xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                    <h3 class="text-base font-bold text-white">Deploy New Web Application</h3>
                    <button wire:click="$set('showDeployModal', false)" class="text-neutral-400 hover:text-white">&times;</button>
                </div>

                <div class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Application Name *</label>
                        <input type="text" wire:model="appName" placeholder="e.g. my-nextjs-store" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded font-mono" />
                        @error('appName') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Git Repository URL *</label>
                        <input type="text" wire:model="gitRepo" placeholder="https://github.com/user/repo" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded font-mono" />
                        @error('gitRepo') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-neutral-300 mb-1">Git Branch</label>
                            <input type="text" wire:model="gitBranch" placeholder="main" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded font-mono" />
                        </div>

                        <div>
                            <label class="block font-bold text-neutral-300 mb-1">Buildpack</label>
                            <select wire:model="buildPack" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded">
                                <option value="nixpacks">Nixpacks (Auto-Detect)</option>
                                <option value="dockerfile">Dockerfile</option>
                                <option value="static">Static Web / SPA</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Hardware Quota Tier</label>
                        <select wire:model="selectedVariantId" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded">
                            @foreach ($availableVariants as $v)
                                <option value="{{ $v->id }}">{{ $v->name }} ({{ $v->vcpu }} vCPU, {{ $v->ram_mb }}MB RAM, {{ $v->disk_gb }}GB SSD)</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-neutral-800">
                    <button wire:click="$set('showDeployModal', false)" class="px-4 py-2 bg-neutral-800 text-neutral-300 rounded text-xs">Cancel</button>
                    <button wire:click="deployApp" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded text-xs">Start Deployment</button>
                </div>
            </div>
        </div>
    @endif
</div>


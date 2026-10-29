<div class="max-w-6xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-800">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Cloud Web Applications &amp; Bots</h1>
            <p class="text-sm text-neutral-400">Deploy containerized web apps and bots directly from Git repositories or 1-click curated templates.</p>
        </div>

        <button 
            wire:click="openDeployModal"
            class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded shadow flex items-center gap-1.5 transition-all"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Deploy App or Bot
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
                            <span class="text-neutral-500">Memory Limit:</span>
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
                        Build Logs &amp; Settings &rarr;
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full p-12 text-center bg-neutral-900 border border-neutral-800 rounded-xl space-y-3">
                <div class="w-12 h-12 rounded-full bg-purple-500/10 text-purple-400 flex items-center justify-center mx-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <h3 class="text-base font-bold text-white">No Applications Deployed</h3>
                <p class="text-xs text-neutral-400 max-w-sm mx-auto">Deploy your first web app or bot from a public/private GitHub repository, or choose a curated 1-click template.</p>
                <button wire:click="openDeployModal" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded">
                    Deploy Application Now
                </button>
            </div>
        @endforelse
    </div>

    <!-- Deploy Modal -->
    @if ($showDeployModal)
        <div class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-2xl w-full p-6 space-y-5 shadow-2xl overflow-y-auto max-h-[90vh]">
                <div class="flex items-center justify-between pb-3 border-b border-neutral-800">
                    <div>
                        <h2 class="text-base font-bold text-white tracking-tight">Deploy Cloud Application or Bot</h2>
                        <p class="text-xs text-neutral-400">Launch a microservice, discord/telegram bot, or web app.</p>
                    </div>
                    <button wire:click="$set('showDeployModal', false)" class="text-neutral-400 hover:text-white">&times;</button>
                </div>

                <!-- Mode Switcher -->
                <div class="flex items-center gap-2 bg-neutral-950 p-1 rounded-xl border border-neutral-800 text-xs font-mono">
                    <button 
                        type="button" 
                        wire:click="$set('deployType', 'git')" 
                        class="flex-1 py-1.5 rounded-lg font-bold transition-all {{ $deployType === 'git' ? 'bg-purple-600 text-white' : 'text-neutral-400 hover:text-white' }}"
                    >
                        Custom GitHub Repo
                    </button>
                    <button 
                        type="button" 
                        wire:click="$set('deployType', 'template')" 
                        class="flex-1 py-1.5 rounded-lg font-bold transition-all {{ $deployType === 'template' ? 'bg-purple-600 text-white' : 'text-neutral-400 hover:text-white' }}"
                    >
                        1-Click Curated Templates &amp; Bots
                    </button>
                </div>

                <!-- Template Selector -->
                @if ($deployType === 'template')
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-neutral-300 uppercase font-mono">Choose a Curated Template</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($curatedTemplates as $key => $tpl)
                                <div 
                                    wire:click="selectTemplate('{{ $key }}')"
                                    class="p-3.5 rounded-xl border cursor-pointer transition-all {{ $selectedTemplate === $key ? 'bg-purple-950/40 border-purple-500 shadow-glow-purple' : 'bg-neutral-950 border-neutral-800 hover:border-neutral-700' }}"
                                >
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-bold text-white text-xs">{{ $tpl['name'] }}</span>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-mono uppercase bg-neutral-800 text-cyan-400">{{ $tpl['category'] }}</span>
                                    </div>
                                    <p class="text-[11px] text-neutral-400 leading-snug">{{ $tpl['desc'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form wire:submit.prevent="deployApp" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-neutral-300 mb-1">Application Identifier *</label>
                            <input 
                                type="text" 
                                wire:model="appName" 
                                placeholder="my-awesome-service" 
                                class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono"
                            />
                            @error('appName') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-neutral-300 mb-1">Hardware Tier / Quota *</label>
                            <select wire:model="selectedVariantId" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                                @foreach ($variants as $variant)
                                    <option value="{{ $variant->id }}">
                                        {{ $variant->name }} ({{ $variant->vcpu }} vCPU, {{ $variant->ram_mb }}MB RAM)
                                    </option>
                                @endforeach
                            </select>
                            @error('selectedVariantId') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-300 mb-1">Git Repository URL *</label>
                        <input 
                            type="text" 
                            wire:model="gitRepo" 
                            placeholder="https://github.com/username/repository" 
                            class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono"
                        />
                        @error('gitRepo') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-neutral-300 mb-1">Target Branch</label>
                            <input 
                                type="text" 
                                wire:model="gitBranch" 
                                class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-neutral-300 mb-1">Buildpack Engine</label>
                            <select wire:model="buildPack" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                                <option value="nixpacks">Nixpacks (Auto-Detect Node/Python/Go/Rust)</option>
                                <option value="dockerfile">Dockerfile</option>
                                <option value="static">Static HTML/SPA</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-300 mb-1">GitHub Personal Access Token (For Private Repositories)</label>
                        <input 
                            type="password" 
                            wire:model="gitAuthToken" 
                            placeholder="ghp_xxxxxxxxxxxxxxxxxxxx (Optional for public repos)" 
                            class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono"
                        />
                        <p class="text-[10px] text-neutral-500 mt-1 font-mono">Token is used for repository cloning and never displayed publicly.</p>
                    </div>

                    <div class="pt-4 border-t border-neutral-800 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showDeployModal', false)" class="px-4 py-2 bg-neutral-800 text-white font-bold text-xs rounded">Cancel</button>
                        <button type="submit" class="px-6 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded shadow transition-all">
                            Start Container Build &amp; Deploy &rarr;
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

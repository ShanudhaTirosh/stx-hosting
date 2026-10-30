<div class="max-w-5xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-800">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('portal.apps') }}" class="text-xs text-purple-400 hover:text-purple-300 font-semibold">&larr; Back to Applications</a>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight mt-1">{{ $service->name }}</h1>
            <p class="text-xs text-neutral-400 font-mono">Service UUID: {{ $service->uuid }}</p>
        </div>

        <div class="flex items-center gap-2">
            <button 
                wire:click="restart" 
                wire:loading.attr="disabled"
                class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 disabled:opacity-50 text-white font-bold text-xs rounded transition-all"
            >
                <span wire:loading.remove wire:target="restart">Restart</span>
                <span wire:loading wire:target="restart">Restarting...</span>
            </button>
            <button 
                wire:click="redeploy" 
                wire:loading.attr="disabled"
                class="px-3 py-1.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white font-bold text-xs rounded transition-all"
            >
                <span wire:loading.remove wire:target="redeploy">Redeploy</span>
                <span wire:loading wire:target="redeploy">Redeploying...</span>
            </button>
        </div>
    </div>

    <!-- Application Metadata & Quotas -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-1">
            <span class="text-[10px] uppercase font-bold text-neutral-500 tracking-wider">Memory Quota</span>
            <div class="text-lg font-bold text-purple-400 font-mono">{{ $service->productVariant->ram_mb ?? 512 }} MB</div>
            <p class="text-[11px] text-neutral-500">cgroups strict limit</p>
        </div>
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-1">
            <span class="text-[10px] uppercase font-bold text-neutral-500 tracking-wider">CPU Allocation</span>
            <div class="text-lg font-bold text-purple-400 font-mono">{{ $service->productVariant->vcpu ?? 1 }} Dedicated vCPU</div>
            <p class="text-[11px] text-neutral-500">Priority CPU shares</p>
        </div>
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-1">
            <span class="text-[10px] uppercase font-bold text-neutral-500 tracking-wider">Buildpack Engine</span>
            <div class="text-lg font-bold text-white font-mono uppercase">{{ $service->os_image }}</div>
            <p class="text-[11px] text-neutral-500">Automatic runtime build</p>
        </div>
    </div>

    <!-- Custom Domains Section -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4 shadow-lg">
        <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-300">Custom Domains & Auto-SSL</h2>
        <p class="text-xs text-neutral-400">Point an A record to your cluster ingress IP and enter your domain below. Let's Encrypt certificates are provisioned automatically.</p>

        <div class="flex gap-2">
            <input 
                type="text" 
                wire:model="newDomain" 
                placeholder="e.g. app.mycompany.com" 
                class="input input-sm flex-1 bg-neutral-950 border-neutral-700 text-white font-mono text-xs rounded"
            />
            <button 
                wire:click="addDomain" 
                class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded transition-all"
            >
                Add Domain
            </button>
        </div>
        @error('newDomain') <span class="text-rose-400 text-xs block">{{ $message }}</span> @enderror

        <div class="space-y-2 pt-2">
            @foreach ($domains as $domain)
                <div class="flex items-center justify-between p-3 rounded bg-neutral-950 border border-neutral-850 font-mono text-xs">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        <a href="https://{{ $domain }}" target="_blank" class="text-white hover:text-purple-300 underline">{{ $domain }}</a>
                    </div>
                    <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 text-[10px] font-bold">HTTPS Active</span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Environment Variables Editor -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4 shadow-lg">
        <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-300">Environment Variables & Secrets</h2>
        <p class="text-xs text-neutral-400">Injected into your application container at runtime. Sensitive tokens are masked.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input 
                type="text" 
                wire:model="envKey" 
                placeholder="KEY (e.g. DATABASE_URL)" 
                class="input input-sm bg-neutral-950 border-neutral-700 text-white font-mono text-xs rounded"
            />
            <div class="flex gap-2">
                <input 
                    type="password" 
                    wire:model="envValue" 
                    placeholder="VALUE" 
                    class="input input-sm flex-1 bg-neutral-950 border-neutral-700 text-white font-mono text-xs rounded"
                />
                <button 
                    wire:click="addEnvVar" 
                    class="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white font-bold text-xs rounded"
                >
                    Add Secret
                </button>
            </div>
        </div>
        @error('envKey') <span class="text-rose-400 text-xs block">{{ $message }}</span> @enderror

        <div class="space-y-2 pt-2">
            @forelse ($envVars as $k => $v)
                <div class="flex items-center justify-between p-3 rounded bg-neutral-950 border border-neutral-850 font-mono text-xs">
                    <span class="text-purple-300 font-bold">{{ $k }}</span>
                    <span class="text-neutral-500">••••••••••••••••</span>
                </div>
            @empty
                <div class="text-xs text-neutral-500 italic p-3 bg-neutral-950 rounded border border-neutral-850">
                    No custom environment variables added yet.
                </div>
            @endforelse
        </div>
    </div>
</div>


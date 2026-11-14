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
                type="button"
                wire:click="openDuplicateModal"
                class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-purple-300 border border-purple-500/30 hover:border-purple-500 font-bold text-xs rounded transition-all flex items-center gap-1.5 shadow-sm"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
                Deploy Another Copy
            </button>
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
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
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
        @php
            $serverName = data_get($service->network_config, 'server_name') ?: ($service->ip_address ? 'Node (' . $service->ip_address . ')' : 'Default Node');
        @endphp
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-1">
            <span class="text-[10px] uppercase font-bold text-neutral-500 tracking-wider">Cluster Node</span>
            <div class="text-lg font-bold text-emerald-400 font-mono truncate" title="{{ $serverName }}">{{ $serverName }}</div>
            <p class="text-[11px] text-neutral-500 font-mono">{{ $service->ip_address ?: 'Primary Ingress' }}</p>
        </div>
    </div>

    <!-- Published Application Endpoints -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4 shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-300">Published HTTPS Endpoints</h2>
                <p class="text-xs text-neutral-400">Zero-config addresses provisioned automatically on deploy with wildcard SSL.</p>
            </div>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-emerald-900/60 text-emerald-300 border border-emerald-700/50">
                SSL Active
            </span>
        </div>

        <div class="space-y-2">
            @if (! empty($assignedEndpoints))
                @foreach ($assignedEndpoints as $svcKey => $endpoint)
                    <div class="flex flex-wrap items-center justify-between p-3.5 rounded-lg bg-neutral-950 border border-neutral-800 gap-3">
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                                <span class="text-xs font-bold text-white">{{ $endpoint['friendly_name'] }}</span>
                                @if (! empty($endpoint['is_default']))
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase bg-purple-900/60 text-purple-300 border border-purple-700/50">Default</span>
                                @endif
                                @if (($endpoint['access_type'] ?? '') === 'admin_only')
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase bg-amber-900/60 text-amber-300 border border-amber-700/50">Admin Only</span>
                                @endif
                                <span class="text-[10px] font-mono text-neutral-500">:{{ $endpoint['internal_port'] }}</span>
                            </div>
                            <div class="text-xs font-mono text-neutral-300 flex items-center gap-2">
                                <span>{{ $endpoint['url'] }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ $endpoint['url'] }}" target="_blank" rel="noopener noreferrer" class="px-3 py-1 bg-purple-600 hover:bg-purple-500 text-white font-semibold text-xs rounded transition flex items-center gap-1">
                                <span>Open</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="flex items-center justify-between p-3.5 rounded-lg bg-neutral-950 border border-neutral-800 font-mono text-xs">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        <span class="text-white">https://{{ $service->hostname }}</span>
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase bg-purple-900/60 text-purple-300 border border-purple-700/50">Primary</span>
                    </div>
                    <a href="https://{{ $service->hostname }}" target="_blank" rel="noopener noreferrer" class="px-3 py-1 bg-purple-600 hover:bg-purple-500 text-white font-semibold text-xs rounded transition flex items-center gap-1">
                        <span>Open</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Custom Domains Section & DNS Verification Studio -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-5 shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-300">Connect Custom Domain</h2>
                <p class="text-xs text-neutral-400">Bring your own domain (e.g. app.mycompany.com or mycompany.com). Automated DNS verification and SSL certificates.</p>
            </div>
        </div>

        <!-- Add Domain Form -->
        <div class="flex flex-wrap items-center gap-2">
            <input 
                type="text" 
                wire:model="newDomain" 
                placeholder="e.g. api.mycompany.com" 
                class="input input-sm flex-1 min-w-[200px] bg-neutral-950 border-neutral-700 text-white font-mono text-xs rounded"
            />
            @if (! empty($assignedEndpoints))
                <select wire:model="targetService" class="select select-sm bg-neutral-950 border-neutral-700 text-white text-xs rounded">
                    <option value="">Route to: Default Service</option>
                    @foreach ($assignedEndpoints as $sKey => $ep)
                        <option value="{{ $sKey }}">Route to: {{ $ep['friendly_name'] }}</option>
                    @endforeach
                </select>
            @endif
            <button 
                wire:click="addDomain" 
                class="px-4 py-1.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded transition-all shadow"
            >
                Add Domain
            </button>
        </div>
        @error('newDomain') <span class="text-rose-400 text-xs block">{{ $message }}</span> @enderror

        <!-- Domain List & Interactive DNS Guidance -->
        <div class="space-y-3 pt-2">
            @php
                $allCustomDomains = array_unique(array_filter(data_get($service->network_config, 'custom_domains', [])));
            @endphp

            @forelse ($allCustomDomains as $domain)
                @php
                    $rec = $customDomainRecords[$domain] ?? null;
                    $isVerified = $rec ? $rec->isVerified() : false;
                    $isApex = substr_count($domain, '.') === 1;
                    $expectedHost = $isApex ? '@' : \Illuminate\Support\Str::before($domain, '.');
                    $expectedTarget = $rec?->expected_target ?: ($isApex ? $ingressIp : ($service->hostname ?: $appsDomain));
                    $recordType = $rec?->record_type ?: ($isApex ? 'A' : 'CNAME');
                    $isPrimary = $service->hostname === $domain;
                @endphp

                <div class="border rounded-lg p-4 bg-neutral-950 border-neutral-800 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full {{ $isVerified ? 'bg-emerald-400' : 'bg-amber-400 animate-pulse' }}"></span>
                            <span class="text-sm font-bold font-mono text-white">{{ $domain }}</span>
                            @if ($isPrimary)
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-purple-900/60 text-purple-300 border border-purple-700/50">Primary</span>
                            @endif
                            @if ($isVerified)
                                <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 text-[10px] font-bold">HTTPS Active</span>
                            @else
                                <span class="px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 text-[10px] font-bold">Pending DNS</span>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            <button 
                                type="button" 
                                wire:click="verifyDomain('{{ $domain }}')" 
                                class="px-2.5 py-1 bg-neutral-800 hover:bg-neutral-700 text-white font-semibold text-xs rounded transition flex items-center gap-1"
                            >
                                <svg class="w-3.5 h-3.5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>Check DNS</span>
                            </button>

                            @if (! $isPrimary && $isVerified)
                                <button 
                                    type="button" 
                                    wire:click="setPrimaryDomain('{{ $domain }}')" 
                                    class="px-2.5 py-1 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 hover:text-white text-xs rounded transition"
                                >
                                    Make Primary
                                </button>
                            @endif

                            <button 
                                type="button" 
                                wire:click="removeDomain('{{ $domain }}')" 
                                class="px-2.5 py-1 bg-neutral-800 hover:bg-rose-950 text-rose-400 text-xs rounded transition"
                            >
                                Remove
                            </button>
                        </div>
                    </div>

                    <!-- DNS Diagnostic Guidance Card -->
                    @if (! $isVerified)
                        <div class="bg-neutral-900/90 border border-neutral-800 rounded-lg p-3 space-y-2 text-xs">
                            <div class="flex items-center justify-between text-neutral-400 font-semibold">
                                <span>Required DNS Record (Add at your DNS provider e.g. Cloudflare, GoDaddy):</span>
                                <span class="text-[11px] font-mono text-neutral-500">TTL: 300 or Auto</span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 font-mono">
                                <div class="bg-neutral-950 p-2 rounded border border-neutral-800">
                                    <span class="text-[10px] text-neutral-500 block uppercase">Type</span>
                                    <span class="text-purple-300 font-bold">{{ $recordType }}</span>
                                </div>
                                <div class="bg-neutral-950 p-2 rounded border border-neutral-800">
                                    <span class="text-[10px] text-neutral-500 block uppercase">Host / Name</span>
                                    <span class="text-white">{{ $expectedHost }}</span>
                                </div>
                                <div class="bg-neutral-950 p-2 rounded border border-neutral-800 truncate">
                                    <span class="text-[10px] text-neutral-500 block uppercase">Target / Value</span>
                                    <span class="text-emerald-400 font-bold">{{ $expectedTarget }}</span>
                                </div>
                            </div>

                            @if ($rec)
                                <div class="pt-1 text-[11px] text-neutral-500 flex items-center justify-between">
                                    <span>Cloudflare proxy users: If direct {{ $recordType }} is proxied, also add TXT record: <code class="text-neutral-400">{{ $rec->txtHost() }}</code> &rarr; <code class="text-neutral-400">{{ $rec->txtValue() }}</code></span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="p-4 rounded-lg bg-neutral-950 border border-neutral-800 text-xs text-neutral-500 italic text-center">
                    No custom domains connected yet. Your app is live at the automatic platform address above.
                </div>
            @endforelse
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

    <!-- Duplicate Application Modal -->
    @if ($showDuplicateModal)
        <div class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-lg w-full p-6 space-y-5 shadow-2xl">
                <div class="flex items-center justify-between pb-3 border-b border-neutral-800">
                    <div>
                        <h2 class="text-base font-bold text-white tracking-tight">Deploy Duplicate Instance</h2>
                        <p class="text-xs text-neutral-400">Launch a fresh copy of <strong>{{ $service->name }}</strong> with isolated endpoints and preserved settings.</p>
                    </div>
                    <button type="button" wire:click="$set('showDuplicateModal', false)" class="text-neutral-400 hover:text-white">&times;</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label for="dupName" class="block text-xs font-semibold text-neutral-300 mb-1">New Application Identifier *</label>
                        <input id="dupName" type="text" wire:model="duplicateName" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                        @error('duplicateName') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="dupSubdomain" class="block text-xs font-semibold text-neutral-300 mb-1">Custom Subdomain (Optional)</label>
                        <input id="dupSubdomain" type="text" wire:model="duplicateSubdomain" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                        <span class="text-[11px] text-neutral-500 mt-1 block">New address: <code>https://{{ $duplicateSubdomain ?: 'new-app' }}.{{ $appsDomain }}</code></span>
                        @error('duplicateSubdomain') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    @if ($service->service_type === 'git_app')
                        <div>
                            <label for="dupBranch" class="block text-xs font-semibold text-neutral-300 mb-1">Git Branch (e.g. staging, develop)</label>
                            <input id="dupBranch" type="text" wire:model="duplicateBranch" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                            @error('duplicateBranch') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div class="p-3 rounded-lg bg-neutral-950 border border-neutral-800 text-xs text-neutral-400 space-y-1">
                        <div class="font-bold text-neutral-300">Preserved Configuration:</div>
                        <div>• {{ count($envVars) }} Environment Variables will be copied.</div>
                        <div>• Isolated network, container name, and instant wildcard HTTPS.</div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-neutral-800">
                    <button type="button" wire:click="$set('showDuplicateModal', false)" class="px-4 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-semibold">
                        Cancel
                    </button>
                    <button type="button" wire:click="duplicateApp" wire:loading.attr="disabled" class="px-5 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold shadow-lg transition">
                        <span wire:loading.remove wire:target="duplicateApp">Launch Duplicate Instance</span>
                        <span wire:loading wire:target="duplicateApp">Provisioning...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>


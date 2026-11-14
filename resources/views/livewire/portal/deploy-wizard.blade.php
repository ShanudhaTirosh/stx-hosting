<div class="max-w-5xl mx-auto p-6 space-y-8">
    <!-- Header -->
    <div class="pb-4 border-b border-neutral-800 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Deploy Application</h1>
            <p class="text-xs text-neutral-400 mt-1">
                Choose a pre-configured template or connect your own Git repository. Every app receives an instant HTTPS address.
            </p>
        </div>

        <!-- Mode Toggle Tabs -->
        <div class="inline-flex rounded-lg bg-neutral-900 border border-neutral-800 p-1 text-xs font-semibold">
            <button type="button" wire:click="setMode('template')" class="px-4 py-1.5 rounded-md transition {{ $mode === 'template' ? 'bg-purple-600 text-white shadow' : 'text-neutral-400 hover:text-white' }}">
                1-Click Templates
            </button>
            <button type="button" wire:click="setMode('git')" class="px-4 py-1.5 rounded-md transition {{ $mode === 'git' ? 'bg-purple-600 text-white shadow' : 'text-neutral-400 hover:text-white' }}">
                Custom Git App (3 Plans)
            </button>
        </div>
    </div>

    @if ($cloningSourceName)
        <div class="p-3.5 rounded-xl bg-purple-950/40 border border-purple-500/50 flex items-center justify-between text-xs">
            <div class="flex items-center gap-2 text-purple-200">
                <span>📑</span>
                <span>Duplicating application based on <strong>{{ $cloningSourceName }}</strong>. Configurations and environment variables have been pre-filled.</span>
            </div>
            <a href="{{ route('portal.deploy') }}" class="text-[11px] font-mono text-neutral-400 hover:text-white underline">Start Fresh</a>
        </div>
    @endif

    <!-- TAB 1: 1-Click Curated Templates -->
    @if ($mode === 'template')
        <div class="space-y-6">
            <!-- Filter Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-neutral-400 font-medium">Category:</span>
                    <select wire:model.live="templateCategory" class="select select-sm bg-neutral-900 border-neutral-800 text-white text-xs rounded-lg">
                        <option value="all">All Categories</option>
                        <option value="cms">CMS & Sites</option>
                        <option value="blog">Publishing & Blogs</option>
                        <option value="automation">Automation</option>
                        <option value="cloud">Storage & Cloud</option>
                        <option value="analytics">Analytics</option>
                        <option value="monitoring">Monitoring</option>
                    </select>
                </div>
                <div class="w-64">
                    <input type="text" wire:model.live.debounce.250ms="templateSearch" placeholder="Search templates..." class="input input-sm w-full bg-neutral-900 border-neutral-800 text-white text-xs rounded-lg focus:border-purple-500">
                </div>
            </div>

            <!-- Templates Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse ($templates as $tmpl)
                    <div wire:click="selectTemplate('{{ $tmpl->template_name }}')"
                         class="cursor-pointer border rounded-xl p-4 transition space-y-3 relative {{ $selectedTemplate === $tmpl->template_name ? 'bg-purple-950/20 border-purple-500 shadow-md ring-1 ring-purple-500' : 'bg-neutral-900 border-neutral-800 hover:border-neutral-700' }}">
                        @if ($tmpl->is_featured)
                            <span class="absolute top-3 right-3 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-purple-900/60 text-purple-300 border border-purple-700/50">
                                Popular
                            </span>
                        @endif

                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-neutral-800 border border-neutral-700/60 flex items-center justify-center p-2">
                                @if ($tmpl->icon_url)
                                    <img src="{{ $tmpl->icon_url }}" alt="{{ $tmpl->display_name }}" class="w-full h-full object-contain">
                                @else
                                    <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                @endif
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white">{{ $tmpl->display_name ?: $tmpl->template_name }}</h3>
                                <span class="text-[11px] text-neutral-400 font-mono">{{ $tmpl->category ?: 'app' }}</span>
                            </div>
                        </div>

                        <p class="text-xs text-neutral-400 line-clamp-2">
                            {{ $tmpl->short_description ?: 'Production-ready self-hosted template with automatic HTTPS.' }}
                        </p>

                        <div class="pt-2 border-t border-neutral-800/80 flex items-center justify-between text-[11px] text-neutral-500 font-mono">
                            <span>RAM: {{ $tmpl->min_ram_mb }} MB</span>
                            <span>vCPU: {{ $tmpl->min_vcpu }}</span>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-8 text-center text-xs text-neutral-500 bg-neutral-900 border border-neutral-800 rounded-xl">
                        No templates match your search criteria.
                    </div>
                @endforelse
            </div>
            @error('selectedTemplate') <span class="text-rose-400 text-xs block">{{ $message }}</span> @enderror

            <!-- Configuration Card -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Deployment Details</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="template-app-name" class="block text-xs font-semibold text-neutral-300 mb-1">Application Name</label>
                        <input id="template-app-name" type="text" wire:model.live.debounce.150ms="appName" placeholder="e.g. my-company-blog" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                        @error('appName') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="custom-subdomain" class="block text-xs font-semibold text-neutral-300 mb-1">Custom Subdomain (Optional)</label>
                        <input id="custom-subdomain" type="text" wire:model.live.debounce.150ms="customSubdomain" placeholder="e.g. acme-backend" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                        @error('customSubdomain') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-neutral-300 mb-1">Primary HTTPS Address</label>
                        @php
                            $targetSlug = $customSubdomain ?: ($appName ? \Illuminate\Support\Str::slug($appName) : 'your-app');
                        @endphp
                        <div class="px-3 py-1.5 rounded bg-neutral-950 border border-neutral-800 text-xs font-mono text-emerald-400 truncate">
                            https://{{ $targetSlug }}.{{ $appsDomain }}
                        </div>
                        <span class="text-[11px] text-neutral-500 mt-1 block">Wildcard SSL issued instantly on deploy.</span>
                    </div>
                </div>

                @if($availableServers->count() > 1)
                    <div class="pt-3 border-t border-neutral-800">
                        <label class="block text-xs font-semibold text-neutral-300 mb-2">Target Cluster Node / Server</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach($availableServers as $srv)
                                <label class="cursor-pointer flex items-center gap-3 p-2.5 rounded-lg border {{ $selectedServerId == $srv->id ? 'bg-indigo-950/40 border-indigo-600' : 'bg-neutral-950 border-neutral-800 hover:border-neutral-700' }} transition">
                                    <input type="radio" wire:model.live="selectedServerId" value="{{ $srv->id }}" class="radio radio-xs radio-primary">
                                    <div class="truncate text-xs">
                                        <span class="font-bold text-white block truncate">{{ $srv->name }}</span>
                                        <span class="text-neutral-400 font-mono text-[11px]">{{ $srv->ip }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Exposable Multi-Container Services Picker -->
                @if (count($exposableServices) > 0)
                    <div class="pt-4 border-t border-neutral-800 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-bold text-white uppercase tracking-wider">Public Endpoints</h4>
                                <p class="text-[11px] text-neutral-400">Select which services to publish with automatic HTTPS addresses.</p>
                            </div>
                            <span class="text-[11px] font-mono text-neutral-500">Zero configuration needed</span>
                        </div>

                        <div class="space-y-2">
                            @foreach ($exposableServices as $endpoint)
                                @php
                                    $isDef = (bool) ($endpoint['is_default'] ?? false);
                                    $prefix = trim($endpoint['subdomain_prefix'] ?? '');
                                    $endpointHost = ($isDef || $prefix === '')
                                        ? "{$targetSlug}.{$appsDomain}"
                                        : "{$prefix}.{$targetSlug}.{$appsDomain}";
                                @endphp
                                <label class="flex items-start gap-3 p-3 rounded-lg border transition {{ in_array($endpoint['service_name'], $selectedEndpoints) || $isDef ? 'bg-neutral-950/80 border-purple-500/50' : 'bg-neutral-950/30 border-neutral-800 opacity-60' }} cursor-pointer">
                                    <input type="checkbox"
                                           value="{{ $endpoint['service_name'] }}"
                                           wire:model.live="selectedEndpoints"
                                           @if ($isDef) checked disabled @endif
                                           class="mt-1 checkbox checkbox-xs checkbox-primary">
                                    <div class="flex-1 space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-white">{{ $endpoint['friendly_name'] }}</span>
                                            @if ($isDef)
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-purple-900/60 text-purple-300 border border-purple-700/50">Primary</span>
                                            @endif
                                            @if (($endpoint['access_type'] ?? '') === 'admin_only')
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-amber-900/60 text-amber-300 border border-amber-700/50">Admin Console</span>
                                            @else
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-emerald-900/60 text-emerald-300 border border-emerald-700/50">Public API</span>
                                            @endif
                                            <span class="text-[10px] font-mono text-neutral-500">:{{ $endpoint['internal_port'] }}</span>
                                        </div>
                                        <p class="text-[11px] text-neutral-400">{{ $endpoint['description'] }}</p>
                                        <div class="text-[10px] font-mono text-purple-300">
                                            https://{{ $endpointHost }}
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        <!-- Internal Security Guarantee Box -->
                        <div class="p-2.5 rounded-lg bg-neutral-950 border border-neutral-800/80 flex items-center gap-2 text-[11px] text-neutral-400">
                            <span class="text-emerald-400 text-xs">🛡️</span>
                            <span><strong>Internal Security Guarantee:</strong> Databases, persistent caches, and task workers are strictly isolated and never exposed to the public internet.</span>
                        </div>
                    </div>
                @endif

                <!-- Environment Variables -->
                <div class="pt-3 border-t border-neutral-800 space-y-2">
                    <label class="block text-xs font-semibold text-neutral-300">Environment Variables (Optional)</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" wire:model="newEnvKey" placeholder="KEY (e.g. SITE_TITLE)" class="input input-sm bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono w-48">
                        <input type="text" wire:model="newEnvValue" placeholder="VALUE" class="input input-sm bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono flex-1">
                        <button type="button" wire:click="addEnvVar" class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-white rounded text-xs font-semibold">
                            Add Variable
                        </button>
                    </div>

                    @if (count($envVars) > 0)
                        <div class="space-y-1.5 pt-2">
                            @foreach ($envVars as $k => $v)
                                <div class="flex items-center justify-between bg-neutral-950 px-3 py-1.5 rounded border border-neutral-800 text-xs font-mono">
                                    <span class="text-neutral-300">{{ $k }} = <span class="text-purple-300">{{ $v }}</span></span>
                                    <button type="button" wire:click="removeEnvVar('{{ $k }}')" class="text-rose-400 hover:text-rose-300 text-xs font-semibold">Remove</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="button" wire:click="deploy" wire:loading.attr="disabled" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs flex items-center gap-2 shadow-lg transition">
                        <span wire:loading.remove>Deploy Application</span>
                        <span wire:loading class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Provisioning Container...
                        </span>
                    </button>
                </div>
            </div>
        </div>

    <!-- TAB 2: Custom Git App Deployment across 3 Plans -->
    @else
        <div class="space-y-6">
            <div>
                <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-3">1. Select a Fixed-Price Git Hosting Plan</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach ($gitPlans as $plan)
                        @php
                            $monthlyTier = $plan->pricingTiers->firstWhere('billing_type', 'monthly');
                            $priceUsd = $monthlyTier ? '$' . number_format($monthlyTier->price_cents / 100, 2) . '/mo' : '$10.00/mo';
                        @endphp
                        <div wire:click="selectPlan({{ $plan->id }})"
                             class="cursor-pointer border rounded-xl p-5 space-y-3 transition relative {{ $selectedPlanId === $plan->id ? 'bg-purple-950/20 border-purple-500 shadow-md ring-1 ring-purple-500' : 'bg-neutral-900 border-neutral-800 hover:border-neutral-700' }}">
                            @if (str_contains($plan->slug, 'standard'))
                                <span class="absolute top-3 right-3 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-900/60 text-emerald-300 border border-emerald-700/50">
                                    Recommended
                                </span>
                            @endif

                            <div>
                                <h3 class="text-base font-bold text-white">{{ $plan->name }}</h3>
                                <div class="text-xl font-extrabold text-purple-400 mt-1">{{ $priceUsd }}</div>
                                <p class="text-xs text-neutral-400 mt-1">{{ data_get($plan->specs_json, 'tagline', 'Fixed monthly resource limits.') }}</p>
                            </div>

                            <div class="pt-3 border-t border-neutral-800/80 space-y-1.5 text-xs font-mono text-neutral-300">
                                <div class="flex justify-between">
                                    <span class="text-neutral-500">vCPU</span>
                                    <span class="font-bold text-white">{{ $plan->vcpu }} Core</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-neutral-500">Memory</span>
                                    <span class="font-bold text-white">{{ $plan->ram_mb }} MB</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-neutral-500">Fast Storage</span>
                                    <span class="font-bold text-white">{{ $plan->disk_gb }} GB SSD</span>
                                </div>
                                <div class="flex justify-between text-emerald-400 font-sans text-[11px] pt-1">
                                    <span>Instant Wildcard SSL</span>
                                    <span>Included</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Git Configuration Form -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">2. Configure Git Repository</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="git-app-name" class="block text-xs font-semibold text-neutral-300 mb-1">Application Name</label>
                        <input id="git-app-name" type="text" wire:model.live.debounce.150ms="appName" placeholder="e.g. my-saas-api" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                        @error('appName') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="git-custom-subdomain" class="block text-xs font-semibold text-neutral-300 mb-1">Custom Subdomain (Optional)</label>
                        <input id="git-custom-subdomain" type="text" wire:model.live.debounce.150ms="customSubdomain" placeholder="e.g. api-staging" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                        @error('customSubdomain') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-neutral-300 mb-1">Assigned HTTPS Address</label>
                        @php
                            $gitTargetSlug = $customSubdomain ?: ($appName ? \Illuminate\Support\Str::slug($appName) : 'your-app');
                        @endphp
                        <div class="px-3 py-1.5 rounded bg-neutral-950 border border-neutral-800 text-xs font-mono text-emerald-400 truncate">
                            https://{{ $gitTargetSlug }}.{{ $appsDomain }}
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label for="git-repo" class="block text-xs font-semibold text-neutral-300 mb-1">Git Repository URL</label>
                        <input id="git-repo" type="text" wire:model="gitRepo" placeholder="https://github.com/organization/repository.git" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                        @error('gitRepo') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="git-branch" class="block text-xs font-semibold text-neutral-300 mb-1">Branch</label>
                        <input id="git-branch" type="text" wire:model="gitBranch" placeholder="main" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                        @error('gitBranch') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="build-pack" class="block text-xs font-semibold text-neutral-300 mb-1">Build Pack</label>
                        <select id="build-pack" wire:model="buildPack" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white text-xs rounded">
                            <option value="nixpacks">Nixpacks (Auto-detect Language)</option>
                            <option value="dockerfile">Dockerfile</option>
                            <option value="node">Node.js</option>
                            <option value="python">Python</option>
                            <option value="php">PHP / Laravel</option>
                        </select>
                    </div>
                </div>

                @if($availableServers->count() > 1)
                    <div class="pt-3 border-t border-neutral-800">
                        <label class="block text-xs font-semibold text-neutral-300 mb-2">Target Cluster Node / Server</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach($availableServers as $srv)
                                <label class="cursor-pointer flex items-center gap-3 p-2.5 rounded-lg border {{ $selectedServerId == $srv->id ? 'bg-indigo-950/40 border-indigo-600' : 'bg-neutral-950 border-neutral-800 hover:border-neutral-700' }} transition">
                                    <input type="radio" wire:model.live="selectedServerId" value="{{ $srv->id }}" class="radio radio-xs radio-primary">
                                    <div class="truncate text-xs">
                                        <span class="font-bold text-white block truncate">{{ $srv->name }}</span>
                                        <span class="text-neutral-400 font-mono text-[11px]">{{ $srv->ip }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Environment Variables -->
                <div class="pt-3 border-t border-neutral-800 space-y-2">
                    <label class="block text-xs font-semibold text-neutral-300">Environment Variables (Optional)</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" wire:model="newEnvKey" placeholder="KEY (e.g. DATABASE_URL)" class="input input-sm bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono w-48">
                        <input type="text" wire:model="newEnvValue" placeholder="VALUE" class="input input-sm bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono flex-1">
                        <button type="button" wire:click="addEnvVar" class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-white rounded text-xs font-semibold">
                            Add Variable
                        </button>
                    </div>

                    @if (count($envVars) > 0)
                        <div class="space-y-1.5 pt-2">
                            @foreach ($envVars as $k => $v)
                                <div class="flex items-center justify-between bg-neutral-950 px-3 py-1.5 rounded border border-neutral-800 text-xs font-mono">
                                    <span class="text-neutral-300">{{ $k }} = <span class="text-purple-300">{{ $v }}</span></span>
                                    <button type="button" wire:click="removeEnvVar('{{ $k }}')" class="text-rose-400 hover:text-rose-300 text-xs font-semibold">Remove</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="button" wire:click="deploy" wire:loading.attr="disabled" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs flex items-center gap-2 shadow-lg transition">
                        <span wire:loading.remove>Launch Git App</span>
                        <span wire:loading class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Creating Container & Binding Plan...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>


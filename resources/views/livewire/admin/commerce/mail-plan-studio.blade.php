<div class="max-w-7xl mx-auto p-6 space-y-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-6 border-b border-black/[0.08] dark:border-white/[0.06]">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-500/10 text-purple-400 border border-purple-500/20">
                    <span class="size-1.5 rounded-full bg-purple-400 animate-pulse"></span>
                    Mail Server & Storage
                </span>
                <span class="text-xs text-neutral-500 font-mono">Commerce / Email Hosting</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-black dark:text-white mt-1">Email Hosting Plans & Fleet Studio</h1>
            <p class="text-xs text-neutral-500 dark:text-neutral-400">Manage sellable email storage tiers, expansion add-ons, WildDuck quotas, and fleet deliverability telemetry.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('store.pricing') }}" target="_blank" class="button border border-neutral-300 dark:border-white/10 hover:bg-neutral-100 dark:hover:bg-white/5 text-xs py-2 px-3">
                <x-reicon name="external-link" class="size-3.5 mr-1" />
                Storefront Catalog
            </a>
            <a href="{{ route('portal.mail') }}" class="button border border-neutral-300 dark:border-white/10 hover:bg-neutral-100 dark:hover:bg-white/5 text-xs py-2 px-3">
                <x-reicon name="inbox" class="size-3.5 mr-1" />
                Customer Mail Portal
            </a>
        </div>
    </div>

    {{-- Fleet Health & Telemetry Banner --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl border border-neutral-200 dark:border-white/[0.06] bg-white dark:bg-white/[0.02]">
            <span class="text-xs font-medium text-neutral-500 block">WildDuck Core Status</span>
            <div class="flex items-center gap-2 mt-1">
                @if(data_get($fleetStats, 'healthy'))
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    <span class="text-lg font-bold text-emerald-500">Online</span>
                    <span class="text-xs text-neutral-400 font-mono">({{ data_get($fleetStats, 'latency_ms', 4) }}ms)</span>
                @else
                    <span class="size-2 rounded-full bg-rose-500"></span>
                    <span class="text-lg font-bold text-rose-500">Offline / Standby</span>
                @endif
            </div>
            <span class="text-[11px] text-neutral-400 mt-0.5 block">API: {{ data_get($fleetStats, 'version', '1.55.2') }}</span>
        </div>

        <div class="p-4 rounded-xl border border-neutral-200 dark:border-white/[0.06] bg-white dark:bg-white/[0.02]">
            <span class="text-xs font-medium text-neutral-500 block">Total Active Subscriptions</span>
            <span class="text-2xl font-bold text-black dark:text-white mt-1 block font-mono">{{ $totalSubscriptions }}</span>
            <span class="text-[11px] text-neutral-400 mt-0.5 block">Across all customer teams</span>
        </div>

        <div class="p-4 rounded-xl border border-neutral-200 dark:border-white/[0.06] bg-white dark:bg-white/[0.02]">
            <span class="text-xs font-medium text-neutral-500 block">Hosted Custom Domains</span>
            <span class="text-2xl font-bold text-purple-400 mt-1 block font-mono">{{ $totalDomains }}</span>
            <span class="text-[11px] text-neutral-400 mt-0.5 block">Automated DKIM + DNS verification</span>
        </div>

        <div class="p-4 rounded-xl border border-neutral-200 dark:border-white/[0.06] bg-white dark:bg-white/[0.02]">
            <span class="text-xs font-medium text-neutral-500 block">Provisioned Mailboxes</span>
            <span class="text-2xl font-bold text-sky-400 mt-1 block font-mono">{{ $totalMailboxes }}</span>
            <span class="text-[11px] text-neutral-400 mt-0.5 block">
                Storage: {{ round(data_get($fleetStats, 'storage_used_bytes', 0) / (1024 * 1024 * 1024), 2) }} GB allocated
            </span>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex items-center gap-2 border-b border-black/[0.08] dark:border-white/[0.06] pb-3">
        <button wire:click="$set('activeTab', 'plans')" type="button"
            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $activeTab === 'plans' ? 'bg-purple-600 text-white font-semibold' : 'text-neutral-400 hover:text-white hover:bg-white/[0.05]' }}">
            Base Storage Plans ({{ count($plans) }})
        </button>
        <button wire:click="$set('activeTab', 'addons')" type="button"
            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $activeTab === 'addons' ? 'bg-purple-600 text-white font-semibold' : 'text-neutral-400 hover:text-white hover:bg-white/[0.05]' }}">
            Expansion Add-Ons ({{ count($addons) }})
        </button>
        <button wire:click="$set('activeTab', 'telemetry')" type="button"
            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $activeTab === 'telemetry' ? 'bg-purple-600 text-white font-semibold' : 'text-neutral-400 hover:text-white hover:bg-white/[0.05]' }}">
            Fleet & Mail Protocols
        </button>
    </div>

    {{-- TAB 1: Base Plans --}}
    @if ($activeTab === 'plans')
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-black dark:text-white">Storage Hosting Plans</h2>
                    <p class="text-xs text-neutral-400">Customers bring their custom domain and share pooled storage across their created mailboxes.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($plans as $p)
                    <div class="rounded-xl border border-neutral-200 dark:border-white/[0.08] bg-white dark:bg-[#121212] p-5 flex flex-col justify-between relative overflow-hidden transition-all hover:border-purple-500/50">
                        @if (! data_get($p, 'is_active', true))
                            <div class="absolute top-2 right-2 px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                Disabled
                            </div>
                        @endif

                        <div>
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-bold text-white">{{ data_get($p, 'name') }}</h3>
                                <span class="text-xs font-mono text-purple-400 font-bold">
                                    ${{ number_format(data_get($p, 'price_cents', 0) / 100, 2) }}/mo
                                </span>
                            </div>
                            <span class="text-xs text-neutral-400 font-mono">{{ data_get($p, 'slug') }}</span>

                            <div class="mt-4 space-y-2 border-t border-white/[0.06] pt-3">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-neutral-400">Storage Quota:</span>
                                    <span class="font-mono text-white font-semibold">
                                        @if(data_get($p, 'storage_mb') >= 1024)
                                            {{ round(data_get($p, 'storage_mb') / 1024, 1) }} GB
                                        @else
                                            {{ data_get($p, 'storage_mb') }} MB
                                        @endif
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-neutral-400">Max Mailboxes:</span>
                                    <span class="font-mono text-white font-semibold">{{ data_get($p, 'max_mailboxes') }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-neutral-400">Daily Send/Recv:</span>
                                    <span class="text-emerald-400 font-medium">Unlimited</span>
                                </div>
                            </div>

                            <div class="mt-4 border-t border-white/[0.06] pt-3">
                                <span class="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider block mb-1.5">Included Features:</span>
                                <ul class="space-y-1 text-xs text-neutral-300">
                                    @foreach ((array) data_get($p, 'features', []) as $feat)
                                        <li class="flex items-center gap-1.5">
                                            <span class="text-purple-400">✓</span> {{ $feat }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        <div class="mt-6 pt-4 border-t border-white/[0.06] flex items-center justify-between gap-2">
                            <button wire:click="togglePlanActive('{{ data_get($p, 'id') }}', {{ data_get($p, 'is_active', true) ? 'true' : 'false' }})"
                                type="button" class="text-xs text-neutral-400 hover:text-white transition-colors">
                                {{ data_get($p, 'is_active', true) ? 'Deactivate' : 'Enable' }}
                            </button>

                            <button wire:click="openEditPlanModal('{{ data_get($p, 'id') }}')" type="button"
                                class="button border border-purple-500/30 hover:bg-purple-500/20 text-xs py-1.5 px-3 text-purple-300">
                                Edit Plan & Quotas
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- TAB 2: Add-Ons --}}
    @if ($activeTab === 'addons')
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-black dark:text-white">Expansion Add-Ons & Extras</h2>
                    <p class="text-xs text-neutral-400">Paid modular upgrades customers can attach to their email subscription at any time.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($addons as $addon)
                    <div class="rounded-xl border border-neutral-200 dark:border-white/[0.08] bg-white dark:bg-[#121212] p-4 flex flex-col justify-between transition-all hover:border-purple-500/40">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider
                                    @if(data_get($addon, 'type') === 'storage') bg-blue-500/10 text-blue-400 border border-blue-500/20
                                    @elseif(data_get($addon, 'type') === 'mailbox') bg-emerald-500/10 text-emerald-400 border border-emerald-500/20
                                    @elseif(data_get($addon, 'type') === 'domain') bg-amber-500/10 text-amber-400 border border-amber-500/20
                                    @else bg-purple-500/10 text-purple-400 border border-purple-500/20 @endif">
                                    {{ data_get($addon, 'type') }}
                                </span>
                                <span class="text-xs font-mono font-bold text-white">
                                    ${{ number_format(data_get($addon, 'price_cents', 0) / 100, 2) }}/mo
                                </span>
                            </div>

                            <h3 class="text-sm font-bold text-white mt-2">{{ data_get($addon, 'name') }}</h3>
                            <span class="text-[11px] text-neutral-500 font-mono">{{ data_get($addon, 'slug') }}</span>

                            <div class="mt-3 p-2.5 rounded bg-black/40 border border-white/[0.04] text-xs font-mono text-neutral-300">
                                <pre class="text-[11px] whitespace-pre-wrap">{{ json_encode(data_get($addon, 'specs_json', []), JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-white/[0.06] flex items-center justify-between">
                            <button wire:click="toggleAddonActive('{{ data_get($addon, 'id') }}', {{ data_get($addon, 'is_active', true) ? 'true' : 'false' }})"
                                type="button" class="text-xs text-neutral-400 hover:text-white">
                                {{ data_get($addon, 'is_active', true) ? 'Disable' : 'Enable' }}
                            </button>
                            <button wire:click="openEditAddonModal('{{ data_get($addon, 'id') }}')" type="button"
                                class="button border border-neutral-700 hover:bg-white/[0.05] text-xs py-1 px-2.5 text-neutral-300">
                                Configure
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- TAB 3: Telemetry & Mail Protocols --}}
    @if ($activeTab === 'telemetry')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-xl border border-neutral-200 dark:border-white/[0.08] bg-white dark:bg-[#121212] p-5 space-y-4">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <x-reicon name="server" class="size-4 text-purple-400" />
                    WildDuck & ZoneMTA Infrastructure Endpoints
                </h3>
                <div class="space-y-3 text-xs">
                    <div class="p-3 rounded-lg bg-black/40 border border-white/[0.06]">
                        <span class="text-neutral-400 block mb-1 font-semibold">Inbound MX (Haraka SMTP)</span>
                        <div class="flex items-center justify-between font-mono text-neutral-200">
                            <span>Port 25 (STARTTLS)</span>
                            <span class="text-emerald-400">Active / Listening</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-lg bg-black/40 border border-white/[0.06]">
                        <span class="text-neutral-400 block mb-1 font-semibold">Client Submission (ZoneMTA SMTP)</span>
                        <div class="flex items-center justify-between font-mono text-neutral-200">
                            <span>Port 587 (STARTTLS) / Port 465 (SSL/TLS)</span>
                            <span class="text-emerald-400">Authenticated / DKIM Signed</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-lg bg-black/40 border border-white/[0.06]">
                        <span class="text-neutral-400 block mb-1 font-semibold">Client Retrieval (WildDuck IMAP)</span>
                        <div class="flex items-center justify-between font-mono text-neutral-200">
                            <span>Port 993 (SSL/TLS)</span>
                            <span class="text-emerald-400">High-Performance Storage</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-lg bg-black/40 border border-white/[0.06]">
                        <span class="text-neutral-400 block mb-1 font-semibold">Webmail UI Ingress</span>
                        <div class="flex items-center justify-between font-mono text-neutral-200">
                            <span>webmail.{{ env('PLATFORM_ROOT_DOMAIN', 'stxapp.io') }} (Caddy Auto-SSL)</span>
                            <a href="https://webmail.{{ env('PLATFORM_ROOT_DOMAIN', 'stxapp.io') }}" target="_blank" class="text-purple-400 underline">Open Webmail</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 dark:border-white/[0.08] bg-white dark:bg-[#121212] p-5 space-y-4">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <x-reicon name="shield" class="size-4 text-purple-400" />
                    Deliverability & Anti-Abuse Policies
                </h3>
                <div class="space-y-2 text-xs text-neutral-300">
                    <p class="leading-relaxed">
                        • <strong>No Advertised Send Caps:</strong> As requested, no daily send or receive quotas are enforced or displayed as plan barriers.
                    </p>
                    <p class="leading-relaxed">
                        • <strong>Automated 2048-bit DKIM:</strong> Generated on-demand for every customer domain and verified in real time before sending authorization.
                    </p>
                    <p class="leading-relaxed">
                        • <strong>Burst Anti-Spam Protection:</strong> Background rate limiters restrict outbound volume spikes (e.g. max 500 emails per 5 minutes per mailbox) to protect the server's clean IP reputation.
                    </p>
                    <p class="leading-relaxed">
                        • <strong>Port 25 Relay Fallback:</strong> If host datacenter blocks TCP port 25, ZoneMTA automatically switches to authenticated Smarthost forwarding over port 587.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL: Edit Plan --}}
    @if ($showPlanModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-[#121212] border border-white/10 rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">Edit Mail Hosting Plan</h3>
                    <button wire:click="$set('showPlanModal', false)" type="button" class="text-neutral-400 hover:text-white">✕</button>
                </div>

                <form wire:submit.prevent="savePlan" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-neutral-300 mb-1 font-semibold">Plan Display Name</label>
                        <input wire:model="planName" type="text" class="w-full bg-white/5 border border-white/10 rounded-lg p-2 text-white" />
                        @error('planName') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-neutral-300 mb-1 font-semibold">Storage Quota (MB)</label>
                            <input wire:model="planStorageMb" type="number" step="100" class="w-full bg-white/5 border border-white/10 rounded-lg p-2 text-white font-mono" />
                            @error('planStorageMb') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-neutral-300 mb-1 font-semibold">Max Mailboxes</label>
                            <input wire:model="planMaxMailboxes" type="number" step="1" class="w-full bg-white/5 border border-white/10 rounded-lg p-2 text-white font-mono" />
                            @error('planMaxMailboxes') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-neutral-300 mb-1 font-semibold">Price USD ($)</label>
                            <input wire:model="planPriceUsd" type="number" step="0.5" class="w-full bg-white/5 border border-white/10 rounded-lg p-2 text-white font-mono" />
                            @error('planPriceUsd') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-neutral-300 mb-1 font-semibold">Price LKR (Rs.)</label>
                            <input wire:model="planPriceLkr" type="number" step="50" class="w-full bg-white/5 border border-white/10 rounded-lg p-2 text-white font-mono" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-neutral-300 mb-1 font-semibold">Features (one per line)</label>
                        <textarea wire:model="planFeaturesText" rows="4" class="w-full bg-white/5 border border-white/10 rounded-lg p-2 text-white"></textarea>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input wire:model="planIsActive" type="checkbox" id="planIsActive" class="rounded bg-white/10 border-white/20 text-purple-600 focus:ring-0" />
                        <label for="planIsActive" class="text-neutral-300">Plan is active and visible on Storefront</label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
                        <button wire:click="$set('showPlanModal', false)" type="button" class="button border border-white/10 text-neutral-300 text-xs py-2 px-3">
                            Cancel
                        </button>
                        <button type="submit" class="button button-highlighted text-xs py-2 px-4 bg-purple-600 text-white font-bold">
                            Save Plan Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL: Edit Add-On --}}
    @if ($showAddonModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-[#121212] border border-white/10 rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">Edit Mail Add-On</h3>
                    <button wire:click="$set('showAddonModal', false)" type="button" class="text-neutral-400 hover:text-white">✕</button>
                </div>

                <form wire:submit.prevent="saveAddon" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-neutral-300 mb-1 font-semibold">Add-On Name</label>
                        <input wire:model="addonName" type="text" class="w-full bg-white/5 border border-white/10 rounded-lg p-2 text-white" />
                        @error('addonName') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-neutral-300 mb-1 font-semibold">Category Type</label>
                            <select wire:model="addonType" class="w-full bg-white/5 border border-white/10 rounded-lg p-2 text-white">
                                <option value="storage">Storage Expansion</option>
                                <option value="mailbox">Mailbox Pack</option>
                                <option value="domain">Domain Pack</option>
                                <option value="feature">Premium Feature</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-neutral-300 mb-1 font-semibold">Price USD ($/mo)</label>
                            <input wire:model="addonPriceUsd" type="number" step="0.25" class="w-full bg-white/5 border border-white/10 rounded-lg p-2 text-white font-mono" />
                            @error('addonPriceUsd') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-neutral-300 mb-1 font-semibold">Specifications JSON</label>
                        <textarea wire:model="addonSpecsJson" rows="3" class="w-full bg-white/5 border border-white/10 rounded-lg p-2 text-white font-mono"></textarea>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input wire:model="addonIsActive" type="checkbox" id="addonIsActive" class="rounded bg-white/10 border-white/20 text-purple-600 focus:ring-0" />
                        <label for="addonIsActive" class="text-neutral-300">Add-on is active and purchasable</label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
                        <button wire:click="$set('showAddonModal', false)" type="button" class="button border border-white/10 text-neutral-300 text-xs py-2 px-3">
                            Cancel
                        </button>
                        <button type="submit" class="button button-highlighted text-xs py-2 px-4 bg-purple-600 text-white font-bold">
                            Save Add-On
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>


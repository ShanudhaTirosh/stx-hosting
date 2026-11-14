<div class="max-w-6xl mx-auto p-6 space-y-8">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-neutral-800">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-purple-900/60 text-purple-300 border border-purple-700/50">
                    Platform Setup
                </span>
                @if ($lastInitializedAt)
                    <span class="text-xs text-emerald-400 font-mono">
                        Active since {{ $lastInitializedAt }}
                    </span>
                @endif
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight mt-1">Platform Domain &amp; Cloudflare Wildcard Setup</h1>
            <p class="text-xs text-neutral-400 mt-1 max-w-2xl">
                Configure your top-level platform domain, automated wildcard subdomains (<code>&lt;subdomain&gt;.app.yourdomain.com</code>), and sync automated DNS &amp; SSL certificates via Cloudflare.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" wire:click="saveSettingsOnly" class="px-4 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-semibold transition">
                Save Draft
            </button>
            <button type="button" wire:click="provisionDns" wire:loading.attr="disabled" class="px-5 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold shadow-lg transition flex items-center gap-2">
                <span wire:loading.remove wire:target="provisionDns">⚡ Verify &amp; Provision Cloudflare DNS</span>
                <span wire:loading wire:target="provisionDns" class="flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    Provisioning Records...
                </span>
            </button>
        </div>
    </div>

    <!-- Configuration Form -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Form Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Domain Coordinates -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4">
                <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                    <span>🌐</span> 1. Top-Level Platform Domain Coordinates
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="baseDomain" class="block text-xs font-semibold text-neutral-300 mb-1">Base Platform Domain</label>
                        <input id="baseDomain" type="text" wire:model.live.debounce.300ms="baseDomain" placeholder="e.g. stxhostings.com" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                        <span class="text-[11px] text-neutral-500 mt-1 block">Root apex domain managed in your Cloudflare account.</span>
                        @error('baseDomain') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="appsDomain" class="block text-xs font-semibold text-neutral-300 mb-1">Apps Wildcard Domain</label>
                        <input id="appsDomain" type="text" wire:model="appsDomain" placeholder="e.g. app.stxhostings.com" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                        <span class="text-[11px] text-neutral-500 mt-1 block">Customer apps receive <code>&lt;subdomain&gt;.{{ $appsDomain ?: 'app.yourdomain.com' }}</code></span>
                        @error('appsDomain') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="ingressIp" class="block text-xs font-semibold text-neutral-300 mb-1">Server Ingress Public IPv4</label>
                        <input id="ingressIp" type="text" wire:model="ingressIp" placeholder="e.g. 198.51.100.1" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                        <span class="text-[11px] text-neutral-500 mt-1 block">The public IP address of this VPS/server where Traefik and Caddy listen on ports 80 and 443.</span>
                        @error('ingressIp') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <!-- Cloudflare API Credentials -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span>☁️</span> 2. Cloudflare API Token Integration
                    </h2>
                    @if ($tokenStatus === 'valid')
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-950 text-emerald-400 border border-emerald-800">
                            Token Verified
                        </span>
                    @elseif ($tokenStatus === 'invalid')
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-rose-950 text-rose-400 border border-rose-800">
                            Token Invalid
                        </span>
                    @endif
                </div>

                <div class="space-y-3">
                    <div>
                        <label for="cfToken" class="block text-xs font-semibold text-neutral-300 mb-1">Cloudflare API Token</label>
                        <div class="flex items-center gap-2">
                            <input id="cfToken" type="password" wire:model="cloudflareToken" placeholder="Paste Cloudflare API Token..." class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                            <button type="button" wire:click="testToken(false)" wire:loading.attr="disabled" class="px-3 py-1.5 rounded bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-semibold whitespace-nowrap">
                                <span wire:loading.remove wire:target="testToken">Test Token</span>
                                <span wire:loading wire:target="testToken">Checking...</span>
                            </button>
                        </div>
                        <p class="text-[11px] text-neutral-400 mt-1.5">
                            Required permissions: <strong>Zone &rarr; Zone &rarr; Read</strong> and <strong>Zone &rarr; DNS &rarr; Edit</strong> on your zone resources.
                        </p>
                        @error('cloudflareToken') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    @if ($cloudflareZoneId)
                        <div class="p-3 rounded-lg bg-neutral-950 border border-neutral-800/80 flex items-center justify-between text-xs font-mono">
                            <span class="text-neutral-400">Detected Cloudflare Zone ID:</span>
                            <span class="text-purple-300 font-bold select-all">{{ $cloudflareZoneId }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Provisioning Results Table (if active) -->
            @if (count($recordsStatus) > 0)
                <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-3">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span>✅</span> Provisioned Cloudflare DNS Records
                    </h2>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs font-mono">
                            <thead class="border-b border-neutral-800 text-neutral-400">
                                <tr>
                                    <th class="py-2">Type</th>
                                    <th class="py-2">Hostname</th>
                                    <th class="py-2">Target (IP)</th>
                                    <th class="py-2">Proxy</th>
                                    <th class="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-800 text-neutral-300">
                                @foreach ($recordsStatus as $k => $rec)
                                    <tr>
                                        <td class="py-2 font-bold text-cyan-400">{{ $rec['type'] }}</td>
                                        <td class="py-2 text-white font-semibold">{{ $rec['name'] }}</td>
                                        <td class="py-2 text-neutral-400">{{ $rec['content'] }}</td>
                                        <td class="py-2">
                                            @if ($rec['proxied'])
                                                <span class="text-amber-400 font-bold">Proxied ☁️</span>
                                            @else
                                                <span class="text-neutral-500">DNS Only</span>
                                            @endif
                                        </td>
                                        <td class="py-2">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-950 text-emerald-300 border border-emerald-800">
                                                {{ $rec['action'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar / Visual Domain Map -->
        <div class="space-y-6">
            <!-- Architecture Map Card -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4">
                <h3 class="text-xs font-bold text-white uppercase tracking-wider">Automated Domain Topology</h3>
                <p class="text-[11px] text-neutral-400">
                    Once provisioned, all platform services and customer apps route automatically with instant HTTPS.
                </p>

                <div class="space-y-3 font-mono text-xs">
                    <div class="p-2.5 rounded bg-neutral-950 border border-neutral-800 space-y-1">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-purple-400 font-bold">Customer Wildcard</span>
                            <span class="text-emerald-400 text-[10px]">Instant HTTPS</span>
                        </div>
                        <div class="text-white font-bold truncate">*.{{ $appsDomain ?: 'app.stxhostings.com' }}</div>
                        <div class="text-[10px] text-neutral-500">All customer apps and templates</div>
                    </div>

                    <div class="p-2.5 rounded bg-neutral-950 border border-neutral-800 space-y-1">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-cyan-400 font-bold">Mail Hosting</span>
                            <span class="text-amber-400 text-[10px]">SMTP / IMAP</span>
                        </div>
                        <div class="text-white font-bold truncate">mail.{{ $baseDomain ?: 'stxhostings.com' }}</div>
                        <div class="text-[10px] text-neutral-500">WildDuck native mail engine</div>
                    </div>

                    <div class="p-2.5 rounded bg-neutral-950 border border-neutral-800 space-y-1">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-blue-400 font-bold">Webmail Client</span>
                            <span class="text-emerald-400 text-[10px]">HTTPS / WAF</span>
                        </div>
                        <div class="text-white font-bold truncate">webmail.{{ $baseDomain ?: 'stxhostings.com' }}</div>
                        <div class="text-[10px] text-neutral-500">Client browser webmail inbox</div>
                    </div>

                    <div class="p-2.5 rounded bg-neutral-950 border border-neutral-800 space-y-1">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-emerald-400 font-bold">Public Status</span>
                            <span class="text-emerald-400 text-[10px]">Telemetry</span>
                        </div>
                        <div class="text-white font-bold truncate">status.{{ $baseDomain ?: 'stxhostings.com' }}</div>
                        <div class="text-[10px] text-neutral-500">90-day uptime and telemetry</div>
                    </div>
                </div>
            </div>

            <!-- SSL Guidance -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-3">
                <h3 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                    <span>🔒</span> Automatic SSL Certificates
                </h3>
                <p class="text-[11px] text-neutral-400 leading-relaxed">
                    By binding the wildcard <code>*.{{ $appsDomain ?: 'app.stxhostings.com' }}</code> to your VPS IP in Cloudflare, Caddy and Traefik automatically negotiate Let's Encrypt / ZeroSSL certificates on-demand as soon as client applications are accessed.
                </p>
                <div class="p-2 rounded bg-neutral-950 border border-neutral-800/80 text-[10px] text-neutral-400 font-mono">
                    Zero manual DNS verification needed per app.
                </div>
            </div>
        </div>
    </div>
</div>


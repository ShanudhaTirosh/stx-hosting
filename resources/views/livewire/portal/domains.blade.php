<div 
    class="max-w-4xl mx-auto p-6 space-y-6"
    x-data="{
        initDomainRealtime() {
            if (window.supabaseClient) {
                const teamId = '{{ currentTeam()?->id }}';
                const channel = window.supabaseClient.channel('realtime:domains:team_id=eq.' + teamId);
                channel.on('broadcast', { event: 'domain_verified' }, () => {
                    $wire.$refresh();
                }).subscribe();
            }
        }
    }"
    x-init="initDomainRealtime()"
>
    <div class="pb-4 border-b border-neutral-800 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Custom Domains</h1>
            <p class="text-xs text-neutral-400 mt-1">
                Every app gets a free instant address@if ($appsDomain) like <span class="font-mono text-purple-400">your-app.{{ $appsDomain }}</span>@endif with automated wildcard HTTPS.
                Add your own domain below for custom branding.
            </p>
        </div>
    </div>

    <!-- Add Domain Form -->
    <form wire:submit.prevent="add" class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 flex flex-wrap items-end gap-3 shadow-sm">
        <div class="flex-1 min-w-[16rem]">
            <label for="new-domain" class="block text-xs font-bold text-neutral-300 mb-1">Add a custom domain</label>
            <input id="new-domain" type="text" wire:model="newDomain" placeholder="e.g. mybrand.com or app.mybrand.com" autocomplete="off" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono focus:border-purple-500">
            @error('newDomain') <span class="text-rose-400 text-xs block mt-1" role="alert">{{ $message }}</span> @enderror
        </div>
        <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs transition duration-150">
            Add Domain
        </button>
    </form>

    <!-- Domain Cards -->
    <div class="space-y-4">
        @forelse ($domains as $domain)
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4 shadow-sm" wire:key="domain-{{ $domain->id }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-2.5 h-2.5 rounded-full {{ $domain->isSslActive() ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400' }}"></div>
                        <div class="font-mono text-base text-white font-bold">{{ $domain->domain }}</div>
                    </div>
                    
                    <div class="flex items-center gap-3 text-xs">
                        @if ($domain->isSslActive())
                            <span class="px-2.5 py-1 rounded-full bg-emerald-950/80 border border-emerald-800/60 text-emerald-300 font-medium flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                                HTTPS Secured
                            </span>
                        @elseif ($domain->isDnsVerified())
                            <span class="px-2.5 py-1 rounded-full bg-blue-950/80 border border-blue-800/60 text-blue-300 font-medium">
                                DNS Verified (Issuing SSL)
                            </span>
                        @else
                            <span class="px-2.5 py-1 rounded-full bg-amber-950/80 border border-amber-800/60 text-amber-300 font-medium flex items-center gap-1.5">
                                <svg class="w-3 h-3 text-amber-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                Waiting for DNS
                            </span>
                            <button type="button" wire:click="verify('{{ $domain->uuid }}')" class="px-2.5 py-1 bg-neutral-800 hover:bg-neutral-700 text-purple-300 font-semibold rounded text-xs transition">
                                Check Now
                            </button>
                        @endif

                        <button type="button" wire:click="remove('{{ $domain->uuid }}')" wire:confirm="Remove this custom domain?" class="text-rose-400 hover:text-rose-300 text-xs px-2 py-1">
                            Remove
                        </button>
                    </div>
                </div>

                @unless ($domain->isSslActive())
                    <!-- Plain-Language DNS Setup Instructions -->
                    <div class="bg-neutral-950 border border-neutral-800/80 rounded-lg p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-neutral-300 uppercase tracking-wider">Required DNS Record</span>
                            <span class="text-[11px] text-neutral-500">Go to your domain provider (Cloudflare, Namecheap, GoDaddy)</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 font-mono text-xs">
                            <div class="bg-neutral-900/90 border border-neutral-800 p-2.5 rounded">
                                <div class="text-[10px] text-neutral-500 uppercase tracking-wider font-sans font-semibold">Record Type</div>
                                <div class="text-emerald-400 font-bold mt-0.5">{{ $domain->record_type ?: 'CNAME' }}</div>
                            </div>
                            <div class="bg-neutral-900/90 border border-neutral-800 p-2.5 rounded">
                                <div class="text-[10px] text-neutral-500 uppercase tracking-wider font-sans font-semibold">Host / Name</div>
                                <div class="text-neutral-200 mt-0.5 select-all">{{ substr_count($domain->domain, '.') === 1 ? '@' : explode('.', $domain->domain)[0] }}</div>
                            </div>
                            <div class="bg-neutral-900/90 border border-neutral-800 p-2.5 rounded">
                                <div class="text-[10px] text-neutral-500 uppercase tracking-wider font-sans font-semibold">Value / Target</div>
                                <div class="text-purple-300 mt-0.5 select-all truncate" title="{{ $domain->expected_target ?: ($ingressIp ?: 'ingress.stxapp.io') }}">
                                    {{ $domain->expected_target ?: ($ingressIp ?: 'ingress.stxapp.io') }}
                                </div>
                            </div>
                        </div>

                        @if ($domain->last_error_message)
                            <div class="text-[11px] text-amber-400 flex items-center gap-1.5 pt-1">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                                <span>{{ $domain->last_error_message }} Automatic background checks will keep retrying.</span>
                            </div>
                        @else
                            <div class="text-[11px] text-neutral-400">
                                Once created, DNS usually propagates in 1-5 minutes. We will detect it automatically and issue an SSL certificate.
                            </div>
                        @endif
                    </div>
                @else
                    <div class="bg-emerald-950/20 border border-emerald-900/40 rounded-lg p-3 text-xs text-emerald-300 flex items-center justify-between">
                        <span>This domain is pointing to STX Hosting and is protected with an automatically renewing SSL certificate.</span>
                        <a href="https://{{ $domain->domain }}" target="_blank" rel="noopener noreferrer" class="text-emerald-400 hover:underline font-semibold flex items-center gap-1">
                            Visit Site
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                @endunless
            </div>
        @empty
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-8 text-center space-y-2">
                <div class="w-12 h-12 mx-auto rounded-full bg-neutral-800 flex items-center justify-center text-neutral-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                </div>
                <div class="text-sm font-bold text-white">No custom domains added yet</div>
                <p class="text-xs text-neutral-400 max-w-sm mx-auto">
                    Add your own domain above to give your applications a custom branded address with automatic HTTPS.
                </p>
            </div>
        @endforelse
    </div>
</div>

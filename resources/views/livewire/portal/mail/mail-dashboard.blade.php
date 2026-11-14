<div class="max-w-6xl mx-auto p-6 space-y-6">
    <!-- Header -->
    <div class="pb-4 border-b border-neutral-800 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded bg-purple-500/10 text-purple-400 border border-purple-500/20">WildDuck Mail</span>
                <span class="text-xs text-neutral-400 font-mono">{{ $activePlan['name'] ?? 'Business Email' }}</span>
                @if ($subscription)
                    <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded {{ $subscription['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                        {{ ucfirst($subscription['status']) }}
                    </span>
                @endif
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Business Email Hosting</h1>
            <p class="text-xs text-neutral-400 mt-1">
                Custom domain business mailboxes with native IMAP/SMTP encryption, anti-spam, and responsive webmail.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ $webmailUrl }}" target="_blank" rel="noopener noreferrer" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl text-xs flex items-center gap-2 shadow-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Open Webmail
            </a>
            @if ($subscription)
                <button wire:click="openNewDomain" class="px-3.5 py-2 bg-neutral-800 hover:bg-neutral-700 text-white font-semibold rounded-xl text-xs transition border border-neutral-700">
                    + Add Domain
                </button>
            @endif
        </div>
    </div>

    @if (! $subscription)
        <!-- Empty State when User has no Active Mail Subscription -->
        <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-10 text-center space-y-5 shadow-xl">
            <div class="w-16 h-16 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center mx-auto">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <h2 class="text-xl font-bold text-white tracking-tight">No Active Business Email Subscription</h2>
                <p class="text-neutral-400 text-xs max-w-lg mx-auto mt-2 leading-relaxed">
                    Connect your own custom domains, create encrypted employee mailboxes, route catch-all addresses, and manage your team's communication with zero data mining and 99.9% inbox deliverability.
                </p>
            </div>
            <div class="pt-2 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('storefront.email-hosting') }}" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl text-xs inline-flex items-center gap-2 shadow-lg transition">
                    Browse Email Plans &amp; Pricing &rarr;
                </a>
            </div>
        </div>
    @else
        <!-- Storage Near-Capacity / Over-Capacity Warning Banner -->
        @if ($isNearCapacity)
            <div class="p-4 rounded-xl border {{ $usedPercentage >= 100 ? 'border-rose-500/40 bg-rose-500/10' : 'border-amber-500/40 bg-amber-500/10' }} flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="p-1 rounded-lg {{ $usedPercentage >= 100 ? 'bg-rose-500/20 text-rose-400' : 'bg-amber-500/20 text-amber-400' }} shrink-0 mt-0.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold {{ $usedPercentage >= 100 ? 'text-rose-300' : 'text-amber-300' }}">
                            {{ $usedPercentage >= 100 ? 'Storage Quota Limit Reached (100%)' : 'Storage Pool Warning (>= 90% Utilized)' }}
                        </h4>
                        <p class="text-[11px] text-neutral-300 mt-0.5">
                            Your mailboxes have utilized {{ $totalUsedMb }} MB of {{ $totalStorageMb }} MB available storage. 
                            {{ $usedPercentage >= 100 ? 'Incoming emails may bounce with a 552 storage limit code. Apply an expansion add-on immediately.' : 'Please expand your storage pool or delete old emails to ensure uninterrupted email reception.' }}
                        </p>
                    </div>
                </div>
                <button wire:click="refreshStorageUsage" class="text-xs px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-white rounded-lg transition border border-neutral-700 shrink-0">
                    Refresh Usage
                </button>
            </div>
        @endif

        <!-- Storage Pool & Quota Stat Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Pooled Storage -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-3">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-medium text-neutral-400">Shared Storage Pool</span>
                    <span class="font-bold text-white font-mono">{{ $totalUsedMb }} MB / {{ $totalStorageMb }} MB</span>
                </div>
                <div class="w-full bg-neutral-950 rounded-full h-2 overflow-hidden border border-neutral-800">
                    <div class="h-full {{ $usedPercentage >= 90 ? 'bg-amber-500' : 'bg-gradient-to-r from-purple-500 to-indigo-500' }} transition-all duration-300" style="width: {{ $usedPercentage }}%"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-neutral-500 pt-0.5">
                    <span>{{ $usedPercentage }}% consumed across all boxes</span>
                    <button wire:click="refreshStorageUsage" wire:loading.attr="disabled" class="text-purple-400 hover:text-purple-300 flex items-center gap-1">
                        <svg wire:loading.class="animate-spin" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Mailboxes Allocation -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-medium text-neutral-400">Mailboxes Provisioned</span>
                    <span class="font-bold text-white font-mono">{{ count($mailboxes) }} / {{ $subscription['max_mailboxes'] ?? 5 }}</span>
                </div>
                <div class="flex items-center gap-1.5 pt-1">
                    @php $maxBoxes = $subscription['max_mailboxes'] ?? 5; @endphp
                    @for ($i = 0; $i < min($maxBoxes, 20); $i++)
                        <div class="h-2 flex-1 rounded-full {{ $i < count($mailboxes) ? 'bg-purple-500' : 'bg-neutral-800' }}"></div>
                    @endfor
                </div>
                <p class="text-[11px] text-neutral-500">{{ max(0, ($subscription['max_mailboxes'] ?? 5) - count($mailboxes)) }} mailbox slot(s) remaining.</p>
            </div>

            <!-- Inbound / Outbound Ports -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-1.5">
                <div class="text-xs font-medium text-neutral-400">Connection Endpoints</div>
                <div class="text-xs font-mono text-purple-300 font-bold truncate">{{ $mailHost }}</div>
                <div class="text-[11px] text-neutral-400 flex items-center justify-between pt-1">
                    <span>IMAP: 993 (SSL)</span>
                    <span>SMTP: 587 (TLS)</span>
                </div>
            </div>
        </div>

        <!-- Domains Section -->
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider">Custom Mail Domains</h2>
                    <span class="text-xs text-neutral-500 font-mono">({{ count($domains) }} / {{ $subscription['max_domains'] ?? 1 }})</span>
                </div>
                <button wire:click="openNewDomain" class="text-xs text-purple-400 hover:text-purple-300 font-semibold">+ Add Another Domain</button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @forelse ($domains as $d)
                    <div 
                        wire:click="$set('selectedDomainId', '{{ $d['id'] }}')" 
                        class="p-4 rounded-xl border transition-all cursor-pointer {{ $selectedDomainId === $d['id'] ? 'bg-purple-950/20 border-purple-500/50 shadow-md' : 'bg-neutral-950 border-neutral-800 hover:border-neutral-700' }}"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-mono font-bold text-sm text-white truncate">{{ $d['domain'] }}</span>
                            @if ($d['is_verified'])
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">DNS Verified</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">DNS Pending</span>
                            @endif
                        </div>
                        
                        <div class="flex items-center justify-between text-xs pt-2 border-t border-neutral-900">
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click.stop="openDnsHelper('{{ $d['id'] }}')" class="text-[11px] text-purple-400 hover:underline">
                                    DNS Records
                                </button>
                                @if (! $d['is_verified'])
                                    <button type="button" wire:click.stop="verifyDomainDns('{{ $d['id'] }}')" class="text-[11px] text-neutral-300 hover:text-white bg-neutral-800 px-2 py-0.5 rounded">
                                        Check
                                    </button>
                                @endif
                            </div>
                            <button type="button" wire:click.stop="deleteDomain('{{ $d['id'] }}')" wire:confirm="Are you sure you want to remove domain '{{ $d['domain'] }}'?" class="text-[11px] text-rose-400 hover:text-rose-300 transition">
                                Delete
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-6 text-center text-xs text-neutral-500">
                        No custom domains added yet. Click <span class="text-purple-400 font-semibold cursor-pointer" wire:click="openNewDomain">Add Domain</span> to connect your email domain.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Mailbox Management Table -->
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden shadow-sm">
            <div class="p-5 border-b border-neutral-800 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider">Mailboxes</h2>
                    <p class="text-xs text-neutral-400 mt-0.5">Active business email accounts on your verified custom domains.</p>
                </div>
                
                <button wire:click="openNewMailbox" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs transition flex items-center gap-1.5 shadow">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create Mailbox
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-neutral-950/60 text-neutral-400 font-bold uppercase tracking-wider text-[10px] border-b border-neutral-800">
                        <tr>
                            <th class="py-3 px-5">Email Address</th>
                            <th class="py-3 px-5">Display Name</th>
                            <th class="py-3 px-5">Storage Quota</th>
                            <th class="py-3 px-5">Status</th>
                            <th class="py-3 px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-800 text-neutral-300">
                        @forelse ($mailboxes as $box)
                            <tr class="hover:bg-neutral-800/30 transition-colors">
                                <td class="py-3.5 px-5 font-mono font-bold text-white">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full {{ ($box['is_active'] ?? true) ? 'bg-emerald-400' : 'bg-rose-400' }}"></span>
                                        <span>{{ $box['address'] }}</span>
                                        @if (! empty($box['is_catch_all']))
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">Catch-All</span>
                                        @endif
                                    </div>
                                    @if (! empty($box['aliases']) && count($box['aliases']) > 0)
                                        <div class="text-[10px] text-neutral-400 font-normal pl-4 mt-0.5">
                                            Aliases: {{ implode(', ', array_column($box['aliases'], 'alias_address')) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5">{{ $box['name'] }}</td>
                                <td class="py-3.5 px-5 font-mono">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $box['quota_mb'] ? $box['quota_mb'] . ' MB' : 'Shared Pool' }}</span>
                                        <button wire:click="promptEditQuota('{{ $box['id'] }}')" class="text-neutral-500 hover:text-white text-[10px] underline">
                                            Edit
                                        </button>
                                    </div>
                                </td>
                                <td class="py-3.5 px-5">
                                    @if ($box['is_active'] ?? true)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Active</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">Suspended</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5 text-right space-x-2">
                                    <a href="{{ $webmailUrl }}/login?email={{ urlencode($box['address']) }}" target="_blank" rel="noopener noreferrer" class="text-purple-400 hover:text-purple-300 font-semibold transition">Webmail</a>
                                    <button wire:click="openCredentials('{{ $box['address'] }}', '{{ $box['id'] }}')" class="text-neutral-400 hover:text-white transition">Setup &amp; Ports</button>
                                    <button wire:click="openAliasesModal('{{ $box['id'] }}')" class="text-neutral-400 hover:text-white transition">Aliases</button>
                                    <button wire:click="toggleCatchAll('{{ $box['id'] }}')" class="text-neutral-400 hover:text-white transition" title="Toggle Catch-All Routing">
                                        {{ ! empty($box['is_catch_all']) ? 'Unset Catch-All' : 'Make Catch-All' }}
                                    </button>
                                    <button wire:click="toggleMailboxStatus('{{ $box['id'] }}')" class="{{ ($box['is_active'] ?? true) ? 'text-amber-400 hover:text-amber-300' : 'text-emerald-400 hover:text-emerald-300' }} transition">
                                        {{ ($box['is_active'] ?? true) ? 'Suspend' : 'Activate' }}
                                    </button>
                                    <button wire:click="promptPasswordReset('{{ $box['wildduck_user_id'] }}')" class="text-neutral-400 hover:text-white transition">Reset Pass</button>
                                    <button wire:click="deleteMailbox('{{ $box['id'] }}')" wire:confirm="Are you sure you want to permanently delete mailbox {{ $box['address'] }}?" class="text-rose-400 hover:text-rose-300 transition">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-xs text-neutral-500">
                                    No mailboxes created yet. Click <span class="text-purple-400 font-semibold cursor-pointer" wire:click="openNewMailbox">Create Mailbox</span> to get started.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Expansion Add-Ons Section -->
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">Expansion Add-Ons</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($addons as $addon)
                    <div class="bg-neutral-950 border border-neutral-800 rounded-xl p-4 flex flex-col justify-between space-y-3">
                        <div>
                            <div class="text-xs font-bold text-white">{{ $addon['name'] }}</div>
                            <div class="text-base font-bold text-purple-400 font-mono mt-1">${{ number_format($addon['price_cents'] / 100, 2) }} <span class="text-[10px] text-neutral-500">/ mo</span></div>
                            <div class="text-[11px] text-neutral-400 mt-1">
                                @if (! empty($addon['specs_json']['storage_mb']))
                                    +{{ number_format($addon['specs_json']['storage_mb'] / 1024, 0) }} GB Pooled Storage
                                @elseif (! empty($addon['specs_json']['extra_mailboxes']))
                                    +{{ $addon['specs_json']['extra_mailboxes'] }} Mailbox Slots
                                @elseif (! empty($addon['specs_json']['extra_domains']))
                                    +{{ $addon['specs_json']['extra_domains'] }} Domain Slot
                                @endif
                            </div>
                        </div>
                        <button wire:click="applyAddon('{{ $addon['id'] }}')" class="w-full py-1.5 px-3 bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-semibold rounded-lg transition border border-neutral-700">
                            Apply Add-On
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Modal: Add Custom Domain -->
    @if ($showNewDomainModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <h3 class="text-lg font-bold text-white">Add Custom Mail Domain</h3>
                <p class="text-xs text-neutral-400">Enter the domain you want to send and receive emails on (e.g. acme.com).</p>
                <form wire:submit.prevent="addDomain" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-neutral-300 mb-1">Domain Name</label>
                        <input type="text" wire:model="newDomainName" placeholder="e.g. acme.com" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono focus:border-purple-500" required>
                        @error('newDomainName') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showNewDomainModal', false)" class="px-4 py-2 text-xs font-semibold text-neutral-400 hover:text-white">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl text-xs">Add &amp; Configure</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal: Create Mailbox -->
    @if ($showNewMailboxModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <h3 class="text-lg font-bold text-white">Create New Mailbox</h3>
                <form wire:submit.prevent="createMailbox" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-neutral-300 mb-1">Email Username</label>
                        <div class="flex items-center">
                            <input type="text" wire:model="newMailboxUsername" placeholder="alex" class="input input-sm bg-neutral-950 border-neutral-700 text-white rounded-l text-xs font-mono flex-1 focus:border-purple-500" required>
                            <span class="bg-neutral-800 px-3 py-1.5 border border-l-0 border-neutral-700 rounded-r text-xs font-mono text-neutral-400">@ {{ collect($domains)->firstWhere('id', $selectedDomainId)['domain'] ?? 'domain.com' }}</span>
                        </div>
                        @error('newMailboxUsername') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-neutral-300 mb-1">Display Name</label>
                        <input type="text" wire:model="newMailboxName" placeholder="Alex Morgan" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs focus:border-purple-500" required>
                        @error('newMailboxName') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-neutral-300 mb-1">Password</label>
                        <input type="password" wire:model="newMailboxPassword" placeholder="Minimum 8 characters" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs focus:border-purple-500" required>
                        @error('newMailboxPassword') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-neutral-300 mb-1">Custom Quota Limit (MB) <span class="text-neutral-500 font-normal">(Optional)</span></label>
                        <input type="number" wire:model="newMailboxQuotaMb" placeholder="Leave empty for unmetered pool share" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono focus:border-purple-500">
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showNewMailboxModal', false)" class="px-4 py-2 text-xs font-semibold text-neutral-400 hover:text-white">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl text-xs">Create Mailbox</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal: Dynamic DNS Pointing Studio & Diagnostics -->
    @if ($showDnsHelperModal && $selectedDnsDomain)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-3xl w-full p-6 space-y-5 shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-white tracking-tight">Dynamic DNS Setup: {{ $selectedDnsDomain['domain'] }}</h3>
                            @if (! empty($selectedDnsDomain['is_verified']))
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">DNS Active</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">Action Required</span>
                            @endif
                        </div>
                        <p class="text-xs text-neutral-400 mt-0.5">Select your DNS provider below for customized record instructions and live propagation checks.</p>
                    </div>
                    <button wire:click="$set('showDnsHelperModal', false)" class="text-neutral-500 hover:text-white text-xl font-bold">&times;</button>
                </div>

                <!-- Scrollable Body -->
                <div class="space-y-4 overflow-y-auto pr-1">
                    <!-- Provider Tabs -->
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-wider text-neutral-400 mb-2">DNS Provider &amp; Registrar Presets</div>
                        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-neutral-950 rounded-xl border border-neutral-800">
                            @foreach ($dnsProviders as $key => $prov)
                                <button 
                                    type="button"
                                    wire:click="setDnsProvider('{{ $key }}')" 
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 {{ $selectedDnsProvider === $key ? 'bg-purple-600 text-white shadow' : 'text-neutral-400 hover:text-white hover:bg-neutral-800/60' }}"
                                >
                                    {{ $prov['name'] }}
                                    @if ($key === 'cloudflare')
                                        <span class="px-1 py-0.2 rounded text-[9px] bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30">DNS Only</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Provider Specific Warning / Advice Box -->
                    @if (! empty($dnsProviders[$selectedDnsProvider]['warning']))
                        <div class="p-3.5 rounded-xl border {{ $selectedDnsProvider === 'cloudflare' ? 'border-amber-500/40 bg-amber-500/10 text-amber-200' : 'border-neutral-800 bg-neutral-950 text-neutral-300' }} text-xs flex items-start justify-between gap-3">
                            <div class="flex items-start gap-2.5">
                                <span class="text-base leading-none mt-0.5">{{ $selectedDnsProvider === 'cloudflare' ? '⚠️' : '💡' }}</span>
                                <div>
                                    <div class="font-bold {{ $selectedDnsProvider === 'cloudflare' ? 'text-amber-300' : 'text-white' }}">
                                        {{ $dnsProviders[$selectedDnsProvider]['name'] }} Guidance
                                    </div>
                                    <p class="text-[11px] mt-0.5 opacity-90">{{ $dnsProviders[$selectedDnsProvider]['warning'] }}</p>
                                </div>
                            </div>
                            @if (! empty($dnsProviders[$selectedDnsProvider]['dashboard_url']))
                                <a href="{{ $dnsProviders[$selectedDnsProvider]['dashboard_url'] }}" target="_blank" rel="noopener noreferrer" class="px-2.5 py-1 bg-neutral-800 hover:bg-neutral-700 text-white font-semibold rounded text-[11px] shrink-0 border border-neutral-700 inline-flex items-center gap-1">
                                    Open {{ $dnsProviders[$selectedDnsProvider]['name'] }} &rarr;
                                </a>
                            @endif
                        </div>
                    @endif

                    @if ($selectedDnsProvider === 'generic')
                        <!-- BIND Zone Snippet View -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-white uppercase tracking-wider text-[11px]">RFC BIND Zone File Snippet</span>
                                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('bindZoneContent').innerText); alert('Zone file snippet copied to clipboard!');" class="text-purple-400 hover:underline text-[11px]">
                                    Copy Entire Zone File
                                </button>
                            </div>
                            <pre id="bindZoneContent" class="bg-neutral-950 p-4 rounded-xl border border-neutral-800 text-[11px] font-mono text-neutral-300 overflow-x-auto whitespace-pre">{{ $bindZoneSnippet }}</pre>
                        </div>
                    @else
                        <!-- Formatted DNS Records Table for Registrar -->
                        <div class="space-y-2.5">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Required DNS Records</div>
                            <div class="divide-y divide-neutral-800 bg-neutral-950 rounded-xl border border-neutral-800 overflow-hidden text-xs">
                                @foreach ($providerDnsRecords as $record)
                                    <div class="p-3.5 space-y-2">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-purple-500/10 text-purple-300 border border-purple-500/20">
                                                    {{ $record['type'] }}
                                                </span>
                                                <span class="text-white font-semibold text-xs">{{ $record['purpose'] }}</span>
                                                @if (! empty($record['required']))
                                                    <span class="text-[9px] uppercase font-bold text-rose-400 bg-rose-500/10 px-1.5 py-0.2 rounded">Required</span>
                                                @endif
                                                @if (! empty($record['proxy']))
                                                    <span class="text-[9px] uppercase font-bold text-amber-300 bg-amber-500/10 px-1.5 py-0.2 rounded border border-amber-500/20">{{ $record['proxy'] }}</span>
                                                @endif
                                            </div>
                                            <button 
                                                type="button" 
                                                onclick="navigator.clipboard.writeText('{{ addslashes($record['value']) }}')" 
                                                class="text-purple-400 hover:text-purple-300 text-[11px] font-semibold flex items-center gap-1"
                                            >
                                                Copy Value
                                            </button>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-12 gap-2 font-mono text-xs pt-1">
                                            <div class="md:col-span-4 bg-neutral-900/80 px-2.5 py-1.5 rounded flex items-center justify-between">
                                                <span class="text-neutral-500 text-[10px] uppercase font-bold">Host:</span>
                                                <span class="text-white font-bold select-all">{{ $record['host'] }}</span>
                                            </div>
                                            <div class="md:col-span-8 bg-neutral-900/80 px-2.5 py-1.5 rounded flex items-center justify-between overflow-hidden">
                                                <span class="text-neutral-500 text-[10px] uppercase font-bold mr-2 shrink-0">Value:</span>
                                                <span class="text-neutral-200 truncate select-all">{{ $record['value'] }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Live Public DNS Health Diagnostic Panel -->
                    <div class="bg-neutral-950 border border-neutral-800 rounded-xl p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-bold text-white uppercase tracking-wider">Live Public DNS Propagation Inspector</h4>
                                <p class="text-[11px] text-neutral-400 mt-0.5">Tests what the global public internet currently resolves for your domain.</p>
                            </div>
                            <button 
                                type="button" 
                                wire:click="runLiveDnsDiagnostic('{{ $selectedDnsDomain['id'] }}')" 
                                wire:loading.attr="disabled"
                                class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-white font-semibold rounded-lg text-xs transition border border-neutral-700 flex items-center gap-1.5 shrink-0"
                            >
                                <svg wire:loading.class="animate-spin" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span wire:loading.remove wire:target="runLiveDnsDiagnostic">Inspect Live DNS</span>
                                <span wire:loading wire:target="runLiveDnsDiagnostic">Probing DNS...</span>
                            </button>
                        </div>

                        @if (! empty($liveDnsDiagnostics))
                            <div class="divide-y divide-neutral-800 border border-neutral-800 rounded-lg overflow-hidden text-xs">
                                @foreach ($liveDnsDiagnostics as $diag)
                                    <div class="p-3 bg-neutral-900/60 space-y-1.5">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-white">{{ $diag['type'] }}</span>
                                                <span class="text-neutral-500 font-mono text-[11px]">({{ $diag['record_name'] }})</span>
                                            </div>
                                            @if ($diag['status'] === 'matched')
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Active &amp; Matching</span>
                                            @elseif ($diag['status'] === 'mismatch')
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">Config Mismatch</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">Not Detected</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-neutral-300 font-mono bg-neutral-950 p-2 rounded border border-neutral-800 truncate">
                                            <span class="text-neutral-500">Live Detected:</span> {{ $diag['found'] }}
                                        </div>
                                        <p class="text-[11px] {{ $diag['status'] === 'matched' ? 'text-emerald-400' : 'text-amber-300' }}">
                                            &bull; {{ $diag['tip'] }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-between pt-3 border-t border-neutral-800 shrink-0">
                    <span class="text-[11px] text-neutral-500">Global DNS propagation usually takes between 1 and 15 minutes.</span>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="$set('showDnsHelperModal', false)" class="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white font-semibold rounded-xl text-xs">Close</button>
                        <button type="button" wire:click="verifyDomainDns('{{ $selectedDnsDomain['id'] }}')" wire:loading.attr="disabled" class="px-5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl text-xs shadow flex items-center gap-1.5">
                            <span wire:loading.remove wire:target="verifyDomainDns">Verify &amp; Activate</span>
                            <span wire:loading wire:target="verifyDomainDns">Validating...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Client Setup & Device Connection Studio -->
    @if ($showCredentialsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-2xl w-full p-6 space-y-5 shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-white tracking-tight">Email Setup &amp; Device Studio</h3>
                            <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-purple-500/10 text-purple-300 border border-purple-500/20">{{ $selectedMailboxAddress }}</span>
                        </div>
                        <p class="text-xs text-neutral-400 mt-0.5">Connect your business email to Apple devices, Outlook, Thunderbird, or mobile apps.</p>
                    </div>
                    <button wire:click="$set('showCredentialsModal', false)" class="text-neutral-500 hover:text-white text-xl font-bold">&times;</button>
                </div>

                <!-- Scrollable Body -->
                <div class="space-y-4 overflow-y-auto pr-1">
                    <!-- Client Platform Tabs -->
                    <div>
                        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-neutral-950 rounded-xl border border-neutral-800">
                            <button 
                                type="button"
                                wire:click="setClientPlatform('apple')" 
                                class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 {{ $selectedClientPlatform === 'apple' ? 'bg-purple-600 text-white shadow' : 'text-neutral-400 hover:text-white' }}"
                            >
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.37c.63-.77 1.06-1.85.94-2.93-.91.04-2.02.61-2.67 1.38-.58.67-.99 1.77-.85 2.81 1.02.08 2.06-.54 2.58-1.26z"/></svg>
                                Apple (iOS / macOS)
                            </button>
                            <button 
                                type="button"
                                wire:click="setClientPlatform('outlook')" 
                                class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 {{ $selectedClientPlatform === 'outlook' ? 'bg-purple-600 text-white shadow' : 'text-neutral-400 hover:text-white' }}"
                            >
                                Outlook
                            </button>
                            <button 
                                type="button"
                                wire:click="setClientPlatform('thunderbird')" 
                                class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 {{ $selectedClientPlatform === 'thunderbird' ? 'bg-purple-600 text-white shadow' : 'text-neutral-400 hover:text-white' }}"
                            >
                                Thunderbird
                            </button>
                            <button 
                                type="button"
                                wire:click="setClientPlatform('android')" 
                                class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 {{ $selectedClientPlatform === 'android' ? 'bg-purple-600 text-white shadow' : 'text-neutral-400 hover:text-white' }}"
                            >
                                Android / Mobile
                            </button>
                            <button 
                                type="button"
                                wire:click="setClientPlatform('generic')" 
                                class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 {{ $selectedClientPlatform === 'generic' ? 'bg-purple-600 text-white shadow' : 'text-neutral-400 hover:text-white' }}"
                            >
                                Manual IMAP / SMTP
                            </button>
                        </div>
                    </div>

                    @if ($selectedClientPlatform === 'apple')
                        <!-- 1-Click Apple Mail Profile (.mobileconfig) -->
                        <div class="bg-neutral-950 border border-purple-500/30 rounded-xl p-5 space-y-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-bold text-white flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse"></span>
                                        1-Click Apple Configuration Profile
                                    </h4>
                                    <p class="text-xs text-neutral-400 mt-1">
                                        Instantly sets up Apple Mail on iPhone, iPad, and Mac without manually typing server hostnames, port numbers, or SSL certificates.
                                    </p>
                                </div>
                                @if ($selectedMailboxId)
                                    <a 
                                        href="{{ route('portal.mail.apple-profile', ['mailboxId' => $selectedMailboxId]) }}" 
                                        class="px-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl text-xs inline-flex items-center gap-2 shadow-lg transition shrink-0"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        Download Apple Profile
                                    </a>
                                @endif
                            </div>

                            <div class="p-3 bg-neutral-900/80 rounded-lg border border-neutral-800 text-xs text-neutral-300 space-y-1.5">
                                <div class="font-bold text-purple-300 text-[11px] uppercase tracking-wider">How to install on iOS / macOS:</div>
                                <ol class="list-decimal list-inside space-y-1 text-[11px] text-neutral-400">
                                    <li>Tap <strong>Download Apple Profile</strong> above on your device.</li>
                                    <li>Open <strong>Settings &rarr; Profile Downloaded</strong> on iPhone/iPad (or <strong>System Settings &rarr; Profiles</strong> on Mac).</li>
                                    <li>Tap <strong>Install</strong>, enter your device passcode, and input your mailbox password once.</li>
                                </ol>
                            </div>
                        </div>
                    @elseif ($selectedClientPlatform === 'outlook' || $selectedClientPlatform === 'thunderbird')
                        <!-- RFC Autodiscover & Autoconfig Guidance -->
                        <div class="bg-neutral-950 border border-neutral-800 rounded-xl p-4 space-y-3">
                            <div class="flex items-start gap-3">
                                <div class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400 shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">
                                        {{ $selectedClientPlatform === 'outlook' ? 'Automated Outlook Autodiscover' : 'Automated Thunderbird Autoconfig' }} Enabled
                                    </h4>
                                    <p class="text-xs text-neutral-400 mt-1">
                                        {{ $selectedClientPlatform === 'outlook' ? 'Microsoft Outlook automatically detects incoming and outgoing ports upon entering your email address.' : 'Mozilla Thunderbird automatically retrieves encrypted IMAP and SMTP configurations in real time.' }}
                                    </p>
                                </div>
                            </div>

                            <div class="bg-neutral-900 p-3 rounded-lg border border-neutral-800 text-xs text-neutral-300 space-y-1 font-mono">
                                <div><span class="text-neutral-500">Account:</span> <span class="text-purple-300 font-bold">{{ $selectedMailboxAddress }}</span></div>
                                <div><span class="text-neutral-500">Auto-Detected Server:</span> {{ $mailHost }} (IMAP 993 / SMTP 587)</div>
                            </div>
                        </div>
                    @endif

                    <!-- Manual Server Connection Parameters (Exposed Details) -->
                    <div class="bg-neutral-950 border border-neutral-800 rounded-xl p-4 space-y-3 font-mono text-xs">
                        <div class="flex items-center justify-between border-b border-neutral-800/80 pb-2">
                            <span class="text-[11px] font-sans font-bold uppercase tracking-wider text-neutral-400">Connection Endpoints</span>
                            <span class="text-[10px] font-sans text-neutral-500">Native TLS 1.3 Encryption</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                            <!-- Inbound Server -->
                            <div class="p-3 bg-neutral-900/60 rounded-lg border border-neutral-800 space-y-1.5">
                                <div class="text-[10px] uppercase font-bold text-purple-400 font-sans">Incoming Mail (IMAP)</div>
                                <div class="text-white font-bold">{{ $mailHost }}</div>
                                <div class="text-[11px] text-neutral-400 flex items-center justify-between pt-1">
                                    <span>Port: <strong class="text-white">993</strong></span>
                                    <span>Security: <strong class="text-white">SSL / TLS</strong></span>
                                </div>
                                <button type="button" onclick="navigator.clipboard.writeText('{{ $mailHost }}')" class="text-[10px] text-purple-400 hover:underline font-sans pt-0.5">Copy Hostname</button>
                            </div>

                            <!-- Outbound Server -->
                            <div class="p-3 bg-neutral-900/60 rounded-lg border border-neutral-800 space-y-1.5">
                                <div class="text-[10px] uppercase font-bold text-purple-400 font-sans">Outgoing Mail (SMTP)</div>
                                <div class="text-white font-bold">{{ $mailHost }}</div>
                                <div class="text-[11px] text-neutral-400 flex items-center justify-between pt-1">
                                    <span>Port: <strong class="text-white">587</strong> (or 465)</span>
                                    <span>Security: <strong class="text-white">STARTTLS</strong></span>
                                </div>
                                <button type="button" onclick="navigator.clipboard.writeText('{{ $mailHost }}')" class="text-[10px] text-purple-400 hover:underline font-sans pt-0.5">Copy Hostname</button>
                            </div>
                        </div>

                        <div class="pt-1 space-y-1">
                            <div class="text-[10px] uppercase font-bold text-neutral-500 font-sans">Account Username</div>
                            <div class="flex items-center justify-between bg-neutral-900 px-3 py-1.5 rounded">
                                <span class="text-purple-300 font-bold">{{ $selectedMailboxAddress }}</span>
                                <button type="button" onclick="navigator.clipboard.writeText('{{ $selectedMailboxAddress }}')" class="text-[10px] text-purple-400 hover:underline font-sans">Copy</button>
                            </div>
                        </div>
                    </div>

                    <!-- Live Server Connectivity & Port Health Prober -->
                    <div class="bg-neutral-950 border border-neutral-800 rounded-xl p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-bold text-white uppercase tracking-wider">Mail Server Port Health Prober</h4>
                                <p class="text-[11px] text-neutral-400 mt-0.5">Tests live socket handshake to IMAP port 993 and SMTP port 587.</p>
                            </div>
                            <button 
                                type="button" 
                                wire:click="testMailServerConnectivity" 
                                wire:loading.attr="disabled"
                                class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-white font-semibold rounded-lg text-xs transition border border-neutral-700 flex items-center gap-1.5 shrink-0"
                            >
                                <svg wire:loading.class="animate-spin" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                <span wire:loading.remove wire:target="testMailServerConnectivity">Test Connection</span>
                                <span wire:loading wire:target="testMailServerConnectivity">Probing Ports...</span>
                            </button>
                        </div>

                        @if ($connectivityTestResult)
                            <div class="p-3 bg-neutral-900 rounded-lg border border-neutral-800 text-xs font-mono text-neutral-300">
                                {{ $connectivityTestResult }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-between pt-3 border-t border-neutral-800 shrink-0">
                    <a 
                        href="{{ $webmailUrl }}/login?email={{ urlencode($selectedMailboxAddress) }}" 
                        target="_blank" 
                        rel="noopener noreferrer" 
                        class="text-xs text-purple-400 hover:underline font-semibold flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        Launch Webmail for this account &rarr;
                    </a>
                    <button type="button" wire:click="$set('showCredentialsModal', false)" class="px-5 py-2 bg-neutral-800 hover:bg-neutral-700 text-white font-semibold rounded-xl text-xs">Done</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Manage Email Aliases -->
    @if ($showAliasesModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
                    <h3 class="text-base font-bold text-white">Email Aliases</h3>
                    <button wire:click="$set('showAliasesModal', false)" class="text-neutral-500 hover:text-white text-lg font-bold">&times;</button>
                </div>
                <p class="text-xs text-neutral-400">Aliases route messages directly into this mailbox without requiring separate login credentials.</p>

                <!-- Existing Aliases List -->
                <div class="space-y-2">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Current Aliases</div>
                    <div class="divide-y divide-neutral-800 bg-neutral-950 rounded-xl border border-neutral-800 overflow-hidden">
                        @forelse ($mailboxAliases as $alias)
                            <div class="px-3.5 py-2.5 flex items-center justify-between text-xs">
                                <span class="font-mono text-white">{{ $alias['alias_address'] }}</span>
                                <button wire:click="deleteAlias('{{ $alias['id'] }}')" class="text-rose-400 hover:text-rose-300 text-[11px]">Remove</button>
                            </div>
                        @empty
                            <div class="p-3 text-center text-xs text-neutral-500">No aliases configured.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Add New Alias Form -->
                <form wire:submit.prevent="addAlias" class="space-y-3 pt-2">
                    <label class="block text-xs font-semibold text-neutral-300">Add New Alias</label>
                    <div class="flex items-center gap-2">
                        <input type="text" wire:model="newAliasUsername" placeholder="e.g. sales, support" class="input input-sm bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono flex-1 focus:border-purple-500" required>
                        <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs">Add</button>
                    </div>
                    @error('newAliasUsername') <span class="text-rose-400 text-xs block">{{ $message }}</span> @enderror
                </form>

                <div class="flex justify-end pt-2 border-t border-neutral-800">
                    <button wire:click="$set('showAliasesModal', false)" class="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white font-semibold rounded-xl text-xs">Done</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Edit Mailbox Quota -->
    @if ($showEditQuotaModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-sm w-full p-6 space-y-4 shadow-2xl">
                <h3 class="text-base font-bold text-white">Edit Mailbox Storage Quota</h3>
                <form wire:submit.prevent="saveMailboxQuota" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-neutral-300 mb-1">Quota Limit in MB</label>
                        <input type="number" wire:model="editQuotaMb" placeholder="Leave empty for unmetered shared pool" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono focus:border-purple-500">
                        <p class="text-[10px] text-neutral-500 mt-1">Leave blank to allow dynamic consumption from your total plan pool.</p>
                        @error('editQuotaMb') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showEditQuotaModal', false)" class="px-3.5 py-2 text-xs font-semibold text-neutral-400 hover:text-white">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl text-xs">Save Quota</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal: Reset Password -->
    @if ($showPasswordResetModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-sm w-full p-6 space-y-4 shadow-2xl">
                <h3 class="text-base font-bold text-white">Reset Mailbox Password</h3>
                <form wire:submit.prevent="executePasswordReset" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-neutral-300 mb-1">New Password</label>
                        <input type="password" wire:model="resetNewPassword" placeholder="Minimum 8 characters" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs focus:border-purple-500" required>
                        @error('resetNewPassword') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showPasswordResetModal', false)" class="px-3.5 py-2 text-xs font-semibold text-neutral-400 hover:text-white">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl text-xs">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

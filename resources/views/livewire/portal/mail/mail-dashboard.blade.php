<div class="max-w-6xl mx-auto p-6 space-y-6">
    <!-- Header -->
    <div class="pb-4 border-b border-neutral-800 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded bg-purple-500/10 text-purple-400 border border-purple-500/20">WildDuck Mail</span>
                <span class="text-xs text-neutral-400 font-mono">{{ $activePlan['name'] ?? 'Active Mail Hosting' }}</span>
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
            <button wire:click="openNewDomain" class="px-3.5 py-2 bg-neutral-800 hover:bg-neutral-700 text-white font-semibold rounded-xl text-xs transition border border-neutral-700">
                + Add Domain
            </button>
        </div>
    </div>

    <!-- Storage Pool & Quota Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Pooled Storage -->
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between text-xs">
                <span class="font-medium text-neutral-400">Shared Storage Pool</span>
                <span class="font-bold text-white font-mono">{{ $totalUsedMb }} MB / {{ $totalStorageMb }} MB</span>
            </div>
            <div class="w-full bg-neutral-950 rounded-full h-2 overflow-hidden border border-neutral-800">
                <div class="h-full bg-gradient-to-r from-purple-500 to-indigo-500 transition-all duration-300" style="width: {{ $usedPercentage }}%"></div>
            </div>
            <p class="text-[11px] text-neutral-500">Shared across all mailboxes on your account.</p>
        </div>

        <!-- Mailboxes Allocation -->
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-2">
            <div class="flex items-center justify-between text-xs">
                <span class="font-medium text-neutral-400">Mailboxes Provisioned</span>
                <span class="font-bold text-white font-mono">{{ count($mailboxes) }} / {{ $subscription['max_mailboxes'] ?? 5 }}</span>
            </div>
            <div class="flex items-center gap-1.5 pt-1">
                @for ($i = 0; $i < ($subscription['max_mailboxes'] ?? 5); $i++)
                    <div class="h-2 flex-1 rounded-full {{ $i < count($mailboxes) ? 'bg-purple-500' : 'bg-neutral-800' }}"></div>
                @endfor
            </div>
            <p class="text-[11px] text-neutral-500">Need more? Add-ons available below.</p>
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
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">Custom Mail Domains</h2>
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
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Verified</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">DNS Needed</span>
                        @endif
                    </div>
                    
                    <div class="flex items-center justify-between text-xs pt-1">
                        <button type="button" wire:click.stop="openDnsHelper('{{ $d['id'] }}')" class="text-[11px] text-purple-400 hover:underline">
                            View DNS Records
                        </button>
                        @if (! $d['is_verified'])
                            <button type="button" wire:click.stop="verifyDomainDns('{{ $d['id'] }}')" class="text-[11px] text-neutral-300 hover:text-white bg-neutral-800 px-2 py-0.5 rounded">
                                Check DNS
                            </button>
                        @endif
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
                <p class="text-xs text-neutral-400 mt-0.5">Active email accounts on your custom domain.</p>
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
                            <td class="py-3.5 px-5 font-mono font-bold text-white flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                {{ $box['address'] }}
                            </td>
                            <td class="py-3.5 px-5">{{ $box['name'] }}</td>
                            <td class="py-3.5 px-5 font-mono">
                                {{ $box['quota_mb'] ? $box['quota_mb'] . ' MB cap' : 'Pool share' }}
                            </td>
                            <td class="py-3.5 px-5">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Active</span>
                            </td>
                            <td class="py-3.5 px-5 text-right space-x-2">
                                <a href="{{ $webmailUrl }}/login?email={{ urlencode($box['address']) }}" target="_blank" rel="noopener noreferrer" class="text-purple-400 hover:text-purple-300 font-semibold transition">Webmail</a>
                                <button wire:click="openCredentials('{{ $box['address'] }}')" class="text-neutral-400 hover:text-white transition">Credentials</button>
                                <button wire:click="promptPasswordReset('{{ $box['wildduck_user_id'] }}')" class="text-neutral-400 hover:text-white transition">Reset Password</button>
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
        <h2 class="text-sm font-bold text-white uppercase tracking-wider">Available Plan Add-Ons</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ($addons as $addon)
                <div class="bg-neutral-950 border border-neutral-800 rounded-xl p-4 flex flex-col justify-between space-y-3">
                    <div>
                        <div class="text-xs font-bold text-white">{{ $addon['name'] }}</div>
                        <div class="text-base font-bold text-purple-400 font-mono mt-1">${{ number_format($addon['price_cents'] / 100, 2) }} <span class="text-[10px] text-neutral-500">/ mo</span></div>
                    </div>
                    <button class="w-full py-1.5 px-3 bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-semibold rounded-lg transition border border-neutral-700">
                        Add to Subscription
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Modal: Add Custom Domain -->
    @if ($showNewDomainModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <h3 class="text-lg font-bold text-white">Add Custom Mail Domain</h3>
                <p class="text-xs text-neutral-400">Enter the domain you want to send and receive emails on (e.g. yourcompany.com).</p>
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

    <!-- Modal: DNS Setup Guide with Copy Buttons -->
    @if ($showDnsHelperModal && $selectedDnsDomain)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-2xl w-full p-6 space-y-5 shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-white">DNS Records for {{ $selectedDnsDomain['domain'] }}</h3>
                        <p class="text-xs text-neutral-400">Add these 4 records at your domain registrar (GoDaddy, Cloudflare, Namecheap).</p>
                    </div>
                    <button wire:click="$set('showDnsHelperModal', false)" class="text-neutral-500 hover:text-white text-lg font-bold">&times;</button>
                </div>

                <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-1">
                    <!-- MX Record -->
                    <div class="bg-neutral-950 border border-neutral-800 rounded-xl p-3.5 space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-purple-400">1. MX Record (Inbound Delivery)</span>
                            <span class="text-neutral-500 font-mono">Priority: 10</span>
                        </div>
                        <div class="flex items-center justify-between bg-neutral-900 px-3 py-1.5 rounded font-mono text-xs text-white">
                            <span>{{ $mailHost }}</span>
                            <button onclick="navigator.clipboard.writeText('{{ $mailHost }}')" class="text-purple-400 hover:underline text-[11px]">Copy</button>
                        </div>
                    </div>

                    <!-- SPF Record -->
                    <div class="bg-neutral-950 border border-neutral-800 rounded-xl p-3.5 space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-purple-400">2. SPF Record (Authorized Sender)</span>
                            <span class="text-neutral-500 font-mono">Type: TXT</span>
                        </div>
                        <div class="flex items-center justify-between bg-neutral-900 px-3 py-1.5 rounded font-mono text-xs text-white">
                            <span class="truncate">v=spf1 mx a:{{ $mailHost }} ~all</span>
                            <button onclick="navigator.clipboard.writeText('v=spf1 mx a:{{ $mailHost }} ~all')" class="text-purple-400 hover:underline text-[11px] shrink-0 ml-2">Copy</button>
                        </div>
                    </div>

                    <!-- DKIM Record -->
                    <div class="bg-neutral-950 border border-neutral-800 rounded-xl p-3.5 space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-purple-400">3. DKIM Signature Record</span>
                            <span class="text-neutral-500 font-mono">Host: {{ $selectedDnsDomain['dkim_selector'] }}._domainkey</span>
                        </div>
                        <div class="bg-neutral-900 px-3 py-1.5 rounded font-mono text-[11px] text-white break-all flex items-start justify-between gap-2">
                            <span>v=DKIM1; k=rsa; p={{ $selectedDnsDomain['dkim_public_key'] }}</span>
                            <button onclick="navigator.clipboard.writeText('v=DKIM1; k=rsa; p={{ $selectedDnsDomain['dkim_public_key'] }}')" class="text-purple-400 hover:underline text-[11px] shrink-0">Copy</button>
                        </div>
                    </div>

                    <!-- DMARC Record -->
                    <div class="bg-neutral-950 border border-neutral-800 rounded-xl p-3.5 space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-purple-400">4. DMARC Policy Record</span>
                            <span class="text-neutral-500 font-mono">Host: _dmarc</span>
                        </div>
                        <div class="flex items-center justify-between bg-neutral-900 px-3 py-1.5 rounded font-mono text-xs text-white">
                            <span class="truncate">v=DMARC1; p=quarantine; pct=100; aspf=r; adkim=r</span>
                            <button onclick="navigator.clipboard.writeText('v=DMARC1; p=quarantine; pct=100; aspf=r; adkim=r')" class="text-purple-400 hover:underline text-[11px] shrink-0 ml-2">Copy</button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-neutral-800">
                    <span class="text-xs text-neutral-500">DNS changes usually propagate within 1 to 15 minutes.</span>
                    <button wire:click="verifyDomainDns('{{ $selectedDnsDomain['id'] }}')" class="px-5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl text-xs shadow">
                        Verify Records Now
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Client Setup Guide (IMAP/SMTP) -->
    @if ($showCredentialsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
                    <h3 class="text-base font-bold text-white">Email Client Setup Settings</h3>
                    <button wire:click="$set('showCredentialsModal', false)" class="text-neutral-500 hover:text-white text-lg font-bold">&times;</button>
                </div>
                <p class="text-xs text-neutral-400">Configure Apple Mail, Outlook, Thunderbird, or mobile mail apps:</p>

                <div class="bg-neutral-950 p-4 rounded-xl border border-neutral-800 space-y-3 font-mono text-xs">
                    <div>
                        <div class="text-neutral-500 text-[10px] uppercase font-bold">Username</div>
                        <div class="text-purple-300 font-bold">{{ $selectedMailboxAddress }}</div>
                    </div>
                    <div>
                        <div class="text-neutral-500 text-[10px] uppercase font-bold">Incoming Server (IMAP)</div>
                        <div class="text-white">{{ $mailHost }} | Port 993 | SSL/TLS</div>
                    </div>
                    <div>
                        <div class="text-neutral-500 text-[10px] uppercase font-bold">Outgoing Server (SMTP)</div>
                        <div class="text-white">{{ $mailHost }} | Port 587 | STARTTLS</div>
                    </div>
                    <div>
                        <div class="text-neutral-500 text-[10px] uppercase font-bold">Webmail Interface</div>
                        <div class="text-white">
                            <a href="{{ $webmailUrl }}/login?email={{ urlencode($selectedMailboxAddress) }}" target="_blank" class="text-purple-400 underline font-sans">
                                Open Webmail with {{ $selectedMailboxAddress }} &rarr;
                            </a>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button wire:click="$set('showCredentialsModal', false)" class="px-5 py-2 bg-neutral-800 hover:bg-neutral-700 text-white font-semibold rounded-xl text-xs">Close</button>
                </div>
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


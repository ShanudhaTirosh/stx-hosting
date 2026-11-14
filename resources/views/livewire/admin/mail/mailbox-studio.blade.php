<div class="max-w-7xl mx-auto p-6 space-y-6">
    {{-- Header --}}
    <div class="pb-4 border-b border-neutral-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-mono uppercase tracking-wider text-purple-400 font-bold px-2 py-0.5 rounded bg-purple-500/10 border border-purple-500/20">
                    Platform Mail Server
                </span>
                <span class="text-xs text-neutral-400 font-mono">WildDuck &amp; ZoneMTA</span>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight mt-1">Mailbox Studio &amp; Admin Delegation</h1>
            <p class="text-xs text-neutral-400 mt-1">Create unlimited platform mailboxes, configure email purposes, delegate admin access, and manage connection credentials.</p>
        </div>

        {{-- Studio Tabs --}}
        <div class="flex items-center gap-1 bg-neutral-900/90 border border-neutral-800 p-1 rounded-xl self-start md:self-auto flex-wrap">
            <button type="button" wire:click="setTab('mailboxes')"
                @class([
                    'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5',
                    'bg-purple-600 text-white shadow-sm' => $activeTab === 'mailboxes',
                    'text-neutral-400 hover:text-white' => $activeTab !== 'mailboxes',
                ])>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <span>Mailboxes ({{ $mailboxes->count() }})</span>
            </button>
            <button type="button" wire:click="setTab('senders')"
                @class([
                    'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5',
                    'bg-purple-600 text-white shadow-sm' => $activeTab === 'senders',
                    'text-neutral-400 hover:text-white' => $activeTab !== 'senders',
                ])>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>System Senders</span>
            </button>
            <button type="button" wire:click="setTab('permissions')"
                @class([
                    'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5',
                    'bg-purple-600 text-white shadow-sm' => $activeTab === 'permissions',
                    'text-neutral-400 hover:text-white' => $activeTab !== 'permissions',
                ])>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span>Admin Matrix</span>
            </button>
            <button type="button" wire:click="setTab('audit')"
                @class([
                    'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5',
                    'bg-purple-600 text-white shadow-sm' => $activeTab === 'audit',
                    'text-neutral-400 hover:text-white' => $activeTab !== 'audit',
                ])>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Audit Trail</span>
            </button>
            <button type="button" wire:click="setTab('telemetry')"
                @class([
                    'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5',
                    'bg-purple-600 text-white shadow-sm' => $activeTab === 'telemetry',
                    'text-neutral-400 hover:text-white' => $activeTab !== 'telemetry',
                ])>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span>Fleet Telemetry</span>
            </button>
        </div>
    </div>

    {{-- TAB 1: MAILBOXES & FLEET MANAGEMENT --}}
    @if ($activeTab === 'mailboxes')
        <div class="space-y-4">
            {{-- Search, Filters, and New Mailbox Button --}}
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-neutral-900 border border-neutral-800 p-4 rounded-xl">
                <div class="flex items-center gap-2 flex-wrap">
                    @foreach (['all' => 'All Purposes', 'system_sender' => 'System Senders', 'shared_inbox' => 'Shared Inboxes', 'personal_mailbox' => 'Personal'] as $pKey => $pLabel)
                        <button type="button" wire:click="$set('purposeFilter', '{{ $pKey }}')"
                            @class([
                                'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                                'bg-purple-600 text-white font-bold' => $purposeFilter === $pKey,
                                'bg-neutral-950 text-neutral-400 hover:text-white border border-neutral-800' => $purposeFilter !== $pKey,
                            ])>
                            {{ $pLabel }}
                        </button>
                    @endforeach
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <div class="relative w-full sm:w-64">
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search address or name..."
                            class="w-full pl-9 pr-3 py-1.5 bg-neutral-950 border border-neutral-800 rounded-lg text-xs text-white placeholder-neutral-500 focus:outline-none focus:border-purple-500">
                        <svg class="w-4 h-4 text-neutral-500 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>

                    <button type="button" wire:click="openCreateModal"
                        class="px-3.5 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition-colors whitespace-nowrap flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Create Mailbox</span>
                    </button>
                </div>
            </div>

            {{-- Mailboxes Table --}}
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-neutral-950 text-neutral-400 font-mono uppercase text-[10px] tracking-wider border-b border-neutral-800">
                            <tr>
                                <th class="py-3 px-4">Address &amp; Name</th>
                                <th class="py-3 px-4">Purpose</th>
                                <th class="py-3 px-4">Quota &amp; Storage</th>
                                <th class="py-3 px-4">Delegated Admins</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-800/60 font-sans">
                            @forelse ($mailboxes as $mailbox)
                                <tr class="hover:bg-neutral-800/40 transition-colors">
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 font-bold shrink-0">
                                                {{ strtoupper(substr($mailbox->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="font-mono text-white font-bold flex items-center gap-1.5">
                                                    <span>{{ $mailbox->address }}</span>
                                                    @if ($mailbox->is_sensitive)
                                                        <span class="text-[9px] font-mono px-1 py-0.2 rounded bg-rose-500/10 text-rose-400 border border-rose-500/20" title="Enforces 2FA Step-up">
                                                            2FA SENSITIVE
                                                        </span>
                                                    @endif
                                                </div>
                                                <span class="text-[11px] text-neutral-400 block">{{ $mailbox->name }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono">
                                        @if ($mailbox->purpose === 'system_sender')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-sky-500/10 text-sky-400 border border-sky-500/20">
                                                System Sender
                                            </span>
                                        @elseif ($mailbox->purpose === 'shared_inbox')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/10 text-purple-400 border border-purple-500/20">
                                                Shared Inbox
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                Personal
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-[11px]">
                                        <div class="text-white">{{ $mailbox->quota_mb ? $mailbox->quota_mb.' MB' : 'Unlimited' }}</div>
                                        <span class="text-neutral-500 text-[10px] block">
                                            {{ round(($mailbox->used_storage_bytes ?? 0) / (1024 * 1024), 1) }} MB used
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        @if ($mailbox->permissions->isNotEmpty())
                                            <div class="flex items-center gap-1 flex-wrap">
                                                @foreach ($mailbox->permissions as $p)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-neutral-800 text-[10px] font-mono text-neutral-300" title="{{ $p->permission_level }}">
                                                        <span>{{ $p->user?->name ?? 'Admin' }}</span>
                                                        <span class="text-purple-400 font-bold">({{ strtoupper(substr($p->permission_level, 0, 1)) }})</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-neutral-500 text-[11px]">Owner Only</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4">
                                        @if ($mailbox->is_active)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                                Suspended
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            {{-- Webmail SSO Launch --}}
                                            <a href="{{ route('admin.mailboxes.webmail-sso', $mailbox->id) }}" target="_blank"
                                                class="px-2.5 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-purple-400 text-xs font-semibold transition-colors flex items-center gap-1"
                                                title="Open in Webmail (SSO - No password required)">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                <span>Webmail</span>
                                            </a>

                                            {{-- Credentials & Programs Setup --}}
                                            <button type="button" wire:click="openCredentialsModal('{{ $mailbox->id }}')"
                                                class="px-2.5 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-medium transition-colors"
                                                title="View IMAP/SMTP host, ports, and generate App Passwords">
                                                Connect Apps
                                            </button>

                                            {{-- Settings --}}
                                            <button type="button" wire:click="openSettingsModal('{{ $mailbox->id }}')"
                                                class="p-1.5 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-300 transition-colors"
                                                title="Mailbox Settings">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            </button>

                                            {{-- Suspend / Resume --}}
                                            <button type="button" wire:click="toggleMailboxStatus('{{ $mailbox->id }}')"
                                                class="p-1.5 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-300 transition-colors"
                                                title="{{ $mailbox->is_active ? 'Suspend Mailbox' : 'Resume Mailbox' }}">
                                                @if ($mailbox->is_active)
                                                    <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                @else
                                                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                @endif
                                            </button>

                                            {{-- Delete --}}
                                            <button type="button" wire:click="deleteMailbox('{{ $mailbox->id }}')" wire:confirm="Are you sure you want to permanently delete mailbox {{ $mailbox->address }}? All emails and storage will be removed from WildDuck."
                                                class="p-1.5 rounded bg-neutral-800 hover:bg-rose-900/50 text-neutral-400 hover:text-rose-400 transition-colors"
                                                title="Delete Mailbox">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-neutral-400 space-y-2">
                                        <div class="text-sm font-semibold text-white">No platform mailboxes created yet</div>
                                        <p class="text-xs text-neutral-500">Create your first platform mailbox for system senders, shared support inboxes, or team members.</p>
                                        <button type="button" wire:click="openCreateModal" class="px-3.5 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition-colors">
                                            Create First Mailbox
                                        </button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 2: SYSTEM SENDERS MAPPING --}}
    @if ($activeTab === 'senders')
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-6">
            <div class="pb-4 border-b border-neutral-800">
                <h2 class="text-lg font-bold text-white tracking-tight">Platform Automated Email Mapping</h2>
                <p class="text-xs text-neutral-400 mt-1">Map each automated email category to a specific sender mailbox. Inbound replies will route to that mailbox, and outbounds will use authenticated SMTP on port 587.</p>
            </div>

            <div class="space-y-4">
                @php
                    $categoryLabels = [
                        'default' => ['label' => 'Default Platform Mailer', 'desc' => 'General fallback for all unmapped transactional emails'],
                        'system' => ['label' => 'Account & Security (System)', 'desc' => 'Welcome verification, login codes (2FA), password resets, security alerts'],
                        'billing' => ['label' => 'Billing & Invoices', 'desc' => 'Invoices, payment receipts, deposit slip approvals & rejections'],
                        'deployments' => ['label' => 'Deployments & CI/CD', 'desc' => 'Application build successes, deployment failures, container health'],
                        'support' => ['label' => 'Support & Domain Warnings', 'desc' => 'Customer assistance, domain DNS issues, SSL renewal warnings'],
                        'security' => ['label' => 'Security & Incidents', 'desc' => 'Platform maintenance alerts and system status incident notices'],
                    ];
                @endphp

                @foreach ($categoryLabels as $catKey => $catInfo)
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 rounded-xl bg-neutral-950 border border-neutral-800/80 items-center">
                        <div class="md:col-span-1 space-y-0.5">
                            <span class="text-xs font-bold text-white block">{{ $catInfo['label'] }}</span>
                            <span class="text-[11px] text-neutral-400 block">{{ $catInfo['desc'] }}</span>
                        </div>

                        <div class="md:col-span-1">
                            <label class="block text-[10px] font-mono uppercase text-neutral-400 mb-1">Sender Mailbox</label>
                            <select wire:model="senderMappings.{{ $catKey }}.mailbox_id" class="select select-sm w-full bg-neutral-900 border-neutral-700 text-white text-xs rounded-lg">
                                <option value="">-- Use Default / System Host --</option>
                                @foreach ($allMailboxesForMapping as $mb)
                                    <option value="{{ $mb->id }}">{{ $mb->address }} ({{ $mb->name }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-1">
                            <label class="block text-[10px] font-mono uppercase text-neutral-400 mb-1">Sender Display Name</label>
                            <input type="text" wire:model="senderMappings.{{ $catKey }}.from_name" class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white text-xs rounded-lg">
                        </div>

                        <div class="md:col-span-1 flex items-center justify-between gap-2">
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono uppercase text-neutral-400 mb-1">Custom Reply-To</label>
                                <input type="text" wire:model="senderMappings.{{ $catKey }}.reply_to" placeholder="Optional reply email" class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white text-xs rounded-lg font-mono">
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end pt-4 border-t border-neutral-800">
                    <button type="button" wire:click="saveSenderMappings" class="px-5 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-sm transition-colors">
                        Save Sender Mappings
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 3: ADMIN ACCESS MATRIX --}}
    @if ($activeTab === 'permissions')
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-6">
            <div class="pb-4 border-b border-neutral-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-lg font-bold text-white tracking-tight">Admin Mailbox Access Matrix</h2>
                    <p class="text-xs text-neutral-400 mt-1">Grant team admins delegated permissions to specific mailboxes. Passwords are never shared; admins authenticate via server-side SSO or their own App Passwords.</p>
                </div>

                <div class="flex items-center gap-2 text-[11px] font-mono">
                    <span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-300">R: Read-only</span>
                    <span class="px-2 py-0.5 rounded bg-neutral-800 text-purple-400">RW: Read &amp; Write</span>
                    <span class="px-2 py-0.5 rounded bg-neutral-800 text-sky-400">S: Send As</span>
                    <span class="px-2 py-0.5 rounded bg-neutral-800 text-emerald-400">M: Manage</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-neutral-950 text-neutral-400 font-mono uppercase text-[10px] tracking-wider border-b border-neutral-800">
                        <tr>
                            <th class="py-3 px-4">Staff Administrator</th>
                            @foreach ($mailboxes as $mb)
                                <th class="py-3 px-4 font-mono text-center">{{ $mb->address }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-800/60 font-sans">
                        @foreach ($admins as $admin)
                            <tr class="hover:bg-neutral-800/40 transition-colors">
                                <td class="py-3 px-4 font-medium text-white">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-neutral-800 flex items-center justify-center text-purple-400 font-bold text-[10px]">
                                            {{ strtoupper(substr($admin->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <span>{{ $admin->name }}</span>
                                            <span class="text-neutral-500 font-mono text-[10px] block">{{ $admin->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                @foreach ($mailboxes as $mb)
                                    @php
                                        $perm = $mb->permissions->firstWhere('user_id', $admin->id);
                                    @endphp
                                    <td class="py-3 px-4 text-center">
                                        @if ($perm && $perm->isActive())
                                            <button type="button" wire:click="openGrantModal('{{ $mb->id }}', {{ $admin->id }})"
                                                @class([
                                                    'px-2.5 py-1 rounded-full text-[10px] font-bold font-mono transition-transform hover:scale-105',
                                                    'bg-purple-600 text-white' => $perm->permission_level === 'read_write',
                                                    'bg-neutral-800 text-neutral-300' => $perm->permission_level === 'read_only',
                                                    'bg-sky-600 text-white' => $perm->permission_level === 'send_as',
                                                    'bg-emerald-600 text-white' => $perm->permission_level === 'manage',
                                                ])>
                                                {{ strtoupper(str_replace('_', ' ', $perm->permission_level)) }}
                                            </button>
                                        @else
                                            <button type="button" wire:click="openGrantModal('{{ $mb->id }}', {{ $admin->id }})"
                                                class="px-2.5 py-1 rounded-full border border-dashed border-neutral-700 text-neutral-500 hover:text-white hover:border-neutral-500 text-[10px] font-mono transition-colors">
                                                + Grant
                                            </button>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB 4: AUDIT TRAIL --}}
    @if ($activeTab === 'audit')
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4">
            <div class="pb-4 border-b border-neutral-800">
                <h2 class="text-lg font-bold text-white tracking-tight">Mailbox Security &amp; Access Audit Trail</h2>
                <p class="text-xs text-neutral-400 mt-1">Immutable ledger recording mailbox creations, permission changes, webmail launches, and credentials reveals.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-neutral-950 text-neutral-400 font-mono uppercase text-[10px] tracking-wider border-b border-neutral-800">
                        <tr>
                            <th class="py-3 px-4">Timestamp</th>
                            <th class="py-3 px-4">Actor</th>
                            <th class="py-3 px-4">Action</th>
                            <th class="py-3 px-4">Target Mailbox</th>
                            <th class="py-3 px-4">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-800/60 font-mono text-[11px]">
                        @forelse ($auditLogs as $log)
                            <tr class="hover:bg-neutral-800/40 transition-colors">
                                <td class="py-3 px-4 text-neutral-400">
                                    {{ $log->created_at->format('M d, Y H:i:s') }}
                                </td>
                                <td class="py-3 px-4 text-white">
                                    {{ $log->actor?->name ?? 'System' }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded bg-neutral-800 text-purple-300 font-bold text-[10px]">
                                        {{ str_replace('_', ' ', strtoupper($log->action)) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-neutral-300">
                                    {{ $log->mailbox?->address ?? ($log->details['deleted_address'] ?? '—') }}
                                </td>
                                <td class="py-3 px-4 text-neutral-500">
                                    {{ $log->ip_address ?? '127.0.0.1' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-neutral-500">
                                    No audit entries recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB 5: FLEET TELEMETRY --}}
    @if ($activeTab === 'telemetry')
        <div class="space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-1">
                    <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 font-bold block">MAIL ENGINE</span>
                    <span class="text-xl font-bold text-white font-mono">WildDuck v{{ $fleetStats['version'] }}</span>
                    <span class="text-[11px] text-emerald-400 block font-semibold flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        {{ $fleetStats['healthy'] ? 'Healthy ('.$fleetStats['latency_ms'].'ms)' : 'Offline' }}
                    </span>
                </div>
                <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-1">
                    <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 font-bold block">TOTAL STORAGE</span>
                    <span class="text-xl font-bold text-white font-mono">{{ round($fleetStats['storage_used_bytes'] / (1024 * 1024 * 1024), 2) }} GB</span>
                    <span class="text-[11px] text-neutral-400 block">Fleet attachments &amp; body</span>
                </div>
                <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-1">
                    <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 font-bold block">ACTIVE MAILBOXES</span>
                    <span class="text-xl font-bold text-purple-400 font-mono">{{ $fleetStats['active_users'] }}</span>
                    <span class="text-[11px] text-neutral-400 block">Across platform domains</span>
                </div>
                <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-1">
                    <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 font-bold block">DOMAINS CONFIGURED</span>
                    <span class="text-xl font-bold text-sky-400 font-mono">{{ $fleetStats['active_domains'] }}</span>
                    <span class="text-[11px] text-neutral-400 block">With MongoDB 2048-bit DKIM</span>
                </div>
            </div>

            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider font-mono">Core Mail Ports &amp; Topology</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs font-mono">
                    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-lg space-y-1">
                        <div class="flex justify-between font-bold">
                            <span class="text-neutral-300">IMAP (Port 993)</span>
                            <span class="text-emerald-400">SSL/TLS</span>
                        </div>
                        <p class="text-[11px] text-neutral-400 font-sans">Client mail fetch (WildDuck core)</p>
                    </div>
                    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-lg space-y-1">
                        <div class="flex justify-between font-bold">
                            <span class="text-neutral-300">SMTP (Port 587)</span>
                            <span class="text-emerald-400">STARTTLS</span>
                        </div>
                        <p class="text-[11px] text-neutral-400 font-sans">Authenticated submission (ZoneMTA)</p>
                    </div>
                    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-lg space-y-1">
                        <div class="flex justify-between font-bold">
                            <span class="text-neutral-300">Inbound MX (Port 25)</span>
                            <span class="text-emerald-400">Haraka</span>
                        </div>
                        <p class="text-[11px] text-neutral-400 font-sans">Internet inbound deliverability</p>
                    </div>
                    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-lg space-y-1">
                        <div class="flex justify-between font-bold">
                            <span class="text-neutral-300">Webmail UI</span>
                            <span class="text-emerald-400">HTTPS SSO</span>
                        </div>
                        <p class="text-[11px] text-neutral-400 font-sans">Authenticated staff &amp; client webmail</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 1: CREATE / BULK CREATE MAILBOX --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl w-full max-w-lg p-6 space-y-5 shadow-2xl">
                <div class="flex justify-between items-center pb-3 border-b border-neutral-800">
                    <h3 class="text-lg font-bold text-white tracking-tight">Create Platform Mailbox</h3>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="$toggle('isBulkMode')" class="text-xs font-mono text-purple-400 hover:text-purple-300">
                            {{ $isBulkMode ? 'Single Mode' : 'Bulk Mode' }}
                        </button>
                        <button type="button" wire:click="$set('showCreateModal', false)" class="text-neutral-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                @if ($isBulkMode)
                    <div class="space-y-3">
                        <p class="text-xs text-neutral-400">Enter one mailbox per line: <code class="text-purple-400 font-mono">username,DisplayName,quotaMb,purpose</code></p>
                        <textarea wire:model="bulkInput" rows="6" placeholder="billing,STX Billing,5120,shared_inbox&#10;support,STX Support,10240,shared_inbox&#10;alerts,STX Alerts,2048,system_sender"
                            class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg font-mono text-xs"></textarea>
                    </div>
                @else
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-neutral-300 mb-1">Username</label>
                                <input type="text" wire:model="createUsername" placeholder="e.g. support" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg text-xs font-mono">
                                @error('createUsername') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-neutral-300 mb-1">Domain</label>
                                <input type="text" wire:model="createDomain" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg text-xs font-mono">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-neutral-300 mb-1">Display Name</label>
                            <input type="text" wire:model="createDisplayName" placeholder="e.g. STX Support Team" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg text-xs">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-neutral-300 mb-1">Storage Quota (MB)</label>
                                <input type="number" wire:model="createQuotaMb" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg text-xs font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-neutral-300 mb-1">Purpose</label>
                                <select wire:model="createPurpose" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg text-xs">
                                    <option value="shared_inbox">Shared Inbox (Support, Billing)</option>
                                    <option value="system_sender">System Sender (Automated Mail)</option>
                                    <option value="personal_mailbox">Personal Team Mailbox</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-neutral-300 mb-1">Generated Password (Encrypted at rest)</label>
                            <input type="text" wire:model="createPassword" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-purple-400 rounded-lg text-xs font-mono">
                        </div>

                        <label class="flex items-center gap-2 cursor-pointer pt-1">
                            <input type="checkbox" wire:model="createIsSensitive" class="checkbox checkbox-sm checkbox-primary">
                            <span class="text-xs text-neutral-300">Mark as Sensitive (Requires 2FA verification before staff access)</span>
                        </label>
                    </div>
                @endif

                <div class="flex justify-end gap-2 pt-3 border-t border-neutral-800">
                    <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-medium">
                        Cancel
                    </button>
                    <button type="button" wire:click="saveCreateMailbox" class="px-5 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold">
                        Create Mailbox
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 2: CONNECTION CREDENTIALS, APP PASSWORDS & CLIENT SETUP --}}
    @if ($showCredentialsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl w-full max-w-2xl p-6 space-y-5 shadow-2xl overflow-y-auto max-h-[90vh]">
                <div class="flex justify-between items-center pb-3 border-b border-neutral-800">
                    <div>
                        <h3 class="text-lg font-bold text-white tracking-tight">Connect External Programs &amp; Clients</h3>
                        <p class="text-xs text-neutral-400 font-mono">{{ $credentialsAddress }}</p>
                    </div>
                    <button type="button" wire:click="$set('showCredentialsModal', false)" class="text-neutral-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Server Parameters Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs font-mono">
                    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-xl space-y-1.5">
                        <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">INCOMING SERVER (IMAP)</span>
                        <div class="flex justify-between text-neutral-300">
                            <span>Host:</span>
                            <span class="text-white font-bold">{{ config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.stxenterprises.com')) }}</span>
                        </div>
                        <div class="flex justify-between text-neutral-300">
                            <span>Port / Security:</span>
                            <span class="text-emerald-400 font-bold">993 (SSL/TLS)</span>
                        </div>
                        <div class="flex justify-between text-neutral-300">
                            <span>Username:</span>
                            <span class="text-white truncate max-w-[180px]">{{ $credentialsAddress }}</span>
                        </div>
                    </div>

                    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-xl space-y-1.5">
                        <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">OUTGOING SERVER (SMTP)</span>
                        <div class="flex justify-between text-neutral-300">
                            <span>Host:</span>
                            <span class="text-white font-bold">{{ config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.stxenterprises.com')) }}</span>
                        </div>
                        <div class="flex justify-between text-neutral-300">
                            <span>Port / Security:</span>
                            <span class="text-emerald-400 font-bold">587 (STARTTLS) or 465 (SSL)</span>
                        </div>
                        <div class="flex justify-between text-neutral-300">
                            <span>Auth:</span>
                            <span class="text-emerald-400 font-bold">Required (Same as incoming)</span>
                        </div>
                    </div>
                </div>

                {{-- Master Password & 1-Click Apple Profile --}}
                <div class="p-3.5 bg-neutral-950 border border-neutral-800 rounded-xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div class="space-y-0.5">
                        <span class="text-xs font-bold text-white block">Master Mailbox Password</span>
                        <span class="text-xs font-mono text-purple-400 font-bold select-all">{{ $revealedPassword ?? '••••••••••••••••' }}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ route('mail.profiles.apple', $credentialsMailboxId) }}" download
                            class="px-3 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-semibold transition-colors flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.84c.66-.82 1.11-1.96.99-3.1-.97.04-2.14.65-2.83 1.46-.61.7-.1.14 1.83 1.02 1.84.1.01.21-.02.32-.05.02-.05.05-.1.08-.15z"/></svg>
                            <span>Apple 1-Click Profile</span>
                        </a>

                        <button type="button" wire:click="testPorts" class="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold transition-colors">
                            Test Ports
                        </button>
                    </div>
                </div>

                @if ($portTestResult)
                    <div class="p-3 rounded-lg bg-neutral-950 border border-purple-500/30 text-xs font-mono text-purple-300">
                        {{ $portTestResult }}
                    </div>
                @endif

                {{-- App-Specific Passwords Generator --}}
                <div class="space-y-3 pt-2 border-t border-neutral-800">
                    <div class="flex justify-between items-center">
                        <h4 class="text-xs font-bold text-white uppercase tracking-wider font-mono">App-Specific Passwords</h4>
                        <span class="text-[11px] text-neutral-400">Never share master passwords with scripts or 3rd-party apps</span>
                    </div>

                    <div class="flex gap-2">
                        <input type="text" wire:model="newAppPasswordLabel" placeholder="e.g. Thunderbird Office Laptop or Billing Script"
                            class="input input-sm flex-1 bg-neutral-950 border-neutral-700 text-white text-xs rounded-lg">
                        <button type="button" wire:click="generateAppPassword" class="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition-colors whitespace-nowrap">
                            Generate App Password
                        </button>
                    </div>

                    @if ($justGeneratedAppPassword)
                        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs space-y-1">
                            <span class="text-emerald-400 font-bold block">New App Password Created:</span>
                            <div class="p-2 bg-neutral-950 rounded font-mono text-emerald-300 font-bold select-all text-sm">
                                {{ $justGeneratedAppPassword }}
                            </div>
                            <span class="text-[10px] text-neutral-400">Copy this password now. It will not be shown again.</span>
                        </div>
                    @endif

                    @if ($currentAppPasswords->isNotEmpty())
                        <div class="space-y-1.5">
                            @foreach ($currentAppPasswords as $ap)
                                <div class="flex justify-between items-center p-2 rounded-lg bg-neutral-950 border border-neutral-800 text-xs font-mono">
                                    <div class="flex items-center gap-2">
                                        <span class="text-white font-bold">{{ $ap->label }}</span>
                                        <span class="text-[10px] text-neutral-500">Created {{ $ap->created_at->format('M d, Y') }}</span>
                                        @if ($ap->is_revoked)
                                            <span class="text-[9px] text-rose-400 uppercase font-bold">(Revoked)</span>
                                        @endif
                                    </div>
                                    @if (! $ap->is_revoked)
                                        <button type="button" wire:click="revokeAppPassword({{ $ap->id }})" class="text-rose-400 hover:text-rose-300 text-xs font-bold">
                                            Revoke
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Ready-to-Copy Code Snippets --}}
                <div class="space-y-2 pt-2 border-t border-neutral-800">
                    <div class="flex items-center gap-2">
                        @foreach (['laravel' => 'Laravel .env', 'python' => 'Python', 'nodejs' => 'Node.js', 'wordpress' => 'WordPress'] as $sKey => $sLabel)
                            <button type="button" wire:click="$set('activeSnippet', '{{ $sKey }}')"
                                @class([
                                    'px-2.5 py-1 rounded text-xs font-mono font-medium',
                                    'bg-purple-600 text-white' => $activeSnippet === $sKey,
                                    'text-neutral-400 hover:text-white bg-neutral-950' => $activeSnippet !== $sKey,
                                ])>
                                {{ $sLabel }}
                            </button>
                        @endforeach
                    </div>

                    @php
                        $host = config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.stxenterprises.com'));
                    @endphp

                    <div class="p-3 bg-neutral-950 rounded-xl font-mono text-[11px] text-neutral-300 border border-neutral-800 overflow-x-auto select-all">
                        @if ($activeSnippet === 'laravel')
MAIL_MAILER=smtp
MAIL_HOST={{ $host }}
MAIL_PORT=587
MAIL_USERNAME={{ $credentialsAddress }}
MAIL_PASSWORD={{ $revealedPassword ?? 'your-password-or-app-token' }}
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS={{ $credentialsAddress }}
MAIL_FROM_NAME="STX Hosting"
                        @elseif ($activeSnippet === 'python')
import smtplib
from email.mime.text import MIMEText

msg = MIMEText('Hello from STX Hosting!')
msg['Subject'] = 'Test Email'
msg['From'] = '{{ $credentialsAddress }}'
msg['To'] = 'recipient@example.com'

with smtplib.SMTP('{{ $host }}', 587) as server:
    server.starttls()
    server.login('{{ $credentialsAddress }}', '{{ $revealedPassword ?? 'your-app-password' }}')
    server.send_message(msg)
                        @elseif ($activeSnippet === 'nodejs')
const nodemailer = require('nodemailer');

const transporter = nodemailer.createTransport({
    host: '{{ $host }}',
    port: 587,
    secure: false,
    auth: {
        user: '{{ $credentialsAddress }}',
        pass: '{{ $revealedPassword ?? 'your-app-password' }}'
    }
});
                        @else
// wp-config.php or WP Mail SMTP settings:
define('SMTP_HOST', '{{ $host }}');
define('SMTP_PORT', 587);
define('SMTP_AUTH', true);
define('SMTP_USER', '{{ $credentialsAddress }}');
define('SMTP_PASS', '{{ $revealedPassword ?? 'your-app-password' }}');
define('SMTP_SECURE', 'tls');
                        @endif
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t border-neutral-800">
                    <button type="button" wire:click="$set('showCredentialsModal', false)" class="px-4 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-semibold">
                        Done
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 3: EDIT MAILBOX SETTINGS (QUOTA, AUTO-REPLY, FORWARDING, SIGNATURE) --}}
    @if ($showSettingsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl w-full max-w-lg p-6 space-y-4 shadow-2xl overflow-y-auto max-h-[90vh]">
                <div class="flex justify-between items-center pb-3 border-b border-neutral-800">
                    <h3 class="text-lg font-bold text-white tracking-tight">Mailbox Settings</h3>
                    <button type="button" wire:click="$set('showSettingsModal', false)" class="text-neutral-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Display Name</label>
                        <input type="text" wire:model="editDisplayName" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-neutral-300 mb-1">Quota (MB)</label>
                            <input type="number" wire:model="editQuotaMb" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-neutral-300 mb-1">Purpose</label>
                            <select wire:model="editPurpose" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg">
                                <option value="shared_inbox">Shared Inbox</option>
                                <option value="system_sender">System Sender</option>
                                <option value="personal_mailbox">Personal Mailbox</option>
                            </select>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer pt-1">
                        <input type="checkbox" wire:model="editIsSensitive" class="checkbox checkbox-sm checkbox-primary">
                        <span class="text-neutral-300">Enforce 2FA Step-up for Staff Access</span>
                    </label>

                    {{-- Auto-Reply Vacation Responder --}}
                    <div class="pt-2 border-t border-neutral-800 space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.live="editAutoReplyEnabled" class="checkbox checkbox-sm checkbox-primary">
                            <span class="font-bold text-white">Enable Auto-Reply (Vacation Responder)</span>
                        </label>

                        @if ($editAutoReplyEnabled)
                            <div class="space-y-2 pl-6">
                                <input type="text" wire:model="editAutoReplySubject" placeholder="Auto-Reply Subject" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg">
                                <textarea wire:model="editAutoReplyBody" rows="3" placeholder="Out of office message..." class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg"></textarea>
                            </div>
                        @endif
                    </div>

                    {{-- Forwarding Targets --}}
                    <div class="pt-2 border-t border-neutral-800 space-y-1">
                        <label class="block font-bold text-neutral-300">Forwarding Targets</label>
                        <p class="text-[11px] text-neutral-500">Forward incoming messages to external emails (one per line):</p>
                        <textarea wire:model="editForwardingTargets" rows="2" placeholder="manager@example.com&#10;backup@example.com" class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg font-mono text-xs"></textarea>
                    </div>

                    {{-- Signature --}}
                    <div class="pt-2 border-t border-neutral-800 space-y-1">
                        <label class="block font-bold text-neutral-300">Email Signature</label>
                        <textarea wire:model="editSignature" rows="2" placeholder="Best regards,&#10;STX Support Team" class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg text-xs"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-neutral-800">
                    <button type="button" wire:click="$set('showSettingsModal', false)" class="px-4 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-semibold">
                        Cancel
                    </button>
                    <button type="button" wire:click="saveSettings" class="px-5 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 4: GRANT ADMIN ACCESS --}}
    @if ($showGrantModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl">
                <div class="flex justify-between items-center pb-3 border-b border-neutral-800">
                    <h3 class="text-lg font-bold text-white tracking-tight">Admin Mailbox Permission</h3>
                    <button type="button" wire:click="$set('showGrantModal', false)" class="text-neutral-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Select Administrator</label>
                        <select wire:model="grantUserId" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg">
                            <option value="">-- Choose Staff Admin --</option>
                            @foreach ($admins as $ad)
                                <option value="{{ $ad->id }}">{{ $ad->name }} ({{ $ad->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Permission Level</label>
                        <select wire:model="grantLevel" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg">
                            <option value="read_only">Read Only (View folders &amp; messages)</option>
                            <option value="read_write">Read &amp; Write (View, reply, draft, organize)</option>
                            <option value="send_as">Send As (Authorized to send from address)</option>
                            <option value="manage">Manage (Settings, auto-reply, forwarding)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Allowed Folders (Optional)</label>
                        <input type="text" wire:model="grantFolders" placeholder="e.g. INBOX, Archive (Leave blank for all)" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg font-mono">
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-300 mb-1">Expiration Date (Optional)</label>
                        <input type="date" wire:model="grantExpiresAt" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded-lg font-mono">
                    </div>
                </div>

                <div class="flex justify-between items-center pt-3 border-t border-neutral-800">
                    @if ($grantUserId)
                        <button type="button" wire:click="revokeGrant('{{ $grantMailboxId }}', {{ $grantUserId }})" class="text-rose-400 hover:text-rose-300 text-xs font-bold">
                            Revoke Access
                        </button>
                    @else
                        <div></div>
                    @endif

                    <div class="flex gap-2">
                        <button type="button" wire:click="$set('showGrantModal', false)" class="px-4 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-semibold">
                            Cancel
                        </button>
                        <button type="button" wire:click="saveGrant" class="px-5 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold">
                            Save Grant
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

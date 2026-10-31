<div class="application-settings-form w-full space-y-6">
    <x-slot:title>Status & Telemetry Studio — STX Admin</x-slot>

    {{-- Top Heading & Control Bar --}}
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-black/[0.06] pb-4 dark:border-white/[0.06]">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-black dark:text-white flex items-center gap-2">
                    <span class="inline-block size-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Status & Telemetry Studio
                </h1>
                @if ($isOwner)
                    <span class="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded bg-purple-500/10 text-purple-400 border border-purple-500/20">
                        Owner Console
                    </span>
                @else
                    <span class="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        Admin View
                    </span>
                @endif
            </div>
            <p class="text-xs text-neutral-500 dark:text-fg-dim mt-1">
                Manage public monitors, declare maintenance windows, publish incident updates, and inspect live probe telemetry.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('status.public') }}" target="_blank"
                class="button button-outline text-xs py-1.5 px-3 flex items-center gap-1.5">
                <x-reicon name="external-link" class="size-3.5" />
                Open Public Status Page
            </a>
            <button type="button" wire:click="runCheckAll" wire:loading.attr="disabled"
                class="button button-highlighted text-xs py-1.5 px-3 flex items-center gap-1.5">
                <span wire:loading.remove wire:target="runCheckAll" class="flex items-center gap-1.5">
                    <x-reicon name="refresh" class="size-3.5" />
                    Check All Monitors
                </span>
                <span wire:loading wire:target="runCheckAll" class="flex items-center gap-1.5">
                    <x-loading compact class="size-3.5 text-white" />
                    Probing...
                </span>
            </button>
        </div>
    </div>

    {{-- Studio Subnavigation Tabs --}}
    <div class="flex items-center gap-2 border-b border-black/[0.06] dark:border-white/[0.06] overflow-x-auto">
        <button type="button" wire:click="$set('activeTab', 'monitors')"
            @class([
                'px-4 py-2 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap',
                'border-cyan-500 text-cyan-500 dark:text-cyan-400' => $activeTab === 'monitors',
                'border-transparent text-neutral-500 hover:text-black dark:text-fg-faint dark:hover:text-fg' => $activeTab !== 'monitors',
            ])>
            Monitors ({{ $monitors->count() }})
        </button>
        <button type="button" wire:click="$set('activeTab', 'incidents')"
            @class([
                'px-4 py-2 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap',
                'border-cyan-500 text-cyan-500 dark:text-cyan-400' => $activeTab === 'incidents',
                'border-transparent text-neutral-500 hover:text-black dark:text-fg-faint dark:hover:text-fg' => $activeTab !== 'incidents',
            ])>
            Incidents & Maintenance ({{ $incidents->count() }})
        </button>
        <button type="button" wire:click="$set('activeTab', 'notifications')"
            @class([
                'px-4 py-2 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap',
                'border-cyan-500 text-cyan-500 dark:text-cyan-400' => $activeTab === 'notifications',
                'border-transparent text-neutral-500 hover:text-black dark:text-fg-faint dark:hover:text-fg' => $activeTab !== 'notifications',
            ])>
            Alert Webhooks & Channels
        </button>
        <button type="button" wire:click="$set('activeTab', 'telemetry')"
            @class([
                'px-4 py-2 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap',
                'border-cyan-500 text-cyan-500 dark:text-cyan-400' => $activeTab === 'telemetry',
                'border-transparent text-neutral-500 hover:text-black dark:text-fg-faint dark:hover:text-fg' => $activeTab !== 'telemetry',
            ])>
            Live Probe Logs
        </button>
    </div>

    {{-- TAB 1: MONITORS STUDIO --}}
    @if ($activeTab === 'monitors')
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-black dark:text-white">Active Infrastructure Monitors</h2>
                    <p class="text-xs text-neutral-500">Live probing configurations and latency metrics.</p>
                </div>
                <button type="button" wire:click="openAddMonitorModal" class="button button-highlighted text-xs py-1 px-2.5 flex items-center gap-1">
                    <x-reicon name="plus" class="size-3" />
                    Add Monitor
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-white/[0.08] dark:bg-white/[0.02]">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-white/[0.06] dark:bg-white/[0.04] text-neutral-500 uppercase tracking-wider font-semibold">
                        <tr>
                            <th class="py-2.5 px-3">Service Name</th>
                            <th class="py-2.5 px-3">Group</th>
                            <th class="py-2.5 px-3">Type & Target</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3">Latency</th>
                            <th class="py-2.5 px-3">90d Uptime</th>
                            <th class="py-2.5 px-3">Interval</th>
                            <th class="py-2.5 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-white/[0.04]">
                        @forelse ($monitors as $m)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-white/[0.02] transition-colors">
                                <td class="py-3 px-3 font-medium text-black dark:text-white">
                                    <div class="flex items-center gap-2">
                                        <span @class([
                                            'size-2 rounded-full shrink-0',
                                            'bg-emerald-500' => $m->current_status === 'operational',
                                            'bg-amber-500' => $m->current_status === 'degraded',
                                            'bg-rose-500' => $m->current_status === 'down',
                                        ])></span>
                                        <div>
                                            <span>{{ $m->name }}</span>
                                            <span class="block text-[10px] text-neutral-400 font-normal">{{ $m->public_label }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-neutral-500 dark:text-fg-dim">{{ $m->group_name }}</td>
                                <td class="py-3 px-3">
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-mono uppercase bg-neutral-100 dark:bg-white/[0.06] text-neutral-600 dark:text-fg-dim">
                                        {{ $m->type }}
                                    </span>
                                    <span class="ml-1 text-[11px] text-neutral-400 font-mono">{{ $m->target_display }}</span>
                                </td>
                                <td class="py-3 px-3">
                                    <span @class([
                                        'px-2 py-0.5 rounded-full text-[10px] font-semibold capitalize',
                                        'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' => $m->current_status === 'operational',
                                        'bg-amber-500/10 text-amber-400 border border-amber-500/20' => $m->current_status === 'degraded',
                                        'bg-rose-500/10 text-rose-400 border border-rose-500/20' => $m->current_status === 'down',
                                    ])>
                                        {{ $m->current_status }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 font-mono text-[11px] text-neutral-600 dark:text-fg">
                                    {{ $m->last_latency_ms }} ms
                                </td>
                                <td class="py-3 px-3 font-mono text-[11px] text-neutral-600 dark:text-fg">
                                    {{ number_format($m->uptime_percentage, 2) }}%
                                </td>
                                <td class="py-3 px-3 text-neutral-500">{{ $m->interval_seconds }}s</td>
                                <td class="py-3 px-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button type="button" wire:click="runCheckNow({{ $m->id }})" title="Run Probe Now"
                                            class="button button-outline text-xs py-1 px-2">
                                            Probe
                                        </button>
                                        <button type="button" wire:click="openEditMonitorModal({{ $m->id }})" title="Edit"
                                            class="button button-outline text-xs py-1 px-2">
                                            Edit
                                        </button>
                                        <button type="button" wire:click="toggleMonitorActive({{ $m->id }})"
                                            @class([
                                                'button text-xs py-1 px-2',
                                                'button-warning' => $m->is_active,
                                                'button-outline' => !$m->is_active,
                                            ])>
                                            {{ $m->is_active ? 'Pause' : 'Resume' }}
                                        </button>
                                        @if ($isOwner)
                                            <button type="button" wire:confirm="Are you sure you want to delete monitor '{{ $m->name }}'?"
                                                wire:click="deleteMonitor({{ $m->id }})"
                                                class="button button-danger text-xs py-1 px-2">
                                                Delete
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-8 text-neutral-400">
                                    No monitors configured yet. Add your first service monitor.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB 2: INCIDENTS & MAINTENANCE --}}
    @if ($activeTab === 'incidents')
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-black dark:text-white">Incidents & Scheduled Maintenance</h2>
                    <p class="text-xs text-neutral-500">Public incident communications and maintenance schedules.</p>
                </div>
                <button type="button" wire:click="openAddIncidentModal" class="button button-highlighted text-xs py-1 px-2.5 flex items-center gap-1">
                    <x-reicon name="plus" class="size-3" />
                    Declare Incident / Notice
                </button>
            </div>

            <div class="space-y-4">
                @forelse ($incidents as $inc)
                    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-white/[0.08] dark:bg-white/[0.02]">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-200 dark:border-white/[0.06] pb-3">
                            <div class="flex items-center gap-2">
                                <span @class([
                                    'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider',
                                    'bg-rose-500/10 text-rose-400 border border-rose-500/20' => $inc->incident_type === 'incident' && $inc->status !== 'resolved',
                                    'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20' => $inc->incident_type === 'maintenance',
                                    'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' => $inc->status === 'resolved',
                                ])>
                                    {{ $inc->incident_type }}
                                </span>
                                <h3 class="text-sm font-semibold text-black dark:text-white">{{ $inc->title }}</h3>
                                <span class="text-xs px-2 py-0.5 rounded bg-neutral-100 dark:bg-white/[0.06] text-neutral-500 uppercase font-mono">
                                    {{ $inc->status }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="text-[11px] text-neutral-400">Created {{ $inc->created_at->diffForHumans() }}</span>
                                @if ($inc->status !== 'resolved')
                                    <button type="button" wire:click="openAddUpdateModal({{ $inc->id }})" class="button button-outline text-xs py-1 px-2.5">
                                        Post Update
                                    </button>
                                    <button type="button" wire:click="resolveIncident({{ $inc->id }})" class="button button-highlighted text-xs py-1 px-2.5">
                                        Resolve
                                    </button>
                                @endif
                            </div>
                        </div>

                        <p class="text-xs text-neutral-600 dark:text-fg mt-2">{{ $inc->message }}</p>

                        {{-- Incident Updates Timeline --}}
                        @if ($inc->updates->isNotEmpty())
                            <div class="mt-4 space-y-2 pl-4 border-l-2 border-neutral-200 dark:border-white/[0.1]">
                                @foreach ($inc->updates as $u)
                                    <div class="text-xs space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-black dark:text-fg capitalize">{{ $u->status }}</span>
                                            <span class="text-[10px] text-neutral-400">{{ $u->created_at->format('M d, H:i T') }}</span>
                                        </div>
                                        <p class="text-neutral-500 dark:text-fg-dim">{{ $u->message }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-xl border border-neutral-200 bg-white p-8 text-center text-xs text-neutral-400 dark:border-white/[0.08] dark:bg-white/[0.02]">
                        No active incidents or scheduled maintenance recorded.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- TAB 3: ALERT WEBHOOKS & CHANNELS --}}
    @if ($activeTab === 'notifications')
        <div class="max-w-2xl space-y-4">
            <div>
                <h2 class="text-sm font-semibold text-black dark:text-white">Alert Notification Webhooks</h2>
                <p class="text-xs text-neutral-500">Configure real-time outgoing alerts when infrastructure transitions to degraded or down state.</p>
            </div>

            <form wire:submit.prevent="saveNotificationSettings" class="space-y-4 rounded-xl border border-neutral-200 bg-white p-5 dark:border-white/[0.08] dark:bg-white/[0.02]">
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-black dark:text-fg">Telegram Bot Token</label>
                    <input type="password" wire:model.defer="telegramBotToken" {{ !$isOwner ? 'disabled' : '' }}
                        placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ"
                        class="w-full text-xs rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2 text-black dark:text-white" />
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-black dark:text-fg">Telegram Chat ID</label>
                    <input type="text" wire:model.defer="telegramChatId" {{ !$isOwner ? 'disabled' : '' }}
                        placeholder="-1001234567890"
                        class="w-full text-xs rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2 text-black dark:text-white" />
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-black dark:text-fg">Discord Webhook URL</label>
                    <input type="url" wire:model.defer="discordWebhookUrl" {{ !$isOwner ? 'disabled' : '' }}
                        placeholder="https://discord.com/api/webhooks/..."
                        class="w-full text-xs rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2 text-black dark:text-white" />
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-black dark:text-fg">Alert Notification Email</label>
                    <input type="email" wire:model.defer="alertEmail" {{ !$isOwner ? 'disabled' : '' }}
                        placeholder="ops@shanutechx.com"
                        class="w-full text-xs rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2 text-black dark:text-white" />
                </div>

                <div class="flex items-center justify-between pt-2">
                    <button type="button" wire:click="sendTestNotification" class="button button-outline text-xs py-1.5 px-3">
                        Test Webhook Trigger
                    </button>
                    @if ($isOwner)
                        <button type="submit" class="button button-highlighted text-xs py-1.5 px-4">
                            Save Alert Settings
                        </button>
                    @endif
                </div>
            </form>
        </div>
    @endif

    {{-- TAB 4: LIVE PROBE LOGS --}}
    @if ($activeTab === 'telemetry')
        <div class="space-y-3">
            <h2 class="text-sm font-semibold text-black dark:text-white">Recent Probe Checks (Last 50 Runs)</h2>
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-white/[0.08] dark:bg-white/[0.02]">
                <table class="w-full text-left text-xs font-mono">
                    <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-white/[0.06] dark:bg-white/[0.04] text-neutral-500 uppercase">
                        <tr>
                            <th class="py-2 px-3">Timestamp</th>
                            <th class="py-2 px-3">Monitor</th>
                            <th class="py-2 px-3">Status</th>
                            <th class="py-2 px-3">Latency</th>
                            <th class="py-2 px-3">Diagnostic Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-white/[0.04]">
                        @forelse ($recentChecks as $c)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-white/[0.02]">
                                <td class="py-2 px-3 text-neutral-400">{{ $c->checked_at?->format('H:i:s.v') }}</td>
                                <td class="py-2 px-3 text-black dark:text-white font-medium">{{ $c->monitor?->name ?? 'Unknown' }}</td>
                                <td class="py-2 px-3">
                                    <span @class([
                                        'px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase',
                                        'text-emerald-400' => $c->status === 'operational' || $c->status === 'up',
                                        'text-amber-400' => $c->status === 'degraded',
                                        'text-rose-400' => $c->status === 'down',
                                    ])>
                                        {{ $c->status }}
                                    </span>
                                </td>
                                <td class="py-2 px-3">{{ $c->response_time_ms }} ms</td>
                                <td class="py-2 px-3 text-neutral-500 truncate max-w-xs">{{ $c->error_message ?: 'OK' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-neutral-400">No probe records recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- MODAL: Add / Edit Monitor --}}
    @if ($showMonitorModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg rounded-2xl border border-neutral-200 bg-white p-6 shadow-2xl dark:border-white/[0.12] dark:bg-panel max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-neutral-200 dark:border-white/[0.08] pb-3 mb-4">
                    <h3 class="text-base font-bold text-black dark:text-white">
                        {{ $editingMonitorId ? 'Edit Monitor' : 'Add New Infrastructure Monitor' }}
                    </h3>
                    <button type="button" wire:click="$set('showMonitorModal', false)" class="text-neutral-400 hover:text-black dark:hover:text-white">&times;</button>
                </div>

                <form wire:submit.prevent="saveMonitor" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold mb-1">Monitor Name</label>
                        <input type="text" wire:model.defer="monitorName" required placeholder="e.g. Edge Ingress Proxy"
                            class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold mb-1">Public Display Label</label>
                            <input type="text" wire:model.defer="monitorPublicLabel" placeholder="e.g. Traefik Gateway"
                                class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2" />
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Category Group</label>
                            <select wire:model.defer="monitorGroupName" class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2">
                                <option value="Core Infrastructure">Core Infrastructure</option>
                                <option value="Compute Infrastructure">Compute Infrastructure</option>
                                <option value="Networking & Messaging">Networking & Messaging</option>
                                <option value="Database Clusters">Database Clusters</option>
                                <option value="Storage & CDN">Storage & CDN</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold mb-1">Probe Type</label>
                            <select wire:model="monitorType" class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2">
                                <option value="http">HTTP / HTTPS</option>
                                <option value="tcp">TCP Socket Port</option>
                                <option value="ping">ICMP Ping</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Check Interval (Seconds)</label>
                            <input type="number" wire:model.defer="monitorInterval" min="10" max="86400"
                                class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2" />
                        </div>
                    </div>

                    @if ($monitorType === 'http' || $monitorType === 'https')
                        <div>
                            <label class="block font-semibold mb-1">Target URL</label>
                            <input type="url" wire:model.defer="monitorTargetUrl" placeholder="https://api.shanutechx.com/health"
                                class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2 font-mono" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Expected HTTP Status</label>
                                <input type="number" wire:model.defer="monitorExpectedStatusCode" placeholder="200"
                                    class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2 font-mono" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Keyword Assertion (Optional)</label>
                                <input type="text" wire:model.defer="monitorKeywordSearch" placeholder="e.g. status:ok"
                                    class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2" />
                            </div>
                        </div>
                    @else
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <label class="block font-semibold mb-1">Target IP / Host</label>
                                <input type="text" wire:model.defer="monitorTargetHost" placeholder="127.0.0.1 or node1.stx.cloud"
                                    class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2 font-mono" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Port</label>
                                <input type="number" wire:model.defer="monitorPort" placeholder="80"
                                    class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2 font-mono" />
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center gap-6 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.defer="monitorIsPublic" class="rounded text-cyan-500" />
                            <span>Show on Public Status Page</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.defer="monitorIsActive" class="rounded text-cyan-500" />
                            <span>Probe Enabled</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-neutral-200 dark:border-white/[0.08]">
                        <button type="button" wire:click="$set('showMonitorModal', false)" class="button button-outline text-xs py-1.5 px-3">
                            Cancel
                        </button>
                        <button type="submit" class="button button-highlighted text-xs py-1.5 px-4">
                            Save Monitor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL: Add Incident --}}
    @if ($showIncidentModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg rounded-2xl border border-neutral-200 bg-white p-6 shadow-2xl dark:border-white/[0.12] dark:bg-panel">
                <div class="flex items-center justify-between border-b border-neutral-200 dark:border-white/[0.08] pb-3 mb-4">
                    <h3 class="text-base font-bold text-black dark:text-white">Declare Incident / Maintenance Notice</h3>
                    <button type="button" wire:click="$set('showIncidentModal', false)" class="text-neutral-400 hover:text-black dark:hover:text-white">&times;</button>
                </div>

                <form wire:submit.prevent="saveIncident" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold mb-1">Notice Title</label>
                        <input type="text" wire:model.defer="incidentTitle" required placeholder="e.g. Elevating API latency under heavy network ingress"
                            class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2 text-black dark:text-white" />
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold mb-1">Notice Type</label>
                            <select wire:model.defer="incidentType" class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2">
                                <option value="incident">Unplanned Incident</option>
                                <option value="maintenance">Scheduled Maintenance</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Impact Level</label>
                            <select wire:model.defer="incidentSeverity" class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2">
                                <option value="none">None / Informational</option>
                                <option value="minor">Minor Degradation</option>
                                <option value="major">Major Outage</option>
                                <option value="critical">Critical Emergency</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Initial Status</label>
                            <select wire:model.defer="incidentStatus" class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2">
                                <option value="investigating">Investigating</option>
                                <option value="identified">Identified</option>
                                <option value="monitoring">Monitoring</option>
                                <option value="resolved">Resolved</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold mb-1">Initial Incident Message</label>
                        <textarea wire:model.defer="incidentMessage" required rows="3" placeholder="Describe the symptoms and initial actions taken by engineering..."
                            class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2 text-black dark:text-white"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-neutral-200 dark:border-white/[0.08]">
                        <button type="button" wire:click="$set('showIncidentModal', false)" class="button button-outline text-xs py-1.5 px-3">
                            Cancel
                        </button>
                        <button type="submit" class="button button-highlighted text-xs py-1.5 px-4">
                            Publish to Status Page
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL: Post Incident Update --}}
    @if ($showUpdateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="w-full max-w-md rounded-2xl border border-neutral-200 bg-white p-6 shadow-2xl dark:border-white/[0.12] dark:bg-panel">
                <div class="flex items-center justify-between border-b border-neutral-200 dark:border-white/[0.08] pb-3 mb-4">
                    <h3 class="text-base font-bold text-black dark:text-white">Post Progress Update</h3>
                    <button type="button" wire:click="$set('showUpdateModal', false)" class="text-neutral-400 hover:text-black dark:hover:text-white">&times;</button>
                </div>

                <form wire:submit.prevent="saveUpdate" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold mb-1">New Status</label>
                        <select wire:model.defer="updateStatus" class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2">
                            <option value="investigating">Investigating</option>
                            <option value="identified">Identified</option>
                            <option value="monitoring">Monitoring</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold mb-1">Update Message</label>
                        <textarea wire:model.defer="updateMessage" required rows="3" placeholder="Detail the latest findings, mitigations applied, or resolution verification..."
                            class="w-full rounded-lg border border-neutral-300 dark:border-white/[0.1] bg-white dark:bg-app px-3 py-2 text-black dark:text-white"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-white/[0.08]">
                        <button type="button" wire:click="$set('showUpdateModal', false)" class="button button-outline text-xs py-1.5 px-3">
                            Cancel
                        </button>
                        <button type="submit" class="button button-highlighted text-xs py-1.5 px-4">
                            Publish Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

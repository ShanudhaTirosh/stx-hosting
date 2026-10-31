<div class="min-h-screen bg-[#07090e] text-white">
    {{-- Header Banner --}}
    <header class="border-b border-white/[0.08] bg-[#0c1017]/80 backdrop-blur sticky top-0 z-40">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="/" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                    <img src="/STKcdr.svg" alt="STX Enterprises" class="size-6" />
                    <span class="font-bold text-base tracking-tight">
                        <span class="text-cyan-400">STX</span> Status
                    </span>
                </a>
                <span class="text-xs px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 font-mono">
                    Live Telemetry
                </span>
            </div>

            <div class="flex items-center gap-3">
                <a href="/" class="text-xs text-neutral-400 hover:text-white transition-colors">
                    &larr; Storefront
                </a>
                <a href="/portal/login" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white transition-colors">
                    Customer Portal
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8 space-y-8">
        {{-- Overall Health Status Banner --}}
        <div @class([
            'rounded-2xl p-6 border shadow-2xl transition-all',
            'bg-emerald-950/20 border-emerald-500/30 text-emerald-400 shadow-emerald-950/20' => $overallStatus === 'operational',
            'bg-amber-950/20 border-amber-500/30 text-amber-400 shadow-amber-950/20' => $overallStatus === 'degraded_performance',
            'bg-rose-950/20 border-rose-500/30 text-rose-400 shadow-rose-950/20' => $overallStatus === 'major_outage',
            'bg-cyan-950/20 border-cyan-500/30 text-cyan-400 shadow-cyan-950/20' => $overallStatus === 'maintenance',
        ])>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <span class="relative flex size-4">
                        <span @class([
                            'animate-ping absolute inline-flex h-full w-full rounded-full opacity-75',
                            'bg-emerald-400' => $overallStatus === 'operational',
                            'bg-amber-400' => $overallStatus === 'degraded_performance',
                            'bg-rose-400' => $overallStatus === 'major_outage',
                            'bg-cyan-400' => $overallStatus === 'maintenance',
                        ])></span>
                        <span @class([
                            'relative inline-flex rounded-full size-4',
                            'bg-emerald-500' => $overallStatus === 'operational',
                            'bg-amber-500' => $overallStatus === 'degraded_performance',
                            'bg-rose-500' => $overallStatus === 'major_outage',
                            'bg-cyan-500' => $overallStatus === 'maintenance',
                        ])></span>
                    </span>
                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight">
                            @if ($overallStatus === 'operational')
                                All Systems Fully Operational
                            @elseif ($overallStatus === 'degraded_performance')
                                Degraded Service Performance Detected
                            @elseif ($overallStatus === 'major_outage')
                                Major Infrastructure Outage Under Investigation
                            @elseif ($overallStatus === 'maintenance')
                                Scheduled Infrastructure Maintenance in Progress
                            @endif
                        </h1>
                        <p class="text-xs opacity-80 mt-0.5">
                            Continuous probes monitoring {{ $totalMonitors }} platform services worldwide.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4 text-xs font-mono">
                    <div class="text-right">
                        <span class="text-neutral-400 block text-[10px] uppercase font-sans">90-Day Uptime</span>
                        <span class="text-base font-bold text-white">{{ $averageUptime }}%</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Active Incidents & Maintenance Notices --}}
        @if ($activeIncidents->isNotEmpty())
            <div class="space-y-4">
                <h2 class="text-sm font-semibold tracking-wider uppercase text-neutral-400">Active Communications</h2>
                @foreach ($activeIncidents as $inc)
                    <div class="rounded-xl border border-amber-500/30 bg-amber-950/10 p-5 space-y-3">
                        <div class="flex items-center justify-between border-b border-amber-500/20 pb-2.5">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">
                                    {{ $inc->incident_type }}
                                </span>
                                <h3 class="text-sm font-bold text-white">{{ $inc->title }}</h3>
                            </div>
                            <span class="text-xs font-mono text-amber-400 uppercase">{{ $inc->status }}</span>
                        </div>
                        <p class="text-xs text-neutral-300 leading-relaxed">{{ $inc->message }}</p>

                        @if ($inc->updates->isNotEmpty())
                            <div class="mt-3 space-y-2 border-l border-amber-500/30 pl-3">
                                @foreach ($inc->updates as $u)
                                    <div class="text-xs space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-amber-300 capitalize">{{ $u->status }}</span>
                                            <span class="text-[10px] text-neutral-500">{{ $u->created_at->format('M d, H:i T') }}</span>
                                        </div>
                                        <p class="text-neutral-400">{{ $u->message }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Service Monitor Groups --}}
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold tracking-wider uppercase text-neutral-400">Infrastructure Services</h2>
                <span class="text-xs text-neutral-500 flex items-center gap-2 font-mono">
                    <span class="inline-block size-2 rounded-full bg-emerald-500"></span> Up
                    <span class="inline-block size-2 rounded-full bg-amber-500 ml-2"></span> Degraded
                    <span class="inline-block size-2 rounded-full bg-rose-500 ml-2"></span> Down
                </span>
            </div>

            @foreach ($groupedMonitors as $groupName => $monitorsList)
                <div class="rounded-2xl border border-white/[0.08] bg-[#0c1017] overflow-hidden shadow-xl">
                    <div class="px-5 py-3 border-b border-white/[0.06] bg-white/[0.02] flex items-center justify-between">
                        <h3 class="text-xs font-bold tracking-wider uppercase text-neutral-300">{{ $groupName }}</h3>
                        <span class="text-[11px] text-neutral-500 font-mono">{{ $monitorsList->count() }} Services</span>
                    </div>

                    <div class="divide-y divide-white/[0.05]">
                        @foreach ($monitorsList as $monitor)
                            <div class="p-5 space-y-3 hover:bg-white/[0.015] transition-colors">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2.5">
                                        <span @class([
                                            'size-2.5 rounded-full shrink-0',
                                            'bg-emerald-400' => $monitor->current_status === 'operational',
                                            'bg-amber-400' => $monitor->current_status === 'degraded',
                                            'bg-rose-400' => $monitor->current_status === 'down',
                                        ])></span>
                                        <div>
                                            <span class="text-sm font-semibold text-white">{{ $monitor->name }}</span>
                                            <span class="text-xs text-neutral-400 ml-2 font-normal">{{ $monitor->public_label }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 text-xs">
                                        <span class="text-neutral-400 font-mono">{{ $monitor->last_latency_ms }} ms</span>
                                        <span @class([
                                            'px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider',
                                            'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' => $monitor->current_status === 'operational',
                                            'bg-amber-500/10 text-amber-400 border border-amber-500/20' => $monitor->current_status === 'degraded',
                                            'bg-rose-500/10 text-rose-400 border border-rose-500/20' => $monitor->current_status === 'down',
                                        ])>
                                            {{ $monitor->current_status }}
                                        </span>
                                    </div>
                                </div>

                                {{-- 90-Day Timeline Bar --}}
                                <div>
                                    <div class="flex items-center gap-[2px] h-7 w-full overflow-hidden rounded bg-white/[0.02] p-1 border border-white/[0.04]">
                                        @foreach ($monitor->daily_uptime_history as $historyDay)
                                            <div @class([
                                                'h-full flex-1 rounded-[1.5px] transition-all hover:scale-y-125 hover:z-10 cursor-pointer relative group',
                                                'bg-emerald-500 hover:bg-emerald-400' => $historyDay['status'] === 'up',
                                                'bg-amber-500 hover:bg-amber-400' => $historyDay['status'] === 'degraded',
                                                'bg-rose-500 hover:bg-rose-400' => $historyDay['status'] === 'down',
                                            ])>
                                                {{-- Tooltip on Hover --}}
                                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover:block z-50 whitespace-nowrap rounded bg-neutral-900 border border-white/20 px-2 py-1 text-[10px] text-white shadow-xl pointer-events-none">
                                                    <span class="font-bold">{{ $historyDay['day'] }}</span>: {{ ucfirst($historyDay['status']) }}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="flex items-center justify-between text-[10px] text-neutral-500 mt-1 font-mono">
                                        <span>90 days ago</span>
                                        <span class="text-neutral-400">{{ number_format($monitor->uptime_percentage, 2) }}% Uptime</span>
                                        <span>Today</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Incident History Archive --}}
        @if ($resolvedIncidents->isNotEmpty())
            <div class="space-y-4 pt-4 border-t border-white/[0.08]">
                <h2 class="text-sm font-semibold tracking-wider uppercase text-neutral-400">Past Incident History</h2>
                <div class="space-y-3">
                    @foreach ($resolvedIncidents as $past)
                        <div class="rounded-xl border border-white/[0.06] bg-[#0c1017] p-4 text-xs space-y-2">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-white">{{ $past->title }}</h3>
                                <span class="text-neutral-500 font-mono text-[11px]">{{ $past->created_at->format('M d, Y') }}</span>
                            </div>
                            <p class="text-neutral-400 leading-relaxed">{{ $past->message }}</p>
                            @if ($past->resolved_at)
                                <p class="text-emerald-400 text-[11px] font-mono">
                                    Resolved on {{ $past->resolved_at->format('M d, H:i T') }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </main>

    {{-- Footer --}}
    <footer class="border-t border-white/[0.06] py-8 text-center text-xs text-neutral-500 space-y-2">
        <p>&copy; {{ date('Y') }} STX Enterprises. Autonomous Cloud Telemetry Platform.</p>
        <p class="font-mono text-[11px]">Probes dispatched every 60 seconds from multiple global edge datacenters.</p>
    </footer>
</div>

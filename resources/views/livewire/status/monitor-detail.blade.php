<div class="min-h-screen bg-[#030014] text-white flex flex-col justify-between relative overflow-hidden">
    {{-- Background Grid & Glow --}}
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0"></div>
    <div class="fixed top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-purple-600/15 rounded-full blur-[140px] pointer-events-none -z-10"></div>

    <x-storefront.navbar current="status" />

    <main class="flex-1 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-24 relative z-10 w-full space-y-10">
        {{-- Header Navigation --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-6">
            <div class="space-y-1">
                <a href="{{ route('status.public') }}" wire:navigate
                    class="inline-flex items-center gap-1.5 text-xs font-mono text-purple-400 hover:text-purple-300 transition-colors mb-2">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Back to Status Portal</span>
                </a>
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">{{ $monitor->name }}</h1>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider bg-white/5 border border-white/10 text-neutral-300">
                        {{ strtoupper($monitor->type) }}
                    </span>
                </div>
                <p class="text-xs font-mono text-neutral-400">
                    Target: <span class="text-neutral-200">{{ $monitor->target_display }}</span>
                    @if ($monitor->last_checked_at)
                        &bull; Last probe: {{ $monitor->last_checked_at->diffForHumans() }}
                    @endif
                </p>
            </div>

            {{-- Live Status Capsule --}}
            <div class="flex items-center gap-3 shrink-0">
                <div @class([
                    'inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border text-xs font-mono font-bold tracking-wider uppercase',
                    'bg-emerald-500/10 border-emerald-500/30 text-emerald-300 shadow-[0_0_20px_rgba(16,185,129,0.2)]' => $monitor->status === 'operational',
                    'bg-amber-500/10 border-amber-500/30 text-amber-300 shadow-[0_0_20px_rgba(245,158,11,0.2)]' => $monitor->status === 'degraded',
                    'bg-rose-500/10 border-rose-500/30 text-rose-300 shadow-[0_0_20px_rgba(244,63,94,0.2)]' => $monitor->status === 'down',
                ])>
                    <span @class([
                        'size-2 rounded-full',
                        'bg-emerald-400 animate-pulse' => $monitor->status === 'operational',
                        'bg-amber-400 animate-ping' => $monitor->status === 'degraded',
                        'bg-rose-400 animate-ping' => $monitor->status === 'down',
                    ])></span>
                    <span>{{ ucfirst($monitor->status) }}</span>
                </div>
            </div>
        </div>

        {{-- Metrics Summary Grid --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="glass-card rounded-2xl p-5 border border-white/10">
                <div class="text-[11px] font-mono text-neutral-400 uppercase tracking-wider">Current Latency</div>
                <div class="text-2xl sm:text-3xl font-black text-white mt-1">{{ $monitor->last_latency_ms ?? 0 }} <span class="text-xs text-neutral-400 font-normal">ms</span></div>
            </div>
            <div class="glass-card rounded-2xl p-5 border border-white/10">
                <div class="text-[11px] font-mono text-neutral-400 uppercase tracking-wider">Average Latency</div>
                <div class="text-2xl sm:text-3xl font-black text-purple-300 mt-1">{{ $avgLatency }} <span class="text-xs text-neutral-400 font-normal">ms</span></div>
            </div>
            <div class="glass-card rounded-2xl p-5 border border-white/10">
                <div class="text-[11px] font-mono text-neutral-400 uppercase tracking-wider">Min / Max Range</div>
                <div class="text-xl sm:text-2xl font-black text-cyan-300 mt-1">{{ $minLatency }} - {{ $maxLatency }} <span class="text-xs text-neutral-400 font-normal">ms</span></div>
            </div>
            <div class="glass-card rounded-2xl p-5 border border-white/10">
                <div class="text-[11px] font-mono text-neutral-400 uppercase tracking-wider">90-Day Uptime</div>
                <div class="text-2xl sm:text-3xl font-black text-emerald-400 mt-1">{{ number_format($monitor->uptime_percentage, 2) }}%</div>
            </div>
        </div>

        {{-- 90-Day Timeline Section --}}
        <div class="glass-card rounded-2xl p-6 border border-white/10 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider font-mono">90-Day Historical Uptime</h3>
                <span class="text-xs font-mono text-emerald-400 font-bold">{{ number_format($monitor->uptime_percentage, 2) }}% availability</span>
            </div>

            <div class="flex items-center gap-1 w-full overflow-hidden py-2" title="90-day daily uptime history">
                @foreach ($monitor->daily_uptime_history as $day)
                    <div @class([
                        'flex-1 h-8 rounded-sm transition-transform hover:scale-125 cursor-pointer relative group',
                        'bg-emerald-500/80 hover:bg-emerald-400' => $day['status'] === 'up',
                        'bg-amber-500/80 hover:bg-amber-400' => $day['status'] === 'partial',
                        'bg-rose-500/80 hover:bg-rose-400' => $day['status'] === 'down',
                    ])>
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-30 pointer-events-none whitespace-nowrap rounded-lg bg-black/90 border border-white/20 px-2 py-1 text-[10px] font-mono text-white shadow-xl">
                            {{ $day['date'] }}: {{ ucfirst($day['status']) }} ({{ $day['uptime'] }}%)
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-between text-[11px] font-mono text-neutral-500">
                <span>90 days ago</span>
                <span>Today</span>
            </div>
        </div>

        {{-- Probe Log History Window --}}
        <div class="glass-card rounded-2xl p-6 border border-white/10 space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider font-mono">Recent Telemetry Checks</h3>
                    <p class="text-xs text-neutral-400 mt-0.5">Live probe verification recorded by the STX health check engine.</p>
                </div>

                {{-- Time Window Selector --}}
                <div class="flex items-center rounded-xl p-1 border border-white/10 bg-white/5 text-xs font-mono">
                    @foreach (['60m' => '60 min', '24h' => '24 hours', '7d' => '7 days', '90d' => '90 days'] as $winKey => $winLabel)
                        <button type="button" wire:click="setTimeWindow('{{ $winKey }}')"
                            @class([
                                'px-3 py-1.5 rounded-lg font-bold transition-colors',
                                'bg-purple-600 text-white' => $timeWindow === $winKey,
                                'text-neutral-400 hover:text-white' => $timeWindow !== $winKey,
                            ])>
                            {{ $winLabel }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Recent Checks Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs font-mono">
                    <thead class="text-neutral-500 uppercase border-b border-white/10 pb-2">
                        <tr>
                            <th class="py-2.5">Status</th>
                            <th class="py-2.5">HTTP / Code</th>
                            <th class="py-2.5">Response Time</th>
                            <th class="py-2.5 text-right">Checked Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($recentChecks as $check)
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-3">
                                    <span @class([
                                        'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold',
                                        'bg-emerald-500/10 text-emerald-400' => $check->status === 'operational',
                                        'bg-amber-500/10 text-amber-400' => $check->status === 'degraded',
                                        'bg-rose-500/10 text-rose-400' => $check->status === 'down',
                                    ])>
                                        <span class="size-1.5 rounded-full {{ $check->status === 'operational' ? 'bg-emerald-400' : ($check->status === 'degraded' ? 'bg-amber-400' : 'bg-rose-400') }}"></span>
                                        {{ ucfirst($check->status) }}
                                    </span>
                                </td>
                                <td class="py-3 text-neutral-300">
                                    {{ $check->status_code ? 'HTTP ' . $check->status_code : ($check->status === 'operational' ? 'TCP / ACK' : 'Timeout') }}
                                </td>
                                <td class="py-3 font-bold text-white">
                                    {{ $check->response_time_ms }} ms
                                </td>
                                <td class="py-3 text-right text-neutral-400">
                                    {{ $check->checked_at->format('M j, H:i:s') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-neutral-500">
                                    No recorded checks in this time window. Live probes run periodically via background scheduler.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <x-storefront.footer />
</div>


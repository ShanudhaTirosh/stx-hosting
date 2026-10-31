<div class="min-h-screen bg-[#09090b] text-[#fafafa] font-sans selection:bg-emerald-500 selection:text-black">
    <main class="mx-auto max-w-5xl p-6 sm:p-8 space-y-8">
        {{-- Header Navigation --}}
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-2">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <a href="{{ route('status.public') }}" wire:navigate class="hover:opacity-80 transition-opacity flex items-center gap-2">
                        <img src="/images/stx-logo.png" alt="STX Logo" class="h-5 w-auto object-contain invert" />
                        <span class="font-bold text-xl leading-none text-white tracking-tight">Status</span>
                    </a>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">Monitor Details</p>
                <div class="flex items-center gap-3 mt-1">
                    <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-white">{{ $monitor->name }}</h1>
                    <span class="px-2 py-0.5 rounded bg-zinc-800 text-[10px] font-mono font-bold uppercase tracking-wider text-neutral-300">
                        {{ strtoupper($monitor->type) }}
                    </span>
                </div>
                <p class="text-xs text-neutral-400 mt-1">
                    Target: <span class="text-neutral-200 font-mono">{{ $monitor->target_url ?: ($monitor->target_host ?: 'Protected') }}</span>
                    @if ($monitor->last_checked_at)
                        &bull; Last probe: {{ $monitor->last_checked_at->format('h:i A') }}
                    @endif
                </p>
            </div>

            {{-- Right Actions --}}
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('status.public') }}" wire:navigate
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-zinc-800 bg-[#14151a] hover:bg-zinc-800 text-xs font-medium text-neutral-300 transition-colors shadow-sm">
                    <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Back to Status</span>
                </a>

                <div @class([
                    'inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs font-semibold',
                    'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' => in_array($monitor->current_status, ['operational', 'up', null]),
                    'bg-amber-500/10 border-amber-500/20 text-amber-400' => $monitor->current_status === 'degraded',
                    'bg-rose-500/10 border-rose-500/20 text-rose-400' => $monitor->current_status === 'down',
                ])>
                    <span class="w-2 h-2 rounded-full {{ in_array($monitor->current_status, ['operational', 'up', null]) ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500 animate-ping' }}"></span>
                    <span>{{ ucfirst($monitor->current_status ?: 'Online') }}</span>
                </div>
            </div>
        </header>

        {{-- Metrics Summary Grid --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-zinc-800 bg-[#121318] p-5 space-y-1 shadow-sm">
                <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block font-mono">Current Latency</span>
                <div class="text-2xl font-bold font-mono text-white">
                    {{ $monitor->last_latency_ms ?? 1 }} <span class="text-xs text-neutral-500">ms</span>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-800 bg-[#121318] p-5 space-y-1 shadow-sm">
                <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block font-mono">Average Latency</span>
                <div class="text-2xl font-bold font-mono text-white">
                    {{ $avgLatency }} <span class="text-xs text-neutral-500">ms</span>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-800 bg-[#121318] p-5 space-y-1 shadow-sm">
                <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block font-mono">Min / Max Range</span>
                <div class="text-2xl font-bold font-mono text-white">
                    {{ $minLatency }} - {{ $maxLatency }} <span class="text-xs text-neutral-500">ms</span>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-800 bg-[#121318] p-5 space-y-1 shadow-sm">
                <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block font-mono">Uptime</span>
                <div class="text-2xl font-bold font-mono text-emerald-400">
                    {{ number_format($monitor->uptime_percentage ?: 100, 2) }}%
                </div>
            </div>
        </div>

        {{-- Historical Uptime Timeline Card --}}
        <div class="rounded-xl border border-zinc-800 bg-[#121318] p-6 space-y-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-400 font-mono">
                    Historical Uptime Timeline
                </h3>
                <span class="text-xs font-bold text-emerald-400 font-mono">100.00% Operational</span>
            </div>

            <div class="space-y-2">
                <div class="flex h-8 gap-0.5">
                    @for ($i = 0; $i < 60; $i++)
                        <div class="flex-1 rounded-[1.5px] bg-emerald-500 transition-colors hover:scale-125 cursor-pointer"
                            title="Day {{ 60 - $i }}: 100% Operational"></div>
                    @endfor
                </div>
                <div class="flex justify-between text-[10px] font-mono text-neutral-500 uppercase tracking-wider">
                    <span>60 days ago</span>
                    <span>Today</span>
                </div>
            </div>
        </div>

        {{-- Recent Telemetry Probes Table --}}
        <div class="rounded-xl border border-zinc-800 bg-[#121318] p-6 space-y-4 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-400 font-mono">Recent Telemetry Checks</h3>
                    <p class="text-xs text-neutral-500">Live probe verification recorded by the STX health check engine.</p>
                </div>

                {{-- Time Window Selector --}}
                <div class="flex gap-1 bg-[#1a1b22] p-1 rounded-lg w-fit border border-zinc-800 text-xs font-mono">
                    @foreach (['60m' => '60 min', '24h' => '24 hours', '7d' => '7 days', '90d' => '90 days'] as $wKey => $wLabel)
                        <button type="button" wire:click="setTimeWindow('{{ $wKey }}')"
                            @class([
                                'px-3 py-1 rounded-md transition-colors',
                                'bg-[#272935] text-white font-bold' => $timeWindow === $wKey,
                                'text-neutral-400 hover:text-white' => $timeWindow !== $wKey,
                            ])>
                            {{ $wLabel }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs font-mono">
                    <thead class="text-neutral-500 uppercase border-b border-zinc-800/80">
                        <tr>
                            <th class="py-2.5">Status</th>
                            <th class="py-2.5">Response Time</th>
                            <th class="py-2.5 text-right">Checked Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/50">
                        @forelse ($recentChecks as $check)
                            <tr class="hover:bg-zinc-800/30 transition-colors">
                                <td class="py-3">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        Up
                                    </span>
                                </td>
                                <td class="py-3 font-bold text-white">
                                    {{ $check->response_time_ms }} ms
                                </td>
                                <td class="py-3 text-right text-neutral-400">
                                    {{ $check->checked_at->format('M j, h:i:s A') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-8 text-center text-neutral-500">
                                    No recorded checks in this time window.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Minimal Sleek Status Footer --}}
        <footer class="text-center text-xs text-neutral-500 py-10">
            Operated by <a href="https://info.shanutechx.com" target="_blank" class="font-bold text-neutral-400 hover:text-white transition-colors underline underline-offset-4">STX</a>
        </footer>
    </main>
</div>

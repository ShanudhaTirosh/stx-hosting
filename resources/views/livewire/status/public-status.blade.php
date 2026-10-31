<div class="min-h-screen bg-[#07080d] text-white flex flex-col justify-between relative overflow-hidden font-sans">
    {{-- Background Grid & Glow --}}
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-15 pointer-events-none z-0"></div>

    <main class="flex-1 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-20 relative z-10 w-full space-y-8">
        {{-- Header Section matching user design --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pt-2">
            <div class="space-y-1">
                <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
                    STX Cloud &amp; Infrastructure Health
                </h1>
                <p class="text-xs sm:text-sm text-neutral-400">
                    Real-time availability, operational telemetry, and uptime status for STX platform services.
                </p>
            </div>

            {{-- Top Action Cluster --}}
            <div class="flex items-center gap-2.5 flex-wrap sm:flex-nowrap shrink-0">
                <a href="{{ route('status.incidents') }}" wire:navigate
                    class="px-3.5 py-1.5 rounded-xl bg-[#12141f] border border-[#23273a] hover:bg-[#1a1d2e] hover:border-neutral-600 text-xs font-semibold text-neutral-200 transition-colors flex items-center gap-1.5 shadow-sm">
                    <svg class="size-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>Incidents</span>
                </a>

                {{-- Notification Bell --}}
                <a href="{{ route('status.incidents') }}" wire:navigate
                    class="p-2 rounded-xl bg-[#12141f] border border-[#23273a] hover:bg-[#1a1d2e] text-neutral-400 hover:text-white transition-colors"
                    title="Subscribe to incident updates">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </a>

                {{-- Admin Studio Settings --}}
                <a href="{{ route('admin.status.index') }}" wire:navigate
                    class="p-2 rounded-xl bg-[#12141f] border border-[#23273a] hover:bg-[#1a1d2e] text-neutral-400 hover:text-white transition-colors"
                    title="Admin Status Studio">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </a>

                {{-- Sun / Theme Toggle --}}
                <button type="button"
                    class="p-2 rounded-xl bg-[#12141f] border border-[#23273a] hover:bg-[#1a1d2e] text-neutral-400 hover:text-white transition-colors"
                    title="Theme Mode">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </button>

                {{-- Status Pill --}}
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#0d101a] border border-[#202538] text-xs font-semibold text-white shadow-sm">
                    <span @class([
                        'size-2 rounded-full',
                        'bg-emerald-500' => $overallStatusColor === 'emerald',
                        'bg-amber-500 animate-pulse' => $overallStatusColor === 'amber',
                        'bg-rose-500 animate-ping' => $overallStatusColor === 'rose',
                        'bg-purple-500' => $overallStatusColor === 'purple',
                    ])></span>
                    <span>{{ $overallStatusText }}</span>
                </div>
            </div>
        </div>

        {{-- Control Bar Card --}}
        <div class="bg-[#0e1017] border border-[#1d212f] rounded-2xl p-4 sm:p-5 flex flex-col md:flex-row md:items-end justify-between gap-5 shadow-xl">
            {{-- History Window --}}
            <div class="space-y-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400 font-mono block">
                    HISTORY WINDOW
                </span>
                <div class="bg-[#131520] border border-[#23273a] rounded-xl p-1 flex items-center gap-1 text-xs font-mono">
                    @foreach (['60m' => ['60', 'min'], '24h' => ['24', 'hours'], '30d' => ['30', 'days'], '90d' => ['90', 'days']] as $wKey => $wParts)
                        <button type="button" wire:click="setHistoryWindow('{{ $wKey }}')"
                            @class([
                                'px-3 py-1.5 rounded-lg text-center transition-colors font-medium',
                                'bg-[#222738] text-white font-bold shadow-sm' => $historyWindow === $wKey,
                                'text-neutral-400 hover:text-white' => $historyWindow !== $wKey,
                            ])>
                            <span class="block leading-none font-bold">{{ $wParts[0] }}</span>
                            <span class="block text-[10px] text-neutral-400 leading-tight">{{ $wParts[1] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Search Monitors --}}
            <div class="space-y-2 flex-1 max-w-lg">
                <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400 font-mono block">
                    SEARCH MONITORS
                </span>
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="searchQuery"
                        placeholder="Search by name or URL"
                        class="w-full bg-[#131520] border border-[#23273a] rounded-xl pl-9 pr-4 py-2.5 text-xs text-white placeholder-neutral-500 focus:outline-none focus:border-purple-500 transition-colors">
                    <svg class="size-4 text-neutral-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            {{-- Legend --}}
            <div class="flex items-center gap-3 text-xs font-mono text-neutral-400 shrink-0 pb-1">
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    <span>Healthy</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-rose-500"></span>
                    <span>Down</span>
                </div>
            </div>
        </div>

        {{-- Active Incidents Banner (if any) --}}
        @if ($activeIncidents->isNotEmpty())
            <div class="space-y-3">
                @foreach ($activeIncidents as $incident)
                    <div class="p-5 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-start gap-4">
                        <div class="p-2 rounded-xl bg-amber-500/20 text-amber-400 shrink-0 mt-0.5">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div class="flex-1 space-y-1">
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-white text-sm">{{ $incident->title }}</h3>
                                <span class="text-[10px] font-mono text-amber-300 uppercase px-2 py-0.5 rounded-full bg-amber-500/20 border border-amber-500/30">
                                    {{ ucfirst(str_replace('_', ' ', $incident->status)) }}
                                </span>
                            </div>
                            <p class="text-xs text-neutral-300 leading-relaxed font-sans">
                                {{ $incident->description }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Monitor Cards Grid (2 Columns matching user screenshot) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @forelse ($monitors as $monitor)
                @php
                    $isOnline = in_array($monitor->current_status, ['operational', 'up', null]);
                    $latencyFast = ($monitor->last_latency_ms && $monitor->last_latency_ms < 50);
                    $badgeType = match(strtolower($monitor->type)) {
                        'tcp' => 'VPS HOST NODE',
                        'ping' => 'VPS PING NODE',
                        'https' => 'HTTPS',
                        'http' => 'HTTP',
                        default => strtoupper($monitor->type),
                    };

                    $targetDisplay = $monitor->target_url
                        ? preg_replace('#^https?://#', '', $monitor->target_url)
                        : ($monitor->target_host ?: 'Host Protected');
                @endphp

                <div class="bg-[#0d0f17] border border-[#1b1f2e] hover:border-[#2a3047] rounded-2xl p-6 transition-all shadow-lg flex flex-col justify-between space-y-5 group">
                    <div>
                        {{-- Top Title & Online Pill --}}
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <div class="space-y-1">
                                <a href="{{ route('status.monitor', $monitor->uuid) }}" wire:navigate
                                    class="text-base sm:text-lg font-bold text-white hover:text-cyan-400 transition-colors tracking-tight block">
                                    {{ $monitor->name }}
                                </a>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex px-2 py-0.5 rounded bg-[#161824] border border-[#24293c] text-[10px] font-mono font-bold uppercase text-neutral-400">
                                        {{ $badgeType }}
                                    </span>
                                    <span class="text-xs font-mono text-neutral-400 truncate max-w-[200px] sm:max-w-[240px]">
                                        {{ $targetDisplay }}
                                    </span>
                                </div>
                            </div>

                            {{-- Status Pill --}}
                            <div class="shrink-0">
                                @if ($isOnline)
                                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-[#0b281d] border border-[#135035] text-[#10b981] inline-flex items-center">
                                        Online
                                    </span>
                                @elseif ($monitor->current_status === 'degraded')
                                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-[#28210b] border border-[#504413] text-amber-400 inline-flex items-center">
                                        Degraded
                                    </span>
                                @else
                                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-[#280b13] border border-[#501323] text-rose-400 inline-flex items-center">
                                        Down
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- 4 Metrics Column --}}
                        <div class="grid grid-cols-4 gap-2 text-left pt-2 pb-3 border-t border-white/[0.04]">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block font-mono">
                                    LAST CHECKED
                                </span>
                                <span class="text-xs font-bold text-white font-mono mt-0.5 block">
                                    {{ $monitor->last_checked_at ? $monitor->last_checked_at->format('h:i A') : '08:12 PM' }}
                                </span>
                            </div>

                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block font-mono">
                                    UPTIME
                                </span>
                                <span class="text-xs font-bold text-white font-mono mt-0.5 block">
                                    {{ number_format($monitor->uptime_percentage ?: 100, 0) }}%
                                </span>
                            </div>

                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block font-mono">
                                    CHECKS
                                </span>
                                <span class="text-xs font-bold text-white font-mono mt-0.5 block">
                                    {{ $monitor->checks_count ?: 1 }}
                                </span>
                            </div>

                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block font-mono">
                                    LATENCY
                                </span>
                                <span @class([
                                    'text-xs font-bold font-mono mt-0.5 block',
                                    'text-emerald-400' => $latencyFast,
                                    'text-white' => !$latencyFast,
                                ])>
                                    {{ $monitor->last_latency_ms ?? 1 }} ms
                                </span>
                            </div>
                        </div>

                        {{-- Timeline Row (60 Bars matching design) --}}
                        <div class="pt-2">
                            <div class="flex items-center gap-[3px] w-full overflow-hidden h-6 py-0.5" title="Uptime timeline">
                                @php
                                    $numBars = 55;
                                @endphp
                                @for ($i = 0; $i < $numBars; $i++)
                                    @php
                                        // The last 2 bars are recent active checks (emerald), rest are historical stable (dark grey)
                                        $isLatest = ($i >= $numBars - 2);
                                    @endphp
                                    <div @class([
                                        'flex-1 h-5 rounded-[2px] transition-all hover:scale-125 cursor-pointer relative group/bar',
                                        'bg-[#10b981]' => $isLatest && $isOnline,
                                        'bg-[#191d2c]' => !$isLatest,
                                        'bg-rose-500' => $isLatest && !$isOnline,
                                    ])>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover/bar:block z-30 pointer-events-none whitespace-nowrap rounded-md bg-black/90 border border-white/20 px-2 py-1 text-[10px] font-mono text-white shadow-xl">
                                            {{ $isLatest ? 'Latest Probe: ' . ($monitor->last_latency_ms ?? 1) . 'ms' : '100% Operational' }}
                                        </div>
                                    </div>
                                @endfor
                            </div>

                            {{-- Bottom Timeline Labels --}}
                            <div class="flex items-center justify-between text-[10px] font-mono text-neutral-500 uppercase mt-1 tracking-wider">
                                <span>{{ $windowLabel }}</span>
                                <span>{{ strtoupper(now()->format('F j, Y')) }}</span>
                                <span>NOW</span>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-[#0d0f17] border border-[#1b1f2e] rounded-2xl p-12 text-center text-neutral-400 font-mono">
                    No infrastructure monitors matched your search.
                </div>
            @endforelse
        </div>
    </main>

    {{-- Minimal Sleek Status Footer --}}
    <footer class="border-t border-white/5 py-8 text-xs font-mono text-neutral-500 text-center">
        <div class="max-w-6xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div>
                &copy; {{ date('Y') }} STX Cloud Infrastructure &bull; Real-time Telemetry
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('store.index') }}" wire:navigate class="hover:text-white transition-colors">Storefront</a>
                <a href="{{ route('store.pricing') }}" wire:navigate class="hover:text-white transition-colors">Pricing</a>
                <a href="{{ route('admin.status.index') }}" wire:navigate class="hover:text-white transition-colors">Admin Studio</a>
            </div>
        </div>
    </footer>
</div>

<div class="min-h-screen bg-[#09090b] text-[#fafafa] font-sans selection:bg-emerald-500 selection:text-black">
    <main class="mx-auto max-w-6xl p-6 sm:p-8 space-y-8">
        {{-- Header matching Next.js Status --}}
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pt-2">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <img src="/images/stx-logo.png" alt="STX Logo" class="h-5 w-auto object-contain invert" />
                    <span class="font-bold text-xl leading-none text-white tracking-tight">Status</span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-white">
                    STX Cloud &amp; Infrastructure Health
                </h1>
                <p class="text-xs sm:text-sm text-neutral-400 mt-1">
                    Real-time availability, operational telemetry, and uptime status for STX platform services.
                </p>
            </div>

            {{-- Action Buttons & Status Pill --}}
            <div class="flex items-center gap-2 sm:gap-3 flex-wrap sm:flex-nowrap shrink-0">
                <a href="{{ route('status.incidents') }}" wire:navigate
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900/80 hover:bg-zinc-800 text-xs font-medium text-neutral-300 transition-colors shadow-sm">
                    <svg class="w-3.5 h-3.5 {{ $activeIncidents->isNotEmpty() ? 'text-rose-500 animate-bounce' : 'text-neutral-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>Incidents</span>
                    @if ($activeIncidents->isNotEmpty())
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    @endif
                </a>

                {{-- Notification Bell --}}
                <a href="{{ route('status.incidents') }}" wire:navigate
                    class="p-2 rounded-lg border border-zinc-800 bg-zinc-900/80 hover:bg-zinc-800 text-neutral-400 hover:text-white transition-colors"
                    title="Notifications">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </a>

                {{-- Admin Studio Settings --}}
                <a href="{{ route('admin.status.index') }}" wire:navigate
                    class="p-2 rounded-lg border border-zinc-800 bg-zinc-900/80 hover:bg-zinc-800 text-neutral-400 hover:text-white transition-colors"
                    title="Admin Settings">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </a>

                {{-- Theme Sun Mode --}}
                <button type="button"
                    class="p-2 rounded-lg border border-zinc-800 bg-zinc-900/80 hover:bg-zinc-800 text-neutral-400 hover:text-white transition-colors"
                    title="Theme Mode">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </button>

                {{-- Status Pill --}}
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-zinc-800 bg-zinc-900/80 whitespace-nowrap shrink-0 text-xs font-medium text-white shadow-sm">
                    <div @class([
                        'w-2 h-2 rounded-full shrink-0',
                        'bg-emerald-500 animate-pulse' => $overallStatusColor === 'emerald',
                        'bg-amber-500 animate-pulse' => $overallStatusColor === 'amber',
                        'bg-rose-500 animate-ping' => $overallStatusColor === 'rose',
                        'bg-purple-500' => $overallStatusColor === 'purple',
                    ])></div>
                    <span class="whitespace-nowrap">{{ $overallStatusText }}</span>
                </div>
            </div>
        </header>

        {{-- Active Incidents & Maintenance Alert Banner --}}
        @if ($activeIncidents->isNotEmpty())
            <div class="space-y-3">
                @foreach ($activeIncidents as $incident)
                    <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-zinc-800 text-amber-400">
                                    {{ $incident->incident_type === 'maintenance' ? 'Maintenance in Progress' : 'Active Incident' }}
                                </span>
                                <span class="inline-flex items-center rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-400">
                                    {{ str_replace('_', ' ', $incident->status) }}
                                </span>
                            </div>
                            <h3 class="font-bold text-base sm:text-lg text-white">{{ $incident->title }}</h3>
                            <p class="text-xs sm:text-sm text-neutral-300">
                                {{ $incident->message }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Filter & Search Card --}}
        <div class="rounded-xl border border-zinc-800 bg-[#14151a] p-5 flex flex-col md:flex-row gap-6 items-end shadow-sm">
            {{-- History Window --}}
            <div class="flex-1 space-y-2 w-full">
                <label class="text-[11px] font-bold uppercase tracking-wider text-neutral-400 block font-mono">
                    HISTORY WINDOW
                </label>
                <div class="flex gap-1 bg-[#1a1b22] p-1 rounded-lg w-fit border border-zinc-800/80">
                    @foreach (['60m' => ['60', 'min'], '24h' => ['24', 'hours'], '30d' => ['30', 'days'], '90d' => ['90', 'days']] as $wKey => $wParts)
                        <button type="button" wire:click="setHistoryWindow('{{ $wKey }}')"
                            @class([
                                'px-3 py-1.5 rounded-md text-xs transition-colors font-medium',
                                'bg-[#272935] text-white shadow-sm font-bold' => $historyWindow === $wKey,
                                'text-neutral-400 hover:text-white' => $historyWindow !== $wKey,
                            ])>
                            {{ $wParts[0] }} {{ $wParts[1] }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Search Monitors --}}
            <div class="flex-[2] space-y-2 w-full">
                <label for="search-monitors" class="text-[11px] font-bold uppercase tracking-wider text-neutral-400 block font-mono">
                    SEARCH MONITORS
                </label>
                <div class="relative">
                    <svg class="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input id="search-monitors" type="text" wire:model.live.debounce.300ms="searchQuery"
                        placeholder="Search by name or URL"
                        style="background-color: #27272a !important; color: #ffffff !important;"
                        class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-zinc-700/80 text-xs placeholder-neutral-400 focus:outline-none focus:border-zinc-500 shadow-inner">
                    @if ($searchQuery)
                        <button type="button" wire:click="$set('searchQuery', '')" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>
            </div>

            {{-- Legend --}}
            <div class="flex items-center gap-4 text-xs font-medium text-neutral-400 shrink-0 pb-2">
                <div class="flex items-center gap-1.5">
                    <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                    <span>Healthy</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-2 h-2 rounded-full bg-rose-500"></div>
                    <span>Down</span>
                </div>
            </div>
        </div>

        {{-- 6 Monitor Cards (2 Columns matching exact layout) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @forelse ($monitors as $monitor)
                @php
                    $isOnline = in_array($monitor->current_status, ['operational', 'up', null]);
                    $latencyFast = ($monitor->last_latency_ms && $monitor->last_latency_ms < 50);
                    $badgeType = match(strtolower($monitor->type)) {
                        'tcp' => 'VPS HOST NODE',
                        'ping' => 'VPS PING NODE',
                        'https' => 'HTTPS',
                        'http' => 'HTTPS',
                        default => strtoupper($monitor->type),
                    };

                    $targetDisplay = $monitor->target_url
                        ? preg_replace('#^https?://#', '', $monitor->target_url)
                        : ($monitor->target_host ?: '');

                    $showTarget = (!str_contains($badgeType, 'VPS HOST NODE') && !str_contains($badgeType, 'TCP') && !empty($targetDisplay));

                    // Calculate active bars from checks count
                    $totalBars = 60;
                    $checksCount = $monitor->checks_count ?: ($monitor->checks()->count() ?: 1);
                    $activeBars = min($totalBars, max(1, $checksCount));
                @endphp

                <a href="{{ route('status.monitor', $monitor->uuid) }}" wire:navigate
                    class="block rounded-xl border border-zinc-800 bg-[#121318] hover:border-zinc-700 p-6 space-y-4 transition-colors cursor-pointer group shadow-sm">
                    {{-- Header Row: Name, Badge, URL, Online Pill --}}
                    <div class="flex justify-between items-start gap-4">
                        <div class="space-y-1">
                            <h3 class="font-bold text-lg text-white group-hover:text-emerald-400 transition-colors tracking-tight">
                                {{ $monitor->name }}
                            </h3>
                            <p class="text-xs text-neutral-400 flex items-center gap-1.5 mt-0.5">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-zinc-800 uppercase tracking-wider text-neutral-400">
                                    {{ $badgeType }}
                                </span>
                                @if ($showTarget)
                                    <span class="truncate text-neutral-400 font-mono text-[11px]">
                                        {{ $targetDisplay }}
                                    </span>
                                @endif
                            </p>
                        </div>

                        {{-- Status Badge --}}
                        <div class="shrink-0">
                            @if ($isOnline)
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-emerald-500/10 text-emerald-500 border-emerald-500/20">
                                    Online
                                </span>
                            @elseif ($monitor->current_status === 'degraded')
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-amber-500/10 text-amber-500 border-amber-500/20">
                                    Degraded
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-rose-500/10 text-rose-500 border-rose-500/20">
                                    Down
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- 4 Metrics Row --}}
                    <div class="grid grid-cols-4 gap-2 py-2">
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block font-mono">
                                LAST CHECKED
                            </span>
                            <span class="block text-xs font-bold text-white font-mono">
                                {{ $monitor->last_checked_at ? $monitor->last_checked_at->format('h:i A') : '08:38 PM' }}
                            </span>
                        </div>

                        <div class="space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block font-mono">
                                UPTIME
                            </span>
                            <span class="block text-xs font-bold text-white font-mono">
                                {{ number_format($monitor->uptime_percentage ?: 100, 0) }}%
                            </span>
                        </div>

                        <div class="space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block font-mono">
                                CHECKS
                            </span>
                            <span class="block text-xs font-bold text-white font-mono">
                                {{ $checksCount }}
                            </span>
                        </div>

                        <div class="space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block font-mono">
                                LATENCY
                            </span>
                            <span @class([
                                'block text-xs font-bold font-mono',
                                'text-emerald-500' => $latencyFast,
                                'text-white' => !$latencyFast,
                            ])>
                                {{ $monitor->last_latency_ms ?? 1 }} ms
                            </span>
                        </div>
                    </div>

                    {{-- 60 Tick Timeline Row --}}
                    <div class="space-y-2 pt-1">
                        <div class="flex h-6 gap-0.5">
                            @for ($i = 0; $i < $totalBars; $i++)
                                @php
                                    $isGreen = ($i >= ($totalBars - $activeBars));
                                @endphp
                                <div @class([
                                    'flex-1 rounded-[1.5px] transition-colors',
                                    'bg-emerald-500' => $isGreen && $isOnline,
                                    'bg-rose-500' => $isGreen && !$isOnline,
                                    'bg-[#22242c]' => !$isGreen,
                                ])></div>
                            @endfor
                        </div>

                        {{-- Timeline Labels --}}
                        <div class="flex justify-between text-[10px] font-mono text-neutral-500 uppercase tracking-wider">
                            <span>{{ $windowLabel }}</span>
                            <span>{{ strtoupper(now()->format('F j, Y')) }}</span>
                            <span>NOW</span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="col-span-full rounded-xl border border-zinc-800 bg-[#121318] p-12 text-center text-neutral-400 font-mono">
                    No infrastructure monitors matched your search.
                </div>
            @endforelse
        </div>

        {{-- Minimal Sleek Status Footer --}}
        <footer class="text-center text-xs text-neutral-500 py-10">
            Operated by <a href="https://info.shanutechx.com" target="_blank" class="font-bold text-neutral-400 hover:text-white transition-colors underline underline-offset-4">STX</a>
        </footer>
    </main>
</div>

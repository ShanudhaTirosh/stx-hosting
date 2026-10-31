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
                <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">Incident History &amp; Notices</h1>
                <p class="text-xs font-mono text-neutral-400">
                    Transparent post-mortem timeline, root cause investigations, and planned maintenance logs.
                </p>
            </div>

            {{-- Tabs --}}
            <div class="flex items-center rounded-xl p-1 border border-white/10 bg-white/5 text-xs font-mono shrink-0">
                @foreach (['all' => 'All Notices', 'active' => 'Active Issues', 'maintenance' => 'Maintenance', 'resolved' => 'Resolved'] as $tKey => $tLabel)
                    <button type="button" wire:click="setTab('{{ $tKey }}')"
                        @class([
                            'px-3 py-1.5 rounded-lg font-bold transition-colors',
                            'bg-purple-600 text-white' => $activeTab === $tKey,
                            'text-neutral-400 hover:text-white' => $activeTab !== $tKey,
                        ])>
                        {{ $tLabel }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Incidents Timeline --}}
        <div class="space-y-6">
            @forelse ($incidents as $inc)
                <div class="glass-card rounded-2xl p-6 sm:p-8 border border-white/10 space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-white/10 pb-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1.5">
                                @if ($inc->incident_type === 'maintenance')
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider bg-purple-500/10 border border-purple-500/20 text-purple-300">
                                        Scheduled Maintenance
                                    </span>
                                @else
                                    <span @class([
                                        'px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider',
                                        'bg-rose-500/10 border border-rose-500/20 text-rose-300' => $inc->impact_level === 'critical',
                                        'bg-amber-500/10 border border-amber-500/20 text-amber-300' => $inc->impact_level === 'major',
                                        'bg-blue-500/10 border border-blue-500/20 text-blue-300' => in_array($inc->impact_level, ['minor', 'none', null]),
                                    ])>
                                        {{ ucfirst($inc->impact_level ?? 'minor') }} Outage
                                    </span>
                                @endif

                                <span @class([
                                    'px-2 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase',
                                    'bg-emerald-500/10 text-emerald-400' => $inc->status === 'resolved',
                                    'bg-amber-500/10 text-amber-400' => in_array($inc->status, ['investigating', 'identified', 'monitoring', 'in_progress']),
                                    'bg-purple-500/10 text-purple-400' => $inc->status === 'scheduled',
                                ])>
                                    {{ ucfirst(str_replace('_', ' ', $inc->status)) }}
                                </span>
                            </div>

                            <h2 class="text-xl font-bold text-white">{{ $inc->title }}</h2>
                        </div>

                        <div class="text-[11px] font-mono text-neutral-400 text-right">
                            @if ($inc->scheduled_start_at && $inc->scheduled_end_at)
                                <div>Window: {{ $inc->scheduled_start_at->format('M j, H:i') }} - {{ $inc->scheduled_end_at->format('H:i T') }}</div>
                            @else
                                <div>Started: {{ $inc->created_at->format('M j, Y H:i T') }}</div>
                                @if ($inc->resolved_at)
                                    <div class="text-emerald-400">Resolved: {{ $inc->resolved_at->format('M j, H:i T') }}</div>
                                @endif
                            @endif
                        </div>
                    </div>

                    {{-- Description --}}
                    @if ($inc->description)
                        <div class="text-xs text-neutral-300 leading-relaxed font-sans">
                            {{ $inc->description }}
                        </div>
                    @endif

                    {{-- Multi-Stage Updates Thread --}}
                    @if ($inc->updates->isNotEmpty())
                        <div class="space-y-4 pt-2">
                            <h4 class="text-xs font-mono font-bold text-neutral-400 uppercase tracking-wider">Investigation &amp; Resolution Updates</h4>
                            <div class="relative pl-6 space-y-4 border-l border-white/10">
                                @foreach ($inc->updates as $upd)
                                    <div class="relative">
                                        <div class="absolute -left-[31px] top-1 size-2.5 rounded-full bg-purple-500 border-2 border-[#090b22]"></div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-white uppercase font-mono">{{ ucfirst($upd->status) }}</span>
                                            <span class="text-[10px] font-mono text-neutral-500">&bull; {{ $upd->created_at->format('M j, H:i T') }}</span>
                                        </div>
                                        <p class="text-xs text-neutral-300 mt-1 leading-relaxed font-sans">
                                            {{ $upd->message }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="glass-card rounded-2xl p-12 text-center text-neutral-400">
                    No incidents recorded for this filter. All cloud systems operate normally.
                </div>
            @endforelse
        </div>
    </main>

    <x-storefront.footer />
</div>


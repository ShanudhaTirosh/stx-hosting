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
                <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-white">Incident History &amp; Notices</h1>
                <p class="text-xs sm:text-sm text-neutral-400 mt-1">
                    Transparent post-mortem timeline, root cause investigations, and planned maintenance logs.
                </p>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('status.public') }}" wire:navigate
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-zinc-800 bg-[#14151a] hover:bg-zinc-800 text-xs font-medium text-neutral-300 transition-colors shadow-sm">
                    <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Back to Status</span>
                </a>

                {{-- Tabs --}}
                <div class="flex items-center rounded-xl p-1 border border-zinc-800 bg-[#1a1b22] text-xs font-mono">
                    @foreach (['all' => 'All Notices', 'active' => 'Active Issues', 'maintenance' => 'Maintenance', 'resolved' => 'Resolved'] as $tKey => $tLabel)
                        <button type="button" wire:click="setTab('{{ $tKey }}')"
                            @class([
                                'px-3 py-1.5 rounded-lg font-bold transition-colors',
                                'bg-[#272935] text-white shadow-sm' => $activeTab === $tKey,
                                'text-neutral-400 hover:text-white' => $activeTab !== $tKey,
                            ])>
                            {{ $tLabel }}
                        </button>
                    @endforeach
                </div>
            </div>
        </header>

        {{-- Incidents List --}}
        <div class="space-y-4">
            @forelse ($incidents as $inc)
                <div class="rounded-xl border border-zinc-800 bg-[#121318] p-6 space-y-4 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-zinc-800/80 pb-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                @if ($inc->incident_type === 'maintenance')
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider bg-purple-500/10 border border-purple-500/20 text-purple-400">
                                        Scheduled Maintenance
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider bg-amber-500/10 border border-amber-500/20 text-amber-400">
                                        {{ str_replace('_', ' ', $inc->status) }}
                                    </span>
                                @endif
                                <span class="text-xs text-neutral-500 font-mono">
                                    {{ $inc->created_at->format('M j, Y • h:i A') }}
                                </span>
                            </div>
                            <h2 class="text-lg font-bold text-white tracking-tight">{{ $inc->title }}</h2>
                        </div>

                        <span @class([
                            'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold',
                            'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' => $inc->status === 'resolved',
                            'bg-amber-500/10 text-amber-400 border border-amber-500/20' => $inc->status !== 'resolved',
                        ])>
                            <span class="w-1.5 h-1.5 rounded-full {{ $inc->status === 'resolved' ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                            {{ ucfirst(str_replace('_', ' ', $inc->status)) }}
                        </span>
                    </div>

                    <p class="text-xs sm:text-sm text-neutral-300 leading-relaxed font-sans">
                        {{ $inc->message }}
                    </p>

                    @if ($inc->updates && $inc->updates->isNotEmpty())
                        <div class="pt-3 border-t border-zinc-800/60 space-y-3">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block font-mono">
                                Incident Updates &amp; Post-Mortem Actions
                            </span>
                            <div class="space-y-2 border-l-2 border-zinc-800 pl-4">
                                @foreach ($inc->updates as $update)
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-bold uppercase font-mono text-neutral-400">
                                                {{ ucfirst($update->status) }}
                                            </span>
                                            <span class="text-[10px] text-neutral-600 font-mono">
                                                {{ $update->created_at->format('h:i A') }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-neutral-300">
                                            {{ $update->message }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-zinc-800 bg-[#121318] p-12 text-center text-neutral-400 font-mono">
                    No active or historical incidents recorded. All STX systems operating normally.
                </div>
            @endforelse
        </div>

        {{-- Minimal Sleek Status Footer --}}
        <footer class="text-center text-xs text-neutral-500 py-10">
            Operated by <a href="https://info.shanutechx.com" target="_blank" class="font-bold text-neutral-400 hover:text-white transition-colors underline underline-offset-4">STX</a>
        </footer>
    </main>
</div>

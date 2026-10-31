<div class="min-h-screen bg-[#030014] text-white flex flex-col justify-between relative overflow-hidden">
    {{-- Background Grid & Glow --}}
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0"></div>
    <div class="fixed top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-purple-600/15 rounded-full blur-[140px] pointer-events-none -z-10"></div>

    <x-storefront.navbar current="faqs" />

    <main class="flex-1 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-24 relative z-10 w-full space-y-12">
        {{-- Hero Header --}}
        <div class="text-center space-y-4 max-w-3xl mx-auto pt-6">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-300 text-xs font-mono font-bold tracking-widest uppercase">
                <span class="size-1.5 rounded-full bg-purple-400 animate-pulse"></span>
                KNOWLEDGE BASE &amp; ANSWERS
            </div>

            <h1 class="text-4xl md:text-6xl font-black tracking-tight text-white leading-tight">
                Frequently Asked <span class="text-gradient">Questions</span>
            </h1>

            <p class="text-base md:text-lg text-neutral-300 leading-relaxed font-sans">
                Find answers regarding Cloud VPS provisioning, automated build pipelines, process supervisors, and payment methods.
            </p>

            {{-- Search Bar --}}
            <div class="max-w-md mx-auto pt-4">
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="searchQuery" placeholder="Search questions or keywords..."
                        class="w-full bg-white/5 border border-white/10 rounded-2xl pl-10 pr-4 py-3 text-xs text-white placeholder-neutral-500 focus:outline-none focus:border-purple-500 transition-colors shadow-lg">
                    <svg class="size-4 text-neutral-500 absolute left-3.5 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>
        </div>

        {{-- Category Tabs --}}
        <div class="flex items-center justify-center gap-2 overflow-x-auto pb-2 scrollbar-none text-xs font-mono">
            @php
                $cats = [
                    'all' => 'All Questions',
                    'vps' => 'Cloud VPS',
                    'app' => 'App Hosting',
                    'bot' => '24/7 Bots',
                    'db' => 'Databases',
                    'billing' => 'Billing & SLA',
                ];
            @endphp
            @foreach ($cats as $key => $label)
                <button type="button" wire:click="setCategory('{{ $key }}')"
                    @class([
                        'px-4 py-2 rounded-xl whitespace-nowrap transition-all border font-bold',
                        'bg-purple-600/20 border-purple-500/50 text-purple-300 shadow-[0_0_15px_rgba(168,85,247,0.2)]' => $activeCategory === $key,
                        'bg-white/5 border-white/10 text-neutral-400 hover:text-white hover:border-white/20' => $activeCategory !== $key,
                    ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- FAQ Accordion List --}}
        <div class="space-y-4 max-w-4xl mx-auto">
            @forelse ($faqs as $idx => $faq)
                <div class="glass-card rounded-2xl border border-white/10 transition-all overflow-hidden">
                    <button type="button" wire:click="toggleFaq({{ $idx }})"
                        class="w-full p-5 text-left flex items-center justify-between gap-4 font-bold text-sm text-white hover:text-purple-300 transition-colors">
                        <span>{{ $faq['q'] }}</span>
                        <div class="size-6 rounded-lg bg-white/5 flex items-center justify-center text-neutral-400 shrink-0">
                            @if ($openFaqIndex === $idx)
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                            @else
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            @endif
                        </div>
                    </button>

                    @if ($openFaqIndex === $idx)
                        <div class="px-5 pb-5 pt-1 text-xs text-neutral-400 leading-relaxed border-t border-white/5 font-sans">
                            {{ $faq['a'] }}
                        </div>
                    @endif
                </div>
            @empty
                <div class="glass-card rounded-2xl p-12 text-center text-neutral-400">
                    No questions found matching your search. Try another keyword or contact live support.
                </div>
            @endforelse
        </div>
    </main>

    <x-storefront.footer />
</div>


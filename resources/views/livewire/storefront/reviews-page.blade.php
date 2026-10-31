<div class="min-h-screen bg-[#030014] text-white flex flex-col justify-between relative overflow-hidden">
    {{-- Background Grid & Glow --}}
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0"></div>
    <div class="fixed top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-purple-600/15 rounded-full blur-[140px] pointer-events-none -z-10"></div>

    <x-storefront.navbar current="reviews" />

    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-24 relative z-10 w-full space-y-12">
        {{-- Hero Header --}}
        <div class="text-center space-y-4 max-w-3xl mx-auto pt-6">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-300 text-xs font-mono font-bold tracking-widest uppercase">
                <span class="size-1.5 rounded-full bg-purple-400 animate-pulse"></span>
                VERIFIED PERFORMANCE REVIEWS
            </div>

            <h1 class="text-4xl md:text-6xl font-black tracking-tight text-white leading-tight">
                Trusted by <span class="text-gradient">Engineers &amp; Founders</span>
            </h1>

            <p class="text-base md:text-lg text-neutral-300 leading-relaxed font-sans">
                Read real benchmark experiences and performance feedback from developers deploying production workloads on STX Cloud.
            </p>

            <div class="pt-4 flex justify-center">
                <button type="button" wire:click="openModal"
                    class="px-6 py-3 rounded-xl bg-gradient-to-r from-purple-600 to-cyan-500 hover:from-purple-500 hover:to-cyan-400 text-white font-bold text-xs shadow-glow-violet transition-all flex items-center gap-2">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>Write a Verified Review</span>
                </button>
            </div>
        </div>

        {{-- Filter Categories --}}
        <div class="flex items-center justify-center gap-2 overflow-x-auto pb-2 scrollbar-none text-xs font-mono">
            @php
                $cats = [
                    'all' => 'All Categories',
                    'vps' => 'Cloud VPS',
                    'app_hosting' => 'App Hosting',
                    'bot_hosting' => 'Bot Hosting',
                    'database' => 'Managed Databases',
                ];
            @endphp
            @foreach ($cats as $key => $label)
                <button type="button" wire:click="setCategory('{{ $key }}')"
                    @class([
                        'px-4 py-2 rounded-xl whitespace-nowrap transition-all border font-bold',
                        'bg-purple-600/20 border-purple-500/50 text-purple-300 shadow-[0_0_15px_rgba(168,85,247,0.2)]' => $selectedCategory === $key,
                        'bg-white/5 border-white/10 text-neutral-400 hover:text-white hover:border-white/20' => $selectedCategory !== $key,
                    ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Reviews Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($reviews as $rev)
                <div class="glass-card rounded-2xl p-6 border border-white/10 hover:border-purple-500/40 transition-all flex flex-col justify-between space-y-4">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1 text-amber-400">
                                @for ($i = 0; $i < $rev['rating']; $i++)
                                    <svg class="size-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                @endfor
                            </div>
                            @if ($rev['verified'])
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-500/10 border border-emerald-500/20 text-emerald-400">
                                    <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Verified Buyer
                                </span>
                            @endif
                        </div>

                        <p class="text-xs text-neutral-300 leading-relaxed font-sans">
                            &ldquo;{{ $rev['comment'] }}&rdquo;
                        </p>
                    </div>

                    <div class="pt-4 border-t border-white/[0.08] flex items-center justify-between">
                        <div>
                            <div class="text-sm font-bold text-white">{{ $rev['name'] }}</div>
                            <div class="text-[11px] text-neutral-400">{{ $rev['role'] }}</div>
                        </div>
                        <div class="text-[10px] font-mono text-neutral-500">{{ $rev['date'] }}</div>
                    </div>
                </div>
            @empty
                <div class="col-span-full glass-card rounded-2xl p-12 text-center text-neutral-400">
                    No reviews found for this category yet.
                </div>
            @endforelse
        </div>
    </main>

    {{-- Review Submission Modal --}}
    @if ($modalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
            <div class="bg-[#090b22] border border-white/10 rounded-3xl p-6 sm:p-8 max-w-lg w-full space-y-6 shadow-2xl relative">
                <button type="button" wire:click="closeModal" class="absolute top-5 right-5 text-neutral-400 hover:text-white">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                <div>
                    <h3 class="text-xl font-bold text-white">Submit a Verified Review</h3>
                    <p class="text-xs text-neutral-400 mt-1">Share your experience deploying infrastructure with STX.</p>
                </div>

                @if ($successMessage)
                    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs">
                        {{ $successMessage }}
                    </div>
                @else
                    <form wire:submit.prevent="submitReview" class="space-y-4">
                        <div class="space-y-1">
                            <label class="text-xs font-mono text-neutral-300">Your Full Name *</label>
                            <input type="text" wire:model="authorName" placeholder="Kavindu Perera"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-xs text-white placeholder-neutral-500 focus:outline-none focus:border-purple-500">
                            @error('authorName') <span class="text-xs text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-mono text-neutral-300">Your Role / Company *</label>
                            <input type="text" wire:model="authorRole" placeholder="DevOps Engineer @ Company"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-xs text-white placeholder-neutral-500 focus:outline-none focus:border-purple-500">
                            @error('authorRole') <span class="text-xs text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xs font-mono text-neutral-300">Product Category</label>
                                <select wire:model="productCategory" class="w-full bg-[#050614] border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-purple-500">
                                    <option value="vps">Cloud VPS</option>
                                    <option value="app_hosting">App Hosting</option>
                                    <option value="bot_hosting">Bot Hosting</option>
                                    <option value="database">Managed Databases</option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label class="text-xs font-mono text-neutral-300">Rating (1 to 5 Stars)</label>
                                <select wire:model="rating" class="w-full bg-[#050614] border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-purple-500">
                                    <option value="5">5 Stars (Excellent)</option>
                                    <option value="4">4 Stars (Very Good)</option>
                                    <option value="3">3 Stars (Good)</option>
                                    <option value="2">2 Stars (Fair)</option>
                                    <option value="1">1 Star (Poor)</option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-mono text-neutral-300">Review Comments *</label>
                            <textarea wire:model="comment" rows="4" placeholder="Detail your experience with performance, latency, uptime, and customer support..."
                                class="w-full bg-white/5 border border-white/10 rounded-xl p-3 text-xs text-white placeholder-neutral-500 focus:outline-none focus:border-purple-500"></textarea>
                            @error('comment') <span class="text-xs text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="pt-2 flex justify-end gap-2">
                            <button type="button" wire:click="closeModal" class="px-4 py-2 rounded-xl bg-white/5 text-neutral-400 hover:text-white text-xs font-bold">
                                Cancel
                            </button>
                            <button type="submit" class="px-5 py-2 rounded-xl bg-gradient-to-r from-purple-600 to-cyan-500 text-white text-xs font-bold shadow-glow-violet">
                                Submit Review
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <x-storefront.footer />
</div>


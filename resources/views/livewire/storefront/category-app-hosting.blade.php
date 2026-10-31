<div class="min-h-screen bg-[#030014] text-white flex flex-col justify-between relative overflow-hidden">
    {{-- Background Grid & Glow --}}
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0"></div>
    <div class="fixed top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-indigo-600/15 rounded-full blur-[140px] pointer-events-none -z-10"></div>

    <x-storefront.navbar current="apps" :currency="$currency" />

    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-24 relative z-10 w-full space-y-16">
        {{-- Hero Header --}}
        <div class="text-center space-y-4 max-w-3xl mx-auto pt-6">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-mono font-bold tracking-widest uppercase">
                <span class="size-1.5 rounded-full bg-indigo-400 animate-pulse"></span>
                INSTANT CODE DEPLOYMENT ENGINE
            </div>

            <h1 class="text-4xl md:text-6xl font-black tracking-tight text-white leading-tight">
                Deploy Web Apps from <span class="text-gradient">Git or ZIP Archives</span>
            </h1>

            <p class="text-base md:text-lg text-neutral-300 leading-relaxed font-sans">
                Connect your GitHub repository or upload a ZIP archive. Our automated build engine detects runtime dependencies, compiles your source code, and provisions custom SSL domains in seconds.
            </p>

            {{-- Currency & Duration Controls --}}
            <div class="flex flex-wrap items-center justify-center gap-4 pt-4">
                {{-- Currency Switcher --}}
                <div class="flex items-center rounded-xl p-1 border border-white/10 bg-white/5 text-xs font-mono">
                    <button type="button" wire:click="selectCurrency('USD')"
                        @class(['px-3 py-1.5 rounded-lg font-bold transition-colors', 'bg-indigo-600 text-white' => $currency === 'USD', 'text-neutral-400 hover:text-white' => $currency !== 'USD'])>
                        USD ($)
                    </button>
                    <button type="button" wire:click="selectCurrency('LKR')"
                        @class(['px-3 py-1.5 rounded-lg font-bold transition-colors', 'bg-indigo-600 text-white' => $currency === 'LKR', 'text-neutral-400 hover:text-white' => $currency !== 'LKR'])>
                        LKR (Rs)
                    </button>
                </div>

                {{-- Billing Switcher --}}
                <div class="flex items-center rounded-xl p-1 border border-white/10 bg-white/5 text-xs font-mono">
                    <button type="button" wire:click="selectBillingPeriod('monthly')"
                        @class(['px-3 py-1.5 rounded-lg font-bold transition-colors', 'bg-indigo-600 text-white' => $billingPeriod === 'monthly', 'text-neutral-400 hover:text-white' => $billingPeriod !== 'monthly'])>
                        Monthly
                    </button>
                    <button type="button" wire:click="selectBillingPeriod('yearly')"
                        @class(['px-3 py-1.5 rounded-lg font-bold transition-colors flex items-center gap-1', 'bg-indigo-600 text-white' => $billingPeriod === 'yearly', 'text-neutral-400 hover:text-white' => $billingPeriod !== 'yearly'])>
                        Yearly <span class="text-[10px] text-emerald-400 font-extrabold">-20%</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Supported Runtimes Grid --}}
        <div class="border-t border-white/[0.06] pt-12">
            <div class="text-center space-y-2 mb-10">
                <span class="text-xs font-mono uppercase font-bold text-indigo-400 tracking-widest">Multi-Language &amp; Runtime Support</span>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-white">Supported Application Frameworks</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <div class="glass-card rounded-2xl p-6 border border-white/10 hover:border-indigo-500/40 transition-all flex items-start gap-4">
                    <div class="p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-base text-white">Node.js Runtimes</h3>
                        <p class="text-xs text-neutral-400 mt-1">Express, Next.js, Remix, NestJS, Nuxt & Fastify</p>
                    </div>
                </div>

                <div class="glass-card rounded-2xl p-6 border border-white/10 hover:border-indigo-500/40 transition-all flex items-start gap-4">
                    <div class="p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-base text-white">Python Engines</h3>
                        <p class="text-xs text-neutral-400 mt-1">Django, FastAPI, Flask with Gunicorn & Uvicorn</p>
                    </div>
                </div>

                <div class="glass-card rounded-2xl p-6 border border-white/10 hover:border-indigo-500/40 transition-all flex items-start gap-4">
                    <div class="p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-base text-white">Go &amp; Rust Binaries</h3>
                        <p class="text-xs text-neutral-400 mt-1">High-speed compiled microservices with low memory footprint</p>
                    </div>
                </div>

                <div class="glass-card rounded-2xl p-6 border border-white/10 hover:border-indigo-500/40 transition-all flex items-start gap-4">
                    <div class="p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-base text-white">Docker &amp; Compose</h3>
                        <p class="text-xs text-neutral-400 mt-1">Custom Dockerfile & multi-container architecture stacks</p>
                    </div>
                </div>

                <div class="glass-card rounded-2xl p-6 border border-white/10 hover:border-indigo-500/40 transition-all flex items-start gap-4">
                    <div class="p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-base text-white">Static Web Frontends</h3>
                        <p class="text-xs text-neutral-400 mt-1">React, Vue, Vite, Astro & HTML5 with global CDN edge caching</p>
                    </div>
                </div>

                <div class="glass-card rounded-2xl p-6 border border-white/10 hover:border-indigo-500/40 transition-all flex items-start gap-4">
                    <div class="p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-base text-white">ZIP Package Uploads</h3>
                        <p class="text-xs text-neutral-400 mt-1">Direct drag & drop deployment without requiring a public git repo</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- App Hosting Plans Grid --}}
        <div class="border-t border-white/[0.06] pt-12">
            <div class="text-center space-y-2 mb-10">
                <span class="text-xs font-mono uppercase font-bold text-indigo-400 tracking-widest">Scalable Runtime Resources</span>
                <h2 class="text-3xl font-extrabold text-white">App Hosting Plans &amp; Pricing</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse ($products as $prod)
                    @php
                        $primaryVariant = $prod->variants->first();
                        $tier = $primaryVariant?->pricingTiers->first();
                        $priceMonthlyUsd = max(3, (float) ($tier?->price_monthly_usd ?: 6));
                        $priceYearlyUsd = max(30, (float) ($tier?->price_yearly_usd ?: $priceMonthlyUsd * 10));
                        $priceMonthlyLkr = ($tier && (float)$tier->price_monthly_lkr > 0) ? (float)$tier->price_monthly_lkr : round($priceMonthlyUsd * 305, 0);
                        $priceYearlyLkr = ($tier && (float)$tier->price_yearly_lkr > 0) ? (float)$tier->price_yearly_lkr : round($priceYearlyUsd * 305, 0);

                        $displayPrice = ($currency === 'USD')
                            ? ($billingPeriod === 'monthly' ? '$' . number_format($priceMonthlyUsd, 2) : '$' . number_format($priceYearlyUsd / 12, 2))
                            : ($billingPeriod === 'monthly' ? 'Rs ' . number_format($priceMonthlyLkr, 0) : 'Rs ' . number_format($priceYearlyLkr / 12, 0));
                    @endphp

                    <div @class([
                        'glass-card rounded-2xl p-6 flex flex-col justify-between transition-all relative border border-white/10 hover:border-indigo-500/40',
                        'border-indigo-500/50 shadow-[0_0_40px_-10px_rgba(99,102,241,0.3)]' => $prod->is_popular,
                    ])>
                        @if ($prod->is_popular)
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-lg">
                                Most Popular
                            </div>
                        @endif

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs uppercase font-mono tracking-wider text-indigo-400">App Runtime</span>
                                <span class="text-[11px] font-mono text-neutral-400">Instant Setup</span>
                            </div>

                            <h3 class="text-xl font-bold text-white mb-2">{{ $prod->name }}</h3>
                            <p class="text-xs text-neutral-400 mb-6 leading-relaxed">
                                {{ $prod->description ?: 'Automated container build pipeline with persistent storage and custom domain routing.' }}
                            </p>

                            <div class="flex items-baseline gap-1 mb-6">
                                <span class="text-3xl font-black text-white">{{ $displayPrice }}</span>
                                <span class="text-xs font-mono text-neutral-400">/month</span>
                            </div>

                            <ul class="space-y-3 pt-6 border-t border-white/[0.08] text-xs text-neutral-300">
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Git Webhook Auto-Deploy</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>ZIP Package Upload Support</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Custom Domain Auto SSL (HTTPS)</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Process Auto-Restart & Live Logs</span>
                                </li>
                            </ul>
                        </div>

                        <div class="pt-8">
                            <button type="button"
                                wire:click="orderPlan('{{ $primaryVariant?->uuid }}', '{{ $tier?->uuid }}')"
                                class="w-full py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold text-xs shadow-glow-violet transition-all flex items-center justify-center gap-2">
                                Deploy {{ $prod->name }}
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full glass-card rounded-2xl p-12 text-center text-neutral-400">
                        No App Hosting plans found in database. Contact support for custom deployment tiers.
                    </div>
                @endforelse
            </div>
        </div>
    </main>

    <x-storefront.footer />
</div>


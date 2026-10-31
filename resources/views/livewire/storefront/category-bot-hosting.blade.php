<div class="min-h-screen bg-[#030014] text-white flex flex-col justify-between relative overflow-hidden">
    {{-- Background Grid & Glow --}}
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0"></div>
    <div class="fixed top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-pink-600/15 rounded-full blur-[140px] pointer-events-none -z-10"></div>

    <x-storefront.navbar current="bots" :currency="$currency" />

    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-24 relative z-10 w-full space-y-16">
        {{-- Hero Header --}}
        <div class="text-center space-y-4 max-w-3xl mx-auto pt-6">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-pink-500/10 border border-pink-500/20 text-pink-300 text-xs font-mono font-bold tracking-widest uppercase">
                <span class="size-1.5 rounded-full bg-pink-400 animate-pulse"></span>
                ALWAYS-ONLINE BOT RUNTIME ENGINE
            </div>

            <h1 class="text-4xl md:text-6xl font-black tracking-tight text-white leading-tight">
                High-Uptime <span class="bg-clip-text text-transparent bg-gradient-to-r from-pink-400 via-purple-300 to-indigo-500">24/7 Bot Hosting</span>
            </h1>

            <p class="text-base md:text-lg text-neutral-300 leading-relaxed font-sans">
                Host your Discord, Telegram, and WhatsApp bots with zero downtime. Built-in process supervisor automatically recovers crashed bot processes in milliseconds.
            </p>

            {{-- Currency & Duration Controls --}}
            <div class="flex flex-wrap items-center justify-center gap-4 pt-4">
                {{-- Currency Switcher --}}
                <div class="flex items-center rounded-xl p-1 border border-white/10 bg-white/5 text-xs font-mono">
                    <button type="button" wire:click="selectCurrency('USD')"
                        @class(['px-3 py-1.5 rounded-lg font-bold transition-colors', 'bg-pink-600 text-white' => $currency === 'USD', 'text-neutral-400 hover:text-white' => $currency !== 'USD'])>
                        USD ($)
                    </button>
                    <button type="button" wire:click="selectCurrency('LKR')"
                        @class(['px-3 py-1.5 rounded-lg font-bold transition-colors', 'bg-pink-600 text-white' => $currency === 'LKR', 'text-neutral-400 hover:text-white' => $currency !== 'LKR'])>
                        LKR (Rs)
                    </button>
                </div>

                {{-- Billing Switcher --}}
                <div class="flex items-center rounded-xl p-1 border border-white/10 bg-white/5 text-xs font-mono">
                    <button type="button" wire:click="selectBillingPeriod('monthly')"
                        @class(['px-3 py-1.5 rounded-lg font-bold transition-colors', 'bg-pink-600 text-white' => $billingPeriod === 'monthly', 'text-neutral-400 hover:text-white' => $billingPeriod !== 'monthly'])>
                        Monthly
                    </button>
                    <button type="button" wire:click="selectBillingPeriod('yearly')"
                        @class(['px-3 py-1.5 rounded-lg font-bold transition-colors flex items-center gap-1', 'bg-pink-600 text-white' => $billingPeriod === 'yearly', 'text-neutral-400 hover:text-white' => $billingPeriod !== 'yearly'])>
                        Yearly <span class="text-[10px] text-emerald-400 font-extrabold">-20%</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Supported Bot Types Grid --}}
        <div class="border-t border-white/[0.06] pt-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="glass-card rounded-2xl p-8 space-y-4 border border-white/10 hover:border-pink-500/40 transition-all">
                    <div class="size-12 rounded-xl bg-pink-500/10 border border-pink-500/20 text-pink-400 flex items-center justify-center">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="font-extrabold text-lg text-white">Discord.js &amp; Python Bots</h3>
                    <p class="text-xs text-neutral-400 leading-relaxed">
                        Optimized support for Discord.js v14, Eris, discord.py, Nextcord, and Lavalink audio streaming nodes with high UDP throughput.
                    </p>
                </div>

                <div class="glass-card rounded-2xl p-8 space-y-4 border border-white/10 hover:border-pink-500/40 transition-all">
                    <div class="size-12 rounded-xl bg-pink-500/10 border border-pink-500/20 text-pink-400 flex items-center justify-center">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </div>
                    <h3 class="font-extrabold text-lg text-white">WhatsApp Gateways (Baileys)</h3>
                    <p class="text-xs text-neutral-400 leading-relaxed">
                        Keep your Baileys and WhatsApp Web session authentication persistent across container restarts with dedicated non-volatile storage.
                    </p>
                </div>

                <div class="glass-card rounded-2xl p-8 space-y-4 border border-white/10 hover:border-pink-500/40 transition-all">
                    <div class="size-12 rounded-xl bg-pink-500/10 border border-pink-500/20 text-pink-400 flex items-center justify-center">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </div>
                    <h3 class="font-extrabold text-lg text-white">Telegram &amp; Custom Daemons</h3>
                    <p class="text-xs text-neutral-400 leading-relaxed">
                        Telebot, python-telegram-bot, GramJS, and custom webhook handlers running with continuous monitoring and zero execution limits.
                    </p>
                </div>
            </div>
        </div>

        {{-- Bot Pricing Grid --}}
        <div class="border-t border-white/[0.06] pt-12">
            <div class="text-center space-y-2 mb-10">
                <span class="text-xs font-mono uppercase font-bold text-pink-400 tracking-widest">Optimized Bot Specs</span>
                <h2 class="text-3xl font-extrabold text-white">Bot Hosting Plans &amp; Runtimes</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse ($products as $prod)
                    @php
                        $primaryVariant = $prod->variants->first();
                        $tier = $primaryVariant?->pricingTiers->first();
                        $priceMonthlyUsd = max(1.5, (float) ($tier?->price_monthly_usd ?: 3.5));
                        $priceYearlyUsd = max(15, (float) ($tier?->price_yearly_usd ?: $priceMonthlyUsd * 10));
                        $priceMonthlyLkr = ($tier && (float)$tier->price_monthly_lkr > 0) ? (float)$tier->price_monthly_lkr : round($priceMonthlyUsd * 305, 0);
                        $priceYearlyLkr = ($tier && (float)$tier->price_yearly_lkr > 0) ? (float)$tier->price_yearly_lkr : round($priceYearlyUsd * 305, 0);

                        $displayPrice = ($currency === 'USD')
                            ? ($billingPeriod === 'monthly' ? '$' . number_format($priceMonthlyUsd, 2) : '$' . number_format($priceYearlyUsd / 12, 2))
                            : ($billingPeriod === 'monthly' ? 'Rs ' . number_format($priceMonthlyLkr, 0) : 'Rs ' . number_format($priceYearlyLkr / 12, 0));
                    @endphp

                    <div @class([
                        'glass-card rounded-2xl p-6 flex flex-col justify-between transition-all relative border border-white/10 hover:border-pink-500/40',
                        'border-pink-500/50 shadow-[0_0_40px_-10px_rgba(236,72,153,0.3)]' => $prod->is_popular,
                    ])>
                        @if ($prod->is_popular)
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-gradient-to-r from-pink-600 to-purple-600 text-white shadow-lg">
                                Community Choice
                            </div>
                        @endif

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs uppercase font-mono tracking-wider text-pink-400">Bot Runtime</span>
                                <span class="text-[11px] font-mono text-neutral-400">24/7 Daemon</span>
                            </div>

                            <h3 class="text-xl font-bold text-white mb-2">{{ $prod->name }}</h3>
                            <p class="text-xs text-neutral-400 mb-6 leading-relaxed">
                                {{ $prod->description ?: 'Dedicated Node.js and Python container environment with real-time logs and auto-restart daemon.' }}
                            </p>

                            <div class="flex items-baseline gap-1 mb-6">
                                <span class="text-3xl font-black text-white">{{ $displayPrice }}</span>
                                <span class="text-xs font-mono text-neutral-400">/month</span>
                            </div>

                            <ul class="space-y-3 pt-6 border-t border-white/[0.08] text-xs text-neutral-300">
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-pink-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Node.js 20 &amp; Python 3.12 Runtimes</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-pink-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Process Supervisor Auto-Restart</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-pink-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Real-Time WebSocket Terminal Logs</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-pink-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Persistent Volume Session Storage</span>
                                </li>
                            </ul>
                        </div>

                        <div class="pt-8">
                            <button type="button"
                                wire:click="orderPlan('{{ $primaryVariant?->uuid }}', '{{ $tier?->uuid }}')"
                                class="w-full py-3 rounded-xl bg-gradient-to-r from-pink-600 to-purple-600 hover:from-pink-500 hover:to-purple-500 text-white font-bold text-xs shadow-glow-violet transition-all flex items-center justify-center gap-2">
                                Deploy {{ $prod->name }}
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full glass-card rounded-2xl p-12 text-center text-neutral-400">
                        No Bot Hosting plans found in database. Contact support for customized bot allocations.
                    </div>
                @endforelse
            </div>
        </div>
    </main>

    <x-storefront.footer />
</div>


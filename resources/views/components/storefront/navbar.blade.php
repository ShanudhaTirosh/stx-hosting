@props(['current' => 'home', 'currency' => 'USD'])

<nav x-data="{ mobileOpen: false, productsOpen: false }" class="sticky top-0 z-50 glass-nav transition-all duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        {{-- Brand Logo --}}
        <div class="flex items-center gap-6">
            <a href="/" class="flex items-center gap-2 hover:opacity-90 transition-opacity">
                <img src="/STKcdr.svg" alt="STX Enterprises" class="size-7" />
                <span class="text-lg font-extrabold tracking-tight">
                    <span class="text-cyan-400">STX</span> <span class="text-white">Hosting</span>
                </span>
            </a>

            {{-- Desktop Links --}}
            <div class="hidden lg:flex items-center gap-1 text-xs font-medium text-neutral-300">
                <a href="/vps" @class(['px-3 py-1.5 rounded-lg hover:text-white transition-colors', 'text-cyan-400 font-bold bg-white/5' => $current === 'vps'])>
                    Cloud VPS
                </a>
                <a href="/game-servers" @class(['px-3 py-1.5 rounded-lg hover:text-white transition-colors', 'text-cyan-400 font-bold bg-white/5' => $current === 'game-servers'])>
                    Game Servers
                </a>
                <a href="/app-hosting" @class(['px-3 py-1.5 rounded-lg hover:text-white transition-colors', 'text-cyan-400 font-bold bg-white/5' => $current === 'app-hosting'])>
                    App Hosting
                </a>
                <a href="/bot-hosting" @class(['px-3 py-1.5 rounded-lg hover:text-white transition-colors', 'text-cyan-400 font-bold bg-white/5' => $current === 'bot-hosting'])>
                    Bot Hosting
                </a>
                <a href="/databases" @class(['px-3 py-1.5 rounded-lg hover:text-white transition-colors', 'text-cyan-400 font-bold bg-white/5' => $current === 'databases'])>
                    Databases
                </a>
                <a href="/pricing" @class(['px-3 py-1.5 rounded-lg hover:text-white transition-colors', 'text-cyan-400 font-bold bg-white/5' => $current === 'pricing'])>
                    Pricing
                </a>
                <a href="/status" class="px-3 py-1.5 rounded-lg text-emerald-400 hover:text-emerald-300 flex items-center gap-1.5">
                    <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Status
                </a>
            </div>
        </div>

        {{-- Desktop Right Controls --}}
        <div class="hidden sm:flex items-center gap-3">
            @auth
                <a href="{{ route('portal.index') }}" class="text-xs text-neutral-300 hover:text-white px-2 py-1 transition-colors">
                    Customer Portal
                </a>
                <a href="{{ route('dashboard') }}" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-gradient-to-r from-purple-600 to-cyan-500 hover:from-purple-500 hover:to-cyan-400 text-white shadow-lg transition-all">
                    Console &rarr;
                </a>
            @else
                <a href="{{ route('login') }}" class="text-xs text-neutral-300 hover:text-white px-2 py-1 transition-colors">
                    Sign In
                </a>
                <a href="{{ route('register') }}" class="text-xs font-semibold px-3.5 py-1.5 rounded-lg bg-gradient-to-r from-purple-600 to-cyan-500 hover:from-purple-500 hover:to-cyan-400 text-white shadow-lg transition-all">
                    Get Started
                </a>
            @endauth
        </div>

        {{-- Mobile Hamburger --}}
        <div class="flex lg:hidden items-center gap-2">
            <button type="button" @click="mobileOpen = !mobileOpen" aria-label="Toggle menu"
                class="p-2 rounded-lg text-neutral-400 hover:text-white hover:bg-white/5">
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path x-show="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path x-show="mobileOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile Dropdown Menu --}}
    <div x-show="mobileOpen" x-cloak class="lg:hidden glass-nav border-t border-white/10 px-4 py-4 space-y-2 text-xs">
        <a href="/vps" class="block py-2 text-neutral-200 hover:text-cyan-400">Cloud VPS</a>
        <a href="/game-servers" class="block py-2 text-neutral-200 hover:text-cyan-400">Game Servers</a>
        <a href="/app-hosting" class="block py-2 text-neutral-200 hover:text-cyan-400">Application Hosting</a>
        <a href="/bot-hosting" class="block py-2 text-neutral-200 hover:text-cyan-400">Bot & Worker Hosting</a>
        <a href="/databases" class="block py-2 text-neutral-200 hover:text-cyan-400">Managed Databases</a>
        <a href="/pricing" class="block py-2 text-neutral-200 hover:text-cyan-400">Pricing & Comparison</a>
        <a href="/status" class="block py-2 text-emerald-400">Infrastructure Status</a>
        <div class="pt-3 border-t border-white/10 flex items-center justify-between">
            @auth
                <a href="{{ route('dashboard') }}" class="font-bold text-cyan-400">Open Console &rarr;</a>
            @else
                <a href="{{ route('login') }}" class="text-neutral-300">Sign In</a>
                <a href="{{ route('register') }}" class="font-bold text-cyan-400">Get Started &rarr;</a>
            @endauth
        </div>
    </div>
</nav>


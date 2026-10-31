<div class="min-h-screen bg-[#030014] text-white flex flex-col justify-between relative overflow-hidden">
    {{-- Background Grid & Glow --}}
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0"></div>
    <div class="fixed top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-cyan-600/15 rounded-full blur-[140px] pointer-events-none -z-10"></div>

    <x-storefront.navbar current="game-servers" :currency="$currency" />

    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-24 relative z-10 w-full space-y-16">
        {{-- Hero Header --}}
        <div class="text-center space-y-4 max-w-3xl mx-auto pt-6">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-300 text-xs font-mono font-bold tracking-widest uppercase">
                <span class="size-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                HIGH-TICKRATE GAME INFRASTRUCTURE
            </div>

            <h1 class="text-4xl md:text-6xl font-black tracking-tight text-white leading-tight">
                Low-Latency <span class="text-gradient">Game Server</span> Nodes
            </h1>

            <p class="text-base md:text-lg text-neutral-300 leading-relaxed font-sans">
                Engineered for extreme performance with DDR5 ECC memory, Gen4 NVMe arrays, custom game DDoS defense, and instant modpack installation.
            </p>
        </div>

        {{-- Official External Platform Link Card (per user specification) --}}
        <div class="max-w-4xl mx-auto glass-card rounded-3xl p-8 sm:p-12 border border-cyan-500/30 shadow-[0_25px_60px_rgba(0,0,0,0.8),0_0_40px_rgba(6,182,212,0.15)] backdrop-blur-2xl space-y-6 text-center relative z-10">
            <div class="size-16 rounded-2xl bg-cyan-500/20 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shadow-[0_0_25px_rgba(6,182,212,0.4)] mx-auto">
                <svg class="size-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>
            </div>

            <div class="space-y-3 max-w-2xl mx-auto">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 text-xs font-mono font-bold tracking-widest uppercase">
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Dedicated Gaming Control Panel
                </div>

                <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                    Managed on Our <span class="text-gradient">Gaming Platform</span>
                </h2>

                <p class="text-sm text-neutral-300 leading-relaxed font-sans">
                    Dedicated Game Servers (<strong class="text-white">Minecraft Paper/Fabric/Forge, Rust 100-Tick, Palworld Dedicated, Counter-Strike 2, and ARK: Survival Ascended</strong>) run on our specialized game orchestration panel with 1-click modpack installers, automated backup snapshots, and real-time console access.
                </p>

                <p class="text-xs text-neutral-400 font-mono">
                    Official Game Node Gateway: <span class="text-cyan-300 font-bold">games.shanutechx.com</span>
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4 border-t border-white/10">
                <a href="{{ $gamePlatformUrl }}" target="_blank" rel="noopener noreferrer"
                    class="w-full sm:w-auto px-8 py-3.5 rounded-2xl bg-gradient-to-r from-cyan-600 via-indigo-600 to-purple-600 hover:from-cyan-500 hover:to-purple-500 text-white font-extrabold text-sm shadow-[0_0_25px_rgba(6,182,212,0.45)] hover:shadow-[0_0_35px_rgba(6,182,212,0.7)] transition-all flex items-center justify-center gap-2 font-mono">
                    <span>Launch Game Server Panel</span>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>

                <a href="https://discord.gg/shanutechx" target="_blank" rel="noopener noreferrer"
                    class="w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 text-neutral-300 hover:text-white font-bold text-sm transition-all flex items-center justify-center gap-2 font-mono">
                    <span>Join Gaming Community</span>
                    <svg class="size-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>

        {{-- Supported Game Titles Showcase --}}
        <div class="border-t border-white/[0.06] pt-12">
            <div class="text-center space-y-2 mb-10">
                <span class="text-xs font-mono uppercase font-bold text-cyan-400 tracking-widest">Supported Game Runtimes</span>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-white">High-Tickrate Game Environments</h3>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-5xl mx-auto">
                <div class="p-5 rounded-2xl glass-card border border-white/10 text-center space-y-2">
                    <div class="size-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center mx-auto">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div class="text-sm font-bold text-white">128-Tick CS2 &amp; Rust</div>
                    <div class="text-[11px] text-neutral-400">Low-jitter UDP packet delivery</div>
                </div>

                <div class="p-5 rounded-2xl glass-card border border-white/10 text-center space-y-2">
                    <div class="size-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center mx-auto">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div class="text-sm font-bold text-white">Game Anti-DDoS</div>
                    <div class="text-[11px] text-neutral-400">Volumetric attack filtering</div>
                </div>

                <div class="p-5 rounded-2xl glass-card border border-white/10 text-center space-y-2">
                    <div class="size-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mx-auto">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    </div>
                    <div class="text-sm font-bold text-white">1-Click Modpacks</div>
                    <div class="text-[11px] text-neutral-400">CurseForge &amp; Modrinth sync</div>
                </div>

                <div class="p-5 rounded-2xl glass-card border border-white/10 text-center space-y-2">
                    <div class="size-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center mx-auto">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="text-sm font-bold text-white">99.9% Uptime SLA</div>
                    <div class="text-[11px] text-neutral-400">Automated daily game backups</div>
                </div>
            </div>
        </div>
    </main>

    <x-storefront.footer />
</div>

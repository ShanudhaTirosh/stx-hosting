<div class="min-h-screen bg-[#030014] text-white flex flex-col justify-between relative overflow-hidden">
    {{-- Background Grid & Glow --}}
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0"></div>
    <div class="fixed top-1/3 left-1/2 -translate-x-1/2 w-[600px] h-[350px] bg-purple-600/20 rounded-full blur-[140px] pointer-events-none -z-10"></div>

    <x-storefront.navbar current="v2ray" />

    <main class="flex-1 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-24 relative z-10 w-full flex flex-col items-center justify-center text-center">
        {{-- Card Container --}}
        <div class="glass-card rounded-3xl p-8 sm:p-12 border border-purple-500/30 shadow-[0_25px_60px_rgba(0,0,0,0.8),0_0_40px_rgba(124,58,237,0.15)] backdrop-blur-2xl space-y-8 relative z-10 w-full">
            <div class="flex items-center justify-center">
                <div class="size-16 rounded-2xl bg-purple-500/20 border border-purple-500/40 flex items-center justify-center text-purple-300 shadow-[0_0_25px_rgba(168,85,247,0.4)]">
                    <svg class="size-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
            </div>

            <div class="space-y-4 max-w-2xl mx-auto">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-500/20 border border-purple-500/30 text-purple-300 text-xs font-mono font-bold tracking-widest uppercase">
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Official Store Notice
                </div>

                <h1 class="text-3xl sm:text-5xl font-black tracking-tight text-white leading-tight">
                    Sold Exclusively at <br class="hidden sm:inline" />
                    <span class="text-gradient">store.shanutechx.com</span>
                </h1>

                <p class="text-sm sm:text-base text-neutral-300 leading-relaxed pt-2">
                    <strong class="text-white font-semibold">V2Ray Proxies</strong> (VMess, VLess, Trojan, ShadowSocks) and
                    <strong class="text-white font-semibold">Digital Subscriptions</strong> (Netflix 4K, Spotify, CapCut Pro, ChatGPT Plus, Claude, Google AI Pro) are not sold directly on this hosting infrastructure portal.
                </p>

                <p class="text-xs sm:text-sm text-neutral-400 leading-relaxed">
                    They are processed and delivered with automated instant activation exclusively on our dedicated e-commerce store at
                    <span class="text-purple-300 font-mono font-bold">store.shanutechx.com</span>.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4 border-t border-white/10">
                <a href="{{ $storeUrl }}" target="_blank" rel="noopener noreferrer"
                    class="w-full sm:w-auto px-8 py-3.5 rounded-2xl bg-gradient-to-r from-purple-600 via-indigo-600 to-cyan-600 hover:from-purple-500 hover:to-cyan-500 text-white font-extrabold text-sm shadow-[0_0_25px_rgba(168,85,247,0.45)] hover:shadow-[0_0_35px_rgba(168,85,247,0.7)] transition-all flex items-center justify-center gap-2 cursor-pointer font-mono">
                    <span>Visit STX Digital Store</span>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>

                <a href="{{ route('store.index') }}" wire:navigate
                    class="w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 text-neutral-300 hover:text-white font-bold text-sm transition-all flex items-center justify-center gap-2">
                    <svg class="size-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>Back to Hosting Home</span>
                </a>
            </div>
        </div>
    </main>

    <x-storefront.footer />
</div>


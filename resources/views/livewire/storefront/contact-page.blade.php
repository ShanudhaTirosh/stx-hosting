<div class="min-h-screen bg-[#030014] text-white flex flex-col justify-between relative overflow-hidden">
    {{-- Background Grid & Glow --}}
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0"></div>
    <div class="fixed top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-purple-600/15 rounded-full blur-[140px] pointer-events-none -z-10"></div>

    <x-storefront.navbar current="contact" />

    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-24 relative z-10 w-full space-y-16">
        {{-- Hero Header --}}
        <div class="text-center space-y-4 max-w-3xl mx-auto pt-6">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-300 text-xs font-mono font-bold tracking-widest uppercase">
                <span class="size-1.5 rounded-full bg-purple-400 animate-pulse"></span>
                24/7/365 LIVE ENGINEER SUPPORT
            </div>

            <h1 class="text-4xl md:text-6xl font-black tracking-tight text-white leading-tight">
                Get in <span class="text-gradient">Touch</span> with Us
            </h1>

            <p class="text-base md:text-lg text-neutral-300 leading-relaxed font-sans">
                Need assistance configuring a server node, custom deployment, or billing inquiry? Our Tier-3 engineers are ready to assist you via WhatsApp, Discord, Phone, or Email.
            </p>
        </div>

        {{-- Direct Contact Channels Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            {{-- WhatsApp --}}
            <div class="glass-card rounded-2xl p-6 flex flex-col justify-between border border-white/10 hover:border-emerald-500/40 transition-all group">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="p-3 rounded-xl bg-emerald-500/10 text-emerald-400">
                            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            Fastest
                        </span>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white group-hover:text-emerald-300 transition-colors">WhatsApp Live Chat</h3>
                        <p class="text-xs text-neutral-400 mt-1 leading-relaxed">Instant messaging directly with on-duty infrastructure engineers.</p>
                    </div>
                    <div class="bg-black/40 border border-white/5 rounded-xl px-3 py-2 text-xs font-mono text-neutral-200">
                        +94 77 123 4567
                    </div>
                </div>
                <div class="pt-5">
                    <a href="https://wa.me/94771234567?text=Hello%20STX%20Support" target="_blank" rel="noopener noreferrer"
                        class="w-full py-2.5 px-4 rounded-xl bg-emerald-600/20 hover:bg-emerald-600 border border-emerald-500/30 text-emerald-300 hover:text-white text-xs font-extrabold flex items-center justify-center gap-2 transition-all">
                        <span>Chat on WhatsApp</span>
                    </a>
                </div>
            </div>

            {{-- Discord --}}
            <div class="glass-card rounded-2xl p-6 flex flex-col justify-between border border-white/10 hover:border-indigo-500/40 transition-all group">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="p-3 rounded-xl bg-indigo-500/10 text-indigo-400">
                            <svg class="size-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994.021-.041.001-.09-.041-.106a13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.929 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.894.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                            Community
                        </span>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white group-hover:text-indigo-300 transition-colors">Discord Community</h3>
                        <p class="text-xs text-neutral-400 mt-1 leading-relaxed">Community drops, announcements &amp; automated ticket bot.</p>
                    </div>
                    <div class="bg-black/40 border border-white/5 rounded-xl px-3 py-2 text-xs font-mono text-neutral-200">
                        discord.gg/shanutechx
                    </div>
                </div>
                <div class="pt-5">
                    <a href="https://discord.gg/shanutechx" target="_blank" rel="noopener noreferrer"
                        class="w-full py-2.5 px-4 rounded-xl bg-indigo-600/20 hover:bg-indigo-600 border border-indigo-500/30 text-indigo-300 hover:text-white text-xs font-extrabold flex items-center justify-center gap-2 transition-all">
                        <span>Join Discord Server</span>
                    </a>
                </div>
            </div>

            {{-- Phone hotline --}}
            <div class="glass-card rounded-2xl p-6 flex flex-col justify-between border border-white/10 hover:border-purple-500/40 transition-all group">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="p-3 rounded-xl bg-purple-500/10 text-purple-400">
                            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider bg-purple-500/20 text-purple-300 border border-purple-500/30">
                            Priority
                        </span>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white group-hover:text-purple-300 transition-colors">Hotline &amp; Telephone</h3>
                        <p class="text-xs text-neutral-400 mt-1 leading-relaxed">Direct voice line for urgent infrastructure alerts.</p>
                    </div>
                    <div class="bg-black/40 border border-white/5 rounded-xl px-3 py-2 text-xs font-mono text-neutral-200">
                        +1 (800) 589-STX1
                    </div>
                </div>
                <div class="pt-5">
                    <a href="tel:+18005897891"
                        class="w-full py-2.5 px-4 rounded-xl bg-purple-600/20 hover:bg-purple-600 border border-purple-500/30 text-purple-300 hover:text-white text-xs font-extrabold flex items-center justify-center gap-2 transition-all">
                        <span>Call Phone Support</span>
                    </a>
                </div>
            </div>

            {{-- Email --}}
            <div class="glass-card rounded-2xl p-6 flex flex-col justify-between border border-white/10 hover:border-cyan-500/40 transition-all group">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="p-3 rounded-xl bg-cyan-500/10 text-cyan-400">
                            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">
                            24/7 Desk
                        </span>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white group-hover:text-cyan-300 transition-colors">Direct Support Email</h3>
                        <p class="text-xs text-neutral-400 mt-1 leading-relaxed">General inquiries, technical assistance &amp; quotes.</p>
                    </div>
                    <div class="bg-black/40 border border-white/5 rounded-xl px-3 py-2 text-xs font-mono text-neutral-200 truncate">
                        support@shanutechx.cloud
                    </div>
                </div>
                <div class="pt-5">
                    <a href="mailto:support@shanutechx.cloud"
                        class="w-full py-2.5 px-4 rounded-xl bg-cyan-600/20 hover:bg-cyan-600 border border-cyan-500/30 text-cyan-300 hover:text-white text-xs font-extrabold flex items-center justify-center gap-2 transition-all">
                        <span>Send Email</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Contact Form Section --}}
        <div class="max-w-3xl mx-auto glass-card rounded-3xl p-8 sm:p-12 border border-white/10 relative">
            <h2 class="text-2xl font-bold text-white mb-2">Send an Inquiry</h2>
            <p class="text-xs text-neutral-400 mb-8">Leave a detailed message and an engineer will reply within 30 minutes.</p>

            @if ($sent)
                <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm flex items-center gap-3">
                    <svg class="size-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Thank you! Your message has been routed to our on-duty engineer team. We will get back to you shortly.</span>
                </div>
            @else
                <form wire:submit.prevent="submitMessage" class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs font-mono text-neutral-300">Your Name *</label>
                            <input type="text" wire:model="name" placeholder="John Doe"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-neutral-500 focus:outline-none focus:border-purple-500 transition-colors">
                            @error('name') <span class="text-xs text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-mono text-neutral-300">Email Address *</label>
                            <input type="email" wire:model="email" placeholder="john@example.com"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-neutral-500 focus:outline-none focus:border-purple-500 transition-colors">
                            @error('email') <span class="text-xs text-rose-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs font-mono text-neutral-300">Department</label>
                            <select wire:model="department"
                                class="w-full bg-[#090b22] border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-purple-500">
                                <option value="tech">Technical Support</option>
                                <option value="sales">Sales &amp; Custom Quotes</option>
                                <option value="billing">Billing &amp; Invoices</option>
                                <option value="abuse">Abuse &amp; Compliance</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-mono text-neutral-300">Subject</label>
                            <input type="text" wire:model="subject" placeholder="Inquiry about Cloud VPS node"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-neutral-500 focus:outline-none focus:border-purple-500 transition-colors">
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-mono text-neutral-300">Message *</label>
                        <textarea wire:model="message" rows="5" placeholder="Provide details regarding your technical inquiry or infrastructure needs..."
                            class="w-full bg-white/5 border border-white/10 rounded-xl p-3.5 text-xs text-white placeholder-neutral-500 focus:outline-none focus:border-purple-500 transition-colors"></textarea>
                        @error('message') <span class="text-xs text-rose-400">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit"
                        class="w-full py-3.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-xs shadow-glow-violet transition-all flex items-center justify-center gap-2">
                        <span>Send Support Message</span>
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </form>
            @endif
        </div>
    </main>

    <x-storefront.footer />
</div>


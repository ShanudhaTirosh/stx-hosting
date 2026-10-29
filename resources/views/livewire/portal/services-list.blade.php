<div class="max-w-6xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-800">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">My Active Hosting Services</h1>
            <p class="text-sm text-neutral-400">Manage your provisioned VPS nodes, application instances, and database credentials.</p>
        </div>

        <a href="http://localhost:3000/vps" target="_blank" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded shadow flex items-center gap-1.5 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Order New Service
        </a>
    </div>

    <!-- Services Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($services as $service)
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4 hover:border-purple-500/50 transition-all shadow-lg flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase rounded bg-neutral-800 text-purple-300 border border-purple-500/20">
                            {{ $service->productVariant->name ?? 'VPS Instance' }}
                        </span>
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-full {{ 
                            $service->status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 
                            ($service->status === 'suspended' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20') 
                        }}">
                            {{ $service->status }}
                        </span>
                    </div>

                    <div>
                        <h3 class="text-base font-bold text-white tracking-tight truncate">{{ $service->hostname ?: $service->name }}</h3>
                        <p class="text-xs text-neutral-500 font-mono mt-0.5">{{ $service->os_image }}</p>
                    </div>

                    <div class="bg-neutral-950 p-3 rounded-lg border border-neutral-850 font-mono text-xs space-y-1">
                        <div class="flex justify-between">
                            <span class="text-neutral-500">Public IPv4:</span>
                            <span class="text-white font-bold select-all">{{ $service->ip_address ?: 'Pending assignment' }}</span>
                        </div>
                        @if ($service->renews_at)
                            <div class="flex justify-between text-[11px]">
                                <span class="text-neutral-500">Next Renewal:</span>
                                <span class="text-neutral-300">{{ $service->renews_at->format('M d, Y') }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="pt-3 border-t border-neutral-800 flex items-center justify-between">
                    <a href="{{ route('portal.service.detail', ['uuid' => $service->uuid]) }}" class="w-full py-2 bg-neutral-800 hover:bg-neutral-700 text-white font-bold text-xs rounded text-center transition-all">
                        Manage & Credentials &rarr;
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full p-12 text-center bg-neutral-900 border border-neutral-800 rounded-xl space-y-3">
                <div class="w-12 h-12 rounded-full bg-purple-500/10 text-purple-400 flex items-center justify-center mx-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <h3 class="text-base font-bold text-white">No active services found</h3>
                <p class="text-xs text-neutral-400 max-w-sm mx-auto">You do not have any provisioned servers or containers yet. Browse our storefront catalog to deploy your first cloud VPS.</p>
                <div class="pt-2">
                    <a href="http://localhost:3000/vps" target="_blank" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded shadow inline-block">
                        Deploy Cloud VPS
                    </a>
                </div>
            </div>
        @endforelse
    </div>
</div>

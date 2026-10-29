<div wire:poll.30s class="min-h-screen bg-[#07060d] text-white py-12 px-4 sm:px-6 lg:px-8 font-sans selection:bg-purple-600 selection:text-white">
    <div class="max-w-4xl mx-auto space-y-10">

        <!-- Top Header & Brand Bar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-6 pb-6 border-b border-neutral-800">
            <div class="flex items-center gap-4">
                <a href="http://localhost:3000" class="flex items-center gap-3 group">
                    <svg class="h-10 w-auto" viewBox="0 0 10000 10000" xmlns="http://www.w3.org/2000/svg">
                        <g fill="#20c6e0">
                            <polygon points="8500,6616.67 7576.67,6616.67 6763.33,5756.67 5910,6616.67 4290,6616.67 4660,4530 5390,4530 5030,6580 5030,6590 6333.33,5293.33 5603.33,4530 6526.67,4526.67 "/>
                            <polygon points="8166.67,3383.33 9160,3383.33 7320,5156.67 6873.33,4670 "/>
                            <polygon points="2140,4963.33 2006.67,4826.67 1963.33,4750 1943.33,4676.67 1940,4610 1946.67,4563.33 1963.33,4503.33 1996.67,4446.67 2040,4403.33 2086.67,4360 2136.67,4326.67 2193.33,4303.33 2243.33,4290 2286.67,4286.67 6346.67,4286.67 7043.33,3620 2290,3616.67 2183.33,3633.33 2096.67,3656.67 2003.33,3683.33 1900,3730 1813.33,3776.67 1723.33,3840 1666.67,3876.67 1590,3943.33 1520,4023.33 1470,4083.33 1413.33,4160 1353.33,4273.33 1313.33,4370 1280,4483.33 1260,4583.33 1256.67,4690 1263.33,4793.33 1283.33,4896.67 1320,5013.33 1350,5070 1393.33,5143.33 1450,5216.67 1496.67,5263.33 1556.67,5310 1640,5363.33 1716.67,5406.67 1790,5433.33 1850,5450 1910,5466.67 1963.33,5473.33 2003.33,5476.67 2083.33,5480 2640,5480 "/>
                            <polygon points="2213.33,4776.67 2873.33,5476.67 3373.33,5476.67 3396.67,5480 3426.67,5493.33 3456.67,5510 3476.67,5526.67 3496.67,5543.33 3516.67,5570 3533.33,5600 3540,5620 3546.67,5643.33 3546.67,5670 3546.67,5700 3540,5730 3526.67,5770 3513.33,5800 3493.33,5830 3473.33,5853.33 3440,5883.33 3406.67,5906.67 3363.33,5926.67 3330,5940 1526.67,5943.33 840,6613.33 3220,6610 3260,6610 3313.33,6606.67 3366.67,6596.67 3420,6580 3493.33,6560 3550,6536.67 3606.67,6513.33 3666.67,6476.67 3743.33,6430 3816.67,6383.33 3880,6326.67 3933.33,6276.67 3990,6213.33 4056.67,6130 4116.67,6030 4153.33,5960 4186.67,5873.33 4216.67,5780 4226.67,5716.67 4233.33,5663.33 4230,5496.67 4210,5390 4176.67,5286.67 4143.33,5206.67 4090,5116.67 4040,5056.67 3980,4993.33 3920,4943.33 3840,4890 3756.67,4846.67 3653.33,4810 3573.33,4793.33 3523.33,4786.67 3443.33,4783.33 "/>
                        </g>
                    </svg>
                    <div>
                        <div class="text-lg font-extrabold tracking-tight text-white group-hover:text-cyan-400 transition-colors">STX Enterprises</div>
                        <div class="text-[9px] font-mono tracking-widest uppercase text-cyan-400 font-bold border-t border-cyan-500/40 pt-0.5">Digital Empire Studio</div>
                    </div>
                </a>
            </div>

            <div class="flex items-center gap-4 text-xs font-mono">
                <a href="http://localhost:3000" class="text-neutral-400 hover:text-white transition-colors">&larr; Storefront</a>
                <span class="text-neutral-700">|</span>
                <a href="{{ route('portal.services') }}" class="text-neutral-400 hover:text-white transition-colors">Customer Portal</a>
            </div>
        </div>

        <!-- Global Status Hero Banner -->
        <div>
            @if ($overallStatus === 'operational')
                <div class="bg-gradient-to-r from-emerald-950/80 via-neutral-900 to-emerald-950/80 border border-emerald-500/40 rounded-2xl p-6 shadow-2xl flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="relative flex items-center justify-center w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400">
                            <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-25 animate-ping"></span>
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-extrabold text-white tracking-tight">All Systems Operational</h1>
                            <p class="text-xs text-neutral-400 mt-0.5">All global edge gateways, cloud clusters, and databases performing normally.</p>
                        </div>
                    </div>

                    <div class="hidden sm:block text-right font-mono text-xs">
                        <span class="text-neutral-500 block uppercase text-[10px] font-bold">90-Day Avg Uptime</span>
                        <span class="text-emerald-400 font-bold text-base">{{ $averageUptime }}%</span>
                    </div>
                </div>
            @elseif ($overallStatus === 'degraded_performance')
                <div class="bg-gradient-to-r from-amber-950/80 via-neutral-900 to-amber-950/80 border border-amber-500/40 rounded-2xl p-6 shadow-2xl flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-amber-500/20 text-amber-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-extrabold text-white tracking-tight">Degraded System Performance</h1>
                            <p class="text-xs text-neutral-400 mt-0.5">Elevated latency or intermittent connectivity detected on non-critical nodes.</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-gradient-to-r from-rose-950/80 via-neutral-900 to-rose-950/80 border border-rose-500/40 rounded-2xl p-6 shadow-2xl flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-extrabold text-white tracking-tight">Service Disruption Detected</h1>
                            <p class="text-xs text-neutral-400 mt-0.5">Our operations engineers are currently investigating and restoring affected nodes.</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Grouped Monitor Categories -->
        <div class="space-y-8">
            @foreach ($groupedMonitors as $groupName => $monitors)
                <div class="bg-neutral-900/60 border border-neutral-800 rounded-2xl p-6 space-y-4 shadow-xl backdrop-blur-sm">
                    <div class="flex items-center justify-between pb-3 border-b border-neutral-800/80">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-300 font-mono flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                            {{ $groupName }}
                        </h2>
                        <span class="text-[11px] font-mono text-neutral-500">{{ $monitors->count() }} Managed Probes</span>
                    </div>

                    <div class="space-y-6">
                        @foreach ($monitors as $monitor)
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-white">{{ $monitor->public_label }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-[11px] font-mono text-neutral-400">{{ $monitor->last_latency_ms }}ms</span>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase font-mono {{ 
                                            $monitor->current_status === 'operational' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 
                                            ($monitor->current_status === 'degraded' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20')
                                        }}">
                                            {{ $monitor->current_status }}
                                        </span>
                                    </div>
                                </div>

                                <!-- 90-Day Interactive Uptime Heatmap Bars -->
                                <div class="flex items-center gap-[2.5px] h-7 w-full bg-neutral-950 p-1 rounded-md border border-neutral-850">
                                    @foreach ($monitor->daily_uptime_history as $day)
                                        <div 
                                            title="{{ $day['day'] }}: {{ strtoupper($day['status']) }}"
                                            class="flex-1 h-full rounded-[1px] transition-all hover:scale-125 cursor-pointer {{ 
                                                $day['status'] === 'up' ? 'bg-emerald-500/90 hover:bg-emerald-400' : 
                                                ($day['status'] === 'degraded' ? 'bg-amber-500 hover:bg-amber-400' : 'bg-rose-500 hover:bg-rose-400')
                                            }}"
                                        ></div>
                                    @endforeach
                                </div>

                                <div class="flex items-center justify-between text-[10px] font-mono text-neutral-500 px-0.5">
                                    <span>90 days ago</span>
                                    <span class="text-neutral-400">{{ $monitor->uptime_percentage }}% uptime</span>
                                    <span>Today</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Incidents & Maintenance Timeline -->
        <div class="bg-neutral-900/60 border border-neutral-800 rounded-2xl p-6 space-y-4 shadow-xl">
            <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-300 font-mono">
                Recent Incident History & Maintenance
            </h3>

            <div class="space-y-4 pt-2">
                @forelse ($incidents as $incident)
                    <div class="bg-neutral-950 p-4 rounded-xl border border-neutral-850 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-white text-xs">{{ $incident->title }}</span>
                            <span class="px-2 py-0.5 text-[10px] uppercase font-mono font-bold rounded {{ 
                                $incident->status === 'resolved' ? 'bg-emerald-950 text-emerald-400 border border-emerald-500/20' : 'bg-amber-950 text-amber-400 border border-amber-500/20'
                            }}">
                                {{ $incident->status }}
                            </span>
                        </div>
                        <p class="text-xs text-neutral-400 leading-relaxed">{{ $incident->message }}</p>
                        <div class="text-[10px] text-neutral-500 font-mono">
                            Logged: {{ $incident->created_at->format('M d, Y — H:i T') }} 
                            @if ($incident->resolved_at)
                                &middot; Resolved: {{ $incident->resolved_at->format('M d, Y — H:i T') }}
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-xs text-neutral-500 text-center py-6">
                        No service disruptions recorded in the last 90 days.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center pt-6 pb-12 text-xs font-mono text-neutral-500 border-t border-neutral-850/80">
            STX Enterprises &middot; Digital Empire Studio &middot; Real-Time Telemetry Engine
        </div>

    </div>
</div>

<div class="space-y-6">
    <!-- Header with Stats -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-neutral-800 pb-5">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-white flex items-center gap-2">
                <span>Cluster Node Fleet & Placement Studio</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-900/60 text-indigo-300 border border-indigo-700/50">
                    Multi-Node Engine
                </span>
            </h1>
            <p class="text-xs text-neutral-400 mt-1">
                Monitor worker nodes, auto-balance workload, configure placement rules, and onboard new servers with a single command.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="openEnrollModal" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg transition shadow-md flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Add Worker Node</span>
            </button>
        </div>
    </div>

    <!-- Cluster Fleet Statistics Overview -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl bg-neutral-900 border border-neutral-800">
            <span class="text-[10px] uppercase font-bold text-neutral-500 tracking-wider">Fleet Nodes</span>
            <div class="text-xl font-bold text-white font-mono mt-0.5">{{ $readyNodes }} / {{ $nodes->count() }} <span class="text-xs text-neutral-500 font-normal">Active</span></div>
            <p class="text-[11px] text-emerald-400 mt-1">Autonomous Docker workers</p>
        </div>
        <div class="p-4 rounded-xl bg-neutral-900 border border-neutral-800">
            <span class="text-[10px] uppercase font-bold text-neutral-500 tracking-wider">Total Compute</span>
            <div class="text-xl font-bold text-indigo-400 font-mono mt-0.5">{{ $totalCores }} <span class="text-xs text-neutral-500 font-normal">vCPUs</span></div>
            <p class="text-[11px] text-neutral-500 mt-1">Multi-core capacity pool</p>
        </div>
        <div class="p-4 rounded-xl bg-neutral-900 border border-neutral-800">
            <span class="text-[10px] uppercase font-bold text-neutral-500 tracking-wider">Total Memory Pool</span>
            <div class="text-xl font-bold text-purple-400 font-mono mt-0.5">{{ $totalRamGb }} <span class="text-xs text-neutral-500 font-normal">GB RAM</span></div>
            <p class="text-[11px] text-neutral-500 mt-1">cgroups memory ceiling</p>
        </div>
        <div class="p-4 rounded-xl bg-neutral-900 border border-neutral-800">
            <span class="text-[10px] uppercase font-bold text-neutral-500 tracking-wider">Hosted Applications</span>
            <div class="text-xl font-bold text-emerald-400 font-mono mt-0.5">{{ $totalApps }} <span class="text-xs text-neutral-500 font-normal">Apps</span></div>
            <p class="text-[11px] text-neutral-500 mt-1">Distributed across fleet</p>
        </div>
    </div>

    <!-- Worker Nodes Table -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden shadow-lg">
        <div class="px-5 py-4 border-b border-neutral-800 flex items-center justify-between">
            <h2 class="text-xs font-bold uppercase tracking-wider text-neutral-300">Active Cluster Nodes</h2>
            <span class="text-[11px] text-neutral-500 font-mono">Heartbeats streamed via Sentinel</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-neutral-800 bg-neutral-950/50 text-neutral-400 uppercase text-[10px] font-semibold tracking-wider">
                        <th class="py-3 px-4">Node / Identity</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Region / IP</th>
                        <th class="py-3 px-4">CPU & Load</th>
                        <th class="py-3 px-4">RAM Allocation</th>
                        <th class="py-3 px-4">Apps Count</th>
                        <th class="py-3 px-4 text-right">Node Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-800/60 font-mono">
                    @forelse($nodes as $node)
                        <tr class="hover:bg-neutral-950/40 transition">
                            <td class="py-3.5 px-4 font-sans">
                                <div class="font-bold text-white text-xs">{{ $node->name }}</div>
                                <span class="text-[10px] text-neutral-500 font-mono">{{ $node->uuid }}</span>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($node->status === 'ready')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-900/60 text-emerald-300 border border-emerald-700/50 flex items-center gap-1 w-fit">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> READY
                                    </span>
                                @elseif($node->status === 'draining')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-900/60 text-amber-300 border border-amber-700/50 flex items-center gap-1 w-fit animate-pulse">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span> DRAINING
                                    </span>
                                @elseif($node->status === 'maintenance')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-900/60 text-purple-300 border border-purple-700/50 flex items-center gap-1 w-fit">
                                        <span class="h-1.5 w-1.5 rounded-full bg-purple-400"></span> CORDONED
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-neutral-800 text-neutral-400 border border-neutral-700 flex items-center gap-1 w-fit">
                                        {{ strtoupper($node->status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="text-white">{{ $node->public_ip }}</div>
                                <span class="text-[10px] text-neutral-500 uppercase">{{ $node->region }}</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="text-neutral-200">{{ $node->cpu_cores }} Cores</div>
                                <span class="text-[10px] text-neutral-400">Load: {{ number_format($node->current_cpu_load, 2) }}</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="text-neutral-200">{{ round($node->total_ram_mb / 1024, 1) }} GB</div>
                                <span class="text-[10px] text-emerald-400 font-semibold">{{ $node->availableRamMb() }} MB free</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded bg-neutral-950 border border-neutral-800 text-neutral-200 font-bold">
                                    {{ $node->service_instances_count }} / {{ $node->max_apps }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-sans space-x-1.5">
                                <button 
                                    wire:click="cordonNode({{ $node->id }})" 
                                    class="px-2.5 py-1 text-[11px] font-semibold rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-200 transition"
                                    title="Cordon or uncordon node to pause new deployments"
                                >
                                    {{ $node->status === 'maintenance' ? 'Uncordon' : 'Cordon' }}
                                </button>

                                <button 
                                    wire:click="drainNode({{ $node->id }})" 
                                    wire:confirm="Are you sure you want to drain '{{ $node->name }}'? All running applications will be migrated to healthy nodes with minimal downtime."
                                    class="px-2.5 py-1 text-[11px] font-semibold rounded bg-amber-900/60 hover:bg-amber-800 text-amber-200 border border-amber-700/50 transition"
                                    @disabled($node->status === 'draining')
                                >
                                    Drain
                                </button>

                                @if($node->service_instances_count === 0 && $nodes->count() > 1)
                                    <button 
                                        wire:click="deleteNode({{ $node->id }})" 
                                        wire:confirm="Permanently delete this worker node record?"
                                        class="px-2.5 py-1 text-[11px] font-semibold rounded bg-rose-950/60 hover:bg-rose-900 text-rose-300 border border-rose-800/50 transition"
                                    >
                                        Delete
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-neutral-500 font-sans">
                                No worker nodes found in cluster. Click "Add Worker Node" to register your first node.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Node Placement Rules Studio -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4">
        <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-300">Cluster Placement Rules</h3>
                <p class="text-xs text-neutral-400">Pin specific teams or templates (e.g. Supabase, PostgreSQL) to designated dedicated nodes.</p>
            </div>
        </div>

        <!-- Add Rule Form -->
        <form wire:submit="addPlacementRule" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-[11px] font-semibold text-neutral-400 mb-1">Rule Type</label>
                <select wire:model="ruleType" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white text-xs rounded">
                    <option value="pin_template">Pin Template (e.g. supabase, wordpress)</option>
                    <option value="pin_team">Pin Team (by Team ID)</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-neutral-400 mb-1">Target Identifier</label>
                <input type="text" wire:model="ruleTarget" placeholder="e.g. supabase or team ID" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white text-xs rounded font-mono">
                @error('ruleTarget') <span class="text-rose-400 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-neutral-400 mb-1">Assigned Target Node</label>
                <select wire:model="ruleNodeId" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white text-xs rounded">
                    <option value="">Select a Node...</option>
                    @foreach($nodes as $n)
                        <option value="{{ $n->id }}">{{ $n->name }} ({{ $n->public_ip }})</option>
                    @endforeach
                </select>
                @error('ruleNodeId') <span class="text-rose-400 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg transition w-full">
                    Create Placement Rule
                </button>
            </div>
        </form>

        @if($rules->isNotEmpty())
            <div class="pt-2">
                <div class="divide-y divide-neutral-800 border border-neutral-800 rounded-lg overflow-hidden bg-neutral-950">
                    @foreach($rules as $rule)
                        <div class="p-3 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-neutral-800 text-neutral-300">
                                    {{ str_replace('_', ' ', $rule->rule_type) }}
                                </span>
                                <span class="font-mono text-emerald-400 font-bold">{{ $rule->target_identifier }}</span>
                                <span class="text-neutral-500">→</span>
                                <span class="text-white font-semibold">{{ $rule->clusterNode->name ?? 'Unknown Node' }}</span>
                            </div>
                            <button wire:click="deleteRule({{ $rule->id }})" class="text-rose-400 hover:text-rose-300 text-xs font-semibold">
                                Remove
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- Onboard Node Modal -->
    @if($showEnrollModal)
        <div class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-2xl w-full p-6 space-y-5 shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-4">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>Onboard New Worker Node (1-Line Install)</span>
                    </h3>
                    <button wire:click="closeEnrollModal" class="text-neutral-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-3 text-xs text-neutral-300 leading-relaxed">
                    <p>
                        Run this command on any fresh Ubuntu 22.04/24.04 or Debian 12 server. The installer installs Docker, creates the internal network, configures the reverse proxy, and registers the node:
                    </p>

                    <div class="relative bg-neutral-950 border border-neutral-800 rounded-xl p-4 font-mono text-[11px] text-emerald-400 break-all select-all">
                        {{ $enrollmentCommand }}
                    </div>

                    <div class="p-3 rounded-lg bg-indigo-950/30 border border-indigo-900/50 text-[11px] text-indigo-200 space-y-1">
                        <span class="font-bold block">Autonomous Telemetry Handshake:</span>
                        <p>Once the script completes on your server, the Sentinel agent will push its CPU/RAM specifications and the node will automatically transition to <strong class="text-emerald-400">READY</strong> in this dashboard.</p>
                    </div>
                </div>

                <div class="pt-3 border-t border-neutral-800 flex justify-end">
                    <button wire:click="closeEnrollModal" class="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white font-semibold text-xs rounded-lg transition">
                        Done / Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>


<div class="max-w-7xl mx-auto p-6 space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-neutral-200 dark:border-neutral-800">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold text-neutral-900 dark:text-white tracking-tight">Customer Teams Studio</h1>
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30">
                    Platform Staff
                </span>
            </div>
            <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
                Oversee all tenant teams, review customer seat allocations, and inspect workspace ownership.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.index') }}" {{ wireNavigate() }}
                class="px-3.5 py-2 rounded-lg text-xs font-medium text-neutral-700 dark:text-neutral-300 bg-neutral-100 dark:bg-white/[0.05] hover:bg-neutral-200 dark:hover:bg-white/[0.1] transition-all">
                &larr; Admin Overview
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl bg-white dark:bg-neutral-900/40 border border-neutral-200 dark:border-white/[0.06] space-y-1">
            <span class="text-neutral-400 text-xs">Total Teams</span>
            <div class="text-2xl font-bold text-neutral-900 dark:text-white">{{ $totalTeams }}</div>
        </div>
        <div class="p-4 rounded-xl bg-white dark:bg-neutral-900/40 border border-neutral-200 dark:border-white/[0.06] space-y-1">
            <span class="text-neutral-400 text-xs">Platform Users</span>
            <div class="text-2xl font-bold text-neutral-900 dark:text-white">{{ $totalMembers }}</div>
        </div>
        <div class="p-4 rounded-xl bg-white dark:bg-neutral-900/40 border border-neutral-200 dark:border-white/[0.06] space-y-1">
            <span class="text-neutral-400 text-xs">Fleet Servers</span>
            <div class="text-2xl font-bold text-neutral-900 dark:text-white">{{ $totalServers }}</div>
        </div>
        <div class="p-4 rounded-xl bg-white dark:bg-neutral-900/40 border border-neutral-200 dark:border-white/[0.06] space-y-1">
            <span class="text-neutral-400 text-xs">Active Invites</span>
            <div class="text-2xl font-bold text-neutral-900 dark:text-white">{{ $activeInvites }}</div>
        </div>
    </div>

    <!-- Search / Filter -->
    <div class="flex items-center justify-between gap-4">
        <div class="relative w-full max-w-md">
            <x-reicon name="search" class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 pointer-events-none" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search teams by name, description, or member..."
                class="w-full pl-9 pr-3 py-2 text-xs rounded-lg bg-neutral-100 dark:bg-white/[0.04] border border-neutral-200 dark:border-white/10 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <span class="text-xs text-neutral-400">
            Showing {{ $teams->count() }} of {{ $teams->total() }} teams
        </span>
    </div>

    <!-- Teams Table -->
    <div class="rounded-2xl bg-white dark:bg-neutral-900/40 border border-neutral-200 dark:border-white/[0.06] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-neutral-200 dark:border-white/5 text-[11px] font-medium text-neutral-400 uppercase tracking-wider bg-neutral-50/50 dark:bg-neutral-950/40">
                        <th class="py-3 px-4">ID</th>
                        <th class="py-3 px-4">Team</th>
                        <th class="py-3 px-4">Owner / Members</th>
                        <th class="py-3 px-4">Servers</th>
                        <th class="py-3 px-4">Created</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-white/[0.04] text-xs">
                    @forelse ($teams as $team)
                        @php
                            $owner = $team->members->first(fn ($m) => data_get($m, 'pivot.role') === 'owner') ?? $team->members->first();
                            $isRoot = $team->id === 0;
                        @endphp
                        <tr class="hover:bg-neutral-50 dark:hover:bg-white/[0.02] transition-colors">
                            <td class="py-3 px-4 font-mono text-[11px] text-neutral-400">
                                @if ($isRoot)
                                    <span class="px-1.5 py-0.5 rounded font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30">0 (Root)</span>
                                @else
                                    #{{ $team->id }}
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-neutral-900 dark:text-white">
                                    {{ $team->name }}
                                </div>
                                <div class="text-[11px] text-neutral-500 dark:text-neutral-400 truncate max-w-xs">
                                    {{ $team->description ?: 'No description' }}
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-neutral-800 dark:text-neutral-200 font-medium">
                                    {{ $owner?->name ?? 'No Owner' }}
                                </div>
                                <div class="text-[11px] text-neutral-400">
                                    {{ $team->members_count }} {{ Str::plural('member', $team->members_count) }} &bull; {{ $owner?->email ?? '-' }}
                                </div>
                            </td>
                            <td class="py-3 px-4 font-mono">
                                {{ $team->servers_count }}
                            </td>
                            <td class="py-3 px-4 text-neutral-500 dark:text-neutral-400 text-[11px]">
                                {{ $team->created_at?->format('M d, Y') ?? 'System' }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" wire:click="switchToTeam({{ $team->id }})"
                                        class="px-2.5 py-1 rounded text-[11px] font-medium text-neutral-700 dark:text-neutral-300 bg-neutral-100 dark:bg-white/[0.06] hover:bg-neutral-200 dark:hover:bg-white/[0.1] transition-all">
                                        Switch Context
                                    </button>
                                    <a href="{{ route('portal.teams.detail', ['uuid' => $team->uuid ?: ($team->id === 0 ? '00000000-0000-0000-0000-000000000000' : 'team-'.$team->id)]) }}" {{ wireNavigate() }}
                                        class="px-2.5 py-1 rounded text-[11px] font-semibold text-amber-400 hover:text-amber-300 transition-colors">
                                        Inspect
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-neutral-400">
                                No teams match your search query.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($teams->hasPages())
            <div class="p-4 border-t border-neutral-200 dark:border-white/5">
                {{ $teams->links() }}
            </div>
        @endif
    </div>
</div>

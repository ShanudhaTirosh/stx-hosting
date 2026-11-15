<div class="max-w-6xl mx-auto p-6 space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-neutral-200 dark:border-neutral-800">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold text-neutral-900 dark:text-white tracking-tight">Teams & Workspaces</h1>
                @if ($pendingInvitesCount > 0)
                    <a href="{{ route('portal.teams.invites') }}" {{ wireNavigate() }}
                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/20 hover:bg-amber-500/20 transition-colors">
                        <span class="size-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                        {{ $pendingInvitesCount }} pending {{ Str::plural('invite', $pendingInvitesCount) }}
                    </a>
                @endif
            </div>
            <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
                Collaborate with developers, manage shared servers, and assign role-based permissions.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('portal.teams.invites') }}" {{ wireNavigate() }}
                class="px-3.5 py-2 rounded-lg text-xs font-medium text-neutral-700 dark:text-neutral-300 bg-neutral-100 dark:bg-white/[0.05] hover:bg-neutral-200 dark:hover:bg-white/[0.09] transition-all flex items-center gap-1.5">
                <x-reicon name="shield-star" class="size-3.5" />
                Invites
                @if ($pendingInvitesCount > 0)
                    <span class="px-1.5 py-0.2 bg-amber-500 text-black text-[10px] font-bold rounded-full">{{ $pendingInvitesCount }}</span>
                @endif
            </a>

            <button type="button" wire:click="$set('showCreateModal', true)"
                class="px-4 py-2 rounded-lg text-xs font-semibold text-white bg-primary-600 hover:bg-primary-500 shadow-xs hover:shadow transition-all flex items-center gap-1.5">
                <x-reicon name="plus" class="size-3.5" />
                Create New Team
            </button>
        </div>
    </div>

    <!-- Filter / Search bar -->
    <div class="flex items-center justify-between gap-4">
        <div class="relative w-full max-w-sm">
            <x-reicon name="search" class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 pointer-events-none" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Filter teams..."
                class="w-full pl-9 pr-3 py-2 text-xs rounded-lg bg-neutral-100 dark:bg-white/[0.04] border border-neutral-200 dark:border-white/10 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
        </div>
        <span class="text-xs text-neutral-500 dark:text-neutral-400">
            {{ $teams->count() }} {{ Str::plural('team', $teams->count()) }}
        </span>
    </div>

    <!-- Teams Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($teams as $team)
            @php
                $isCurrent = $team->id === $currentTeamId;
                $userPivotRole = data_get($team, 'pivot.role', 'member');
                $isRootTeam = $team->id === 0;
            @endphp
            <div @class([
                'relative flex flex-col justify-between p-5 rounded-xl border transition-all duration-150',
                'bg-neutral-50/80 dark:bg-neutral-900/60 border-primary-500/40 shadow-sm ring-1 ring-primary-500/20' => $isCurrent,
                'bg-white dark:bg-neutral-900/40 border-neutral-200 dark:border-white/[0.06] hover:border-neutral-300 dark:hover:border-white/15' => ! $isCurrent,
            ])>
                <div>
                    <!-- Top row: Avatar / Sentinel badge & Role badge -->
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="size-10 rounded-lg flex items-center justify-center font-bold text-sm shrink-0 {{ $isRootTeam ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'bg-primary-500/10 text-primary-400 border border-primary-500/20' }}">
                                {{ strtoupper(substr($team->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <h3 class="font-semibold text-sm text-neutral-900 dark:text-white truncate">
                                        {{ $team->name }}
                                    </h3>
                                    @if ($isRootTeam)
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-mono font-semibold bg-amber-500/15 text-amber-400 border border-amber-500/30">Root</span>
                                    @endif
                                </div>
                                <span class="text-[11px] text-neutral-500 dark:text-neutral-400">
                                    Created {{ $team->created_at?->diffForHumans() ?? 'recently' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5">
                            @if ($isCurrent)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 flex items-center gap-1">
                                    <span class="size-1.5 rounded-full bg-emerald-400"></span> Active
                                </span>
                            @endif
                            <span @class([
                                'px-2 py-0.5 rounded-md text-[10px] font-medium uppercase tracking-wider',
                                'bg-purple-500/10 text-purple-400 border border-purple-500/20' => $userPivotRole === 'owner',
                                'bg-blue-500/10 text-blue-400 border border-blue-500/20' => $userPivotRole === 'admin',
                                'bg-neutral-500/10 text-neutral-400 border border-neutral-500/20' => $userPivotRole === 'member',
                            ])>
                                {{ $userPivotRole }}
                            </span>
                        </div>
                    </div>

                    <!-- Description -->
                    <p class="text-xs text-neutral-600 dark:text-neutral-400 line-clamp-2 min-h-8 mb-4">
                        {{ $team->description ?: 'No team description provided.' }}
                    </p>

                    <!-- Stats Row -->
                    <div class="grid grid-cols-2 gap-2 p-2.5 rounded-lg bg-neutral-100/70 dark:bg-white/[0.02] border border-neutral-200/50 dark:border-white/[0.04] text-xs mb-4">
                        <div class="flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400">
                            <x-reicon name="teams" class="size-3.5 text-neutral-400" />
                            <span>{{ $team->members_count }} {{ Str::plural('member', $team->members_count) }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400">
                            <x-reicon name="servers" class="size-3.5 text-neutral-400" />
                            <span>{{ $team->servers_count }} {{ Str::plural('server', $team->servers_count) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="flex items-center gap-2 pt-2 border-t border-neutral-200 dark:border-white/[0.05]">
                    @if (! $isCurrent)
                        <button type="button" wire:click="switchTeam({{ $team->id }})" wire:loading.attr="disabled"
                            class="flex-1 py-1.5 px-3 rounded-lg text-xs font-medium text-neutral-700 dark:text-neutral-300 bg-neutral-100 dark:bg-white/[0.05] hover:bg-neutral-200 dark:hover:bg-white/[0.1] transition-all">
                            Switch Team
                        </button>
                    @endif

                    <a href="{{ route('portal.teams.detail', ['uuid' => $team->uuid ?: ($team->id === 0 ? '00000000-0000-0000-0000-000000000000' : 'team-'.$team->id)]) }}" {{ wireNavigate() }}
                        @class([
                            'py-1.5 px-3 rounded-lg text-xs font-semibold text-center transition-all flex items-center justify-center gap-1',
                            'flex-1 bg-primary-600 hover:bg-primary-500 text-white' => $isCurrent,
                            'text-neutral-600 dark:text-neutral-400 hover:text-white px-3' => ! $isCurrent,
                        ])>
                        Manage
                        <x-reicon name="chevron-down" class="size-3 -rotate-90" />
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full p-12 text-center rounded-xl bg-neutral-50 dark:bg-neutral-900/30 border border-neutral-200 dark:border-white/5">
                <x-reicon name="teams" class="size-10 mx-auto text-neutral-400 mb-3" />
                <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">No teams match your search</h3>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1 max-w-sm mx-auto">
                    Try adjusting your filter or create a new team to collaborate with teammates.
                </p>
                <button type="button" wire:click="$set('showCreateModal', true)"
                    class="mt-4 px-4 py-2 rounded-lg text-xs font-semibold text-white bg-primary-600 hover:bg-primary-500 inline-flex items-center gap-1.5">
                    <x-reicon name="plus" class="size-3.5" />
                    Create New Team
                </button>
            </div>
        @endforelse
    </div>

    <!-- Create Team Modal -->
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
            x-data @keydown.escape.window="$wire.set('showCreateModal', false)">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6 space-y-5"
                @click.away="$wire.set('showCreateModal', false)">
                <div class="flex items-center justify-between pb-3 border-b border-neutral-200 dark:border-neutral-800">
                    <div>
                        <h2 class="text-base font-bold text-neutral-900 dark:text-white">Create a New Team</h2>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Start collaborating with teammates immediately.</p>
                    </div>
                    <button type="button" wire:click="$set('showCreateModal', false)" class="text-neutral-400 hover:text-white">
                        <x-reicon name="x" class="size-4" />
                    </button>
                </div>

                <form wire:submit="createTeam" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">Team Name <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="name" placeholder="e.g. Acme Engineering" required autofocus
                            class="w-full px-3 py-2 text-xs rounded-lg bg-neutral-100 dark:bg-white/[0.04] border border-neutral-300 dark:border-white/10 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary-500">
                        @error('name') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">Description (Optional)</label>
                        <textarea wire:model="description" rows="3" placeholder="Briefly describe what this team works on..."
                            class="w-full px-3 py-2 text-xs rounded-lg bg-neutral-100 dark:bg-white/[0.04] border border-neutral-300 dark:border-white/10 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary-500"></textarea>
                        @error('description') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-neutral-200 dark:border-neutral-800">
                        <button type="button" wire:click="$set('showCreateModal', false)"
                            class="px-3.5 py-2 rounded-lg text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-white/[0.05] transition-all">
                            Cancel
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                            class="px-4 py-2 rounded-lg text-xs font-semibold text-white bg-primary-600 hover:bg-primary-500 transition-all flex items-center gap-1.5">
                            <span wire:loading.remove>Create Team</span>
                            <span wire:loading class="flex items-center gap-1">
                                <span class="size-3 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                                Creating...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

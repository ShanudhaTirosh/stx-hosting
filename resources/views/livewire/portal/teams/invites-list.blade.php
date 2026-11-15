<div class="max-w-4xl mx-auto p-6 space-y-6">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
        <a href="{{ route('portal.teams.index') }}" {{ wireNavigate() }} class="hover:text-neutral-900 dark:hover:text-white transition-colors">Teams</a>
        <x-reicon name="chevron-down" class="size-3 -rotate-90 text-neutral-400" />
        <span class="text-neutral-900 dark:text-white font-medium">Pending Invitations</span>
    </nav>

    <!-- Header -->
    <div class="pb-4 border-b border-neutral-200 dark:border-neutral-800">
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-white tracking-tight">Pending Team Invitations</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
            Accept or decline invitations to collaborate on other teams and projects.
        </p>
    </div>

    <!-- Invites List -->
    <div class="space-y-4">
        @forelse ($invitations as $invite)
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-xl bg-white dark:bg-neutral-900/40 border border-neutral-200 dark:border-white/[0.06] hover:border-neutral-300 dark:hover:border-white/15 transition-all">
                <div class="flex items-center gap-3.5">
                    <div class="size-11 rounded-lg bg-primary-500/10 border border-primary-500/20 text-primary-400 font-bold flex items-center justify-center text-sm">
                        {{ strtoupper(substr($invite->team->name, 0, 2)) }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-neutral-900 dark:text-white text-sm">{{ $invite->team->name }}</h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-neutral-100 dark:bg-white/10 text-neutral-400">
                                Offered Role: {{ $invite->role }}
                            </span>
                        </div>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                            Invited by {{ $invite->invitedBy?->name ?? 'Team Admin' }} ({{ $invite->invitedBy?->email ?? 'admin' }}) &bull; Expires {{ $invite->expires_at?->diffForHumans() ?? 'soon' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:self-center">
                    <button type="button" wire:click="declineInvite({{ $invite->id }})" wire:loading.attr="disabled"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-medium text-neutral-600 dark:text-neutral-400 hover:text-red-400 dark:hover:text-red-400 transition-colors">
                        Decline
                    </button>
                    <button type="button" wire:click="acceptInvite({{ $invite->id }})" wire:loading.attr="disabled"
                        class="px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-primary-600 hover:bg-primary-500 shadow-xs hover:shadow transition-all flex items-center gap-1.5">
                        <x-reicon name="check" class="size-3.5" />
                        Accept Invite
                    </button>
                </div>
            </div>
        @empty
            <div class="p-12 text-center rounded-xl bg-neutral-50 dark:bg-neutral-900/30 border border-neutral-200 dark:border-white/5 space-y-2">
                <x-reicon name="shield-star" class="size-10 mx-auto text-neutral-400 mb-2" />
                <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">No pending invitations</h3>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 max-w-sm mx-auto">
                    When another team owner or manager invites your email to their workspace, the invitation will show up here.
                </p>
                <div class="pt-2">
                    <a href="{{ route('portal.teams.index') }}" {{ wireNavigate() }}
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold text-white bg-primary-600 hover:bg-primary-500">
                        Back to My Teams
                    </a>
                </div>
            </div>
        @endforelse
    </div>
</div>

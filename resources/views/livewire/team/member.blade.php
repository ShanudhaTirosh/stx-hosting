<div wire:key="team-member-row-{{ $member->id }}"
    x-cloak x-show="isMemberVisible({{ $member->id }})"
    x-bind:style="{ order: memberOrder({{ $member->id }}) }"
    @class([
        'data-table-row team-members-table-grid border-b border-neutral-200 last:border-b-0 dark:border-white/[0.07]',
        'team-members-table-grid-2fa' => auth()->user()?->can('manageMembers', currentTeam()),
    ])>
    <div>
        <div class="flex items-center gap-2">
            @if ($member->avatar_path)
                <img src="{{ profile_avatar_url($member) }}" alt="{{ $member->name }}"
                    class="size-7 shrink-0 rounded-lg object-cover">
            @else
                <div
                    class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-[11px] font-semibold text-neutral-600 dark:bg-white/[0.06] dark:text-fg-dim">
                    {{ Str::upper(Str::substr($member->name ?: $member->email, 0, 1)) }}
                </div>
            @endif
            <span class="truncate text-[13px] font-medium text-black dark:text-fg">{{ $member->name }}</span>
            @if ($member->id === Auth::id())
                <span
                    class="rounded-full bg-coollabs/10 px-1.5 py-0.5 text-[10px] font-medium text-coollabs dark:bg-brand/15 dark:text-brand-light">
                    You
                </span>
            @endif
        </div>
    </div>
    <div class="truncate text-[12px] text-neutral-500 dark:text-fg-dim">{{ $member->email }}</div>
    <div>
        @php
            $roleValue = data_get($member, 'pivot.role');
            $badgeClasses = match($roleValue) {
                'owner' => 'bg-amber-100 dark:bg-amber-500/15 text-amber-800 dark:text-amber-400 border border-amber-300 dark:border-amber-500/30',
                'admin' => 'bg-indigo-100 dark:bg-indigo-500/15 text-indigo-800 dark:text-indigo-400 border border-indigo-300 dark:border-indigo-500/30',
                'developer' => 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-800 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-500/30',
                'billing' => 'bg-cyan-100 dark:bg-cyan-500/15 text-cyan-800 dark:text-cyan-400 border border-cyan-300 dark:border-cyan-500/30',
                default => 'bg-neutral-100 dark:bg-white/[0.06] text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-white/10',
            };
        @endphp
        <button type="button"
            class="table-filter-value table-filter-badge inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium capitalize {{ $badgeClasses }}"
            title="Filter by {{ $roleValue }}"
            x-on:click="roleFilter = roleFilter === @js($roleValue) ? 'all' : @js($roleValue); page = 1">
            {{ $roleValue }}
        </button>
    </div>
    @can('manageMembers', currentTeam())
        <div class="flex items-center">
            <x-two-factor-badge :enabled="filled($member->two_factor_confirmed_at)" />
        </div>
    @endcan
    <div class="flex justify-end">
        @can('manageMembers', currentTeam())
            @if ($member->id !== Auth::id() && (! \App\Services\RbacService::isOwner($member) || \App\Services\RbacService::isOwner(Auth::user())))
                <div x-data="{ open: false }" @keydown.escape.window="open = false">
                    <button type="button" class="button h-7! px-2.5! text-[11px]!" x-ref="trigger"
                        @click="open = !open" aria-haspopup="menu" :aria-expanded="open">
                        Manage
                    </button>
                    <template x-teleport="body">
                        <div x-show="open" x-cloak role="menu" x-anchor.bottom-end.fixed.offset.4="$refs.trigger"
                            @click.outside="if (! $refs.trigger.contains($event.target)) open = false"
                            class="listbox-panel w-40! min-w-0! z-50!">
                            @if (Auth::user()->isOwner() || Auth::user()->roleInTeam(currentTeam()->id) === 'owner')
                                @if (data_get($member, 'pivot.role') !== 'owner')
                                    <button type="button" class="listbox-option justify-start!" wire:click="makeOwner"
                                        @click="open = false">
                                        Make owner
                                    </button>
                                @endif
                                @if (data_get($member, 'pivot.role') !== 'admin')
                                    <button type="button" class="listbox-option justify-start!" wire:click="makeAdmin"
                                        @click="open = false">
                                        Make admin
                                    </button>
                                @endif
                                @if (data_get($member, 'pivot.role') !== 'developer')
                                    <button type="button" class="listbox-option justify-start!" wire:click="makeDeveloper"
                                        @click="open = false">
                                        Make developer
                                    </button>
                                @endif
                                @if (data_get($member, 'pivot.role') !== 'billing')
                                    <button type="button" class="listbox-option justify-start!" wire:click="makeBilling"
                                        @click="open = false">
                                        Make billing
                                    </button>
                                @endif
                                @if (data_get($member, 'pivot.role') !== 'viewer')
                                    <button type="button" class="listbox-option justify-start!" wire:click="makeViewer"
                                        @click="open = false">
                                        Make viewer
                                    </button>
                                @endif
                                @if (data_get($member, 'pivot.role') !== 'member')
                                    <button type="button" class="listbox-option justify-start!"
                                        wire:click="makeReadonly" @click="open = false">
                                        Make member
                                    </button>
                                @endif
                            @elseif (Auth::user()->isAdmin() || Auth::user()->roleInTeam(currentTeam()->id) === 'admin')
                                @if (data_get($member, 'pivot.role') !== 'owner' && data_get($member, 'pivot.role') !== 'admin')
                                    <button type="button" class="listbox-option justify-start!" wire:click="makeDeveloper"
                                        @click="open = false">
                                        Make developer
                                    </button>
                                    <button type="button" class="listbox-option justify-start!" wire:click="makeBilling"
                                        @click="open = false">
                                        Make billing
                                    </button>
                                    <button type="button" class="listbox-option justify-start!" wire:click="makeViewer"
                                        @click="open = false">
                                        Make viewer
                                    </button>
                                    <button type="button" class="listbox-option justify-start!" wire:click="makeReadonly"
                                        @click="open = false">
                                        Make member
                                    </button>
                                @endif
                            @endif
                            <div class="my-1 border-t border-neutral-200 dark:border-white/[0.08]"></div>
                            <button type="button" class="listbox-option justify-start! text-error! hover:text-error!"
                                wire:click="remove" @click="open = false">
                                Remove member
                            </button>
                        </div>
                    </template>
                </div>
            @endif
        @endcan
    </div>
</div>

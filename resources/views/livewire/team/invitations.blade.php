<div>
    @can('manageInvitations', currentTeam())
        @if ($invitations->count() > 0)
            <x-application.settings-section title="Pending invitations"
                description="Shareable and email invitation links that are currently active." flush>
                <div class="overflow-x-auto">
                    <div class="data-table min-w-[760px] transition-opacity" wire:loading.class="opacity-50 pointer-events-none"
                        wire:target="setSort">
                        <div
                            class="data-table-header grid-cols-[minmax(12rem,1.2fr)_6rem_6rem_6rem_minmax(14rem,1.4fr)_8rem]">
                            <x-table.sort-header label="Target" asc="email-asc" desc="email-desc" action="setSort"
                                :current="$sort" />
                            <x-table.sort-header label="Method" asc="via-asc" desc="via-desc" action="setSort"
                                :current="$sort" />
                            <x-table.sort-header label="Role" asc="role-asc" desc="role-desc" first="desc"
                                action="setSort" :current="$sort" />
                            <span>Uses</span>
                            <span>Invitation link</span>
                            <span class="text-right">Actions</span>
                        </div>
                        @foreach ($sortedInvitations as $invite)
                            <div wire:key="team-invitation-{{ $invite->id }}"
                                class="data-table-row grid-cols-[minmax(12rem,1.2fr)_6rem_6rem_6rem_minmax(14rem,1.4fr)_8rem] border-b border-neutral-200 last:border-b-0 dark:border-white/[0.07]">
                                <div class="truncate text-[12px] font-medium text-black dark:text-fg">
                                    @if ($invite->email)
                                        {{ $invite->email }}
                                    @else
                                        <span class="inline-flex items-center gap-1 text-cyan-600 dark:text-cyan-400 font-semibold">
                                            <span class="size-1.5 rounded-full bg-cyan-500"></span>
                                            Shareable Link
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[12px] capitalize text-neutral-500 dark:text-fg-dim">
                                    {{ $invite->via }}
                                </div>
                                <div class="text-[12px] capitalize text-neutral-500 dark:text-fg-dim">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-neutral-100 dark:bg-white/[0.06] text-neutral-700 dark:text-neutral-300">
                                        {{ $invite->role }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-neutral-500 dark:text-fg-dim font-mono">
                                    {{ $invite->uses_count }}/{{ $invite->max_uses ?: '∞' }}
                                </div>
                                <div class="flex min-w-0 items-center gap-2">
                                    <span
                                        class="min-w-0 truncate font-mono text-[11px] text-neutral-500 dark:text-fg-dim"
                                        title="{{ $invite->link }}">{{ $invite->link }}</span>
                                    <x-copy-button :value="$invite->link" label="Copy invitation link" />
                                </div>
                                <div class="text-right flex items-center justify-end gap-1.5">
                                    @if ($invite->email && is_transactional_emails_enabled())
                                        <button type="button"
                                            class="button h-7! px-2! text-[11px]!"
                                            title="Resend invitation email"
                                            wire:click.prevent="resendInvitation({{ $invite->id }})">
                                            Resend
                                        </button>
                                    @endif
                                    <button type="button"
                                        class="button h-7! px-2.5! text-[11px]! text-error! hover:text-error!"
                                        wire:click.prevent="deleteInvitation({{ $invite->id }})">
                                        Revoke
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-application.settings-section>
        @endif
    @endcan
</div>

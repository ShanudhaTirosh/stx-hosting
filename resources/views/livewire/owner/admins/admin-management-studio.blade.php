<div>
    <x-slot:title>Staff & Admin Governance | STX Hosting</x-slot>

    <div class="mt-8 flex w-full max-w-none flex-col gap-6 lg:mt-3">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-black dark:text-white">Platform Staff & Admin Governance</h1>
                <p class="text-sm text-neutral-500 dark:text-fg-dim">Manage trusted administrators and enforce root team security invariants.</p>
            </div>
            <div class="flex items-center gap-2">
                <x-forms.button wire:click="openInviteModal">
                    <x-reicon name="plus" class="size-4" />
                    Add Staff Member
                </x-forms.button>
                <x-forms.button wire:click="openTransferModal" class="border-amber-500/50 text-amber-500 hover:bg-amber-500/10">
                    <x-reicon name="shield-star" class="size-4" />
                    Transfer Platform Ownership
                </x-forms.button>
            </div>
        </div>

        <x-callout type="info" title="Platform Security Invariants">
            <span>Root Team #0 governs platform staff. Only the platform Owner can invite, modify, or demote administrators. At least one platform Owner must always exist in the root team.</span>
        </x-callout>

        <!-- Staff Members Table -->
        <section class="application-settings-section">
            <div class="application-settings-section-header">
                <div>
                    <h2>Platform Staff Directory</h2>
                    <p>{{ $members->count() }} active staff members in Root Team #0</p>
                </div>
            </div>
            <div class="application-settings-section-body p-0!">
                <div class="data-table">
                    <div class="data-table-header grid grid-cols-12 gap-2">
                        <span class="col-span-4">Staff Member</span>
                        <span class="col-span-3">Role in Root Team</span>
                        <span class="col-span-2">2FA Status</span>
                        <span class="col-span-3 text-right">Actions</span>
                    </div>

                    @foreach ($members as $member)
                        @php
                            $role = data_get($member, 'pivot.role', 'viewer');
                            $isOwner = $role === 'owner';
                            $has2fa = $member->two_factor_confirmed_at !== null;
                        @endphp
                        <div class="data-table-row grid grid-cols-12 gap-2 border-b border-neutral-200 last:border-b-0 dark:border-white/[0.06] items-center">
                            <div class="col-span-4 min-w-0">
                                <div class="truncate text-[12px] font-semibold text-black dark:text-fg">
                                    {{ $member->name }}
                                    @if ($member->id === auth()->id())
                                        <span class="ml-1 text-[10px] text-neutral-400 font-normal">(You)</span>
                                    @endif
                                </div>
                                <div class="truncate text-[11px] text-neutral-500 dark:text-fg-dim">
                                    {{ $member->email }}
                                </div>
                            </div>

                            <div class="col-span-3">
                                <x-status-badge
                                    :status="ucfirst($role)"
                                    :type="$isOwner ? 'success' : ($role === 'admin' ? 'info' : 'neutral')" />
                            </div>

                            <div class="col-span-2 text-xs">
                                @if ($has2fa)
                                    <span class="text-emerald-500 font-medium">Enabled</span>
                                @else
                                    <span class="text-amber-500 font-medium">Pending 2FA</span>
                                @endif
                            </div>

                            <div class="col-span-3 flex justify-end gap-1">
                                @if ($member->id !== auth()->id())
                                    @if ($role !== 'admin')
                                        <button type="button" class="button button-xs" wire:click="setRole({{ $member->id }}, 'admin')">
                                            Make Admin
                                        </button>
                                    @else
                                        <button type="button" class="button button-xs" wire:click="setRole({{ $member->id }}, 'developer')">
                                            Demote
                                        </button>
                                    @endif
                                    @unless ($isOwner)
                                        <button type="button" class="button button-xs border-red-500/30 text-red-500 hover:bg-red-500/10"
                                            wire:confirm="Are you sure you want to remove {{ $member->name }} from platform staff?"
                                            wire:click="removeStaff({{ $member->id }})">
                                            Remove
                                        </button>
                                    @endunless
                                @else
                                    <span class="text-xs text-neutral-400">Current Owner</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Invite Staff Modal -->
        @if ($showInviteModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-4">
                <div class="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-3">
                        <h3 class="text-base font-bold text-neutral-900 dark:text-white">Add Platform Staff Member</h3>
                        <button wire:click="$set('showInviteModal', false)" class="text-neutral-400 hover:text-white">&times;</button>
                    </div>
                    <form wire:submit="inviteStaff" class="flex flex-col gap-4">
                        <x-forms.input wire:model="inviteName" label="Staff Name" placeholder="Full Name" required />
                        <x-forms.input wire:model="inviteEmail" label="Staff Email" type="email" placeholder="staff@example.com" required />
                        <x-forms.select wire:model="inviteRole" label="Platform Role in Team #0">
                            <option value="admin">Platform Administrator (Management Tasks)</option>
                            <option value="owner">Platform Co-Owner (Full Platform Control)</option>
                        </x-forms.select>

                        <div class="flex justify-end gap-2 mt-4">
                            <x-forms.button type="button" wire:click="$set('showInviteModal', false)">Cancel</x-forms.button>
                            <x-forms.button type="submit" class="button-primary">Add Member</x-forms.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Ownership Transfer Modal -->
        @if ($showTransferModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-4">
                <div class="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-3">
                        <h3 class="text-base font-bold text-neutral-900 dark:text-white">Transfer Platform Ownership</h3>
                        <button wire:click="$set('showTransferModal', false)" class="text-neutral-400 hover:text-white">&times;</button>
                    </div>
                    <form wire:submit="transferOwnership" class="flex flex-col gap-4">
                        <x-callout type="danger" title="Irreversible Security Action">
                            <span>Transferring platform ownership assigns primary control of the entire STX Cloud Platform to another verified administrator. Your account will be demoted to Administrator.</span>
                        </x-callout>

                        <x-forms.select wire:model="selectedSuccessorId" label="Select Successor Administrator" required>
                            <option value="">-- Choose an administrator --</option>
                            @foreach ($members->filter(fn ($m) => $m->id !== auth()->id()) as $candidate)
                                <option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>
                            @endforeach
                        </x-forms.select>

                        <x-forms.input wire:model="confirmPassword" type="password" label="Confirm Your Account Password" placeholder="••••••••" required />

                        @if (auth()->user()->two_factor_confirmed_at !== null)
                            <x-forms.input wire:model="twoFactorCode" label="Two-Factor Authentication Code (6 digits)" placeholder="123456" maxlength="6" required />
                        @endif

                        <div class="flex justify-end gap-2 mt-4">
                            <x-forms.button type="button" wire:click="$set('showTransferModal', false)">Cancel</x-forms.button>
                            <x-forms.button type="submit" class="border-red-500 bg-red-600 text-white hover:bg-red-700">Confirm Transfer</x-forms.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>

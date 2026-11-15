<div class="max-w-6xl mx-auto p-6 space-y-8">
    <!-- Breadcrumbs & Nav Back -->
    <div class="flex items-center justify-between">
        <nav class="flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
            <a href="{{ route('portal.teams.index') }}" {{ wireNavigate() }} class="hover:text-neutral-900 dark:hover:text-white transition-colors">Teams</a>
            <x-reicon name="chevron-down" class="size-3 -rotate-90 text-neutral-400" />
            <span class="text-neutral-900 dark:text-white font-medium truncate max-w-xs">{{ $team->name }}</span>
        </nav>

        <div class="flex items-center gap-2">
            @if ($team->id !== currentTeam()?->id)
                <button type="button" wire:click="$parent.switchTeam({{ $team->id }})"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium text-neutral-700 dark:text-neutral-300 bg-neutral-100 dark:bg-white/[0.05] hover:bg-neutral-200 dark:hover:bg-white/[0.1] transition-all">
                    Switch to this Team
                </button>
            @endif
            @if ($canManage)
                <button type="button" wire:click="$set('showInviteModal', true)"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-white bg-primary-600 hover:bg-primary-500 transition-all flex items-center gap-1.5">
                    <x-reicon name="plus" class="size-3.5" />
                    Invite Member
                </button>
            @endif
        </div>
    </div>

    <!-- Overview Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-6 rounded-2xl bg-neutral-100/60 dark:bg-neutral-900/60 border border-neutral-200 dark:border-white/[0.06]">
        <div class="flex items-center gap-3.5">
            <div class="size-14 rounded-xl flex items-center justify-center font-bold text-lg {{ $team->id === 0 ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'bg-primary-500/10 text-primary-400 border border-primary-500/20' }}">
                {{ strtoupper(substr($team->name, 0, 2)) }}
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-bold text-neutral-900 dark:text-white">{{ $team->name }}</h1>
                    @if ($team->id === 0)
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-amber-500/15 text-amber-400 border border-amber-500/30">Root Sentinel</span>
                    @endif
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase tracking-wider bg-neutral-200 dark:bg-white/[0.08] text-neutral-700 dark:text-neutral-300">
                        Your role: {{ $userRole }}
                    </span>
                </div>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                    {{ $team->description ?: 'No description provided.' }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-4 text-xs">
            <div class="p-3 rounded-xl bg-white dark:bg-neutral-950/60 border border-neutral-200 dark:border-white/5 min-w-28 text-center">
                <span class="text-neutral-400 text-[11px] block">Seats Used</span>
                <span class="font-bold text-sm text-neutral-900 dark:text-white">{{ $members->count() }} / {{ $team->seat_limit ?? 5 }}</span>
            </div>
            <div class="p-3 rounded-xl bg-white dark:bg-neutral-950/60 border border-neutral-200 dark:border-white/5 min-w-28 text-center">
                <span class="text-neutral-400 text-[11px] block">Servers</span>
                <span class="font-bold text-sm text-neutral-900 dark:text-white">{{ $team->servers()->count() }}</span>
            </div>
        </div>
    </div>

    <!-- Team Settings (Rename & Description) -->
    @if ($canManage)
        <div class="p-6 rounded-2xl bg-white dark:bg-neutral-900/40 border border-neutral-200 dark:border-white/[0.06] space-y-4">
            <h2 class="text-sm font-bold text-neutral-900 dark:text-white uppercase tracking-wider">Team Information</h2>
            <form wire:submit="updateDetails" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">Team Name</label>
                    <input type="text" wire:model="name" class="w-full px-3 py-2 text-xs rounded-lg bg-neutral-100 dark:bg-white/[0.04] border border-neutral-300 dark:border-white/10 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('name') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">Description</label>
                    <input type="text" wire:model="description" class="w-full px-3 py-2 text-xs rounded-lg bg-neutral-100 dark:bg-white/[0.04] border border-neutral-300 dark:border-white/10 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('description') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold text-white bg-primary-600 hover:bg-primary-500 transition-all">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- Team Members Table -->
    <div class="p-6 rounded-2xl bg-white dark:bg-neutral-900/40 border border-neutral-200 dark:border-white/[0.06] space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-neutral-900 dark:text-white uppercase tracking-wider">Team Members ({{ $members->count() }})</h2>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Manage members, update roles, or configure granular access permissions.</p>
            </div>
            @if ($canManage)
                <button type="button" wire:click="$set('showInviteModal', true)"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold text-primary-400 bg-primary-500/10 border border-primary-500/20 hover:bg-primary-500/20 transition-all flex items-center gap-1.5">
                    <x-reicon name="plus" class="size-3" />
                    Invite
                </button>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-neutral-200 dark:border-white/5 text-[11px] font-medium text-neutral-400 uppercase tracking-wider">
                        <th class="py-3 px-3">User</th>
                        <th class="py-3 px-3">Role</th>
                        <th class="py-3 px-3">Granular Access</th>
                        <th class="py-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-white/[0.04] text-xs">
                    @foreach ($members as $member)
                        @php
                            $memberRole = data_get($member, 'pivot.role');
                            $rawAccess = data_get($member, 'pivot.access');
                            $access = is_array($rawAccess) ? $rawAccess : (json_decode($rawAccess ?: '{}', true) ?: []);
                            $isMemberSelf = $member->id === auth()->id();
                        @endphp
                        <tr class="hover:bg-neutral-50 dark:hover:bg-white/[0.02] transition-colors">
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-8 rounded-full bg-neutral-200 dark:bg-white/10 flex items-center justify-center font-bold text-xs text-neutral-700 dark:text-neutral-300">
                                        {{ strtoupper(substr($member->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <span class="font-medium text-neutral-900 dark:text-white block">{{ $member->name }}</span>
                                        <span class="text-[11px] text-neutral-400">{{ $member->email }}</span>
                                    </div>
                                    @if ($isMemberSelf)
                                        <span class="px-1.5 py-0.2 rounded text-[10px] bg-neutral-100 dark:bg-white/10 text-neutral-500">You</span>
                                    @endif
                                </div>
                            </td>

                            <td class="py-3 px-3">
                                @if ($canManage && $memberRole !== 'owner' && ! $isMemberSelf)
                                    <select wire:change="updateMemberRole({{ $member->id }}, $event.target.value)"
                                        class="px-2 py-1 text-xs rounded bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-white/10 text-neutral-800 dark:text-neutral-200 focus:outline-none">
                                        <option value="member" @selected($memberRole === 'member')>Member</option>
                                        <option value="admin" @selected($memberRole === 'admin')>Admin</option>
                                    </select>
                                @else
                                    <span @class([
                                        'px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider',
                                        'bg-purple-500/15 text-purple-400 border border-purple-500/30' => $memberRole === 'owner',
                                        'bg-blue-500/15 text-blue-400 border border-blue-500/30' => $memberRole === 'admin',
                                        'bg-neutral-500/15 text-neutral-400 border border-neutral-500/30' => $memberRole === 'member',
                                    ])>
                                        {{ $memberRole }}
                                    </span>
                                @endif
                            </td>

                            <td class="py-3 px-3">
                                @if ($memberRole === 'member')
                                    <div class="flex flex-wrap items-center gap-2">
                                        @foreach (['start_stop' => 'Start/Stop', 'console' => 'Console', 'files' => 'Files', 'backups' => 'Backups'] as $permKey => $permLabel)
                                            @php $granted = (bool) ($access[$permKey] ?? false); @endphp
                                            @if ($canManage)
                                                <button type="button" wire:click="updateMemberAccess({{ $member->id }}, '{{ $permKey }}', {{ $granted ? 'false' : 'true' }})"
                                                    @class([
                                                        'px-2 py-0.5 rounded text-[10px] font-medium transition-all',
                                                        'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/25' => $granted,
                                                        'bg-neutral-100 dark:bg-white/[0.04] text-neutral-400 border border-neutral-200 dark:border-white/5 hover:text-white' => ! $granted,
                                                    ])>
                                                    {{ $permLabel }}
                                                </button>
                                            @else
                                                <span @class([
                                                    'px-2 py-0.5 rounded text-[10px] font-medium',
                                                    'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' => $granted,
                                                    'bg-neutral-100 dark:bg-white/[0.04] text-neutral-400 opacity-60' => ! $granted,
                                                ])>
                                                    {{ $permLabel }}
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-[11px] text-neutral-400 italic">Full team privileges</span>
                                @endif
                            </td>

                            <td class="py-3 px-3 text-right">
                                @if ($isMemberSelf && $memberRole !== 'owner')
                                    <button type="button" wire:click="removeMember({{ $member->id }})" wire:confirm="Are you sure you want to leave this team?"
                                        class="text-xs text-red-400 hover:text-red-300 transition-colors">
                                        Leave Team
                                    </button>
                                @elseif ($canManage && $memberRole !== 'owner')
                                    <button type="button" wire:click="removeMember({{ $member->id }})" wire:confirm="Are you sure you want to remove {{ $member->name }}?"
                                        class="text-xs text-neutral-400 hover:text-red-400 transition-colors">
                                        Remove
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pending Invitations -->
    @if ($canManage)
        <div class="p-6 rounded-2xl bg-white dark:bg-neutral-900/40 border border-neutral-200 dark:border-white/[0.06] space-y-4">
            <h2 class="text-sm font-bold text-neutral-900 dark:text-white uppercase tracking-wider">Pending Invitations ({{ $invitations->count() }})</h2>

            @if ($invitations->isEmpty())
                <p class="text-xs text-neutral-500 dark:text-neutral-400">No pending invitations for this team.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-neutral-200 dark:border-white/5 text-[11px] font-medium text-neutral-400 uppercase tracking-wider">
                                <th class="py-2.5 px-3">Invitee</th>
                                <th class="py-2.5 px-3">Offered Role</th>
                                <th class="py-2.5 px-3">Invited By</th>
                                <th class="py-2.5 px-3">Expires</th>
                                <th class="py-2.5 px-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-white/[0.04] text-xs">
                            @foreach ($invitations as $invitation)
                                <tr>
                                    <td class="py-2.5 px-3 font-medium text-neutral-900 dark:text-white">
                                        {{ $invitation->email }}
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-neutral-100 dark:bg-white/10 text-neutral-400">
                                            {{ $invitation->role }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-neutral-500">
                                        {{ $invitation->invitedBy?->name ?? 'Admin' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-neutral-500 font-mono text-[11px]">
                                        {{ $invitation->expires_at?->diffForHumans() ?? 'In 7 days' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        <button type="button" wire:click="revokeInvite({{ $invitation->id }})"
                                            class="text-xs text-red-400 hover:underline">
                                            Revoke
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    <!-- Danger Zone / Ownership Transfer -->
    @if ($isOwner && $team->id !== 0)
        <div class="p-6 rounded-2xl bg-red-950/20 border border-red-500/20 space-y-4">
            <h2 class="text-sm font-bold text-red-400 uppercase tracking-wider">Danger Zone</h2>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 py-3 border-b border-red-500/10">
                <div>
                    <h3 class="text-xs font-bold text-neutral-900 dark:text-white">Transfer Team Ownership</h3>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">Transfer full ownership of this team to another team member.</p>
                </div>
                <button type="button" wire:click="$set('showTransferModal', true)"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-neutral-800 dark:text-neutral-200 bg-neutral-200 dark:bg-white/10 hover:bg-neutral-300 dark:hover:bg-white/20 transition-all">
                    Transfer Ownership
                </button>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 py-3">
                <div>
                    <h3 class="text-xs font-bold text-red-500 dark:text-red-400">Delete this Team</h3>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">Permanently delete this team and associated permissions. This action cannot be undone.</p>
                </div>
                <button type="button" wire:click="$set('showDeleteModal', true)"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-white bg-red-600 hover:bg-red-500 transition-all">
                    Delete Team
                </button>
            </div>
        </div>
    @endif

    <!-- Invite Modal -->
    @if ($showInviteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
            x-data @keydown.escape.window="$wire.set('showInviteModal', false)">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6 space-y-4"
                @click.away="$wire.set('showInviteModal', false)">
                <div class="flex items-center justify-between pb-3 border-b border-neutral-200 dark:border-neutral-800">
                    <h2 class="text-base font-bold text-neutral-900 dark:text-white">Invite Team Member</h2>
                    <button type="button" wire:click="$set('showInviteModal', false)" class="text-neutral-400 hover:text-white">
                        <x-reicon name="x" class="size-4" />
                    </button>
                </div>

                @if ($generatedShareLink)
                    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 space-y-2">
                        <span class="text-xs font-bold text-emerald-400 block">Invitation created! Share this link:</span>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly value="{{ $generatedShareLink }}" id="shareLinkInput"
                                class="w-full px-2.5 py-1.5 text-xs font-mono rounded bg-black/40 border border-white/10 text-white select-all">
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('shareLinkInput').value); this.innerText = 'Copied!';"
                                class="px-3 py-1.5 text-xs font-semibold rounded bg-emerald-600 text-white shrink-0">
                                Copy
                            </button>
                        </div>
                    </div>
                @endif

                <form wire:submit="sendInvite" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">Email Address</label>
                        <input type="email" wire:model="inviteEmail" placeholder="colleague@example.com" required
                            class="w-full px-3 py-2 text-xs rounded-lg bg-neutral-100 dark:bg-white/[0.04] border border-neutral-300 dark:border-white/10 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary-500">
                        @error('inviteEmail') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">Role</label>
                            <select wire:model.live="inviteRole" class="w-full px-3 py-2 text-xs rounded-lg bg-neutral-100 dark:bg-white/[0.04] border border-neutral-300 dark:border-white/10 text-neutral-900 dark:text-white focus:outline-none">
                                <option value="member">Member</option>
                                <option value="admin">Admin / Manager</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">Expires in</label>
                            <select wire:model="inviteExpiresInDays" class="w-full px-3 py-2 text-xs rounded-lg bg-neutral-100 dark:bg-white/[0.04] border border-neutral-300 dark:border-white/10 text-neutral-900 dark:text-white focus:outline-none">
                                <option value="1">1 Day</option>
                                <option value="7">7 Days</option>
                                <option value="30">30 Days</option>
                            </select>
                        </div>
                    </div>

                    @if ($inviteRole === 'member')
                        <div class="p-3 rounded-lg bg-neutral-100/70 dark:bg-white/[0.02] border border-neutral-200/50 dark:border-white/5 space-y-2">
                            <span class="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block">Granular Permissions:</span>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="inviteAccess.start_stop" class="rounded text-primary-600">
                                    <span>Start / Stop Servers</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="inviteAccess.console" class="rounded text-primary-600">
                                    <span>Web Terminal</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="inviteAccess.files" class="rounded text-primary-600">
                                    <span>File Browser</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="inviteAccess.backups" class="rounded text-primary-600">
                                    <span>Manage Backups</span>
                                </label>
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-neutral-200 dark:border-neutral-800">
                        <button type="button" wire:click="$set('showInviteModal', false)"
                            class="px-3.5 py-2 rounded-lg text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-white/[0.05]">
                            Close
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold text-white bg-primary-600 hover:bg-primary-500">
                            Send Invitation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Transfer Ownership Modal -->
    @if ($showTransferModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
            x-data @keydown.escape.window="$wire.set('showTransferModal', false)">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6 space-y-4"
                @click.away="$wire.set('showTransferModal', false)">
                <h2 class="text-base font-bold text-neutral-900 dark:text-white">Transfer Team Ownership</h2>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                    Select a member to become the new owner of <strong>{{ $team->name }}</strong>. You will be demoted to an Admin.
                </p>

                <div class="space-y-3">
                    <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Choose New Owner</label>
                    <select wire:model="selectedTransferUserId" class="w-full px-3 py-2 text-xs rounded-lg bg-neutral-100 dark:bg-white/[0.04] border border-neutral-300 dark:border-white/10 text-neutral-900 dark:text-white focus:outline-none">
                        <option value="">Select a member...</option>
                        @foreach ($members->where('id', '!=', auth()->id()) as $candidate)
                            <option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                    <button type="button" wire:click="$set('showTransferModal', false)"
                        class="px-3.5 py-2 rounded-lg text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-white/[0.05]">
                        Cancel
                    </button>
                    <button type="button" wire:click="transferOwnership"
                        class="px-4 py-2 rounded-lg text-xs font-semibold text-white bg-red-600 hover:bg-red-500">
                        Confirm Transfer
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Team Modal -->
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
            x-data @keydown.escape.window="$wire.set('showDeleteModal', false)">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-neutral-900 border border-red-500/30 shadow-2xl p-6 space-y-4"
                @click.away="$wire.set('showDeleteModal', false)">
                <h2 class="text-base font-bold text-red-500">Delete Team</h2>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                    Are you completely sure? To confirm deletion, type <strong>{{ $team->name }}</strong> below:
                </p>

                @if (! empty($blockers))
                    <div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 text-xs text-amber-400 space-y-1">
                        <span class="font-bold">Active resources prevent deletion:</span>
                        <ul class="list-disc pl-4 space-y-0.5">
                            @foreach ($blockers as $blockerType => $blockerCount)
                                <li>{{ $blockerCount }} {{ $blockerType }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <input type="text" wire:model="confirmDeleteName" placeholder="Type team name..."
                        class="w-full px-3 py-2 text-xs rounded-lg bg-neutral-100 dark:bg-white/[0.04] border border-neutral-300 dark:border-white/10 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-red-500">
                    @error('confirmDeleteName') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                    <button type="button" wire:click="$set('showDeleteModal', false)"
                        class="px-3.5 py-2 rounded-lg text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-white/[0.05]">
                        Cancel
                    </button>
                    <button type="button" wire:click="deleteTeam" @disabled(! empty($blockers))
                        class="px-4 py-2 rounded-lg text-xs font-semibold text-white bg-red-600 hover:bg-red-500 disabled:opacity-50">
                        Permanently Delete
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

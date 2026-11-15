<?php

namespace App\Livewire\Portal\Teams;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class TeamDetail extends Component
{
    public Team $team;

    public string $name = '';

    public string $description = '';

    // Modals
    public bool $showInviteModal = false;

    public bool $showTransferModal = false;

    public bool $showDeleteModal = false;

    // Invite fields
    public string $inviteEmail = '';

    public string $inviteRole = 'member';

    public array $inviteAccess = [
        'start_stop' => true,
        'console' => false,
        'files' => false,
        'backups' => false,
        'settings' => false,
    ];

    public int $inviteExpiresInDays = 7;

    public ?string $generatedShareLink = null;

    // Transfer fields
    public ?int $selectedTransferUserId = null;

    // Delete fields
    public string $confirmDeleteName = '';

    public function mount(string $uuid)
    {
        $this->team = Team::where('uuid', $uuid)
            ->orWhere('id', str_replace('team-', '', $uuid))
            ->firstOrFail();

        $user = Auth::user();
        if (! $this->team->members()->where('users.id', $user->id)->exists() && ! RbacService::isStaff($user)) {
            abort(403, 'You do not have permission to access this team.');
        }

        $this->name = $this->team->name;
        $this->description = $this->team->description ?? '';
    }

    public function updateDetails()
    {
        $user = Auth::user();
        if (! $this->team->canBeManagedBy($user)) {
            $this->dispatch('error', 'You are not authorized to update team settings.');

            return;
        }

        $this->validate([
            'name' => 'required|string|min:2|max:64',
            'description' => 'nullable|string|max:255',
        ]);

        $this->team->update([
            'name' => trim($this->name),
            'description' => trim($this->description) ?: null,
        ]);

        $this->dispatch('success', 'Team details updated successfully!');
    }

    public function sendInvite()
    {
        $user = Auth::user();
        if (! $this->team->canBeManagedBy($user)) {
            $this->dispatch('error', 'Only team admins or owners can invite new members.');

            return;
        }

        if ($this->team->hasReachedSeatLimit()) {
            $this->dispatch('error', 'Seat limit reached for this team. Upgrade subscription to invite more members.');

            return;
        }

        $this->validate([
            'inviteEmail' => 'required|email|max:255',
            'inviteRole' => 'required|in:admin,member',
            'inviteExpiresInDays' => 'required|integer|in:1,7,30,90',
        ]);

        $email = strtolower(trim($this->inviteEmail));

        // Check if already a member
        $existingUser = User::where('email', $email)->first();
        if ($existingUser && $this->team->members()->where('users.id', $existingUser->id)->exists()) {
            $this->addError('inviteEmail', 'This user is already a member of this team.');

            return;
        }

        // Check if invitation already pending
        if ($this->team->invitations()->where('email', $email)->exists()) {
            $this->addError('inviteEmail', 'An active invitation has already been sent to this email.');

            return;
        }

        $uuid = (string) Str::uuid();
        $invitation = TeamInvitation::create([
            'team_id' => $this->team->id,
            'invited_by_user_id' => $user->id,
            'target_user_id' => $existingUser?->id,
            'uuid' => $uuid,
            'email' => $email,
            'role' => $this->inviteRole,
            'access' => $this->inviteRole === 'member' ? $this->inviteAccess : null,
            'max_uses' => 1,
            'uses_count' => 0,
            'expires_at' => now()->addDays($this->inviteExpiresInDays),
            'link' => route('team.invitation.show', ['uuid' => $uuid]),
        ]);

        $this->generatedShareLink = route('team.invitation.show', ['uuid' => $uuid]);
        $this->reset(['inviteEmail', 'inviteRole']);
        $this->dispatch('success', "Invitation created for {$email}!");
    }

    public function revokeInvite(int $inviteId)
    {
        $user = Auth::user();
        if (! $this->team->canBeManagedBy($user)) {
            $this->dispatch('error', 'Unauthorized.');

            return;
        }

        $invite = $this->team->invitations()->find($inviteId);
        if ($invite) {
            $invite->delete();
            $this->dispatch('success', 'Invitation revoked.');
        }
    }

    public function updateMemberRole(int $userId, string $role)
    {
        $currentUser = Auth::user();
        if (! $this->team->canBeManagedBy($currentUser)) {
            $this->dispatch('error', 'Unauthorized.');

            return;
        }

        if (! in_array($role, ['admin', 'member'], true)) {
            $this->dispatch('error', 'Invalid role selected.');

            return;
        }

        // Target cannot be team owner
        $targetRole = $this->team->userRole(User::find($userId));
        if ($targetRole === 'owner') {
            $this->dispatch('error', 'Cannot alter the team owner role directly. Use Transfer Ownership instead.');

            return;
        }

        $this->team->members()->updateExistingPivot($userId, ['role' => $role]);
        $this->dispatch('success', 'Member role updated.');
    }

    public function updateMemberAccess(int $userId, string $permission, bool $value)
    {
        $currentUser = Auth::user();
        if (! $this->team->canBeManagedBy($currentUser)) {
            $this->dispatch('error', 'Unauthorized.');

            return;
        }

        $member = $this->team->members()->where('users.id', $userId)->first();
        if (! $member) {
            return;
        }

        $rawAccess = data_get($member, 'pivot.access');
        $access = is_array($rawAccess) ? $rawAccess : (json_decode($rawAccess ?: '{}', true) ?: []);
        $access[$permission] = $value;

        $this->team->members()->updateExistingPivot($userId, ['access' => json_encode($access)]);
        $this->dispatch('success', 'Permissions updated.');
    }

    public function removeMember(int $userId)
    {
        $currentUser = Auth::user();
        $isSelf = $currentUser->id === $userId;

        if (! $isSelf && ! $this->team->canBeManagedBy($currentUser)) {
            $this->dispatch('error', 'Unauthorized.');

            return;
        }

        $targetUser = User::findOrFail($userId);
        $role = $this->team->userRole($targetUser);

        if ($role === 'owner') {
            $this->dispatch('error', 'The team owner cannot be removed. Transfer ownership or delete the team.');

            return;
        }

        $this->team->members()->detach($userId);

        if ($isSelf) {
            refreshSession();
            $this->dispatch('success', "You have left '{$this->team->name}'.");

            return redirect()->route('portal.teams.index');
        }

        $this->dispatch('success', "{$targetUser->name} removed from team.");
    }

    public function transferOwnership()
    {
        $currentUser = Auth::user();
        if (! $this->team->canBeDeletedBy($currentUser)) {
            $this->dispatch('error', 'Only the current team owner can transfer ownership.');

            return;
        }

        if (! $this->selectedTransferUserId) {
            $this->dispatch('error', 'Please select a recipient member.');

            return;
        }

        $newOwner = User::findOrFail($this->selectedTransferUserId);

        try {
            $this->team->transferOwnership($newOwner, $currentUser);
            $this->showTransferModal = false;
            $this->dispatch('success', "Ownership transferred to {$newOwner->name}!");
        } catch (\Throwable $e) {
            $this->dispatch('error', $e->getMessage());
        }
    }

    public function deleteTeam()
    {
        $currentUser = Auth::user();
        if (! $this->team->canBeDeletedBy($currentUser)) {
            $this->dispatch('error', 'Only the team owner can delete this team.');

            return;
        }

        if ($this->team->id === 0) {
            $this->dispatch('error', 'The root team cannot be deleted.');

            return;
        }

        if (trim($this->confirmDeleteName) !== $this->team->name) {
            $this->addError('confirmDeleteName', 'The confirmation name does not match.');

            return;
        }

        $teamName = $this->team->name;
        $this->team->delete();

        refreshSession();
        $this->dispatch('success', "Team '{$teamName}' permanently deleted.");

        return redirect()->route('portal.teams.index');
    }

    public function render()
    {
        $user = Auth::user();
        $userRole = $this->team->userRole($user);
        $canManage = $this->team->canBeManagedBy($user);
        $isOwner = $this->team->canBeDeletedBy($user);

        $members = $this->team->members()->withPivot('role', 'access')->get();
        $invitations = $this->team->invitations()->with('invitedBy')->latest()->get();
        $blockers = $this->team->deletionBlockers();

        return view('livewire.portal.teams.team-detail', [
            'members' => $members,
            'invitations' => $invitations,
            'userRole' => $userRole,
            'canManage' => $canManage,
            'isOwner' => $isOwner,
            'blockers' => $blockers,
        ]);
    }
}

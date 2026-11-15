<?php

namespace App\Livewire\Owner\Admins;

use App\Models\Team;
use App\Models\User;
use App\Services\Auth\TwoFactorVerificationService;
use App\Services\RbacService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;

class AdminManagementStudio extends Component
{
    // New Admin Invite Form
    public bool $showInviteModal = false;

    public string $inviteEmail = '';

    public string $inviteName = '';

    public string $inviteRole = 'admin'; // admin or owner

    // Ownership Transfer Form
    public bool $showTransferModal = false;

    public ?int $selectedSuccessorId = null;

    public string $confirmPassword = '';

    public string $twoFactorCode = '';

    public function boot(): void
    {
        abort_unless(RbacService::isPlatformOwner(Auth::user()), 403, 'This area is restricted to the platform owner.');
    }

    public function openInviteModal(): void
    {
        $this->reset(['inviteEmail', 'inviteName', 'inviteRole']);
        $this->inviteRole = 'admin';
        $this->showInviteModal = true;
    }

    public function inviteStaff(): void
    {
        $this->validate([
            'inviteEmail' => 'required|email|max:255',
            'inviteName' => 'required|string|min:2|max:100',
            'inviteRole' => 'required|in:admin,owner',
        ]);

        $rootTeam = Team::find(0);
        if (! $rootTeam) {
            $this->dispatch('error', 'Root team (id = 0) does not exist.');

            return;
        }

        // Find or create user
        $user = User::where('email', strtolower(trim($this->inviteEmail)))->first();
        if (! $user) {
            $user = User::create([
                'name' => trim($this->inviteName),
                'email' => strtolower(trim($this->inviteEmail)),
                'password' => Hash::make(Str::random(32)),
            ]);
        }

        // Attach or update in Team 0
        if ($rootTeam->members()->where('users.id', $user->id)->exists()) {
            $rootTeam->members()->updateExistingPivot($user->id, ['role' => $this->inviteRole]);
        } else {
            $rootTeam->members()->attach($user->id, ['role' => $this->inviteRole]);
        }

        auditLog('platform.staff.invited', [
            'team_id' => 0,
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'role' => $this->inviteRole,
            'invited_by' => Auth::id(),
        ]);

        $this->showInviteModal = false;
        $this->dispatch('success', "Staff member '{$user->name}' granted role {$this->inviteRole} in platform root team.");
    }

    public function setRole(int $userId, string $role): void
    {
        $this->validate([
            'role' => 'required|in:admin,developer,viewer',
        ]);

        $rootTeam = Team::findOrFail(0);
        $user = User::findOrFail($userId);

        if ($userId === Auth::id() && $role !== 'owner') {
            $this->dispatch('error', 'You cannot demote yourself. Use ownership transfer instead.');

            return;
        }

        // Check minimum owners invariant
        $rootTeam->assertMinimumOwnersRemaining($userId);

        $rootTeam->members()->updateExistingPivot($userId, ['role' => $role]);

        auditLog('platform.staff.role_updated', [
            'team_id' => 0,
            'target_user_id' => $user->id,
            'new_role' => $role,
            'updated_by' => Auth::id(),
        ]);

        $this->dispatch('success', "Role for {$user->name} updated to {$role}.");
    }

    public function removeStaff(int $userId): void
    {
        $rootTeam = Team::findOrFail(0);
        $user = User::findOrFail($userId);

        if ($userId === Auth::id()) {
            $this->dispatch('error', 'You cannot remove yourself from the platform root team.');

            return;
        }

        // Check minimum owners invariant
        $rootTeam->assertMinimumOwnersRemaining($userId);

        $rootTeam->members()->detach($userId);

        auditLog('platform.staff.removed', [
            'team_id' => 0,
            'target_user_id' => $user->id,
            'removed_by' => Auth::id(),
        ]);

        $this->dispatch('success', "{$user->name} removed from platform staff.");
    }

    public function openTransferModal(): void
    {
        $this->reset(['selectedSuccessorId', 'confirmPassword', 'twoFactorCode']);
        $this->showTransferModal = true;
    }

    public function transferOwnership(): void
    {
        $this->validate([
            'selectedSuccessorId' => 'required|exists:users,id',
            'confirmPassword' => 'required|string',
        ]);

        $currentUser = Auth::user();

        // 1. Validate password
        if (! Hash::check($this->confirmPassword, $currentUser->password)) {
            $this->addError('confirmPassword', 'Incorrect account password.');

            return;
        }

        // 2. Validate 2FA if configured
        if ($currentUser->two_factor_confirmed_at !== null) {
            $this->validate(['twoFactorCode' => 'required|string|size:6']);
            $valid = app(TwoFactorVerificationService::class)
                ->verifyCode($currentUser, $this->twoFactorCode);
            if (! $valid) {
                $this->addError('twoFactorCode', 'Invalid two-factor authentication code.');

                return;
            }
        }

        $rootTeam = Team::findOrFail(0);
        $newOwner = User::findOrFail($this->selectedSuccessorId);

        try {
            $rootTeam->transferRootTeamOwnership($newOwner, $currentUser);
            $this->showTransferModal = false;
            $this->dispatch('success', "Platform ownership successfully transferred to {$newOwner->name}!");
            $this->redirectRoute('owner.admins');
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Transfer failed: '.$e->getMessage());
        }
    }

    public function render()
    {
        $rootTeam = Team::find(0);
        $members = $rootTeam ? $rootTeam->members()->get() : collect();

        return view('livewire.owner.admins.admin-management-studio', [
            'members' => $members,
            'rootTeam' => $rootTeam,
        ])->layout('layouts.app');
    }
}

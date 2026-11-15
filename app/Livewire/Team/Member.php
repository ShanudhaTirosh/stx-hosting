<?php

namespace App\Livewire\Team;

use App\Actions\User\RevokeUserTeamTokens;
use App\Enums\Role;
use App\Models\User;
use App\Services\RbacService;
use App\Services\Supabase\TeamSyncService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Member extends Component
{
    use AuthorizesRequests;

    public User $member;

    public function makeAdmin()
    {
        try {
            $this->authorize('manageMembers', currentTeam());

            if (RbacService::isOwner($this->member) && currentTeam()->id === 0) {
                throw new \Exception('The platform Owner account cannot be modified.');
            }

            if (Role::from(auth()->user()->role())->lt(Role::ADMIN)
                || Role::from($this->getMemberRole())->gt(auth()->user()->role())) {
                throw new \Exception('You are not authorized to perform this action.');
            }
            $this->updateRole(Role::ADMIN);
            $this->dispatch('reloadWindow');
        } catch (\Exception $e) {
            $this->dispatch('error', $e->getMessage());
        }
    }

    public function makeDeveloper()
    {
        try {
            $this->authorize('manageMembers', currentTeam());

            if (RbacService::isOwner($this->member) && currentTeam()->id === 0) {
                throw new \Exception('The platform Owner account cannot be modified.');
            }

            if (Role::from(auth()->user()->role())->lt(Role::ADMIN)
                || Role::from($this->getMemberRole())->gt(auth()->user()->role())) {
                throw new \Exception('You are not authorized to perform this action.');
            }
            $this->updateRole(Role::DEVELOPER);
            $this->dispatch('reloadWindow');
        } catch (\Exception $e) {
            $this->dispatch('error', $e->getMessage());
        }
    }

    public function makeBilling()
    {
        try {
            $this->authorize('manageMembers', currentTeam());

            if (RbacService::isOwner($this->member) && currentTeam()->id === 0) {
                throw new \Exception('The platform Owner account cannot be modified.');
            }

            if (Role::from(auth()->user()->role())->lt(Role::ADMIN)
                || Role::from($this->getMemberRole())->gt(auth()->user()->role())) {
                throw new \Exception('You are not authorized to perform this action.');
            }
            $this->updateRole(Role::BILLING);
            $this->dispatch('reloadWindow');
        } catch (\Exception $e) {
            $this->dispatch('error', $e->getMessage());
        }
    }

    public function makeViewer()
    {
        try {
            $this->authorize('manageMembers', currentTeam());

            if (RbacService::isOwner($this->member) && currentTeam()->id === 0) {
                throw new \Exception('The platform Owner account cannot be modified.');
            }

            if (Role::from(auth()->user()->role())->lt(Role::ADMIN)
                || Role::from($this->getMemberRole())->gt(auth()->user()->role())) {
                throw new \Exception('You are not authorized to perform this action.');
            }
            $this->updateRole(Role::VIEWER);
            $this->dispatch('reloadWindow');
        } catch (\Exception $e) {
            $this->dispatch('error', $e->getMessage());
        }
    }

    public function makeOwner()
    {
        try {
            $this->authorize('manageMembers', currentTeam());

            $isRootTeam = currentTeam()->id === 0;
            if ($isRootTeam && ! RbacService::isOwner(auth()->user())) {
                throw new \Exception('Only the platform Owner can transfer or assign the Owner role in the root team.');
            }
            if (! $isRootTeam && auth()->user()->roleInTeam(currentTeam()->id) !== 'owner') {
                throw new \Exception('Only the team Owner can transfer or assign the Owner role.');
            }

            if (Role::from(auth()->user()->role())->lt(Role::OWNER)
                || Role::from($this->getMemberRole())->gt(auth()->user()->role())) {
                throw new \Exception('You are not authorized to perform this action.');
            }
            $this->updateRole(Role::OWNER);
            $this->dispatch('reloadWindow');
        } catch (\Exception $e) {
            $this->dispatch('error', $e->getMessage());
        }
    }

    public function makeReadonly()
    {
        try {
            $this->authorize('manageMembers', currentTeam());

            if (RbacService::isOwner($this->member) && currentTeam()->id === 0) {
                throw new \Exception('The platform Owner account cannot be modified or demoted.');
            }

            if (Role::from(auth()->user()->role())->lt(Role::ADMIN)
                || Role::from($this->getMemberRole())->gt(auth()->user()->role())) {
                throw new \Exception('You are not authorized to perform this action.');
            }
            $this->updateRole(Role::MEMBER);
            $this->dispatch('reloadWindow');
        } catch (\Exception $e) {
            $this->dispatch('error', $e->getMessage());
        }
    }

    public function setRole(string $roleValue)
    {
        match ($roleValue) {
            'owner' => $this->makeOwner(),
            'admin' => $this->makeAdmin(),
            'developer' => $this->makeDeveloper(),
            'billing' => $this->makeBilling(),
            'viewer' => $this->makeViewer(),
            default => $this->makeReadonly(),
        };
    }

    public function remove()
    {
        try {
            $this->authorize('manageMembers', currentTeam());

            if (RbacService::isOwner($this->member) && currentTeam()->id === 0) {
                throw new \Exception('The platform Owner account cannot be removed.');
            }

            if (Role::from(auth()->user()->role())->lt(Role::ADMIN)
                || Role::from($this->getMemberRole())->gt(auth()->user()->role())) {
                throw new \Exception('You are not authorized to perform this action.');
            }
            $team = currentTeam();
            $teamId = $team->id;
            DB::transaction(function () use ($teamId): void {
                $this->member->teams()->detach($teamId);
                RevokeUserTeamTokens::forUserTeam($this->member, $teamId);
                $this->member->clearStoredTeamIfMatches($teamId);
            });

            // Sync removal to Supabase
            app(TeamSyncService::class)->removeMember($team, $this->member);

            auditLog('ui.team_member.removed', [
                'team_id' => $teamId,
                'member_id' => $this->member->id,
                'member_name' => $this->member->name,
                'member_email' => $this->member->email,
            ]);

            Cache::forget("team:{$this->member->id}");
            Cache::forget("user:{$this->member->id}:team:{$teamId}");
            $this->dispatch('reloadWindow');
        } catch (\Exception $e) {
            $this->dispatch('error', $e->getMessage());
        }
    }

    private function getMemberRole()
    {
        return $this->member->teams()->where('teams.id', currentTeam()->id)->first()?->pivot?->role;
    }

    private function updateRole(Role $role): void
    {
        $team = currentTeam();
        $teamId = $team->id;
        $previousRole = Role::from($this->getMemberRole());
        DB::transaction(function () use ($teamId, $role, $previousRole): void {
            $this->member->teams()->updateExistingPivot($teamId, ['role' => $role->value]);
            if ($role->lt($previousRole)) {
                RevokeUserTeamTokens::forUserTeam($this->member, $teamId);
            }
        });

        // Sync to Supabase
        app(TeamSyncService::class)->syncMember($team, $this->member, $role->value);

        auditLog('ui.team_member.role_updated', [
            'team_id' => $teamId,
            'member_id' => $this->member->id,
            'member_name' => $this->member->name,
            'member_email' => $this->member->email,
            'role' => $role->value,
        ]);
    }
}

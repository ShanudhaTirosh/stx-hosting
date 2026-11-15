<?php

namespace App\Livewire\Team;

use App\Actions\Team\DeleteTeam;
use App\Actions\User\RevokeUserTeamTokens;
use App\Models\Team;
use App\Services\RbacService;
use App\Services\Supabase\TeamSyncService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class DangerZone extends Component
{
    use AuthorizesRequests;

    public Team $team;

    public ?int $selectedNewOwnerId = null;

    public function mount(): void
    {
        $this->team = currentTeam();
        $this->authorize('view', $this->team);
    }

    public function transferOwnership(): mixed
    {
        try {
            $currentTeam = currentTeam();
            $this->authorize('transferOwnership', $currentTeam);

            if (! $this->selectedNewOwnerId) {
                throw new \Exception('Please select a member to transfer ownership to.');
            }

            if ($this->selectedNewOwnerId === auth()->id()) {
                throw new \Exception('You are already the owner of this team.');
            }

            $newOwner = $currentTeam->members()->where('users.id', $this->selectedNewOwnerId)->first();
            if (! $newOwner) {
                throw new \Exception('Selected member was not found in this team.');
            }

            $isRootTeam = $currentTeam->id === 0;
            if ($isRootTeam && ! RbacService::isOwner(auth()->user())) {
                throw new \Exception('Only the platform Owner can transfer ownership in the root team.');
            }

            DB::transaction(function () use ($currentTeam, $newOwner): void {
                $currentTeam->members()->updateExistingPivot(auth()->id(), ['role' => 'admin']);
                $currentTeam->members()->updateExistingPivot($newOwner->id, ['role' => 'owner']);
            });

            app(TeamSyncService::class)->syncMember($currentTeam, auth()->user(), 'admin');
            app(TeamSyncService::class)->syncMember($currentTeam, $newOwner, 'owner');

            auditLog('ui.team.ownership_transferred', [
                'team_id' => $currentTeam->id,
                'previous_owner_id' => auth()->id(),
                'new_owner_id' => $newOwner->id,
            ]);

            refreshSession($currentTeam);
            $this->dispatch('success', "Team ownership successfully transferred to {$newOwner->name}.");

            return redirect()->route('team.index');
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function leaveTeam(): mixed
    {
        try {
            $currentTeam = currentTeam();
            $user = auth()->user();

            if ($currentTeam->id === 0) {
                throw new \Exception('You cannot leave the platform Root team.');
            }

            if ($user->roleInTeam($currentTeam->id) === 'owner') {
                $otherMembersCount = $currentTeam->members()->where('users.id', '!=', $user->id)->count();
                if ($otherMembersCount > 0) {
                    throw new \Exception('You must transfer ownership to another member before leaving this team.');
                }

                throw new \Exception('You are the sole member of this team. You must delete the team rather than leave it.');
            }

            $teamId = $currentTeam->id;
            DB::transaction(function () use ($currentTeam, $user, $teamId): void {
                $currentTeam->members()->detach($user->id);
                RevokeUserTeamTokens::forUserTeam($user, $teamId);
                $user->clearStoredTeamIfMatches($teamId);
            });

            app(TeamSyncService::class)->removeMember($currentTeam, $user);

            auditLog('ui.team_member.left', [
                'team_id' => $teamId,
                'member_id' => $user->id,
                'member_name' => $user->name,
                'member_email' => $user->email,
            ]);

            Cache::forget("team:{$user->id}");
            Cache::forget("user:{$user->id}:team:{$teamId}");

            $nextTeam = $user->personalTeam() ?? $user->teams()->first();
            if ($nextTeam) {
                refreshSession($nextTeam);
            } else {
                session()->forget('currentTeam');
            }

            $this->dispatch('success', 'You have left the team.');

            return redirect()->route('portal.index');
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function delete(): mixed
    {
        try {
            $currentTeam = currentTeam();
            $this->authorize('delete', $currentTeam);
            $newTeam = app(DeleteTeam::class)->handle($currentTeam, auth()->user());
            refreshSession($newTeam);

            return redirect()->route('team.index');
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function refreshResources(): void
    {
        $this->team = Team::query()->findOrFail($this->team->id);
        refreshSession($this->team);
    }

    public function render(): mixed
    {
        $eligibleNewOwners = $this->team->members()
            ->where('users.id', '!=', auth()->id())
            ->get();

        return view('livewire.team.danger-zone', [
            'eligibleNewOwners' => $eligibleNewOwners,
        ]);
    }
}

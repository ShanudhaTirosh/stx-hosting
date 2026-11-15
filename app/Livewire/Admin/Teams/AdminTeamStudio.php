<?php

namespace App\Livewire\Admin\Teams;

use App\Models\Server;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AdminTeamStudio extends Component
{
    public string $search = '';

    public function mount()
    {
        if (! Auth::check() || ! RbacService::isStaff(Auth::user())) {
            abort(403, 'Platform staff access required.');
        }
    }

    public function switchToTeam(int $teamId)
    {
        if (! RbacService::isStaff(Auth::user())) {
            abort(403);
        }

        if (RbacService::isPlatformAdminOnly(Auth::user())) {
            abort(403, 'Platform Admins are restricted from entering the client portal.');
        }

        $team = Team::findOrFail($teamId);
        refreshSession($team);

        $this->dispatch('success', "Active context switched to {$team->name}.");

        return redirect()->route('portal.services');
    }

    public function render()
    {
        $query = Team::withCount(['members', 'servers'])->with('members');

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('members', function ($mq) use ($term) {
                        $mq->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
            });
        }

        $teams = $query->latest('id')->paginate(20);

        $totalTeams = Team::count();
        $totalMembers = User::count();
        $totalServers = Server::count();
        $activeInvites = TeamInvitation::count();

        return view('livewire.admin.teams.admin-team-studio', [
            'teams' => $teams,
            'totalTeams' => $totalTeams,
            'totalMembers' => $totalMembers,
            'totalServers' => $totalServers,
            'activeInvites' => $activeInvites,
        ]);
    }
}

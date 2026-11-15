<?php

namespace App\Livewire\Portal\Teams;

use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TeamList extends Component
{
    public string $name = '';

    public string $description = '';

    public string $search = '';

    public bool $showCreateModal = false;

    protected $rules = [
        'name' => 'required|string|min:2|max:64',
        'description' => 'nullable|string|max:255',
    ];

    public function createTeam()
    {
        $this->validate();

        $user = Auth::user();

        $team = DB::transaction(function () use ($user) {
            $created = Team::create([
                'name' => trim($this->name),
                'description' => trim($this->description) ?: null,
                'personal_team' => false,
            ]);

            $created->members()->attach($user->id, [
                'role' => 'owner',
                'access' => null,
            ]);

            return $created;
        });

        refreshSession($team);

        $this->reset(['name', 'description', 'showCreateModal']);
        $this->dispatch('success', "Team '{$team->name}' created successfully!");

        return redirect()->route('portal.teams.detail', ['uuid' => $team->uuid]);
    }

    public function switchTeam(int $teamId)
    {
        $user = Auth::user();

        if (! $user->teams->contains('id', $teamId)) {
            $this->dispatch('error', 'You are not a member of this team.');

            return;
        }

        $team = Team::find($teamId);
        if (! $team) {
            $this->dispatch('error', 'Team not found.');

            return;
        }

        refreshSession($team);
        $this->dispatch('success', "Switched to '{$team->name}'");

        return redirect()->route('portal.teams.index');
    }

    public function render()
    {
        $user = Auth::user();
        $query = $user->teams()->withCount(['members', 'servers'])->with('subscription');

        if (trim($this->search) !== '') {
            $searchTerm = '%'.trim($this->search).'%';
            $query->where('teams.name', 'like', $searchTerm);
        }

        $teams = $query->get();

        $pendingInvitesCount = TeamInvitation::where(function ($q) use ($user) {
            $q->where('email', strtolower($user->email))
                ->orWhere('target_user_id', $user->id);
        })->count();

        return view('livewire.portal.teams.team-list', [
            'teams' => $teams,
            'currentTeamId' => currentTeam()?->id,
            'pendingInvitesCount' => $pendingInvitesCount,
        ]);
    }
}

<?php

namespace App\Livewire;

use App\Models\Team;
use App\Services\RbacService;
use Livewire\Component;

class SwitchTeam extends Component
{
    public string $selectedTeamId = 'default';

    protected $listeners = [
        'team-avatar-updated' => '$refresh',
        'team-updated' => '$refresh',
    ];

    public function mount()
    {
        $this->selectedTeamId = (string) (auth()->user()?->currentTeam()?->id ?? 'default');
    }

    public function updatedSelectedTeamId()
    {
        $this->switch_to($this->selectedTeamId);
    }

    public function switch_to($team_id)
    {
        if (! auth()->user()->teams->contains('id', (int) $team_id)) {
            return;
        }
        $team_to_switch_to = Team::find((int) $team_id);
        if (! $team_to_switch_to) {
            return;
        }
        refreshSession($team_to_switch_to);

        $referer = request()->headers->get('referer');
        if ($referer && str_contains($referer, '/portal')) {
            return redirect()->to($referer);
        }

        if (! RbacService::isStaff(auth()->user())) {
            return redirect()->route('portal.index');
        }

        return redirect()->route('dashboard');
    }
}

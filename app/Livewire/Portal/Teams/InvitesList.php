<?php

namespace App\Livewire\Portal\Teams;

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class InvitesList extends Component
{
    public function acceptInvite(int $inviteId)
    {
        $user = Auth::user();
        $invitation = TeamInvitation::where(function ($q) use ($user) {
            $q->where('email', strtolower($user->email))
                ->orWhere('target_user_id', $user->id);
        })->where('id', $inviteId)->first();

        if (! $invitation) {
            $this->dispatch('error', 'Invitation not found or expired.');

            return;
        }

        if ($invitation->hasExpired()) {
            $invitation->delete();
            $this->dispatch('error', 'This invitation has expired.');

            return;
        }

        $team = $invitation->team;
        $invitation->acceptFor($user);

        refreshSession($team);
        $this->dispatch('success', "Welcome to {$team->name}!");

        return redirect()->route('portal.teams.detail', ['uuid' => $team->uuid]);
    }

    public function declineInvite(int $inviteId)
    {
        $user = Auth::user();
        $invitation = TeamInvitation::where(function ($q) use ($user) {
            $q->where('email', strtolower($user->email))
                ->orWhere('target_user_id', $user->id);
        })->where('id', $inviteId)->first();

        if ($invitation) {
            $invitation->delete();
            $this->dispatch('success', 'Invitation declined.');
        }
    }

    public function render()
    {
        $user = Auth::user();
        $invitations = TeamInvitation::where(function ($q) use ($user) {
            $q->where('email', strtolower($user->email))
                ->orWhere('target_user_id', $user->id);
        })->with(['team.members', 'invitedBy'])->latest()->get();

        return view('livewire.portal.teams.invites-list', [
            'invitations' => $invitations,
        ]);
    }
}

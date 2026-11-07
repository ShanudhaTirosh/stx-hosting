<?php

namespace App\Livewire\Admin;

use App\Models\Team;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public int $activeSubscribers;

    public int $inactiveSubscribers;

    public Collection $foundUsers;

    public string $search = '';

    public function mount()
    {
        $this->authorizeAdminAccess();
        $this->getSubscribers();
    }

    public function back()
    {
        $this->authorizeAdminAccess();
        if (session('impersonator_id') === 0) {
            session()->forget(['impersonator_id', 'impersonating']);
            $user = User::find(0);
            $team_to_switch_to = $user->resolveStoredTeam() ?? $user->teams->first();
            Auth::login($user);
            refreshSession($team_to_switch_to);

            return redirect()->route('admin.index');
        }
    }

    public function submitSearch()
    {
        $this->authorizeAdminAccess();
        if ($this->search !== '') {
            $this->foundUsers = User::where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            })->get();
        }
    }

    public function getSubscribers()
    {
        $this->authorizeAdminAccess();
        $this->inactiveSubscribers = Team::whereRelation('subscription', 'stripe_invoice_paid', false)->count();
        $this->activeSubscribers = Team::whereRelation('subscription', 'stripe_invoice_paid', true)->count();
    }

    public function switchUser(int $user_id)
    {
        $this->authorizeRootOnly();
        session(['impersonator_id' => Auth::id(), 'impersonating' => true]);
        $user = User::find($user_id);
        if (! $user) {
            abort(404);
        }
        $team_to_switch_to = $user->resolveStoredTeam() ?? $user->teams->first();
        auditLog('ui.user.impersonated', [
            'team_id' => currentTeam()?->id,
            'resource' => 'user',
            'user_name' => $user->name,
            'target_user_id' => $user->id,
            'target_user_email' => $user->email,
            'target_team_id' => $team_to_switch_to?->id,
        ]);
        Auth::login($user);
        refreshSession($team_to_switch_to);

        return redirect()->route('dashboard');
    }

    private function authorizeAdminAccess(): void
    {
        // Platform staff (Owner/Admin), or the root user while impersonating a client.
        if (! Auth::check() || (! RbacService::isStaff(Auth::user()) && session('impersonator_id') !== 0)) {
            abort(403);
        }
    }

    private function authorizeRootOnly(): void
    {
        // Impersonation is Owner-only (root user or root-team owner).
        if (! Auth::check() || (Auth::id() !== 0 && ! RbacService::isPlatformOwner(Auth::user()))) {
            abort(403);
        }
    }

    public function render()
    {
        return view('livewire.admin.index');
    }
}

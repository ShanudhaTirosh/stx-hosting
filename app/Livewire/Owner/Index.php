<?php

namespace App\Livewire\Owner;

use App\Models\Commerce\Order;
use App\Models\Commerce\PaymentProof;
use App\Models\Commerce\ServiceInstance;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public int $totalTeams = 0;

    public int $totalUsers = 0;

    public int $activeEntitlements = 0;

    public int $pendingProofsCount = 0;

    public int $pendingOrdersCount = 0;

    public int $totalServersCount = 0;

    public Collection $foundUsers;

    public string $search = '';

    public string $studioUrl = '';

    public function boot(): void
    {
        abort_unless(RbacService::isPlatformOwner(Auth::user()), 403, 'This area is restricted to the platform owner.');
    }

    public function mount(): void
    {
        $this->studioUrl = (string) config('supabase.studio_url', 'http://localhost:8001');
        $this->loadMetrics();
        $this->foundUsers = collect();
    }

    public function loadMetrics(): void
    {
        $this->totalTeams = Team::where('id', '>', 0)->count();
        $this->totalUsers = User::count();
        $this->activeEntitlements = ServiceInstance::where('status', 'active')->count();
        $this->pendingProofsCount = PaymentProof::where('status', 'pending_review')->count();
        $this->pendingOrdersCount = Order::where('status', 'paid')
            ->whereHas('items.productVariant.product', fn ($q) => $q->where('category', 'vps'))
            ->count();
        $this->totalServersCount = Server::count();
    }

    public function submitSearch(): void
    {
        if (trim($this->search) !== '') {
            $term = trim($this->search);
            $this->foundUsers = User::where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })->limit(20)->get();
        } else {
            $this->foundUsers = collect();
        }
    }

    public function switchUser(int $userId): void
    {
        session(['impersonator_id' => Auth::id(), 'impersonating' => true]);
        $user = User::findOrFail($userId);
        $teamToSwitchTo = $user->resolveStoredTeam() ?? $user->teams->first();

        auditLog('ui.user.impersonated', [
            'team_id' => currentTeam()?->id,
            'resource' => 'user',
            'user_name' => $user->name,
            'target_user_id' => $user->id,
            'target_user_email' => $user->email,
            'target_team_id' => $teamToSwitchTo?->id,
        ]);

        Auth::login($user);
        refreshSession($teamToSwitchTo);

        $this->redirectRoute('portal.services');
    }

    public function back(): void
    {
        if (session('impersonator_id')) {
            $impersonatorId = session('impersonator_id');
            session()->forget(['impersonator_id', 'impersonating']);
            $user = User::find($impersonatorId) ?? User::find(0);
            $teamToSwitchTo = $user->resolveStoredTeam() ?? $user->teams->first();
            Auth::login($user);
            refreshSession($teamToSwitchTo);

            $this->redirectRoute('owner.index');
        }
    }

    public function render()
    {
        return view('livewire.owner.index')->layout('layouts.app');
    }
}

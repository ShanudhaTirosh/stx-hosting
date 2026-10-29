<?php

namespace App\Livewire\Portal;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\ServiceInstance;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class ServiceDetail extends Component
{
    public string $uuid;
    public ?string $revealedPassword = null;
    public string $accountPassword = '';
    public bool $showPasswordModal = false;
    public string $ptrRecord = '';

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function openPasswordModal(): void
    {
        $this->accountPassword = '';
        $this->showPasswordModal = true;
    }

    public function revealRootPassword(): void
    {
        $this->validate([
            'accountPassword' => 'required|string',
        ]);

        $user = auth()->user();
        if (! Hash::check($this->accountPassword, $user->password)) {
            $this->addError('accountPassword', 'Incorrect account password.');
            return;
        }

        $team = currentTeam();
        $service = ServiceInstance::where('uuid', $this->uuid)
            ->where('team_id', $team->id)
            ->firstOrFail();

        $rootCred = $service->credentials()
            ->where('credential_type', 'root_password')
            ->first();

        if (! $rootCred) {
            $this->dispatch('error', 'No stored root password found for this instance.');
            $this->showPasswordModal = false;
            return;
        }

        $this->revealedPassword = $rootCred->getDecryptedSecret();
        $rootCred->update(['last_retrieved_at' => now()]);

        CommerceAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'action' => 'credential.revealed',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->showPasswordModal = false;
        $this->dispatch('success', 'Root password revealed. Store it securely in a password manager.');
    }

    public function requestPtr(): void
    {
        $this->validate([
            'ptrRecord' => 'required|string|regex:/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i',
        ]);

        $team = currentTeam();
        $service = ServiceInstance::where('uuid', $this->uuid)
            ->where('team_id', $team->id)
            ->firstOrFail();

        CommerceAuditLog::create([
            'user_id' => auth()->id(),
            'team_id' => $team->id,
            'action' => 'ptr_record.requested',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'payload' => ['requested_ptr' => $this->ptrRecord],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->ptrRecord = '';
        $this->dispatch('success', 'Reverse DNS PTR request submitted to network operators.');
    }

    public function render()
    {
        $team = currentTeam();
        $service = ServiceInstance::with(['productVariant.product', 'credentials', 'order'])
            ->where('uuid', $this->uuid)
            ->where('team_id', $team->id)
            ->firstOrFail();

        return view('livewire.portal.service-detail', [
            'service' => $service,
        ]);
    }
}


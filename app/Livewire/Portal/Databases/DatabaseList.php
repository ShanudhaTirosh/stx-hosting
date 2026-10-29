<?php

namespace App\Livewire\Portal\Databases;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceInstance;
use App\Services\Commerce\DatabaseProvisioningService;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class DatabaseList extends Component
{
    public bool $showCreateModal = false;
    public string $dbName = '';
    public string $engine = 'postgresql';
    public ?int $selectedVariantId = null;

    public ?int $revealingServiceId = null;
    public string $accountPassword = '';
    public ?string $revealedDbPassword = null;

    public function openCreateModal(): void
    {
        $this->reset(['dbName']);
        $this->engine = 'postgresql';
        $firstVariant = ProductVariant::whereHas('product', fn ($q) => $q->where('category', 'database'))->first();
        $this->selectedVariantId = $firstVariant ? $firstVariant->id : null;
        $this->showCreateModal = true;
    }

    public function createDatabase(): void
    {
        $this->validate([
            'dbName' => 'required|string|min:3|max:32|regex:/^[a-zA-Z0-9_]+$/',
            'engine' => 'required|in:postgresql,mysql,redis',
            'selectedVariantId' => 'required|exists:product_variants,id',
        ]);

        $team = currentTeam();
        $user = auth()->user();
        $variant = ProductVariant::findOrFail($this->selectedVariantId);

        $service = DatabaseProvisioningService::provision(
            $team,
            $user,
            $variant,
            $this->dbName,
            $this->engine
        );

        $this->showCreateModal = false;
        $this->dispatch('success', "Database '{$service->name}' provisioned successfully!");
    }

    public function openPasswordModal(int $serviceId): void
    {
        $this->revealingServiceId = $serviceId;
        $this->accountPassword = '';
        $this->revealedDbPassword = null;
    }

    public function revealPassword(): void
    {
        $this->validate([
            'accountPassword' => 'required|string',
        ]);

        $user = auth()->user();
        if (! Hash::check($this->accountPassword, $user->password)) {
            $this->addError('accountPassword', 'Incorrect account password.');
            return;
        }

        $service = ServiceInstance::findOrFail($this->revealingServiceId);
        $cred = $service->credentials()->where('credential_type', 'db_password')->first();

        if ($cred) {
            $this->revealedDbPassword = $cred->getDecryptedSecret();
            $this->dispatch('success', 'Database password revealed.');
        } else {
            $this->dispatch('error', 'No stored password found.');
        }
    }

    public function triggerBackup(int $serviceId): void
    {
        $service = ServiceInstance::findOrFail($serviceId);

        CommerceAuditLog::create([
            'user_id' => auth()->id(),
            'team_id' => $service->team_id,
            'action' => 'database.backup_triggered',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->dispatch('success', "Automated S3 backup triggered for '{$service->name}'.");
    }

    public function render()
    {
        $team = currentTeam();
        $databases = collect();

        if ($team) {
            $databases = ServiceInstance::with(['productVariant', 'credentials'])
                ->where('team_id', $team->id)
                ->where('service_type', 'database')
                ->latest()
                ->get();
        }

        $availableVariants = ProductVariant::whereHas('product', function ($q) {
            $q->where('category', 'database');
        })->get();

        return view('livewire.portal.databases.database-list', [
            'databases' => $databases,
            'availableVariants' => $availableVariants,
        ]);
    }
}


<?php

namespace App\Livewire\Portal\Apps;

use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceInstance;
use App\Services\Commerce\AppProvisioningService;
use Livewire\Component;

class AppList extends Component
{
    public bool $showDeployModal = false;
    public string $appName = '';
    public string $gitRepo = '';
    public string $gitBranch = 'main';
    public string $buildPack = 'nixpacks';
    public ?int $selectedVariantId = null;

    public function openDeployModal(): void
    {
        $this->reset(['appName', 'gitRepo']);
        $this->gitBranch = 'main';
        $this->buildPack = 'nixpacks';
        $firstVariant = ProductVariant::whereHas('product', fn ($q) => $q->where('category', 'web_hosting'))->first();
        $this->selectedVariantId = $firstVariant ? $firstVariant->id : null;
        $this->showDeployModal = true;
    }

    public function deployApp(): void
    {
        $this->validate([
            'appName' => 'required|string|min:3|max:64|regex:/^[a-zA-Z0-9-_]+$/',
            'gitRepo' => 'required|url',
            'gitBranch' => 'required|string|max:64',
            'buildPack' => 'required|in:nixpacks,dockerfile,static',
            'selectedVariantId' => 'required|exists:product_variants,id',
        ]);

        $team = currentTeam();
        $user = auth()->user();
        $variant = ProductVariant::findOrFail($this->selectedVariantId);

        $service = AppProvisioningService::provision(
            $team,
            $user,
            $variant,
            $this->appName,
            $this->gitRepo,
            $this->gitBranch,
            $this->buildPack
        );

        $this->showDeployModal = false;
        $this->dispatch('success', "Application '{$service->name}' deployed with {$variant->name} limits!");
    }

    public function render()
    {
        $team = currentTeam();
        $apps = collect();

        if ($team) {
            $apps = ServiceInstance::with(['productVariant'])
                ->where('team_id', $team->id)
                ->where('service_type', 'web_app')
                ->latest()
                ->get();
        }

        $availableVariants = ProductVariant::whereHas('product', function ($q) {
            $q->where('category', 'web_hosting');
        })->get();

        return view('livewire.portal.apps.app-list', [
            'apps' => $apps,
            'availableVariants' => $availableVariants,
        ]);
    }
}


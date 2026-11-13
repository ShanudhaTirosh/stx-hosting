<?php

namespace App\Livewire\Portal;

use App\Exceptions\PlatformRestrictionException;
use App\Models\Commerce\Product;
use App\Models\Commerce\ProductVariant;
use App\Models\TemplatePermission;
use App\Services\Commerce\AppProvisioningService;
use App\Services\Commerce\HostingCatalogSeederService;
use App\Services\Commerce\PlatformDomainService;
use App\Services\TemplateAccessService;
use Illuminate\Support\Collection;
use Livewire\Component;

class DeployWizard extends Component
{
    public string $mode = 'template'; // 'template' or 'git'

    // Template Deploy Fields
    public ?string $selectedTemplate = null;
    public string $templateSearch = '';
    public string $templateCategory = 'all';

    // Git Deploy Fields
    public ?int $selectedPlanId = null;
    public string $gitRepo = '';
    public string $gitBranch = 'main';
    public string $buildPack = 'nixpacks';

    // Common Fields
    public string $appName = '';
    public array $envVars = [];
    public string $newEnvKey = '';
    public string $newEnvValue = '';

    public function mount(): void
    {
        // Ensure catalog is seeded
        if (TemplatePermission::count() === 0 || Product::where('slug', 'git-app-hosting')->count() === 0) {
            HostingCatalogSeederService::seedAll();
        }

        // Default select the first popular template and first Git plan
        $firstTmpl = TemplatePermission::where('is_globally_enabled', true)->where('is_featured', true)->first();
        if ($firstTmpl) {
            $this->selectedTemplate = $firstTmpl->template_name;
        }

        $standardPlan = ProductVariant::where('slug', 'git-standard')->first() 
            ?: ProductVariant::whereHas('product', fn ($q) => $q->where('slug', 'git-app-hosting'))->first();
        if ($standardPlan) {
            $this->selectedPlanId = $standardPlan->id;
        }
    }

    private function team()
    {
        $team = currentTeam();
        abort_unless($team, 403, 'No active team.');

        return $team;
    }

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['template', 'git']) ? $mode : 'template';
    }

    public function selectTemplate(string $templateName): void
    {
        $this->selectedTemplate = $templateName;
    }

    public function selectPlan(int $planId): void
    {
        $this->selectedPlanId = $planId;
    }

    public function addEnvVar(): void
    {
        $this->validate([
            'newEnvKey' => 'required|string|regex:/^[A-Z0-9_]+$/',
            'newEnvValue' => 'required|string',
        ]);

        $this->envVars[$this->newEnvKey] = $this->newEnvValue;
        $this->newEnvKey = '';
        $this->newEnvValue = '';
    }

    public function removeEnvVar(string $key): void
    {
        unset($this->envVars[$key]);
    }

    public function deploy(): mixed
    {
        $team = $this->team();
        $user = auth()->user();

        if ($this->mode === 'template') {
            $this->validate([
                'selectedTemplate' => 'required|string',
                'appName' => 'required|string|min:2|max:64|regex:/^[a-zA-Z0-9-]+$/',
            ]);

            try {
                $service = AppProvisioningService::provisionTemplate(
                    $team,
                    $user,
                    $this->selectedTemplate,
                    $this->appName,
                    $this->envVars
                );

                $this->dispatch('success', "{$service->name} deployed successfully with instant HTTPS!");

                return redirect()->route('portal.apps.detail', ['uuid' => $service->uuid]);
            } catch (PlatformRestrictionException $e) {
                $this->addError('selectedTemplate', $e->getMessage());

                return null;
            }
        } else {
            $this->validate([
                'selectedPlanId' => 'required|exists:product_variants,id',
                'appName' => 'required|string|min:2|max:64|regex:/^[a-zA-Z0-9-]+$/',
                'gitRepo' => 'required|url',
                'gitBranch' => 'required|string|max:100',
                'buildPack' => 'required|string|in:nixpacks,dockerfile,node,python,php',
            ]);

            $variant = ProductVariant::findOrFail($this->selectedPlanId);

            $service = AppProvisioningService::provision(
                $team,
                $user,
                $variant,
                $this->appName,
                $this->gitRepo,
                $this->gitBranch,
                $this->buildPack,
                $this->envVars
            );

            $this->dispatch('success', "Git application {$service->name} created and bound to {$variant->name}!");

            return redirect()->route('portal.apps.detail', ['uuid' => $service->uuid]);
        }
    }

    public function render()
    {
        $team = $this->team();
        $curatedTemplates = TemplateAccessService::getCuratedTemplates($team);

        if ($this->templateCategory !== 'all') {
            $curatedTemplates = $curatedTemplates->where('category', $this->templateCategory)->values();
        }

        if (trim($this->templateSearch) !== '') {
            $curatedTemplates = $curatedTemplates->filter(function ($t) {
                $q = strtolower(trim($this->templateSearch));

                return str_contains(strtolower($t->display_name ?? $t->template_name), $q)
                    || str_contains(strtolower($t->short_description ?? ''), $q);
            })->values();
        }

        $gitPlans = ProductVariant::with(['pricingTiers'])
            ->whereHas('product', fn ($q) => $q->where('slug', 'git-app-hosting'))
            ->where('is_active', true)
            ->orderBy('vcpu')
            ->orderBy('ram_mb')
            ->get();

        $appsDomain = PlatformDomainService::appsDomain() ?: config('hosting.default_domain', 'apps.stxapp.io');

        return view('livewire.portal.deploy-wizard', [
            'templates' => $curatedTemplates,
            'gitPlans' => $gitPlans,
            'appsDomain' => $appsDomain,
        ]);
    }
}


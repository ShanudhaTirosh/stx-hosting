<?php

namespace App\Livewire\Portal;

use App\Exceptions\PlatformRestrictionException;
use App\Models\Commerce\Product;
use App\Models\Commerce\ProductVariant;
use App\Models\GithubApp;
use App\Models\Server;
use App\Models\TemplatePermission;
use App\Services\Commerce\AppProvisioningService;
use App\Services\Commerce\HostingCatalogSeederService;
use App\Services\Commerce\PlatformDomainService;
use App\Services\TemplateAccessService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
    public string $gitSourceType = 'manual'; // 'github_app' or 'manual'
    public ?int $selectedGithubAppId = null;
    public array $githubAppsList = [];
    public array $githubRepositories = [];
    public array $githubBranches = [];
    public ?string $selectedGithubRepo = null;
    public bool $isPrivateRepo = false;
    public bool $isLoadingRepos = false;
    public bool $isLoadingBranches = false;

    public string $gitRepo = '';
    public string $gitBranch = 'main';
    public string $buildPack = 'nixpacks';

    // Common Fields
    public ?int $selectedServerId = null;
    public string $appName = '';
    public string $customSubdomain = '';
    public array $selectedEndpoints = [];
    public array $envVars = [];
    public string $newEnvKey = '';
    public string $newEnvValue = '';
    public ?string $cloningSourceName = null;

    public function mount(?string $clone_from = null): void
    {
        // Pre-select default server
        try {
            $this->selectedServerId = Server::isUsable()->first()?->id;
        } catch (\Throwable) {
            $this->selectedServerId = null;
        }

        // Ensure catalog is seeded
        if (TemplatePermission::count() === 0 || Product::where('slug', 'git-app-hosting')->count() === 0) {
            HostingCatalogSeederService::seedAll();
        }

        if ($clone_from) {
            $source = \App\Models\Commerce\ServiceInstance::where('uuid', $clone_from)
                ->where('team_id', currentTeam()->id)
                ->first();

            if ($source) {
                $this->cloningSourceName = $source->name;
                $this->appName = PlatformDomainService::nextAvailableSlug($source->name);
                $this->customSubdomain = PlatformDomainService::nextAvailableSlug($source->name);
                $this->envVars = data_get($source->network_config, 'env_vars', []);
                if (data_get($source->network_config, 'server_id')) {
                    $this->selectedServerId = (int) data_get($source->network_config, 'server_id');
                }

                if ($source->service_type === 'template_app' && $source->template_name) {
                    $this->mode = 'template';
                    $this->selectedTemplate = $source->template_name;
                    $this->selectedEndpoints = array_keys(data_get($source->network_config, 'assigned_endpoints', []));
                } else {
                    $this->mode = 'git';
                    $this->gitRepo = data_get($source->network_config, 'git_repo', '');
                    $this->gitBranch = data_get($source->network_config, 'git_branch', 'main');
                    $this->buildPack = data_get($source->network_config, 'build_pack', $source->os_image ?: 'nixpacks');
                    $this->selectedPlanId = $source->product_variant_id;
                }

                return;
            }
        }

        // Default select the first popular template and first Git plan
        $firstTmpl = TemplatePermission::where('is_globally_enabled', true)->where('is_featured', true)->first();
        if ($firstTmpl) {
            $this->selectedTemplate = $firstTmpl->template_name;
            $this->initTemplateEndpoints($firstTmpl->template_name);
        }

        $standardPlan = ProductVariant::where('slug', 'git-standard')->first() 
            ?: ProductVariant::whereHas('product', fn ($q) => $q->where('slug', 'git-app-hosting'))->first();
        if ($standardPlan) {
            $this->selectedPlanId = $standardPlan->id;
        }

        $this->loadGithubApps();
    }

    public function loadGithubApps(): void
    {
        $team = currentTeam();
        if (! $team) {
            return;
        }

        $this->githubAppsList = GithubApp::where(function ($q) use ($team) {
            $q->where('team_id', $team->id)->orWhere('is_system_wide', true);
        })->get()->map(fn ($app) => [
            'id' => $app->id,
            'name' => $app->name,
            'organization' => $app->organization,
            'installation_id' => $app->installation_id,
        ])->toArray();

        if (! empty($this->githubAppsList)) {
            $this->gitSourceType = 'github_app';
            $this->selectGithubApp($this->githubAppsList[0]['id']);
        }
    }

    public function selectGithubApp(?int $appId): void
    {
        $this->selectedGithubAppId = $appId;
        $this->githubRepositories = [];
        $this->githubBranches = [];
        $this->selectedGithubRepo = null;

        if (! $appId) {
            return;
        }

        $team = currentTeam();
        $app = GithubApp::where('id', $appId)
            ->where(function ($q) use ($team) {
                $q->where('team_id', $team->id)->orWhere('is_system_wide', true);
            })->first();

        if (! $app || ! $app->installation_id) {
            return;
        }

        $this->isLoadingRepos = true;

        try {
            $token = generateGithubInstallationToken($app);
            $response = Http::GitHub($app->api_url, $token)
                ->timeout(15)
                ->get('/installation/repositories', ['per_page' => 100]);

            if ($response->successful()) {
                $this->githubRepositories = collect(data_get($response->json(), 'repositories', []))
                    ->map(fn ($r) => [
                        'id' => $r['id'],
                        'name' => $r['name'],
                        'full_name' => $r['full_name'],
                        'private' => (bool) $r['private'],
                        'default_branch' => $r['default_branch'] ?? 'main',
                        'html_url' => $r['html_url'],
                        'clone_url' => $r['clone_url'],
                    ])->toArray();
            }
        } catch (\Throwable $e) {
            logger()->warning("Could not load GitHub repositories: {$e->getMessage()}");
        } finally {
            $this->isLoadingRepos = false;
        }
    }

    public function selectGithubRepo(string $repoFullName): void
    {
        $this->selectedGithubRepo = $repoFullName;
        $repo = collect($this->githubRepositories)->firstWhere('full_name', $repoFullName);

        if (! $repo) {
            return;
        }

        $this->gitRepo = $repo['clone_url'];
        $this->isPrivateRepo = (bool) $repo['private'];
        $defaultBranch = $repo['default_branch'] ?: 'main';
        $this->gitBranch = $defaultBranch;

        if (blank($this->appName)) {
            $this->appName = Str::slug($repo['name']);
        }
        if (blank($this->customSubdomain)) {
            $this->customSubdomain = Str::slug($repo['name']);
        }

        // Fetch repository branches
        $this->isLoadingBranches = true;
        try {
            $app = GithubApp::find($this->selectedGithubAppId);
            if ($app) {
                $token = generateGithubInstallationToken($app);
                $response = Http::GitHub($app->api_url, $token)
                    ->timeout(10)
                    ->get("/repos/{$repoFullName}/branches", ['per_page' => 100]);

                if ($response->successful()) {
                    $this->githubBranches = collect($response->json())->pluck('name')->toArray();
                }
            }
        } catch (\Throwable $e) {
            $this->githubBranches = [$defaultBranch];
        } finally {
            $this->isLoadingBranches = false;
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
        $this->initTemplateEndpoints($templateName);
    }

    private function initTemplateEndpoints(string $templateName): void
    {
        $services = TemplateAccessService::getExposableServices($templateName);
        $this->selectedEndpoints = collect($services)
            ->filter(fn ($s) => ($s['suggested'] ?? true) || ($s['is_default'] ?? false))
            ->pluck('service_name')
            ->values()
            ->all();
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
                'customSubdomain' => 'nullable|string|min:3|max:50|regex:/^[a-z0-9-]+$/',
            ]);

            if (filled($this->customSubdomain) && ! PlatformDomainService::isSubdomainAvailable($this->customSubdomain)) {
                $this->addError('customSubdomain', "The address '{$this->customSubdomain}' is already in use. Please choose another subdomain.");

                return null;
            }

            try {
                $service = AppProvisioningService::provisionTemplate(
                    $team,
                    $user,
                    $this->selectedTemplate,
                    $this->appName,
                    $this->envVars,
                    null,
                    $this->customSubdomain ?: null,
                    $this->selectedEndpoints,
                    $this->selectedServerId
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
                'customSubdomain' => 'nullable|string|min:3|max:50|regex:/^[a-z0-9-]+$/',
                'gitRepo' => 'required|url',
                'gitBranch' => 'required|string|max:100',
                'buildPack' => 'required|string|in:nixpacks,dockerfile,node,python,php',
            ]);

            if (filled($this->customSubdomain) && ! PlatformDomainService::isSubdomainAvailable($this->customSubdomain)) {
                $this->addError('customSubdomain', "The address '{$this->customSubdomain}' is already in use. Please choose another subdomain.");

                return null;
            }

            $variant = ProductVariant::findOrFail($this->selectedPlanId);

            $service = AppProvisioningService::provision(
                $team,
                $user,
                $variant,
                $this->appName,
                $this->gitRepo,
                $this->gitBranch,
                $this->buildPack,
                $this->envVars,
                $this->customSubdomain ?: null,
                $this->selectedServerId,
                $this->selectedGithubAppId,
                $this->isPrivateRepo
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

        $exposableServices = $this->selectedTemplate
            ? TemplateAccessService::getExposableServices($this->selectedTemplate)
            : [];

        $availableServers = collect();
        try {
            $availableServers = Server::isUsable()->get();
        } catch (\Throwable) {
            $availableServers = collect();
        }

        return view('livewire.portal.deploy-wizard', [
            'templates' => $curatedTemplates,
            'gitPlans' => $gitPlans,
            'appsDomain' => $appsDomain,
            'exposableServices' => $exposableServices,
            'availableServers' => $availableServers,
        ]);
    }
}


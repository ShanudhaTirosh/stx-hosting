<?php

namespace App\Livewire\Portal\Apps;

use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceInstance;
use App\Services\Commerce\AppProvisioningService;
use Livewire\Component;

class AppList extends Component
{
    public bool $showDeployModal = false;
    public string $deployType = 'git'; // 'git' or 'template'
    public string $appName = '';
    public string $gitRepo = '';
    public string $gitBranch = 'main';
    public string $buildPack = 'railpack';
    public ?string $gitAuthToken = null;
    public ?string $selectedTemplate = null;
    public ?int $selectedVariantId = null;

    public array $curatedTemplates = [
        'discord-bot' => [
            'name' => 'Discord Moderation & Utility Bot',
            'desc' => 'Production discord.js bot with slash commands, message handling, and auto-restart.',
            'repo' => 'https://github.com/ShanudhaTirosh/discord-bot-starter',
            'buildpack' => 'railpack',
            'category' => 'Bots',
        ],
        'telegram-bot' => [
            'name' => 'Telegram Community Automation Bot',
            'desc' => 'High-performance Python aiogram bot with webhook support and state persistence.',
            'repo' => 'https://github.com/ShanudhaTirosh/telegram-bot-starter',
            'buildpack' => 'railpack',
            'category' => 'Bots',
        ],
        'nextjs-app' => [
            'name' => 'Next.js 14 App Router Full-Stack',
            'desc' => 'SSR & Edge-ready React 18 web application template with Tailwind CSS styling.',
            'repo' => 'https://github.com/ShanudhaTirosh/nextjs-starter',
            'buildpack' => 'railpack',
            'category' => 'Web Apps',
        ],
        'express-api' => [
            'name' => 'Express.js & TypeScript API Engine',
            'desc' => 'Lightweight Node.js microservice architecture with structured JSON logging.',
            'repo' => 'https://github.com/ShanudhaTirosh/express-api-starter',
            'buildpack' => 'railpack',
            'category' => 'APIs',
        ],
    ];

    public function openDeployModal(): void
    {
        $this->redirectRoute('portal.deploy');
    }

    public function selectTemplate(string $key): void
    {
        if (isset($this->curatedTemplates[$key])) {
            $tpl = $this->curatedTemplates[$key];
            $this->selectedTemplate = $key;
            $this->appName = strtolower(str_replace([' ', '&', '—'], '-', $tpl['name'])) . '-' . rand(100, 999);
            $this->gitRepo = $tpl['repo'];
            $this->buildPack = $tpl['buildpack'];
            $this->deployType = 'template';
        }
    }

    public function deployApp(): void
    {
        $team = currentTeam();
        abort_unless($team, 403, 'No active team selected.');

        $user = auth()->user();
        abort_unless($user && $user->canPerformInTeam($team->id, 'deploy'), 403, 'You do not have permission to deploy applications for this team.');

        $this->validate([
            'appName' => 'required|string|min:3|max:64|regex:/^[a-zA-Z0-9-_]+$/',
            'gitRepo' => 'required|url',
            'gitBranch' => 'required|string|max:64',
            'buildPack' => 'required|in:railpack,nixpacks,dockerfile,dockercompose,static',
            'selectedVariantId' => 'required|exists:product_variants,id',
            'gitAuthToken' => 'nullable|string|max:255',
        ]);

        $variant = ProductVariant::findOrFail($this->selectedVariantId);

        // Append token to private repository URL if provided
        $finalRepo = $this->gitRepo;
        if ($this->gitAuthToken && str_starts_with($finalRepo, 'https://github.com')) {
            $finalRepo = str_replace('https://github.com', "https://{$this->gitAuthToken}@github.com", $finalRepo);
        }

        $service = AppProvisioningService::provision(
            $team,
            $user,
            $variant,
            $this->appName,
            $finalRepo,
            $this->gitBranch,
            $this->buildPack
        );

        $this->showDeployModal = false;
        $this->dispatch('success', "Application '{$service->name}' deployed successfully with {$variant->name} limits!");
    }

    public function render()
    {
        $team = currentTeam();
        $apps = collect();

        if ($team) {
            $apps = ServiceInstance::with('productVariant')
                ->where('team_id', $team->id)
                ->whereIn('service_type', ['app', 'web_app', 'git_app', 'template_app'])
                ->latest()
                ->get();
        }

        $variants = ProductVariant::whereHas('product', fn ($q) => $q->where('category', 'web_hosting'))
            ->where('is_active', true)
            ->get();

        return view('livewire.portal.apps.app-list', [
            'apps' => $apps,
            'variants' => $variants,
        ]);
    }
}

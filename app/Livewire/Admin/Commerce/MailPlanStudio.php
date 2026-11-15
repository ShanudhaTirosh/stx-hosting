<?php

namespace App\Livewire\Admin\Commerce;

use App\Livewire\Concerns\RequiresPlatformStaff;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\MailAddon;
use App\Models\Commerce\MailDomain;
use App\Models\Commerce\MailMailbox;
use App\Models\Commerce\MailPlan;
use App\Models\Commerce\MailSubscription;
use App\Services\Commerce\WildDuckProvisioningService;
use App\Services\RbacService;
use App\Services\Supabase\SupabaseClient;
use Illuminate\Support\Str;
use Livewire\Component;

class MailPlanStudio extends Component
{
    use RequiresPlatformStaff;

    public string $activeTab = 'plans'; // 'plans', 'addons', 'telemetry'

    // Plan editing
    public bool $showPlanModal = false;

    public ?string $editingPlanId = null;

    public string $planName = '';

    public string $planSlug = '';

    public int $planStorageMb = 1024;

    public int $planMaxMailboxes = 5;

    public float $planPriceUsd = 3.00;

    public float $planPriceLkr = 950.00;

    public string $planBillingInterval = 'monthly';

    public bool $planIsActive = true;

    public string $planFeaturesText = '';

    // Addon editing
    public bool $showAddonModal = false;

    public ?string $editingAddonId = null;

    public string $addonName = '';

    public string $addonSlug = '';

    public string $addonType = 'storage'; // storage, mailbox, domain, feature

    public float $addonPriceUsd = 1.00;

    public string $addonBillingInterval = 'monthly';

    public bool $addonIsActive = true;

    public string $addonSpecsJson = '{"storage_mb": 1024}';

    // Telemetry & WildDuck Stats
    public array $fleetStats = [];

    protected function rulesForPlan(): array
    {
        return [
            'planName' => 'required|string|max:100',
            'planStorageMb' => 'required|integer|min:100',
            'planMaxMailboxes' => 'required|integer|min:1',
            'planPriceUsd' => 'required|numeric|min:0',
        ];
    }

    protected function rulesForAddon(): array
    {
        return [
            'addonName' => 'required|string|max:100',
            'addonType' => 'required|string|in:storage,mailbox,domain,feature',
            'addonPriceUsd' => 'required|numeric|min:0',
        ];
    }

    public function mount(WildDuckProvisioningService $wildDuckService): void
    {
        $this->refreshFleetStats($wildDuckService);
    }

    public function refreshFleetStats(WildDuckProvisioningService $wildDuckService): void
    {
        $this->fleetStats = $wildDuckService->getFleetStats();
    }

    public function openEditPlanModal(string $id): void
    {
        $this->authorizePricing();

        $supabase = app(SupabaseClient::class);
        $plan = $supabase->from('mail_plans')->eq('id', $id)->first();

        if (! $plan) {
            $planModel = MailPlan::find($id);
            if ($planModel) {
                $plan = $planModel->toArray();
            }
        }

        if (! $plan) {
            $this->dispatch('error', 'Plan not found.');

            return;
        }

        $this->editingPlanId = $plan['id'];
        $this->planName = $plan['name'];
        $this->planSlug = $plan['slug'];
        $this->planStorageMb = (int) $plan['storage_mb'];
        $this->planMaxMailboxes = (int) $plan['max_mailboxes'];
        $this->planPriceUsd = round(($plan['price_cents'] ?? 0) / 100, 2);
        $this->planPriceLkr = round(($plan['price_lkr_cents'] ?? 0) / 100, 2);
        $this->planBillingInterval = $plan['billing_interval'] ?? 'monthly';
        $this->planIsActive = (bool) ($plan['is_active'] ?? true);

        $features = $plan['features'] ?? [];
        if (is_array($features)) {
            $this->planFeaturesText = implode("\n", $features);
        } else {
            $this->planFeaturesText = '';
        }

        $this->showPlanModal = true;
    }

    public function savePlan(): void
    {
        $this->authorizePricing();
        $this->validate($this->rulesForPlan());

        $features = array_values(array_filter(array_map('trim', explode("\n", $this->planFeaturesText))));
        $priceCents = (int) round($this->planPriceUsd * 100);
        $priceLkrCents = (int) round($this->planPriceLkr * 100);
        $slug = $this->planSlug ?: Str::slug($this->planName);

        $data = [
            'name' => $this->planName,
            'slug' => $slug,
            'storage_mb' => $this->planStorageMb,
            'max_mailboxes' => $this->planMaxMailboxes,
            'price_cents' => $priceCents,
            'price_lkr_cents' => $priceLkrCents,
            'billing_interval' => $this->planBillingInterval,
            'is_active' => $this->planIsActive,
            'features' => $features,
            'updated_at' => now()->toISOString(),
        ];

        $supabase = app(SupabaseClient::class);

        if ($this->editingPlanId) {
            try {
                $supabase->from('mail_plans')->eq('id', $this->editingPlanId)->update($data);
            } catch (\Throwable $e) {
                // Fallback to Eloquent if Supabase REST not directly reachable
                MailPlan::where('id', $this->editingPlanId)->update($data);
            }
        } else {
            $data['id'] = (string) Str::uuid();
            try {
                $supabase->from('mail_plans')->insert($data);
            } catch (\Throwable $e) {
                MailPlan::create($data);
            }
        }

        CommerceAuditLog::record(
            'mail.plan_saved',
            null,
            null,
            ['id' => $this->editingPlanId, 'name' => $this->planName, 'price_usd' => $this->planPriceUsd]
        );

        $this->showPlanModal = false;
        $this->dispatch('success', "Plan '{$this->planName}' updated successfully.");
    }

    public function togglePlanActive(string $id, bool $currentState): void
    {
        $this->authorizePricing();

        $newState = ! $currentState;
        $supabase = app(SupabaseClient::class);
        try {
            $supabase->from('mail_plans')->eq('id', $id)->update(['is_active' => $newState, 'updated_at' => now()->toISOString()]);
        } catch (\Throwable $e) {
            MailPlan::where('id', $id)->update(['is_active' => $newState]);
        }

        $this->dispatch('success', 'Plan status updated.');
    }

    public function openEditAddonModal(string $id): void
    {
        $this->authorizePricing();

        $supabase = app(SupabaseClient::class);
        $addon = $supabase->from('mail_addons')->eq('id', $id)->first();

        if (! $addon) {
            $addonModel = MailAddon::find($id);
            if ($addonModel) {
                $addon = $addonModel->toArray();
            }
        }

        if (! $addon) {
            $this->dispatch('error', 'Add-on not found.');

            return;
        }

        $this->editingAddonId = $addon['id'];
        $this->addonName = $addon['name'];
        $this->addonSlug = $addon['slug'];
        $this->addonType = $addon['type'];
        $this->addonPriceUsd = round(($addon['price_cents'] ?? 0) / 100, 2);
        $this->addonBillingInterval = $addon['billing_interval'] ?? 'monthly';
        $this->addonIsActive = (bool) ($addon['is_active'] ?? true);

        $specs = $addon['specs_json'] ?? [];
        $this->addonSpecsJson = json_encode($specs, JSON_PRETTY_PRINT);

        $this->showAddonModal = true;
    }

    public function saveAddon(): void
    {
        $this->authorizePricing();
        $this->validate($this->rulesForAddon());

        $specs = json_decode($this->addonSpecsJson, true);
        if (! is_array($specs)) {
            $specs = [];
        }

        $priceCents = (int) round($this->addonPriceUsd * 100);
        $slug = $this->addonSlug ?: Str::slug($this->addonName);

        $data = [
            'name' => $this->addonName,
            'slug' => $slug,
            'type' => $this->addonType,
            'specs_json' => $specs,
            'price_cents' => $priceCents,
            'billing_interval' => $this->addonBillingInterval,
            'is_active' => $this->addonIsActive,
            'updated_at' => now()->toISOString(),
        ];

        $supabase = app(SupabaseClient::class);

        if ($this->editingAddonId) {
            try {
                $supabase->from('mail_addons')->eq('id', $this->editingAddonId)->update($data);
            } catch (\Throwable $e) {
                MailAddon::where('id', $this->editingAddonId)->update($data);
            }
        } else {
            $data['id'] = (string) Str::uuid();
            try {
                $supabase->from('mail_addons')->insert($data);
            } catch (\Throwable $e) {
                MailAddon::create($data);
            }
        }

        CommerceAuditLog::record(
            'mail.addon_saved',
            null,
            null,
            ['id' => $this->editingAddonId, 'name' => $this->addonName, 'price_usd' => $this->addonPriceUsd]
        );

        $this->showAddonModal = false;
        $this->dispatch('success', "Add-on '{$this->addonName}' updated successfully.");
    }

    public function toggleAddonActive(string $id, bool $currentState): void
    {
        $this->authorizePricing();

        $newState = ! $currentState;
        $supabase = app(SupabaseClient::class);
        try {
            $supabase->from('mail_addons')->eq('id', $id)->update(['is_active' => $newState, 'updated_at' => now()->toISOString()]);
        } catch (\Throwable $e) {
            MailAddon::where('id', $id)->update(['is_active' => $newState]);
        }

        $this->dispatch('success', 'Add-on status updated.');
    }

    protected function authorizePricing(): void
    {
        abort_unless(
            RbacService::can(auth()->user(), 'pricing.manage') || auth()->user()->isPlatformOwner(),
            403,
            'Only platform administrators or owners can modify email hosting plans and pricing.'
        );
    }

    public function render()
    {
        $supabase = app(SupabaseClient::class);

        // Fetch Plans
        try {
            $plans = $supabase->from('mail_plans')->order('storage_mb')->get();
        } catch (\Throwable $e) {
            $plans = MailPlan::orderBy('storage_mb')->get()->toArray();
        }

        // Fetch Add-ons
        try {
            $addons = $supabase->from('mail_addons')->order('type')->get();
        } catch (\Throwable $e) {
            $addons = MailAddon::orderBy('type')->get()->toArray();
        }

        // Count totals
        $totalSubscriptions = 0;
        $totalDomains = 0;
        $totalMailboxes = 0;

        try {
            $totalSubscriptions = (int) $supabase->from('mail_subscriptions')->count();
            $totalDomains = (int) $supabase->from('mail_domains')->count();
            $totalMailboxes = (int) $supabase->from('mail_mailboxes')->count();
        } catch (\Throwable $e) {
            // Fallback to Eloquent
            $totalSubscriptions = MailSubscription::count();
            $totalDomains = MailDomain::count();
            $totalMailboxes = MailMailbox::count();
        }

        return view('livewire.admin.commerce.mail-plan-studio', [
            'canManagePricing' => RbacService::can(auth()->user(), 'pricing.manage') || auth()->user()->isPlatformOwner(),
            'plans' => $plans,
            'addons' => $addons,
            'totalSubscriptions' => $totalSubscriptions,
            'totalDomains' => $totalDomains,
            'totalMailboxes' => $totalMailboxes,
        ])->layout('layouts.app', ['title' => 'Email Hosting Studio | STX Admin']);
    }
}

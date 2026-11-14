<?php

namespace App\Livewire\Admin\Billing;

use App\Models\Commerce\CommercialSetting;
use App\Services\Commerce\PayVerifyClient;
use App\Services\RbacService;
use Livewire\Component;

class VerificationSettings extends Component
{
    public string $payverifyUrl = '';
    public string $payverifyApiKey = '';
    public bool $autoApproveEnabled = true;
    public float $autoApproveThreshold = 0.90;
    public float $manualReviewThreshold = 0.60;
    public int $maxDateSkewDays = 7;

    public ?array $healthStatus = null;
    public ?string $testError = null;
    public bool $testing = false;

    public function boot(): void
    {
        abort_unless(RbacService::can(auth()->user(), 'payment_methods.manage'), 403, 'Only the platform owner can manage verification settings.');
    }

    public function mount(): void
    {
        $this->payverifyUrl = (string) CommercialSetting::get('payverify_url', config('commerce.payverify.base_url', 'http://localhost:8000/api/v1/external'));
        $this->payverifyApiKey = (string) CommercialSetting::get('payverify_api_key', config('commerce.payverify.api_key', 'dev_test_key'));
        $this->autoApproveEnabled = (bool) CommercialSetting::get('payverify_auto_approve_enabled', config('commerce.payverify.auto_approve_enabled', true));
        $this->autoApproveThreshold = (float) CommercialSetting::get('payverify_auto_approve_min_confidence', config('commerce.payverify.auto_approve_min_confidence', 0.90));
        $this->manualReviewThreshold = (float) CommercialSetting::get('payverify_manual_review_min_confidence', config('commerce.payverify.manual_review_min_confidence', 0.60));
        $this->maxDateSkewDays = (int) CommercialSetting::get('payverify_max_date_skew_days', config('commerce.payverify.max_date_skew_days', 7));
    }

    public function testConnection(PayVerifyClient $client): void
    {
        $this->testing = true;
        $this->healthStatus = null;
        $this->testError = null;

        $tempClient = new PayVerifyClient($this->payverifyUrl, $this->payverifyApiKey);
        $health = $tempClient->health();

        if ($health && ($health['status'] ?? '') === 'online') {
            $this->healthStatus = $health;
            $this->dispatch('success', 'PayVerify API connection successful!');
        } else {
            $this->testError = 'Failed to connect to PayVerify API. Check the URL and API key.';
            $this->dispatch('error', 'PayVerify API unreachable.');
        }

        $this->testing = false;
    }

    public function save(): void
    {
        $this->validate([
            'payverifyUrl' => 'required|url',
            'payverifyApiKey' => 'required|string|min:4',
            'autoApproveThreshold' => 'required|numeric|min:0.50|max:1.00',
            'manualReviewThreshold' => 'required|numeric|min:0.10|max:0.90',
            'maxDateSkewDays' => 'required|integer|min:1|max:30',
        ]);

        CommercialSetting::set('payverify_url', rtrim($this->payverifyUrl, '/'), 'string', 'PayVerify API base URL');
        CommercialSetting::set('payverify_api_key', trim($this->payverifyApiKey), 'string', 'PayVerify API secure key');
        CommercialSetting::set('payverify_auto_approve_enabled', $this->autoApproveEnabled, 'boolean', 'Enable automated approval for high confidence slips');
        CommercialSetting::set('payverify_auto_approve_min_confidence', $this->autoApproveThreshold, 'string', 'Minimum confidence score for auto approval');
        CommercialSetting::set('payverify_manual_review_min_confidence', $this->manualReviewThreshold, 'string', 'Minimum confidence score for manual review queue');
        CommercialSetting::set('payverify_max_date_skew_days', $this->maxDateSkewDays, 'integer', 'Maximum allowed receipt date age in days');

        $this->dispatch('success', 'Verification settings saved successfully.');
    }

    public function render()
    {
        return view('livewire.admin.billing.verification-settings');
    }
}


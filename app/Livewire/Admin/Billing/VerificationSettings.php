<?php

namespace App\Livewire\Admin\Billing;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\CommercialSetting;
use App\Services\Commerce\PayVerifyClient;
use App\Services\RbacService;
use Livewire\Component;

class VerificationSettings extends Component
{
    public const MASK = '••••••••';

    public string $payverifyUrl = '';

    public string $payverifyApiKey = '';

    public bool $autoApproveEnabled = true;

    public float $autoApproveThreshold = 0.90;

    public float $manualReviewThreshold = 0.60;

    public int $maxDateSkewDays = 7;

    public string $geminiApiKeys = '';

    public int $geminiRateLimitRpm = 14;

    public ?array $healthStatus = null;

    public ?string $testError = null;

    public bool $testing = false;

    public function boot(): void
    {
        if (! RbacService::isPlatformOwner(auth()->user())) {
            abort(403, 'Only the platform owner can manage verification settings.');
        }
    }

    public function mount(): void
    {
        $this->payverifyUrl = (string) CommercialSetting::get('payverify_url', config('commerce.payverify.base_url', 'http://localhost:8000/api/v1/external'));
        $storedKey = (string) CommercialSetting::get('payverify_api_key', config('commerce.payverify.api_key', 'dev_test_key'));
        $this->payverifyApiKey = filled($storedKey) ? self::MASK : '';

        $this->autoApproveEnabled = (bool) CommercialSetting::get('payverify_auto_approve_enabled', config('commerce.payverify.auto_approve_enabled', true));
        $this->autoApproveThreshold = (float) CommercialSetting::get('payverify_auto_approve_min_confidence', config('commerce.payverify.auto_approve_min_confidence', 0.90));
        $this->manualReviewThreshold = (float) CommercialSetting::get('payverify_manual_review_min_confidence', config('commerce.payverify.manual_review_min_confidence', 0.60));
        $this->maxDateSkewDays = (int) CommercialSetting::get('payverify_max_date_skew_days', config('commerce.payverify.max_date_skew_days', 7));

        $storedGemini = (string) CommercialSetting::get('payverify_gemini_api_keys', env('PV_GEMINI_API_KEYS', ''));
        $this->geminiApiKeys = filled($storedGemini) ? self::MASK : '';

        $this->geminiRateLimitRpm = (int) CommercialSetting::get('payverify_gemini_rate_limit_rpm', 14);
    }

    private function resolveApiKey(): string
    {
        if ($this->payverifyApiKey === self::MASK || str_starts_with($this->payverifyApiKey, '••••')) {
            return (string) CommercialSetting::get('payverify_api_key', config('commerce.payverify.api_key', 'dev_test_key'));
        }

        return trim($this->payverifyApiKey);
    }

    public function testConnection(PayVerifyClient $client): void
    {
        $this->testing = true;
        $this->healthStatus = null;
        $this->testError = null;

        $apiKey = $this->resolveApiKey();
        $tempClient = new PayVerifyClient($this->payverifyUrl, $apiKey);
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
            'autoApproveThreshold' => 'required|numeric|min:0.50|max:1.00',
            'manualReviewThreshold' => 'required|numeric|min:0.10|max:0.90',
            'maxDateSkewDays' => 'required|integer|min:1|max:30',
        ]);

        CommercialSetting::set('payverify_url', rtrim($this->payverifyUrl, '/'), 'string', 'PayVerify API base URL');

        if ($this->payverifyApiKey !== self::MASK && ! str_starts_with($this->payverifyApiKey, '••••')) {
            CommercialSetting::set('payverify_api_key', trim($this->payverifyApiKey), 'string', 'PayVerify API secure key');
            $this->payverifyApiKey = self::MASK;
        }

        CommercialSetting::set('payverify_auto_approve_enabled', $this->autoApproveEnabled, 'boolean', 'Enable automated approval for high confidence slips');
        CommercialSetting::set('payverify_auto_approve_min_confidence', $this->autoApproveThreshold, 'string', 'Minimum confidence score for auto approval');
        CommercialSetting::set('payverify_manual_review_min_confidence', $this->manualReviewThreshold, 'string', 'Minimum confidence score for manual review queue');
        CommercialSetting::set('payverify_max_date_skew_days', $this->maxDateSkewDays, 'integer', 'Maximum allowed receipt date age in days');

        if ($this->geminiApiKeys !== self::MASK && ! str_starts_with($this->geminiApiKeys, '••••')) {
            CommercialSetting::set('payverify_gemini_api_keys', trim($this->geminiApiKeys), 'string', 'Multi-key rotation for Gemini Vision API');
            $this->geminiApiKeys = self::MASK;
        }

        CommercialSetting::set('payverify_gemini_rate_limit_rpm', $this->geminiRateLimitRpm, 'integer', 'Gemini requests per minute token bucket ceiling');

        CommerceAuditLog::record('settings.payverify_updated', null, null, [
            'auto_approve_enabled' => $this->autoApproveEnabled,
            'auto_approve_threshold' => $this->autoApproveThreshold,
            'manual_review_threshold' => $this->manualReviewThreshold,
            'max_date_skew_days' => $this->maxDateSkewDays,
            'gemini_rate_limit_rpm' => $this->geminiRateLimitRpm,
        ]);

        $this->dispatch('success', 'Verification settings saved successfully.');
    }

    public function render()
    {
        return view('livewire.admin.billing.verification-settings');
    }
}

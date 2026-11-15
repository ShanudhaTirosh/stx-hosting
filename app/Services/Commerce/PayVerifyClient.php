<?php

namespace App\Services\Commerce;

use App\Models\Commerce\CommercialSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client service for interacting with the PayVerify external API.
 * Supports health checks, telemetry, single-request upload-and-review, claim polling, and overrides.
 */
class PayVerifyClient
{
    protected string $baseUrl;

    protected string $apiKey;

    protected int $timeout;

    public function __construct(
        ?string $baseUrl = null,
        ?string $apiKey = null,
        ?int $timeout = null
    ) {
        $this->baseUrl = rtrim(
            $baseUrl ?: (CommercialSetting::get('payverify_url') ?: config('commerce.payverify.base_url', 'http://localhost:8000/api/v1/external')),
            '/'
        );
        $this->apiKey = $apiKey ?: (CommercialSetting::get('payverify_api_key') ?: config('commerce.payverify.api_key', 'dev_test_key'));
        $this->timeout = $timeout ?: (int) config('commerce.payverify.timeout_seconds', 35);
    }

    protected function http(): PendingRequest
    {
        if (app()->isProduction() && ($this->apiKey === '' || $this->apiKey === 'dev_test_key')) {
            throw new \RuntimeException('PAYVERIFY_API_KEY must be configured with a production secret key in production environment.');
        }

        return Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ])->timeout($this->timeout);
    }

    /**
     * Inspect operational health of PayVerify (database, storage, WhatsApp bot, SMS relays, AI vision).
     */
    public function health(): ?array
    {
        try {
            $response = $this->http()->timeout(3)->get("{$this->baseUrl}/health");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::warning("PayVerify health check failed: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Retrieve live system telemetry and claim queue metrics.
     */
    public function systemStatus(): ?array
    {
        try {
            $response = $this->http()->timeout(3)->get("{$this->baseUrl}/system-status");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::warning("PayVerify system-status failed: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Submit payment proof for instant AI analysis, visual forensics, and deterministic matching.
     *
     * @param  string  $invoiceNo  Merchant invoice reference
     * @param  int  $amountMinor  Payment amount in minor units (cents)
     * @param  string  $base64Proof  Base64-encoded receipt image or PDF
     * @param  string  $mime  MIME type ('image/png', 'image/jpeg', etc.)
     * @param  string|null  $filename  Original filename
     * @param  array  $customer  Customer information (name, email, phone)
     * @param  array  $payment  Payment details (reference, channel, bank_name)
     * @return array PayVerify verification response envelope
     */
    public function uploadAndReview(
        string $invoiceNo,
        int $amountMinor,
        string $base64Proof,
        string $mime = 'image/png',
        ?string $filename = 'receipt.png',
        array $customer = [],
        array $payment = []
    ): array {
        $endpoint = "{$this->baseUrl}/proofs/upload-and-review";

        $payload = [
            'invoice_no' => $invoiceNo,
            'amount_minor' => $amountMinor,
            'proof_base64' => $base64Proof,
            'proof_mime' => $mime,
            'proof_filename' => $filename ?: 'receipt.png',
            'customer' => [
                'name' => $customer['name'] ?? null,
                'email' => $customer['email'] ?? null,
                'phone' => $customer['phone'] ?? null,
                'customer_id' => $customer['id'] ?? null,
            ],
            'payment' => [
                'channel' => $payment['channel'] ?? 'bank_transfer',
                'reference' => $payment['reference'] ?? null,
                'bank_name' => $payment['bank_name'] ?? null,
            ],
            'auto_match' => true,
        ];

        try {
            $response = $this->http()->post($endpoint, $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("PayVerify uploadAndReview HTTP error: status {$response->status()} - {$response->body()}");

            return [
                'is_approved' => false,
                'status' => 'MANUAL_REVIEW',
                'verdict' => [
                    'decision' => 'api_error',
                    'confidence_score' => 0.0,
                    'summary' => "PayVerify returned status {$response->status()}",
                    'discrepancies' => ['api_error'],
                ],
                'error' => $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error("PayVerify uploadAndReview exception: {$e->getMessage()}");

            return [
                'is_approved' => false,
                'status' => 'MANUAL_REVIEW',
                'verdict' => [
                    'decision' => 'connection_failed',
                    'confidence_score' => 0.0,
                    'summary' => "Failed to reach PayVerify API: {$e->getMessage()}",
                    'discrepancies' => ['network_timeout'],
                ],
                'exception' => $e->getMessage(),
            ];
        }
    }

    /**
     * Query detailed claim inspection state.
     */
    public function getClaim(int $claimId): ?array
    {
        try {
            $response = $this->http()->get("{$this->baseUrl}/claims/{$claimId}");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::warning("PayVerify getClaim failed for claim #{$claimId}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Query order payment status by invoice number or ID.
     */
    public function getOrder(string $invoiceNo): ?array
    {
        try {
            $response = $this->http()->get("{$this->baseUrl}/orders/{$invoiceNo}");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::warning("PayVerify getOrder failed for invoice {$invoiceNo}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Resubmit replacement slip for an existing claim.
     */
    public function resubmit(
        int $claimId,
        string $base64Proof,
        string $mime = 'image/png',
        ?string $reference = null,
        ?int $amountMinor = null
    ): array {
        $endpoint = "{$this->baseUrl}/claims/{$claimId}/resubmit";

        $payload = [
            'proof_base64' => $base64Proof,
            'proof_mime' => $mime,
            'payment' => [
                'reference' => $reference,
            ],
            'amount_minor' => $amountMinor,
        ];

        try {
            $response = $this->http()->post($endpoint, $payload);

            return $response->successful() ? $response->json() : ['error' => $response->body()];
        } catch (\Throwable $e) {
            Log::error("PayVerify resubmit exception: {$e->getMessage()}");

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Force manual verification of a claim via API.
     */
    public function verifyClaimOverride(int $claimId): ?array
    {
        try {
            $response = $this->http()->post("{$this->baseUrl}/claims/{$claimId}/verify");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::error("PayVerify verifyClaimOverride exception: {$e->getMessage()}");

            return null;
        }
    }
}

<?php

return [
    // Private disk that holds payment proofs. Point at an S3/R2 disk (see config/filesystems.php)
    // by setting PAYMENT_PROOFS_DISK; the app never serves these files publicly.
    'proof_disk' => env('PAYMENT_PROOFS_DISK', 'payment_proofs'),

    // Disk for support attachments (private). Use an S3/R2 disk in production if you prefer.
    'chat_disk' => env('CHAT_DISK', 'local'),

    'proof_max_bytes' => 5 * 1024 * 1024,
    'proof_max_attempts' => 5,

    // Storage driver: 'supabase' (private bucket with RLS and expiring signed URLs) or 'local'/'disk'
    'storage_driver' => env('COMMERCE_STORAGE_DRIVER', 'supabase'),
    'supabase_proof_bucket' => env('SUPABASE_PROOF_BUCKET', 'payment-proofs'),

    // PayVerify AI Slip-Verification API settings
    'payverify' => [
        'base_url' => rtrim(env('PAYVERIFY_URL', 'http://127.0.0.1:8080/api/v1/external'), '/'),
        'api_key' => env('PAYVERIFY_API_KEY', env('PV_EXTERNAL_API_KEY', 'dev_test_key')),
        'timeout_seconds' => (int) env('PAYVERIFY_TIMEOUT_SECONDS', 35),
        'auto_approve_enabled' => (bool) env('PAYVERIFY_AUTO_APPROVE', true),
        'auto_approve_min_confidence' => (float) env('PAYVERIFY_AUTO_APPROVE_MIN_CONFIDENCE', 0.90),
        'manual_review_min_confidence' => (float) env('PAYVERIFY_MANUAL_REVIEW_MIN_CONFIDENCE', 0.60),
        'max_date_skew_days' => (int) env('PAYVERIFY_MAX_DATE_SKEW_DAYS', 7),
    ],

    // Shown on invoices (PDF and web).
    'business' => [
        'name' => env('COMMERCE_BUSINESS_NAME', 'SHANUTECHX'),
        'address' => env('COMMERCE_BUSINESS_ADDRESS', ''),
        'email' => env('COMMERCE_BUSINESS_EMAIL', ''),
    ],
];

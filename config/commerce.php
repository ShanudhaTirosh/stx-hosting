<?php

return [
    // Private disk that holds payment proofs. Point at an S3/R2 disk (see config/filesystems.php)
    // by setting PAYMENT_PROOFS_DISK; the app never serves these files publicly.
    'proof_disk' => env('PAYMENT_PROOFS_DISK', 'payment_proofs'),

    // Disk for support attachments (private). Use an S3/R2 disk in production if you prefer.
    'chat_disk' => env('CHAT_DISK', 'local'),

    'proof_max_bytes' => 5 * 1024 * 1024,
    'proof_max_attempts' => 5,

    // Shown on invoices (PDF and web).
    'business' => [
        'name' => env('COMMERCE_BUSINESS_NAME', 'SHANUTECHX'),
        'address' => env('COMMERCE_BUSINESS_ADDRESS', ''),
        'email' => env('COMMERCE_BUSINESS_EMAIL', ''),
    ],
];

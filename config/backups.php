<?php

return [
    // Backup space each client team gets in the platform R2 bucket. The owner can set a different number per team.
    'default_quota_gb' => (float) env('BACKUP_DEFAULT_QUOTA_GB', 5),

    // Pre-chosen platform storage (a storage in the root team). The owner normally picks it in the admin page.
    'storage_uuid' => env('BACKUP_STORAGE_UUID'),

    // A client can ask for "back up now" once per this many minutes per database.
    'run_now_cooldown_minutes' => 5,

    // A restore can be requested once per this many minutes per database.
    'restore_cooldown_minutes' => 10,

    // Schedules a client can pick (cron, server time).
    'presets' => [
        'daily' => ['label' => 'Every day at 02:00', 'cron' => '0 2 * * *'],
        'weekly' => ['label' => 'Every Sunday at 03:00', 'cron' => '0 3 * * 0'],
    ],

    'keep_options' => [3, 7, 14, 30],
];

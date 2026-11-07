<?php

namespace App\Models;

/** Where staff alerts for one event go: a Discord channel webhook. Several routes per event are fine. */
class NotificationRoute extends BaseModel
{
    protected $guarded = [];

    protected $hidden = ['webhook_url'];

    protected $casts = [
        'webhook_url' => 'encrypted',
        'is_enabled' => 'boolean',
    ];

    /** discord.com/api/webhooks/123/abc... -> ...abc123 (never the whole secret) */
    public function maskedUrl(): string
    {
        return 'Discord webhook ending ...'.substr((string) $this->webhook_url, -6);
    }
}

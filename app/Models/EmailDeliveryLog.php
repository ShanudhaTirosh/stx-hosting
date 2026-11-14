<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailDeliveryLog extends Model
{
    protected $table = 'email_delivery_logs';

    protected $fillable = [
        'recipient',
        'subject',
        'template_key',
        'status',
        'mailer',
        'error_message',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public static function record(string $recipient, string $subject, ?string $templateKey, string $status = 'sent', ?string $mailer = null, ?string $errorMessage = null, array $meta = []): self
    {
        return self::create([
            'recipient' => strtolower(trim($recipient)),
            'subject' => $subject,
            'template_key' => $templateKey,
            'status' => $status,
            'mailer' => $mailer ?: config('mail.default'),
            'error_message' => $errorMessage,
            'meta' => $meta,
        ]);
    }
}

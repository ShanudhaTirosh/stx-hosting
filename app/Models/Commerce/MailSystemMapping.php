<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailSystemMapping extends Model
{
    protected $table = 'mail_system_mappings';

    protected $fillable = [
        'email_type',
        'mailbox_id',
        'from_name',
        'reply_to_address',
        'fallback_to_relay',
    ];

    protected $casts = [
        'fallback_to_relay' => 'boolean',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(MailMailbox::class, 'mailbox_id');
    }

    /**
     * Map a template key to its functional system category.
     */
    public static function categoryForTemplate(string $templateKey): string
    {
        return match ($templateKey) {
            'welcome_verify', 'login_code', 'password_reset', 'password_changed' => 'system',
            'invoice_paid', 'order_placed', 'payment_approved', 'payment_rejected', 'order_status' => 'billing',
            'deploy_success', 'deploy_failed' => 'deployments',
            'domain_ssl_warning' => 'support',
            'system_incident' => 'security',
            default => 'default',
        };
    }

    /**
     * Resolve sender email, display name, and reply-to address for an email template.
     *
     * @return array{from_address: string, from_name: string, reply_to: ?string, mailer: string}
     */
    public static function resolveSender(string $templateKey): array
    {
        $defaultFrom = config('mail.from.address', 'no-reply@stxenterprises.com');
        $defaultName = config('mail.from.name', 'STX Platform');

        try {
            $category = self::categoryForTemplate($templateKey);

            $mapping = self::with('mailbox')
                ->where('email_type', $category)
                ->orWhere('email_type', 'default')
                ->orderByRaw("CASE WHEN email_type = ? THEN 1 ELSE 2 END", [$category])
                ->first();

            if ($mapping && $mapping->mailbox && $mapping->mailbox->is_active) {
                return [
                    'from_address' => $mapping->mailbox->address,
                    'from_name' => $mapping->from_name ?: $defaultName,
                    'reply_to' => $mapping->reply_to_address ?: $mapping->mailbox->address,
                    'mailer' => config('mail.default', 'smtp'),
                ];
            }
        } catch (\Throwable) {
            // Fallback gracefully during migrations or DB unavailability
        }

        return [
            'from_address' => $defaultFrom,
            'from_name' => $defaultName,
            'reply_to' => null,
            'mailer' => config('mail.default', 'smtp'),
        ];
    }
}

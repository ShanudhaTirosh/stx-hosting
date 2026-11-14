<?php

namespace App\Models\Commerce;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class MailAppPassword extends Model
{
    protected $table = 'mail_app_passwords';

    protected $fillable = [
        'mailbox_id',
        'user_id',
        'label',
        'password_encrypted',
        'last_used_at',
        'expires_at',
        'is_revoked',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_revoked' => 'boolean',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(MailMailbox::class, 'mailbox_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isValid(): bool
    {
        if ($this->is_revoked) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function getDecryptedPassword(): string
    {
        try {
            return Crypt::decryptString($this->password_encrypted);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Generate a new unique App-Specific Password for a program or script.
     *
     * @return array{model: self, plain_password: string}
     */
    public static function generate(MailMailbox $mailbox, User $user, string $label, ?Carbon $expiresAt = null): array
    {
        // 16-character format: xxxx-xxxx-xxxx-xxxx
        $plain = Str::lower(Str::random(4).'-'.Str::random(4).'-'.Str::random(4).'-'.Str::random(4));

        $model = self::create([
            'mailbox_id' => $mailbox->id,
            'user_id' => $user->id,
            'label' => trim($label),
            'password_encrypted' => Crypt::encryptString($plain),
            'expires_at' => $expiresAt,
            'is_revoked' => false,
        ]);

        return [
            'model' => $model,
            'plain_password' => $plain,
        ];
    }
}

<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use App\Models\Team;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class MailMailbox extends BaseModel
{
    protected $table = 'mail_mailboxes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'quota_mb' => 'integer',
        'used_storage_bytes' => 'integer',
        'is_active' => 'boolean',
        'is_catch_all' => 'boolean',
        'is_platform_managed' => 'boolean',
        'is_sensitive' => 'boolean',
        'auto_reply_enabled' => 'boolean',
        'auto_reply_start_at' => 'datetime',
        'auto_reply_end_at' => 'datetime',
        'forwarding_targets' => 'array',
        'hourly_send_limit' => 'integer',
        'hourly_sent_count' => 'integer',
        'suspended_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(MailDomain::class, 'domain_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(MailAlias::class, 'mailbox_id');
    }

    public function appPasswords(): HasMany
    {
        return $this->hasMany(MailAppPassword::class, 'mailbox_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(MailboxPermission::class, 'mailbox_id');
    }

    public function collaborations(): HasMany
    {
        return $this->hasMany(MailboxCollaboration::class, 'mailbox_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(MailboxAuditLog::class, 'mailbox_id');
    }

    public function systemMappings(): HasMany
    {
        return $this->hasMany(MailSystemMapping::class, 'mailbox_id');
    }

    /**
     * Store mailbox password encrypted with platform APP_KEY.
     */
    public function setPasswordEncrypted(string $plainPassword): self
    {
        $this->password_encrypted = Crypt::encryptString($plainPassword);
        return $this;
    }

    /**
     * Retrieve decrypted mailbox password.
     */
    public function getDecryptedPassword(): ?string
    {
        if (empty($this->password_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->password_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Verify whether a user is authorized to access this mailbox at a given permission level.
     */
    public function isAccessibleBy(User $user, string $level = 'read_only'): bool
    {
        // 1. Platform owner has unrestricted access
        if (RbacService::isPlatformOwner($user)) {
            return true;
        }

        // 2. Team owner/admin of customer mailbox
        if (! $this->is_platform_managed && $user->isAdminOfTeam($this->team_id)) {
            return true;
        }

        // 3. Admin delegated permission check
        $perm = $this->permissions()
            ->active()
            ->where('user_id', $user->id)
            ->first();

        if (! $perm) {
            return false;
        }

        return match ($level) {
            'read_only' => $perm->canRead(),
            'read_write' => $perm->canWrite(),
            'send_as' => $perm->canSend(),
            'manage' => $perm->canManage(),
            default => false,
        };
    }
}

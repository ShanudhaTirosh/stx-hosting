<?php

namespace App\Models\Commerce;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailboxAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'mailbox_audit_logs';

    protected $fillable = [
        'mailbox_id',
        'actor_id',
        'action',
        'target_user_id',
        'ip_address',
        'user_agent',
        'details',
        'created_at',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(MailMailbox::class, 'mailbox_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public static function record(?string $mailboxId, int $actorId, string $action, ?int $targetUserId = null, array $details = []): self
    {
        return self::create([
            'mailbox_id' => $mailboxId,
            'actor_id' => $actorId,
            'action' => $action,
            'target_user_id' => $targetUserId,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}

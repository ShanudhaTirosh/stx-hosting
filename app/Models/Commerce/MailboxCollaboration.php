<?php

namespace App\Models\Commerce;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailboxCollaboration extends Model
{
    protected $table = 'mailbox_collaborations';

    protected $fillable = [
        'mailbox_id',
        'thread_id',
        'assigned_to_user_id',
        'status',
        'last_read_by_user_id',
        'last_read_at',
        'last_replied_by_user_id',
        'last_replied_at',
        'internal_notes',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'last_replied_at' => 'datetime',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(MailMailbox::class, 'mailbox_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function lastReader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_read_by_user_id');
    }

    public function lastReplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_replied_by_user_id');
    }

    /**
     * Mark a thread as read by an admin.
     */
    public static function markRead(string $mailboxId, string $threadId, int $userId): self
    {
        return self::updateOrCreate(
            ['mailbox_id' => $mailboxId, 'thread_id' => $threadId],
            [
                'last_read_by_user_id' => $userId,
                'last_read_at' => now(),
            ]
        );
    }

    /**
     * Assign a thread to an admin.
     */
    public static function assign(string $mailboxId, string $threadId, ?int $userId, string $status = 'in_progress'): self
    {
        return self::updateOrCreate(
            ['mailbox_id' => $mailboxId, 'thread_id' => $threadId],
            [
                'assigned_to_user_id' => $userId,
                'status' => $status,
            ]
        );
    }

    /**
     * Record a reply on a thread.
     */
    public static function markReplied(string $mailboxId, string $threadId, int $userId): self
    {
        return self::updateOrCreate(
            ['mailbox_id' => $mailboxId, 'thread_id' => $threadId],
            [
                'last_replied_by_user_id' => $userId,
                'last_replied_at' => now(),
                'status' => 'resolved',
            ]
        );
    }
}

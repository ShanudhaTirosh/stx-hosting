<?php

namespace App\Models\Commerce;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailboxPermission extends Model
{
    protected $table = 'mailbox_permissions';

    protected $fillable = [
        'mailbox_id',
        'user_id',
        'permission_level',
        'allowed_folders',
        'allowed_labels',
        'is_sensitive_read_only',
        'granted_by',
        'expires_at',
    ];

    protected $casts = [
        'allowed_folders' => 'array',
        'allowed_labels' => 'array',
        'is_sensitive_read_only' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(MailMailbox::class, 'mailbox_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function canRead(): bool
    {
        return $this->isActive() && in_array($this->permission_level, ['read_only', 'read_write', 'send_as', 'manage'], true);
    }

    public function canWrite(): bool
    {
        return $this->isActive() && in_array($this->permission_level, ['read_write', 'send_as', 'manage'], true);
    }

    public function canSend(): bool
    {
        return $this->isActive() && in_array($this->permission_level, ['send_as', 'manage'], true);
    }

    public function canManage(): bool
    {
        return $this->isActive() && $this->permission_level === 'manage';
    }
}

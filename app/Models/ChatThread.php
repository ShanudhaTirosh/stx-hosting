<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A support conversation between a customer team and our staff. */
class ChatThread extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'last_message_at' => 'datetime',
        'client_read_at' => 'datetime',
        'staff_read_at' => 'datetime',
        'client_emailed_at' => 'datetime',
        'last_sender_is_staff' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** The customer has something new from staff. */
    public function scopeUnreadForClient(Builder $query): Builder
    {
        return $query->where('last_sender_is_staff', true)
            ->where(fn ($q) => $q->whereNull('client_read_at')->orWhereColumn('client_read_at', '<', 'last_message_at'));
    }

    /** An open conversation where the customer wrote last and staff have not read it. */
    public function scopeUnreadForStaff(Builder $query): Builder
    {
        return $query->where('status', 'open')
            ->where('last_sender_is_staff', false)
            ->where(fn ($q) => $q->whereNull('staff_read_at')->orWhereColumn('staff_read_at', '<', 'last_message_at'));
    }
}

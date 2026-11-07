<?php

namespace App\Models\Commerce;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceAuditLog extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    /** The audit log is append-only: entries can be written, never changed or removed through the application. */
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit log entries cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('Audit log entries cannot be deleted.'));
    }

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public static function record(string $action, ?string $targetType = null, ?int $targetId = null, ?array $payload = null): static
    {
        return static::create([
            'user_id' => auth()->id(),
            'team_id' => currentTeam()?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'payload' => $payload,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}


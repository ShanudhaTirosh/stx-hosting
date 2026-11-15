<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamInvitation extends Model
{
    protected $fillable = [
        'team_id',
        'uuid',
        'email',
        'role',
        'link',
        'via',
        'access',
        'max_uses',
        'uses_count',
        'expires_at',
    ];

    protected $casts = [
        'link' => 'encrypted',
        'access' => 'array',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * Set the email attribute to lowercase or null.
     */
    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value ? strtolower($value) : null;
    }

    /**
     * Pivot attributes for the new `team_user` row.
     *
     * @return array{role: string, access: string|null}
     */
    public function membershipAttributes(): array
    {
        return [
            'role' => $this->role,
            'access' => $this->role === 'member' && $this->access !== null ? json_encode($this->access) : null,
        ];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public static function ownedByCurrentTeam()
    {
        return TeamInvitation::whereTeamId(currentTeam()->id);
    }

    public function isShareableLink(): bool
    {
        return empty($this->email);
    }

    public function isValid(): bool
    {
        if ($this->hasExpired()) {
            $this->delete();
            if (filled($this->email)) {
                $user = User::whereEmail($this->email)->first();
                if (filled($user)) {
                    $user->deleteIfNotVerifiedAndForcePasswordReset();
                }
            }

            return false;
        }

        if ($this->max_uses > 0 && $this->uses_count >= $this->max_uses) {
            $this->delete();

            return false;
        }

        return true;
    }

    public function hasExpired(): bool
    {
        if ($this->expires_at !== null) {
            return now()->greaterThan($this->expires_at);
        }

        $days = (int) (config('constants.invitation.link.expiration_days') ?: 3);

        return $this->created_at->diffInDays(now()) > $days;
    }

    /**
     * Increment uses_count and remove invitation if limit reached.
     */
    public function recordUse(): void
    {
        $this->increment('uses_count');

        if ($this->max_uses > 0 && $this->uses_count >= $this->max_uses) {
            $this->delete();
        }
    }
}

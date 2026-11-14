<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class LoginVerificationCode extends Model
{
    protected $table = 'login_verification_codes';

    protected $fillable = [
        'user_id',
        'code_hash',
        'ip_address',
        'user_agent',
        'attempts',
        'is_used',
        'expires_at',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'attempts' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(string $inputCode): bool
    {
        if ($this->is_used) {
            return false;
        }

        if ($this->expires_at->isPast()) {
            return false;
        }

        if ($this->attempts >= 5) {
            return false;
        }

        return Hash::check($inputCode, $this->code_hash);
    }
}

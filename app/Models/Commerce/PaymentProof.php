<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends BaseModel
{
    protected $guarded = [];

    public const STATUS_VERIFYING = 'verifying';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_AUTO_VERIFIED = 'auto_verified';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_RESUBMIT = 'resubmission_requested';

    protected $casts = [
        'file_size_bytes' => 'integer',
        'reviewed_at' => 'datetime',
        'extracted_datetime' => 'datetime',
        'confidence_score' => 'float',
        'extracted_amount_cents' => 'integer',
        'ai_analysis' => 'array',
        'discrepancies' => 'array',
        'attempt_no' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_REVIEW);
    }

    public function scopeVerifying(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VERIFYING);
    }

    public function isApproved(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_AUTO_VERIFIED], true);
    }

    public function isAutoVerified(): bool
    {
        return $this->status === self::STATUS_AUTO_VERIFIED || $this->verification_basis === 'ai_confidence';
    }

    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function isVerifying(): bool
    {
        return $this->status === self::STATUS_VERIFYING;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function hasDiscrepancies(): bool
    {
        return ! empty($this->discrepancies);
    }

    public function confidencePercent(): int
    {
        return (int) round(($this->confidence_score ?? 0.0) * 100);
    }

    public function confidenceLevel(): string
    {
        $score = (float) ($this->confidence_score ?? 0.0);
        if ($score >= 0.90) {
            return 'high';
        }
        if ($score >= 0.60) {
            return 'medium';
        }

        return 'low';
    }
}

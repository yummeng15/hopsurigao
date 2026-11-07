<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REFUNDED_TEST = 'refunded_test';

    public const CATEGORY_OPERATOR_NO_SHOW = 'operator_no_show';

    public const CATEGORY_WEATHER = 'weather';

    /** @var list<string> */
    public const ELIGIBLE_CATEGORIES = [
        self::CATEGORY_OPERATOR_NO_SHOW,
        self::CATEGORY_WEATHER,
    ];

    protected $fillable = [
        'booking_id',
        'payment_id',
        'user_id',
        'tour_operator_id',
        'category',
        'reason',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('tour_operator', function (Builder $builder): void {
            $user = auth()->user();

            if ($user?->role?->name !== 'Admin') {
                return;
            }

            $operator = $user->tourOperator;

            if ($operator === null || ! $operator->is_active) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where('tour_operator_id', $operator->id);
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tourOperator(): BelongsTo
    {
        return $this->belongsTo(TourOperator::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_REFUNDED_TEST => 'Refunded (Test)',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            self::CATEGORY_OPERATOR_NO_SHOW => 'Operator or boat did not arrive',
            self::CATEGORY_WEATHER => 'Unsafe weather cancellation',
            default => $this->category
                ? ucfirst(str_replace('_', ' ', (string) $this->category))
                : 'Not specified',
        };
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}

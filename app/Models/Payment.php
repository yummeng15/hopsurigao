<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'amount',
        'payment_method',
        'gateway',
        'gateway_checkout_id',
        'payment_status',
        'transaction_reference',
        'notes',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Payment Isolation
        |--------------------------------------------------------------------------
        |
        | An operator can see payments only when the related
        | booking belongs to that operator.
        |
        */

        static::addGlobalScope(
            'tour_operator',
            function (Builder $builder): void {
                $user = auth()->user();

                if ($user?->role?->name !== 'Admin') {
                    return;
                }

                $operator = $user->tourOperator;

                if (
                    $operator === null ||
                    ! $operator->is_active
                ) {
                    $builder->whereRaw('1 = 0');

                    return;
                }

                $builder->whereHas(
                    'booking.tourDate.package',
                    function (Builder $query) use ($operator): void {
                        $query->where(
                            'packages.tour_operator_id',
                            $operator->id
                        );
                    }
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Protect Payment Creation and Updates
        |--------------------------------------------------------------------------
        */

        static::saving(function (Payment $payment): void {
            $user = auth()->user();

            if ($user?->role?->name !== 'Admin') {
                return;
            }

            $operator = $user->tourOperator;

            abort_unless(
                $operator !== null && $operator->is_active,
                403,
                'You are not assigned to an active tour operator.'
            );

            self::ensureBookingBelongsToOperator(
                $payment->booking_id,
                $operator->id
            );

            if ($payment->exists) {
                self::ensureBookingBelongsToOperator(
                    $payment->getRawOriginal('booking_id'),
                    $operator->id
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Protect Payment Deletion
        |--------------------------------------------------------------------------
        */

        static::deleting(function (Payment $payment): void {
            $user = auth()->user();

            if ($user?->role?->name !== 'Admin') {
                return;
            }

            $operator = $user->tourOperator;

            abort_unless(
                $operator !== null && $operator->is_active,
                403,
                'You are not assigned to an active tour operator.'
            );

            self::ensureBookingBelongsToOperator(
                $payment->getRawOriginal('booking_id'),
                $operator->id
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Booking Ownership
    |--------------------------------------------------------------------------
    */

    private static function ensureBookingBelongsToOperator(
        mixed $bookingId,
        int $operatorId
    ): void {
        $allowed = Booking::query()
            ->whereKey($bookingId)
            ->whereHas(
                'tourDate.package',
                function (Builder $query) use ($operatorId): void {
                    $query->where(
                        'packages.tour_operator_id',
                        $operatorId
                    );
                }
            )
            ->exists();

        abort_unless(
            $allowed,
            403,
            'You cannot manage payments belonging to another tour operator.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickupSchedule extends Model
{
    protected $fillable = [
        'tour_date_id',
        'pickup_location_id',
        'pickup_time',
    ];

    protected $casts = [
        'pickup_time' => 'datetime:H:i',
    ];

    protected static function booted(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Tour Operator Isolation
        |--------------------------------------------------------------------------
        |
        | Superadmin:
        | Can access pickup schedules for every operator.
        |
        | Admin:
        | Can access pickup schedules only for their own operator.
        |
        | Tourist:
        | Existing tourist access rules remain unchanged.
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
                    'tourDate.package',
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
        | Protect Creation and Updates
        |--------------------------------------------------------------------------
        */

        static::saving(function (PickupSchedule $schedule): void {
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

            self::ensureTourDateOwnership(
                $schedule->tour_date_id,
                $operator->id
            );

            if ($schedule->exists) {
                self::ensureTourDateOwnership(
                    $schedule->getRawOriginal('tour_date_id'),
                    $operator->id
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Protect Deletion
        |--------------------------------------------------------------------------
        */

        static::deleting(function (PickupSchedule $schedule): void {
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

            self::ensureTourDateOwnership(
                $schedule->getRawOriginal('tour_date_id'),
                $operator->id
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Tour Date Ownership
    |--------------------------------------------------------------------------
    */

    private static function ensureTourDateOwnership(
        mixed $tourDateId,
        int $operatorId
    ): void {
        $allowed = TourDate::query()
            ->whereKey($tourDateId)
            ->whereHas(
                'package',
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
            'You cannot manage another tour operator\'s pickup schedules.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function tourDate(): BelongsTo
    {
        return $this->belongsTo(TourDate::class);
    }

    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(PickupLocation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
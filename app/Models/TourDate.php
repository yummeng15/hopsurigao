<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TourDate extends Model
{
    protected $fillable = [
        'package_id',
        'tour_date',
        'capacity',
        'travel_closed',
    ];

    protected function casts(): array
    {
        return [
            'tour_date' => 'date',
            'travel_closed' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Tour Operator Schedule Isolation
        |--------------------------------------------------------------------------
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
                    'package',
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
        | Protect Schedule Creation and Updates
        |--------------------------------------------------------------------------
        */

        static::saving(function (TourDate $tourDate): void {
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

            self::ensurePackageBelongsToOperator(
                $tourDate->package_id,
                $operator->id
            );

            if ($tourDate->exists) {
                self::ensurePackageBelongsToOperator(
                    $tourDate->getRawOriginal('package_id'),
                    $operator->id
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Protect Schedule Deletion
        |--------------------------------------------------------------------------
        */

        static::deleting(function (TourDate $tourDate): void {
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

            self::ensurePackageBelongsToOperator(
                $tourDate->getRawOriginal('package_id'),
                $operator->id
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Package Ownership
    |--------------------------------------------------------------------------
    */

    private static function ensurePackageBelongsToOperator(
        mixed $packageId,
        int $operatorId
    ): void {
        $allowed = Package::query()
            ->whereKey($packageId)
            ->where('packages.tour_operator_id', $operatorId)
            ->exists();

        abort_unless(
            $allowed,
            403,
            'You cannot manage another tour operator\'s schedules.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function times(): HasMany
    {
        return $this->hasMany(TourTime::class);
    }

    public function pickupSchedules(): HasMany
    {
        return $this->hasMany(PickupSchedule::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function weatherForecast(): HasOne
    {
        return $this->hasOne(WeatherForecast::class);
    }
}
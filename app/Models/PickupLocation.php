<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickupLocation extends Model
{
    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    protected static function booted(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Pickup Location Visibility
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

                $builder->where(
                    function (Builder $query) use ($operator): void {
                        $query
                            ->whereNull('pickup_locations.tour_operator_id')
                            ->orWhere(
                                'pickup_locations.tour_operator_id',
                                $operator->id
                            );
                    }
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Assign Operator Ownership
        |--------------------------------------------------------------------------
        */

        static::creating(function (PickupLocation $location): void {
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

            $location->tour_operator_id = $operator->id;
        });

        /*
        |--------------------------------------------------------------------------
        | Protect Updates
        |--------------------------------------------------------------------------
        */

        static::updating(function (PickupLocation $location): void {
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

            abort_unless(
                $location->getRawOriginal('tour_operator_id') !== null
                    && (int) $location->getRawOriginal('tour_operator_id') === (int) $operator->id
                    && (int) $location->tour_operator_id === (int) $operator->id,
                403,
                'You cannot edit this pickup location.'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Protect Deletion
        |--------------------------------------------------------------------------
        */

        static::deleting(function (PickupLocation $location): void {
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

            abort_unless(
                $location->getRawOriginal('tour_operator_id') !== null
                    && (int) $location->getRawOriginal('tour_operator_id') === (int) $operator->id,
                403,
                'You cannot delete this pickup location.'
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function tourOperator(): BelongsTo
    {
        return $this->belongsTo(
            TourOperator::class,
            'tour_operator_id'
        );
    }

    public function pickupSchedules(): HasMany
    {
        return $this->hasMany(PickupSchedule::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
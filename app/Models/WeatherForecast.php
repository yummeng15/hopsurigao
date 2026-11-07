<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeatherForecast extends Model
{
    use HasFactory;

    protected $table = 'weather_forecasts';

    protected $fillable = [
        'tour_date_id',
        'forecast_date',
        'location',
        'latitude',
        'longitude',
        'temperature_min',
        'temperature_max',
        'temperature_current',
        'weather_condition',
        'weather_code',
        'rainfall_probability',
        'rainfall',
        'wind_speed',
        'wind_gusts',
        'wind_direction',
        'humidity',
        'advisory_level',
        'advisory_badge',
        'advisory_message',
        'is_outdated',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'forecast_date' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
            'temperature_min' => 'decimal:2',
            'temperature_max' => 'decimal:2',
            'temperature_current' => 'decimal:2',
            'weather_code' => 'integer',
            'rainfall_probability' => 'integer',
            'rainfall' => 'decimal:2',
            'wind_speed' => 'decimal:2',
            'wind_gusts' => 'decimal:2',
            'wind_direction' => 'integer',
            'humidity' => 'integer',
            'is_outdated' => 'boolean',
            'fetched_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Weather Forecast Isolation
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
        | Protect Weather Record Creation and Updates
        |--------------------------------------------------------------------------
        */

        static::saving(function (WeatherForecast $forecast): void {
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
                $forecast->tour_date_id,
                $operator->id
            );

            if ($forecast->exists) {
                self::ensureTourDateOwnership(
                    $forecast->getRawOriginal('tour_date_id'),
                    $operator->id
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Protect Weather Record Deletion
        |--------------------------------------------------------------------------
        */

        static::deleting(function (WeatherForecast $forecast): void {
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
                $forecast->getRawOriginal('tour_date_id'),
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
            'You cannot manage weather records belonging to another tour operator.'
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
}
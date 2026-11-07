<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'tour_date_id',
        'pickup_schedule_id',
        'pickup_latitude',
        'pickup_longitude',
        'number_of_guests',
        'phone_number',
        'nationality',
        'special_request',
        'booking_status',
        'qr_token',
        'is_archived',
        'archived_at',
        // Price snapshots are deliberately NOT mass assignable.
    ];

    protected function casts(): array
    {
        return [
            'pickup_latitude' => 'float',
            'pickup_longitude' => 'float',
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
            'booked_unit_price' => 'decimal:2',
            'booked_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Tour Operator Booking Isolation
        |--------------------------------------------------------------------------
        |
        | Superadmin:
        |   Can access bookings from all tour operators.
        |
        | Admin:
        |   Can access only bookings connected to packages
        |   belonging to their assigned tour operator.
        |
        | Tourist:
        |   Existing tourist access rules remain unchanged.
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
                    ! $operator->canManageRecords()
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
        | Protect Booking Creation and Updates
        |--------------------------------------------------------------------------
        */

        static::saving(function (Booking $booking): void {
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

            // The selected tour date must belong to this operator.
            self::ensureTourDateBelongsToOperator(
                $booking->tour_date_id,
                $operator->id
            );

            // Also protect the original booking when changing its tour date.
            if ($booking->exists) {
                self::ensureTourDateBelongsToOperator(
                    $booking->getRawOriginal('tour_date_id'),
                    $operator->id
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Protect Booking Deletion
        |--------------------------------------------------------------------------
        */

        static::deleting(function (Booking $booking): void {
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

            self::ensureTourDateBelongsToOperator(
                $booking->getRawOriginal('tour_date_id'),
                $operator->id
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Freeze Price When the Booking Is Created
        |--------------------------------------------------------------------------
        |
        | The price is read from the database, never from a client request.
        | It also applies to Operator walk-in bookings created via Eloquent.
        |
        */

        static::creating(function (Booking $booking): void {
            $price = DB::table('tour_dates')
                ->join('packages', 'packages.id', '=', 'tour_dates.package_id')
                ->where('tour_dates.id', $booking->tour_date_id)
                ->value('packages.price');

            $guests = (int) $booking->number_of_guests;
            $unitCents = (int) round((float) $price * 100);

            if ($price === null || $guests < 1 || $unitCents <= 0) {
                throw ValidationException::withMessages([
                    'tour_date_id' => 'The tour must have a valid price and at least one guest.',
                ]);
            }

            $booking->booked_unit_price = number_format($unitCents / 100, 2, '.', '');
            $booking->booked_total = number_format(($unitCents * $guests) / 100, 2, '.', '');
            $booking->price_snapshot_source = 'at_booking';
        });

        /*
        |--------------------------------------------------------------------------
        | Prevent Changing the Agreed Quote
        |--------------------------------------------------------------------------
        |
        | If a reservation needs different guests or a different tour date,
        | handle that through a separately reviewed repricing workflow.
        |
        */

        static::updating(function (Booking $booking): void {
            if ($booking->isDirty([
                'tour_date_id',
                'number_of_guests',
                'booked_unit_price',
                'booked_total',
                'price_snapshot_source',
            ])) {
                throw ValidationException::withMessages([
                    'number_of_guests' => 'The agreed booking price and guest count cannot be changed after reservation.',
                ]);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Generate Booking QR Token
        |--------------------------------------------------------------------------
        */

        static::saving(function (Booking $booking): void {
            if (
                $booking->booking_status === 'confirmed'
                && empty($booking->qr_token)
            ) {
                $booking->qr_token = (string) Str::uuid();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Tour Date Ownership
    |--------------------------------------------------------------------------
    */

    private static function ensureTourDateBelongsToOperator(
        mixed $tourDateId,
        int $operatorId
    ): void {
        $belongsToOperator = TourDate::query()
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
            $belongsToOperator,
            403,
            'You cannot manage bookings belonging to another tour operator.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tourDate(): BelongsTo
    {
        return $this->belongsTo(TourDate::class);
    }

    public function pickupSchedule(): BelongsTo
    {
        return $this->belongsTo(PickupSchedule::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(BookingFeedback::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Booking Calculations
    |--------------------------------------------------------------------------
    */

    public function packageTotal(): float
    {
        if ($this->booked_total !== null) {
            return (float) $this->booked_total;
        }

        // Compatibility for records inserted outside Eloquent without a
        // price snapshot. New reservations always receive a snapshot.
        $this->loadMissing('tourDate.package');

        return round(
            ((float) $this->tourDate?->package?->price)
                * (int) $this->number_of_guests,
            2
        );
    }

    public function totalCentavos(): int
    {
        return (int) round($this->packageTotal() * 100);
    }

    public function amountReceived(): float
    {
        $this->loadMissing('payments');

        return round(
            (float) $this->payments
                ->whereIn('payment_status', ['paid', 'partial'])
                ->sum('amount'),
            2
        );
    }

    public function remainingBalance(): float
    {
        return round(
            max(
                0,
                $this->packageTotal() - $this->amountReceived()
            ),
            2
        );
    }
}

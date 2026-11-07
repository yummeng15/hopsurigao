<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingFeedback extends Model
{
    protected $table = 'booking_feedback';

    protected $fillable = [
        'booking_id',
        'user_id',
        'enjoyment',
        'operator_service',
        'overall_experience',
        'comment',
    ];

    protected static function booted(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Feedback Isolation
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
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

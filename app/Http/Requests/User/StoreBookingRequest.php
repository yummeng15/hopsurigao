<?php

namespace App\Http\Requests\User;

use App\Models\Booking;
use App\Models\PickupSchedule;
use App\Models\TourDate;
use App\Services\TourDateBookingAvailability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'tour_date_id' => [
                'required',
                'integer',
                Rule::exists('tour_dates', 'id'),
            ],

            'pickup_schedule_id' => [
                'required',
                'integer',
                Rule::exists('pickup_schedules', 'id'),
            ],

            'pickup_latitude' => [
                'required',
                'numeric',
                'between:4,22',
            ],

            'pickup_longitude' => [
                'required',
                'numeric',
                'between:116,128',
            ],

            'number_of_guests' => [
                'required',
                'integer',
                'min:1',
            ],

            'phone_number' => [
                'required',
                'string',
                'max:30',
            ],

            'nationality' => [
                'required',
                'string',
                'max:100',
            ],

            'special_request' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            /*
             * Stop here if the basic validation already failed.
             */
            if (
                ! $this->filled('tour_date_id') ||
                ! $this->filled('pickup_schedule_id') ||
                ! $this->filled('number_of_guests')
            ) {
                return;
            }

            $tourDate = TourDate::query()
                ->with(['weatherForecast', 'package.tourOperator'])
                ->find($this->integer('tour_date_id'));

            if (! $tourDate) {
                return;
            }

            $bookedGuests = (int) Booking::query()
                ->where('tour_date_id', $tourDate->id)
                ->whereIn('booking_status', ['pending', 'confirmed'])
                ->sum('number_of_guests');

            $assessment = app(TourDateBookingAvailability::class)->assess(
                $tourDate->package,
                $tourDate,
                $bookedGuests,
                $tourDate->weatherForecast?->advisory_level,
                $tourDate->weatherForecast !== null,
            );

            if (! $assessment['bookable']) {
                $validator->errors()->add(
                    'tour_date_id',
                    $assessment['reason'] ?? 'This tour date is unavailable.',
                );
            }

            /*
             * ------------------------------------------------------
             * PICKUP SCHEDULE VALIDATION
             * ------------------------------------------------------
             *
             * Make sure the selected pickup schedule actually
             * belongs to the selected tour date.
             */
            $pickupScheduleExists = PickupSchedule::query()
                ->whereKey(
                    $this->integer('pickup_schedule_id')
                )
                ->where(
                    'tour_date_id',
                    $tourDate->id
                )
                ->exists();

            if (! $pickupScheduleExists) {
                $validator->errors()->add(
                    'pickup_schedule_id',
                    'The selected pickup schedule is not available for this tour date.'
                );
            }

            /*
             * ------------------------------------------------------
             * DUPLICATE BOOKING CHECK
             * ------------------------------------------------------
             *
             * A user cannot create another active booking
             * for the same tour date.
             *
             * Cancelled bookings are ignored, so a user can
             * book the same tour again after cancelling.
             */
            $duplicateBooking = Booking::query()
                ->where(
                    'user_id',
                    auth()->id()
                )
                ->where(
                    'tour_date_id',
                    $tourDate->id
                )
                ->whereIn('booking_status', [
                    'pending',
                    'confirmed',
                ])
                ->exists();

            if ($duplicateBooking) {
                $validator->errors()->add(
                    'tour_date_id',
                    'You already have an active booking for this tour date.'
                );
            }

            /*
             * ------------------------------------------------------
             * CAPACITY CHECK
             * ------------------------------------------------------
             *
             * Only pending and confirmed bookings consume capacity.
             *
             * Cancelled bookings do NOT consume capacity.
             */
            $bookedGuests = $tourDate
                ->bookings()
                ->whereIn('booking_status', [
                    'pending',
                    'confirmed',
                ])
                ->sum('number_of_guests');

            /*
             * Actual available seats before this booking.
             */
            $remainingCapacity = max(
                0,
                $tourDate->capacity - $bookedGuests
            );

            /*
             * Requested guests cannot exceed available capacity.
             */
            if (
                $this->integer('number_of_guests') >
                $remainingCapacity
            ) {
                $validator->errors()->add(
                    'number_of_guests',
                    "Only {$remainingCapacity} guest(s) are available for this tour date."
                );
            }
        });
    }
}

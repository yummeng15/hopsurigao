<?php

namespace App\Http\Requests\Admin;

use App\Models\PackageDestination;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $destinations = PackageDestination::normalizeNames(
            $this->input('destinations', [])
        );

        $this->merge([
            'destinations' => $destinations,
            'destination' => implode(', ', $destinations),
        ]);
    }

    public function rules(): array
    {
        return [
            /*
             * -------------------------------------------------------------
             * Package
             * -------------------------------------------------------------
             */

            'package_name' => [
                'required',
                'string',
                'max:255',
            ],

            'boat_name' => [
                'required',
                'string',
                'max:255',
            ],

            'destinations' => [
                'required',
                'array',
                'min:1',
            ],

            'destinations.*' => [
                'required',
                'string',
                'max:255',
                'distinct',
            ],

            'destination' => [
                'required',
                'string',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'status' => [
                'required',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],

            /*
             * -------------------------------------------------------------
             * Images
             * -------------------------------------------------------------
             */

            'images' => [
                'nullable',
                'array',
                'max:10',
            ],

            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048', // 2MB in KB
            ],

            'removed_images' => [
                'nullable',
                'array',
            ],

            'removed_images.*' => [
                'string',
            ],

            /*
             * -------------------------------------------------------------
             * Inclusions
             * -------------------------------------------------------------
             */

            'inclusions' => [
                'nullable',
                'array',
            ],

            'inclusions.*.description' => [
                'required',
                'string',
                'max:500',
            ],

            'activities' => [
                'nullable',
                'array',
            ],

            'activities.*.description' => [
                'required',
                'string',
                'max:500',
            ],

            /*
             * -------------------------------------------------------------
             * Reminders
             * -------------------------------------------------------------
             */

            'reminders' => [
                'nullable',
                'array',
            ],

            'reminders.*.description' => [
                'required',
                'string',
                'max:500',
            ],

            /*
             * -------------------------------------------------------------
             * Tour dates
             * -------------------------------------------------------------
             */

            'tour_dates' => [
                'nullable',
                'array',
            ],

            'tour_dates.*.tour_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
            ],

            'tour_dates.*.capacity' => [
                'required',
                'integer',
                'min:1',
            ],

            /*
             * -------------------------------------------------------------
             * Tour times
             * -------------------------------------------------------------
             */

            'tour_dates.*.times' => [
                'nullable',
                'array',
            ],

            'tour_dates.*.times.*.time' => [
                'required',
                'date_format:H:i',
            ],

            'tour_dates.*.times.*.description' => [
                'required',
                'string',
                'max:255',
            ],

            /*
             * -------------------------------------------------------------
             * Pickup schedules
             * -------------------------------------------------------------
             */

            'tour_dates.*.pickup_schedules' => [
                'nullable',
                'array',
            ],

            'tour_dates.*.pickup_schedules.*.pickup_location_id' => [
                'required',
                Rule::exists(
                    'pickup_locations',
                    'id'
                ),
            ],

            'tour_dates.*.pickup_schedules.*.pickup_time' => [
                'required',
                'date_format:H:i',
            ],
        ];
    }

    /**
     * Additional validation.
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (Validator $validator) {

                $tourDates =
                    $this->input(
                        'tour_dates',
                        []
                    );

                /*
                 * Track dates manually.
                 *
                 * We intentionally do NOT use Laravel's `distinct`
                 * rule because we want one clean error on the duplicate
                 * field instead of confusing duplicate errors.
                 */
                $seenDates = [];

                foreach (
                    $tourDates as $dateIndex => $tourDate
                ) {

                    $date =
                        $tourDate['tour_date']
                        ?? null;

                    /*
                     * Skip empty dates.
                     *
                     * The required rule handles them.
                     */
                    if (! $date) {
                        continue;
                    }

                    /*
                     * -----------------------------------------------------
                     * TOUR DATE MUST NOT BE IN THE PAST
                     * -----------------------------------------------------
                     */

                    try {
                        $tourDateValue =
                            Carbon::createFromFormat(
                                'Y-m-d',
                                $date
                            )->startOfDay();

                        if (
                            $tourDateValue->lt(
                                Carbon::today()
                            )
                        ) {
                            $validator->errors()->add(
                                "tour_dates.{$dateIndex}.tour_date",
                                'Tour date cannot be in the past. Please choose today or a future date.'
                            );
                        }
                    } catch (\Throwable $e) {
                        /*
                         * The date_format rule handles invalid dates.
                         */
                    }

                    /*
                     * -----------------------------------------------------
                     * DUPLICATE TOUR DATE IN FORM
                     * -----------------------------------------------------
                     */

                    if (
                        isset(
                            $seenDates[$date]
                        )
                    ) {
                        $validator->errors()->add(
                            "tour_dates.{$dateIndex}.tour_date",
                            'This tour date is already added. Please choose a different date.'
                        );
                    } else {
                        $seenDates[$date] = $dateIndex;
                    }

                    /*
                     * -----------------------------------------------------
                     * TIMES
                     * -----------------------------------------------------
                     */

                    $times =
                        $tourDate['times']
                        ?? [];

                    $pickups =
                        $tourDate['pickup_schedules']
                        ?? [];

                    /*
                     * -----------------------------------------------------
                     * TOUR TIMES MUST BE CHRONOLOGICAL
                     * -----------------------------------------------------
                     *
                     * Example:
                     *
                     * Breakfast 08:00
                     * Snacks    10:00
                     * Lunch     12:00
                     *
                     * Valid.
                     *
                     * Breakfast 11:00
                     * Snacks    10:00
                     *
                     * Invalid.
                     */

                    $previousTime = null;

                    foreach (
                        $times as $timeIndex => $timeData
                    ) {

                        $currentTime =
                            $timeData['time']
                            ?? null;

                        if (! $currentTime) {
                            continue;
                        }

                        if (
                            $previousTime !== null &&
                            $currentTime <= $previousTime
                        ) {
                            $validator->errors()->add(
                                "tour_dates.{$dateIndex}.times.{$timeIndex}.time",
                                'Tour times must be in chronological order.'
                            );
                        }

                        $previousTime =
                            $currentTime;
                    }

                    /*
                     * -----------------------------------------------------
                     * FIND FIRST TOUR TIME
                     * -----------------------------------------------------
                     */

                    $firstTourTime = null;

                    foreach (
                        $times as $timeData
                    ) {

                        $time =
                            $timeData['time']
                            ?? null;

                        if (! $time) {
                            continue;
                        }

                        if (
                            $firstTourTime === null ||
                            $time < $firstTourTime
                        ) {
                            $firstTourTime =
                                $time;
                        }
                    }

                    /*
                     * -----------------------------------------------------
                     * PICKUP MUST BE BEFORE FIRST TOUR TIME
                     * -----------------------------------------------------
                     */

                    if (
                        $firstTourTime !== null
                    ) {

                        foreach (
                            $pickups as $pickupIndex => $pickupData
                        ) {

                            $pickupTime =
                                $pickupData['pickup_time']
                                ?? null;

                            if (! $pickupTime) {
                                continue;
                            }

                            if (
                                $pickupTime >=
                                $firstTourTime
                            ) {

                                $formattedTime =
                                    Carbon::createFromFormat(
                                        'H:i',
                                        $firstTourTime
                                    )->format(
                                        'g:i A'
                                    );

                                $validator->errors()->add(
                                    "tour_dates.{$dateIndex}.pickup_schedules.{$pickupIndex}.pickup_time",
                                    "Pickup time must be earlier than the first tour time ({$formattedTime})."
                                );
                            }
                        }
                    }
                }
            }
        );
    }

    public function messages(): array
    {
        return [
            'destinations.required' => 'Please add at least one destination.',
            'destinations.min' => 'Please add at least one destination.',
            'destinations.*.distinct' => 'This destination is already added.',

            'images.max' => 'You can only upload up to 10 images per package.',
            'images.*.image' => 'Each uploaded file must be a valid image.',
            'images.*.mimes' => 'Each image must be a file of type: jpg, jpeg, png, webp.',
            'images.*.max' => 'Each image may not be greater than 2MB.',
            'images.*.uploaded' => 'The image failed to upload. Please ensure the file is under 2MB and try again.',

            /*
             * Tour date
             */

            'tour_dates.*.tour_date.required' => 'Please select a tour date.',

            'tour_dates.*.tour_date.date' => 'Please enter a valid tour date.',

            'tour_dates.*.tour_date.date_format' => 'Tour date must be in the format YYYY-MM-DD.',

            /*
             * Times
             */

            'tour_dates.*.times.*.time.required' => 'Please enter a tour time.',

            'tour_dates.*.times.*.time.date_format' => 'Tour time must be in the format HH:MM.',

            /*
             * Pickup
             */

            'tour_dates.*.pickup_schedules.*.pickup_time.required' => 'Please enter a pickup time.',

            'tour_dates.*.pickup_schedules.*.pickup_time.date_format' => 'Pickup time must be in the format HH:MM.',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Models\Package;
use App\Models\PackageDestination;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePackageRequest extends FormRequest
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
                'after_or_equal:today',
                'distinct',
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
                 * ---------------------------------------------------------
                 * CHECK DATABASE DUPLICATES
                 * ---------------------------------------------------------
                 *
                 * Store request is only for creating a new package.
                 *
                 * If a date already exists for this package,
                 * return a validation error instead of allowing
                 * MySQL to throw a UniqueConstraintViolationException.
                 *
                 * Note:
                 * During package creation there normally isn't a package
                 * ID yet, so this database duplicate check is mainly
                 * relevant when the request is used with an existing
                 * package context.
                 */

                $packageId =
                    $this->route('package')?->id
                    ?? (
                        $this->route('package')
                        instanceof Package
                        ? $this->route('package')->id
                        : null
                    );

                /*
                 * ---------------------------------------------------------
                 * VALIDATE EACH TOUR DATE
                 * ---------------------------------------------------------
                 */

                foreach (
                    $tourDates as $dateIndex => $tourDate
                ) {

                    $date =
                        $tourDate['tour_date']
                        ?? null;

                    if (! $date) {
                        continue;
                    }

                    /*
                     * -----------------------------------------------------
                     * DATE MUST NOT BE IN THE PAST
                     * -----------------------------------------------------
                     */

                    if (
                        Carbon::parse($date)
                            ->startOfDay()
                            ->lt(
                                Carbon::today()
                            )
                    ) {
                        $validator->errors()->add(
                            "tour_dates.{$dateIndex}.tour_date",
                            'Tour date cannot be in the past. Please choose today or a future date.'
                        );
                    }

                    /*
                     * -----------------------------------------------------
                     * DATABASE DUPLICATE
                     * -----------------------------------------------------
                     */

                    if ($packageId) {

                        $exists =
                            Package::find($packageId)
                                ?->tourDates()
                                ->whereDate(
                                    'tour_date',
                                    $date
                                )
                                ->exists();

                        if ($exists) {
                            $validator->errors()->add(
                                "tour_dates.{$dateIndex}.tour_date",
                                'This tour date is already added. Please choose a different date.'
                            );
                        }
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
                     * TOUR TIMES MUST BE CHRONOLOGICAL
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
                     * FIRST TOUR TIME
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
                            $firstTourTime = $time;
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

                                $validator->errors()->add(
                                    "tour_dates.{$dateIndex}.pickup_schedules.{$pickupIndex}.pickup_time",
                                    'Pickup time must be earlier than the first tour time ('.
                                        Carbon::createFromFormat(
                                            'H:i',
                                            $firstTourTime
                                        )->format('g:i A').
                                        ').'
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

            'tour_dates.*.tour_date.required' => 'Please select a tour date.',

            'tour_dates.*.tour_date.date_format' => 'Please enter a valid tour date.',

            'tour_dates.*.tour_date.after_or_equal' => 'Tour date cannot be in the past. Please choose today or a future date.',

            'tour_dates.*.tour_date.distinct' => 'This tour date is already added. Please choose a different date.',

            'tour_dates.*.times.*.time.date_format' => 'Tour time must be in the format HH:MM.',

            'tour_dates.*.pickup_schedules.*.pickup_time.date_format' => 'Pickup time must be in the format HH:MM.',
        ];
    }
}

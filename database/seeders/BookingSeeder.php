<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Role;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BookingSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $userRole = Role::where('name', 'User')->first();
        $tourists = User::where('role_id', $userRole?->id)->get();

        if ($tourists->isEmpty()) {
            $tourists = User::all();
        }

        $tourDates = TourDate::with(['pickupSchedules', 'package'])->get();

        if ($tourists->isEmpty() || $tourDates->isEmpty()) {
            return;
        }

        $nationalities = [
            'Filipino',
            'American',
            'Australian',
            'German',
            'Japanese',
            'Canadian',
            'British',
            'French',
            'Korean',
        ];

        $specialRequests = [
            'Vegetarian meal preference for 2 passengers',
            'Senior citizen in group — requested front seat on outrigger boat',
            'Snorkel set rental requested for all guests',
            'Celebrating wedding anniversary — requested complimentary photo assistance',
            'First time island hopping, requested non-swimmer safety briefing',
            null,
            null,
            null,
        ];

        $phonePrefixes = ['0917', '0918', '0920', '0928', '0939', '0977', '0995'];

        $today = Carbon::today();

        // Clear existing bookings before seeding
        Booking::query()->delete();

        foreach ($tourDates as $tourDate) {
            if ($tourDate->pickupSchedules->isEmpty()) {
                continue;
            }

            $isPastDate = Carbon::parse($tourDate->tour_date)->lt($today);

            // 1 to 4 bookings per tour date
            $bookingCount = fake()->numberBetween(1, 4);

            for ($i = 0; $i < $bookingCount; $i++) {
                $tourist = $tourists->random();
                $pickupSchedule = $tourDate->pickupSchedules->random();

                if ($isPastDate) {
                    $status = fake()->randomElement(['completed', 'completed', 'completed', 'cancelled']);
                } else {
                    $status = fake()->randomElement(['confirmed', 'confirmed', 'confirmed', 'pending', 'cancelled']);
                }

                $prefix = fake()->randomElement($phonePrefixes);
                $phone = $prefix . '-' . fake()->numerify('###-####');

                Booking::create([
                    'user_id'            => $tourist->id,
                    'tour_date_id'       => $tourDate->id,
                    'pickup_schedule_id' => $pickupSchedule->id,
                    'number_of_guests'   => fake()->numberBetween(1, 5),
                    'phone_number'       => $phone,
                    'nationality'        => fake()->randomElement($nationalities),
                    'special_request'    => fake()->randomElement($specialRequests),
                    'booking_status'     => $status,
                    'qr_token'           => Str::random(32),
                    'created_at'         => Carbon::parse($tourDate->tour_date)->subDays(fake()->numberBetween(3, 10)),
                    'updated_at'         => now(),
                ]);
            }
        }
    }
}
<?php

use App\Models\Booking;
use App\Models\Package;
use App\Models\PickupLocation;
use App\Models\PickupSchedule;
use App\Models\Role;
use App\Models\TourDate;
use App\Models\TourOperator;
use App\Models\TourTime;
use App\Models\User;
use App\Models\WeatherForecast;
use App\Services\TourDateBookingAvailability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * @return array{tourist: User, package: Package, date: TourDate, pickup: PickupSchedule}
 */
function reasonFixture(array $overrides = []): array
{
    $operator = TourOperator::query()->create([
        'name' => 'Reason Op '.Str::random(6),
        'slug' => 'reason-op-'.Str::uuid(),
        'is_active' => true,
        'approval_status' => TourOperator::STATUS_ACTIVE,
    ]);

    User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Admin'])->id,
        'tour_operator_id' => $operator->id,
    ]);

    $package = new Package([
        'package_name' => 'Reason Tour '.Str::random(5),
        'boat_name' => 'Reason Boat',
        'destination' => 'Basul Island',
        'description' => 'Availability reason demo',
        'price' => 1500,
        'status' => 'active',
        'under_maintenance' => $overrides['under_maintenance'] ?? false,
    ]);
    $package->tour_operator_id = $operator->id;
    $package->save();

    $date = TourDate::withoutGlobalScopes()->create([
        'package_id' => $package->id,
        'tour_date' => today()->addDays(5)->toDateString(),
        'capacity' => $overrides['capacity'] ?? 60,
        'travel_closed' => $overrides['travel_closed'] ?? false,
    ]);

    TourTime::query()->create([
        'tour_date_id' => $date->id,
        'time' => '08:00:00',
        'description' => 'Departure',
    ]);

    $location = PickupLocation::withoutGlobalScopes()->create([
        'tour_operator_id' => $operator->id,
        'name' => 'Reason Pier '.Str::random(4),
        'status' => 'active',
        'latitude' => 9.78,
        'longitude' => 125.49,
    ]);

    $pickup = PickupSchedule::withoutGlobalScopes()->create([
        'tour_date_id' => $date->id,
        'pickup_location_id' => $location->id,
        'pickup_time' => '07:00:00',
    ]);

    if (isset($overrides['advisory_level'])) {
        WeatherForecast::query()->updateOrCreate(
            ['tour_date_id' => $date->id],
            [
                'forecast_date' => $date->tour_date->toDateString(),
                'advisory_level' => $overrides['advisory_level'],
                'advisory_badge' => $overrides['advisory_level'],
                'weather_condition' => 'Test',
            ],
        );
    }

    if (($overrides['booked_guests'] ?? 0) > 0) {
        $touristForFill = User::factory()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => 'User'])->id,
        ]);

        $booking = new Booking([
            'user_id' => $touristForFill->id,
            'tour_date_id' => $date->id,
            'pickup_schedule_id' => $pickup->id,
            'number_of_guests' => $overrides['booked_guests'],
            'phone_number' => '09170000001',
            'nationality' => 'Filipino',
            'booking_status' => 'confirmed',
        ]);
        $booking->booked_unit_price = 1500;
        $booking->booked_total = 1500 * $overrides['booked_guests'];
        $booking->save();
    }

    $tourist = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'User'])->id,
    ]);

    return compact('tourist', 'package', 'date', 'pickup');
}

it('returns exact unavailable reasons on the package page', function (): void {
    $weather = reasonFixture(['advisory_level' => 'red']);
    $manual = reasonFixture(['travel_closed' => true, 'advisory_level' => 'green']);
    $full = reasonFixture(['capacity' => 2, 'booked_guests' => 2, 'advisory_level' => 'green']);
    $maintenance = reasonFixture(['under_maintenance' => true, 'advisory_level' => 'green']);

    $this->actingAs($weather['tourist'])
        ->get(route('user.packages.show', $weather['package']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('package.tour_dates.0.availability_status', TourDateBookingAvailability::STATUS_TRAVEL_CLOSED_WEATHER)
            ->where('package.tour_dates.0.availability_reason', fn ($reason) => str_contains((string) $reason, 'Unfavorable weather conditions'))
            ->where('package.tour_dates.0.bookable', false));

    $this->actingAs($manual['tourist'])
        ->get(route('user.packages.show', $manual['package']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('package.tour_dates.0.availability_status', TourDateBookingAvailability::STATUS_TRAVEL_CLOSED_MANUAL)
            ->where('package.tour_dates.0.availability_reason', 'This tour date has been closed by the tour operator.'));

    $this->actingAs($full['tourist'])
        ->get(route('user.packages.show', $full['package']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('package.tour_dates.0.availability_status', TourDateBookingAvailability::STATUS_FULLY_BOOKED)
            ->where('package.tour_dates.0.availability_reason', 'All guest slots have already been reserved.'));

    $this->actingAs($maintenance['tourist'])
        ->get(route('user.packages.show', $maintenance['package']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('package.tour_dates.0.availability_status', TourDateBookingAvailability::STATUS_UNDER_MAINTENANCE)
            ->where('package.tour_dates.0.availability_reason', 'The assigned boat is currently under maintenance.'));
});

it('blocks booking with the matching unavailable reason', function (): void {
    $fixture = reasonFixture(['advisory_level' => 'orange']);

    $this->actingAs($fixture['tourist'])
        ->withSession(['_token' => 'test-token'])
        ->from(route('user.packages.show', $fixture['package']))
        ->post(route('user.bookings.store'), [
            '_token' => 'test-token',
            'tour_date_id' => $fixture['date']->id,
            'pickup_schedule_id' => $fixture['pickup']->id,
            'pickup_latitude' => 9.78,
            'pickup_longitude' => 125.49,
            'number_of_guests' => 1,
            'phone_number' => '09175550999',
            'nationality' => 'Filipino',
        ])
        ->assertSessionHasErrors([
            'tour_date_id' => 'Travel Closed: Unfavorable weather conditions. Booking is temporarily unavailable for this date. Please choose another available date.',
        ]);

    expect(Booking::query()->where('phone_number', '09175550999')->exists())->toBeFalse();
});

it('shows forecast unavailable notice without marking weather as safe or unsafe', function (): void {
    $fixture = reasonFixture();

    $this->actingAs($fixture['tourist'])
        ->get(route('user.packages.show', $fixture['package']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('package.tour_dates.0.bookable', true)
            ->where('package.tour_dates.0.availability_status', TourDateBookingAvailability::STATUS_AVAILABLE)
            ->where('package.tour_dates.0.forecast_notice', fn ($notice) => str_contains((string) $notice, 'not yet available')));
});

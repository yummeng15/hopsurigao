<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PickupSchedule;
use App\Models\Role;
use App\Models\User;
use App\Models\WeatherForecast;
use Illuminate\Foundation\Testing\DatabaseTransactions;

function keepDateOpen(PickupSchedule $schedule): void
{
    $schedule->tourDate->update(['travel_closed' => false]);

    WeatherForecast::query()->updateOrCreate(
        ['tour_date_id' => $schedule->tour_date_id],
        [
            'forecast_date' => $schedule->tourDate->tour_date->toDateString(),
            'advisory_level' => 'green',
            'advisory_badge' => 'Suitable',
            'weather_condition' => 'Calm',
        ],
    );
}

uses(DatabaseTransactions::class);

test('staff can book a walk-in guest who pays cash at the counter', function () {
    $operatorRole = Role::query()->where('name', 'Admin')->first();
    $operator = User::factory()->create([
        'role_id' => $operatorRole->id,
    ]);

    $schedule = PickupSchedule::query()
        ->whereHas('tourDate', function ($query) {
            $query->whereDate('tour_date', '>=', now()->toDateString())
                ->whereHas('package', fn ($package) => $package->where('status', 'active'));
        })
        ->with('tourDate.package')
        ->get()
        ->first(function (PickupSchedule $schedule) {
            $booked = Booking::query()
                ->where('tour_date_id', $schedule->tour_date_id)
                ->whereIn('booking_status', ['pending', 'confirmed'])
                ->sum('number_of_guests');

            return ($schedule->tourDate->capacity - $booked) >= 2;
        });

    expect($schedule)->not->toBeNull();

    keepDateOpen($schedule);

    $this->actingAs($operator)
        ->get(route('admin.bookings.create'))
        ->assertOk();

    $this->actingAs($operator)
        ->withSession(['_token' => 'test-token'])
        ->post(route('admin.bookings.store'), [
            '_token' => 'test-token',
            'customer_name' => 'Onsite Guest',
            'phone_number' => '09175550199',
            'nationality' => 'Filipino',
            'tour_date_id' => $schedule->tour_date_id,
            'pickup_schedule_id' => $schedule->id,
            'number_of_guests' => 2,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $booking = Booking::query()->where('phone_number', '09175550199')->first();

    expect($booking)->not->toBeNull()
        ->and($booking->booking_status)->toBe('confirmed')
        ->and($booking->number_of_guests)->toBe(2)
        ->and($booking->user->name)->toBe('Onsite Guest')
        ->and($booking->user->email)->toContain('@hopsurigao.local');

    $payment = Payment::query()->where('booking_id', $booking->id)->first();
    $expected = round((float) $schedule->tourDate->package->price * 2, 2);

    expect($payment)->not->toBeNull()
        ->and($payment->payment_status)->toBe('paid')
        ->and($payment->payment_method)->toBe('Cash')
        ->and((float) $payment->amount)->toBe($expected);
});

test('an on-site booking cannot take more seats than the tour date has left', function () {
    $adminRole = Role::query()->where('name', 'Superadmin')->first();
    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);

    $schedule = PickupSchedule::query()
        ->whereHas('tourDate', fn ($query) => $query->whereDate('tour_date', '>=', now()->toDateString()))
        ->first();

    expect($schedule)->not->toBeNull();

    keepDateOpen($schedule);

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->from(route('admin.bookings.create'))
        ->post(route('admin.bookings.store'), [
            '_token' => 'test-token',
            'customer_name' => 'Too Many Guests',
            'phone_number' => '09175550200',
            'nationality' => 'Filipino',
            'tour_date_id' => $schedule->tour_date_id,
            'pickup_schedule_id' => $schedule->id,
            'number_of_guests' => 500,
        ])
        ->assertRedirect(route('admin.bookings.create'))
        ->assertSessionHasErrors('number_of_guests');

    expect(Booking::query()->where('phone_number', '09175550200')->exists())->toBeFalse();
});

test('a tourist cannot open the on-site booking form', function () {
    $touristRole = Role::query()->where('name', 'User')->first();
    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $this->actingAs($tourist)
        ->get(route('admin.bookings.create'))
        ->assertForbidden();
});

test('staff can load on-site tour dates for one package without loading every package date', function () {
    $operatorRole = Role::query()->where('name', 'Admin')->first();
    $operator = User::factory()->create([
        'role_id' => $operatorRole->id,
    ]);

    $schedule = PickupSchedule::query()
        ->whereHas('tourDate', fn ($query) => $query->whereDate('tour_date', '>=', now()->toDateString()))
        ->with('tourDate.package')
        ->first();

    expect($schedule)->not->toBeNull();

    keepDateOpen($schedule);

    $this->actingAs($operator)
        ->get(route('admin.bookings.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/bookings/Create')
            ->has('packages')
            ->where('packages.0.tour_dates', []));

    $this->actingAs($operator)
        ->getJson(route('admin.bookings.onsite-dates', $schedule->tourDate->package_id))
        ->assertOk()
        ->assertJsonStructure(['tour_dates' => [['id', 'tour_date', 'remaining_capacity', 'pickup_schedules']]]);
});

test('the bookings list shows the on-site booking button', function () {
    $page = file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/admin/bookings/Index.vue');

    expect($page)->toContain('On-site booking')
        ->and($page)->toContain('create().url');

    expect(file_get_contents(dirname(__DIR__, 3).'/resources/js/navigation/operator.ts'))->toContain('On-site booking');
    expect(file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/user/bookings/Payment.vue'))->not->toContain('Cash On-Site');
});

test('the on-site booking save button stays above the mobile bottom tabs', function () {
    $page = file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/admin/bookings/Create.vue');

    expect($page)
        ->toContain('bottom: calc(4.75rem + env(safe-area-inset-bottom))')
        ->toContain('Save on-site booking · ₱');
});


<?php

use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
});

function stageSevenOperator(string $name, bool $active = true): TourOperator
{
    return TourOperator::create([
        'name' => $name,
        'slug' => 'stage-seven-'.Str::uuid(),
        'is_active' => $active,
    ]);
}

function stageSevenAccount(string $role, ?TourOperator $operator = null): User
{
    $record = Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create([
        'role_id' => $record->id,
    ]);

    if ($operator) {
        $user->forceFill([
            'tour_operator_id' => $operator->id,
        ])->save();
    }

    return $user->refresh();
}

/**
 * Create an operator-owned package and a dated booking
 * without invoking unrelated services.
 */
function stageSevenBooking(
    TourOperator $operator,
    string $date,
    string $status = 'confirmed',
    int $guests = 2
): int {
    $packageId = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Booking Test Tour',
        'boat_name' => 'Test Boat',
        'destination' => 'Daku Island',
        'price' => '1500.00',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tourDateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => $date,
        'capacity' => 20,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupLocationId = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator->id,
        'name' => 'Stage Seven Pickup '.Str::random(8),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupScheduleId = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $tourDateId,
        'pickup_location_id' => $pickupLocationId,
        'pickup_time' => '07:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tourist = stageSevenAccount('User');

    return DB::table('bookings')->insertGetId([
        'user_id' => $tourist->id,
        'tour_date_id' => $tourDateId,
        'pickup_schedule_id' => $pickupScheduleId,
        'number_of_guests' => $guests,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => $status,
        'qr_token' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('operator sees only their own bookings in the index', function () {
    $operatorA = stageSevenOperator('A');
    $operatorB = stageSevenOperator('B');

    $date = today()->addDays(5)->toDateString();

    $ownId = stageSevenBooking($operatorA, $date);
    stageSevenBooking($operatorB, $date);

    $this->actingAs(stageSevenAccount('Admin', $operatorA), 'web')
        ->get(route('operator.bookings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/Bookings')
            ->where('bookings.total', 1)
            ->where('bookings.data.0.id', $ownId)
            ->missing('bookings.data.1'));
});

test('operator can view their booking but gets 404 for a foreign booking', function () {
    $operatorA = stageSevenOperator('A');
    $operatorB = stageSevenOperator('B');

    $date = today()->addDays(5)->toDateString();

    $ownId = stageSevenBooking($operatorA, $date);
    $foreignId = stageSevenBooking($operatorB, $date);

    $this->actingAs(stageSevenAccount('Admin', $operatorA), 'web')
        ->get(route('operator.bookings.show', $ownId))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/BookingDetails')
            ->where('booking.id', $ownId));

    $this->get(route('operator.bookings.show', $foreignId))
        ->assertNotFound();
});

test('calendar totals exclude other operators and cancelled bookings', function () {
    $operatorA = stageSevenOperator('A');
    $operatorB = stageSevenOperator('B');

    $date = today()->addDays(5)->toDateString();

    stageSevenBooking($operatorA, $date, 'confirmed', 3);
    stageSevenBooking($operatorA, $date, 'cancelled', 8);
    stageSevenBooking($operatorB, $date, 'confirmed', 7);

    $this->actingAs(stageSevenAccount('Admin', $operatorA), 'web')
        ->get(route('operator.bookings.calendar', [
            'month' => substr($date, 0, 7),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/BookingCalendar')
            ->has('days', 1)
            ->where('days.0.date', $date)
            ->where('days.0.bookings', 1)
            ->where('days.0.guests', 3));
});

test('operator filters bookings by status and tour date', function () {
    $operator = stageSevenOperator('A');

    $dayA = today()->addDays(5)->toDateString();
    $dayB = today()->addDays(6)->toDateString();

    $targetId = stageSevenBooking($operator, $dayA, 'pending');
    stageSevenBooking($operator, $dayB, 'confirmed');

    $this->actingAs(stageSevenAccount('Admin', $operator), 'web')
        ->get(route('operator.bookings.index', [
            'status' => 'pending',
            'date' => $dayA,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('bookings.total', 1)
            ->where('bookings.data.0.id', $targetId));
});

test('super admin and tourist cannot use operator booking pages', function () {
    foreach (['Superadmin', 'User'] as $role) {
        $this->actingAs(stageSevenAccount($role), 'web')
            ->get(route('operator.bookings.index'))
            ->assertForbidden();

        $this->get(route('operator.bookings.calendar'))
            ->assertForbidden();
    }
});

test('unassigned and inactive admins cannot use operator booking pages', function () {
    $accounts = [
        stageSevenAccount('Admin'),
        stageSevenAccount(
            'Admin',
            stageSevenOperator('Inactive', false)
        ),
    ];

    foreach ($accounts as $admin) {
        $this->actingAs($admin, 'web')
            ->get(route('operator.bookings.index'))
            ->assertForbidden();
    }
});

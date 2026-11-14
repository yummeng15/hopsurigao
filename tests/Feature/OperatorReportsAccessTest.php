<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    config()->set('inertia.ssr.enabled', false);
    $this->withoutVite();
});

/**
 * @return array{
 *     operator_a: TourOperator,
 *     operator_b: TourOperator,
 *     admin_a: User,
 *     admin_b: User,
 *     superadmin: User,
 *     package_a: int,
 *     package_b: int
 * }
 */
function operatorReportsFixture(): array
{
    $operatorA = TourOperator::query()->create([
        'name' => 'Alpha Ops '.Str::random(4),
        'slug' => 'alpha-ops-'.Str::uuid(),
        'is_active' => true,
        'approval_status' => TourOperator::STATUS_ACTIVE,
    ]);

    $operatorB = TourOperator::query()->create([
        'name' => 'Beta Ops '.Str::random(4),
        'slug' => 'beta-ops-'.Str::uuid(),
        'is_active' => true,
        'approval_status' => TourOperator::STATUS_ACTIVE,
    ]);

    $adminA = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Admin'])->id,
        'tour_operator_id' => $operatorA->id,
        'email_verified_at' => now(),
    ]);

    $adminB = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Admin'])->id,
        'tour_operator_id' => $operatorB->id,
        'email_verified_at' => now(),
    ]);

    $superadmin = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Superadmin'])->id,
        'email_verified_at' => now(),
    ]);

    $packageA = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operatorA->id,
        'package_name' => 'Alpha Scoped Hop',
        'boat_name' => 'Alpha Boat',
        'destination' => 'Siargao',
        'price' => 2000,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $packageB = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operatorB->id,
        'package_name' => 'Beta Scoped Cave',
        'boat_name' => 'Beta Boat',
        'destination' => 'Bucas Grande',
        'price' => 2500,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dayOffset = 3;

    $makeBooking = function (
        int $packageId,
        int $operatorId,
        string $status,
        ?array $payment = null,
    ) use (&$dayOffset): Booking {
        $tourDateId = DB::table('tour_dates')->insertGetId([
            'package_id' => $packageId,
            'tour_date' => today()->addDays($dayOffset++)->toDateString(),
            'capacity' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pickupId = DB::table('pickup_locations')->insertGetId([
            'tour_operator_id' => $operatorId,
            'name' => 'Ops Pier '.Str::random(5),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scheduleId = DB::table('pickup_schedules')->insertGetId([
            'tour_date_id' => $tourDateId,
            'pickup_location_id' => $pickupId,
            'pickup_time' => '07:30:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tourist = User::factory()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => 'User'])->id,
        ]);

        $booking = Booking::query()->create([
            'user_id' => $tourist->id,
            'tour_date_id' => $tourDateId,
            'pickup_schedule_id' => $scheduleId,
            'number_of_guests' => 2,
            'phone_number' => '09112223333',
            'nationality' => 'Filipino',
            'booking_status' => $status,
            'booked_unit_price' => 2000,
            'booked_total' => 4000,
            'price_snapshot_source' => 'live',
            'qr_token' => (string) Str::uuid(),
        ]);

        if ($payment !== null) {
            Payment::query()->create([
                'booking_id' => $booking->id,
                'amount' => $payment['amount'],
                'payment_method' => $payment['method'],
                'gateway' => $payment['gateway'] ?? null,
                'transaction_reference' => 'ops-'.Str::random(8),
                'payment_status' => $payment['status'],
                'paid_at' => now(),
            ]);
        }

        return $booking;
    };

    $makeBooking($packageA, $operatorA->id, 'pending');
    $makeBooking($packageA, $operatorA->id, 'confirmed', [
        'amount' => 4000,
        'method' => 'gcash',
        'gateway' => 'paymongo',
        'status' => 'paid',
    ]);
    $makeBooking($packageA, $operatorA->id, 'completed', [
        'amount' => 4000,
        'method' => 'cash',
        'status' => 'paid',
    ]);
    $makeBooking($packageA, $operatorA->id, 'cancelled');

    $makeBooking($packageB, $operatorB->id, 'confirmed', [
        'amount' => 5000,
        'method' => 'gcash',
        'gateway' => 'paymongo',
        'status' => 'paid',
    ]);

    return [
        'operator_a' => $operatorA,
        'operator_b' => $operatorB,
        'admin_a' => $adminA->refresh(),
        'admin_b' => $adminB->refresh(),
        'superadmin' => $superadmin,
        'package_a' => $packageA,
        'package_b' => $packageB,
    ];
}

test('admin operator reports index exposes only the four authorized report types', function () {
    $fixture = operatorReportsFixture();

    $this->actingAs($fixture['admin_a'])
        ->get(route('operator.operations.reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/reports/Index')
            ->has('reports', 4)
            ->where('reports.0.id', 'booking')
            ->where('reports.1.id', 'package-performance')
            ->where('reports.2.id', 'operator-performance')
            ->where('reports.3.id', 'payment')
        );
});

test('admin operator booking preview is limited to own operator bookings and status totals', function () {
    $fixture = operatorReportsFixture();

    $response = $this->actingAs($fixture['admin_a'])
        ->getJson(route('operator.operations.reports.preview', [
            'type' => 'booking',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addMonth()->toDateString(),
        ]));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.summary.total_bookings', 4)
        ->assertJsonPath('data.summary.pending_bookings', 1)
        ->assertJsonPath('data.summary.confirmed_bookings', 1)
        ->assertJsonPath('data.summary.completed_bookings', 1)
        ->assertJsonPath('data.summary.cancelled_bookings', 1)
        ->assertJsonCount(4, 'data.records');

    $packageNames = collect($response->json('data.records'))->pluck('package_name')->unique()->values();
    expect($packageNames->all())->toBe(['Alpha Scoped Hop']);
});

test('admin operator cannot preview another operator package through filters', function () {
    $fixture = operatorReportsFixture();

    $this->actingAs($fixture['admin_a'])
        ->getJson(route('operator.operations.reports.preview', [
            'type' => 'package-performance',
            'package_id' => $fixture['package_b'],
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addMonth()->toDateString(),
        ]))
        ->assertForbidden();
});

test('admin operator package and operator performance previews stay scoped', function () {
    $fixture = operatorReportsFixture();

    $package = $this->actingAs($fixture['admin_a'])
        ->getJson(route('operator.operations.reports.preview', [
            'type' => 'package-performance',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ]));

    $package->assertOk()
        ->assertJsonPath('data.summary.overall_total_bookings', 4)
        ->assertJsonPath('data.summary.overall_pending_bookings', 1)
        ->assertJsonPath('data.summary.overall_completed_bookings', 1)
        ->assertJsonCount(1, 'data.records')
        ->assertJsonPath('data.records.0.package_name', 'Alpha Scoped Hop');

    $operator = $this->actingAs($fixture['admin_a'])
        ->getJson(route('operator.operations.reports.preview', [
            'type' => 'operator-performance',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ]));

    $operator->assertOk()
        ->assertJsonPath('data.summary.total_operators', 1)
        ->assertJsonPath('data.summary.overall_total_bookings', 4)
        ->assertJsonPath('data.records.0.operator_name', $fixture['operator_a']->name)
        ->assertJsonMissingPath('data.records.1');
});

test('admin operator payment preview and csv export exclude other operators', function () {
    $fixture = operatorReportsFixture();

    $preview = $this->actingAs($fixture['admin_a'])
        ->getJson(route('operator.operations.reports.preview', [
            'type' => 'payment',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ]));

    $preview->assertOk()
        ->assertJsonPath('data.summary.total_payments_count', 2)
        ->assertJsonPath('data.summary.total_revenue', 8000);

    $csv = $this->actingAs($fixture['admin_a'])
        ->get(route('operator.operations.reports.export', [
            'type' => 'payment',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ]));

    $csv->assertOk();
    $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($csv->streamedContent())->toContain('Alpha Scoped Hop')
        ->and($csv->streamedContent())->not->toContain('Beta Scoped Cave');
});

test('admin operator can download scoped booking pdf', function () {
    $fixture = operatorReportsFixture();

    $this->actingAs($fixture['admin_a'])
        ->get(route('operator.operations.reports.download', [
            'type' => 'booking',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addMonth()->toDateString(),
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('admin operator cannot request unsupported platform-only report types', function () {
    $fixture = operatorReportsFixture();

    $this->actingAs($fixture['admin_a'])
        ->getJson(route('operator.operations.reports.preview', [
            'type' => 'weather-advisory',
        ]))
        ->assertStatus(422);
});

test('superadmin platform booking report still consolidates all operators', function () {
    $fixture = operatorReportsFixture();

    $this->actingAs($fixture['superadmin'])
        ->getJson('/admin/reports/preview?'.http_build_query([
            'type' => 'booking',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addMonth()->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonPath('data.summary.total_bookings', 5);
});

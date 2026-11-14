<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use App\Services\Reports\OperatorPerformanceReportService;
use App\Services\Reports\PackagePerformanceReportService;
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
 *     superadmin: User,
 *     package_a: int,
 *     package_b: int
 * }
 */
function reportFixture(): array
{
    $operatorA = TourOperator::query()->create([
        'name' => 'Alpha Tours '.Str::random(4),
        'slug' => 'alpha-'.Str::uuid(),
        'is_active' => true,
        'approval_status' => TourOperator::STATUS_ACTIVE,
    ]);

    $operatorB = TourOperator::query()->create([
        'name' => 'Beta Tours '.Str::random(4),
        'slug' => 'beta-'.Str::uuid(),
        'is_active' => true,
        'approval_status' => TourOperator::STATUS_ACTIVE,
    ]);

    $adminA = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Admin'])->id,
        'tour_operator_id' => $operatorA->id,
        'email_verified_at' => now(),
    ]);

    $superadmin = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Superadmin'])->id,
        'email_verified_at' => now(),
    ]);

    $packageA = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operatorA->id,
        'package_name' => 'Alpha Island Hop',
        'boat_name' => 'Alpha Boat',
        'destination' => 'Siargao',
        'price' => 2000,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $packageB = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operatorB->id,
        'package_name' => 'Beta Cave Tour',
        'boat_name' => 'Beta Boat',
        'destination' => 'Bucas Grande',
        'price' => 2500,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dayOffset = 6;

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
            'name' => 'Report Pier '.Str::random(5),
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
            'phone_number' => '09111111111',
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
                'transaction_reference' => 'rep-'.Str::random(8),
                'payment_status' => $payment['status'],
                'paid_at' => now(),
            ]);
        }

        return $booking;
    };

    // Operator A: pending, confirmed+gcash (with failed retry), completed+cash, cancelled
    $makeBooking($packageA, $operatorA->id, 'pending');

    $confirmedGcash = $makeBooking($packageA, $operatorA->id, 'confirmed', [
        'amount' => 4000,
        'method' => 'gcash',
        'gateway' => 'paymongo',
        'status' => 'paid',
    ]);
    addPaymentAttempt($confirmedGcash, 4000, 'failed');

    $makeBooking($packageA, $operatorA->id, 'completed', [
        'amount' => 4000,
        'method' => 'cash',
        'gateway' => null,
        'status' => 'paid',
    ]);

    $makeBooking($packageA, $operatorA->id, 'cancelled');

    // Operator B: 1 confirmed+gcash
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
        'superadmin' => $superadmin,
        'package_a' => $packageA,
        'package_b' => $packageB,
    ];
}

function addPaymentAttempt(Booking $booking, float $amount, string $status): void
{
    Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => $amount,
        'payment_method' => 'gcash',
        'gateway' => 'paymongo',
        'transaction_reference' => null,
        'payment_status' => $status,
        'paid_at' => null,
    ]);
}

test('package performance report groups by package and separates status and payment totals', function () {
    $fixture = reportFixture();

    $data = app(PackagePerformanceReportService::class)->getData([
        'date_from' => now()->subDay()->toDateString(),
        'date_to' => now()->addDay()->toDateString(),
    ]);

    $alpha = collect($data['records'])->firstWhere('id', $fixture['package_a']);
    $beta = collect($data['records'])->firstWhere('id', $fixture['package_b']);

    expect($alpha)->not->toBeNull()
        ->and($alpha['operator_name'])->toBe($fixture['operator_a']->name)
        ->and($alpha['total_bookings'])->toBe(4)
        ->and($alpha['pending_bookings'])->toBe(1)
        ->and($alpha['confirmed_bookings'])->toBe(1)
        ->and($alpha['completed_bookings'])->toBe(1)
        ->and($alpha['cancelled_bookings'])->toBe(1)
        ->and($alpha['pending_bookings'] + $alpha['confirmed_bookings'] + $alpha['completed_bookings'] + $alpha['cancelled_bookings'])->toBe($alpha['total_bookings'])
        ->and($alpha['gcash_revenue'])->toBe(4000.0)
        ->and($alpha['cash_revenue'])->toBe(4000.0)
        ->and($alpha['revenue'])->toBe(8000.0)
        ->and($beta['total_bookings'])->toBe(1)
        ->and($beta['pending_bookings'])->toBe(0)
        ->and($beta['confirmed_bookings'])->toBe(1)
        ->and($beta['completed_bookings'])->toBe(0)
        ->and($beta['gcash_revenue'])->toBe(5000.0)
        ->and($data['summary']['overall_pending_bookings'])->toBe(1)
        ->and($data['summary']['overall_confirmed_bookings'])->toBe(2)
        ->and($data['summary']['overall_completed_bookings'])->toBe(1)
        ->and($data['summary']['overall_cancelled_bookings'])->toBe(1);
});

test('operator performance report consolidates across businesses with status splits', function () {
    $fixture = reportFixture();

    $data = app(OperatorPerformanceReportService::class)->getData([
        'date_from' => now()->subDay()->toDateString(),
        'date_to' => now()->addDay()->toDateString(),
    ]);

    $alpha = collect($data['records'])->firstWhere('operator_id', $fixture['operator_a']->id);
    $beta = collect($data['records'])->firstWhere('operator_id', $fixture['operator_b']->id);

    expect($alpha['total_bookings'])->toBe(4)
        ->and($alpha['pending_bookings'])->toBe(1)
        ->and($alpha['confirmed_bookings'])->toBe(1)
        ->and($alpha['completed_bookings'])->toBe(1)
        ->and($alpha['cancelled_bookings'])->toBe(1)
        ->and($alpha['pending_bookings'] + $alpha['confirmed_bookings'] + $alpha['completed_bookings'] + $alpha['cancelled_bookings'])->toBe($alpha['total_bookings'])
        ->and($alpha['gcash_revenue'])->toBe(4000.0)
        ->and($alpha['cash_revenue'])->toBe(4000.0)
        ->and($beta['total_bookings'])->toBe(1)
        ->and($beta['confirmed_bookings'])->toBe(1)
        ->and($beta['completed_bookings'])->toBe(0)
        ->and($beta['revenue'])->toBe(5000.0)
        ->and($data['summary']['overall_pending_bookings'])->toBe(1)
        ->and($data['summary']['overall_confirmed_bookings'])->toBe(2)
        ->and($data['summary']['overall_completed_bookings'])->toBe(1)
        ->and($data['summary']['overall_cancelled_bookings'])->toBe(1);
});

test('admin operator reports page renders shared analytics workspace scoped to own business', function () {
    $fixture = reportFixture();

    $this->actingAs($fixture['admin_a'])
        ->get(route('operator.operations.reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/reports/Index')
            ->has('reports', 4)
            ->where('operatorName', $fixture['operator_a']->name)
            ->where('endpoints.preview', route('operator.operations.reports.preview'))
            ->has('filterOptions.packages', 1)
            ->where('filterOptions.packages.0.package_name', 'Alpha Island Hop')
        );
});

test('superadmin can preview operator performance report with pending and completed columns', function () {
    $fixture = reportFixture();

    $this->actingAs($fixture['superadmin'])
        ->getJson('/admin/reports/preview?'.http_build_query([
            'type' => 'operator-performance',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.summary.overall_pending_bookings', 1)
        ->assertJsonPath('data.summary.overall_confirmed_bookings', 2)
        ->assertJsonPath('data.summary.overall_completed_bookings', 1)
        ->assertJsonPath('data.summary.overall_cancelled_bookings', 1)
        ->assertJson(fn ($json) => $json
            ->has('data.records')
            ->has('data.summary.overall_total_bookings')
            ->etc()
        );

    $this->actingAs($fixture['superadmin'])
        ->getJson('/admin/reports/preview?'.http_build_query([
            'type' => 'package-performance',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.summary.overall_pending_bookings', 1)
        ->assertJsonPath('data.summary.overall_completed_bookings', 1);
});

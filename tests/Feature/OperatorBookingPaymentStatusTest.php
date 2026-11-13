<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use App\Services\BookingPaymentStatus;
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
 *     operator: TourOperator,
 *     admin: User,
 *     booking: Booking,
 *     package_id: int
 * }
 */
function paymentStatusFixture(string $bookingStatus = 'confirmed'): array
{
    $operator = TourOperator::query()->create([
        'name' => 'Payment Status Op '.Str::random(6),
        'slug' => 'pay-status-'.Str::uuid(),
        'is_active' => true,
        'approval_status' => TourOperator::STATUS_ACTIVE,
    ]);

    $admin = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Admin'])->id,
        'tour_operator_id' => $operator->id,
        'email_verified_at' => now(),
    ]);

    $packageId = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Sohoton Lagoon Tour',
        'boat_name' => 'Boat One',
        'destination' => 'Bucas Grande',
        'price' => 1500,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tourDateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => today()->addDays(10)->toDateString(),
        'capacity' => 20,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupId = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator->id,
        'name' => 'Pier '.Str::random(5),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $scheduleId = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $tourDateId,
        'pickup_location_id' => $pickupId,
        'pickup_time' => '07:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tourist = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'User'])->id,
        'name' => 'Test Tourist',
    ]);

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $tourDateId,
        'pickup_schedule_id' => $scheduleId,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => $bookingStatus,
        'booked_unit_price' => 1500,
        'booked_total' => 3000,
        'price_snapshot_source' => 'live',
        'qr_token' => (string) Str::uuid(),
    ]);

    return [
        'operator' => $operator,
        'admin' => $admin->refresh(),
        'booking' => $booking,
        'package_id' => $packageId,
    ];
}

function addPayment(
    Booking $booking,
    float $amount,
    string $status,
    string $method = 'gcash',
    ?string $gateway = 'paymongo',
): Payment {
    return Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => $amount,
        'payment_method' => $method,
        'gateway' => $gateway,
        'gateway_checkout_id' => $gateway === 'paymongo' ? 'cs_test_'.Str::random(10) : null,
        'transaction_reference' => $status === 'pending' ? null : 'ref-'.Str::random(10),
        'payment_status' => $status,
        'paid_at' => in_array($status, ['paid', 'partial'], true) ? now() : null,
    ]);
}

test('confirmed booking without payment stays unpaid', function () {
    $fixture = paymentStatusFixture('confirmed');
    $summary = app(BookingPaymentStatus::class)->summarize($fixture['booking']);

    expect($summary['status'])->toBe('unpaid')
        ->and($summary['label'])->toBe('UNPAID')
        ->and($summary['amount_paid'])->toBe(0.0);
});

test('verified paymongo gcash payment marks booking paid', function () {
    $fixture = paymentStatusFixture('pending');
    addPayment($fixture['booking'], 3000, 'paid', 'gcash', 'paymongo');

    $summary = app(BookingPaymentStatus::class)->summarize($fixture['booking']->fresh(['payments']));

    expect($summary['status'])->toBe('paid')
        ->and($summary['label'])->toBe('PAID')
        ->and($summary['amount_paid'])->toBe(3000.0)
        ->and($summary['payments'][0]['channel_label'])->toBe('GCash (PayMongo Test Mode)');
});

test('failed payment without success stays failed', function () {
    $fixture = paymentStatusFixture('pending');
    addPayment($fixture['booking'], 3000, 'failed', 'gcash', 'paymongo');

    $summary = app(BookingPaymentStatus::class)->summarize($fixture['booking']->fresh(['payments']));

    expect($summary['status'])->toBe('failed')
        ->and($summary['label'])->toBe('FAILED');
});

test('cash walk-in payment marks booking paid with cash channel', function () {
    $fixture = paymentStatusFixture('confirmed');
    addPayment($fixture['booking'], 3000, 'paid', 'cash', null);

    $summary = app(BookingPaymentStatus::class)->summarize($fixture['booking']->fresh(['payments']));

    expect($summary['status'])->toBe('paid')
        ->and($summary['payments'][0]['channel_label'])->toBe('Walk-in Cash');
});

test('multiple payment attempts keep booking once and prefer successful amount', function () {
    $fixture = paymentStatusFixture('pending');
    addPayment($fixture['booking'], 3000, 'failed', 'gcash', 'paymongo');
    addPayment($fixture['booking'], 3000, 'pending', 'gcash', 'paymongo');
    addPayment($fixture['booking'], 3000, 'paid', 'gcash', 'paymongo');

    $summary = app(BookingPaymentStatus::class)->summarize($fixture['booking']->fresh(['payments']));

    expect($summary['status'])->toBe('paid')
        ->and($summary['amount_paid'])->toBe(3000.0)
        ->and(count($summary['payments']))->toBe(3);
});

test('operator booking list shows payment status badges from payment rows', function () {
    $fixture = paymentStatusFixture('confirmed');
    addPayment($fixture['booking'], 3000, 'paid', 'gcash', 'paymongo');

    $other = TourOperator::query()->create([
        'name' => 'Other Op '.Str::random(5),
        'slug' => 'other-'.Str::uuid(),
        'is_active' => true,
        'approval_status' => TourOperator::STATUS_ACTIVE,
    ]);

    $otherPackage = DB::table('packages')->insertGetId([
        'tour_operator_id' => $other->id,
        'package_name' => 'Other Package',
        'boat_name' => 'Boat B',
        'destination' => 'Somewhere',
        'price' => 1000,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherDate = DB::table('tour_dates')->insertGetId([
        'package_id' => $otherPackage,
        'tour_date' => today()->addDays(8)->toDateString(),
        'capacity' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherPickup = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $other->id,
        'name' => 'Other Pier '.Str::random(4),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherSchedule = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $otherDate,
        'pickup_location_id' => $otherPickup,
        'pickup_time' => '08:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Booking::query()->create([
        'user_id' => User::factory()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => 'User'])->id,
        ])->id,
        'tour_date_id' => $otherDate,
        'pickup_schedule_id' => $otherSchedule,
        'number_of_guests' => 1,
        'phone_number' => '09999999999',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
        'booked_unit_price' => 1000,
        'booked_total' => 1000,
        'price_snapshot_source' => 'live',
        'qr_token' => (string) Str::uuid(),
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('operator.bookings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/Bookings')
            ->has('bookings.data', 1)
            ->where('bookings.data.0.id', $fixture['booking']->id)
            ->where('bookings.data.0.payment_status', 'paid')
            ->where('bookings.data.0.payment_label', 'PAID')
        );
});

test('operator booking details expose payment summary fields', function () {
    $fixture = paymentStatusFixture('confirmed');
    addPayment($fixture['booking'], 3000, 'paid', 'cash', null);

    $this->actingAs($fixture['admin'])
        ->get(route('operator.bookings.show', $fixture['booking']->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/BookingDetails')
            ->where('booking.payment.status', 'paid')
            ->where('booking.payment.label', 'PAID')
            ->where('booking.payment.total_amount', 3000)
            ->where('booking.payment.amount_paid', 3000)
            ->where('booking.payment.payment_method', 'cash')
            ->has('booking.payment.transaction_reference')
            ->has('booking.payment.paid_at')
        );
});


<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

/**
 * Create a normal saved-price test booking.
 */
function auditFixture(
    string $status = 'pending'
): Booking {
    $operator = TourOperator::query()->create([
        'name' => 'Audit '.Str::random(8),
        'slug' => 'audit-'.Str::uuid(),
        'is_active' => true,
    ]);

    $package = DB::table('packages')
        ->insertGetId([
            'tour_operator_id' => $operator->id,
            'package_name' => 'Audit Island Tour',
            'boat_name' => 'Audit Boat',
            'destination' => 'Daku Island',
            'price' => 1500,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $date = DB::table('tour_dates')
        ->insertGetId([
            'package_id' => $package,
            'tour_date' => today()
                ->addDays(12)
                ->toDateString(),
            'capacity' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $location = DB::table('pickup_locations')
        ->insertGetId([
            'tour_operator_id' => $operator->id,
            'name' => 'Audit Pier '.Str::random(8),
            'latitude' => 9.86,
            'longitude' => 126.08,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $schedule = DB::table('pickup_schedules')
        ->insertGetId([
            'tour_date_id' => $date,
            'pickup_location_id' => $location,
            'pickup_time' => '07:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $role = Role::query()->firstOrCreate([
        'name' => 'User',
    ]);

    $tourist = User::factory()->create([
        'role_id' => $role->id,
    ]);

    return Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $date,
        'pickup_schedule_id' => $schedule,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => $status,
    ]);
}

/**
 * Add a test payment.
 */
function auditPayment(
    Booking $booking,
    float $amount,
    string $status = 'paid',
    ?string $gateway = null
): Payment {
    return Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => $amount,
        'payment_method' =>
            $gateway === 'paymongo'
                ? 'gcash'
                : 'cash',
        'gateway' => $gateway,
        'gateway_checkout_id' =>
            $gateway === 'paymongo'
                ? 'cs_test_'.Str::random(15)
                : null,
        'transaction_reference' =>
            $status === 'pending'
                ? null
                : 'audit-ref-'.Str::random(12),
        'payment_status' => $status,
        'paid_at' =>
            $status === 'pending'
                ? null
                : now(),
    ]);
}

/**
 * Run the audit command and return its results.
 */
function runBookingAudit(
    int $bookingId,
    bool $strict = false
): array {
    $exitCode = Artisan::call(
        'hopsurigao:audit-payments',
        [
            '--booking' => (string) $bookingId,
            '--json' => true,
            '--strict' => $strict,
        ]
    );

    $output = json_decode(
        Artisan::output(),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($exitCode)->toBe(
        $strict && $output['issue_count'] > 0
            ? 1
            : 0
    );

    return $output;
}

function auditCodes(array $result): array
{
    return array_column(
        $result['issues'],
        'code'
    );
}

/*
|--------------------------------------------------------------------------
| TEST 1 — CLEAN BOOKING
|--------------------------------------------------------------------------
*/

test('clean pending booking has no payment integrity findings', function () {
    $booking = auditFixture();

    $result = runBookingAudit(
        $booking->id,
        true
    );

    expect($result['bookings_scanned'])
        ->toBe(1)

        ->and($result['issue_count'])
        ->toBe(0)

        ->and($result['issues'])
        ->toBe([]);
});

/*
|--------------------------------------------------------------------------
| TEST 2 — PENDING WITH RECEIVED PAYMENT
|--------------------------------------------------------------------------
*/

test('flags payment recorded while booking is pending without changing records', function () {
    $booking = auditFixture();

    $payment = auditPayment(
        $booking,
        1500
    );

    $result = runBookingAudit(
        $booking->id,
        true
    );

    expect(auditCodes($result))
        ->toContain('pending_with_payment')

        ->and($booking->fresh()->booking_status)
        ->toBe('pending')

        ->and($payment->fresh()->payment_status)
        ->toBe('paid');
});

/*
|--------------------------------------------------------------------------
| TEST 3 — MISSING PAYMENT AND OVERPAYMENT
|--------------------------------------------------------------------------
*/

test('flags overpayments and confirmed bookings with no payments', function () {
    $booking = auditFixture(
        'confirmed'
    );

    expect(
        auditCodes(
            runBookingAudit(
                $booking->id,
                true
            )
        )
    )->toContain(
        'confirmed_without_payment'
    );

    auditPayment(
        $booking,
        3001
    );

    $result = runBookingAudit(
        $booking->id,
        true
    );

    expect(auditCodes($result))
        ->toContain('overpaid')

        ->not->toContain(
            'confirmed_without_payment'
        );
});

/*
|--------------------------------------------------------------------------
| TEST 4 — CANCELLED WITH RECEIVED PAYMENT
|--------------------------------------------------------------------------
*/

test('flags cancelled bookings that received funds', function () {
    $booking = auditFixture(
        'cancelled'
    );

    auditPayment(
        $booking,
        1500,
        'partial',
        'paymongo'
    );

    expect(
        auditCodes(
            runBookingAudit(
                $booking->id,
                true
            )
        )
    )->toContain(
        'cancelled_with_payment'
    );
});

/*
|--------------------------------------------------------------------------
| TEST 5 — MULTIPLE PENDING CHECKOUTS
|--------------------------------------------------------------------------
*/

test('flags more than one pending checkout without expiring either one', function () {
    $booking = auditFixture();

    $first = auditPayment(
        $booking,
        1500,
        'pending',
        'paymongo'
    );

    $second = auditPayment(
        $booking,
        1500,
        'pending',
        'paymongo'
    );

    expect(
        auditCodes(
            runBookingAudit(
                $booking->id,
                true
            )
        )
    )
        ->toContain(
            'multiple_pending_checkouts'
        )

        ->and($first->fresh()->payment_status)
        ->toBe('pending')

        ->and($second->fresh()->payment_status)
        ->toBe('pending');
});

/*
|--------------------------------------------------------------------------
| TEST 6 — LATE PAYMENT RECONCILIATION
|--------------------------------------------------------------------------
*/

test('flags outstanding reconciliation cases for manual review', function () {
    $booking = auditFixture(
        'cancelled'
    );

    $payment = auditPayment(
        $booking,
        1500,
        'partial',
        'paymongo'
    );

    DB::table(
        'payment_reconciliation_cases'
    )->insert([
        'booking_id' => $booking->id,
        'payment_id' => $payment->id,
        'gateway_checkout_id' =>
            $payment->gateway_checkout_id,
        'gateway_payment_id' =>
            'pay_test_'.Str::random(15),
        'amount' => 1500,
        'reason' => 'paid_after_cancellation',
        'status' => 'needs_review',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(
        auditCodes(
            runBookingAudit(
                $booking->id,
                true
            )
        )
    )->toContain(
        'reconciliation_pending'
    );
});

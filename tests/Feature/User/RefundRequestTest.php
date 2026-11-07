<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    config()->set('inertia.ssr.enabled', false);
    $this->withoutVite();
});

/**
 * @return array{
 *     tourist: User,
 *     operatorAdmin: User,
 *     booking: Booking,
 *     payment: Payment,
 *     operator: TourOperator
 * }
 */
function touristRefundFixture(): array
{
    $touristRole = Role::query()->firstOrCreate(['name' => 'User']);
    $adminRole = Role::query()->firstOrCreate(['name' => 'Admin']);

    $operator = TourOperator::query()->create([
        'name' => 'Refund Tour Op',
        'slug' => 'refund-tour-op-'.Str::random(6),
        'is_active' => true,
        'approval_status' => 'active',
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
        'name' => 'Refund Tourist',
    ]);

    $operatorAdmin = User::factory()->create([
        'role_id' => $adminRole->id,
        'tour_operator_id' => $operator->id,
    ]);

    $packageId = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Refund Island Hop',
        'boat_name' => 'Boat R',
        'destination' => 'Siargao',
        'price' => 2500,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => today()->addDays(4)->toDateString(),
        'capacity' => 12,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupId = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator->id,
        'name' => 'Refund Pier '.Str::random(4),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $scheduleId = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $dateId,
        'pickup_location_id' => $pickupId,
        'pickup_time' => '07:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $dateId,
        'pickup_schedule_id' => $scheduleId,
        'number_of_guests' => 2,
        'phone_number' => '09171234567',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
    ]);

    $payment = Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => 5000,
        'payment_method' => 'gcash',
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);

    return compact('tourist', 'operatorAdmin', 'booking', 'payment', 'operator');
}

test('a tourist can request a refund for no-show via the incident form path', function () {
    $fixture = touristRefundFixture();

    $this->actingAs($fixture['tourist'])
        ->from(route('user.bookings.show', $fixture['booking']))
        ->post(route('user.bookings.cases.store', $fixture['booking']), [
            'reason' => RefundRequest::CATEGORY_OPERATOR_NO_SHOW,
            'requested_resolution' => 'refund',
            'details' => 'The boat never arrived at the pickup pier.',
        ])
        ->assertRedirect();

    $request = RefundRequest::query()->where('booking_id', $fixture['booking']->id)->first();

    expect($request)->not->toBeNull()
        ->and($request->status)->toBe(RefundRequest::STATUS_PENDING)
        ->and($request->category)->toBe(RefundRequest::CATEGORY_OPERATOR_NO_SHOW)
        ->and($request->payment_id)->toBe($fixture['payment']->id)
        ->and($fixture['payment']->fresh()->payment_status)->toBe('paid');
});

test('a tourist cannot request a refund with a non-eligible category', function () {
    $fixture = touristRefundFixture();

    $this->actingAs($fixture['tourist'])
        ->post(route('user.bookings.refunds.store', $fixture['booking']), [
            'category' => 'change_of_mind',
            'reason' => 'I just changed my mind about this trip.',
        ])
        ->assertSessionHasErrors('category');

    expect(RefundRequest::query()->count())->toBe(0);
});

test('a tourist cannot request a refund for an unpaid booking', function () {
    $fixture = touristRefundFixture();
    $fixture['payment']->update(['payment_status' => 'pending', 'paid_at' => null]);

    $this->actingAs($fixture['tourist'])
        ->post(route('user.bookings.refunds.store', $fixture['booking']), [
            'category' => RefundRequest::CATEGORY_WEATHER,
            'reason' => 'Unsafe weather cancelled the island hop.',
        ])
        ->assertSessionHasErrors('reason');

    expect(RefundRequest::query()->count())->toBe(0);
});

test('a tourist cannot submit a second open refund request for the same payment', function () {
    $fixture = touristRefundFixture();

    RefundRequest::query()->create([
        'booking_id' => $fixture['booking']->id,
        'payment_id' => $fixture['payment']->id,
        'user_id' => $fixture['tourist']->id,
        'tour_operator_id' => $fixture['operator']->id,
        'category' => RefundRequest::CATEGORY_WEATHER,
        'reason' => 'Already requested once for this payment.',
        'status' => RefundRequest::STATUS_PENDING,
    ]);

    $this->actingAs($fixture['tourist'])
        ->post(route('user.bookings.cases.store', $fixture['booking']), [
            'reason' => RefundRequest::CATEGORY_WEATHER,
            'requested_resolution' => 'refund',
            'details' => 'Trying to request a refund again for the same payment.',
        ])
        ->assertSessionHasErrors('details');

    expect(RefundRequest::query()->count())->toBe(1);
});

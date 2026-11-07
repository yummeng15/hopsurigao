<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use App\Notifications\RefundDecisionNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    config()->set('inertia.ssr.enabled', false);
    $this->withoutVite();
});

/**
 * @return array{
 *     tourist: User,
 *     operatorAdmin: User,
 *     otherAdmin: User,
 *     booking: Booking,
 *     payment: Payment,
 *     refund: RefundRequest,
 *     operator: TourOperator
 * }
 */
function operatorRefundFixture(): array
{
    $touristRole = Role::query()->firstOrCreate(['name' => 'User']);
    $adminRole = Role::query()->firstOrCreate(['name' => 'Admin']);

    $operator = TourOperator::query()->create([
        'name' => 'Ops Refund A',
        'slug' => 'ops-refund-a-'.Str::random(6),
        'is_active' => true,
        'approval_status' => 'active',
    ]);

    $otherOperator = TourOperator::query()->create([
        'name' => 'Ops Refund B',
        'slug' => 'ops-refund-b-'.Str::random(6),
        'is_active' => true,
        'approval_status' => 'active',
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
        'name' => 'Ops Refund Guest',
    ]);

    $operatorAdmin = User::factory()->create([
        'role_id' => $adminRole->id,
        'tour_operator_id' => $operator->id,
    ]);

    $otherAdmin = User::factory()->create([
        'role_id' => $adminRole->id,
        'tour_operator_id' => $otherOperator->id,
    ]);

    $packageId = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Ops Refund Tour',
        'boat_name' => 'Boat Ops',
        'destination' => 'Cloud 9',
        'price' => 3000,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => today()->addDays(5)->toDateString(),
        'capacity' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupId = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator->id,
        'name' => 'Ops Pier '.Str::random(4),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $scheduleId = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $dateId,
        'pickup_location_id' => $pickupId,
        'pickup_time' => '08:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $dateId,
        'pickup_schedule_id' => $scheduleId,
        'number_of_guests' => 1,
        'phone_number' => '09170001111',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
    ]);

    $payment = Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => 3000,
        'payment_method' => 'gcash',
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);

    $refund = RefundRequest::query()->create([
        'booking_id' => $booking->id,
        'payment_id' => $payment->id,
        'user_id' => $tourist->id,
        'tour_operator_id' => $operator->id,
        'category' => RefundRequest::CATEGORY_OPERATOR_NO_SHOW,
        'reason' => 'Boat did not arrive at the agreed pickup time.',
        'status' => RefundRequest::STATUS_PENDING,
    ]);

    return compact(
        'tourist',
        'operatorAdmin',
        'otherAdmin',
        'booking',
        'payment',
        'refund',
        'operator'
    );
}

test('an operator can view and approve own refund requests as refunded test', function () {
    Notification::fake();

    $fixture = operatorRefundFixture();

    $this->actingAs($fixture['operatorAdmin'])
        ->get(route('operator.refunds.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/Refunds')
            ->has('refunds.data', 1)
            ->where('refunds.data.0.booking_id', $fixture['booking']->id)
        );

    $this->actingAs($fixture['operatorAdmin'])
        ->from(route('operator.refunds.show', $fixture['refund']))
        ->post(route('operator.refunds.approve', $fixture['refund']))
        ->assertRedirect();

    expect($fixture['refund']->fresh()->status)->toBe(RefundRequest::STATUS_REFUNDED_TEST)
        ->and($fixture['payment']->fresh()->payment_status)->toBe('refunded')
        ->and($fixture['payment']->fresh()->notes)->toContain('Simulated refund (PayMongo Test Mode)');

    Notification::assertSentTo(
        $fixture['tourist'],
        RefundDecisionNotification::class
    );
});

test('an operator must provide a reason when rejecting a refund request', function () {
    Notification::fake();

    $fixture = operatorRefundFixture();

    $this->actingAs($fixture['operatorAdmin'])
        ->post(route('operator.refunds.reject', $fixture['refund']), [])
        ->assertSessionHasErrors('rejection_reason');

    $this->actingAs($fixture['operatorAdmin'])
        ->post(route('operator.refunds.reject', $fixture['refund']), [
            'rejection_reason' => 'Trip was completed as scheduled.',
        ])
        ->assertRedirect();

    expect($fixture['refund']->fresh()->status)->toBe(RefundRequest::STATUS_REJECTED)
        ->and($fixture['payment']->fresh()->payment_status)->toBe('paid');

    Notification::assertSentTo(
        $fixture['tourist'],
        RefundDecisionNotification::class
    );
});

test('an operator cannot manage another operators refund request', function () {
    $fixture = operatorRefundFixture();

    $this->actingAs($fixture['otherAdmin'])
        ->get(route('operator.refunds.show', $fixture['refund']))
        ->assertNotFound();

    $this->actingAs($fixture['otherAdmin'])
        ->post(route('operator.refunds.approve', $fixture['refund']))
        ->assertNotFound();

    expect($fixture['refund']->fresh()->status)->toBe(RefundRequest::STATUS_PENDING)
        ->and($fixture['payment']->fresh()->payment_status)->toBe('paid');
});

test('super admin sidebar does not list refund oversight separately from payments', function () {
    $fixture = operatorRefundFixture();
    $superadmin = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Superadmin'])->id,
    ]);

    $nav = file_get_contents(resource_path('js/navigation/admin.ts'));

    expect($nav)
        ->toContain('Payment Oversight')
        ->not->toContain('Refund Oversight')
        ->not->toContain('/admin/refunds');

    $this->actingAs($superadmin)
        ->post(route('admin.refunds.store', $fixture['payment']))
        ->assertForbidden();
});

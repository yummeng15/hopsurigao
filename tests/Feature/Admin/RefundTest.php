<?php

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

function refundRole(string $name): Role
{
    return Role::query()->firstOrCreate(['name' => $name]);
}

function refundUser(string $role): User
{
    return User::factory()->create([
        'role_id' => refundRole($role)->id,
        'name' => $role === 'User' ? 'Refund Guest' : 'Refund '.$role,
    ]);
}

/**
 * @return array{payment: int, operator: TourOperator}
 */
function refundPayment(): array
{
    $tourist = refundUser('User');

    $operator = TourOperator::query()->create([
        'name' => 'Refund Oversight',
        'slug' => 'refund-oversight-'.Str::uuid(),
        'is_active' => true,
        'approval_status' => 'active',
    ]);

    $packageId = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Refund Oversight Tour',
        'boat_name' => 'Boat A',
        'destination' => 'Daku Island',
        'price' => 2200,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => today()->addDays(3)->toDateString(),
        'capacity' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupId = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator->id,
        'name' => 'Refund Pier '.Str::random(5),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupScheduleId = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $dateId,
        'pickup_location_id' => $pickupId,
        'pickup_time' => '07:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $bookingId = DB::table('bookings')->insertGetId([
        'user_id' => $tourist->id,
        'tour_date_id' => $dateId,
        'pickup_schedule_id' => $pickupScheduleId,
        'number_of_guests' => 1,
        'phone_number' => '09176661018',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
        'qr_token' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $paymentId = DB::table('payments')->insertGetId([
        'booking_id' => $bookingId,
        'amount' => 2200,
        'payment_method' => 'gcash',
        'payment_status' => 'paid',
        'paid_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'payment' => $paymentId,
        'operator' => $operator,
    ];
}

test('the super admin can view refunds but cannot mark a payment as refunded', function () {
    $paymentId = refundPayment()['payment'];
    $administrator = refundUser('Superadmin');

    $this->actingAs($administrator)
        ->get(route('admin.refunds.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/refunds/Index'));

    $this->actingAs($administrator)
        ->get(route('admin.refunds.data'))
        ->assertOk()
        ->assertJsonFragment([
            'id' => $paymentId,
            'payment_status' => 'paid',
            'user_name' => 'Refund Guest',
        ]);

    $this->actingAs($administrator)
        ->post(route('admin.refunds.store', $paymentId))
        ->assertForbidden();

    expect(DB::table('payments')->where('id', $paymentId)->value('payment_status'))->toBe('paid');
    expect(file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/admin/payments/Index.vue'))
        ->not->toContain('Refund this payment');
    expect(file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/admin/refunds/Index.vue'))
        ->toContain('The Operator Admin records a refund')
        ->toContain('View-only monitoring')
        ->not->toContain('>Refund<')
        ->not->toContain('reviewCase');
    expect(file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/operator/Payments.vue'))
        ->not->toContain('Mark refunded')
        ->toContain('Refund Management');
});

test('a boat operator and a tourist cannot use the super admin refund action', function () {
    $fixture = refundPayment();
    $paymentId = $fixture['payment'];
    $operatorAdmin = refundUser('Admin');
    $operatorAdmin->forceFill([
        'tour_operator_id' => $fixture['operator']->id,
    ])->save();

    $this->actingAs($operatorAdmin->refresh())
        ->post(route('admin.refunds.store', $paymentId))
        ->assertForbidden();

    $this->actingAs(refundUser('User'))
        ->get(route('admin.refunds.index'))
        ->assertForbidden();

    expect(DB::table('payments')->where('id', $paymentId)->value('payment_status'))->toBe('paid');
});

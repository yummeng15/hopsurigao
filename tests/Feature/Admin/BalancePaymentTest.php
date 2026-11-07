<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PickupSchedule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('staff can record money received after a downpayment and the balance updates', function () {
    [$operator, $booking, $total] = downpaymentBooking('Admin');
    $half = round($total / 2, 2);

    $this->actingAs($operator)
        ->withSession(['_token' => 'test-token'])
        ->post(route('admin.bookings.balance', $booking), [
            '_token' => 'test-token',
            'amount' => $half / 2,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $booking->unsetRelation('payments');

    expect($booking->fresh()->remainingBalance())->toBe(round($total - $half - ($half / 2), 2))
        ->and($booking->payments()->where('payment_status', 'partial')->exists())->toBeTrue()
        ->and($booking->payments()->where('notes', 'like', 'Balance collected%')->count())->toBe(1);

    $this->actingAs($operator)
        ->withSession(['_token' => 'test-token'])
        ->post(route('admin.bookings.balance', $booking), [
            '_token' => 'test-token',
            'amount' => $booking->fresh()->remainingBalance(),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($booking->fresh()->remainingBalance())->toBe(0.0)
        ->and($booking->payments()->where('payment_status', 'partial')->exists())->toBeFalse()
        ->and($booking->payments()->where('payment_status', 'paid')->count())->toBe(3);
});

test('a balance record cannot be higher than the remaining amount and a tourist cannot record it', function () {
    [$operator, $booking] = downpaymentBooking('Superadmin');
    $tourist = User::factory()->create([
        'role_id' => Role::query()->where('name', 'User')->value('id'),
    ]);

    $this->actingAs($operator)
        ->withSession(['_token' => 'test-token'])
        ->post(route('admin.bookings.balance', $booking), [
            '_token' => 'test-token',
            'amount' => $booking->remainingBalance() + 1,
        ])
        ->assertSessionHasErrors('amount');

    expect($booking->payments()->count())->toBe(1);

    $this->actingAs($tourist)
        ->withSession(['_token' => 'test-token'])
        ->post(route('admin.bookings.balance', $booking), [
            '_token' => 'test-token',
            'amount' => 100,
        ])
        ->assertForbidden();

    expect(file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/user/bookings/Payment.vue'))
        ->toContain('Payment policy')
        ->toContain('do not show up');

    expect(file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/admin/bookings/Show.vue'))
        ->toContain('Remaining balance')
        ->toContain('Update payment');

    expect(file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/admin/bookings/Index.vue'))
        ->toContain('Balance left');

    expect(file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/admin/qr-scanner/VerifyShow.vue'))
        ->toContain('Update payment');
});

test('an operator can see the remaining balance on the bookings list', function () {
    [$operator, $booking] = downpaymentBooking('Admin');

    $response = $this->actingAs($operator)
        ->getJson(route('admin.bookings.data', ['search' => (string) $booking->id]));

    $response->assertOk();

    $row = collect($response->json('data'))->firstWhere('id', $booking->id);

    expect($row)->not->toBeNull()
        ->and((float) $row['remaining_balance'])->toBe($booking->remainingBalance());
});

/**
 * @return array{0: User, 1: Booking, 2: float}
 */
function downpaymentBooking(string $roleName): array
{
    $role = Role::query()->where('name', $roleName)->first();

    expect($role)->not->toBeNull();

    $staff = User::factory()->create([
        'role_id' => $role->id,
    ]);

    $schedule = PickupSchedule::query()->with('tourDate.package')->first();

    expect($schedule)->not->toBeNull();

    $tourist = User::factory()->create([
        'role_id' => Role::query()->where('name', 'User')->value('id'),
    ]);

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $schedule->tour_date_id,
        'pickup_schedule_id' => $schedule->id,
        'number_of_guests' => 2,
        'phone_number' => '0917'.random_int(1000000, 9999999),
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
    ]);

    $total = $booking->packageTotal();
    $downpayment = round($total / 2, 2);

    Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => $downpayment,
        'payment_method' => 'gcash',
        'payment_status' => 'partial',
        'notes' => 'Downpayment of 50% was confirmed by PayMongo. The remaining balance is still due.',
        'paid_at' => now(),
    ]);

    return [$staff, $booking->fresh(['tourDate.package', 'payments']), $total];
}

<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PickupSchedule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

test('the administrator confirms a booking payment that has a receipt', function () {
    $adminRole = Role::query()->where('name', 'Superadmin')->first();
    $touristRole = Role::query()->where('name', 'User')->first();

    expect($adminRole)->not->toBeNull()
        ->and($touristRole)->not->toBeNull();

    $operator = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $schedule = PickupSchedule::query()->first();

    expect($schedule)->not->toBeNull();

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $schedule->tour_date_id,
        'pickup_schedule_id' => $schedule->id,
        'number_of_guests' => 2,
        'phone_number' => '09171234567',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    $payment = Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => 3000,
        'payment_method' => 'gcash',
        'payment_status' => 'pending',
    ]);

    Storage::fake('public');
    Storage::disk('public')->put("payments/{$booking->id}/receipt.jpg", 'proof');

    $this->actingAs($operator)
        ->withSession(['_token' => 'test-token'])
        ->from(route('admin.bookings.index'))
        ->post(route('admin.bookings.confirm-payment', $booking), [
            '_token' => 'test-token',
        ])
        ->assertRedirect(route('admin.bookings.index'));

    expect($payment->fresh()->payment_status)->toBe('paid')
        ->and($payment->fresh()->paid_at)->not->toBeNull()
        ->and($booking->fresh()->booking_status)->toBe('confirmed');
});

test('a boat operator cannot confirm a booking payment', function () {
    $operatorRole = Role::query()->where('name', 'Admin')->first();
    $touristRole = Role::query()->where('name', 'User')->first();

    $admin = User::factory()->create([
        'role_id' => $operatorRole->id,
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $schedule = PickupSchedule::query()->first();

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $schedule->tour_date_id,
        'pickup_schedule_id' => $schedule->id,
        'number_of_guests' => 1,
        'phone_number' => '09170000000',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => 1500,
        'payment_method' => 'gcash',
        'payment_status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->post(route('admin.bookings.confirm-payment', $booking), [
            '_token' => 'test-token',
        ])
        ->assertForbidden();

    expect($booking->fresh()->booking_status)->toBe('pending')
        ->and($booking->payments()->first()->payment_status)->toBe('pending');
});

test('a boat operator can verify a booking without accepting the payment', function () {
    $operatorRole = Role::query()->where('name', 'Admin')->first();
    $touristRole = Role::query()->where('name', 'User')->first();

    $operator = User::factory()->create([
        'role_id' => $operatorRole->id,
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $schedule = PickupSchedule::query()->first();

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $schedule->tour_date_id,
        'pickup_schedule_id' => $schedule->id,
        'number_of_guests' => 2,
        'phone_number' => '09171234567',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    $payment = Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => 3000,
        'payment_method' => 'gcash',
        'payment_status' => 'pending',
    ]);

    $this->actingAs($operator)
        ->withSession(['_token' => 'test-token'])
        ->put(route('admin.bookings.update', $booking), [
            '_token' => 'test-token',
            'number_of_guests' => 2,
            'phone_number' => '09171234567',
            'nationality' => 'Filipino',
            'special_request' => null,
            'booking_status' => 'confirmed',
        ])
        ->assertRedirect(route('admin.bookings.index'));

    expect($booking->fresh()->booking_status)->toBe('confirmed')
        ->and($payment->fresh()->payment_status)->toBe('pending')
        ->and($payment->fresh()->paid_at)->toBeNull();
});

test('an administrator can accept a payment without a receipt photo', function () {
    $adminRole = Role::query()->where('name', 'Superadmin')->first();
    $touristRole = Role::query()->where('name', 'User')->first();

    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $schedule = PickupSchedule::query()->first();

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $schedule->tour_date_id,
        'pickup_schedule_id' => $schedule->id,
        'number_of_guests' => 1,
        'phone_number' => '09170001111',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    $payment = Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => 1500,
        'payment_method' => 'gcash',
        'payment_status' => 'pending',
    ]);

    Storage::fake('public');

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->from(route('admin.bookings.show', $booking))
        ->post(route('admin.bookings.confirm-payment', $booking), [
            '_token' => 'test-token',
        ])
        ->assertRedirect(route('admin.bookings.show', $booking))
        ->assertSessionHasNoErrors();

    expect($payment->fresh()->payment_status)->toBe('paid')
        ->and($booking->fresh()->booking_status)->toBe('confirmed');
});

test('a boat operator can view payments but cannot record or change one', function () {
    $operatorRole = Role::query()->where('name', 'Admin')->first();
    $touristRole = Role::query()->where('name', 'User')->first();

    $operator = User::factory()->create([
        'role_id' => $operatorRole->id,
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $schedule = PickupSchedule::query()->first();

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $schedule->tour_date_id,
        'pickup_schedule_id' => $schedule->id,
        'number_of_guests' => 1,
        'phone_number' => '09170003333',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    $payment = Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => 2000,
        'payment_method' => 'gcash',
        'payment_status' => 'pending',
    ]);

    $this->actingAs($operator)
        ->get(route('admin.payments.index'))
        ->assertOk();

    $this->actingAs($operator)
        ->withSession(['_token' => 'test-token'])
        ->post(route('admin.payments.store'), [
            '_token' => 'test-token',
            'booking_id' => $booking->id,
            'amount' => 2000,
            'payment_method' => 'gcash',
            'payment_status' => 'pending',
        ])
        ->assertForbidden();

    $this->actingAs($operator)
        ->withSession(['_token' => 'test-token'])
        ->put(route('admin.payments.update', $payment), [
            '_token' => 'test-token',
            'amount' => 2000,
            'payment_method' => 'gcash',
            'payment_status' => 'pending',
        ])
        ->assertForbidden();

    $this->actingAs($operator)
        ->withSession(['_token' => 'test-token'])
        ->delete(route('admin.payments.destroy', $payment), [
            '_token' => 'test-token',
        ])
        ->assertForbidden();

    expect($payment->fresh()->payment_status)->toBe('pending')
        ->and(Payment::query()->where('booking_id', $booking->id)->count())->toBe(1);
});

test('an administrator can view payments but cannot edit or delete one', function () {
    $adminRole = Role::query()->where('name', 'Superadmin')->first();
    $touristRole = Role::query()->where('name', 'User')->first();

    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $schedule = PickupSchedule::query()->first();

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $schedule->tour_date_id,
        'pickup_schedule_id' => $schedule->id,
        'number_of_guests' => 1,
        'phone_number' => '09170004444',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    $payment = Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => 2200,
        'payment_method' => 'gcash',
        'payment_status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->post(route('admin.payments.store'), [
            '_token' => 'test-token',
            'booking_id' => $booking->id,
            'amount' => 2200,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->put(route('admin.payments.update', $payment), [
            '_token' => 'test-token',
            'amount' => 2200,
            'payment_method' => 'gcash',
            'payment_status' => 'paid',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->delete(route('admin.payments.destroy', $payment), [
            '_token' => 'test-token',
        ])
        ->assertForbidden();

    expect($payment->fresh()->payment_status)->toBe('pending')
        ->and(Payment::query()->where('booking_id', $booking->id)->count())->toBe(1);
});

test('a tourist cannot attach a receipt photo from the user payment page', function () {
    $touristRole = Role::query()->where('name', 'User')->first();

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $schedule = PickupSchedule::query()->first();

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $schedule->tour_date_id,
        'pickup_schedule_id' => $schedule->id,
        'number_of_guests' => 1,
        'phone_number' => '09170002222',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    Storage::fake('public');
    config(['payment.paymongo.secret_key' => null]);

    $this->actingAs($tourist)
        ->withSession(['_token' => 'test-token'])
        ->post(route('user.bookings.payment.submit', $booking), [
            '_token' => 'test-token',
            'payment_method' => 'gcash',
            'transaction_reference' => 'GCASH-1',
        ])
        ->assertSessionHasErrors('payment_method');

    expect(Payment::query()->where('booking_id', $booking->id)->exists())->toBeFalse();

    $this->actingAs($tourist)
        ->withSession(['_token' => 'test-token'])
        ->post(route('user.bookings.payment.submit', $booking), [
            '_token' => 'test-token',
            'payment_method' => 'cash',
            'proof_of_payment' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertSessionHasErrors('payment_method');

    expect(Payment::query()->where('booking_id', $booking->id)->exists())->toBeFalse();
});

test('the administrator can cancel a booking that has no payment', function () {
    $adminRole = Role::query()->where('name', 'Superadmin')->first();
    $touristRole = Role::query()->where('name', 'User')->first();

    $administrator = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $schedule = PickupSchedule::query()->first();

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $schedule->tour_date_id,
        'pickup_schedule_id' => $schedule->id,
        'number_of_guests' => 1,
        'phone_number' => '09170003333',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    $this->actingAs($administrator)
        ->withSession(['_token' => 'test-token'])
        ->put(route('admin.bookings.update', $booking), [
            '_token' => 'test-token',
            'number_of_guests' => 1,
            'phone_number' => '09170003333',
            'nationality' => 'Filipino',
            'booking_status' => 'cancelled',
        ])
        ->assertRedirect(route('admin.bookings.index'));

    expect($booking->fresh()->booking_status)->toBe('cancelled')
        ->and(Payment::query()->where('booking_id', $booking->id)->exists())->toBeFalse();
});

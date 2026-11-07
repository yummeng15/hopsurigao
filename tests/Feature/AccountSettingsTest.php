<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TourDate;
use App\Models\TourOperator;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\WeatherAdvisoryNotification;
use App\Support\SuperadminNotificationGate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('inertia.ssr.enabled', false);
});

function accountFor(string $roleName): User
{
    $role = Role::firstOrCreate(['name' => $roleName]);

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    if ($roleName === 'Admin') {
        $operator = TourOperator::create([
            'name' => 'Settings Operator',
            'slug' => 'settings-operator-'.Str::uuid(),
            'is_active' => true,
        ]);

        $user->forceFill([
            'tour_operator_id' => $operator->id,
        ])->save();
    }

    return $user->refresh();
}

test('a tour operator can update only their own account settings', function () {
    $operator = accountFor('Admin');
    $other = accountFor('Admin');

    $this->actingAs($operator)
        ->get(route('operator.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/Settings')
            ->where('profile.email', $operator->email)
            ->where('preferences.bookings', true)
            ->where('preferences.trips', true)
        );

    $this->actingAs($operator)
        ->patch(route('operator.settings.profile'), [
            'name' => 'Updated Operator',
            'email' => 'updated-operator@example.com',
        ])
        ->assertRedirect(route('operator.settings.edit'))
        ->assertSessionHas('profile_status', 'Profile saved.');

    expect($operator->fresh()->name)->toBe('Updated Operator')
        ->and($other->fresh()->name)->not->toBe('Updated Operator');

    $this->actingAs($operator)
        ->put(route('operator.settings.password'), [
            'current_password' => 'wrong-password',
            'password' => 'Updated-pass-1',
            'password_confirmation' => 'Updated-pass-1',
        ])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($operator)
        ->put(route('operator.settings.password'), [
            'current_password' => 'password',
            'password' => 'Updated-pass-1',
            'password_confirmation' => 'Updated-pass-1',
        ])
        ->assertSessionHas('password_status', 'Password updated.');

    expect(Hash::check('Updated-pass-1', $operator->fresh()->password))->toBeTrue();

    $this->actingAs($operator)
        ->patch(route('operator.settings.notifications'), [
            'bookings' => false,
            'trips' => false,
        ])
        ->assertSessionHas('notification_status', 'Notification preferences saved.');

    $operator->refresh();

    expect(SuperadminNotificationGate::shouldDeliver($operator, new BookingCreatedNotification(new Booking)))->toBeFalse()
        ->and(SuperadminNotificationGate::shouldDeliver($operator, new BookingCancelledNotification(new Booking)))->toBeFalse()
        ->and(SuperadminNotificationGate::shouldDeliver(
            $operator,
            new WeatherAdvisoryNotification(new TourDate, 'red', 'Danger', 'Unsafe conditions'),
        ))->toBeTrue();
});

test('a tourist can update personal information and cannot silence essential alerts', function () {
    $tourist = accountFor('User');
    $other = accountFor('User');

    $this->actingAs($tourist)
        ->get(route('user.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/Settings')
            ->where('profile.email', $tourist->email)
            ->where('preferences.cancellations', true)
        );

    $this->actingAs($tourist)
        ->patch(route('user.settings.profile'), [
            'name' => 'Updated Tourist',
            'email' => 'updated-tourist@example.com',
            'contact_number' => '09171234567',
        ])
        ->assertRedirect(route('user.settings.edit'))
        ->assertSessionHas('profile_status', 'Profile saved.');

    $tourist->refresh();

    expect($tourist->name)->toBe('Updated Tourist')
        ->and($tourist->contact_number)->toBe('09171234567')
        ->and($other->fresh()->email)->not->toBe('updated-tourist@example.com');

    $this->actingAs($tourist)
        ->patch(route('user.settings.profile'), [
            'name' => 'Updated Tourist',
            'email' => $tourist->email,
            'contact_number' => 'abc',
        ])
        ->assertSessionHasErrors('contact_number');

    $this->actingAs($tourist)
        ->put(route('user.settings.password'), [
            'current_password' => 'password',
            'password' => 'Updated-pass-1',
            'password_confirmation' => 'Updated-pass-1',
        ])
        ->assertSessionHas('password_status', 'Password updated.');

    expect(Hash::check('Updated-pass-1', $tourist->fresh()->password))->toBeTrue();

    $this->actingAs($tourist)
        ->patch(route('user.settings.notifications'), [
            'cancellations' => false,
        ])
        ->assertSessionHas('notification_status', 'Notification preferences saved.');

    $tourist->refresh();
    $tourist->notify(new BookingCancelledNotification(new Booking));

    expect($tourist->notifications()->count())->toBe(0)
        ->and(SuperadminNotificationGate::shouldDeliver($tourist, new BookingConfirmedNotification(new Booking)))->toBeTrue()
        ->and(SuperadminNotificationGate::shouldDeliver($tourist, new PaymentConfirmedNotification(new Payment)))->toBeTrue()
        ->and(SuperadminNotificationGate::shouldDeliver(
            $tourist,
            new WeatherAdvisoryNotification(new TourDate, 'red', 'Danger', 'Unsafe conditions'),
        ))->toBeTrue();
});

test('settings pages stay limited to the matching role', function () {
    $superadmin = accountFor('Superadmin');
    $operator = accountFor('Admin');
    $tourist = accountFor('User');

    $this->actingAs($tourist)->get(route('operator.settings.edit'))->assertForbidden();
    $this->actingAs($superadmin)->get(route('operator.settings.edit'))->assertForbidden();
    $this->actingAs($operator)->get(route('user.settings.edit'))->assertForbidden();
    $this->actingAs($superadmin)->get(route('user.settings.edit'))->assertForbidden();

    $this->actingAs($operator)
        ->patch(route('user.settings.profile'), [
            'name' => 'Not Allowed',
            'email' => 'not-allowed@example.com',
            'contact_number' => '09170000000',
        ])
        ->assertForbidden();

    expect($operator->fresh()->name)->not->toBe('Not Allowed');
});

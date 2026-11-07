<?php

use App\Models\Booking;
use App\Models\Role;
use App\Models\TourDate;
use App\Models\User;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\WeatherAdvisoryNotification;
use App\Support\SuperadminNotificationGate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('inertia.ssr.enabled', false);
});

function settingsAccount(string $roleName): User
{
    $role = Role::firstOrCreate([
        'name' => $roleName,
    ]);

    return User::factory()->create([
        'role_id' => $role->id,
    ]);
}

test('super admin can view and update their own profile', function () {
    $superadmin = settingsAccount('Superadmin');

    $this->actingAs($superadmin)
        ->get(route('superadmin.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('superadmin/Settings')
            ->where('profile.name', $superadmin->name)
            ->where('profile.email', $superadmin->email)
            ->where('preferences.bookings', true)
            ->where('about.name', config('app.name'))
            ->missing('about.key')
        );

    $this->actingAs($superadmin)
        ->patch(route('superadmin.settings.profile'), [
            'name' => 'Updated Super Admin',
            'email' => 'updated-superadmin@example.com',
        ])
        ->assertRedirect(route('superadmin.settings.edit'))
        ->assertSessionHas('profile_status', 'Profile saved.');

    $superadmin->refresh();

    expect($superadmin->name)->toBe('Updated Super Admin')
        ->and($superadmin->email)->toBe('updated-superadmin@example.com');
});

test('profile and password changes are validated', function () {
    $superadmin = settingsAccount('Superadmin');
    $other = settingsAccount('User');

    $this->actingAs($superadmin)
        ->patch(route('superadmin.settings.profile'), [
            'name' => '',
            'email' => $other->email,
        ])
        ->assertSessionHasErrors(['name', 'email']);

    expect($superadmin->fresh()->email)->not->toBe($other->email);

    $this->actingAs($superadmin)
        ->put(route('superadmin.settings.password'), [
            'current_password' => 'wrong-password',
            'password' => 'Updated-pass-1',
            'password_confirmation' => 'Updated-pass-1',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $superadmin->fresh()->password))->toBeTrue();

    $this->actingAs($superadmin)
        ->put(route('superadmin.settings.password'), [
            'current_password' => 'password',
            'password' => 'Updated-pass-1',
            'password_confirmation' => 'Updated-pass-1',
        ])
        ->assertRedirect(route('superadmin.settings.edit'))
        ->assertSessionHas('password_status', 'Password updated.');

    expect(Hash::check('Updated-pass-1', $superadmin->fresh()->password))->toBeTrue()
        ->and(Hash::check('password', $superadmin->fresh()->password))->toBeFalse();
});

test('notification preferences are saved per super admin and skip only optional alerts', function () {
    $superadmin = settingsAccount('Superadmin');
    $operator = settingsAccount('Admin');

    $this->actingAs($superadmin)
        ->patch(route('superadmin.settings.notifications'), [
            'bookings' => false,
            'cancellations' => true,
            'payments' => false,
            'weather' => false,
        ])
        ->assertRedirect(route('superadmin.settings.edit'))
        ->assertSessionHas('notification_status', 'Notification preferences saved.');

    $superadmin->refresh();

    expect($superadmin->notification_preferences)->toMatchArray([
        'bookings' => false,
        'cancellations' => true,
        'payments' => false,
        'weather' => false,
    ]);

    $tourist = settingsAccount('User');
    $tourist->notify(new BookingCreatedNotification(new Booking));
    expect($tourist->notifications()->count())->toBe(1);

    $superadmin->notify(new BookingCreatedNotification(new Booking));
    expect($superadmin->notifications()->count())->toBe(0);

    $yellow = new WeatherAdvisoryNotification(new TourDate, 'yellow', 'Caution', 'Routine advisory');
    $red = new WeatherAdvisoryNotification(new TourDate, 'red', 'Danger', 'Unsafe conditions');

    expect(SuperadminNotificationGate::shouldDeliver($superadmin, $yellow))->toBeFalse()
        ->and(SuperadminNotificationGate::shouldDeliver($superadmin, $red))->toBeTrue()
        ->and(SuperadminNotificationGate::shouldDeliver($operator, $yellow))->toBeTrue();
});

test('tour operators and tourists cannot open super admin settings', function () {
    $operator = settingsAccount('Admin');
    $tourist = settingsAccount('User');

    foreach ([$operator, $tourist] as $account) {
        $this->actingAs($account)
            ->get(route('superadmin.settings.edit'))
            ->assertForbidden();

        $this->actingAs($account)
            ->patch(route('superadmin.settings.profile'), [
                'name' => 'Not Allowed',
                'email' => 'not-allowed@example.com',
            ])
            ->assertForbidden();

        expect($account->fresh()->name)->not->toBe('Not Allowed');
    }
});

test('settings save buttons use bright cyan across staff portals', function () {
    $superadmin = file_get_contents(resource_path('js/pages/superadmin/Settings.vue'));
    $operator = file_get_contents(resource_path('js/pages/operator/Settings.vue'));

    expect($superadmin)->toContain('bg-[#00FFFF]')
        ->and($superadmin)->not->toContain('bg-cyan-700')
        ->and($operator)->toContain("bg-[#00FFFF]")
        ->and($operator)->toContain("text-cyan-950");
});

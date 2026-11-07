<?php

use App\Models\Role;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use App\Services\PasswordResetCodeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    config()->set('inertia.ssr.enabled', false);
    $this->withoutVite();
});

function resetCodeUser(string $role = 'User'): User
{
    $roleId = Role::query()->firstOrCreate(['name' => $role])->id;

    return User::factory()->create([
        'role_id' => $roleId,
        'email' => 'reset-'.strtolower($role).'@example.com',
        'password' => Hash::make('old-password'),
    ]);
}

function seedResetCode(string $email, string $plainCode = '123456', bool $verified = false): void
{
    DB::table('password_reset_codes')->where('email', $email)->delete();

    DB::table('password_reset_codes')->insert([
        'email' => strtolower($email),
        'code' => Hash::make($plainCode),
        'attempts' => 0,
        'expires_at' => now()->addMinutes(10),
        'verified_at' => $verified ? now() : null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('forgot password screens render for guests', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/ForgotPassword'));
});

test('requesting a code notifies an existing user and opens the verify page', function () {
    Notification::fake();

    $user = resetCodeUser('Admin');

    $this->post(route('password.email'), [
        'email' => $user->email,
    ])
        ->assertRedirect(route('password.verify'));

    Notification::assertSentTo($user, PasswordResetCodeNotification::class);

    $this->get(route('password.verify'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/VerifyPasswordResetCode')
            ->where('email', strtolower($user->email)));
});

test('requesting a code for an unknown email does not leak existence', function () {
    Notification::fake();

    $this->post(route('password.email'), [
        'email' => 'missing@example.com',
    ])
        ->assertRedirect(route('password.verify'));

    Notification::assertNothingSent();
});

test('a valid code unlocks the new password form for any role', function (string $role) {
    $user = resetCodeUser($role);
    seedResetCode($user->email, '654321');

    $this->withSession([
        'password_reset_email' => strtolower($user->email),
    ])
        ->post(route('password.verify.store'), [
            'code' => '654321',
        ])
        ->assertRedirect(route('password.reset'));

    $this->withSession([
        'password_reset_email' => strtolower($user->email),
        'password_reset_verified' => true,
    ])
        ->get(route('password.reset'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/ResetPassword'));
})->with(['User', 'Admin', 'Superadmin']);

test('an invalid code is rejected', function () {
    $user = resetCodeUser();
    seedResetCode($user->email, '111111');

    $this->withSession([
        'password_reset_email' => strtolower($user->email),
    ])
        ->post(route('password.verify.store'), [
            'code' => '000000',
        ])
        ->assertSessionHasErrors('code');
});

test('a verified code can reset the password', function () {
    $user = resetCodeUser('Superadmin');
    seedResetCode($user->email, '222222', verified: true);

    $this->withSession([
        'password_reset_email' => strtolower($user->email),
        'password_reset_verified' => true,
    ])
        ->post(route('password.update'), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertRedirect(route('login'));

    expect(Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue();
    expect(DB::table('password_reset_codes')->where('email', strtolower($user->email))->count())->toBe(0);
});

test('password reset codes expire after the configured window', function () {
    $service = app(PasswordResetCodeService::class);
    $user = resetCodeUser();

    DB::table('password_reset_codes')->insert([
        'email' => strtolower($user->email),
        'code' => Hash::make('333333'),
        'attempts' => 0,
        'expires_at' => now()->subMinute(),
        'verified_at' => null,
        'created_at' => now()->subMinutes(11),
        'updated_at' => now()->subMinutes(11),
    ]);

    expect(fn () => $service->verify($user->email, '333333'))
        ->toThrow(ValidationException::class);
});

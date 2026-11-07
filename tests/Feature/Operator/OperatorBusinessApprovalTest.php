<?php

use App\Models\Booking;
use App\Models\PackageOwnershipRequest;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\OperatorBusinessDecisionNotification;
use App\Notifications\OperatorBusinessSubmittedNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('inertia.ssr.enabled', false);
    Notification::fake();
});

function approvalAdmin(): User
{
    return User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Admin'])->id,
        'email_verified_at' => now(),
    ]);
}

function approvalSuperadmin(): User
{
    return User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Superadmin'])->id,
        'email_verified_at' => now(),
    ]);
}

function approvalBusinessPayload(string $name): array
{
    return [
        'name' => $name.' '.Str::random(4),
        'contact_email' => Str::uuid().'@example.com',
        'contact_phone' => '09170001234',
        'description' => 'Island departures',
    ];
}

test('an operator admin registers a pending business and cannot book until it is approved', function () {
    $admin = approvalAdmin();
    $superadmin = approvalSuperadmin();
    $payload = approvalBusinessPayload('Pending Shores');

    $this->actingAs($admin)
        ->post(route('operator.business.store'), $payload)
        ->assertRedirect(route('operator.business.edit'));

    $operator = TourOperator::query()->where('name', $payload['name'])->first();

    expect($operator)->not->toBeNull()
        ->and($operator->approval_status)->toBe(TourOperator::STATUS_PENDING)
        ->and($operator->is_active)->toBeFalse()
        ->and($admin->fresh()->tour_operator_id)->toBe($operator->id);

    Notification::assertSentTo($superadmin, OperatorBusinessSubmittedNotification::class);

    $this->actingAs($admin)
        ->get(route('operator.business.edit'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('operator.dashboard'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('operator.business.store'), approvalBusinessPayload('Second Shores'))
        ->assertSessionHasErrors('name');

    $this->actingAs($admin)
        ->patch(route('superadmin.operators.decide', $operator), ['decision' => 'approve'])
        ->assertForbidden();

    expect($operator->fresh()->approval_status)->toBe(TourOperator::STATUS_PENDING);
});

test('superadmin approval rejection suspension and package ownership keep existing records', function () {
    $admin = approvalAdmin();
    $other = approvalAdmin();
    $superadmin = approvalSuperadmin();
    $payload = approvalBusinessPayload('Reviewed Shores');

    $this->actingAs($admin)->post(route('operator.business.store'), $payload)->assertRedirect();
    $admin->refresh();
    $operator = TourOperator::query()->where('name', $payload['name'])->firstOrFail();

    $this->actingAs($superadmin)
        ->patch(route('superadmin.operators.decide', $operator), [
            'decision' => 'reject',
            'rejection_reason' => 'Contact number could not be confirmed.',
        ])
        ->assertRedirect();

    expect($operator->fresh()->approval_status)->toBe(TourOperator::STATUS_REJECTED)
        ->and($operator->fresh()->rejection_reason)->toBe('Contact number could not be confirmed.');

    Notification::assertSentTo($admin, OperatorBusinessDecisionNotification::class);

    $admin->refresh();

    $this->actingAs($admin)
        ->get(route('operator.business.edit'))
        ->assertOk()
        ->assertSee('Contact number could not be confirmed.', false);

    $this->actingAs($admin)
        ->patch(route('operator.business.update'), [
            'name' => $payload['name'],
            'contact_email' => $payload['contact_email'],
            'contact_phone' => '09180009999',
            'description' => 'Corrected details',
        ])
        ->assertRedirect();

    expect($operator->fresh()->approval_status)->toBe(TourOperator::STATUS_PENDING);

    $this->actingAs($superadmin)
        ->patch(route('superadmin.operators.decide', $operator), ['decision' => 'approve'])
        ->assertRedirect();

    expect($operator->fresh()->approval_status)->toBe(TourOperator::STATUS_ACTIVE)
        ->and($operator->fresh()->is_active)->toBeTrue();

    $admin->unsetRelation('tourOperator');

    $this->actingAs($admin)
        ->get(route('operator.packages.index'))
        ->assertOk();

    $packageId = DB::table('packages')->insertGetId([
        'tour_operator_id' => null,
        'package_name' => 'Preserved Package '.Str::random(3),
        'boat_name' => 'Preserved Boat',
        'destination' => 'Guyam Island',
        'price' => 1600,
        'status' => 'active',
        'under_maintenance' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('operator.business.package-requests.store'), ['package_id' => $packageId])
        ->assertRedirect();

    $request = PackageOwnershipRequest::query()->where('package_id', $packageId)->firstOrFail();

    $this->actingAs($superadmin)
        ->patch(route('superadmin.package-ownership-requests.review', $request), ['decision' => 'approve'])
        ->assertRedirect();

    expect((int) DB::table('packages')->where('id', $packageId)->value('tour_operator_id'))->toBe($operator->id)
        ->and((string) DB::table('packages')->where('id', $packageId)->value('price'))->toBe('1600.00');

    $date = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => today()->addDays(8)->toDateString(),
        'capacity' => 5,
        'travel_closed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $location = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator->id,
        'name' => 'Review Pier '.Str::random(3),
        'status' => 'active',
        'latitude' => 9.78,
        'longitude' => 125.49,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $pickup = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $date,
        'pickup_location_id' => $location,
        'pickup_time' => '08:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tourist = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'User'])->id,
    ]);

    $this->actingAs($tourist)
        ->post(route('user.bookings.store'), [
            'tour_date_id' => $date,
            'pickup_schedule_id' => $pickup,
            'pickup_latitude' => 9.78,
            'pickup_longitude' => 125.49,
            'number_of_guests' => 1,
            'phone_number' => '09181230000',
            'nationality' => 'Filipino',
        ])
        ->assertRedirect();

    $booking = Booking::query()->where('user_id', $tourist->id)->first();
    $bookingId = $booking->id;
    $bookingStatus = $booking->booking_status;

    Notification::assertSentTo($admin, BookingCreatedNotification::class);

    $this->actingAs($other);
    expect(Booking::query()->whereKey($bookingId)->exists())->toBeFalse();

    $this->actingAs($superadmin)
        ->patch(route('superadmin.operators.decide', $operator), ['decision' => 'suspend'])
        ->assertRedirect();

    $this->actingAs($tourist)
        ->post(route('user.bookings.store'), [
            'tour_date_id' => $date,
            'pickup_schedule_id' => $pickup,
            'pickup_latitude' => 9.78,
            'pickup_longitude' => 125.49,
            'number_of_guests' => 1,
            'phone_number' => '09181230001',
            'nationality' => 'Filipino',
        ])
        ->assertSessionHasErrors('tour_date_id');

    $preserved = DB::table('bookings')->where('id', $bookingId)->first();

    expect($preserved->booking_status)->toBe($bookingStatus)
        ->and((int) $preserved->number_of_guests)->toBe(1);

    $this->actingAs($admin);
    expect(Booking::query()->whereKey($bookingId)->exists())->toBeTrue();
});

test('public operator registration creates an admin and never a superadmin', function () {
    $this->post(route('register.operator.store'), [
        'name' => 'New Operator',
        'email' => 'new-operator-'.Str::uuid().'@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'role' => 'Superadmin',
    ])->assertRedirect(route('operator.business.edit'));

    $user = User::query()->where('name', 'New Operator')->first();

    expect($user)->not->toBeNull()
        ->and($user->role->name)->toBe('Admin')
        ->and($user->tour_operator_id)->toBeNull();
});

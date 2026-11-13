<?php

use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    config()->set('inertia.ssr.enabled', false);
    $this->withoutVite();
});

function profileAdmin(): User
{
    return User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Admin'])->id,
        'email_verified_at' => now(),
    ]);
}

function profilePayload(string $name): array
{
    return [
        'name' => $name.' '.Str::random(5),
        'contact_email' => Str::uuid().'@example.com',
        'contact_phone' => '09171234567',
        'description' => 'Surigao island hopping departures',
    ];
}

test('unassigned operator sees a null business for the registration form', function () {
    $admin = profileAdmin();

    $this->actingAs($admin)
        ->get(route('operator.business.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/Business')
            ->where('business', null)
            ->has('unassignedPackages')
        );
});

test('saving business details links one operator and returns saved business props', function () {
    $admin = profileAdmin();
    $payload = profilePayload('Lagoon Shores');

    $this->actingAs($admin)
        ->post(route('operator.business.store'), $payload)
        ->assertRedirect(route('operator.business.edit'));

    $admin->refresh();
    $operator = TourOperator::query()->whereKey($admin->tour_operator_id)->first();

    expect($operator)->not->toBeNull()
        ->and(TourOperator::query()->where('contact_email', $payload['contact_email'])->count())->toBe(1)
        ->and($operator->name)->toBe($payload['name'])
        ->and($operator->approval_status)->toBe(TourOperator::STATUS_PENDING);

    $this->actingAs($admin)
        ->get(route('operator.business.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/Business')
            ->where('business.id', $operator->id)
            ->where('business.name', $payload['name'])
            ->where('business.contact_email', $payload['contact_email'])
            ->where('business.contact_phone', $payload['contact_phone'])
            ->where('business.description', $payload['description'])
            ->where('business.approval_status', TourOperator::STATUS_PENDING)
            ->where('business.status_label', 'Pending Approval')
        );
});

test('editing an existing business updates the same record without creating a duplicate', function () {
    $admin = profileAdmin();
    $payload = profilePayload('Blue Cove');

    $this->actingAs($admin)
        ->post(route('operator.business.store'), $payload)
        ->assertRedirect();

    $admin->refresh();
    $operatorId = (int) $admin->tour_operator_id;
    $beforeCount = TourOperator::query()->count();

    $updated = [
        'name' => $payload['name'],
        'contact_email' => $payload['contact_email'],
        'contact_phone' => '09180001111',
        'description' => 'Updated description for defense demo',
    ];

    $this->actingAs($admin)
        ->patch(route('operator.business.update'), $updated)
        ->assertRedirect(route('operator.business.edit'));

    expect(TourOperator::query()->count())->toBe($beforeCount)
        ->and((int) $admin->fresh()->tour_operator_id)->toBe($operatorId)
        ->and(TourOperator::query()->whereKey($operatorId)->value('contact_phone'))->toBe('09180001111')
        ->and(TourOperator::query()->whereKey($operatorId)->value('description'))->toBe('Updated description for defense demo')
        ->and(TourOperator::query()->whereKey($operatorId)->value('approval_status'))->toBe(TourOperator::STATUS_PENDING);

    $this->actingAs($admin)
        ->post(route('operator.business.store'), profilePayload('Duplicate Attempt'))
        ->assertSessionHasErrors('name');

    expect(TourOperator::query()->count())->toBe($beforeCount);
});

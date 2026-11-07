
<?php

use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
});

function assignmentAccount(
    string $role,
    ?TourOperator $operator = null
): User {
    $user = User::factory()->create([
        'role_id' => Role::firstOrCreate([
            'name' => $role,
        ])->id,
    ]);

    if ($operator !== null) {
        $user->forceFill([
            'tour_operator_id' => $operator->id,
        ])->save();
    }

    return $user->refresh();
}

function assignmentOperator(
    bool $active = true
): TourOperator {
    return TourOperator::create([
        'name' => 'Assignment Test '.Str::random(8),
        'slug' => 'assignment-test-'.Str::uuid(),
        'is_active' => $active,
    ]);
}

test('superadmin can see existing unassigned Admin accounts', function () {
    $unassigned = assignmentAccount('Admin');

    $assigned = assignmentAccount(
        'Admin',
        assignmentOperator()
    );

    $superadmin = assignmentAccount('Superadmin');

    $this->actingAs($superadmin)
        ->get(route('superadmin.operator-admins.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('superadmin/operator-admins/Index')
            ->has('operators')
            ->has('admins')
            ->where(
                'unassignedAdmins',
                fn ($admins) =>
                    collect($admins)->contains(
                        'id',
                        $unassigned->id
                    )
                    && ! collect($admins)->contains(
                        'id',
                        $assigned->id
                    )
            )
        );
});

test('superadmin can assign an existing Admin and restore Operator dashboard access', function () {
    $operator = assignmentOperator();
    $admin = assignmentAccount('Admin');
    $superadmin = assignmentAccount('Superadmin');

    $originalPassword = $admin->password;
    $originalEmail = $admin->email;

    $this->actingAs($admin)
        ->get(route('operator.dashboard'))
        ->assertForbidden();

    $this->actingAs($superadmin)
        ->patch(
            route(
                'superadmin.operator-admins.assign',
                $admin->id
            ),
            [
                'tour_operator_id' => $operator->id,
            ]
        )
        ->assertRedirect(
            route('superadmin.operator-admins.index')
        );

    $admin->refresh();

    expect((int) $admin->tour_operator_id)
        ->toBe((int) $operator->id)
        ->and($admin->email)
        ->toBe($originalEmail)
        ->and($admin->password)
        ->toBe($originalPassword)
        ->and($admin->role?->name)
        ->toBe('Admin');

    $this->actingAs($admin)
        ->get(route('operator.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/Dashboard')
        );
});

test('the same Admin cannot be assigned to a different operator again', function () {
    $first = assignmentOperator();
    $second = assignmentOperator();

    $admin = assignmentAccount('Admin', $first);

    $this->actingAs(assignmentAccount('Superadmin'))
        ->patch(
            route(
                'superadmin.operator-admins.assign',
                $admin->id
            ),
            [
                'tour_operator_id' => $second->id,
            ]
        )
        ->assertSessionHasErrors('tour_operator_id');

    expect((int) $admin->fresh()->tour_operator_id)
        ->toBe((int) $first->id);
});

test('Tourist and Superadmin user accounts cannot be assigned as Operator Admins', function () {
    $operator = assignmentOperator();

    $tourist = assignmentAccount('User');
    $otherSuperadmin = assignmentAccount('Superadmin');

    $this->actingAs(
        assignmentAccount('Superadmin')
    );

    foreach (
        [$tourist, $otherSuperadmin]
        as $user
    ) {
        $this->patch(
            route(
                'superadmin.operator-admins.assign',
                $user->id
            ),
            [
                'tour_operator_id' => $operator->id,
            ]
        )->assertSessionHasErrors('tour_operator_id');

        expect($user->fresh()->tour_operator_id)
            ->toBeNull();
    }
});

test('inactive or nonexistent tour operators cannot receive an Admin assignment', function () {
    $inactive = assignmentOperator(false);
    $admin = assignmentAccount('Admin');

    $this->actingAs(
        assignmentAccount('Superadmin')
    );

    $this->patch(
        route(
            'superadmin.operator-admins.assign',
            $admin->id
        ),
        [
            'tour_operator_id' => $inactive->id,
        ]
    )->assertSessionHasErrors('tour_operator_id');

    $this->patch(
        route(
            'superadmin.operator-admins.assign',
            $admin->id
        ),
        [
            'tour_operator_id' => 999999999,
        ]
    )->assertSessionHasErrors('tour_operator_id');

    expect($admin->fresh()->tour_operator_id)
        ->toBeNull();
});

test('only Superadmin can assign existing Admins', function () {
    $operator = assignmentOperator();
    $target = assignmentAccount('Admin');

    $actors = [
        assignmentAccount('User'),
        assignmentAccount('Admin', $operator),
    ];

    foreach ($actors as $actor) {
        $this->actingAs($actor)
            ->patch(
                route(
                    'superadmin.operator-admins.assign',
                    $target->id
                ),
                [
                    'tour_operator_id' => $operator->id,
                ]
            )
            ->assertForbidden();
    }

    expect($target->fresh()->tour_operator_id)
        ->toBeNull();
});

test('creating a new Admin still creates a correctly assigned account', function () {
    $operator = assignmentOperator();
    $email = 'newadmin'
        .Str::random(10)
        .'@example.test';

    $this->actingAs(
        assignmentAccount('Superadmin')
    )
        ->post(
            route('superadmin.operator-admins.store'),
            [
                'name' => 'New Operator Admin',
                'email' => $email,
                'password' => 'StrongPassword123',
                'password_confirmation' =>
                    'StrongPassword123',
                'tour_operator_id' => $operator->id,
            ]
        )
        ->assertRedirect(
            route('superadmin.operator-admins.index')
        );

    $admin = User::where('email', $email)
        ->firstOrFail();

    expect($admin->role?->name)
        ->toBe('Admin')
        ->and((int) $admin->tour_operator_id)
        ->toBe((int) $operator->id)
        ->and(
            Hash::check(
                'StrongPassword123',
                $admin->password
            )
        )
        ->toBeTrue();
});

<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Fortify\Features;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->withSession(['_token' => 'test-token'])->post(route('register.store'), [
        '_token' => 'test-token',
        'name' => 'Test User',
        'email' => 'unique-user-'.uniqid().'@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login', absolute: false));
    $response->assertSessionHas('status', 'Your account was created. Please log in.');
});

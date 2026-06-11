<?php

use App\Models\User;

test('a user can register and receives a token', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
        'device_name' => 'iphone-15',
    ]);

    $response
        ->assertCreated()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'created_at', 'updated_at']])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', 'test@example.com');

    $user = User::firstWhere('email', 'test@example.com');

    expect($user)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->first()->name)->toBe('iphone-15');
});

test('the token name defaults to api when device_name is omitted', function () {
    $this->postJson('/api/v1/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertCreated();

    expect(User::firstWhere('email', 'test@example.com')->tokens()->first()->name)->toBe('api');
});

test('the email is normalized to lowercase', function () {
    $this->postJson('/api/v1/register', [
        'name' => 'Test User',
        'email' => 'Test@EXAMPLE.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertCreated();

    expect(User::firstWhere('email', 'test@example.com'))->not->toBeNull();
});

test('registration fails with a duplicate email', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $this->postJson('/api/v1/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('registration fails with a weak password', function () {
    $this->postJson('/api/v1/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => '123',
        'password_confirmation' => '123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

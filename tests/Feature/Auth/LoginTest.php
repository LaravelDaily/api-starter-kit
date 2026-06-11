<?php

use App\Models\User;

test('a user can login with valid credentials', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'password',
        'device_name' => 'cli',
    ]);

    $response
        ->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'created_at', 'updated_at']])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id);

    expect($user->tokens()->first()->name)->toBe('cli');
});

test('the email lookup is case-insensitive', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $this->postJson('/api/v1/login', [
        'email' => 'Test@EXAMPLE.com',
        'password' => 'password',
    ])->assertOk();
});

test('login fails with wrong credentials', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $this->postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('login fails for an unknown email', function () {
    $this->postJson('/api/v1/login', [
        'email' => 'missing@example.com',
        'password' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('login is rate limited after too many attempts', function () {
    User::factory()->create(['email' => 'test@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});

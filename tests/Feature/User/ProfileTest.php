<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('the authenticated user can be retrieved', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonMissingPath('data.password');
});

test('the user endpoint requires authentication', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized();
});

test('the profile name and email can be updated', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->putJson('/api/v1/user', [
        'name' => 'New Name',
        'email' => 'New@Example.com',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.email', 'new@example.com');

    expect($user->fresh())
        ->name->toBe('New Name')
        ->email->toBe('new@example.com');
});

test('a partial update only changes the provided fields', function () {
    $user = User::factory()->create(['name' => 'Old Name']);

    Sanctum::actingAs($user);

    $this->putJson('/api/v1/user', ['name' => 'New Name'])->assertOk();

    expect($user->fresh())
        ->name->toBe('New Name')
        ->email->toBe($user->email);
});

test('the email must be unique to other users', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->putJson('/api/v1/user', ['email' => 'taken@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('the user can keep their own email', function () {
    $user = User::factory()->create(['email' => 'mine@example.com']);

    Sanctum::actingAs($user);

    $this->putJson('/api/v1/user', ['email' => 'mine@example.com'])->assertOk();
});

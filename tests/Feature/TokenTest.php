<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('the active tokens can be listed without exposing hashes', function () {
    $user = User::factory()->create();
    $user->createToken('iphone-15');
    $user->createToken('cli');

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/tokens')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'abilities', 'last_used_at', 'created_at']]])
        ->assertJsonMissingPath('data.0.token');
});

test('a token can be revoked by id', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api');

    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/tokens/{$token->accessToken->id}")
        ->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

test('revoking another users token returns 404', function () {
    $otherToken = User::factory()->create()->createToken('api');
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/tokens/{$otherToken->accessToken->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
});

test('all tokens except the current one can be revoked', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('current')->plainTextToken;
    $user->createToken('other');
    $user->createToken('another');

    $this->withToken($currentToken)
        ->deleteJson('/api/v1/tokens')
        ->assertNoContent();

    expect($user->tokens()->pluck('name')->all())->toBe(['current']);

    $this->withToken($currentToken)
        ->getJson('/api/v1/user')
        ->assertOk();
});

test('listing tokens requires authentication', function () {
    $this->getJson('/api/v1/tokens')->assertUnauthorized();
});

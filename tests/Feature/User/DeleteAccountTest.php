<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('the account can be deleted with a password confirmation', function () {
    $user = User::factory()->create();
    $user->createToken('api');

    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/user', ['password' => 'password'])
        ->assertNoContent();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'tokenable_type' => User::class,
    ]);
});

test('the password must be correct to delete the account', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/user', ['password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

test('deleting the account requires authentication', function () {
    $this->deleteJson('/api/v1/user', ['password' => 'password'])->assertUnauthorized();
});

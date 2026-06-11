<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the password can be changed and other tokens are revoked', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('current')->plainTextToken;
    $user->createToken('other');

    $this->withToken($currentToken)
        ->putJson('/api/v1/user/password', [
            'current_password' => 'password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Password updated.');

    expect(Hash::check('new-secret-password', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->pluck('name')->all())->toBe(['current']);

    $this->withToken($currentToken)
        ->getJson('/api/v1/user')
        ->assertOk();
});

test('the current password must be correct', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');
});

test('changing the password requires authentication', function () {
    $this->putJson('/api/v1/user/password', [
        'current_password' => 'password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertUnauthorized();
});

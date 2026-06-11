<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function seedResetCode(string $email, string $code, ?DateTimeInterface $createdAt = null): void
{
    DB::table('password_reset_tokens')->updateOrInsert(
        ['email' => $email],
        ['token' => Hash::make($code), 'created_at' => $createdAt ?? now()],
    );
}

test('a valid code resets the password and revokes all tokens', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $user->createToken('api');
    $user->createToken('cli');

    seedResetCode('test@example.com', '123456');

    $this->postJson('/api/v1/reset-password', [
        'email' => 'test@example.com',
        'code' => '123456',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Password has been reset.');

    expect(Hash::check('new-secret-password', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('password_reset_tokens')->where('email', 'test@example.com')->exists())->toBeFalse()
        ->and($user->tokens()->count())->toBe(0);
});

test('a wrong code is rejected', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    seedResetCode('test@example.com', '123456');

    $this->postJson('/api/v1/reset-password', [
        'email' => 'test@example.com',
        'code' => '654321',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect(Hash::check('new-secret-password', $user->fresh()->password))->toBeFalse();
});

test('an expired code is rejected and deleted', function () {
    User::factory()->create(['email' => 'test@example.com']);

    seedResetCode('test@example.com', '123456', now()->subMinutes(16));

    $this->postJson('/api/v1/reset-password', [
        'email' => 'test@example.com',
        'code' => '123456',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect(DB::table('password_reset_tokens')->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('a code cannot be used twice', function () {
    User::factory()->create(['email' => 'test@example.com']);

    seedResetCode('test@example.com', '123456');

    $payload = [
        'email' => 'test@example.com',
        'code' => '123456',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ];

    $this->postJson('/api/v1/reset-password', $payload)->assertOk();
    $this->postJson('/api/v1/reset-password', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

test('an email without a pending code is rejected with the same error', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $this->postJson('/api/v1/reset-password', [
        'email' => 'test@example.com',
        'code' => '123456',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

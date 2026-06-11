<?php

use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('a reset code is emailed and stored hashed', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);

    $this->postJson('/api/v1/forgot-password', ['email' => 'test@example.com'])
        ->assertOk()
        ->assertJsonPath('message', 'If the email exists, a reset code has been sent.');

    $row = DB::table('password_reset_tokens')->where('email', 'test@example.com')->first();

    expect($row)->not->toBeNull();

    Notification::assertSentTo($user, PasswordResetCode::class, function (PasswordResetCode $notification) use ($row) {
        return strlen($notification->code) === 6
            && Hash::check($notification->code, $row->token);
    });
});

test('an unknown email gets the same response and no notification', function () {
    Notification::fake();

    $this->postJson('/api/v1/forgot-password', ['email' => 'missing@example.com'])
        ->assertOk()
        ->assertJsonPath('message', 'If the email exists, a reset code has been sent.');

    Notification::assertNothingSent();

    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

test('requesting a new code replaces the previous one', function () {
    Notification::fake();

    User::factory()->create(['email' => 'test@example.com']);

    $this->postJson('/api/v1/forgot-password', ['email' => 'test@example.com'])->assertOk();
    $this->postJson('/api/v1/forgot-password', ['email' => 'test@example.com'])->assertOk();

    expect(DB::table('password_reset_tokens')->where('email', 'test@example.com')->count())->toBe(1);
});

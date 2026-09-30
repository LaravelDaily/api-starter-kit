<?php

namespace App\Actions\V1;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Random\RandomException;

class ForgotPasswordAction
{
    /**
     * Email a short-lived reset code to the given address. Does nothing when
     * the email has no account, so callers cannot probe for registered emails.
     *
     * @throws RandomException
     */
    public function handle(string $email): void
    {
        $email = Str::lower($email);

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($code), 'created_at' => now()],
        );

        $user->notify(new PasswordResetCode($code));
    }
}

<?php

namespace App\Actions\V1;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResetPasswordAction
{
    /**
     * Set a new password using the emailed reset code, then revoke every
     * token the user holds.
     *
     * @param  array{email: string, code: string, password: string}  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data): void
    {
        $email = Str::lower($data['email']);

        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $row) {
            $this->throwInvalidResetCode();
        }

        $expiresAt = Carbon::parse($row->created_at)
            ->addMinutes(config('auth.passwords.users.expire'));

        if ($expiresAt->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            $this->throwInvalidResetCode();
        }

        if (! Hash::check($data['code'], $row->token)) {
            $this->throwInvalidResetCode();
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->throwInvalidResetCode();
        }

        $user->update(['password' => Hash::make($data['password'])]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        $user->tokens()->delete();

        event(new PasswordReset($user));
    }

    /**
     * Throw the generic invalid-code error used for every reset failure, so
     * callers cannot probe which emails have pending codes.
     */
    protected function throwInvalidResetCode(): never
    {
        throw ValidationException::withMessages([
            'code' => [__('The reset code is invalid or has expired.')],
        ]);
    }
}

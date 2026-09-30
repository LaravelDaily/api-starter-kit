<?php

namespace App\Actions\V1;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    /**
     * Verify the credentials and issue an API token.
     *
     * @param  array{email: string, password: string, device_name?: string|null}  $data
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function handle(array $data): array
    {
        $user = User::query()
            ->where('email', Str::lower($data['email']))
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'api');

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
        ];
    }
}

<?php

namespace App\Actions\V1;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;

class RegisterAction
{
    /**
     * Create the user, fire the Registered event, and issue an API token.
     *
     * @param  array{name: string, email: string, password: string, device_name?: string|null}  $data
     * @return array{user: User, token: string}
     */
    public function handle(array $data): array
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        event(new Registered($user));

        $token = $user->createToken($data['device_name'] ?? 'api');

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
        ];
    }
}

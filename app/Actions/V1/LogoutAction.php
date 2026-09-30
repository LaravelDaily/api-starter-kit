<?php

namespace App\Actions\V1;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutAction
{
    /**
     * Revoke the token the user authenticated the current request with.
     */
    public function handle(User $user): void
    {
        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        $token->delete();
    }
}

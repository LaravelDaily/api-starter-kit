<?php

namespace App\Actions\V1;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class RevokeOtherTokensAction
{
    /**
     * Revoke every token the user holds except the one used for the
     * current request.
     */
    public function handle(User $user): void
    {
        /** @var PersonalAccessToken $currentToken */
        $currentToken = $user->currentAccessToken();

        $user->tokens()->where('id', '!=', $currentToken->id)->delete();
    }
}

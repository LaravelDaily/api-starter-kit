<?php

namespace App\Actions\V1;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdatePasswordAction
{
    public function __construct(
        private readonly RevokeOtherTokensAction $revokeOtherTokens,
    ) {}

    /**
     * Change the user's password and revoke every token except the current one.
     */
    public function handle(User $user, string $password): void
    {
        $user->update(['password' => Hash::make($password)]);

        $this->revokeOtherTokens->handle($user);
    }
}

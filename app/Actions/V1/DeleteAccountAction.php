<?php

namespace App\Actions\V1;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteAccountAction
{
    /**
     * Permanently delete the user and revoke all of their tokens. Runs in a
     * transaction so a failed delete does not leave the account without tokens.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();

            $user->delete();
        });
    }
}

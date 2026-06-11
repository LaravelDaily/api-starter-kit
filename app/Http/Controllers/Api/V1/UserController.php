<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteAccountRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class UserController extends Controller
{
    /**
     * Get Current User
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Update Profile
     *
     * Update the authenticated user's name and/or email.
     */
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();

        $user->update($request->validated());

        return new UserResource($user);
    }

    /**
     * Update Password
     *
     * Change the password and revoke every token except the current one.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update(['password' => Hash::make($request->validated('password'))]);

        /** @var PersonalAccessToken $currentToken */
        $currentToken = $user->currentAccessToken();

        $user->tokens()->where('id', '!=', $currentToken->id)->delete();

        return response()->json([
            'message' => __('Password updated.'),
        ]);
    }

    /**
     * Delete Account
     *
     * Permanently delete the account and revoke all of its tokens.
     */
    public function destroy(DeleteAccountRequest $request): Response
    {
        $user = $request->user();

        $user->tokens()->delete();

        $user->delete();

        return response()->noContent();
    }
}

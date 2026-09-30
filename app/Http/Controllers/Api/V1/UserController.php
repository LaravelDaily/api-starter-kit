<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\V1\DeleteAccountAction;
use App\Actions\V1\UpdatePasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteAccountRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
    public function updatePassword(UpdatePasswordRequest $request, UpdatePasswordAction $action): JsonResponse
    {
        $action->handle($request->user(), $request->validated('password'));

        return response()->json([
            'message' => __('Password updated.'),
        ]);
    }

    /**
     * Delete Account
     *
     * Permanently delete the account and revoke all of its tokens.
     */
    public function destroy(DeleteAccountRequest $request, DeleteAccountAction $action): Response
    {
        $action->handle($request->user());

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\V1\ForgotPasswordAction;
use App\Actions\V1\LoginAction;
use App\Actions\V1\LogoutAction;
use App\Actions\V1\RegisterAction;
use App\Actions\V1\ResetPasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Random\RandomException;

class AuthController extends Controller
{
    /**
     * Register
     *
     * Create a new user account and issue an API token.
     */
    public function register(RegisterRequest $request, RegisterAction $action): JsonResponse
    {
        $response = $action->handle($request->validated());

        return response()->json([
            'token' => $response['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($response['user']),
        ], 201);
    }

    /**
     * Login
     *
     * Verify credentials and issue an API token.
     */
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $response = $action->handle($request->validated());

        return response()->json([
            'token' => $response['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($response['user']),
        ]);
    }

    /**
     * Logout
     *
     * Revoke the token that was used to authenticate the current request.
     */
    public function logout(Request $request, LogoutAction $action): Response
    {
        $action->handle($request->user());

        return response()->noContent();
    }

    /**
     * Forgot Password
     *
     * Email a short-lived reset code to the given address. The response is
     * identical whether or not the email belongs to an account.
     *
     * @throws RandomException
     */
    public function forgotPassword(ForgotPasswordRequest $request, ForgotPasswordAction $action): JsonResponse
    {
        $action->handle($request->validated('email'));

        return response()->json([
            'message' => __('If the email exists, a reset code has been sent.'),
        ]);
    }

    /**
     * Reset Password
     *
     * Set a new password using the emailed reset code, then revoke every
     * token the user holds.
     */
    public function resetPassword(ResetPasswordRequest $request, ResetPasswordAction $action): JsonResponse
    {
        $action->handle($request->validated());

        return response()->json([
            'message' => __('Password has been reset.'),
        ]);
    }
}

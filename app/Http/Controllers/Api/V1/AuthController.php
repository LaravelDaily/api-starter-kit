<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Register
     *
     * Create a new user account and issue an API token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);

        event(new Registered($user));

        $token = $user->createToken($request->validated('device_name') ?? 'api');

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Login
     *
     * Verify credentials and issue an API token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', Str::lower($request->validated('email')))
            ->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken($request->validated('device_name') ?? 'api');

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Logout
     *
     * Revoke the token that was used to authenticate the current request.
     */
    public function logout(Request $request): Response
    {
        /** @var PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();

        $token->delete();

        return response()->noContent();
    }

    /**
     * Forgot Password
     *
     * Email a short-lived reset code to the given address. The response is
     * identical whether or not the email belongs to an account.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = Str::lower($request->validated('email'));

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($code), 'created_at' => now()],
            );

            $user->notify(new PasswordResetCode($code));
        }

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
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $email = Str::lower($request->validated('email'));

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

        if (! Hash::check($request->validated('code'), $row->token)) {
            $this->throwInvalidResetCode();
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->throwInvalidResetCode();
        }

        $user->update(['password' => Hash::make($request->validated('password'))]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        $user->tokens()->delete();

        event(new PasswordReset($user));

        return response()->json([
            'message' => __('Password has been reset.'),
        ]);
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

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TokenResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    /**
     * List Tokens
     *
     * List the authenticated user's active API tokens.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return TokenResource::collection($request->user()->tokens()->latest()->get());
    }

    /**
     * Revoke Token
     *
     * Revoke a single token by ID. Returns 404 when the token does not
     * exist or belongs to another user.
     */
    public function destroy(Request $request, int $id): Response
    {
        $token = $request->user()->tokens()->findOrFail($id);

        $token->delete();

        return response()->noContent();
    }

    /**
     * Revoke Other Tokens
     *
     * Revoke every token except the one used for the current request.
     */
    public function destroyOthers(Request $request): Response
    {
        /** @var PersonalAccessToken $currentToken */
        $currentToken = $request->user()->currentAccessToken();

        $request->user()->tokens()->where('id', '!=', $currentToken->id)->delete();

        return response()->noContent();
    }
}

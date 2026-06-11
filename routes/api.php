<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:api')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
    });

    Route::middleware('throttle:login')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::middleware('throttle:password')->group(function () {
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/user', [UserController::class, 'show']);
        Route::put('/user', [UserController::class, 'update']);
        Route::put('/user/password', [UserController::class, 'updatePassword']);
        Route::delete('/user', [UserController::class, 'destroy']);

        Route::get('/tokens', [TokenController::class, 'index']);
        Route::delete('/tokens', [TokenController::class, 'destroyOthers']);
        Route::delete('/tokens/{id}', [TokenController::class, 'destroy']);
    });
});

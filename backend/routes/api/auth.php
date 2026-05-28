<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Middleware\RegistrationEnabled;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register'])->middleware(['throttle:10,1', RegistrationEnabled::class]);
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/password/reset', [AuthController::class, 'resetPassword']);
    Route::post('/password/set', [AuthController::class, 'setPassword'])->middleware('throttle:5,1');
    Route::get('/registration-status', [AuthController::class, 'registrationStatus']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware('signed')
            ->name('verification.verify');
        Route::post('/email/verify/resend', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:6,1');
    });
});

<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:admin|super-admin'])->prefix('admin')->group(function (): void {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);

    Route::get('/users', [AdminController::class, 'users']);
    Route::post('/users', [AdminController::class, 'store']);
    Route::get('/users/{user}', [AdminController::class, 'show']);
    Route::put('/users/{user}', [AdminController::class, 'updateUser']);
    Route::delete('/users/{user}', [AdminController::class, 'destroy']);
    Route::patch('/users/{user}/role', [AdminController::class, 'updateRole']);
    Route::patch('/users/{user}/enable', [AdminController::class, 'enable']);
    Route::patch('/users/{user}/disable', [AdminController::class, 'disable']);
    Route::post('/users/{user}/resend-welcome', [AdminController::class, 'resendWelcome']);

    Route::post('/impersonate/leave', [AdminController::class, 'leaveImpersonation']);
    Route::post('/impersonate/{user}', [AdminController::class, 'impersonate']);
});

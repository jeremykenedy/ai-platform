<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:admin|super-admin'])->group(function (): void {
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::put('/admin/users/{user}', [AdminController::class, 'updateUser']);
    Route::post('/admin/users/invite', [AdminController::class, 'invite']);
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
});

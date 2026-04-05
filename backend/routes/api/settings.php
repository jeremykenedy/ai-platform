<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\MemoryController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'update']);

    Route::get('/settings/memories', [MemoryController::class, 'index']);
    Route::post('/settings/memories', [MemoryController::class, 'store']);
    Route::put('/settings/memories/{memory}', [MemoryController::class, 'update']);
    Route::delete('/settings/memories/{memory}', [MemoryController::class, 'destroy']);
    Route::post('/settings/memories/bulk-delete', [MemoryController::class, 'bulkDestroy']);
    Route::post('/settings/memories/conflicts/{conflict}/resolve', [MemoryController::class, 'resolveConflict']);
});

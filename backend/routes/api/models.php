<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ModelController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/models', [ModelController::class, 'index']);
    Route::get('/models/running', [ModelController::class, 'running']);
    Route::get('/models/{model}', [ModelController::class, 'show']);
    Route::post('/models/pull', [ModelController::class, 'pull'])->middleware('throttle:5,60');
    Route::delete('/models/{model}', [ModelController::class, 'destroy']);
});

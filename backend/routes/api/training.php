<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\TrainingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/training/datasets', [TrainingController::class, 'datasetsIndex']);
    Route::post('/training/datasets', [TrainingController::class, 'datasetsStore']);
    Route::delete('/training/datasets/{dataset}', [TrainingController::class, 'datasetsDestroy']);

    Route::get('/training/jobs', [TrainingController::class, 'jobsIndex']);
    Route::post('/training/jobs', [TrainingController::class, 'jobsStore']);
    Route::get('/training/jobs/{job}', [TrainingController::class, 'jobsShow']);
    Route::post('/training/jobs/{job}/cancel', [TrainingController::class, 'jobsCancel']);
});

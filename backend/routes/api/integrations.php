<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\IntegrationController;
use Illuminate\Support\Facades\Route;

// OAuth callback requires no auth (provider redirects here)
Route::get('/integrations/{provider}/callback', [IntegrationController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/integrations', [IntegrationController::class, 'index']);
    Route::post('/integrations/connect', [IntegrationController::class, 'connect']);
    Route::delete('/integrations/{integrationName}/disconnect', [IntegrationController::class, 'disconnect']);
    Route::post('/integrations/tools/execute', [IntegrationController::class, 'executeTools']);
});

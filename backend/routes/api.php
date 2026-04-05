<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    require __DIR__.'/api/auth.php';
    require __DIR__.'/api/conversations.php';
    require __DIR__.'/api/models.php';
    require __DIR__.'/api/personas.php';
    require __DIR__.'/api/projects.php';
    require __DIR__.'/api/training.php';
    require __DIR__.'/api/integrations.php';
    require __DIR__.'/api/settings.php';
    require __DIR__.'/api/admin.php';
});

// Health check outside v1 prefix, no auth
Route::get('/health', HealthController::class);

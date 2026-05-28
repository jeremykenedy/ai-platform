<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Named login route so Laravel's default AuthenticationException
// redirect (redirect(route('login'))) does not throw RouteNotFoundException.
// Returns the SPA shell for browser requests, JSON 401 for API/JSON.
Route::get('/login', function (Request $request) {
    if ($request->is('api/*') || $request->expectsJson()) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    return view('app');
})->name('login');

Route::get('/{any?}', function (): View {
    return view('app');
})->where('any', '.*');

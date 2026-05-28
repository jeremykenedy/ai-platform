<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('auth.registration_open', false)) {
            abort(403, 'Registration is currently disabled.');
        }

        return $next($request);
    }
}

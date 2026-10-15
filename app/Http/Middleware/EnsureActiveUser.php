<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_active, 403, 'Sua conta está inativa. Apenas o acesso público está disponível.');

        return $next($request);
    }
}

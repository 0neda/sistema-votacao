<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Round;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AuditDeniedAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if ($request->user() && ! $request->user()->is_active && ! $request->routeIs('home', 'logout', 'login', 'login.store', 'register', 'register.store', 'password.*', 'verification.*')) {
                abort(403, 'Sua conta está inativa. Apenas o acesso público está disponível.');
            }
            $response = $next($request);
            if ($response->getStatusCode() === 403) {
                $this->record($request);
            }

            return $response;
        } catch (AuthorizationException|HttpExceptionInterface $exception) {
            $status = $exception instanceof AuthorizationException ? ($exception->status() ?? 403) : $exception->getStatusCode();
            if ($status === 403) {
                $this->record($request);
            }
            throw $exception;
        }
    }

    private function record(Request $request): void
    {
        AuditLog::record('ACESSO_NEGADO', 'route', result: 'rejeitado', metadata: ['route' => $request->route()?->getName() ?? 'desconhecida']);
        if ($request->routeIs('votes.store')) {
            $round = $request->route('round');
            AuditLog::record('VOTO_REJEITADO', 'round', $round instanceof Round ? $round->id : (int) $round, 'rejeitado', ['reason' => 'autorizacao']);
        }
    }
}

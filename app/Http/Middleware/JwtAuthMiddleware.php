<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Library\Adapters\Security\JwtTokenIssuer;

final class JwtAuthMiddleware
{
    private JwtTokenIssuer $tokenIssuer;

    public function __construct(JwtTokenIssuer $tokenIssuer)
    {
        $this->tokenIssuer = $tokenIssuer;
    }

    public function handle(Request $request, Closure $next, ?string $requiredRole = null)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'error' => 'UNAUTHORIZED',
                'message' => 'Token no proporcionado',
            ], 401);
        }

        $payload = $this->tokenIssuer->verify($token);

        if (!$payload) {
            return response()->json([
                'error' => 'UNAUTHORIZED',
                'message' => 'Token inválido',
            ], 401);
        }

        // Check role if required
        if ($requiredRole && $payload['role'] !== $requiredRole) {
            return response()->json([
                'error' => 'FORBIDDEN',
                'message' => 'Acceso denegado',
            ], 403);
        }

        // Attach to request
        $request->attributes->set('user_id', $payload['user_id']);
        $request->attributes->set('role', $payload['role']);

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, $role): Response
    {
        // Si el usuario está logueado y su rol coincide con el que pedimos, lo deja pasar
        if (auth()->check() && auth()->user()->role === $role) {
            return $next($request);
        }

        // Si es cajera intentando entrar a zona de admin, la bloqueamos
        abort(403, 'ACCESO DENEGADO: No tienes permisos de Administrador para realizar esta acción.');
    }
}

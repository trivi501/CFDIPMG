<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mientras el usuario siga con la contraseña genérica con la que se le dio de alta, lo único
 * que puede hacer es cambiarla (o cerrar sesión).
 */
class RequireCambioPassword
{
    /**
     * @var list<string>
     */
    public const RUTAS_PERMITIDAS = [
        'password.cambiar',
        'password.cambiar.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! $request->routeIs(...self::RUTAS_PERMITIDAS)) {
            return redirect()->route('password.cambiar');
        }

        return $next($request);
    }
}

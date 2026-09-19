<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Cargar datos y editar parametros cambia lo que ve toda la empresa: solo administradores. */
class SoloAdministrador
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->esAdministrador()) {
            abort(403, 'Esta seccion es solo para administradores del tablero.');
        }

        return $next($request);
    }
}

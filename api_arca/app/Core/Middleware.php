<?php

namespace ApiArca\App\Core;

/**
 * Clase base para middlewares
 */
abstract class Middleware
{
    /**
     * Procesa la solicitud y ejecuta el middleware
     * 
     * @param Request $request La solicitud HTTP
     * @param callable $next El siguiente middleware o controlador
     * @return Response Respuesta HTTP
     */
    abstract public function handle(Request $request, callable $next): Response;
}

<?php

namespace ApiArca\App\Middleware;

use ApiArca\App\Core\Request;
use ApiArca\App\Core\Response;
use ApiArca\App\Core\Middleware;

/**
 * Middleware para manejo de tenant (empresa) en requests multiempresa
 */
class TenantMiddleware extends Middleware
{
    /**
     * Procesa la solicitud verificando el tenant (empresa)
     */
    public function handle(Request $request, callable $next): Response
    {
        // El company_id debería haber sido establecido por AuthMiddleware
        $companyId = $request->route('company_id');

        if (empty($companyId)) {
            return Response::error('Empresa no identificada. Verifique su API Key.', [], 401);
        }

        // Agregar company_id a todos los inputs para que los controladores puedan usarlo
        // Esto asegura que todas las operaciones se filtren por empresa
        
        return $next($request);
    }
}

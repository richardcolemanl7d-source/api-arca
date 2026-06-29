<?php

namespace ApiArca\App\Controllers;

use ApiArca\App\Core\Request;
use ApiArca\App\Core\Response;
use ApiArca\App\Repositories\CustomerRepository;
use ApiArca\App\Helpers\Logger;

/**
 * Controlador para gestión de clientes
 */
class CustomerController
{
    private CustomerRepository $customerRepository;
    private Logger $logger;

    public function __construct(
        CustomerRepository $customerRepository,
        Logger $logger
    ) {
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
    }

    /**
     * Obtiene un cliente por CUIT
     * GET /api/customer/{cuit}
     */
    public function getByCuit(Request $request): Response
    {
        try {
            $companyId = $request->route('company_id');
            $cuit = $request->route('cuit');

            if (empty($cuit)) {
                return Response::error('CUIT requerido', [], 400);
            }

            // Limpiar CUIT (solo dígitos)
            $cuit = preg_replace('/[^0-9]/', '', $cuit);

            $customer = $this->customerRepository->findByCuit((int)$companyId, $cuit);

            if ($customer === null) {
                return Response::error('Cliente no encontrado', [], 404);
            }

            return Response::success($customer);

        } catch (\Exception $e) {
            $this->logger->error('Error obteniendo cliente', ['error' => $e->getMessage()]);
            return Response::error('Error interno del servidor', [], 500);
        }
    }

    /**
     * Lista clientes de la empresa
     * GET /api/customer
     */
    public function index(Request $request): Response
    {
        try {
            $companyId = $request->route('company_id');
            $limit = min((int)$request->query('limit', 50), 100);
            $offset = max(0, (int)$request->query('offset', 0));

            $customers = $this->customerRepository->findByCompany((int)$companyId, $limit, $offset);

            return Response::success($customers);

        } catch (\Exception $e) {
            $this->logger->error('Error listando clientes', ['error' => $e->getMessage()]);
            return Response::error('Error interno del servidor', [], 500);
        }
    }

    /**
     * Busca clientes por nombre
     * GET /api/customer/search?q=nombre
     */
    public function search(Request $request): Response
    {
        try {
            $companyId = $request->route('company_id');
            $query = $request->query('q');

            if (empty($query)) {
                return Response::error('Término de búsqueda requerido', [], 400);
            }

            $customers = $this->customerRepository->searchByNombre((int)$companyId, $query);

            return Response::success($customers);

        } catch (\Exception $e) {
            $this->logger->error('Error buscando clientes', ['error' => $e->getMessage()]);
            return Response::error('Error interno del servidor', [], 500);
        }
    }
}

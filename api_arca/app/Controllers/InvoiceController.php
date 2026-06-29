<?php

namespace ApiArca\App\Controllers;

use ApiArca\App\Core\Request;
use ApiArca\App\Core\Response;
use ApiArca\App\Repositories\InvoiceRepository;
use ApiArca\App\Services\InvoiceService;
use ApiArca\App\Helpers\Validator;
use ApiArca\App\Helpers\Logger;

/**
 * Controlador para gestión de facturas electrónicas
 */
class InvoiceController
{
    private InvoiceRepository $invoiceRepository;
    private InvoiceService $invoiceService;
    private Logger $logger;

    public function __construct(
        InvoiceRepository $invoiceRepository,
        InvoiceService $invoiceService,
        Logger $logger
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->invoiceService = $invoiceService;
        $this->logger = $logger;
    }

    /**
     * Emite una factura electrónica
     * POST /api/invoice
     */
    public function store(Request $request): Response
    {
        try {
            $companyId = $request->route('company_id');
            $data = $request->allInput();

            // Validar datos mínimos requeridos
            $validator = new Validator($data, [
                'tipo_comprobante' => 'required|integer|min:1|max:99',
                'punto_venta' => 'required|integer|min:1|max:9999',
                'cliente_cuit' => 'required|cuit',
                'cliente_razon_social' => 'required|string|max:255',
                'total' => 'required|numeric|min:0.01',
            ]);

            if (!$validator->validate()) {
                return Response::error('Datos inválidos', $validator->getErrors(), 400);
            }

            // Emitir factura a través del servicio
            $result = $this->invoiceService->emitirFactura($companyId, $data);

            $this->logger->info('Factura emitida', [
                'company_id' => $companyId,
                'invoice_id' => $result['id'],
                'cae' => $result['cae'] ?? null
            ]);

            return Response::success($result, 'Factura emitida exitosamente', 201);

        } catch (\Exception $e) {
            $this->logger->error('Error emitiendo factura', ['error' => $e->getMessage()]);
            return Response::error('Error al emitir factura: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Emite una nota de crédito
     * POST /api/credit-note
     */
    public function creditNote(Request $request): Response
    {
        try {
            $companyId = $request->route('company_id');
            $data = $request->allInput();

            // Validar datos mínimos requeridos
            $validator = new Validator($data, [
                'punto_venta' => 'required|integer|min:1|max:9999',
                'cliente_cuit' => 'required|cuit',
                'cliente_razon_social' => 'required|string|max:255',
                'total' => 'required|numeric|min:0.01',
                'comprobante_referencia' => 'required|array',
                'comprobante_referencia.tipo' => 'required|integer',
                'comprobante_referencia.punto_venta' => 'required|integer',
                'comprobante_referencia.numero' => 'required|integer',
            ]);

            if (!$validator->validate()) {
                return Response::error('Datos inválidos', $validator->getErrors(), 400);
            }

            // Emitir nota de crédito
            $result = $this->invoiceService->emitirNC($companyId, $data);

            $this->logger->info('Nota de crédito emitida', [
                'company_id' => $companyId,
                'invoice_id' => $result['id'],
                'cae' => $result['cae'] ?? null
            ]);

            return Response::success($result, 'Nota de crédito emitida exitosamente', 201);

        } catch (\Exception $e) {
            $this->logger->error('Error emitiendo nota de crédito', ['error' => $e->getMessage()]);
            return Response::error('Error al emitir nota de crédito: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Obtiene una factura por ID
     * GET /api/invoice/{id}
     */
    public function show(Request $request): Response
    {
        try {
            $invoiceId = $request->route('id');
            
            if (empty($invoiceId)) {
                return Response::error('ID de comprobante requerido', [], 400);
            }

            $invoice = $this->invoiceRepository->findById((int)$invoiceId);
            
            if ($invoice === null) {
                return Response::error('Comprobante no encontrado', [], 404);
            }

            return Response::success($invoice);

        } catch (\Exception $e) {
            $this->logger->error('Error obteniendo factura', ['error' => $e->getMessage()]);
            return Response::error('Error interno del servidor', [], 500);
        }
    }

    /**
     * Consulta el estado de un comprobante en ARCA
     * GET /api/invoice/{id}/status
     */
    public function status(Request $request): Response
    {
        try {
            $invoiceId = $request->route('id');
            
            if (empty($invoiceId)) {
                return Response::error('ID de comprobante requerido', [], 400);
            }

            $invoice = $this->invoiceRepository->findById((int)$invoiceId);
            
            if ($invoice === null) {
                return Response::error('Comprobante no encontrado', [], 404);
            }

            // Consultar estado en ARCA
            $status = $this->invoiceService->consultarComprobante(
                (int)$invoice['company_id'],
                $invoice['tipo_comprobante'],
                $invoice['punto_venta'],
                $invoice['numero_comprobante']
            );

            return Response::success($status);

        } catch (\Exception $e) {
            $this->logger->error('Error consultando estado', ['error' => $e->getMessage()]);
            return Response::error('Error al consultar estado: ' . $e->getMessage(), [], 500);
        }
    }
}

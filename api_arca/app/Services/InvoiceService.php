<?php

namespace ApiArca\App\Services;

use ApiArca\App\Repositories\InvoiceRepository;
use ApiArca\App\Repositories\CustomerRepository;
use ApiArca\App\Helpers\Logger;
use ApiArca\App\DTO\InvoiceDTO;

/**
 * Servicio para gestión de facturas electrónicas ARCA/AFIP
 */
class InvoiceService
{
    private InvoiceRepository $invoiceRepository;
    private CustomerRepository $customerRepository;
    private WsfeService $wsfeService;
    private TokenService $tokenService;
    private Logger $logger;

    public function __construct(
        InvoiceRepository $invoiceRepository,
        CustomerRepository $customerRepository,
        WsfeService $wsfeService,
        TokenService $tokenService,
        Logger $logger
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->customerRepository = $customerRepository;
        $this->wsfeService = $wsfeService;
        $this->tokenService = $tokenService;
        $this->logger = $logger;
    }

    /**
     * Emite una factura electrónica
     * 
     * @param int $companyId ID de la empresa
     * @param array $data Datos de la factura
     * @return array Resultado con CAE
     */
    public function emitirFactura(int $companyId, array $data): array
    {
        // Validar cliente
        $cliente = $this->validarCliente($companyId, $data);

        // Obtener último comprobante autorizado
        $ultimoComprobante = $this->wsfeService->FECompUltimoAutorizado(
            $companyId,
            $data['punto_venta'],
            $data['tipo_comprobante'] ?? 1
        );

        // Preparar datos para ARCA
        $feRequest = $this->prepararFERequest($companyId, $data, $cliente, $ultimoComprobante + 1);

        // Solicitar CAE
        $response = $this->wsfeService->FECAESolicitar($companyId, $feRequest);

        // Guardar factura en BD
        $invoiceData = [
            'company_id' => $companyId,
            'customer_id' => $cliente['id'] ?? null,
            'tipo_comprobante' => $data['tipo_comprobante'] ?? 1,
            'punto_venta' => $data['punto_venta'],
            'numero_comprobante' => $ultimoComprobante + 1,
            'cae' => $response['cae'] ?? null,
            'cae_fecavto' => isset($response['cae_fecavto']) ? new \DateTime($response['cae_fecavto']) : null,
            'estado' => ($response['resultado'] === 'A') ? 'APPROVED' : 'REJECTED',
            'total' => $data['total'],
            'moneda' => $data['moneda'] ?? 'PES',
            'cotizacion' => $data['cotizacion'] ?? 1.0,
            'observaciones' => $data['observaciones'] ?? null,
            'xml_request' => $feRequest['xml'] ?? null,
            'xml_response' => $response['xml'] ?? null,
        ];

        $invoiceId = $this->guardarCAE($invoiceData);

        return [
            'id' => $invoiceId,
            'cae' => $response['cae'] ?? null,
            'cae_fecavto' => $response['cae_fecavto'] ?? null,
            'resultado' => $response['resultado'] ?? 'R',
            'observaciones' => $response['observaciones'] ?? [],
            'numero_comprobante' => $ultimoComprobante + 1,
        ];
    }

    /**
     * Emite una nota de crédito
     */
    public function emitirNC(int $companyId, array $data): array
    {
        $data['tipo_comprobante'] = 3; // Nota de Crédito A
        return $this->emitirFactura($companyId, $data);
    }

    /**
     * Emite una nota de débito
     */
    public function emitirND(int $companyId, array $data): array
    {
        $data['tipo_comprobante'] = 2; // Nota de Débito A
        return $this->emitirFactura($companyId, $data);
    }

    /**
     * Valida o crea un cliente
     */
    public function validarCliente(int $companyId, array $data): array
    {
        $cuit = preg_replace('/[^0-9]/', '', $data['cliente_cuit']);
        
        // Buscar cliente existente
        $cliente = $this->customerRepository->findByCuit($companyId, $cuit);

        if ($cliente !== null) {
            return $cliente;
        }

        // Crear nuevo cliente
        $clienteData = [
            'company_id' => $companyId,
            'cuit' => $cuit,
            'razon_social' => $data['cliente_razon_social'],
            'tipo_documento' => $data['cliente_tipo_documento'] ?? 96,
            'condicion_iva' => $data['cliente_condicion_iva'] ?? 5,
            'domicilio' => $data['cliente_domicilio'] ?? null,
            'localidad' => $data['cliente_localidad'] ?? null,
            'provincia' => $data['cliente_provincia'] ?? null,
            'codigo_postal' => $data['cliente_codigo_postal'] ?? null,
            'email' => $data['cliente_email'] ?? null,
            'telefono' => $data['cliente_telefono'] ?? null,
        ];

        $customerId = $this->customerRepository->create($clienteData);
        
        return array_merge(['id' => $customerId], $clienteData);
    }

    /**
     * Prepara el request para ARCA
     */
    private function prepararFERequest(int $companyId, array $data, array $cliente, int $numero): array
    {
        $fechaHoy = date('Ymd');

        return [
            'cbte_tipo' => $data['tipo_comprobante'] ?? 1,
            'pto_vta' => $data['punto_venta'],
            'cbte_desde' => $numero,
            'cbte_hasta' => $numero,
            'concepto' => $data['concepto'] ?? 1,
            'doc_tipo' => $cliente['tipo_documento'] ?? 96,
            'doc_nro' => $cuit,
            'cbte_fch' => $fechaHoy,
            'imp_total' => $data['total'],
            'imp_tot_conc' => $data['imp_tot_conc'] ?? 0,
            'imp_neto' => $data['imp_neto'] ?? 0,
            'imp_iva' => $data['imp_iva'] ?? 0,
            'imp_trib' => $data['imp_trib'] ?? 0,
            'moneda' => $data['moneda'] ?? 'PES',
            'mon_ctz' => $data['cotizacion'] ?? 1.0,
            'detalles' => $data['detalles'] ?? [],
            'ivae' => $data['ivae'] ?? [],
            'tributos' => $data['tributos'] ?? [],
        ];
    }

    /**
     * Guarda el CAE en la base de datos
     */
    public function guardarCAE(array $data): int
    {
        return $this->invoiceRepository->create($data);
    }

    /**
     * Consulta un comprobante en ARCA
     */
    public function consultarComprobante(int $companyId, int $tipoComprobante, int $puntoVenta, int $numero): array
    {
        return $this->wsfeService->consultarComprobante(
            $companyId,
            $tipoComprobante,
            $puntoVenta,
            $numero
        );
    }
}

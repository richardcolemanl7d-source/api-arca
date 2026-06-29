<?php

namespace ApiArca\App\Services;

use ApiArca\App\Helpers\Logger;
use ApiArca\App\Core\Config;

/**
 * Servicio WSFE - Web Services Facturación Electrónica
 * Maneja la comunicación con ARCA/AFIP para emisión de comprobantes
 */
class WsfeService
{
    private TokenService $tokenService;
    private Logger $logger;
    private string $wsfeUrl;
    private int $timeout;

    public function __construct(
        TokenService $tokenService,
        Logger $logger
    ) {
        $this->tokenService = $tokenService;
        $this->logger = $logger;
        
        $ambiente = env('ARCA_AMBIENTE', 'homologacion');
        // URLs de ejemplo - ajustar según documentación oficial de ARCA
        $this->wsfeUrl = $ambiente === 'production'
            ? 'https://servicios1.arca.gob.ar/servicio/api/wsfev2'
            : 'https://fwshomo.afip.gov.ar/ws/service/service.asmx?WSDL';
        $this->timeout = 30;
    }

    /**
     * Obtiene el último comprobante autorizado para un punto de venta y tipo
     * 
     * @param int $companyId ID de la empresa
     * @param int $puntoVenta Número de punto de venta
     * @param int $tipoComprobante Tipo de comprobante
     * @return int Número del último comprobante
     */
    public function FECompUltimoAutorizado(int $companyId, int $puntoVenta, int $tipoComprobante): int
    {
        try {
            // Obtener tokens de acceso
            $tokens = $this->tokenService->obtenerTA($companyId, 'wsfe');

            // Crear cliente SOAP
            $client = new \SoapClient($this->wsfeUrl, [
                'soap_version' => SOAP_1_2,
                'trace' => true,
                'connection_timeout' => $this->timeout,
            ]);

            // Preparar request
            $request = [
                'Auth' => [
                    'Token' => $tokens['access_token'],
                    'Sign' => $tokens['sign_token'],
                    'Cuit' => $this->getCompanyCuit($companyId),
                ],
                'PtoVta' => $puntoVenta,
                'CbteTipo' => $tipoComprobante,
            ];

            // Llamar al servicio
            $result = $client->FECompUltimoAutorizado(['FeCompUltimoAutorizadoReq' => $request]);

            return (int)($result->FECompUltimoAutorizadoResult->CbteNro ?? 0);

        } catch (\Exception $e) {
            $this->logger->error('Error en FECompUltimoAutorizado', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error consultando último comprobante: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Solicita CAE para un lote de comprobantes
     * 
     * @param int $companyId ID de la empresa
     * @param array $feRequest Datos del comprobante
     * @return array Resultado con CAE
     */
    public function FECAESolicitar(int $companyId, array $feRequest): array
    {
        try {
            // Obtener tokens de acceso
            $tokens = $this->tokenService->obtenerTA($companyId, 'wsfe');

            // Crear cliente SOAP
            $client = new \SoapClient($this->wsfeUrl, [
                'soap_version' => SOAP_1_2,
                'trace' => true,
                'connection_timeout' => $this->timeout,
            ]);

            // Preparar request completo
            $request = [
                'Auth' => [
                    'Token' => $tokens['access_token'],
                    'Sign' => $tokens['sign_token'],
                    'Cuit' => $this->getCompanyCuit($companyId),
                ],
                'FeCAEReq' => [
                    'FeCabReq' => [
                        'CantReg' => 1,
                        'PtoVta' => $feRequest['pto_vta'],
                        'CbteTipo' => $feRequest['cbte_tipo'],
                    ],
                    'FeDetReq' => [
                        'FECAEDetRequest' => [
                            'Concepto' => $feRequest['concepto'],
                            'DocTipo' => $feRequest['doc_tipo'],
                            'DocNro' => $feRequest['doc_nro'],
                            'CbteDesde' => $feRequest['cbte_desde'],
                            'CbteHasta' => $feRequest['cbte_hasta'],
                            'CbteFch' => $feRequest['cbte_fch'],
                            'ImpTotal' => $feRequest['imp_total'],
                            'ImpTotConc' => $feRequest['imp_tot_conc'],
                            'ImpNeto' => $feRequest['imp_neto'],
                            'ImpIva' => $feRequest['imp_iva'],
                            'ImpTrib' => $feRequest['imp_trib'],
                            'Moneda' => $feRequest['moneda'],
                            'MonCotiz' => $feRequest['mon_ctz'],
                        ]
                    ]
                ]
            ];

            // Agregar detalles si existen
            if (!empty($feRequest['detalles'])) {
                $request['FeCAEReq']['FeDetReq']['FECAEDetRequest']['ArrayItems'] = $feRequest['detalles'];
            }

            // Llamar al servicio
            $result = $client->FECAESolicitar(['FeCAEReq' => $request]);

            $responseResult = $result->FECAESolicitarResult ?? null;

            if ($responseResult === null) {
                throw new \RuntimeException('Respuesta vacía de ARCA');
            }

            // Extraer CAE y datos relevantes
            $cae = $responseResult->FeCabResp->CAE ?? null;
            $resultado = $responseResult->FeCabResp->Resultado ?? 'R';
            $observaciones = [];

            if (isset($responseResult->FeCabResp->Observaciones)) {
                foreach ($responseResult->FeCabResp->Observaciones as $obs) {
                    $observaciones[] = [
                        'codigo' => $obs->Code ?? null,
                        'mensaje' => $obs->Msg ?? null,
                    ];
                }
            }

            return [
                'cae' => $cae,
                'cae_fecavto' => $responseResult->FeCabResp->FchProceso ?? null,
                'resultado' => $resultado,
                'observaciones' => $observaciones,
                'xml' => $client->__getLastResponse() ?? null,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error en FECAESolicitar', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error solicitando CAE: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Consulta un comprobante ya emitido
     * 
     * @param int $companyId ID de la empresa
     * @param int $tipoComprobante Tipo de comprobante
     * @param int $puntoVenta Punto de venta
     * @param int $numero Número de comprobante
     * @return array Estado del comprobante
     */
    public function consultarComprobante(int $companyId, int $tipoComprobante, int $puntoVenta, int $numero): array
    {
        try {
            // Obtener tokens de acceso
            $tokens = $this->tokenService->obtenerTA($companyId, 'wsfe');

            // Crear cliente SOAP
            $client = new \SoapClient($this->wsfeUrl, [
                'soap_version' => SOAP_1_2,
                'trace' => true,
                'connection_timeout' => $this->timeout,
            ]);

            // Preparar request
            $request = [
                'Auth' => [
                    'Token' => $tokens['access_token'],
                    'Sign' => $tokens['sign_token'],
                    'Cuit' => $this->getCompanyCuit($companyId),
                ],
                'CbteTipo' => $tipoComprobante,
                'PtoVta' => $puntoVenta,
                'CbteNumero' => $numero,
            ];

            // Llamar al servicio
            $result = $client->FECompConsultar(['FeCompConsultarReq' => $request]);

            $responseResult = $result->FECompConsultarResult ?? null;

            return [
                'estado' => $responseResult->Estado ?? 'UNKNOWN',
                'cae' => $responseResult->CAE ?? null,
                'vencimiento_cae' => $responseResult->CAEFchVto ?? null,
                'emitido' => $responseResult->EmisionTipo ?? null,
                'xml' => $client->__getLastResponse() ?? null,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error consultando comprobante', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error consultando comprobante: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Obtiene el CUIT de una empresa
     */
    private function getCompanyCuit(int $companyId): string
    {
        // Esto debería venir de un repository o cache
        // Implementación simplificada
        static $cuitCache = [];
        
        if (!isset($cuitCache[$companyId])) {
            // En producción, obtener de CompanyRepository
            $cuitCache[$companyId] = env('ARCA_DEFAULT_CUIT', '20000000000');
        }
        
        return $cuitCache[$companyId];
    }
}

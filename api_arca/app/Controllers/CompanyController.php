<?php

namespace ApiArca\App\Controllers;

use ApiArca\App\Core\Request;
use ApiArca\App\Core\Response;
use ApiArca\App\Repositories\CompanyRepository;
use ApiArca\App\Repositories\ApiKeyRepository;
use ApiArca\App\Services\CertificateService;
use ApiArca\App\Helpers\Validator;
use ApiArca\App\Helpers\Logger;

/**
 * Controlador para gestión de empresas
 */
class CompanyController
{
    private CompanyRepository $companyRepository;
    private ApiKeyRepository $apiKeyRepository;
    private CertificateService $certificateService;
    private Logger $logger;

    public function __construct(
        CompanyRepository $companyRepository,
        ApiKeyRepository $apiKeyRepository,
        CertificateService $certificateService,
        Logger $logger
    ) {
        $this->companyRepository = $companyRepository;
        $this->apiKeyRepository = $apiKeyRepository;
        $this->certificateService = $certificateService;
        $this->logger = $logger;
    }

    /**
     * Crea una nueva empresa
     * POST /api/company
     */
    public function store(Request $request): Response
    {
        try {
            $data = $request->allInput();

            // Validar datos de entrada
            $validator = new Validator($data, [
                'cuit' => 'required|cuit',
                'razon_social' => 'required|string|max:255',
            ]);

            if (!$validator->validate()) {
                return Response::error('Datos inválidos', $validator->getErrors(), 400);
            }

            // Verificar que el CUIT no exista
            if ($this->companyRepository->existsByCuit($data['cuit'])) {
                return Response::error('Ya existe una empresa con ese CUIT', [], 409);
            }

            // Crear empresa sin certificado (se sube después)
            $companyId = $this->companyRepository->create([
                'cuit' => preg_replace('/[^0-9]/', '', $data['cuit']),
                'razon_social' => $data['razon_social'],
                'certificado_encriptado' => '',
                'private_key_encriptada' => '',
                'iv' => '',
                'tag' => '',
                'activo' => 0, // Inactiva hasta subir certificado
                'fecha_vencimiento_certificado' => date('Y-m-d H:i:s'),
            ]);

            // Generar API Key automáticamente
            $apiKey = ApiKeyRepository::generateKey();
            $this->apiKeyRepository->create([
                'company_id' => $companyId,
                'api_key' => $apiKey,
                'name' => 'API Key por defecto',
                'activo' => 1,
            ]);

            $this->logger->info('Empresa creada', [
                'company_id' => $companyId,
                'cuit' => $data['cuit']
            ]);

            return Response::success([
                'id' => $companyId,
                'api_key' => $apiKey,
                'message' => 'Empresa creada. Debe subir el certificado para activarla.',
            ], 'Empresa creada exitosamente', 201);

        } catch (\Exception $e) {
            $this->logger->error('Error creando empresa', ['error' => $e->getMessage()]);
            return Response::error('Error interno del servidor', [], 500);
        }
    }

    /**
     * Sube/actualiza el certificado de una empresa
     * POST /api/company/upload-certificate
     */
    public function uploadCertificate(Request $request): Response
    {
        try {
            $companyId = $request->route('company_id');
            
            if (empty($companyId)) {
                return Response::error('ID de empresa requerido', [], 400);
            }

            // Verificar que la empresa exista
            $company = $this->companyRepository->findById($companyId);
            
            if ($company === null) {
                return Response::error('Empresa no encontrada', [], 404);
            }

            // Obtener certificado y clave privada del request
            $certificate = $request->input('certificate');
            $privateKey = $request->input('private_key');

            if (empty($certificate) || empty($privateKey)) {
                return Response::error('Certificado y clave privada son requeridos', [], 400);
            }

            // Validar certificado
            $certValidation = $this->certificateService->validateCertificate($certificate);
            
            if (!$certValidation['valid']) {
                return Response::error('Certificado inválido: ' . ($certValidation['error'] ?? ''), [], 400);
            }

            // Encriptar certificado y clave
            $encrypted = $this->certificateService->encrypt($certificate, $privateKey);

            // Actualizar empresa
            $this->companyRepository->updateCertificate(
                $companyId,
                $encrypted['certificado_encriptado'],
                $encrypted['private_key_encriptada'],
                $encrypted['iv'],
                $encrypted['tag'],
                $certValidation['expiration']
            );

            // Activar empresa
            $this->companyRepository->update($companyId, ['activo' => 1]);

            $this->logger->info('Certificado subido', [
                'company_id' => $companyId,
                'expiration' => $certValidation['expiration']->format('Y-m-d')
            ]);

            return Response::success([
                'days_to_expiration' => $certValidation['daysToExpiration'],
                'expiration_date' => $certValidation['expiration']->format('Y-m-d'),
            ], 'Certificado guardado exitosamente');

        } catch (\Exception $e) {
            $this->logger->error('Error subiendo certificado', ['error' => $e->getMessage()]);
            return Response::error('Error al procesar certificado: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Obtiene información de una empresa
     * GET /api/company/{id}
     */
    public function show(Request $request): Response
    {
        try {
            $companyId = $request->route('company_id') ?? $request->route('id');
            
            if (empty($companyId)) {
                return Response::error('ID de empresa requerido', [], 400);
            }

            $company = $this->companyRepository->findById($companyId);
            
            if ($company === null) {
                return Response::error('Empresa no encontrada', [], 404);
            }

            // No devolver datos sensibles
            unset($company['certificado_encriptado']);
            unset($company['private_key_encriptada']);
            unset($company['iv']);
            unset($company['tag']);

            return Response::success($company);

        } catch (\Exception $e) {
            $this->logger->error('Error obteniendo empresa', ['error' => $e->getMessage()]);
            return Response::error('Error interno del servidor', [], 500);
        }
    }

    /**
     * Lista todas las empresas activas
     * GET /api/company
     */
    public function index(Request $request): Response
    {
        try {
            $companies = $this->companyRepository->findAllActive();
            
            return Response::success($companies);

        } catch (\Exception $e) {
            $this->logger->error('Error listando empresas', ['error' => $e->getMessage()]);
            return Response::error('Error interno del servidor', [], 500);
        }
    }
}

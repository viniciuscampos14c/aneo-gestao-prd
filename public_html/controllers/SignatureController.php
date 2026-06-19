<?php

class SignatureController extends BaseController
{
    private SignatureModel $signatures;
    private StudentModel $students;
    private D4SignService $d4sign;
    private CompanyIntegrationModel $integrations;
    private PDO $db;

    public function __construct()
    {
        $this->signatures = new SignatureModel();
        $this->students = new StudentModel();
        $this->d4sign = new D4SignService();
        $this->integrations = new CompanyIntegrationModel();
        $this->db = db();
    }

    public function index(): void
    {
        require_auth();
        require_permission('signatures');

        $filters = [
            'q' => trim((string) request('q', '')),
            'status' => trim((string) request('status', '')),
            'student_id' => trim((string) request('student_id', '')),
        ];

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $companyId = (int) (current_company_id() ?? 0);
        $result = $this->signatures->listRequests($filters, $perPage, $page, $companyId);

        $this->render('signatures/index', [
            'title' => 'Assinaturas Eletronicas',
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'filters' => $filters,
            'students' => $this->signatures->studentsForSelection($companyId),
            'stats' => $this->signatures->stats($companyId),
            'recentEvents' => $this->signatures->recentEvents(20, $companyId),
            'featureAvailable' => $this->signatures->featureAvailable(),
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
            'integration' => [
                'enabled' => $this->d4sign->isEnabled(),
                'configured' => $this->d4sign->isConfigured(),
                'base_url' => $this->d4sign->baseUrl(),
                'token_api' => $this->d4sign->tokenApi(),
                'crypt_key' => $this->d4sign->cryptKey(),
                'safe_uuid' => $this->d4sign->safeUuid(),
                'webhook_url' => $this->buildWebhookUrl(),
                'webhook_token' => $this->d4sign->webhookToken(),
                'webhook_hmac_secret' => $this->d4sign->webhookHmacSecret(),
                'storage_available' => $this->integrations->tableExists(),
            ],
        ]);
    }

    public function saveSettings(): void
    {
        require_auth();
        require_permission('companies');
        csrf_validate();

        $companyId = (int) (current_company_id() ?? 0);
        if ($companyId <= 0) {
            $this->error('Selecione a empresa antes de salvar a configuração D4Sign.');
            $this->redirect('signatures');
        }

        if (!$this->integrations->tableExists()) {
            $this->error('Tabela company_integrations ainda não existe. Execute a migração da Fase 2.');
            $this->redirect('signatures');
        }

        $enabled = post('d4sign_enabled') ? true : false;
        $settings = [
            'base_url' => trim((string) post('d4sign_base_url')),
            'token_api' => trim((string) post('d4sign_token_api')),
            'crypt_key' => trim((string) post('d4sign_crypt_key')),
            'safe_uuid' => trim((string) post('d4sign_safe_uuid')),
            'webhook_token' => trim((string) post('d4sign_webhook_token')),
            'webhook_hmac_secret' => trim((string) post('d4sign_webhook_hmac_secret')),
        ];

        if ($settings['base_url'] !== '') {
            $settings['base_url'] = rtrim($settings['base_url'], '/');
        }

        foreach ($settings as $key => $value) {
            if ($value === '') {
                unset($settings[$key]);
            }
        }

        $changedBy = (int) (current_user()['id'] ?? 0);
        $this->integrations->save($companyId, 'd4sign', $enabled, $settings, $changedBy);

        if (!$enabled) {
            $this->success('Integracao D4Sign desativada para a empresa ativa.');
            $this->redirect('signatures');
        }

        $configured = isset($settings['token_api'], $settings['crypt_key'], $settings['safe_uuid'])
            && $settings['token_api'] !== ''
            && $settings['crypt_key'] !== ''
            && $settings['safe_uuid'] !== '';

        if ($configured) {
            $this->success('Configuração D4Sign atualizada com sucesso.');
        } else {
            $this->success('Configuração salva. Faltam token_api, crypt_key ou safe_uuid para envio de contratos.');
        }

        $this->redirect('signatures');
    }

    public function store(): void
    {
        require_auth();
        require_permission('signatures.create');
        csrf_validate();

        if (!$this->signatures->featureAvailable()) {
            $this->error('Estrutura de assinaturas indisponivel no banco. Execute a migração de assinaturas.');
            $this->redirect('signatures');
        }

        $studentId = (int) post('student_id');
        $title = trim((string) post('title'));
        $description = trim((string) post('description'));
        $file = $_FILES['contract_file'] ?? null;

        if ($studentId <= 0 || $title === '') {
            $this->error('Aluno e título do contrato são obrigatórios.');
            $this->redirect('signatures');
        }

        $student = $this->students->find($studentId);
        if (!$student) {
            $this->error('Aluno não encontrado para assinatura.');
            $this->redirect('signatures');
        }

        if (trim((string) ($student['email_primary'] ?? '')) === '') {
            $this->error('Aluno sem email cadastrado. O D4Sign exige email para assinatura.');
            $this->redirect('signatures');
        }

        $uploadedPath = $this->handleOriginalContractUpload($studentId, $file);
        if ($uploadedPath === null) {
            $this->redirect('signatures');
        }

        $requestId = $this->signatures->createRequest([
            'student_id' => $studentId,
            'title' => $title,
            'description' => $description,
            'signer_name' => (string) ($student['full_name'] ?? ''),
            'signer_email' => (string) ($student['email_primary'] ?? ''),
            'signer_phone' => (string) ($student['phone'] ?? ''),
            'file_original_path' => $uploadedPath,
            'd4sign_safe_uuid' => $this->d4sign->safeUuid(),
            'metadata' => [
                'financial_snapshot' => $this->buildFinancialSnapshot($student),
                'financial_origin' => 'student_record',
            ],
        ], (int) current_user()['id'], (int) (current_company_id() ?? 0));

        if ($requestId <= 0) {
            $this->error('Não foi possível cadastrar solicitacao de assinatura.');
            $this->redirect('signatures');
        }

        $this->success('Solicitacao de assinatura criada. Agora clique em "Enviar D4Sign".');
        $this->redirect('signatures');
    }

    public function send(): void
    {
        require_auth();
        require_permission('signatures.send');
        csrf_validate();

        $id = (int) post('id');
        $companyId = (int) (current_company_id() ?? 0);
        $request = $this->signatures->findRequest($id, $companyId);

        if (!$request) {
            $this->error('Solicitacao de assinatura não encontrada.');
            $this->redirect('signatures');
        }

        if (!$this->d4sign->isEnabled()) {
            $this->signatures->markError(
                $id,
                'Integracao D4Sign desativada em config.php.',
                $this->decodeMetadata((string) ($request['metadata_json'] ?? '')),
                $companyId
            );
            $this->error('Integracao D4Sign desativada em config.php.');
            $this->redirect('signatures');
        }

        if (!$this->d4sign->isConfigured()) {
            $this->signatures->markError(
                $id,
                'Integracao D4Sign habilitada, mas sem token/crypt/safe.',
                $this->decodeMetadata((string) ($request['metadata_json'] ?? '')),
                $companyId
            );
            $this->error('Preencha token_api, crypt_key e safe_uuid no config.php antes do envio.');
            $this->redirect('signatures');
        }

        $documentUuid = trim((string) ($request['d4sign_document_uuid'] ?? ''));
        $signerKey = trim((string) ($request['d4sign_signer_key'] ?? ''));
        $meta = $this->decodeMetadata((string) ($request['metadata_json'] ?? ''));
        $meta['send_started_at'] = now();

        try {
            if ($documentUuid === '') {
                $upload = $this->d4sign->uploadDocument(
                    (string) ($request['d4sign_safe_uuid'] ?: $this->d4sign->safeUuid()),
                    (string) $request['file_original_path'],
                    (string) $request['title']
                );
                $meta['upload'] = $this->compactD4SignResult($upload);

                if (!$upload['ok'] || empty($upload['document_uuid'])) {
                    $message = (string) ($upload['message'] ?? 'Falha no upload para o D4Sign.');
                    $this->signatures->markError($id, $message, $meta, $companyId);
                    $this->error($message);
                    $this->redirect('signatures');
                }

                $documentUuid = (string) $upload['document_uuid'];

                $signer = $this->d4sign->createSigner($documentUuid, [
                    'email' => (string) $request['signer_email'],
                    'name' => (string) $request['signer_name'],
                    'document' => (string) ($request['student_document'] ?? ''),
                    'whatsapp' => (string) ($request['signer_phone'] ?? ''),
                ]);
                $meta['create_signer'] = $this->compactD4SignResult($signer);

                if (!$signer['ok']) {
                    $message = (string) ($signer['message'] ?? 'Falha ao cadastrar signatario no D4Sign.');
                    $this->signatures->markError($id, $message, $meta, $companyId);
                    $this->error($message);
                    $this->redirect('signatures');
                }

                $signerKey = (string) ($signer['signer_key'] ?? '');
            }

            $webhookRegistration = $this->d4sign->registerWebhook($documentUuid, $this->buildWebhookUrl());
            $meta['webhook'] = $this->compactD4SignResult($webhookRegistration);

            $sendResult = $this->d4sign->sendToSigner(
                $documentUuid,
                'Ola! Seu contrato ANEO esta pronto para assinatura eletronicamente.'
            );
            $meta['sendtosigner'] = $this->compactD4SignResult($sendResult);

            if (!$sendResult['ok']) {
                $message = (string) ($sendResult['message'] ?? 'Falha ao enviar documento para assinatura.');
                $this->signatures->markError($id, $message, $meta, $companyId);
                $this->error($message);
                $this->redirect('signatures');
            }

            $this->signatures->markSent($id, $documentUuid, $signerKey !== '' ? $signerKey : null, $meta, $companyId);
            $this->success('Contrato enviado para assinatura via D4Sign.');
        } catch (Throwable $e) {
            $meta['send_exception'] = [
                'message' => $e->getMessage(),
                'caught_at' => now(),
            ];
            $message = 'O D4Sign recebeu o envio, mas o sistema não conseguiu finalizar o retorno da tela. Sincronize o contrato para confirmar o status.';
            $this->signatures->markError($id, $message . ' Detalhe técnico: ' . $e->getMessage(), $meta, $companyId);
            $this->error($message);
        }
        $this->redirect('signatures');
    }

    public function sync(): void
    {
        require_auth();
        require_permission('signatures.sync');
        csrf_validate();

        $id = (int) post('id');
        $companyId = (int) (current_company_id() ?? 0);
        $request = $this->signatures->findRequest($id, $companyId);

        if (!$request) {
            $this->error('Solicitacao não encontrada para sincronizacao.');
            $this->redirect('signatures');
        }

        $documentUuid = trim((string) ($request['d4sign_document_uuid'] ?? ''));
        if ($documentUuid === '') {
            $this->error('Solicitacao ainda sem UUID do documento D4Sign.');
            $this->redirect('signatures');
        }

        $details = $this->d4sign->documentDetails($documentUuid);
        if (!$details['ok']) {
            $message = (string) ($details['message'] ?: 'Falha ao consultar status no D4Sign.');
            $errorMetadata = $this->decodeMetadata((string) ($request['metadata_json'] ?? ''));
            $errorMetadata['details'] = $details['data'] ?? [];
            $this->signatures->markError($id, $message, $errorMetadata, $companyId);
            $this->error($message);
            $this->redirect('signatures');
        }

        $d4Status = $this->d4sign->inferDocumentStatus($details['data'] ?? []);
        $signed = $this->d4sign->looksSignedStatus($d4Status);
        $metadata = $this->decodeMetadata((string) ($request['metadata_json'] ?? ''));
        $metadata['details'] = $details['data'] ?? [];
        $metadata['synced_at'] = now();

        if ($signed) {
            $signedPath = trim((string) ($request['file_signed_path'] ?? ''));
            $downloadError = '';
            if ($signedPath === '') {
                $download = $this->downloadSignedCopy($documentUuid, (int) $request['id']);
                $metadata['download'] = $download;
                if (!$download['ok']) {
                    $downloadError = (string) ($download['message'] ?? '');
                } else {
                    $signedPath = (string) ($download['signed_path'] ?? '');
                }
            }

            $billing = $this->processBillingGenerationOnSignedRequest($request, $metadata, $companyId, (int) current_user()['id']);
            $metadata = $billing['metadata'];
            $this->signatures->markSigned($id, $signedPath !== '' ? $signedPath : null, $d4Status, $metadata, $companyId);

            if ($downloadError !== '') {
                $this->error('Assinatura confirmada no D4Sign, mas falhou o download automatico: ' . $downloadError);
            } else {
                $message = 'Contrato sincronizado como assinado.';
                if ((int) ($billing['created'] ?? 0) > 0) {
                    $message .= ' ' . (int) $billing['created'] . ' parcela(s) financeira(s) gerada(s) automaticamente.';
                }
                $this->success($message);
            }
            $this->redirect('signatures');
        }

        $status = $this->mapLocalStatus($d4Status, 'sent');
        $this->signatures->markSync($id, $status, $d4Status, $metadata, $companyId);
        $this->success('Status sincronizado com o D4Sign.');
        $this->redirect('signatures');
    }

    public function delete(): void
    {
        require_auth();
        require_permission('signatures.delete');
        csrf_validate();

        $id = (int) post('id');
        $companyId = (int) (current_company_id() ?? 0);
        $request = $this->signatures->findRequest($id, $companyId);
        if (!$request) {
            $this->error('Solicitacao não encontrada para exclusão.');
            $this->redirect('signatures');
        }

        $this->safeRemoveSignatureFile((string) ($request['file_original_path'] ?? ''));
        $this->safeRemoveSignatureFile((string) ($request['file_signed_path'] ?? ''));
        $this->signatures->deleteRequest($id, $companyId);

        $this->success('Solicitacao de assinatura removida.');
        $this->redirect('signatures');
    }

    public function webhook(): void
    {
        $receivedToken = trim((string) request('token', ''));
        $this->d4sign = $this->resolveServiceByWebhookToken($receivedToken);
        $companyId = $this->d4sign->companyId();
        $configuredToken = $this->d4sign->webhookToken();
        if ($configuredToken !== '' && !hash_equals($configuredToken, $receivedToken)) {
            $this->json(['ok' => false, 'message' => 'Token de webhook inválido.'], 401);
        }

        $rawBody = file_get_contents('php://input');
        $rawBody = is_string($rawBody) ? $rawBody : '';
        $json = json_decode($rawBody, true);
        $payload = is_array($json) && $json !== [] ? $json : ($_POST ?: []);

        if ($payload === []) {
            $this->json(['ok' => false, 'message' => 'Payload vazio.'], 400);
        }

        if (!$this->validateWebhookHmac($rawBody)) {
            $this->json(['ok' => false, 'message' => 'HMAC inválido.'], 401);
        }

        $documentUuid = $this->extractFromPayload($payload, ['uuid_document', 'uuid_doc', 'document_uuid', 'uuid']);
        $eventType = (string) ($this->extractFromPayload($payload, ['event', 'action', 'type']) ?? 'webhook');
        $eventStatus = (string) ($this->extractFromPayload($payload, ['status', 'document_status']) ?? '');
        $eventMessage = (string) ($this->extractFromPayload($payload, ['message', 'msg']) ?? '');

        $request = $documentUuid ? $this->signatures->findByDocumentUuid($documentUuid, $companyId > 0 ? $companyId : null) : null;
        $requestId = $request ? (int) $request['id'] : null;

        $this->signatures->addEvent([
            'company_id' => $companyId > 0 ? $companyId : null,
            'signature_request_id' => $requestId,
            'd4sign_document_uuid' => $documentUuid,
            'event_type' => $eventType,
            'event_status' => $eventStatus,
            'event_message' => $eventMessage,
            'payload' => $payload,
            'received_at' => now(),
        ]);

        if ($request) {
            $signedByEvent = $this->d4sign->looksSignedStatus($eventStatus) || $this->d4sign->looksSignedStatus($eventType);
            $metadata = $this->decodeMetadata((string) ($request['metadata_json'] ?? ''));
            $metadata['webhook_payload'] = $payload;
            if ($signedByEvent) {
                $signedPath = trim((string) ($request['file_signed_path'] ?? ''));
                if ($signedPath === '') {
                    $download = $this->downloadSignedCopy((string) $documentUuid, (int) $request['id']);
                    $metadata['download'] = $download;
                    if ($download['ok']) {
                        $signedPath = (string) ($download['signed_path'] ?? '');
                    }
                }

                $billing = $this->processBillingGenerationOnSignedRequest(
                    $request,
                    $metadata,
                    $companyId > 0 ? $companyId : null,
                    (int) ($request['created_by'] ?? 0)
                );
                $this->signatures->markSigned(
                    (int) $request['id'],
                    $signedPath !== '' ? $signedPath : null,
                    $eventStatus,
                    $billing['metadata'],
                    $companyId > 0 ? $companyId : null
                );
            } else {
                $this->signatures->markSync(
                    (int) $request['id'],
                    $this->mapLocalStatus($eventStatus, (string) ($request['status'] ?? 'sent')),
                    $eventStatus,
                    $metadata,
                    $companyId > 0 ? $companyId : null
                );
            }
        }

        $this->json(['ok' => true, 'message' => 'Webhook processado com sucesso.']);
    }

    private function compactD4SignResult(array $result): array
    {
        $compact = [
            'ok' => !empty($result['ok']),
            'message' => (string) ($result['message'] ?? ''),
        ];

        foreach (['document_uuid', 'signer_key', 'status', 'url'] as $key) {
            if (isset($result[$key]) && is_scalar($result[$key])) {
                $compact[$key] = (string) $result[$key];
            }
        }

        $response = $result['response'] ?? null;
        if (is_array($response)) {
            foreach (['message', 'uuid', 'uuid_document', 'key_signer', 'status', 'statusName'] as $key) {
                if (isset($response[$key]) && is_scalar($response[$key])) {
                    $compact['response_' . $key] = (string) $response[$key];
                }
            }
        }

        return $compact;
    }

    private function decodeMetadata(string $metadataJson): array
    {
        $decoded = json_decode($metadataJson, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function processBillingGenerationOnSignedRequest(array $request, array $metadata, ?int $companyId, int $createdBy): array
    {
        $metadata['billing_generation'] = [
            'status' => 'disabled_in_signatures',
            'created' => 0,
            'existing' => 0,
            'failed' => 0,
            'requested_installments' => 0,
            'generated_at' => null,
            'updated_at' => now(),
            'message' => 'A geracao financeira por assinaturas foi desativada. O plano deve ser controlado apenas no cadastro do aluno.',
        ];

        return [
            'metadata' => $metadata,
            'created' => 0,
            'existing' => 0,
            'failed' => 0,
        ];
    }

    private function buildFinancialSnapshot(array $student): array
    {
        return [
            'monthly_fee' => round((float) ($student['monthly_fee'] ?? 0), 2),
            'billing_day' => (int) ($student['billing_day'] ?? 0),
            'financial_plan_installments' => (int) ($student['financial_plan_installments'] ?? 0),
            'financial_plan_first_due_date' => trim((string) ($student['financial_plan_first_due_date'] ?? '')),
            'financial_plan_generated_at' => trim((string) ($student['financial_plan_generated_at'] ?? '')),
            'captured_at' => now(),
        ];
    }

    private function handleOriginalContractUpload(int $studentId, $file): ?string
    {
        if (!$file || !isset($file['name']) || trim((string) ($file['name'] ?? '')) === '') {
            $this->error('Selecione o arquivo do contrato.');
            return null;
        }

        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            $this->error('Falha no upload do contrato.');
            return null;
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf', 'doc', 'docx'], true)) {
            $this->error('Formato inválido para contrato. Use PDF, DOC ou DOCX.');
            return null;
        }

        if ((int) ($file['size'] ?? 0) > (10 * 1024 * 1024)) {
            $this->error('Contrato acima de 10MB.');
            return null;
        }

        $targetDir = __DIR__ . '/../uploads/signatures/original';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) $file['name']);
        $storedName = 'signature_' . $studentId . '_' . date('YmdHis') . '_' . $safeName;
        $targetPath = $targetDir . '/' . $storedName;

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $targetPath)) {
            $this->error('Não foi possível salvar o contrato no servidor.');
            return null;
        }

        return 'uploads/signatures/original/' . $storedName;
    }

    private function downloadSignedCopy(string $documentUuid, int $requestId): array
    {
        $list = $this->d4sign->downloadList($documentUuid);
        $urls = $list['ok'] ? ($list['urls'] ?? []) : [];

        $targetDir = __DIR__ . '/../uploads/signatures/signed';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $storedName = 'signed_' . $requestId . '_' . date('YmdHis') . '.pdf';
        $targetPath = $targetDir . '/' . $storedName;

        $attempts = [];
        if (!$list['ok']) {
            $attempts[] = ['source' => 'downloadlist', 'ok' => false, 'message' => (string) ($list['message'] ?? '')];
        }

        foreach ($urls as $url) {
            $download = $this->d4sign->downloadSignedFileFromUrl((string) $url, $targetPath);
            $attempts[] = ['source' => 'downloadlist_url', 'url' => $url, 'ok' => (bool) ($download['ok'] ?? false), 'message' => (string) ($download['message'] ?? '')];

            if ($download['ok']) {
                $storedAbsolute = (string) ($download['stored_path'] ?? $targetPath);
                $storedRelative = 'uploads/signatures/signed/' . basename($storedAbsolute !== '' ? $storedAbsolute : $targetPath);
                return [
                    'ok' => true,
                    'message' => 'Contrato assinado baixado com sucesso.',
                    'signed_path' => $storedRelative,
                ];
            }
        }

        $direct = $this->d4sign->downloadSignedDocument($documentUuid, $targetPath);
        $attempts[] = [
            'source' => 'direct_download',
            'url' => (string) ($direct['url'] ?? ''),
            'ok' => (bool) ($direct['ok'] ?? false),
            'message' => (string) ($direct['message'] ?? ''),
        ];

        if (!$direct['ok']) {
            if (is_file($targetPath)) {
                @unlink($targetPath);
            }

            $message = (string) ($direct['message'] ?? '');
            if ($message === '') {
                $message = (string) ($list['message'] ?? '');
            }
            if ($message === '') {
                $message = 'D4Sign ainda não retornou arquivo assinado para download.';
            }

            return ['ok' => false, 'message' => $message, 'attempts' => $attempts];
        }

        return [
            'ok' => true,
            'message' => 'Contrato assinado baixado com sucesso.',
            'signed_path' => 'uploads/signatures/signed/' . basename((string) ($direct['stored_path'] ?? $targetPath)),
            'attempts' => $attempts,
        ];
    }

    private function safeRemoveSignatureFile(string $relativePath): void
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '') {
            return;
        }

        $uploadsBase = realpath(__DIR__ . '/../uploads');
        if (!$uploadsBase) {
            return;
        }

        $fullPath = realpath(__DIR__ . '/../' . ltrim($relativePath, '/\\'));
        if (!$fullPath) {
            return;
        }

        if (!str_starts_with($fullPath, $uploadsBase)) {
            return;
        }

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function mapLocalStatus(?string $d4signStatus, string $fallback): string
    {
        $status = strtolower(trim((string) $d4signStatus));
        if ($status === '') {
            return $fallback;
        }

        if ($this->d4sign->looksSignedStatus($status)) {
            return 'signed';
        }

        if (str_contains($status, 'cancel') || str_contains($status, 'refus') || str_contains($status, 'reject')) {
            return 'cancelled';
        }

        if (str_contains($status, 'error') || str_contains($status, 'fail')) {
            return 'error';
        }

        return 'sent';
    }

    private function buildWebhookUrl(): string
    {
        $baseUrl = trim((string) config('app.base_url', ''));
        if ($baseUrl !== '') {
            $baseUrl = rtrim($baseUrl, '/');
        } else {
            $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $scheme = $isHttps ? 'https' : 'http';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $scriptDir = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
            $scriptDir = str_replace('\\', '/', $scriptDir);
            $scriptDir = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');
            $baseUrl = $scheme . '://' . $host . $scriptDir;
        }

        $url = $baseUrl . '/index.php?route=signatures/webhook';
        $token = $this->d4sign->webhookToken();
        if ($token !== '') {
            $url .= '&token=' . rawurlencode($token);
        }

        return $url;
    }

    private function validateWebhookHmac(string $rawBody): bool
    {
        $secret = $this->d4sign->webhookHmacSecret();
        if ($secret === '') {
            return true;
        }

        $headerValue = (string) ($_SERVER['HTTP_CONTENT_HMAC'] ?? '');
        if ($headerValue === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $headerName => $headerData) {
                    if (strtolower((string) $headerName) === 'content-hmac') {
                        $headerValue = (string) $headerData;
                        break;
                    }
                }
            }
        }

        if ($headerValue === '') {
            return false;
        }

        $computed = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals(strtolower(trim($headerValue)), strtolower(trim($computed)));
    }

    private function extractFromPayload(array $payload, array $keys)
    {
        $keysMap = array_fill_keys($keys, true);
        $stack = [$payload];

        while ($stack !== []) {
            $item = array_pop($stack);
            if (!is_array($item)) {
                continue;
            }

            foreach ($item as $key => $value) {
                if (is_string($key) && isset($keysMap[$key])) {
                    return $value;
                }

                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        return null;
    }

    private function resolveServiceByWebhookToken(string $providedToken): D4SignService
    {
        if ($providedToken !== '') {
            $companyId = $this->integrations->findCompanyIdByToken('d4sign', 'webhook_token', $providedToken);
            if ($companyId !== null && $companyId > 0) {
                return new D4SignService($companyId);
            }
        }

        return new D4SignService();
    }
}

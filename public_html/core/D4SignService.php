<?php

class D4SignService
{
    private int $companyId;
    private array $settings;

    public function __construct(?int $companyId = null)
    {
        $this->companyId = (int) ($companyId ?? current_company_id() ?? 0);
        $this->settings = (new CompanyIntegrationModel())->mergeWithGlobalConfig('d4sign', $this->companyId);
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->setting('enabled', false);
    }

    public function baseUrl(): string
    {
        $base = trim((string) $this->setting('base_url', 'https://secure.d4sign.com.br'));
        return rtrim($base, '/');
    }

    public function tokenApi(): string
    {
        return trim((string) $this->setting('token_api', ''));
    }

    public function cryptKey(): string
    {
        return trim((string) $this->setting('crypt_key', ''));
    }

    public function safeUuid(): string
    {
        return trim((string) $this->setting('safe_uuid', ''));
    }

    public function webhookToken(): string
    {
        return trim((string) $this->setting('webhook_token', ''));
    }

    public function webhookHmacSecret(): string
    {
        return trim((string) $this->setting('webhook_hmac_secret', ''));
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled()
            && $this->tokenApi() !== ''
            && $this->cryptKey() !== ''
            && $this->safeUuid() !== '';
    }

    public function uploadDocument(string $safeUuid, string $filePath, string $displayName): array
    {
        if (!is_file($filePath)) {
            return ['ok' => false, 'message' => 'Arquivo local do contrato não encontrado.', 'document_uuid' => null];
        }

        $safeUuid = trim($safeUuid);
        if ($safeUuid === '') {
            return ['ok' => false, 'message' => 'UUID do cofre D4Sign não informado.', 'document_uuid' => null];
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $uploadName = basename($filePath);
        $mimeType = $this->mimeByExtension($extension);
        $documentName = trim($displayName) !== '' ? trim($displayName) : pathinfo($uploadName, PATHINFO_FILENAME);

        if ($extension !== '' && !preg_match('/\.' . preg_quote($extension, '/') . '$/i', $documentName)) {
            $documentName .= '.' . $extension;
        }

        $fields = [
            'name_document' => $documentName !== '' ? $documentName : $uploadName,
            'file' => new CURLFile($filePath, $mimeType, $uploadName),
        ];

        $response = $this->requestMultipart('POST', '/documents/' . rawurlencode($safeUuid) . '/upload', $fields);
        if (!$response['ok']) {
            return ['ok' => false, 'message' => $response['message'], 'document_uuid' => null, 'response' => $response['data']];
        }

        $documentUuid = $this->extractDocumentUuid($response['data'] ?? []);
        return [
            'ok' => $documentUuid !== null,
            'message' => $documentUuid ? 'Documento enviado ao D4Sign.' : 'Documento enviado, mas sem UUID de retorno.',
            'document_uuid' => $documentUuid,
            'response' => $response['data'],
        ];
    }

    public function createSigner(string $documentUuid, array $signer): array
    {
        $documentUuid = trim($documentUuid);
        if ($documentUuid === '') {
            return ['ok' => false, 'message' => 'UUID do documento não informado.', 'signer_key' => null];
        }

        $email = strtolower(trim((string) ($signer['email'] ?? '')));
        if ($email === '') {
            return ['ok' => false, 'message' => 'Email do signatário não informado.', 'signer_key' => null];
        }

        $payload = [
            'signers' => [[
                'email' => $email,
                'act' => '1',
                'foreign' => '0',
                'certificadoicpbr' => '0',
                'assinatura_presencial' => '0',
                'docauth' => '0',
                'docauthandselfie' => '0',
                'selfie' => '0',
                'comunica_whatsapp' => '0',
                'nome' => trim((string) ($signer['name'] ?? '')),
                'documentation' => trim((string) ($signer['document'] ?? '')),
                'birthday' => trim((string) ($signer['birthday'] ?? '')),
            ]],
        ];

        $response = $this->requestJson('POST', '/documents/' . rawurlencode($documentUuid) . '/createlist', $payload);
        if (!$response['ok']) {
            return ['ok' => false, 'message' => $response['message'], 'signer_key' => null, 'response' => $response['data']];
        }

        $signerKey = $this->extractSignerKey($response['data'] ?? []);
        return [
            'ok' => true,
            'message' => 'Signatario cadastrado no D4Sign.',
            'signer_key' => $signerKey,
            'response' => $response['data'],
        ];
    }

    public function sendToSigner(string $documentUuid, string $message = ''): array
    {
        $documentUuid = trim($documentUuid);
        if ($documentUuid === '') {
            return ['ok' => false, 'message' => 'UUID do documento não informado.'];
        }

        $payload = [
            'message' => $message !== '' ? $message : 'Contrato disponível para assinatura eletrônica.',
            'skipemail' => '0',
        ];

        $response = $this->requestJson('POST', '/documents/' . rawurlencode($documentUuid) . '/sendtosigner', $payload);
        if (!$response['ok']) {
            return ['ok' => false, 'message' => $response['message'], 'response' => $response['data']];
        }

        return ['ok' => true, 'message' => 'Envio para assinatura solicitado com sucesso.', 'response' => $response['data']];
    }

    public function registerWebhook(string $documentUuid, string $webhookUrl): array
    {
        $documentUuid = trim($documentUuid);
        $webhookUrl = trim($webhookUrl);
        if ($documentUuid === '' || $webhookUrl === '') {
            return ['ok' => false, 'message' => 'Documento ou URL de webhook não informados.'];
        }

        $response = $this->requestJson('POST', '/documents/' . rawurlencode($documentUuid) . '/webhooks', [
            'url' => $webhookUrl,
        ]);

        if (!$response['ok']) {
            return ['ok' => false, 'message' => $response['message'], 'response' => $response['data']];
        }

        return ['ok' => true, 'message' => 'Webhook registrado no documento.', 'response' => $response['data']];
    }

    public function documentDetails(string $documentUuid): array
    {
        $documentUuid = trim($documentUuid);
        if ($documentUuid === '') {
            return ['ok' => false, 'message' => 'UUID do documento não informado.', 'data' => []];
        }

        $response = $this->requestJson('GET', '/documents/' . rawurlencode($documentUuid) . '/list');

        if (!$response['ok']) {
            $message = strtolower(trim((string) ($response['message'] ?? '')));
            if ($message !== '' && str_contains($message, 'tempo limite')) {
                $download = $this->downloadList($documentUuid);
                if (!empty($download['ok']) && !empty($download['urls'])) {
                    return [
                        'ok' => true,
                        'message' => '',
                        'data' => [
                            'status' => 'finalizado',
                            'statusName' => 'Finalizado',
                            'document_uuid' => $documentUuid,
                            'fallback_source' => 'downloadlist',
                            'download_urls' => $download['urls'],
                            'download_data' => $download['data'] ?? [],
                        ],
                    ];
                }
            }
        }

        return [
            'ok' => $response['ok'],
            'message' => $response['message'],
            'data' => $response['data'] ?? [],
        ];
    }

    public function downloadList(string $documentUuid): array
    {
        $documentUuid = trim($documentUuid);
        if ($documentUuid === '') {
            return ['ok' => false, 'message' => 'UUID do documento não informado.', 'urls' => [], 'data' => []];
        }

        $path = '/documents/' . rawurlencode($documentUuid) . '/downloadlist';
        $getResponse = $this->requestJson('GET', $path);
        $urls = $this->collectUrls($getResponse['data'] ?? []);

        $postResponse = ['ok' => false, 'message' => '', 'data' => []];
        if ($urls === []) {
            $postResponse = $this->requestJson('POST', $path, []);
            $urls = array_values(array_unique(array_merge(
                $urls,
                $this->collectUrls($postResponse['data'] ?? [])
            )));
        }

        $postDownloadResponse = ['ok' => false, 'message' => '', 'data' => []];
        if ($urls === []) {
            $postDownloadResponse = $this->requestJson('POST', '/documents/' . rawurlencode($documentUuid) . '/download', []);
            $urls = array_values(array_unique(array_merge(
                $urls,
                $this->collectUrls($postDownloadResponse['data'] ?? [])
            )));
        }

        $message = '';
        if ($urls === []) {
            $parts = array_values(array_filter([
                (string) ($getResponse['message'] ?? ''),
                (string) ($postResponse['message'] ?? ''),
                (string) ($postDownloadResponse['message'] ?? ''),
            ], static fn ($v) => trim((string) $v) !== ''));
            $message = $parts !== [] ? implode(' | ', $parts) : 'D4Sign não retornou URLs de download.';
        }

        return [
            'ok' => $urls !== [],
            'message' => $message,
            'urls' => $urls,
            'data' => [
                'downloadlist_get' => $getResponse['data'] ?? [],
                'downloadlist_post' => $postResponse['data'] ?? [],
                'download_post' => $postDownloadResponse['data'] ?? [],
            ],
        ];
    }

    public function directDownloadUrls(string $documentUuid): array
    {
        $documentUuid = trim($documentUuid);
        if ($documentUuid === '') {
            return [];
        }

        $documentUuid = rawurlencode($documentUuid);
        $urls = [];
        $postDownloadList = $this->requestJson('POST', '/documents/' . $documentUuid . '/downloadlist', []);
        $urls = array_values(array_unique(array_merge(
            $urls,
            $this->collectUrls($postDownloadList['data'] ?? [])
        )));

        $postDownload = $this->requestJson('POST', '/documents/' . $documentUuid . '/download', []);
        $urls = array_values(array_unique(array_merge(
            $urls,
            $this->collectUrls($postDownload['data'] ?? [])
        )));

        $paths = [
            '/documents/' . $documentUuid . '/download',
            '/documents/' . $documentUuid . '/downloadsigned',
            '/documents/' . $documentUuid . '/downloadzip',
        ];

        foreach ($paths as $path) {
            $url = $this->buildUrl($path, $this->withAuthQuery([]));
            $urls[$url] = $url;
        }

        return array_values($urls);
    }

    public function downloadSignedDocument(string $documentUuid, string $targetPath): array
    {
        $listResult = $this->downloadList($documentUuid);
        $urls = array_values(array_unique(array_merge(
            $listResult['urls'] ?? [],
            $this->directDownloadUrls($documentUuid)
        )));
        if ($urls === []) {
            return ['ok' => false, 'message' => 'D4Sign não retornou URLs para download do assinado.', 'url' => null];
        }

        $errors = [];
        foreach ($urls as $url) {
            $download = $this->downloadAndNormalizeSignedFile($url, $targetPath);
            if (!$download['ok']) {
                $errors[] = (string) ($download['message'] ?? 'Falha de download.');
                continue;
            }

            return [
                'ok' => true,
                'message' => (string) ($download['message'] ?? 'Arquivo assinado baixado com sucesso.'),
                'url' => $url,
                'stored_path' => (string) ($download['stored_path'] ?? $targetPath),
                'file_extension' => (string) ($download['file_extension'] ?? 'pdf'),
            ];
        }

        return [
            'ok' => false,
            'message' => $errors !== [] ? implode(' | ', $errors) : 'Falha no download direto do documento assinado.',
            'url' => null,
        ];
    }

    public function downloadSignedFileFromUrl(string $url, string $targetPath): array
    {
        return $this->downloadAndNormalizeSignedFile($url, $targetPath);
    }

    public function downloadRemoteFile(string $url, string $targetPath): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['ok' => false, 'message' => 'URL de download não informada.'];
        }

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'Extensão cURL indisponivel para baixar arquivo.'];
        }

        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $httpCode < 200 || $httpCode >= 300) {
            return [
                'ok' => false,
                'message' => 'Falha ao baixar arquivo assinado: ' . ($curlError !== '' ? $curlError : ('HTTP ' . $httpCode)),
            ];
        }

        if (!is_string($raw) || strlen($raw) === 0) {
            return [
                'ok' => false,
                'message' => 'D4Sign retornou arquivo vazio no download.',
            ];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $nextUrl = trim((string) ($decoded['url'] ?? ''));
            if ($nextUrl !== '' && $nextUrl !== $url && filter_var($nextUrl, FILTER_VALIDATE_URL)) {
                return $this->downloadRemoteFile($nextUrl, $targetPath);
            }
        }

        if (file_put_contents($targetPath, $raw) === false) {
            return ['ok' => false, 'message' => 'Falha ao gravar arquivo assinado no servidor.'];
        }

        return ['ok' => true, 'message' => 'Arquivo assinado baixado com sucesso.'];
    }

    public function inferDocumentStatus(array $details): ?string
    {
        $status = $this->firstByKeys($details, ['status', 'document_status', 'status_name', 'statusName']);
        if (is_scalar($status)) {
            $normalized = strtolower(trim((string) $status));
            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    public function looksSignedStatus(?string $status): bool
    {
        $status = strtolower(trim((string) $status));
        if ($status === '') {
            return false;
        }

        $signedKeys = ['closed', 'signed', 'finished', 'completed', 'concluido', 'finalizado'];
        foreach ($signedKeys as $key) {
            if (str_contains($status, $key)) {
                return true;
            }
        }

        return false;
    }

    private function requestJson(string $method, string $path, ?array $payload = null, array $query = []): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'message' => 'Extensão cURL indisponivel.', 'data' => []];
        }

        $method = strtoupper(trim($method));
        $query = $this->withAuthQuery($query);
        $url = $this->buildUrl($path, $query);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'tokenAPI: ' . $this->tokenApi(),
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if ($method === 'POST') {
            $json = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json !== false ? $json : '{}');
        }

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return $this->normalizeResponse($raw, $httpCode, $curlError);
    }

    private function requestMultipart(string $method, string $path, array $fields, array $query = []): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'message' => 'Extensão cURL indisponivel.', 'data' => []];
        }

        $method = strtoupper(trim($method));
        $query = $this->withAuthQuery($query);
        $url = $this->buildUrl($path, $query);

        $headers = [
            'Accept: application/json',
            'tokenAPI: ' . $this->tokenApi(),
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return $this->normalizeResponse($raw, $httpCode, $curlError);
    }

    private function normalizeResponse($raw, int $httpCode, string $curlError): array
    {
        if ($raw === false) {
            return [
                'ok' => false,
                'status' => $httpCode,
                'message' => 'Falha de conexao com D4Sign: ' . ($curlError !== '' ? $curlError : 'erro desconhecido'),
                'data' => [],
            ];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => (string) $raw];
        }

        $ok = $httpCode >= 200 && $httpCode < 300;
        $message = '';
        if (!$ok) {
            $message = $this->extractMessage($decoded);
            if ($message === '') {
                $message = 'Erro HTTP ' . $httpCode . ' na API D4Sign.';
            }
        }

        return [
            'ok' => $ok,
            'status' => $httpCode,
            'message' => $message,
            'data' => $decoded,
        ];
    }

    private function withAuthQuery(array $query = []): array
    {
        $query['tokenAPI'] = $this->tokenApi();
        $query['cryptKey'] = $this->cryptKey();
        return $query;
    }

    private function buildUrl(string $path, array $query = []): string
    {
        $base = $this->baseUrl();
        if (!str_contains($base, '/api/')) {
            $base .= '/api/v1';
        }

        $url = rtrim($base, '/') . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $url;
    }

    private function extractDocumentUuid(array $data): ?string
    {
        $value = $this->firstByKeys($data, ['uuid', 'uuid_document', 'uuid-doc', 'uuidDoc', 'document_uuid', 'uuid_documento']);
        if (is_scalar($value)) {
            $uuid = trim((string) $value);
            return $uuid !== '' ? $uuid : null;
        }
        return null;
    }

    private function extractSignerKey(array $data): ?string
    {
        $value = $this->firstByKeys($data, ['key_signer', 'keySigner', 'signer_key', 'id_linkassinatura']);
        if (is_scalar($value)) {
            $key = trim((string) $value);
            return $key !== '' ? $key : null;
        }
        return null;
    }

    private function collectUrls(array $data): array
    {
        $urls = [];
        $stack = [$data];

        while ($stack !== []) {
            $item = array_pop($stack);
            if (!is_array($item)) {
                continue;
            }

            foreach ($item as $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                    continue;
                }

                if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                    $urls[$value] = $value;
                }
            }
        }

        return array_values($urls);
    }

    private function firstByKeys(array $data, array $keys)
    {
        $keysMap = array_fill_keys($keys, true);
        $stack = [$data];

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

    private function extractMessage(array $data): string
    {
        $possible = ['message', 'msg', 'error', 'errors', 'detail'];
        foreach ($possible as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            if (is_string($value)) {
                return trim($value);
            }
            if (is_array($value)) {
                $parts = [];
                array_walk_recursive($value, function ($item, $itemKey) use (&$parts): void {
                    if (is_scalar($item)) {
                        $parts[] = is_string($itemKey) && $itemKey !== ''
                            ? ($itemKey . ': ' . (string) $item)
                            : (string) $item;
                    }
                });

                $joined = trim(implode('; ', $parts));
                if ($joined !== '') {
                    return $joined;
                }

                $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return $json !== false ? $json : '';
            }
        }

        return '';
    }

    private function mimeByExtension(string $extension): string
    {
        $extension = strtolower(trim($extension));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }

    private function looksLikePdf(string $filePath): bool
    {
        if (!is_file($filePath) || filesize($filePath) < 32) {
            return false;
        }

        $fh = @fopen($filePath, 'rb');
        if (!$fh) {
            return false;
        }

        $head = fread($fh, 4);
        fclose($fh);

        return $head === '%PDF';
    }

    private function looksLikeZip(string $filePath): bool
    {
        if (!is_file($filePath) || filesize($filePath) < 32) {
            return false;
        }

        $fh = @fopen($filePath, 'rb');
        if (!$fh) {
            return false;
        }

        $head = fread($fh, 4);
        fclose($fh);

        return $head === "PK\x03\x04";
    }

    private function downloadAndNormalizeSignedFile(string $url, string $targetPath): array
    {
        $download = $this->downloadRemoteFile($url, $targetPath);
        if (!$download['ok']) {
            return $download;
        }

        if ($this->looksLikePdf($targetPath)) {
            return [
                'ok' => true,
                'message' => 'PDF assinado baixado.',
                'stored_path' => $targetPath,
                'file_extension' => 'pdf',
            ];
        }

        if ($this->looksLikeZip($targetPath)) {
            if (!class_exists('ZipArchive')) {
                $zipPath = preg_replace('/\.pdf$/i', '.zip', $targetPath);
                if (!is_string($zipPath) || trim($zipPath) === '') {
                    $zipPath = $targetPath . '.zip';
                }

                if ($zipPath !== $targetPath) {
                    if (is_file($zipPath)) {
                        @unlink($zipPath);
                    }
                    if (!@rename($targetPath, $zipPath)) {
                        $zipPath = $targetPath;
                    }
                }

                return [
                    'ok' => true,
                    'message' => 'Arquivo assinado baixado em ZIP (extensão ZipArchive indisponivel para extrair PDF).',
                    'stored_path' => $zipPath,
                    'file_extension' => 'zip',
                ];
            }

            $zipPath = $targetPath . '.zip';
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }

            if (!@rename($targetPath, $zipPath)) {
                $zipPath = $targetPath;
            }

            $extract = $this->extractPdfFromZip($zipPath, $targetPath);
            if ($zipPath !== $targetPath && is_file($zipPath)) {
                @unlink($zipPath);
            }

            if ($extract['ok']) {
                return [
                    'ok' => true,
                    'message' => 'PDF assinado extraído de arquivo ZIP do D4Sign.',
                    'stored_path' => $targetPath,
                    'file_extension' => 'pdf',
                ];
            }

            if (is_file($targetPath) && !$this->looksLikePdf($targetPath)) {
                @unlink($targetPath);
            }

            return [
                'ok' => false,
                'message' => (string) ($extract['message'] ?? 'Arquivo ZIP sem PDF assinado valido.'),
            ];
        }

        if (is_file($targetPath)) {
            @unlink($targetPath);
        }

        return ['ok' => false, 'message' => 'Arquivo retornado pelo D4Sign não parece PDF assinado.'];
    }

    private function extractPdfFromZip(string $zipPath, string $targetPdfPath): array
    {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'message' => 'Extensão ZipArchive indisponivel para extrair PDF assinado.'];
        }

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath);
        if ($opened !== true) {
            return ['ok' => false, 'message' => 'Falha ao abrir ZIP retornado pelo D4Sign.'];
        }

        $selectedIndex = -1;
        $selectedScore = -9999;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $normalized = strtolower(trim($name));
            if ($normalized === '' || !str_ends_with($normalized, '.pdf')) {
                continue;
            }

            $score = 0;
            if (str_contains($normalized, 'assinado') || str_contains($normalized, 'signed')) {
                $score += 10;
            }
            if (str_contains($normalized, 'cert') || str_contains($normalized, 'certificate')) {
                $score += 5;
            }
            if (str_contains($normalized, 'original')) {
                $score -= 3;
            }

            if ($score > $selectedScore) {
                $selectedScore = $score;
                $selectedIndex = $i;
            }
        }

        if ($selectedIndex < 0) {
            $zip->close();
            return ['ok' => false, 'message' => 'ZIP não possui arquivos PDF para extração.'];
        }

        $content = $zip->getFromIndex($selectedIndex);
        $zip->close();
        if ($content === false || $content === '') {
            return ['ok' => false, 'message' => 'Falha ao ler PDF dentro do ZIP retornado pelo D4Sign.'];
        }

        if (file_put_contents($targetPdfPath, $content) === false) {
            return ['ok' => false, 'message' => 'Falha ao gravar PDF assinado extraído do ZIP.'];
        }

        if (!$this->looksLikePdf($targetPdfPath)) {
            @unlink($targetPdfPath);
            return ['ok' => false, 'message' => 'PDF extraído do ZIP parece inválido.'];
        }

        return ['ok' => true, 'message' => 'PDF extraído com sucesso.'];
    }

    private function setting(string $key, $default = null)
    {
        if (array_key_exists($key, $this->settings)) {
            return $this->settings[$key];
        }

        return $default;
    }
}

<?php

class ItauService
{
    private array $settings;

    private const STS_URL        = 'https://sts.itau.com.br/api/oauth/token';
    private const BASE_SANDBOX   = 'https://sandbox.api.itau.com.br';
    private const BASE_PRODUCTION = 'https://secure.api.cloud.itau.com.br';
    private const TOKEN_CACHE_KEY = 'itau_oauth_token';

    public function __construct(?int $companyId = null)
    {
        $companyId = (int) ($companyId ?? current_company_id() ?? 0);
        $this->settings = (new CompanyIntegrationModel())->mergeWithGlobalConfig('itau', $companyId);
    }

    public function provider(): string
    {
        return 'itau';
    }

    public function isEnabled(): bool
    {
        return (bool) $this->setting('enabled', false);
    }

    public function buildPayload(array $invoice, array $student, ?array $existing = null): array
    {
        $existing = $existing ?? [];

        return [
            'provider'    => $this->provider(),
            'environment' => (string) $this->setting('environment', 'sandbox'),
            'invoice'     => [
                'id'            => (int) $invoice['id'],
                'number'        => (string) $invoice['invoice_number'],
                'amount'        => (float) $invoice['amount'],
                'paid_amount'   => (float) ($invoice['paid_amount'] ?? 0),
                'due_date'      => (string) ($invoice['due_date'] ?? ''),
                'tags'          => (string) ($invoice['tags'] ?? ''),
                'project'       => (string) ($invoice['project_name'] ?? ''),
                'existing_boleto_url' => (string) ($invoice['boleto_url'] ?? ''),
            ],
            'student' => [
                'id'       => (int) $student['id'],
                'name'     => (string) ($student['full_name'] ?? ''),
                'email'    => (string) ($student['email_primary'] ?? ''),
                'phone'    => (string) ($student['phone'] ?? ''),
                'document' => (string) ($student['cpf'] ?? $student['rg'] ?? ''),
                'ra'       => (string) ($student['ra'] ?? ''),
            ],
            'beneficiary' => [
                'id_beneficiario' => (string) $this->setting('id_beneficiario', ''),
                'agencia'         => (string) $this->setting('agencia', ''),
                'conta'           => (string) $this->setting('conta', ''),
                'conta_dv'        => (string) $this->setting('conta_dv', ''),
                'carteira'        => (string) $this->setting('carteira', ''),
                'name'            => (string) $this->setting('beneficiary_name', ''),
                'cnpj'            => (string) $this->setting('beneficiary_cnpj', ''),
            ],
            'existing' => [
                'external_id'  => (string) ($existing['external_id'] ?? ''),
                'nosso_numero' => (string) ($existing['nosso_numero'] ?? ''),
                'status'       => (string) ($existing['status'] ?? ''),
            ],
            'generated_at' => now(),
        ];
    }

    public function requestGeneration(array $payload, ?array $existing = null): array
    {
        $existing = $existing ?? [];

        if (!$this->isEnabled()) {
            return [
                'sent'             => false,
                'status'           => 'pending',
                'message'          => 'Integracao Itau nao configurada. Registro pendente criado.',
                'external_id'      => $existing['external_id'] ?? null,
                'nosso_numero'     => $existing['nosso_numero'] ?? null,
                'digitable_line'   => $existing['digitable_line'] ?? null,
                'barcode'          => $existing['barcode'] ?? null,
                'pix_qr_code'      => $existing['pix_qr_code'] ?? null,
                'pix_copy_paste'   => $existing['pix_copy_paste'] ?? null,
                'boleto_url'       => $payload['invoice']['existing_boleto_url'] ?: ($existing['boleto_url'] ?? null),
                'pdf_url'          => $existing['pdf_url'] ?? null,
                'expires_at'       => null,
                'response_payload' => null,
            ];
        }

        $token = $this->getAccessToken();
        if ($token === '') {
            return [
                'sent'             => true,
                'status'           => 'error',
                'message'          => 'Falha ao obter token OAuth2 do Itau. Verifique Client ID/Secret e certificados.',
                'external_id'      => null,
                'nosso_numero'     => null,
                'digitable_line'   => null,
                'barcode'          => null,
                'pix_qr_code'      => null,
                'pix_copy_paste'   => null,
                'boleto_url'       => null,
                'pdf_url'          => null,
                'expires_at'       => null,
                'response_payload' => null,
            ];
        }

        $body     = $this->buildBoletoBody($payload);
        $response = $this->callApi('POST', '/boletoscash/v2/boletos', $body);

        if (!empty($response['error'])) {
            return [
                'sent'             => true,
                'status'           => 'error',
                'message'          => 'Erro ao emitir boleto: ' . ($response['error']),
                'external_id'      => null,
                'nosso_numero'     => null,
                'digitable_line'   => null,
                'barcode'          => null,
                'pix_qr_code'      => null,
                'pix_copy_paste'   => null,
                'boleto_url'       => null,
                'pdf_url'          => null,
                'expires_at'       => null,
                'response_payload' => $response,
            ];
        }

        return $this->parseBoletoResponse($response);
    }

    public function requestStatus(array $bankSlip): array
    {
        if (!$this->isEnabled()) {
            return [
                'sent'           => false,
                'status'         => (string) ($bankSlip['status'] ?? 'pending'),
                'message'        => 'Integracao Itau nao configurada.',
                'external_id'    => $bankSlip['external_id'] ?? null,
                'nosso_numero'   => $bankSlip['nosso_numero'] ?? null,
                'digitable_line' => $bankSlip['digitable_line'] ?? null,
                'barcode'        => $bankSlip['barcode'] ?? null,
                'pix_qr_code'    => $bankSlip['pix_qr_code'] ?? null,
                'pix_copy_paste' => $bankSlip['pix_copy_paste'] ?? null,
                'boleto_url'     => $bankSlip['boleto_url'] ?? null,
                'pdf_url'        => $bankSlip['pdf_url'] ?? null,
                'paid_at'        => $bankSlip['paid_at'] ?? null,
                'paid_amount'    => null,
                'expires_at'     => $bankSlip['expires_at'] ?? null,
                'response_payload' => null,
            ];
        }

        $nossoNumero  = (string) ($bankSlip['nosso_numero'] ?? $bankSlip['external_id'] ?? '');
        $idBeneficiario = (string) $this->setting('id_beneficiario', '');

        if ($nossoNumero === '' || $idBeneficiario === '') {
            return [
                'sent'             => false,
                'status'           => (string) ($bankSlip['status'] ?? 'pending'),
                'message'          => 'Nosso numero ou ID beneficiario nao configurado para consulta.',
                'external_id'      => $bankSlip['external_id'] ?? null,
                'nosso_numero'     => $bankSlip['nosso_numero'] ?? null,
                'digitable_line'   => $bankSlip['digitable_line'] ?? null,
                'barcode'          => $bankSlip['barcode'] ?? null,
                'pix_qr_code'      => null,
                'pix_copy_paste'   => null,
                'boleto_url'       => $bankSlip['boleto_url'] ?? null,
                'pdf_url'          => $bankSlip['pdf_url'] ?? null,
                'paid_at'          => $bankSlip['paid_at'] ?? null,
                'paid_amount'      => null,
                'expires_at'       => $bankSlip['expires_at'] ?? null,
                'response_payload' => null,
            ];
        }

        $response = $this->callApi('GET', '/boletoscash/v2/boletos/' . $idBeneficiario . '/' . $nossoNumero, null);

        if (!empty($response['error'])) {
            return [
                'sent'             => true,
                'status'           => (string) ($bankSlip['status'] ?? 'processing'),
                'message'          => 'Erro ao consultar boleto: ' . $response['error'],
                'external_id'      => $bankSlip['external_id'] ?? null,
                'nosso_numero'     => $nossoNumero,
                'digitable_line'   => $bankSlip['digitable_line'] ?? null,
                'barcode'          => $bankSlip['barcode'] ?? null,
                'pix_qr_code'      => null,
                'pix_copy_paste'   => null,
                'boleto_url'       => $bankSlip['boleto_url'] ?? null,
                'pdf_url'          => $bankSlip['pdf_url'] ?? null,
                'paid_at'          => $bankSlip['paid_at'] ?? null,
                'paid_amount'      => null,
                'expires_at'       => $bankSlip['expires_at'] ?? null,
                'response_payload' => $response,
            ];
        }

        $detalhes   = $response['dado_boleto']['dados_individuais_boleto'][0] ?? [];
        $situacao   = strtolower(trim((string) ($detalhes['situacao_geral_boleto'] ?? '')));
        $valorPago  = isset($detalhes['valor_titulo']) ? (float) $detalhes['valor_titulo'] : null;
        $pagoStatus = ['paga', 'baixada', 'liquidado', 'liquidada'];
        $isPago     = in_array($situacao, $pagoStatus, true);

        return [
            'sent'             => true,
            'status'           => $isPago ? 'paid' : 'issued',
            'message'          => 'Status sincronizado: ' . ($situacao !== '' ? $situacao : 'ativo'),
            'external_id'      => $bankSlip['external_id'] ?? null,
            'nosso_numero'     => $nossoNumero,
            'digitable_line'   => $bankSlip['digitable_line'] ?? null,
            'barcode'          => $bankSlip['barcode'] ?? null,
            'pix_qr_code'      => null,
            'pix_copy_paste'   => null,
            'boleto_url'       => $bankSlip['boleto_url'] ?? null,
            'pdf_url'          => $bankSlip['pdf_url'] ?? null,
            'paid_at'          => $isPago ? date('Y-m-d') : ($bankSlip['paid_at'] ?? null),
            'paid_amount'      => $isPago ? $valorPago : null,
            'expires_at'       => $bankSlip['expires_at'] ?? null,
            'response_payload' => $response,
        ];
    }

    public function registerWebhook(string $webhookUrl): array
    {
        $idBeneficiario = (string) $this->setting('id_beneficiario', '');

        if ($idBeneficiario === '') {
            return ['ok' => false, 'message' => 'ID Beneficiario nao configurado.'];
        }

        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'message' => 'URL do webhook invalida.'];
        }

        $response = $this->callApi('PUT', '/boletoscash/v2/webhooks/' . $idBeneficiario, [
            'webhookUrl' => $webhookUrl,
        ]);

        if (!empty($response['error'])) {
            return ['ok' => false, 'message' => 'Erro ao registrar webhook: ' . $response['error']];
        }

        return ['ok' => true, 'message' => 'Webhook registrado com sucesso.', 'data' => $response];
    }

    // ------------------------------------------------------------------
    // Métodos privados
    // ------------------------------------------------------------------

    private function getAccessToken(): string
    {
        $cacheKey = self::TOKEN_CACHE_KEY . '_' . md5((string) $this->setting('client_id', ''));

        if (!empty($_SESSION[$cacheKey]['token']) && !empty($_SESSION[$cacheKey]['expires_at'])) {
            if ($_SESSION[$cacheKey]['expires_at'] > time() + 60) {
                return (string) $_SESSION[$cacheKey]['token'];
            }
        }

        $clientId     = (string) $this->setting('client_id', '');
        $clientSecret = (string) $this->setting('client_secret', '');
        $certPath     = (string) $this->setting('cert_path', '');
        $keyPath      = (string) $this->setting('key_path', '');

        if ($clientId === '' || $clientSecret === '') {
            return '';
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::STS_URL,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($certPath !== '' && is_file($certPath)) {
            curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
        }
        if ($keyPath !== '' && is_file($keyPath)) {
            curl_setopt($ch, CURLOPT_SSLKEY, $keyPath);
        }

        $raw  = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($raw === false || ($info['http_code'] ?? 0) < 200 || ($info['http_code'] ?? 0) >= 300) {
            return '';
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data) || empty($data['access_token'])) {
            return '';
        }

        $expiresIn = (int) ($data['expires_in'] ?? 3600);
        $_SESSION[$cacheKey] = [
            'token'      => $data['access_token'],
            'expires_at' => time() + $expiresIn,
        ];

        return (string) $data['access_token'];
    }

    private function callApi(string $method, string $path, ?array $body): array
    {
        $isSandbox   = strtolower((string) $this->setting('environment', 'sandbox')) === 'sandbox';
        $baseUrl     = $isSandbox ? self::BASE_SANDBOX : self::BASE_PRODUCTION;
        $clientId    = (string) $this->setting('client_id', '');
        $certPath    = (string) $this->setting('cert_path', '');
        $keyPath     = (string) $this->setting('key_path', '');
        $token       = $this->getAccessToken();
        $correlId    = uniqid('aneo_', true);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
            'x-itau-apikey: ' . $clientId,
            'x-itau-correlationID: ' . $correlId,
            'x-itau-flowID: ' . $correlId,
            'x-correlation-id: ' . $correlId,
            'User-Agent: ANEO ERP',
        ];

        $ch = curl_init();
        $url = $baseUrl . $path;

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body ?? [], JSON_UNESCAPED_UNICODE);
        } elseif ($method === 'PUT') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $opts[CURLOPT_POSTFIELDS]    = json_encode($body ?? [], JSON_UNESCAPED_UNICODE);
        }

        if ($certPath !== '' && is_file($certPath)) {
            $opts[CURLOPT_SSLCERT] = $certPath;
        }
        if ($keyPath !== '' && is_file($keyPath)) {
            $opts[CURLOPT_SSLKEY] = $keyPath;
        }

        curl_setopt_array($ch, $opts);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $curlErr !== '') {
            return ['error' => 'cURL: ' . ($curlErr ?: 'falha na requisicao')];
        }

        $decoded = json_decode((string) $raw, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $errMsg = is_array($decoded) ? json_encode($decoded) : (string) $raw;
            return ['error' => 'HTTP ' . $httpCode . ': ' . $errMsg];
        }

        return is_array($decoded) ? $decoded : ['raw' => (string) $raw];
    }

    private function buildBoletoBody(array $payload): array
    {
        $invoice     = $payload['invoice'];
        $student     = $payload['student'];
        $beneficiary = $payload['beneficiary'];
        $nossoNumero = $this->nossoNumero((int) $invoice['id']);

        $dueDate = (string) ($invoice['due_date'] ?? date('Y-m-d'));
        $amount  = (float) ($invoice['amount'] ?? 0);

        return [
            'etapa_processo_boleto' => 'validacao',
            'beneficiario' => [
                'id_beneficiario' => $beneficiary['id_beneficiario'],
            ],
            'dado_boleto' => [
                'descricao_instrumento_cobranca' => 'boleto',
                'tipo_boleto'                    => 'a vista',
                'codigo_carteira'                => $beneficiary['carteira'],
                'valor_total_titulo'             => $amount,
                'codigo_especie'                 => '99',
                'data_emissao'                   => date('Y-m-d'),
                'pagador' => [
                    'pessoa' => [
                        'nome_pessoa' => (string) ($student['name'] ?? ''),
                        'tipo_pessoa' => [
                            'codigo_tipo_pessoa' => 'F',
                        ],
                        'numero_cadastro_pessoa_fisica' => preg_replace('/\D/', '', (string) ($student['document'] ?? '')),
                    ],
                ],
                'dados_individuais_boleto' => [
                    [
                        'numero_nosso_numero' => $nossoNumero,
                        'data_vencimento'     => $dueDate,
                        'valor_titulo'        => $amount,
                        'texto_seu_numero'    => (string) ($invoice['number'] ?? ('INV-' . $invoice['id'])),
                    ],
                ],
            ],
        ];
    }

    private function parseBoletoResponse(array $response): array
    {
        $dados     = $response['dado_boleto']['dados_individuais_boleto'][0] ?? [];
        $nosso     = (string) ($dados['numero_nosso_numero'] ?? '');
        $linha     = (string) ($dados['linha_digitavel'] ?? $dados['codigo_barra_numerico'] ?? '');
        $barcode   = (string) ($dados['codigo_barras'] ?? $dados['codigo_barra_numerico'] ?? '');
        $boletoUrl = (string) ($dados['url_boleto'] ?? $response['url_boleto'] ?? '');
        $pdfUrl    = (string) ($dados['url_pdf'] ?? $response['url_pdf'] ?? '');
        $extId     = (string) ($response['id_boleto'] ?? $response['id'] ?? $nosso);

        return [
            'sent'             => true,
            'status'           => 'issued',
            'message'          => 'Boleto emitido com sucesso pelo Itau.',
            'external_id'      => $extId !== '' ? $extId : null,
            'nosso_numero'     => $nosso !== '' ? $nosso : null,
            'digitable_line'   => $linha !== '' ? $linha : null,
            'barcode'          => $barcode !== '' ? $barcode : null,
            'pix_qr_code'      => null,
            'pix_copy_paste'   => null,
            'boleto_url'       => $boletoUrl !== '' ? $boletoUrl : null,
            'pdf_url'          => $pdfUrl !== '' ? $pdfUrl : null,
            'expires_at'       => null,
            'response_payload' => $response,
        ];
    }

    private function nossoNumero(int $invoiceId): string
    {
        return str_pad((string) $invoiceId, 10, '0', STR_PAD_LEFT);
    }

    private function setting(string $key, $default = null)
    {
        if (array_key_exists($key, $this->settings)) {
            return $this->settings[$key];
        }

        return $default;
    }
}

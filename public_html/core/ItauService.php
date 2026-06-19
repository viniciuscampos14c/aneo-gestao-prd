<?php

class ItauService
{
    private array $settings;

    private const STS_URL        = 'https://sts.itau.com.br/api/oauth/token';
    private const BASE_SANDBOX   = 'https://sandbox.api.itau.com.br';
    private const BASE_PRODUCTION = 'https://secure.api.cloud.itau.com.br';
    private const BASE_PRODUCTION_PIX = 'https://secure.api.itau';
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
                'message'          => 'Integração Itau não configurada. Registro pendente criado.',
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
                'status'           => 'failed',
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
        $path     = $this->isBoletoPix() ? '/pix_recebimentos_conciliacoes/v2/boletos_pix' : '/boletoscash/v2/boletos';
        $response = $this->callApi('POST', $path, $body);

        if (!empty($response['error'])) {
            return [
                'sent'             => true,
                'status'           => 'failed',
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
                'message'        => 'Integração Itau não configurada.',
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

        $isBoletoPix = $this->isBoletoPix();
        $externalId  = trim((string) ($bankSlip['external_id'] ?? ''));
        $nossoNumero = trim((string) ($bankSlip['nosso_numero'] ?? ''));
        if (!$isBoletoPix && $nossoNumero === '') {
            $nossoNumero = $externalId;
        }
        $idBeneficiario = (string) $this->setting('id_beneficiario', '');

        if ($nossoNumero === '' || $idBeneficiario === '') {
            return [
                'sent'             => false,
                'status'           => (string) ($bankSlip['status'] ?? 'pending'),
                'message'          => 'Nosso número ou ID beneficiário não configurado para consulta.',
                'external_id'      => $bankSlip['external_id'] ?? null,
                'nosso_numero'     => $bankSlip['nosso_numero'] ?? null,
                'digitable_line'   => $bankSlip['digitable_line'] ?? null,
                'barcode'          => $bankSlip['barcode'] ?? null,
                'pix_qr_code'      => $bankSlip['pix_qr_code'] ?? null,
                'pix_copy_paste'   => $bankSlip['pix_copy_paste'] ?? null,
                'boleto_url'       => $bankSlip['boleto_url'] ?? null,
                'pdf_url'          => $bankSlip['pdf_url'] ?? null,
                'paid_at'          => $bankSlip['paid_at'] ?? null,
                'paid_amount'      => null,
                'expires_at'       => $bankSlip['expires_at'] ?? null,
                'response_payload' => null,
            ];
        }

        $carteira = (string) $this->setting('carteira', '109');
        $path = '/boletoscash/v2/boletos?id_beneficiario=' . rawurlencode($idBeneficiario)
            . '&codigo_carteira=' . rawurlencode($carteira)
            . '&nosso_numero=' . rawurlencode($nossoNumero);
        $response = $this->callApi('GET', $path, null);

        if (!empty($response['error'])) {
            return [
                'sent'             => true,
                'status'           => (string) ($bankSlip['status'] ?? 'processing'),
                'message'          => 'Erro ao consultar boleto: ' . $response['error'],
                'external_id'      => $bankSlip['external_id'] ?? null,
                'nosso_numero'     => $nossoNumero !== '' ? $nossoNumero : ($bankSlip['nosso_numero'] ?? null),
                'digitable_line'   => $bankSlip['digitable_line'] ?? null,
                'barcode'          => $bankSlip['barcode'] ?? null,
                'pix_qr_code'      => $bankSlip['pix_qr_code'] ?? null,
                'pix_copy_paste'   => $bankSlip['pix_copy_paste'] ?? null,
                'boleto_url'       => $bankSlip['boleto_url'] ?? null,
                'pdf_url'          => $bankSlip['pdf_url'] ?? null,
                'paid_at'          => $bankSlip['paid_at'] ?? null,
                'paid_amount'      => null,
                'expires_at'       => $bankSlip['expires_at'] ?? null,
                'response_payload' => $response,
            ];
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : $response;
        if (is_array($data) && array_is_list($data)) {
            $data = $data[0] ?? [];
        }
        $detalhes = $data['dado_boleto']['dados_individuais_boleto'][0]
            ?? $data['dado_boleto']['dados_individuais_boleto']
            ?? $data['dados_individuais_boleto'][0]
            ?? $data['dados_individuais_boleto']
            ?? $data;
        $situacao = strtolower(trim((string) (
            $detalhes['situacao_geral_boleto']
            ?? $detalhes['status']
            ?? $detalhes['situacao']
            ?? $detalhes['codigo_situacao']
            ?? $data['status']
            ?? ''
        )));
        $pagamentos = $data['dado_boleto']['pagamentos_cobranca']
            ?? $detalhes['pagamentos_cobranca']
            ?? $data['pagamentos_cobranca']
            ?? [];
        if (is_array($pagamentos) && !array_is_list($pagamentos)) {
            $pagamentos = [$pagamentos];
        }
        $ultimoPagamento = is_array($pagamentos) && $pagamentos !== []
            ? end($pagamentos)
            : [];
        if (!is_array($ultimoPagamento)) {
            $ultimoPagamento = [];
        }
        $valorPagoRaw = $ultimoPagamento['valor_pago_total_cobranca']
            ?? $detalhes['valor_pago']
            ?? $detalhes['valor_total_pago']
            ?? $detalhes['valor_titulo']
            ?? $data['valor_pago']
            ?? null;
        $valorPago = $valorPagoRaw !== null ? $this->parseMoneyValue($valorPagoRaw) : null;
        $dataPagamento = $this->parseDateValue(
            $ultimoPagamento['data_inclusao_pagamento']
                ?? $detalhes['data_pagamento']
                ?? $detalhes['data_liquidacao']
                ?? $data['data_pagamento']
                ?? $data['data_liquidacao']
                ?? null
        );
        $pagoStatus = ['paga', 'pago', 'baixada', 'baixado', 'liquidado', 'liquidada', 'recebido', 'recebida', 'paid', 'received', 'liquidated'];
        $isPago = in_array($situacao, $pagoStatus, true) || str_contains($situacao, 'liquid') || str_contains($situacao, 'baixad') || str_contains($situacao, 'pag');
        $pixData = $data['dados_qrcode'] ?? $detalhes['dados_qrcode'] ?? [];
        $linha = (string) ($detalhes['numero_linha_digitavel'] ?? $detalhes['linha_digitavel'] ?? $data['numero_linha_digitavel'] ?? $bankSlip['digitable_line'] ?? '');
        $barcode = (string) ($detalhes['codigo_barras'] ?? $detalhes['codigo_barra_numerico'] ?? $data['codigo_barras'] ?? $data['codigo_barra_numerico'] ?? $bankSlip['barcode'] ?? '');

        return [
            'sent'             => true,
            'status'           => $isPago ? 'paid' : 'issued',
            'message'          => 'Status sincronizado: ' . ($situacao !== '' ? $situacao : 'ativo'),
            'external_id'      => $bankSlip['external_id'] ?? null,
            'nosso_numero'     => $nossoNumero !== '' ? $nossoNumero : ($bankSlip['nosso_numero'] ?? null),
            'digitable_line'   => $linha !== '' ? $linha : ($bankSlip['digitable_line'] ?? null),
            'barcode'          => $barcode !== '' ? $barcode : ($bankSlip['barcode'] ?? null),
            'pix_qr_code'      => $pixData['base64'] ?? $pixData['imagem'] ?? $bankSlip['pix_qr_code'] ?? null,
            'pix_copy_paste'   => $pixData['emv'] ?? $pixData['pix_copia_e_cola'] ?? $bankSlip['pix_copy_paste'] ?? null,
            'boleto_url'       => $bankSlip['boleto_url'] ?? null,
            'pdf_url'          => $bankSlip['pdf_url'] ?? null,
            'paid_at'          => $isPago ? ($dataPagamento ?? date('Y-m-d')) : ($bankSlip['paid_at'] ?? null),
            'paid_amount'      => $isPago ? $valorPago : null,
            'expires_at'       => $bankSlip['expires_at'] ?? null,
            'response_payload' => $response,
        ];
    }

    public function registerWebhook(string $webhookUrl): array
    {
        $idBeneficiario = (string) $this->setting('id_beneficiario', '');

        if ($idBeneficiario === '') {
            return ['ok' => false, 'message' => 'ID Beneficiário não configurado.'];
        }

        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'message' => 'URL do webhook inválida.'];
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
        if (!$isSandbox && str_starts_with($path, '/pix_recebimentos_conciliacoes/')) {
            $baseUrl = self::BASE_PRODUCTION_PIX;
        }
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
        $ourNumberText = $this->shortSeuNumero($invoice);
        $document = preg_replace('/\D/', '', (string) ($student['document'] ?? ''));
        $pixKey = trim((string) $this->setting('chave_pix', ''));
        $instrument = $pixKey !== ''
            ? (string) $this->setting('instrument', 'boleto_pix')
            : (string) $this->setting('instrument', 'boleto');

        $person = [
            'nome_pessoa' => (string) ($student['name'] ?? ''),
            'tipo_pessoa' => [
                'codigo_tipo_pessoa' => strlen($document) === 14 ? 'J' : 'F',
            ],
        ];

        if (strlen($document) === 14) {
            $person['tipo_pessoa']['numero_cadastro_nacional_pessoa_juridica'] = $document;
        } elseif (strlen($document) === 11) {
            $person['tipo_pessoa']['numero_cadastro_pessoa_fisica'] = $document;
        }

        $body = [
            'etapa_processo_boleto' => (string) $this->setting('process_stage', 'efetivacao'),
            'beneficiario' => [
                'id_beneficiario' => $beneficiary['id_beneficiario'],
            ],
            'dado_boleto' => [
                'descricao_instrumento_cobranca' => $instrument,
                'tipo_boleto'                    => 'a vista',
                'texto_seu_numero'               => $ourNumberText,
                'codigo_carteira'                => $beneficiary['carteira'],
                'codigo_especie'                 => (string) $this->setting('codigo_especie', '01'),
                'data_emissao'                   => date('Y-m-d'),
                'pagador' => [
                    'pessoa' => $person,
                ],
                'dados_individuais_boleto' => [
                    [
                        'numero_nosso_numero'    => $nossoNumero,
                        'data_vencimento'        => $dueDate < date('Y-m-d') ? date('Y-m-d') : $dueDate,
                        'valor_titulo'           => $this->formatItauAmount($amount),
                        'texto_seu_numero'       => $ourNumberText,
                        'texto_uso_beneficiario' => (string) ($invoice['number'] ?? $invoice['id']),
                    ],
                ],
            ],
        ];

        $messages = $this->billingMessages();
        if ($messages !== []) {
            $body['dado_boleto']['lista_mensagem_cobranca'] = array_map(
                static fn (string $message): array => ['mensagem' => substr($message, 0, 78)],
                $messages
            );
        }

        $interest = $this->interestSettings($dueDate);
        if ($interest !== []) {
            $body['dado_boleto']['juros'] = $interest;
        }

        $fine = $this->fineSettings($dueDate);
        if ($fine !== []) {
            $body['dado_boleto']['multa'] = $fine;
        }

        if ($pixKey !== '') {
            $body['dados_qrcode'] = [
                'chave' => $pixKey,
                'tipo_cobranca' => 'cob',
                'txid' => $this->pixTxid((int) $invoice['id']),
            ];
        }

        return $body;
    }

    private function parseBoletoResponse(array $response): array
    {
        $responsePayload = is_array($response['data'] ?? null) ? $response['data'] : $response;
        $dados     = $responsePayload['dado_boleto']['dados_individuais_boleto'][0] ?? [];
        $qrcode    = $responsePayload['dados_qrcode'] ?? $responsePayload['dado_boleto']['dados_qrcode'] ?? [];
        $nosso     = (string) ($dados['numero_nosso_numero'] ?? '');
        $linha     = (string) ($dados['numero_linha_digitavel'] ?? $dados['linha_digitavel'] ?? $dados['codigo_barra_numerico'] ?? '');
        $barcode   = (string) ($dados['codigo_barras'] ?? $dados['codigo_barra_numerico'] ?? '');
        $boletoUrl = (string) ($dados['url_boleto'] ?? $responsePayload['url_boleto'] ?? '');
        $pdfUrl    = (string) ($dados['url_pdf'] ?? $responsePayload['url_pdf'] ?? '');
        $extId     = (string) ($responsePayload['id_boleto'] ?? $responsePayload['id'] ?? $dados['id_boleto_individual'] ?? $nosso);
        $pixCopyPaste = (string) ($qrcode['emv'] ?? $qrcode['pixCopiaECola'] ?? $qrcode['copy_paste'] ?? '');
        $pixQrCode = (string) ($qrcode['base64'] ?? $qrcode['imagem'] ?? '');

        return [
            'sent'             => true,
            'status'           => 'issued',
            'message'          => 'Boleto emitido com sucesso pelo Itau.',
            'external_id'      => $extId !== '' ? $extId : null,
            'nosso_numero'     => $nosso !== '' ? $nosso : null,
            'digitable_line'   => $linha !== '' ? $linha : null,
            'barcode'          => $barcode !== '' ? $barcode : null,
            'pix_qr_code'      => $pixQrCode !== '' ? $pixQrCode : null,
            'pix_copy_paste'   => $pixCopyPaste !== '' ? $pixCopyPaste : null,
            'boleto_url'       => $boletoUrl !== '' ? $boletoUrl : null,
            'pdf_url'          => $pdfUrl !== '' ? $pdfUrl : null,
            'expires_at'       => null,
            'response_payload' => $response,
        ];
    }

    private function nossoNumero(int $invoiceId): string
    {
        $prefix = preg_replace('/\D/', '', (string) $this->setting('nosso_numero_prefix', '02'));
        $prefix = substr($prefix !== '' ? $prefix : '02', 0, 2);

        return $prefix . str_pad((string) $invoiceId, 6, '0', STR_PAD_LEFT);
    }

    private function formatItauAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function parseMoneyValue($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value > 999 ? $value / 100 : (float) $value;
        }

        if (is_float($value)) {
            return $value;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $raw)) {
            $intValue = (int) $raw;
            return strlen($raw) > 3 ? $intValue / 100 : (float) $intValue;
        }

        $normalized = str_contains($raw, ',')
            ? str_replace(['.', ','], ['', '.'], $raw)
            : $raw;
        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function parseDateValue($value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($raw))->format('Y-m-d');
        } catch (Throwable $e) {
            return null;
        }
    }

    private function pixTxid(int $invoiceId): string
    {
        $hash = substr(sha1('aneo-itau-' . $invoiceId), 0, 16);

        return substr('BL' . str_pad((string) $invoiceId, 8, '0', STR_PAD_LEFT) . $hash, 0, 35);
    }

    private function shortSeuNumero(array $invoice): string
    {
        $id = (int) ($invoice['id'] ?? 0);
        if ($id > 0) {
            return substr('F' . str_pad((string) $id, 9, '0', STR_PAD_LEFT), 0, 10);
        }

        return substr(preg_replace('/\W+/', '', (string) ($invoice['number'] ?? 'FATURA')), 0, 10);
    }

    private function isBoletoPix(): bool
    {
        return trim((string) $this->setting('chave_pix', '')) !== ''
            && (string) $this->setting('instrument', 'boleto_pix') === 'boleto_pix';
    }

    private function billingMessages(): array
    {
        $messages = [];
        for ($i = 1; $i <= 5; $i++) {
            $message = trim((string) $this->setting('instrucao_' . $i, ''));
            if ($message !== '') {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    private function interestSettings(string $dueDate): array
    {
        $type = trim((string) $this->setting('codigo_tipo_juros', ''));
        if ($type === '' || $type === '05') {
            return [];
        }

        $interest = ['codigo_tipo_juros' => $type];
        $days = trim((string) $this->setting('dias_juros', ''));
        $interestDate = $this->dateAfterDueDate($dueDate, $days);
        if ($interestDate !== null) {
            $interest['data_juros'] = $interestDate;
        }

        if ($type === '93') {
            $value = trim((string) $this->setting('valor_juros', ''));
            if ($value !== '') {
                $interest['valor_juros'] = $value;
            }
        } else {
            $percentage = trim((string) $this->setting('percentual_juros', ''));
            if ($percentage !== '') {
                $interest['percentual_juros'] = $percentage;
            }
        }

        return $interest;
    }

    private function fineSettings(string $dueDate): array
    {
        $type = trim((string) $this->setting('codigo_tipo_multa', ''));
        if ($type === '' || $type === '03') {
            return [];
        }

        $fine = ['codigo_tipo_multa' => $type];
        $days = trim((string) $this->setting('dias_multa', ''));
        $fineDate = $this->dateAfterDueDate($dueDate, $days);
        if ($fineDate !== null) {
            $fine['data_multa'] = $fineDate;
        }

        if ($type === '01') {
            $value = trim((string) $this->setting('valor_multa', ''));
            if ($value !== '') {
                $fine['valor_multa'] = $value;
            }
        } elseif ($type === '02') {
            $percentage = trim((string) $this->setting('percentual_multa', ''));
            if ($percentage !== '') {
                $fine['percentual_multa'] = $percentage;
            }
        }

        return $fine;
    }

    private function dateAfterDueDate(string $dueDate, string $days): ?string
    {
        if ($days === '' || !is_numeric($days)) {
            return null;
        }

        try {
            return (new DateTimeImmutable($dueDate))
                ->modify('+' . max(0, (int) $days) . ' days')
                ->format('Y-m-d');
        } catch (Throwable $e) {
            return null;
        }
    }

    private function setting(string $key, $default = null)
    {
        if (array_key_exists($key, $this->settings)) {
            return $this->settings[$key];
        }

        return $default;
    }
}

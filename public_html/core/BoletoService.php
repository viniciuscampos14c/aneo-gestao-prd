<?php

class BoletoService
{
    private array $settings;

    public function __construct(?int $companyId = null)
    {
        $companyId = (int) ($companyId ?? current_company_id() ?? 0);
        $this->settings = (new CompanyIntegrationModel())->mergeWithGlobalConfig('bank_slip', $companyId);
    }

    public function provider(): string
    {
        return (string) $this->setting('provider', 'manual');
    }

    public function isEnabled(): bool
    {
        return (bool) $this->setting('enabled', false);
    }

    public function buildPayload(array $invoice, array $student, ?array $existing = null): array
    {
        $existing = $existing ?? [];

        return [
            'provider' => $this->provider(),
            'environment' => (string) $this->setting('environment', 'sandbox'),
            'invoice' => [
                'id' => (int) $invoice['id'],
                'number' => (string) $invoice['invoice_number'],
                'amount' => (float) $invoice['amount'],
                'paid_amount' => (float) ($invoice['paid_amount'] ?? 0),
                'due_date' => (string) ($invoice['due_date'] ?? ''),
                'tags' => (string) ($invoice['tags'] ?? ''),
                'project' => (string) ($invoice['project_name'] ?? ''),
                'existing_boleto_url' => (string) ($invoice['boleto_url'] ?? ''),
            ],
            'student' => [
                'id' => (int) $student['id'],
                'name' => (string) ($student['full_name'] ?? ''),
                'email' => (string) ($student['email_primary'] ?? ''),
                'phone' => (string) ($student['phone'] ?? ''),
                'document' => (string) ($student['rg'] ?? ''),
                'ra' => (string) ($student['ra'] ?? ''),
            ],
            'beneficiary' => [
                'name' => (string) $this->setting('beneficiary_name', ''),
                'document' => (string) $this->setting('beneficiary_document', ''),
                'wallet' => (string) $this->setting('wallet', ''),
            ],
            'existing' => [
                'external_id' => (string) ($existing['external_id'] ?? ''),
                'status' => (string) ($existing['status'] ?? ''),
            ],
            'generated_at' => now(),
        ];
    }

    public function requestGeneration(array $payload, ?array $existing = null): array
    {
        $existing = $existing ?? [];

        if (!$this->isEnabled()) {
            return [
                'sent' => false,
                'status' => 'pending',
                'message' => 'Integração de boleto não configurada. Registro pendente criado.',
                'external_id' => $existing['external_id'] ?? null,
                'digitable_line' => $existing['digitable_line'] ?? null,
                'barcode' => $existing['barcode'] ?? null,
                'pix_qr_code' => $existing['pix_qr_code'] ?? null,
                'pix_copy_paste' => $existing['pix_copy_paste'] ?? null,
                'boleto_url' => $payload['invoice']['existing_boleto_url'] ?: ($existing['boleto_url'] ?? null),
                'pdf_url' => $existing['pdf_url'] ?? null,
                'expires_at' => null,
                'response_payload' => null,
            ];
        }

        return [
            'sent' => false,
            'status' => 'processing',
            'message' => 'Integração de boleto habilitada, aguardando implementacao do cliente API.',
            'external_id' => $existing['external_id'] ?? null,
            'digitable_line' => null,
            'barcode' => null,
            'pix_qr_code' => null,
            'pix_copy_paste' => null,
            'boleto_url' => $payload['invoice']['existing_boleto_url'] ?: null,
            'pdf_url' => null,
            'expires_at' => null,
            'response_payload' => null,
        ];
    }

    public function requestStatus(array $bankSlip): array
    {
        if (!$this->isEnabled()) {
            return [
                'sent' => false,
                'status' => (string) ($bankSlip['status'] ?? 'pending'),
                'message' => 'Integração de boleto ainda não configurada.',
                'external_id' => $bankSlip['external_id'] ?? null,
                'digitable_line' => $bankSlip['digitable_line'] ?? null,
                'barcode' => $bankSlip['barcode'] ?? null,
                'pix_qr_code' => $bankSlip['pix_qr_code'] ?? null,
                'pix_copy_paste' => $bankSlip['pix_copy_paste'] ?? null,
                'boleto_url' => $bankSlip['boleto_url'] ?? null,
                'pdf_url' => $bankSlip['pdf_url'] ?? null,
                'paid_at' => $bankSlip['paid_at'] ?? null,
                'paid_amount' => null,
                'expires_at' => $bankSlip['expires_at'] ?? null,
                'response_payload' => null,
            ];
        }

        return [
            'sent' => false,
            'status' => (string) ($bankSlip['status'] ?? 'processing'),
            'message' => 'Sincronizacao habilitada, aguardando implementacao do cliente API.',
            'external_id' => $bankSlip['external_id'] ?? null,
            'digitable_line' => $bankSlip['digitable_line'] ?? null,
            'barcode' => $bankSlip['barcode'] ?? null,
            'pix_qr_code' => $bankSlip['pix_qr_code'] ?? null,
            'pix_copy_paste' => $bankSlip['pix_copy_paste'] ?? null,
            'boleto_url' => $bankSlip['boleto_url'] ?? null,
            'pdf_url' => $bankSlip['pdf_url'] ?? null,
            'paid_at' => $bankSlip['paid_at'] ?? null,
            'paid_amount' => null,
            'expires_at' => $bankSlip['expires_at'] ?? null,
            'response_payload' => null,
        ];
    }

    private function setting(string $key, $default = null)
    {
        if (array_key_exists($key, $this->settings)) {
            return $this->settings[$key];
        }

        return $default;
    }
}

<?php

class FiscalInvoiceService
{
    private array $settings;

    public function __construct(?int $companyId = null)
    {
        $companyId = (int) ($companyId ?? current_company_id() ?? 0);
        $this->settings = (new CompanyIntegrationModel())->mergeWithGlobalConfig('fiscal', $companyId);
    }

    public function provider(): string
    {
        return (string) $this->setting('provider', 'manual');
    }

    public function isEnabled(): bool
    {
        return (bool) $this->setting('enabled', false);
    }

    public function buildPayload(array $invoice, array $student): array
    {
        return [
            'provider' => $this->provider(),
            'environment' => (string) $this->setting('environment', 'sandbox'),
            'invoice' => [
                'id' => (int) $invoice['id'],
                'number' => (string) $invoice['invoice_number'],
                'amount' => (float) $invoice['amount'],
                'tax_amount' => (float) ($invoice['tax_amount'] ?? 0),
                'paid_at' => (string) ($invoice['paid_at'] ?? ''),
                'project' => (string) ($invoice['project_name'] ?? ''),
                'tags' => (string) ($invoice['tags'] ?? ''),
            ],
            'student' => [
                'id' => (int) $student['id'],
                'name' => (string) ($student['full_name'] ?? ''),
                'email' => (string) ($student['email_primary'] ?? ''),
                'phone' => (string) ($student['phone'] ?? ''),
                'document' => (string) ($student['rg'] ?? ''),
                'ra' => (string) ($student['ra'] ?? ''),
            ],
            'issuer' => [
                'company_name' => (string) $this->setting('company_name', ''),
                'company_document' => (string) $this->setting('company_document', ''),
            ],
            'generated_at' => now(),
        ];
    }

    public function requestEmission(array $payload): array
    {
        if (!$this->isEnabled()) {
            return [
                'sent' => false,
                'status' => 'pending',
                'message' => 'Integração fiscal não configurada. Registro pendente criado.',
                'external_id' => null,
                'number' => null,
                'response_payload' => null,
            ];
        }

        return [
            'sent' => false,
            'status' => 'pending',
            'message' => 'Integração fiscal habilitada, aguardando implementacao do cliente API.',
            'external_id' => null,
            'number' => null,
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

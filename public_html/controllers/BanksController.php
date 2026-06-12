<?php

class BanksController extends BaseController
{
    private CompanyIntegrationModel $integrations;
    private CompanyModel $companies;
    private AuditLogService $audit;

    public function __construct()
    {
        $this->integrations = new CompanyIntegrationModel();
        $this->companies = new CompanyModel();
        $this->audit = new AuditLogService();
    }

    public function index(): void
    {
        require_auth();
        require_permission('banks');

        $companyId = (int) (current_company_id() ?? 0);
        $itauRow = $this->integrations->tableExists() ? $this->integrations->get($companyId, 'itau') : null;
        $itauEnabled = !empty($itauRow['is_enabled']);
        $itauSettings = $itauRow ? (is_array($itauRow['settings'] ?? null) ? $itauRow['settings'] : []) : [];
        $itauEnvironment = (string) ($itauSettings['environment'] ?? config('itau.environment', 'sandbox'));

        $banks = [
            [
                'key'         => 'itau',
                'label'       => 'Banco Itaú',
                'icon'        => 'building-library',
                'enabled'     => $itauEnabled,
                'environment' => $itauEnvironment,
                'route'       => 'banks/itau',
            ],
        ];

        $this->render('banks/index', [
            'title' => 'Bancos',
            'banks' => $banks,
        ]);
    }

    public function itauSettings(): void
    {
        require_auth();
        require_permission('banks');

        $companyId = (int) (current_company_id() ?? 0);
        $settings = $this->integrations->tableExists()
            ? $this->integrations->mergeWithGlobalConfig('itau', $companyId)
            : config('itau', []);

        $settings = $this->normalizeItauSettings($settings);

        $webhookUrl = $this->buildItauWebhookUrl($settings);

        $this->render('banks/itau', [
            'title'       => 'Configuração Itaú',
            'settings'    => $settings,
            'webhookUrl'  => $webhookUrl,
            'companyId'   => $companyId,
        ]);
    }

    public function saveItauSettings(): void
    {
        require_auth();
        require_permission('banks');
        csrf_validate();

        if (!$this->integrations->tableExists()) {
            $this->error('Tabela company_integrations nao existe. Execute a migration de fase 2.');
            $this->redirect('banks/itau');
        }

        $companyId = (int) (current_company_id() ?? 0);
        if ($companyId <= 0) {
            $this->error('Nenhuma empresa selecionada na sessao.');
            $this->redirect('banks/itau');
        }

        $existing = $this->integrations->get($companyId, 'itau');
        $existingSettings = is_array($existing['settings'] ?? null) ? $existing['settings'] : [];

        $collected = $this->collectItauSettings($existingSettings);
        $changedBy = (int) (current_user()['id'] ?? 0);

        $this->integrations->save($companyId, 'itau', $collected['enabled'], $collected['settings'], $changedBy);

        $paymentMethods = new PaymentMethodModel();
        if ($paymentMethods->tableExists()) {
            $paymentMethods->syncIntegratedContractMethod(
                $companyId,
                'itau',
                'ITAU - Boleto API',
                'boleto',
                (bool) $collected['enabled'],
                $changedBy,
                ['integration_key' => 'itau']
            );
        }

        $this->audit->log([
            'module'       => 'cadastro.bancos',
            'action'       => 'update',
            'entity_type'  => 'bank_integration',
            'entity_id'    => $companyId,
            'entity_label' => 'Itaú',
            'description'  => 'Configuracao do Banco Itau atualizada.',
            'before'       => [],
            'after'        => ['enabled' => $collected['enabled']],
            'company_id'   => $companyId,
        ]);

        $this->success('Configuracao do Itau salva com sucesso.');
        $this->redirect('banks/itau');
    }

    public function registerWebhook(): void
    {
        require_auth();
        require_permission('banks');

        header('Content-Type: application/json; charset=utf-8');

        $companyId = (int) (current_company_id() ?? 0);
        if ($companyId <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Nenhuma empresa selecionada.']);
            return;
        }

        $settings = $this->integrations->tableExists()
            ? $this->integrations->mergeWithGlobalConfig('itau', $companyId)
            : config('itau', []);
        $webhookUrl = $this->buildItauWebhookUrl($settings);

        try {
            $service = new ItauService($companyId);

            if (!$service->isEnabled()) {
                echo json_encode(['ok' => false, 'message' => 'Integração Itau nao esta ativada para esta empresa.']);
                return;
            }

            $result = $service->registerWebhook($webhookUrl);
            echo json_encode([
                'ok'      => (bool) ($result['ok'] ?? false),
                'message' => (string) ($result['message'] ?? 'Webhook registrado.'),
            ]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        }
    }

    private function collectItauSettings(array $existingSettings = []): array
    {
        $clientSecret = trim((string) post('itau_client_secret'));
        if ($clientSecret === '') {
            $clientSecret = (string) ($existingSettings['client_secret'] ?? '');
        }

        $keyPath = trim((string) post('itau_key_path'));
        if ($keyPath === '') {
            $keyPath = (string) ($existingSettings['key_path'] ?? '');
        }

        $webhookToken = trim((string) post('itau_webhook_token'));
        if ($webhookToken === '') {
            $webhookToken = (string) ($existingSettings['webhook_token'] ?? bin2hex(random_bytes(16)));
        }

        $settings = [
            'environment'      => in_array(trim((string) post('itau_environment')), ['sandbox', 'production'], true)
                ? trim((string) post('itau_environment'))
                : 'sandbox',
            'client_id'        => trim((string) post('itau_client_id')),
            'client_secret'    => $clientSecret,
            'id_beneficiario'  => trim((string) post('itau_id_beneficiario')),
            'agencia'          => trim((string) post('itau_agencia')),
            'conta'            => trim((string) post('itau_conta')),
            'conta_dv'         => trim((string) post('itau_conta_dv')),
            'carteira'         => trim((string) post('itau_carteira')),
            'chave_pix'        => trim((string) post('itau_chave_pix')),
            'beneficiary_name' => trim((string) post('itau_beneficiary_name')),
            'beneficiary_cnpj' => preg_replace('/\D/', '', (string) post('itau_beneficiary_cnpj')),
            'cert_path'        => trim((string) post('itau_cert_path')),
            'key_path'         => $keyPath,
            'webhook_token'    => $webhookToken,
        ];

        foreach ($settings as $key => $value) {
            if ($value === '') {
                unset($settings[$key]);
            }
        }

        return [
            'enabled'  => post('itau_enabled') ? true : false,
            'settings' => $settings,
        ];
    }

    private function normalizeItauSettings(array $settings): array
    {
        $settings['enabled'] = !empty($settings['enabled']);
        $settings['environment'] = in_array((string) ($settings['environment'] ?? ''), ['sandbox', 'production'], true)
            ? (string) $settings['environment']
            : 'sandbox';
        $settings['client_id']        = (string) ($settings['client_id'] ?? '');
        $settings['client_secret']    = (string) ($settings['client_secret'] ?? '');
        $settings['id_beneficiario']  = (string) ($settings['id_beneficiario'] ?? '');
        $settings['agencia']          = (string) ($settings['agencia'] ?? '');
        $settings['conta']            = (string) ($settings['conta'] ?? '');
        $settings['conta_dv']         = (string) ($settings['conta_dv'] ?? '');
        $settings['carteira']         = (string) ($settings['carteira'] ?? '');
        $settings['chave_pix']        = (string) ($settings['chave_pix'] ?? '');
        $settings['beneficiary_name'] = (string) ($settings['beneficiary_name'] ?? '');
        $settings['beneficiary_cnpj'] = (string) ($settings['beneficiary_cnpj'] ?? '');
        $settings['cert_path']        = (string) ($settings['cert_path'] ?? '');
        $settings['key_path']         = (string) ($settings['key_path'] ?? '');
        $settings['webhook_token']    = (string) ($settings['webhook_token'] ?? '');

        return $settings;
    }

    private function buildItauWebhookUrl(array $settings): string
    {
        $baseUrl = trim((string) config('app.base_url', ''));
        if ($baseUrl === '') {
            $baseUrl = trim((string) config('app.public_url', ''));
        }

        $webhookUrl = rtrim($baseUrl, '/') . '/index.php?route=finance/webhook/itau';
        $token = trim((string) ($settings['webhook_token'] ?? ''));
        if ($token !== '') {
            $webhookUrl .= '&token=' . rawurlencode($token);
        }

        return $webhookUrl;
    }
}

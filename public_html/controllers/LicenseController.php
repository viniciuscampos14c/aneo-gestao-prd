<?php

class LicenseController extends BaseController
{
    private CompanyModel $companies;
    private LicenseService $licenses;
    private AuditLogService $audit;

    public function __construct()
    {
        $this->companies = new CompanyModel();
        $this->licenses = new LicenseService();
        $this->audit = new AuditLogService();
    }

    public function index(): void
    {
        require_admin();

        $companyOptions = $this->companies->activeCompanies();
        $selectedCompanyId = (int) request('company_id', (int) (current_company_id() ?? 0));

        if ($selectedCompanyId <= 0 && $companyOptions !== []) {
            $selectedCompanyId = (int) ($companyOptions[0]['id'] ?? 0);
        }

        $selectedCompany = $selectedCompanyId > 0 ? $this->companies->find($selectedCompanyId) : null;
        if (!$selectedCompany && $companyOptions !== []) {
            $selectedCompanyId = (int) ($companyOptions[0]['id'] ?? 0);
            $selectedCompany = $selectedCompanyId > 0 ? $this->companies->find($selectedCompanyId) : null;
        }

        $status = $this->licenses->currentStatus($selectedCompanyId);
        if ($selectedCompanyId > 0 && $this->licenses->available()) {
            $this->licenses->touchCheck($selectedCompanyId);
        }

        $history = $selectedCompanyId > 0 ? $this->licenses->historyByCompany($selectedCompanyId, 30) : [];

        $this->render('companies/license', [
            'title' => 'Licenca',
            'companyOptions' => $companyOptions,
            'selectedCompanyId' => $selectedCompanyId,
            'selectedCompany' => $selectedCompany,
            'licenseStatus' => $status,
            'licenseHistory' => $history,
            'licenseTablesAvailable' => $this->licenses->available(),
            'configuredKeyLabels' => $this->licenses->listConfiguredKeyLabels(),
        ]);
    }

    public function activate(): void
    {
        require_admin();
        csrf_validate();

        $companyId = (int) post('company_id');
        $company = $companyId > 0 ? $this->companies->find($companyId) : null;
        if (!$company) {
            $this->error('Empresa inválida para ativar licenca.');
            $this->redirect('companies/license');
        }

        $key = (string) post('license_key', '');
        $note = trim((string) post('activation_note', ''));

        $before = $this->licenses->currentStatus($companyId);
        $result = $this->licenses->activateFixedKey($companyId, $key, (int) (current_user()['id'] ?? 0), $note);

        if (empty($result['ok'])) {
            $this->error((string) ($result['message'] ?? 'Falha ao ativar licenca.'));
            $this->redirect('companies/license&company_id=' . $companyId);
        }

        $after = $this->licenses->currentStatus($companyId);
        $companyName = trim((string) ($company['trade_name'] ?? '')) !== ''
            ? (string) ($company['trade_name'] ?? '')
            : (string) ($company['legal_name'] ?? ('Empresa #' . $companyId));

        $this->audit->log([
            'module' => 'cadastro.licenca',
            'action' => (string) ($result['action'] ?? 'activate'),
            'entity_type' => 'company_license',
            'entity_id' => $companyId,
            'entity_label' => $companyName,
            'description' => 'Licenca ativada/renovada ate ' . date('d/m/Y', strtotime((string) ($result['valid_until'] ?? date('Y-m-d')))) . '.',
            'before' => [
                'status' => (string) ($before['status'] ?? 'missing'),
                'valid_from' => (string) ($before['valid_from'] ?? ''),
                'valid_until' => (string) ($before['valid_until'] ?? ''),
                'license_label' => (string) ($before['license_label'] ?? ''),
                'key_hash' => (string) ($before['key_hash'] ?? ''),
            ],
            'after' => [
                'status' => (string) ($after['status'] ?? 'active'),
                'valid_from' => (string) ($after['valid_from'] ?? ''),
                'valid_until' => (string) ($after['valid_until'] ?? ''),
                'license_label' => (string) ($after['license_label'] ?? ''),
                'key_hash' => (string) ($after['key_hash'] ?? ''),
            ],
            'company_id' => $companyId,
            'metadata' => [
                'note' => $note,
                'mode' => 'fixed_key',
            ],
        ]);

        $this->success((string) $result['message']);
        $this->redirect('companies/license&company_id=' . $companyId);
    }
}

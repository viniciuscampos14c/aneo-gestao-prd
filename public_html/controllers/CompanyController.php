<?php

class CompanyController extends BaseController
{
    private CompanyModel $companies;
    private CompanyIntegrationModel $integrations;

    public function __construct()
    {
        $this->companies = new CompanyModel();
        $this->integrations = new CompanyIntegrationModel();
    }

    public function index(): void
    {
        require_auth();
        require_permission('companies');

        $filters = [
            'q' => trim((string) request('q', '')),
            'is_active' => request('is_active', ''),
        ];

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->companies->list($filters, $perPage, $page);

        $editingId = (int) request('edit', 0);
        $editing = $editingId > 0 ? $this->companies->find($editingId) : null;

        $integrationCompanyId = (int) request('integration_company_id', (int) (current_company_id() ?? 0));
        if ($integrationCompanyId <= 0 && $result['rows'] !== []) {
            $integrationCompanyId = (int) ($result['rows'][0]['id'] ?? 0);
        }
        $companyOptions = $this->companies->activeCompanies();
        if ($companyOptions === []) {
            $companyOptions = $result['rows'];
        }
        $integrationCompany = $integrationCompanyId > 0 ? $this->companies->find($integrationCompanyId) : null;
        $integrationSettings = $integrationCompany ? $this->loadIntegrationSettings($integrationCompanyId) : [];

        $this->render('companies/index', [
            'title' => 'Empresas',
            'filters' => $filters,
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
            'editing' => $editing,
            'integrationAvailable' => $this->integrations->tableExists(),
            'integrationCompanyId' => $integrationCompanyId,
            'integrationCompany' => $integrationCompany,
            'integrationSettings' => $integrationSettings,
            'companyOptions' => $companyOptions,
        ]);
    }

    public function store(): void
    {
        require_auth();
        require_permission('companies');
        csrf_validate();

        $payload = $this->collectPayload();
        if ($payload['error']) {
            $this->error($payload['error']);
            $this->redirect('companies');
        }

        try {
            $id = $this->companies->create($payload['data']);
        } catch (PDOException $e) {
            $this->error('Nao foi possivel cadastrar empresa. Verifique se o CNPJ ja existe.');
            $this->redirect('companies');
        }

        $this->success('Empresa cadastrada #' . $id . '.');
        $this->redirect('companies');
    }

    public function update(): void
    {
        require_auth();
        require_permission('companies');
        csrf_validate();

        $id = (int) post('id');
        if ($id <= 0 || !$this->companies->find($id)) {
            $this->error('Empresa nao encontrada.');
            $this->redirect('companies');
        }

        $payload = $this->collectPayload();
        if ($payload['error']) {
            $this->error($payload['error']);
            $this->redirect('companies&edit=' . $id);
        }

        try {
            $this->companies->update($id, $payload['data']);
        } catch (PDOException $e) {
            $this->error('Nao foi possivel atualizar empresa. Verifique se o CNPJ ja existe.');
            $this->redirect('companies&edit=' . $id);
        }

        if (current_company_id() === $id) {
            $this->refreshSessionCompany($id);
        }

        $this->success('Empresa atualizada.');
        $this->redirect('companies');
    }

    public function toggle(): void
    {
        require_auth();
        require_permission('companies');
        csrf_validate();

        $id = (int) post('id');
        $active = (int) post('active', 1);

        if ($id <= 0 || !$this->companies->find($id)) {
            $this->error('Empresa nao encontrada.');
            $this->redirect('companies');
        }

        $this->companies->setActive($id, $active);

        if ((int) $active === 0 && current_company_id() === $id) {
            clear_current_company();
        } elseif ((int) $active === 1 && current_company_id() === $id) {
            $this->refreshSessionCompany($id);
        }

        $this->success('Status da empresa atualizado.');
        $this->redirect('companies');
    }

    public function updateIntegrations(): void
    {
        require_auth();
        require_permission('companies');
        csrf_validate();

        if (!$this->integrations->tableExists()) {
            $this->error('Tabela company_integrations ainda nao existe. Execute a migracao da Fase 2.');
            $this->redirect('companies');
        }

        $companyId = (int) post('company_id');
        $company = $companyId > 0 ? $this->companies->find($companyId) : null;
        if (!$company) {
            $this->error('Empresa invalida para salvar credenciais.');
            $this->redirect('companies');
        }

        $changedBy = (int) (current_user()['id'] ?? 0);

        $chatwoot = $this->collectChatwootSettings();
        $d4sign = $this->collectD4SignSettings();
        $fiscal = $this->collectFiscalSettings();
        $bankSlip = $this->collectBankSlipSettings();
        $adminAi = $this->collectAdminAiSettings();

        $this->integrations->save($companyId, 'chatwoot', $chatwoot['enabled'], $chatwoot['settings'], $changedBy);
        $this->integrations->save($companyId, 'd4sign', $d4sign['enabled'], $d4sign['settings'], $changedBy);
        $this->integrations->save($companyId, 'fiscal', $fiscal['enabled'], $fiscal['settings'], $changedBy);
        $this->integrations->save($companyId, 'bank_slip', $bankSlip['enabled'], $bankSlip['settings'], $changedBy);
        $this->integrations->save($companyId, 'admin_ai', $adminAi['enabled'], $adminAi['settings'], $changedBy);

        if ((int) (current_company_id() ?? 0) === $companyId) {
            $this->refreshSessionCompany($companyId);
        }

        $this->success('Credenciais da empresa atualizadas com sucesso.');
        $this->redirect('companies&integration_company_id=' . $companyId);
    }

    private function loadIntegrationSettings(int $companyId): array
    {
        return [
            'chatwoot' => $this->normalizeChatwootSettings($this->integrations->mergeWithGlobalConfig('chatwoot', $companyId)),
            'd4sign' => $this->normalizeD4SignSettings($this->integrations->mergeWithGlobalConfig('d4sign', $companyId)),
            'fiscal' => $this->normalizeFiscalSettings($this->integrations->mergeWithGlobalConfig('fiscal', $companyId)),
            'bank_slip' => $this->normalizeBankSlipSettings($this->integrations->mergeWithGlobalConfig('bank_slip', $companyId)),
            'admin_ai' => $this->normalizeAdminAiSettings($this->integrations->mergeWithGlobalConfig('admin_ai', $companyId)),
        ];
    }

    private function normalizeChatwootSettings(array $settings): array
    {
        $settings['enabled'] = !empty($settings['enabled']);
        $settings['bot_enabled'] = !empty($settings['bot_enabled']);
        $settings['account_id'] = (string) ($settings['account_id'] ?? '');
        $settings['inbox_id'] = (string) ($settings['inbox_id'] ?? '');

        $keywords = $settings['bot_start_keywords'] ?? [];
        if (is_string($keywords)) {
            $keywords = $this->parseDelimitedItems($keywords);
        }
        if (!is_array($keywords)) {
            $keywords = [];
        }
        $settings['bot_start_keywords'] = array_values(array_filter(array_map('trim', array_map('strval', $keywords)), fn ($item) => $item !== ''));
        $settings['bot_start_keywords_text'] = implode(', ', $settings['bot_start_keywords']);

        $map = $settings['bot_city_team_map'] ?? [];
        if (is_string($map)) {
            $decoded = json_decode($map, true);
            $map = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($map)) {
            $map = [];
        }
        $settings['bot_city_team_map'] = $map;

        $lines = [];
        foreach ($map as $city => $teamId) {
            $city = trim((string) $city);
            if ($city === '') {
                continue;
            }
            $lines[] = $city . '=' . ($teamId !== null && $teamId !== '' ? (string) $teamId : '');
        }
        $settings['bot_city_team_map_text'] = implode(PHP_EOL, $lines);

        return $settings;
    }

    private function normalizeD4SignSettings(array $settings): array
    {
        $settings['enabled'] = !empty($settings['enabled']);
        return $settings;
    }

    private function normalizeFiscalSettings(array $settings): array
    {
        $settings['enabled'] = !empty($settings['enabled']);
        return $settings;
    }

    private function normalizeBankSlipSettings(array $settings): array
    {
        $settings['enabled'] = !empty($settings['enabled']);
        return $settings;
    }

    private function normalizeAdminAiSettings(array $settings): array
    {
        $settings['enabled'] = !empty($settings['enabled']);
        $settings['temperature'] = isset($settings['temperature']) ? (string) $settings['temperature'] : '0.2';
        $settings['max_tokens'] = isset($settings['max_tokens']) ? (string) $settings['max_tokens'] : '700';
        $settings['timeout_seconds'] = isset($settings['timeout_seconds']) ? (string) $settings['timeout_seconds'] : '30';
        $settings['history_messages'] = isset($settings['history_messages']) ? (string) $settings['history_messages'] : '8';
        return $settings;
    }

    private function collectChatwootSettings(): array
    {
        $settings = [
            'base_url' => trim((string) post('chatwoot_base_url')),
            'account_id' => (int) post('chatwoot_account_id', 0),
            'inbox_id' => (int) post('chatwoot_inbox_id', 0),
            'api_access_token' => trim((string) post('chatwoot_api_access_token')),
            'webhook_token' => trim((string) post('chatwoot_webhook_token')),
            'bot_enabled' => post('chatwoot_bot_enabled') ? true : false,
            'bot_start_keywords' => $this->parseDelimitedItems((string) post('chatwoot_bot_start_keywords')),
            'bot_message_menu' => trim((string) post('chatwoot_bot_message_menu')),
            'bot_message_name_city' => trim((string) post('chatwoot_bot_message_name_city')),
            'bot_message_invalid_option' => trim((string) post('chatwoot_bot_message_invalid_option')),
            'bot_message_city_retry' => trim((string) post('chatwoot_bot_message_city_retry')),
            'bot_message_handoff' => trim((string) post('chatwoot_bot_message_handoff')),
            'bot_city_team_map' => $this->parseCityTeamMap((string) post('chatwoot_bot_city_team_map')),
        ];

        if ($settings['base_url'] === '') {
            unset($settings['base_url']);
        }
        if ($settings['account_id'] <= 0) {
            unset($settings['account_id']);
        }
        if ($settings['inbox_id'] <= 0) {
            unset($settings['inbox_id']);
        }
        if ($settings['api_access_token'] === '') {
            unset($settings['api_access_token']);
        }
        if ($settings['webhook_token'] === '') {
            unset($settings['webhook_token']);
        }
        if ($settings['bot_start_keywords'] === []) {
            unset($settings['bot_start_keywords']);
        }
        if ($settings['bot_message_menu'] === '') {
            unset($settings['bot_message_menu']);
        }
        if ($settings['bot_message_name_city'] === '') {
            unset($settings['bot_message_name_city']);
        }
        if ($settings['bot_message_invalid_option'] === '') {
            unset($settings['bot_message_invalid_option']);
        }
        if ($settings['bot_message_city_retry'] === '') {
            unset($settings['bot_message_city_retry']);
        }
        if ($settings['bot_message_handoff'] === '') {
            unset($settings['bot_message_handoff']);
        }
        if ($settings['bot_city_team_map'] === []) {
            unset($settings['bot_city_team_map']);
        }

        return [
            'enabled' => post('chatwoot_enabled') ? true : false,
            'settings' => $settings,
        ];
    }

    private function collectD4SignSettings(): array
    {
        $settings = [
            'base_url' => trim((string) post('d4sign_base_url')),
            'token_api' => trim((string) post('d4sign_token_api')),
            'crypt_key' => trim((string) post('d4sign_crypt_key')),
            'safe_uuid' => trim((string) post('d4sign_safe_uuid')),
            'webhook_token' => trim((string) post('d4sign_webhook_token')),
            'webhook_hmac_secret' => trim((string) post('d4sign_webhook_hmac_secret')),
        ];

        foreach ($settings as $key => $value) {
            if ($value === '') {
                unset($settings[$key]);
            }
        }

        return [
            'enabled' => post('d4sign_enabled') ? true : false,
            'settings' => $settings,
        ];
    }

    private function collectFiscalSettings(): array
    {
        $settings = [
            'provider' => trim((string) post('fiscal_provider')),
            'environment' => trim((string) post('fiscal_environment')),
            'base_url' => trim((string) post('fiscal_base_url')),
            'api_token' => trim((string) post('fiscal_api_token')),
            'company_document' => trim((string) post('fiscal_company_document')),
            'company_name' => trim((string) post('fiscal_company_name')),
        ];

        foreach ($settings as $key => $value) {
            if ($value === '') {
                unset($settings[$key]);
            }
        }

        return [
            'enabled' => post('fiscal_enabled') ? true : false,
            'settings' => $settings,
        ];
    }

    private function collectBankSlipSettings(): array
    {
        $settings = [
            'provider' => trim((string) post('bank_slip_provider')),
            'environment' => trim((string) post('bank_slip_environment')),
            'base_url' => trim((string) post('bank_slip_base_url')),
            'api_token' => trim((string) post('bank_slip_api_token')),
            'webhook_secret' => trim((string) post('bank_slip_webhook_secret')),
            'wallet' => trim((string) post('bank_slip_wallet')),
            'beneficiary_document' => trim((string) post('bank_slip_beneficiary_document')),
            'beneficiary_name' => trim((string) post('bank_slip_beneficiary_name')),
        ];

        foreach ($settings as $key => $value) {
            if ($value === '') {
                unset($settings[$key]);
            }
        }

        return [
            'enabled' => post('bank_slip_enabled') ? true : false,
            'settings' => $settings,
        ];
    }

    private function collectAdminAiSettings(): array
    {
        $temperatureRaw = trim((string) post('admin_ai_temperature'));
        $maxTokensRaw = trim((string) post('admin_ai_max_tokens'));
        $timeoutRaw = trim((string) post('admin_ai_timeout_seconds'));
        $historyRaw = trim((string) post('admin_ai_history_messages'));

        $settings = [
            'provider' => trim((string) post('admin_ai_provider')),
            'base_url' => trim((string) post('admin_ai_base_url')),
            'api_key' => trim((string) post('admin_ai_api_key')),
            'model' => trim((string) post('admin_ai_model')),
            'http_referer' => trim((string) post('admin_ai_http_referer')),
            'app_title' => trim((string) post('admin_ai_app_title')),
            'system_prompt' => trim((string) post('admin_ai_system_prompt')),
        ];

        if ($temperatureRaw !== '' && is_numeric($temperatureRaw)) {
            $settings['temperature'] = (float) $temperatureRaw;
        }

        if ($maxTokensRaw !== '' && is_numeric($maxTokensRaw)) {
            $settings['max_tokens'] = max(100, min(3000, (int) $maxTokensRaw));
        }

        if ($timeoutRaw !== '' && is_numeric($timeoutRaw)) {
            $settings['timeout_seconds'] = max(5, min(120, (int) $timeoutRaw));
        }

        if ($historyRaw !== '' && is_numeric($historyRaw)) {
            $settings['history_messages'] = max(0, min(20, (int) $historyRaw));
        }

        foreach ($settings as $key => $value) {
            if ($value === '' || $value === null) {
                unset($settings[$key]);
            }
        }

        return [
            'enabled' => post('admin_ai_enabled') ? true : false,
            'settings' => $settings,
        ];
    }

    private function parseDelimitedItems(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $items = preg_split('/[\r\n,;]+/', $value) ?: [];
        return array_values(array_filter(array_map('trim', $items), fn ($item) => $item !== ''));
    }

    private function parseCityTeamMap(string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($value)) ?: [];
        $map = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/=|:/', $line, 2);
            $city = trim((string) ($parts[0] ?? ''));
            $teamRaw = trim((string) ($parts[1] ?? ''));
            if ($city === '') {
                continue;
            }

            $teamId = $teamRaw !== '' && is_numeric($teamRaw) ? (int) $teamRaw : null;
            $map[$city] = $teamId;
        }

        return $map;
    }

    private function collectPayload(): array
    {
        $data = [
            'legal_name' => trim((string) post('legal_name')),
            'trade_name' => trim((string) post('trade_name')),
            'cnpj' => trim((string) post('cnpj')),
            'is_active' => (int) post('is_active', 1),
        ];

        if ($data['legal_name'] === '') {
            return ['error' => 'Razao social e obrigatoria.'];
        }

        $digits = $this->companies->normalizeCnpj($data['cnpj']);
        if (strlen($digits) !== 14) {
            return ['error' => 'Informe um CNPJ valido com 14 digitos.'];
        }

        $data['cnpj'] = $digits;

        return [
            'error' => null,
            'data' => $data,
        ];
    }

    private function refreshSessionCompany(int $companyId): void
    {
        $user = current_user();
        if (!$user) {
            return;
        }

        $users = new UserModel();
        $companies = $users->companiesForUser((int) $user['id']);
        $_SESSION['user_companies'] = $companies;

        foreach ($companies as $company) {
            if ((int) ($company['id'] ?? 0) === $companyId) {
                set_current_company($company);
                return;
            }
        }

        clear_current_company();
    }
}

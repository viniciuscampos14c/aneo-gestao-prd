<?php

class CompanyIntegrationModel extends BaseModel
{
    private ?bool $tableExists = null;

    public function tableExists(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'company_integrations'");
        $stmt->execute();
        $this->tableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->tableExists;
    }

    public function get(int $companyId, string $integrationKey): ?array
    {
        if (!$this->tableExists() || $companyId <= 0 || trim($integrationKey) === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT *
            FROM company_integrations
            WHERE company_id = :company_id
              AND integration_key = :integration_key
            LIMIT 1');
        $stmt->execute([
            ':company_id' => $companyId,
            ':integration_key' => trim($integrationKey),
        ]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $settings = [];
        $raw = (string) ($row['settings_json'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }

        return [
            'id' => (int) $row['id'],
            'company_id' => (int) $row['company_id'],
            'integration_key' => (string) $row['integration_key'],
            'is_enabled' => (int) ($row['is_enabled'] ?? 0) === 1,
            'settings' => $settings,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public function allByCompany(int $companyId): array
    {
        if (!$this->tableExists() || $companyId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT *
            FROM company_integrations
            WHERE company_id = :company_id');
        $stmt->execute([':company_id' => $companyId]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $integrationKey = (string) ($row['integration_key'] ?? '');
            if ($integrationKey === '') {
                continue;
            }

            $settings = [];
            $raw = (string) ($row['settings_json'] ?? '');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }

            $result[$integrationKey] = [
                'id' => (int) $row['id'],
                'company_id' => (int) $row['company_id'],
                'integration_key' => $integrationKey,
                'is_enabled' => (int) ($row['is_enabled'] ?? 0) === 1,
                'settings' => $settings,
            ];
        }

        return $result;
    }

    public function mergeWithGlobalConfig(string $integrationKey, ?int $companyId = null): array
    {
        $base = config($integrationKey, []);
        if (!is_array($base)) {
            $base = [];
        }

        $companyId = (int) ($companyId ?? current_company_id() ?? 0);
        if ($companyId <= 0 || !$this->tableExists()) {
            return $base;
        }

        $row = $this->get($companyId, $integrationKey);
        if (!$row) {
            return $base;
        }

        $merged = array_merge($base, $row['settings']);
        $merged['enabled'] = $row['is_enabled'];

        return $merged;
    }

    public function save(int $companyId, string $integrationKey, bool $isEnabled, array $settings, int $changedBy = 0): void
    {
        if (!$this->tableExists() || $companyId <= 0 || trim($integrationKey) === '') {
            return;
        }

        $cleanSettings = [];
        foreach ($settings as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }
            $cleanSettings[$key] = $value;
        }

        $stmt = $this->db->prepare('INSERT INTO company_integrations (
                company_id, integration_key, is_enabled, settings_json, created_by, updated_by, created_at, updated_at
            ) VALUES (
                :company_id, :integration_key, :is_enabled, :settings_json, :created_by, :updated_by, :created_at, :updated_at
            )
            ON DUPLICATE KEY UPDATE
                is_enabled = VALUES(is_enabled),
                settings_json = VALUES(settings_json),
                updated_by = VALUES(updated_by),
                updated_at = VALUES(updated_at)');

        $now = now();
        $stmt->execute([
            ':company_id' => $companyId,
            ':integration_key' => trim($integrationKey),
            ':is_enabled' => $isEnabled ? 1 : 0,
            ':settings_json' => json_encode($cleanSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':created_by' => $changedBy > 0 ? $changedBy : null,
            ':updated_by' => $changedBy > 0 ? $changedBy : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    public function findCompanyIdByToken(string $integrationKey, string $tokenField, string $token): ?int
    {
        $companyIds = $this->findCompanyIdsByToken($integrationKey, $tokenField, $token);
        return $companyIds[0] ?? null;
    }

    public function findCompanyIdsByToken(string $integrationKey, string $tokenField, string $token): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $integrationKey = trim($integrationKey);
        $tokenField = trim($tokenField);
        $token = trim($token);
        if ($integrationKey === '' || $tokenField === '' || $token === '') {
            return [];
        }

        $stmt = $this->db->prepare('SELECT company_id, settings_json
            FROM company_integrations
            WHERE integration_key = :integration_key
              AND is_enabled = 1');
        $stmt->execute([':integration_key' => $integrationKey]);

        $companyIds = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings = json_decode((string) ($row['settings_json'] ?? ''), true);
            if (!is_array($settings)) {
                continue;
            }

            $candidate = trim((string) ($settings[$tokenField] ?? ''));
            if ($candidate !== '' && hash_equals($candidate, $token)) {
                $companyId = (int) ($row['company_id'] ?? 0);
                if ($companyId > 0) {
                    $companyIds[] = $companyId;
                }
            }
        }

        return array_values(array_unique($companyIds));
    }
}

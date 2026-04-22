<?php

class LicenseService
{
    private CompanyLicenseModel $licenses;

    public function __construct()
    {
        $this->licenses = new CompanyLicenseModel();
    }

    public function available(): bool
    {
        return $this->licenses->licensesTableExists() && $this->licenses->historyTableExists();
    }

    public function currentStatus(int $companyId): array
    {
        $result = [
            'exists' => false,
            'is_valid' => false,
            'status' => 'missing',
            'license_label' => '',
            'valid_from' => '',
            'valid_until' => '',
            'grace_until' => '',
            'days_left' => null,
            'within_grace' => false,
            'key_hash' => '',
            'key_masked' => '',
            'row' => null,
        ];

        if ($companyId <= 0 || !$this->licenses->licensesTableExists()) {
            return $result;
        }

        $row = $this->licenses->getByCompany($companyId);
        if (!$row) {
            return $result;
        }

        $today = date('Y-m-d');
        $validFrom = (string) ($row['valid_from'] ?? '');
        $validUntil = (string) ($row['valid_until'] ?? '');
        $graceUntil = (string) ($row['grace_until'] ?? '');

        $isWithinWindow = $validFrom !== '' && $validUntil !== '' && $today >= $validFrom && $today <= $validUntil;
        $withinGrace = !$isWithinWindow && $graceUntil !== '' && $today <= $graceUntil;

        $daysLeft = null;
        if ($validUntil !== '') {
            $daysLeft = (int) floor((strtotime($validUntil . ' 23:59:59') - time()) / 86400);
        }

        $status = $isWithinWindow ? 'active' : ($withinGrace ? 'grace' : 'expired');

        $result['exists'] = true;
        $result['is_valid'] = $isWithinWindow;
        $result['status'] = $status;
        $result['license_label'] = (string) ($row['license_label'] ?? '');
        $result['valid_from'] = $validFrom;
        $result['valid_until'] = $validUntil;
        $result['grace_until'] = $graceUntil;
        $result['days_left'] = $daysLeft;
        $result['within_grace'] = $withinGrace;
        $result['key_hash'] = (string) ($row['license_key_hash'] ?? '');
        $result['key_masked'] = $this->maskHash((string) ($row['license_key_hash'] ?? ''));
        $result['row'] = $row;

        return $result;
    }

    public function isCompanyLicensed(int $companyId): bool
    {
        $status = $this->currentStatus($companyId);
        return !empty($status['is_valid']) || !empty($status['within_grace']);
    }

    public function activateFixedKey(int $companyId, string $key, int $userId = 0, string $note = ''): array
    {
        if ($companyId <= 0) {
            return ['ok' => false, 'message' => 'Empresa invalida.'];
        }

        if (!$this->available()) {
            return ['ok' => false, 'message' => 'Estrutura de licenciamento nao encontrada no banco.'];
        }

        $validated = $this->validateFixedKey($key);
        if (!$validated['ok']) {
            return ['ok' => false, 'message' => (string) ($validated['message'] ?? 'Chave de licenca invalida.')];
        }

        $durationDays = max(1, (int) ($validated['duration_days'] ?? 365));
        $validFromDate = date('Y-m-d');
        $validUntilDate = date('Y-m-d', strtotime('+' . ($durationDays - 1) . ' days'));

        $graceDays = max(0, (int) config('licensing.grace_days', 0));
        $graceUntilDate = $graceDays > 0
            ? date('Y-m-d', strtotime($validUntilDate . ' +' . $graceDays . ' days'))
            : null;

        $before = $this->licenses->getByCompany($companyId);

        $this->licenses->saveLicense($companyId, [
            'license_key_hash' => (string) $validated['key_hash'],
            'license_label' => (string) ($validated['label'] ?? 'Licenca fixa anual'),
            'status' => 'active',
            'activated_at' => now(),
            'valid_from' => $validFromDate,
            'valid_until' => $validUntilDate,
            'grace_until' => $graceUntilDate,
            'last_checked_at' => now(),
            'metadata' => [
                'validation_mode' => 'fixed_key',
                'duration_days' => $durationDays,
            ],
        ], $userId);

        $action = $before ? 'renew' : 'activate';
        $this->licenses->addHistory($companyId, $userId, [
            'action' => $action,
            'license_key_hash' => (string) $validated['key_hash'],
            'license_label' => (string) ($validated['label'] ?? 'Licenca fixa anual'),
            'valid_from' => $validFromDate,
            'valid_until' => $validUntilDate,
            'note' => $note !== '' ? $note : null,
            'metadata' => [
                'mode' => 'fixed_key',
                'previous_valid_until' => (string) ($before['valid_until'] ?? ''),
                'grace_until' => $graceUntilDate,
            ],
        ]);

        return [
            'ok' => true,
            'message' => 'Licenca ativada ate ' . date('d/m/Y', strtotime($validUntilDate)) . '.',
            'valid_from' => $validFromDate,
            'valid_until' => $validUntilDate,
            'grace_until' => $graceUntilDate,
            'action' => $action,
            'label' => (string) ($validated['label'] ?? 'Licenca fixa anual'),
            'key_hash' => (string) $validated['key_hash'],
        ];
    }

    public function historyByCompany(int $companyId, int $limit = 30): array
    {
        return $this->licenses->historyByCompany($companyId, $limit);
    }

    public function touchCheck(int $companyId): void
    {
        $this->licenses->updateLastCheckedAt($companyId);
    }

    public function listConfiguredKeyLabels(): array
    {
        $keys = $this->configuredKeys();
        $labels = [];

        foreach ($keys as $row) {
            $labels[] = [
                'label' => (string) ($row['label'] ?? 'Licenca fixa anual'),
                'duration_days' => max(1, (int) ($row['duration_days'] ?? 365)),
            ];
        }

        return $labels;
    }

    private function validateFixedKey(string $rawKey): array
    {
        $normalized = $this->normalizeKey($rawKey);
        if ($normalized === '') {
            return ['ok' => false, 'message' => 'Informe a chave de licenca.'];
        }

        $keys = $this->configuredKeys();
        foreach ($keys as $row) {
            $candidate = $this->normalizeKey((string) ($row['key'] ?? ''));
            if ($candidate === '') {
                continue;
            }

            if (!hash_equals($candidate, $normalized)) {
                continue;
            }

            return [
                'ok' => true,
                'key_hash' => hash('sha256', $normalized),
                'label' => (string) ($row['label'] ?? 'Licenca fixa anual'),
                'duration_days' => max(1, (int) ($row['duration_days'] ?? 365)),
            ];
        }

        return ['ok' => false, 'message' => 'Chave de licenca invalida.'];
    }

    private function configuredKeys(): array
    {
        $keys = config('licensing.fixed_keys', []);
        return is_array($keys) ? $keys : [];
    }

    private function normalizeKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }

        $key = preg_replace('/\s+/', '', $key) ?? '';
        return strtoupper($key);
    }

    private function maskHash(string $hash): string
    {
        $hash = trim($hash);
        if ($hash === '') {
            return '';
        }

        if (strlen($hash) <= 12) {
            return $hash;
        }

        return substr($hash, 0, 8) . '...' . substr($hash, -4);
    }
}

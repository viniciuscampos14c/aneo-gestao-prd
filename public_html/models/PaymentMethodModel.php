<?php

class PaymentMethodModel extends BaseModel
{
    private ?bool $tableExists = null;
    private ?bool $invoiceColumnExists = null;
    private ?bool $paymentsColumnExists = null;

    public function tableExists(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'payment_methods'");
        $stmt->execute();
        $this->tableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->tableExists;
    }

    public function invoiceColumnExists(): bool
    {
        if ($this->invoiceColumnExists !== null) {
            return $this->invoiceColumnExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'invoices'
              AND column_name = 'payment_method_id'");
        $stmt->execute();
        $this->invoiceColumnExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->invoiceColumnExists;
    }

    public function paymentsColumnExists(): bool
    {
        if ($this->paymentsColumnExists !== null) {
            return $this->paymentsColumnExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'payments'
              AND column_name = 'payment_method_id'");
        $stmt->execute();
        $this->paymentsColumnExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->paymentsColumnExists;
    }

    public function allByCompany(int $companyId): array
    {
        if (!$this->tableExists() || $companyId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT *
            FROM payment_methods
            WHERE company_id = :company_id
            ORDER BY is_active DESC, sort_order ASC, name ASC');
        $stmt->execute([':company_id' => $companyId]);

        return $stmt->fetchAll() ?: [];
    }

    public function activeByCompany(int $companyId): array
    {
        if (!$this->tableExists() || $companyId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT *
            FROM payment_methods
            WHERE company_id = :company_id
              AND is_active = 1
            ORDER BY sort_order ASC, name ASC');
        $stmt->execute([':company_id' => $companyId]);

        return $stmt->fetchAll() ?: [];
    }

    public function find(int $companyId, int $id): ?array
    {
        if (!$this->tableExists() || $companyId <= 0 || $id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT *
            FROM payment_methods
            WHERE id = :id
              AND company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $companyId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findActive(int $companyId, int $id): ?array
    {
        $row = $this->find($companyId, $id);
        if (!$row || (int) ($row['is_active'] ?? 0) !== 1) {
            return null;
        }

        return $row;
    }

    public function createManual(int $companyId, string $name, string $channel, int $createdBy = 0): int
    {
        if (!$this->tableExists() || $companyId <= 0) {
            return 0;
        }

        $name = trim($name);
        $channel = trim($channel);
        if ($name === '') {
            return 0;
        }

        $existing = $this->findByName($companyId, $name);
        if ($existing) {
            return (int) $existing['id'];
        }

        $slugBase = $this->slug('manual-' . ($channel !== '' ? $channel : $name));
        $slug = $this->uniqueSlug($companyId, $slugBase);
        $now = now();

        $stmt = $this->db->prepare('INSERT INTO payment_methods (
            company_id, name, slug, mode, provider_key, channel, auto_created,
            is_active, sort_order, settings_json, created_by, updated_by, created_at, updated_at
        ) VALUES (
            :company_id, :name, :slug, :mode, :provider_key, :channel, :auto_created,
            :is_active, :sort_order, :settings_json, :created_by, :updated_by, :created_at, :updated_at
        )');
        $stmt->execute([
            ':company_id' => $companyId,
            ':name' => $name,
            ':slug' => $slug,
            ':mode' => 'manual',
            ':provider_key' => null,
            ':channel' => $channel !== '' ? $channel : 'other',
            ':auto_created' => 0,
            ':is_active' => 1,
            ':sort_order' => $this->nextSortOrder($companyId),
            ':settings_json' => null,
            ':created_by' => $createdBy > 0 ? $createdBy : null,
            ':updated_by' => $createdBy > 0 ? $createdBy : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function setActive(int $companyId, int $id, bool $active, int $updatedBy = 0): bool
    {
        if (!$this->tableExists() || $companyId <= 0 || $id <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE payment_methods
            SET is_active = :is_active,
                updated_by = :updated_by,
                updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id');
        $stmt->execute([
            ':is_active' => $active ? 1 : 0,
            ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
            ':updated_at' => now(),
            ':id' => $id,
            ':company_id' => $companyId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function seedManualDefaults(int $companyId, int $createdBy = 0): void
    {
        if (!$this->tableExists() || $companyId <= 0) {
            return;
        }

        $defaults = [
            ['name' => 'PIX', 'channel' => 'pix'],
            ['name' => 'Cartao de credito', 'channel' => 'card'],
            ['name' => 'Transferencia', 'channel' => 'transfer'],
            ['name' => 'Dinheiro', 'channel' => 'cash'],
        ];

        foreach ($defaults as $default) {
            $this->createManual($companyId, (string) $default['name'], (string) $default['channel'], $createdBy);
        }
    }

    public function syncIntegratedContractMethod(
        int $companyId,
        string $contractKey,
        string $displayName,
        string $channel,
        bool $enabled,
        int $changedBy = 0,
        array $settings = []
    ): ?int {
        if (!$this->tableExists() || $companyId <= 0) {
            return null;
        }

        $contractKey = strtolower(trim($contractKey));
        $displayName = trim($displayName);
        $channel = strtolower(trim($channel));
        if ($contractKey === '' || $displayName === '') {
            return null;
        }

        if ($channel === '') {
            $channel = 'other';
        }

        $slug = $this->slug('integrated-' . $contractKey . '-' . $channel);
        $current = $this->findBySlug($companyId, $slug);
        $now = now();
        $encodedSettings = $settings !== []
            ? json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        if ($current) {
            $stmt = $this->db->prepare('UPDATE payment_methods
                SET name = :name,
                    mode = :mode,
                    provider_key = :provider_key,
                    channel = :channel,
                    auto_created = 1,
                    is_active = :is_active,
                    settings_json = :settings_json,
                    updated_by = :updated_by,
                    updated_at = :updated_at
                WHERE id = :id
                  AND company_id = :company_id');
            $stmt->execute([
                ':name' => $displayName,
                ':mode' => 'integrated',
                ':provider_key' => $contractKey,
                ':channel' => $channel,
                ':is_active' => $enabled ? 1 : 0,
                ':settings_json' => $encodedSettings,
                ':updated_by' => $changedBy > 0 ? $changedBy : null,
                ':updated_at' => $now,
                ':id' => (int) $current['id'],
                ':company_id' => $companyId,
            ]);

            return (int) $current['id'];
        }

        $stmt = $this->db->prepare('INSERT INTO payment_methods (
            company_id, name, slug, mode, provider_key, channel, auto_created,
            is_active, sort_order, settings_json, created_by, updated_by, created_at, updated_at
        ) VALUES (
            :company_id, :name, :slug, :mode, :provider_key, :channel, :auto_created,
            :is_active, :sort_order, :settings_json, :created_by, :updated_by, :created_at, :updated_at
        )');
        $stmt->execute([
            ':company_id' => $companyId,
            ':name' => $displayName,
            ':slug' => $slug,
            ':mode' => 'integrated',
            ':provider_key' => $contractKey,
            ':channel' => $channel,
            ':auto_created' => 1,
            ':is_active' => $enabled ? 1 : 0,
            ':sort_order' => $this->nextSortOrder($companyId),
            ':settings_json' => $encodedSettings,
            ':created_by' => $changedBy > 0 ? $changedBy : null,
            ':updated_by' => $changedBy > 0 ? $changedBy : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function deactivateByContract(int $companyId, string $contractKey, int $updatedBy = 0): void
    {
        if (!$this->tableExists() || $companyId <= 0 || trim($contractKey) === '') {
            return;
        }

        $stmt = $this->db->prepare('UPDATE payment_methods
            SET is_active = 0,
                updated_by = :updated_by,
                updated_at = :updated_at
            WHERE company_id = :company_id
              AND mode = :mode
              AND provider_key = :provider_key');
        $stmt->execute([
            ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
            ':updated_at' => now(),
            ':company_id' => $companyId,
            ':mode' => 'integrated',
            ':provider_key' => strtolower(trim($contractKey)),
        ]);
    }

    public function methodNamesFromPayments(int $companyId): array
    {
        if ($companyId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT DISTINCT method
            FROM payments
            WHERE company_id = :company_id
              AND method IS NOT NULL
              AND method <> ""
            ORDER BY method ASC');
        $stmt->execute([':company_id' => $companyId]);

        return array_values(array_filter(array_map(
            fn ($row) => trim((string) ($row['method'] ?? '')),
            $stmt->fetchAll() ?: []
        )));
    }

    private function findByName(int $companyId, string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT *
            FROM payment_methods
            WHERE company_id = :company_id
              AND LOWER(name) = LOWER(:name)
            LIMIT 1');
        $stmt->execute([
            ':company_id' => $companyId,
            ':name' => trim($name),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function findBySlug(int $companyId, string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT *
            FROM payment_methods
            WHERE company_id = :company_id
              AND slug = :slug
            LIMIT 1');
        $stmt->execute([
            ':company_id' => $companyId,
            ':slug' => trim($slug),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function uniqueSlug(int $companyId, string $base): string
    {
        $base = $this->slug($base);
        $slug = $base;
        $suffix = 2;

        while ($this->findBySlug($companyId, $slug)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'metodo';
    }

    private function nextSortOrder(int $companyId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10
            FROM payment_methods
            WHERE company_id = :company_id');
        $stmt->execute([':company_id' => $companyId]);

        return (int) ($stmt->fetchColumn() ?: 10);
    }
}


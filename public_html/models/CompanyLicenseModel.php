<?php

class CompanyLicenseModel extends BaseModel
{
    private ?bool $licensesTableExists = null;
    private ?bool $historyTableExists = null;

    public function licensesTableExists(): bool
    {
        if ($this->licensesTableExists !== null) {
            return $this->licensesTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'company_licenses'");
        $stmt->execute();
        $this->licensesTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->licensesTableExists;
    }

    public function historyTableExists(): bool
    {
        if ($this->historyTableExists !== null) {
            return $this->historyTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'company_license_history'");
        $stmt->execute();
        $this->historyTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->historyTableExists;
    }

    public function getByCompany(int $companyId): ?array
    {
        if ($companyId <= 0 || !$this->licensesTableExists()) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT *
            FROM company_licenses
            WHERE company_id = :company_id
            LIMIT 1');
        $stmt->execute([':company_id' => $companyId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $metadata = [];
        $rawMetadata = (string) ($row['metadata_json'] ?? '');
        if ($rawMetadata !== '') {
            $decoded = json_decode($rawMetadata, true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'company_id' => (int) ($row['company_id'] ?? 0),
            'license_key_hash' => (string) ($row['license_key_hash'] ?? ''),
            'license_label' => (string) ($row['license_label'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'activated_at' => (string) ($row['activated_at'] ?? ''),
            'valid_from' => (string) ($row['valid_from'] ?? ''),
            'valid_until' => (string) ($row['valid_until'] ?? ''),
            'grace_until' => (string) ($row['grace_until'] ?? ''),
            'last_checked_at' => (string) ($row['last_checked_at'] ?? ''),
            'metadata' => $metadata,
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public function saveLicense(int $companyId, array $data, int $changedBy): void
    {
        if ($companyId <= 0 || !$this->licensesTableExists()) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO company_licenses (
            company_id, license_key_hash, license_label, status,
            activated_at, valid_from, valid_until, grace_until,
            last_checked_at, metadata_json,
            created_by, updated_by, created_at, updated_at
        ) VALUES (
            :company_id, :license_key_hash, :license_label, :status,
            :activated_at, :valid_from, :valid_until, :grace_until,
            :last_checked_at, :metadata_json,
            :created_by, :updated_by, :created_at, :updated_at
        )
        ON DUPLICATE KEY UPDATE
            license_key_hash = VALUES(license_key_hash),
            license_label = VALUES(license_label),
            status = VALUES(status),
            activated_at = VALUES(activated_at),
            valid_from = VALUES(valid_from),
            valid_until = VALUES(valid_until),
            grace_until = VALUES(grace_until),
            last_checked_at = VALUES(last_checked_at),
            metadata_json = VALUES(metadata_json),
            updated_by = VALUES(updated_by),
            updated_at = VALUES(updated_at)');

        $now = now();
        $stmt->execute([
            ':company_id' => $companyId,
            ':license_key_hash' => (string) ($data['license_key_hash'] ?? ''),
            ':license_label' => (string) ($data['license_label'] ?? ''),
            ':status' => (string) ($data['status'] ?? 'active'),
            ':activated_at' => (string) ($data['activated_at'] ?? $now),
            ':valid_from' => (string) ($data['valid_from'] ?? date('Y-m-d')),
            ':valid_until' => (string) ($data['valid_until'] ?? date('Y-m-d')),
            ':grace_until' => $data['grace_until'] ?? null,
            ':last_checked_at' => $data['last_checked_at'] ?? null,
            ':metadata_json' => json_encode((array) ($data['metadata'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':created_by' => $changedBy > 0 ? $changedBy : null,
            ':updated_by' => $changedBy > 0 ? $changedBy : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    public function updateLastCheckedAt(int $companyId): void
    {
        if ($companyId <= 0 || !$this->licensesTableExists()) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE company_licenses
            SET last_checked_at = :last_checked_at,
                updated_at = :updated_at
            WHERE company_id = :company_id');
        $stmt->execute([
            ':last_checked_at' => now(),
            ':updated_at' => now(),
            ':company_id' => $companyId,
        ]);
    }

    public function addHistory(int $companyId, int $userId, array $data): void
    {
        if ($companyId <= 0 || !$this->historyTableExists()) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO company_license_history (
            company_id, user_id, action, license_key_hash, license_label,
            valid_from, valid_until, note, metadata_json, created_at
        ) VALUES (
            :company_id, :user_id, :action, :license_key_hash, :license_label,
            :valid_from, :valid_until, :note, :metadata_json, :created_at
        )');

        $stmt->execute([
            ':company_id' => $companyId,
            ':user_id' => $userId > 0 ? $userId : null,
            ':action' => (string) ($data['action'] ?? 'activate'),
            ':license_key_hash' => (string) ($data['license_key_hash'] ?? ''),
            ':license_label' => (string) ($data['license_label'] ?? ''),
            ':valid_from' => $data['valid_from'] ?? null,
            ':valid_until' => $data['valid_until'] ?? null,
            ':note' => $data['note'] ?? null,
            ':metadata_json' => json_encode((array) ($data['metadata'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':created_at' => now(),
        ]);
    }

    public function historyByCompany(int $companyId, int $limit = 30): array
    {
        if ($companyId <= 0 || !$this->historyTableExists()) {
            return [];
        }

        $limit = max(1, min(200, $limit));

        $stmt = $this->db->prepare('SELECT h.*, u.name AS user_name, u.email AS user_email
            FROM company_license_history h
            LEFT JOIN users u ON u.id = h.user_id
            WHERE h.company_id = :company_id
            ORDER BY h.id DESC
            LIMIT :limit');
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $metadata = [];
            $rawMetadata = (string) ($row['metadata_json'] ?? '');
            if ($rawMetadata !== '') {
                $decoded = json_decode($rawMetadata, true);
                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            }

            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'company_id' => (int) ($row['company_id'] ?? 0),
                'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
                'user_name' => (string) ($row['user_name'] ?? ''),
                'user_email' => (string) ($row['user_email'] ?? ''),
                'action' => (string) ($row['action'] ?? ''),
                'license_key_hash' => (string) ($row['license_key_hash'] ?? ''),
                'license_label' => (string) ($row['license_label'] ?? ''),
                'valid_from' => (string) ($row['valid_from'] ?? ''),
                'valid_until' => (string) ($row['valid_until'] ?? ''),
                'note' => (string) ($row['note'] ?? ''),
                'metadata' => $metadata,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $rows;
    }
}

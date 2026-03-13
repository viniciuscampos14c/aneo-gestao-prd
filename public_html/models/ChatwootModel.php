<?php

class ChatwootModel extends BaseModel
{
    private ?bool $linksTableExists = null;
    private ?bool $companyColumnExists = null;

    public function featureAvailable(): bool
    {
        return $this->hasLinksTable();
    }

    public function findByEntity(string $entityType, int $entityId): ?array
    {
        if (!$this->hasLinksTable() || $entityId <= 0) {
            return null;
        }

        if ($this->hasCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('SELECT *
                FROM chatwoot_links
                WHERE company_id = :company_id
                  AND entity_type = :entity_type
                  AND entity_id = :entity_id
                LIMIT 1');
            $stmt->execute([
                ':company_id' => $this->companyId(),
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
            ]);
        } else {
            $stmt = $this->db->prepare('SELECT *
                FROM chatwoot_links
                WHERE entity_type = :entity_type
                  AND entity_id = :entity_id
                LIMIT 1');
            $stmt->execute([
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
            ]);
        }

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function upsertLink(array $data, int $createdBy): void
    {
        if (!$this->hasLinksTable()) {
            return;
        }

        $entityType = trim((string) ($data['entity_type'] ?? 'other'));
        $entityId = (int) ($data['entity_id'] ?? 0);
        if ($entityId <= 0) {
            return;
        }

        $now = now();
        if ($this->hasCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('INSERT INTO chatwoot_links (
                company_id, entity_type, entity_id, contact_id, contact_source_id, conversation_id, conversation_url,
                status, contact_name, contact_phone, contact_email, last_message, last_synced_at,
                created_by, created_at, updated_at
            ) VALUES (
                :company_id, :entity_type, :entity_id, :contact_id, :contact_source_id, :conversation_id, :conversation_url,
                :status, :contact_name, :contact_phone, :contact_email, :last_message, :last_synced_at,
                :created_by, :created_at, :updated_at
            ) ON DUPLICATE KEY UPDATE
                contact_id = VALUES(contact_id),
                contact_source_id = VALUES(contact_source_id),
                conversation_id = VALUES(conversation_id),
                conversation_url = VALUES(conversation_url),
                status = VALUES(status),
                contact_name = VALUES(contact_name),
                contact_phone = VALUES(contact_phone),
                contact_email = VALUES(contact_email),
                last_message = VALUES(last_message),
                last_synced_at = VALUES(last_synced_at),
                updated_at = VALUES(updated_at),
                created_by = VALUES(created_by)');

            $stmt->execute([
                ':company_id' => $this->companyId(),
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':contact_id' => ($data['contact_id'] ?? null) ?: null,
                ':contact_source_id' => trim((string) ($data['contact_source_id'] ?? '')) ?: null,
                ':conversation_id' => ($data['conversation_id'] ?? null) ?: null,
                ':conversation_url' => trim((string) ($data['conversation_url'] ?? '')) ?: null,
                ':status' => trim((string) ($data['status'] ?? 'open')) ?: 'open',
                ':contact_name' => trim((string) ($data['contact_name'] ?? '')) ?: null,
                ':contact_phone' => trim((string) ($data['contact_phone'] ?? '')) ?: null,
                ':contact_email' => trim((string) ($data['contact_email'] ?? '')) ?: null,
                ':last_message' => trim((string) ($data['last_message'] ?? '')) ?: null,
                ':last_synced_at' => $data['last_synced_at'] ?? $now,
                ':created_by' => $createdBy > 0 ? $createdBy : null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO chatwoot_links (
            entity_type, entity_id, contact_id, contact_source_id, conversation_id, conversation_url,
            status, contact_name, contact_phone, contact_email, last_message, last_synced_at,
            created_by, created_at, updated_at
        ) VALUES (
            :entity_type, :entity_id, :contact_id, :contact_source_id, :conversation_id, :conversation_url,
            :status, :contact_name, :contact_phone, :contact_email, :last_message, :last_synced_at,
            :created_by, :created_at, :updated_at
        ) ON DUPLICATE KEY UPDATE
            contact_id = VALUES(contact_id),
            contact_source_id = VALUES(contact_source_id),
            conversation_id = VALUES(conversation_id),
            conversation_url = VALUES(conversation_url),
            status = VALUES(status),
            contact_name = VALUES(contact_name),
            contact_phone = VALUES(contact_phone),
            contact_email = VALUES(contact_email),
            last_message = VALUES(last_message),
            last_synced_at = VALUES(last_synced_at),
            updated_at = VALUES(updated_at),
            created_by = VALUES(created_by)');

        $stmt->execute([
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':contact_id' => ($data['contact_id'] ?? null) ?: null,
            ':contact_source_id' => trim((string) ($data['contact_source_id'] ?? '')) ?: null,
            ':conversation_id' => ($data['conversation_id'] ?? null) ?: null,
            ':conversation_url' => trim((string) ($data['conversation_url'] ?? '')) ?: null,
            ':status' => trim((string) ($data['status'] ?? 'open')) ?: 'open',
            ':contact_name' => trim((string) ($data['contact_name'] ?? '')) ?: null,
            ':contact_phone' => trim((string) ($data['contact_phone'] ?? '')) ?: null,
            ':contact_email' => trim((string) ($data['contact_email'] ?? '')) ?: null,
            ':last_message' => trim((string) ($data['last_message'] ?? '')) ?: null,
            ':last_synced_at' => $data['last_synced_at'] ?? $now,
            ':created_by' => $createdBy > 0 ? $createdBy : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    public function listLinks(array $filters, int $perPage, int $page): array
    {
        if (!$this->hasLinksTable()) {
            return [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, $page),
            ];
        }

        $where = ['1=1'];
        $params = [];

        if ($this->hasCompanyColumn() && $this->companyId() > 0) {
            $where[] = 'cl.company_id = :company_id';
            $params[':company_id'] = $this->companyId();
        }

        if (!empty($filters['entity_type'])) {
            $where[] = 'cl.entity_type = :entity_type';
            $params[':entity_type'] = $filters['entity_type'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(cl.contact_name LIKE :q
                OR cl.contact_phone LIKE :q
                OR cl.contact_email LIKE :q
                OR CAST(cl.conversation_id AS CHAR) LIKE :q
                OR s.full_name LIKE :q
                OR l.full_name LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
            FROM chatwoot_links cl
            LEFT JOIN students s ON cl.entity_type = 'student' AND s.id = cl.entity_id
            LEFT JOIN leads l ON cl.entity_type = 'lead' AND l.id = cl.entity_id
            WHERE {$whereSql}";

        $dataSql = "SELECT
                cl.*,
                CASE
                    WHEN cl.entity_type = 'student' THEN s.full_name
                    WHEN cl.entity_type = 'lead' THEN l.full_name
                    ELSE cl.contact_name
                END AS entity_name
            FROM chatwoot_links cl
            LEFT JOIN students s ON cl.entity_type = 'student' AND s.id = cl.entity_id
            LEFT JOIN leads l ON cl.entity_type = 'lead' AND l.id = cl.entity_id
            WHERE {$whereSql}
            ORDER BY cl.updated_at DESC, cl.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function stats(): array
    {
        if (!$this->hasLinksTable()) {
            return [
                'total' => 0,
                'with_conversation' => 0,
                'students' => 0,
                'leads' => 0,
            ];
        }

        if ($this->hasCompanyColumn() && $this->companyId() > 0) {
            $params = [':company_id' => $this->companyId()];

            return [
                'total' => (int) $this->scalar('SELECT COUNT(*) FROM chatwoot_links WHERE company_id = :company_id', $params),
                'with_conversation' => (int) $this->scalar('SELECT COUNT(*) FROM chatwoot_links WHERE company_id = :company_id AND conversation_id IS NOT NULL', $params),
                'students' => (int) $this->scalar("SELECT COUNT(*) FROM chatwoot_links WHERE company_id = :company_id AND entity_type = 'student'", $params),
                'leads' => (int) $this->scalar("SELECT COUNT(*) FROM chatwoot_links WHERE company_id = :company_id AND entity_type = 'lead'", $params),
            ];
        }

        return [
            'total' => (int) $this->scalar('SELECT COUNT(*) FROM chatwoot_links'),
            'with_conversation' => (int) $this->scalar('SELECT COUNT(*) FROM chatwoot_links WHERE conversation_id IS NOT NULL'),
            'students' => (int) $this->scalar("SELECT COUNT(*) FROM chatwoot_links WHERE entity_type = 'student'"),
            'leads' => (int) $this->scalar("SELECT COUNT(*) FROM chatwoot_links WHERE entity_type = 'lead'"),
        ];
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function hasLinksTable(): bool
    {
        if ($this->linksTableExists !== null) {
            return $this->linksTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'chatwoot_links'");
        $stmt->execute();
        $this->linksTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->linksTableExists;
    }

    private function hasCompanyColumn(): bool
    {
        if ($this->companyColumnExists !== null) {
            return $this->companyColumnExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'chatwoot_links'
              AND column_name = 'company_id'");
        $stmt->execute();
        $this->companyColumnExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->companyColumnExists;
    }
}

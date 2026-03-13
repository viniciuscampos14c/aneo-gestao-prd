<?php

class SignatureModel extends BaseModel
{
    private ?bool $requestsTableExists = null;
    private ?bool $eventsTableExists = null;
    private ?bool $requestsCompanyColumnExists = null;
    private ?bool $eventsCompanyColumnExists = null;

    public function featureAvailable(): bool
    {
        return $this->hasRequestsTable() && $this->hasEventsTable();
    }

    public function studentsForSelection(?int $companyId = null): array
    {
        $companyId = $this->normalizeCompanyId($companyId);

        if ($companyId > 0) {
            $stmt = $this->db->prepare('SELECT id, full_name, email_primary, phone, rg
                FROM students
                WHERE is_active = 1
                  AND company_id = :company_id
                ORDER BY full_name ASC');
            $stmt->execute([':company_id' => $companyId]);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->query('SELECT id, full_name, email_primary, phone, rg
            FROM students
            WHERE is_active = 1
            ORDER BY full_name ASC');
        return $stmt->fetchAll();
    }

    public function listRequests(array $filters, int $perPage, int $page, ?int $companyId = null): array
    {
        if (!$this->hasRequestsTable()) {
            return [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, $page),
            ];
        }

        $companyId = $this->normalizeCompanyId($companyId);
        $where = ['1=1'];
        $params = [];

        if ($this->hasRequestsCompanyColumn() && $companyId > 0) {
            $where[] = 'sr.company_id = :company_id';
            $params[':company_id'] = $companyId;
        } elseif ($companyId > 0) {
            $where[] = 's.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        if (!empty($filters['q'])) {
            $where[] = '(sr.title LIKE :q OR sr.signer_name LIKE :q OR sr.signer_email LIKE :q OR s.full_name LIKE :q OR sr.d4sign_document_uuid LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $where[] = 'sr.status = :status';
            $params[':status'] = trim((string) $filters['status']);
        }

        if (!empty($filters['student_id'])) {
            $where[] = 'sr.student_id = :student_id';
            $params[':student_id'] = (int) $filters['student_id'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
            FROM signature_requests sr
            INNER JOIN students s ON s.id = sr.student_id
            WHERE {$whereSql}";

        $dataSql = "SELECT
                sr.*,
                s.full_name AS student_name,
                s.email_primary AS student_email
            FROM signature_requests sr
            INNER JOIN students s ON s.id = sr.student_id
            WHERE {$whereSql}
            ORDER BY sr.updated_at DESC, sr.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function stats(?int $companyId = null): array
    {
        if (!$this->hasRequestsTable()) {
            return [
                'total' => 0,
                'draft' => 0,
                'sent' => 0,
                'signed' => 0,
                'error' => 0,
            ];
        }

        $companyId = $this->normalizeCompanyId($companyId);

        if ($this->hasRequestsCompanyColumn() && $companyId > 0) {
            $params = [':company_id' => $companyId];
            return [
                'total' => (int) $this->scalar('SELECT COUNT(*) FROM signature_requests WHERE company_id = :company_id', $params),
                'draft' => (int) $this->scalar("SELECT COUNT(*) FROM signature_requests WHERE company_id = :company_id AND status = 'draft'", $params),
                'sent' => (int) $this->scalar("SELECT COUNT(*) FROM signature_requests WHERE company_id = :company_id AND status = 'sent'", $params),
                'signed' => (int) $this->scalar("SELECT COUNT(*) FROM signature_requests WHERE company_id = :company_id AND status = 'signed'", $params),
                'error' => (int) $this->scalar("SELECT COUNT(*) FROM signature_requests WHERE company_id = :company_id AND status = 'error'", $params),
            ];
        }

        if ($companyId > 0) {
            $params = [':company_id' => $companyId];
            return [
                'total' => (int) $this->scalar('SELECT COUNT(*)
                    FROM signature_requests sr
                    INNER JOIN students s ON s.id = sr.student_id
                    WHERE s.company_id = :company_id', $params),
                'draft' => (int) $this->scalar("SELECT COUNT(*)
                    FROM signature_requests sr
                    INNER JOIN students s ON s.id = sr.student_id
                    WHERE s.company_id = :company_id
                      AND sr.status = 'draft'", $params),
                'sent' => (int) $this->scalar("SELECT COUNT(*)
                    FROM signature_requests sr
                    INNER JOIN students s ON s.id = sr.student_id
                    WHERE s.company_id = :company_id
                      AND sr.status = 'sent'", $params),
                'signed' => (int) $this->scalar("SELECT COUNT(*)
                    FROM signature_requests sr
                    INNER JOIN students s ON s.id = sr.student_id
                    WHERE s.company_id = :company_id
                      AND sr.status = 'signed'", $params),
                'error' => (int) $this->scalar("SELECT COUNT(*)
                    FROM signature_requests sr
                    INNER JOIN students s ON s.id = sr.student_id
                    WHERE s.company_id = :company_id
                      AND sr.status = 'error'", $params),
            ];
        }

        return [
            'total' => (int) $this->scalar('SELECT COUNT(*) FROM signature_requests'),
            'draft' => (int) $this->scalar("SELECT COUNT(*) FROM signature_requests WHERE status = 'draft'"),
            'sent' => (int) $this->scalar("SELECT COUNT(*) FROM signature_requests WHERE status = 'sent'"),
            'signed' => (int) $this->scalar("SELECT COUNT(*) FROM signature_requests WHERE status = 'signed'"),
            'error' => (int) $this->scalar("SELECT COUNT(*) FROM signature_requests WHERE status = 'error'"),
        ];
    }

    public function recentEvents(int $limit = 20, ?int $companyId = null): array
    {
        if (!$this->hasEventsTable()) {
            return [];
        }

        $companyId = $this->normalizeCompanyId($companyId);
        $limit = max(1, $limit);

        $sql = "SELECT
                se.*,
                sr.title AS request_title
            FROM signature_events se
            LEFT JOIN signature_requests sr ON sr.id = se.signature_request_id
            WHERE 1=1";
        $params = [];

        if ($this->hasEventsCompanyColumn() && $companyId > 0) {
            $sql .= ' AND se.company_id = :company_id';
            $params[':company_id'] = $companyId;
        } elseif ($this->hasRequestsCompanyColumn() && $companyId > 0) {
            $sql .= ' AND sr.company_id = :company_id';
            $params[':company_id'] = $companyId;
        } elseif ($companyId > 0) {
            $sql .= ' AND EXISTS (
                SELECT 1
                FROM students s
                WHERE s.id = sr.student_id
                  AND s.company_id = :company_id
            )';
            $params[':company_id'] = $companyId;
        }

        $sql .= " ORDER BY se.id DESC LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function createRequest(array $data, int $createdBy, ?int $companyId = null): int
    {
        if (!$this->hasRequestsTable()) {
            return 0;
        }

        $companyId = $this->normalizeCompanyId($companyId);
        if ($this->hasRequestsCompanyColumn() && $companyId <= 0) {
            return 0;
        }

        $now = now();
        if ($this->hasRequestsCompanyColumn() && $companyId > 0) {
            $stmt = $this->db->prepare('INSERT INTO signature_requests (
                company_id, student_id, title, description, signer_name, signer_email, signer_phone,
                file_original_path, file_signed_path, d4sign_safe_uuid, d4sign_document_uuid, d4sign_signer_key,
                status, d4sign_status, sent_at, signed_at, last_synced_at, last_error, metadata_json,
                created_by, created_at, updated_at
            ) VALUES (
                :company_id, :student_id, :title, :description, :signer_name, :signer_email, :signer_phone,
                :file_original_path, NULL, :d4sign_safe_uuid, NULL, NULL,
                :status, NULL, NULL, NULL, NULL, NULL, NULL,
                :created_by, :created_at, :updated_at
            )');

            $stmt->execute([
                ':company_id' => $companyId,
                ':student_id' => (int) $data['student_id'],
                ':title' => trim((string) $data['title']),
                ':description' => trim((string) ($data['description'] ?? '')) ?: null,
                ':signer_name' => trim((string) $data['signer_name']),
                ':signer_email' => trim((string) $data['signer_email']),
                ':signer_phone' => trim((string) ($data['signer_phone'] ?? '')) ?: null,
                ':file_original_path' => trim((string) $data['file_original_path']),
                ':d4sign_safe_uuid' => trim((string) ($data['d4sign_safe_uuid'] ?? '')) ?: null,
                ':status' => 'draft',
                ':created_by' => $createdBy > 0 ? $createdBy : null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            return (int) $this->db->lastInsertId();
        }

        $stmt = $this->db->prepare('INSERT INTO signature_requests (
            student_id, title, description, signer_name, signer_email, signer_phone,
            file_original_path, file_signed_path, d4sign_safe_uuid, d4sign_document_uuid, d4sign_signer_key,
            status, d4sign_status, sent_at, signed_at, last_synced_at, last_error, metadata_json,
            created_by, created_at, updated_at
        ) VALUES (
            :student_id, :title, :description, :signer_name, :signer_email, :signer_phone,
            :file_original_path, NULL, :d4sign_safe_uuid, NULL, NULL,
            :status, NULL, NULL, NULL, NULL, NULL, NULL,
            :created_by, :created_at, :updated_at
        )');

        $stmt->execute([
            ':student_id' => (int) $data['student_id'],
            ':title' => trim((string) $data['title']),
            ':description' => trim((string) ($data['description'] ?? '')) ?: null,
            ':signer_name' => trim((string) $data['signer_name']),
            ':signer_email' => trim((string) $data['signer_email']),
            ':signer_phone' => trim((string) ($data['signer_phone'] ?? '')) ?: null,
            ':file_original_path' => trim((string) $data['file_original_path']),
            ':d4sign_safe_uuid' => trim((string) ($data['d4sign_safe_uuid'] ?? '')) ?: null,
            ':status' => 'draft',
            ':created_by' => $createdBy > 0 ? $createdBy : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findRequest(int $id, ?int $companyId = null): ?array
    {
        if (!$this->hasRequestsTable() || $id <= 0) {
            return null;
        }

        $companyId = $this->normalizeCompanyId($companyId);
        $sql = 'SELECT
                sr.*,
                s.full_name AS student_name,
                s.email_primary AS student_email,
                s.phone AS student_phone,
                s.rg AS student_document
            FROM signature_requests sr
            INNER JOIN students s ON s.id = sr.student_id
            WHERE sr.id = :id';
        $params = [':id' => $id];

        if ($this->hasRequestsCompanyColumn() && $companyId > 0) {
            $sql .= ' AND sr.company_id = :company_id';
            $params[':company_id'] = $companyId;
        } elseif ($companyId > 0) {
            $sql .= ' AND s.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByDocumentUuid(string $documentUuid, ?int $companyId = null): ?array
    {
        if (!$this->hasRequestsTable()) {
            return null;
        }

        $documentUuid = trim($documentUuid);
        if ($documentUuid === '') {
            return null;
        }

        $companyId = $this->normalizeCompanyId($companyId);
        $sql = 'SELECT * FROM signature_requests
            WHERE d4sign_document_uuid = :uuid';
        $params = [':uuid' => $documentUuid];

        if ($this->hasRequestsCompanyColumn() && $companyId > 0) {
            $sql .= ' AND company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markSent(int $id, string $documentUuid, ?string $signerKey, array $metadata, ?int $companyId = null): void
    {
        if (!$this->hasRequestsTable()) {
            return;
        }

        $companyId = $this->normalizeCompanyId($companyId);
        $sql = 'UPDATE signature_requests SET
            d4sign_document_uuid = :document_uuid,
            d4sign_signer_key = :signer_key,
            status = :status,
            sent_at = :sent_at,
            last_synced_at = :last_synced_at,
            last_error = NULL,
            metadata_json = :metadata_json,
            updated_at = :updated_at
            WHERE id = :id';
        $params = [
            ':document_uuid' => $documentUuid,
            ':signer_key' => ($signerKey ?? '') !== '' ? $signerKey : null,
            ':status' => 'sent',
            ':sent_at' => now(),
            ':last_synced_at' => now(),
            ':metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':updated_at' => now(),
            ':id' => $id,
        ];

        if ($this->hasRequestsCompanyColumn() && $companyId > 0) {
            $sql .= ' AND company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function markSigned(int $id, ?string $signedPath, ?string $d4signStatus, array $metadata, ?int $companyId = null): void
    {
        if (!$this->hasRequestsTable()) {
            return;
        }

        $companyId = $this->normalizeCompanyId($companyId);
        $sql = 'UPDATE signature_requests SET
            file_signed_path = :signed_path,
            status = :status,
            d4sign_status = :d4sign_status,
            signed_at = :signed_at,
            last_synced_at = :last_synced_at,
            last_error = NULL,
            metadata_json = :metadata_json,
            updated_at = :updated_at
            WHERE id = :id';
        $params = [
            ':signed_path' => ($signedPath ?? '') !== '' ? $signedPath : null,
            ':status' => 'signed',
            ':d4sign_status' => ($d4signStatus ?? '') !== '' ? $d4signStatus : null,
            ':signed_at' => now(),
            ':last_synced_at' => now(),
            ':metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':updated_at' => now(),
            ':id' => $id,
        ];

        if ($this->hasRequestsCompanyColumn() && $companyId > 0) {
            $sql .= ' AND company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function markSync(int $id, string $status, ?string $d4signStatus, array $metadata, ?int $companyId = null): void
    {
        if (!$this->hasRequestsTable()) {
            return;
        }

        $companyId = $this->normalizeCompanyId($companyId);
        $sql = 'UPDATE signature_requests SET
            status = :status,
            d4sign_status = :d4sign_status,
            last_synced_at = :last_synced_at,
            metadata_json = :metadata_json,
            updated_at = :updated_at
            WHERE id = :id';
        $params = [
            ':status' => $status,
            ':d4sign_status' => ($d4signStatus ?? '') !== '' ? $d4signStatus : null,
            ':last_synced_at' => now(),
            ':metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':updated_at' => now(),
            ':id' => $id,
        ];

        if ($this->hasRequestsCompanyColumn() && $companyId > 0) {
            $sql .= ' AND company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function markError(int $id, string $message, array $metadata = [], ?int $companyId = null): void
    {
        if (!$this->hasRequestsTable()) {
            return;
        }

        $companyId = $this->normalizeCompanyId($companyId);
        $sql = 'UPDATE signature_requests SET
            status = :status,
            last_error = :last_error,
            metadata_json = :metadata_json,
            updated_at = :updated_at
            WHERE id = :id';
        $params = [
            ':status' => 'error',
            ':last_error' => trim($message) !== '' ? trim($message) : null,
            ':metadata_json' => $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ':updated_at' => now(),
            ':id' => $id,
        ];

        if ($this->hasRequestsCompanyColumn() && $companyId > 0) {
            $sql .= ' AND company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function deleteRequest(int $id, ?int $companyId = null): void
    {
        if (!$this->hasRequestsTable()) {
            return;
        }

        $companyId = $this->normalizeCompanyId($companyId);
        if ($this->hasRequestsCompanyColumn() && $companyId > 0) {
            $stmt = $this->db->prepare('DELETE FROM signature_requests WHERE id = :id AND company_id = :company_id');
            $stmt->execute([
                ':id' => $id,
                ':company_id' => $companyId,
            ]);
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM signature_requests WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function addEvent(array $event): void
    {
        if (!$this->hasEventsTable()) {
            return;
        }

        $receivedAt = trim((string) ($event['received_at'] ?? '')) !== '' ? (string) $event['received_at'] : now();

        if ($this->hasEventsCompanyColumn()) {
            $companyId = (int) ($event['company_id'] ?? 0);
            if ($companyId <= 0) {
                $companyId = $this->normalizeCompanyId();
            }

            $stmt = $this->db->prepare('INSERT INTO signature_events (
                company_id, signature_request_id, d4sign_document_uuid, event_type, event_status, event_message,
                payload_json, received_at, created_at
            ) VALUES (
                :company_id, :signature_request_id, :d4sign_document_uuid, :event_type, :event_status, :event_message,
                :payload_json, :received_at, :created_at
            )');

            $stmt->execute([
                ':company_id' => $companyId > 0 ? $companyId : null,
                ':signature_request_id' => !empty($event['signature_request_id']) ? (int) $event['signature_request_id'] : null,
                ':d4sign_document_uuid' => trim((string) ($event['d4sign_document_uuid'] ?? '')) ?: null,
                ':event_type' => trim((string) ($event['event_type'] ?? '')) ?: null,
                ':event_status' => trim((string) ($event['event_status'] ?? '')) ?: null,
                ':event_message' => trim((string) ($event['event_message'] ?? '')) ?: null,
                ':payload_json' => json_encode($event['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':received_at' => $receivedAt,
                ':created_at' => now(),
            ]);
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO signature_events (
            signature_request_id, d4sign_document_uuid, event_type, event_status, event_message,
            payload_json, received_at, created_at
        ) VALUES (
            :signature_request_id, :d4sign_document_uuid, :event_type, :event_status, :event_message,
            :payload_json, :received_at, :created_at
        )');

        $stmt->execute([
            ':signature_request_id' => !empty($event['signature_request_id']) ? (int) $event['signature_request_id'] : null,
            ':d4sign_document_uuid' => trim((string) ($event['d4sign_document_uuid'] ?? '')) ?: null,
            ':event_type' => trim((string) ($event['event_type'] ?? '')) ?: null,
            ':event_status' => trim((string) ($event['event_status'] ?? '')) ?: null,
            ':event_message' => trim((string) ($event['event_message'] ?? '')) ?: null,
            ':payload_json' => json_encode($event['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':received_at' => $receivedAt,
            ':created_at' => now(),
        ]);
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function normalizeCompanyId(?int $companyId = null): int
    {
        return (int) ($companyId ?? $this->companyId() ?? 0);
    }

    private function hasRequestsTable(): bool
    {
        if ($this->requestsTableExists !== null) {
            return $this->requestsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'signature_requests'");
        $stmt->execute();
        $this->requestsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->requestsTableExists;
    }

    private function hasEventsTable(): bool
    {
        if ($this->eventsTableExists !== null) {
            return $this->eventsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'signature_events'");
        $stmt->execute();
        $this->eventsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->eventsTableExists;
    }

    private function hasRequestsCompanyColumn(): bool
    {
        if ($this->requestsCompanyColumnExists !== null) {
            return $this->requestsCompanyColumnExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'signature_requests'
              AND column_name = 'company_id'");
        $stmt->execute();
        $this->requestsCompanyColumnExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->requestsCompanyColumnExists;
    }

    private function hasEventsCompanyColumn(): bool
    {
        if ($this->eventsCompanyColumnExists !== null) {
            return $this->eventsCompanyColumnExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'signature_events'
              AND column_name = 'company_id'");
        $stmt->execute();
        $this->eventsCompanyColumnExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->eventsCompanyColumnExists;
    }
}

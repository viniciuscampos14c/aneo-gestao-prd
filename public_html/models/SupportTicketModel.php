<?php

class SupportTicketModel extends BaseModel
{
    private ?bool $ticketsTableExists = null;
    private ?bool $attachmentsTableExists = null;
    private ?bool $commentsTableExists = null;

    public function featureAvailable(): bool
    {
        return $this->hasTicketsTable()
            && $this->hasAttachmentsTable()
            && $this->hasCommentsTable();
    }

    public function listTickets(array $filters, int $perPage, int $page): array
    {
        $companyId = $this->companyId();
        if (!$this->featureAvailable() || $companyId <= 0) {
            return [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, $page),
            ];
        }

        $where = ['st.company_id = :company_id'];
        $params = [':company_id' => $companyId];

        if (!empty($filters['q'])) {
            $where[] = '(st.ticket_code LIKE :q OR st.subject LIKE :q OR st.description LIKE :q OR st.requester_name LIKE :q OR st.requester_email LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $where[] = 'st.status = :status';
            $params[':status'] = trim((string) $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $where[] = 'st.priority = :priority';
            $params[':priority'] = trim((string) $filters['priority']);
        }

        if (!empty($filters['source'])) {
            $where[] = 'st.source = :source';
            $params[':source'] = trim((string) $filters['source']);
        }

        if (isset($filters['email_sent']) && (string) $filters['email_sent'] !== '') {
            $where[] = 'st.email_sent = :email_sent';
            $params[':email_sent'] = (int) $filters['email_sent'] > 0 ? 1 : 0;
        }

        if (isset($filters['webhook_forwarded']) && (string) $filters['webhook_forwarded'] !== '') {
            $where[] = 'st.webhook_forwarded = :webhook_forwarded';
            $params[':webhook_forwarded'] = (int) $filters['webhook_forwarded'] > 0 ? 1 : 0;
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
            FROM support_tickets st
            WHERE {$whereSql}";

        $dataSql = "SELECT
                st.*,
                u.name AS created_by_name,
                (SELECT COUNT(*) FROM support_ticket_attachments ta WHERE ta.ticket_id = st.id) AS attachments_count,
                (SELECT COUNT(*) FROM support_ticket_comments tc WHERE tc.ticket_id = st.id) AS comments_count
            FROM support_tickets st
            LEFT JOIN users u ON u.id = st.created_by
            WHERE {$whereSql}
            ORDER BY st.updated_at DESC, st.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function stats(): array
    {
        $companyId = $this->companyId();
        if (!$this->featureAvailable() || $companyId <= 0) {
            return [
                'total' => 0,
                'open' => 0,
                'in_progress' => 0,
                'resolved' => 0,
                'closed' => 0,
            ];
        }

        $params = [':company_id' => $companyId];
        $totalStmt = $this->db->prepare('SELECT COUNT(*) FROM support_tickets WHERE company_id = :company_id');
        $totalStmt->execute($params);

        $openStmt = $this->db->prepare("SELECT COUNT(*) FROM support_tickets WHERE company_id = :company_id AND status = 'open'");
        $openStmt->execute($params);

        $inProgressStmt = $this->db->prepare("SELECT COUNT(*) FROM support_tickets WHERE company_id = :company_id AND status = 'in_progress'");
        $inProgressStmt->execute($params);

        $resolvedStmt = $this->db->prepare("SELECT COUNT(*) FROM support_tickets WHERE company_id = :company_id AND status = 'resolved'");
        $resolvedStmt->execute($params);

        $closedStmt = $this->db->prepare("SELECT COUNT(*) FROM support_tickets WHERE company_id = :company_id AND status = 'closed'");
        $closedStmt->execute($params);

        return [
            'total' => (int) $totalStmt->fetchColumn(),
            'open' => (int) $openStmt->fetchColumn(),
            'in_progress' => (int) $inProgressStmt->fetchColumn(),
            'resolved' => (int) $resolvedStmt->fetchColumn(),
            'closed' => (int) $closedStmt->fetchColumn(),
        ];
    }

    public function dispatchStats(): array
    {
        $companyId = $this->companyId();
        if (!$this->featureAvailable() || $companyId <= 0) {
            return [
                'email_sent' => 0,
                'email_pending' => 0,
                'webhook_sent' => 0,
                'webhook_pending' => 0,
                'from_webhook' => 0,
            ];
        }

        $stmt = $this->db->prepare("SELECT
                SUM(CASE WHEN email_sent = 1 THEN 1 ELSE 0 END) AS email_sent,
                SUM(CASE WHEN email_sent = 0 THEN 1 ELSE 0 END) AS email_pending,
                SUM(CASE WHEN webhook_forwarded = 1 THEN 1 ELSE 0 END) AS webhook_sent,
                SUM(CASE WHEN webhook_forwarded = 0 THEN 1 ELSE 0 END) AS webhook_pending,
                SUM(CASE WHEN source = 'webhook' THEN 1 ELSE 0 END) AS from_webhook
            FROM support_tickets
            WHERE company_id = :company_id");
        $stmt->execute([':company_id' => $companyId]);
        $row = $stmt->fetch() ?: [];

        return [
            'email_sent' => (int) ($row['email_sent'] ?? 0),
            'email_pending' => (int) ($row['email_pending'] ?? 0),
            'webhook_sent' => (int) ($row['webhook_sent'] ?? 0),
            'webhook_pending' => (int) ($row['webhook_pending'] ?? 0),
            'from_webhook' => (int) ($row['from_webhook'] ?? 0),
        ];
    }

    public function listStudentTickets(int $companyId, int $studentId, string $studentEmail, array $filters, int $perPage, int $page): array
    {
        if (!$this->featureAvailable() || $companyId <= 0 || $studentId <= 0) {
            return [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, $page),
            ];
        }

        [$where, $params] = $this->studentScopeFilters($companyId, $studentId, $studentEmail, 'st');

        if (!empty($filters['q'])) {
            $where[] = '(st.ticket_code LIKE :q OR st.subject LIKE :q OR st.description LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $where[] = 'st.status = :status';
            $params[':status'] = trim((string) $filters['status']);
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
            FROM support_tickets st
            WHERE {$whereSql}";

        $dataSql = "SELECT
                st.*,
                u.name AS created_by_name,
                (SELECT COUNT(*) FROM support_ticket_attachments ta WHERE ta.ticket_id = st.id) AS attachments_count,
                (SELECT COUNT(*) FROM support_ticket_comments tc WHERE tc.ticket_id = st.id) AS comments_count
            FROM support_tickets st
            LEFT JOIN users u ON u.id = st.created_by
            WHERE {$whereSql}
            ORDER BY st.updated_at DESC, st.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function studentStats(int $companyId, int $studentId, string $studentEmail): array
    {
        if (!$this->featureAvailable() || $companyId <= 0 || $studentId <= 0) {
            return [
                'total' => 0,
                'open' => 0,
                'in_progress' => 0,
                'resolved' => 0,
                'closed' => 0,
            ];
        }

        [$where, $params] = $this->studentScopeFilters($companyId, $studentId, $studentEmail, 'st');
        $whereSql = implode(' AND ', $where);

        $stmt = $this->db->prepare("SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN st.status = 'open' THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN st.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
                SUM(CASE WHEN st.status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count,
                SUM(CASE WHEN st.status = 'closed' THEN 1 ELSE 0 END) AS closed_count
            FROM support_tickets st
            WHERE {$whereSql}");
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'open' => (int) ($row['open_count'] ?? 0),
            'in_progress' => (int) ($row['in_progress_count'] ?? 0),
            'resolved' => (int) ($row['resolved_count'] ?? 0),
            'closed' => (int) ($row['closed_count'] ?? 0),
        ];
    }

    public function listAllTickets(array $filters, int $perPage, int $page): array
    {
        if (!$this->featureAvailable()) {
            return [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, $page),
            ];
        }

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(st.ticket_code LIKE :q OR st.subject LIKE :q OR st.description LIKE :q OR st.requester_name LIKE :q OR st.requester_email LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $where[] = 'st.status = :status';
            $params[':status'] = trim((string) $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $where[] = 'st.priority = :priority';
            $params[':priority'] = trim((string) $filters['priority']);
        }

        if (!empty($filters['source'])) {
            $where[] = 'st.source = :source';
            $params[':source'] = trim((string) $filters['source']);
        }

        $companyIds = $this->sanitizeCompanyIds($filters['company_ids'] ?? []);
        if ($companyIds !== []) {
            $companyKeys = [];
            foreach ($companyIds as $idx => $cid) {
                $key = ':company_scope_' . $idx;
                $companyKeys[] = $key;
                $params[$key] = $cid;
            }
            $where[] = 'st.company_id IN (' . implode(', ', $companyKeys) . ')';
        }

        if (isset($filters['company_id']) && (int) $filters['company_id'] > 0) {
            $where[] = 'st.company_id = :company_id';
            $params[':company_id'] = (int) $filters['company_id'];
        }

        if (isset($filters['email_sent']) && (string) $filters['email_sent'] !== '') {
            $where[] = 'st.email_sent = :email_sent';
            $params[':email_sent'] = (int) $filters['email_sent'] > 0 ? 1 : 0;
        }

        if (isset($filters['webhook_forwarded']) && (string) $filters['webhook_forwarded'] !== '') {
            $where[] = 'st.webhook_forwarded = :webhook_forwarded';
            $params[':webhook_forwarded'] = (int) $filters['webhook_forwarded'] > 0 ? 1 : 0;
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
            FROM support_tickets st
            WHERE {$whereSql}";

        $dataSql = "SELECT
                st.*,
                c.legal_name AS company_legal_name,
                c.trade_name AS company_trade_name,
                u.name AS created_by_name,
                (SELECT COUNT(*) FROM support_ticket_attachments ta WHERE ta.ticket_id = st.id) AS attachments_count,
                (SELECT COUNT(*) FROM support_ticket_comments tc WHERE tc.ticket_id = st.id) AS comments_count
            FROM support_tickets st
            LEFT JOIN companies c ON c.id = st.company_id
            LEFT JOIN users u ON u.id = st.created_by
            WHERE {$whereSql}
            ORDER BY st.updated_at DESC, st.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function statsAll(array $companyIds = []): array
    {
        if (!$this->featureAvailable()) {
            return [
                'total' => 0,
                'open' => 0,
                'in_progress' => 0,
                'resolved' => 0,
                'closed' => 0,
            ];
        }

        $companyIds = $this->sanitizeCompanyIds($companyIds);
        $whereSql = '';
        $params = [];
        if ($companyIds !== []) {
            $keys = [];
            foreach ($companyIds as $idx => $cid) {
                $key = ':company_scope_' . $idx;
                $keys[] = $key;
                $params[$key] = $cid;
            }
            $whereSql = ' WHERE company_id IN (' . implode(', ', $keys) . ')';
        }

        $stmt = $this->db->prepare("SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_count
            FROM support_tickets{$whereSql}");
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'open' => (int) ($row['open_count'] ?? 0),
            'in_progress' => (int) ($row['in_progress_count'] ?? 0),
            'resolved' => (int) ($row['resolved_count'] ?? 0),
            'closed' => (int) ($row['closed_count'] ?? 0),
        ];
    }

    public function dispatchStatsAll(array $companyIds = []): array
    {
        if (!$this->featureAvailable()) {
            return [
                'email_sent' => 0,
                'email_pending' => 0,
                'webhook_sent' => 0,
                'webhook_pending' => 0,
                'from_webhook' => 0,
            ];
        }

        $companyIds = $this->sanitizeCompanyIds($companyIds);
        $whereSql = '';
        $params = [];
        if ($companyIds !== []) {
            $keys = [];
            foreach ($companyIds as $idx => $cid) {
                $key = ':company_scope_' . $idx;
                $keys[] = $key;
                $params[$key] = $cid;
            }
            $whereSql = ' WHERE company_id IN (' . implode(', ', $keys) . ')';
        }

        $stmt = $this->db->prepare("SELECT
                SUM(CASE WHEN email_sent = 1 THEN 1 ELSE 0 END) AS email_sent,
                SUM(CASE WHEN email_sent = 0 THEN 1 ELSE 0 END) AS email_pending,
                SUM(CASE WHEN webhook_forwarded = 1 THEN 1 ELSE 0 END) AS webhook_sent,
                SUM(CASE WHEN webhook_forwarded = 0 THEN 1 ELSE 0 END) AS webhook_pending,
                SUM(CASE WHEN source = 'webhook' THEN 1 ELSE 0 END) AS from_webhook
            FROM support_tickets{$whereSql}");
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'email_sent' => (int) ($row['email_sent'] ?? 0),
            'email_pending' => (int) ($row['email_pending'] ?? 0),
            'webhook_sent' => (int) ($row['webhook_sent'] ?? 0),
            'webhook_pending' => (int) ($row['webhook_pending'] ?? 0),
            'from_webhook' => (int) ($row['from_webhook'] ?? 0),
        ];
    }

    public function findTicketAny(int $id): ?array
    {
        if (!$this->hasTicketsTable() || $id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT
                st.*,
                c.legal_name AS company_legal_name,
                c.trade_name AS company_trade_name,
                u.name AS created_by_name
            FROM support_tickets st
            LEFT JOIN companies c ON c.id = st.company_id
            LEFT JOIN users u ON u.id = st.created_by
            WHERE st.id = :id
            LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateStatusAny(int $ticketId, string $status): void
    {
        if (!$this->hasTicketsTable() || $ticketId <= 0) {
            return;
        }

        $status = $this->normalizeStatus($status);
        $stmt = $this->db->prepare('UPDATE support_tickets SET
            status = :status,
            updated_at = :updated_at
            WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':updated_at' => now(),
            ':id' => $ticketId,
        ]);
    }

    public function addCommentAny(int $ticketId, string $comment, ?int $createdBy): void
    {
        if (!$this->hasCommentsTable() || $ticketId <= 0) {
            return;
        }

        $comment = trim($comment);
        if ($comment === '') {
            return;
        }

        $ticket = $this->findTicketAny($ticketId);
        if (!$ticket) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO support_ticket_comments (
            ticket_id, comment, is_internal, created_by, created_at
        ) VALUES (
            :ticket_id, :comment, :is_internal, :created_by, :created_at
        )');
        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':comment' => $comment,
            ':is_internal' => 0,
            ':created_by' => ($createdBy ?? 0) > 0 ? (int) $createdBy : null,
            ':created_at' => now(),
        ]);

        $this->db->prepare('UPDATE support_tickets SET updated_at = :updated_at WHERE id = :id')
            ->execute([
                ':updated_at' => now(),
                ':id' => $ticketId,
            ]);
    }

    public function attachmentsByTicketIdsAny(array $ticketIds): array
    {
        $ticketIds = array_values(array_filter(array_map('intval', $ticketIds), fn ($id) => $id > 0));
        if (!$this->hasAttachmentsTable() || $ticketIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $sql = "SELECT
                ta.*,
                u.name AS uploaded_by_name
            FROM support_ticket_attachments ta
            LEFT JOIN users u ON u.id = ta.created_by
            WHERE ta.ticket_id IN ({$placeholders})
            ORDER BY ta.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($ticketIds);
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $tid = (int) ($row['ticket_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            if (!isset($grouped[$tid])) {
                $grouped[$tid] = [];
            }
            $grouped[$tid][] = $row;
        }

        return $grouped;
    }

    public function commentsByTicketIdsAny(array $ticketIds): array
    {
        $ticketIds = array_values(array_filter(array_map('intval', $ticketIds), fn ($id) => $id > 0));
        if (!$this->hasCommentsTable() || $ticketIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $sql = "SELECT
                tc.*,
                u.name AS author_name
            FROM support_ticket_comments tc
            LEFT JOIN users u ON u.id = tc.created_by
            WHERE tc.ticket_id IN ({$placeholders})
            ORDER BY tc.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($ticketIds);
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $tid = (int) ($row['ticket_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            if (!isset($grouped[$tid])) {
                $grouped[$tid] = [];
            }
            $grouped[$tid][] = $row;
        }

        return $grouped;
    }

    public function findTicket(int $id): ?array
    {
        return $this->findTicketScoped($id, $this->companyId());
    }

    public function findTicketForCompany(int $companyId, int $id): ?array
    {
        return $this->findTicketScoped($id, $companyId);
    }

    public function createTicket(array $data, ?int $createdBy, string $source = 'internal'): int
    {
        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return 0;
        }

        return $this->createTicketForCompany($companyId, $data, $createdBy, $source);
    }

    public function createTicketForCompany(int $companyId, array $data, ?int $createdBy, string $source = 'internal'): int
    {
        if (!$this->featureAvailable() || $companyId <= 0) {
            return 0;
        }

        $ticketCode = $this->temporaryTicketCode($companyId);
        $priority = $this->normalizePriority((string) ($data['priority'] ?? 'medium'));
        $subject = trim((string) ($data['subject'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if ($subject === '' || $description === '') {
            return 0;
        }

        $stmt = $this->db->prepare('INSERT INTO support_tickets (
            company_id, ticket_code, subject, description, priority, status, source,
            requester_name, requester_email, email_sent, webhook_forwarded, external_reference,
            created_by, created_at, updated_at
        ) VALUES (
            :company_id, :ticket_code, :subject, :description, :priority, :status, :source,
            :requester_name, :requester_email, :email_sent, :webhook_forwarded, :external_reference,
            :created_by, :created_at, :updated_at
        )');

        $now = now();
        $stmt->execute([
            ':company_id' => $companyId,
            ':ticket_code' => $ticketCode,
            ':subject' => $subject,
            ':description' => $description,
            ':priority' => $priority,
            ':status' => 'open',
            ':source' => trim($source) !== '' ? trim($source) : 'internal',
            ':requester_name' => trim((string) ($data['requester_name'] ?? '')) ?: null,
            ':requester_email' => trim((string) ($data['requester_email'] ?? '')) ?: null,
            ':email_sent' => 0,
            ':webhook_forwarded' => 0,
            ':external_reference' => trim((string) ($data['external_reference'] ?? '')) ?: null,
            ':created_by' => ($createdBy ?? 0) > 0 ? (int) $createdBy : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $ticketId = (int) $this->db->lastInsertId();
        if ($ticketId <= 0) {
            return 0;
        }

        $this->db->prepare('UPDATE support_tickets
            SET ticket_code = :ticket_code
            WHERE id = :id')
            ->execute([
                ':ticket_code' => $this->formatTicketCode($ticketId),
                ':id' => $ticketId,
            ]);

        return $ticketId;
    }

    public function updateStatus(int $ticketId, string $status): void
    {
        if (!$this->hasTicketsTable()) {
            return;
        }

        $companyId = $this->companyId();
        if ($companyId <= 0 || $ticketId <= 0) {
            return;
        }

        $status = $this->normalizeStatus($status);
        $stmt = $this->db->prepare('UPDATE support_tickets SET
            status = :status,
            updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id');
        $stmt->execute([
            ':status' => $status,
            ':updated_at' => now(),
            ':id' => $ticketId,
            ':company_id' => $companyId,
        ]);
    }

    public function addAttachment(int $ticketId, string $fileName, string $filePath, ?string $fileType, ?int $fileSize, ?int $createdBy): void
    {
        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return;
        }

        $this->addAttachmentForCompany($companyId, $ticketId, $fileName, $filePath, $fileType, $fileSize, $createdBy);
    }

    public function addAttachmentForCompany(int $companyId, int $ticketId, string $fileName, string $filePath, ?string $fileType, ?int $fileSize, ?int $createdBy): void
    {
        if (!$this->hasAttachmentsTable() || $ticketId <= 0 || $companyId <= 0) {
            return;
        }

        $ticket = $this->findTicketScoped($ticketId, $companyId);
        if (!$ticket) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO support_ticket_attachments (
            ticket_id, file_name, file_path, file_type, file_size, created_by, created_at
        ) VALUES (
            :ticket_id, :file_name, :file_path, :file_type, :file_size, :created_by, :created_at
        )');

        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':file_name' => trim($fileName) !== '' ? trim($fileName) : 'anexo',
            ':file_path' => trim($filePath),
            ':file_type' => trim((string) ($fileType ?? '')) ?: null,
            ':file_size' => ($fileSize ?? 0) > 0 ? (int) $fileSize : null,
            ':created_by' => ($createdBy ?? 0) > 0 ? (int) $createdBy : null,
            ':created_at' => now(),
        ]);
    }

    public function addComment(int $ticketId, string $comment, ?int $createdBy): void
    {
        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return;
        }

        $this->addCommentForCompany($companyId, $ticketId, $comment, $createdBy);
    }

    public function addCommentForCompany(int $companyId, int $ticketId, string $comment, ?int $createdBy): void
    {
        if (!$this->hasCommentsTable() || $ticketId <= 0 || $companyId <= 0) {
            return;
        }

        $comment = trim($comment);
        if ($comment === '') {
            return;
        }

        $ticket = $this->findTicketScoped($ticketId, $companyId);
        if (!$ticket) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO support_ticket_comments (
            ticket_id, comment, is_internal, created_by, created_at
        ) VALUES (
            :ticket_id, :comment, :is_internal, :created_by, :created_at
        )');
        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':comment' => $comment,
            ':is_internal' => 0,
            ':created_by' => ($createdBy ?? 0) > 0 ? (int) $createdBy : null,
            ':created_at' => now(),
        ]);

        $this->touchTicket($ticketId, $companyId);
    }

    public function attachmentsByTicketIds(array $ticketIds): array
    {
        $ticketIds = array_values(array_filter(array_map('intval', $ticketIds), fn ($id) => $id > 0));
        if (!$this->hasAttachmentsTable() || $ticketIds === []) {
            return [];
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $sql = "SELECT
                ta.*,
                u.name AS uploaded_by_name
            FROM support_ticket_attachments ta
            INNER JOIN support_tickets st ON st.id = ta.ticket_id AND st.company_id = ?
            LEFT JOIN users u ON u.id = ta.created_by
            WHERE ta.ticket_id IN ({$placeholders})
            ORDER BY ta.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$companyId], $ticketIds));
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $tid = (int) ($row['ticket_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            if (!isset($grouped[$tid])) {
                $grouped[$tid] = [];
            }
            $grouped[$tid][] = $row;
        }

        return $grouped;
    }

    public function commentsByTicketIds(array $ticketIds): array
    {
        $ticketIds = array_values(array_filter(array_map('intval', $ticketIds), fn ($id) => $id > 0));
        if (!$this->hasCommentsTable() || $ticketIds === []) {
            return [];
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $sql = "SELECT
                tc.*,
                u.name AS author_name
            FROM support_ticket_comments tc
            INNER JOIN support_tickets st ON st.id = tc.ticket_id AND st.company_id = ?
            LEFT JOIN users u ON u.id = tc.created_by
            WHERE tc.ticket_id IN ({$placeholders})
            ORDER BY tc.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$companyId], $ticketIds));
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $tid = (int) ($row['ticket_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            if (!isset($grouped[$tid])) {
                $grouped[$tid] = [];
            }
            $grouped[$tid][] = $row;
        }

        return $grouped;
    }

    public function markDispatchStatus(int $ticketId, bool $emailSent, bool $webhookForwarded, ?string $externalReference = null): void
    {
        if (!$this->hasTicketsTable()) {
            return;
        }

        $companyId = $this->companyId();
        if ($companyId <= 0 || $ticketId <= 0) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE support_tickets SET
            email_sent = :email_sent,
            webhook_forwarded = :webhook_forwarded,
            external_reference = :external_reference,
            updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id');
        $stmt->execute([
            ':email_sent' => $emailSent ? 1 : 0,
            ':webhook_forwarded' => $webhookForwarded ? 1 : 0,
            ':external_reference' => trim((string) ($externalReference ?? '')) ?: null,
            ':updated_at' => now(),
            ':id' => $ticketId,
            ':company_id' => $companyId,
        ]);
    }

    public function firstCompanyId(): int
    {
        $stmt = $this->db->query('SELECT id FROM companies ORDER BY id ASC LIMIT 1');
        return (int) ($stmt ? $stmt->fetchColumn() : 0);
    }

    private function findTicketScoped(int $id, int $companyId): ?array
    {
        if (!$this->hasTicketsTable() || $id <= 0 || $companyId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT st.*, u.name AS created_by_name
            FROM support_tickets st
            LEFT JOIN users u ON u.id = st.created_by
            WHERE st.id = :id
              AND st.company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $companyId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function touchTicket(int $ticketId, int $companyId): void
    {
        $stmt = $this->db->prepare('UPDATE support_tickets
            SET updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id');
        $stmt->execute([
            ':updated_at' => now(),
            ':id' => $ticketId,
            ':company_id' => $companyId,
        ]);
    }

    private function studentScopeFilters(int $companyId, int $studentId, string $studentEmail, string $alias = 'st'): array
    {
        $alias = trim($alias) !== '' ? trim($alias) : 'st';
        $where = [
            "{$alias}.company_id = :company_id",
            "{$alias}.source = :source",
        ];
        $params = [
            ':company_id' => $companyId,
            ':source' => 'student_portal',
            ':student_ref' => 'student:' . $studentId,
        ];

        $studentEmail = trim($studentEmail);
        if ($studentEmail !== '') {
            $where[] = "({$alias}.external_reference = :student_ref OR ({$alias}.external_reference IS NULL AND {$alias}.requester_email = :student_email))";
            $params[':student_email'] = $studentEmail;
        } else {
            $where[] = "{$alias}.external_reference = :student_ref";
        }

        return [$where, $params];
    }

    private function temporaryTicketCode(int $companyId): string
    {
        return 'TMP-' . $companyId . '-' . date('YmdHis') . '-' . random_int(1000, 9999);
    }

    private function formatTicketCode(int $ticketId): string
    {
        return 'ANEO' . str_pad((string) $ticketId, 3, '0', STR_PAD_LEFT);
    }

    private function normalizePriority(string $priority): string
    {
        $priority = strtolower(trim($priority));
        return in_array($priority, ['low', 'medium', 'high', 'urgent'], true) ? $priority : 'medium';
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true) ? $status : 'open';
    }

    private function sanitizeCompanyIds($companyIds): array
    {
        if (!is_array($companyIds)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $companyIds), fn ($id) => $id > 0)));
    }

    private function hasTicketsTable(): bool
    {
        if ($this->ticketsTableExists !== null) {
            return $this->ticketsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'support_tickets'");
        $stmt->execute();
        $this->ticketsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->ticketsTableExists;
    }

    private function hasAttachmentsTable(): bool
    {
        if ($this->attachmentsTableExists !== null) {
            return $this->attachmentsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'support_ticket_attachments'");
        $stmt->execute();
        $this->attachmentsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->attachmentsTableExists;
    }

    private function hasCommentsTable(): bool
    {
        if ($this->commentsTableExists !== null) {
            return $this->commentsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'support_ticket_comments'");
        $stmt->execute();
        $this->commentsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->commentsTableExists;
    }
}

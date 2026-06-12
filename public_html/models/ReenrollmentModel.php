<?php

class ReenrollmentModel extends BaseModel
{
    private const INTERVAL_MONTHS = 6;
    // Quantos dias antes do vencimento a tela começa a aparecer (aviso)
    private const WARN_BEFORE_DAYS = 30;

    // -------------------------------------------------------------------------
    // Verificação de feature
    // -------------------------------------------------------------------------

    public function tableExists(): bool
    {
        return $this->schemaTableExists('reenrollments');
    }

    public function adminControlAvailable(): bool
    {
        return $this->tableExists()
            && $this->schemaColumnExists('reenrollments', 'admin_viewed_at')
            && $this->schemaColumnExists('reenrollments', 'confirmation_email_sent_at')
            && $this->schemaColumnExists('reenrollments', 'confirmation_email_error');
    }

    // -------------------------------------------------------------------------
    // Lógica principal
    // -------------------------------------------------------------------------

    /**
     * Aviso: retorna true quando faltam 30 dias ou menos para o vencimento,
     * ou quando já venceu. Usado para mostrar a tela no dashboard.
     */
    public function isDue(int $studentId): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        $periodEnd = $this->currentPeriodEnd($studentId);
        if ($periodEnd === null) {
            return false;
        }

        $threshold = date('Y-m-d', strtotime('+' . self::WARN_BEFORE_DAYS . ' days'));
        return $periodEnd <= $threshold;
    }

    /**
     * Bloqueio total: retorna true somente quando o prazo já expirou.
     * Usado para bloquear TODAS as rotas do portal.
     */
    public function isExpired(int $studentId): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        $periodEnd = $this->currentPeriodEnd($studentId);
        if ($periodEnd === null) {
            return false;
        }

        return $periodEnd < date('Y-m-d');
    }

    /**
     * Retorna a data de fim do período atual (não confirmado).
     * null = aluno ainda não está em janela ou não precisa rematricularr.
     */
    public function currentPeriodEnd(int $studentId): ?string
    {
        if (!$this->tableExists()) {
            return null;
        }

        // Último registro de rematrícula (confirmado ou não)
        $stmt = $this->db->prepare(
            "SELECT period_start, period_end, confirmed_at
             FROM reenrollments
             WHERE student_id = :sid
             ORDER BY period_end DESC
             LIMIT 1"
        );
        $stmt->execute([':sid' => $studentId]);
        $last = $stmt->fetch();

        if ($last !== false) {
            // Se o último registro ainda não foi confirmado, usa o period_end dele
            if ($last['confirmed_at'] === null) {
                return (string) $last['period_end'];
            }
            // Se já confirmado, próximo período = period_end + 6 meses
            $nextEnd = date('Y-m-d', strtotime($last['period_end'] . ' +' . self::INTERVAL_MONTHS . ' months'));
            return $nextEnd;
        }

        // Nunca rematriculou: usa enrolled_at (ou created_at como fallback) do aluno como base
        $stmt2 = $this->db->prepare(
            "SELECT enrolled_at, created_at FROM students WHERE id = :sid LIMIT 1"
        );
        $stmt2->execute([':sid' => $studentId]);
        $row = $stmt2->fetch();
        if ($row === false) {
            return null;
        }

        $base = !empty($row['enrolled_at']) ? $row['enrolled_at'] : date('Y-m-d', strtotime((string) $row['created_at']));
        $firstEnd = date('Y-m-d', strtotime($base . ' +' . self::INTERVAL_MONTHS . ' months'));
        return $firstEnd;
    }

    /**
     * Retorna dados do período pendente para exibição na tela.
     */
    public function getPendingPeriod(int $studentId): array
    {
        $periodEnd = $this->currentPeriodEnd($studentId);
        if ($periodEnd === null) {
            return [];
        }

        $periodStart = date('Y-m-d', strtotime($periodEnd . ' -' . self::INTERVAL_MONTHS . ' months'));

        return [
            'period_start' => $periodStart,
            'period_end'   => $periodEnd,
        ];
    }

    /**
     * Confirma a rematrícula do aluno e cria o próximo período.
     */
    public function confirm(int $studentId, int $companyId, string $ip): int
    {
        if (!$this->tableExists()) {
            return 0;
        }

        $now    = now();
        $today  = date('Y-m-d');
        $period = $this->getPendingPeriod($studentId);

        if ($period === []) {
            return 0;
        }

        $periodStart = $period['period_start'];
        $periodEnd   = $period['period_end'];

        // Verifica se já existe registro não confirmado para este período
        $stmt = $this->db->prepare(
            "SELECT id FROM reenrollments
             WHERE student_id = :sid AND period_end = :pend AND confirmed_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([':sid' => $studentId, ':pend' => $periodEnd]);
        $existing = $stmt->fetch();

        if ($existing !== false) {
            // Atualiza o registro existente
            $upd = $this->db->prepare(
                "UPDATE reenrollments
                 SET confirmed_at = :now, confirmed_ip = :ip, updated_at = :upd
                 WHERE id = :id"
            );
            $ok = $upd->execute([
                ':now' => $now,
                ':ip'  => $ip,
                ':upd' => $now,
                ':id'  => (int) $existing['id'],
            ]);
            return $ok ? (int) $existing['id'] : 0;
        }

        // Cria novo registro já confirmado
        $ins = $this->db->prepare(
            "INSERT INTO reenrollments
                (student_id, company_id, period_start, period_end, confirmed_at, confirmed_ip, created_at, updated_at)
             VALUES
                (:sid, :cid, :pstart, :pend, :now, :ip, :cat, :uat)"
        );
        $ok = $ins->execute([
            ':sid'    => $studentId,
            ':cid'    => $companyId,
            ':pstart' => $periodStart,
            ':pend'   => $periodEnd,
            ':now'    => $now,
            ':ip'     => $ip,
            ':cat'    => $now,
            ':uat'    => $now,
        ]);
        return $ok ? (int) $this->db->lastInsertId() : 0;
    }

    public function markConfirmationEmail(int $reenrollmentId, bool $sent, string $error = ''): void
    {
        if (!$this->adminControlAvailable() || $reenrollmentId <= 0) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE reenrollments
            SET confirmation_email_sent_at = :sent_at,
                confirmation_email_error = :error,
                updated_at = :updated_at
            WHERE id = :id');
        $stmt->execute([
            ':sent_at' => $sent ? now() : null,
            ':error' => $error !== '' ? $error : null,
            ':updated_at' => now(),
            ':id' => $reenrollmentId,
        ]);
    }

    public function latestConfirmedAlerts(int $companyId, int $limit = 5): array
    {
        if (!$this->adminControlAvailable() || $companyId <= 0) {
            return [];
        }

        $limit = max(1, min(20, $limit));
        $stmt = $this->db->prepare("SELECT
                r.*,
                s.full_name AS student_name,
                s.email_primary AS student_email
            FROM reenrollments r
            INNER JOIN students s ON s.id = r.student_id
            WHERE r.company_id = :company_id
              AND r.confirmed_at IS NOT NULL
              AND r.admin_viewed_at IS NULL
            ORDER BY r.confirmed_at DESC, r.id DESC
            LIMIT {$limit}");
        $stmt->execute([':company_id' => $companyId]);
        return $stmt->fetchAll();
    }

    public function countUnviewedConfirmed(int $companyId): int
    {
        if (!$this->adminControlAvailable() || $companyId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*)
            FROM reenrollments
            WHERE company_id = :company_id
              AND confirmed_at IS NOT NULL
              AND admin_viewed_at IS NULL');
        $stmt->execute([':company_id' => $companyId]);
        return (int) $stmt->fetchColumn();
    }

    public function confirmedList(int $companyId, array $filters, int $perPage, int $page): array
    {
        if (!$this->tableExists() || $companyId <= 0) {
            return ['rows' => [], 'meta' => pagination_meta(0, $perPage, 1)];
        }

        $where = ['r.company_id = :company_id', 'r.confirmed_at IS NOT NULL'];
        $params = [':company_id' => $companyId];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(s.full_name LIKE :q OR s.email_primary LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $where[] = 'DATE(r.confirmed_at) BETWEEN :start_date AND :end_date';
            $params[':start_date'] = (string) $filters['start_date'];
            $params[':end_date'] = (string) $filters['end_date'];
        }

        $whereSql = implode(' AND ', $where);
        $emailFields = $this->adminControlAvailable()
            ? 'r.admin_viewed_at, r.confirmation_email_sent_at, r.confirmation_email_error'
            : 'NULL AS admin_viewed_at, NULL AS confirmation_email_sent_at, NULL AS confirmation_email_error';

        return $this->paginate(
            "SELECT COUNT(*)
                FROM reenrollments r
                INNER JOIN students s ON s.id = r.student_id
                WHERE {$whereSql}",
            "SELECT r.id, r.student_id, r.company_id, r.period_start, r.period_end, r.confirmed_at, r.confirmed_ip, {$emailFields},
                    s.full_name AS student_name, s.email_primary AS student_email
                FROM reenrollments r
                INNER JOIN students s ON s.id = r.student_id
                WHERE {$whereSql}
                ORDER BY r.confirmed_at DESC, r.id DESC",
            $params,
            $perPage,
            $page
        );
    }

    public function markConfirmedViewed(int $companyId): void
    {
        if (!$this->adminControlAvailable() || $companyId <= 0) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE reenrollments
            SET admin_viewed_at = :viewed_at,
                updated_at = :updated_at
            WHERE company_id = :company_id
              AND confirmed_at IS NOT NULL
              AND admin_viewed_at IS NULL');
        $stmt->execute([
            ':viewed_at' => now(),
            ':updated_at' => now(),
            ':company_id' => $companyId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Situação financeira
    // -------------------------------------------------------------------------

    /**
     * Retorna faturas inadimplentes do aluno.
     * Faturas futuras renegociadas ficam em aberto, mas nao devem travar rematricula.
     * Array vazio = aluno regular para rematricula.
     */
    public function openInvoices(int $studentId, int $companyId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                id,
                invoice_number,
                due_date,
                amount,
                paid_amount,
                status,
                GREATEST(amount - COALESCE(paid_amount, 0), 0) AS outstanding_amount
             FROM invoices
             WHERE student_id = :sid
               AND company_id = :cid
               AND status IN ('open', 'overdue', 'partial')
               AND (status = 'overdue' OR due_date < CURDATE())
               AND GREATEST(amount - COALESCE(paid_amount, 0), 0) > 0.009
             ORDER BY due_date ASC"
        );
        $stmt->execute([':sid' => $studentId, ':cid' => $companyId]);
        return $stmt->fetchAll();
    }
}

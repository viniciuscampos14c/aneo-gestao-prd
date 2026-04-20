<?php

class ReenrollmentModel extends BaseModel
{
    private const INTERVAL_MONTHS = 6;
    // Quantos dias antes do vencimento a tela começa a aparecer
    private const SHOW_BEFORE_DAYS = 15;

    // -------------------------------------------------------------------------
    // Verificação de feature
    // -------------------------------------------------------------------------

    public function tableExists(): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'reenrollments'");
        $stmt->execute();
        return ((int) $stmt->fetchColumn()) > 0;
    }

    // -------------------------------------------------------------------------
    // Lógica principal
    // -------------------------------------------------------------------------

    /**
     * Retorna true se o aluno está na janela de rematrícula
     * (período vencido ou vencendo nos próximos SHOW_BEFORE_DAYS dias).
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

        $threshold = date('Y-m-d', strtotime('+' . self::SHOW_BEFORE_DAYS . ' days'));
        return $periodEnd <= $threshold;
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

        // Nunca rematriculou: usa created_at do aluno como base
        $stmt2 = $this->db->prepare(
            "SELECT created_at FROM students WHERE id = :sid LIMIT 1"
        );
        $stmt2->execute([':sid' => $studentId]);
        $row = $stmt2->fetch();
        if ($row === false) {
            return null;
        }

        $enrolledAt = date('Y-m-d', strtotime((string) $row['created_at']));
        $firstEnd   = date('Y-m-d', strtotime($enrolledAt . ' +' . self::INTERVAL_MONTHS . ' months'));
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
    public function confirm(int $studentId, int $companyId, string $ip): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        $now    = now();
        $today  = date('Y-m-d');
        $period = $this->getPendingPeriod($studentId);

        if ($period === []) {
            return false;
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
            return $upd->execute([
                ':now' => $now,
                ':ip'  => $ip,
                ':upd' => $now,
                ':id'  => (int) $existing['id'],
            ]);
        }

        // Cria novo registro já confirmado
        $ins = $this->db->prepare(
            "INSERT INTO reenrollments
                (student_id, company_id, period_start, period_end, confirmed_at, confirmed_ip, created_at, updated_at)
             VALUES
                (:sid, :cid, :pstart, :pend, :now, :ip, :cat, :uat)"
        );
        return $ins->execute([
            ':sid'    => $studentId,
            ':cid'    => $companyId,
            ':pstart' => $periodStart,
            ':pend'   => $periodEnd,
            ':now'    => $now,
            ':ip'     => $ip,
            ':cat'    => $now,
            ':uat'    => $now,
        ]);
    }

    // -------------------------------------------------------------------------
    // Situação financeira
    // -------------------------------------------------------------------------

    /**
     * Retorna faturas em aberto/vencidas/parciais do aluno.
     * Array vazio = aluno em dia.
     */
    public function openInvoices(int $studentId, int $companyId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, invoice_number, due_date, amount, paid_amount, status
             FROM invoices
             WHERE student_id = :sid
               AND company_id = :cid
               AND status IN ('open', 'overdue', 'partial')
             ORDER BY due_date ASC"
        );
        $stmt->execute([':sid' => $studentId, ':cid' => $companyId]);
        return $stmt->fetchAll();
    }
}

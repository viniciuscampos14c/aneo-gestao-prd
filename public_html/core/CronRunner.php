<?php

/**
 * CronRunner — executa jobs agendados do sistema ANEO.
 *
 * Uso via cron.php:
 *   curl "https://erp.aneobrasil.com.br/cron.php?token=SECRET&job=all"
 *   curl "https://erp.aneobrasil.com.br/cron.php?token=SECRET&job=finance_billing_notifications"
 *
 * Uso interno (admin):
 *   CronRunner::run('finance_billing_notifications')
 */
class CronRunner
{
    private PDO $db;

    /** Jobs registrados: job_key => callable que retorna ['ok'=>bool,'message'=>string] */
    private array $jobs = [];

    public function __construct()
    {
        $this->db = db();
        $this->registerJobs();
    }

    // -----------------------------------------------------------------
    // API pública
    // -----------------------------------------------------------------

    /** Executa um job pelo job_key e retorna resultado. */
    public function run(string $jobKey): array
    {
        if (!isset($this->jobs[$jobKey])) {
            return ['ok' => false, 'message' => "Job '{$jobKey}' nao encontrado."];
        }

        if (!$this->isEnabled($jobKey)) {
            return ['ok' => false, 'message' => "Job '{$jobKey}' esta desativado."];
        }

        $logId = $this->logStart($jobKey);
        $start = microtime(true);

        try {
            $result = ($this->jobs[$jobKey])();
        } catch (Throwable $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $status     = ($result['ok'] ?? false) ? 'ok' : 'error';
        $message    = (string) ($result['message'] ?? '');

        $this->logFinish($logId, $jobKey, $status, $message, $durationMs);

        return array_merge($result, ['duration_ms' => $durationMs]);
    }

    /** Executa todos os jobs habilitados e retorna resultados por job_key. */
    public function runAll(): array
    {
        $results = [];
        foreach (array_keys($this->jobs) as $key) {
            $results[$key] = $this->run($key);
        }
        return $results;
    }

    /** Retorna lista de jobs com status atual do banco. */
    public function listJobs(): array
    {
        $stmt = $this->db->query(
            "SELECT job_key, label, description, enabled,
                    last_run_at, last_status, last_message, last_duration_ms
             FROM cron_jobs
             ORDER BY label"
        );
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /** Retorna logs paginados de um job. */
    public function logs(string $jobKey, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, job_key, started_at, finished_at, status, message, duration_ms
             FROM cron_job_logs
             WHERE job_key = :key
             ORDER BY started_at DESC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':key', $jobKey);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Ativa ou desativa um job. */
    public function setEnabled(string $jobKey, bool $enabled): void
    {
        $stmt = $this->db->prepare(
            "UPDATE cron_jobs SET enabled = :e WHERE job_key = :k"
        );
        $stmt->execute([':e' => $enabled ? 1 : 0, ':k' => $jobKey]);
    }

    // -----------------------------------------------------------------
    // Registro dos jobs
    // -----------------------------------------------------------------

    private function registerJobs(): void
    {
        $this->jobs['finance_billing_notifications'] = function (): array {
            return $this->jobFinanceBillingNotifications();
        };

        $this->jobs['boleto_issue_due'] = function (): array {
            return $this->jobBoletoIssueDue();
        };

        $this->jobs['boleto_sync'] = function (): array {
            return $this->jobBoletoSync();
        };

        $this->jobs['signatures_sync'] = function (): array {
            return $this->jobSignaturesSync();
        };
    }

    // -----------------------------------------------------------------
    // Implementações dos jobs
    // -----------------------------------------------------------------

    /**
     * Job: envia e-mails de cobrança para faturas próximas do vencimento / vencidas.
     */
    private function jobFinanceBillingNotifications(): array
    {
        if (!(bool) config('automation.enabled', true)) {
            return ['ok' => false, 'message' => 'Automacoes desativadas no config.php.'];
        }

        $model    = new FinanceNotificationModel();
        $dispatch = $model->dispatchDueNotifications(null, date('Y-m-d'));

        $sent   = (int) ($dispatch['sent']   ?? 0);
        $errors = (int) ($dispatch['errors'] ?? 0);
        $msg    = "Notificacoes enviadas: {$sent}. Erros: {$errors}.";

        return ['ok' => $errors === 0, 'message' => $msg, 'data' => $dispatch];
    }

    /**
     * Job: emite boletos Itau apenas para faturas que entraram na janela configurada.
     */
    private function jobBoletoIssueDue(): array
    {
        $stmt = $this->db->query(
            "SELECT DISTINCT i.company_id
             FROM invoices i
             INNER JOIN payment_methods pm ON pm.id = i.payment_method_id AND pm.company_id = i.company_id
             WHERE i.status IN ('open','partial','overdue')
               AND pm.mode = 'integrated'
               AND pm.provider_key = 'itau'
             ORDER BY i.company_id ASC"
        );

        if (!$stmt) {
            return ['ok' => true, 'message' => 'Nenhuma empresa elegivel para emissao automatica de boletos.'];
        }

        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($companies === []) {
            return ['ok' => true, 'message' => 'Nenhuma empresa elegivel para emissao automatica de boletos.'];
        }

        $processed = 0;
        $issued = 0;
        $pending = 0;
        $errors = 0;

        foreach ($companies as $row) {
            $companyId = (int) ($row['company_id'] ?? 0);
            if ($companyId <= 0) {
                continue;
            }

            $result = (new FinanceModel($companyId))->issueDueBankSlips(0, 10);
            $processed += (int) ($result['processed'] ?? 0);
            $issued += (int) ($result['issued'] ?? 0);
            $pending += (int) ($result['pending'] ?? 0);
            $errors += (int) ($result['errors'] ?? 0);
        }

        return [
            'ok' => $errors === 0,
            'message' => "Faturas processadas: {$processed}. Emitidos: {$issued}. Pendentes: {$pending}. Erros: {$errors}.",
        ];
    }

    /**
     * Job: sincroniza status de boletos com status 'pending' ou 'processing'.
     */
    private function jobBoletoSync(): array
    {
        if (!config('boleto.enabled', false)) {
            return ['ok' => true, 'message' => 'Boleto nao configurado — job ignorado.'];
        }

        // Busca boletos pendentes de todas as empresas
        $stmt = $this->db->query(
            "SELECT bs.id AS slip_id, bs.invoice_id, i.company_id
             FROM bank_slips bs
             INNER JOIN invoices i ON i.id = bs.invoice_id
             WHERE bs.status IN ('pending','processing')
             ORDER BY bs.created_at ASC
             LIMIT 100"
        );

        if (!$stmt) {
            return ['ok' => true, 'message' => 'Nenhum boleto pendente encontrado.'];
        }

        $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $synced = 0;
        $errors = 0;

        foreach ($rows as $row) {
            $companyId = (int) ($row['company_id'] ?? 0);
            if ($companyId <= 0) {
                $errors++;
                continue;
            }

            $result = (new FinanceModel($companyId))->syncBankSlipStatus((int) $row['invoice_id'], 0);
            if ($result['ok'] ?? false) {
                $synced++;
            } else {
                $errors++;
            }
        }

        $total = count($rows);
        $msg   = "Boletos verificados: {$total}. Atualizados: {$synced}. Erros: {$errors}.";

        return ['ok' => $errors === 0, 'message' => $msg];
    }

    /**
     * Job: sincroniza status de assinaturas D4Sign com status 'sent'.
     */
    private function jobSignaturesSync(): array
    {
        $cfg = config('d4sign', []);
        if (empty($cfg['api_key']) || empty($cfg['api_key'] !== '')) {
            // d4sign pode nao estar configurado — verifica se a feature está ativa
        }

        // Busca assinaturas enviadas e ainda não assinadas
        $stmt = $this->db->query(
            "SELECT id, company_id, d4sign_document_uuid, metadata_json, file_signed_path
             FROM signature_requests
             WHERE status = 'sent'
               AND d4sign_document_uuid IS NOT NULL
               AND d4sign_document_uuid != ''
             ORDER BY sent_at ASC
             LIMIT 50"
        );

        if (!$stmt) {
            return ['ok' => true, 'message' => 'Nenhuma assinatura pendente.'];
        }

        $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return ['ok' => true, 'message' => 'Nenhuma assinatura pendente.'];
        }

        $d4sign  = new D4SignService();
        $model   = new SignatureModel();
        $synced  = 0;
        $errors  = 0;

        foreach ($rows as $row) {
            $id          = (int) $row['id'];
            $companyId   = (int) ($row['company_id'] ?? 0);
            $uuid        = (string) $row['d4sign_document_uuid'];
            $metaRaw     = (string) ($row['metadata_json'] ?? '');
            $metadata    = $metaRaw !== '' ? (json_decode($metaRaw, true) ?: []) : [];

            $details = $d4sign->documentDetails($uuid);
            if (!$details['ok']) {
                $metadata['cron_error'] = $details['message'] ?? 'Falha ao consultar D4Sign.';
                $model->markError($id, (string) ($details['message'] ?? 'Erro cron'), $metadata, $companyId);
                $errors++;
                continue;
            }

            $d4Status = $d4sign->inferDocumentStatus($details['data'] ?? []);
            $signed   = $d4sign->looksSignedStatus($d4Status);
            $metadata['details']   = $details['data'] ?? [];
            $metadata['synced_at'] = now();

            if ($signed) {
                $signedPath = trim((string) ($row['file_signed_path'] ?? ''));
                $model->markSigned($id, $signedPath !== '' ? $signedPath : null, $d4Status, $metadata, $companyId);
            } else {
                $localStatus = $this->mapD4SignStatus($d4Status);
                $model->markSync($id, $localStatus, $d4Status, $metadata, $companyId);
            }

            $synced++;
        }

        $total = count($rows);
        $msg   = "Assinaturas verificadas: {$total}. Atualizadas: {$synced}. Erros: {$errors}.";

        return ['ok' => $errors === 0, 'message' => $msg];
    }

    // -----------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------

    private function isEnabled(string $jobKey): bool
    {
        $stmt = $this->db->prepare("SELECT enabled FROM cron_jobs WHERE job_key = :k");
        $stmt->execute([':k' => $jobKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (bool) $row['enabled'] : true;
    }

    private function logStart(string $jobKey): int
    {
        // Marca job como 'running' na tabela principal
        $stmt = $this->db->prepare(
            "UPDATE cron_jobs
             SET last_status = 'running', last_run_at = :now, last_message = NULL
             WHERE job_key = :k"
        );
        $stmt->execute([':now' => now(), ':k' => $jobKey]);

        // Insere linha no log
        $stmt2 = $this->db->prepare(
            "INSERT INTO cron_job_logs (job_key, started_at, status)
             VALUES (:k, :now, 'running')"
        );
        $stmt2->execute([':k' => $jobKey, ':now' => now()]);

        return (int) $this->db->lastInsertId();
    }

    private function logFinish(int $logId, string $jobKey, string $status, string $message, int $durationMs): void
    {
        // Atualiza linha do log
        $stmt = $this->db->prepare(
            "UPDATE cron_job_logs
             SET finished_at = :now, status = :s, message = :msg, duration_ms = :dur
             WHERE id = :id"
        );
        $stmt->execute([
            ':now' => now(),
            ':s'   => $status,
            ':msg' => $message,
            ':dur' => $durationMs,
            ':id'  => $logId,
        ]);

        // Atualiza status na tabela principal
        $stmt2 = $this->db->prepare(
            "UPDATE cron_jobs
             SET last_status = :s, last_message = :msg, last_duration_ms = :dur
             WHERE job_key = :k"
        );
        $stmt2->execute([
            ':s'   => $status,
            ':msg' => $message,
            ':dur' => $durationMs,
            ':k'   => $jobKey,
        ]);
    }

    private function mapD4SignStatus(string $d4Status): string
    {
        $map = [
            'signed'     => 'signed',
            'completed'  => 'signed',
            'concluded'  => 'signed',
            'done'       => 'signed',
            'canceled'   => 'canceled',
            'refused'    => 'refused',
        ];
        return $map[strtolower($d4Status)] ?? 'sent';
    }
}

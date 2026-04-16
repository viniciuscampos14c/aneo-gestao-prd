<?php

class FinanceNotificationModel extends BaseModel
{
    private ?bool $logsTableExists = null;

    public function dispatchDueNotifications(?int $companyId = null, ?string $today = null): array
    {
        if (!$this->hasLogsTable()) {
            return [
                'available' => false,
                'checked' => 0,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
            ];
        }

        $todayDate = $this->normalizeDate($today);
        $reminderDays = max(0, min(30, (int) config('automation.finance_reminder_days_before', 3)));
        $reminderDate = (new DateTime($todayDate))->modify('+' . $reminderDays . ' day')->format('Y-m-d');
        $adminEmail = trim((string) config('automation.finance_admin_email', config('support.notification_email', '')));
        $targetCompanyId = (int) ($companyId ?? 0);

        $invoices = $this->pendingInvoicesForNotification($todayDate, $reminderDate, $targetCompanyId);
        $result = [
            'available' => true,
            'checked' => count($invoices),
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'today' => $todayDate,
            'reminder_date' => $reminderDate,
            'reminder_days_before' => $reminderDays,
        ];

        foreach ($invoices as $invoice) {
            $notificationType = ((string) ($invoice['due_date'] ?? '') === $todayDate) ? 'due_today' : 'reminder';

            $studentEmail = trim((string) ($invoice['student_email'] ?? ''));
            if ($studentEmail !== '' && filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
                $dispatch = $this->dispatchNotification($invoice, $notificationType, 'student', strtolower($studentEmail));
                $result['sent'] += (int) $dispatch['sent'];
                $result['failed'] += (int) $dispatch['failed'];
                $result['skipped'] += (int) $dispatch['skipped'];
            } else {
                $result['skipped']++;
            }

            if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $dispatch = $this->dispatchNotification($invoice, $notificationType, 'admin', strtolower($adminEmail));
                $result['sent'] += (int) $dispatch['sent'];
                $result['failed'] += (int) $dispatch['failed'];
                $result['skipped'] += (int) $dispatch['skipped'];
            } else {
                $result['skipped']++;
            }
        }

        return $result;
    }

    private function pendingInvoicesForNotification(string $todayDate, string $reminderDate, int $companyId): array
    {
        $sql = "SELECT
                i.id,
                i.company_id,
                i.invoice_number,
                i.due_date,
                i.amount,
                i.paid_amount,
                s.id AS student_id,
                s.full_name AS student_name,
                s.email_primary AS student_email,
                c.trade_name AS company_trade_name,
                c.legal_name AS company_legal_name
            FROM invoices i
            INNER JOIN students s ON s.id = i.student_id
            INNER JOIN companies c ON c.id = i.company_id
            WHERE i.status IN ('open', 'partial', 'overdue')
              AND (i.amount - i.paid_amount) > 0
              AND i.due_date IN (:due_today, :due_reminder)";
        $params = [
            ':due_today' => $todayDate,
            ':due_reminder' => $reminderDate,
        ];

        if ($companyId > 0) {
            $sql .= ' AND i.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $sql .= ' ORDER BY i.due_date ASC, i.id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function dispatchNotification(array $invoice, string $notificationType, string $recipientType, string $recipientEmail): array
    {
        $companyId = (int) ($invoice['company_id'] ?? 0);
        $invoiceId = (int) ($invoice['id'] ?? 0);
        if ($companyId <= 0 || $invoiceId <= 0) {
            return ['sent' => 0, 'failed' => 1, 'skipped' => 0];
        }

        $existing = $this->findNotificationLog($companyId, $invoiceId, $notificationType, $recipientType, $recipientEmail);
        if ($existing && (string) ($existing['status'] ?? '') === 'sent') {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 1];
        }

        $mailResult = $this->sendEmail($invoice, $notificationType, $recipientType, $recipientEmail);
        $this->saveNotificationLog(
            $existing ? (int) ($existing['id'] ?? 0) : 0,
            $companyId,
            $invoiceId,
            $notificationType,
            $recipientType,
            $recipientEmail,
            (bool) ($mailResult['ok'] ?? false),
            (string) ($mailResult['message'] ?? '')
        );

        if (!empty($mailResult['ok'])) {
            return ['sent' => 1, 'failed' => 0, 'skipped' => 0];
        }

        return ['sent' => 0, 'failed' => 1, 'skipped' => 0];
    }

    private function findNotificationLog(
        int $companyId,
        int $invoiceId,
        string $notificationType,
        string $recipientType,
        string $recipientEmail
    ): ?array {
        $stmt = $this->db->prepare('SELECT id, status
            FROM finance_notification_logs
            WHERE company_id = :company_id
              AND invoice_id = :invoice_id
              AND notification_type = :notification_type
              AND recipient_type = :recipient_type
              AND recipient_email = :recipient_email
            LIMIT 1');
        $stmt->execute([
            ':company_id' => $companyId,
            ':invoice_id' => $invoiceId,
            ':notification_type' => $notificationType,
            ':recipient_type' => $recipientType,
            ':recipient_email' => $recipientEmail,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function saveNotificationLog(
        int $id,
        int $companyId,
        int $invoiceId,
        string $notificationType,
        string $recipientType,
        string $recipientEmail,
        bool $sent,
        string $message
    ): void {
        $status = $sent ? 'sent' : 'error';
        $errorMessage = $sent ? null : ($message !== '' ? $message : 'Falha no envio.');
        $sentAt = $sent ? now() : null;

        if ($id > 0) {
            $stmt = $this->db->prepare('UPDATE finance_notification_logs
                SET status = :status,
                    error_message = :error_message,
                    sent_at = :sent_at,
                    updated_at = :updated_at
                WHERE id = :id');
            $stmt->execute([
                ':status' => $status,
                ':error_message' => $errorMessage,
                ':sent_at' => $sentAt,
                ':updated_at' => now(),
                ':id' => $id,
            ]);
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO finance_notification_logs (
            company_id, invoice_id, notification_type, recipient_type, recipient_email,
            status, error_message, sent_at, created_at, updated_at
        ) VALUES (
            :company_id, :invoice_id, :notification_type, :recipient_type, :recipient_email,
            :status, :error_message, :sent_at, :created_at, :updated_at
        )');
        $now = now();
        $stmt->execute([
            ':company_id' => $companyId,
            ':invoice_id' => $invoiceId,
            ':notification_type' => $notificationType,
            ':recipient_type' => $recipientType,
            ':recipient_email' => $recipientEmail,
            ':status' => $status,
            ':error_message' => $errorMessage,
            ':sent_at' => $sentAt,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    private function sendEmail(array $invoice, string $notificationType, string $recipientType, string $recipientEmail): array
    {
        $from = trim((string) config('support.from_email', 'nao-responda@aneo.local'));
        $companyId = (int) ($invoice['company_id'] ?? current_company_id() ?? 0);
        $invoiceNumber = (string) ($invoice['invoice_number'] ?? 'Fatura');
        $studentName = (string) ($invoice['student_name'] ?? 'Aluno');
        $companyName = trim((string) ($invoice['company_trade_name'] ?? '')) !== ''
            ? (string) ($invoice['company_trade_name'] ?? '')
            : (string) ($invoice['company_legal_name'] ?? 'ANEO');
        $dueDate = (string) ($invoice['due_date'] ?? '');
        $dueDateLabel = $dueDate !== '' ? date('d/m/Y', strtotime($dueDate)) : '-';
        $outstanding = max(0, (float) ($invoice['amount'] ?? 0) - (float) ($invoice['paid_amount'] ?? 0));

        $subject = $notificationType === 'due_today'
            ? '[ANEO] Alerta de vencimento hoje - ' . $invoiceNumber
            : '[ANEO] Lembrete de vencimento - ' . $invoiceNumber;

        if ($recipientType === 'admin') {
            $subject = $notificationType === 'due_today'
                ? '[ANEO] Alerta admin: vencimento hoje - ' . $invoiceNumber
                : '[ANEO] Lembrete admin: vencimento proximo - ' . $invoiceNumber;
        }

        $body = [
            'Empresa: ' . $companyName,
            'Aluno: ' . $studentName,
            'Fatura: ' . $invoiceNumber,
            'Vencimento: ' . $dueDateLabel,
            'Valor em aberto: ' . format_currency($outstanding),
            '',
        ];

        if ($notificationType === 'due_today') {
            $body[] = 'Este titulo vence hoje.';
        } else {
            $body[] = 'Este e um lembrete de vencimento proximo.';
        }

        if ($recipientType === 'student') {
            $body[] = 'Caso ja tenha efetuado o pagamento, desconsidere esta mensagem.';
        } else {
            $body[] = 'Mensagem enviada automaticamente para acompanhamento financeiro.';
        }

        // Quando o email vai para o aluno, envia BCC para o financeiro
        $bcc = [];
        if ($recipientType === 'student') {
            $financeEmail = strtolower(trim((string) config('automation.finance_bcc_email', '')));
            if ($financeEmail !== '' && filter_var($financeEmail, FILTER_VALIDATE_EMAIL)) {
                $bcc[] = $financeEmail;
            }
        }

        $mailer = new EmailService();
        $result = $mailer->send(
            $recipientEmail,
            $subject,
            implode(PHP_EOL, $body),
            [
                'company_id' => $companyId,
                'from_email' => $from,
                'from_name' => $companyName,
                'reply_to' => $from,
                'bcc' => $bcc,
            ]
        );

        return $result;
    }

    private function hasLogsTable(): bool
    {
        if ($this->logsTableExists !== null) {
            return $this->logsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'finance_notification_logs'");
        $stmt->execute();
        $this->logsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->logsTableExists;
    }

    private function normalizeDate(?string $date): string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return date('Y-m-d');
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        if (!$parsed) {
            return date('Y-m-d');
        }

        return $parsed->format('Y-m-d');
    }
}

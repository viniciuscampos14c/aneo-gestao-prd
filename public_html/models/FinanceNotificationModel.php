<?php

class FinanceNotificationModel extends BaseModel
{
    private ?bool $logsTableExists = null;
    private ?array $supportedNotificationTypes = null;

    public function dispatchInvoiceEvent(int $invoiceId, string $notificationType, ?int $companyId = null): array
    {
        $notificationType = $this->normalizeNotificationType($notificationType);
        if (!in_array($notificationType, ['invoice_issued', 'invoice_paid'], true)) {
            return [
                'available' => true,
                'checked' => 0,
                'sent' => 0,
                'failed' => 1,
                'skipped' => 0,
                'message' => 'Tipo de notificacao financeira invalido.',
            ];
        }

        if ($companyId !== null && $companyId > 0) {
            $this->useCompany($companyId);
        }

        if (!$this->canSafelyDispatchNotificationType($notificationType)) {
            return [
                'available' => false,
                'checked' => 0,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 1,
                'message' => 'Estrutura de log financeiro ainda nao esta pronta para este tipo de notificacao.',
            ];
        }

        $invoice = $this->invoiceForEventNotification($invoiceId);
        if (!$invoice) {
            return [
                'available' => true,
                'checked' => 0,
                'sent' => 0,
                'failed' => 1,
                'skipped' => 0,
                'message' => 'Fatura nao encontrada para notificacao.',
            ];
        }

        $studentEmail = strtolower(trim((string) ($invoice['student_email'] ?? '')));
        $financeEmail = strtolower(trim((string) config('automation.finance_bcc_email', '')));
        $studentEmailValid = $studentEmail !== '' && filter_var($studentEmail, FILTER_VALIDATE_EMAIL);
        $financeEmailValid = $financeEmail !== '' && filter_var($financeEmail, FILTER_VALIDATE_EMAIL);

        $result = [
            'available' => true,
            'checked' => 1,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        if ($studentEmailValid) {
            $dispatch = $this->dispatchNotification($invoice, $notificationType, 'student', $studentEmail);
            $result['sent'] += (int) $dispatch['sent'];
            $result['failed'] += (int) $dispatch['failed'];
            $result['skipped'] += (int) $dispatch['skipped'];
        } elseif ($financeEmailValid) {
            $dispatch = $this->dispatchNotification($invoice, $notificationType, 'admin', $financeEmail);
            $result['sent'] += (int) $dispatch['sent'];
            $result['failed'] += (int) $dispatch['failed'];
            $result['skipped'] += (int) $dispatch['skipped'];
        } else {
            $result['skipped']++;
        }

        return $result;
    }

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

        try {
            $existing = $this->hasLogsTable()
                ? $this->findNotificationLog($companyId, $invoiceId, $notificationType, $recipientType, $recipientEmail)
                : null;
            if ($existing && (string) ($existing['status'] ?? '') === 'sent') {
                return ['sent' => 0, 'failed' => 0, 'skipped' => 1];
            }

            $mailResult = $this->sendEmail($invoice, $notificationType, $recipientType, $recipientEmail);
            if ($this->hasLogsTable()) {
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
            }

            if (!empty($mailResult['ok'])) {
                return ['sent' => 1, 'failed' => 0, 'skipped' => 0];
            }
        } catch (Throwable $e) {
            error_log('[FINANCE_NOTIFICATION_ERROR] ' . $e->getMessage());
            return ['sent' => 0, 'failed' => 1, 'skipped' => 0];
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
        $companyId = (int) ($invoice['company_id'] ?? current_company_id() ?? 0);
        $invoiceNumber = (string) ($invoice['invoice_number'] ?? 'Fatura');
        $studentName = (string) ($invoice['student_name'] ?? 'Aluno');
        $companyName = trim((string) ($invoice['company_trade_name'] ?? '')) !== ''
            ? (string) ($invoice['company_trade_name'] ?? '')
            : (string) ($invoice['company_legal_name'] ?? 'ANEO');
        $dueDate = (string) ($invoice['due_date'] ?? '');
        $dueDateLabel = $dueDate !== '' ? date('d/m/Y', strtotime($dueDate)) : '-';
        $outstanding = max(0, (float) ($invoice['amount'] ?? 0) - (float) ($invoice['paid_amount'] ?? 0));
        $amountLabel = format_currency((float) ($invoice['amount'] ?? 0));
        $paidAmountLabel = format_currency((float) ($invoice['paid_amount'] ?? 0));
        $paidAt = trim((string) ($invoice['paid_at'] ?? ''));
        $paidAtLabel = $paidAt !== '' ? date('d/m/Y', strtotime($paidAt)) : '-';
        $bankSlipUrl = trim((string) ($invoice['boleto_url'] ?? ''));
        $bankSlipDigitableLine = trim((string) ($invoice['boleto_digitable_line'] ?? ''));
        $bankSlipBarcode = trim((string) ($invoice['boleto_barcode'] ?? ''));
        $bankSlipPixCopyPaste = trim((string) ($invoice['boleto_pix_copy_paste'] ?? ''));

        [$subject, $body] = $this->buildMessagePayload(
            $notificationType,
            $recipientType,
            [
                'invoiceNumber'    => $invoiceNumber,
                'studentName'      => $studentName,
                'companyName'      => $companyName,
                'dueDateLabel'     => $dueDateLabel,
                'outstanding'      => format_currency($outstanding),
                'amountLabel'      => $amountLabel,
                'paidAmountLabel'  => $paidAmountLabel,
                'paidAtLabel'      => $paidAtLabel,
                'bankSlipUrl'      => $bankSlipUrl,
                'bankSlipDigitableLine' => $bankSlipDigitableLine,
                'bankSlipBarcode' => $bankSlipBarcode,
                'bankSlipPixCopyPaste' => $bankSlipPixCopyPaste,
                'notificationType' => $notificationType,
                'recipientType'    => $recipientType,
            ]
        );

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
            $body,
            [
                'company_id' => $companyId,
                'from_name'  => $companyName,
                'is_html'    => true,
                'bcc'        => $bcc,
            ]
        );

        return $result;
    }

    private function invoiceForEventNotification(int $invoiceId): ?array
    {
        $stmt = $this->db->prepare('SELECT
                i.id,
                i.company_id,
                i.invoice_number,
                i.due_date,
                i.amount,
                i.paid_amount,
                i.paid_at,
                i.status,
                COALESCE(NULLIF(bs.boleto_url, \'\'), NULLIF(i.boleto_url, \'\')) AS boleto_url,
                bs.digitable_line AS boleto_digitable_line,
                bs.barcode AS boleto_barcode,
                bs.pix_copy_paste AS boleto_pix_copy_paste,
                s.id AS student_id,
                s.full_name AS student_name,
                s.email_primary AS student_email,
                c.trade_name AS company_trade_name,
                c.legal_name AS company_legal_name
            FROM invoices i
            LEFT JOIN bank_slips bs
                ON bs.invoice_id = i.id
               AND bs.status NOT IN (\'cancelled\',\'failed\')
            INNER JOIN students s ON s.id = i.student_id
            INNER JOIN companies c ON c.id = i.company_id
            WHERE i.id = :invoice_id
              AND i.company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':company_id' => $this->companyId(),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
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

    private function canSafelyDispatchNotificationType(string $notificationType): bool
    {
        if (!$this->hasLogsTable()) {
            return false;
        }

        return in_array($notificationType, $this->supportedNotificationTypes(), true);
    }

    private function supportedNotificationTypes(): array
    {
        if ($this->supportedNotificationTypes !== null) {
            return $this->supportedNotificationTypes;
        }

        $default = ['reminder', 'due_today'];
        try {
            $stmt = $this->db->prepare("SELECT COLUMN_TYPE
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'finance_notification_logs'
                  AND column_name = 'notification_type'
                LIMIT 1");
            $stmt->execute();
            $columnType = (string) ($stmt->fetchColumn() ?: '');

            if (preg_match_all("/'([^']+)'/", $columnType, $matches) && !empty($matches[1])) {
                $this->supportedNotificationTypes = array_values(array_unique(array_map('strval', $matches[1])));
                return $this->supportedNotificationTypes;
            }
        } catch (Throwable $e) {
            error_log('[FINANCE_NOTIFICATION_SCHEMA_ERROR] ' . $e->getMessage());
        }

        $this->supportedNotificationTypes = $default;
        return $this->supportedNotificationTypes;
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

    private function normalizeNotificationType(string $notificationType): string
    {
        $notificationType = strtolower(trim($notificationType));
        return match ($notificationType) {
            'issued', 'boleto_issued' => 'invoice_issued',
            'paid', 'payment_paid' => 'invoice_paid',
            default => $notificationType,
        };
    }

    private function buildMessagePayload(string $notificationType, string $recipientType, array $vars): array
    {
        $invoiceNumber = (string) ($vars['invoiceNumber'] ?? 'Fatura');
        $subject = '[ANEO] Atualizacao financeira - ' . $invoiceNumber;

        if ($notificationType === 'due_today') {
            $subject = '[ANEO] Alerta de vencimento hoje - ' . $invoiceNumber;
            if ($recipientType === 'admin') {
                $subject = '[ANEO] Alerta admin: vencimento hoje - ' . $invoiceNumber;
            }
        } elseif ($notificationType === 'reminder') {
            $subject = '[ANEO] Lembrete de vencimento - ' . $invoiceNumber;
            if ($recipientType === 'admin') {
                $subject = '[ANEO] Lembrete admin: vencimento proximo - ' . $invoiceNumber;
            }
        } elseif ($notificationType === 'invoice_issued') {
            $subject = $recipientType === 'admin'
                ? '[ANEO] Copia financeira: boleto emitido - ' . $invoiceNumber
                : '[ANEO] Boleto emitido - ' . $invoiceNumber;
        } elseif ($notificationType === 'invoice_paid') {
            $subject = $recipientType === 'admin'
                ? '[ANEO] Copia financeira: fatura paga - ' . $invoiceNumber
                : '[ANEO] Confirmacao de pagamento - ' . $invoiceNumber;
        }

        return [$subject, $this->renderEmailTemplate($vars)];
    }

    private function renderEmailTemplate(array $vars): string
    {
        // Monta URL absoluta da logo
        $publicUrl = rtrim((string) config('app.public_url', ''), '/');
        if ($publicUrl === '') {
            // Fallback: tenta detectar pelo HTTP_HOST quando disponível (requisição web)
            $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
            if ($host !== '') {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $publicUrl = $scheme . '://' . $host;
            }
        }
        $vars['logoUrl']     = $publicUrl !== '' ? $publicUrl . '/assets/brand/aneo-wordmark-transparente-branco.png?v=20260512-brand-kit-v1' : '';
        $vars['accentColor'] = '#0ea5e9';

        // Renderiza via output buffer
        ob_start();
        extract($vars, EXTR_SKIP);
        $template = in_array((string) ($vars['notificationType'] ?? ''), ['invoice_issued', 'invoice_paid'], true)
            ? '/../views/email/finance_event_notification.php'
            : '/../views/email/billing_notification.php';
        include __DIR__ . $template;
        return (string) ob_get_clean();
    }
}

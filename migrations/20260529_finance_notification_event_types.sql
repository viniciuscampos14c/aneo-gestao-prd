ALTER TABLE finance_notification_logs
    MODIFY COLUMN notification_type ENUM('reminder', 'due_today', 'invoice_issued', 'invoice_paid') NOT NULL;

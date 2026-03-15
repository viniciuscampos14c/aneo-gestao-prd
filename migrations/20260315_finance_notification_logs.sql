CREATE TABLE IF NOT EXISTS finance_notification_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    notification_type ENUM('reminder', 'due_today') NOT NULL,
    recipient_type ENUM('student', 'admin') NOT NULL,
    recipient_email VARCHAR(180) NOT NULL,
    status ENUM('sent', 'error') NOT NULL DEFAULT 'sent',
    error_message VARCHAR(255) NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_finance_notification_unique (company_id, invoice_id, notification_type, recipient_type, recipient_email),
    INDEX idx_finance_notification_company (company_id),
    INDEX idx_finance_notification_invoice (invoice_id),
    INDEX idx_finance_notification_status (status),
    CONSTRAINT fk_finance_notification_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_finance_notification_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

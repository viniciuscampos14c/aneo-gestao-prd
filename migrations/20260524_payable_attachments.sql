-- Aplicar apos migrations/20260523_finance_payables.sql

CREATE TABLE IF NOT EXISTS payable_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    payable_id INT UNSIGNED NOT NULL,
    attachment_type ENUM('boleto', 'nota_fiscal', 'contrato', 'comprovante', 'outro') NOT NULL DEFAULT 'outro',
    original_file_name VARCHAR(255) NOT NULL,
    stored_file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(80) NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_payable_attachments_company (company_id),
    INDEX idx_payable_attachments_payable (payable_id),
    INDEX idx_payable_attachments_type (attachment_type),
    CONSTRAINT fk_payable_attachments_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_payable_attachments_payable FOREIGN KEY (payable_id) REFERENCES payables(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_payable_attachments_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_name VARCHAR(180) NOT NULL,
    trade_name VARCHAR(180) NULL,
    cnpj VARCHAR(20) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_companies_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_user_company (user_id, company_id),
    INDEX idx_user_companies_company (company_id),
    CONSTRAINT fk_user_companies_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_user_companies_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO companies (legal_name, trade_name, cnpj, is_active, created_at, updated_at)
SELECT 'ANEO Brasil', 'ANEO Brasil', '00.000.000/0001-00', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM companies LIMIT 1);

SET @default_company_id = (SELECT id FROM companies ORDER BY id ASC LIMIT 1);

ALTER TABLE students ADD COLUMN company_id INT UNSIGNED NULL AFTER id;
UPDATE students SET company_id = @default_company_id WHERE company_id IS NULL;
ALTER TABLE students MODIFY company_id INT UNSIGNED NOT NULL;
ALTER TABLE students ADD INDEX idx_students_company (company_id);
ALTER TABLE students ADD CONSTRAINT fk_students_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE leads ADD COLUMN company_id INT UNSIGNED NULL AFTER id;
UPDATE leads SET company_id = @default_company_id WHERE company_id IS NULL;
ALTER TABLE leads MODIFY company_id INT UNSIGNED NOT NULL;
ALTER TABLE leads ADD INDEX idx_leads_company (company_id);
ALTER TABLE leads ADD CONSTRAINT fk_leads_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE invoices ADD COLUMN company_id INT UNSIGNED NULL AFTER invoice_number;
UPDATE invoices i
INNER JOIN students s ON s.id = i.student_id
SET i.company_id = s.company_id
WHERE i.company_id IS NULL;
UPDATE invoices SET company_id = @default_company_id WHERE company_id IS NULL;
ALTER TABLE invoices MODIFY company_id INT UNSIGNED NOT NULL;
ALTER TABLE invoices ADD INDEX idx_invoices_company (company_id);
ALTER TABLE invoices ADD CONSTRAINT fk_invoices_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE payments ADD COLUMN company_id INT UNSIGNED NULL AFTER payment_ref;
UPDATE payments p
LEFT JOIN (
    SELECT pi.payment_id, MIN(i.company_id) AS company_id
    FROM payment_items pi
    INNER JOIN invoices i ON i.id = pi.invoice_id
    GROUP BY pi.payment_id
) src ON src.payment_id = p.id
SET p.company_id = src.company_id
WHERE p.company_id IS NULL;
UPDATE payments SET company_id = @default_company_id WHERE company_id IS NULL;
ALTER TABLE payments MODIFY company_id INT UNSIGNED NOT NULL;
ALTER TABLE payments ADD INDEX idx_payments_company (company_id);
ALTER TABLE payments ADD CONSTRAINT fk_payments_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE;

INSERT INTO user_companies (user_id, company_id, is_default, created_at, updated_at)
SELECT u.id, @default_company_id, 1, NOW(), NOW()
FROM users u
LEFT JOIN user_companies uc ON uc.user_id = u.id AND uc.company_id = @default_company_id
WHERE uc.id IS NULL;

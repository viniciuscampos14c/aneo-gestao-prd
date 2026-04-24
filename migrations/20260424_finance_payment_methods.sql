-- Formas de pagamento (manual x integrada por contrato)
-- Aplicar: mysql -u <user> -p<pass> <db> < migrations/20260424_finance_payment_methods.sql

CREATE TABLE IF NOT EXISTS payment_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    mode ENUM('manual','integrated') NOT NULL DEFAULT 'manual',
    provider_key VARCHAR(80) NULL,
    channel VARCHAR(40) NULL,
    auto_created TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    settings_json LONGTEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_payment_methods_company_slug (company_id, slug),
    INDEX idx_payment_methods_company_active (company_id, is_active),
    INDEX idx_payment_methods_provider (provider_key),
    CONSTRAINT fk_payment_methods_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_payment_methods_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_payment_methods_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- invoices.payment_method_id
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'invoices' AND column_name = 'payment_method_id') = 0,
    'ALTER TABLE invoices ADD COLUMN payment_method_id INT UNSIGNED NULL AFTER student_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'invoices' AND index_name = 'idx_invoices_payment_method') = 0,
    'ALTER TABLE invoices ADD INDEX idx_invoices_payment_method (payment_method_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.table_constraints
      WHERE table_schema = DATABASE() AND table_name = 'invoices' AND constraint_name = 'fk_invoices_payment_method') = 0,
    'ALTER TABLE invoices ADD CONSTRAINT fk_invoices_payment_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payments.payment_method_id
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'payments' AND column_name = 'payment_method_id') = 0,
    'ALTER TABLE payments ADD COLUMN payment_method_id INT UNSIGNED NULL AFTER company_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'payments' AND index_name = 'idx_payments_payment_method') = 0,
    'ALTER TABLE payments ADD INDEX idx_payments_payment_method (payment_method_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.table_constraints
      WHERE table_schema = DATABASE() AND table_name = 'payments' AND constraint_name = 'fk_payments_payment_method') = 0,
    'ALTER TABLE payments ADD CONSTRAINT fk_payments_payment_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Seed de metodos manuais basicos
INSERT INTO payment_methods (
    company_id, name, slug, mode, provider_key, channel, auto_created,
    is_active, sort_order, settings_json, created_by, updated_by, created_at, updated_at
)
SELECT c.id, 'PIX', 'manual-pix', 'manual', NULL, 'pix', 0,
       1, 10, NULL, NULL, NULL, NOW(), NOW()
FROM companies c
LEFT JOIN payment_methods pm
       ON pm.company_id = c.id
      AND LOWER(pm.name) = 'pix'
WHERE pm.id IS NULL;

INSERT INTO payment_methods (
    company_id, name, slug, mode, provider_key, channel, auto_created,
    is_active, sort_order, settings_json, created_by, updated_by, created_at, updated_at
)
SELECT c.id, 'Cartao de credito', 'manual-card', 'manual', NULL, 'card', 0,
       1, 20, NULL, NULL, NULL, NOW(), NOW()
FROM companies c
LEFT JOIN payment_methods pm
       ON pm.company_id = c.id
      AND LOWER(pm.name) = 'cartao de credito'
WHERE pm.id IS NULL;

INSERT INTO payment_methods (
    company_id, name, slug, mode, provider_key, channel, auto_created,
    is_active, sort_order, settings_json, created_by, updated_by, created_at, updated_at
)
SELECT c.id, 'Transferencia', 'manual-transfer', 'manual', NULL, 'transfer', 0,
       1, 30, NULL, NULL, NULL, NOW(), NOW()
FROM companies c
LEFT JOIN payment_methods pm
       ON pm.company_id = c.id
      AND LOWER(pm.name) = 'transferencia'
WHERE pm.id IS NULL;

INSERT INTO payment_methods (
    company_id, name, slug, mode, provider_key, channel, auto_created,
    is_active, sort_order, settings_json, created_by, updated_by, created_at, updated_at
)
SELECT c.id, 'Dinheiro', 'manual-cash', 'manual', NULL, 'cash', 0,
       1, 40, NULL, NULL, NULL, NOW(), NOW()
FROM companies c
LEFT JOIN payment_methods pm
       ON pm.company_id = c.id
      AND LOWER(pm.name) = 'dinheiro'
WHERE pm.id IS NULL;

-- Seed integrado automatico para contratos ITAU ja ativos
INSERT INTO payment_methods (
    company_id, name, slug, mode, provider_key, channel, auto_created,
    is_active, sort_order, settings_json, created_by, updated_by, created_at, updated_at
)
SELECT ci.company_id,
       'ITAU - Boleto API',
       'integrated-itau-boleto',
       'integrated',
       'itau',
       'boleto',
       1,
       IF(ci.is_enabled = 1, 1, 0),
       100,
       JSON_OBJECT('integration_key', 'itau'),
       NULL, NULL,
       NOW(), NOW()
FROM company_integrations ci
LEFT JOIN payment_methods pm
       ON pm.company_id = ci.company_id
      AND pm.slug = 'integrated-itau-boleto'
WHERE ci.integration_key = 'itau'
  AND pm.id IS NULL;

-- Ajusta status ativo/inativo da forma integrada ITAU conforme contrato
UPDATE payment_methods pm
INNER JOIN company_integrations ci
        ON ci.company_id = pm.company_id
       AND ci.integration_key = 'itau'
SET pm.is_active = IF(ci.is_enabled = 1, 1, 0),
    pm.updated_at = NOW()
WHERE pm.slug = 'integrated-itau-boleto';

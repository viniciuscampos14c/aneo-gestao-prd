-- Aplicar apos migrations/20260523_finance_payables.sql

ALTER TABLE payables
    ADD COLUMN is_recurring TINYINT(1) NOT NULL DEFAULT 0 AFTER notes,
    ADD COLUMN recurrence_interval VARCHAR(20) NULL AFTER is_recurring,
    ADD COLUMN recurrence_until DATE NULL AFTER recurrence_interval,
    ADD COLUMN recurrence_parent_id INT UNSIGNED NULL AFTER recurrence_until,
    ADD INDEX idx_payables_recurrence (company_id, is_recurring, recurrence_parent_id),
    ADD INDEX idx_payables_recurrence_parent (recurrence_parent_id),
    ADD CONSTRAINT fk_payables_recurrence_parent FOREIGN KEY (recurrence_parent_id) REFERENCES payables(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Controle administrativo de rematriculas confirmadas
-- Data: 2026-05-25

ALTER TABLE reenrollments
    ADD COLUMN IF NOT EXISTS admin_viewed_at DATETIME NULL AFTER confirmed_ip,
    ADD COLUMN IF NOT EXISTS confirmation_email_sent_at DATETIME NULL AFTER admin_viewed_at,
    ADD COLUMN IF NOT EXISTS confirmation_email_error TEXT NULL AFTER confirmation_email_sent_at;

CREATE INDEX IF NOT EXISTS idx_reenrollments_admin_alert
    ON reenrollments (company_id, confirmed_at, admin_viewed_at);

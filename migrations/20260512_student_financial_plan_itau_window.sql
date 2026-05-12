-- Plano financeiro do aluno + janela de emissao de boletos Itau
-- Aplicar: mysql -u ... -p... database < migrations/20260512_student_financial_plan_itau_window.sql

ALTER TABLE students
    ADD COLUMN financial_plan_profile VARCHAR(40) NULL AFTER billing_day,
    ADD COLUMN financial_plan_installments SMALLINT UNSIGNED NULL AFTER financial_plan_profile,
    ADD COLUMN financial_plan_first_due_date DATE NULL AFTER financial_plan_installments,
    ADD COLUMN financial_plan_payment_method_id INT UNSIGNED NULL AFTER financial_plan_first_due_date,
    ADD COLUMN financial_plan_auto_generate TINYINT(1) NOT NULL DEFAULT 0 AFTER financial_plan_payment_method_id,
    ADD COLUMN financial_plan_boleto_days_before TINYINT UNSIGNED NOT NULL DEFAULT 10 AFTER financial_plan_auto_generate,
    ADD COLUMN financial_plan_generated_at DATETIME NULL AFTER financial_plan_boleto_days_before,
    ADD INDEX idx_students_financial_plan_payment_method (financial_plan_payment_method_id),
    ADD INDEX idx_students_financial_plan_generated_at (financial_plan_generated_at);

INSERT IGNORE INTO cron_jobs (job_key, label, description, enabled) VALUES
  ('boleto_issue_due',
   'Emissao Programada de Boletos Itau',
   'Transmite ao Itau apenas as faturas que entraram na janela configurada para emissao.',
   1);

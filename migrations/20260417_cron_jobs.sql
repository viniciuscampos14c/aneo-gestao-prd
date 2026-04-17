-- Cron Jobs: registro de jobs e histórico de execuções
-- Aplicar: mysql -u u674156040_erpaneo -p'1nsertTecn0@2026' u674156040_erpaneo < migrations/20260417_cron_jobs.sql

CREATE TABLE IF NOT EXISTS cron_jobs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_key       VARCHAR(80)  NOT NULL UNIQUE,
    label         VARCHAR(120) NOT NULL,
    description   VARCHAR(255) NOT NULL DEFAULT '',
    enabled       TINYINT(1)   NOT NULL DEFAULT 1,
    last_run_at   DATETIME     NULL,
    last_status   ENUM('ok','error','running') NULL,
    last_message  TEXT         NULL,
    last_duration_ms INT UNSIGNED NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cron_job_logs (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_key      VARCHAR(80)  NOT NULL,
    started_at   DATETIME     NOT NULL,
    finished_at  DATETIME     NULL,
    status       ENUM('ok','error','running') NOT NULL DEFAULT 'running',
    message      TEXT         NULL,
    duration_ms  INT UNSIGNED NULL,
    INDEX idx_job_key (job_key),
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jobs padrão do sistema
INSERT IGNORE INTO cron_jobs (job_key, label, description, enabled) VALUES
  ('finance_billing_notifications',
   'Notificações de Cobrança',
   'Envia e-mails de aviso para faturas próximas do vencimento e vencidas.',
   1),
  ('boleto_sync',
   'Sincronização de Boletos',
   'Consulta status de boletos pendentes junto ao provedor de pagamento.',
   1),
  ('signatures_sync',
   'Sincronização de Assinaturas',
   'Atualiza status de contratos pendentes no D4Sign.',
   1);

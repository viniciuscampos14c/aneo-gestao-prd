-- Rematrícula automática a cada 6 meses
-- Data: 2026-04-20

CREATE TABLE IF NOT EXISTS reenrollments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      INT UNSIGNED NOT NULL,
    company_id      INT UNSIGNED NOT NULL,
    period_start    DATE         NOT NULL,
    period_end      DATE         NOT NULL,
    confirmed_at    DATETIME     NULL,
    confirmed_ip    VARCHAR(45)  NULL,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    INDEX idx_student   (student_id),
    INDEX idx_company   (company_id),
    INDEX idx_period    (student_id, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

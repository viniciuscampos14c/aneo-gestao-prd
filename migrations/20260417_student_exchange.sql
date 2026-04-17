-- Tabela de solicitações de intercâmbio dos alunos
CREATE TABLE IF NOT EXISTS student_exchange_requests (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id    INT UNSIGNED NOT NULL,
    student_id    INT UNSIGNED NOT NULL,
    student_name  VARCHAR(255) NOT NULL,
    current_unit  VARCHAR(255) NOT NULL,
    target_unit   VARCHAR(255) NOT NULL,
    desired_month VARCHAR(20)  NOT NULL,   -- formato YYYY-MM
    months_enrolled SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM('pending','viewed','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_notes   TEXT NULL,
    created_at    DATETIME NOT NULL,
    updated_at    DATETIME NOT NULL,
    INDEX idx_company  (company_id),
    INDEX idx_student  (student_id),
    INDEX idx_status   (status),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

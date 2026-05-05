CREATE TABLE IF NOT EXISTS student_portal_notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    notification_type VARCHAR(80) NOT NULL DEFAULT 'general',
    title VARCHAR(180) NOT NULL,
    message TEXT NULL,
    link_url VARCHAR(255) NULL,
    meta_json JSON NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    read_at DATETIME NULL,
    KEY idx_student_portal_notifications_student (student_id, is_read, created_at),
    KEY idx_student_portal_notifications_company (company_id, notification_type),
    CONSTRAINT fk_student_portal_notifications_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

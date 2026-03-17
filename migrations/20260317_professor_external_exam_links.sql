-- Perfil Professor + vinculo de prova externa por aluno.

ALTER TABLE users
    MODIFY COLUMN role ENUM('admin', 'suporte', 'professor') NOT NULL DEFAULT 'suporte';

CREATE TABLE IF NOT EXISTS exam_external_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    external_url VARCHAR(500) NOT NULL,
    instructions TEXT NULL,
    due_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    first_opened_at DATETIME NULL,
    last_opened_at DATETIME NULL,
    open_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_exam_external_link_exam_student (exam_id, student_id),
    INDEX idx_exam_external_link_student (student_id, is_active),
    INDEX idx_exam_external_link_exam (exam_id, is_active),
    CONSTRAINT fk_exam_external_links_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_exam_external_links_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_exam_external_links_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

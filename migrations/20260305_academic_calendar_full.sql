-- Migracao incremental: Agenda Academica completa
-- Inclui prazos de atividades e fila de lembretes automaticos para aluno/professor

CREATE TABLE IF NOT EXISTS course_activities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    due_datetime DATETIME NOT NULL,
    reminder_hours_before SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_course_activities_due (due_datetime),
    INDEX idx_course_activities_course (course_id),
    CONSTRAINT fk_course_activities_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_course_activities_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS academic_reminders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type ENUM('exam', 'live_class', 'activity') NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    recipient_type ENUM('student', 'teacher') NOT NULL,
    recipient_id INT UNSIGNED NOT NULL,
    scheduled_for DATETIME NOT NULL,
    message VARCHAR(255) NOT NULL,
    status ENUM('pending', 'sent') NOT NULL DEFAULT 'pending',
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_academic_reminder_unique (event_type, event_id, recipient_type, recipient_id, scheduled_for),
    INDEX idx_academic_reminders_schedule (status, scheduled_for),
    INDEX idx_academic_reminders_recipient (recipient_type, recipient_id),
    INDEX idx_academic_reminders_course (course_id),
    CONSTRAINT fk_academic_reminders_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

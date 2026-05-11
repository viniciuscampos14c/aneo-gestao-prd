ALTER TABLE students
    ADD COLUMN IF NOT EXISTS gda_priority ENUM('none','low','medium','high','critical') NOT NULL DEFAULT 'none' AFTER kanban_status_id,
    ADD COLUMN IF NOT EXISTS gda_due_date DATE NULL AFTER gda_priority,
    ADD COLUMN IF NOT EXISTS gda_cover_color VARCHAR(20) NULL AFTER gda_due_date,
    ADD COLUMN IF NOT EXISTS gda_description TEXT NULL AFTER gda_cover_color,
    ADD COLUMN IF NOT EXISTS gda_assigned_to INT UNSIGNED NULL AFTER gda_description,
    ADD COLUMN IF NOT EXISTS gda_is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER gda_assigned_to,
    ADD COLUMN IF NOT EXISTS gda_due_notified TINYINT(1) NOT NULL DEFAULT 0 AFTER gda_is_archived,
    ADD COLUMN IF NOT EXISTS gda_display_order SMALLINT UNSIGNED NOT NULL DEFAULT 999 AFTER gda_due_notified;

CREATE TABLE IF NOT EXISTS gda_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL DEFAULT 0,
    note TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gda_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL DEFAULT '',
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    KEY idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gda_labels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL DEFAULT 0,
    name VARCHAR(60) NOT NULL,
    color VARCHAR(20) NOT NULL DEFAULT '#3b82f6',
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 99
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gda_card_labels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    label_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uidx (student_id, label_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gda_card_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uidx (student_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gda_checklists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 99,
    created_at DATETIME NOT NULL,
    KEY idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gda_checklist_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT UNSIGNED NOT NULL,
    text VARCHAR(500) NOT NULL,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 99,
    created_at DATETIME NOT NULL,
    KEY idx_checklist (checklist_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gda_custom_fields (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL DEFAULT 0,
    name VARCHAR(80) NOT NULL,
    field_type ENUM('text','number','date','select','checkbox') NOT NULL DEFAULT 'text',
    options_json TEXT NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 99
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gda_custom_field_values (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    field_id INT UNSIGNED NOT NULL,
    value TEXT NULL,
    UNIQUE KEY uidx (student_id, field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gda_automations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL DEFAULT 0,
    name VARCHAR(120) NOT NULL,
    trigger_type VARCHAR(40) NOT NULL,
    trigger_value VARCHAR(120) NULL,
    action_type VARCHAR(40) NOT NULL,
    action_value VARCHAR(120) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gda_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL DEFAULT 0,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    priority ENUM('none','low','medium','high','critical') NOT NULL DEFAULT 'none',
    label_ids TEXT NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 99
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gda_template_checklists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id INT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 99
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gda_template_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT UNSIGNED NOT NULL,
    text VARCHAR(500) NOT NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 99
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

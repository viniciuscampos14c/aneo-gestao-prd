CREATE TABLE IF NOT EXISTS arsenal_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_arsenal_category_company_name (company_id, name),
    INDEX idx_arsenal_categories_company (company_id),
    INDEX idx_arsenal_categories_active (is_active),
    CONSTRAINT fk_arsenal_categories_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_arsenal_categories_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS arsenal_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    material_type ENUM('file', 'link') NOT NULL DEFAULT 'file',
    file_name VARCHAR(255) NULL,
    file_path VARCHAR(255) NULL,
    file_type VARCHAR(40) NULL,
    file_size BIGINT NULL,
    external_url VARCHAR(500) NULL,
    visibility_scope ENUM('global', 'course', 'student') NOT NULL DEFAULT 'global',
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    publish_start_at DATETIME NULL,
    publish_end_at DATETIME NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_arsenal_items_company (company_id),
    INDEX idx_arsenal_items_category (category_id),
    INDEX idx_arsenal_items_scope_status (visibility_scope, status),
    INDEX idx_arsenal_items_publish_window (publish_start_at, publish_end_at),
    CONSTRAINT fk_arsenal_items_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_arsenal_items_category FOREIGN KEY (category_id) REFERENCES arsenal_categories(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_arsenal_items_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS arsenal_item_courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    arsenal_item_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uk_arsenal_item_course (company_id, arsenal_item_id, course_id),
    INDEX idx_arsenal_item_courses_item (arsenal_item_id),
    INDEX idx_arsenal_item_courses_course (course_id),
    CONSTRAINT fk_arsenal_item_courses_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_arsenal_item_courses_item FOREIGN KEY (arsenal_item_id) REFERENCES arsenal_items(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_arsenal_item_courses_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS arsenal_item_students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    arsenal_item_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uk_arsenal_item_student (company_id, arsenal_item_id, student_id),
    INDEX idx_arsenal_item_students_item (arsenal_item_id),
    INDEX idx_arsenal_item_students_student (student_id),
    CONSTRAINT fk_arsenal_item_students_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_arsenal_item_students_item FOREIGN KEY (arsenal_item_id) REFERENCES arsenal_items(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_arsenal_item_students_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS arsenal_access_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    arsenal_item_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_arsenal_access_company (company_id),
    INDEX idx_arsenal_access_item (arsenal_item_id),
    INDEX idx_arsenal_access_student (student_id),
    INDEX idx_arsenal_access_action_date (action, created_at),
    CONSTRAINT fk_arsenal_access_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_arsenal_access_item FOREIGN KEY (arsenal_item_id) REFERENCES arsenal_items(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_arsenal_access_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


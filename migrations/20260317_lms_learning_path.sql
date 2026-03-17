CREATE TABLE IF NOT EXISTS course_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    display_order INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_course_modules_course (course_id),
    INDEX idx_course_modules_order (course_id, display_order, id),
    CONSTRAINT fk_course_modules_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_course_modules_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS course_lessons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    lesson_type ENUM('video') NOT NULL DEFAULT 'video',
    video_url VARCHAR(500) NULL,
    duration_seconds INT UNSIGNED NULL,
    min_progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 70,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_course_lessons_course (course_id),
    INDEX idx_course_lessons_module (module_id),
    INDEX idx_course_lessons_order (module_id, display_order, id),
    CONSTRAINT fk_course_lessons_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_course_lessons_module FOREIGN KEY (module_id) REFERENCES course_modules(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_course_lessons_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_lesson_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    lesson_id INT UNSIGNED NOT NULL,
    watched_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    last_position_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,
    last_event_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_student_lesson_progress (student_id, lesson_id),
    INDEX idx_student_lesson_progress_course (course_id),
    INDEX idx_student_lesson_progress_module (module_id),
    INDEX idx_student_lesson_progress_student_course (student_id, course_id),
    CONSTRAINT fk_student_lesson_progress_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_student_lesson_progress_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_student_lesson_progress_module FOREIGN KEY (module_id) REFERENCES course_modules(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_student_lesson_progress_lesson FOREIGN KEY (lesson_id) REFERENCES course_lessons(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


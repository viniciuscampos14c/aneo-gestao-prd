CREATE TABLE IF NOT EXISTS course_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    lesson_id INT UNSIGNED NULL,
    student_id INT UNSIGNED NOT NULL,
    subject VARCHAR(180) NOT NULL,
    status ENUM('open', 'answered', 'resolved') NOT NULL DEFAULT 'open',
    last_message_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_course_questions_company_status (company_id, status, last_message_at),
    INDEX idx_course_questions_student (student_id, last_message_at),
    INDEX idx_course_questions_course (course_id, lesson_id),
    CONSTRAINT fk_course_questions_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_course_questions_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_course_questions_lesson FOREIGN KEY (lesson_id) REFERENCES course_lessons(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_course_questions_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_question_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    sender_type ENUM('student', 'professor') NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_course_question_messages_question (question_id, created_at),
    CONSTRAINT fk_course_question_messages_question FOREIGN KEY (question_id) REFERENCES course_questions(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

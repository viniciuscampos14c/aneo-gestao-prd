-- Migracao incremental para provas disponiveis no Portal do Aluno
-- Guarda respostas e status da submissao para correcoes manuais/auto-correcao

CREATE TABLE IF NOT EXISTS exam_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    status ENUM('submitted', 'auto_graded', 'pending_review') NOT NULL DEFAULT 'submitted',
    score DECIMAL(4,2) NULL,
    graded_questions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    correct_answers SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    submitted_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_exam_submissions_exam_student (exam_id, student_id),
    INDEX idx_exam_submissions_status (status),
    CONSTRAINT fk_exam_submissions_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_exam_submissions_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_exam_submissions_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exam_submission_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    answer_text TEXT NULL,
    is_correct TINYINT(1) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_exam_submission_answers_submission (submission_id),
    CONSTRAINT fk_exam_submission_answers_submission FOREIGN KEY (submission_id) REFERENCES exam_submissions(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_exam_submission_answers_question FOREIGN KEY (question_id) REFERENCES exam_questions(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

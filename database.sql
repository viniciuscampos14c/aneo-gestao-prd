-- ANEO Gestão Integrada
-- MySQL 8+ / MariaDB 10.5+

SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE DATABASE IF NOT EXISTS `aneo_gestao` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `aneo_gestao`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS payment_items;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS bank_slips;
DROP TABLE IF EXISTS fiscal_invoices;
DROP TABLE IF EXISTS finance_notification_logs;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS exam_submission_answers;
DROP TABLE IF EXISTS exam_submissions;
DROP TABLE IF EXISTS exam_results;
DROP TABLE IF EXISTS exam_questions;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS academic_reminders;
DROP TABLE IF EXISTS course_activities;
DROP TABLE IF EXISTS course_comments;
DROP TABLE IF EXISTS student_lesson_progress;
DROP TABLE IF EXISTS course_lessons;
DROP TABLE IF EXISTS course_modules;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS course_categories;
DROP TABLE IF EXISTS chatwoot_flow_sessions;
DROP TABLE IF EXISTS chatwoot_links;
DROP TABLE IF EXISTS signature_events;
DROP TABLE IF EXISTS signature_requests;
DROP TABLE IF EXISTS arsenal_access_logs;
DROP TABLE IF EXISTS arsenal_item_students;
DROP TABLE IF EXISTS arsenal_item_courses;
DROP TABLE IF EXISTS arsenal_items;
DROP TABLE IF EXISTS arsenal_categories;
DROP TABLE IF EXISTS lead_history;
DROP TABLE IF EXISTS leads;
DROP TABLE IF EXISTS lead_status;
DROP TABLE IF EXISTS student_kanban_history;
DROP TABLE IF EXISTS student_contacts;
DROP TABLE IF EXISTS student_portal_accounts;
DROP TABLE IF EXISTS uploads;
DROP TABLE IF EXISTS taggables;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS module_items;
DROP TABLE IF EXISTS user_permissions;
DROP TABLE IF EXISTS company_integrations;
DROP TABLE IF EXISTS user_companies;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS kanban_status;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS companies;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_name VARCHAR(180) NOT NULL,
    trade_name VARCHAR(180) NULL,
    cnpj VARCHAR(20) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_companies_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'suporte', 'professor') NOT NULL DEFAULT 'suporte',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    permission_key VARCHAR(120) NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_user_permission (user_id, permission_key),
    INDEX idx_user_permissions_key (permission_key),
    CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_user_company (user_id, company_id),
    INDEX idx_user_companies_company (company_id),
    CONSTRAINT fk_user_companies_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_user_companies_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE company_integrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    integration_key VARCHAR(60) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    settings_json LONGTEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_company_integrations_company_key (company_id, integration_key),
    INDEX idx_company_integrations_key (integration_key),
    CONSTRAINT fk_company_integrations_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_company_integrations_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_company_integrations_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE kanban_status (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    color VARCHAR(20) NOT NULL DEFAULT '#0ea5e9',
    display_order INT NOT NULL DEFAULT 99,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(180) NOT NULL,
    primary_contact VARCHAR(150) NULL,
    email_primary VARCHAR(180) NULL,
    phone VARCHAR(40) NULL,
    profile_photo VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    admin_info VARCHAR(255) NULL,
    ra VARCHAR(80) NULL,
    birth_date DATE NULL,
    rg VARCHAR(80) NULL,
    cro VARCHAR(80) NULL,
    notes TEXT NULL,
    monthly_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    billing_day TINYINT UNSIGNED NULL,
    kanban_status_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_students_name (full_name),
    INDEX idx_students_email (email_primary),
    INDEX idx_students_phone (phone),
    INDEX idx_students_company (company_id),
    INDEX idx_students_status (kanban_status_id),
    CONSTRAINT fk_students_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_students_kanban_status FOREIGN KEY (kanban_status_id) REFERENCES kanban_status(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_students_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE student_portal_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL UNIQUE,
    login VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_student_portal_login (login),
    CONSTRAINT fk_student_portal_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE student_contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    contact_name VARCHAR(150) NOT NULL,
    email VARCHAR(180) NULL,
    phone VARCHAR(40) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_student_contacts_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE student_kanban_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    from_status_id INT UNSIGNED NULL,
    to_status_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NULL,
    changed_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_student_kanban_history_student (student_id),
    CONSTRAINT fk_student_kanban_history_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_student_kanban_history_from FOREIGN KEY (from_status_id) REFERENCES kanban_status(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_student_kanban_history_to FOREIGN KEY (to_status_id) REFERENCES kanban_status(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_student_kanban_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lead_status (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    color VARCHAR(20) NOT NULL DEFAULT '#6366f1',
    display_order INT NOT NULL DEFAULT 99,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE leads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(180) NOT NULL,
    email VARCHAR(180) NULL,
    phone VARCHAR(40) NULL,
    lead_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    assigned_to INT UNSIGNED NULL,
    source VARCHAR(120) NULL,
    lead_status_id INT UNSIGNED NULL,
    unit_name VARCHAR(120) NULL,
    tags VARCHAR(255) NULL,
    last_contact_at DATETIME NULL,
    converted_student_id INT UNSIGNED NULL,
    converted_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_leads_name (full_name),
    INDEX idx_leads_company (company_id),
    INDEX idx_leads_status (lead_status_id),
    CONSTRAINT fk_leads_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_leads_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leads_status FOREIGN KEY (lead_status_id) REFERENCES lead_status(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leads_converted_student FOREIGN KEY (converted_student_id) REFERENCES students(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leads_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE chatwoot_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    entity_type ENUM('student', 'lead', 'other') NOT NULL DEFAULT 'other',
    entity_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    contact_source_id VARCHAR(120) NULL,
    conversation_id INT UNSIGNED NULL,
    conversation_url VARCHAR(255) NULL,
    status VARCHAR(60) NOT NULL DEFAULT 'open',
    contact_name VARCHAR(180) NULL,
    contact_phone VARCHAR(40) NULL,
    contact_email VARCHAR(180) NULL,
    last_message TEXT NULL,
    last_synced_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_chatwoot_entity_company (company_id, entity_type, entity_id),
    INDEX idx_chatwoot_links_company (company_id),
    INDEX idx_chatwoot_conversation (conversation_id),
    INDEX idx_chatwoot_contact (contact_id),
    INDEX idx_chatwoot_status (status),
    CONSTRAINT fk_chatwoot_links_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_chatwoot_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE chatwoot_flow_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    conversation_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    contact_name VARCHAR(180) NULL,
    phone VARCHAR(40) NULL,
    current_step VARCHAR(80) NOT NULL DEFAULT 'menu_choice',
    menu_choice VARCHAR(20) NULL,
    city VARCHAR(120) NULL,
    last_user_message TEXT NULL,
    handoff_team_id INT UNSIGNED NULL,
    handoff_sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_chatwoot_flow_company_conversation (company_id, conversation_id),
    INDEX idx_chatwoot_flow_company (company_id),
    INDEX idx_chatwoot_flow_step (current_step),
    INDEX idx_chatwoot_flow_city (city)
    ,
    CONSTRAINT fk_chatwoot_flow_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lead_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id INT UNSIGNED NOT NULL,
    interaction TEXT NOT NULL,
    status_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_lead_history_lead (lead_id),
    CONSTRAINT fk_lead_history_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_lead_history_status FOREIGN KEY (status_id) REFERENCES lead_status(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_lead_history_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE course_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(140) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_course_categories_company_name (company_id, name),
    INDEX idx_course_categories_company (company_id),
    CONSTRAINT fk_course_categories_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_course_categories_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    category_id INT UNSIGNED NULL,
    cover_image VARCHAR(255) NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    workload_hours INT NULL,
    curriculum TEXT NULL,
    materials TEXT NULL,
    live_link VARCHAR(255) NULL,
    live_password VARCHAR(80) NULL,
    live_meeting_id VARCHAR(120) NULL,
    live_datetime DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_courses_company (company_id),
    CONSTRAINT fk_courses_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_courses_category FOREIGN KEY (category_id) REFERENCES course_categories(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_courses_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE course_modules (
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

CREATE TABLE course_lessons (
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

CREATE TABLE student_lesson_progress (
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

CREATE TABLE enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    status ENUM('active', 'cancelled', 'completed') NOT NULL DEFAULT 'active',
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    started_at DATE NULL,
    completed_at DATE NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_enrollment_student_course (student_id, course_id),
    CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_enrollments_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_enrollments_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE course_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_course_comments_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_course_comments_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE exams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    passing_score DECIMAL(4,2) NOT NULL DEFAULT 7.00,
    scheduled_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_exams_scheduled_at (scheduled_at),
    CONSTRAINT fk_exams_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_exams_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE course_activities (
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

CREATE TABLE academic_reminders (
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

CREATE TABLE exam_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id INT UNSIGNED NOT NULL,
    question_type ENUM('objective', 'essay') NOT NULL DEFAULT 'objective',
    question_text TEXT NOT NULL,
    options_json TEXT NULL,
    correct_answer TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_exam_questions_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_exam_questions_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE exam_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    score DECIMAL(4,2) NOT NULL,
    status ENUM('approved', 'failed') NOT NULL,
    submitted_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_exam_results_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_exam_results_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_exam_results_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE exam_submissions (
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

CREATE TABLE exam_external_links (
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

CREATE TABLE exam_submission_answers (
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

CREATE TABLE payment_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    mode ENUM('manual', 'integrated') NOT NULL DEFAULT 'manual',
    provider_key VARCHAR(80) NULL,
    channel VARCHAR(40) NULL,
    auto_created TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    settings_json LONGTEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_payment_methods_company_slug (company_id, slug),
    INDEX idx_payment_methods_company_active (company_id, is_active),
    INDEX idx_payment_methods_provider (provider_key),
    CONSTRAINT fk_payment_methods_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_payment_methods_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_payment_methods_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(60) NOT NULL UNIQUE,
    company_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    payment_method_id INT UNSIGNED NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_at DATE NULL,
    status ENUM('draft', 'open', 'partial', 'paid', 'overdue') NOT NULL DEFAULT 'open',
    tags VARCHAR(255) NULL,
    project_name VARCHAR(150) NULL,
    boleto_url VARCHAR(255) NULL,
    is_recurring TINYINT(1) NOT NULL DEFAULT 0,
    recurrence_interval ENUM('monthly', 'quarterly', 'yearly') NOT NULL DEFAULT 'monthly',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_invoices_company (company_id),
    INDEX idx_invoices_student (student_id),
    INDEX idx_invoices_payment_method (payment_method_id),
    INDEX idx_invoices_due (due_date),
    INDEX idx_invoices_status (status),
    CONSTRAINT fk_invoices_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_invoices_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_invoices_payment_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_invoices_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bank_slips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL UNIQUE,
    provider VARCHAR(60) NOT NULL DEFAULT 'manual',
    status ENUM('pending', 'processing', 'registered', 'issued', 'paid', 'received', 'overdue', 'cancelled', 'failed') NOT NULL DEFAULT 'pending',
    external_id VARCHAR(120) NULL,
    digitable_line VARCHAR(120) NULL,
    barcode VARCHAR(120) NULL,
    pix_qr_code TEXT NULL,
    pix_copy_paste TEXT NULL,
    boleto_url VARCHAR(255) NULL,
    pdf_url VARCHAR(255) NULL,
    amount DECIMAL(12,2) NULL,
    due_date DATE NULL,
    request_payload JSON NULL,
    response_payload JSON NULL,
    error_message VARCHAR(255) NULL,
    last_attempt_at DATETIME NULL,
    issued_at DATETIME NULL,
    paid_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_bank_slips_status (status),
    CONSTRAINT fk_bank_slips_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bank_slips_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fiscal_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL UNIQUE,
    provider VARCHAR(60) NOT NULL DEFAULT 'manual',
    status ENUM('pending', 'processing', 'issued', 'failed') NOT NULL DEFAULT 'pending',
    external_id VARCHAR(120) NULL,
    number VARCHAR(80) NULL,
    request_payload JSON NULL,
    response_payload JSON NULL,
    error_message VARCHAR(255) NULL,
    last_attempt_at DATETIME NULL,
    issued_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_fiscal_status (status),
    CONSTRAINT fk_fiscal_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fiscal_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    qty DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_ref VARCHAR(60) NOT NULL UNIQUE,
    company_id INT UNSIGNED NOT NULL,
    payment_method_id INT UNSIGNED NULL,
    method VARCHAR(60) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    paid_at DATE NOT NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_payments_company (company_id),
    INDEX idx_payments_payment_method (payment_method_id),
    CONSTRAINT fk_payments_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_payments_payment_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_payments_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payment_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_payment_items_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_payment_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE finance_notification_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    notification_type ENUM('reminder', 'due_today') NOT NULL,
    recipient_type ENUM('student', 'admin') NOT NULL,
    recipient_email VARCHAR(180) NOT NULL,
    status ENUM('sent', 'error') NOT NULL DEFAULT 'sent',
    error_message VARCHAR(255) NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_finance_notification_unique (company_id, invoice_id, notification_type, recipient_type, recipient_email),
    INDEX idx_finance_notification_company (company_id),
    INDEX idx_finance_notification_invoice (invoice_id),
    INDEX idx_finance_notification_status (status),
    CONSTRAINT fk_finance_notification_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_finance_notification_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    color VARCHAR(20) NOT NULL DEFAULT '#64748b',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_tags_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE taggables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tag_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_taggables_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE uploads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('student', 'lead', 'course', 'finance', 'other') NOT NULL DEFAULT 'other',
    entity_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(40) NULL,
    file_size BIGINT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_uploads_entity (entity_type, entity_id),
    CONSTRAINT fk_uploads_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE arsenal_categories (
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

CREATE TABLE arsenal_items (
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

CREATE TABLE arsenal_item_courses (
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

CREATE TABLE arsenal_item_students (
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

CREATE TABLE arsenal_access_logs (
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

CREATE TABLE signature_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    signer_name VARCHAR(180) NOT NULL,
    signer_email VARCHAR(180) NOT NULL,
    signer_phone VARCHAR(40) NULL,
    file_original_path VARCHAR(255) NOT NULL,
    file_signed_path VARCHAR(255) NULL,
    d4sign_safe_uuid VARCHAR(120) NULL,
    d4sign_document_uuid VARCHAR(120) NULL,
    d4sign_signer_key VARCHAR(255) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    d4sign_status VARCHAR(80) NULL,
    sent_at DATETIME NULL,
    signed_at DATETIME NULL,
    last_synced_at DATETIME NULL,
    last_error TEXT NULL,
    metadata_json LONGTEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_signature_requests_company (company_id),
    INDEX idx_signature_requests_student (student_id),
    INDEX idx_signature_requests_status (status),
    UNIQUE KEY uk_signature_requests_company_document (company_id, d4sign_document_uuid),
    CONSTRAINT fk_signature_requests_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_signature_requests_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_signature_requests_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE signature_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    signature_request_id INT UNSIGNED NULL,
    d4sign_document_uuid VARCHAR(120) NULL,
    event_type VARCHAR(80) NULL,
    event_status VARCHAR(80) NULL,
    event_message VARCHAR(255) NULL,
    payload_json LONGTEXT NULL,
    received_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_signature_events_company (company_id),
    INDEX idx_signature_events_request (signature_request_id),
    INDEX idx_signature_events_document (d4sign_document_uuid),
    CONSTRAINT fk_signature_events_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_signature_events_request FOREIGN KEY (signature_request_id) REFERENCES signature_requests(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE module_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_name ENUM('projects', 'tasks', 'requests', 'automations', 'help') NOT NULL,
    title VARCHAR(180) NOT NULL,
    status VARCHAR(60) NOT NULL DEFAULT 'aberto',
    responsible VARCHAR(120) NULL,
    priority VARCHAR(40) NOT NULL DEFAULT 'media',
    due_date DATE NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_module_items_module (module_name),
    CONSTRAINT fk_module_items_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed inicial
INSERT INTO companies (legal_name, trade_name, cnpj, is_active, created_at, updated_at)
VALUES
    ('ANEO Brasil', 'ANEO Brasil', '00.000.000/0001-00', 1, NOW(), NOW());

INSERT INTO users (name, username, email, password_hash, role, is_active, created_at, updated_at)
VALUES
    ('Administrador ANEO', 'admin', 'admin@aneo.local', 'admin123', 'admin', 1, NOW(), NOW());

INSERT INTO user_companies (user_id, company_id, is_default, created_at, updated_at)
VALUES
    (1, 1, 1, NOW(), NOW());

INSERT INTO kanban_status (name, slug, color, display_order, is_default, created_at, updated_at)
VALUES
    ('Sem pendencias', 'sem-pendencias', '#14b8a6', 1, 1, NOW(), NOW()),
    ('Inadimplente', 'inadimplente', '#ef4444', 2, 0, NOW(), NOW()),
    ('Em processo de acordo', 'em-processo-de-acordo', '#f97316', 3, 0, NOW(), NOW()),
    ('Acordo ativo', 'acordo-ativo', '#22c55e', 4, 0, NOW(), NOW()),
    ('2o acordo ativo', 'segundo-acordo-ativo', '#6366f1', 5, 0, NOW(), NOW());

INSERT INTO lead_status (name, color, display_order, is_default, created_at, updated_at)
VALUES
    ('Lead', '#0ea5e9', 1, 1, NOW(), NOW()),
    ('Aguardando Pag Taxa', '#f59e0b', 2, 0, NOW(), NOW()),
    ('Prova em andamento', '#14b8a6', 3, 0, NOW(), NOW()),
    ('Aguardando preench. matricula', '#6366f1', 4, 0, NOW(), NOW()),
    ('Aguardando pag. matricula', '#f97316', 5, 0, NOW(), NOW()),
    ('Aguardado ass. contrato', '#eab308', 6, 0, NOW(), NOW()),
    ('Enviar documentos', '#22c55e', 7, 0, NOW(), NOW()),
    ('Escala', '#3b82f6', 8, 0, NOW(), NOW()),
    ('Documentacao pendente', '#ef4444', 9, 0, NOW(), NOW()),
    ('Aluno ativo', '#16a34a', 10, 0, NOW(), NOW());

INSERT INTO course_categories (company_id, name, created_by, created_at, updated_at)
VALUES
    (1, 'Saude', 1, NOW(), NOW()),
    (1, 'Gestao', 1, NOW(), NOW()),
    (1, 'Atualizacao Profissional', 1, NOW(), NOW());

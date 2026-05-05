CREATE TABLE IF NOT EXISTS student_practice_units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    city VARCHAR(120) NULL,
    state VARCHAR(10) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_student_practice_units_company (company_id),
    KEY idx_student_practice_units_active (is_active)
);

CREATE TABLE IF NOT EXISTS student_duty_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    unit_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    published_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_student_duty_schedules_company (company_id),
    KEY idx_student_duty_schedules_unit (unit_id),
    KEY idx_student_duty_schedules_status (status)
);

CREATE TABLE IF NOT EXISTS student_duty_schedule_weeks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT UNSIGNED NOT NULL,
    month_ref VARCHAR(40) NOT NULL,
    week_order INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    r3_slots INT UNSIGNED NOT NULL DEFAULT 1,
    r2_slots INT UNSIGNED NOT NULL DEFAULT 1,
    r1_slots INT UNSIGNED NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_student_duty_schedule_weeks_order (schedule_id, week_order),
    KEY idx_student_duty_schedule_weeks_month (month_ref)
);

CREATE TABLE IF NOT EXISTS student_duty_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_week_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    residency_level_snapshot VARCHAR(10) NOT NULL,
    slot_group VARCHAR(10) NOT NULL,
    position_order INT UNSIGNED NOT NULL DEFAULT 1,
    assigned_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_student_duty_assignments_week_student (schedule_week_id, student_id),
    UNIQUE KEY uq_student_duty_assignments_slot_position (schedule_week_id, slot_group, position_order),
    KEY idx_student_duty_assignments_student (student_id)
);

ALTER TABLE students
    ADD COLUMN IF NOT EXISTS practice_unit_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS residency_level VARCHAR(10) NOT NULL DEFAULT 'R1';

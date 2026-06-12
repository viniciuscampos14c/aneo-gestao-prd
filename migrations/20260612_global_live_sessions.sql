ALTER TABLE course_live_sessions
    ADD COLUMN IF NOT EXISTS is_global TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN IF NOT EXISTS global_session_uuid CHAR(36) NULL AFTER is_global,
    ADD COLUMN IF NOT EXISTS global_master_session_id INT UNSIGNED NULL AFTER global_session_uuid;

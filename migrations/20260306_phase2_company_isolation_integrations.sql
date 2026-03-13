SET NAMES utf8mb4;
SET time_zone = '-03:00';

SET @default_company_id = (SELECT id FROM companies ORDER BY id ASC LIMIT 1);

CREATE TABLE IF NOT EXISTS company_integrations (
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

ALTER TABLE course_categories ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id;
UPDATE course_categories SET company_id = @default_company_id WHERE company_id IS NULL;
ALTER TABLE course_categories MODIFY company_id INT UNSIGNED NOT NULL;

SET @legacy_course_categories_unique = (
    SELECT INDEX_NAME
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'course_categories'
      AND NON_UNIQUE = 0
    GROUP BY INDEX_NAME
    HAVING COUNT(*) = 1
       AND SUM(CASE WHEN COLUMN_NAME = 'name' THEN 1 ELSE 0 END) = 1
    LIMIT 1
);
SET @sql = IF(
    @legacy_course_categories_unique IS NOT NULL,
    CONCAT('ALTER TABLE course_categories DROP INDEX `', @legacy_course_categories_unique, '`'),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_course_categories_company = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'course_categories'
      AND index_name = 'idx_course_categories_company'
);
SET @sql = IF(@idx_course_categories_company = 0, 'ALTER TABLE course_categories ADD INDEX idx_course_categories_company (company_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @uk_course_categories_company_name = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'course_categories'
      AND index_name = 'uk_course_categories_company_name'
);
SET @sql = IF(@uk_course_categories_company_name = 0, 'ALTER TABLE course_categories ADD UNIQUE KEY uk_course_categories_company_name (company_id, name)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_course_categories_company = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'course_categories'
      AND constraint_name = 'fk_course_categories_company'
      AND constraint_type = 'FOREIGN KEY'
);
SET @sql = IF(@fk_course_categories_company = 0, 'ALTER TABLE course_categories ADD CONSTRAINT fk_course_categories_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE courses ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id;

UPDATE courses c
LEFT JOIN course_categories cat ON cat.id = c.category_id
SET c.company_id = cat.company_id
WHERE c.company_id IS NULL
  AND cat.company_id IS NOT NULL;

UPDATE courses c
INNER JOIN (
    SELECT e.course_id, MIN(s.company_id) AS company_id
    FROM enrollments e
    INNER JOIN students s ON s.id = e.student_id
    GROUP BY e.course_id
) src ON src.course_id = c.id
SET c.company_id = src.company_id
WHERE c.company_id IS NULL;

UPDATE courses SET company_id = @default_company_id WHERE company_id IS NULL;
ALTER TABLE courses MODIFY company_id INT UNSIGNED NOT NULL;

UPDATE courses c
INNER JOIN course_categories cat ON cat.id = c.category_id
SET c.category_id = NULL
WHERE cat.company_id <> c.company_id;

SET @idx_courses_company = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'courses'
      AND index_name = 'idx_courses_company'
);
SET @sql = IF(@idx_courses_company = 0, 'ALTER TABLE courses ADD INDEX idx_courses_company (company_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_courses_company = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'courses'
      AND constraint_name = 'fk_courses_company'
      AND constraint_type = 'FOREIGN KEY'
);
SET @sql = IF(@fk_courses_company = 0, 'ALTER TABLE courses ADD CONSTRAINT fk_courses_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE chatwoot_links ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id;

UPDATE chatwoot_links cl
INNER JOIN students s ON cl.entity_type = 'student' AND s.id = cl.entity_id
SET cl.company_id = s.company_id
WHERE cl.company_id IS NULL;

UPDATE chatwoot_links cl
INNER JOIN leads l ON cl.entity_type = 'lead' AND l.id = cl.entity_id
SET cl.company_id = l.company_id
WHERE cl.company_id IS NULL;

UPDATE chatwoot_links SET company_id = @default_company_id WHERE company_id IS NULL;
ALTER TABLE chatwoot_links MODIFY company_id INT UNSIGNED NOT NULL;

SET @idx_chatwoot_links_company = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'chatwoot_links'
      AND index_name = 'idx_chatwoot_links_company'
);
SET @sql = IF(@idx_chatwoot_links_company = 0, 'ALTER TABLE chatwoot_links ADD INDEX idx_chatwoot_links_company (company_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @legacy_chatwoot_entity = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'chatwoot_links'
      AND index_name = 'uk_chatwoot_entity'
);
SET @sql = IF(@legacy_chatwoot_entity > 0, 'ALTER TABLE chatwoot_links DROP INDEX uk_chatwoot_entity', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @uk_chatwoot_entity_company = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'chatwoot_links'
      AND index_name = 'uk_chatwoot_entity_company'
);
SET @sql = IF(@uk_chatwoot_entity_company = 0, 'ALTER TABLE chatwoot_links ADD UNIQUE KEY uk_chatwoot_entity_company (company_id, entity_type, entity_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_chatwoot_links_company = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'chatwoot_links'
      AND constraint_name = 'fk_chatwoot_links_company'
      AND constraint_type = 'FOREIGN KEY'
);
SET @sql = IF(@fk_chatwoot_links_company = 0, 'ALTER TABLE chatwoot_links ADD CONSTRAINT fk_chatwoot_links_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE chatwoot_flow_sessions ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id;
UPDATE chatwoot_flow_sessions SET company_id = @default_company_id WHERE company_id IS NULL;
ALTER TABLE chatwoot_flow_sessions MODIFY company_id INT UNSIGNED NOT NULL;

SET @legacy_chatwoot_flow_unique = (
    SELECT INDEX_NAME
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'chatwoot_flow_sessions'
      AND NON_UNIQUE = 0
    GROUP BY INDEX_NAME
    HAVING COUNT(*) = 1
       AND SUM(CASE WHEN COLUMN_NAME = 'conversation_id' THEN 1 ELSE 0 END) = 1
    LIMIT 1
);
SET @sql = IF(
    @legacy_chatwoot_flow_unique IS NOT NULL,
    CONCAT('ALTER TABLE chatwoot_flow_sessions DROP INDEX `', @legacy_chatwoot_flow_unique, '`'),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_chatwoot_flow_company = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'chatwoot_flow_sessions'
      AND index_name = 'idx_chatwoot_flow_company'
);
SET @sql = IF(@idx_chatwoot_flow_company = 0, 'ALTER TABLE chatwoot_flow_sessions ADD INDEX idx_chatwoot_flow_company (company_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @uk_chatwoot_flow_company_conversation = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'chatwoot_flow_sessions'
      AND index_name = 'uk_chatwoot_flow_company_conversation'
);
SET @sql = IF(@uk_chatwoot_flow_company_conversation = 0, 'ALTER TABLE chatwoot_flow_sessions ADD UNIQUE KEY uk_chatwoot_flow_company_conversation (company_id, conversation_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_chatwoot_flow_company = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'chatwoot_flow_sessions'
      AND constraint_name = 'fk_chatwoot_flow_company'
      AND constraint_type = 'FOREIGN KEY'
);
SET @sql = IF(@fk_chatwoot_flow_company = 0, 'ALTER TABLE chatwoot_flow_sessions ADD CONSTRAINT fk_chatwoot_flow_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE signature_requests ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id;

UPDATE signature_requests sr
INNER JOIN students s ON s.id = sr.student_id
SET sr.company_id = s.company_id
WHERE sr.company_id IS NULL;

UPDATE signature_requests SET company_id = @default_company_id WHERE company_id IS NULL;
ALTER TABLE signature_requests MODIFY company_id INT UNSIGNED NOT NULL;

SET @idx_signature_requests_company = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'signature_requests'
      AND index_name = 'idx_signature_requests_company'
);
SET @sql = IF(@idx_signature_requests_company = 0, 'ALTER TABLE signature_requests ADD INDEX idx_signature_requests_company (company_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @legacy_signature_document = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'signature_requests'
      AND index_name = 'uk_signature_requests_document'
);
SET @sql = IF(@legacy_signature_document > 0, 'ALTER TABLE signature_requests DROP INDEX uk_signature_requests_document', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @uk_signature_requests_company_document = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'signature_requests'
      AND index_name = 'uk_signature_requests_company_document'
);
SET @sql = IF(@uk_signature_requests_company_document = 0, 'ALTER TABLE signature_requests ADD UNIQUE KEY uk_signature_requests_company_document (company_id, d4sign_document_uuid)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_signature_requests_company = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'signature_requests'
      AND constraint_name = 'fk_signature_requests_company'
      AND constraint_type = 'FOREIGN KEY'
);
SET @sql = IF(@fk_signature_requests_company = 0, 'ALTER TABLE signature_requests ADD CONSTRAINT fk_signature_requests_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE signature_events ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id;

UPDATE signature_events se
INNER JOIN signature_requests sr ON sr.id = se.signature_request_id
SET se.company_id = sr.company_id
WHERE se.company_id IS NULL;

UPDATE signature_events se
INNER JOIN signature_requests sr ON sr.d4sign_document_uuid = se.d4sign_document_uuid
SET se.company_id = sr.company_id
WHERE se.company_id IS NULL;

UPDATE signature_events SET company_id = @default_company_id WHERE company_id IS NULL;
ALTER TABLE signature_events MODIFY company_id INT UNSIGNED NOT NULL;

SET @idx_signature_events_company = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'signature_events'
      AND index_name = 'idx_signature_events_company'
);
SET @sql = IF(@idx_signature_events_company = 0, 'ALTER TABLE signature_events ADD INDEX idx_signature_events_company (company_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_signature_events_company = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'signature_events'
      AND constraint_name = 'fk_signature_events_company'
      AND constraint_type = 'FOREIGN KEY'
);
SET @sql = IF(@fk_signature_events_company = 0, 'ALTER TABLE signature_events ADD CONSTRAINT fk_signature_events_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

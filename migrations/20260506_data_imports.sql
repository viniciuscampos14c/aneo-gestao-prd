CREATE TABLE IF NOT EXISTS data_import_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    import_type VARCHAR(40) NOT NULL,
    original_filename VARCHAR(255) NULL,
    stored_file_path VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'uploaded',
    total_rows INT UNSIGNED NOT NULL DEFAULT 0,
    valid_rows INT UNSIGNED NOT NULL DEFAULT 0,
    error_rows INT UNSIGNED NOT NULL DEFAULT 0,
    created_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_count INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
    error_count INT UNSIGNED NOT NULL DEFAULT 0,
    options_json LONGTEXT NULL,
    summary_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    KEY idx_data_import_batches_company (company_id, created_at),
    KEY idx_data_import_batches_type_status (import_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS data_import_rows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    row_number INT UNSIGNED NOT NULL,
    source_key VARCHAR(190) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'valid',
    action VARCHAR(40) NOT NULL DEFAULT 'pending',
    target_table VARCHAR(80) NULL,
    target_id INT UNSIGNED NULL,
    raw_data LONGTEXT NULL,
    normalized_data LONGTEXT NULL,
    errors_json LONGTEXT NULL,
    warnings_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_data_import_rows_batch (batch_id, row_number),
    KEY idx_data_import_rows_status (batch_id, status),
    KEY idx_data_import_rows_source (batch_id, source_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS data_import_entity_map (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    import_type VARCHAR(40) NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    source_key VARCHAR(190) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_data_import_entity_map (company_id, import_type, entity_type, source_key),
    KEY idx_data_import_entity_map_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

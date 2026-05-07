CREATE TABLE IF NOT EXISTS system_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_key VARCHAR(80) NOT NULL,
    title VARCHAR(160) NOT NULL,
    version VARCHAR(40) NOT NULL,
    min_core_version VARCHAR(40) NULL,
    description TEXT NULL,
    author VARCHAR(160) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'inactive',
    installed_by INT UNSIGNED NULL,
    installed_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    package_filename VARCHAR(255) NULL,
    package_hash CHAR(64) NULL,
    install_path VARCHAR(255) NULL,
    manifest_json LONGTEXT NULL,
    permissions_json LONGTEXT NULL,
    menu_json LONGTEXT NULL,
    migrations_json LONGTEXT NULL,
    last_error TEXT NULL,
    UNIQUE KEY uk_system_modules_key (module_key),
    KEY idx_system_modules_status (status),
    KEY idx_system_modules_installed_at (installed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_module_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NOT NULL,
    module_key VARCHAR(80) NOT NULL,
    permission_key VARCHAR(120) NOT NULL,
    label VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uk_system_module_permission (module_key, permission_key),
    KEY idx_system_module_permissions_module (module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_module_migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NOT NULL,
    module_key VARCHAR(80) NOT NULL,
    migration_file VARCHAR(255) NOT NULL,
    checksum CHAR(64) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'executed',
    executed_by INT UNSIGNED NULL,
    executed_at DATETIME NOT NULL,
    error_message TEXT NULL,
    UNIQUE KEY uk_system_module_migration (module_key, migration_file),
    KEY idx_system_module_migrations_module (module_id),
    KEY idx_system_module_migrations_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_module_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NULL,
    module_key VARCHAR(80) NULL,
    action VARCHAR(60) NOT NULL,
    level VARCHAR(20) NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    context_json LONGTEXT NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    KEY idx_system_module_logs_module (module_id, created_at),
    KEY idx_system_module_logs_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

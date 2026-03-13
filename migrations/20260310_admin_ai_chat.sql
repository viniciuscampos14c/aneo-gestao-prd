-- Historico do chat administrativo com IA

CREATE TABLE IF NOT EXISTS admin_ai_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_admin_ai_sessions_company_user (company_id, user_id),
    INDEX idx_admin_ai_sessions_updated (updated_at),
    CONSTRAINT fk_admin_ai_sessions_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_admin_ai_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_ai_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    role ENUM('system', 'user', 'assistant') NOT NULL DEFAULT 'assistant',
    content LONGTEXT NOT NULL,
    metadata_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_admin_ai_messages_session (session_id),
    CONSTRAINT fk_admin_ai_messages_session FOREIGN KEY (session_id) REFERENCES admin_ai_sessions(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migracao incremental para integrar CRM ao Chatwoot
-- Salva o vinculo local de aluno/lead com contato e conversa do Chatwoot

CREATE TABLE IF NOT EXISTS chatwoot_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
    UNIQUE KEY uk_chatwoot_entity (entity_type, entity_id),
    INDEX idx_chatwoot_conversation (conversation_id),
    INDEX idx_chatwoot_contact (contact_id),
    INDEX idx_chatwoot_status (status),
    CONSTRAINT fk_chatwoot_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

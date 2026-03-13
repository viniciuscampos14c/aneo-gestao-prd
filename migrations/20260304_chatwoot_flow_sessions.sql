-- Migracao incremental para estado de automacao do bot Chatwoot
-- Guarda etapa atual por conversa para fluxo menu -> nome/cidade -> encaminhamento

CREATE TABLE IF NOT EXISTS chatwoot_flow_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL UNIQUE,
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
    INDEX idx_chatwoot_flow_step (current_step),
    INDEX idx_chatwoot_flow_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

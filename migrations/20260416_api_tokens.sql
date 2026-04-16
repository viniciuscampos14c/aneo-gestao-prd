-- Migracao: sistema de tokens de API com permissoes granulares
-- Data: 2026-04-16

CREATE TABLE IF NOT EXISTS api_tokens (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    company_id   INT UNSIGNED     NOT NULL,
    user_id      INT UNSIGNED     NOT NULL,
    name         VARCHAR(100)     NOT NULL,
    token_hash   VARCHAR(64)      NOT NULL UNIQUE COMMENT 'SHA-256 hex do token bruto',
    permissions  JSON             NOT NULL             COMMENT '{"students":["get","search"],...}',
    expires_at   DATE             NULL                 COMMENT 'NULL = sem expiracao',
    last_used_at DATETIME         NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_api_tokens_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_api_tokens_user    FOREIGN KEY (user_id)    REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

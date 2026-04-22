-- Migration: adiciona coluna nosso_numero à tabela bank_slips
-- Necessária para integração Itaú (lookup por webhook)
ALTER TABLE bank_slips
    ADD COLUMN nosso_numero VARCHAR(30) NULL AFTER external_id,
    ADD INDEX idx_nosso_numero (nosso_numero);

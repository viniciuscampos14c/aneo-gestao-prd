-- Adiciona coluna de data de entrada do aluno (base para rematrícula)
-- Data: 2026-04-20

ALTER TABLE students
    ADD COLUMN IF NOT EXISTS enrolled_at DATE NULL DEFAULT NULL
        COMMENT 'Data de entrada do aluno — base para rematrícula semestral'
        AFTER birth_date;

-- CPF do aluno para integracoes bancarias (Itaú/boletos).

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'students' AND column_name = 'cpf') = 0,
    'ALTER TABLE students ADD COLUMN cpf VARCHAR(14) NULL AFTER birth_date',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'students' AND index_name = 'idx_students_cpf') = 0,
    'ALTER TABLE students ADD INDEX idx_students_cpf (cpf)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

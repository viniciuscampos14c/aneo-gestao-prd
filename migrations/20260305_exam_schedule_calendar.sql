-- Migracao incremental para habilitar calendario de provas
-- Adiciona data/hora de agendamento em exames sem quebrar bases ja atualizadas

SET @has_scheduled_at := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'exams'
      AND column_name = 'scheduled_at'
);

SET @add_scheduled_at_sql := IF(
    @has_scheduled_at = 0,
    'ALTER TABLE exams ADD COLUMN scheduled_at DATETIME NULL AFTER passing_score',
    'SELECT 1'
);

PREPARE add_scheduled_at_stmt FROM @add_scheduled_at_sql;
EXECUTE add_scheduled_at_stmt;
DEALLOCATE PREPARE add_scheduled_at_stmt;

SET @has_schedule_index := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'exams'
      AND index_name = 'idx_exams_scheduled_at'
);

SET @add_schedule_index_sql := IF(
    @has_schedule_index = 0,
    'ALTER TABLE exams ADD INDEX idx_exams_scheduled_at (scheduled_at)',
    'SELECT 1'
);

PREPARE add_schedule_index_stmt FROM @add_schedule_index_sql;
EXECUTE add_schedule_index_stmt;
DEALLOCATE PREPARE add_schedule_index_stmt;

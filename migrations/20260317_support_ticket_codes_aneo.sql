-- Normaliza codigos de chamados para o padrao ANEO + sequencial.
-- Exemplo: ANEO001, ANEO002...

SET @has_support_tickets := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'support_tickets'
);

SET @sql_support_tickets := IF(
    @has_support_tickets > 0,
    'UPDATE support_tickets
        SET ticket_code = CONCAT(''ANEO'', LPAD(CAST(id AS CHAR), 3, ''0''))
      WHERE ticket_code IS NULL
         OR ticket_code = ''''
         OR ticket_code NOT REGEXP ''^ANEO[0-9]+$''',
    'SELECT ''Tabela support_tickets nao encontrada'' AS message'
);

PREPARE stmt_support_tickets FROM @sql_support_tickets;
EXECUTE stmt_support_tickets;
DEALLOCATE PREPARE stmt_support_tickets;

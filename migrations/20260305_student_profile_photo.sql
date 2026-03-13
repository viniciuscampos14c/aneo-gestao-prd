SET @profile_photo_column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'students'
      AND column_name = 'profile_photo'
);

SET @profile_photo_sql := IF(
    @profile_photo_column_exists = 0,
    'ALTER TABLE students ADD COLUMN profile_photo VARCHAR(255) NULL AFTER phone',
    'SELECT 1'
);

PREPARE profile_photo_stmt FROM @profile_photo_sql;
EXECUTE profile_photo_stmt;
DEALLOCATE PREPARE profile_photo_stmt;

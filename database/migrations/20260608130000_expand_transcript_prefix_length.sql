SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tahun_ajaran'
      AND COLUMN_NAME = 'transkrip_nomor_prefix'
);

SET @sql := IF(
    @column_exists = 1,
    'ALTER TABLE tahun_ajaran MODIFY COLUMN transkrip_nomor_prefix VARCHAR(80) NULL',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

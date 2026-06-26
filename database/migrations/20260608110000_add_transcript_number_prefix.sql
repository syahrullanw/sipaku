SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sekolah'
      AND COLUMN_NAME = 'transkrip_nomor_prefix'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE sekolah ADD COLUMN transkrip_nomor_prefix VARCHAR(30) NULL',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

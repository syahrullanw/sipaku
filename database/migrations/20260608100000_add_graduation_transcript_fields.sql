SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'status_kelulusan_siswa' AND COLUMN_NAME = 'nomor_ijazah') = 0,
    'ALTER TABLE status_kelulusan_siswa ADD COLUMN nomor_ijazah VARCHAR(100) NULL AFTER catatan',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'status_kelulusan_siswa' AND COLUMN_NAME = 'jenis_kekhususan') = 0,
    'ALTER TABLE status_kelulusan_siswa ADD COLUMN jenis_kekhususan VARCHAR(100) NULL AFTER nomor_ijazah',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

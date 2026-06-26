SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tahun_ajaran'
      AND COLUMN_NAME = 'skl_nomor_surat'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE tahun_ajaran ADD COLUMN skl_nomor_surat VARCHAR(190) NULL AFTER tanggal_raport_tengah_semester',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tahun_ajaran'
      AND COLUMN_NAME = 'skl_tanggal_rapat_pleno'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE tahun_ajaran ADD COLUMN skl_tanggal_rapat_pleno DATE NULL AFTER skl_nomor_surat',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tahun_ajaran'
      AND COLUMN_NAME = 'skl_titimangsa'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE tahun_ajaran ADD COLUMN skl_titimangsa DATE NULL AFTER skl_tanggal_rapat_pleno',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tahun_ajaran'
      AND COLUMN_NAME = 'transkrip_nomor_prefix'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE tahun_ajaran ADD COLUMN transkrip_nomor_prefix VARCHAR(80) NULL AFTER skl_titimangsa',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @school_skl_nomor_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sekolah'
      AND COLUMN_NAME = 'skl_nomor_surat'
);

SET @school_skl_rapat_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sekolah'
      AND COLUMN_NAME = 'skl_tanggal_rapat_pleno'
);

SET @school_skl_titimangsa_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sekolah'
      AND COLUMN_NAME = 'skl_titimangsa'
);

SET @school_transkrip_prefix_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sekolah'
      AND COLUMN_NAME = 'transkrip_nomor_prefix'
);

SET @school_skl_nomor_expr := IF(@school_skl_nomor_exists > 0, 's.skl_nomor_surat', 'NULL');
SET @school_skl_rapat_expr := IF(@school_skl_rapat_exists > 0, 's.skl_tanggal_rapat_pleno', 'NULL');
SET @school_skl_titimangsa_expr := IF(@school_skl_titimangsa_exists > 0, 's.skl_titimangsa', 'NULL');
SET @school_transkrip_prefix_expr := IF(@school_transkrip_prefix_exists > 0, 's.transkrip_nomor_prefix', 'NULL');

SET @sql := CONCAT(
    'UPDATE tahun_ajaran ta ',
    'CROSS JOIN (',
    'SELECT ',
    @school_skl_nomor_expr, ' AS skl_nomor_surat, ',
    @school_skl_rapat_expr, ' AS skl_tanggal_rapat_pleno, ',
    @school_skl_titimangsa_expr, ' AS skl_titimangsa, ',
    @school_transkrip_prefix_expr, ' AS transkrip_nomor_prefix ',
    'FROM sekolah s ORDER BY s.id ASC LIMIT 1',
    ') legacy ',
    'SET ',
    'ta.skl_nomor_surat = COALESCE(ta.skl_nomor_surat, legacy.skl_nomor_surat), ',
    'ta.skl_tanggal_rapat_pleno = COALESCE(ta.skl_tanggal_rapat_pleno, legacy.skl_tanggal_rapat_pleno), ',
    'ta.skl_titimangsa = COALESCE(ta.skl_titimangsa, legacy.skl_titimangsa), ',
    'ta.transkrip_nomor_prefix = COALESCE(ta.transkrip_nomor_prefix, legacy.transkrip_nomor_prefix) ',
    'WHERE ',
    'ta.skl_nomor_surat IS NULL ',
    'OR ta.skl_tanggal_rapat_pleno IS NULL ',
    'OR ta.skl_titimangsa IS NULL ',
    'OR ta.transkrip_nomor_prefix IS NULL'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

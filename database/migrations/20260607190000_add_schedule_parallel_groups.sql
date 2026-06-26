CREATE TABLE IF NOT EXISTS jadwal_kelas_paralel (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
    nama VARCHAR(150) NULL,
    kelas_ids_json LONGTEXT NOT NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_jadwal_kelas_paralel_tahun (tahun_ajaran_id, guru_mata_pelajaran_id, aktif),
    CONSTRAINT fk_jadwal_kelas_paralel_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_jadwal_kelas_paralel_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'jadwal_draft_items'
       AND COLUMN_NAME = 'parallel_group_id') = 0,
    'ALTER TABLE jadwal_draft_items ADD COLUMN parallel_group_id INT UNSIGNED NULL AFTER jumlah_jam',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'jadwal_pelajaran'
       AND COLUMN_NAME = 'parallel_group_id') = 0,
    'ALTER TABLE jadwal_pelajaran ADD COLUMN parallel_group_id INT UNSIGNED NULL AFTER ruangan_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'jadwal_draft_items'
       AND CONSTRAINT_NAME = 'fk_jadwal_draft_items_parallel') = 0,
    'ALTER TABLE jadwal_draft_items ADD CONSTRAINT fk_jadwal_draft_items_parallel FOREIGN KEY (parallel_group_id) REFERENCES jadwal_kelas_paralel(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

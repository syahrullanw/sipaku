CREATE TABLE IF NOT EXISTS jadwal_ruangan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(32) NOT NULL,
    nama VARCHAR(120) NOT NULL,
    jenis ENUM('kelas','lab','bengkel','lainnya') NOT NULL DEFAULT 'kelas',
    kapasitas SMALLINT UNSIGNED NULL,
    status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_jadwal_ruangan_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jadwal_hari_aktif (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    hari ENUM('senin','selasa','rabu','kamis','jumat','sabtu','minggu') NOT NULL,
    urutan TINYINT UNSIGNED NOT NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_jadwal_hari_tahun (tahun_ajaran_id, hari),
    KEY idx_jadwal_hari_tahun (tahun_ajaran_id, aktif, urutan),
    CONSTRAINT fk_jadwal_hari_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jadwal_jam_pelajaran (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    hari ENUM('senin','selasa','rabu','kamis','jumat','sabtu','minggu') NOT NULL,
    jam_ke TINYINT UNSIGNED NOT NULL,
    waktu_mulai TIME NOT NULL,
    waktu_selesai TIME NOT NULL,
    tipe ENUM('pelajaran','istirahat','kegiatan') NOT NULL DEFAULT 'pelajaran',
    label VARCHAR(120) NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_jadwal_jam_tahun_hari (tahun_ajaran_id, hari, jam_ke),
    KEY idx_jadwal_jam_tahun (tahun_ajaran_id, hari, aktif, jam_ke),
    CONSTRAINT fk_jadwal_jam_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jadwal_kegiatan_tetap (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    hari ENUM('senin','selasa','rabu','kamis','jumat','sabtu','minggu') NOT NULL,
    jam_ke_mulai TINYINT UNSIGNED NOT NULL,
    jam_ke_selesai TINYINT UNSIGNED NOT NULL,
    nama VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_jadwal_kegiatan_tahun (tahun_ajaran_id, hari, jam_ke_mulai),
    CONSTRAINT fk_jadwal_kegiatan_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jadwal_ketersediaan_guru (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    hari ENUM('senin','selasa','rabu','kamis','jumat','sabtu','minggu') NOT NULL,
    jam_ke TINYINT UNSIGNED NOT NULL,
    status ENUM('tersedia','tidak_tersedia') NOT NULL DEFAULT 'tidak_tersedia',
    catatan VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_jadwal_availability (tahun_ajaran_id, guru_id, hari, jam_ke),
    KEY idx_jadwal_availability_guru (guru_id, tahun_ajaran_id),
    CONSTRAINT fk_jadwal_availability_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_jadwal_availability_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jadwal_batas_guru (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NULL,
    maksimal_jam_per_hari TINYINT UNSIGNED NOT NULL DEFAULT 8,
    maksimal_jam_per_minggu SMALLINT UNSIGNED NOT NULL DEFAULT 40,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_jadwal_batas_tahun_guru (tahun_ajaran_id, guru_id),
    CONSTRAINT fk_jadwal_batas_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_jadwal_batas_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jadwal_preferensi_generate (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    blok_produktif_min TINYINT UNSIGNED NOT NULL DEFAULT 2,
    blok_produktif_maks TINYINT UNSIGNED NOT NULL DEFAULT 4,
    blok_umum_maks TINYINT UNSIGNED NOT NULL DEFAULT 2,
    maks_mapel_berat_berurutan TINYINT UNSIGNED NOT NULL DEFAULT 2,
    prioritas_praktik_pagi TINYINT(1) NOT NULL DEFAULT 1,
    hindari_mapel_sama_per_hari TINYINT(1) NOT NULL DEFAULT 1,
    sebar_beban_guru TINYINT(1) NOT NULL DEFAULT 1,
    rapatkan_jadwal_kelas TINYINT(1) NOT NULL DEFAULT 1,
    bobot_jam_guru_harian TINYINT UNSIGNED NOT NULL DEFAULT 7,
    bobot_jam_kelas_harian TINYINT UNSIGNED NOT NULL DEFAULT 3,
    penalti_slot_sore_produktif TINYINT UNSIGNED NOT NULL DEFAULT 25,
    penalti_mapel_sama_hari TINYINT UNSIGNED NOT NULL DEFAULT 30,
    penalti_jam_kosong_guru TINYINT UNSIGNED NOT NULL DEFAULT 18,
    penalti_jam_kosong_kelas TINYINT UNSIGNED NOT NULL DEFAULT 15,
    penalti_mapel_berat_berurutan TINYINT UNSIGNED NOT NULL DEFAULT 22,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_jadwal_preferensi_tahun (tahun_ajaran_id),
    CONSTRAINT fk_jadwal_preferensi_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jadwal_preferensi_waktu (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    jam_masuk TIME NOT NULL DEFAULT '07:00:00',
    durasi_jp_menit TINYINT UNSIGNED NOT NULL DEFAULT 45,
    jeda_jp_menit TINYINT UNSIGNED NOT NULL DEFAULT 0,
    jumlah_jp_per_hari TINYINT UNSIGNED NOT NULL DEFAULT 8,
    istirahat_pertama_setelah_jp TINYINT UNSIGNED NOT NULL DEFAULT 4,
    durasi_istirahat_pertama_menit TINYINT UNSIGNED NOT NULL DEFAULT 15,
    istirahat_dzuhur_setelah_jp TINYINT UNSIGNED NOT NULL DEFAULT 6,
    durasi_istirahat_dzuhur_menit TINYINT UNSIGNED NOT NULL DEFAULT 45,
    durasi_istirahat_jumat_menit TINYINT UNSIGNED NOT NULL DEFAULT 75,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_jadwal_preferensi_waktu_tahun (tahun_ajaran_id),
    CONSTRAINT fk_jadwal_preferensi_waktu_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS jadwal_draft (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
    tingkat TINYINT UNSIGNED NULL,
    nama VARCHAR(150) NOT NULL,
    status ENUM('draft','aktif','arsip') NOT NULL DEFAULT 'draft',
    total_item INT UNSIGNED NOT NULL DEFAULT 0,
    total_gagal INT UNSIGNED NOT NULL DEFAULT 0,
    conflict_json LONGTEXT NULL,
    created_by INT UNSIGNED NULL,
    activated_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_jadwal_draft_context (tahun_ajaran_id, semester, tingkat, status),
    CONSTRAINT fk_jadwal_draft_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jadwal_draft_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    draft_id INT UNSIGNED NOT NULL,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
    guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    ruangan_id INT UNSIGNED NULL,
    hari ENUM('senin','selasa','rabu','kamis','jumat','sabtu','minggu') NULL,
    jam_ke_mulai TINYINT UNSIGNED NULL,
    jam_ke_selesai TINYINT UNSIGNED NULL,
    waktu_mulai TIME NULL,
    waktu_selesai TIME NULL,
    jumlah_jam TINYINT UNSIGNED NOT NULL,
    parallel_group_id INT UNSIGNED NULL,
    status ENUM('generated','manual','fixed','failed') NOT NULL DEFAULT 'generated',
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    catatan VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_jadwal_draft_items_draft (draft_id, hari, jam_ke_mulai),
    KEY idx_jadwal_draft_items_guru (guru_id, hari, jam_ke_mulai),
    KEY idx_jadwal_draft_items_kelas (kelas_id, hari, jam_ke_mulai),
    CONSTRAINT fk_jadwal_draft_items_draft FOREIGN KEY (draft_id) REFERENCES jadwal_draft(id) ON DELETE CASCADE,
    CONSTRAINT fk_jadwal_draft_items_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_jadwal_draft_items_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_jadwal_draft_items_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    CONSTRAINT fk_jadwal_draft_items_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_jadwal_draft_items_parallel FOREIGN KEY (parallel_group_id) REFERENCES jadwal_kelas_paralel(id) ON DELETE SET NULL,
    CONSTRAINT fk_jadwal_draft_items_ruangan FOREIGN KEY (ruangan_id) REFERENCES jadwal_ruangan(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jadwal_pelajaran' AND COLUMN_NAME = 'semester') = 0, 'ALTER TABLE jadwal_pelajaran ADD COLUMN semester TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER tahun_ajaran_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jadwal_pelajaran' AND COLUMN_NAME = 'draft_id') = 0, 'ALTER TABLE jadwal_pelajaran ADD COLUMN draft_id INT UNSIGNED NULL AFTER semester', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jadwal_pelajaran' AND COLUMN_NAME = 'jam_ke_mulai') = 0, 'ALTER TABLE jadwal_pelajaran ADD COLUMN jam_ke_mulai TINYINT UNSIGNED NULL AFTER jumlah_jam', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jadwal_pelajaran' AND COLUMN_NAME = 'jam_ke_selesai') = 0, 'ALTER TABLE jadwal_pelajaran ADD COLUMN jam_ke_selesai TINYINT UNSIGNED NULL AFTER jam_ke_mulai', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jadwal_pelajaran' AND COLUMN_NAME = 'ruangan_id') = 0, 'ALTER TABLE jadwal_pelajaran ADD COLUMN ruangan_id INT UNSIGNED NULL AFTER jam_ke_selesai', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jadwal_draft_items' AND COLUMN_NAME = 'parallel_group_id') = 0, 'ALTER TABLE jadwal_draft_items ADD COLUMN parallel_group_id INT UNSIGNED NULL AFTER jumlah_jam', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jadwal_pelajaran' AND COLUMN_NAME = 'parallel_group_id') = 0, 'ALTER TABLE jadwal_pelajaran ADD COLUMN parallel_group_id INT UNSIGNED NULL AFTER ruangan_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jadwal_pelajaran' AND COLUMN_NAME = 'status_jadwal') = 0, 'ALTER TABLE jadwal_pelajaran ADD COLUMN status_jadwal ENUM("aktif","arsip") NOT NULL DEFAULT "aktif" AFTER ruangan_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jadwal_pelajaran' AND COLUMN_NAME = 'is_locked') = 0, 'ALTER TABLE jadwal_pelajaran ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER status_jadwal', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jadwal_pelajaran' AND COLUMN_NAME = 'sumber') = 0, 'ALTER TABLE jadwal_pelajaran ADD COLUMN sumber ENUM("manual","generate","aktivasi") NOT NULL DEFAULT "manual" AFTER is_locked', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jadwal_pelajaran' AND INDEX_NAME = 'idx_jadwal_status_context') = 0, 'CREATE INDEX idx_jadwal_status_context ON jadwal_pelajaran (tahun_ajaran_id, semester, status_jadwal, kelas_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jadwal_pelajaran' AND INDEX_NAME = 'idx_jadwal_slot_guru') = 0, 'CREATE INDEX idx_jadwal_slot_guru ON jadwal_pelajaran (tahun_ajaran_id, semester, hari, jam_ke_mulai, guru_mata_pelajaran_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

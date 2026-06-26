ALTER TABLE kelas
    ADD COLUMN kurikulum ENUM('k13','kurmer') NOT NULL DEFAULT 'k13' AFTER tingkat;

ALTER TABLE guru_mata_pelajaran_kelas
    ADD COLUMN penilaian_mode ENUM('inherit','k13','kurmer') NOT NULL DEFAULT 'inherit' AFTER kelas_id;

CREATE TABLE IF NOT EXISTS mata_pelajaran_tp (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
    guru_mata_pelajaran_kelas_id INT UNSIGNED NULL,
    kelas_id INT UNSIGNED NOT NULL,
    kode_tp VARCHAR(100) NOT NULL,
    fase CHAR(5) NULL,
    elemen TEXT NULL,
    deskripsi TEXT NULL,
    urutan INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_tp_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_tp_gmp_kelas FOREIGN KEY (guru_mata_pelajaran_kelas_id) REFERENCES guru_mata_pelajaran_kelas(id) ON DELETE SET NULL,
    CONSTRAINT fk_tp_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tp_per_mapel_kelas (guru_mata_pelajaran_id, kelas_id, kode_tp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS penilaian_tp_siswa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    tp_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    capaian_enum ENUM('BB','MB','BSH','SB') NOT NULL,
    nilai_opsional DECIMAL(5,2) NULL,
    catatan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_penilaian_tp_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_tp_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_tp_tp FOREIGN KEY (tp_id) REFERENCES mata_pelajaran_tp(id) ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_tp_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    UNIQUE KEY unique_penilaian_tp_siswa (tp_id, siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS penilaian_kurmer_mapel_siswa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    capaian_akhir_enum ENUM('BB','MB','BSH','SB') NULL,
    deskripsi_umum TEXT NULL,
    tindak_lanjut TEXT NULL,
    nilai_opsional DECIMAL(5,2) NULL,
    sumber_tp JSON NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_penilaian_kurmer_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_kurmer_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_kurmer_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    UNIQUE KEY unique_penilaian_kurmer_siswa (guru_mata_pelajaran_id, kelas_id, siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

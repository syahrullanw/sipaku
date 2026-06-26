CREATE TABLE IF NOT EXISTS kokurikuler_kegiatan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
    tema VARCHAR(255) NOT NULL,
    nama VARCHAR(255) NOT NULL,
    deskripsi TEXT NULL,
    guru_koordinator_id INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_kokurikuler_kegiatan_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_kokurikuler_kegiatan_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_kokurikuler_kegiatan_guru FOREIGN KEY (guru_koordinator_id) REFERENCES guru(id) ON DELETE SET NULL,
    UNIQUE KEY unique_kokurikuler_kelas_semester_nama (kelas_id, tahun_ajaran_id, semester, nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kokurikuler_kegiatan_elemen (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kegiatan_id INT UNSIGNED NOT NULL,
    elemen_id INT UNSIGNED NOT NULL,
    sub_elemen TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_kokurikuler_elemen_kegiatan FOREIGN KEY (kegiatan_id) REFERENCES kokurikuler_kegiatan(id) ON DELETE CASCADE,
    CONSTRAINT fk_kokurikuler_elemen_p5 FOREIGN KEY (elemen_id) REFERENCES p5_elemen(id) ON DELETE CASCADE,
    UNIQUE KEY unique_kokurikuler_kegiatan_elemen (kegiatan_id, elemen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kokurikuler_penilaian (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kegiatan_id INT UNSIGNED NOT NULL,
    elemen_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    capaian_enum ENUM('BB','MB','BSH','SB') NOT NULL,
    catatan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_kokurikuler_penilaian_kegiatan FOREIGN KEY (kegiatan_id) REFERENCES kokurikuler_kegiatan(id) ON DELETE CASCADE,
    CONSTRAINT fk_kokurikuler_penilaian_elemen FOREIGN KEY (elemen_id) REFERENCES kokurikuler_kegiatan_elemen(id) ON DELETE CASCADE,
    CONSTRAINT fk_kokurikuler_penilaian_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    UNIQUE KEY unique_kokurikuler_penilaian (kegiatan_id, elemen_id, siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kokurikuler_ringkasan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kegiatan_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    deskripsi_umum TEXT NULL,
    tindak_lanjut TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_kokurikuler_ringkasan_kegiatan FOREIGN KEY (kegiatan_id) REFERENCES kokurikuler_kegiatan(id) ON DELETE CASCADE,
    CONSTRAINT fk_kokurikuler_ringkasan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    UNIQUE KEY unique_kokurikuler_ringkasan (kegiatan_id, siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

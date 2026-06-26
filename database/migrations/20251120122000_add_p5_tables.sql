CREATE TABLE IF NOT EXISTS p5_dimensi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(50) NOT NULL,
    nama VARCHAR(255) NOT NULL,
    deskripsi TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_p5_dimensi_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS p5_elemen (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dimensi_id INT UNSIGNED NOT NULL,
    kode VARCHAR(50) NOT NULL,
    nama VARCHAR(255) NOT NULL,
    fase VARCHAR(20) NULL,
    deskripsi TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_p5_elemen_dimensi FOREIGN KEY (dimensi_id) REFERENCES p5_dimensi(id) ON DELETE CASCADE,
    UNIQUE KEY unique_p5_elemen_kode (dimensi_id, kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS p5_projek (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    tema VARCHAR(255) NOT NULL,
    judul VARCHAR(255) NOT NULL,
    deskripsi TEXT NULL,
    tanggal_mulai DATE NULL,
    tanggal_selesai DATE NULL,
    guru_pembimbing_id INT UNSIGNED NULL,
    lampiran_path VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_p5_projek_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_p5_projek_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_p5_projek_guru FOREIGN KEY (guru_pembimbing_id) REFERENCES guru(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS p5_projek_elemen (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    projek_id INT UNSIGNED NOT NULL,
    elemen_id INT UNSIGNED NULL,
    tp_deskripsi TEXT NULL,
    urutan INT UNSIGNED NULL,
    bobot_opsional DECIMAL(5,2) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_p5_projek_elemen_projek FOREIGN KEY (projek_id) REFERENCES p5_projek(id) ON DELETE CASCADE,
    CONSTRAINT fk_p5_projek_elemen_elemen FOREIGN KEY (elemen_id) REFERENCES p5_elemen(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS p5_penilaian_siswa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    projek_id INT UNSIGNED NOT NULL,
    projek_elemen_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    capaian_enum ENUM('BB','MB','BSH','SB') NOT NULL,
    catatan TEXT NULL,
    nilai_opsional DECIMAL(5,2) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_p5_penilaian_projek FOREIGN KEY (projek_id) REFERENCES p5_projek(id) ON DELETE CASCADE,
    CONSTRAINT fk_p5_penilaian_elemen FOREIGN KEY (projek_elemen_id) REFERENCES p5_projek_elemen(id) ON DELETE CASCADE,
    CONSTRAINT fk_p5_penilaian_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    UNIQUE KEY unique_p5_penilaian (projek_elemen_id, siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS p5_penilaian_ringkasan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    projek_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    capaian_akhir_enum ENUM('BB','MB','BSH','SB') NULL,
    deskripsi_umum TEXT NULL,
    tindak_lanjut TEXT NULL,
    nilai_opsional DECIMAL(5,2) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_p5_ringkasan_projek FOREIGN KEY (projek_id) REFERENCES p5_projek(id) ON DELETE CASCADE,
    CONSTRAINT fk_p5_ringkasan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    UNIQUE KEY unique_p5_ringkasan (projek_id, siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO p5_dimensi (kode, nama, deskripsi, created_at, updated_at) VALUES
('DIM1', 'Beriman, bertakwa kepada Tuhan YME, dan berakhlak mulia', NULL, NOW(), NOW()),
('DIM2', 'Berkebinekaan global', NULL, NOW(), NOW()),
('DIM3', 'Gotong royong', NULL, NOW(), NOW()),
('DIM4', 'Mandiri', NULL, NOW(), NOW()),
('DIM5', 'Bernalar kritis', NULL, NOW(), NOW()),
('DIM6', 'Kreatif', NULL, NOW(), NOW());

INSERT INTO p5_elemen (dimensi_id, kode, nama, fase, deskripsi, created_at, updated_at)
SELECT d.id, e.kode, e.nama, e.fase, e.deskripsi, NOW(), NOW()
FROM p5_dimensi d
JOIN (
    SELECT 'DIM1' AS dim_kode, 'EL1' AS kode, 'Akhlak kepada Tuhan YME' AS nama, 'E/F' AS fase, NULL AS deskripsi UNION ALL
    SELECT 'DIM1', 'EL2', 'Akhlak pribadi', 'E/F', NULL UNION ALL
    SELECT 'DIM1', 'EL3', 'Akhlak kepada manusia', 'E/F', NULL UNION ALL
    SELECT 'DIM1', 'EL4', 'Akhlak kepada alam', 'E/F', NULL UNION ALL
    SELECT 'DIM1', 'EL5', 'Akhlak bernegara', 'E/F', NULL UNION ALL
    SELECT 'DIM2', 'EL1', 'Mengenal dan menghargai budaya', 'E/F', NULL UNION ALL
    SELECT 'DIM2', 'EL2', 'Komunikasi dan interaksi antar budaya', 'E/F', NULL UNION ALL
    SELECT 'DIM2', 'EL3', 'Refleksi dan tanggung jawab terhadap pengalaman kebinekaan', 'E/F', NULL UNION ALL
    SELECT 'DIM3', 'EL1', 'Kolaborasi', 'E/F', NULL UNION ALL
    SELECT 'DIM3', 'EL2', 'Kepedulian', 'E/F', NULL UNION ALL
    SELECT 'DIM3', 'EL3', 'Berbagi', 'E/F', NULL UNION ALL
    SELECT 'DIM4', 'EL1', 'Pemahaman diri dan situasi', 'E/F', NULL UNION ALL
    SELECT 'DIM4', 'EL2', 'Regulasi diri', 'E/F', NULL UNION ALL
    SELECT 'DIM4', 'EL3', 'Refleksi pengembangan diri', 'E/F', NULL UNION ALL
    SELECT 'DIM5', 'EL1', 'Mengajukan pertanyaan', 'E/F', NULL UNION ALL
    SELECT 'DIM5', 'EL2', 'Mengidentifikasi dan mengolah informasi serta gagasan', 'E/F', NULL UNION ALL
    SELECT 'DIM5', 'EL3', 'Menganalisis dan mengevaluasi penalaran', 'E/F', NULL UNION ALL
    SELECT 'DIM5', 'EL4', 'Refleksi pemikiran dan proses berpikir', 'E/F', NULL UNION ALL
    SELECT 'DIM6', 'EL1', 'Menghasilkan gagasan yang orisinal', 'E/F', NULL UNION ALL
    SELECT 'DIM6', 'EL2', 'Memiliki keluwesan berpikir', 'E/F', NULL UNION ALL
    SELECT 'DIM6', 'EL3', 'Menghasilkan karya dan tindakan yang orisinal', 'E/F', NULL
) e ON e.dim_kode = d.kode;

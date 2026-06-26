CREATE TABLE IF NOT EXISTS ukk_skkni (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    jurusan_id INT UNSIGNED NOT NULL,
    kode VARCHAR(100) NOT NULL,
    judul VARCHAR(255) NOT NULL,
    deskripsi TEXT NULL,
    unit_kompetensi TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_ukk_skkni_year FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_ukk_skkni_major FOREIGN KEY (jurusan_id) REFERENCES jurusan(id) ON DELETE CASCADE,
    UNIQUE KEY unique_ukk_skkni (tahun_ajaran_id, jurusan_id, kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ukk_dudi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    jurusan_id INT UNSIGNED NOT NULL,
    nama VARCHAR(255) NOT NULL,
    penanggung_jawab VARCHAR(255) NULL,
    kontak VARCHAR(120) NULL,
    alamat TEXT NULL,
    catatan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_ukk_dudi_year FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_ukk_dudi_major FOREIGN KEY (jurusan_id) REFERENCES jurusan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ukk_asesor (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dudi_id INT UNSIGNED NOT NULL,
    nama VARCHAR(255) NOT NULL,
    jabatan VARCHAR(150) NULL,
    nomor_registrasi VARCHAR(150) NULL,
    kontak VARCHAR(120) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_ukk_asesor_dudi FOREIGN KEY (dudi_id) REFERENCES ukk_dudi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ukk_penilaian_siswa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    jurusan_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    skkni_id INT UNSIGNED NOT NULL,
    dudi_id INT UNSIGNED NOT NULL,
    asesor_id INT UNSIGNED NULL,
    nilai_teori DECIMAL(5,2) NULL,
    nilai_praktik DECIMAL(5,2) NULL,
    nilai_akhir DECIMAL(5,2) NULL,
    predikat VARCHAR(50) NULL,
    catatan TEXT NULL,
    nomor_sertifikat VARCHAR(150) NULL,
    tanggal_sertifikat DATE NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_ukk_penilaian_year FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_ukk_penilaian_class FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_ukk_penilaian_major FOREIGN KEY (jurusan_id) REFERENCES jurusan(id) ON DELETE CASCADE,
    CONSTRAINT fk_ukk_penilaian_student FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_ukk_penilaian_skkni FOREIGN KEY (skkni_id) REFERENCES ukk_skkni(id) ON DELETE CASCADE,
    CONSTRAINT fk_ukk_penilaian_dudi FOREIGN KEY (dudi_id) REFERENCES ukk_dudi(id) ON DELETE CASCADE,
    CONSTRAINT fk_ukk_penilaian_asesor FOREIGN KEY (asesor_id) REFERENCES ukk_asesor(id) ON DELETE SET NULL,
    UNIQUE KEY unique_ukk_penilaian_student_year (siswa_id, tahun_ajaran_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

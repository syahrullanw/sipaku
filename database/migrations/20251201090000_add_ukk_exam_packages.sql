CREATE TABLE IF NOT EXISTS ukk_paket_ujian (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    jurusan_id INT UNSIGNED NOT NULL,
    nama VARCHAR(150) NOT NULL,
    deskripsi TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_ukk_paket_year FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_ukk_paket_major FOREIGN KEY (jurusan_id) REFERENCES jurusan(id) ON DELETE CASCADE,
    UNIQUE KEY unique_ukk_paket (tahun_ajaran_id, jurusan_id, nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE ukk_skkni
    ADD COLUMN paket_ujian_id INT UNSIGNED NULL AFTER jurusan_id,
    ADD CONSTRAINT fk_ukk_skkni_paket FOREIGN KEY (paket_ujian_id) REFERENCES ukk_paket_ujian(id) ON DELETE RESTRICT;

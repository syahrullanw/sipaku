CREATE TABLE IF NOT EXISTS siswa_penempatan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_siswa_penempatan_tahun (siswa_id, tahun_ajaran_id),
    KEY idx_siswa_penempatan_kelas_tahun (kelas_id, tahun_ajaran_id),
    CONSTRAINT fk_siswa_penempatan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_siswa_penempatan_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_siswa_penempatan_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO siswa_penempatan (siswa_id, kelas_id, tahun_ajaran_id, created_at, updated_at)
SELECT s.id, s.kelas_id, s.tahun_ajaran_id, NOW(), NOW()
FROM siswa s
WHERE s.kelas_id IS NOT NULL
  AND s.tahun_ajaran_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    kelas_id = VALUES(kelas_id),
    updated_at = VALUES(updated_at);

INSERT INTO siswa_penempatan (siswa_id, kelas_id, tahun_ajaran_id, created_at, updated_at)
SELECT inferred.siswa_id, inferred.kelas_id, inferred.tahun_ajaran_id, NOW(), NOW()
FROM (
    SELECT siswa_id, kelas_id, tahun_ajaran_id FROM penilaian_sikap
    UNION SELECT siswa_id, kelas_id, tahun_ajaran_id FROM presensi_siswa
    UNION SELECT siswa_id, kelas_id, tahun_ajaran_id FROM catatan_walikelas
    UNION SELECT siswa_id, kelas_id, tahun_ajaran_id FROM status_naik_kelas
    UNION SELECT siswa_id, kelas_id, tahun_ajaran_id FROM status_kelulusan_siswa
    UNION SELECT siswa_id, kelas_id, tahun_ajaran_id FROM prestasi_siswa
    UNION SELECT siswa_id, kelas_id, tahun_ajaran_id FROM siswa_ekstrakurikuler
    UNION SELECT siswa_id, kelas_id, tahun_ajaran_id FROM penempatan_prakerin
    UNION
    SELECT p.siswa_id, gmpk.kelas_id, mp.tahun_ajaran_id
    FROM penilaian_pengetahuan_siswa p
    JOIN guru_mata_pelajaran gmp ON gmp.id = p.guru_mata_pelajaran_id
    JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
    JOIN (
        SELECT guru_mata_pelajaran_id
        FROM guru_mata_pelajaran_kelas
        GROUP BY guru_mata_pelajaran_id
        HAVING COUNT(*) = 1
    ) unique_gmpk ON unique_gmpk.guru_mata_pelajaran_id = gmp.id
    JOIN guru_mata_pelajaran_kelas gmpk ON gmpk.guru_mata_pelajaran_id = gmp.id
    UNION
    SELECT p.siswa_id, gmpk.kelas_id, mp.tahun_ajaran_id
    FROM penilaian_keterampilan_siswa p
    JOIN guru_mata_pelajaran gmp ON gmp.id = p.guru_mata_pelajaran_id
    JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
    JOIN (
        SELECT guru_mata_pelajaran_id
        FROM guru_mata_pelajaran_kelas
        GROUP BY guru_mata_pelajaran_id
        HAVING COUNT(*) = 1
    ) unique_gmpk ON unique_gmpk.guru_mata_pelajaran_id = gmp.id
    JOIN guru_mata_pelajaran_kelas gmpk ON gmpk.guru_mata_pelajaran_id = gmp.id
    UNION
    SELECT p.siswa_id, p.kelas_id, mp.tahun_ajaran_id
    FROM penilaian_kurmer_mapel_siswa p
    JOIN guru_mata_pelajaran gmp ON gmp.id = p.guru_mata_pelajaran_id
    JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
) inferred
WHERE inferred.siswa_id IS NOT NULL
  AND inferred.kelas_id IS NOT NULL
  AND inferred.tahun_ajaran_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated_at = VALUES(updated_at);

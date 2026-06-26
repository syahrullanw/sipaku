ALTER TABLE `presensi_siswa_sesi`
  ADD COLUMN `guru_jadwal_id` int(10) UNSIGNED NULL AFTER `guru_id`,
  ADD COLUMN `tipe_sesi` enum('jadwal','pengganti') NOT NULL DEFAULT 'jadwal' AFTER `agenda`,
  ADD COLUMN `catatan_pengganti` text NULL AFTER `tipe_sesi`;

UPDATE `presensi_siswa_sesi` sesi
JOIN `jadwal_pelajaran` jp ON jp.id = sesi.jadwal_pelajaran_id
JOIN `guru_mata_pelajaran` gmp ON gmp.id = jp.guru_mata_pelajaran_id
SET sesi.guru_jadwal_id = gmp.guru_id,
    sesi.tipe_sesi = CASE WHEN sesi.guru_id = gmp.guru_id THEN 'jadwal' ELSE 'pengganti' END
WHERE sesi.guru_jadwal_id IS NULL;

ALTER TABLE `presensi_siswa_sesi`
  ADD KEY `idx_presensi_sesi_tipe` (`tipe_sesi`, `tanggal`),
  ADD KEY `idx_presensi_sesi_guru_jadwal` (`guru_jadwal_id`, `tanggal`),
  ADD CONSTRAINT `fk_presensi_sesi_guru_jadwal` FOREIGN KEY (`guru_jadwal_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL;

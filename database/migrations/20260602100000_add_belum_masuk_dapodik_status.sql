ALTER TABLE siswa
    MODIFY status_dapodik ENUM('aktif','mutasi','pindah','residu','belum_masuk') NOT NULL DEFAULT 'aktif';

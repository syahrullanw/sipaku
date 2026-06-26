-- SIAKAD SMK schema
-- -----------------------------------------------------
-- Import file ini untuk menyiapkan struktur dasar Phase 1.
-- Pastikan menyesuaikan kredensial admin setelah import.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS tahun_ajaran (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(32) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'nonaktif',
    semester_aktif TINYINT UNSIGNED NOT NULL DEFAULT 1,
    tanggal_raport_tingkat_10_11 DATE NULL,
    tanggal_raport_tingkat_12 DATE NULL,
    tanggal_raport_tengah_semester DATE NULL,
    skl_nomor_surat VARCHAR(190) NULL,
    skl_tanggal_rapat_pleno DATE NULL,
    skl_titimangsa DATE NULL,
    transkrip_nomor_prefix VARCHAR(80) NULL,
    kepala_sekolah_id INT UNSIGNED NULL,
    digital_signature_enabled TINYINT(1) NOT NULL DEFAULT 0,
    digital_signature_enabled_at TIMESTAMP NULL DEFAULT NULL,
    digital_signature_enabled_by INT UNSIGNED NULL,
    saldo_kas_awal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    saldo_kas_akhir DECIMAL(15,2) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT ck_tahun_ajaran_semester CHECK (semester_aktif IN (1, 2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jurusan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(32) NOT NULL UNIQUE,
    nama VARCHAR(120) NOT NULL,
    deskripsi TEXT NULL,
    status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS guru (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nip VARCHAR(32) NULL,
    nomor_surat_tugas VARCHAR(120) NULL,
    tanggal_surat_tugas DATE NULL,
    sekolah_induk VARCHAR(150) NULL,
    nama VARCHAR(150) NOT NULL,
    nik VARCHAR(32) NULL,
    jenis_kelamin ENUM('L','P') NULL,
    tempat_lahir VARCHAR(100) NULL,
    tanggal_lahir DATE NULL,
    nama_ibu_kandung VARCHAR(150) NULL,
    agama VARCHAR(50) NULL,
    status_perkawinan VARCHAR(50) NULL,
    nama_pasangan VARCHAR(150) NULL,
    pekerjaan_pasangan VARCHAR(150) NULL,
    email VARCHAR(120) NULL,
    telepon VARCHAR(32) NULL,
    alamat TEXT NULL,
    npwp VARCHAR(32) NULL,
    nama_wp VARCHAR(150) NULL,
    jenis_gtk VARCHAR(100) NULL,
    nuptk VARCHAR(32) NULL,
    status_kepegawaian VARCHAR(100) NULL,
    sk_pengangkatan VARCHAR(150) NULL,
    tmt_pengangkatan DATE NULL,
    lembaga_pengangkat VARCHAR(150) NULL,
    kartu_pasangan VARCHAR(150) NULL,
    pendidikan_terakhir VARCHAR(100) NULL,
    status_kuliah VARCHAR(50) NULL,
    tahun_pensiun SMALLINT NULL,
    tugas_tambahan TEXT NULL,
    status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_guru_nip (nip),
    UNIQUE KEY unique_guru_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS data_sikap (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jenis ENUM('spiritual','sosial') NOT NULL,
    kode VARCHAR(30) NOT NULL,
    nama VARCHAR(150) NOT NULL,
    deskripsi TEXT NULL,
    status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_data_sikap_jenis_kode (jenis, kode),
    UNIQUE KEY unique_data_sikap_jenis_nama (jenis, nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mata_pelajaran (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kode VARCHAR(32) NOT NULL,
    nama VARCHAR(150) NOT NULL,
    jenis VARCHAR(50) NOT NULL,
    jurusan_id INT UNSIGNED NULL,
    deskripsi TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_mata_pelajaran_kode_per_tahun (tahun_ajaran_id, kode),
    UNIQUE KEY unique_mata_pelajaran_nama_per_tahun (tahun_ajaran_id, nama),
    CONSTRAINT fk_mata_pelajaran_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_mata_pelajaran_jurusan FOREIGN KEY (jurusan_id) REFERENCES jurusan(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS guru_mata_pelajaran (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mata_pelajaran_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    catatan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_guru_mata_pelajaran_mapel FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_guru_mata_pelajaran_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    UNIQUE KEY unique_guru_mata_pelajaran (mata_pelajaran_id, guru_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS guru_mata_pelajaran_kelas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    penilaian_mode ENUM('inherit','k13','kurmer') NOT NULL DEFAULT 'inherit',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_gmp_kelas_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_gmp_kelas_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_gmp_kelas (guru_mata_pelajaran_id, kelas_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jadwal_pelajaran (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    hari ENUM('senin','selasa','rabu','kamis','jumat','sabtu','minggu') NOT NULL,
    waktu_mulai TIME NOT NULL,
    waktu_selesai TIME NOT NULL,
    jumlah_jam TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_jadwal_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_jadwal_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_jadwal_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    INDEX idx_jadwal_tahun_hari (tahun_ajaran_id, hari),
    INDEX idx_jadwal_guru (guru_mata_pelajaran_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pengaturan_penilaian_mapel (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
    enable_keterampilan TINYINT(1) NOT NULL DEFAULT 1,
    enable_kkm TINYINT(1) NOT NULL DEFAULT 0,
    nilai_kkm DECIMAL(5,2) NULL,
    bobot_manual TINYINT(1) NOT NULL DEFAULT 0,
    bobot_kd DECIMAL(5,2) NULL,
    bobot_uts DECIMAL(5,2) NULL,
    bobot_uas DECIMAL(5,2) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_pengaturan_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pengaturan_gmp (guru_mata_pelajaran_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mata_pelajaran_kd (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    jenis ENUM('pengetahuan','keterampilan') NOT NULL,
    kode VARCHAR(50) NOT NULL,
    deskripsi TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_kd_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_kd_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_kd_per_mapel_kelas (guru_mata_pelajaran_id, kelas_id, jenis, kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kelas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    jurusan_id INT UNSIGNED NOT NULL,
    tingkat TINYINT UNSIGNED NOT NULL,
    kurikulum ENUM('k13','kurmer') NOT NULL DEFAULT 'k13',
    nama VARCHAR(60) NOT NULL,
    wali_kelas_id INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_kelas_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_kelas_jurusan FOREIGN KEY (jurusan_id) REFERENCES jurusan(id) ON DELETE CASCADE,
    CONSTRAINT fk_kelas_wali FOREIGN KEY (wali_kelas_id) REFERENCES guru(id) ON DELETE SET NULL,
    UNIQUE KEY unique_kelas_periode (tahun_ajaran_id, jurusan_id, nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS siswa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NULL,
    kelas_id INT UNSIGNED NULL,
    nama VARCHAR(150) NOT NULL,
    nipd VARCHAR(32) NOT NULL,
    jenis_kelamin ENUM('L','P') NOT NULL,
    nisn VARCHAR(32) NOT NULL,
    tempat_lahir VARCHAR(100) NOT NULL,
    tanggal_lahir DATE NOT NULL,
    nik VARCHAR(32) NOT NULL,
    agama VARCHAR(50) NULL,
    alamat TEXT NULL,
    rt VARCHAR(5) NULL,
    rw VARCHAR(5) NULL,
    dusun VARCHAR(100) NULL,
    kelurahan VARCHAR(100) NULL,
    kecamatan VARCHAR(100) NULL,
    kode_pos VARCHAR(10) NULL,
    jenis_tinggal VARCHAR(50) NULL,
    alat_transportasi VARCHAR(50) NULL,
    telepon VARCHAR(32) NULL,
    hp VARCHAR(32) NULL,
    email VARCHAR(120) NULL,
    foto_path VARCHAR(255) NULL,
    scan_ijazah_path VARCHAR(255) NULL,
    scan_rapor_path VARCHAR(255) NULL,
    scan_kartu_keluarga_path VARCHAR(255) NULL,
    scan_akta_lahir_path VARCHAR(255) NULL,
    scan_ktp_ayah_path VARCHAR(255) NULL,
    scan_ktp_ibu_path VARCHAR(255) NULL,
    skhun VARCHAR(50) NULL,
    penerima_kps TINYINT(1) NOT NULL DEFAULT 0,
    nomor_kps VARCHAR(50) NULL,
    ayah_nama VARCHAR(150) NOT NULL,
    ayah_tahun_lahir YEAR NULL,
    ayah_jenjang_pendidikan VARCHAR(50) NULL,
    ayah_pekerjaan VARCHAR(100) NULL,
    ayah_penghasilan VARCHAR(100) NULL,
    ayah_nik VARCHAR(32) NULL,
    ibu_nama VARCHAR(150) NOT NULL,
    ibu_tahun_lahir YEAR NULL,
    ibu_jenjang_pendidikan VARCHAR(50) NULL,
    ibu_pekerjaan VARCHAR(100) NULL,
    ibu_penghasilan VARCHAR(100) NULL,
    ibu_nik VARCHAR(32) NULL,
    wali_nama VARCHAR(150) NULL,
    wali_tahun_lahir YEAR NULL,
    wali_jenjang_pendidikan VARCHAR(50) NULL,
    wali_pekerjaan VARCHAR(100) NULL,
    wali_penghasilan VARCHAR(100) NULL,
    wali_nik VARCHAR(32) NULL,
    rombel_saat_ini VARCHAR(100) NULL,
    nomor_peserta_ujian VARCHAR(50) NULL,
    nomor_seri_ijazah VARCHAR(50) NULL,
    penerima_kip TINYINT(1) NOT NULL DEFAULT 0,
    nomor_kip VARCHAR(50) NULL,
    nama_di_kip VARCHAR(150) NULL,
    nomor_kks VARCHAR(50) NULL,
    nomor_registrasi_akta_lahir VARCHAR(50) NULL,
    bank VARCHAR(100) NULL,
    nomor_rekening_bank VARCHAR(50) NULL,
    rekening_atas_nama VARCHAR(150) NULL,
    layak_pip TINYINT(1) NOT NULL DEFAULT 0,
    alasan_layak_pip TEXT NULL,
    kebutuhan_khusus VARCHAR(100) NULL,
    sekolah_asal VARCHAR(150) NULL,
    anak_ke TINYINT UNSIGNED NULL,
    lintang DECIMAL(11,8) NULL,
    bujur DECIMAL(11,8) NULL,
    nomor_kk VARCHAR(32) NULL,
    berat_badan DECIMAL(5,2) NULL,
    tinggi_badan DECIMAL(5,2) NULL,
    lingkar_kepala DECIMAL(5,2) NULL,
    jumlah_saudara_kandung TINYINT UNSIGNED NULL,
    jarak_rumah_ke_sekolah_km DECIMAL(5,2) NULL,
    status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    status_dapodik ENUM('aktif','mutasi','pindah','residu','belum_masuk') NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_siswa_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE SET NULL,
    CONSTRAINT fk_siswa_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL,
    UNIQUE KEY unique_siswa_nisn (nisn),
    UNIQUE KEY unique_siswa_nipd (nipd),
    UNIQUE KEY unique_siswa_nik (nik)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS cbt_student_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT UNSIGNED NOT NULL,
    username VARCHAR(100) NULL,
    password VARCHAR(100) NULL,
    exam_room VARCHAR(100) NULL,
    exam_session VARCHAR(100) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_cbt_student (siswa_id),
    CONSTRAINT fk_cbt_profile_student FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS penilaian_kd_siswa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
    kd_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    nilai DECIMAL(5,2) NULL,
    deskripsi TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_penilaian_kd_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_kd_kd FOREIGN KEY (kd_id) REFERENCES mata_pelajaran_kd(id) ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_kd_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    UNIQUE KEY unique_penilaian_kd_siswa (kd_id, siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS penilaian_pengetahuan_siswa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    nilai_kd DECIMAL(5,2) NULL,
    nilai_uts DECIMAL(5,2) NULL,
    nilai_uas DECIMAL(5,2) NULL,
    nilai_akhir DECIMAL(5,2) NULL,
    predikat VARCHAR(50) NULL,
    deskripsi TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_pengetahuan_siswa (guru_mata_pelajaran_id, siswa_id),
    CONSTRAINT fk_pengetahuan_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_pengetahuan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS penilaian_keterampilan_siswa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    nilai_akhir DECIMAL(5,2) NULL,
    predikat VARCHAR(50) NULL,
    deskripsi TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_keterampilan_siswa (guru_mata_pelajaran_id, siswa_id),
    CONSTRAINT fk_keterampilan_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_keterampilan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS ukk_skkni (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    jurusan_id INT UNSIGNED NOT NULL,
    paket_ujian_id INT UNSIGNED NULL,
    kode VARCHAR(100) NOT NULL,
    judul VARCHAR(255) NOT NULL,
    deskripsi TEXT NULL,
    unit_kompetensi TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_ukk_skkni_year FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_ukk_skkni_major FOREIGN KEY (jurusan_id) REFERENCES jurusan(id) ON DELETE CASCADE,
    CONSTRAINT fk_ukk_skkni_paket FOREIGN KEY (paket_ujian_id) REFERENCES ukk_paket_ujian(id) ON DELETE RESTRICT,
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
    internal_assessor_teacher_id INT UNSIGNED NULL,
    internal_assessor_name VARCHAR(255) NULL,
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
    CONSTRAINT fk_ukk_penilaian_internal_teacher FOREIGN KEY (internal_assessor_teacher_id) REFERENCES guru(id) ON DELETE SET NULL,
    UNIQUE KEY unique_ukk_penilaian_student_year (siswa_id, tahun_ajaran_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(120) NULL,
    role ENUM('admin','staff','guru','siswa','bendahara','kepala_sekolah') NOT NULL,
    teacher_id INT UNSIGNED NULL,
    student_id INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_users_email (email),
    UNIQUE KEY unique_users_teacher (teacher_id),
    UNIQUE KEY unique_users_student (student_id),
    CONSTRAINT fk_users_teacher FOREIGN KEY (teacher_id) REFERENCES guru(id) ON DELETE CASCADE,
    CONSTRAINT fk_users_student FOREIGN KEY (student_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT ck_users_role_assignment CHECK (
        (
            role IN ('admin','staff')
            AND teacher_id IS NULL
            AND student_id IS NULL
        ) OR (
            role IN ('guru','bendahara','kepala_sekolah')
            AND teacher_id IS NOT NULL
            AND student_id IS NULL
        ) OR (
            role = 'siswa'
            AND student_id IS NOT NULL
            AND teacher_id IS NULL
        )
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    actor_name VARCHAR(150) NULL,
    actor_username VARCHAR(80) NULL,
    actor_role VARCHAR(30) NULL,
    request_method VARCHAR(10) NOT NULL,
    request_path VARCHAR(255) NOT NULL,
    route_action VARCHAR(255) NULL,
    action_description VARCHAR(255) NULL,
    status_code SMALLINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload TEXT NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_activity_user (user_id),
    INDEX idx_user_activity_path (request_path),
    INDEX idx_user_activity_created_at (created_at),
    CONSTRAINT fk_user_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_module_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_code VARCHAR(50) NOT NULL,
    module_key VARCHAR(100) NOT NULL,
    is_allowed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_user_module_rule (role_code, module_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS digital_document_signatures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    document_type VARCHAR(64) NOT NULL,
    document_key VARCHAR(120) NOT NULL,
    document_title VARCHAR(150) NOT NULL,
    student_id INT UNSIGNED NULL,
    class_id INT UNSIGNED NULL,
    status ENUM('pending','approved','revoked') NOT NULL DEFAULT 'pending',
    payload TEXT NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    signature_token CHAR(64) NULL UNIQUE,
    requested_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    approval_note TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_document_per_year (tahun_ajaran_id, document_type, document_key),
    KEY idx_digital_signatures_status (status),
    KEY idx_digital_signatures_student (student_id),
    CONSTRAINT fk_digital_signatures_year FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_digital_signatures_student FOREIGN KEY (student_id) REFERENCES siswa(id) ON DELETE SET NULL,
    CONSTRAINT fk_digital_signatures_class FOREIGN KEY (class_id) REFERENCES kelas(id) ON DELETE SET NULL,
    CONSTRAINT fk_digital_signatures_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_digital_signatures_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whatsapp_gateway_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template VARCHAR(40) NOT NULL DEFAULT 'custom',
    base_url TEXT NOT NULL,
    authorization_token TEXT NULL,
    body_type ENUM('json','form-data','x-www-form-urlencoded') NOT NULL DEFAULT 'json',
    default_parameter_key VARCHAR(120) NOT NULL,
    default_parameter_value TEXT NOT NULL,
    default_message_key VARCHAR(120) NOT NULL,
    default_message_value TEXT NOT NULL,
    extra_parameter_one_key VARCHAR(120) NULL,
    extra_parameter_one_value TEXT NULL,
    extra_parameter_two_key VARCHAR(120) NULL,
    extra_parameter_two_value TEXT NULL,
    send_interval_seconds INT UNSIGNED NOT NULL DEFAULT 30,
    qr_scan_url TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT ck_whatsapp_template CHECK (template IN ('default','custom','fonnte','waha')),
    CONSTRAINT ck_whatsapp_body_type CHECK (body_type IN ('json','form-data','x-www-form-urlencoded')),
    CONSTRAINT ck_whatsapp_send_interval CHECK (send_interval_seconds BETWEEN 5 AND 86400)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whatsapp_message_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(32) NOT NULL,
    message TEXT NOT NULL,
    payload TEXT NOT NULL,
    message_hash CHAR(64) NOT NULL,
    status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_attempt_at TIMESTAMP NULL DEFAULT NULL,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    response_status INT NULL DEFAULT NULL,
    last_response TEXT NULL,
    last_error TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_whatsapp_queue_status_available (status, available_at),
    KEY idx_whatsapp_queue_hash (message_hash),
    KEY idx_whatsapp_queue_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS penilaian_sikap (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    jenis ENUM('spiritual','sosial') NOT NULL,
    data_sikap_selalu_1_id INT UNSIGNED NULL,
    data_sikap_selalu_2_id INT UNSIGNED NULL,
    data_sikap_meningkat_id INT UNSIGNED NULL,
    catatan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_penilaian_sikap_per_siswa (tahun_ajaran_id, kelas_id, siswa_id, jenis),
    KEY idx_penilaian_sikap_selalu_1 (data_sikap_selalu_1_id),
    KEY idx_penilaian_sikap_selalu_2 (data_sikap_selalu_2_id),
    KEY idx_penilaian_sikap_meningkat (data_sikap_meningkat_id),
    CONSTRAINT fk_penilaian_sikap_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_sikap_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_sikap_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_sikap_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_sikap_selalu_1 FOREIGN KEY (data_sikap_selalu_1_id) REFERENCES data_sikap(id) ON DELETE SET NULL,
    CONSTRAINT fk_penilaian_sikap_selalu_2 FOREIGN KEY (data_sikap_selalu_2_id) REFERENCES data_sikap(id) ON DELETE SET NULL,
    CONSTRAINT fk_penilaian_sikap_meningkat FOREIGN KEY (data_sikap_meningkat_id) REFERENCES data_sikap(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS presensi_siswa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    sakit SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    izin SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bolos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    alpa SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_presensi_siswa (tahun_ajaran_id, kelas_id, siswa_id),
    KEY idx_presensi_siswa_kelas (kelas_id),
    KEY idx_presensi_siswa_guru (guru_id),
    CONSTRAINT fk_presensi_siswa_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_presensi_siswa_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_presensi_siswa_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_presensi_siswa_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS presensi_siswa_sesi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    jadwal_pelajaran_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    guru_jadwal_id INT UNSIGNED NULL,
    kelas_id INT UNSIGNED NOT NULL,
    kelas_paralel_id INT UNSIGNED NULL,
    mata_pelajaran_id INT UNSIGNED NOT NULL,
    tanggal DATE NOT NULL,
    agenda TEXT NOT NULL,
    tipe_sesi ENUM('jadwal','pengganti') NOT NULL DEFAULT 'jadwal',
    catatan_pengganti TEXT NULL,
    token VARCHAR(64) NOT NULL,
    durasi_menit SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    valid_dari TIMESTAMP NOT NULL,
    valid_sampai TIMESTAMP NOT NULL,
    status ENUM('aktif','ditutup') NOT NULL DEFAULT 'aktif',
    ditutup_pada TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_presensi_sesi_token (token),
    KEY idx_presensi_sesi_tanggal (tanggal),
    KEY idx_presensi_sesi_kelas (kelas_id, tanggal),
    KEY idx_presensi_sesi_kelas_paralel (kelas_paralel_id, tanggal),
    KEY idx_presensi_sesi_mapel (mata_pelajaran_id, tanggal),
    KEY idx_presensi_sesi_guru (guru_id, tanggal),
    KEY idx_presensi_sesi_tipe (tipe_sesi, tanggal),
    KEY idx_presensi_sesi_guru_jadwal (guru_jadwal_id, tanggal),
    CONSTRAINT fk_presensi_sesi_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_presensi_sesi_jadwal FOREIGN KEY (jadwal_pelajaran_id) REFERENCES jadwal_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_presensi_sesi_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    CONSTRAINT fk_presensi_sesi_guru_jadwal FOREIGN KEY (guru_jadwal_id) REFERENCES guru(id) ON DELETE SET NULL,
    CONSTRAINT fk_presensi_sesi_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_presensi_sesi_kelas_paralel FOREIGN KEY (kelas_paralel_id) REFERENCES kelas(id) ON DELETE SET NULL,
    CONSTRAINT fk_presensi_sesi_mapel FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS presensi_siswa_sesi_detail (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sesi_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    status ENUM('hadir','sakit','izin','bolos','alpa') NOT NULL DEFAULT 'hadir',
    metode ENUM('qr','manual') NOT NULL DEFAULT 'qr',
    catatan VARCHAR(255) NULL,
    presensi_pada TIMESTAMP NOT NULL,
    dicatat_oleh_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_presensi_sesi_detail (sesi_id, siswa_id),
    KEY idx_presensi_sesi_detail_sesi (sesi_id),
    KEY idx_presensi_sesi_detail_siswa (siswa_id),
    KEY idx_presensi_sesi_detail_status (status),
    CONSTRAINT fk_presensi_sesi_detail_sesi FOREIGN KEY (sesi_id) REFERENCES presensi_siswa_sesi(id) ON DELETE CASCADE,
    CONSTRAINT fk_presensi_sesi_detail_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_presensi_sesi_detail_user FOREIGN KEY (dicatat_oleh_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tempat_prakerin (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    deskripsi TEXT NULL,
    pembina_guru_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_tempat_prakerin_nama (nama),
    KEY idx_tempat_prakerin_pembina (pembina_guru_id),
    CONSTRAINT fk_tempat_prakerin_pembina FOREIGN KEY (pembina_guru_id) REFERENCES guru(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS penempatan_prakerin (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    tempat_prakerin_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_penempatan_prakerin (tahun_ajaran_id, siswa_id),
    KEY idx_penempatan_prakerin_kelas (kelas_id),
    KEY idx_penempatan_prakerin_tempat (tempat_prakerin_id),
    KEY idx_penempatan_prakerin_guru (guru_id),
    CONSTRAINT fk_penempatan_prakerin_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_penempatan_prakerin_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_penempatan_prakerin_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_penempatan_prakerin_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    CONSTRAINT fk_penempatan_prakerin_tempat FOREIGN KEY (tempat_prakerin_id) REFERENCES tempat_prakerin(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS homeroom_prakerin_confirmations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    prakerin_required TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_homeroom_prakerin_confirmation (guru_id, kelas_id),
    KEY idx_homeroom_prakerin_kelas (kelas_id),
    KEY idx_homeroom_prakerin_guru (guru_id),
    CONSTRAINT fk_homeroom_prakerin_confirmation_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    CONSTRAINT fk_homeroom_prakerin_confirmation_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS penilaian_prakerin (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    tempat_prakerin_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    nilai_keaktifan DECIMAL(5,2) NOT NULL DEFAULT 0,
    nilai_jurnal DECIMAL(5,2) NOT NULL DEFAULT 0,
    nilai_laporan DECIMAL(5,2) NOT NULL DEFAULT 0,
    nilai_akhir DECIMAL(5,2) NOT NULL DEFAULT 0,
    predikat ENUM('Amat Baik','Baik','Kurang') NOT NULL DEFAULT 'Kurang',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_penilaian_prakerin (tahun_ajaran_id, siswa_id),
    KEY idx_penilaian_prakerin_tempat (tempat_prakerin_id),
    KEY idx_penilaian_prakerin_guru (guru_id),
    CONSTRAINT fk_penilaian_prakerin_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_prakerin_tempat FOREIGN KEY (tempat_prakerin_id) REFERENCES tempat_prakerin(id) ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_prakerin_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_prakerin_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ekstrakurikuler (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    nama VARCHAR(150) NOT NULL,
    deskripsi TEXT NULL,
    pembina_guru_id INT UNSIGNED NOT NULL,
    jadwal VARCHAR(120) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_ekstrakurikuler_per_tahun (tahun_ajaran_id, nama),
    KEY idx_ekstrakurikuler_pembina (pembina_guru_id),
    KEY idx_ekstrakurikuler_tahun (tahun_ajaran_id),
    CONSTRAINT fk_ekstrakurikuler_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_ekstrakurikuler_pembina FOREIGN KEY (pembina_guru_id) REFERENCES guru(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS siswa_ekstrakurikuler (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    ekstrakurikuler_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    nilai_keaktifan DECIMAL(5,2) NULL,
    nilai_kemampuan_teknis DECIMAL(5,2) NULL,
    nilai_kehadiran DECIMAL(5,2) NULL,
    nilai_akhir DECIMAL(5,2) NULL,
    predikat VARCHAR(20) NULL,
    deskripsi TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_siswa_ekskul_per_tahun (tahun_ajaran_id, siswa_id, ekstrakurikuler_id),
    KEY idx_siswa_ekskul_kelas (kelas_id),
    KEY idx_siswa_ekskul_ekstrakurikuler (ekstrakurikuler_id),
    KEY idx_siswa_ekskul_guru (guru_id),
    CONSTRAINT fk_siswa_ekskul_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_siswa_ekskul_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_siswa_ekskul_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_siswa_ekskul_ekstrakurikuler FOREIGN KEY (ekstrakurikuler_id) REFERENCES ekstrakurikuler(id) ON DELETE CASCADE,
    CONSTRAINT fk_siswa_ekskul_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prestasi_siswa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    jenis VARCHAR(150) NOT NULL,
    keterangan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_prestasi_siswa_tahun (tahun_ajaran_id),
    KEY idx_prestasi_siswa_kelas (kelas_id),
    KEY idx_prestasi_siswa_siswa (siswa_id),
    KEY idx_prestasi_siswa_guru (guru_id),
    CONSTRAINT fk_prestasi_siswa_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_prestasi_siswa_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_prestasi_siswa_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_prestasi_siswa_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS status_naik_kelas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    status ENUM('naik','tinggal') NOT NULL,
    catatan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_status_naik (tahun_ajaran_id, kelas_id, siswa_id),
    KEY idx_status_naik_guru (guru_id),
    CONSTRAINT fk_status_naik_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_status_naik_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_status_naik_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_status_naik_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS status_kelulusan_siswa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    status ENUM('lulus','tidak_lulus') NOT NULL,
    catatan TEXT NULL,
    nomor_ijazah VARCHAR(100) NULL,
    jenis_kekhususan VARCHAR(100) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_status_kelulusan (tahun_ajaran_id, kelas_id, siswa_id),
    KEY idx_status_kelulusan_guru (guru_id),
    CONSTRAINT fk_status_kelulusan_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_status_kelulusan_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_status_kelulusan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_status_kelulusan_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS catatan_walikelas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    catatan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_catatan_walikelas (tahun_ajaran_id, kelas_id, siswa_id),
    KEY idx_catatan_walikelas_guru (guru_id),
    CONSTRAINT fk_catatan_walikelas_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_catatan_walikelas_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_catatan_walikelas_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_catatan_walikelas_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jabatan_akademik (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    deskripsi TEXT NULL,
    level TINYINT UNSIGNED NULL,
    kategori ENUM('guru','siswa') NOT NULL DEFAULT 'guru',
    assigns_user_role ENUM('bendahara','kepala_sekolah','tata_usaha','waka_kurikulum','kepala_prodi') NULL,
    requires_major TINYINT(1) NOT NULL DEFAULT 0,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_jabatan_akademik_nama (kategori, nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS guru_jabatan_akademik (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    jabatan_akademik_id INT UNSIGNED NOT NULL,
    jurusan_id INT UNSIGNED NULL,
    tanggal_mulai DATE NULL,
    tanggal_selesai DATE NULL,
    catatan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_guru_jabatan_akademik_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_guru_jabatan_akademik_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    CONSTRAINT fk_guru_jabatan_akademik_jabatan FOREIGN KEY (jabatan_akademik_id) REFERENCES jabatan_akademik(id) ON DELETE CASCADE,
    CONSTRAINT fk_guru_jabatan_akademik_jurusan FOREIGN KEY (jurusan_id) REFERENCES jurusan(id) ON DELETE SET NULL,
    UNIQUE KEY unique_guru_jabatan_per_tahun (tahun_ajaran_id, guru_id, jabatan_akademik_id, jurusan_id),
    KEY idx_guru_jabatan_akademik_jabatan (jabatan_akademik_id),
    KEY idx_guru_jabatan_akademik_jurusan (jurusan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS siswa_jabatan_akademik (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    jabatan_akademik_id INT UNSIGNED NOT NULL,
    tanggal_mulai DATE NULL,
    tanggal_selesai DATE NULL,
    catatan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_siswa_jabatan_akademik_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_siswa_jabatan_akademik_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_siswa_jabatan_akademik_jabatan FOREIGN KEY (jabatan_akademik_id) REFERENCES jabatan_akademik(id) ON DELETE CASCADE,
    UNIQUE KEY unique_siswa_jabatan_per_tahun (tahun_ajaran_id, siswa_id, jabatan_akademik_id),
    KEY idx_siswa_jabatan_akademik_jabatan (jabatan_akademik_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sekolah (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    npsn VARCHAR(20) NULL,
    nss VARCHAR(20) NULL,
    alamat TEXT NULL,
    desa VARCHAR(120) NULL,
    kecamatan VARCHAR(120) NULL,
    kabupaten VARCHAR(120) NULL,
    provinsi VARCHAR(120) NULL,
    kode_pos VARCHAR(10) NULL,
    telepon VARCHAR(32) NULL,
    email VARCHAR(120) NULL,
    website VARCHAR(120) NULL,
    akreditasi VARCHAR(5) NULL,
    logo_sekolah VARCHAR(255) NULL,
    logo_dinas VARCHAR(255) NULL,
    lambang_negara VARCHAR(255) NULL,
    app_icon VARCHAR(255) NULL,
    kop_surat VARCHAR(255) NULL,
    transkrip_nomor_prefix VARCHAR(30) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    presensi_radius_meter SMALLINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_sekolah_npsn (npsn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS surat_keluar (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kode_jenis VARCHAR(10) NOT NULL,
    jenis VARCHAR(150) NOT NULL,
    nomor_urut INT UNSIGNED NOT NULL,
    nomor_surat VARCHAR(190) NOT NULL,
    unit_kode VARCHAR(80) NOT NULL,
    tujuan VARCHAR(190) NULL,
    perihal VARCHAR(190) NOT NULL,
    lampiran VARCHAR(150) NULL,
    tembusan TEXT NULL,
    tanggal_surat DATE NOT NULL,
    tanggal_dicatat DATE NOT NULL,
    isi TEXT NULL,
    pdf_path VARCHAR(190) NULL,
    pdf_signature_options TEXT NULL,
    pdf_signed_path VARCHAR(190) NULL,
    catatan TEXT NULL,
    tanda_tangan VARCHAR(150) NULL,
    dibuat_oleh INT UNSIGNED NULL,
    diperbarui_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_surat_keluar_nomor (nomor_surat),
    UNIQUE KEY unique_surat_keluar_tahun_nomor_urut (tahun_ajaran_id, nomor_urut),
    CONSTRAINT fk_surat_keluar_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_surat_keluar_dibuat FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_surat_keluar_diperbarui FOREIGN KEY (diperbarui_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS surat_keluar_lampiran (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    surat_keluar_id INT UNSIGNED NOT NULL,
    nomor TINYINT UNSIGNED NOT NULL,
    isi_html TEXT NOT NULL,
    isi_text TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_surat_keluar_lampiran_surat FOREIGN KEY (surat_keluar_id) REFERENCES surat_keluar(id) ON DELETE CASCADE,
    UNIQUE KEY unique_surat_keluar_lampiran_nomor (surat_keluar_id, nomor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS surat_masuk (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    nomor_agenda INT UNSIGNED NOT NULL,
    nomor_surat VARCHAR(190) NOT NULL,
    asal_surat VARCHAR(190) NOT NULL,
    penerima VARCHAR(190) NULL,
    perihal VARCHAR(190) NOT NULL,
    lampiran VARCHAR(150) NULL,
    tanggal_surat DATE NULL,
    tanggal_diterima DATE NOT NULL,
    catatan TEXT NULL,
    diterima_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_surat_masuk_agenda (tahun_ajaran_id, nomor_agenda),
    CONSTRAINT fk_surat_masuk_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_surat_masuk_diterima FOREIGN KEY (diterima_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kategori_tagihan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(50) NOT NULL,
    nama VARCHAR(120) NOT NULL,
    tipe ENUM('rutin','insidental') NOT NULL DEFAULT 'rutin',
    status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    urutan TINYINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    UNIQUE KEY unique_kategori_tagihan_kode (kode),
    UNIQUE KEY unique_kategori_tagihan_nama (nama),
    CONSTRAINT fk_kategori_tagihan_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_kategori_tagihan_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tagihan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(60) NOT NULL,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kategori_id INT UNSIGNED NOT NULL,
    judul VARCHAR(180) NOT NULL,
    deskripsi TEXT NULL,
    whatsapp_message_template TEXT NULL,
    nominal_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    metode_penagihan ENUM('per_siswa','per_kelas','per_jurusan') NOT NULL DEFAULT 'per_siswa',
    tanggal_jatuh_tempo DATE NULL,
    rutin_tipe ENUM('tidak','mingguan','bulanan') NOT NULL DEFAULT 'tidak',
    rutin_jadwal_berikutnya DATE NULL,
    rutin_terakhir_generate DATE NULL,
    rutin_hari_mingguan TINYINT UNSIGNED NULL,
    rutin_tanggal_bulanan TINYINT UNSIGNED NULL,
    status ENUM('draft','aktif','ditutup','dibatalkan') NOT NULL DEFAULT 'draft',
    catatan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    UNIQUE KEY unique_tagihan_kode (kode),
    KEY idx_tagihan_tahun (tahun_ajaran_id),
    KEY idx_tagihan_status (status),
    CONSTRAINT fk_tagihan_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_tagihan_kategori FOREIGN KEY (kategori_id) REFERENCES kategori_tagihan(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tagihan_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_tagihan_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tagihan_kas (
    tagihan_id INT UNSIGNED PRIMARY KEY,
    saldo_masuk DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    saldo_keluar DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    saldo_akhir DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_tagihan_kas_tagihan FOREIGN KEY (tagihan_id) REFERENCES tagihan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tagihan_item (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tagihan_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NULL,
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    nominal_periode DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    sisa_nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('menunggu_pembayaran','menunggu_verifikasi','cicilan_berjalan','lunas','gagal','dibatalkan') NOT NULL DEFAULT 'menunggu_pembayaran',
    catatan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_tagihan_item (tagihan_id, siswa_id),
    KEY idx_tagihan_item_status (status),
    CONSTRAINT fk_tagihan_item_tagihan FOREIGN KEY (tagihan_id) REFERENCES tagihan(id) ON DELETE CASCADE,
    CONSTRAINT fk_tagihan_item_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_tagihan_item_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tagihan_cicilan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tagihan_item_id INT UNSIGNED NOT NULL,
    jatuh_tempo DATE NOT NULL,
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    nominal_terbayar DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('menunggu','terbayar','tertunggak','dibatalkan') NOT NULL DEFAULT 'menunggu',
    tanggal_terbayar DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_tagihan_cicilan_item (tagihan_item_id),
    KEY idx_tagihan_cicilan_status (status),
    CONSTRAINT fk_tagihan_cicilan_item FOREIGN KEY (tagihan_item_id) REFERENCES tagihan_item(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pembayaran (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tagihan_item_id INT UNSIGNED NOT NULL,
    kode_transaksi VARCHAR(80) NOT NULL,
    tanggal_bayar DATETIME NOT NULL,
    metode ENUM('tunai','transfer','tabungan') NOT NULL DEFAULT 'tunai',
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    sisa_setelah DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('menunggu_verifikasi','disetujui','ditolak') NOT NULL DEFAULT 'menunggu_verifikasi',
    bukti_path VARCHAR(255) NULL,
    catatan TEXT NULL,
    diverifikasi_oleh INT UNSIGNED NULL,
    diverifikasi_pada DATETIME NULL,
    tabungan_transaksi_id INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    UNIQUE KEY unique_pembayaran_kode (kode_transaksi),
    KEY idx_pembayaran_status (status),
    KEY idx_pembayaran_tagihan_item (tagihan_item_id),
    KEY idx_pembayaran_tabungan (tabungan_transaksi_id),
    CONSTRAINT fk_pembayaran_tagihan_item FOREIGN KEY (tagihan_item_id) REFERENCES tagihan_item(id) ON DELETE CASCADE,
    CONSTRAINT fk_pembayaran_diverifikasi FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pembayaran_tabungan FOREIGN KEY (tabungan_transaksi_id) REFERENCES tabungan_transaksi(id) ON DELETE SET NULL,
    CONSTRAINT fk_pembayaran_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pembayaran_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tabungan_siswa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT UNSIGNED NOT NULL,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    saldo_terakhir DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    catatan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    UNIQUE KEY unique_tabungan_siswa_tahun (siswa_id, tahun_ajaran_id),
    CONSTRAINT fk_tabungan_siswa_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_tabungan_siswa_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_tabungan_siswa_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_tabungan_siswa_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tabungan_transaksi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tabungan_id INT UNSIGNED NOT NULL,
    kode_transaksi VARCHAR(80) NOT NULL,
    jenis ENUM('setor','tarik') NOT NULL,
    tanggal DATETIME NOT NULL,
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    saldo_setelah DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    bukti_path VARCHAR(255) NULL,
    catatan TEXT NULL,
    dicatat_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_tabungan_transaksi_kode (kode_transaksi),
    KEY idx_tabungan_transaksi_tabungan (tabungan_id, tanggal),
    CONSTRAINT fk_tabungan_transaksi_tabungan FOREIGN KEY (tabungan_id) REFERENCES tabungan_siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_tabungan_transaksi_dicatat_oleh FOREIGN KEY (dicatat_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pembelian_perlengkapan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(60) NOT NULL,
    tagihan_id INT UNSIGNED NULL,
    siswa_id INT UNSIGNED NOT NULL,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    item_label VARCHAR(180) NOT NULL,
    jenis ENUM('atribut','seragam','lain') NOT NULL DEFAULT 'lain',
    metode_pembayaran ENUM('cash','tabungan','sekolah') NOT NULL DEFAULT 'cash',
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    nominal_terbayar DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    sisa_nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('menunggu_pembayaran','cicilan_berjalan','lunas','dibatalkan') NOT NULL DEFAULT 'menunggu_pembayaran',
    catatan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    UNIQUE KEY unique_pembelian_tagihan (tagihan_id),
    UNIQUE KEY unique_pembelian_kode (kode),
    KEY idx_pembelian_status (status),
    KEY idx_pembelian_siswa (siswa_id),
    KEY idx_pembelian_tahun (tahun_ajaran_id),
    CONSTRAINT fk_pembelian_tagihan FOREIGN KEY (tagihan_id) REFERENCES tagihan(id) ON DELETE CASCADE,
    CONSTRAINT fk_pembelian_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_pembelian_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_pembelian_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pembelian_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pembelian_kas (
    pembelian_id INT UNSIGNED PRIMARY KEY,
    saldo_masuk DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    saldo_keluar DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    saldo_akhir DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_pembelian_kas_pembelian FOREIGN KEY (pembelian_id) REFERENCES pembelian_perlengkapan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pembelian_pembayaran (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pembelian_id INT UNSIGNED NOT NULL,
    kode_transaksi VARCHAR(80) NOT NULL,
    tanggal_bayar DATETIME NOT NULL,
    metode ENUM('cash','tabungan','sekolah') NOT NULL,
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    sisa_setelah DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    catatan TEXT NULL,
    tabungan_transaksi_id INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    UNIQUE KEY unique_pembelian_payment_kode (kode_transaksi),
    KEY idx_pembelian_payment_purchase (pembelian_id),
    CONSTRAINT fk_pembelian_payment_purchase FOREIGN KEY (pembelian_id) REFERENCES pembelian_perlengkapan(id) ON DELETE CASCADE,
    CONSTRAINT fk_pembelian_payment_savings FOREIGN KEY (tabungan_transaksi_id) REFERENCES tabungan_transaksi(id) ON DELETE SET NULL,
    CONSTRAINT fk_pembelian_payment_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pembelian_payment_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kasbon_guru (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_id INT UNSIGNED NOT NULL,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kode VARCHAR(60) NOT NULL,
    tanggal_pengajuan DATE NOT NULL,
    nominal_diminta DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tujuan TEXT NULL,
    tenor_bulan TINYINT UNSIGNED NULL,
    status ENUM('draft','diajukan','diverifikasi_bendahara','menunggu_acc','disetujui','ditolak','lunas') NOT NULL DEFAULT 'draft',
    tanggal_acc DATETIME NULL,
    tanggal_cair DATETIME NULL,
    saldo_terhutang DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    catatan_penolakan TEXT NULL,
    catatan TEXT NULL,
    diverifikasi_oleh INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    UNIQUE KEY unique_kasbon_kode (kode),
    KEY idx_kasbon_status (status),
    CONSTRAINT fk_kasbon_guru_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    CONSTRAINT fk_kasbon_guru_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_kasbon_guru_diverifikasi FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_kasbon_guru_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_kasbon_guru_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_kasbon_guru_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kasbon_cicilan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kasbon_id INT UNSIGNED NOT NULL,
    jatuh_tempo DATE NOT NULL,
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    nominal_terbayar DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('menunggu','terbayar','tertunggak','dibatalkan') NOT NULL DEFAULT 'menunggu',
    tanggal_bayar_terakhir DATETIME NULL,
    dicatat_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_kasbon_cicilan_kasbon (kasbon_id),
    KEY idx_kasbon_cicilan_status (status),
    CONSTRAINT fk_kasbon_cicilan_kasbon FOREIGN KEY (kasbon_id) REFERENCES kasbon_guru(id) ON DELETE CASCADE,
    CONSTRAINT fk_kasbon_cicilan_dicatat_oleh FOREIGN KEY (dicatat_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pengeluaran_tak_terduga (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kode_transaksi VARCHAR(80) NOT NULL,
    tipe_pemohon ENUM('guru','siswa','lainnya') NOT NULL DEFAULT 'guru',
    guru_id INT UNSIGNED NULL,
    siswa_id INT UNSIGNED NULL,
    pemohon_nama VARCHAR(180) NOT NULL,
    deskripsi TEXT NULL,
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tanggal DATETIME NOT NULL,
    dicatat_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_pengeluaran_tak_terduga_kode (kode_transaksi),
    KEY idx_pengeluaran_tak_terduga_tahun (tahun_ajaran_id, tanggal),
    KEY idx_pengeluaran_tak_terduga_guru (guru_id),
    KEY idx_pengeluaran_tak_terduga_siswa (siswa_id),
    CONSTRAINT fk_pengeluaran_tak_terduga_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_pengeluaran_tak_terduga_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE SET NULL,
    CONSTRAINT fk_pengeluaran_tak_terduga_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE SET NULL,
    CONSTRAINT fk_pengeluaran_tak_terduga_dicatat FOREIGN KEY (dicatat_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pengadaan_praktikum (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(30) NOT NULL,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    jurusan_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    judul VARCHAR(150) NOT NULL,
    tujuan TEXT NULL,
    rincian_kebutuhan TEXT NULL,
    total_estimasi DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('draft','submitted','approved','rejected','funded','reported') NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    reviewed_by_user_id INT UNSIGNED NULL,
    review_note TEXT NULL,
    funded_at DATETIME NULL,
    funded_by_user_id INT UNSIGNED NULL,
    funding_note TEXT NULL,
    lpj_deskripsi TEXT NULL,
    lpj_lampiran VARCHAR(255) NULL,
    lpj_submitted_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_pengadaan_praktikum_kode (kode),
    KEY idx_pengadaan_praktikum_status (status),
    KEY idx_pengadaan_praktikum_tahun (tahun_ajaran_id),
    KEY idx_pengadaan_praktikum_jurusan (jurusan_id),
    CONSTRAINT fk_pengadaan_praktikum_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_pengadaan_praktikum_jurusan FOREIGN KEY (jurusan_id) REFERENCES jurusan(id) ON DELETE CASCADE,
    CONSTRAINT fk_pengadaan_praktikum_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    CONSTRAINT fk_pengadaan_praktikum_review FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pengadaan_praktikum_funded FOREIGN KEY (funded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lpj_keuangan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('dana_kegiatan','pengeluaran_tak_terduga') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    judul VARCHAR(180) NOT NULL,
    deskripsi TEXT NULL,
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tanggal DATETIME NOT NULL,
    bukti_path VARCHAR(255) NULL,
    dibuat_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_lpj_entity (entity_type, entity_id),
    CONSTRAINT fk_lpj_keuangan_user FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dana_kegiatan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_id INT UNSIGNED NOT NULL,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kode VARCHAR(60) NOT NULL,
    kategori VARCHAR(100) NOT NULL,
    judul VARCHAR(180) NOT NULL,
    deskripsi TEXT NULL,
    estimasi_biaya DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','diajukan','diverifikasi_bendahara','menunggu_acc','disetujui','ditolak','selesai') NOT NULL DEFAULT 'draft',
    tanggal_pengajuan DATETIME NOT NULL,
    tanggal_acc DATETIME NULL,
    catatan TEXT NULL,
    lampiran_path VARCHAR(255) NULL,
    diverifikasi_oleh INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    UNIQUE KEY unique_dana_kegiatan_kode (kode),
    KEY idx_dana_kegiatan_status (status),
    CONSTRAINT fk_dana_kegiatan_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    CONSTRAINT fk_dana_kegiatan_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_dana_kegiatan_diverifikasi FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_dana_kegiatan_approved FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_dana_kegiatan_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_dana_kegiatan_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dana_kegiatan_realisasi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dana_kegiatan_id INT UNSIGNED NOT NULL,
    kode_transaksi VARCHAR(80) NOT NULL,
    tanggal DATETIME NOT NULL,
    jenis_pengeluaran VARCHAR(120) NOT NULL,
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    bukti_path VARCHAR(255) NULL,
    catatan TEXT NULL,
    dicatat_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_dana_kegiatan_realisasi_kode (kode_transaksi),
    KEY idx_dana_kegiatan_realisasi_dana (dana_kegiatan_id),
    CONSTRAINT fk_dana_kegiatan_realisasi_dana FOREIGN KEY (dana_kegiatan_id) REFERENCES dana_kegiatan(id) ON DELETE CASCADE,
    CONSTRAINT fk_dana_kegiatan_realisasi_dicatat FOREIGN KEY (dicatat_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teacher_salary_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    category ENUM('hourly_rate','special_role','academic_position','activity') NOT NULL,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(150) NOT NULL,
    reference_id INT UNSIGNED NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    metadata TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_teacher_salary_code (tahun_ajaran_id, code),
    KEY idx_teacher_salary_category (category),
    CONSTRAINT fk_teacher_salary_year FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_teacher_salary_reference FOREIGN KEY (reference_id) REFERENCES jabatan_akademik(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teacher_salary_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    honor_id INT UNSIGNED NULL,
    periode VARCHAR(20) NOT NULL,
    teaching_hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    hourly_rate DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_teaching DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_special DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_academic DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_activity DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_adjustment DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_deduction DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_bruto DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_net DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','validated','disbursed') NOT NULL DEFAULT 'draft',
    slip_number VARCHAR(60) NULL,
    note TEXT NULL,
    disbursed_at DATETIME NULL,
    disbursed_by INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    UNIQUE KEY unique_teacher_salary_record (tahun_ajaran_id, guru_id, periode),
    KEY idx_teacher_salary_record_status (status),
    KEY idx_teacher_salary_record_period (periode),
    CONSTRAINT fk_teacher_salary_record_year FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_teacher_salary_record_teacher FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    CONSTRAINT fk_teacher_salary_record_honor FOREIGN KEY (honor_id) REFERENCES honor_guru(id) ON DELETE SET NULL,
    CONSTRAINT fk_teacher_salary_record_disbursed FOREIGN KEY (disbursed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_teacher_salary_record_created FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_teacher_salary_record_updated FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teacher_salary_components (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_salary_record_id INT UNSIGNED NOT NULL,
    type ENUM('teaching','special','academic','activity','adjustment','deduction') NOT NULL,
    code VARCHAR(80) NOT NULL,
    label VARCHAR(150) NOT NULL,
    quantity DECIMAL(8,2) NULL,
    rate DECIMAL(15,2) NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    metadata TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_teacher_salary_component_type (type),
    CONSTRAINT fk_teacher_salary_component_record FOREIGN KEY (teacher_salary_record_id) REFERENCES teacher_salary_records(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS honor_guru (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_id INT UNSIGNED NOT NULL,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    periode VARCHAR(20) NOT NULL,
    kategori VARCHAR(100) NOT NULL,
    judul VARCHAR(180) NOT NULL,
    nominal_bruto DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    nominal_potongan DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    nominal_diterima DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','menunggu_verifikasi','menunggu_acc','disetujui','ditolak','terbayar') NOT NULL DEFAULT 'draft',
    tanggal_verifikasi DATETIME NULL,
    tanggal_acc DATETIME NULL,
    slip_path VARCHAR(255) NULL,
    catatan TEXT NULL,
    diverifikasi_oleh INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    UNIQUE KEY unique_honor_periode (guru_id, tahun_ajaran_id, periode, kategori),
    KEY idx_honor_status (status),
    CONSTRAINT fk_honor_guru_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    CONSTRAINT fk_honor_guru_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_honor_guru_diverifikasi FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_honor_guru_approved FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_honor_guru_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_honor_guru_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS keuangan_approval (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('tagihan','pembayaran','kasbon','dana_kegiatan','honor') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    approver_id INT UNSIGNED NOT NULL,
    status ENUM('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
    tanggal DATETIME NOT NULL,
    catatan TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_keuangan_approval_entity (entity_type, entity_id),
    KEY idx_keuangan_approval_status (status),
    CONSTRAINT fk_keuangan_approval_approver FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS arus_kas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_transaksi VARCHAR(80) NOT NULL,
    tipe ENUM('masuk','keluar') NOT NULL,
    sumber ENUM('tagihan','tabungan','kasbon','kegiatan','honor','penyesuaian','kas_umum','tak_terduga','pembelian') NOT NULL,
    referensi_id INT UNSIGNED NULL,
    referensi_kode VARCHAR(100) NULL,
    tanggal DATETIME NOT NULL,
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    saldo_setelah DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    keterangan TEXT NULL,
    dicatat_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_arus_kas_kode (kode_transaksi),
    KEY idx_arus_kas_tanggal (tanggal),
    KEY idx_arus_kas_sumber (sumber),
    CONSTRAINT fk_arus_kas_dicatat_oleh FOREIGN KEY (dicatat_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kas_umum (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    saldo DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_kas_umum_tahun (tahun_ajaran_id),
    CONSTRAINT fk_kas_umum_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kas_umum_transaksi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    kode_transaksi VARCHAR(80) NOT NULL,
    tipe ENUM('masuk','keluar') NOT NULL,
    sumber_tipe ENUM('eksternal','tagihan','tabungan','kas_umum','pembelian') NOT NULL,
    sumber_id INT UNSIGNED NULL,
    tujuan_tipe ENUM('kas_umum','tagihan','tabungan','kasbon','tak_terduga','honor','pembelian') NOT NULL,
    tujuan_id INT UNSIGNED NULL,
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tanggal DATETIME NOT NULL,
    keterangan TEXT NULL,
    dicatat_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_kas_umum_transaksi (kode_transaksi),
    KEY idx_kas_umum_transaksi_tahun (tahun_ajaran_id),
    CONSTRAINT fk_kas_umum_transaksi_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_kas_umum_transaksi_dicatat FOREIGN KEY (dicatat_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tabungan_pool_adjustments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT UNSIGNED NOT NULL,
    tipe ENUM('pinjam','kembalikan') NOT NULL,
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tanggal DATETIME NOT NULL,
    keterangan TEXT NULL,
    dicatat_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_tabungan_pool_adjustments_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_tabungan_pool_adjustments_dicatat FOREIGN KEY (dicatat_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ppdb_periode (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(50) NOT NULL,
    nama VARCHAR(120) NOT NULL,
    tahun_masuk VARCHAR(9) NULL,
    tahun_ajaran_target_id INT UNSIGNED NULL,
    pendaftaran_mulai DATE NULL,
    pendaftaran_selesai DATE NULL,
    seleksi_mulai DATE NULL,
    seleksi_selesai DATE NULL,
    pengumuman_mulai DATE NULL,
    pengumuman_selesai DATE NULL,
    daftar_ulang_mulai DATE NULL,
    daftar_ulang_selesai DATE NULL,
    pembayaran_mulai DATE NULL,
    pembayaran_selesai DATE NULL,
    pendaftaran_diaktifkan TINYINT(1) NOT NULL DEFAULT 1,
    seleksi_diaktifkan TINYINT(1) NOT NULL DEFAULT 0,
    pengumuman_diaktifkan TINYINT(1) NOT NULL DEFAULT 0,
    daftar_ulang_diaktifkan TINYINT(1) NOT NULL DEFAULT 0,
    pembayaran_diaktifkan TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('draft','aktif','selesai','arsip') NOT NULL DEFAULT 'draft',
    token_pendaftaran VARCHAR(64) NOT NULL,
    catatan TEXT NULL,
    dibuat_oleh INT UNSIGNED NULL,
    diperbarui_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_ppdb_periode_kode (kode),
    UNIQUE KEY unique_ppdb_periode_token (token_pendaftaran),
    KEY idx_ppdb_periode_status (status),
    KEY idx_ppdb_periode_tahun (tahun_ajaran_target_id),
    CONSTRAINT fk_ppdb_periode_tahun FOREIGN KEY (tahun_ajaran_target_id) REFERENCES tahun_ajaran(id) ON DELETE SET NULL,
    CONSTRAINT fk_ppdb_periode_dibuat FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ppdb_periode_diperbarui FOREIGN KEY (diperbarui_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ppdb_periode_penanggung_jawab (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    periode_id INT UNSIGNED NOT NULL,
    guru_id INT UNSIGNED NOT NULL,
    peran ENUM('ketua','sekretaris','anggota') NOT NULL DEFAULT 'anggota',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_ppdb_penanggung_periode_guru (periode_id, guru_id),
    KEY idx_ppdb_penanggung_guru (guru_id),
    CONSTRAINT fk_ppdb_penanggung_periode FOREIGN KEY (periode_id) REFERENCES ppdb_periode(id) ON DELETE CASCADE,
    CONSTRAINT fk_ppdb_penanggung_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ppdb_pendaftar (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    periode_id INT UNSIGNED NOT NULL,
    kode_pendaftaran VARCHAR(50) NOT NULL,
    sumber ENUM('mandiri','panitia') NOT NULL DEFAULT 'mandiri',
    nama_lengkap VARCHAR(150) NOT NULL,
    jenis_kelamin ENUM('L','P') NOT NULL,
    tempat_lahir VARCHAR(100) NULL,
    tanggal_lahir DATE NULL,
    nik VARCHAR(32) NULL,
    nisn VARCHAR(32) NULL,
    asal_sekolah VARCHAR(150) NULL,
    alamat TEXT NULL,
    telepon VARCHAR(32) NULL,
    email VARCHAR(120) NULL,
    nama_wali VARCHAR(150) NULL,
    telepon_wali VARCHAR(32) NULL,
    tanggal_daftar DATETIME NOT NULL,
    catatan TEXT NULL,
    status_verifikasi ENUM('draft','lengkap','diverifikasi') NOT NULL DEFAULT 'draft',
    status_seleksi ENUM('belum_dijadwalkan','dijadwalkan','lulus','cadangan','tidak_lulus') NOT NULL DEFAULT 'belum_dijadwalkan',
    status_pengumuman ENUM('menunggu','lulus','cadangan','tidak_lulus') NOT NULL DEFAULT 'menunggu',
    status_daftar_ulang ENUM('tidak_dibuka','menunggu','selesai') NOT NULL DEFAULT 'tidak_dibuka',
    status_pembayaran ENUM('tidak_dibuka','menunggu','lunas','dibebaskan') NOT NULL DEFAULT 'tidak_dibuka',
    status_final ENUM('pendaftar','diterima','cadangan','ditolak','mengundurkan_diri') NOT NULL DEFAULT 'pendaftar',
    jadwal_seleksi DATETIME NULL,
    nilai_seleksi DECIMAL(6,2) NULL,
    tanggal_pengumuman DATETIME NULL,
    tanggal_daftar_ulang DATETIME NULL,
    tanggal_pembayaran DATETIME NULL,
    nominal_pembayaran DECIMAL(15,2) NULL,
    bukti_pembayaran_path VARCHAR(255) NULL,
    diverifikasi_oleh INT UNSIGNED NULL,
    seleksi_diperbarui_oleh INT UNSIGNED NULL,
    daftar_ulang_diperbarui_oleh INT UNSIGNED NULL,
    pembayaran_diperbarui_oleh INT UNSIGNED NULL,
    siswa_id INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_ppdb_kode (kode_pendaftaran),
    KEY idx_ppdb_periode (periode_id),
    KEY idx_ppdb_status_final (status_final),
    KEY idx_ppdb_siswa (siswa_id),
    CONSTRAINT fk_ppdb_pendaftar_periode FOREIGN KEY (periode_id) REFERENCES ppdb_periode(id) ON DELETE CASCADE,
    CONSTRAINT fk_ppdb_pendaftar_diverifikasi FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ppdb_pendaftar_seleksi FOREIGN KEY (seleksi_diperbarui_oleh) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ppdb_pendaftar_daftar_ulang FOREIGN KEY (daftar_ulang_diperbarui_oleh) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ppdb_pendaftar_pembayaran FOREIGN KEY (pembayaran_diperbarui_oleh) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ppdb_pendaftar_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ppdb_pembayaran (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pendaftar_id INT UNSIGNED NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    tanggal_pembayaran DATETIME NOT NULL,
    metode VARCHAR(80) NULL,
    status ENUM('menunggu','lunas','ditolak') NOT NULL DEFAULT 'menunggu',
    catatan TEXT NULL,
    lampiran_path VARCHAR(255) NULL,
    dibuat_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_ppdb_pembayaran_pendaftar FOREIGN KEY (pendaftar_id) REFERENCES ppdb_pendaftar(id) ON DELETE CASCADE,
    CONSTRAINT fk_ppdb_pembayaran_dibuat FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Dummy data
-- -----------------------------------------------------

INSERT INTO tahun_ajaran (kode, nama, tanggal_mulai, tanggal_selesai, status, semester_aktif)
VALUES
('2023-2024', 'Tahun Pelajaran 2023/2024', '2023-07-17', '2024-06-30', 'nonaktif', 2),
('2024-2025', 'Tahun Pelajaran 2024/2025', '2024-07-15', '2025-06-30', 'aktif', 1);

INSERT INTO jurusan (kode, nama, deskripsi)
VALUES
('RPL', 'Rekayasa Perangkat Lunak', 'Jurusan Rekayasa Perangkat Lunak'),
('TKJ', 'Teknik Komputer dan Jaringan', 'Jurusan Teknik Komputer dan Jaringan');

INSERT INTO guru (nip, nama, email, telepon, alamat)
VALUES
('198001010001', 'Budi Santoso', 'budi.santoso@example.test', '081234567890', 'Jl. Merdeka No. 10'),
('198502121234', 'Siti Aminah', 'siti.aminah@example.test', '081234567891', 'Jl. Melati No. 22'),
('197911110987', 'Eko Prasetyo', 'eko.prasetyo@example.test', '081234567892', 'Jl. Kenanga No. 5');

INSERT INTO data_sikap (jenis, kode, nama, deskripsi, status)
VALUES
('spiritual', 'SP01', 'Berdoa sebelum belajar', 'Melakukan doa sebelum memulai pembelajaran', 'aktif'),
('spiritual', 'SP02', 'Bersyukur', 'Mengucapkan rasa syukur atas hasil pembelajaran', 'aktif'),
('sosial', 'SO01', 'Gotong royong', 'Saling membantu menjaga kebersihan lingkungan kelas', 'aktif'),
('sosial', 'SO02', 'Disiplin', 'Mematuhi tata tertib sekolah dan datang tepat waktu', 'aktif');

INSERT INTO mata_pelajaran (tahun_ajaran_id, kode, nama, jenis, jurusan_id, deskripsi)
VALUES
(1, 'MP1001', 'Pemrograman Dasar', 'produktif', 1, 'Pengenalan logika dan algoritma pemrograman'),
(1, 'MP1002', 'Jaringan Komputer', 'produktif', 2, 'Dasar instalasi dan konfigurasi jaringan komputer'),
(2, 'MP2001', 'Basis Data Lanjut', 'produktif', 1, 'Perancangan basis data dan optimasi query'),
(2, 'MP2002', 'Keamanan Jaringan', 'produktif', 2, 'Konsep dasar pengamanan jaringan pada skala menengah');

INSERT INTO guru_mata_pelajaran (mata_pelajaran_id, guru_id, catatan)
VALUES
(1, 1, 'Mengajar kelas XI RPL 1'),
(2, 2, 'Mengajar kelas XI TKJ 1'),
(3, 1, 'Mengajar kelas XII RPL 1'),
(4, 3, 'Mengajar kelas XI TKJ 1 materi lanjutan');

INSERT INTO kelas (tahun_ajaran_id, jurusan_id, tingkat, nama, wali_kelas_id)
VALUES
(1, 1, 11, 'XI RPL 1', 1),
(2, 1, 12, 'XII RPL 1', 1),
(2, 2, 11, 'XI TKJ 1', 2);

INSERT INTO siswa (
    nama,
    nipd,
    jenis_kelamin,
    nisn,
    tempat_lahir,
    tanggal_lahir,
    nik,
    agama,
    alamat,
    rt,
    rw,
    dusun,
    kelurahan,
    kecamatan,
    kode_pos,
    jenis_tinggal,
    alat_transportasi,
    telepon,
    hp,
    email,
    skhun,
    penerima_kps,
    nomor_kps,
    ayah_nama,
    ayah_tahun_lahir,
    ayah_jenjang_pendidikan,
    ayah_pekerjaan,
    ayah_penghasilan,
    ayah_nik,
    ibu_nama,
    ibu_tahun_lahir,
    ibu_jenjang_pendidikan,
    ibu_pekerjaan,
    ibu_penghasilan,
    ibu_nik,
    wali_nama,
    wali_tahun_lahir,
    wali_jenjang_pendidikan,
    wali_pekerjaan,
    wali_penghasilan,
    wali_nik,
    rombel_saat_ini,
    nomor_peserta_ujian,
    nomor_seri_ijazah,
    penerima_kip,
    nomor_kip,
    nama_di_kip,
    nomor_kks,
    nomor_registrasi_akta_lahir,
    bank,
    nomor_rekening_bank,
    rekening_atas_nama,
    layak_pip,
    alasan_layak_pip,
    kebutuhan_khusus,
    sekolah_asal,
    anak_ke,
    lintang,
    bujur,
    nomor_kk,
    berat_badan,
    tinggi_badan,
    lingkar_kepala,
    jumlah_saudara_kandung,
    jarak_rumah_ke_sekolah_km,
    tahun_ajaran_id,
    kelas_id,
    status,
    status_dapodik
)
VALUES
(
    'Adi Nugroho',
    'S2024001',
    'L',
    '0034567890',
    'Bandung',
    '2007-05-10',
    '3273011005070001',
    'Islam',
    'Jl. Mawar No. 3',
    '001',
    '002',
    'Mawar',
    'Karang Mulya',
    'Cimahi Utara',
    '40511',
    'Bersama Orang Tua',
    'Jalan Kaki',
    '0227000001',
    '081312345678',
    'adi.nugroho@example.test',
    'SKHUN-2024-001',
    0,
    NULL,
    'Slamet Nugroho',
    '1978',
    'SMA',
    'Karyawan Swasta',
    '4-5 juta',
    '3273011503780002',
    'Lestari Wulandari',
    '1980',
    'SMA',
    'Ibu Rumah Tangga',
    '<1 juta',
    '3273016405800003',
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    'XII RPL 1',
    '24-12-10-001',
    'DN-99 XX 0012345',
    1,
    '123456789012',
    'Adi Nugroho',
    'KKS-001',
    '3273011005070001',
    'Bank BRI',
    '1234567890',
    'Adi Nugroho',
    1,
    'Berprestasi dan membutuhkan dukungan biaya',
    NULL,
    'SMP Negeri 1 Cimahi',
    1,
    -6.87234500,
    107.54210000,
    '3273010102030405',
    58.50,
    168.00,
    54.20,
    2,
    2.50,
    2,
    2,
    'aktif',
    'aktif'
),
(
    'Rina Putri',
    'S2024002',
    'P',
    '0034567891',
    'Bandung',
    '2007-08-22',
    '3273012208070004',
    'Islam',
    'Jl. Anggrek No. 15',
    '003',
    '004',
    'Anggrek',
    'Karang Mulya',
    'Cimahi Utara',
    '40511',
    'Bersama Orang Tua',
    'Sepeda',
    '0227000002',
    '081312345679',
    'rina.putri@example.test',
    'SKHUN-2024-002',
    1,
    '987654321098',
    'Rahmat Putra',
    '1976',
    'S1',
    'Pegawai Negeri',
    '5-10 juta',
    '3273011502760005',
    'Sulastri Dewi',
    '1978',
    'S1',
    'Guru',
    '4-5 juta',
    '3273016405780006',
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    'XII RPL 1',
    '24-12-10-002',
    'DN-99 XX 0012346',
    1,
    '123456789013',
    'Rina Putri',
    'KKS-002',
    '3273012208070004',
    'Bank BNI',
    '9876543210',
    'Rina Putri',
    1,
    'Aktif mengikuti kegiatan kompetisi',
    'Tidak Ada',
    'SMP Negeri 2 Cimahi',
    2,
    -6.87300000,
    107.54180000,
    '3273010102030406',
    52.30,
    162.00,
    52.10,
    1,
    3.10,
    2,
    2,
    'aktif',
    'aktif'
),
(
    'Dimas Saputra',
    'S2024003',
    'L',
    '0034567892',
    'Cimahi',
    '2008-01-14',
    '3273011401080007',
    'Islam',
    'Jl. Dahlia No. 7',
    '005',
    '006',
    'Dahlia',
    'Cigugur Tengah',
    'Cimahi Tengah',
    '40512',
    'Bersama Orang Tua',
    'Motor',
    '0227000003',
    '081312345680',
    'dimas.saputra@example.test',
    'SKHUN-2024-003',
    0,
    NULL,
    'Bambang Saputra',
    '1980',
    'D3',
    'Teknisi',
    '3-4 juta',
    '3273011503800008',
    'Nurhayati',
    '1982',
    'SMA',
    'Wiraswasta',
    '2-3 juta',
    '3273016405820009',
    'Suharto Saputra',
    '1972',
    'SMA',
    'Pensiunan',
    '2-3 juta',
    '3273011503720010',
    'XI TKJ 1',
    '24-11-03-003',
    'DN-99 XX 0012347',
    0,
    NULL,
    NULL,
    NULL,
    '3273011401080007',
    'Bank Mandiri',
    '5432167890',
    'Dimas Saputra',
    0,
    NULL,
    'Tidak Ada',
    'SMP Negeri 3 Cimahi',
    3,
    -6.87420000,
    107.54310000,
    '3273010102030407',
    60.10,
    170.00,
    55.00,
    3,
    4.80,
    2,
    3,
    'aktif',
    'aktif'
);

INSERT INTO tempat_prakerin (nama, deskripsi, pembina_guru_id)
VALUES
(
    'PT Nusantara Teknologi',
    'Mitra prakerin bidang pengembangan perangkat lunak',
    (
        SELECT g.id
        FROM guru g
        WHERE g.nama = 'Budi Santoso'
          AND g.status = 'aktif'
          AND (
              EXISTS (
                  SELECT 1
                  FROM guru_mata_pelajaran gmp
                  JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                  JOIN tahun_ajaran ta ON ta.id = mp.tahun_ajaran_id
                  WHERE gmp.guru_id = g.id
                    AND ta.status = 'aktif'
              )
              OR EXISTS (
                  SELECT 1
                  FROM guru_jabatan_akademik gja
                  JOIN tahun_ajaran ta2 ON ta2.id = gja.tahun_ajaran_id
                  WHERE gja.guru_id = g.id
                    AND ta2.status = 'aktif'
              )
              OR EXISTS (
                  SELECT 1
                  FROM kelas k
                  JOIN tahun_ajaran ta3 ON ta3.id = k.tahun_ajaran_id
                  WHERE k.wali_kelas_id = g.id
                    AND ta3.status = 'aktif'
              )
          )
        LIMIT 1
    )
),
(
    'CV Jaringan Prima',
    'Mitra prakerin bidang jaringan komputer dan infrastruktur',
    (
        SELECT g.id
        FROM guru g
        WHERE g.nama = 'Eko Prasetyo'
          AND g.status = 'aktif'
          AND (
              EXISTS (
                  SELECT 1
                  FROM guru_mata_pelajaran gmp
                  JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                  JOIN tahun_ajaran ta ON ta.id = mp.tahun_ajaran_id
                  WHERE gmp.guru_id = g.id
                    AND ta.status = 'aktif'
              )
              OR EXISTS (
                  SELECT 1
                  FROM guru_jabatan_akademik gja
                  JOIN tahun_ajaran ta2 ON ta2.id = gja.tahun_ajaran_id
                  WHERE gja.guru_id = g.id
                    AND ta2.status = 'aktif'
              )
              OR EXISTS (
                  SELECT 1
                  FROM kelas k
                  JOIN tahun_ajaran ta3 ON ta3.id = k.tahun_ajaran_id
                  WHERE k.wali_kelas_id = g.id
                    AND ta3.status = 'aktif'
              )
          )
        LIMIT 1
    )
);

INSERT INTO ekstrakurikuler (tahun_ajaran_id, nama, deskripsi, pembina_guru_id, jadwal)
VALUES
(
    (SELECT ta.id FROM tahun_ajaran ta WHERE ta.status = 'aktif' ORDER BY ta.tanggal_mulai DESC LIMIT 1),
    'Paskibra',
    'Latihan baris berbaris dan pengibaran bendera',
    (
        SELECT g.id
        FROM guru g
        WHERE g.nama = 'Eko Prasetyo'
          AND g.status = 'aktif'
          AND (
              EXISTS (
                  SELECT 1
                  FROM guru_mata_pelajaran gmp
                  JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                  JOIN tahun_ajaran ta2 ON ta2.id = mp.tahun_ajaran_id
                  WHERE gmp.guru_id = g.id
                    AND ta2.status = 'aktif'
              )
              OR EXISTS (
                  SELECT 1
                  FROM guru_jabatan_akademik gja
                  JOIN tahun_ajaran ta3 ON ta3.id = gja.tahun_ajaran_id
                  WHERE gja.guru_id = g.id
                    AND ta3.status = 'aktif'
              )
              OR EXISTS (
                  SELECT 1
                  FROM kelas k
                  JOIN tahun_ajaran ta4 ON ta4.id = k.tahun_ajaran_id
                  WHERE k.wali_kelas_id = g.id
                    AND ta4.status = 'aktif'
              )
          )
        LIMIT 1
    ),
    'Jumat 15:00-17:00'
),
(
    (SELECT ta.id FROM tahun_ajaran ta WHERE ta.status = 'aktif' ORDER BY ta.tanggal_mulai DESC LIMIT 1),
    'Robotik',
    'Pengembangan robot berbasis mikrokontroler',
    (
        SELECT g.id
        FROM guru g
        WHERE g.nama = 'Budi Santoso'
          AND g.status = 'aktif'
          AND (
              EXISTS (
                  SELECT 1
                  FROM guru_mata_pelajaran gmp
                  JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                  JOIN tahun_ajaran ta2 ON ta2.id = mp.tahun_ajaran_id
                  WHERE gmp.guru_id = g.id
                    AND ta2.status = 'aktif'
              )
              OR EXISTS (
                  SELECT 1
                  FROM guru_jabatan_akademik gja
                  JOIN tahun_ajaran ta3 ON ta3.id = gja.tahun_ajaran_id
                  WHERE gja.guru_id = g.id
                    AND ta3.status = 'aktif'
              )
              OR EXISTS (
                  SELECT 1
                  FROM kelas k
                  JOIN tahun_ajaran ta4 ON ta4.id = k.tahun_ajaran_id
                  WHERE k.wali_kelas_id = g.id
                    AND ta4.status = 'aktif'
              )
          )
        LIMIT 1
    ),
    'Sabtu 09:00-12:00'
),
(
    (SELECT ta.id FROM tahun_ajaran ta WHERE ta.status = 'aktif' ORDER BY ta.tanggal_mulai DESC LIMIT 1),
    'Futsal',
    'Ekstrakurikuler olahraga futsal tingkat sekolah',
    (
        SELECT g.id
        FROM guru g
        WHERE g.nama = 'Siti Aminah'
          AND g.status = 'aktif'
          AND (
              EXISTS (
                  SELECT 1
                  FROM guru_mata_pelajaran gmp
                  JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                  JOIN tahun_ajaran ta2 ON ta2.id = mp.tahun_ajaran_id
                  WHERE gmp.guru_id = g.id
                    AND ta2.status = 'aktif'
              )
              OR EXISTS (
                  SELECT 1
                  FROM guru_jabatan_akademik gja
                  JOIN tahun_ajaran ta3 ON ta3.id = gja.tahun_ajaran_id
                  WHERE gja.guru_id = g.id
                    AND ta3.status = 'aktif'
              )
              OR EXISTS (
                  SELECT 1
                  FROM kelas k
                  JOIN tahun_ajaran ta4 ON ta4.id = k.tahun_ajaran_id
                  WHERE k.wali_kelas_id = g.id
                    AND ta4.status = 'aktif'
              )
          )
        LIMIT 1
    ),
    'Rabu 16:00-18:00'
);

INSERT INTO sekolah (nama, npsn, nss, alamat, desa, kecamatan, kabupaten, provinsi, kode_pos, telepon, email, website, akreditasi, logo_sekolah, logo_dinas, lambang_negara)
VALUES
('SMK Negeri Contoh', '10293847', '12345678', 'Jl. Pendidikan No. 1', 'Karang Mulya', 'Cimahi Utara', 'Cimahi', 'Jawa Barat', '40511', '0227000000', 'info@smkcontoh.sch.id', 'https://smkcontoh.sch.id', 'A', NULL, NULL, NULL);

INSERT INTO users (name, username, password, email, role)
VALUES
('Administrator', 'admin', '$2y$10$OvfnhxhIqTHsXqofZwpbeeI5ZEPm8c5yPD.9Vm6KsCFqd.yGK/oqK', 'admin@example.test', 'admin');

INSERT INTO users (name, username, password, email, role, teacher_id)
VALUES
('Budi Santoso', 'budi', '$2y$10$OvfnhxhIqTHsXqofZwpbeeI5ZEPm8c5yPD.9Vm6KsCFqd.yGK/oqK', 'budi.santoso@example.test', 'guru', 1),
('Siti Aminah', 'siti', '$2y$10$OvfnhxhIqTHsXqofZwpbeeI5ZEPm8c5yPD.9Vm6KsCFqd.yGK/oqK', 'siti.aminah@example.test', 'guru', 2),
('Eko Prasetyo', 'eko', '$2y$10$OvfnhxhIqTHsXqofZwpbeeI5ZEPm8c5yPD.9Vm6KsCFqd.yGK/oqK', 'eko.prasetyo@example.test', 'guru', 3);

INSERT INTO users (name, username, password, email, role, student_id)
VALUES
('Adi Nugroho', '0034567890', '$2y$10$iDADdXMNP14vQJIruFy7dujq/tEWPU.uf8ZS5QiMJ/xKhO3BgH8Um', 'adi.nugroho@example.test', 'siswa', 1),
('Rina Putri', '0034567891', '$2y$10$5Tq3A6XZUxMm3suD8ssgOu/IwtnYcmG6eG3TPFUarNqe6SSJmMRSi', 'rina.putri@example.test', 'siswa', 2),
('Dimas Saputra', '0034567892', '$2y$10$B.qpA3UwMGBbXx5nvYyQZOw/REkKZtr.wiSdPdfsKfOraQwaasST2', 'dimas.saputra@example.test', 'siswa', 3);

INSERT INTO jabatan_akademik (nama, deskripsi, level, kategori, assigns_user_role, requires_major, is_system)
VALUES
('Bendahara', 'Mengelola administrasi keuangan sekolah', 1, 'guru', 'bendahara', 0, 1),
('Staf Tata Usaha', 'Menangani administrasi tata usaha sekolah', 2, 'guru', 'tata_usaha', 0, 1),
('Wali Kelas', 'Bertanggung jawab terhadap pengelolaan kelas', 3, 'guru', NULL, 0, 0),
('Kepala Sekolah', 'Memimpin keseluruhan kegiatan sekolah', 4, 'guru', 'kepala_sekolah', 0, 0),
('Waka Kurikulum', 'Mengelola kurikulum dan kegiatan akademik', 5, 'guru', 'waka_kurikulum', 0, 1),
('Kepala Program Studi', 'Mengkoordinasikan kebutuhan jurusan dan pengadaan praktik', 6, 'guru', 'kepala_prodi', 1, 1),
('Ketua OSIS', 'Memimpin organisasi OSIS', 1, 'siswa', NULL, 0, 0),
('Ketua Kelas', 'Koordinator kegiatan kelas', 2, 'siswa', NULL, 0, 0);

INSERT INTO guru_jabatan_akademik (tahun_ajaran_id, guru_id, jabatan_akademik_id, jurusan_id, tanggal_mulai, tanggal_selesai, catatan)
VALUES
(1, 1, 1, NULL, '2023-07-17', '2024-06-30', 'Menjabat pada TP 2023/2024'),
(2, 1, 1, NULL, '2024-07-15', NULL, 'Menjabat kembali pada TP 2024/2025'),
(2, 2, 3, NULL, '2024-07-15', NULL, NULL),
(2, 3, 2, NULL, '2024-07-15', NULL, 'Dilantik pada awal tahun ajaran'),
(2, 4, 6, 1, '2024-07-15', NULL, 'Kepala Prodi Teknik Mesin'),
(2, 5, 6, 2, '2024-07-15', NULL, 'Kepala Prodi Teknik Informatika');

INSERT INTO siswa_jabatan_akademik (tahun_ajaran_id, siswa_id, jabatan_akademik_id, tanggal_mulai, tanggal_selesai, catatan)
VALUES
(2, 1, 5, '2024-07-15', NULL, 'Dipilih oleh wali kelas'),
(2, 2, 4, '2024-07-15', NULL, 'Hasil pemilihan OSIS');

INSERT INTO prestasi_siswa (tahun_ajaran_id, kelas_id, siswa_id, guru_id, jenis, keterangan, created_at, updated_at)
VALUES
(2, 2, 1, 1, 'Juara 1 LKS Provinsi', 'Memenangkan lomba kompetensi siswa tingkat provinsi bidang RPL.', '2024-09-01 08:15:00', '2024-09-01 08:15:00'),
(2, 2, 2, 1, 'Finalis Olimpiade Sains', 'Menjadi finalis olimpiade sains kabupaten dengan proyek energi terbarukan.', '2024-10-05 10:45:00', '2024-10-05 10:45:00'),
(2, 3, 3, 2, 'Juara 2 Turnamen Futsal', NULL, '2024-08-20 17:30:00', '2024-08-20 17:30:00');

SET FOREIGN_KEY_CHECKS = 1;

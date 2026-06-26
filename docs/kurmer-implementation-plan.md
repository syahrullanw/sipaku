# Rencana Implementasi Kurikulum Merdeka (dual-mode)

## Audit singkat modul K13 yang ada
- Skema penilaian inti: `mata_pelajaran_kd` (KD pengetahuan/keterampilan per guru_mata_pelajaran + kelas), `penilaian_kd_siswa` (nilai per KD), rekap per komponen `penilaian_pengetahuan_siswa` (nilai_kd, uts, uas, nilai_akhir, predikat, deskripsi) dan `penilaian_keterampilan_siswa` (nilai_akhir, predikat, deskripsi). Pengaturan bobot/KKM di `pengaturan_penilaian_mapel`.
- Controller & view guru: `TeacherKnowledgeAssessmentController` + `resources/views/teacher/subjects/knowledge.php` (kelola KD P + input nilai KD/UTS/UAS, deskripsi otomatis), `TeacherSkillAssessmentController` + `resources/views/teacher/subjects/skill.php` (KD K + nilai KD → nilai akhir), `TeacherSubjectAssessmentController` (pengaturan KKM/bobot), `TeacherSubjectLedgerController` + `app/Services/LedgerExporter.php` (rekap leger).
- Layanan & agregasi rapor: `ReportPrintController::collectSubjectScores` menarik pengetahuan/keterampilan per mapel + KKM, digrup `Subject::GROUPS`, dipakai di `resources/views/reports/partials/grade-report.php`. Dashboard siswa & wali kelas memakai `StudentScoreSummary`/`StudentReportService`. Ledger wali kelas (`HomeroomLedgerController`) memakai tabel pengetahuan/keterampilan yang sama. Belum ada struktur atau tampilan P5.
- Penunjang predikat berbasis angka: `AssessmentEvaluator` hanya mengenali rentang 0-100 (+ KKM) dan mengembalikan predikat teks.

## Sasaran perubahan KurMer (minimum viable, tanpa mematikan K13)
- Menambah mode KurMer per rombel/kelas dengan master TP per mapel, penilaian capaian TP berskala BB/MB/BSH/SB (angka opsional), ringkasan deskripsi rapor per mapel, dan modul Projek P5. K13 tetap berjalan dengan tabel/layanan lama.

## Rencana data & skema

### Flag kurikulum
- Tambah kolom `kelas.kurikulum` enum(`k13`,`kurmer`) default `k13`.
- Tambah kolom `guru_mata_pelajaran_kelas.penilaian_mode` enum(`inherit`,`k13`,`kurmer`) default `inherit` untuk override khusus mapel jika diperlukan (jika null/tidak diisi, ikut flag kelas).

### Tabel/kolom baru
- `mata_pelajaran_tp` (master TP per mapel & kelas KurMer)  
  Kolom: id, guru_mata_pelajaran_id (FK), guru_mata_pelajaran_kelas_id (FK, nullable), kelas_id (FK), kode_tp, fase (char, opsional), elemen/sub_elemen (text), deskripsi, urutan, created_at, updated_at. Unique: (guru_mata_pelajaran_id, kelas_id, kode_tp).
- `penilaian_tp_siswa` (capaian per TP per siswa, kualitatif dengan angka opsional)  
  Kolom: id, guru_mata_pelajaran_id, kelas_id, tp_id (FK), siswa_id (FK), capaian_enum (`BB`,`MB`,`BSH`,`SB`), nilai_opsional DECIMAL(5,2) NULL, catatan TEXT NULL, created_at, updated_at. Unique: (tp_id, siswa_id).
- `penilaian_kurmer_mapel_siswa` (ringkasan per mapel untuk rapor KurMer)  
  Kolom: id, guru_mata_pelajaran_id, kelas_id, siswa_id, capaian_akhir_enum (`BB`…`SB`), deskripsi_umum TEXT, tindak_lanjut TEXT NULL, nilai_opsional DECIMAL(5,2) NULL, sumber_tp JSON NULL (list TP dominan), created_at, updated_at. Unique: (guru_mata_pelajaran_id, kelas_id, siswa_id).
- `p5_dimensi` (master dimensi Profil Pancasila; seeded)  
  Kolom: id, kode, nama, deskripsi.
- `p5_elemen` (master elemen/sub-elemen per dimensi; seeded)  
  Kolom: id, dimensi_id (FK), kode, nama, fase, deskripsi.
- `p5_projek` (projek P5 per kelas/tahun ajaran)  
  Kolom: id, tahun_ajaran_id (FK), kelas_id (FK), tema, judul, deskripsi, tanggal_mulai, tanggal_selesai, guru_pembimbing_id (FK), lampiran_path NULL, created_at, updated_at.
- `p5_projek_elemen` (elemen/TP yang dinilai di sebuah projek)  
  Kolom: id, projek_id (FK), elemen_id (FK, bisa null jika TP custom), tp_deskripsi TEXT, urutan, bobot_opsional DECIMAL(5,2).
- `p5_penilaian_siswa` (capaian siswa per projek/elemen)  
  Kolom: id, projek_id, projek_elemen_id, siswa_id, capaian_enum (`BB`,`MB`,`BSH`,`SB`), catatan TEXT NULL, nilai_opsional DECIMAL(5,2) NULL, updated_at.
- `p5_penilaian_ringkasan` (resume P5 per projek per siswa untuk rapor)  
  Kolom: id, projek_id, siswa_id, capaian_akhir_enum, deskripsi_umum TEXT, tindak_lanjut TEXT NULL, updated_at.
- Perlu migrasi baru untuk semua kolom/tabel di atas; skema lama tidak disentuh.

### Relasi utama
- kelas.kurikulum → mengendalikan mode default penilaian semua mapel di kelas tersebut.
- guru_mata_pelajaran_kelas.penilaian_mode → override per mapel-per-kelas; fallback ke kelas.kurikulum.
- mata_pelajaran_tp & penilaian_tp_siswa terikat ke guru_mata_pelajaran (+ kelas) mirip KD saat ini.
- penilaian_kurmer_mapel_siswa merangkum capaian TP per siswa untuk ditarik di rapor KurMer.
- Projek P5: p5_projek → p5_projek_elemen → p5_penilaian_siswa/p5_penilaian_ringkasan; referensi ke dimensi/elemen master agar tetap konsisten antar projek.

## Perubahan layanan/API
- Tambah controller & model baru untuk TP KurMer (mirip KD): CRUD TP, simpan capaian TP, simpan ringkasan mapel. Endpoint terpisah dari K13 agar tidak mencampur payload angka KD.
- Tambah controller/module Projek P5: CRUD projek, pilih elemen/TP, input capaian siswa, ekspor/cetak rapor P5.
- Update `TeacherSubjectAssessmentController` untuk mendeteksi kurikulum (kelas atau override) dan mengarahkan ke form K13 lama atau form KurMer baru.
- Update `AssessmentEvaluator` agar mengenali skala BB/MB/BSH/SB dan konversi opsional ke angka (jika angka diisi), tanpa mengubah logika penentu predikat K13.
- Update `SubjectAssessmentSetting` untuk menyimpan preferensi KurMer (mis. `mode_penilaian`, `skala_kurmer=bb_mb_bsh_sb`, flag “butuh_nilai_angka”); tetap backward compatible untuk assignment K13.
- Update agregator rapor: `ReportPrintController::collectSubjectScores` + `StudentReportService` + `StudentScoreSummary` untuk cabang KurMer (ambil capaian TP & ringkasan KurMer, bukan nilai KD/UTS/UAS). Pastikan pipeline lama tetap berjalan untuk kelas K13.
- Update leger wali kelas/guru (`HomeroomLedgerController`, `TeacherSubjectLedgerController`, `LedgerExporter`) agar bisa menampilkan mode KurMer (skala capaian + deskripsi TP) atau K13 (angka).
- Tambah routing di `routes/web.php` untuk modul TP KurMer & Projek P5; route lama tidak diubah.

## Perubahan UI/UX (input guru & cetak rapor)
- Admin/Operator: form master kelas (`resources/views/.../kelas`) diberi pilihan kurikulum (K13/KurMer) + indikator di daftar kelas; opsional toggle per mapel di halaman guru pengampu jika perlu override.
- Guru mapel KurMer:
  - Halaman TP KurMer (baru) untuk menambah/menghapus TP per kelas; atribut: kode/fase/elemen/deskripsi.
  - Form input capaian TP per siswa (matrix siswa × TP) dengan pilihan BB/MB/BSH/SB, kolom catatan singkat, nilai angka opsional.  
  - Form ringkasan mapel per siswa (ambil TP dominan otomatis, guru bisa edit deskripsi umum/tindak lanjut).
- Guru mapel K13: tetap memakai halaman existing `knowledge.php`/`skill.php` tanpa perubahan selain deteksi mode.
- Projek P5:
  - UI wali kelas/guru pembimbing untuk membuat projek P5, memilih elemen/TP, menetapkan siswa, dan memasukkan capaian (BB/MB/BSH/SB) + catatan.
  - Rekap P5 per projek/per siswa dan tombol cetak/unduh.
- Cetak rapor:
  - Mode K13: layout tetap (NP/NK, predikat, deskripsi).
  - Mode KurMer: tabel mapel menampilkan capaian akhir skala BB–SB + ringkasan deskripsi per mapel (tanpa kolom UTS/UAS); opsional tampilkan nilai angka jika diisi.  
  - Tambah halaman/section P5 (judul projek, tema, elemen, capaian siswa, catatan guru).
  - Pastikan digital signature & cover tetap berfungsi; gunakan label semester sama.

## Strategi kompatibilitas & migrasi
- Default seluruh kelas ke `k13` saat migrasi; tidak ada data lama yang diubah. Override penilaian_mode opsional, default mewarisi kelas.
- Jalur kode K13 tidak disentuh; cabang KurMer hanya aktif bila kelas/override berstatus `kurmer`.
- Migrasi bertahap: aktifkan KurMer per kelas (rombel) mulai tahun ajaran baru, kemudian buat TP & P5 tanpa menghapus data KD yang sudah ada.
- Jika perlu migrasi data KD → TP, siapkan skrip terpisah (tidak masuk skema) untuk menyalin KD sebagai TP draft.

## File/area yang akan disentuh
- Migrasi & skema: `database/migrations/*`, `database/schema.sql`.
- Model baru: TP & penilaian KurMer, projek P5; update model lama (`Classroom`, `SubjectTeacherClass`, `SubjectAssessmentSetting`, `AssessmentEvaluator`).
- Controller/service: tambah controller TP KurMer & P5; update `TeacherSubjectAssessmentController`, `ReportPrintController`, `StudentReportService`, `StudentScoreSummary`, `HomeroomLedgerController`, `TeacherSubjectLedgerController`, `LedgerExporter`.
- View & UI: form kelas (kurikulum), halaman guru penilaian KurMer (baru), penyesuaian `resources/views/teacher/subjects/*`, leger, laporan cetak (`resources/views/reports/partials/grade-report.php` + section baru P5).
- Routing: `routes/web.php` untuk endpoint KurMer/P5 baru.

## Paket pekerjaan (aksi) agar mudah di-troubleshoot

1) **Kurikulum flag & wiring dasar**
   - Tambah kolom `kelas.kurikulum` + `guru_mata_pelajaran_kelas.penilaian_mode` (migrasi).
   - Update form master kelas + daftar kelas untuk memilih/menampilkan kurikulum; opsional toggle override di guru pengampu.
   - Guard di controller penilaian mapel: jika `kurmer`, redirect ke flow KurMer; else tetap K13.

2) **Schema KurMer TP (tanpa P5 dulu)**
   - Buat tabel `mata_pelajaran_tp`, `penilaian_tp_siswa`, `penilaian_kurmer_mapel_siswa`.
   - Model & repository dasar untuk CRUD TP dan simpan capaian TP.
   - Seeder/sample minimal tidak perlu; cukup pastikan unique constraint bekerja.

3) **Form guru KurMer**
   - Halaman master TP per mapel/kelas (list + tambah/hapus).
   - Matrix input capaian TP per siswa (BB/MB/BSH/SB + catatan + nilai opsional).
   - Form ringkasan mapel per siswa (ambil TP dominan otomatis, boleh edit).
   - Update `SubjectAssessmentSetting` bila perlu flag skala KurMer/nilai angka opsional.

4) **Agregasi & laporan rapor KurMer**
   - Update `collectSubjectScores`/`StudentReportService`/`StudentScoreSummary` agar jika mode KurMer mengambil data `penilaian_kurmer_mapel_siswa` (bukan KD/UTS/UAS).
   - Sesuaikan view rapor (`grade-report.php`) untuk mode KurMer: kolom capaian (BB–SB) + deskripsi mapel; sembunyikan UTS/UAS/KKM.
   - Pastikan digital signature dan cover tetap jalan (payload rapor bercabang).

5) **Leger & dashboard wali**
   - Ubah `HomeroomLedgerController`/`TeacherSubjectLedgerController`/`LedgerExporter` agar bisa menampilkan mode KurMer (capaian kualitatif + deskripsi TP) berdampingan dengan K13.
   - Tambah indikator mode per mapel di leger.

6) **Projek P5**
   - Tambah tabel `p5_dimensi`, `p5_elemen`, `p5_projek`, `p5_projek_elemen`, `p5_penilaian_siswa`, `p5_penilaian_ringkasan` (migrasi + seeder master dimensi/elemen).
   - UI projek P5: CRUD projek, pilih elemen/TP, input capaian per siswa, rekap/cetak.
   - Tambah section P5 di rapor KurMer (pisah dari tabel mapel).

7) **Uji & kompatibilitas**
   - Smoke test K13 path (pengetahuan/keterampilan) agar tidak terpengaruh flag baru.
   - Uji KurMer: tambah kelas ber-flag KurMer → input TP → simpan capaian → cetak rapor → lihat leger.
   - Uji P5: buat projek, nilai siswa, cetak rapor dengan section P5.

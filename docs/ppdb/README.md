# PPDB Module Blueprint

Dokumen ini merangkum rancangan fitur PPDB (Penerimaan Peserta Didik Baru) agar implementasinya dapat dibangun dan diuji secara bertahap.

## 1. Ruang Lingkup Utama
- Admin menetapkan periode PPDB, jadwal tiap tahapan, serta daftar guru penanggung jawab (tidak bergantung pada jabatan akademik).
- Guru penanggung jawab otomatis memperoleh menu PPDB ketika periode aktif.
- Calon siswa mendaftar lewat tautan publik atau diinput panitia melalui panel internal.
- Tahapan seleksi akademik, pengumuman, daftar ulang, dan pembayaran dapat dinyalakan/dimatikan per periode.
- Tahap akhir memindahkan calon siswa yang diterima menjadi siswa aktif ketika tahun ajaran berikutnya dibuka.

## 2. Struktur Basis Data

### Tabel `ppdb_periode`
- Identitas periode (`nama`, `kode`, relasi tahun ajaran tujuan).
- Rentang tanggal tiap tahapan (pendaftaran, seleksi, pengumuman, daftar ulang, pembayaran).
- Flag aktivasi tahapan (`pendaftaran_diaktifkan`, `seleksi_diaktifkan`, `pengumuman_diaktifkan`, `daftar_ulang_diaktifkan`, `pembayaran_diaktifkan`).
- Status periode (`draft`, `aktif`, `selesai`, `arsip`) + token tautan pendaftaran publik.

### Tabel `ppdb_periode_penanggung_jawab`
- Relasi banyak-ke-satu antara periode dan guru bertanggung jawab.
- Menjadi referensi utama `PpdbGate` saat menampilkan menu khusus guru.

### Tabel `ppdb_pendaftar`
- Data identitas calon siswa + metadata jalur pendaftaran (mandiri/panitia).
- Status per tahapan (`status_verifikasi`, `status_seleksi`, `status_pengumuman`, `status_daftar_ulang`, `status_pembayaran`, `status_final`).
- Jadwal seleksi individual, hasil seleksi, bukti pembayaran, dan pengait ke tabel `siswa` (`siswa_id`) ketika proses finalisasi dilakukan.

### Tabel `ppdb_pembayaran` (opsional lanjutan)
- Log detail pembayaran (nominal, tanggal, metode) jika diperlukan audit granular.

## 3. Model & Service
- `App\Models\PpdbPeriod`, `PpdbPeriodResponsible`, `PpdbRegistrant`, `PpdbPayment`.
- `App\Support\PpdbGate` untuk pengecekan akses guru.
- Service helper:
  - `PpdbStageManager` → validasi aktivasi tahapan & transisi status.
  - `PpdbStudentImporter` → konversi calon siswa menjadi entri `siswa` beserta akun.

## 4. Routing & Modul
- Modul baru `modules/Ppdb` memuat:
  - Admin controllers (`PeriodController`, `RegistrantController`, `StageController`).
  - Guru controllers (`DashboardController`, `SelectionController`, `ReenrollmentController`, `PaymentController`).
  - Public controllers (`RegistrationController`, `AnnouncementController`).
- Rute dibagi: admin (`/ppdb/admin/...`), guru (`/ppdb/guru/...`), publik (`/ppdb/{token}`).

## 5. Antarmuka
- Halaman admin:
  1. Manajemen periode (CRUD, pengaturan tahapan, assignment guru).
  2. Daftar pendaftar (filter per status, ekspor, input manual).
  3. Konfigurasi jadwal seleksi bulk dan pengiriman notifikasi (lanjutan).
- Halaman guru penanggung jawab:
  - Dashboard ringkas status periode aktif.
  - Penjadwalan seleksi & input hasil.
  - Monitoring daftar ulang dan pembayaran.
- Halaman publik:
  - Formulir pendaftaran responsif.
  - Laman cek status dengan token pendaftaran.

## 6. Alur Tahapan & Toggle
| Tahap | Penjelasan | Aksi ketika dinonaktifkan |
| --- | --- | --- |
| Pendaftaran | Input data calon siswa | Form publik & panitia ditutup |
| Seleksi | Penjadwalan + penilaian | Field jadwal/hasil dikunci |
| Pengumuman | Menandai hasil | Status tidak dapat diubah ke lulus/tidak lulus |
| Daftar ulang | Konfirmasi kedatangan & berkas | Tombol konfirmasi disembunyikan |
| Pembayaran | Validasi pelunasan administrasi | Form pembayaran & update status dinonaktifkan |

> Status seleksi, pengumuman, daftar ulang, dan pembayaran kini dapat diperbarui langsung dari daftar pendaftar (oleh admin maupun guru penanggung jawab) ketika tahapan terkait diaktifkan.

## 7. Migrasi ke Siswa Aktif
- Admin memilih periode + tahun ajaran + rombel tujuan.
- Sistem mempersiapkan data minimal (`nama`, `jenis_kelamin`, `ttl`, kontak, orang tua).
- Setelah membuat entri `siswa`, catat referensi `siswa_id` di `ppdb_pendaftar` dan kunci status akhir.

## 8. Tahapan Implementasi
1. **Skema & Model**  
   Tambahkan tabel, model, dan gate dasar sehingga modul PPDB dapat dibootstrap.
2. **Halaman Admin Periode**  
   CRUD periode + assignment guru + toggle tahapan.
3. **Registrasi & Monitoring**  
   Form publik, input manual, daftar pendaftar, update status dasar.
4. **Tahapan Lanjutan**  
   Seleksi, pengumuman, daftar ulang, pembayaran (beserta toggling UI).
5. **Integrasi Siswa Aktif**  
   Wizard finalisasi + sinkronisasi akun siswa.

## 9. Status Pekerjaan (per commit ini)
- Skema `ppdb_*` beserta model PHP dasar sudah tersedia.
- Menu panel admin untuk membuat/mengubah periode beserta assignment guru dan toggle tahapan aktif sudah bisa digunakan.
- Admin dan guru penanggung jawab dapat memonitor daftar pendaftar; admin dapat menambah pendaftar manual.
- Penanggung jawab dapat menjadwalkan seleksi akademik, mencatat status, dan menyimpan nilai selama tahap seleksi diaktifkan.
- Formulir pendaftaran publik sudah menerima input dasar calon siswa dan menghasilkan kode pendaftaran. Tautan publik dapat memakai kode periode (misal `PPDB2025`) agar mudah diingat, atau token acak untuk distribusi terbatas.
- Wizard migrasi tersedia untuk memindahkan calon siswa berstatus diterima ke tabel `siswa` beserta pembuatan akun otomatis.

Dengan struktur ini, setiap fase dapat diuji terpisah sehingga troubleshooting menjadi lebih mudah.

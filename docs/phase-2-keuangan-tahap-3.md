# Dokumentasi Fase Keuangan – Tahap 3 (Implementasi Modul)

Tahap ini mengonversi desain dan fondasi teknis ke implementasi modul keuangan. Fokus utamanya pada pembuatan controller, route, view, dan integrasi service untuk bendahara, siswa, guru, dan kepala sekolah.

## 1. Struktur Modul

- Buat direktori `modules/Finance` dengan struktur:
  ```
  modules/Finance/
    module.json
    routes/
      web.php
    Controllers/
      Bendahara/
      Siswa/
      Guru/
      KepalaSekolah/
    Views/
      bendahara/
      siswa/
      guru/
      kepsek/
    ViewModels/ (opsional, untuk menyusun data kompleks)
  ```
- `module.json` mendefinisikan nama modul, versi, serta lokasi file route.
- Semua controller extends `App\Controllers\Controller` untuk memanfaatkan helper view/redirect.

## 2. Routing & Middleware

- Tambah rute di `modules/Finance/routes/web.php` menggunakan prefix:
  - `/keuangan/bendahara` → khusus guru level 1 (bendahara).
  - `/keuangan/siswa` → role `siswa`.
  - `/keuangan/guru` → role `guru`.
  - `/keuangan/kepala-sekolah` → role kepala sekolah (mapping dari tahun ajaran aktif).
- Setiap rute memanggil middleware guard:
  - Implementasikan `FinanceGate::ensureRole($role)` dan `FinanceGate::ensureBendahara()` di `app/Support/FinanceGate.php`.
  - Guard memverifikasi sesi user (`Auth::user()`), memastikan guru level 1 via `TeacherAcademicPosition`.
  - Kepala sekolah dicek menggunakan `SchoolYear::getActive()` → `kepala_sekolah_id`.
- Gunakan grouping route agar middleware tidak dinyatakan berulang.

## 3. Controller & Tugas

### 3.1 Bendahara
- `DashboardController`: rangkuman tagihan aktif, pembayaran menunggu verifikasi, saldo kas, tabungan, kasbon pending.
- `TagihanController`: CRUD tagihan; endpoint untuk generate tagihan massal, melihat daftar cicilan.
- `PembayaranController`: verifikasi pembayaran, upload bukti, cancel, generate bukti digital.
- `TabunganController`: kelola profil tabungan dan catat setor/tarik.
- `KasbonController`: verifikasi pengajuan guru, jadwalkan cicilan, pencairan.
- `DanaKegiatanController`: verifikasi pengajuan, atur realisasi belanja.
- `HonorController`: verifikasi slip honor dan mendorong ke approval kepala sekolah.
- `LaporanController`: export pemasukan, pengeluaran, tabungan, kasbon, dana kegiatan.

### 3.2 Siswa
- `DashboardController`: daftar tagihan aktif, riwayat pembayaran, progress tabungan.
- `TagihanController`: detail tagihan, riwayat cicilan, unduh bukti pembayaran.
- `TabunganController`: histori mutasi tabungan dan grafik sederhana.

### 3.3 Guru
- `DashboardController`: status kasbon, dana kegiatan, honor terbaru.
- `KasbonController`: ajukan kasbon, lihat status, upload bukti cicilan jika diperlukan.
- `DanaKegiatanController`: ajukan dana, unggah dokumen pendukung, pantau approval dan realisasi.
- `HonorController`: lihat slip honor dan riwayat pembayaran.

### 3.4 Kepala Sekolah
- `DashboardController`: ringkasan saldo, pemasukan/pengeluaran, pengajuan pending.
- `ApprovalController`: daftar permohonan kasbon/dana/honor, aksi ACC/Tolak.
- `LaporanController`: filter laporan per periode & kategori, tampilkan grafik.

Controller memanfaatkan service di `app/Services/Finance` untuk logika bisnis utama, menjaga controller tetap tipis.

## 4. View & UX

- Gunakan layout `resources/layouts/admin.php`.
- Sediakan komponen partial untuk tabel, kartu ringkasan, filter. Tempatkan di `modules/Finance/Views/components`.
- Guidelines desain:
  - Tampilan bendahara prioritas untuk efisiensi (table master-detail, badge status, modal verifikasi).
  - Siswa diberi tampilan ringkas dengan kartu tagihan, chart tabungan (gunakan Chart.js CDN).
  - Guru dengan tab navigasi kasbon/dana/honor.
  - Kepala sekolah fokus pada insight visual (line chart pemasukan, stacked bar pengeluaran).
- Siapkan template bukti pembayaran/slip honor di `modules/Finance/Views/exports`.
- Gunakan tailwind utility yang sudah tersedia; perhatikan dark mode.

## 5. Integrasi Service & Workflow

- Hubungkan controller dengan service tahap 2:
  - Tagihan → `BillingService`, `PaymentService`.
  - Tabungan → `SavingsService`.
  - Kasbon → `LoanService`.
  - Dana Kegiatan → `ActivityFundService`.
  - Honor → `HonorService`.
  - Approval → `ApprovalService`.
  - Laporan → `CashflowService` + query aggregator.
- Pastikan setiap perubahan status memicu:
  - Update arus kas (`CashflowService`).
  - Tulis log approval (`FinanceApproval`).
  - Kirim notifikasi (flash/in-app; siapkan hook ke email/WA).
- Implementasikan helper `NotificationService` (opsional) atau gunakan fungsionalitas existing jika ada.

## 6. File Upload & Penyimpanan

- Bukti transaksi simpan di `storage/keuangan/{tahun}/{kategori}`.
- Gunakan helper `Storage::putFile()` jika tersedia, atau buat utilitas di `app/Support/Storage`.
- Validasi tipe file (`jpg`, `png`, `pdf`) dan size maksimal (konfigurasi di `app/Config/filesystems.php` atau file baru `finance.php`).
- Beri nama file unik (`kode_transaksi` + timestamp).

## 7. Testing & QA

- Buat checklist manual:
  - `docs/tests/finance-bendahara.md`
  - `docs/tests/finance-siswa.md`
  - `docs/tests/finance-guru.md`
  - `docs/tests/finance-kepsek.md`
- Pengujian utama:
  - Tagihan massal → pembayaran parsial → verifikasi.
  - Tabungan setor/tarik → saldo kumulatif.
  - Kasbon ajukan → approval berlapis → cicilan.
  - Dana kegiatan → realisasi > export laporan.
  - Honor → slip terunduh setelah ACC.
  - Approval oleh kepsek menutup workflow.
- Logging error: pastikan try-catch pada service mencatat ke log (`storage/logs/finance.log`).

## 8. Rollout & Migrasi Data

- Jalankan migrasi dan seeder tahap 2 di lingkungan staging.
- Import data tagihan awal (jika ada) melalui script migrasi manual atau CSV importer (opsional, modul `ImportController`).
- Latih bendahara menggunakan modul dengan data dummy sebelum go-live.
- Siapkan SOP koreksi transaksi salah input (membatalkan pembayaran, rollback cicilan).
- Dokumentasi user disimpan di `docs/manual/finance/`.

## 9. Dokumentasi & Tindak Lanjut

- Setiap controller baru dokumentasikan endpointnya dalam `docs/api/finance.md` (struktur: URL, method, parameter, guard).
- Update `README.md` modul dengan langkah instalasi/config.
- Tahap berikut (Tahap 4) fokus ke tuning performa, notifikasi tambahan, dan integrasi laporan PDF.

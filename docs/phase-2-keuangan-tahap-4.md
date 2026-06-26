# Dokumentasi Fase Keuangan – Tahap 4 (Stabilisasi & Optimalisasi)

Tahap akhir fase keuangan memusatkan perhatian pada peningkatan performa, keandalan, monitoring, serta fitur tambahan setelah modul utama berjalan. Fokus utamanya memastikan modul siap dipakai di produksi dan mudah dipelihara.

## 1. Tujuan Fase

- Menjamin performa dan scalability modul keuangan saat memproses data besar (tagihan massal, laporan periode panjang).
- Memperkuat keamanan, logging, dan audit trail.
- Menambah kanal notifikasi dan automasi rutin (reminder tagihan/cicilan/honor).
- Menyediakan export laporan ke format PDF/CSV siap audit.
- Menetapkan SOP backup, restore, dan maintenance berkala.

## 2. Optimasi Performa

- **Query Profiling**: gunakan `EXPLAIN` pada query berat (tagihan massal, laporan arus kas) → tambahkan indeks tambahan jika diperlukan.
- **Caching**: simpan ringkasan dashboard (saldo kas, tagihan aktif) di cache 5–10 menit menggunakan helper sederhana (`FinanceCache` di `storage/cache/finance`). Gunakan invalidasi saat transaksi baru tercatat.
- **Pagination**: pastikan daftar tagihan/pembayaran/kasbon menggunakan pagination dan filter (tanggal, status, kategori).
- **Batch Processing**: untuk tagihan massal, implementasikan queue sederhana atau batch insert transaction untuk menghindari timeout.
- **Lazy Loading**: hindari query berulang di view; manfaatkan ViewModel atau service aggregator.

## 3. Monitoring & Logging

- Implementasi logging khusus:
  - Buat channel `finance` di `app/Support/Logger.php` (jika belum ada) → simpan di `storage/logs/finance.log`.
  - Log setiap perubahan status kritis (verifikasi pembayaran, ACC kasbon/dana/honor).
  - Catat error upload bukti atau kegagalan integritas data.
- Tambahkan `FinanceAuditTrail` (tabel atau JSON di kolom `catatan_histori`) untuk mencatat before/after status, user, timestamp.
- Siapkan alert sederhana: email/SMS ke bendahara jika ada error batch atau saldo kas negatif.

## 4. Notifikasi & Reminder

- Buat service `FinanceNotificationService`:
  - Trigger notifikasi in-app (flash) dan eksternal (email/WA gateway jika disediakan).
  - Reminder tagihan sebelum jatuh tempo, pengingat cicilan kasbon, honor tersedia.
- Jadwalkan cron (menggunakan scheduler OS atau tools PHP sederhana) untuk menjalankan reminder harian.
- Catat pengiriman notifikasi di tabel `finance_notifications` (opsional) agar ada jejak.

## 5. Keamanan & Hak Akses

- Review middleware/module guard untuk memastikan:
  - Hanya bendahara aktif yang bisa mengakses menu bendahara.
  - Guru tanpa jabatan level 1 tidak bisa masuk modul bendahara.
  - Kepala sekolah yang sudah tidak menjabat (tahun ajaran berganti) otomatis kehilangan akses.
- Tambahkan rate limiter untuk aksi sensitif (upload bukti, submit pengajuan).
- Validasi file upload tambahan: scan mime type, sanitasi nama file, batasi ukuran.
- Audit permission `Auth::user()`; pastikan session invalidation bekerja saat role berubah.

## 6. Laporan & Export

- Integrasikan layanan PDF exist (`app/Libraries/Fpdf`) untuk:
  - Bukti pembayaran siswa.
  - Slip honor guru.
  - Laporan tabungan dan arus kas per periode.
- Sediakan opsi export CSV/Excel untuk laporan kas dan tabungan.
- Buat template print-friendly di `modules/Finance/Views/print` menggunakan `resources/layouts/print.php`.
- Tambahkan watermark digital signature bila `tahun_ajaran.digital_signature_enabled` aktif.

## 7. Backup & Recovery

- SOP backup harian database (dump MySQL) dan file bukti (`storage/keuangan`).
- Simpan backup di lokasi terpisah (cloud/offsite) dengan retention minimal 30 hari.
- Uji restore berkala, dokumentasikan langkah di `docs/manual/finance/backup-restore.md`.
- Siapkan script maintenance untuk membersihkan file bukti yang tidak terpakai (orphan).

## 8. Testing & Quality Assurance

- Tambah pengujian otomatis (jika memungkinkan):
  - Unit test untuk service keuangan (perhitungan cicilan, saldo tabungan).
  - Feature test untuk workflow approval.
- Lengkapi checklist manual (lanjutan Tahap 3) dengan skenario stress test (1000 siswa, 12 bulan tagihan).
- Smoke test setiap rilis: verifikasi login, dashboard, transaksi baru, export laporan.
- Monitoring pasca deploy: pantau log error 24 jam pertama.

## 9. Dokumentasi & Pelatihan

- Update dokumentasi user (`docs/manual/finance/`) dengan panduan notifikasi & export.
- Buat FAQ troubleshooting umum (tagihan double, bukti gagal upload, cicilan tidak muncul).
- Adakan pelatihan untuk bendahara dan kepala sekolah tentang fitur reminder & audit trail.
- Catat release note di `docs/changelog/finance.md` setiap update modul.

## 10. Backlog & Ekstensi

- Integrasi pembayaran online (gateway virtual account/QRIS).
- Dashboard mobile/responsive khusus siswa/guru.
- Integrasi dengan sistem akuntansi eksternal (export jurnal umum).
- Analitik lanjutan: forecast pemasukan, analisis tren bayar tepat waktu.
- Otomatisasi pengenalan bukti pembayaran (OCR) dan rekonsiliasi bank.

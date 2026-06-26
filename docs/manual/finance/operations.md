# Operasional Modul Keuangan

## 1. Menjalankan Reminder
- Scheduler dapat memanggil perintah CLI:
  ```bash
  php public/index.php finance:reminder
  ```
- Perintah akan mengeksekusi:
  - `ReminderService::dispatchBillingReminders()` → log daftar tagihan mendekati jatuh tempo.
  - `ReminderService::dispatchLoanInstallmentReminders()` → log cicilan kasbon jatuh tempo 3 hari lagi.
  - `ReminderService::dispatchHonorReminders()` → placeholder untuk notifikasi honor.
- Hasil log tersimpan pada `storage/logs/finance.log`.

## 2. Reset Cache Dashboard
Jika angka dashboard stagnan setelah transaksi:
1. Hapus file cache keuangan: `rm storage/cache/finance_*`.
2. Atau jalankan ulang perintah CLI di atas untuk memicu refresh otomatis.

## 3. Lokasi Log & Bukti
- Log audit keuangan: `storage/logs/finance.log`.
- Bukti transaksi (pembayaran/tabungan/dana): `storage/keuangan/`.
- Rotasi log disarankan saat ukuran > 10 MB.

## 4. Backup Rekomendasi
- Backup database harian (dump SQL) dan folder `storage/keuangan`.
- Simpan salinan minimal 30 hari di lokasi terpisah.
- Uji restore tiap 3 bulan dan catat hasilnya.

## 5. Tindak Lanjut
- Integrasi notifikasi eksternal dapat menggantikan placeholder log pada ReminderService.
- Jika API eksternal diaktifkan, gunakan referensi `docs/api/finance-integration.md` untuk scope/token.

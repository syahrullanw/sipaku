# Cron Job Pengisian Tagihan Rutin

## Tujuan
Pastikan tagihan rutin (`rutin_tipe` = `mingguan`/`bulanan`) dijadwalkan secara otomatis setiap pagi ketika `rutin_jadwal_berikutnya` sampai pada tanggal hari ini.

## Perintah Cron yang tersedia
Skenario standar menjalankan command berikut dari direktori proyek:

```bash
cd /Applications/MAMP/htdocs/siakad
php public/index.php finance:generate-tagihan-rutin
```

Perintah CLI di atas memanggil `RecurringBillingService::generateDue()` dan mencetak jumlah siklus tagihan yang digenerate hari ini. Untuk menghindari ketergantungan langsung pada web root, ada helper script kecil di `scripts/recurring-billing-cron.php` yang bisa dipanggil langsung:

```bash
php scripts/recurring-billing-cron.php
```

## Contoh entry crontab
```
0 7 * * * cd /Applications/MAMP/htdocs/siakad && php public/index.php finance:generate-tagihan-rutin >> storage/logs/finance-cron.log 2>&1
```
Perintah di atas dijalankan pukul 07:00 setiap hari dan menulis output ke `storage/logs/finance-cron.log`.

## Fallback manual Bendahara
Jika otomatisasi gagal (misal server cron mati), bendahara dapat tekan tombol **Generate Tagihan Rutin** pada halaman \*Tagihan Siswa\* (`/keuangan/bendahara/tagihan`). Tombol tersebut menjalankan perintah yang sama dan memunculkan notifikasi success/warning di layar.

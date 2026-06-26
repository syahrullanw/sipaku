# Finance Module Changelog

## 0.2.0 – Tahap 4
- Tambah logging channel `finance` dengan output ke `storage/logs/finance.log`.
- Implementasi cache ringkas dashboard bendahara & kepala sekolah (`FinanceCache`).
- CLI command `php public/index.php finance:reminder` untuk menjalankan reminder tagihan/kasbon/honor (placeholder log).
- Dashboard bendahara dan kepsek memanfaatkan cache (auto invalidasi saat transaksi/approval/kasbon diproses).
- Tambah dokumentasi operasional & hardening, termasuk panduan analytics.

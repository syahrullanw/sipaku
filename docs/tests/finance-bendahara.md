# Manual Test – Modul Keuangan (Bendahara)

## Persiapan
- Login sebagai bendahara (guru level 1 atau role `bendahara`).
- Pastikan data sekolah tahun ajaran aktif telah di-set.
- Import seeder `database/seeders/finance_demo.sql` bila belum ada kategori tagihan dan saldo awal.

## Skenario
1. **Dashboard Ringkasan**
   - Buka `/keuangan/bendahara`.
   - Verifikasi kartu saldo kas, total tagihan, piutang, dan tabungan muncul tanpa error.
   - Pastikan tabel arus kas dan pembaruan status pending memuat data bila tersedia.
2. **Daftar Pembayaran Menunggu Verifikasi**
   - Buka `/keuangan/bendahara/pembayaran`.
   - Cek daftar pending menampilkan nama siswa, tagihan, nominal.
   - Klik `Setujui` → status pembayaran berubah menjadi `disetujui`, piutang berkurang, entri arus kas terbuat.
   - Klik `Tolak` pada pembayaran lain → status menjadi `ditolak`, catatan tersimpan.
3. **Validasi Hak Akses**
   - Logout, login sebagai user non bendahara.
   - Akses `/keuangan/bendahara` → sistem menolak dan mengarahkan ke dashboard dengan pesan error.

Catat hasil test, capture pesan error bila terjadi, dan laporkan ke tim pengembang.

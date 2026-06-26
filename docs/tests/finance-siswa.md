# Manual Test – Modul Keuangan (Siswa)

## Persiapan
- Login sebagai siswa yang memiliki tagihan dan tabungan aktif.
- Pastikan bendahara telah mencatat minimal satu tagihan dan tabungan untuk siswa.

## Skenario
1. **Dashboard Siswa**
   - Buka `/keuangan/siswa`.
   - Pastikan daftar tagihan aktif menampilkan judul, jatuh tempo, total, dan sisa.
   - Periksa badge status (`menunggu_verifikasi`, `cicilan_berjalan`, dll.) sesuai data.
2. **Riwayat Pembayaran**
   - Lihat bagian “Pembayaran Terakhir”.
   - Pastikan status pembayaran dan nominal sesuai dengan hasil verifikasi bendahara.
3. **Tabungan**
   - Cek saldo tabungan terakhir dan riwayat transaksi.
   - Setelah bendahara menambahkan transaksi baru, refresh halaman dan pastikan saldo ter-update.
4. **Hak Akses**
   - Login sebagai siswa tanpa data tabungan → halaman menampilkan pesan tabungan belum aktif tanpa error.

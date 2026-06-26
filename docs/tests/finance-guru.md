# Manual Test – Modul Keuangan (Guru)

## Persiapan
- Login sebagai guru yang memiliki kasbon, pengajuan dana, dan honor.
- Pastikan bendahara sudah menambahkan contoh data kasbon dan honor untuk guru terkait.

## Skenario
1. **Dashboard Guru**
   - Buka `/keuangan/guru`.
   - Verifikasi daftar kasbon menampilkan kode, nominal, saldo terhutang, dan tenor.
   - Pastikan jadwal cicilan muncul sesuai entri di `kasbon_cicilan`.
2. **Pengajuan Dana Kegiatan**
   - Cek tabel “Pengajuan Dana Kegiatan” apakah menampilkan status terkini (`diajukan`, `disetujui`, dsb.).
   - Setelah bendahara mengubah status, refresh halaman dan pastikan badge status ikut berubah.
3. **Honor Guru**
   - Pastikan slip honor menampilkan bruto, potongan, dan nominal diterima dengan benar.
4. **Hak Akses**
   - Login sebagai guru tanpa data kasbon/honor → halaman tetap tampil tanpa error dan menampilkan pesan kosong.

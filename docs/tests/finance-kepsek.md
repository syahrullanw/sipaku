# Manual Test – Modul Keuangan (Kepala Sekolah)

## Persiapan
- Login sebagai akun kepala sekolah (`role = kepala_sekolah`).
- Pastikan terdapat permohonan kasbon/dana/honor dengan status `menunggu` pada `keuangan_approval`.

## Skenario
1. **Dashboard Ringkasan**
   - Buka `/keuangan/kepala-sekolah`.
   - Validasi kartu saldo kas, pemasukan/pengeluaran bulanan, dan jumlah pending approval.
   - Pastikan daftar sumber pemasukan terbesar menampilkan kategori beserta nominal.
2. **Daftar Persetujuan**
   - Buka `/keuangan/kepala-sekolah/approval`.
   - Pastikan setiap permohonan menampilkan detail kode/nama/nominal.
   - Klik `Setujui` untuk satu permohonan → status pada tabel asal berubah (misal `kasbon_guru.status = disetujui`) dan approval hilang dari daftar.
   - Klik `Tolak` pada permohonan lain dengan catatan → status berubah menjadi `ditolak` dan catatan tersimpan.
3. **Hak Akses**
   - Login sebagai pengguna non kepala sekolah dan akses URL di atas → sistem menolak dengan redirect ke dashboard.

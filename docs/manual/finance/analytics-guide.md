# Panduan Dashboard Keuangan

## 1. Akses
- Bendahara: `/keuangan/bendahara` → ringkasan kas, tagihan, piutang, approval, arus kas terbaru.
- Kepala sekolah: `/keuangan/kepala-sekolah` → saldo kas, pemasukan/pengeluaran bulanan, sumber pemasukan terbesar, status kasbon.
- Siswa/Guru: `/keuangan/siswa` atau `/keuangan/guru` untuk ringkasan pribadi.

## 2. Komponen Dashboard Bendahara
| Komponen | Deskripsi |
| --- | --- |
| Kartu Statistik | Saldo kas, total tagihan, total piutang, total tabungan aktif. |
| Pembayaran Pending | Daftar pembayaran siswa yang menunggu verifikasi. |
| Approval Queue | Kasbon/dana/honor yang menunggu ACC kepala sekolah. |
| Arus Kas Terbaru | 10 transaksi kas terakhir beserta saldo setelah transaksi. |

Tips: jika data tidak berubah setelah verifikasi transaksi, kosongkan cache finance dengan menghapus file `storage/cache/finance_*`.

## 3. Dashboard Kepala Sekolah
- Kartu: saldo kas, pemasukan/pengeluaran bulan berjalan, jumlah approval pending.
- “Sumber Pemasukan Terbesar”: top 5 kategori pemasukan bulanan.
- “Status Kasbon Guru”: komposisi kasbon (disetujui, menunggu, lunas, ditolak).
- Tombol “Persetujuan Keuangan” → daftar detail permohonan yang harus di-ACC.

## 4. Drill-down & Data Sumber
- Total piutang = `SUM(tagihan_item.sisa_nominal)` untuk tagihan aktif.
- Pemasukan/pengeluaran bulanan diambil dari `arus_kas` dengan filter tanggal bulan berjalan.
- Riwayat tabungan siswa menampilkan 20 transaksi terakhir dari `tabungan_transaksi`.

## 5. Checklist Monitoring
- Relevansi data: pastikan saldo kas tidak negatif tanpa alasan (cek arus kas).
- Pending approval > 3 hari → follow-up ke bendahara.
- Bandingkan total pemasukan modul dengan rekening bank (rekonsiliasi).

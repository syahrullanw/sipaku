# Dokumentasi Fase Keuangan – Tahap 1 (Discovery & Desain)

Tahap ini memetakan kebutuhan bisnis, alur, serta rancangan basis data awal untuk modul keuangan SIAKAD. Outputnya menjadi referensi implementasi teknis pada tahap berikut.

## 1. Lingkup & Sasaran

- Menyatukan proses pembayaran siswa, pengelolaan tabungan, kasbon guru, dana kegiatan, dan honorarium dalam satu modul terintegrasi.
- Menentukan batas peran: siswa & guru sebagai pelaku transaksi, bendahara (guru level 1) sebagai pengelola, kepala sekolah sebagai approver, admin sebagai pengatur hak akses.
- Menyusun standar status transaksi, dependensi terhadap master data (tahun ajaran, kelas, jabatan), dan integrasi notifikasi internal.
- Menetapkan struktur tabel inti dan dependensi antar entitas untuk disiapkan pada migrasi tahap 2.
- Menentukan kebutuhan laporan dan audit trail minimum sebelum modul dirilis.

## 2. Peran & Kebutuhan

### Siswa
- Melihat dashboard tagihan aktif (SPP,infaq mingguan komite, kegiatan, biaya insidental) beserta jatuh tempo & status.
- Melihat riwayat pembayaran dan bukti bayar digital.
- Mengajukan pembayaran parsial (cicilan) dengan perhitungan sisa otomatis.
- Memantau tabungan yang disetorkan via bendahara lengkap dengan histori transaksi.

### Guru
- Mengajukan kasbon/pinjaman dengan nominal, tujuan, dan tenor pengembalian.
- Mengajukan dana kegiatan (ekstrakurikuler, prakerin, kurikulum, TU) termasuk rincian kebutuhan serta lampiran dokumen.
- Melihat status pengajuan (menunggu verifikasi bendahara, menunggu ACC kepala sekolah, disetujui, ditolak).
- Menerima rincian honorarium bulanan (jabatan struktural dan jam tambahan) plus slip digital jika sudah diverifikasi.

### Bendahara (Guru dengan jabatan akademik level 1)
- aktif nan nonaktifkan SPP
- Dashboard verifikasi pembayaran siswa dan pencatatan tabungan.
- Membuat tagihan massal ataupun per siswa, menetapkan Apakah biayanya mingguan atau bulanan, menetapkan skema cicilan dan jatuh tempo.
- Memverifikasi pembayaran, mengunggah bukti transaksi, serta mengelola status Lunas/Cicilan.
- Mengelola kas keluar: mencairkan dana kegiatan, menyalurkan honor, dan mengatur penarikan kasbon.
- Mengelola approval queue: menandai verifikasi internal sebelum diteruskan ke kepala sekolah.
- Mengakses laporan pemasukan, pengeluaran, saldo kas, tabungan siswa per kelas, dan distribusi dana.

### Kepala Sekolah
- Melihat ringkasan saldo kas, pemasukan, pengeluaran, dan pengajuan yang menunggu ACC.
- Memberikan keputusan ACC/Tolak pada pengajuan kasbon & dana kegiatan (setelah verifikasi bendahara).
- Meninjau laporan per periode (harian/bulanan/tahunan) dan kategori dana (BOS, komite, kegiatan, honor, dll).
- Menjaga audit trail keputusan untuk keperluan pelaporan ke yayasan/pengawas.

### Admin Sistem
- Menetapkan bendahara aktif (mapping ke `guru_jabatan_akademik` level 1) dan fallback apabila jabatan berganti.
- Menyesuaikan kategori tagihan/dana, aturan penomoran, serta template notifikasi.
- Mengatur hak akses menu/route agar modul hanya tampil untuk peran relevan.

## 3. Alur Bisnis Prioritas

### 3.1 Tagihan & Pembayaran Siswa
1. Bendahara membuat tagihan (single atau massal) dan mengatur kategori, nominal, jatuh tempo, opsi cicilan.
2. Tagihan muncul di dashboard siswa. Sistem menghitung outstanding per siswa.
3. Siswa melakukan pembayaran ke bendahara → bendahara mencatat transaksi (cash/transfer) dan unggah bukti.
4. Sistem meng-update status tagihan (`draft` → `aktif` → `menunggu_verifikasi` → `lunas` / `cicilan_berjalan`).
5. Bukti pembayaran digital otomatis tersedia di portal siswa (PDF/HTML).
6. Pencatatan otomatis ke laporan pemasukan dan arus kas periode berjalan.

### 3.2 Tabungan Siswa
1. Bendahara membuat profil tabungan per siswa atau batch per kelas.
2. Setiap setor/tarik dicatat sebagai transaksi tabungan dengan saldo kumulatif.
3. Siswa melihat progres tabungan dan riwayat mutasi di dashboard.
4. Laporan tabungan dapat difilter per siswa, kelas, atau periode; rekap akhir tahun bisa diekspor.

### 3.3 Kasbon/Pinjaman Guru
1. Guru mengajukan kasbon dengan nominal, alasan, bukti pendukung.
2. Bendahara memeriksa dan memverifikasi kelengkapan → status `menunggu_acc_kepala`.
3. Kepala sekolah menyetujui/menolak. Jika setuju, sistem menjadwalkan cicilan & tanggal jatuh tempo.
4. Bendahara mencairkan dana dan mencatat bukti pencairan.
5. Pengembalian dicatat sebagai cicilan yang mengurangi saldo kasbon hingga lunas.
6. Notifikasi dikirim ke guru pada setiap perubahan status.

### 3.4 Dana Kegiatan
1. Guru mengisi pengajuan dana kegiatan (kategori, rincian kebutuhan, estimasi anggaran, lampiran).
2. Bendahara verifikasi (mengecek kesesuaian anggaran) → status `menunggu_acc_kepala`.
3. Kepala sekolah memutuskan ACC/Tolak. Jika ACC, dana dicairkan sesuai mekanisme (transfer/tunai).
4. Bendahara mencatat realisasi penggunaan dana beserta bukti (nota, tanda tangan penerima).
5. Laporan menampilkan perbandingan anggaran vs realisasi untuk tiap kegiatan.

### 3.5 Honorarium Guru
1. Bendahara mengimpor/menginput draft honor (sumber master: jabatan struktural, jam tambahan).
2. Bendahara memverifikasi slip → status `menunggu_acc_kepala`.
3. Kepala sekolah menyetujui. Setelah disetujui, slip honor muncul di dashboard guru dan dapat diunduh.
4. Pembayaran honor dicatat sebagai pengeluaran kas dan mengurangi saldo kas sesuai kategori dana.

## 4. Entitas & Relasi Awal

Tabel baru direncanakan mengikuti konvensi penamaan eksisting (snake case, bahasa Indonesia). Kolom `*_id` merujuk ke tabel yang sudah ada (misal `siswa.id`, `guru.id`, `tahun_ajaran.id`).

- **tagihan** – header tagihan (kategori, tahun ajaran, kelas opsional, nominal total, tipe penagihan, jatuh tempo, status).
- **tagihan_item** – rincian per siswa (tagihan_id, siswa_id, nominal, status, sisa_tagihan, catatan).
- **tagihan_cicilan** – jadwal cicilan opsional per item (tanggal_jatuh_tempo, nominal).
- **pembayaran** – transaksi pembayaran (tagihan_item_id, metode, nominal_bayar, sisa_setelah_bayar, bukti_path, diverifikasi_oleh).
- **tabungan_siswa** – saldo per siswa per tahun ajaran (diawasi bendahara).
- **tabungan_transaksi** – mutasi tabungan (tabungan_id, jenis `setor/ambil`, nominal, bukti, dicatat_oleh).
- **kasbon_guru** – pengajuan kasbon (guru_id, tahun_ajaran_id, nominal, tujuan, status, tanggal_pencairan, saldo_terhutang).
- **kasbon_cicilan** – jadwal dan realisasi cicilan kasbon.
- **dana_kegiatan** – pengajuan dana kegiatan (guru_id, kategori, uraian, estimasi, status, tanggal_acc, catatan_penolakan).
- **dana_kegiatan_realisasi** – pencairan dan belanja aktual (dana_kegiatan_id, jenis_pengeluaran, nominal, bukti).
- **honor_guru** – entri honor (guru_id, periode, kategori, nominal_bruto, potongan, nominal_diterima, status).
- **keuangan_approval** – tabel generik untuk melacak approval kepala sekolah (entity_type, entity_id, approver_id, status, tanggal, catatan).
- **arus_kas** – jurnal ringkas pemasukan/pengeluaran (tipe, referensi, nominal, saldo_setelah_transaksi) untuk laporan real-time.

Relasi penting:

- `tagihan` ↔ `tagihan_item` (1:N) ↔ `pembayaran` (1:N).
- `tagihan_item` dapat memiliki banyak `tagihan_cicilan`; cicilan otomatis berubah status ketika pembayaran match nominal.
- `tabungan_siswa` (1:N) `tabungan_transaksi` dengan saldo dihitung kumulatif.
- `kasbon_guru` ↔ `kasbon_cicilan` (1:N) dan terkait dengan `keuangan_approval`.
- `dana_kegiatan` ↔ `dana_kegiatan_realisasi` (1:N) dan approval.
- `honor_guru` membutuhkan approval sebelum jadi slip final.
- `arus_kas` menerima referensi ke semua transaksi (tagihan, tabungan, kasbon, kegiatan, honor) untuk laporan konsolidasi.

## 5. Integrasi & Ketergantungan

- **Master Data**: memakai `siswa`, `guru`, `kelas`, `tahun_ajaran`, `jurusan`. Tagihan bisa difilter berdasarkan kelas & tahun ajaran aktif.
- **Penetapan Bendahara**: memanfaatkan `guru_jabatan_akademik` (level = 1) untuk menentukan siapa yang dapat mengakses modul bendahara. Perlu fallback jika jabatan kosong.
- **Autentikasi**: modul mengikuti sesi dan helper existing (`Auth::user()`). Menu modul ditampilkan berdasar `user.role` dan mapping jabatan.
- **Dokumen Digital**: slip/bukti menggunakan digital signature opsional (`digital_signature_enabled`) memanfaatkan `App\Models\DigitalDocumentSignature`.
- **Notifikasi**: gunakan mekanisme flash/in-app; siapkan hook untuk ekstensi (email/WA) tanpa mengunci vendor.
- **Pelaporan**: modul harus memanfaatkan tanggal transaksi untuk filter `harian/bulanan/tahunan` dan kategori dana.

## 6. Status & Enum yang Diusulkan

- `tagihan.status`: `draft`, `aktif`, `ditutup`, `dibatalkan`.
- `tagihan_item.status`: `menunggu_pembayaran`, `menunggu_verifikasi`, `cicilan_berjalan`, `lunas`, `gagal`, `dibatalkan`.
- `pembayaran.status`: `menunggu_verifikasi`, `ditolak`, `disetujui`.
- `kasbon_guru.status`: `draft`, `diajukan`, `diverifikasi_bendahara`, `menunggu_acc`, `disetujui`, `ditolak`, `lunas`.
- `dana_kegiatan.status`: `draft`, `diajukan`, `diverifikasi_bendahara`, `menunggu_acc`, `disetujui`, `ditolak`, `selesai`.
- `honor_guru.status`: `draft`, `menunggu_verifikasi`, `menunggu_acc`, `disetujui`, `ditolak`, `terbayar`.
- `keuangan_approval.status`: `menunggu`, `disetujui`, `ditolak`.
- `tabungan_transaksi.jenis`: `setor`, `tarik`.

Enum mengikuti pola `ENUM` MySQL seperti di skema sekarang agar konsisten.

## 7. Laporan & Audit Trail

- **Laporan pemasukan/pengeluaran** per kategori dana dengan opsi periode.
- **Laporan saldo kas** realtime (akumulasi dari `arus_kas`).
- **Laporan tabungan siswa** individu & rekap kelas.
- **Laporan kasbon & cicilan** (status, sisa pembayaran).
- **Laporan dana kegiatan** (anggaran vs realisasi, status bukti).
- **Log approval** yang dapat ditelusuri kembali ke pengguna pemberi keputusan & timestamp.
- **Export** minimal CSV/PDF untuk laporan tabungan dan transaksi keuangan.

Audit trail minimal:

- Simpan `created_by`, `verified_by`, `approved_by`, `updated_by` pada tabel transaksi kunci.
- Log perubahan status ke tabel history atau catatan JSON agar troubleshooting mudah.

## 8. Risiko & Catatan Implementasi

- Pastikan semua operasi keuangan dibungkus transaksi database untuk mencegah data out-of-sync.
- Validasi data ganda (misal pembayaran lebih besar dari tagihan) dengan constraint & pengecekan aplikasi.
- Rancang permission granular agar bendahara hanya melihat data pada tahun ajaran aktif jika diperlukan.
- Siapkan mekanisme rollback (membatalkan pembayaran salah input) dengan audit trail jelas.
- Pertimbangkan nominal dengan tipe DECIMAL(15,2) untuk menampung nilai rupiah besar.
- Lampiran bukti simpan di `storage/keuangan/...` dengan validasi tipe file & ukuran.

## 9. Artefak Lanjutan untuk Tahap 2

- Wireframe dashboard per peran (siswa, guru, bendahara, kepala sekolah).
- Spesifikasi endpoint/rute dan middleware akses.
- Draft migrasi SQL mengikuti daftar tabel pada bagian 4.
- Skema notifikasi (template pesan, trigger status).
- Checklist pengujian end-to-end per alur bisnis (tagihan, tabungan, kasbon, dana kegiatan, honor).

Dokumen ini menjadi dasar review produk sebelum coding dimulai. Setelah disetujui, lanjutkan ke Tahap 2 (Fondasi Teknis) dengan menurunkan struktur tabel, model, dan service layer sesuai rancangan di atas.

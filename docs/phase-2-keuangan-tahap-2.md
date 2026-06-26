# Dokumentasi Fase Keuangan – Tahap 2 (Fondasi Teknis)

Tahap ini menerjemahkan hasil discovery ke dalam rencana teknis: struktur database, model, service, serta infrastruktur akses modul keuangan. Seluruh pekerjaan dilakukan sebelum logika UI agar integrasi dan pengujian lebih mudah.

## 1. Tujuan Teknis

- Menyusun skema SQL baru dan perubahan tabel eksisting agar modul keuangan dapat berjalan.
- Menyiapkan model, repository/helper, dan service layer dasar untuk transaksi keuangan.
- Menentukan struktur direktori modul (`modules/Finance`) beserta routing dan middleware akses.
- Mengatur seeding dasar dan helper untuk mengidentifikasi bendahara aktif, enumerasi kategori, dan nomor transaksi.

## 2. Perubahan Database

### 2.1 Konvensi Umum
- Tanggal pakai `DATE`/`DATETIME`, nominal pakai `DECIMAL(15,2)`.
- Status disimpan sebagai `ENUM` dengan nilai yang sudah didefinisikan pada Tahap 1.
- Tambahkan kolom audit: `created_at`, `updated_at`, `created_by`, `updated_by`, `verified_by`, `approved_by` sesuai kebutuhan tabel.
- Setiap berkas migrasi dicatat pada `database/schema.sql`; jika nantinya CLI migrasi aktif, siapkan versi `.php` di `database/migrations`.

### 2.2 Tabel Baru

| Tabel | Kolom Kunci | Deskripsi |
| --- | --- | --- |
| `kategori_tagihan` | `id`, `kode`, `nama`, `tipe` (`rutin/insidental`), `aktif` | Master kategori pembayaran (SPP, komite, seragam, dsb). |
| `tagihan` | `id`, `kode`, `tahun_ajaran_id`, `kategori_id`, `judul`, `deskripsi`, `nominal_total`, `metode_penagihan` (`per_siswa/per_kelas/per_jurusan`), `tanggal_jatuh_tempo`, `status`, `created_by` | Header tagihan. |
| `tagihan_item` | `id`, `tagihan_id`, `siswa_id`, `kelas_id`, `nominal`, `sisa_nominal`, `status`, `notes` | Item per siswa. `kelas_id` duplikasi konteks saat tagihan dibuat. |
| `tagihan_cicilan` | `id`, `tagihan_item_id`, `jatuh_tempo`, `nominal`, `status` | Jadwal cicilan opsional. |
| `pembayaran` | `id`, `tagihan_item_id`, `kode_transaksi`, `tanggal_bayar`, `metode` (`tunai/transfer`), `nominal`, `sisa_setelah`, `status`, `bukti_path`, `diverifikasi_oleh`, `diverifikasi_pada` | Transaksi pembayaran siswa. |
| `tabungan_siswa` | `id`, `siswa_id`, `tahun_ajaran_id`, `saldo_terakhir`, `status` (`aktif/nonaktif`), `catatan` | Rekening tabungan per siswa per tahun. |
| `tabungan_transaksi` | `id`, `tabungan_id`, `kode_transaksi`, `jenis` (`setor/tarik`), `tanggal`, `nominal`, `saldo_setelah`, `bukti_path`, `dicatat_oleh` | Mutasi tabungan. |
| `kasbon_guru` | `id`, `guru_id`, `tahun_ajaran_id`, `kode`, `tanggal_pengajuan`, `nominal_diminta`, `tujuan`, `tenor_bulan`, `status`, `tanggal_acc`, `tanggal_cair`, `saldo_terhutang`, `catatan_penolakan`, `diverifikasi_oleh`, `approved_by` | Pengajuan kasbon/pinjaman. |
| `kasbon_cicilan` | `id`, `kasbon_id`, `jatuh_tempo`, `nominal`, `nominal_terbayar`, `status`, `tanggal_bayar_terakhir`, `dicatat_oleh` | Pelunasan kasbon. |
| `dana_kegiatan` | `id`, `guru_id`, `tahun_ajaran_id`, `kode`, `kategori`, `judul`, `deskripsi`, `estimasi_biaya`, `status`, `tanggal_pengajuan`, `tanggal_acc`, `catatan`, `lampiran_path`, `diverifikasi_oleh`, `approved_by` | Pengajuan dana kegiatan. |
| `dana_kegiatan_realisasi` | `id`, `dana_kegiatan_id`, `kode_transaksi`, `tanggal`, `jenis_pengeluaran`, `nominal`, `bukti_path`, `dicatat_oleh` | Realisasi pencairan/penggunaan dana. |
| `honor_guru` | `id`, `guru_id`, `tahun_ajaran_id`, `periode`, `kategori`, `judul`, `nominal_bruto`, `nominal_potongan`, `nominal_diterima`, `status`, `tanggal_verifikasi`, `tanggal_acc`, `slip_path`, `diverifikasi_oleh`, `approved_by` | Honorarium guru. |
| `keuangan_approval` | `id`, `entity_type`, `entity_id`, `approver_id`, `status`, `tanggal`, `catatan` | Jejak approval kepala sekolah. `entity_type` gunakan ENUM (`kasbon`, `dana_kegiatan`, `honor`, `pembayaran`). |
| `arus_kas` | `id`, `kode_transaksi`, `tipe` (`masuk/keluar`), `sumber` (`tagihan`, `tabungan`, `kasbon`, `kegiatan`, `honor`, `penyesuaian`), `referensi_id`, `tanggal`, `nominal`, `saldo_setelah`, `keterangan`, `dicatat_oleh` | Ringkasan arus kas untuk pelaporan. |

Catatan:
- Tabel lampiran (bukti) cukup menyimpan path relative pada storage. Validasi file dilakukan di controller.
- `kode`/`kode_transaksi` gunakan format `prefix/tahun/bulan/serial` via helper yang akan dibuat pada service.

### 2.3 Perubahan Tabel Eksisting

- `guru_jabatan_akademik`: pastikan kolom `level` digunakan untuk identifikasi bendahara. Tambah hasil migrasi doc saja; tidak perlu ubah struktur jika sudah ada `level`.
- `tahun_ajaran`: tambahkan kolom `saldo_kas_awal` DECIMAL(15,2) dan `saldo_kas_akhir` opsional untuk tracking.
- `users`: pastikan kolom `role` mendukung `bendahara` bila diperlukan; atau gunakan mapping jabatan -> peran via service sehingga tidak perlu menambah enum.
- Buat view/materialized helper (opsional) untuk `bendahara_aktif` yang mengambil guru level 1 pada tahun ajaran aktif.

### 2.4 Index & Constraint

- Index kombinasi:
  - `tagihan_item (tagihan_id, siswa_id)` unik.
  - `pembayaran (tagihan_item_id, status)` untuk pencarian verifikasi.
  - `kasbon_cicilan (kasbon_id, status)`.
  - `dana_kegiatan_realisasi (dana_kegiatan_id)`.
  - `arus_kas (tanggal)` untuk laporan per periode.
- Foreign key cascading mengacu ke entitas induk dengan `ON DELETE CASCADE` ketika entitas induk dihapus sebelum produksi.

## 3. Model & Repository

Simpan model baru di `app/Models` mengikuti pola `Core\Model`.

| Model | Tabel | Catatan |
| --- | --- | --- |
| `BillingCategory` | `kategori_tagihan` | Helper untuk dropdown kategori. |
| `Billing` | `tagihan` | Menyediakan scope per tahun ajaran. |
| `BillingItem` | `tagihan_item` | Hitung `sisa_nominal` otomatis di setter/service. |
| `BillingInstallment` | `tagihan_cicilan` | Mengelola status cicilan. |
| `Payment` | `pembayaran` | Menangani verifikasi dan bukti bayar. |
| `StudentSaving` | `tabungan_siswa` | Saldo dihitung via trigger/service. |
| `StudentSavingTransaction` | `tabungan_transaksi` | |
| `TeacherLoan` | `kasbon_guru` | |
| `TeacherLoanInstallment` | `kasbon_cicilan` | |
| `ActivityFund` | `dana_kegiatan` | |
| `ActivityFundRealization` | `dana_kegiatan_realisasi` | |
| `TeacherHonor` | `honor_guru` | |
| `FinanceApproval` | `keuangan_approval` | |
| `Cashflow` | `arus_kas` | Menyediakan query saldo kumulatif. |

Jika perlu query kompleks, buat repository/service di `app/Repositories/Finance` atau `app/Services/Finance` untuk menjaga controller tetap ramping.

## 4. Service Layer & Helper

- `Finance\TransactionCodeGenerator` – generator kode transaksi berbasis prefix & tanggal.
- `Finance\CashflowService` – catat setiap pemasukan/pengeluaran, hitung saldo kas, sinkronkan dengan `tahun_ajaran`.
- `Finance\BillingService` – membuat tagihan massal, update status item, sinkron cicilan.
- `Finance\PaymentService` – mencatat pembayaran, validasi nominal, unggah bukti, trigger approval bila perlu.
- `Finance\SavingsService` – manage tabungan siswa & saldo akhir.
- `Finance\LoanService` – mengelola kasbon & cicilan, menghitung outstanding.
- `Finance\ActivityFundService` – handle pengajuan kegiatan & realisasi.
- `Finance\HonorService` – menyusun slip honor dari data master (jabatan struktural, jam tambahan).
- `Finance\ApprovalService` – menyimpan keputusan kepala sekolah, memperbarui entitas terkait, kirim notifikasi.

Service ditempatkan di `app/Services/Finance`. Pastikan dependency injection sederhana via constructor atau method static.

## 5. Modul & Routing

- Buat modul baru `modules/Finance/module.json` dengan rute `routes/web.php`.
- Struktur rute:
  - `/keuangan/bendahara/*` – dashboard bendahara (hanya guru level 1).
  - `/keuangan/siswa/*` – halaman siswa.
  - `/keuangan/guru/*` – kasbon, dana kegiatan, honor.
  - `/keuangan/kepala-sekolah/*` – approval & laporan.
- Middleware/guard:
  - Gunakan helper `Auth::user()` kemudian validasi peran:
    - Siswa: `user['role'] === 'siswa'`.
    - Guru: `user['role'] === 'guru'`; cek jabatan level 1 untuk bendahara.
    - Kepala Sekolah: mapping dari `tahun_ajaran.kepala_sekolah_id`.
- Tambah helper `FinanceGate::ensureBendahara()` di `app/Support` untuk reusable guard.

## 6. Seeding & Data Contoh

- Tambah seeder manual (SQL) di `database/seeders/finance_demo.sql` untuk:
  - Kategori tagihan default: SPP, Komite, Seragam, Ekskul.
  - Tagihan contoh (SPP bulan berjalan) untuk beberapa siswa.
  - Tabungan sample.
  - Kasbon dan dana kegiatan sample.
- Seeder memudahkan QA saat menguji modul UI nanti.

## 7. Validasi & Aturan Bisnis

- Tagihan: nominal > 0, jatuh tempo >= hari ini. Metode cicilan wajib punya jadwal lengkap.
- Pembayaran: nominal tidak boleh melebihi `sisa_nominal`; bukti wajib jika metode `transfer`.
- Tabungan: transaksi tarik tidak boleh membuat saldo negatif.
- Kasbon: maksimal outstanding per guru definisikan config (misal `max_kasbon_per_guru` di `app/Config/finance.php`).
- Dana kegiatan: lampiran wajib untuk pengajuan di atas threshold rupiah.
- Honor: periode unik per guru + kategori.
- Approval: satu entitas hanya boleh punya satu record `keuangan_approval` aktif.

## 8. Testing & Observability

- Siapkan skrip uji manual:
  - `tests/manual/finance-billing.md` dsb untuk checklist (opsional di folder `docs/tests`).
- Logging:
  - Catat event penting memakai `Core\Log` (atau buat helper jika belum ada).
  - Audit trail perubahan status simpan ke kolom `catatan` atau tabel histori terpisah jika dibutuhkan di Tahap 3.
- Pertimbangkan menambahkan view SQL untuk laporan (misal `view_laporan_tabungan`) bila query berat.

## 9. Langkah Selanjutnya

Setelah migrasi dan service dasar siap:
1. Implementasikan endpoint API/web (Tahap 3) mengikuti struktur modul.
2. Siapkan form request/pemeriksaan hak akses di controller.
3. Lakukan seeding dan uji integrasi awal sebelum mengerjakan tampilan.

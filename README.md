# SIPAKU SMK

**Sistem Informasi Akademik Sekolah** — Aplikasi manajemen akademik untuk SMK berbasis PHP native dengan arsitektur modular. Mengelola seluruh siklus akademik dari penerimaan siswa baru hingga penerbitan rapor dan sertifikat kelulusan.

![Version](https://img.shields.io/badge/version-1.1.8-blue)
![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-777BB4)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)

## Daftar Isi

- [Fitur Utama](#fitur-utama)
- [Teknologi](#teknologi)
- [Role & Hak Akses](#role--hak-akses)
- [Screenshots](#screenshots)
- [Prasyarat](#prasyarat)
- [Instalasi](#instalasi)
- [Konfigurasi Server](#konfigurasi-server)
- [Cron Jobs](#cron-jobs)
- [CLI Commands](#cli-commands)
- [Struktur Direktori](#struktur-direktori)
- [Database](#database)
- [Backup & Restore](#backup--restore)
- [Update Aplikasi](#update-aplikasi)
- [Troubleshooting](#troubleshooting)
- [Lisensi](#lisensi)

---

## Fitur Utama

### 👤 Manajemen Pengguna & Autentikasi
- Login/logout berbasis sesi dengan proteksi CSRF
- Role-based access control: admin, staff, guru, siswa, bendahara, kepala sekolah
- Reset password mandiri dan oleh admin
- Atur masa berlaku sesi login
- Log aktivitas pengguna (riwayat akses, IP, user agent)
- Mode demo (menyembunyikan data sensitif)

### 📚 Master Data
- **Tahun Ajaran** — Kelola periode akademik, semester aktif, tanggal rapor
- **Jurusan** — Data kompetensi keahlian
- **Guru** — Data diri, riwayat pendidikan, jabatan, status kepegawaian, NUPTK, NPWP
- **Kelas** — Relasi tahun ajaran, jurusan, wali kelas, tingkat, kurikulum (K13/Kurmer)
- **Siswa** — Data lengkap (ortu, alamat, dokumen, prestasi), import/export Excel, foto massal
- **Data Sikap** — Butir sikap spiritual dan sosial
- **Tempat Prakerin** — Data DUDI untuk praktik kerja lapangan
- **Ekstrakurikuler** — Kelola kegiatan ekskul dan pembina
- **Jabatan Akademik** — Struktur organisasi sekolah
- **Profil Sekolah** — Identitas dan alamat sekolah

### 📖 Akademik
- **Mata Pelajaran** — Kelola mapel per tahun ajaran dan jurusan
- **Guru Pengampu** — Penugasan guru ke mata pelajaran dan kelas
- **Jadwal Pelajaran**
  - Input manual
  - Generate otomatis berbasis preferensi (waktu, ruang paralel, kendala)
  - Ekspor jadwal
- **Presensi Siswa** — Scan QR Code atau input manual, rekap presensi
- **Presensi Guru** — Manual oleh tata usaha
- **Penilaian K13**
  - Pengetahuan: input nilai KD, UTS, UAS, nilai akhir + predikat
  - Keterampilan: input nilai KD, nilai akhir + predikat
  - Legger guru (cetak PDF/Excel)
- **Penilaian Kurikulum Merdeka**
  - Tujuan Pembelajaran (TP)
  - Capaian (BB, MB, BSH, SB)
  - Ringkasan capaian akhir
- **P5 (Projek Penguatan Profil Pelajar Pancasila)**
  - Projek, elemen, dimensi
  - Penilaian siswa per elemen
  - Ringkasan capaian akhir
  - Cetak laporan P5
- **Kokurikuler** — Kegiatan, elemen, penilaian, ringkasan

### 🏠 Wali Kelas
- **Nilai Sikap** — Spiritual & sosial
- **Prakerin** — Konfirmasi, penempatan, penilaian
- **Ekstrakurikuler** — Input nilai ekskul siswa
- **Prestasi** — Catat prestasi siswa
- **Catatan** — Catatan wali kelas
- **Upload Nilai** — Upload nilai via Excel + validasi + commit/rollback
- **Legger** — Lihat rekapitulasi nilai
- **Transkrip** — Cetak transkrip nilai individu/massal
- **Status Naik Kelas** — Tentukan naik/tinggal
- **Status Kelulusan** — Tentukan lulus/tidak lulus
- **SKL** — Ajukan Surat Keterangan Lulus

### 💰 Keuangan
- **Kategori Tagihan** — SPP, komite, seragam, dll
- **Tagihan Rutin** — Generate otomatis bulanan (cron job)
- **Tagihan Insidental** — Tagihan satu kali
- **Pembayaran** — Input, cetak slip, histori
- **Kas Umum** — Transaksi kas keluar/masuk
- **Dana Kegiatan** — Pengajuan, realisasi, LPJ
- **Pengadaan/Pembelian** — Barang dan jasa
- **Pinjaman Guru** — Dengan angsuran
- **Honor Guru** — Generate dan cetak slip
- **Tabungan Siswa** — Simpanan dan transaksi
- **Laporan** — Arus kas, tunggakan, penerimaan, rekap
- **Multi Tahun Ajaran** — Saldo akhir dibawa ke tahun berikutnya

### 🏫 PPDB (Penerimaan Peserta Didik Baru)
- Periode pendaftaran
- Pendaftaran online (form publik)
- Verifikasi berkas
- Laporan pendaftar
- Migrasi data ke siswa aktif
- Broadcast WhatsApp ke pendaftar

### 🛠️ UKK (Ujian Kompetensi Keahlian)
- Paket ujian per jurusan
- SKKNI (Standar Kompetensi Kerja Nasional Indonesia)
- DUDI dan asesor eksternal
- Penilaian teori & praktik
- Cetak sertifikat dan SKK Passport

### 📄 Tata Usaha
- **SK Penugasan** — Cetak surat tugas guru, tanda tangan digital
- **Persuratan** — Surat keluar (dengan template, kop, PDF, tanda tangan), surat masuk
- **Presensi Manual** — Cetak daftar hadir dan sampul

### ✍️ Tanda Tangan Digital
- Aktivasi per tahun ajaran
- Request tanda tangan untuk rapor, transkrip, SKL, SK penugasan, surat
- Persetujuan oleh kepala sekolah
- Verifikasi publik via QR code (scan untuk cek keaslian)

### 🖨️ Cetak
- **Rapor** — Tengah semester & akhir, cover, informasi sekolah, biodata, hasil penilaian, prestasi, P5
- **Transkrip** — Per siswa atau massal per kelas
- **Kartu Pelajar** — Dengan verifikasi QR
- **SKL** — Surat Keterangan Lulus
- **Sertifikat UKK**
- **Buku Induk** — Cetak dan export
- **Legger** — PDF dan Excel

### 📱 Integrasi WhatsApp
- Gateway: Fonnte, Waha, atau kustom (JSON/form-data)
- Kirim notifikasi default password
- Kirim notifikasi reset password
- Broadcast PPDB
- Reminder tagihan dan honor (cron job)
- Antrian pesan dengan rate limiting

---

## Teknologi

| Komponen | Teknologi |
|----------|-----------|
| **Backend** | PHP ≥ 8.1 (native OOP, custom framework) |
| **Database** | MySQL 5.7+ / MariaDB 10.4+ |
| **Frontend** | TailAdmin (Tailwind CSS), Alpine.js, ApexCharts, Chart.js |
| **Build Tools** | Webpack, Babel, PostCSS, Prettier |
| **PDF** | FPDF, FPDI, TCPDF |
| **Spreadsheet** | SimpleXLSX, SimpleXLS |
| **QR Code** | phpqrcode |
| **Maps** | jsvectormap |
| **Calendar** | FullCalendar |
| **Datepicker** | Flatpickr |
| **File Upload** | Dropzone |
| **Carousel** | Swiper |

---

## Role & Hak Akses

| Role | Akses Utama |
|------|-------------|
| **Admin** | Full akses: master data, pengguna, pengaturan, backup, log, utilitas |
| **Staff** | Terbatas: master data tertentu, sesuai aturan modul |
| **Kepala Sekolah** | Dashboard, TTD digital, laporan keuangan, approval |
| **Bendahara** | Modul keuangan penuh: tagihan, pembayaran, kas, laporan |
| **Guru** | Nilai, presensi, profil, ekskul, prakerin, P5 |
| **Wali Kelas** | Nilai sikap, upload nilai, legger, transkrip, prestasi, catatan |
| **Siswa** | Lihat nilai, profil, cetak rapor |
| **Kaprodi** | UKK, pengadaan |

---

## Screenshots

> *(Tambahkan screenshot di sini)*

| Halaman | Gambar |
|---------|--------|
| Login | |
| Dashboard | |
| Data Siswa | |
| Jadwal | |
| Penilaian | |
| Keuangan | |
| Rapor | |

---

## Prasyarat

| Komponen | Minimal | Rekomendasi |
|----------|---------|-------------|
| **PHP** | 8.1 | 8.2+ |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `gd`, `zip`, `json`, `fileinfo` | + `imagick`, `bcmath` |
| **MySQL / MariaDB** | 5.7 / 10.4 | 8.0+ / 10.11+ |
| **Web Server** | Apache dengan `mod_rewrite` | Nginx + PHP-FPM |
| **Node.js** (opsional) | 18+ | 20+ LTS |
| **RAM Server** | 512 MB | 1 GB+ |
| **Storage** | 100 MB (aplikasi) + upload | 1 GB+ |

---

## Instalasi

### Cara Cepat (Otomatis)

```bash
git clone https://github.com/syahrullanw/sipaku.git
cd sipaku
chmod +x setup.sh
./setup.sh
```

Script akan memandu Anda mengatur:
1. Koneksi database (host, port, user, password)
2. Nama database (dibuat otomatis)
3. URL aplikasi
4. Akun admin pertama
5. Import schema + migrasi database
6. Build frontend (jika Node.js tersedia)

### Cara Manual

**1. Clone proyek**

```bash
git clone https://github.com/syahrullanw/sipaku.git
cd sipaku
```

**2. Konfigurasi database**

```bash
cp app/Config/database.example.php app/Config/database.php
```

Edit `app/Config/database.php`:
```php
'host' => '127.0.0.1',
'port' => 3306,
'database' => 'sipaku',
'username' => 'root',
'password' => 'password_anda',
```

**3. Konfigurasi aplikasi**

```bash
cp app/Config/app.example.php app/Config/app.php
```

Edit `app/Config/app.php`:
```php
'url' => 'http://localhost:8000',   // domain atau subdirektori
'debug' => false,                    // true untuk development
```

**4. Buat database**

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS sipaku CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**5. Import schema & migrasi**

```bash
mysql -u root -p sipaku < database/schema.sql
for f in database/migrations/*.sql; do
  mysql -u root -p sipaku < "$f"
done
```

**6. Buat akun admin**

```bash
php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"
# Salin hash-nya, lalu:
mysql -u root -p sipaku -e "
INSERT INTO users (name, username, password, email, role, created_at, updated_at)
VALUES ('Administrator', 'admin', '<hash_dari_atas>', 'admin@sekolah.sch.id', 'admin', NOW(), NOW());"
```

**7. Jalankan server development**

```bash
php -S localhost:8000 -t public
```

Akses `http://localhost:8000`, login dengan `admin` / `admin123`.

**8. (Opsional) Build frontend**

```bash
npm install
npm run build
```

---

## Konfigurasi Server

### Apache

Pastikan `mod_rewrite` aktif. File `.htaccess` sudah disediakan di root dan `public/`. Cukup arahkan Document Root ke direktori `public/`:

```apache
<VirtualHost *:80>
    ServerName sipaku.sekolah.sch.id
    DocumentRoot /var/www/sipaku/public
    
    <Directory /var/www/sipaku/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog /var/log/apache2/sipaku-error.log
    CustomLog /var/log/apache2/sipaku-access.log combined
</VirtualHost>
```

### Nginx

```nginx
server {
    listen 80;
    server_name sipaku.sekolah.sch.id;
    root /var/www/sipaku/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~ /(storage|public\/uploads)/ {
        # File statis, log tidak perlu diakses via web
    }
}
```

### Permission

```bash
chown -R www-data:www-data storage/ public/uploads/
chmod -R 755 storage/ public/uploads/
```

### Environment Production

```php
// app/Config/app.php
'env' => 'production',
'debug' => false,
```

---

## Cron Jobs

Beberapa fitur membutuhkan cron job untuk berjalan otomatis:

```cron
# Generate tagihan rutin setiap tanggal 1 bulan 00:05
5 0 1 * * php /var/www/sipaku/public/index.php finance:generate-tagihan-rutin

# Kirim reminder tagihan + honor setiap jam 8 pagi
0 8 * * * php /var/www/sipaku/public/index.php finance:reminder

# Kirim antrian WhatsApp setiap 5 menit
*/5 * * * * php /var/www/sipaku/public/index.php whatsapp:dispatch
```

Atau gunakan script terpisah:

```cron
*/5 * * * * php /var/www/sipaku/scripts/recurring-billing-cron.php
```

---

## CLI Commands

Jalankan dari terminal:

```bash
# Generate tagihan rutin (billing bulanan)
php public/index.php finance:generate-tagihan-rutin

# Kirim reminder keuangan (tagihan, angsuran, honor)
php public/index.php finance:reminder

# Proses antrian WhatsApp (kirim pesan terjadwal)
php public/index.php whatsapp:dispatch

# Kirim 10 pesan WhatsApp saja
php public/index.php whatsapp:dispatch 10
```

---

## Struktur Direktori

```
sipaku/
├── app/
│   ├── Config/              # Konfigurasi (database, app, demo, finance)
│   │   ├── database.example.php
│   │   ├── app.example.php
│   │   ├── demo.php
│   │   └── finance.php
│   ├── Controllers/         # Controller (Auth, Master, Akademik, Keuangan, dll)
│   ├── Libraries/           # Library pihak ketiga (FPDF, FPDI, QR Code, Spreadsheet)
│   ├── Models/              # Model PDO (~80+ model)
│   ├── Repositories/        # Repository pattern
│   ├── Services/            # Business logic & service layer
│   │   ├── Finance/         # Sub-service keuangan (~20 file)
│   │   ├── Import/          # Import Excel
│   │   └── Migration/       # Migrasi data legacy
│   ├── Support/             # Helper, Gate, Support classes (~25 file)
│   └── Traits/              # Shared traits
├── bootstrap/               # Bootstrap aplikasi (app.php, autoload.php)
├── core/                    # Framework custom PHP
│   ├── Application.php      # Kernel utama
│   ├── Router.php           # Routing
│   ├── Database.php         # Koneksi PDO
│   ├── Auth.php             # Autentikasi
│   ├── Session.php          # Session handler
│   ├── View.php             # Template engine
│   ├── Request.php          # Request handler
│   ├── Response.php         # Response handler
│   ├── Controller.php       # Base controller
│   ├── Model.php            # Base model
│   ├── Config.php           # Config loader
│   ├── Csrf.php             # CSRF protection
│   ├── Log.php              # Logging
│   ├── ModuleManager.php    # Module loader
│   └── helpers.php          # Fungsi bantuan global
├── database/
│   ├── schema.sql           # Skema database utama (+100 tabel)
│   ├── migrations/          # Migrasi SQL (~28 file)
│   └── seeders/             # Data contoh (finance)
├── modules/
│   ├── Finance/             # Modul keuangan (Controller, View, Routes)
│   └── Ppdb/                # Modul PPDB (Controller, View, Routes)
├── public/                  # Document root
│   ├── index.php            # Front controller
│   ├── .htaccess            # Rewrite rules
│   ├── assets/tailadmin/    # Aset frontend TailAdmin
│   ├── css/                 # CSS kustom (admin, login, app)
│   ├── js/                  # JavaScript kustom
│   ├── icons/               # PWA icons
│   ├── uploads/             # File upload (siswa, sekolah, surat)
│   └── service-worker.js    # PWA service worker
├── resources/
│   ├── layouts/             # Template layout (admin, auth, print)
│   └── views/               # View per fitur (~120+ file)
├── routes/
│   └── web.php              # Definisi rute (~200+ route)
├── scripts/                 # Script utilitas (build release, cron)
├── src/                     # Source TailAdmin (webpack entry)
├── storage/
│   ├── cache/               # Cache file
│   ├── backups/             # Backup database
│   ├── keuangan/            # File upload keuangan
│   ├── logs/                # Log aplikasi
│   └── settings/            # Pengaturan dalam JSON
├── setup.sh                 # Script instalasi otomatis
├── webpack.config.js        # Konfigurasi build frontend
├── package.json             # Dependensi Node.js
└── VERSION                  # Versi aplikasi
```

---

## Database

### Skema Utama

File `database/schema.sql` berisi definisi tabel inti:

| Grup | Tabel |
|------|-------|
| **Akademik** | `tahun_ajaran`, `jurusan`, `kelas`, `mata_pelajaran`, `guru_mata_pelajaran`, `guru_mata_pelajaran_kelas`, `jadwal_pelajaran` |
| **Siswa** | `siswa`, `siswa_penempatan`, `cbt_student_profiles` |
| **Penilaian K13** | `mata_pelajaran_kd`, `penilaian_kd_siswa`, `penilaian_pengetahuan_siswa`, `penilaian_keterampilan_siswa` |
| **Penilaian Kurmer** | `mata_pelajaran_tp`, `penilaian_tp_siswa`, `penilaian_kurmer_mapel_siswa` |
| **P5** | `p5_dimensi`, `p5_elemen`, `p5_projek`, `p5_projek_elemen`, `p5_penilaian_siswa`, `p5_penilaian_ringkasan` |
| **Kokurikuler** | `kokurikuler_kegiatan`, `kokurikuler_kegiatan_elemen`, `kokurikuler_penilaian`, `kokurikuler_ringkasan` |
| **UKK** | `ukk_paket_ujian`, `ukk_skkni`, `ukk_dudi`, `ukk_asesor`, `ukk_penilaian_siswa` |
| **Presensi** | `presensi_siswa`, `presensi_siswa_sesi`, `presensi_siswa_sesi_detail` |
| **Prakerin** | `tempat_prakerin`, `penempatan_prakerin`, `penilaian_prakerin` |
| **Ekskul** | `ekstrakurikuler`, `siswa_ekstrakurikuler` |
| **Sikap** | `data_sikap`, `penilaian_sikap` |
| **Prestasi** | `prestasi_siswa` |
| **Status** | `status_naik_kelas`, `status_kelulusan_siswa` |
| **Users & Auth** | `users`, `user_activity_logs`, `user_module_rules` |
| **TTD Digital** | `digital_document_signatures` |
| **WhatsApp** | `whatsapp_gateway_settings`, `whatsapp_message_queue` |

### Migrasi

Migrasi tambahan ada di `database/migrations/` untuk update antar versi. Dijalankan berurutan berdasarkan timestamp.

### Seeder

- `database/seeders/finance_demo.sql` — Data contoh modul keuangan

---

## Backup & Restore

### Via Panel Admin

Masuk ke **Admin Utilities → Backup & Restore**.

### Manual

```bash
# Backup database
mysqldump -u root -p sipaku > backup-$(date +%Y%m%d-%H%M%S).sql

# Backup file upload
tar czf uploads-$(date +%Y%m%d).tar.gz -C public uploads/

# Backup seluruh storage (log, konfigurasi, upload)
tar czf storage-$(date +%Y%m%d).tar.gz storage/

# Restore database
mysql -u root -p sipaku < backup-20250101-120000.sql

# Restore upload
tar xzf uploads-20250101.tar.gz -C public/
```

---

## Update Aplikasi

1. Backup database dan file upload
2. Upload file update `.zip` melalui **Admin Utilities → Update Aplikasi**
3. Atau manual: ekstrak file update, overwrite file, jalankan migrasi SQL baru
4. Cek `CHANGELOG.md` atau `docs/changelog/` untuk riwayat perubahan

---

## Troubleshooting

### Error "Class not found"

Pastikan autoload sudah benar. Jalankan:
```bash
php bootstrap/autoload.php
```

### Error database connection

Periksa:
- Kredensial di `app/Config/database.php`
- MySQL server berjalan
- Port benar (default 3306, MAMP 8889)

### Blank page / 500 error

Set `'debug' => true` di `app/Config/app.php` untuk melihat pesan error detail. Periksa juga `storage/logs/app.log`.

### File upload tidak muncul

Pastikan permission direktori `public/uploads/` writable oleh web server:
```bash
chmod -R 755 public/uploads/
chown -R www-data:www-data public/uploads/
```

### Session login cepat habis

Atur durasi sesi di **Admin → Pengaturan → Sesi Login**.

### Migrasi tidak jalan

Cek tabel `migrations` di database. Jalankan migrasi yang belum terdaftar secara manual:
```bash
mysql -u root -p sipaku < database/migrations/2026xxxxxxxxxx_nama_file.sql
```

### Error "CSRF token mismatch"

Hapus cookie session atau login ulang.

---

## Lisensi

Hak cipta dilindungi undang-undang. Tidak untuk didistribusikan tanpa izin dari pengembang.

---

*Dibangun dengan ❤️ untuk dunia pendidikan SMK Indonesia.*

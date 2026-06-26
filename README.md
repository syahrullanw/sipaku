# SIPAKU SMK

Sistem Informasi Akademik Sekolah (SIAKAD) untuk SMK. Mengelola data siswa, guru, kelas, jadwal, penilaian, presensi, ekstrakurikuler, P5, UKK, keuangan, dan rapor digital.

## Fitur Utama

- **Master Data** — Tahun ajaran, jurusan, guru, kelas, siswa dengan import/export
- **Akademik** — Mata pelajaran, guru pengampu, jadwal (manual & otomatis), penilaian K13 & Kurmer, presensi siswa (QR & manual), P5, kokurikuler
- **Wali Kelas** — Nilai sikap, prakerin, ekskul, prestasi, catatan, status naik kelas/kelulusan, upload nilai, legger, transkrip
- **Keuangan** — Tagihan rutin & insidental, pembayaran, pengeluaran, laporan, honor guru
- **PPDB** — Penerimaan peserta didik baru
- **UKK** — Paket ujian, SKKNI, DUDI, asesor, penilaian, sertifikat
- **Tata Usaha** — SK penugasan, persuratan (surat keluar/masuk), presensi manual guru
- **Manajemen Pengguna** — Role-based access (admin, staff, guru, siswa, bendahara, kepala sekolah)
- **Tanda Tangan Digital** — Persetujuan dokumen oleh kepala sekolah via QR code
- **Cetak** — Rapor (tengah semester & akhir), transkrip, kartu pelajar, sertifikat UKK, SKL
- **Integrasi WhatsApp** — Notifikasi otomatis via gateway (Fonnte, Waha, kustom)

## Prasyarat

| Komponen | Versi Minimal |
|----------|--------------|
| PHP | ≥ 8.1 (dengan PDO MySQL) |
| MySQL / MariaDB | 5.7+ / 10.4+ |
| Node.js (opsional) | 18+ (untuk build frontend) |

## Cara Instalasi

### Cara Cepat (Otomatis)

```bash
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

**1. Clone / ekstrak proyek**

```bash
cd /var/www/html
git clone <url-repo> sipaku
cd sipaku
```

**2. Konfigurasi database**

Edit `app/Config/database.php`:
```php
'database' => 'sipaku',
'username' => 'root',
'password' => 'password_anda',
```

**3. Konfigurasi aplikasi**

Edit `app/Config/app.php`:
```php
'url' => 'http://localhost:8000',  // atau domain Anda
'debug' => false,  // true untuk development
```

**4. Import database**

```bash
mysql -u root -p sipaku < database/schema.sql
```

Atau jalankan semua migrasi secara berurutan:
```bash
for f in database/migrations/*.sql; do
  mysql -u root -p sipaku < "$f"
done
```

**5. Buat akun admin**

```sql
INSERT INTO users (name, username, password, email, role, created_at, updated_at)
VALUES ('Administrator', 'admin', '<hash_dari_password_hash()>', 'admin@sekolah.sch.id', 'admin', NOW(), NOW());
```

**6. Jalankan server**

```bash
php -S localhost:8000 -t public
```

Akses di `http://localhost:8000`

### Frontend (Opsional)

Hanya diperlukan jika ingin membangun ulang aset TailAdmin:

```bash
npm install
npm run build
```

## Struktur Direktori

```
sipaku/
├── app/                  # Kode aplikasi (Controllers, Models, Services, Config)
├── bootstrap/            # Bootstrap aplikasi (app.php, autoload.php)
├── core/                 # Framework custom (Router, Database, Auth, dll.)
├── database/
│   ├── schema.sql        # Skema database utama
│   ├── migrations/       # Migrasi tambahan (dijalankan berurutan)
│   └── seeders/          # Data contoh
├── modules/              # Modul (Finance, Ppdb)
├── public/               # Document root (front controller index.php)
│   ├── assets/           # Aset frontend (TailAdmin)
│   ├── css/              # CSS kustom
│   ├── js/               # JavaScript kustom
│   └── uploads/          # File upload (siswa, sekolah, surat)
├── resources/            # Layout & view HTML
├── routes/               # Definisi rute
├── storage/              # Log, cache, backup, file sementara
├── setup.sh              # Script instalasi otomatis
└── .gitignore
```

## Environment Production

Untuk deployment production:

1. Set `'debug' => false` di `app/Config/app.php`
2. Set `'env' => 'production'`
3. Pastikan `public/` menjadi document root Apache/Nginx
4. Atur permission: `storage/` dan `public/uploads/` writable oleh web server
5. Gunakan HTTPS
6. Ubah password default segera setelah login

## Backup & Restore

Backup dapat dilakukan melalui panel admin di menu **Admin Utilities → Backup & Restore**, atau manual:

```bash
# Backup database
mysqldump -u root -p sipaku > backup-$(date +%Y%m%d).sql

# Backup file upload
tar czf uploads-$(date +%Y%m%d).tar.gz public/uploads/
```

## Lisensi

Hak cipta milik pengembang. Tidak untuk didistribusikan tanpa izin.

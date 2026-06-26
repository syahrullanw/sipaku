# Mekanisme Release ZIP SIPAKU

Release ZIP dipakai untuk mengirim kode aplikasi dari localhost ke hosting tanpa menimpa data hosting.

## Membuat Release

Dari folder project lokal:

```bash
/Applications/MAMP/bin/php/php8.3.14/bin/php scripts/build-release.php
```

Jika PHP sudah tersedia di PATH:

```bash
php scripts/build-release.php
```

File release akan dibuat di:

```text
build/releases/
```

Contoh nama file:

```text
sipaku-1.0.7-20260511-183000.zip
```

## Isi Release

Release berisi kode runtime aplikasi:

- `.htaccess`
- `VERSION`
- `app/`, kecuali `app/Config/*.php`
- `bootstrap/`
- `core/`
- `database/migrations/`
- `database/seeders/`
- `modules/`
- `public/`, kecuali `public/uploads/`
- `resources/`
- `routes/`
- `scripts/`
- `docs/`

Release tidak menyertakan:

- `app/Config/*.php`
- `storage/`
- `public/uploads/`
- `update/`
- `build/`
- `node_modules/`
- `vendor/`
- `src/`
- `Archive.zip`

## Prosedur Upgrade Hosting

1. Aktifkan maintenance mode.
2. Buat backup full dari menu `/admin/backup-restore`.
3. Upload ZIP release ke hosting.
4. Extract ZIP ke root aplikasi hosting.
5. Pastikan `app/Config/*.php`, `storage/`, dan `public/uploads/` hosting tidak tertimpa.
6. Jalankan file SQL baru dari `database/migrations/` yang belum pernah dijalankan.
7. Hapus cache jika perlu dari `storage/cache/`.
8. Cek halaman login, dashboard, dan modul yang berubah.
9. Matikan maintenance mode.

## Catatan Konfigurasi

Karena `app/Config/*.php` tidak ikut release, perubahan konfigurasi baru harus diterapkan manual di hosting. Ini sengaja dibuat begitu agar konfigurasi database, URL hosting, mode debug, dan path storage tidak tertimpa konfigurasi localhost.

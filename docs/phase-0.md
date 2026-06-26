# Dokumentasi Fase 0

Fase 0 berfokus pada penyusunan fondasi sistem agar fase-fase berikutnya dapat berjalan cepat tanpa mengubah struktur inti.

## 1. Arsitektur Inti

- **Core Kernel** – mengelola bootstrap aplikasi (`Core\Application`), routing (`Core\Router`), request/response, view renderer dan koneksi database.
- **App Layer** – berisi controller, model, dan service bawaan yang bukan bagian dari modul terpisah.
- **Modules** – setiap modul berada di `modules/NamaModul` dan memiliki `module.json` untuk mendefinisikan versi, dependensi dan file routes.
- **Resources** – view/layout PHP native yang dapat digantikan oleh modul.
- **Storage** – cache/log yang aman untuk ditulisi di server produksi.

## 2. Siklus Request

1. Semua request publik diarahkan ke `public/index.php`.
2. File tersebut mem-boot aplikasi melalui `bootstrap/app.php`.
3. `Core\Application` membaca konfigurasi statis, menjalankan `ModuleManager`, dan memuat route.
4. `Core\Router` mencocokkan request dengan action (closure atau controller).
5. Controller mengembalikan `Core\Response` (HTML, JSON, redirect, dll).

## 3. Konfigurasi

- Konfigurasi dasar tersimpan di `app/Config/*.php`. Edit langsung file tersebut untuk menyesuaikan nama aplikasi, timezone, dan kredensial database.
- Fungsi helper `config()`, `base_path()`, `resource_path()` dsb tersedia secara global melalui `core/helpers.php`.

## 4. Modul Mandiri

Contoh minimal `modules/Students/module.json`:

```json
{
  "name": "students",
  "version": "0.1.0",
  "routes": {
    "web": "routes/web.php"
  }
}
```

Isi `modules/Students/routes/web.php` bisa memanfaatkan variabel `$router` yang sama seperti `routes/web.php` utama.

## 5. Skema Database

- File `database/schema.sql` disediakan untuk menampung definisi tabel dan relasi. Import file SQL ini melalui phpMyAdmin, Adminer, atau client MySQL favorit Anda.
- Direktori `database/migrations` dan `database/seeders` tetap disiapkan sebagai opsional jika di masa depan membutuhkan otomatisasi, namun fase awal berfokus pada import SQL manual.

## 6. Integrasi Aset

- Direktori `src/` menyimpan template TailAdmin sumber. Build menggunakan `npm run build`.
- Hasil build dapat dipindahkan manual ke `public/` atau dikaitkan ke modul front-end tertentu.

## 7. Praktik Pengembangan

- Gunakan standar PSR-12 untuk penulisan kode PHP.
- Tambahkan komentar ringkas hanya pada bagian yang kompleks.
- Simpan kelas baru mengikuti namespace PSR-4.
- Dokumentasikan perubahan struktur database pada `database/schema.sql` agar mudah dibagikan antar tim.

## 8. Langkah Selanjutnya

- Menentukan ERD awal dan daftar tabel inti.
- Menyusun modul autentikasi + master data (fase 1).
- Menyiapkan pengujian otomatis dasar (PHPUnit) setelah struktur controller stabil.

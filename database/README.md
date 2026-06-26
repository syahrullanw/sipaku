# Database Schema

- Gunakan `database/schema.sql` untuk menyimpan definisi tabel dan relasi utama. Import file ini langsung melalui phpMyAdmin, Adminer, atau client MySQL pilihan.
- Direktori `database/migrations` dan `database/seeders` disediakan hanya jika nantinya ingin menambahkan otomatisasi via script PHP. Saat fase awal, cukup kelola file SQL manual.
- Entry point CLI `php bootstrap/console.php` masih dipersiapkan untuk masa depan (migrasi otomatis, seeding, dsb). Saat ini belum diaktifkan.

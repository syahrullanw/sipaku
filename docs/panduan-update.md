# Panduan Update Aplikasi

## Struktur Paket Update (ZIP)

Setiap paket update adalah file ZIP yang berisi file-file yang berubah pada versi tersebut.

### File Wajib

| File | Keterangan |
|------|------------|
| `VERSION` | Berisi nomor versi (format `x.x.x`, contoh: `1.2.0`) |

### File Opsional

| File | Keterangan |
|------|------------|
| `database/migration.sql` | SQL migration jika ada perubahan database |

## Aturan SQL Migration

Jika update mencakup perubahan database (tambah/hapus/edit tabel, kolom, index, atau data), **WAJIB** menyertakan `database/migration.sql` di dalam ZIP dengan ketentuan:

1. **Idempotent**: Gunakan `IF NOT EXISTS` / `IF EXISTS` / `OR REPLACE` agar migration aman dijalankan ulang.
2. **Satu file**: Semua perubahan SQL digabung dalam satu file `database/migration.sql`.
3. **Pemisah**: Setiap pernyataan SQL dipisahkan dengan titik koma (`;`).
4. **Komentar**: Baris diawali `--` atau `#` untuk komentar.

### Contoh `database/migration.sql`:

```sql
-- Menambahkan kolom nomor_telepon ke tabel siswa
ALTER TABLE siswa
    ADD COLUMN nomor_telepon VARCHAR(20) NULL AFTER alamat;

-- Membuat tabel baru untuk log aktivitas
CREATE TABLE IF NOT EXISTS log_aktivitas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    aksi VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Memperbarui data referensi
UPDATE pengaturan SET nilai = '1.2.0' WHERE kunci = 'db_version';
```

## Flow Update via Admin UI

1. Admin membuka menu **Pemeliharaan → Update Aplikasi**
2. Mengunggah file ZIP pembaruan
3. Sistem akan:
   a. Memvalidasi ZIP (MIME, ekstensi, ukuran, file VERSION)
   b. Membackup file yang akan ditimpa ke `storage/backups/updates/`
   c. Mengekstrak semua file ke direktori aplikasi
   d. Jika ada `database/migration.sql`:
      - Membackup seluruh database ke `storage/backups/updates/`
      - Mengeksekusi pernyataan SQL satu per satu
   e. Memperbarui file `VERSION`

## Catatan Penting

- **Backup database**: Selalu backup database sebelum update, terutama jika ada migration SQL.
- **Tidak ada rollback otomatis**: Jika migration SQL gagal pada pernyataan tertentu, pernyataan sebelumnya sudah tereksekusi. Migration harus idempotent.
- **Update file konfigurasi**: File `app/Config/*.php` sebaiknya tidak disertakan dalam ZIP agar konfigurasi server tidak tertimpa.

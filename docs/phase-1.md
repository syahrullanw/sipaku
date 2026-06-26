# Dokumentasi Fase 1 – Autentikasi & Master Data

Fase 1 menambahkan modul dasar yang dibutuhkan oleh operasional akademik SMK: autentikasi, dashboard administrasi, dan master data inti.

## 1. Autentikasi

- **Controller:** `App\Controllers\AuthController`
- **Model:** `App\Models\User`
- **Alur:**
  1. Pengguna mengakses `/login` → `AuthController::showLoginForm` menampilkan layout `resources/layouts/auth.php`.
  2. `POST /login` memanggil `Auth::attempt()` yang memverifikasi password Bcrypt.
  3. Sesi tersimpan pada `$_SESSION['auth_user']`. Flash message dikelola via `Core\Session`.
  4. `POST /logout` menghapus sesi dan mengarahkan kembali ke login.
- **Catatan:** tambahkan admin baru dengan melakukan hash melalui `password_hash('kataSandi', PASSWORD_BCRYPT)`.
- **Keamanan:** setiap permintaan `POST` wajib menyertakan token `csrf_field()` dan diverifikasi otomatis pada controller.
- **Helper penting:** gunakan `asset('path')` untuk memuat aset (otomatis menyesuaikan base path) dan `base_url('relative/path')` untuk membuat tautan internal.

## 2. Dashboard

- **Controller:** `App\Controllers\DashboardController`
- Menampilkan metrik sederhana (`Student::count()`, `Teacher::count()`, dll.) dan daftar siswa terbaru (`Student::latest`).
- Layout `resources/layouts/admin.php` menggunakan Tailwind CDN + komponen sidebar/topbar yang menyerupai TailAdmin.

## 3. Master Data

| Modul | Controller | Model | Rute | Catatan |
| --- | --- | --- | --- | --- |
| Tahun Ajaran | `SchoolYearController` | `SchoolYear` | `/master/tahun-ajaran` | When status "aktif" diset, entri lain otomatis nonaktif via transaksi sederhana. |
| Jurusan | `MajorController` | `Major` | `/master/jurusan` | Menyimpan kode, nama, dan deskripsi. |
| Guru | `TeacherController` | `Teacher` | `/master/guru` | Menyimpan NIP, kontak, dan alamat (unik). |
| Kelas | `ClassroomController` | `Classroom` | `/master/kelas` | Relasi ke tahun ajaran, jurusan, wali kelas; nama kelas unik per periode & jurusan. |
| Siswa | `StudentController` | `Student` | `/master/siswa` | Menyimpan identitas siswa beserta relasi kelas/tahun ajaran, NISN unik. |
| Pengguna | `UserController` | `User` | `/admin/pengguna` | (Admin saja) CRUD akun admin/staff/guru, reset password, validasi username/email unik. |
| Ganti Password | `PasswordController` | `User` | `/profile/password` | Pengguna mengubah password sendiri dengan verifikasi password lama. |

### Validasi & Flash

- Setiap `store/update` memanfaatkan helper `Session::flash()` untuk pesan dan `Session::flashInput()` untuk nilai lama.
- View menggunakan helper `old('field')` untuk repopulasi input setelah validasi gagal.
- Validasi unik yang sudah diterapkan:
  - Kode & nama tahun ajaran.
  - Kode & nama jurusan.
  - NIP dan email guru.
  - Nama kelas per tahun ajaran.
  - NISN siswa.
  - Validasi tambahan: format email guru, rentang tingkat kelas (X–XIII), dan tanggal lahir siswa.

### Struktur Tampilan

- Setiap modul master data menggunakan pola yang sama:
  - Form input di sisi kiri (`grid` 4–5 kolom).
  - Tabel data di sisi kanan dengan aksi `Edit` dan `Hapus`.
  - Query parameter `?edit={id}` memuat data ke form.

## 4. Database

- Skema disajikan pada `database/schema.sql` lengkap dengan foreign key.
- Contoh hash Bcrypt admin tersedia pada bagian akhir file (password `admin123`).
- Sesuaikan data awal setelah import (misal menambahkan jurusan/guru sebelum menambah kelas/siswa).
- Pada fase ini ditambahkan unique index untuk: `guru.nip`, `guru.email`, kombinasi `kelas (tahun_ajaran_id, jurusan_id, nama)`, `siswa.nisn`, serta `users.email`. Password pengguna disimpan menggunakan `password_hash` (Bcrypt).

## 5. Poin Pengembangan Berikutnya

- Menambahkan pagination, pencarian, serta filter pada daftar data.
- Integrasi upload foto profil siswa/guru (storage terpisah pada `storage/`).
- Menyusun modul akademik lanjutan (kurikulum, jadwal, penilaian) dan laporan PDF.
- Implementasi role-based access (admin, guru, wali kelas) dan audit trail.

## Finance Module Hardening Checklist

### 1. Akses & Role
- Gunakan `FinanceGate` untuk validasi role/bendahara di setiap controller.
- Nonaktifkan akses jika guru tidak lagi di level 1 (update mapping jabatan).
- Implementasi rate limit pada endpoint sensitif (approve/verify).

### 2. Upload & Storage
- Validasi MIME dan size (lihat `app/Config/finance.php`).
- Simpan bukti di `storage/keuangan/{tahun}` dengan nama file unik.
- Jalankan job pembersihan file yatim (tidak terhubung ke DB) tiap bulan.

### 3. Data Protection
- Enkripsi slip honor jika menyimpan data pribadi (opsional).
- Gunakan HTTPS; jika belum, hindari upload bukti sensitif.
- Batasi akses langsung ke folder `storage/keuangan` via web server (deny direct access).

### 4. Logging & Monitoring
- Gunakan channel `finance` (`storage/logs/finance.log`) untuk audit.
- Aktifkan alert jika terjadi error verifikasi atau saldo kas negatif.
- Audit file log secara berkala dan rotasi jika >10MB.

### 5. Backup
- Backup DB harian + file bukti.
- Backup disimpan offsite minimal 30 hari.
- Uji restore triwulanan dan dokumentasikan.

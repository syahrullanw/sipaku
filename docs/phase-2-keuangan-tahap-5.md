# Dokumentasi Fase Keuangan – Tahap 5 (Go-Live & Continuous Improvement)

Tahap ini mempersiapkan rilis produksi, pelatihan pengguna akhir, serta menetapkan mekanisme evaluasi berkala untuk modul keuangan. Fokusnya memastikan transisi dari pengembangan ke operasional berjalan mulus dan modul terus berkembang sesuai kebutuhan sekolah.

## 1. Persiapan Go-Live

- **Checklist Teknis**:
  - Pastikan migrasi dan seeder keuangan (Tahap 2) telah dijalankan di environment staging dan produksi.
  - Verifikasi konfigurasi `app/Config/finance.php` (limit kasbon, folder bukti, notifikasi).
  - Pastikan storage (`storage/keuangan`) memiliki permission tulis di server produksi.
  - Review guard akses (bendahara level 1, kepala sekolah aktif) sesuai data nyata.
  - Jalankan smoke test modul (tagihan, pembayaran, tabungan, kasbon, dana, honor) di staging dengan data dummy.
- **Cutover Plan**:
  - Tentukan tanggal cutover (perpindahan dari sistem lama ke modul keuangan).
  - Bekukan input transaksi di sistem lama H-1 sebelum cutover.
  - Import saldo awal kas, tagihan outstanding, tabungan, dan kasbon ke sistem baru.
  - Sediakan fallback manual (spreadsheet) jika terjadi gangguan saat peluncuran.

## 2. Deployment & Konfigurasi Lingkungan

- **Deployment Pipeline**:
  - Gunakan branch/versi khusus `release/finance` untuk rilis pertama.
  - Terapkan proses deploy standar (backup, deploy, migrate, seed, cache clear).
  - Catat hasil deploy di log rilis (tgl, versi, changelog).
- **Pengaturan Server**:
  - Pastikan PHP extension pendukung (GD untuk PDF jika butuh, Zip untuk export) aktif.
  - Siapkan cron job untuk reminder harian (`php public/index.php finance:reminder`).
  - Konfigurasikan monitoring server (disk space, load) karena modul keuangan menyimpan lampiran.
- **Integrasi Eksternal**:
  - Jika memakai email/SMS gateway, buat credential production dan uji kirim.
  - Hubungkan modul ke sistem akuntansi eksternal jika diperlukan (export CSV otomatis).

## 3. Pelatihan & Dokumentasi

- **Materi Pelatihan**:
  - Buat slide dan video tutorial per peran (siswa, guru, bendahara, kepala sekolah).
  - Workshop bendahara: pembuatan tagihan, verifikasi, pelaporan.
  - Workshop kepala sekolah: approval, laporan, audit.
  - Sesi siswa/guru: portal tagihan, tabungan, kasbon/dana, slip honor.
- **Dokumentasi**:
  - Susun panduan operasional di `docs/manual/finance/` (step-by-step, FAQ).
  - Siapkan quick reference card (1 halaman) untuk tugas rutin bendahara.
  - Dokumentasikan prosedur koreksi transaksi (misal payment salah nominal).
- **Support**:
  - Tetapkan PIC support (IT sekolah) dan jalur eskalasi (email, WhatsApp grup).
  - Buat template form laporan masalah agar troubleshooting mudah.

## 4. Monitoring Pasca Go-Live

- **Masa Stabilitas Awal (1–2 minggu)**:
  - Pantau log error harian (`finance.log`) dan respon maksimal 24 jam.
  - Review laporan kas dan tabungan secara manual untuk mendeteksi anomali awal.
  - Validasi bahwa reminder dan notifikasi berjalan sesuai jadwal.
- **Check-in Mingguan**:
  - Meeting singkat dengan bendahara dan kepala sekolah untuk menerima feedback.
  - Update backlog bug/fitur baru berdasarkan laporan lapangan.
- **Metode Validasi**:
  - Sampling bukti pembayaran fisik vs digital.
  - Cross-check saldo kas modul dengan rekening bank/ buku kas nyata.

## 5. Continuous Improvement

- **KPI & Metrik**:
  - Persentase tagihan yang dibayar tepat waktu.
  - Rata-rata waktu approval kasbon/dana/honor.
  - Jumlah error transaksi per bulan.
  - Tingkat penggunaan portal siswa/guru (login & unduh bukti).
- **Feedback Loop**:
  - Buat formulir feedback triwulanan untuk bendahara & kepala sekolah.
  - Kumpulkan insight siswa/guru lewat survei online.
  - Evaluasi fitur berdasarkan KPI, rencanakan iterasi per semester.
- **Roadmap Jangka Menengah**:
  - Integrasi pembayaran online (gateway VA/QRIS).
  - Modul analitik (dashboard trend, forecast).
  - Integrasi dengan modul akademik lain (misal presensi mempengaruhi honor).
  - Mobile-first atau PWA untuk akses cepat.

## 6. Tata Kelola & Audit

- **Audit Internal**:
  - Jadwalkan audit keuangan internal setelah 3 bulan untuk memastikan proses berjalan sesuai SOP.
  - Simpan log approval dan transaksi minimal 5 tahun.
- **Compliance**:
  - Pastikan modul mengikuti kebijakan yayasan/instansi (misal format laporan BOS).
  - Terapkan pembaruan regulasi (pajak honor, batas kas) secara berkala.
- **Risk Management**:
  - Buat daftar risiko (server down, data corrupt, penyalahgunaan akses) beserta mitigasinya.
  - Siapkan rencana darurat (DRP) untuk pemadaman panjang atau kehilangan data.

## 7. Dokumentasi Rilis & Evaluasi

- Setiap rilis modul (patch/fitur baru) dicatat di `docs/changelog/finance.md`.
- Setelah 1 semester, lakukan evaluasi menyeluruh: seberapa baik modul membantu, apa yang perlu disesuaikan.
- Catat lesson learned agar proyek serupa ke depan lebih cepat.

Tahap 5 menutup fase pengembangan awal modul keuangan dan membuka fase operasional berkelanjutan. Pastikan setiap aktivitas terdokumentasi dan menjadi bagian dari budaya kerja tim sekolah agar modul tetap relevan dan handal.

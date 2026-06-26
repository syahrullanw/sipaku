<?php

namespace App\Support;

use App\Models\Classroom;
use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;

class UserModuleRules
{
    private const STORAGE_FILE = 'settings/user-module-rules.json';

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return [
            'admin' => 'Admin',
            'staff' => 'Staff',
            'guru' => 'Guru',
            'wali_kelas' => 'Wali Kelas',
            'siswa' => 'Siswa',
            'bendahara' => 'Bendahara',
            'kepala_sekolah' => 'Kepala Sekolah',
            'tata_usaha' => 'Tata Usaha',
            'waka_kurikulum' => 'Waka Kurikulum',
            'kepala_prodi' => 'Kepala Prodi',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function modules(): array
    {
        $modules = [
            ['key' => 'years', 'group' => 'Master Data', 'label' => 'Tahun Ajaran', 'roles' => ['admin'], 'routes' => ['/master/tahun-ajaran']],
            ['key' => 'grade-rescue-periods', 'group' => 'Master Data', 'label' => 'Periode Rescue Nilai', 'roles' => ['admin'], 'routes' => ['/admin/periode-rescue-nilai']],
            ['key' => 'majors', 'group' => 'Master Data', 'label' => 'Jurusan', 'roles' => ['admin'], 'routes' => ['/master/jurusan']],
            ['key' => 'teachers', 'group' => 'Master Data', 'label' => 'Guru', 'roles' => ['admin'], 'routes' => ['/master/guru']],
            ['key' => 'classes', 'group' => 'Master Data', 'label' => 'Kelas', 'roles' => ['admin'], 'routes' => ['/master/kelas']],
            ['key' => 'students', 'group' => 'Master Data', 'label' => 'Siswa', 'roles' => ['admin'], 'routes' => ['/master/siswa/import', '/master/siswa/export', '/master/siswa']],
            ['key' => 'student-transfers', 'group' => 'Master Data', 'label' => 'Siswa Pindahan', 'roles' => ['admin', 'tata_usaha'], 'routes' => ['/master/siswa/pindahan']],
            ['key' => 'student-register', 'group' => 'Master Data', 'label' => 'Buku Induk Siswa', 'roles' => ['admin', 'guru'], 'routes' => ['/buku-induk']],
            ['key' => 'student-placements', 'group' => 'Master Data', 'label' => 'Penempatan Siswa', 'roles' => ['admin'], 'routes' => ['/master/siswa/penempatan']],
            ['key' => 'attitudes', 'group' => 'Master Data', 'label' => 'Data Sikap', 'roles' => ['admin'], 'routes' => ['/master/data-sikap']],
            ['key' => 'prakerin', 'group' => 'Master Data', 'label' => 'Tempat Prakerin', 'roles' => ['admin'], 'routes' => ['/master/prakerin']],
            ['key' => 'extracurriculars', 'group' => 'Master Data', 'label' => 'Ekstrakurikuler', 'roles' => ['admin'], 'routes' => ['/master/ekskul']],
            ['key' => 'subjects', 'group' => 'Master Data', 'label' => 'Mata Pelajaran', 'roles' => ['admin', 'staff', 'guru', 'siswa'], 'routes' => ['/akademik/mata-pelajaran']],
            ['key' => 'subject-teachers', 'group' => 'Master Data', 'label' => 'Guru Pengampu', 'roles' => ['admin', 'staff', 'tata_usaha', 'waka_kurikulum'], 'routes' => ['/akademik/guru-pengampu']],
            ['key' => 'lesson-schedules', 'group' => 'Master Data', 'label' => 'Jadwal Pelajaran', 'roles' => ['admin', 'staff', 'tata_usaha', 'waka_kurikulum'], 'routes' => ['/akademik/jadwal']],
            ['key' => 'automatic-schedules', 'group' => 'Master Data', 'label' => 'Generate Jadwal', 'roles' => ['admin', 'staff', 'tata_usaha', 'waka_kurikulum'], 'routes' => ['/akademik/jadwal/generate']],
            ['key' => 'academic-positions', 'group' => 'Master Data', 'label' => 'Jabatan Akademik', 'roles' => ['admin'], 'routes' => ['/master/jabatan-akademik']],
            ['key' => 'schools', 'group' => 'Master Data', 'label' => 'Profil Sekolah', 'roles' => ['admin'], 'routes' => ['/master/sekolah']],

            ['key' => 'ppdb-periods', 'group' => 'PPDB', 'label' => 'Periode PPDB', 'roles' => ['admin'], 'routes' => ['/ppdb/admin/periode']],
            ['key' => 'ppdb-registrants', 'group' => 'PPDB', 'label' => 'Data Pendaftar PPDB', 'roles' => ['admin'], 'routes' => ['/ppdb/admin/pendaftar']],
            ['key' => 'ppdb-migration', 'group' => 'PPDB', 'label' => 'Migrasi PPDB ke Siswa', 'roles' => ['admin'], 'routes' => ['/ppdb/admin/migrasi']],
            ['key' => 'ppdb-report', 'group' => 'PPDB', 'label' => 'Laporan PPDB', 'roles' => ['admin'], 'routes' => ['/ppdb/admin/laporan']],
            ['key' => 'ppdb-broadcast-admin', 'group' => 'PPDB', 'label' => 'Broadcast PPDB Admin', 'roles' => ['admin'], 'routes' => ['/ppdb/admin/broadcast']],
            ['key' => 'ppdb-teacher-dashboard', 'group' => 'PPDB', 'label' => 'Dashboard Penanggung Jawab PPDB', 'roles' => ['guru'], 'routes' => ['/ppdb/guru'], 'contextual' => true],
            ['key' => 'ppdb-teacher-registrants', 'group' => 'PPDB', 'label' => 'Data Pendaftar PPDB Guru', 'roles' => ['guru'], 'routes' => ['/ppdb/guru/pendaftar'], 'contextual' => true],
            ['key' => 'ppdb-broadcast-guru', 'group' => 'PPDB', 'label' => 'Broadcast Pendaftar PPDB', 'roles' => ['guru'], 'routes' => ['/ppdb/guru/broadcast'], 'contextual' => true],

            ['key' => 'graduation-certificates', 'group' => 'Tata Usaha', 'label' => 'SKL Kelulusan', 'roles' => ['admin', 'staff', 'waka_kurikulum'], 'routes' => ['/akademik/skl', '/kelulusan/skl']],
            ['key' => 'assignment-letters', 'group' => 'Tata Usaha', 'label' => 'SK Penugasan Guru', 'roles' => ['admin', 'staff', 'tata_usaha'], 'routes' => ['/tata-usaha/sk-penugasan']],
            ['key' => 'manual-attendance', 'group' => 'Tata Usaha', 'label' => 'Cetak Absensi Manual', 'roles' => ['admin', 'staff', 'tata_usaha'], 'routes' => ['/tata-usaha/presensi-manual']],
            ['key' => 'letters', 'group' => 'Tata Usaha', 'label' => 'Persuratan', 'roles' => ['admin', 'staff', 'tata_usaha'], 'routes' => ['/tata-usaha/persuratan']],

            ['key' => 'users', 'group' => 'Utilitas', 'label' => 'Pengguna', 'roles' => ['admin'], 'routes' => ['/admin/pengguna']],
            ['key' => 'user-rules', 'group' => 'Utilitas', 'label' => 'User Rules', 'roles' => ['admin'], 'routes' => ['/admin/user-rules'], 'locked_admin' => true],
            ['key' => 'user-logs', 'group' => 'Utilitas', 'label' => 'Log Pengguna', 'roles' => ['admin'], 'routes' => ['/admin/log-aktivitas']],
            ['key' => 'session-timeout', 'group' => 'Utilitas', 'label' => 'Sesi Login', 'roles' => ['admin'], 'routes' => ['/admin/pengaturan/sesi-login']],
            ['key' => 'demo-mode', 'group' => 'Utilitas', 'label' => 'Mode Demo', 'roles' => ['admin'], 'routes' => ['/admin/demo-mode']],
            ['key' => 'maintenance-mode', 'group' => 'Utilitas', 'label' => 'Maintenance Mode', 'roles' => ['admin'], 'routes' => ['/admin/maintenance-mode']],
            ['key' => 'periodic-copy', 'group' => 'Utilitas', 'label' => 'Salin Data Periodik', 'roles' => ['admin'], 'routes' => ['/admin/salin-data-periodik']],
            ['key' => 'legacy-migration', 'group' => 'Utilitas', 'label' => 'Migrasi Rapor Legacy', 'roles' => ['admin'], 'routes' => ['/admin/migrasi-rapor']],
            ['key' => 'admin-file-manager', 'group' => 'Utilitas', 'label' => 'File Manager', 'roles' => ['admin'], 'routes' => ['/admin/file-manager']],
            ['key' => 'cbt-export', 'group' => 'Integrasi', 'label' => 'Export Data CBT', 'roles' => ['admin'], 'routes' => ['/admin/cbt/export']],
            ['key' => 'whatsapp-gateway', 'group' => 'Integrasi', 'label' => 'WhatsApp Gateway', 'roles' => ['admin'], 'routes' => ['/admin/integrasi/whatsapp']],
            ['key' => 'clean-data-ppdb', 'group' => 'Pemeliharaan', 'label' => 'Clean Data PPDB', 'roles' => ['admin'], 'routes' => ['/admin/clean-data/ppdb']],
            ['key' => 'clean-data-letters', 'group' => 'Pemeliharaan', 'label' => 'Clean Data Persuratan', 'roles' => ['admin'], 'routes' => ['/admin/clean-data/persuratan']],
            ['key' => 'clean-data-report', 'group' => 'Pemeliharaan', 'label' => 'Clean Data Rapor', 'roles' => ['admin'], 'routes' => ['/admin/clean-data/raport']],
            ['key' => 'clean-data-finance', 'group' => 'Pemeliharaan', 'label' => 'Clean Data Keuangan', 'roles' => ['admin'], 'routes' => ['/admin/clean-data/keuangan']],
            ['key' => 'clean-data-logs', 'group' => 'Pemeliharaan', 'label' => 'Clean Log Pengguna', 'roles' => ['admin'], 'routes' => ['/admin/clean-data/log']],
            ['key' => 'data-backup-restore', 'group' => 'Pemeliharaan', 'label' => 'Backup & Restore Data', 'roles' => ['admin'], 'routes' => ['/admin/backup-restore']],
            ['key' => 'app-update', 'group' => 'Pemeliharaan', 'label' => 'Update Aplikasi', 'roles' => ['admin'], 'routes' => ['/admin/update']],

            ['key' => 'midterm-report', 'group' => 'Laporan', 'label' => 'Laporan Tengah Semester', 'roles' => ['admin', 'guru'], 'routes' => ['/raport/tengah-semester']],
            ['key' => 'report-cards', 'group' => 'Laporan', 'label' => 'Cetak Raport', 'roles' => ['admin', 'guru'], 'routes' => ['/raport/cetak']],
            ['key' => 'student-cards', 'group' => 'Laporan', 'label' => 'Cetak Kartu Pelajar', 'roles' => ['admin', 'guru'], 'routes' => ['/kartu-pelajar']],

            ['key' => 'graduation-approvals', 'group' => 'Kepala Sekolah', 'label' => 'Persetujuan SKL', 'roles' => ['kepala_sekolah'], 'routes' => ['/kepala-sekolah/skl'], 'contextual' => true],
            ['key' => 'digital-signatures-letters', 'group' => 'Kepala Sekolah', 'label' => 'Persetujuan Persuratan', 'roles' => ['kepala_sekolah'], 'routes' => ['/kepala-sekolah/persuratan'], 'contextual' => true],
            ['key' => 'digital-signatures', 'group' => 'Kepala Sekolah', 'label' => 'Persetujuan Raport', 'roles' => ['kepala_sekolah'], 'routes' => ['/kepala-sekolah/ttd-digital'], 'contextual' => true],
            ['key' => 'digital-signatures-transkrip', 'group' => 'Kepala Sekolah', 'label' => 'Persetujuan Transkrip', 'roles' => ['kepala_sekolah'], 'routes' => ['/kepala-sekolah/ttd-digital/transkrip'], 'contextual' => true],

            ['key' => 'ukk', 'group' => 'Kepala Prodi', 'label' => 'UKK & Skill Passport', 'roles' => ['kepala_prodi'], 'routes' => ['/kaprodi/ukk'], 'contextual' => true],

            ['key' => 'finance-bendahara-dashboard', 'group' => 'Keuangan Bendahara', 'label' => 'Dashboard Bendahara', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara'], 'contextual' => true],
            ['key' => 'finance-bendahara-billings', 'group' => 'Keuangan Bendahara', 'label' => 'Tagihan Siswa', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara/tagihan'], 'contextual' => true],
            ['key' => 'finance-bendahara-categories', 'group' => 'Keuangan Bendahara', 'label' => 'Kategori Tagihan', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara/kategori'], 'contextual' => true],
            ['key' => 'finance-bendahara-purchases', 'group' => 'Keuangan Bendahara', 'label' => 'Pembelian Perlengkapan', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara/pembelian'], 'contextual' => true],
            ['key' => 'finance-bendahara-savings', 'group' => 'Keuangan Bendahara', 'label' => 'Tabungan Siswa', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara/tabungan'], 'contextual' => true],
            ['key' => 'finance-bendahara-student-ledger', 'group' => 'Keuangan Bendahara', 'label' => 'Rekap Keuangan Siswa', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara/rekap-siswa'], 'contextual' => true],
            ['key' => 'finance-bendahara-general-cash', 'group' => 'Keuangan Bendahara', 'label' => 'Kas Utama', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara/kas-utama'], 'contextual' => true],
            ['key' => 'finance-bendahara-teacher-attendance', 'group' => 'Keuangan Bendahara', 'label' => 'Rekap Presensi Guru', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara/presensi-guru'], 'contextual' => true],
            ['key' => 'finance-bendahara-teacher-salary', 'group' => 'Keuangan Bendahara', 'label' => 'Input Gaji Guru', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara/gaji-guru'], 'contextual' => true],
            ['key' => 'finance-bendahara-unexpected-expenses', 'group' => 'Keuangan Bendahara', 'label' => 'Pengeluaran Tak Terduga', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara/pengeluaran-tak-terduga'], 'contextual' => true],
            ['key' => 'finance-bendahara-reports', 'group' => 'Keuangan Bendahara', 'label' => 'Rekap Keuangan', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara/laporan'], 'contextual' => true],
            ['key' => 'finance-bendahara-payments', 'group' => 'Keuangan Bendahara', 'label' => 'Verifikasi Pembayaran', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara/pembayaran'], 'contextual' => true],
            ['key' => 'finance-bendahara-procurements', 'group' => 'Keuangan Bendahara', 'label' => 'Pengadaan Alat Praktik', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara/pengadaan'], 'contextual' => true],
            ['key' => 'finance-bendahara-loans', 'group' => 'Keuangan Bendahara', 'label' => 'Pencairan Kasbon Guru', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara/kasbon'], 'contextual' => true],
            ['key' => 'finance-bendahara-activities', 'group' => 'Keuangan Bendahara', 'label' => 'Dana Kegiatan', 'roles' => ['bendahara'], 'routes' => ['/keuangan/bendahara/dana-kegiatan'], 'contextual' => true],
            ['key' => 'finance-kepsek-dashboard', 'group' => 'Keuangan Kepala Sekolah', 'label' => 'Dashboard Keuangan Kepala Sekolah', 'roles' => ['kepala_sekolah'], 'routes' => ['/keuangan/kepala-sekolah'], 'contextual' => true],
            ['key' => 'finance-kepsek-reports', 'group' => 'Keuangan Kepala Sekolah', 'label' => 'Rekap Keuangan Kepala Sekolah', 'roles' => ['kepala_sekolah'], 'routes' => ['/keuangan/kepala-sekolah/laporan'], 'contextual' => true],
            ['key' => 'finance-kepsek-approvals', 'group' => 'Keuangan Kepala Sekolah', 'label' => 'Persetujuan Keuangan', 'roles' => ['kepala_sekolah'], 'routes' => ['/keuangan/kepala-sekolah/approval'], 'contextual' => true],
            ['key' => 'finance-headmaster-approvals', 'group' => 'Keuangan Kepala Sekolah', 'label' => 'Approve Kasbon/Dana/Honor', 'roles' => ['kepala_sekolah'], 'routes' => ['/keuangan/kepala-sekolah/approval'], 'contextual' => true],
            ['key' => 'finance-headmaster-procurements', 'group' => 'Keuangan Kepala Sekolah', 'label' => 'Pengadaan Praktikum Kepala Sekolah', 'roles' => ['kepala_sekolah'], 'routes' => ['/keuangan/kepala-sekolah/pengadaan'], 'contextual' => true],
            ['key' => 'finance-kaprodi-procurements', 'group' => 'Kepala Prodi', 'label' => 'Pengadaan Alat Praktik Kaprodi', 'roles' => ['kepala_prodi'], 'routes' => ['/keuangan/kaprodi/pengadaan'], 'contextual' => true],
            ['key' => 'finance-guru-dashboard', 'group' => 'Keuangan Guru', 'label' => 'Ringkasan Keuangan Guru', 'roles' => ['guru'], 'routes' => ['/keuangan/guru'], 'contextual' => true],
            ['key' => 'finance-guru-loans', 'group' => 'Keuangan Guru', 'label' => 'Pengajuan Kasbon', 'roles' => ['guru'], 'routes' => ['/keuangan/guru/kasbon'], 'contextual' => true],
            ['key' => 'finance-guru-activities', 'group' => 'Keuangan Guru', 'label' => 'Pengajuan Dana Kegiatan', 'roles' => ['guru'], 'routes' => ['/keuangan/guru/dana-kegiatan'], 'contextual' => true],
            ['key' => 'finance-siswa-dashboard', 'group' => 'Keuangan Siswa', 'label' => 'Ringkasan Keuangan Siswa', 'roles' => ['siswa'], 'routes' => ['/keuangan/siswa'], 'contextual' => true],

            ['key' => 'student-self-profile', 'group' => 'Siswa', 'label' => 'Profil Saya', 'roles' => ['siswa'], 'routes' => ['/siswa/profil'], 'contextual' => true],
            ['key' => 'student-profile', 'group' => 'Siswa', 'label' => 'Edit Data Diri', 'roles' => ['siswa'], 'routes' => ['/siswa/data-diri'], 'contextual' => true],
            ['key' => 'student-documents', 'group' => 'Siswa', 'label' => 'Berkas Fisik', 'roles' => ['siswa'], 'routes' => ['/siswa/berkas'], 'contextual' => true],
            ['key' => 'student-grades', 'group' => 'Siswa', 'label' => 'Nilai Saya', 'roles' => ['siswa'], 'routes' => ['/siswa/nilai'], 'contextual' => true],
            ['key' => 'student-attendance-scan', 'group' => 'Siswa', 'label' => 'Scan Presensi', 'roles' => ['siswa'], 'routes' => ['/presensi/scan'], 'contextual' => true],
            ['key' => 'student-graduation', 'group' => 'Siswa', 'label' => 'Informasi Kelulusan', 'roles' => ['siswa'], 'routes' => ['/siswa/kelulusan'], 'contextual' => true],

            ['key' => 'teacher-profile', 'group' => 'Guru', 'label' => 'Profil Guru', 'roles' => ['guru'], 'routes' => ['/guru/profil'], 'contextual' => true],
            ['key' => 'teacher-subject-assessments', 'group' => 'Guru', 'label' => 'Input Nilai Mapel', 'roles' => ['guru'], 'routes' => ['/guru/nilai'], 'contextual' => true],
            ['key' => 'teacher-attendance', 'group' => 'Guru', 'label' => 'Presensi QR Siswa', 'roles' => ['guru'], 'routes' => ['/guru/presensi'], 'contextual' => true],
            ['key' => 'teacher-attendance-recap', 'group' => 'Guru', 'label' => 'Rekap Presensi Guru', 'roles' => ['guru'], 'routes' => ['/guru/presensi/rekap'], 'contextual' => true],
            ['key' => 'teacher-extracurricular-assessments', 'group' => 'Guru', 'label' => 'Input Nilai Ekskul', 'roles' => ['guru'], 'routes' => ['/guru/ekskul/nilai'], 'contextual' => true],
            ['key' => 'teacher-prakerin-assessments', 'group' => 'Guru', 'label' => 'Input Nilai Prakerin', 'roles' => ['guru'], 'routes' => ['/guru/prakerin/nilai'], 'contextual' => true],

            ['key' => 'homeroom-students', 'group' => 'Wali Kelas', 'label' => 'Data Siswa Wali Kelas', 'roles' => ['wali_kelas'], 'routes' => ['/master/siswa'], 'contextual' => true],
            ['key' => 'homeroom-student-register', 'group' => 'Wali Kelas', 'label' => 'Buku Induk Siswa Wali Kelas', 'roles' => ['wali_kelas'], 'routes' => ['/buku-induk'], 'contextual' => true],
            ['key' => 'homeroom-attendance', 'group' => 'Wali Kelas', 'label' => 'Input Presensi Wali Kelas', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/presensi'], 'contextual' => true],
            ['key' => 'homeroom-ledger', 'group' => 'Wali Kelas', 'label' => 'Legger Kelas', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/legger'], 'contextual' => true],
            ['key' => 'homeroom-grade-upload', 'group' => 'Wali Kelas', 'label' => 'Upload Nilai Rescue', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/nilai-upload'], 'contextual' => true],
            ['key' => 'homeroom-transcripts', 'group' => 'Wali Kelas', 'label' => 'Cetak Transkrip', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/transkrip'], 'contextual' => true],
            ['key' => 'homeroom-prakerin', 'group' => 'Wali Kelas', 'label' => 'Penempatan Prakerin', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/prakerin'], 'contextual' => true],
            ['key' => 'homeroom-extracurriculars', 'group' => 'Wali Kelas', 'label' => 'Ekskul Siswa', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/ekskul'], 'contextual' => true],
            ['key' => 'homeroom-achievements', 'group' => 'Wali Kelas', 'label' => 'Prestasi Siswa', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/prestasi'], 'contextual' => true],
            ['key' => 'homeroom-attitudes-spiritual', 'group' => 'Wali Kelas', 'label' => 'Nilai Sikap Spiritual', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/nilai-sikap/spiritual'], 'contextual' => true],
            ['key' => 'homeroom-attitudes-sosial', 'group' => 'Wali Kelas', 'label' => 'Nilai Sikap Sosial', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/nilai-sikap/sosial'], 'contextual' => true],
            ['key' => 'homeroom-cocurriculars', 'group' => 'Wali Kelas', 'label' => 'Kokurikuler Kurmer', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/kokurikuler'], 'contextual' => true],
            ['key' => 'homeroom-p5', 'group' => 'Wali Kelas', 'label' => 'Projek P5', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/p5'], 'contextual' => true],
            ['key' => 'homeroom-p5-print', 'group' => 'Wali Kelas', 'label' => 'Cetak Rapor P5', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/p5/cetak'], 'contextual' => true],
            ['key' => 'homeroom-promotions', 'group' => 'Wali Kelas', 'label' => 'Status Naik Kelas', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/status-naik-kelas'], 'contextual' => true],
            ['key' => 'homeroom-graduations', 'group' => 'Wali Kelas', 'label' => 'Status Kelulusan', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/status-lulus', '/walikelas/skl'], 'contextual' => true],
            ['key' => 'homeroom-graduation-print', 'group' => 'Wali Kelas', 'label' => 'Cetak SKL', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/status-lulus'], 'contextual' => true],
            ['key' => 'homeroom-notes', 'group' => 'Wali Kelas', 'label' => 'Catatan Wali Kelas', 'roles' => ['wali_kelas'], 'routes' => ['/walikelas/catatan'], 'contextual' => true],
        ];

        $indexed = [];
        foreach ($modules as $module) {
            $indexed[(string) $module['key']] = $module;
        }

        return $indexed;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function permissions(): array
    {
        $defaults = self::defaultPermissions();
        $stored = self::readStoredPermissions();
        $isLegacyRules = !array_key_exists('wali_kelas', $stored);

        foreach (self::roleOptions() as $role => $_label) {
            foreach (self::modules() as $key => $module) {
                if ($isLegacyRules && ($module['group'] ?? '') === 'Wali Kelas') {
                    if ($role === 'wali_kelas' && isset($stored['guru']) && array_key_exists($key, $stored['guru'])) {
                        $defaults[$role][$key] = (bool) $stored['guru'][$key];
                    }

                    if ($role === 'guru') {
                        $defaults[$role][$key] = false;
                    }
                } elseif (isset($stored[$role]) && array_key_exists($key, $stored[$role])) {
                    $defaults[$role][$key] = (bool) $stored[$role][$key];
                }

                if (($module['locked_admin'] ?? false) && $role === 'admin') {
                    $defaults[$role][$key] = true;
                }
            }
        }

        return $defaults;
    }

    /**
     * @param array<string, array<string, mixed>> $permissions
     */
    public static function save(array $permissions): void
    {
        $normalized = self::defaultPermissions(false);

        foreach (self::roleOptions() as $role => $_label) {
            foreach (self::modules() as $key => $module) {
                $normalized[$role][$key] = !empty($permissions[$role][$key]);

                if (($module['locked_admin'] ?? false) && $role === 'admin') {
                    $normalized[$role][$key] = true;
                }
            }
        }

        $path = self::path();
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Tidak dapat membuat direktori pengaturan user rules.');
        }

        $json = json_encode([
            'permissions' => $normalized,
            'updated_at' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Gagal menyimpan user rules.');
        }
    }

    public static function reset(): void
    {
        $path = self::path();
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException('Gagal mengembalikan user rules ke default.');
        }
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function isAllowed(?array $user, string $moduleKey): bool
    {
        if (!isset(self::modules()[$moduleKey])) {
            return true;
        }

        if ($moduleKey === 'user-rules') {
            return is_array($user) && (string) ($user['role'] ?? '') === 'admin';
        }

        foreach (self::effectiveRoles($user) as $role) {
            if ((self::permissions()[$role][$moduleKey] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function allowsCurrentRequest(?array $user, bool $requireMapped = false): bool
    {
        $request = Request::capture();
        $moduleKeys = self::moduleKeysForPath($request->getPath());

        if (empty($moduleKeys)) {
            return !$requireMapped;
        }

        foreach ($moduleKeys as $moduleKey) {
            if (self::isAllowed($user, $moduleKey)) {
                return true;
            }
        }

        return false;
    }

    public static function guardCurrentRequest(Request $request): ?Response
    {
        $moduleKeys = self::moduleKeysForPath($request->getPath());
        if (empty($moduleKeys)) {
            return null;
        }

        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        foreach ($moduleKeys as $moduleKey) {
            if (self::isAllowed($user, $moduleKey)) {
                return null;
            }
        }

        Session::flash('error', 'Anda tidak memiliki hak akses ke modul ini.');

        return Response::make('', 302, ['Location' => base_url('dashboard')]);
    }

    public static function moduleKeyForPath(string $path): ?string
    {
        return self::moduleKeysForPath($path)[0] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function moduleKeysForPath(string $path): array
    {
        $path = '/' . ltrim($path, '/');
        $patterns = [];

        foreach (self::modules() as $key => $module) {
            foreach (($module['routes'] ?? []) as $route) {
                $route = '/' . trim((string) $route, '/');
                $patterns[] = [$key, $route];
            }
        }

        usort($patterns, static fn (array $a, array $b): int => strlen((string) $b[1]) <=> strlen((string) $a[1]));

        $matches = [];
        $maxLength = 0;
        foreach ($patterns as [$key, $prefix]) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                $length = strlen((string) $prefix);
                if ($length > $maxLength) {
                    $matches = [];
                    $maxLength = $length;
                }

                if ($length === $maxLength) {
                    $matches[] = (string) $key;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @param array<int, array<string, mixed>> $groups
     * @param array<string, mixed>|null $user
     *
     * @return array<int, array<string, mixed>>
     */
    public static function filterMenuGroups(array $groups, ?array $user): array
    {
        $modules = self::modules();

        foreach ($groups as &$group) {
            if (!isset($group['items']) || !is_array($group['items'])) {
                continue;
            }

            $groupOriginalVisible = (bool) ($group['visible'] ?? true);
            $hasVisibleItem = false;
            foreach ($group['items'] as &$item) {
                $key = (string) ($item['key'] ?? '');
                if (!isset($modules[$key])) {
                    $hasVisibleItem = $hasVisibleItem || (bool) ($item['visible'] ?? true);
                    continue;
                }

                $originalVisible = (bool) ($item['visible'] ?? true);
                $allowed = self::isAllowed($user, $key);
                $contextual = (bool) ($modules[$key]['contextual'] ?? false);

                $item['visible'] = $contextual ? ($groupOriginalVisible && $originalVisible && $allowed) : $allowed;
                $hasVisibleItem = $hasVisibleItem || (bool) $item['visible'];
            }
            unset($item);

            $group['visible'] = $hasVisibleItem;
        }
        unset($group);

        return $groups;
    }

    /**
     * @param array<string, mixed>|null $user
     *
     * @return array<int, string>
     */
    public static function effectiveRoles(?array $user): array
    {
        if (!is_array($user)) {
            return [];
        }

        $roles = [];
        $baseRole = (string) ($user['role'] ?? '');
        if ($baseRole !== '') {
            $roles[] = $baseRole;
        }

        if (self::isHomeroomTeacher($user)) {
            $roles[] = 'wali_kelas';
        }

        if (FinanceGate::isBendahara($user)) {
            $roles[] = 'bendahara';
        }

        if (FinanceGate::isHeadmaster($user)) {
            $roles[] = 'kepala_sekolah';
        }

        if (AcademicRoleGate::isTataUsaha($user)) {
            $roles[] = 'tata_usaha';
        }

        if (AcademicRoleGate::isWakaKurikulum($user)) {
            $roles[] = 'waka_kurikulum';
        }

        if (AcademicRoleGate::isKepalaProdi(null, $user)) {
            $roles[] = 'kepala_prodi';
        }

        return array_values(array_unique(array_filter($roles, static fn (string $role): bool => isset(self::roleOptions()[$role]))));
    }

    /**
     * @param array<string, mixed> $user
     */
    private static function isHomeroomTeacher(array $user): bool
    {
        if (($user['role'] ?? null) !== 'guru') {
            return false;
        }

        $teacherId = (int) ($user['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            return false;
        }

        $activeYearId = SchoolYearContext::id();
        if ($activeYearId !== null && Classroom::teacherHasHomeroom($teacherId, $activeYearId)) {
            return true;
        }

        return Classroom::teacherHasHomeroom($teacherId);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function defaultPermissions(bool $withDefaults = true): array
    {
        $permissions = [];
        foreach (self::roleOptions() as $role => $_label) {
            $permissions[$role] = [];
            foreach (self::modules() as $key => $module) {
                $permissions[$role][$key] = $withDefaults && in_array($role, (array) ($module['roles'] ?? []), true);
            }
        }

        return $permissions;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private static function readStoredPermissions(): array
    {
        $path = self::path();
        if (!is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !isset($decoded['permissions']) || !is_array($decoded['permissions'])) {
            return [];
        }

        return $decoded['permissions'];
    }

    private static function path(): string
    {
        return storage_path(self::STORAGE_FILE);
    }
}

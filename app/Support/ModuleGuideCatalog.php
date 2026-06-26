<?php

namespace App\Support;

use App\Models\Extracurricular;
use App\Models\PrakerinPlace;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Support\UserModuleRules;

class ModuleGuideCatalog
{
    /**
     * @return array<int, string>
     */
    public static function roleLabelsForUser(array $user): array
    {
        $roleOptions = UserModuleRules::roleOptions();
        $labels = [];

        foreach (UserModuleRules::effectiveRoles($user) as $role) {
            $labels[] = $roleOptions[$role] ?? ucwords(str_replace('_', ' ', $role));
        }

        return array_values(array_unique($labels));
    }

    /**
     * @return array<int, array{label:string, entries:array<int, array<string, mixed>>}>
     */
    public static function groupsForUser(array $user): array
    {
        $entries = self::commonEntries();
        $modules = UserModuleRules::modules();

        foreach ($modules as $key => $module) {
            if (!UserModuleRules::isAllowed($user, $key) || !self::isContextAvailable($key, $user)) {
                continue;
            }

            $entries[] = self::entryForModule($key, $module);
        }

        $groups = [];
        foreach ($entries as $entry) {
            $group = (string) ($entry['group'] ?? 'Umum');
            if (!isset($groups[$group])) {
                $groups[$group] = [
                    'label' => $group,
                    'entries' => [],
                ];
            }

            $groups[$group]['entries'][] = $entry;
        }

        return array_values($groups);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function commonEntries(): array
    {
        return [
            [
                'key' => 'dashboard',
                'group' => 'Umum',
                'label' => 'Dashboard',
                'url' => base_url('dashboard'),
                'description' => 'Halaman awal untuk melihat ringkasan pekerjaan, notifikasi, dan pintasan sesuai hak akses.',
                'steps' => [
                    'Cek kartu ringkasan untuk melihat data yang perlu ditindaklanjuti.',
                    'Gunakan pintasan menu untuk masuk ke tugas yang paling sering digunakan.',
                    'Pastikan tahun ajaran aktif sudah sesuai sebelum menginput data akademik.',
                ],
                'tips' => [
                    'Jika data tidak muncul, cek pilihan tahun ajaran aktif di bagian atas aplikasi.',
                ],
            ],
            [
                'key' => 'profile',
                'group' => 'Umum',
                'label' => 'Profil & Password',
                'url' => base_url('profile'),
                'description' => 'Area untuk memperbarui data pribadi, foto, dan password akun.',
                'steps' => [
                    'Buka menu profil di kanan atas halaman.',
                    'Lengkapi data kontak agar admin mudah melakukan verifikasi.',
                    'Ganti password default sebelum menggunakan fitur penting.',
                ],
                'tips' => [
                    'Gunakan password yang tidak sama dengan username atau NIS/NIP.',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $module
     *
     * @return array<string, mixed>
     */
    private static function entryForModule(string $key, array $module): array
    {
        $guide = self::guides()[$key] ?? self::fallbackGuide($module);

        return [
            'key' => $key,
            'group' => (string) ($module['group'] ?? 'Modul'),
            'label' => (string) ($module['label'] ?? $key),
            'url' => self::urlForModule($key, $module),
            'description' => (string) ($guide['description'] ?? ''),
            'steps' => array_values((array) ($guide['steps'] ?? [])),
            'tips' => array_values((array) ($guide['tips'] ?? [])),
        ];
    }

    /**
     * @param array<string, mixed> $module
     */
    private static function urlForModule(string $key, array $module): ?string
    {
        $preferred = [
            'students' => '/master/siswa',
            'graduation-certificates' => '/akademik/skl',
            'homeroom-graduations' => '/walikelas/status-lulus',
            'homeroom-graduation-print' => '/walikelas/status-lulus',
        ];

        if (isset($preferred[$key])) {
            return base_url(ltrim($preferred[$key], '/'));
        }

        $routes = array_values((array) ($module['routes'] ?? []));
        if (empty($routes)) {
            return null;
        }

        $fallback = (string) $routes[0];
        foreach ($routes as $route) {
            $route = (string) $route;
            if (!str_contains($route, '/import') && !str_contains($route, '/export')) {
                return base_url(ltrim($route, '/'));
            }
        }

        return base_url(ltrim($fallback, '/'));
    }

    private static function isContextAvailable(string $key, array $user): bool
    {
        if (str_starts_with($key, 'ppdb-teacher') || $key === 'ppdb-broadcast-guru') {
            return PpdbGate::teacherHasActiveAssignment($user);
        }

        if (str_starts_with($key, 'homeroom-')) {
            return in_array('wali_kelas', UserModuleRules::effectiveRoles($user), true);
        }

        if (str_starts_with($key, 'finance-bendahara-')) {
            return FinanceGate::isBendahara($user);
        }

        if (
            str_starts_with($key, 'finance-kepsek-')
            || str_starts_with($key, 'finance-headmaster-')
            || str_starts_with($key, 'digital-signatures')
            || $key === 'graduation-approvals'
        ) {
            return FinanceGate::isHeadmaster($user);
        }

        if ($key === 'ukk' || str_starts_with($key, 'finance-kaprodi-')) {
            return AcademicRoleGate::isKepalaProdi(null, $user);
        }

        if ($key === 'teacher-extracurricular-assessments') {
            $teacherId = (int) ($user['teacher_id'] ?? 0);
            $activeYear = SchoolYear::active();
            $activeYearId = (int) ($activeYear['id'] ?? 0);

            return $teacherId > 0 && $activeYearId > 0 && Extracurricular::teacherHasMentorship($teacherId, $activeYearId);
        }

        if ($key === 'teacher-prakerin-assessments') {
            $teacherId = (int) ($user['teacher_id'] ?? 0);
            $activeYear = SchoolYear::active();
            $activeYearId = (int) ($activeYear['id'] ?? 0);

            return $teacherId > 0 && $activeYearId > 0 && PrakerinPlace::teacherHasActivePlacements($teacherId, $activeYearId);
        }

        if ($key === 'student-graduation') {
            $studentId = (int) ($user['student_id'] ?? 0);
            if ($studentId <= 0) {
                return false;
            }

            $student = Student::findWithRelations($studentId);

            return $student !== null && (int) ($student['kelas_tingkat'] ?? 0) === 12;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $module
     *
     * @return array{description:string,steps:array<int,string>,tips:array<int,string>}
     */
    private static function fallbackGuide(array $module): array
    {
        $group = (string) ($module['group'] ?? 'Modul');
        $label = (string) ($module['label'] ?? 'modul ini');

        $templates = [
            'Master Data' => [
                'description' => 'Gunakan modul ini untuk menjaga data referensi tetap rapi dan siap dipakai fitur lain.',
                'steps' => [
                    'Cek data yang sudah tersedia sebelum menambahkan data baru.',
                    'Gunakan tambah, edit, atau hapus sesuai kebutuhan operasional.',
                    'Pastikan data utama sudah benar sebelum digunakan pada transaksi atau penilaian.',
                ],
                'tips' => [
                    'Hindari membuat data ganda karena data master dipakai lintas modul.',
                ],
            ],
            'PPDB' => [
                'description' => 'Gunakan modul ini untuk mengelola proses penerimaan peserta didik baru.',
                'steps' => [
                    'Pilih periode PPDB yang aktif.',
                    'Input atau verifikasi data pendaftar sesuai dokumen yang diterima.',
                    'Perbarui status tahapan agar laporan PPDB tetap akurat.',
                ],
                'tips' => [
                    'Pastikan nomor WhatsApp benar sebelum mengirim notifikasi.',
                ],
            ],
            'Keuangan Bendahara' => [
                'description' => 'Gunakan modul ini untuk mencatat, memverifikasi, dan merekap transaksi keuangan sekolah.',
                'steps' => [
                    'Cek data transaksi atau tagihan yang perlu ditindaklanjuti.',
                    'Verifikasi nominal, tanggal, dan bukti sebelum menyimpan.',
                    'Gunakan laporan untuk mencocokkan saldo dan rekap periode berjalan.',
                ],
                'tips' => [
                    'Simpan transaksi hanya setelah dokumen pendukung sudah sesuai.',
                ],
            ],
            'Siswa' => [
                'description' => 'Gunakan modul ini untuk melihat dan memperbarui data pribadi serta informasi akademik siswa.',
                'steps' => [
                    'Buka menu yang sesuai dengan kebutuhan: profil, berkas, nilai, atau presensi.',
                    'Periksa kembali data sebelum mengirim perubahan.',
                    'Hubungi wali kelas atau admin jika ada data yang tidak bisa diperbaiki sendiri.',
                ],
                'tips' => [
                    'Pastikan berkas yang diunggah jelas dan sesuai format yang diminta sekolah.',
                ],
            ],
        ];

        $guide = $templates[$group] ?? [
            'description' => sprintf('Gunakan menu %s sesuai tugas dan hak akses Anda.', $label),
            'steps' => [
                'Buka menu dari sidebar sesuai kebutuhan.',
                'Baca pesan validasi atau notifikasi setelah menyimpan data.',
                'Laporkan ke admin jika data yang dibutuhkan belum tersedia.',
            ],
            'tips' => [
                'Cek kembali pilihan tahun ajaran dan kelas sebelum mengolah data.',
            ],
        ];

        return $guide;
    }

    /**
     * @return array<string, array{description:string,steps:array<int,string>,tips?:array<int,string>}>
     */
    private static function guides(): array
    {
        return [
            'years' => [
                'description' => 'Mengatur tahun ajaran, semester aktif, dan kepala sekolah yang dipakai seluruh fitur akademik.',
                'steps' => ['Tambahkan tahun ajaran baru.', 'Aktifkan tahun ajaran yang sedang berjalan.', 'Pastikan semester aktif dan kepala sekolah sudah benar.'],
                'tips' => ['Ubah tahun ajaran aktif sebelum input nilai, presensi, atau cetak rapor.'],
            ],
            'teachers' => [
                'description' => 'Mengelola data guru, status aktif, akun login guru, dan profil pengajar.',
                'steps' => ['Tambahkan atau impor data guru.', 'Lengkapi NIP, kontak, jabatan, dan status.', 'Gunakan reset password jika guru tidak bisa login.'],
                'tips' => ['Nonaktifkan guru yang sudah tidak mengajar agar tidak muncul di penugasan baru.'],
            ],
            'students' => [
                'description' => 'Mengelola data siswa, kelas, identitas, status Dapodik, foto, dan dokumen siswa.',
                'steps' => ['Cari siswa berdasarkan nama, NIS, NISN, atau kelas.', 'Lengkapi biodata dan data wali.', 'Gunakan menu edit untuk memperbarui status dan kelas.'],
                'tips' => ['Cek NISN/NIK sebelum membuat data baru untuk mencegah duplikasi siswa.'],
            ],
            'subjects' => [
                'description' => 'Melihat atau mengelola daftar mata pelajaran sesuai tahun ajaran dan kurikulum.',
                'steps' => ['Pilih tahun ajaran aktif.', 'Cek kode, nama, kelompok, dan KKM/parameter mapel.', 'Admin dapat menambah atau memperbaiki data mata pelajaran.'],
                'tips' => ['Guru dan siswa menggunakan data ini sebagai acuan nilai dan jadwal.'],
            ],
            'subject-teachers' => [
                'description' => 'Menetapkan guru pengampu untuk mata pelajaran dan kelas.',
                'steps' => ['Pilih tahun ajaran.', 'Tentukan mata pelajaran, guru, kelas, dan semester.', 'Simpan penugasan agar guru dapat menginput nilai.'],
                'tips' => ['Jika guru tidak melihat menu input nilai, cek penugasan guru pengampu terlebih dahulu.'],
            ],
            'lesson-schedules' => [
                'description' => 'Mengelola jadwal pelajaran per kelas dan guru.',
                'steps' => ['Pilih tahun ajaran dan kelas.', 'Input hari, jam, mata pelajaran, dan guru.', 'Cek bentrok jadwal sebelum menyimpan final.'],
                'tips' => ['Gunakan generate jadwal untuk menyusun draft awal bila data pengampu sudah lengkap.'],
            ],
            'teacher-subject-assessments' => [
                'description' => 'Menu guru mata pelajaran untuk menginput nilai pengetahuan, keterampilan, atau TP Kurikulum Merdeka.',
                'steps' => ['Pilih penugasan mengajar.', 'Lengkapi komponen/KD/TP sesuai kurikulum kelas.', 'Input nilai siswa lalu simpan berkala.'],
                'tips' => ['Pastikan kelas memakai kurikulum yang benar sebelum menambah KD atau TP.'],
            ],
            'teacher-attendance' => [
                'description' => 'Membuat sesi presensi QR agar siswa dapat melakukan scan kehadiran.',
                'steps' => ['Buat sesi presensi untuk kelas dan mata pelajaran.', 'Tampilkan QR kepada siswa.', 'Pantau siswa yang sudah scan dan lengkapi yang belum hadir.'],
                'tips' => ['Tutup sesi jika pembelajaran sudah selesai agar scan tidak berlangsung di luar waktu.'],
            ],
            'teacher-attendance-recap' => [
                'description' => 'Melihat rekap presensi siswa dari sesi yang dibuat guru.',
                'steps' => ['Pilih periode atau kelas.', 'Cek status hadir, izin, sakit, atau alfa.', 'Gunakan rekap sebagai bahan laporan ke wali kelas.'],
                'tips' => ['Perbaiki data presensi dari sesi terkait sebelum rekap digunakan.'],
            ],
            'homeroom-students' => [
                'description' => 'Wali kelas melihat dan memantau data siswa pada kelas binaannya.',
                'steps' => ['Pilih kelas yang diampu.', 'Cek biodata, kontak wali, dan status siswa.', 'Koordinasikan koreksi data dengan admin bila perlu.'],
                'tips' => ['Gunakan data ini sebelum cetak buku induk atau rekap kelas.'],
            ],
            'homeroom-attendance' => [
                'description' => 'Wali kelas menginput atau merapikan presensi harian siswa.',
                'steps' => ['Pilih tanggal dan kelas.', 'Isi status kehadiran setiap siswa.', 'Simpan dan cek rekap sebelum akhir bulan.'],
                'tips' => ['Isi catatan untuk izin, sakit, atau kasus khusus agar laporan lebih jelas.'],
            ],
            'homeroom-ledger' => [
                'description' => 'Melihat rekap nilai kelas dari semua mata pelajaran.',
                'steps' => ['Pilih kelas dan semester.', 'Cek nilai yang sudah masuk dari guru mapel.', 'Tindaklanjuti nilai kosong atau belum lengkap.'],
                'tips' => ['Hubungi guru pengampu jika ada nilai yang belum muncul.'],
            ],
            'homeroom-grade-upload' => [
                'description' => 'Mengunggah nilai rescue atau perbaikan nilai melalui template spreadsheet.',
                'steps' => ['Unduh template dari sistem.', 'Isi nilai sesuai kolom dan siswa yang tersedia.', 'Unggah kembali template dan cek validasi hasil import.'],
                'tips' => ['Jangan mengubah struktur kolom template agar proses import tidak gagal.'],
            ],
            'homeroom-transcripts' => [
                'description' => 'Mencetak transkrip nilai siswa dari data akademik yang sudah lengkap, dengan dukungan cetak massal dan tanda tangan digital.',
                'steps' => ['Pilih kelas dan siswa untuk melihat transkrip.', 'Gunakan "Cetak Transkrip Siswa" untuk mencetak per siswa.', 'Gunakan "Cetak Semua Transkrip" di card Aksi Kelas untuk mencetak semua siswa sekaligus.', 'Ajukan TTD Digital per siswa, atau gunakan "Ajukan TTD Semua Siswa" untuk semua siswa sekelas.'],
                'tips' => ['Pastikan nilai akhir sudah final sebelum transkrip dibagikan.', 'Cetak Semua Transkrip menghasilkan halaman F4 terpisah per siswa, siap untuk ditandatangani.'],
            ],
            'homeroom-p5' => [
                'description' => 'Mengelola projek P5 untuk kelas Kurikulum Merdeka.',
                'steps' => ['Buat projek dan elemen yang dinilai.', 'Input capaian siswa.', 'Cek ringkasan sebelum mencetak rapor P5.'],
                'tips' => ['Gunakan deskripsi capaian yang konsisten untuk memudahkan pembacaan rapor.'],
            ],
            'finance-siswa-dashboard' => [
                'description' => 'Siswa melihat tagihan, status pembayaran, dan riwayat keuangan pribadi.',
                'steps' => ['Cek daftar tagihan yang aktif.', 'Buka detail tagihan untuk melihat nominal dan status.', 'Unggah bukti pembayaran bila diminta sekolah.'],
                'tips' => ['Simpan bukti pembayaran sampai status berubah menjadi lunas.'],
            ],
            'finance-bendahara-payments' => [
                'description' => 'Bendahara memverifikasi pembayaran siswa dari bukti yang masuk.',
                'steps' => ['Buka daftar pembayaran menunggu verifikasi.', 'Cocokkan nominal dan bukti transfer.', 'Setujui atau tolak dengan catatan yang jelas.'],
                'tips' => ['Gunakan catatan penolakan agar siswa tahu perbaikan yang diperlukan.'],
            ],
            'finance-bendahara-billings' => [
                'description' => 'Bendahara membuat dan mengelola tagihan siswa.',
                'steps' => ['Pilih kategori dan periode tagihan.', 'Tentukan siswa atau kelas yang menerima tagihan.', 'Cek total tagihan sebelum dipublikasikan.'],
                'tips' => ['Gunakan kategori tagihan yang konsisten agar laporan mudah dibaca.'],
            ],
            'finance-guru-loans' => [
                'description' => 'Guru mengajukan kasbon dan memantau status persetujuannya.',
                'steps' => ['Isi nominal dan alasan pengajuan.', 'Kirim pengajuan untuk diverifikasi bendahara.', 'Pantau status sampai pencairan atau penolakan.'],
                'tips' => ['Lengkapi alasan pengajuan agar proses review lebih cepat.'],
            ],
            'finance-guru-activities' => [
                'description' => 'Guru mengajukan dana kegiatan dan laporan pertanggungjawaban.',
                'steps' => ['Buat pengajuan dana sesuai kegiatan.', 'Tunggu verifikasi dan persetujuan.', 'Lengkapi realisasi penggunaan setelah dana digunakan.'],
                'tips' => ['Simpan bukti pengeluaran untuk pertanggungjawaban.'],
            ],
            'digital-signatures' => [
                'description' => 'Kepala sekolah menyetujui atau meninjau permintaan tanda tangan digital rapor.',
                'steps' => ['Buka daftar dokumen menunggu persetujuan.', 'Periksa kelas, siswa, dan jenis dokumen.', 'Setujui jika dokumen sudah benar atau kembalikan untuk diperbaiki.'],
                'tips' => ['Pastikan dokumen sudah final sebelum disetujui.'],
            ],
            'ppdb-registrants' => [
                'description' => 'Admin PPDB mengelola data pendaftar, status seleksi, pengumuman, daftar ulang, dan pembayaran.',
                'steps' => ['Pilih periode PPDB.', 'Tambah atau periksa data pendaftar.', 'Perbarui status setiap tahapan sesuai proses panitia.'],
                'tips' => ['Gunakan tombol Hapus hanya untuk data salah atau dobel.'],
            ],
            'ppdb-teacher-registrants' => [
                'description' => 'Guru penanggung jawab PPDB menginput dan memantau pendaftar pada periode yang ditugaskan.',
                'steps' => ['Pilih periode tugas.', 'Input data calon siswa yang datang langsung.', 'Pantau status dan kirim informasi jika diperlukan.'],
                'tips' => ['Pastikan nomor WhatsApp pendaftar benar sebelum menyimpan.'],
            ],
            'student-profile' => [
                'description' => 'Siswa memperbarui data diri yang diminta sekolah.',
                'steps' => ['Buka formulir data diri.', 'Lengkapi alamat, kontak, dan data wali.', 'Simpan perubahan dan cek pesan validasi.'],
                'tips' => ['Hubungi admin jika data penting terkunci atau tidak bisa diedit.'],
            ],
            'student-documents' => [
                'description' => 'Siswa mengunggah atau mengelola berkas fisik yang diperlukan sekolah.',
                'steps' => ['Pilih jenis dokumen.', 'Unggah file yang jelas dan sesuai format.', 'Cek daftar berkas untuk memastikan upload berhasil.'],
                'tips' => ['Gunakan scan/foto yang terbaca jelas.'],
            ],
            'student-grades' => [
                'description' => 'Siswa melihat nilai yang sudah dipublikasikan sekolah.',
                'steps' => ['Pilih semester atau tahun ajaran.', 'Cek nilai per mata pelajaran.', 'Hubungi guru terkait jika ada nilai yang belum sesuai.'],
                'tips' => ['Nilai yang belum final bisa berubah sesuai proses guru dan wali kelas.'],
            ],
        ];
    }
}

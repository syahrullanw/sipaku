<?php

namespace App\Services;

use App\Models\SchoolProfile;
use App\Models\Teacher;
use Core\Request;
use Core\Session;

class TeacherProfileForm
{
    /**
     * @return array{
     *     genders: array<string, string>,
     *     religions: array<string, string>,
     *     maritalStatuses: array<string, string>,
     *     gtkTypes: array<string, string>,
     *     employmentStatuses: array<string, string>,
     *     educationLevels: array<string, string>,
     *     studyStatuses: array<string, string>
     * }
     */
    public static function options(): array
    {
        return [
            'genders' => [
                'L' => 'Laki-laki',
                'P' => 'Perempuan',
            ],
            'religions' => [
                'Islam' => 'Islam',
                'Kristen Protestan' => 'Kristen Protestan',
                'Kristen Katolik' => 'Kristen Katolik',
                'Hindu' => 'Hindu',
                'Buddha' => 'Buddha',
                'Konghucu' => 'Konghucu',
                'Kepercayaan' => 'Kepercayaan Lainnya',
            ],
            'maritalStatuses' => [
                'Belum Menikah' => 'Belum Menikah',
                'Menikah' => 'Menikah',
                'Cerai Hidup' => 'Cerai Hidup',
                'Cerai Mati' => 'Cerai Mati',
            ],
            'gtkTypes' => [
                'Guru Mata Pelajaran' => 'Guru Mata Pelajaran',
                'Guru BK' => 'Guru BK',
                'Kepala Sekolah' => 'Kepala Sekolah',
                'Wakil Kepala Sekolah' => 'Wakil Kepala Sekolah',
                'Wali Kelas' => 'Wali Kelas',
                'Pembina Ekskul' => 'Pembina Ekskul',
                'Tenaga Kependidikan' => 'Tenaga Kependidikan',
            ],
            'employmentStatuses' => [
                'PNS' => 'PNS',
                'PPPK' => 'PPPK',
                'Honorer' => 'Honorer',
                'GTT' => 'Guru Tidak Tetap',
                'GTY' => 'Guru Tetap Yayasan',
                'Lainnya' => 'Lainnya',
            ],
            'educationLevels' => [
                'SMA/SMK' => 'SMA / SMK',
                'D3' => 'Diploma 3 (D3)',
                'S1' => 'Sarjana (S1)',
                'S2' => 'Magister (S2)',
                'S3' => 'Doktor (S3)',
                'Lainnya' => 'Lainnya',
            ],
            'studyStatuses' => [
                'Tidak Kuliah' => 'Tidak Kuliah',
                'Sedang Kuliah' => 'Sedang Kuliah',
                'Lulus' => 'Sudah Lulus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function validate(Request $request, ?int $ignoreId = null): ?array
    {
        $data = [
            'nip' => trim((string) $request->input('nip', '')),
            'nama' => trim((string) $request->input('nama', '')),
            'email' => trim((string) $request->input('email', '')),
            'telepon' => trim((string) $request->input('telepon', '')),
            'alamat' => trim((string) $request->input('alamat', '')),
            'nomor_surat_tugas' => trim((string) $request->input('nomor_surat_tugas', '')),
            'tanggal_surat_tugas' => trim((string) $request->input('tanggal_surat_tugas', '')),
            'sekolah_induk' => trim((string) $request->input('sekolah_induk', '')),
            'nik' => trim((string) $request->input('nik', '')),
            'jenis_kelamin' => trim((string) $request->input('jenis_kelamin', '')),
            'tempat_lahir' => trim((string) $request->input('tempat_lahir', '')),
            'tanggal_lahir' => trim((string) $request->input('tanggal_lahir', '')),
            'nama_ibu_kandung' => trim((string) $request->input('nama_ibu_kandung', '')),
            'agama' => trim((string) $request->input('agama', '')),
            'status_perkawinan' => trim((string) $request->input('status_perkawinan', '')),
            'nama_pasangan' => trim((string) $request->input('nama_pasangan', '')),
            'pekerjaan_pasangan' => trim((string) $request->input('pekerjaan_pasangan', '')),
            'npwp' => trim((string) $request->input('npwp', '')),
            'nama_wp' => trim((string) $request->input('nama_wp', '')),
            'jenis_gtk' => trim((string) $request->input('jenis_gtk', '')),
            'nuptk' => trim((string) $request->input('nuptk', '')),
            'status_kepegawaian' => trim((string) $request->input('status_kepegawaian', '')),
            'sk_pengangkatan' => trim((string) $request->input('sk_pengangkatan', '')),
            'tmt_pengangkatan' => trim((string) $request->input('tmt_pengangkatan', '')),
            'lembaga_pengangkat' => trim((string) $request->input('lembaga_pengangkat', '')),
            'kartu_pasangan' => trim((string) $request->input('kartu_pasangan', '')),
            'pendidikan_terakhir' => trim((string) $request->input('pendidikan_terakhir', '')),
            'status_kuliah' => trim((string) $request->input('status_kuliah', '')),
            'tahun_pensiun' => trim((string) $request->input('tahun_pensiun', '')),
            'tugas_tambahan' => trim((string) $request->input('tugas_tambahan', '')),
        ];

        $options = self::options();

        if ($data['nama'] === '') {
            Session::flash('error', 'Nama guru wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Format email tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['nik'] === '') {
            Session::flash('error', 'NIK guru wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        if (!ctype_digit($data['nik']) || strlen($data['nik']) !== 16) {
            Session::flash('error', 'NIK guru harus berisi 16 digit angka.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['jenis_kelamin'] === '' || !array_key_exists($data['jenis_kelamin'], $options['genders'])) {
            Session::flash('error', 'Jenis kelamin guru wajib dipilih.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['tempat_lahir'] === '') {
            Session::flash('error', 'Tempat lahir guru wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        $birthDate = self::normalizeDate($data['tanggal_lahir']);
        if ($birthDate === false || $birthDate === null) {
            Session::flash('error', 'Tanggal lahir guru tidak valid (gunakan format YYYY-MM-DD).');
            Session::flashInput($request->all());

            return null;
        }
        $data['tanggal_lahir'] = $birthDate;

        if ($data['nama_ibu_kandung'] === '') {
            Session::flash('error', 'Nama ibu kandung guru wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['agama'] === '' || !array_key_exists($data['agama'], $options['religions'])) {
            Session::flash('error', 'Agama guru wajib dipilih.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['status_perkawinan'] !== '' && !array_key_exists($data['status_perkawinan'], $options['maritalStatuses'])) {
            Session::flash('error', 'Status perkawinan guru tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['status_kepegawaian'] === '' || !array_key_exists($data['status_kepegawaian'], $options['employmentStatuses'])) {
            Session::flash('error', 'Status kepegawaian guru wajib dipilih.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['jenis_gtk'] === '' || !array_key_exists($data['jenis_gtk'], $options['gtkTypes'])) {
            Session::flash('error', 'Jenis GTK guru wajib dipilih.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['pendidikan_terakhir'] !== '' && !array_key_exists($data['pendidikan_terakhir'], $options['educationLevels'])) {
            Session::flash('error', 'Pendidikan terakhir guru tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['status_kuliah'] !== '' && !array_key_exists($data['status_kuliah'], $options['studyStatuses'])) {
            Session::flash('error', 'Status kuliah guru tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        $assignmentDate = self::normalizeDate($data['tanggal_surat_tugas']);
        if ($assignmentDate === false) {
            Session::flash('error', 'Tanggal surat tugas tidak valid (gunakan format YYYY-MM-DD).');
            Session::flashInput($request->all());

            return null;
        }

        $tmt = self::normalizeDate($data['tmt_pengangkatan']);
        if ($tmt === false) {
            Session::flash('error', 'TMT Pengangkatan tidak valid (gunakan format YYYY-MM-DD).');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['npwp'] !== '') {
            $npwpDigits = preg_replace('/[^0-9]/', '', $data['npwp']);
            if ($npwpDigits === '' || strlen($npwpDigits) < 15) {
                Session::flash('error', 'NPWP guru harus berisi minimal 15 digit angka.');
                Session::flashInput($request->all());

                return null;
            }

            if ($data['nama_wp'] === '') {
                Session::flash('error', 'Nama wajib pajak wajib diisi jika NPWP dicantumkan.');
                Session::flashInput($request->all());

                return null;
            }

            $data['npwp'] = $npwpDigits;
        }

        if ($data['tahun_pensiun'] !== '') {
            if (!ctype_digit($data['tahun_pensiun'])) {
                Session::flash('error', 'Tahun pensiun harus berupa angka.');
                Session::flashInput($request->all());

                return null;
            }

            $year = (int) $data['tahun_pensiun'];
            if ($year < 1950 || $year > 2100) {
                Session::flash('error', 'Tahun pensiun berada di luar rentang wajar.');
                Session::flashInput($request->all());

                return null;
            }

            $data['tahun_pensiun'] = $year;
        } else {
            $data['tahun_pensiun'] = null;
        }

        if ($data['sekolah_induk'] === '') {
            $school = SchoolProfile::first();
            if (is_array($school) && !empty($school['nama'])) {
                $data['sekolah_induk'] = (string) $school['nama'];
            } else {
                $data['sekolah_induk'] = null;
            }
        }

        if ($data['nip'] !== '' && Teacher::exists(['nip' => $data['nip']], $ignoreId)) {
            Session::flash('error', 'NIP guru sudah digunakan.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['email'] !== '' && Teacher::exists(['email' => $data['email']], $ignoreId)) {
            Session::flash('error', 'Email guru sudah terdaftar.');
            Session::flashInput($request->all());

            return null;
        }

        foreach (['nip', 'email', 'telepon', 'alamat', 'nomor_surat_tugas', 'nama_pasangan', 'pekerjaan_pasangan', 'nama_wp', 'sk_pengangkatan', 'lembaga_pengangkat', 'kartu_pasangan', 'tugas_tambahan', 'nuptk'] as $nullableKey) {
            if ($data[$nullableKey] === '') {
                $data[$nullableKey] = null;
            }
        }

        if ($data['status_perkawinan'] === '') {
            $data['status_perkawinan'] = null;
        }

        if ($data['pendidikan_terakhir'] === '') {
            $data['pendidikan_terakhir'] = null;
        }

        if ($data['status_kuliah'] === '') {
            $data['status_kuliah'] = null;
        }

        $data['tanggal_surat_tugas'] = $assignmentDate;
        $data['tmt_pengangkatan'] = $tmt;

        return $data;
    }

    /**
     * @return string|null|false
     */
    private static function normalizeDate(string $value)
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            return false;
        }

        return $date->format('Y-m-d');
    }
}


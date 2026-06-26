<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\Major;
use App\Models\SchoolProfile;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAcademicPosition;
use App\Models\UkkAssessor;
use App\Models\UkkDudi;
use App\Models\UkkExamPackage;
use App\Models\UkkSkkni;
use App\Models\UkkStudentAssessment;
use App\Support\AcademicRoleGate;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class UkkController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        [$guard, $teacherId, $activeYearId, $allowedMajors] = $this->guardKaprodiWithContext();

        if ($guard instanceof Response) {
            return $guard;
        }

        $activeYear = SchoolYear::find($activeYearId);

        $majorOptions = $this->majorOptions($allowedMajors);
        $selectedMajorId = (int) $request->query('jurusan_id', $majorOptions['first'] ?? 0);
        if (!in_array($selectedMajorId, $allowedMajors, true) && !empty($allowedMajors)) {
            $selectedMajorId = $allowedMajors[0];
        }

        $classes = $this->kelasDuaBelas($activeYearId, $allowedMajors);
        $selectedClassId = (int) $request->query('kelas_id', $classes['first_id'] ?? 0);
        if ($selectedClassId > 0 && !isset($classes['items'][$selectedClassId])) {
            $selectedClassId = $classes['first_id'] ?? 0;
        }

        $students = [];
        if ($selectedClassId > 0) {
            $students = Student::byClass($selectedClassId, $activeYearId);
        }

        $studentIds = array_values(array_filter(array_map(static fn ($s) => (int) ($s['id'] ?? 0), $students), static fn (int $id) => $id > 0));

        $packageList = UkkExamPackage::byYearAndMajors($activeYearId, [$selectedMajorId]);
        $skkniList = UkkSkkni::byYearAndMajors($activeYearId, [$selectedMajorId]);
        $dudiList = UkkDudi::byYearAndMajors($activeYearId, [$selectedMajorId]);
        $assessorMap = !empty($dudiList) ? UkkAssessor::mapByDudi(array_map(static fn ($d) => (int) $d['id'], $dudiList)) : [];
        $assessmentMap = !empty($studentIds) ? UkkStudentAssessment::mapByStudents($studentIds, $activeYearId) : [];

        $editingSkkniId = (int) $request->query('skkni_edit', 0);
        $editingSkkni = $editingSkkniId > 0 ? UkkSkkni::findForMajor($editingSkkniId, $activeYearId, [$selectedMajorId]) : null;
        $editingPackageId = (int) $request->query('paket_edit', 0);
        $editingPackage = $editingPackageId > 0 ? UkkExamPackage::findForMajor($editingPackageId, $activeYearId, [$selectedMajorId]) : null;
        $teacherOptions = Teacher::options(true);
        $editingDudiId = (int) $request->query('dudi_edit', 0);
        $editingDudi = $editingDudiId > 0 ? UkkDudi::findForMajor($editingDudiId, $activeYearId, [$selectedMajorId]) : null;
        $editingAssessorId = (int) $request->query('asesor_edit', 0);
        $editingAssessor = null;
        if ($editingAssessorId > 0) {
            $candidateAssessor = UkkAssessor::find($editingAssessorId);
            $candidateDudiId = $candidateAssessor['dudi_id'] ?? null;
            $candidateDudi = $candidateDudiId !== null ? UkkDudi::findForMajor((int) $candidateDudiId, $activeYearId, [$selectedMajorId]) : null;
            if ($candidateAssessor !== null && $candidateDudi !== null) {
                $editingAssessor = $candidateAssessor;
            }
        }

        $selectedTab = strtolower((string) $request->query('tab', 'nilai'));
        if (!in_array($selectedTab, ['nilai', 'master'], true)) {
            $selectedTab = 'nilai';
        }
        if ($editingPackage !== null || $editingSkkni !== null || $editingDudi !== null || $editingAssessor !== null) {
            $selectedTab = 'master';
        }

        return $this->render('ukk/index', [
            'title' => 'UKK & Skill Passport',
            'pageTitle' => 'UKK & Skill Passport',
            'activeMenu' => 'ukk',
            'activeYear' => $activeYear,
            'allowedMajorIds' => $allowedMajors,
            'majorOptions' => $majorOptions['options'],
            'selectedMajorId' => $selectedMajorId,
            'classOptions' => $classes['options'],
            'selectedClassId' => $selectedClassId,
            'students' => $students,
            'packageList' => $packageList,
            'skkniList' => $skkniList,
            'dudiList' => $dudiList,
            'teacherOptions' => $teacherOptions,
            'assessorMap' => $assessorMap,
            'assessmentMap' => $assessmentMap,
            'editingPackage' => $editingPackage,
            'editingSkkni' => $editingSkkni,
            'editingDudi' => $editingDudi,
            'editingAssessor' => $editingAssessor,
            'selectedTab' => $selectedTab,
        ]);
    }

    public function saveExamPackage(Request $request): Response
    {
        [$guard, $teacherId, $activeYearId, $allowedMajors] = $this->guardKaprodiWithContext();

        if ($guard instanceof Response) {
            return $guard;
        }

        if ($response = $this->guardCsrf($request, 'kaprodi/ukk')) {
            return $response;
        }

        $id = (int) $request->input('id', 0);
        $majorId = (int) $request->input('jurusan_id', 0);
        $payload = [
            'tahun_ajaran_id' => $activeYearId,
            'jurusan_id' => $majorId,
            'nama' => trim((string) $request->input('nama', '')),
            'deskripsi' => trim((string) $request->input('deskripsi', '')),
        ];

        if (!in_array($majorId, $allowedMajors, true)) {
            Session::flash('error', 'Anda tidak dapat mengelola paket ujian di jurusan lain.');

            return $this->redirect('kaprodi/ukk');
        }

        if ($payload['nama'] === '') {
            Session::flash('error', 'Nama paket ujian wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect('kaprodi/ukk?jurusan_id=' . $majorId . '&tab=master');
        }

        $payload['deskripsi'] = $payload['deskripsi'] !== '' ? $payload['deskripsi'] : null;
        $now = date('Y-m-d H:i:s');

        if ($id > 0) {
            $existing = UkkExamPackage::findForMajor($id, $activeYearId, [$majorId]);
            if ($existing === null) {
                Session::flash('error', 'Data paket ujian tidak ditemukan atau di luar kewenangan Anda.');

                return $this->redirect('kaprodi/ukk');
            }

            $payload['updated_at'] = $now;
            UkkExamPackage::updateById($id, $payload);
            Session::flash('success', 'Paket ujian berhasil diperbarui.');
        } else {
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;
            UkkExamPackage::create($payload);
            Session::flash('success', 'Paket ujian berhasil ditambahkan.');
        }

        return $this->redirect('kaprodi/ukk?jurusan_id=' . $majorId . '&tab=master');
    }

    public function deleteExamPackage(Request $request, int $id): Response
    {
        [$guard, $teacherId, $activeYearId, $allowedMajors] = $this->guardKaprodiWithContext();

        if ($guard instanceof Response) {
            return $guard;
        }

        if ($response = $this->guardCsrf($request, 'kaprodi/ukk')) {
            return $response;
        }

        $record = UkkExamPackage::findForMajor($id, $activeYearId, $allowedMajors);
        if ($record === null) {
            Session::flash('error', 'Data paket ujian tidak ditemukan.');

            return $this->redirect('kaprodi/ukk');
        }

        if (UkkSkkni::countByPackage($id) > 0) {
            Session::flash('error', 'Paket ujian masih terhubung dengan SKKNI. Hapus SKKNI terlebih dahulu.');

            return $this->redirect('kaprodi/ukk?jurusan_id=' . (int) ($record['jurusan_id'] ?? 0) . '&tab=master');
        }

        UkkExamPackage::deleteById($id);
        Session::flash('success', 'Paket ujian dihapus.');

        return $this->redirect('kaprodi/ukk?jurusan_id=' . (int) ($record['jurusan_id'] ?? 0) . '&tab=master');
    }

    public function saveSkkni(Request $request): Response
    {
        [$guard, $teacherId, $activeYearId, $allowedMajors] = $this->guardKaprodiWithContext();

        if ($guard instanceof Response) {
            return $guard;
        }

        if ($response = $this->guardCsrf($request, 'kaprodi/ukk')) {
            return $response;
        }

        $id = (int) $request->input('id', 0);
        $majorId = (int) $request->input('jurusan_id', 0);
        $packageId = (int) $request->input('paket_ujian_id', 0);
        $payload = [
            'tahun_ajaran_id' => $activeYearId,
            'jurusan_id' => $majorId,
            'paket_ujian_id' => $packageId,
            'kode' => trim((string) $request->input('kode', '')),
            'judul' => trim((string) $request->input('judul', '')),
            'deskripsi' => trim((string) $request->input('deskripsi', '')),
            'unit_kompetensi' => trim((string) $request->input('unit_kompetensi', '')),
        ];

        if (!in_array($majorId, $allowedMajors, true)) {
            Session::flash('error', 'Anda tidak dapat mengelola SKKNI di jurusan lain.');

            return $this->redirect('kaprodi/ukk');
        }

        if ($payload['kode'] === '' || $payload['judul'] === '') {
            Session::flash('error', 'Kode dan judul SKKNI wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect('kaprodi/ukk');
        }

        if ($packageId <= 0) {
            Session::flash('error', 'Pilih paket ujian terlebih dahulu.');
            Session::flashInput($request->all());

            return $this->redirect('kaprodi/ukk?jurusan_id=' . $majorId . '&tab=master');
        }

        $package = UkkExamPackage::findForMajor($packageId, $activeYearId, [$majorId]);
        if ($package === null) {
            Session::flash('error', 'Paket ujian tidak valid untuk jurusan ini.');
            Session::flashInput($request->all());

            return $this->redirect('kaprodi/ukk?jurusan_id=' . $majorId . '&tab=master');
        }

        $now = date('Y-m-d H:i:s');
        $payload['deskripsi'] = $payload['deskripsi'] !== '' ? $payload['deskripsi'] : null;
        $payload['unit_kompetensi'] = $payload['unit_kompetensi'] !== '' ? $payload['unit_kompetensi'] : null;

        if ($id > 0) {
            $existing = UkkSkkni::findForMajor($id, $activeYearId, [$majorId]);
            if ($existing === null) {
                Session::flash('error', 'Data SKKNI tidak ditemukan atau di luar kewenangan Anda.');

                return $this->redirect('kaprodi/ukk');
            }

            $payload['updated_at'] = $now;
            UkkSkkni::updateById($id, $payload);
            Session::flash('success', 'SKKNI berhasil diperbarui.');
        } else {
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;
            UkkSkkni::create($payload);
            Session::flash('success', 'SKKNI berhasil ditambahkan.');
        }

        return $this->redirect('kaprodi/ukk?jurusan_id=' . $majorId . '&tab=master');
    }

    public function deleteSkkni(Request $request, int $id): Response
    {
        [$guard, $teacherId, $activeYearId, $allowedMajors] = $this->guardKaprodiWithContext();

        if ($guard instanceof Response) {
            return $guard;
        }

        if ($response = $this->guardCsrf($request, 'kaprodi/ukk')) {
            return $response;
        }

        $record = UkkSkkni::findForMajor($id, $activeYearId, $allowedMajors);
        if ($record === null) {
            Session::flash('error', 'Data SKKNI tidak ditemukan.');

            return $this->redirect('kaprodi/ukk');
        }

        UkkSkkni::deleteById($id);
        Session::flash('success', 'SKKNI dihapus.');

        return $this->redirect('kaprodi/ukk?jurusan_id=' . (int) ($record['jurusan_id'] ?? 0) . '&tab=master');
    }

    public function saveDudi(Request $request): Response
    {
        [$guard, $teacherId, $activeYearId, $allowedMajors] = $this->guardKaprodiWithContext();

        if ($guard instanceof Response) {
            return $guard;
        }

        if ($response = $this->guardCsrf($request, 'kaprodi/ukk')) {
            return $response;
        }

        $id = (int) $request->input('id', 0);
        $majorId = (int) $request->input('jurusan_id', 0);
        $payload = [
            'tahun_ajaran_id' => $activeYearId,
            'jurusan_id' => $majorId,
            'nama' => trim((string) $request->input('nama', '')),
            'penanggung_jawab' => trim((string) $request->input('penanggung_jawab', '')),
            'kontak' => trim((string) $request->input('kontak', '')),
            'alamat' => trim((string) $request->input('alamat', '')),
            'catatan' => trim((string) $request->input('catatan', '')),
        ];

        if (!in_array($majorId, $allowedMajors, true)) {
            Session::flash('error', 'Anda tidak dapat mengelola DUDI di jurusan lain.');

            return $this->redirect('kaprodi/ukk');
        }

        if ($payload['nama'] === '') {
            Session::flash('error', 'Nama DUDI wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect('kaprodi/ukk?jurusan_id=' . $majorId . '&tab=master');
        }

        foreach (['penanggung_jawab', 'kontak', 'alamat', 'catatan'] as $field) {
            if ($payload[$field] === '') {
                $payload[$field] = null;
            }
        }

        $now = date('Y-m-d H:i:s');

        if ($id > 0) {
            $existing = UkkDudi::findForMajor($id, $activeYearId, $allowedMajors);
            if ($existing === null) {
                Session::flash('error', 'Data DUDI tidak ditemukan atau di luar kewenangan Anda.');

                return $this->redirect('kaprodi/ukk');
            }

            $payload['updated_at'] = $now;
            UkkDudi::updateById($id, $payload);
            Session::flash('success', 'Data DUDI diperbarui.');
        } else {
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;
            UkkDudi::create($payload);
            Session::flash('success', 'Data DUDI ditambahkan.');
        }

        return $this->redirect('kaprodi/ukk?jurusan_id=' . $majorId . '&tab=master');
    }

    public function deleteDudi(Request $request, int $id): Response
    {
        [$guard, $teacherId, $activeYearId, $allowedMajors] = $this->guardKaprodiWithContext();

        if ($guard instanceof Response) {
            return $guard;
        }

        if ($response = $this->guardCsrf($request, 'kaprodi/ukk')) {
            return $response;
        }

        $record = UkkDudi::findForMajor($id, $activeYearId, $allowedMajors);
        if ($record === null) {
            Session::flash('error', 'Data DUDI tidak ditemukan.');

            return $this->redirect('kaprodi/ukk');
        }

        UkkDudi::deleteById($id);
        Session::flash('success', 'Data DUDI dihapus.');

        return $this->redirect('kaprodi/ukk?jurusan_id=' . (int) ($record['jurusan_id'] ?? 0) . '&tab=master');
    }

    public function saveAssessor(Request $request): Response
    {
        [$guard, $teacherId, $activeYearId, $allowedMajors] = $this->guardKaprodiWithContext();

        if ($guard instanceof Response) {
            return $guard;
        }

        if ($response = $this->guardCsrf($request, 'kaprodi/ukk')) {
            return $response;
        }

        $id = (int) $request->input('id', 0);
        $dudiId = (int) $request->input('dudi_id', 0);
        $dudi = $dudiId > 0 ? UkkDudi::find($dudiId) : null;

        if ($dudi === null || (int) ($dudi['tahun_ajaran_id'] ?? 0) !== $activeYearId || !in_array((int) ($dudi['jurusan_id'] ?? 0), $allowedMajors, true)) {
            Session::flash('error', 'DUDI tidak valid untuk data asesor.');

            return $this->redirect('kaprodi/ukk');
        }

        $payload = [
            'dudi_id' => $dudiId,
            'nama' => trim((string) $request->input('nama', '')),
            'jabatan' => trim((string) $request->input('jabatan', '')),
            'nomor_registrasi' => trim((string) $request->input('nomor_registrasi', '')),
            'kontak' => trim((string) $request->input('kontak', '')),
        ];

        if ($payload['nama'] === '') {
            Session::flash('error', 'Nama asesor wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect('kaprodi/ukk?jurusan_id=' . (int) $dudi['jurusan_id'] . '&tab=master');
        }

        foreach (['jabatan', 'nomor_registrasi', 'kontak'] as $field) {
            if ($payload[$field] === '') {
                $payload[$field] = null;
            }
        }

        $now = date('Y-m-d H:i:s');

        if ($id > 0) {
            $existing = UkkAssessor::find($id);
            if ($existing === null || (int) ($existing['dudi_id'] ?? 0) !== $dudiId) {
                Session::flash('error', 'Data asesor tidak ditemukan.');

                return $this->redirect('kaprodi/ukk');
            }

            $payload['updated_at'] = $now;
            UkkAssessor::updateById($id, $payload);
            Session::flash('success', 'Data asesor diperbarui.');
        } else {
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;
            UkkAssessor::create($payload);
            Session::flash('success', 'Data asesor ditambahkan.');
        }

        return $this->redirect('kaprodi/ukk?jurusan_id=' . (int) $dudi['jurusan_id'] . '&tab=master');
    }

    public function deleteAssessor(Request $request, int $id): Response
    {
        [$guard, $teacherId, $activeYearId, $allowedMajors] = $this->guardKaprodiWithContext();

        if ($guard instanceof Response) {
            return $guard;
        }

        if ($response = $this->guardCsrf($request, 'kaprodi/ukk')) {
            return $response;
        }

        $existing = UkkAssessor::find($id);
        $dudiId = $existing['dudi_id'] ?? null;
        $dudi = $dudiId !== null ? UkkDudi::find((int) $dudiId) : null;

        if ($dudi === null || (int) ($dudi['tahun_ajaran_id'] ?? 0) !== $activeYearId || !in_array((int) ($dudi['jurusan_id'] ?? 0), $allowedMajors, true)) {
            Session::flash('error', 'Data asesor tidak ditemukan.');

            return $this->redirect('kaprodi/ukk');
        }

        UkkAssessor::deleteById($id);
        Session::flash('success', 'Data asesor dihapus.');

        return $this->redirect('kaprodi/ukk?jurusan_id=' . (int) $dudi['jurusan_id'] . '&tab=master');
    }

    public function saveAssessments(Request $request): Response
    {
        [$guard, $teacherId, $activeYearId, $allowedMajors] = $this->guardKaprodiWithContext();

        if ($guard instanceof Response) {
            return $guard;
        }

        if ($response = $this->guardCsrf($request, 'kaprodi/ukk')) {
            return $response;
        }

        $classId = (int) $request->input('kelas_id', 0);
        $class = $classId > 0 ? Classroom::findWithRelations($classId) : null;

        if ($class === null || (int) ($class['tingkat'] ?? 0) !== 12 || (int) ($class['tahun_ajaran_id'] ?? 0) !== $activeYearId || !in_array((int) ($class['jurusan_id'] ?? 0), $allowedMajors, true)) {
            Session::flash('error', 'Kelas tidak valid untuk input UKK.');

            return $this->redirect('kaprodi/ukk');
        }

        $entries = $request->input('assessments', []);
        $packageId = (int) $request->input('paket_ujian_id', 0);

        if ($packageId <= 0) {
            Session::flash('error', 'Pilih paket ujian terlebih dahulu.');

            return $this->redirect('kaprodi/ukk?jurusan_id=' . (int) $class['jurusan_id'] . '&kelas_id=' . $classId . '&tab=nilai');
        }

        $package = UkkExamPackage::findForMajor($packageId, $activeYearId, [$class['jurusan_id']]);
        if ($package === null) {
            Session::flash('error', 'Paket ujian tidak valid untuk jurusan ini.');

            return $this->redirect('kaprodi/ukk?jurusan_id=' . (int) $class['jurusan_id'] . '&kelas_id=' . $classId . '&tab=nilai');
        }

        $packageSkkni = UkkSkkni::byPackage($packageId);
        $skkniId = (int) ($packageSkkni[0]['id'] ?? 0);

        if ($skkniId <= 0) {
            Session::flash('error', 'SKKNI pada paket ini belum diisi.');

            return $this->redirect('kaprodi/ukk?jurusan_id=' . (int) $class['jurusan_id'] . '&kelas_id=' . $classId . '&tab=nilai');
        }

        $internalAssessorTeacherId = (int) $request->input('internal_assessor_teacher_id', 0);
        $internalAssessorName = trim((string) $request->input('internal_assessor_name', ''));
        if ($internalAssessorName !== '') {
            $internalAssessorTeacherId = 0;
        }

        if ($internalAssessorTeacherId > 0) {
            $teacher = Teacher::find($internalAssessorTeacherId);
            if ($teacher === null) {
                Session::flash('error', 'Penguji internal tidak valid.');
                Session::flashInput($request->all());

                return $this->redirect('kaprodi/ukk?jurusan_id=' . (int) $class['jurusan_id'] . '&kelas_id=' . $classId . '&tab=nilai');
            }
        }

        $dudiId = (int) $request->input('dudi_id', 0);
        $dudi = $dudiId > 0 ? UkkDudi::findForMajor($dudiId, $activeYearId, [$class['jurusan_id']]) : null;

        if ($dudi === null) {
            Session::flash('error', 'DUDI penguji tidak valid untuk jurusan ini.');

            return $this->redirect('kaprodi/ukk?jurusan_id=' . (int) $class['jurusan_id'] . '&kelas_id=' . $classId . '&tab=nilai');
        }

        $certificateDate = trim((string) $request->input('tanggal_sertifikat', ''));
        if ($certificateDate === '' || $certificateDate === '0000-00-00') {
            $certificateDate = null;
        }

        $studentIds = array_keys(is_array($entries) ? $entries : []);
        $studentIds = array_values(array_filter(array_map('intval', $studentIds), static fn (int $id) => $id > 0));
        $classStudents = Student::byClass($classId, $activeYearId);
        $allowedStudentIds = array_map(static fn ($s) => (int) ($s['id'] ?? 0), $classStudents);

        $saved = 0;

        foreach ($studentIds as $studentId) {
            if (!in_array($studentId, $allowedStudentIds, true)) {
                continue;
            }

            $row = $entries[$studentId] ?? [];
            $assessorId = isset($row['asesor_id']) ? (int) $row['asesor_id'] : 0;

            if ($assessorId > 0) {
                $assessor = UkkAssessor::find($assessorId);
                if ($assessor === null || (int) ($assessor['dudi_id'] ?? 0) !== $dudiId) {
                    $assessorId = null;
                }
            } else {
                $assessorId = null;
            }

            $nilaiTeori = $this->nullableScore($row['nilai_teori'] ?? null);
            $nilaiPraktik = $this->nullableScore($row['nilai_praktik'] ?? null);
            $nilaiAkhir = UkkStudentAssessment::calculateFinalScore($nilaiTeori, $nilaiPraktik);

            $payload = [
                'kelas_id' => $classId,
                'jurusan_id' => (int) $class['jurusan_id'],
                'skkni_id' => $skkniId,
                'dudi_id' => $dudiId,
                'asesor_id' => $assessorId,
                'internal_assessor_teacher_id' => $internalAssessorTeacherId > 0 ? $internalAssessorTeacherId : null,
                'internal_assessor_name' => $internalAssessorName !== '' ? $internalAssessorName : null,
                'nilai_teori' => $nilaiTeori,
                'nilai_praktik' => $nilaiPraktik,
                'nilai_akhir' => $nilaiAkhir,
                'predikat' => trim((string) ($row['predikat'] ?? '')),
                'catatan' => trim((string) ($row['catatan'] ?? '')),
                'nomor_sertifikat' => trim((string) ($row['nomor_sertifikat'] ?? '')),
                'tanggal_sertifikat' => $certificateDate,
            ];

            foreach (['predikat', 'catatan', 'nomor_sertifikat', 'tanggal_sertifikat'] as $field) {
                if ($payload[$field] === '') {
                    $payload[$field] = null;
                }
            }

            if (UkkStudentAssessment::upsertForStudent($studentId, $activeYearId, $payload)) {
                $saved++;
            }
        }

        Session::flash('success', $saved . ' penilaian UKK tersimpan.');

        return $this->redirect('kaprodi/ukk?jurusan_id=' . (int) $class['jurusan_id'] . '&kelas_id=' . $classId . '&tab=nilai');
    }

    private function nullableScore(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    public function printCertificate(Request $request): Response
    {
        return $this->renderCertificates($request, 'certificate');
    }

    public function printSkillPassport(Request $request): Response
    {
        return $this->renderCertificates($request, 'passport');
    }

    private function renderCertificates(Request $request, string $type): Response
    {
        [$guard, $teacherId, $activeYearId, $allowedMajors] = $this->guardKaprodiWithContext();

        if ($guard instanceof Response) {
            return $guard;
        }

        $classId = (int) $request->query('kelas_id', 0);
        $studentId = (int) $request->query('siswa_id', 0);

        if ($classId <= 0 && $studentId <= 0) {
            Session::flash('error', 'Pilih siswa atau kelas terlebih dahulu.');

            return $this->redirect('kaprodi/ukk');
        }

        $class = $classId > 0 ? Classroom::findWithRelations($classId) : null;
        if ($class !== null && ((int) ($class['tingkat'] ?? 0) !== 12 || (int) ($class['tahun_ajaran_id'] ?? 0) !== $activeYearId || !in_array((int) ($class['jurusan_id'] ?? 0), $allowedMajors, true))) {
            Session::flash('error', 'Kelas tidak valid untuk UKK.');

            return $this->redirect('kaprodi/ukk');
        }

        $students = [];
        if ($class !== null) {
            $students = Student::byClass($classId, $activeYearId);
        } elseif ($studentId > 0) {
            $student = Student::findWithRelations($studentId);
            if ($student !== null) {
                $students = [$student];
                $class = Classroom::findWithRelations((int) ($student['kelas_id'] ?? 0));
            }
        }

        if (empty($students)) {
            Session::flash('error', 'Data siswa tidak ditemukan.');

            return $this->redirect('kaprodi/ukk');
        }

        if ($class !== null) {
            $classMajorId = (int) ($class['jurusan_id'] ?? 0);
            if ((int) ($class['tingkat'] ?? 0) !== 12 || !in_array($classMajorId, $allowedMajors, true) || (int) ($class['tahun_ajaran_id'] ?? 0) !== $activeYearId) {
                Session::flash('error', 'Kelas siswa tidak valid untuk UKK.');

                return $this->redirect('kaprodi/ukk');
            }
        }

        $studentIds = array_map(static fn ($s) => (int) ($s['id'] ?? 0), $students);
        $assessments = UkkStudentAssessment::mapByStudents($studentIds, $activeYearId);

        $dudiIds = [];
        $skkniIds = [];
        $assessorIds = [];
        foreach ($assessments as $assessment) {
            if (isset($assessment['dudi_id'])) {
                $dudiIds[] = (int) $assessment['dudi_id'];
            }
            if (isset($assessment['skkni_id'])) {
                $skkniIds[] = (int) $assessment['skkni_id'];
            }
            if (isset($assessment['asesor_id'])) {
                $assessorIds[] = (int) $assessment['asesor_id'];
            }
        }

        $dudiIds = array_values(array_unique(array_filter($dudiIds, static fn (int $id) => $id > 0)));
        $skkniIds = array_values(array_unique(array_filter($skkniIds, static fn (int $id) => $id > 0)));
        $assessorIds = array_values(array_unique(array_filter($assessorIds, static fn (int $id) => $id > 0)));

        $dudiMap = [];
        foreach ($dudiIds as $id) {
            $record = UkkDudi::find($id);
            if ($record !== null) {
                $dudiMap[$id] = $record;
            }
        }

        $skkniMap = [];
        foreach ($skkniIds as $id) {
            $record = UkkSkkni::find($id);
            if ($record !== null) {
                $skkniMap[$id] = $record;
            }
        }

        $assessorMap = [];
        foreach ($assessorIds as $id) {
            $record = UkkAssessor::find($id);
            if ($record !== null) {
                $assessorMap[$id] = $record;
            }
        }

        $schoolProfile = SchoolProfile::first();
        if (is_array($schoolProfile) && $class !== null && !isset($schoolProfile['tahun_ajaran_nama'])) {
            $schoolProfile['tahun_ajaran_nama'] = $class['tahun_ajaran_nama'] ?? '';
        }
        $activeYear = SchoolYear::find($activeYearId);
        if (is_array($schoolProfile) && $activeYear !== null) {
            $headmasterId = (int) ($activeYear['kepala_sekolah_id'] ?? 0);
            if ($headmasterId > 0) {
                $headmaster = Teacher::find($headmasterId);
                $headmasterName = trim((string) ($headmaster['nama'] ?? ''));
                if ($headmasterName !== '') {
                    $schoolProfile['kepala_sekolah'] = $headmasterName;
                }
            }
        }

        $skkniList = [];
        if ($class !== null && isset($class['jurusan_id'])) {
            $skkniList = UkkSkkni::byYearAndMajors($activeYearId, [(int) $class['jurusan_id']]);
        } elseif (!empty($allowedMajors)) {
            $skkniList = UkkSkkni::byYearAndMajors($activeYearId, $allowedMajors);
        }
        $paperSize = strtolower((string) $request->query('paper', 'f4'));
        if (!in_array($paperSize, ['f4', 'a4'], true)) {
            $paperSize = 'f4';
        }

        $view = $type === 'passport' ? 'ukk/print-skill-passport' : 'ukk/print-certificate';
        $certificatePrintSide = 'front';
        if ($type === 'certificate') {
            $requestedSide = strtolower((string) $request->query('sisi', 'depan'));
            $certificatePrintSide = in_array($requestedSide, ['belakang', 'back'], true) ? 'back' : 'front';
        }

        return $this->render($view, [
            'title' => $type === 'passport' ? 'Skill Passport Siswa' : ($certificatePrintSide === 'back' ? 'Lembar Belakang Sertifikat UKK' : 'Sertifikat UKK'),
            'class' => $class,
            'students' => $students,
            'assessments' => $assessments,
            'dudiMap' => $dudiMap,
            'skkniMap' => $skkniMap,
            'skkniList' => $skkniList,
            'assessorMap' => $assessorMap,
            'schoolProfile' => $schoolProfile,
            'paperSize' => $paperSize,
            'certificatePrintSide' => $certificatePrintSide,
        ], 'print');
    }

    /**
     * @return array{0:Response|null,1:int,2:int,3:array<int,int>}
     */
    private function guardKaprodiWithContext(): array
    {
        if ($response = $this->ensureAuthenticated()) {
            return [$response, 0, 0, []];
        }

        $user = auth();
        $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;

        if (!AcademicRoleGate::isKepalaProdi(null, $user) || $teacherId <= 0) {
            Session::flash('error', 'Menu ini khusus kepala program studi.');

            return [$this->redirect('dashboard'), 0, 0, []];
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYearId <= 0) {
            Session::flash('error', 'Tahun ajaran aktif belum disetel.');

            return [$this->redirect('dashboard'), $teacherId, 0, []];
        }

        $allowedMajors = TeacherAcademicPosition::teacherMajorIdsForRole($teacherId, 'kepala_prodi', $activeYearId);

        if (empty($allowedMajors)) {
            Session::flash('error', 'Anda belum terhubung ke jurusan sebagai Kaprodi pada tahun ajaran aktif.');

            return [$this->redirect('dashboard'), $teacherId, $activeYearId, []];
        }

        return [null, $teacherId, $activeYearId, $allowedMajors];
    }

    /**
     * @param array<int, int> $allowedMajors
     * @return array{options: array<int,string>, first:int|null}
     */
    private function majorOptions(array $allowedMajors): array
    {
        if (empty($allowedMajors)) {
            return ['options' => [], 'first' => null];
        }

        $options = [];
        $majors = Major::allOrdered();
        foreach ($majors as $major) {
            $id = (int) ($major['id'] ?? 0);
            if ($id > 0 && in_array($id, $allowedMajors, true)) {
                $options[$id] = (string) ($major['nama'] ?? ('Jurusan #' . $id));
            }
        }

        $first = null;
        foreach ($options as $id => $_) {
            $first = $id;
            break;
        }

        return ['options' => $options, 'first' => $first];
    }

    /**
     * @param array<int, int> $allowedMajors
     * @return array{options: array<int,string>, first_id:int|null, items: array<int,array<string,mixed>>}
     */
    private function kelasDuaBelas(int $schoolYearId, array $allowedMajors): array
    {
        $options = [];
        $items = [];
        $first = null;

        if ($schoolYearId <= 0 || empty($allowedMajors)) {
            return ['options' => $options, 'first_id' => $first, 'items' => $items];
        }

        $classes = Classroom::allWithRelations($schoolYearId);
        foreach ($classes as $class) {
            $id = (int) ($class['id'] ?? 0);
            $level = (int) ($class['tingkat'] ?? 0);
            $majorId = (int) ($class['jurusan_id'] ?? 0);

            if ($id <= 0 || $level !== 12 || !in_array($majorId, $allowedMajors, true)) {
                continue;
            }

            $label = sprintf('XII %s (%s)', $class['nama'] ?? '-', $class['jurusan_nama'] ?? '-');
            $options[$id] = $label;
            $items[$id] = $class;

            if ($first === null) {
                $first = $id;
            }
        }

        return ['options' => $options, 'first_id' => $first, 'items' => $items];
    }
}

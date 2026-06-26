<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\Major;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Services\Import\MasterDataImporter;
use App\Traits\HandlesImportUpload;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class SubjectController extends Controller
{
    use HandlesImportUpload;

    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();
        $role = (string) ($user['role'] ?? '');
        $canManage = in_array($role, ['admin', 'staff'], true);
        $teacherId = !empty($user['teacher_id']) ? (int) $user['teacher_id'] : null;
        $studentId = !empty($user['student_id']) ? (int) $user['student_id'] : null;

        $groupOptions = Subject::groupOptions();
        $subjects = [];
        $majorOptions = [];
        $editing = null;
        $activeYear = null;
        $yearOptions = [];
        $selectedYearId = (int) $request->query('tahun_ajaran_id', 0);
        $selectedYearLabel = null;
        $contextMessage = null;
        $viewContext = $canManage ? 'admin' : ($role === 'guru' ? 'teacher' : 'student');
        $classroomInfo = null;

        if ($canManage) {
            $yearOptions = SchoolYear::options();
            $allYears = SchoolYear::allOrdered();
            $editId = (int) $request->query('edit', 0);
            $editing = $editId > 0 ? Subject::find($editId) : null;
            if ($editing !== null) {
                $selectedYearId = (int) ($editing['tahun_ajaran_id'] ?? 0);
            }

            if ($selectedYearId === 0 && !empty($allYears)) {
                foreach ($allYears as $year) {
                    if (($year['status'] ?? 'nonaktif') === 'aktif') {
                        $selectedYearId = (int) $year['id'];
                        break;
                    }
                }

                if ($selectedYearId === 0) {
                    $selectedYearId = (int) ($allYears[0]['id'] ?? 0);
                }
            }

            $activeYear = SchoolYear::active();
            $subjects = Subject::allWithDisplayGroup($selectedYearId > 0 ? $selectedYearId : null);
            $majorOptions = Major::options(true, $editing['jurusan_id'] ?? null);

            if ($selectedYearId > 0 && isset($yearOptions[$selectedYearId])) {
                $selectedYearLabel = $yearOptions[$selectedYearId];
            }
        } elseif ($role === 'guru' && $teacherId !== null) {
            $activeYear = SchoolYear::active();
            $assignments = SubjectTeacher::byTeacher($teacherId);

            $yearOptions = [];
            foreach ($assignments as $assignment) {
                $yearId = (int) ($assignment['mata_pelajaran_tahun_ajaran_id'] ?? 0);
                if ($yearId <= 0) {
                    continue;
                }

                $semesterValue = (int) ($assignment['mata_pelajaran_tahun_ajaran_semester'] ?? 1);
                $semesterLabel = $semesterValue === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
                $label = sprintf(
                    '%s - %s',
                    $assignment['mata_pelajaran_tahun_ajaran_nama'] ?? 'Tahun Ajaran',
                    $semesterLabel
                );
                $yearOptions[$yearId] = $label;
            }

            if ($selectedYearId <= 0) {
                if ($activeYear !== null && isset($yearOptions[(int) $activeYear['id']])) {
                    $selectedYearId = (int) $activeYear['id'];
                } elseif (!empty($yearOptions)) {
                    $selectedYearId = (int) array_key_first($yearOptions);
                }
            }

            if ($selectedYearId > 0 && !isset($yearOptions[$selectedYearId])) {
                $selectedYearId = !empty($yearOptions) ? (int) array_key_first($yearOptions) : 0;
            }

            $subjects = [];
            $seen = [];
            foreach ($assignments as $assignment) {
                $yearId = (int) ($assignment['mata_pelajaran_tahun_ajaran_id'] ?? 0);
                if ($selectedYearId > 0 && $yearId !== $selectedYearId) {
                    continue;
                }

                $subjectId = (int) ($assignment['mata_pelajaran_id'] ?? 0);
                if ($subjectId <= 0 || isset($seen[$subjectId])) {
                    continue;
                }

                $semesterValue = (int) ($assignment['mata_pelajaran_tahun_ajaran_semester'] ?? 1);
                $subjects[] = [
                    'id' => $subjectId,
                    'jenis' => $assignment['mata_pelajaran_jenis'] ?? '',
                    'jenis_label' => $groupOptions[$assignment['mata_pelajaran_jenis'] ?? ''] ?? ($assignment['mata_pelajaran_jenis'] ?? ''),
                    'tahun_ajaran_nama' => $assignment['mata_pelajaran_tahun_ajaran_nama'] ?? '-',
                    'tahun_ajaran_semester_label' => $semesterValue === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)',
                    'kode' => $assignment['mata_pelajaran_kode'] ?? '',
                    'nama' => $assignment['mata_pelajaran_nama'] ?? '',
                    'jurusan_id' => $assignment['mata_pelajaran_jurusan_id'] ?? null,
                    'jurusan_nama' => $assignment['mata_pelajaran_jurusan_nama'] ?? null,
                    'deskripsi' => $assignment['mata_pelajaran_deskripsi'] ?? null,
                ];

                $seen[$subjectId] = true;
            }

            if ($selectedYearId > 0 && isset($yearOptions[$selectedYearId])) {
                $selectedYearLabel = $yearOptions[$selectedYearId];
            }

            $contextMessage = 'Daftar mata pelajaran berikut merupakan mapel yang Anda ampu.';
        } elseif ($role === 'siswa' && $studentId !== null) {
            $student = Student::find($studentId);
            if ($student === null) {
                Session::flash('error', 'Data siswa tidak ditemukan.');

                return $this->redirect('dashboard');
            }

            $classId = (int) ($student['kelas_id'] ?? 0);
            $classroom = $classId > 0 ? Classroom::find($classId) : null;

            if ($classroom === null) {
                Session::flash('error', 'Data kelas tidak ditemukan.');

                return $this->redirect('dashboard');
            }

            $selectedYearId = (int) ($classroom['tahun_ajaran_id'] ?? 0);
            $activeYear = $selectedYearId > 0 ? SchoolYear::find($selectedYearId) : null;

            $subjectsList = Subject::allWithDisplayGroup($selectedYearId > 0 ? $selectedYearId : null);
            $majorId = (int) ($classroom['jurusan_id'] ?? 0);

            $subjects = array_values(array_filter($subjectsList, static function (array $subject) use ($majorId): bool {
                $subjectMajor = isset($subject['jurusan_id']) ? (int) $subject['jurusan_id'] : null;

                return $subjectMajor === null || $subjectMajor === 0 || $subjectMajor === $majorId;
            }));

            if ($activeYear !== null) {
                $semesterValue = (int) ($activeYear['semester_aktif'] ?? 1);
                $semesterLabel = $semesterValue === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
                $selectedYearLabel = sprintf('%s - %s', $activeYear['nama'], $semesterLabel);
                if ($selectedYearId > 0) {
                    $yearOptions = [$selectedYearId => $selectedYearLabel];
                }
            } elseif (!empty($subjects)) {
                $selectedYearLabel = $subjects[0]['tahun_ajaran_nama'] ?? null;
                if ($selectedYearLabel !== null && $selectedYearId > 0) {
                    $yearOptions = [$selectedYearId => $selectedYearLabel];
                }
            }

            $majorName = null;
            if ($majorId > 0) {
                $majorRecord = Major::find($majorId);
                $majorName = $majorRecord['nama'] ?? null;
            }

            $classroomInfo = [
                'name' => trim(sprintf('%s %s', $classroom['tingkat'] ?? '', $classroom['nama'] ?? '')),
                'major' => $majorName,
            ];

            $contextMessage = 'Mata pelajaran yang ditampilkan berasal dari kelas yang Anda ikuti.';
        } else {
            Session::flash('error', 'Anda tidak memiliki hak akses ke data mata pelajaran.');

            return $this->redirect('dashboard');
        }

        return $this->render('academic/subjects/index', [
            'title' => 'Mata Pelajaran',
            'pageTitle' => 'Daftar Mata Pelajaran',
            'activeMenu' => 'subjects',
            'subjects' => $subjects,
            'groupOptions' => $groupOptions,
            'majorOptions' => $majorOptions,
            'editingSubject' => $canManage ? $editing : null,
            'majorRequiredTypes' => ['C2', 'C3'],
            'yearOptions' => $yearOptions,
            'selectedYearId' => $selectedYearId,
            'activeYear' => $activeYear,
            'canManageSubjects' => $canManage,
            'canImportSubjects' => $role === 'admin',
            'contextMessage' => $contextMessage,
            'selectedYearLabel' => $selectedYearLabel,
            'viewContext' => $viewContext,
            'classroomInfo' => $classroomInfo,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureRole(['admin', 'staff'])) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/mata-pelajaran')) {
            return $response;
        }

        $activeYear = SchoolYear::active();
        $requestYearId = (int) ($activeYear['id'] ?? 0);
        $redirectUrl = 'akademik/mata-pelajaran' . ($requestYearId > 0 ? '?tahun_ajaran_id=' . $requestYearId : '');

        $payload = $this->validate($request);

        if ($payload === null) {
            return $this->redirect($redirectUrl);
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            Subject::create($payload);
            Session::flash('success', 'Mata pelajaran berhasil ditambahkan.');
            $redirectUrl = 'akademik/mata-pelajaran?tahun_ajaran_id=' . $payload['tahun_ajaran_id'];
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menambahkan mata pelajaran: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    public function import(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/mata-pelajaran')) {
            return $response;
        }

        $activeYear = SchoolYear::active();
        if ($activeYear === null) {
            Session::flash('error', 'Tidak ada tahun ajaran aktif. Silakan aktifkan tahun ajaran terlebih dahulu.');

            return $this->redirect('akademik/mata-pelajaran');
        }

        $yearId = (int) ($activeYear['id'] ?? 0);
        if ($yearId <= 0) {
            Session::flash('error', 'Tahun ajaran aktif tidak valid.');

            return $this->redirect('akademik/mata-pelajaran');
        }

        $redirectUrl = 'akademik/mata-pelajaran?tahun_ajaran_id=' . $yearId;

        $files = $request->files();
        $upload = is_array($files) ? ($files['import_file'] ?? null) : null;

        if (!is_array($upload)) {
            Session::flash('error', 'File import tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        $errorMessage = null;
        $path = $this->moveImportFile($upload, $errorMessage);

        if ($path === null) {
            Session::flash('error', $errorMessage ?? 'File import tidak valid.');

            return $this->redirect($redirectUrl);
        }

        try {
            $importer = new MasterDataImporter();
            $result = $importer->importSubjects($path, $yearId);

            $summary = sprintf(
                'Import mata pelajaran selesai. %d baris diproses: %d baru, %d diperbarui, %d dilewati.',
                $result['processed'],
                $result['inserted'],
                $result['updated'],
                $result['skipped']
            );

            Session::flash('success', $summary);

            if (!empty($result['errors'])) {
                $preview = array_slice($result['errors'], 0, 5);
                $warning = implode(' ', $preview);
                if (count($result['errors']) > 5) {
                    $warning .= sprintf(' Dan %d kesalahan lainnya.', count($result['errors']) - 5);
                }
                Session::flash('warning', $warning);
            }
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memproses file import: ' . $exception->getMessage());
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return $this->redirect($redirectUrl);
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureRole(['admin', 'staff'])) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/mata-pelajaran')) {
            return $response;
        }

        $subject = Subject::find($id);
        if ($subject === null) {
            Session::flash('error', 'Data mata pelajaran tidak ditemukan.');

            return $this->redirect('akademik/mata-pelajaran');
        }

        $requestYearId = (int) ($subject['tahun_ajaran_id'] ?? 0);
        $redirectUrl = 'akademik/mata-pelajaran' . ($requestYearId > 0 ? '?tahun_ajaran_id=' . $requestYearId : '');

        $payload = $this->validate($request, false, $id);

        if ($payload === null) {
            return $this->redirect($redirectUrl);
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            Subject::updateById($id, $payload);
            Session::flash('success', 'Mata pelajaran berhasil diperbarui.');
            $redirectUrl = 'akademik/mata-pelajaran?tahun_ajaran_id=' . $payload['tahun_ajaran_id'];
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui mata pelajaran: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureRole(['admin', 'staff'])) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/mata-pelajaran')) {
            return $response;
        }

        $redirectUrl = 'akademik/mata-pelajaran';

        $subject = Subject::find($id);
        if ($subject !== null) {
            $redirectUrl = 'akademik/mata-pelajaran?tahun_ajaran_id=' . (int) $subject['tahun_ajaran_id'];
        }

        try {
            Subject::deleteById($id);
            Session::flash('success', 'Mata pelajaran dihapus.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus mata pelajaran: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    private function validate(Request $request, bool $isCreate = true, ?int $ignoreId = null): ?array
    {
        $groupOptions = Subject::groupOptions();
        $allowedGroups = array_keys($groupOptions);

        $existingSubject = null;
        $yearId = 0;

        if ($isCreate) {
            $activeYear = SchoolYear::active();
            if ($activeYear === null) {
                Session::flash('error', 'Tidak ada tahun ajaran aktif. Silakan aktifkan tahun ajaran terlebih dahulu.');
                Session::flashInput($request->all());

                return null;
            }

            $yearId = (int) $activeYear['id'];
        } else {
            if ($ignoreId === null) {
                Session::flash('error', 'Data mata pelajaran tidak valid.');
                Session::flashInput($request->all());

                return null;
            }

            $existingSubject = Subject::find($ignoreId);

            if ($existingSubject === null) {
                Session::flash('error', 'Data mata pelajaran tidak ditemukan.');
                Session::flashInput($request->all());

                return null;
            }

            $yearId = (int) ($existingSubject['tahun_ajaran_id'] ?? 0);
        }

        if ($yearId <= 0 || SchoolYear::find($yearId) === null) {
            Session::flash('error', 'Tahun ajaran tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        $data = [
            'tahun_ajaran_id' => $yearId,
            'kode' => strtoupper(trim((string) $request->input('kode', ''))),
            'nama' => trim((string) $request->input('nama', '')),
            'jenis' => trim((string) $request->input('jenis', '')),
            'deskripsi' => trim((string) $request->input('deskripsi', '')),
        ];
        $jurusanId = (int) $request->input('jurusan_id', 0);

        if ($data['kode'] === '') {
            Session::flash('error', 'Kode mata pelajaran wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['nama'] === '') {
            Session::flash('error', 'Nama mata pelajaran wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        if (!in_array($data['jenis'], $allowedGroups, true)) {
            Session::flash('error', 'Jenis mata pelajaran tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        if (in_array($data['jenis'], ['C2', 'C3'], true)) {
            if ($jurusanId <= 0) {
                Session::flash('error', 'Jenis mata pelajaran ini membutuhkan jurusan.');
                Session::flashInput($request->all());

                return null;
            }
            $data['jurusan_id'] = $jurusanId;
        } else {
            $data['jurusan_id'] = $jurusanId > 0 ? $jurusanId : null;
        }

        if ($data['deskripsi'] === '') {
            $data['deskripsi'] = null;
        }

        if (Subject::exists(['tahun_ajaran_id' => $yearId, 'kode' => $data['kode']], $ignoreId)) {
            Session::flash('error', 'Kode mata pelajaran sudah digunakan pada tahun ajaran tersebut.');
            Session::flashInput($request->all());

            return null;
        }

        if (Subject::exists(['tahun_ajaran_id' => $yearId, 'nama' => $data['nama']], $ignoreId)) {
            Session::flash('error', 'Nama mata pelajaran sudah digunakan pada tahun ajaran tersebut.');
            Session::flashInput($request->all());

            return null;
        }

        return $data;
    }
}

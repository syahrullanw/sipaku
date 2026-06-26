<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\Teacher;
use App\Services\Import\SubjectTeacherAssignmentImporter;
use App\Traits\HandlesImportUpload;
use App\Support\AcademicRoleGate;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class SubjectTeacherController extends Controller
{
    use HandlesImportUpload;

    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $yearOptions = SchoolYear::options();
        $allYears = SchoolYear::allOrdered();
        $selectedYearId = (int) $request->query('tahun_ajaran_id', 0);
        $groupOptions = Subject::groupOptions();
        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? SubjectTeacher::find($editId) : null;
        if ($editing !== null) {
            $editingSubject = Subject::find((int) ($editing['mata_pelajaran_id'] ?? 0));
            if ($editingSubject !== null) {
                $selectedYearId = (int) ($editingSubject['tahun_ajaran_id'] ?? 0);
            }
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

        $assignments = SubjectTeacher::allWithRelations($selectedYearId > 0 ? $selectedYearId : null);

        $subjectOptions = [];
        $subjectMeta = [];

        if ($selectedYearId > 0) {
            $subjects = Subject::allOrdered($selectedYearId);

            foreach ($subjects as $subject) {
                $subjectId = (int) ($subject['id'] ?? 0);
                if ($subjectId <= 0) {
                    continue;
                }

                $label = sprintf('%s - %s', $subject['kode'], $subject['nama']);

                if (!empty($subject['jurusan_nama'])) {
                    $label .= sprintf(' (%s)', $subject['jurusan_nama']);
                }

                if (!empty($subject['tahun_ajaran_nama'])) {
                    $semesterValue = (int) ($subject['tahun_ajaran_semester'] ?? 1);
                    $semesterLabel = $semesterValue === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
                    $label .= sprintf(' [%s - %s]', $subject['tahun_ajaran_nama'], $semesterLabel);
                }

                $subjectOptions[$subjectId] = $label;
                $subjectMeta[$subjectId] = [
                    'tahun_ajaran_id' => (int) ($subject['tahun_ajaran_id'] ?? 0),
                    'jurusan_id' => isset($subject['jurusan_id']) ? (int) $subject['jurusan_id'] : null,
                ];
            }
        }

        $teacherOptions = Teacher::options(true, isset($editing['guru_id']) ? (int) $editing['guru_id'] : null);
        $classList = $selectedYearId > 0 ? Classroom::byYear($selectedYearId) : [];
        $selectedClassIds = [];

        if ($editing !== null) {
            $selectedClassIds = SubjectTeacher::assignedClassIds((int) ($editing['id'] ?? 0));
        }

        $oldClassInput = old('kelas_ids', null);
        if (is_array($oldClassInput)) {
            $selectedClassIds = array_values(array_filter(array_map(
                static fn ($value): int => (int) $value,
                $oldClassInput
            ), static fn (int $id): bool => $id > 0));
        } elseif (is_string($oldClassInput) && $oldClassInput !== '') {
            $selectedClassIds = [(int) $oldClassInput];
        }

        $selectedSubjectIds = old('mata_pelajaran_ids', []);
        if (is_string($selectedSubjectIds) && $selectedSubjectIds !== '') {
            $selectedSubjectIds = [$selectedSubjectIds];
        }

        if (!is_array($selectedSubjectIds)) {
            $selectedSubjectIds = [];
        }

        $selectedSubjectIds = array_values(array_unique(array_filter(array_map(
            static fn ($value): int => (int) $value,
            $selectedSubjectIds
        ), static fn (int $id): bool => $id > 0)));

        $isEditing = $editing !== null;
        if ($isEditing && empty($selectedSubjectIds) && isset($editing['mata_pelajaran_id'])) {
            $selectedSubjectIds = [(int) $editing['mata_pelajaran_id']];
        }

        $disableAssignmentForm = empty($subjectOptions) || $selectedYearId <= 0;
        $canImportAssignments = !$disableAssignmentForm && !empty($subjectOptions) && !empty($teacherOptions) && !empty($classList);

        return $this->render('academic/subject-teachers/index', [
            'title' => 'Guru Pengampu',
            'pageTitle' => 'Daftar Guru Pengampu Mata Pelajaran',
            'activeMenu' => 'subject-teachers',
            'assignments' => $assignments,
            'subjectOptions' => $subjectOptions,
            'subjectMeta' => $subjectMeta,
            'teacherOptions' => $teacherOptions,
            'classList' => $classList,
            'selectedClassIds' => $selectedClassIds,
            'selectedSubjectIds' => $selectedSubjectIds,
            'groupOptions' => $groupOptions,
            'editingAssignment' => $editing,
            'yearOptions' => $yearOptions,
            'selectedYearId' => $selectedYearId,
            'disableAssignmentForm' => $disableAssignmentForm,
            'canImportAssignments' => $canImportAssignments,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/guru-pengampu')) {
            return $response;
        }

        $filterYearId = (int) $request->input('tahun_ajaran_id', 0);
        $redirectUrl = 'akademik/guru-pengampu' . ($filterYearId > 0 ? '?tahun_ajaran_id=' . $filterYearId : '');

        $validated = $this->validate($request);

        if ($validated === null) {
            return $this->redirect($redirectUrl);
        }

        $classIds = $validated['classes'];
        $labels = $validated['labels'] ?? [];
        $now = date('Y-m-d H:i:s');

        if (!empty($validated['multi'])) {
            $payloads = $validated['data'];
            $created = 0;
            $failures = [];

            foreach ($payloads as $payload) {
                $payload['created_at'] = $now;
                $payload['updated_at'] = $now;

                try {
                    SubjectTeacher::createWithClasses($payload, $classIds);
                    $created++;
                } catch (\Throwable $exception) {
                    $subjectId = (int) ($payload['mata_pelajaran_id'] ?? 0);
                    $failures[] = sprintf('%s (%s)', $labels[$subjectId] ?? 'ID ' . $subjectId, $exception->getMessage());
                }
            }

            if ($created > 0) {
                Session::flash('success', sprintf('Guru pengampu berhasil ditambahkan untuk %d mata pelajaran.', $created));
            }

            if (!empty($failures)) {
                $preview = array_slice($failures, 0, 3);
                $message = implode(' ', $preview);
                if (count($failures) > 3) {
                    $message .= sprintf(' Dan %d mata pelajaran lainnya gagal.', count($failures) - 3);
                }
                Session::flash('warning', 'Sebagian mata pelajaran tidak berhasil ditambahkan: ' . $message);
            }
        } else {
            $payload = $validated['data'];
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;

            try {
                SubjectTeacher::createWithClasses($payload, $classIds);
                Session::flash('success', 'Guru pengampu berhasil ditambahkan.');
                $subject = Subject::find($payload['mata_pelajaran_id']);
                if ($subject !== null) {
                    $redirectUrl = 'akademik/guru-pengampu?tahun_ajaran_id=' . (int) $subject['tahun_ajaran_id'];
                }
            } catch (\Throwable $exception) {
                Session::flash('error', 'Gagal menambahkan guru pengampu: ' . $exception->getMessage());
            }
        }

        return $this->redirect($redirectUrl);
    }

    public function import(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/guru-pengampu')) {
            return $response;
        }

        $schoolYearId = (int) $request->input('tahun_ajaran_id', 0);
        $redirectUrl = 'akademik/guru-pengampu' . ($schoolYearId > 0 ? '?tahun_ajaran_id=' . $schoolYearId : '');

        if ($schoolYearId <= 0 || SchoolYear::find($schoolYearId) === null) {
            Session::flash('error', 'Tahun ajaran tidak valid untuk import guru pengampu.');

            return $this->redirect($redirectUrl);
        }

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
            $importer = new SubjectTeacherAssignmentImporter();
            $result = $importer->import($path, $schoolYearId);

            $summary = sprintf(
                'Import guru pengampu selesai. %d baris diproses: %d baru, %d diperbarui, %d dilewati.',
                $result['processed'],
                $result['created'],
                $result['updated'],
                $result['skipped']
            );

            Session::flash('success', $summary);

            if (!empty($result['errors'])) {
                $preview = array_slice($result['errors'], 0, 5);
                $warning = implode(' ', $preview);
                if (count($result['errors']) > 5) {
                    $warning .= sprintf(' Dan %d catatan lainnya.', count($result['errors']) - 5);
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

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/guru-pengampu')) {
            return $response;
        }

        $filterYearId = (int) $request->input('tahun_ajaran_id', 0);
        $redirectUrl = 'akademik/guru-pengampu' . ($filterYearId > 0 ? '?tahun_ajaran_id=' . $filterYearId : '');

        if (SubjectTeacher::find($id) === null) {
            Session::flash('error', 'Data guru pengampu tidak ditemukan.');
            return $this->redirect($redirectUrl);
        }

        $validated = $this->validate($request, false, $id);

        if ($validated === null) {
            return $this->redirect($redirectUrl);
        }

        $payload = $validated['data'];
        $classIds = $validated['classes'];

        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            SubjectTeacher::updateWithClasses($id, $payload, $classIds);
            Session::flash('success', 'Guru pengampu berhasil diperbarui.');
            $subject = Subject::find($payload['mata_pelajaran_id']);
            if ($subject !== null) {
                $redirectUrl = 'akademik/guru-pengampu?tahun_ajaran_id=' . (int) $subject['tahun_ajaran_id'];
            }
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui guru pengampu: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/guru-pengampu')) {
            return $response;
        }

        $redirectUrl = 'akademik/guru-pengampu';

        $assignment = SubjectTeacher::find($id);
        if ($assignment !== null) {
            $subject = Subject::find((int) ($assignment['mata_pelajaran_id'] ?? 0));
            if ($subject !== null) {
                $redirectUrl = 'akademik/guru-pengampu?tahun_ajaran_id=' . (int) $subject['tahun_ajaran_id'];
            }
        }

        try {
            SubjectTeacher::deleteById($id);
            Session::flash('success', 'Guru pengampu dihapus.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus guru pengampu: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    private function validate(Request $request, bool $isCreate = true, ?int $ignoreId = null): ?array
    {
        $filterYearId = (int) $request->input('tahun_ajaran_id', 0);
        if ($filterYearId <= 0 || SchoolYear::find($filterYearId) === null) {
            Session::flash('error', 'Tahun ajaran tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        $teacherId = (int) $request->input('guru_id', 0);
        if ($teacherId <= 0 || Teacher::find($teacherId) === null) {
            Session::flash('error', 'Guru tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        $catatan = trim((string) $request->input('catatan', ''));

        $classInput = $request->input('kelas_ids', []);
        if (is_string($classInput) && $classInput !== '') {
            $classInput = [$classInput];
        }

        if (!is_array($classInput)) {
            Session::flash('error', 'Format pilihan kelas tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        $classIds = array_values(array_filter(array_unique(array_map(
            static fn ($value): int => (int) $value,
            $classInput
        )), static fn (int $id): bool => $id > 0));

        if (empty($classIds)) {
            Session::flash('error', 'Pilih minimal satu kelas yang diampu oleh guru.');
            Session::flashInput($request->all());

            return null;
        }

        $availableClasses = Classroom::byYear($filterYearId);
        $classMap = [];
        foreach ($availableClasses as $class) {
            $classId = (int) ($class['id'] ?? 0);
            if ($classId <= 0) {
                continue;
            }
            $classMap[$classId] = $class;
        }

        if (empty($classMap)) {
            Session::flash('error', 'Belum ada data kelas pada tahun ajaran ini. Tambahkan kelas terlebih dahulu.');
            Session::flashInput($request->all());

            return null;
        }

        $invalidClassIds = array_diff($classIds, array_keys($classMap));
        if (!empty($invalidClassIds)) {
            Session::flash('error', 'Terdapat kelas yang tidak valid untuk tahun ajaran ini.');
            Session::flashInput($request->all());

            return null;
        }

        $bulkSubjectInput = $isCreate ? $request->input('mata_pelajaran_ids', null) : null;
        $isBulk = $isCreate && is_array($bulkSubjectInput);

        $subjectIds = [];
        if ($isBulk) {
            $subjectIds = array_values(array_filter(array_unique(array_map(
                static fn ($value): int => (int) $value,
                $bulkSubjectInput
            )), static fn (int $id): bool => $id > 0));

            if (empty($subjectIds)) {
                Session::flash('error', 'Pilih minimal satu mata pelajaran.');
                Session::flashInput($request->all());

                return null;
            }
        } else {
            $subjectId = (int) $request->input('mata_pelajaran_id', 0);
            if ($subjectId <= 0) {
                Session::flash('error', 'Mata pelajaran tidak valid.');
                Session::flashInput($request->all());

                return null;
            }
            $subjectIds = [$subjectId];
        }

        $payloads = [];
        $labels = [];

        foreach ($subjectIds as $subjectId) {
            $subject = Subject::find($subjectId);

            if ($subject === null) {
                Session::flash('error', 'Mata pelajaran tidak ditemukan.');
                Session::flashInput($request->all());

                return null;
            }

            if ($filterYearId > 0 && (int) ($subject['tahun_ajaran_id'] ?? 0) !== $filterYearId) {
                Session::flash('error', sprintf(
                    'Mata pelajaran %s tidak sesuai dengan tahun ajaran yang dipilih.',
                    (string) ($subject['nama'] ?? $subjectId)
                ));
                Session::flashInput($request->all());

                return null;
            }

            if (SubjectTeacher::exists(['mata_pelajaran_id' => $subjectId, 'guru_id' => $teacherId], $ignoreId)) {
                Session::flash('error', sprintf(
                    'Guru sudah terdaftar sebagai pengampu untuk mata pelajaran %s.',
                    (string) ($subject['nama'] ?? $subjectId)
                ));
                Session::flashInput($request->all());

                return null;
            }

            $subjectMajorId = isset($subject['jurusan_id']) ? (int) $subject['jurusan_id'] : null;
            if ($subjectMajorId !== null && $subjectMajorId > 0) {
                foreach ($classIds as $classId) {
                    $class = $classMap[$classId] ?? null;
                    if ($class === null) {
                        continue;
                    }
                    $classMajorId = isset($class['jurusan_id']) ? (int) $class['jurusan_id'] : null;
                    if ($classMajorId !== null && $classMajorId !== $subjectMajorId) {
                        Session::flash('error', sprintf(
                            'Kelas yang dipilih tidak sesuai dengan jurusan mata pelajaran %s.',
                            (string) ($subject['nama'] ?? $subjectId)
                        ));
                        Session::flashInput($request->all());

                        return null;
                    }
                }
            }

            $payload = [
                'mata_pelajaran_id' => $subjectId,
                'guru_id' => $teacherId,
                'catatan' => $catatan !== '' ? $catatan : null,
            ];

            $payloads[] = $payload;

            $code = trim((string) ($subject['kode'] ?? ''));
            $name = trim((string) ($subject['nama'] ?? ''));
            if ($code !== '' && $name !== '') {
                $labels[$subjectId] = $code . ' - ' . $name;
            } elseif ($name !== '') {
                $labels[$subjectId] = $name;
            } elseif ($code !== '') {
                $labels[$subjectId] = $code;
            } else {
                $labels[$subjectId] = 'ID ' . $subjectId;
            }
        }

        if (!$isBulk) {
            return [
                'data' => $payloads[0],
                'classes' => $classIds,
                'labels' => $labels,
            ];
        }

        return [
            'data' => $payloads,
            'classes' => $classIds,
            'labels' => $labels,
            'multi' => true,
        ];
    }

    private function ensureTataUsahaAccess(): ?Response
    {
        $user = auth();

        if (\App\Support\UserModuleRules::allowsCurrentRequest($user, true)) {
            return null;
        }

        if (
            AcademicRoleGate::isTataUsaha($user)
            || AcademicRoleGate::isWakaKurikulum($user)
        ) {
            return null;
        }

        return $this->ensureRole(['admin', 'staff']);
    }
}

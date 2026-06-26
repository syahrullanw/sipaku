<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentCompetencyScore;
use App\Models\StudentSkillAssessment;
use App\Models\SubjectAssessmentSetting;
use App\Models\SubjectCompetency;
use App\Models\SubjectTeacher;
use App\Models\SubjectTeacherClass;
use App\Services\AssessmentEvaluator;
use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Throwable;

class TeacherSkillAssessmentController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request, int $assignmentId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $context = $this->resolveContext($assignmentId);
        if ($context instanceof Response) {
            return $context;
        }

        [
            'assignment' => $assignment,
            'setting' => $setting,
        ] = $context;

        if ((int) ($setting['enable_keterampilan'] ?? 1) !== 1) {
            Session::flash('error', 'Penilaian keterampilan dinonaktifkan untuk mata pelajaran ini.');

            return $this->redirect('guru/nilai?focus=' . $assignmentId);
        }

        $classId = (int) $request->query('kelas_id', 0);
        $classOptions = $this->classOptions($assignment);
        $students = [];
        $studentIds = [];
        $competencies = [];
        $scoreMap = [];
        $skillMap = [];

        if ($classId > 0) {
            $classRecord = Classroom::find($classId);
            if ($classRecord !== null && ($classRecord['kurikulum'] ?? 'k13') === 'kurmer') {
                Session::flash('error', 'Kelas ini memakai Kurikulum Merdeka. Gunakan menu TP KurMer.');

                return $this->redirect('guru/nilai/' . $assignmentId . '/kurmer?kelas_id=' . $classId);
            }

            $competencies = SubjectCompetency::byAssignment($assignment['id'], 'keterampilan', $classId);
            $students = Student::byClass($classId, (int) $assignment['mata_pelajaran_tahun_ajaran_id']);
            $studentIds = array_map(static fn ($student) => (int) ($student['id'] ?? 0), $students);
            $studentIds = array_filter($studentIds);

            if (!empty($studentIds)) {
                $rawScores = StudentCompetencyScore::byAssignmentAndType($assignment['id'], 'keterampilan', $studentIds);
                foreach ($rawScores as $row) {
                    $studentId = (int) ($row['siswa_id'] ?? 0);
                    $kdId = (int) ($row['kd_id'] ?? 0);
                    if ($studentId <= 0 || $kdId <= 0) {
                        continue;
                    }
                    $scoreMap[$studentId][$kdId] = [
                        'nilai' => $row['nilai'],
                        'deskripsi' => $row['deskripsi'],
                    ];
                }

                $rawSkills = StudentSkillAssessment::byAssignment($assignment['id'], $studentIds);
                foreach ($rawSkills as $row) {
                    $studentId = (int) ($row['siswa_id'] ?? 0);
                    if ($studentId <= 0) {
                        continue;
                    }
                    $skillMap[$studentId] = $row;
                }
            }
        }

        return $this->render('teacher/subjects/skill', [
            'title' => 'Penilaian Keterampilan',
            'pageTitle' => 'Input Nilai Keterampilan',
            'activeMenu' => 'teacher-subject-assessments',
            'assignment' => $assignment,
            'setting' => $setting,
            'classOptions' => $classOptions,
            'selectedClassId' => $classId > 0 ? $classId : null,
            'students' => $students,
            'competencies' => $competencies,
            'scoreMap' => $scoreMap,
            'skillMap' => $skillMap,
        ]);
    }

    public function storeCompetency(Request $request, int $assignmentId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'guru/nilai/' . $assignmentId . '/keterampilan/kd')) {
            return $response;
        }

        $context = $this->resolveContext($assignmentId);
        if ($context instanceof Response) {
            return $context;
        }

        ['assignment' => $assignment] = $context;

        $code = strtoupper(trim((string) $request->input('kode', '')));
        $description = trim((string) $request->input('deskripsi', ''));
        $classId = (int) $request->input('kelas_id', 0);
        $redirectUrl = sprintf('guru/nilai/%d/keterampilan', $assignmentId);
        if ($classId > 0) {
            $redirectUrl .= '?kelas_id=' . $classId;
        }

        if ($code === '' || $classId <= 0) {
            Session::flash('error', $classId <= 0 ? 'Pilih kelas sebelum menambahkan KD.' : 'Kode KD wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect($redirectUrl);
        }

        $classRecord = Classroom::find($classId);
        if ($classRecord !== null && ($classRecord['kurikulum'] ?? 'k13') === 'kurmer') {
            Session::flash('error', 'Kelas ini memakai Kurikulum Merdeka. Tambahkan TP melalui menu KurMer.');

            return $this->redirect('guru/nilai/' . $assignmentId . '/kurmer?kelas_id=' . $classId);
        }

        if (SubjectCompetency::existsWithCode($assignment['id'], $classId, 'keterampilan', $code)) {
            Session::flash('error', 'Kode KD sudah terdaftar untuk mata pelajaran ini.');
            Session::flashInput($request->all());

            return $this->redirect($redirectUrl);
        }

        $now = date('Y-m-d H:i:s');
        $created = SubjectCompetency::create([
            'guru_mata_pelajaran_id' => $assignment['id'],
            'kelas_id' => $classId,
            'jenis' => 'keterampilan',
            'kode' => $code,
            'deskripsi' => $description !== '' ? $description : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($created) {
            Session::flash('success', 'KD keterampilan berhasil ditambahkan.');
        } else {
            Session::flash('error', 'KD keterampilan gagal disimpan.');
            Session::flashInput($request->all());
        }

        return $this->redirect($redirectUrl);
    }

    public function deleteCompetency(Request $request, int $assignmentId, int $competencyId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'guru/nilai/' . $assignmentId . '/keterampilan/kd/' . $competencyId . '/hapus')) {
            return $response;
        }

        $context = $this->resolveContext($assignmentId);
        if ($context instanceof Response) {
            return $context;
        }

        ['assignment' => $assignment] = $context;

        $classId = (int) $request->input('kelas_id', 0);
        $redirectUrl = sprintf('guru/nilai/%d/keterampilan', $assignmentId);
        if ($classId > 0) {
            $redirectUrl .= '?kelas_id=' . $classId;
        }

        if ($classId <= 0) {
            Session::flash('error', 'Pilih kelas terlebih dahulu.');

            return $this->redirect($redirectUrl);
        }

        $classRecord = Classroom::find($classId);
        if ($classRecord !== null && ($classRecord['kurikulum'] ?? 'k13') === 'kurmer') {
            Session::flash('error', 'Kelas ini memakai Kurikulum Merdeka. Hapus/kelola TP lewat menu KurMer.');

            return $this->redirect('guru/nilai/' . $assignmentId . '/kurmer?kelas_id=' . $classId);
        }

        $competency = SubjectCompetency::findForAssignment($competencyId, $assignment['id'], $classId);

        if ($competency === null || ($competency['jenis'] ?? '') !== 'keterampilan') {
            Session::flash('error', 'KD tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        try {
            $connection = Database::connection();
            $connection->beginTransaction();

            StudentCompetencyScore::deleteByAssignment($assignment['id'], $competencyId);
            SubjectCompetency::deleteById($competencyId);

            $connection->commit();
            Session::flash('success', 'KD keterampilan dihapus.');
        } catch (Throwable $exception) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollBack();
            }
            Session::flash('error', 'KD gagal dihapus: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    public function storeScores(Request $request, int $assignmentId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'guru/nilai/' . $assignmentId . '/keterampilan/simpan')) {
            return $response;
        }

        $context = $this->resolveContext($assignmentId);
        if ($context instanceof Response) {
            return $context;
        }

        [
            'assignment' => $assignment,
            'setting' => $setting,
        ] = $context;

        if ((int) ($setting['enable_keterampilan'] ?? 1) !== 1) {
            Session::flash('error', 'Penilaian keterampilan dinonaktifkan untuk mata pelajaran ini.');

            return $this->redirect('guru/nilai?focus=' . $assignmentId);
        }

        $classId = (int) $request->input('kelas_id', 0);
        $redirectUrl = sprintf('guru/nilai/%d/keterampilan', $assignmentId);
        if ($classId > 0) {
            $redirectUrl .= '?kelas_id=' . $classId;
        }

        if ($classId <= 0) {
            Session::flash('error', 'Pilih kelas terlebih dahulu sebelum menyimpan nilai.');

            return $this->redirect($redirectUrl);
        }

        $classRecord = Classroom::find($classId);
        if ($classRecord !== null && ($classRecord['kurikulum'] ?? 'k13') === 'kurmer') {
            Session::flash('error', 'Kelas ini memakai Kurikulum Merdeka. Gunakan form TP KurMer.');

            return $this->redirect('guru/nilai/' . $assignmentId . '/kurmer?kelas_id=' . $classId);
        }

        $competencies = SubjectCompetency::byAssignment($assignment['id'], 'keterampilan', $classId);
        if (empty($competencies)) {
            Session::flash('error', 'Tambah minimal satu KD keterampilan sebelum menginput nilai.');

            return $this->redirect($redirectUrl);
        }

        $students = Student::byClass($classId, (int) $assignment['mata_pelajaran_tahun_ajaran_id']);
        if (empty($students)) {
            Session::flash('error', 'Tidak ada siswa pada kelas terpilih.');

            return $this->redirect($redirectUrl);
        }

        $scoreInputs = $request->input('nilai_kd', []);

        $errors = [];
        $skippedInactive = 0;

        try {
            $connection = Database::connection();
            $connection->beginTransaction();

            foreach ($students as $student) {
                $studentId = (int) ($student['id'] ?? 0);
                if ($studentId <= 0) {
                    continue;
                }

                if (!Student::hasActiveStatus($student)) {
                    $skippedInactive++;
                    continue;
                }

                $studentScores = $scoreInputs[$studentId] ?? [];
                $kdValues = [];
                $kdSummaries = [];

                foreach ($competencies as $competency) {
                    $kdId = (int) ($competency['id'] ?? 0);
                    if ($kdId <= 0) {
                        continue;
                    }

                    $rawValue = $studentScores[$kdId] ?? null;
                    $score = AssessmentEvaluator::normalizeScoreOrZero($rawValue);

                    if ($score === null) {
                        $errors[] = sprintf('Nilai KD %s untuk siswa %s harus berupa angka antara 0 - 100.', $competency['kode'], $student['nama']);
                        continue;
                    }

                    $kdValues[] = $score;

                    $predicateForKd = AssessmentEvaluator::determinePredicate(
                        $score,
                        (int) ($setting['enable_kkm'] ?? 0) === 1,
                        $setting['nilai_kkm'] ?? null
                    );

                    $competencyLabel = $this->resolveCompetencyLabel($competency);

                    $kdSummaries[] = [
                        'predicate' => $predicateForKd,
                        'label' => $competencyLabel,
                    ];

                    StudentCompetencyScore::upsert(
                        $assignment['id'],
                        $kdId,
                        $studentId,
                        $score,
                        null
                    );
                }

                if (!empty($errors)) {
                    continue;
                }

                $avgKd = null;
                if (!empty($kdValues)) {
                    $avgKd = round(array_sum($kdValues) / count($kdValues), 2);
                }

                $finalScore = $avgKd;
                $predicate = null;
                if ($finalScore !== null) {
                    $predicate = AssessmentEvaluator::determinePredicate(
                        $finalScore,
                        (int) ($setting['enable_kkm'] ?? 0) === 1,
                        $setting['nilai_kkm'] ?? null
                    );
                }

                $finalDescription = $this->buildSkillSummaryDescription($predicate, $kdSummaries);

                StudentSkillAssessment::upsert(
                    $assignment['id'],
                    $studentId,
                    $finalScore,
                    $predicate,
                    $finalDescription
                );
            }

            if (!empty($errors)) {
                throw new \RuntimeException(implode(' ', $errors));
            }

            $connection->commit();
            Session::flash('success', 'Nilai keterampilan berhasil disimpan.');
            if ($skippedInactive > 0) {
                Session::flash('warning', sprintf('%d siswa nonaktif dilewati dan nilainya tidak diubah.', $skippedInactive));
            }
        } catch (Throwable $exception) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollBack();
            }
            Session::flash('error', 'Gagal menyimpan nilai keterampilan: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * @param array<int, array{predicate:string|null,label:string}> $kdSummaries
     */
    private function buildSkillSummaryDescription(?string $finalPredicate, array $kdSummaries): ?string
    {
        if ($finalPredicate === null) {
            return null;
        }

        $details = [];
        foreach (array_slice($kdSummaries, 0, 3) as $summary) {
            $predicate = trim((string) ($summary['predicate'] ?? ''));
            $label = trim((string) ($summary['label'] ?? ''));

            if ($predicate === '' || $label === '') {
                continue;
            }

            $details[] = sprintf('%s pada: %s', $predicate, $label);
        }

        $description = sprintf('Capaian kompetensi sudah tuntas dengan predikat %s.', $finalPredicate);

        if (!empty($details)) {
            $description .= ' ' . implode(', ', $details) . '.';
        }

        return $description;
    }

    /**
     * @param array<string, mixed> $competency
     */
    private function resolveCompetencyLabel(array $competency): string
    {
        $description = trim((string) ($competency['deskripsi'] ?? ''));
        if ($description !== '') {
            return $description;
        }

        $name = trim((string) ($competency['nama'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $code = trim((string) ($competency['kode'] ?? ''));

        return $code !== '' ? $code : 'Kompetensi';
    }

    /**
     * @return array<string, mixed>|Response
     */
    private function resolveContext(int $assignmentId): array|Response
    {
        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh guru.');

            return $this->redirect('dashboard');
        }

        $teacherId = (int) $user['teacher_id'];
        $assignment = SubjectTeacher::findForTeacher($assignmentId, $teacherId);

        if ($assignment === null) {
            Session::flash('error', 'Mata pelajaran tidak ditemukan atau tidak dapat diakses.');

            return $this->redirect('guru/nilai');
        }

        $setting = SubjectAssessmentSetting::ensureDefault($assignment['id']);

        return [
            'teacherId' => $teacherId,
            'assignment' => $assignment,
            'setting' => $setting,
        ];
    }

    /**
     * @param array<string, mixed> $assignment
     * @return array<int, array<string, mixed>>
     */
    private function classOptions(array $assignment): array
    {
        $assignmentId = (int) ($assignment['id'] ?? 0);

        if ($assignmentId <= 0) {
            return [];
        }

        $assignedClasses = SubjectTeacherClass::classroomsForAssignment($assignmentId);

        if (!empty($assignedClasses)) {
            return $assignedClasses;
        }

        $schoolYearId = (int) ($assignment['mata_pelajaran_tahun_ajaran_id'] ?? 0);
        if ($schoolYearId <= 0) {
            return [];
        }

        $majorId = null;
        if (!empty($assignment['mata_pelajaran_jurusan_id'])) {
            $majorId = (int) $assignment['mata_pelajaran_jurusan_id'];
        }

        return Classroom::forSchoolYear($schoolYearId, $majorId);
    }
}

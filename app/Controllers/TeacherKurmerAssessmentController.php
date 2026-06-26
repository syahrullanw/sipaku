<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentKurmerSubjectSummary;
use App\Models\StudentTpAssessment;
use App\Models\SubjectLearningObjective;
use App\Models\SubjectTeacher;
use App\Models\SubjectTeacherClass;
use App\Services\AssessmentEvaluator;
use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Throwable;

class TeacherKurmerAssessmentController extends Controller
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

        ['assignment' => $assignment, 'teacherId' => $teacherId] = $context;

        $classOptions = $this->classOptions($assignment);
        $classId = (int) $request->query('kelas_id', 0);
        $students = [];
        $learningObjectives = [];
        $assessmentMap = [];
        $summaryMap = [];
        $classSummary = null;

        if ($classId > 0) {
            $classSummary = $this->summarizeClass($classOptions, $classId);

            $classRecord = Classroom::find($classId);
            if ($classRecord !== null && ($classRecord['kurikulum'] ?? 'k13') !== 'kurmer') {
                Session::flash('error', 'Kelas ini bukan Kurikulum Merdeka. Gunakan menu penilaian K13.');

                return $this->redirect('guru/nilai/' . $assignmentId . '/pengetahuan?kelas_id=' . $classId);
            }

            $learningObjectives = SubjectLearningObjective::byAssignment($assignment['id'], $classId);
            $students = Student::byClass($classId, (int) $assignment['mata_pelajaran_tahun_ajaran_id']);
            $studentIds = array_values(array_filter(array_map(static fn ($student): int => (int) ($student['id'] ?? 0), $students)));

            if (!empty($studentIds)) {
                $assessmentMap = StudentTpAssessment::mapByAssignmentAndClass($assignment['id'], $classId, $studentIds);
                $summaryMap = StudentKurmerSubjectSummary::byAssignmentAndClass($assignment['id'], $classId, $studentIds);
            }
        }

        return $this->render('teacher/subjects/kurmer', [
            'title' => 'Penilaian KurMer',
            'pageTitle' => 'Input TP & Ringkasan KurMer',
            'activeMenu' => 'teacher-subject-assessments',
            'assignment' => $assignment,
            'classOptions' => $classOptions,
            'selectedClassId' => $classId > 0 ? $classId : null,
            'students' => $students,
            'learningObjectives' => $learningObjectives,
            'assessmentMap' => $assessmentMap,
            'summaryMap' => $summaryMap,
            'classSummary' => $classSummary,
        ]);
    }

    public function storeLearningObjective(Request $request, int $assignmentId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'guru/nilai/' . $assignmentId . '/kurmer/tp')) {
            return $response;
        }

        $context = $this->resolveContext($assignmentId);
        if ($context instanceof Response) {
            return $context;
        }

        ['assignment' => $assignment] = $context;

        $classId = (int) $request->input('kelas_id', 0);
        $redirectUrl = 'guru/nilai/' . $assignmentId . '/kurmer';
        if ($classId > 0) {
            $redirectUrl .= '?kelas_id=' . $classId;
        }

        if (!$this->ensureKurmerClass($classId)) {
            Session::flash('error', 'Penilaian KurMer hanya berlaku untuk kelas Kurikulum Merdeka.');

            return $this->redirect($redirectUrl);
        }

        $code = strtoupper(trim((string) $request->input('kode_tp', '')));
        $fase = trim((string) $request->input('fase', ''));
        $elemen = trim((string) $request->input('elemen', ''));
        $deskripsi = trim((string) $request->input('deskripsi', ''));
        $urutan = $request->input('urutan') !== null ? (int) $request->input('urutan') : null;

        if ($code === '' || $classId <= 0) {
            Session::flash('error', 'Kode TP dan kelas wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect($redirectUrl);
        }

        if (SubjectLearningObjective::existsWithCode($assignment['id'], $classId, $code)) {
            Session::flash('error', 'Kode TP sudah digunakan pada kelas ini.');
            Session::flashInput($request->all());

            return $this->redirect($redirectUrl);
        }

        $now = date('Y-m-d H:i:s');

        $created = SubjectLearningObjective::create([
            'guru_mata_pelajaran_id' => $assignment['id'],
            'kelas_id' => $classId,
            'kode_tp' => $code,
            'fase' => $fase !== '' ? $fase : null,
            'elemen' => $elemen !== '' ? $elemen : null,
            'deskripsi' => $deskripsi !== '' ? $deskripsi : null,
            'urutan' => $urutan,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Session::flash($created ? 'success' : 'error', $created ? 'TP berhasil ditambahkan.' : 'TP gagal disimpan.');

        return $this->redirect($redirectUrl);
    }

    public function deleteLearningObjective(Request $request, int $assignmentId, int $objectiveId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'guru/nilai/' . $assignmentId . '/kurmer/tp/' . $objectiveId . '/hapus')) {
            return $response;
        }

        $context = $this->resolveContext($assignmentId);
        if ($context instanceof Response) {
            return $context;
        }

        ['assignment' => $assignment] = $context;

        $classId = (int) $request->input('kelas_id', 0);
        $redirectUrl = 'guru/nilai/' . $assignmentId . '/kurmer';
        if ($classId > 0) {
            $redirectUrl .= '?kelas_id=' . $classId;
        }

        if (!$this->ensureKurmerClass($classId)) {
            Session::flash('error', 'Kelas ini bukan Kurikulum Merdeka.');

            return $this->redirect($redirectUrl);
        }

        $objective = SubjectLearningObjective::findForAssignment($objectiveId, $assignment['id'], $classId);

        if ($objective === null) {
            Session::flash('error', 'TP tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        $deleted = SubjectLearningObjective::deleteById($objectiveId);
        Session::flash($deleted ? 'success' : 'error', $deleted ? 'TP dihapus.' : 'TP gagal dihapus.');

        return $this->redirect($redirectUrl);
    }

    public function storeAssessments(Request $request, int $assignmentId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'guru/nilai/' . $assignmentId . '/kurmer/tp/simpan')) {
            return $response;
        }

        $context = $this->resolveContext($assignmentId);
        if ($context instanceof Response) {
            return $context;
        }

        ['assignment' => $assignment] = $context;
        $classId = (int) $request->input('kelas_id', 0);
        $redirectUrl = 'guru/nilai/' . $assignmentId . '/kurmer';
        if ($classId > 0) {
            $redirectUrl .= '?kelas_id=' . $classId;
        }

        if (!$this->ensureKurmerClass($classId)) {
            Session::flash('error', 'Penilaian KurMer hanya untuk kelas KurMer.');

            return $this->redirect($redirectUrl);
        }

        $learningObjectives = SubjectLearningObjective::byAssignment($assignment['id'], $classId);
        if (empty($learningObjectives)) {
            Session::flash('error', 'Tambahkan TP terlebih dahulu.');

            return $this->redirect($redirectUrl);
        }

        $students = Student::byClass($classId, (int) $assignment['mata_pelajaran_tahun_ajaran_id']);
        if (empty($students)) {
            Session::flash('error', 'Tidak ada siswa pada kelas ini.');

            return $this->redirect($redirectUrl);
        }

        $capaianInput = $request->input('capaian', []);
        $nilaiInput = $request->input('nilai', []);
        $catatanInput = $request->input('catatan', []);
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

                foreach ($learningObjectives as $objective) {
                    $tpId = (int) ($objective['id'] ?? 0);
                    if ($tpId <= 0) {
                        continue;
                    }

                    $rawCapaian = $capaianInput[$studentId][$tpId] ?? null;
                    $normalizedCapaian = AssessmentEvaluator::normalizeKurmerCapaian($rawCapaian);

                    if ($normalizedCapaian === null) {
                        continue;
                    }

                    $rawNilai = $nilaiInput[$studentId][$tpId] ?? null;
                    $nilai = AssessmentEvaluator::normalizeScore($rawNilai);
                    $catatan = $catatanInput[$studentId][$tpId] ?? null;

                    StudentTpAssessment::upsert(
                        $assignment['id'],
                        $classId,
                        $tpId,
                        $studentId,
                        $normalizedCapaian,
                        $nilai,
                        is_string($catatan) ? trim($catatan) : null
                    );
                }
            }

            $connection->commit();
            Session::flash('success', 'Capaian TP berhasil disimpan.');
            if ($skippedInactive > 0) {
                Session::flash('warning', sprintf('%d siswa nonaktif dilewati dan capaian TP-nya tidak diubah.', $skippedInactive));
            }
        } catch (Throwable $exception) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollBack();
            }
            Session::flash('error', 'Gagal menyimpan capaian TP: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    public function storeSummaries(Request $request, int $assignmentId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'guru/nilai/' . $assignmentId . '/kurmer/ringkasan/simpan')) {
            return $response;
        }

        $context = $this->resolveContext($assignmentId);
        if ($context instanceof Response) {
            return $context;
        }

        ['assignment' => $assignment] = $context;
        $classId = (int) $request->input('kelas_id', 0);
        $redirectUrl = 'guru/nilai/' . $assignmentId . '/kurmer';
        if ($classId > 0) {
            $redirectUrl .= '?kelas_id=' . $classId;
        }

        if (!$this->ensureKurmerClass($classId)) {
            Session::flash('error', 'Ringkasan KurMer hanya untuk kelas KurMer.');

            return $this->redirect($redirectUrl);
        }

        $students = Student::byClass($classId, (int) $assignment['mata_pelajaran_tahun_ajaran_id']);
        if (empty($students)) {
            Session::flash('error', 'Tidak ada siswa pada kelas ini.');

            return $this->redirect($redirectUrl);
        }

        $capaianInput = $request->input('capaian_akhir', []);
        $deskripsiInput = $request->input('deskripsi_umum', []);
        $tindakLanjutInput = $request->input('tindak_lanjut', []);
        $nilaiInput = $request->input('nilai_opsional', []);
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

                $capaianAkhir = AssessmentEvaluator::normalizeKurmerCapaian($capaianInput[$studentId] ?? null);
                $deskripsi = is_string($deskripsiInput[$studentId] ?? null) ? trim((string) $deskripsiInput[$studentId]) : null;
                $tindakLanjut = is_string($tindakLanjutInput[$studentId] ?? null) ? trim((string) $tindakLanjutInput[$studentId]) : null;
                $nilai = AssessmentEvaluator::normalizeScore($nilaiInput[$studentId] ?? null);

                if ($deskripsi === '') {
                    $deskripsi = null;
                }
                if ($tindakLanjut === '') {
                    $tindakLanjut = null;
                }

                StudentKurmerSubjectSummary::upsert(
                    $assignment['id'],
                    $classId,
                    $studentId,
                    $capaianAkhir,
                    $deskripsi,
                    $tindakLanjut,
                    $nilai,
                    null
                );
            }

            $connection->commit();
            Session::flash('success', 'Ringkasan mapel KurMer berhasil disimpan.');
            if ($skippedInactive > 0) {
                Session::flash('warning', sprintf('%d siswa nonaktif dilewati dan ringkasannya tidak diubah.', $skippedInactive));
            }
        } catch (Throwable $exception) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollBack();
            }
            Session::flash('error', 'Gagal menyimpan ringkasan KurMer: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
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

        return [
            'teacherId' => $teacherId,
            'assignment' => $assignment,
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

    /**
     * @param array<int, array<string, mixed>> $classOptions
     */
    private function summarizeClass(array $classOptions, int $classId): ?string
    {
        foreach ($classOptions as $class) {
            if ((int) ($class['id'] ?? 0) === $classId) {
                return sprintf(
                    'Kelas %s %s · Kurikulum %s',
                    $class['tingkat'] ?? '-',
                    $class['nama'] ?? '-',
                    strtoupper((string) ($class['kurikulum'] ?? 'k13'))
                );
            }
        }

        return null;
    }

    private function ensureKurmerClass(int $classId): bool
    {
        if ($classId <= 0) {
            return false;
        }

        $class = Classroom::find($classId);

        return $class !== null && ($class['kurikulum'] ?? 'k13') === 'kurmer';
    }
}

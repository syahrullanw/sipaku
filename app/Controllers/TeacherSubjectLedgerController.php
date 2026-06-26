<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentKnowledgeAssessment;
use App\Models\StudentKurmerSubjectSummary;
use App\Models\StudentSkillAssessment;
use App\Models\SubjectAssessmentSetting;
use App\Models\SubjectTeacher;
use App\Models\SubjectTeacherClass;
use App\Services\LedgerExporter;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class TeacherSubjectLedgerController extends Controller
{
    protected ?string $layout = 'admin';

    public function show(Request $request, int $assignmentId): Response
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

        $classId = (int) $request->query('kelas_id', 0);
        $classOptions = $this->classOptions($assignment);
        $ledgerData = $this->prepareLedgerData($assignment, $setting, $classId > 0 ? $classId : null);

        return $this->render('teacher/subjects/ledger', [
            'title' => 'Legger Nilai Mapel',
            'pageTitle' => 'Legger Nilai Pengetahuan & Keterampilan',
            'activeMenu' => 'teacher-subject-assessments',
            'assignment' => $assignment,
            'setting' => $setting,
            'classOptions' => $classOptions,
            'selectedClassId' => $classId > 0 ? $classId : null,
            'selectedClass' => $ledgerData['class'] ?? null,
            'students' => $ledgerData['students'],
            'ledgerRows' => $ledgerData['rows'],
            'skillEnabled' => $ledgerData['hasSkill'],
            'isKurmer' => $ledgerData['isKurmer'] ?? false,
        ]);
    }

    public function exportPdf(Request $request, int $assignmentId): Response
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

        $classId = (int) $request->query('kelas_id', 0);
        if ($classId <= 0) {
            Session::flash('error', 'Pilih kelas terlebih dahulu sebelum mengekspor legger.');

            return $this->redirect('guru/nilai/' . $assignmentId . '/legger');
        }

        $ledgerData = $this->prepareLedgerData($assignment, $setting, $classId);

        if (empty($ledgerData['rows'])) {
            Session::flash('error', 'Belum ada data nilai yang dapat diekspor untuk kelas tersebut.');

            return $this->redirect('guru/nilai/' . $assignmentId . '/legger?kelas_id=' . $classId);
        }

        $content = LedgerExporter::makePdf($assignment, $ledgerData['class'] ?? null, $setting, $ledgerData['rows'], $ledgerData['hasSkill'], $ledgerData['isKurmer'] ?? false);
        $filename = $this->generateFileName($assignment, $ledgerData['class'] ?? null, 'pdf');

        return Response::make($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportExcel(Request $request, int $assignmentId): Response
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

        $classId = (int) $request->query('kelas_id', 0);
        if ($classId <= 0) {
            Session::flash('error', 'Pilih kelas terlebih dahulu sebelum mengekspor legger.');

            return $this->redirect('guru/nilai/' . $assignmentId . '/legger');
        }

        $ledgerData = $this->prepareLedgerData($assignment, $setting, $classId);

        if (empty($ledgerData['rows'])) {
            Session::flash('error', 'Belum ada data nilai yang dapat diekspor untuk kelas tersebut.');

            return $this->redirect('guru/nilai/' . $assignmentId . '/legger?kelas_id=' . $classId);
        }

        $content = LedgerExporter::makeExcel($assignment, $ledgerData['class'] ?? null, $setting, $ledgerData['rows'], $ledgerData['hasSkill'], $ledgerData['isKurmer'] ?? false);
        $filename = $this->generateFileName($assignment, $ledgerData['class'] ?? null, 'xls');

        return Response::make($content, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function history(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh guru.');

            return $this->redirect('dashboard');
        }

        $teacherId = (int) $user['teacher_id'];
        $assignments = SubjectTeacher::byTeacher($teacherId);

        if (empty($assignments)) {
            return $this->render('teacher/subjects/history', [
                'title' => 'Riwayat Mengajar',
                'pageTitle' => 'Riwayat Legger Mengajar',
                'activeMenu' => 'teacher-subject-assessments',
                'historyYears' => [],
                'selectedYearId' => null,
                'selectedYear' => null,
                'assignments' => [],
            ]);
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        $assignmentIds = [];
        foreach ($assignments as $assignment) {
            $assignmentId = (int) ($assignment['id'] ?? 0);
            $yearId = (int) ($assignment['mata_pelajaran_tahun_ajaran_id'] ?? 0);

            if ($assignmentId <= 0) {
                continue;
            }

            if ($activeYearId > 0 && $yearId === $activeYearId) {
                continue;
            }

            $assignmentIds[] = $assignmentId;
        }

        $assignmentIds = array_values(array_unique($assignmentIds));
        $knowledgeCounts = StudentKnowledgeAssessment::countByAssignments($assignmentIds);
        $skillCounts = StudentSkillAssessment::countByAssignments($assignmentIds);

        $historyYears = [];

        foreach ($assignments as $assignment) {
            $assignmentId = (int) ($assignment['id'] ?? 0);
            $yearId = (int) ($assignment['mata_pelajaran_tahun_ajaran_id'] ?? 0);

            if ($assignmentId <= 0) {
                continue;
            }

            if ($activeYearId > 0 && $yearId === $activeYearId) {
                continue;
            }

            $knowledgeTotal = $knowledgeCounts[$assignmentId] ?? 0;
            $skillTotal = $skillCounts[$assignmentId] ?? 0;

            if ($knowledgeTotal <= 0 && $skillTotal <= 0) {
                continue;
            }

            $effectiveYearId = $yearId > 0 ? $yearId : 0;
            $yearLabel = (string) ($assignment['mata_pelajaran_tahun_ajaran_nama'] ?? 'Tahun Ajaran Tidak Diketahui');
            $semester = (int) ($assignment['mata_pelajaran_tahun_ajaran_semester'] ?? 0);
            $semesterLabel = $semester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';

            if (!isset($historyYears[$effectiveYearId])) {
                $historyYears[$effectiveYearId] = [
                    'year_id' => $effectiveYearId,
                    'label' => sprintf('%s - %s', $yearLabel, $semesterLabel),
                    'assignments' => [],
                ];
            }

            $historyYears[$effectiveYearId]['assignments'][] = [
                'id' => $assignmentId,
                'subject_code' => (string) ($assignment['mata_pelajaran_kode'] ?? ''),
                'subject_name' => (string) ($assignment['mata_pelajaran_nama'] ?? ''),
                'subject_type' => (string) ($assignment['mata_pelajaran_jenis'] ?? ''),
                'knowledge_total' => $knowledgeTotal,
                'skill_total' => $skillTotal,
            ];
        }

        if (empty($historyYears)) {
            return $this->render('teacher/subjects/history', [
                'title' => 'Riwayat Mengajar',
                'pageTitle' => 'Riwayat Legger Mengajar',
                'activeMenu' => 'teacher-subject-assessments',
                'historyYears' => [],
                'selectedYearId' => null,
                'selectedYear' => null,
                'assignments' => [],
            ]);
        }

        krsort($historyYears);

        $requestedYearId = (int) $request->query('tahun_ajaran_id', 0);
        $selectedYearId = null;

        if ($requestedYearId > 0 && isset($historyYears[$requestedYearId])) {
            $selectedYearId = $requestedYearId;
        } else {
            $keys = array_keys($historyYears);
            $selectedYearId = $keys[0];
        }

        $selectedYear = $historyYears[$selectedYearId] ?? null;
        $assignmentsList = $selectedYear !== null ? $selectedYear['assignments'] : [];

        return $this->render('teacher/subjects/history', [
            'title' => 'Riwayat Mengajar',
            'pageTitle' => 'Riwayat Legger Mengajar',
            'activeMenu' => 'teacher-subject-assessments',
            'historyYears' => array_values($historyYears),
            'selectedYearId' => $selectedYearId,
            'selectedYear' => $selectedYear,
            'assignments' => $assignmentsList,
        ]);
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

    /**
     * @param array<string, mixed> $assignment
     * @param array<string, mixed> $setting
     * @return array<string, mixed>
     */
    private function prepareLedgerData(array $assignment, array $setting, ?int $classId): array
    {
        $class = null;
        $students = [];
        $rows = [];
        $hasSkill = (int) ($setting['enable_keterampilan'] ?? 1) === 1;
        $isKurmer = false;

        if ($classId !== null && $classId > 0) {
            $class = Classroom::findWithRelations($classId);
            $isKurmer = ($class['kurikulum'] ?? 'k13') === 'kurmer';

            $schoolYearId = (int) ($assignment['mata_pelajaran_tahun_ajaran_id'] ?? 0);
            $students = Student::byClass($classId, $schoolYearId > 0 ? $schoolYearId : null);

            if (empty($students) && $schoolYearId > 0) {
                $students = Student::byClass($classId, null);
            }

            $studentIds = array_filter(array_map(static fn ($student) => (int) ($student['id'] ?? 0), $students));

            $knowledgeMap = [];
            $skillMap = [];
            $kurmerMap = [];

            if (!empty($studentIds)) {
                if ($isKurmer) {
                    $kurmerMap = StudentKurmerSubjectSummary::byAssignmentAndClass($assignment['id'], $classId, $studentIds);
                } else {
                    $knowledgeRecords = StudentKnowledgeAssessment::byAssignment($assignment['id'], $studentIds);
                    foreach ($knowledgeRecords as $record) {
                        $studentId = (int) ($record['siswa_id'] ?? 0);
                        if ($studentId > 0) {
                            $knowledgeMap[$studentId] = $record;
                        }
                    }

                    $skillRecords = StudentSkillAssessment::byAssignment($assignment['id'], $studentIds);
                    foreach ($skillRecords as $record) {
                        $studentId = (int) ($record['siswa_id'] ?? 0);
                        if ($studentId > 0) {
                            $skillMap[$studentId] = $record;
                        }
                    }
                }
            }

            foreach ($students as $student) {
                $studentId = (int) ($student['id'] ?? 0);
                if ($studentId <= 0) {
                    continue;
                }

                $rows[] = [
                    'student' => $student,
                    'knowledge' => $knowledgeMap[$studentId] ?? null,
                    'skill' => $skillMap[$studentId] ?? null,
                    'kurmer_summary' => $kurmerMap[$studentId] ?? null,
                ];
            }

            if ($isKurmer) {
                $hasSkill = false;
            } elseif (!$hasSkill) {
                foreach ($rows as $row) {
                    if (!empty($row['skill'])) {
                        $hasSkill = true;
                        break;
                    }
                }
            }
        }

        return [
            'class' => $class,
            'students' => $students,
            'rows' => $rows,
            'hasSkill' => $hasSkill,
            'isKurmer' => $isKurmer,
        ];
    }

    /**
     * @param array<string, mixed> $assignment
     * @param array<string, mixed>|null $class
     */
    private function generateFileName(array $assignment, ?array $class, string $extension): string
    {
        $parts = [];

        $subjectCode = trim((string) ($assignment['mata_pelajaran_kode'] ?? ''));
        $subjectName = trim((string) ($assignment['mata_pelajaran_nama'] ?? ''));

        if ($subjectCode !== '') {
            $parts[] = $subjectCode;
        } elseif ($subjectName !== '') {
            $parts[] = $subjectName;
        } else {
            $parts[] = 'legger';
        }

        if ($class !== null) {
            $classLabel = trim((string) (($class['tingkat'] ?? '') . ' ' . ($class['nama'] ?? '')));
            if ($classLabel !== '') {
                $parts[] = $classLabel;
            }
        }

        $parts[] = date('Ymd-His');

        $base = implode('-', array_map([$this, 'slugify'], $parts));
        if ($base === '') {
            $base = 'legger';
        }

        return $base . '.' . $extension;
    }

    private function slugify(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }
}

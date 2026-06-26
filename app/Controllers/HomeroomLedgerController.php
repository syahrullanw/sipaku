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
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class HomeroomLedgerController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $teacherId = (int) $user['teacher_id'];
        $activeYear = SchoolYear::active();

        $classes = $activeYear !== null
            ? Classroom::homeroomClassesForTeacher($teacherId, (int) ($activeYear['id'] ?? 0))
            : [];

        if (empty($classes)) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId);
        }

        $selectedClassId = (int) $request->query('kelas_id', 0);
        $selectedClass = null;

        foreach ($classes as $class) {
            if ((int) ($class['id'] ?? 0) === $selectedClassId) {
                $selectedClass = $class;
                break;
            }
        }

        if ($selectedClass === null && !empty($classes)) {
            $selectedClass = $classes[0];
            $selectedClassId = (int) ($selectedClass['id'] ?? 0);
        }

        $students = [];
        $assignments = [];
        $assignmentSummaries = [];
        $ledgerRows = [];
        $hasSkillData = false;
        $isKurmer = false;

        if ($selectedClass !== null) {
            $schoolYearId = (int) ($selectedClass['tahun_ajaran_id'] ?? 0);
            $majorId = isset($selectedClass['jurusan_id']) ? (int) $selectedClass['jurusan_id'] : null;
            $schoolYearFilter = $schoolYearId > 0 ? $schoolYearId : null;
            $isKurmer = ($selectedClass['kurikulum'] ?? 'k13') === 'kurmer';

            $students = Student::byClass($selectedClassId, $schoolYearFilter);

            if (!empty($students) && $schoolYearId > 0) {
                $assignments = SubjectTeacher::bySchoolYearForClass($schoolYearId, $majorId, $selectedClassId);

                if (empty($assignments)) {
                    $assignments = SubjectTeacher::bySchoolYearForClass($schoolYearId, $majorId);
                }
            }

            if (!empty($assignments)) {
                $assignmentIds = array_values(array_filter(array_map(static function (array $assignment): int {
                    return (int) ($assignment['id'] ?? 0);
                }, $assignments), static fn (int $id) => $id > 0));

                if (!empty($assignmentIds)) {
                    $knowledgeMap = $isKurmer ? [] : StudentKnowledgeAssessment::mapByClass($selectedClassId, $assignmentIds);
                    $skillMap = $isKurmer ? [] : StudentSkillAssessment::mapByClass($selectedClassId, $assignmentIds);
                    $kurmerMaps = [];

                    $settingsMap = [];

                    foreach ($assignmentIds as $assignmentId) {
                        $settingsMap[$assignmentId] = SubjectAssessmentSetting::ensureDefault($assignmentId);
                        if ($isKurmer) {
                            $kurmerMaps[$assignmentId] = StudentKurmerSubjectSummary::byAssignmentAndClass(
                                $assignmentId,
                                $selectedClassId,
                                array_column($students, 'id')
                            );
                        }
                    }

                    foreach ($assignments as $assignment) {
                        $assignmentId = (int) ($assignment['id'] ?? 0);

                        if ($assignmentId <= 0) {
                            continue;
                        }

                        $setting = $settingsMap[$assignmentId] ?? null;
                        $kkmEnabled = (int) ($setting['enable_kkm'] ?? 0) === 1;
                        $kkmValue = $kkmEnabled ? (float) ($setting['nilai_kkm'] ?? 0) : null;

                        $assignmentSummaries[] = [
                            'id' => $assignmentId,
                            'code' => (string) ($assignment['mata_pelajaran_kode'] ?? ''),
                            'name' => (string) ($assignment['mata_pelajaran_nama'] ?? ''),
                            'group' => (string) ($assignment['mata_pelajaran_jenis'] ?? ''),
                            'teacher' => (string) ($assignment['guru_nama'] ?? ''),
                            'kkm_enabled' => $kkmEnabled,
                            'kkm_value' => $kkmValue,
                        ];
                    }

                    foreach ($students as $student) {
                        $studentId = (int) ($student['id'] ?? 0);

                        if ($studentId <= 0) {
                            continue;
                        }

                        $subjectsData = [];
                        $totalScore = 0.0;
                        $scoreCount = 0;

                        foreach ($assignmentSummaries as $assignmentSummary) {
                            $assignmentId = (int) $assignmentSummary['id'];

                            $knowledge = $knowledgeMap[$studentId][$assignmentId] ?? null;
                            $skill = $skillMap[$studentId][$assignmentId] ?? null;
                            $kurmerSummary = $isKurmer ? ($kurmerMaps[$assignmentId][$studentId] ?? null) : null;

                            if ($isKurmer) {
                                $nilaiOpsional = $kurmerSummary['nilai_opsional'] ?? null;
                                if ($nilaiOpsional !== null) {
                                    $totalScore += (float) $nilaiOpsional;
                                    $scoreCount++;
                                }

                                $subjectsData[$assignmentId] = [
                                    'kurmer_summary' => $kurmerSummary,
                                ];
                            } else {
                                $knowledgeScore = $knowledge !== null && $knowledge['nilai_akhir'] !== null
                                    ? (float) $knowledge['nilai_akhir']
                                    : null;

                                $skillScore = $skill !== null && $skill['nilai_akhir'] !== null
                                    ? (float) $skill['nilai_akhir']
                                    : null;

                                if ($skillScore !== null) {
                                    $hasSkillData = true;
                                }

                                if ($knowledgeScore !== null) {
                                    $totalScore += $knowledgeScore;
                                    $scoreCount++;
                                }

                                $kkmEnabled = (bool) ($assignmentSummary['kkm_enabled'] ?? false);
                                $kkmValue = $assignmentSummary['kkm_value'] ?? null;
                                $isBelowStandard = $kkmEnabled
                                    && $kkmValue !== null
                                    && $knowledgeScore !== null
                                    && $knowledgeScore < (float) $kkmValue;

                                $subjectsData[$assignmentId] = [
                                    'knowledge_score' => $knowledgeScore,
                                    'knowledge_predicate' => $knowledge['predikat'] ?? null,
                                    'skill_score' => $skillScore,
                                    'skill_predicate' => $skill['predikat'] ?? null,
                                    'kkm_enabled' => $kkmEnabled,
                                    'kkm_value' => $kkmValue !== null ? (float) $kkmValue : null,
                                    'below_standard' => $isBelowStandard,
                                ];
                            }
                        }

                        $ledgerRows[] = [
                            'student' => $student,
                            'subjects' => $subjectsData,
                            'total_score' => $scoreCount > 0 ? $totalScore : null,
                            'score_count' => $scoreCount,
                            'average_score' => $scoreCount > 0 ? $totalScore / $scoreCount : null,
                        ];
                    }
                }
            }
        }

        return $this->render('homeroom/ledger/index', [
            'title' => 'Legger Kelas',
            'pageTitle' => 'Legger Nilai Kelas',
            'activeMenu' => 'homeroom-ledger',
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'assignments' => $assignmentSummaries,
            'students' => $students,
            'ledgerRows' => $ledgerRows,
            'hasSkillData' => $hasSkillData,
            'isKurmer' => $isKurmer,
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentKurmerSubjectSummary;
use App\Models\StudentKnowledgeAssessment;
use App\Models\StudentPlacementHistory;
use App\Models\StudentSkillAssessment;
use App\Models\SubjectTeacher;
use App\Models\Classroom;

class StudentScoreSummary
{
    /**
     * @return array{
     *     student: ?array<string, mixed>,
     *     school_year: ?array<string, mixed>,
     *     subjects: array<int, array<string, mixed>>,
     *     summary: ?array<string, mixed>
     * }
     */
    public static function forStudent(int $studentId, ?int $preferredSchoolYearId = null): array
    {
        $studentId = (int) $studentId;

        if ($studentId <= 0) {
            return [
                'student' => null,
                'school_year' => null,
                'subjects' => [],
                'summary' => null,
                'curriculum' => 'k13',
            ];
        }

        $student = Student::findWithRelations($studentId);

        if ($student === null) {
            return [
                'student' => null,
                'school_year' => null,
                'subjects' => [],
                'summary' => null,
                'curriculum' => 'k13',
            ];
        }

        $studentYearId = (int) ($student['tahun_ajaran_id'] ?? 0);
        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        $targetYearId = $studentYearId > 0 ? $studentYearId : $activeYearId;

        if ($preferredSchoolYearId !== null && $preferredSchoolYearId > 0) {
            $targetYearId = $preferredSchoolYearId;
        }

        $placement = $targetYearId > 0
            ? StudentPlacementHistory::forStudentYear($studentId, $targetYearId)
            : null;

        $classId = $placement !== null && !empty($placement['class_id'])
            ? (int) $placement['class_id']
            : (int) ($student['kelas_id'] ?? 0);
        $classRecord = $classId > 0 ? Classroom::find($classId) : null;
        $curriculum = $classRecord['kurikulum'] ?? 'k13';

        $schoolYear = $targetYearId > 0 ? SchoolYear::find($targetYearId) : null;

        if ($classId <= 0 || $targetYearId <= 0) {
            return [
                'student' => $student,
                'school_year' => $schoolYear,
                'subjects' => [],
                'curriculum' => $curriculum,
                'summary' => null,
            ];
        }

        $assignments = SubjectTeacher::bySchoolYearForClass($targetYearId, null, $classId);

        if (empty($assignments)) {
            return [
                'student' => $student,
                'school_year' => $schoolYear,
                'subjects' => [],
                'curriculum' => $curriculum,
                'summary' => [
                    'total_subjects' => 0,
                    'completed_subjects' => 0,
                    'subjects_with_full_scores' => 0,
                    'pending_subjects' => 0,
                    'knowledge_completed' => 0,
                    'skill_completed' => 0,
                    'knowledge_average' => null,
                    'skill_average' => null,
                    'overall_average' => null,
                    'last_updated_at' => null,
                    'top_subjects' => [],
                ],
            ];
        }

        $assignmentIds = array_values(array_filter(array_map(
            static fn ($assignment) => (int) ($assignment['id'] ?? 0),
            $assignments
        ), static fn (int $id) => $id > 0));

        if (empty($assignmentIds)) {
            return [
                'student' => $student,
                'school_year' => $schoolYear,
                'subjects' => [],
                'curriculum' => $curriculum,
                'summary' => [
                    'total_subjects' => 0,
                    'completed_subjects' => 0,
                    'subjects_with_full_scores' => 0,
                    'pending_subjects' => 0,
                    'knowledge_completed' => 0,
                    'skill_completed' => 0,
                    'knowledge_average' => null,
                    'skill_average' => null,
                    'overall_average' => null,
                    'last_updated_at' => null,
                    'top_subjects' => [],
                ],
            ];
        }

        $knowledgeScores = StudentKnowledgeAssessment::byAssignmentsForStudent($assignmentIds, $studentId);
        $skillScores = StudentSkillAssessment::byAssignmentsForStudent($assignmentIds, $studentId);
        $kurmerSummaries = $curriculum === 'kurmer' ? StudentKurmerSubjectSummary::byAssignmentsForStudent($assignmentIds, $studentId) : [];

        $subjects = [];
        $knowledgeSum = 0.0;
        $knowledgeCount = 0;
        $skillSum = 0.0;
        $skillCount = 0;
        $finalSum = 0.0;
        $finalCount = 0;
        $fullyScoredSubjects = 0;
        $latestTimestamp = null;

        foreach ($assignments as $assignment) {
            $assignmentId = (int) ($assignment['id'] ?? 0);

            if ($assignmentId <= 0) {
                continue;
            }

            $knowledge = $knowledgeScores[$assignmentId] ?? null;
            $skill = $skillScores[$assignmentId] ?? null;
            $kurmerSummary = $curriculum === 'kurmer' ? ($kurmerSummaries[$assignmentId] ?? null) : null;

            $knowledgeScore = null;
            $knowledgeUpdatedAt = null;
            $knowledgePredicate = null;

            if ($curriculum !== 'kurmer' && $knowledge !== null) {
                $knowledgeScoreValue = $knowledge['nilai_akhir'] ?? null;
                if ($knowledgeScoreValue !== null) {
                    $knowledgeScore = (float) $knowledgeScoreValue;
                    $knowledgeSum += $knowledgeScore;
                    $knowledgeCount++;
                }

                $knowledgeUpdatedAt = $knowledge['updated_at'] ?? ($knowledge['created_at'] ?? null);
                $knowledgePredicate = isset($knowledge['predikat']) ? (string) $knowledge['predikat'] : null;
            }

            $skillScore = null;
            $skillUpdatedAt = null;
            $skillPredicate = null;

            if ($curriculum !== 'kurmer' && $skill !== null) {
                $skillScoreValue = $skill['nilai_akhir'] ?? null;
                if ($skillScoreValue !== null) {
                    $skillScore = (float) $skillScoreValue;
                    $skillSum += $skillScore;
                    $skillCount++;
                }

                $skillUpdatedAt = $skill['updated_at'] ?? ($skill['created_at'] ?? null);
                $skillPredicate = isset($skill['predikat']) ? (string) $skill['predikat'] : null;
            }

            $finalScore = null;
            $finalAccumulator = 0.0;
            $componentsCompleted = 0;

            if ($knowledgeScore !== null) {
                $finalAccumulator += $knowledgeScore;
                $componentsCompleted++;
            }

            if ($skillScore !== null) {
                $finalAccumulator += $skillScore;
                $componentsCompleted++;
            }

            if ($curriculum === 'kurmer' && $kurmerSummary !== null) {
                $componentsCompleted = !empty($kurmerSummary['capaian_akhir_enum']) ? 1 : 0;
                $finalScore = isset($kurmerSummary['nilai_opsional']) && $kurmerSummary['nilai_opsional'] !== null
                    ? (float) $kurmerSummary['nilai_opsional']
                    : null;
                if ($finalScore !== null) {
                    $finalAccumulator = $finalScore;
                    $finalSum += $finalScore;
                    $finalCount++;
                }
            } elseif ($componentsCompleted > 0) {
                $finalScore = $finalAccumulator / $componentsCompleted;
                $finalSum += $finalScore;
                $finalCount++;
            }

            if ($componentsCompleted === 2) {
                $fullyScoredSubjects++;
            } elseif ($curriculum === 'kurmer' && $componentsCompleted >= 1) {
                $fullyScoredSubjects++;
            }

            $subjectLatestTimestamp = null;
            $subjectLatestString = null;

            if ($curriculum === 'kurmer' && $kurmerSummary !== null) {
                $timestamp = $kurmerSummary['updated_at'] ?? ($kurmerSummary['created_at'] ?? null);
                if ($timestamp !== null && $timestamp !== '' && strtotime((string) $timestamp) !== false) {
                    $subjectLatestTimestamp = strtotime((string) $timestamp);
                    $subjectLatestString = (string) $timestamp;
                    $latestTimestamp = max($latestTimestamp ?? 0, $subjectLatestTimestamp);
                }
            }

            foreach (['knowledge' => $knowledgeUpdatedAt, 'skill' => $skillUpdatedAt] as $timestamp) {
                if ($timestamp === null || $timestamp === '') {
                    continue;
                }

                $parsed = strtotime((string) $timestamp);

                if ($parsed === false) {
                    continue;
                }

                if ($subjectLatestTimestamp === null || $parsed > $subjectLatestTimestamp) {
                    $subjectLatestTimestamp = $parsed;
                    $subjectLatestString = (string) $timestamp;
                }

                if ($latestTimestamp === null || $parsed > $latestTimestamp) {
                    $latestTimestamp = $parsed;
                }
            }

            $subjects[] = [
                'assignment_id' => $assignmentId,
                'subject_code' => (string) ($assignment['mata_pelajaran_kode'] ?? ''),
                'subject_name' => (string) ($assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran'),
                'subject_group' => (string) ($assignment['mata_pelajaran_jenis'] ?? ''),
                'teacher_name' => (string) ($assignment['guru_nama'] ?? ''),
                'curriculum' => $curriculum,
                'knowledge_score' => $knowledgeScore,
                'knowledge_predicate' => $knowledgePredicate,
                'knowledge_description' => is_array($knowledge) ? ($knowledge['deskripsi'] ?? null) : null,
                'knowledge_updated_at' => $knowledgeUpdatedAt,
                'skill_score' => $skillScore,
                'skill_predicate' => $skillPredicate,
                'skill_description' => is_array($skill) ? ($skill['deskripsi'] ?? null) : null,
                'skill_updated_at' => $skillUpdatedAt,
                'final_score' => $finalScore,
                'components_completed' => $componentsCompleted,
                'latest_updated_at' => $subjectLatestString,
                'kurmer_summary' => $kurmerSummary,
            ];
        }

        $totalSubjects = count($subjects);
        $knowledgeAverage = $knowledgeCount > 0 ? round($knowledgeSum / $knowledgeCount, 2) : null;
        $skillAverage = $skillCount > 0 ? round($skillSum / $skillCount, 2) : null;
        $overallAverage = $finalCount > 0 ? round($finalSum / $finalCount, 2) : null;

        $scoredSubjects = array_values(array_filter(
            $subjects,
            static fn (array $subject): bool => isset($subject['final_score']) && $subject['final_score'] !== null
        ));

        usort($scoredSubjects, static function (array $a, array $b): int {
            $scoreA = (float) ($a['final_score'] ?? 0);
            $scoreB = (float) ($b['final_score'] ?? 0);

            return $scoreB <=> $scoreA;
        });

        $topSubjects = array_slice($scoredSubjects, 0, 3);

        $recentEntries = [];

        foreach ($subjects as $subject) {
            if (!is_array($subject)) {
                continue;
            }

            $subjectName = (string) ($subject['subject_name'] ?? 'Mata Pelajaran');
            $subjectCode = (string) ($subject['subject_code'] ?? '');
            $teacherName = (string) ($subject['teacher_name'] ?? '');

            if (!empty($subject['knowledge_updated_at'])) {
                $recentEntries[] = [
                    'subject_name' => $subjectName,
                    'subject_code' => $subjectCode,
                    'teacher_name' => $teacherName,
                    'component' => 'Pengetahuan',
                    'score' => $subject['knowledge_score'] ?? null,
                    'predicate' => $subject['knowledge_predicate'] ?? null,
                    'updated_at' => $subject['knowledge_updated_at'],
                ];
            }

            if (!empty($subject['skill_updated_at'])) {
                $recentEntries[] = [
                    'subject_name' => $subjectName,
                    'subject_code' => $subjectCode,
                    'teacher_name' => $teacherName,
                    'component' => 'Keterampilan',
                    'score' => $subject['skill_score'] ?? null,
                    'predicate' => $subject['skill_predicate'] ?? null,
                    'updated_at' => $subject['skill_updated_at'],
                ];
            }

            if (($subject['curriculum'] ?? 'k13') === 'kurmer' && !empty($subject['kurmer_summary']['updated_at'] ?? null)) {
                $recentEntries[] = [
                    'subject_name' => $subjectName,
                    'subject_code' => $subjectCode,
                    'teacher_name' => $teacherName,
                    'component' => 'KurMer',
                    'score' => $subject['kurmer_summary']['nilai_opsional'] ?? null,
                    'predicate' => $subject['kurmer_summary']['capaian_akhir_enum'] ?? null,
                    'updated_at' => $subject['kurmer_summary']['updated_at'],
                ];
            }
        }

        $recentEntries = array_values(array_filter($recentEntries, static function (array $entry): bool {
            $timestamp = $entry['updated_at'] ?? null;
            if ($timestamp === null || $timestamp === '') {
                return false;
            }

            return strtotime((string) $timestamp) !== false;
        }));

        usort($recentEntries, static function (array $a, array $b): int {
            $timeA = strtotime((string) ($a['updated_at'] ?? ''));
            $timeB = strtotime((string) ($b['updated_at'] ?? ''));

            return $timeB <=> $timeA;
        });

        $recentEntries = array_slice($recentEntries, 0, 6);

        $summary = [
            'total_subjects' => $totalSubjects,
            'completed_subjects' => $finalCount,
            'subjects_with_full_scores' => $fullyScoredSubjects,
            'pending_subjects' => max(0, $totalSubjects - $finalCount),
            'knowledge_completed' => $knowledgeCount,
            'skill_completed' => $skillCount,
            'knowledge_average' => $knowledgeAverage,
            'skill_average' => $skillAverage,
            'overall_average' => $overallAverage,
            'last_updated_at' => $latestTimestamp !== null ? date('Y-m-d H:i:s', $latestTimestamp) : null,
            'top_subjects' => $topSubjects,
            'recent_entries' => $recentEntries,
            'class_name' => isset($student['kelas_nama']) ? (string) $student['kelas_nama'] : null,
            'school_year_name' => $schoolYear['nama'] ?? null,
        ];

        return [
            'student' => $student,
            'school_year' => $schoolYear,
            'curriculum' => $curriculum,
            'subjects' => $subjects,
            'summary' => $summary,
        ];
    }
}

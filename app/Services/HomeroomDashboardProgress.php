<?php

namespace App\Services;

use App\Models\Attitude;
use App\Models\AttitudeScore;
use App\Models\Classroom;
use App\Models\HomeroomNote;
use App\Models\HomeroomPrakerinConfirmation;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Models\StudentExtracurricular;
use App\Models\StudentKnowledgeAssessment;
use App\Models\StudentPrakerinPlacement;
use App\Models\SubjectTeacher;

class HomeroomDashboardProgress
{
    /**
     * @var array<string, array<string, string>>
     */
    private const CATEGORY_DEFINITIONS = [
        'knowledge' => [
            'label' => 'Nilai Pengetahuan',
            'description' => 'Diinput guru mata pelajaran',
            'unit' => 'nilai terekam',
        ],
        'attitudes' => [
            'label' => 'Nilai Sikap',
            'description' => 'Spiritual & sosial',
            'unit' => 'entri sikap',
        ],
        'prakerin' => [
            'label' => 'Prakerin',
            'description' => 'Penempatan industri',
            'unit' => 'siswa ditempatkan',
        ],
        'achievements' => [
            'label' => 'Prestasi',
            'description' => 'Siswa berprestasi',
            'unit' => 'siswa tercatat',
        ],
        'extracurriculars' => [
            'label' => 'Ekskul',
            'description' => 'Minimal satu ekskul',
            'unit' => 'siswa memiliki ekskul',
        ],
        'notes' => [
            'label' => 'Catatan Wali Kelas',
            'description' => 'Penguatan & tindak lanjut',
            'unit' => 'siswa memiliki catatan',
        ],
    ];

    /**
     * Hitung progres pengisian data untuk wali kelas.
     *
     * @return array<string, mixed>
     */
    public static function calculateForTeacher(int $teacherId): array
    {
        $activeYear = SchoolYear::active();
        $activeYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : null;

        $classes = [];

        if ($teacherId > 0) {
            if ($activeYearId !== null && $activeYearId > 0) {
                $classes = Classroom::homeroomClassesForTeacher($teacherId, $activeYearId);
            }

            if (empty($classes)) {
                $classes = Classroom::homeroomClassesForTeacher($teacherId);
            }
        }

        $categories = [];

        foreach (self::CATEGORY_DEFINITIONS as $key => $definition) {
            $categories[$key] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'unit' => $definition['unit'],
                'completed' => 0,
                'total' => 0,
                'pending' => 0,
                'percentage' => null,
            ];
        }

        if ($teacherId <= 0 || empty($classes)) {
            return [
                'activeYear' => $activeYear,
                'studentCount' => 0,
                'classesCount' => 0,
                'categories' => array_values($categories),
                'overall' => [
                    'percentage' => null,
                    'count' => 0,
                    'totalCategories' => count($categories),
                ],
            ];
        }

        $classIds = array_values(array_filter(array_map(
            static fn ($class) => (int) ($class['id'] ?? 0),
            $classes
        ), static fn (int $id) => $id > 0));

        $confirmationMap = HomeroomPrakerinConfirmation::mapByClassIds($classIds, $teacherId);

        $studentTotal = 0;

        foreach ($classes as $class) {
            $classId = (int) ($class['id'] ?? 0);
            if ($classId <= 0) {
                continue;
            }

            $schoolYearId = isset($class['tahun_ajaran_id']) ? (int) $class['tahun_ajaran_id'] : null;
            $majorId = isset($class['jurusan_id']) ? (int) $class['jurusan_id'] : null;

            $students = Student::byClass($classId, $schoolYearId);

            if (empty($students)) {
                continue;
            }

            $studentIds = [];

            foreach ($students as $student) {
                $studentId = (int) ($student['id'] ?? 0);
                if ($studentId > 0) {
                    $studentIds[] = $studentId;
                }
            }

            if (empty($studentIds)) {
                continue;
            }

            $prakerinConfirmation = $confirmationMap[$classId] ?? null;
            $prakerinRequired = $prakerinConfirmation === null
                ? true
                : ((int) ($prakerinConfirmation['prakerin_required'] ?? 1) === 1);

            $studentCount = count($studentIds);
            $studentTotal += $studentCount;
            $studentIdSet = array_fill_keys($studentIds, true);

            if ($prakerinRequired) {
                $categories['prakerin']['total'] += $studentCount;
            }
            $categories['achievements']['total'] += $studentCount;
            $categories['extracurriculars']['total'] += $studentCount;
            $categories['notes']['total'] += $studentCount;
            $categories['attitudes']['total'] += $studentCount * count(Attitude::TYPES);

            $assignmentYearId = null;
            if ($schoolYearId !== null && $schoolYearId > 0) {
                $assignmentYearId = $schoolYearId;
            } elseif ($activeYearId !== null && $activeYearId > 0) {
                $assignmentYearId = $activeYearId;
            }

            $assignments = [];
            if ($assignmentYearId !== null) {
                $majorFilter = $majorId !== null && $majorId > 0 ? $majorId : null;
                $assignments = SubjectTeacher::bySchoolYearForClass(
                    $assignmentYearId,
                    $majorFilter,
                    $classId,
                );

                if (empty($assignments)) {
                    $assignments = SubjectTeacher::bySchoolYearForClass($assignmentYearId, $majorFilter);
                }
            }

            $assignmentIds = [];
            foreach ($assignments as $assignment) {
                $assignmentId = (int) ($assignment['id'] ?? 0);
                if ($assignmentId > 0) {
                    $assignmentIds[] = $assignmentId;
                }
            }
            $assignmentIds = array_values(array_unique($assignmentIds));

            if (!empty($assignmentIds)) {
                $assignmentCount = count($assignmentIds);
                $categories['knowledge']['total'] += $studentCount * $assignmentCount;

                $knowledgeMap = StudentKnowledgeAssessment::mapByClass($classId, $assignmentIds);

                foreach ($studentIds as $studentId) {
                    foreach ($assignmentIds as $assignmentId) {
                        $record = $knowledgeMap[$studentId][$assignmentId] ?? null;
                        if ($record === null) {
                            continue;
                        }

                        $finalScore = $record['nilai_akhir'] ?? null;
                        if ($finalScore !== null && $finalScore !== '') {
                            $categories['knowledge']['completed']++;
                        }
                    }
                }
            }

            foreach (array_keys(Attitude::TYPES) as $type) {
                $scores = AttitudeScore::byClassAndType($classId, $type);

                if (empty($scores)) {
                    continue;
                }

                foreach ($studentIds as $studentId) {
                    if (isset($scores[$studentId])) {
                        $categories['attitudes']['completed']++;
                    }
                }
            }

            $placements = [];
            if ($prakerinRequired) {
                $placements = StudentPrakerinPlacement::byClass($classId, $schoolYearId);
                foreach ($studentIds as $studentId) {
                    $placement = $placements[$studentId]['tempat_prakerin_id'] ?? null;
                    if ($placement !== null && (int) $placement > 0) {
                        $categories['prakerin']['completed']++;
                    }
                }
            }

            $achievementRows = StudentAchievement::byClass($classId, $schoolYearId);
            if (!empty($achievementRows)) {
                $achievementStudentIds = [];
                foreach ($achievementRows as $row) {
                    $studentId = (int) ($row['siswa_id'] ?? 0);
                    if ($studentId > 0 && isset($studentIdSet[$studentId])) {
                        $achievementStudentIds[$studentId] = true;
                    }
                }

                $categories['achievements']['completed'] += count($achievementStudentIds);
            }

            $extracurricularMap = StudentExtracurricular::byClass($classId, $schoolYearId);
            foreach ($studentIds as $studentId) {
                $activities = $extracurricularMap[$studentId] ?? [];
                if (!empty($activities)) {
                    $categories['extracurriculars']['completed']++;
                }
            }

            $notes = HomeroomNote::byClass($classId, $schoolYearId);
            foreach ($studentIds as $studentId) {
                $note = $notes[$studentId] ?? null;
                if (is_string($note) && trim($note) !== '') {
                    $categories['notes']['completed']++;
                }
            }
        }

        $percentageSum = 0.0;
        $percentageCount = 0;

        foreach ($categories as &$category) {
            $total = (int) ($category['total'] ?? 0);
            $completed = (int) ($category['completed'] ?? 0);

            if ($total < 0) {
                $total = 0;
            }

            if ($completed < 0) {
                $completed = 0;
            }

            if ($completed > $total) {
                $completed = $total;
            }

            $category['total'] = $total;
            $category['completed'] = $completed;
            $category['pending'] = $total > $completed ? $total - $completed : 0;

            if ($total > 0) {
                $percentage = ($completed / $total) * 100;
                $category['percentage'] = round($percentage, 2);
                $percentageSum += $category['percentage'];
                $percentageCount++;
            } else {
                $category['percentage'] = null;
            }
        }
        unset($category);

        $overallPercentage = $percentageCount > 0
            ? round($percentageSum / $percentageCount, 2)
            : null;

        return [
            'activeYear' => $activeYear,
            'studentCount' => $studentTotal,
            'classesCount' => count($classes),
            'categories' => array_values($categories),
            'overall' => [
                'percentage' => $overallPercentage,
                'count' => $percentageCount,
                'totalCategories' => count($categories),
            ],
        ];
    }
}

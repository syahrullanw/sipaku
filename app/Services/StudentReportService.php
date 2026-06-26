<?php

namespace App\Services;

use App\Models\AttitudeScore;
use App\Models\Classroom;
use App\Models\HomeroomNote;
use App\Models\PrakerinAssessment;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Models\StudentAttendance;
use App\Models\StudentExtracurricular;
use App\Models\StudentPlacementHistory;
use App\Models\StudentPrakerinPlacement;
use App\Services\AttitudeFormatter;

class StudentReportService
{
    /**
     * @return array<string, mixed>
     */
    public static function build(int $studentId, ?int $schoolYearId = null): array
    {
        $default = [
            'student' => null,
            'class' => null,
            'school_year' => null,
            'subjects' => [],
            'summary' => null,
            'curriculum' => null,
            'attitudes' => [
                'spiritual' => null,
                'social' => null,
            ],
            'attendance' => null,
            'achievements' => [],
            'homeroom_note' => null,
            'extracurriculars' => [],
            'prakerin' => null,
        ];

        if ($studentId <= 0) {
            return $default;
        }

        $student = Student::findWithRelations($studentId);

        if ($student === null) {
            return $default;
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        $studentYearId = isset($student['tahun_ajaran_id']) ? (int) $student['tahun_ajaran_id'] : 0;

        $targetYearId = $schoolYearId !== null && $schoolYearId > 0
            ? $schoolYearId
            : ($studentYearId > 0 ? $studentYearId : $activeYearId);

        $placement = $targetYearId > 0
            ? StudentPlacementHistory::forStudentYear($studentId, $targetYearId)
            : null;

        $studentClassId = $placement !== null && !empty($placement['class_id'])
            ? (int) $placement['class_id']
            : (isset($student['kelas_id']) ? (int) $student['kelas_id'] : 0);

        $scoreBundle = StudentScoreSummary::forStudent($studentId, $targetYearId);
        $subjects = isset($scoreBundle['subjects']) && is_array($scoreBundle['subjects'])
            ? $scoreBundle['subjects']
            : [];
        $summary = isset($scoreBundle['summary']) && is_array($scoreBundle['summary'])
            ? $scoreBundle['summary']
            : null;

        $bundleSchoolYear = isset($scoreBundle['school_year']) && is_array($scoreBundle['school_year'])
            ? $scoreBundle['school_year']
            : null;

        if ($bundleSchoolYear !== null) {
            $targetYearId = (int) ($bundleSchoolYear['id'] ?? $targetYearId);
        }

        $schoolYear = $targetYearId > 0 ? SchoolYear::find($targetYearId) : null;

        $class = null;
        $curriculum = null;
        if ($studentClassId > 0) {
            $class = Classroom::findWithRelations($studentClassId);
            $curriculum = $class['kurikulum'] ?? null;
        }

        $attitudes = [
            'spiritual' => self::buildAttitudeNote($studentClassId, $targetYearId, $studentId, 'spiritual'),
            'social' => self::buildAttitudeNote($studentClassId, $targetYearId, $studentId, 'sosial'),
        ];

        $attendance = null;
        if ($studentClassId > 0) {
            $attendanceRecords = StudentAttendance::byClass($studentClassId, $targetYearId > 0 ? $targetYearId : null);
            $attendance = $attendanceRecords[$studentId] ?? [
                'sakit' => 0,
                'izin' => 0,
                'bolos' => 0,
                'alpa' => 0,
            ];
        }

        $achievements = [];
        if ($studentClassId > 0) {
            $achievementRecords = StudentAchievement::byClass($studentClassId, $targetYearId > 0 ? $targetYearId : null);
            foreach ($achievementRecords as $achievement) {
                if ((int) ($achievement['siswa_id'] ?? 0) === $studentId) {
                    $achievements[] = $achievement;
                }
            }
        }

        $homeroomNote = null;
        if ($studentClassId > 0) {
            $notes = HomeroomNote::byClass($studentClassId, $targetYearId > 0 ? $targetYearId : null);
            $homeroomNote = isset($notes[$studentId]) ? (string) $notes[$studentId] : null;
            if ($homeroomNote !== null) {
                $homeroomNote = trim($homeroomNote);
            }
        }

        $extracurriculars = [];
        if ($studentClassId > 0 && $targetYearId > 0) {
            $extracurricularDetails = StudentExtracurricular::detailedByClass($studentClassId, $targetYearId);
            $extracurriculars = array_values($extracurricularDetails[$studentId] ?? []);
        }

        $prakerin = null;
        if ($studentClassId > 0 && $targetYearId > 0) {
            $prakerinPlacements = StudentPrakerinPlacement::byClass($studentClassId, $targetYearId);
            $placement = $prakerinPlacements[$studentId] ?? null;

            $prakerinAssessments = PrakerinAssessment::byStudents([$studentId], $targetYearId);
            $assessment = $prakerinAssessments[$studentId] ?? null;

            if ($placement !== null || $assessment !== null) {
                $prakerin = [
                    'place_id' => $placement['tempat_prakerin_id'] ?? ($assessment['tempat_prakerin_id'] ?? null),
                    'place_name' => $placement['tempat_nama'] ?? null,
                    'start_date' => $placement['tanggal_mulai'] ?? null,
                    'end_date' => $placement['tanggal_selesai'] ?? null,
                    'scores' => [
                        'activity' => $assessment['nilai_keaktifan'] ?? null,
                        'journal' => $assessment['nilai_jurnal'] ?? null,
                        'report' => $assessment['nilai_laporan'] ?? null,
                        'final' => $assessment['nilai_akhir'] ?? null,
                    ],
                    'predicate' => $assessment['predikat'] ?? null,
                ];
            }
        }

        return [
            'student' => $student,
            'class' => $class,
            'school_year' => $bundleSchoolYear ?? $schoolYear,
            'curriculum' => $curriculum ?? ($scoreBundle['curriculum'] ?? null),
            'subjects' => $subjects,
            'summary' => $summary,
            'attitudes' => $attitudes,
            'attendance' => $attendance,
            'achievements' => $achievements,
            'homeroom_note' => $homeroomNote,
            'extracurriculars' => $extracurriculars,
            'prakerin' => $prakerin,
        ];
    }

    private static function buildAttitudeNote(int $classId, int $schoolYearId, int $studentId, string $type): ?string
    {
        if ($classId <= 0) {
            return null;
        }

        $records = AttitudeScore::byClassAndType($classId, $type);
        $record = $records[$studentId] ?? null;

        if ($record === null) {
            return null;
        }

        if ($schoolYearId > 0 && (int) ($record['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            return null;
        }

        $note = trim((string) ($record['catatan'] ?? ''));
        if ($note !== '') {
            return $note;
        }

        $alwaysEntries = array_values(array_filter([
            AttitudeFormatter::formatEntry(
                $record['data_sikap_selalu_1_nama'] ?? null,
                $record['data_sikap_selalu_1_deskripsi'] ?? null,
            ),
            AttitudeFormatter::formatEntry(
                $record['data_sikap_selalu_2_nama'] ?? null,
                $record['data_sikap_selalu_2_deskripsi'] ?? null,
            ),
        ], static fn (?string $value) => $value !== null && trim($value) !== ''));

        $improvingEntry = AttitudeFormatter::formatEntry(
            $record['data_sikap_meningkat_nama'] ?? null,
            $record['data_sikap_meningkat_deskripsi'] ?? null,
        );

        $parts = [];

        if (!empty($alwaysEntries)) {
            $parts[] = 'Selalu menunjukkan: ' . implode(', ', $alwaysEntries) . '.';
        }

        if ($improvingEntry !== null && trim($improvingEntry) !== '') {
            $parts[] = 'Perlu ditingkatkan: ' . trim($improvingEntry) . '.';
        }

        if (!empty($parts)) {
            return implode(' ', $parts);
        }

        return null;
    }
}

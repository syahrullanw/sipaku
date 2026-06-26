<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\DigitalDocumentSignature;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentGraduationStatus;
use App\Models\StudentKnowledgeAssessment;
use App\Models\StudentKurmerSubjectSummary;
use App\Models\StudentSkillAssessment;
use App\Models\SubjectAssessmentSetting;
use App\Models\SubjectTeacher;
use App\Services\StudentScoreSummary;

class GraduationCertificateService
{
    public const DOCUMENT_TYPE = 'graduation_certificate';

    /**
     * Build a unique key per siswa & tahun ajaran.
     */
    public static function documentKey(int $studentId, int $schoolYearId): string
    {
        return sprintf('skl:%d:%d', $studentId, $schoolYearId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function availableSubjectsForClass(int $classId, int $schoolYearId): array
    {
        if ($classId <= 0 || $schoolYearId <= 0) {
            return [];
        }

        $class = Classroom::findWithRelations($classId);
        $majorId = isset($class['jurusan_id']) ? (int) $class['jurusan_id'] : null;
        $assignments = SubjectTeacher::bySchoolYearForClass($schoolYearId, $majorId, $classId);
        $subjects = [];

        foreach ($assignments as $assignment) {
            $assignmentId = (int) ($assignment['id'] ?? 0);
            if ($assignmentId <= 0) {
                continue;
            }

            if (!isset($subjects[$assignmentId])) {
                $subjects[$assignmentId] = [
                    'assignment_id' => $assignmentId,
                    'code' => (string) ($assignment['mata_pelajaran_kode'] ?? ''),
                    'name' => (string) ($assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran'),
                    'group' => (string) ($assignment['mata_pelajaran_jenis'] ?? ''),
                ];
            }
        }

        $subjectList = array_values($subjects);

        usort($subjectList, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $subjectList;
    }

    /**
     * @param array<int> $allowedAssignmentIds
     * @param array<int, array<string, mixed>> $subjectEntries
     *
     * @return array<int, array<string, mixed>>
     */
    public static function filterSubjects(array $subjectEntries, array $allowedAssignmentIds): array
    {
        $allowedMap = [];
        foreach ($allowedAssignmentIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $allowedMap[$id] = true;
            }
        }

        $subjects = [];

        foreach ($subjectEntries as $subject) {
            $assignmentId = (int) ($subject['assignment_id'] ?? 0);
            $finalScore = $subject['final_score'] ?? null;

            if ($assignmentId <= 0 || $finalScore === null) {
                continue;
            }

            if (!empty($allowedMap) && !isset($allowedMap[$assignmentId])) {
                continue;
            }

            $subjects[] = [
                'assignment_id' => $assignmentId,
                'code' => (string) ($subject['subject_code'] ?? ''),
                'name' => (string) ($subject['subject_name'] ?? 'Mata Pelajaran'),
                'group' => (string) ($subject['subject_group'] ?? ''),
                'knowledge_score' => isset($subject['knowledge_score']) ? (float) $subject['knowledge_score'] : null,
                'knowledge_predicate' => $subject['knowledge_predicate'] ?? null,
                'skill_score' => isset($subject['skill_score']) ? (float) $subject['skill_score'] : null,
                'skill_predicate' => $subject['skill_predicate'] ?? null,
                'final_score' => round((float) $finalScore, 2),
            ];
        }

        return $subjects;
    }

    /**
     * @param array<int, array<string, mixed>> $subjects
     */
    public static function averageScore(array $subjects): ?float
    {
        if (empty($subjects)) {
            return null;
        }

        $sum = 0.0;
        $count = 0;

        foreach ($subjects as $subject) {
            if (!isset($subject['final_score'])) {
                continue;
            }

            $sum += (float) $subject['final_score'];
            $count++;
        }

        if ($count === 0) {
            return null;
        }

        return round($sum / $count, 2);
    }

    public static function documentTitle(string $studentName, ?string $schoolYearName = null): string
    {
        $suffix = $schoolYearName !== null && $schoolYearName !== ''
            ? sprintf(' (%s)', $schoolYearName)
            : '';

        return sprintf('Surat Keterangan Lulus%s - %s', $suffix, $studentName);
    }

    /**
     * @return array<string, mixed>
     */
    public static function evaluateStudentEligibility(int $studentId, ?int $schoolYearId = null, ?array $signatureRecord = null): array
    {
        $student = Student::findWithRelations($studentId);

        if ($student === null) {
            return self::emptyEligibility('Data siswa tidak ditemukan.');
        }

        $activeYear = SchoolYear::active();
        $studentYearId = (int) ($student['tahun_ajaran_id'] ?? 0);
        $activeYearId = (int) ($activeYear['id'] ?? 0);
        $targetYearId = $schoolYearId !== null && $schoolYearId > 0
            ? $schoolYearId
            : ($activeYearId > 0 ? $activeYearId : $studentYearId);

        if ($targetYearId <= 0) {
            $targetYearId = $studentYearId;
        }

        $schoolYear = $targetYearId > 0 ? SchoolYear::find($targetYearId) : null;
        $classId = (int) ($student['kelas_id'] ?? 0);
        $class = $classId > 0 ? Classroom::findWithRelations($classId) : null;
        $classLevel = (int) ($class['tingkat'] ?? ($student['kelas_tingkat'] ?? 0));
        $isGraduatingClass = $classLevel === 12;

        $scoreEvaluation = self::evaluateScoreCriteria($student, $class, $targetYearId);

        $graduationStatus = null;
        if ($classId > 0 && $targetYearId > 0) {
            $graduationStatuses = StudentGraduationStatus::byClass($classId, $targetYearId);
            $graduationStatus = $graduationStatuses[$studentId] ?? null;
        }

        $graduationPassed = ($graduationStatus['status'] ?? null) === 'lulus';

        if ($signatureRecord === null && $targetYearId > 0) {
            $signatureRecord = DigitalDocumentSignature::findByDocument(
                $targetYearId,
                self::DOCUMENT_TYPE,
                self::documentKey($studentId, $targetYearId)
            );
        }

        $payload = [];
        if ($signatureRecord !== null && isset($signatureRecord['payload']) && is_string($signatureRecord['payload'])) {
            $decoded = json_decode($signatureRecord['payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $signatureRequested = $signatureRecord !== null;
        $signatureApproved = $signatureRequested
            && ($signatureRecord['status'] ?? null) === 'approved'
            && trim((string) ($signatureRecord['signature_token'] ?? '')) !== '';

        $criteria = [
            'scores' => self::criterion(
                (bool) ($scoreEvaluation['passed'] ?? false),
                'Nilai semua mapel tuntas',
                (string) ($scoreEvaluation['message'] ?? 'Nilai belum dapat diperiksa.'),
                $scoreEvaluation['issues'] ?? []
            ),
            'graduation_status' => self::criterion(
                $graduationPassed,
                'Ditetapkan lulus wali kelas',
                $graduationPassed
                    ? 'Wali kelas sudah menetapkan status lulus.'
                    : self::graduationStatusMessage($graduationStatus),
                $graduationStatus !== null && !empty($graduationStatus['catatan']) ? [(string) $graduationStatus['catatan']] : []
            ),
            'signature_requested' => self::criterion(
                $signatureRequested,
                'Diajukan TTD digital',
                $signatureRequested
                    ? 'Dokumen SKL sudah diajukan untuk pengesahan digital.'
                    : 'Dokumen SKL belum diajukan untuk TTD digital.'
            ),
            'signature_approved' => self::criterion(
                $signatureApproved,
                'Disetujui kepala sekolah',
                $signatureApproved
                    ? 'TTD digital sudah disetujui kepala sekolah.'
                    : self::signatureStatusMessage($signatureRecord)
            ),
        ];

        $contextIssues = [];
        if (!$isGraduatingClass) {
            $contextIssues[] = 'SKL hanya tersedia untuk siswa kelas tingkat 12.';
        }

        if ($class === null) {
            $contextIssues[] = 'Data kelas siswa belum valid.';
        }

        if ($schoolYear === null) {
            $contextIssues[] = 'Tahun ajaran siswa belum valid.';
        }

        $passed = $isGraduatingClass
            && $class !== null
            && $schoolYear !== null
            && self::criteriaPassed($criteria);

        $scoreRequestPassed = (bool) ($scoreEvaluation['request_passed'] ?? ($scoreEvaluation['passed'] ?? false));
        $canPrint = $isGraduatingClass
            && $class !== null
            && $schoolYear !== null
            && $scoreRequestPassed
            && (bool) ($criteria['graduation_status']['passed'] ?? false)
            && (bool) ($criteria['signature_approved']['passed'] ?? false);
        $canRequestSignature = $isGraduatingClass
            && $class !== null
            && $schoolYear !== null
            && $scoreRequestPassed
            && (bool) ($criteria['graduation_status']['passed'] ?? false);

        $canApproveSignature = $canRequestSignature && (bool) ($criteria['signature_requested']['passed'] ?? false);

        return [
            'student' => $student,
            'class' => $class,
            'school_year' => $schoolYear,
            'school_year_id' => $targetYearId,
            'class_level' => $classLevel,
            'is_graduating_class' => $isGraduatingClass,
            'graduation_status' => $graduationStatus,
            'signature_record' => $signatureRecord,
            'payload' => $payload,
            'criteria' => $criteria,
            'score_evaluation' => $scoreEvaluation,
            'subjects' => $scoreEvaluation['subjects'] ?? [],
            'average' => $scoreEvaluation['average'] ?? null,
            'context_issues' => $contextIssues,
            'eligible' => $canPrint,
            'can_print' => $canPrint,
            'can_request_signature' => $canRequestSignature,
            'can_approve_signature' => $canApproveSignature,
        ];
    }

    /**
     * @param array<string, mixed> $evaluation
     *
     * @return array<string, mixed>
     */
    public static function buildPayloadFromEvaluation(array $evaluation): array
    {
        $student = is_array($evaluation['student'] ?? null) ? $evaluation['student'] : [];
        $class = is_array($evaluation['class'] ?? null) ? $evaluation['class'] : [];
        $schoolYear = is_array($evaluation['school_year'] ?? null) ? $evaluation['school_year'] : [];
        $subjects = is_array($evaluation['subjects'] ?? null) ? $evaluation['subjects'] : [];
        $fatherName = self::preferredParentName($student['ayah_nama'] ?? null);
        $motherName = self::preferredParentName($student['ibu_nama'] ?? null);
        $parentName = $fatherName !== null ? $fatherName : ($motherName !== null ? $motherName : '');

        return [
            'document_type' => self::DOCUMENT_TYPE,
            'school_year_id' => (int) ($evaluation['school_year_id'] ?? ($schoolYear['id'] ?? 0)),
            'school_year_name' => (string) ($schoolYear['nama'] ?? ''),
            'student' => [
                'id' => (int) ($student['id'] ?? 0),
                'name' => (string) ($student['nama'] ?? ''),
                'nisn' => (string) ($student['nisn'] ?? ''),
                'nipd' => (string) ($student['nipd'] ?? ''),
                'birth_place' => (string) ($student['tempat_lahir'] ?? ''),
                'birth_date' => (string) ($student['tanggal_lahir'] ?? ''),
                'father_name' => (string) ($student['ayah_nama'] ?? ''),
                'mother_name' => (string) ($student['ibu_nama'] ?? ''),
                'parent_name' => $parentName,
            ],
            'class' => [
                'id' => (int) ($class['id'] ?? 0),
                'name' => (string) ($class['nama'] ?? ''),
                'level' => (int) ($class['tingkat'] ?? 0),
                'major' => $class['jurusan_nama'] ?? null,
            ],
            'subjects' => self::certificateSubjects($subjects),
            'average' => $evaluation['average'] ?? null,
            'graduation_status' => $evaluation['graduation_status'] ?? null,
            'eligibility' => [
                'scores' => (bool) ($evaluation['criteria']['scores']['passed'] ?? false),
                'graduation_status' => (bool) ($evaluation['criteria']['graduation_status']['passed'] ?? false),
                'signature_requested' => (bool) ($evaluation['criteria']['signature_requested']['passed'] ?? false),
                'signature_approved' => (bool) ($evaluation['criteria']['signature_approved']['passed'] ?? false),
            ],
            'requested_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Build an up-to-date SKL payload for an existing digital signature record.
     *
     * The signature token proves approval of the document record, but the score
     * summary should follow the current class subject and grade data so student
     * pages and verification links do not show an obsolete snapshot.
     *
     * @param array<string, mixed>|null $record
     *
     * @return array<string, mixed>
     */
    public static function livePayloadForRecord(?array $record): array
    {
        if (
            $record === null
            || (string) ($record['document_type'] ?? '') !== self::DOCUMENT_TYPE
            || (int) ($record['student_id'] ?? 0) <= 0
            || (int) ($record['tahun_ajaran_id'] ?? 0) <= 0
        ) {
            return [];
        }

        $evaluation = self::evaluateStudentEligibility(
            (int) $record['student_id'],
            (int) $record['tahun_ajaran_id'],
            $record
        );

        if (!is_array($evaluation['student'] ?? null)) {
            return [];
        }

        $payload = self::buildPayloadFromEvaluation($evaluation);
        $existingPayload = [];

        if (isset($record['payload']) && is_string($record['payload'])) {
            $decoded = json_decode($record['payload'], true);
            if (is_array($decoded)) {
                $existingPayload = $decoded;
            }
        }

        foreach (['requested_at', 'attendance', 'achievements', 'homeroom_note', 'semester'] as $key) {
            if (array_key_exists($key, $existingPayload) && !array_key_exists($key, $payload)) {
                $payload[$key] = $existingPayload[$key];
            }
        }

        return $payload;
    }

    private static function preferredParentName(mixed $value): ?string
    {
        $name = trim((string) $value);
        if ($name === '') {
            return null;
        }

        if (strcasecmp($name, 'belum') === 0) {
            return null;
        }

        return $name;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public static function criteriaPassed(array $criteria): bool
    {
        foreach ($criteria as $criterion) {
            if (!is_array($criterion) || (bool) ($criterion['passed'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyEligibility(string $message): array
    {
        $criteria = [
            'scores' => self::criterion(false, 'Nilai semua mapel tuntas', $message),
            'graduation_status' => self::criterion(false, 'Ditetapkan lulus wali kelas', $message),
            'signature_requested' => self::criterion(false, 'Diajukan TTD digital', $message),
            'signature_approved' => self::criterion(false, 'Disetujui kepala sekolah', $message),
        ];

        return [
            'student' => null,
            'class' => null,
            'school_year' => null,
            'school_year_id' => 0,
            'class_level' => 0,
            'is_graduating_class' => false,
            'graduation_status' => null,
            'signature_record' => null,
            'payload' => [],
            'criteria' => $criteria,
            'score_evaluation' => [],
            'subjects' => [],
            'average' => null,
            'context_issues' => [$message],
            'eligible' => false,
            'can_print' => false,
            'can_request_signature' => false,
            'can_approve_signature' => false,
        ];
    }

    /**
     * @param array<string, mixed> $student
     * @param array<string, mixed>|null $class
     *
     * @return array<string, mixed>
     */
    private static function evaluateScoreCriteria(array $student, ?array $class, int $schoolYearId): array
    {
        if ($class === null || $schoolYearId <= 0) {
            return [
                'passed' => false,
                'message' => 'Data kelas atau tahun ajaran belum valid.',
                'issues' => ['Data kelas atau tahun ajaran belum valid.'],
                'subjects' => [],
                'average' => null,
            ];
        }

        $studentId = (int) ($student['id'] ?? 0);
        $classId = (int) ($class['id'] ?? 0);
        $majorId = isset($class['jurusan_id']) ? (int) $class['jurusan_id'] : null;
        $isKurmer = ($class['kurikulum'] ?? 'k13') === 'kurmer';

        if ($studentId <= 0 || $classId <= 0) {
            return [
                'passed' => false,
                'message' => 'Data siswa atau kelas belum valid.',
                'issues' => ['Data siswa atau kelas belum valid.'],
                'subjects' => [],
                'average' => null,
            ];
        }

        $assignments = SubjectTeacher::bySchoolYearForClass($schoolYearId, $majorId, $classId);

        if (empty($assignments)) {
            return [
                'passed' => false,
                'message' => 'Belum ada mata pelajaran yang terhubung ke kelas ini.',
                'issues' => ['Belum ada mata pelajaran yang terhubung ke kelas ini.'],
                'subjects' => [],
                'average' => null,
            ];
        }

        $assignmentIds = array_values(array_filter(array_map(
            static fn (array $assignment): int => (int) ($assignment['id'] ?? 0),
            $assignments
        ), static fn (int $id): bool => $id > 0));

        $settingsMap = SubjectAssessmentSetting::mapByAssignments($assignmentIds);
        $knowledgeScores = $isKurmer ? [] : StudentKnowledgeAssessment::byAssignmentsForStudent($assignmentIds, $studentId);
        $skillScores = $isKurmer ? [] : StudentSkillAssessment::byAssignmentsForStudent($assignmentIds, $studentId);
        $kurmerSummaries = $isKurmer ? StudentKurmerSubjectSummary::byAssignmentsForStudent($assignmentIds, $studentId) : [];

        $subjects = [];
        $issues = [];
        $sum = 0.0;
        $count = 0;

        foreach ($assignments as $assignment) {
            $assignmentId = (int) ($assignment['id'] ?? 0);
            if ($assignmentId <= 0) {
                continue;
            }

            $setting = $settingsMap[$assignmentId] ?? [];
            $kkmEnabled = (int) ($setting['enable_kkm'] ?? 0) === 1;
            $kkmValue = $kkmEnabled && isset($setting['nilai_kkm']) && $setting['nilai_kkm'] !== null
                ? (float) $setting['nilai_kkm']
                : null;
            $skillEnabled = array_key_exists('enable_keterampilan', $setting)
                ? (int) ($setting['enable_keterampilan'] ?? 1) === 1
                : true;

            $subjectIssues = [];
            $knowledgeScore = null;
            $skillScore = null;
            $finalScore = null;
            $kurmerCapaian = null;

            if ($isKurmer) {
                $summary = $kurmerSummaries[$assignmentId] ?? null;
                $kurmerCapaian = is_array($summary) ? trim((string) ($summary['capaian_akhir_enum'] ?? '')) : '';
                $scoreValue = is_array($summary) ? ($summary['nilai_opsional'] ?? null) : null;

                if ($kurmerCapaian === '') {
                    $subjectIssues[] = 'Capaian akhir belum diisi.';
                }

                if ($scoreValue === null || $scoreValue === '' || !is_numeric($scoreValue)) {
                    $subjectIssues[] = 'Nilai akhir belum diisi.';
                } else {
                    $finalScore = round((float) $scoreValue, 2);
                }

                if ($kkmEnabled && $kkmValue !== null && $finalScore !== null && $finalScore < $kkmValue) {
                    $subjectIssues[] = 'Nilai akhir di bawah KKM ' . self::formatScore($kkmValue) . '.';
                }
            } else {
                $knowledge = $knowledgeScores[$assignmentId] ?? null;
                $skill = $skillScores[$assignmentId] ?? null;

                if (is_array($knowledge) && $knowledge['nilai_akhir'] !== null && $knowledge['nilai_akhir'] !== '') {
                    $knowledgeScore = round((float) $knowledge['nilai_akhir'], 2);
                } else {
                    $subjectIssues[] = 'Nilai pengetahuan belum diisi.';
                }

                if ($skillEnabled) {
                    if (is_array($skill) && $skill['nilai_akhir'] !== null && $skill['nilai_akhir'] !== '') {
                        $skillScore = round((float) $skill['nilai_akhir'], 2);
                    } else {
                        $subjectIssues[] = 'Nilai keterampilan belum diisi.';
                    }
                }

                $scoreComponents = [];
                if ($knowledgeScore !== null) {
                    $scoreComponents[] = ['label' => 'pengetahuan', 'score' => $knowledgeScore];
                }
                if ($skillEnabled && $skillScore !== null) {
                    $scoreComponents[] = ['label' => 'keterampilan', 'score' => $skillScore];
                }

                if (!empty($scoreComponents) && count($scoreComponents) === ($skillEnabled ? 2 : 1)) {
                    $finalScore = round(array_sum(array_column($scoreComponents, 'score')) / count($scoreComponents), 2);
                }

                if ($finalScore === null && $knowledgeScore !== null) {
                    $finalScore = $knowledgeScore;
                }

                if ($kkmEnabled && $kkmValue !== null) {
                    foreach ($scoreComponents as $component) {
                        if ((float) $component['score'] < $kkmValue) {
                            $subjectIssues[] = sprintf(
                                'Nilai %s di bawah KKM %s.',
                                $component['label'],
                                self::formatScore($kkmValue)
                            );
                        }
                    }
                }
            }

            if ($finalScore !== null) {
                $sum += $finalScore;
                $count++;
            }

            $subjectName = (string) ($assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran');
            if (!empty($subjectIssues)) {
                $issues[] = $subjectName . ': ' . implode(' ', array_unique($subjectIssues));
            }

            $subjects[] = [
                'assignment_id' => $assignmentId,
                'code' => (string) ($assignment['mata_pelajaran_kode'] ?? ''),
                'name' => $subjectName,
                'group' => (string) ($assignment['mata_pelajaran_jenis'] ?? ''),
                'teacher' => (string) ($assignment['guru_nama'] ?? ''),
                'curriculum' => $isKurmer ? 'kurmer' : 'k13',
                'kkm_enabled' => $kkmEnabled,
                'kkm_value' => $kkmValue,
                'skill_enabled' => $skillEnabled,
                'knowledge_score' => $knowledgeScore,
                'skill_score' => $skillScore,
                'kurmer_capaian' => $kurmerCapaian,
                'final_score' => $finalScore,
                'passed' => empty($subjectIssues),
                'issues' => array_values(array_unique($subjectIssues)),
            ];
        }

        $passed = !empty($subjects) && empty($issues);
        $requestPassed = !empty($subjects);
        $average = $count > 0 ? round($sum / $count, 2) : null;

        if (!$isKurmer) {
            foreach ($subjects as $subject) {
                if (($subject['knowledge_score'] ?? null) === null) {
                    $requestPassed = false;
                    break;
                }
            }
        } else {
            $requestPassed = $passed;
        }

        if ($passed) {
            $message = sprintf(
                'Semua %d mata pelajaran sudah lengkap dan tidak ada nilai di bawah KKM.',
                count($subjects)
            );
        } else {
            $message = sprintf(
                '%d mata pelajaran masih perlu diperbaiki sebelum SKL dapat dicetak.',
                count($issues)
            );
        }

        return [
            'passed' => $passed,
            'request_passed' => $requestPassed,
            'message' => $message,
            'issues' => $issues,
            'subjects' => $subjects,
            'average' => $average,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $subjects
     *
     * @return array<int, array<string, mixed>>
     */
    private static function certificateSubjects(array $subjects): array
    {
        $certificateSubjects = [];

        foreach ($subjects as $subject) {
            if (!is_array($subject) || ($subject['final_score'] ?? null) === null) {
                continue;
            }

            $certificateSubjects[] = [
                'assignment_id' => (int) ($subject['assignment_id'] ?? 0),
                'code' => (string) ($subject['code'] ?? ''),
                'name' => (string) ($subject['name'] ?? 'Mata Pelajaran'),
                'group' => (string) ($subject['group'] ?? ''),
                'knowledge_score' => $subject['knowledge_score'] ?? null,
                'skill_score' => $subject['skill_score'] ?? null,
                'kurmer_capaian' => $subject['kurmer_capaian'] ?? null,
                'final_score' => round((float) $subject['final_score'], 2),
                'kkm_enabled' => (bool) ($subject['kkm_enabled'] ?? false),
                'kkm_value' => $subject['kkm_value'] ?? null,
            ];
        }

        return $certificateSubjects;
    }

    /**
     * @param array<int, string> $details
     *
     * @return array<string, mixed>
     */
    private static function criterion(bool $passed, string $title, string $message, array $details = []): array
    {
        return [
            'passed' => $passed,
            'title' => $title,
            'message' => $message,
            'details' => array_values(array_filter(array_map(
                static fn ($detail): string => trim((string) $detail),
                $details
            ), static fn (string $detail): bool => $detail !== '')),
        ];
    }

    /**
     * @param array<string, mixed>|null $graduationStatus
     */
    private static function graduationStatusMessage(?array $graduationStatus): string
    {
        if ($graduationStatus === null) {
            return 'Wali kelas belum menetapkan status kelulusan.';
        }

        if (($graduationStatus['status'] ?? null) === 'tidak_lulus') {
            return 'Wali kelas menetapkan status tidak lulus.';
        }

        return 'Status kelulusan belum valid.';
    }

    /**
     * @param array<string, mixed>|null $signatureRecord
     */
    private static function signatureStatusMessage(?array $signatureRecord): string
    {
        if ($signatureRecord === null) {
            return 'TTD digital belum diajukan.';
        }

        $status = (string) ($signatureRecord['status'] ?? 'pending');

        if ($status === 'pending') {
            return 'TTD digital sudah diajukan dan menunggu persetujuan kepala sekolah.';
        }

        if ($status === 'revoked') {
            return 'Persetujuan TTD digital dicabut dan perlu diajukan ulang.';
        }

        return 'TTD digital belum memiliki token persetujuan yang valid.';
    }

    private static function formatScore(float $value): string
    {
        $rounded = round($value, 2);
        if (abs($rounded - round($rounded)) < 0.01) {
            return number_format($rounded, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($rounded, 2, ',', '.'), '0'), ',');
    }
}

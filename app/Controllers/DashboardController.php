<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\LessonSchedule;
use App\Models\SubjectTeacher;
use App\Models\TeacherAcademicPosition;
use App\Models\PrakerinPlace;
use App\Models\Extracurricular;
use App\Models\DigitalDocumentSignature;
use App\Models\StudentAttendance;
use App\Models\StudentAttendanceSessionDetail;
use App\Models\StudentKnowledgeAssessment;
use App\Models\StudentSkillAssessment;
use App\Models\StudentKurmerSubjectSummary;
use App\Models\SubjectAssessmentSetting;
use App\Models\HomeroomPrakerinConfirmation;
use App\Models\TeacherLoan;
use App\Models\ActivityFund;
use App\Services\HomeroomDashboardProgress;
use App\Services\StudentScoreSummary;
use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Response;

class DashboardController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();
        $isAdmin = is_array($user) && ($user['role'] ?? '') === 'admin';
        $isStudent = is_array($user) && ($user['role'] ?? '') === 'siswa';
        $isTeacher = is_array($user) && ($user['role'] ?? '') === 'guru';
        $isHeadmasterRole = is_array($user) && ($user['role'] ?? '') === 'kepala_sekolah';
        $isBendaharaRole = is_array($user) && ($user['role'] ?? '') === 'bendahara';
        $teacherId = is_array($user) ? (int) ($user['teacher_id'] ?? 0) : 0;

        $metrics = [
            'students' => Student::count(),
            'teachers' => Teacher::count(),
            'classes' => Classroom::count(),
            'years' => SchoolYear::count(),
        ];

        $latestStudents = $isAdmin ? Student::latest(5) : [];
        $activeYears = SchoolYear::allOrdered();
        $activeSchoolYear = SchoolYear::active();
        $activeSchoolYearId = isset($activeSchoolYear['id']) ? (int) $activeSchoolYear['id'] : null;
        $classSummaries = Student::classGenderSummary($activeSchoolYearId);
        $unassignedSummary = Student::unassignedGenderSummary($activeSchoolYearId);
        $homeroomProgress = null;
        $homeroomClasses = [];
        $teacherSchedule = [];
        $academicPositions = [];
        $prakerinSupervisions = [];
        $extracurricularMentorships = [];
        $userDisplayName = 'Pengguna';
        $roleLabels = [];
        $studentSubjects = [];
        $studentScoreSummary = null;
        $studentAttendanceSummary = [
            'hadir' => 0,
            'izin' => 0,
            'sakit' => 0,
            'bolos' => 0,
            'alpa' => 0,
        ];
        $studentWeekRange = null;
        $studentInfo = null;
        $teacherMetrics = null;
        $teacherAssignments = [];
        $pendingActions = [];
        $teacherPendingCounts = [
            'knowledge' => 0,
            'skill' => 0,
            'kurmer' => 0,
        ];
        $classStudentCache = [];
        $getStudentsForClass = static function (int $classId, ?int $schoolYearId) use (&$classStudentCache): array {
            $cacheKey = $classId . ':' . ($schoolYearId ?? '0');
            if (!array_key_exists($cacheKey, $classStudentCache)) {
                $classStudentCache[$cacheKey] = Student::byClass($classId, $schoolYearId);
            }

            return $classStudentCache[$cacheKey];
        };
        $subjectDetails = [];

        if (is_array($user)) {
            $userDisplayName = trim((string) ($user['name'] ?? ''));
            if ($userDisplayName === '') {
                $userDisplayName = trim((string) ($user['username'] ?? ''));
            }
            if ($userDisplayName === '') {
                $userDisplayName = 'Pengguna';
            }
        }

        $roleLabelMap = [
            'admin' => 'Administrator',
            'staff' => 'Staf Akademik',
            'guru' => 'Guru',
            'siswa' => 'Siswa',
            'kepala_sekolah' => 'Kepala Sekolah',
            'bendahara' => 'Bendahara',
        ];

        $studentCurriculum = null;

        if ($isStudent) {
            $studentId = isset($user['student_id']) ? (int) $user['student_id'] : 0;

            if ($studentId > 0) {
                $studentInfo = Student::findWithRelations($studentId);

                if ($studentInfo !== null) {
                    $studentClassId = isset($studentInfo['kelas_id']) ? (int) $studentInfo['kelas_id'] : 0;
                    $studentYearId = isset($studentInfo['tahun_ajaran_id']) ? (int) $studentInfo['tahun_ajaran_id'] : 0;

                    if ($studentClassId > 0) {
                        $targetYearId = $studentYearId > 0
                            ? $studentYearId
                            : ($activeSchoolYearId ?? 0);

                        $scoreBundle = StudentScoreSummary::forStudent(
                            $studentId,
                            $targetYearId > 0 ? $targetYearId : null
                        );

                        $studentCurriculum = isset($scoreBundle['curriculum']) ? (string) $scoreBundle['curriculum'] : null;

                        $subjectDetails = isset($scoreBundle['subjects']) && is_array($scoreBundle['subjects'])
                            ? $scoreBundle['subjects']
                            : [];

                        $studentSubjects = [];
                        foreach ($subjectDetails as $detail) {
                            if (!is_array($detail)) {
                                continue;
                            }
                            $studentSubjects[] = [
                                'name' => (string) ($detail['subject_name'] ?? 'Mata Pelajaran'),
                                'code' => (string) ($detail['subject_code'] ?? ''),
                                'teacher' => (string) ($detail['teacher_name'] ?? ''),
                                'final_score' => $detail['final_score'] ?? null,
                                'knowledge_score' => $detail['knowledge_score'] ?? null,
                                'skill_score' => $detail['skill_score'] ?? null,
                                'knowledge_predicate' => $detail['knowledge_predicate'] ?? null,
                                'skill_predicate' => $detail['skill_predicate'] ?? null,
                                'curriculum' => $detail['curriculum'] ?? null,
                                'kurmer_summary' => $detail['kurmer_summary'] ?? null,
                            ];
                        }

                        $studentScoreSummary = isset($scoreBundle['summary']) && is_array($scoreBundle['summary'])
                            ? $scoreBundle['summary']
                            : null;

                        $weekStart = date('Y-m-d', strtotime('monday this week'));
                        $weekEnd = date('Y-m-d', strtotime('sunday this week'));

                        if ($weekStart !== false && $weekEnd !== false) {
                            $weeklySummary = StudentAttendanceSessionDetail::summaryForStudent($studentId, $weekStart, $weekEnd);

                            foreach ($studentAttendanceSummary as $key => $value) {
                                $studentAttendanceSummary[$key] = (int) ($weeklySummary[$key] ?? 0);
                            }

                            $studentWeekRange = [
                                'start' => $weekStart,
                                'end' => $weekEnd,
                            ];
                        }
                    }
                }
            }
        }

        if ($isTeacher) {
            if ($teacherId > 0) {
                $isHomeroomTeacher = Classroom::teacherHasHomeroom($teacherId);

                if ($isHomeroomTeacher) {
                    $homeroomProgress = HomeroomDashboardProgress::calculateForTeacher($teacherId);
                    $homeroomClasses = Classroom::homeroomClassesForTeacher($teacherId, $activeSchoolYearId ?? null);
                    if (empty($homeroomClasses) && $activeSchoolYearId !== null) {
                        $homeroomClasses = Classroom::homeroomClassesForTeacher($teacherId);
                    }
                }

                $teacherSchedule = LessonSchedule::forTeacher($teacherId, $activeSchoolYearId);
                $academicPositions = TeacherAcademicPosition::forTeacher($teacherId, $activeSchoolYearId);
                if ($activeSchoolYearId !== null && $activeSchoolYearId > 0) {
                    $prakerinSupervisions = PrakerinPlace::supervisedByTeacher($teacherId, $activeSchoolYearId);
                    $extracurricularMentorships = Extracurricular::byMentor($teacherId, $activeSchoolYearId);
                }

                $totalTeachingHours = 0;
                foreach ($teacherSchedule as $scheduleEntry) {
                    if (!is_array($scheduleEntry)) {
                        continue;
                    }

                    $hours = isset($scheduleEntry['jumlah_jam']) ? (int) $scheduleEntry['jumlah_jam'] : 0;
                    if ($hours > 0) {
                        $totalTeachingHours += $hours;
                    }
                }

                $assignmentYearId = $activeSchoolYearId !== null && $activeSchoolYearId > 0 ? $activeSchoolYearId : null;
                $teacherAssignments = SubjectTeacher::byTeacher($teacherId, $assignmentYearId);
                $classMap = [];
                $subjectMap = [];

                foreach ($teacherAssignments as $assignment) {
                    if (!is_array($assignment)) {
                        continue;
                    }

                    $subjectId = isset($assignment['mata_pelajaran_id']) ? (int) $assignment['mata_pelajaran_id'] : 0;
                    if ($subjectId > 0) {
                        $subjectMap[$subjectId] = true;
                    }

                    if (isset($assignment['classes']) && is_array($assignment['classes'])) {
                        foreach ($assignment['classes'] as $classInfo) {
                            if (!is_array($classInfo)) {
                                continue;
                            }
                            $classId = isset($classInfo['id']) ? (int) $classInfo['id'] : 0;
                            if ($classId > 0) {
                                $classMap[$classId] = true;
                            }
                        }
                    }
                }

                $pendingLoans = TeacherLoan::countPendingForTeacher($teacherId, $assignmentYearId);
                $pendingActivities = ActivityFund::countPendingForTeacher($teacherId, $assignmentYearId);

                $teacherMetrics = [
                    'total_hours' => $totalTeachingHours,
                    'class_count' => count($classMap),
                    'subject_count' => count($subjectMap),
                    'pending_submissions' => $pendingLoans + $pendingActivities,
                ];

                if ($isHomeroomTeacher) {
                    $roleLabels[] = 'Wali Kelas';
                }

                $headmasterTeacherId = isset($activeSchoolYear['kepala_sekolah_id'])
                    ? (int) $activeSchoolYear['kepala_sekolah_id']
                    : 0;
                if ($headmasterTeacherId > 0 && $headmasterTeacherId === $teacherId) {
                    $roleLabels[] = $roleLabelMap['kepala_sekolah'];
                }

                if (!empty($teacherAssignments)) {
                    $assignmentIds = array_values(array_filter(array_map(
                        static fn ($assignment) => (int) ($assignment['id'] ?? 0),
                        $teacherAssignments
                    ), static fn (int $id) => $id > 0));

                    $knowledgeCounts = StudentKnowledgeAssessment::countByAssignments($assignmentIds);
                    $skillCounts = StudentSkillAssessment::countByAssignments($assignmentIds);
                    $assignmentSettings = SubjectAssessmentSetting::mapByAssignments($assignmentIds);

                    foreach ($teacherAssignments as $assignment) {
                        if (!is_array($assignment)) {
                            continue;
                        }

                        $assignmentId = (int) ($assignment['id'] ?? 0);
                        if ($assignmentId <= 0) {
                            continue;
                        }

                        $expectedNumericStudents = 0;
                        $expectedKurmerStudents = 0;
                        $completedKurmerSummaries = 0;
                        $kurmerClassStudents = [];
                        $classes = isset($assignment['classes']) && is_array($assignment['classes'])
                            ? $assignment['classes']
                            : [];

                        foreach ($classes as $classInfo) {
                            if (!is_array($classInfo)) {
                                continue;
                            }

                            $classId = (int) ($classInfo['id'] ?? 0);
                            if ($classId <= 0) {
                                continue;
                            }

                            $classYearId = isset($classInfo['tahun_ajaran_id'])
                                ? (int) $classInfo['tahun_ajaran_id']
                                : ($assignmentYearId ?? null);

                            $classCurriculum = strtolower((string) ($classInfo['kurikulum'] ?? 'k13'));
                            $classStudents = $getStudentsForClass($classId, $classYearId);

                            if ($classCurriculum === 'kurmer') {
                                $expectedKurmerStudents += count($classStudents);
                                $studentIds = array_values(array_filter(array_map(
                                    static fn ($student) => (int) ($student['id'] ?? 0),
                                    $classStudents
                                ), static fn (int $id) => $id > 0));

                                if (!empty($studentIds)) {
                                    $kurmerClassStudents[] = [
                                        'class_id' => $classId,
                                        'student_ids' => $studentIds,
                                    ];
                                }
                            } else {
                                $expectedNumericStudents += count($classStudents);
                            }
                        }

                        if ($expectedKurmerStudents > 0 && !empty($kurmerClassStudents)) {
                            foreach ($kurmerClassStudents as $kurmerClass) {
                                $summaryMap = StudentKurmerSubjectSummary::byAssignmentAndClass(
                                    $assignmentId,
                                    (int) $kurmerClass['class_id'],
                                    $kurmerClass['student_ids']
                                );
                                $completedKurmerSummaries += count($summaryMap);
                            }

                            $teacherPendingCounts['kurmer'] += max(0, $expectedKurmerStudents - $completedKurmerSummaries);
                        }

                        if ($expectedNumericStudents <= 0) {
                            continue;
                        }

                        $teacherPendingCounts['knowledge'] += max(
                            0,
                            $expectedNumericStudents - (int) ($knowledgeCounts[$assignmentId] ?? 0)
                        );

                        $skillSetting = $assignmentSettings[$assignmentId] ?? null;
                        $skillEnabled = (int) ($skillSetting['enable_keterampilan'] ?? 1) === 1;
                        if ($skillEnabled) {
                            $teacherPendingCounts['skill'] += max(
                                0,
                                $expectedNumericStudents - (int) ($skillCounts[$assignmentId] ?? 0)
                            );
                        }
                    }
                }
            }
        }

        $prakerinConfirmationClasses = [];
        if ($teacherId > 0 && !empty($homeroomClasses)) {
            $confirmationMap = HomeroomPrakerinConfirmation::mapByClassIds(
                array_values(array_filter(array_map(
                    static fn ($class) => (int) ($class['id'] ?? 0),
                    $homeroomClasses
                ), static fn (int $id) => $id > 0)),
                $teacherId
            );

            foreach ($homeroomClasses as $classInfo) {
                $classId = (int) ($classInfo['id'] ?? 0);
                if ($classId <= 0) {
                    continue;
                }

                $level = (int) ($classInfo['tingkat'] ?? 0);
                if ($level >= 12) {
                    continue;
                }

                $labelParts = [];
                $name = trim((string) ($classInfo['nama'] ?? ''));
                if ($name !== '') {
                    $labelParts[] = $name;
                }
                $major = trim((string) ($classInfo['jurusan_nama'] ?? ''));
                if ($major !== '') {
                    $labelParts[] = $major;
                }

                $confirmation = $confirmationMap[$classId] ?? null;

                $prakerinConfirmationClasses[] = [
                    'id' => $classId,
                    'label' => $labelParts !== [] ? implode(' · ', $labelParts) : sprintf('Kelas %s', $level > 0 ? $level : '?'),
                    'level' => $level,
                    'required' => $confirmation !== null ? ((int) ($confirmation['prakerin_required'] ?? 1) === 1) : null,
                    'has_confirmation' => $confirmation !== null,
                ];
            }
        }

        if (is_array($user)) {
            $roleKey = (string) ($user['role'] ?? '');
            if ($roleKey !== '') {
                $roleLabels[] = $roleLabelMap[$roleKey] ?? ucwords(str_replace('_', ' ', $roleKey));
            }
        }

        foreach ($academicPositions as $position) {
            $positionName = trim((string) ($position['jabatan_nama'] ?? ''));
            if ($positionName !== '') {
                $roleLabels[] = $positionName;
            }
        }

        if (!empty($prakerinSupervisions)) {
            $roleLabels[] = sprintf('Pembina Prakerin (%d tempat)', count($prakerinSupervisions));
        }

        if (!empty($extracurricularMentorships)) {
            $roleLabels[] = sprintf('Pembina Ekskul (%d kegiatan)', count($extracurricularMentorships));
        }

        $roleLabels = array_values(array_unique(array_filter(array_map(
            static fn ($label) => trim((string) $label),
            $roleLabels
        ), static fn ($label) => $label !== '')));

        $greetingLabel = $this->resolveGreetingLabel();
        $greetingMessage = sprintf('%s! Senang bertemu lagi, %s.', $greetingLabel, $userDisplayName);
        $headmasterTeacherId = isset($activeSchoolYear['kepala_sekolah_id'])
            ? (int) $activeSchoolYear['kepala_sekolah_id']
            : 0;
        $hasHeadmasterPrivilege = $isHeadmasterRole || ($teacherId > 0 && $headmasterTeacherId > 0 && $teacherId === $headmasterTeacherId);

        if ($teacherPendingCounts['knowledge'] > 0) {
            $pendingActions[] = [
                'key' => 'teacher-knowledge',
                'label' => 'Nilai pengetahuan belum diinput',
                'description' => 'Lengkapi nilai pengetahuan untuk siswa di mata pelajaran Anda.',
                'count' => $teacherPendingCounts['knowledge'],
                'url' => base_url('guru/nilai'),
                'role' => 'Guru Mapel',
            ];
        }

        if ($teacherPendingCounts['skill'] > 0) {
            $pendingActions[] = [
                'key' => 'teacher-skill',
                'label' => 'Nilai keterampilan belum lengkap',
                'description' => 'Isi penilaian keterampilan di tiap kelas yang Anda ampu.',
                'count' => $teacherPendingCounts['skill'],
                'url' => base_url('guru/nilai'),
                'role' => 'Guru Mapel',
            ];
        }

        if ($teacherPendingCounts['kurmer'] > 0) {
            $pendingActions[] = [
                'key' => 'teacher-kurmer',
                'label' => 'Ringkasan KurMer belum lengkap',
                'description' => 'Isi capaian akhir (BB/MB/BSH/SB) dan narasi per mapel untuk kelas Kurmer.',
                'count' => $teacherPendingCounts['kurmer'],
                'url' => base_url('guru/nilai'),
                'role' => 'Guru Mapel',
            ];
        }

        if (!empty($homeroomClasses)) {
            $progressCategoryMap = [];
            if (is_array($homeroomProgress) && isset($homeroomProgress['categories']) && is_array($homeroomProgress['categories'])) {
                foreach ($homeroomProgress['categories'] as $category) {
                    if (!is_array($category)) {
                        continue;
                    }
                    $key = (string) ($category['key'] ?? '');
                    if ($key !== '') {
                        $progressCategoryMap[$key] = $category;
                    }
                }
            }

            $pendingAttitudes = isset($progressCategoryMap['attitudes']['pending'])
                ? (int) $progressCategoryMap['attitudes']['pending']
                : 0;
            if ($pendingAttitudes > 0) {
                $pendingActions[] = [
                    'key' => 'homeroom-attitude',
                    'label' => 'Nilai sikap belum lengkap',
                    'description' => 'Lengkapi sikap spiritual dan sosial untuk siswa di kelas binaan.',
                    'count' => $pendingAttitudes,
                    'url' => base_url('walikelas/nilai-sikap/spiritual'),
                    'role' => 'Wali Kelas',
                ];
            }

            $pendingExtracurricular = isset($progressCategoryMap['extracurriculars']['pending'])
                ? (int) $progressCategoryMap['extracurriculars']['pending']
                : 0;
            if ($pendingExtracurricular > 0) {
                $pendingActions[] = [
                    'key' => 'homeroom-extracurricular',
                    'label' => 'Mapping ekstrakurikuler belum selesai',
                    'description' => 'Pastikan setiap siswa sudah memiliki data ekskul dan nilainya.',
                    'count' => $pendingExtracurricular,
                    'url' => base_url('walikelas/ekskul'),
                    'role' => 'Wali Kelas',
                ];
            }

            $pendingPrakerin = isset($progressCategoryMap['prakerin']['pending'])
                ? (int) $progressCategoryMap['prakerin']['pending']
                : 0;
            if ($pendingPrakerin > 0) {
                $pendingActions[] = [
                    'key' => 'homeroom-prakerin',
                    'label' => 'Penempatan prakerin belum lengkap',
                    'description' => 'Lengkapi mapping prakerin untuk siswa yang belum mendapatkan tempat.',
                    'count' => $pendingPrakerin,
                    'url' => base_url('walikelas/prakerin'),
                    'role' => 'Wali Kelas',
                ];
            }

            $pendingAttendance = 0;
            foreach ($homeroomClasses as $classInfo) {
                if (!is_array($classInfo)) {
                    continue;
                }

                $classId = (int) ($classInfo['id'] ?? 0);
                if ($classId <= 0) {
                    continue;
                }

                $classYearId = isset($classInfo['tahun_ajaran_id']) ? (int) $classInfo['tahun_ajaran_id'] : null;
                $students = $getStudentsForClass($classId, $classYearId);
                $attendanceRecords = StudentAttendance::byClass($classId, $classYearId);

                $pendingAttendance += max(0, count($students) - count($attendanceRecords));
            }

            if ($pendingAttendance > 0) {
                $pendingActions[] = [
                    'key' => 'homeroom-attendance',
                    'label' => 'Rekap kehadiran belum diisi',
                    'description' => 'Isi ringkasan kehadiran siswa (sakit/izin/alpa/bolos).',
                    'count' => $pendingAttendance,
                    'url' => base_url('walikelas/presensi'),
                    'role' => 'Wali Kelas',
                ];
            }

            $missingTranscriptSignatures = 0;
            if ($activeSchoolYearId !== null
                && $activeSchoolYearId > 0
                && (int) ($activeSchoolYear['digital_signature_enabled'] ?? 0) === 1
            ) {
                foreach ($homeroomClasses as $classInfo) {
                    if (!is_array($classInfo)) {
                        continue;
                    }

                    $classId = (int) ($classInfo['id'] ?? 0);
                    $classYearId = isset($classInfo['tahun_ajaran_id']) ? (int) $classInfo['tahun_ajaran_id'] : 0;

                    if ($classId <= 0 || $classYearId !== $activeSchoolYearId) {
                        continue;
                    }

                    $students = $getStudentsForClass($classId, $classYearId);
                    if (empty($students)) {
                        continue;
                    }

                    $signatureMap = DigitalDocumentSignature::mapByClass(
                        $activeSchoolYearId,
                        $classId,
                        'student_transcript'
                    );

                    foreach ($students as $student) {
                        $studentId = (int) ($student['id'] ?? 0);
                        if ($studentId > 0 && !isset($signatureMap[$studentId])) {
                            $missingTranscriptSignatures++;
                        }
                    }
                }
            }

            if ($missingTranscriptSignatures > 0) {
                $pendingActions[] = [
                    'key' => 'homeroom-signature',
                    'label' => 'Ajukan TTD ke kepala sekolah',
                    'description' => 'Masih ada transkrip yang belum diajukan untuk TTD digital.',
                    'count' => $missingTranscriptSignatures,
                    'url' => base_url('walikelas/transkrip'),
                    'role' => 'Wali Kelas',
                ];
            }
        }

        if ($hasHeadmasterPrivilege && $activeSchoolYearId !== null && $activeSchoolYearId > 0) {
            $pendingSignatures = count(DigitalDocumentSignature::listForYear($activeSchoolYearId, 'pending'));
            if ($pendingSignatures > 0) {
                $pendingActions[] = [
                    'key' => 'headmaster-signature',
                    'label' => 'Pengajuan TTD menunggu ACC',
                    'description' => 'Setujui permintaan TTD raport, surat, atau dokumen lain.',
                    'count' => $pendingSignatures,
                    'url' => base_url('kepala-sekolah/ttd-digital'),
                    'role' => 'Kepala Sekolah',
                ];
            }
        }

        if ($isBendaharaRole) {
            $connection = Database::connection();
            $pendingLoans = $connection->query(
                "SELECT COUNT(*) FROM kasbon_guru WHERE status IN ('diajukan','diverifikasi_bendahara','menunggu_acc')"
            );
            $pendingActivities = $connection->query(
                "SELECT COUNT(*) FROM dana_kegiatan WHERE status IN ('diajukan','diverifikasi_bendahara','menunggu_acc')"
            );
            $pendingFinanceApprovals = $connection->prepare(
                'SELECT COUNT(*) FROM keuangan_approval WHERE status = :status'
            );

            $loanTotal = $pendingLoans !== false ? (int) ($pendingLoans->fetchColumn() ?: 0) : 0;
            $activityTotal = $pendingActivities !== false ? (int) ($pendingActivities->fetchColumn() ?: 0) : 0;
            $approvalTotal = 0;
            if ($pendingFinanceApprovals !== false) {
                $pendingFinanceApprovals->bindValue(':status', 'menunggu');
                $pendingFinanceApprovals->execute();
                $approvalTotal = (int) ($pendingFinanceApprovals->fetchColumn() ?: 0);
            }

            $pendingFinanceTotal = $loanTotal + $activityTotal + $approvalTotal;

            if ($pendingFinanceTotal > 0) {
                $pendingActions[] = [
                    'key' => 'bendahara-approvals',
                    'label' => 'Pengajuan keuangan belum di-ACC',
                    'description' => 'Tinjau kasbon guru, dana kegiatan, atau persetujuan keuangan lainnya.',
                    'count' => $pendingFinanceTotal,
                    'url' => base_url('keuangan/bendahara'),
                    'role' => 'Bendahara',
                ];
            }
        }

        if ($isStudent && !empty($subjectDetails)) {
            $lowScoreCount = 0;
            $kkmCache = [];

            foreach ($subjectDetails as $detail) {
                if (!is_array($detail)) {
                    continue;
                }

                $assignmentId = isset($detail['assignment_id']) ? (int) $detail['assignment_id'] : 0;
                $kkmValue = 75.0;

                if ($assignmentId > 0) {
                    if (!isset($kkmCache[$assignmentId])) {
                        $setting = SubjectAssessmentSetting::findByAssignment($assignmentId);
                        $enabled = (int) ($setting['enable_kkm'] ?? 0) === 1;
                        $kkmCache[$assignmentId] = [
                            'enabled' => $enabled,
                            'value' => $enabled ? (float) ($setting['nilai_kkm'] ?? 75.0) : 75.0,
                        ];
                    }

                    $kkmValue = $kkmCache[$assignmentId]['enabled'] ? (float) $kkmCache[$assignmentId]['value'] : 75.0;
                }

                $finalScore = isset($detail['final_score']) ? $detail['final_score'] : null;
                $knowledgeScore = isset($detail['knowledge_score']) ? $detail['knowledge_score'] : null;
                $skillScore = isset($detail['skill_score']) ? $detail['skill_score'] : null;

                if ($finalScore !== null && (float) $finalScore < $kkmValue) {
                    $lowScoreCount++;
                    continue;
                }

                if (($knowledgeScore !== null && (float) $knowledgeScore < $kkmValue)
                    || ($skillScore !== null && (float) $skillScore < $kkmValue)
                ) {
                    $lowScoreCount++;
                }
            }

            if ($lowScoreCount > 0) {
                $pendingActions[] = [
                    'key' => 'student-low-scores',
                    'label' => 'Nilai di bawah KKM',
                    'description' => 'Masih ada nilai yang perlu ditingkatkan agar mencapai KKM.',
                    'count' => $lowScoreCount,
                    'url' => '#ringkasan-nilai',
                    'role' => 'Siswa',
                ];
            }
        }

        return $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'pageTitle' => 'Dashboard',
            'activeMenu' => 'dashboard',
            'metrics' => $metrics,
            'latestStudents' => $latestStudents,
            'schoolYears' => $activeYears,
            'activeSchoolYear' => $activeSchoolYear,
            'classSummaries' => $classSummaries,
            'unassignedSummary' => $unassignedSummary,
            'homeroomProgress' => $homeroomProgress,
            'homeroomClasses' => $homeroomClasses,
            'homeroomPrakerinConfirmationClasses' => $prakerinConfirmationClasses,
            'teacherSchedule' => $teacherSchedule,
            'teacherMetrics' => $teacherMetrics,
            'isAdmin' => $isAdmin,
            'isTeacher' => $isTeacher,
            'greetingLabel' => $greetingLabel,
            'greetingMessage' => $greetingMessage,
            'userDisplayName' => $userDisplayName,
            'userRoleLabels' => $roleLabels,
            'prakerinSupervisions' => $prakerinSupervisions,
            'extracurricularMentorships' => $extracurricularMentorships,
            'isStudent' => $isStudent,
            'studentSubjects' => $studentSubjects,
            'studentScoreSummary' => $studentScoreSummary,
            'studentAttendanceSummary' => $studentAttendanceSummary,
            'studentWeekRange' => $studentWeekRange,
            'studentInfo' => $studentInfo,
            'studentCurriculum' => $studentCurriculum,
            'pendingActions' => $pendingActions,
        ], 'admin');
    }

    private function resolveGreetingLabel(): string
    {
        $hour = (int) date('H');

        if ($hour >= 4 && $hour < 11) {
            return 'Selamat pagi';
        }

        if ($hour >= 11 && $hour < 15) {
            return 'Selamat siang';
        }

        if ($hour >= 15 && $hour < 18) {
            return 'Selamat sore';
        }

        return 'Selamat malam';
    }
}

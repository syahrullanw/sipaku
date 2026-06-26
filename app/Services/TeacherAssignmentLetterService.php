<?php

namespace App\Services;

use App\Models\AcademicPosition;
use App\Models\Classroom;
use App\Models\DigitalDocumentSignature;
use App\Models\Extracurricular;
use App\Models\LessonSchedule;
use App\Models\SchoolProfile;
use App\Models\SchoolYear;
use App\Models\SubjectTeacher;
use App\Models\Teacher;
use App\Models\TeacherAcademicPosition;
use Core\Database;
use App\Support\SchoolYearContext;
use DateTimeImmutable;
use PDO;
use function base_url;

class TeacherAssignmentLetterService
{
    /**
     * @return array<string, mixed>
     */
    public static function build(?int $schoolYearId, ?int $teacherId = null): array
    {
        $schoolYear = null;

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $schoolYear = SchoolYear::find($schoolYearId);
        }

        if ($schoolYear === null) {
            $schoolYear = SchoolYearContext::resolve();
        }

        $resolvedYearId = isset($schoolYear['id']) ? (int) $schoolYear['id'] : 0;
        $schoolProfile = SchoolProfile::first();
        $headmaster = self::resolveHeadmaster($schoolYear);

        $assignments = SubjectTeacher::allWithRelations($resolvedYearId > 0 ? $resolvedYearId : null);
        $schedules = LessonSchedule::listWithRelations($resolvedYearId > 0 ? $resolvedYearId : null);

        $scheduleMap = [];

        foreach ($schedules as $schedule) {
            $assignmentId = isset($schedule['guru_mata_pelajaran_id']) ? (int) $schedule['guru_mata_pelajaran_id'] : 0;

            if ($assignmentId <= 0) {
                continue;
            }

            if (!isset($scheduleMap[$assignmentId])) {
                $scheduleMap[$assignmentId] = [];
            }

            $scheduleMap[$assignmentId][] = $schedule;
        }

        $teachers = [];
        $assignmentCount = 0;
        $uniqueClassIds = [];
        $uniqueSubjectIds = [];
        $totalHours = 0;
        $totalScheduleCount = 0;

        $missingClasses = [];
        $missingSchedules = [];

        foreach ($assignments as $assignment) {
            $assignmentId = isset($assignment['id']) ? (int) $assignment['id'] : 0;
            $assignmentTeacherId = isset($assignment['guru_id']) ? (int) $assignment['guru_id'] : 0;

            if ($assignmentId <= 0 || $assignmentTeacherId <= 0) {
                continue;
            }

            if ($teacherId !== null && $teacherId > 0 && $assignmentTeacherId !== $teacherId) {
                continue;
            }

            $assignmentCount++;

            $teacherName = trim((string) ($assignment['guru_nama'] ?? 'Guru'));
            $teacherNip = trim((string) ($assignment['guru_nip'] ?? ''));

            if (!isset($teachers[$assignmentTeacherId])) {
                $teachers[$assignmentTeacherId] = [
                    'id' => $assignmentTeacherId,
                    'name' => $teacherName !== '' ? $teacherName : 'Guru',
                    'nip' => $teacherNip,
                    'assignments' => [],
                    'total_hours' => 0,
                    'class_ids' => [],
                    'subject_ids' => [],
                    'schedule_count' => 0,
                ];
            }

            $classes = [];

            if (isset($assignment['classes']) && is_array($assignment['classes'])) {
                $classes = $assignment['classes'];
            }

            $classLabels = [];
            $classIds = [];

            foreach ($classes as $class) {
                if (!is_array($class)) {
                    continue;
                }

                $classId = isset($class['id']) ? (int) $class['id'] : 0;

                if ($classId > 0) {
                    $classIds[] = $classId;
                    $uniqueClassIds[$classId] = true;
                }

                $level = trim((string) ($class['tingkat'] ?? ''));
                $name = trim((string) ($class['nama'] ?? ''));
                $label = trim($level . ' ' . $name);
                $major = trim((string) ($class['jurusan_nama'] ?? ''));

                if ($major !== '') {
                    $label = $label !== '' ? sprintf('%s (%s)', $label, $major) : $major;
                }

                if ($label === '') {
                    $label = 'Kelas tanpa nama';
                }

                $classLabels[] = $label;
            }

            $currentClassIds = $teachers[$assignmentTeacherId]['class_ids'] ?? [];
            $teachers[$assignmentTeacherId]['class_ids'] = array_values(array_unique(array_merge($currentClassIds, $classIds)));

            $subjectId = isset($assignment['mata_pelajaran_id']) ? (int) $assignment['mata_pelajaran_id'] : 0;

            if ($subjectId > 0) {
                $teachers[$assignmentTeacherId]['subject_ids'][$subjectId] = true;
                $uniqueSubjectIds[$subjectId] = true;
            }

            $scheduleEntries = $scheduleMap[$assignmentId] ?? [];
            $scheduleLabels = [];
            $assignmentHours = 0;

            foreach ($scheduleEntries as $schedule) {
                $scheduleLabels[] = self::formatScheduleEntry($schedule);
                $hours = isset($schedule['jumlah_jam']) ? (int) $schedule['jumlah_jam'] : 0;

                if ($hours > 0) {
                    $assignmentHours += $hours;
                }
            }

            $scheduleCount = count($scheduleEntries);
            $teachers[$assignmentTeacherId]['schedule_count'] += $scheduleCount;

            $totalHours += $assignmentHours;
            $totalScheduleCount += $scheduleCount;

            $subjectName = trim((string) ($assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran'));
            $subjectCode = trim((string) ($assignment['mata_pelajaran_kode'] ?? ''));
            $notes = trim((string) ($assignment['catatan'] ?? ''));

            $teachers[$assignmentTeacherId]['assignments'][] = [
                'id' => $assignmentId,
                'subject_id' => $subjectId,
                'subject_name' => $subjectName !== '' ? $subjectName : 'Mata Pelajaran',
                'subject_code' => $subjectCode,
                'classes' => $classes,
                'class_labels' => $classLabels,
                'schedule_entries' => $scheduleEntries,
                'schedule_labels' => $scheduleLabels,
                'schedule_count' => $scheduleCount,
                'total_hours' => $assignmentHours,
                'notes' => $notes,
            ];

            $teachers[$assignmentTeacherId]['total_hours'] += $assignmentHours;

            if (empty($classLabels)) {
                $missingClasses[] = [
                    'teacher_id' => $assignmentTeacherId,
                    'teacher_name' => $teacherName !== '' ? $teacherName : 'Guru',
                    'subject_name' => $subjectName !== '' ? $subjectName : 'Mata Pelajaran',
                ];
            }

            if (empty($scheduleEntries)) {
                $missingSchedules[] = [
                    'teacher_id' => $assignmentTeacherId,
                    'teacher_name' => $teacherName !== '' ? $teacherName : 'Guru',
                    'subject_name' => $subjectName !== '' ? $subjectName : 'Mata Pelajaran',
                ];
            }
        }

        foreach ($teachers as &$teacher) {
            $teacher['assignment_count'] = count($teacher['assignments']);
            $teacher['class_count'] = count($teacher['class_ids']);
            $teacher['subject_count'] = count($teacher['subject_ids']);
            $teacherId = (int) ($teacher['id'] ?? 0);

            $positions = [];

            if ($teacherId > 0) {
                $positionRows = TeacherAcademicPosition::forTeacher(
                    $teacherId,
                    $resolvedYearId > 0 ? $resolvedYearId : null
                );

                foreach ($positionRows as $positionRow) {
                    $name = trim((string) ($positionRow['jabatan_nama'] ?? ''));
                    $startDate = $positionRow['tanggal_mulai'] ?? ($schoolYear['tanggal_mulai'] ?? null);
                    $endDate = $positionRow['tanggal_selesai'] ?? ($schoolYear['tanggal_selesai'] ?? null);

                    $positions[] = [
                        'name' => $name !== '' ? $name : 'Jabatan Akademik',
                        'start_date' => $startDate,
                        'start_date_formatted' => self::formatDate($startDate),
                        'end_date' => $endDate,
                        'end_date_formatted' => self::formatDate($endDate),
                    ];
                }
            }

            $teacher['positions'] = $positions;
            unset($teacher['class_ids'], $teacher['subject_ids']);
        }

        unset($teacher);

        $teacherList = array_values($teachers);

        usort($teacherList, static function (array $a, array $b): int {
            return strcmp($a['name'], $b['name']);
        });

        $periodLabel = null;
        $startDate = $schoolYear['tanggal_mulai'] ?? null;
        $endDate = $schoolYear['tanggal_selesai'] ?? null;
        $semester = isset($schoolYear['semester_aktif']) ? (int) $schoolYear['semester_aktif'] : 1;

        if ($schoolYear !== null) {
            $semesterLabel = $semester === 2 ? 'Semester Genap' : 'Semester Ganjil';
            $periodLabel = trim(($schoolYear['nama'] ?? '') . ' · ' . $semesterLabel);
        }

        return [
            'yearId' => $resolvedYearId,
            'schoolYear' => $schoolYear,
            'schoolProfile' => $schoolProfile,
            'headmaster' => $headmaster,
            'teachers' => $teacherList,
            'metrics' => [
                'teacher_count' => count($teacherList),
                'assignment_count' => $assignmentCount,
                'unique_subject_count' => count($uniqueSubjectIds),
                'unique_class_count' => count($uniqueClassIds),
                'total_hours' => $totalHours,
                'schedule_count' => $totalScheduleCount,
            ],
            'issues' => [
                'missing_classes' => $missingClasses,
                'missing_schedules' => $missingSchedules,
            ],
            'period' => [
                'label' => $periodLabel,
                'start_date' => $startDate,
                'start_date_formatted' => self::formatDate($startDate),
                'end_date' => $endDate,
                'end_date_formatted' => self::formatDate($endDate),
                'semester' => $semester,
            ],
            'teacherFilter' => $teacherId !== null && $teacherId > 0 ? $teacherId : null,
            'positionSummary' => self::buildPositionSummary($resolvedYearId, $schoolYear),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultLetterConfig(?array $schoolYear, ?array $schoolProfile): array
    {
        $yearName = trim((string) ($schoolYear['nama'] ?? 'Tahun Pelajaran ____/____'));
        $startFormatted = self::formatDate($schoolYear['tanggal_mulai'] ?? null);
        $endFormatted = self::formatDate($schoolYear['tanggal_selesai'] ?? null);

        $location =
            trim((string) ($schoolProfile['kabupaten'] ?? '')) ?:
            trim((string) ($schoolProfile['kecamatan'] ?? '')) ?:
            trim((string) ($schoolProfile['desa'] ?? '')) ?:
            '________';

        $subjectDefault = 'Penugasan Guru Mata Pelajaran';

        return [
            'number' => '',
            'subject' => $subjectDefault,
            'place' => $location,
            'sign_date' => date('Y-m-d'),
            'effective_start' => $schoolYear['tanggal_mulai'] ?? '',
            'effective_end' => $schoolYear['tanggal_selesai'] ?? '',
            'menimbang' => [
                sprintf('bahwa untuk menjamin keterlaksanaan proses pembelajaran pada Tahun Pelajaran %s perlu menetapkan guru pengampu pada setiap mata pelajaran.', $yearName),
                'bahwa tenaga pendidik yang ditugaskan wajib melaksanakan tugas sesuai jadwal dan ketentuan yang berlaku di sekolah.',
            ],
            'mengingat' => [
                'Undang-Undang Nomor 20 Tahun 2003 tentang Sistem Pendidikan Nasional.',
                'Peraturan Pemerintah Nomor 19 Tahun 2005 jo. Peraturan Pemerintah Nomor 32 Tahun 2013 tentang Standar Nasional Pendidikan.',
                'Peraturan Menteri Pendidikan dan Kebudayaan Nomor 22 Tahun 2016 tentang Standar Proses Pendidikan Dasar dan Menengah.',
            ],
            'menetapkan' => [
                sprintf('Menugaskan guru sebagaimana tercantum dalam lampiran surat keputusan ini untuk melaksanakan pembelajaran pada Tahun Pelajaran %s.', $yearName),
                'Guru yang bersangkutan wajib melaksanakan tugas sesuai jadwal, ketentuan, dan standar operasional prosedur yang berlaku di sekolah.',
                sprintf('Surat keputusan ini berlaku sejak tanggal ditetapkan sampai dengan berakhirnya Tahun Pelajaran %s atau sampai ditetapkan keputusan baru.', $yearName),
            ],
            'tembusan' => [
                'Arsip sekolah',
            ],
            'period_label' => $yearName,
            'period_date_label' => $startFormatted !== null && $endFormatted !== null
                ? sprintf('%s s.d. %s', $startFormatted, $endFormatted)
                : null,
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function buildPositionSummary(int $yearId, ?array $schoolYear): array
    {
        if ($yearId <= 0) {
            return [];
        }

        $teacherDirectory = self::teacherDirectory();
        $defaultStart = $schoolYear['tanggal_mulai'] ?? null;
        $defaultEnd = $schoolYear['tanggal_selesai'] ?? null;

        $summary = [];

        // Jabatan akademik formal (bendahara, waka, dll)
        $grouped = TeacherAcademicPosition::byYearGroupedByPosition($yearId);
        if (!empty($grouped)) {
            $positions = AcademicPosition::allOrdered();
            $positionNameMap = [];

            foreach ($positions as $position) {
                $positionId = isset($position['id']) ? (int) $position['id'] : 0;

                if ($positionId > 0) {
                    $positionNameMap[$positionId] = trim((string) ($position['nama'] ?? 'Jabatan Akademik'));
                }
            }

            foreach ($grouped as $positionId => $rows) {
                $positionId = (int) $positionId;
                $positionName = $positionNameMap[$positionId] ?? 'Jabatan Akademik';

                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $teacherId = isset($row['guru_id']) ? (int) $row['guru_id'] : 0;
                    $teacherName = trim((string) ($row['guru_nama'] ?? ''));
                    $teacherNip = trim((string) ($row['guru_nip'] ?? ''));

                    if ($teacherName === '' || $teacherNip === '') {
                        $teacher = self::resolveTeacherDetails($teacherId, $teacherDirectory);
                        if ($teacher !== null) {
                            $teacherName = $teacher['name'];
                            $teacherNip = $teacher['nip'];
                        }
                    }

                    $note = trim((string) ($row['catatan'] ?? ''));

                    self::appendPositionEntry($summary, $positionName, [
                        'teacher_name' => $teacherName !== '' ? $teacherName : 'Guru',
                        'teacher_nip' => $teacherNip,
                        'start_date' => $row['tanggal_mulai'] ?? $defaultStart,
                        'end_date' => $row['tanggal_selesai'] ?? $defaultEnd,
                        'note' => $note,
                    ]);
                }
            }
        }

        // Wali kelas (berdasarkan kelas pada tahun ajaran aktif)
        foreach (Classroom::allWithRelations($yearId) as $class) {
            $teacherId = isset($class['wali_kelas_id']) ? (int) $class['wali_kelas_id'] : 0;
            if ($teacherId <= 0) {
                continue;
            }

            $teacher = self::resolveTeacherDetails($teacherId, $teacherDirectory);

            if ($teacher === null) {
                continue;
            }

            $level = trim((string) ($class['tingkat'] ?? ''));
            $name = trim((string) ($class['nama'] ?? ''));
            $major = trim((string) ($class['jurusan_nama'] ?? ''));

            $classLabel = trim($level . ' ' . $name);
            if ($major !== '') {
                $classLabel = $classLabel !== '' ? sprintf('%s (%s)', $classLabel, $major) : $major;
            }

            self::appendPositionEntry($summary, 'Wali Kelas', [
                'teacher_name' => $teacher['name'],
                'teacher_nip' => $teacher['nip'],
                'start_date' => $defaultStart,
                'end_date' => $defaultEnd,
                'note' => $classLabel !== '' ? 'Kelas: ' . $classLabel : null,
            ]);
        }

        // Pembina ekstrakurikuler
        foreach (Extracurricular::allOrdered($yearId) as $activity) {
            $teacherId = isset($activity['pembina_guru_id']) ? (int) $activity['pembina_guru_id'] : 0;
            if ($teacherId <= 0) {
                continue;
            }

            $teacher = self::resolveTeacherDetails($teacherId, $teacherDirectory);
            if ($teacher === null) {
                continue;
            }

            $name = trim((string) ($activity['nama'] ?? 'Ekskul'));

            self::appendPositionEntry($summary, 'Pembina Ekstrakurikuler', [
                'teacher_name' => $teacher['name'],
                'teacher_nip' => $teacher['nip'],
                'start_date' => $defaultStart,
                'end_date' => $defaultEnd,
                'note' => 'Ekskul: ' . ($name !== '' ? $name : 'Ekskul'),
            ]);
        }

        // Pembina prakerin (tempat prakerin dengan penempatan pada tahun ajaran aktif)
        $connection = Database::connection();
        $sql = <<<SQL
SELECT DISTINCT tp.pembina_guru_id AS guru_id, tp.nama AS tempat_nama
FROM tempat_prakerin tp
LEFT JOIN penempatan_prakerin pp
    ON pp.tempat_prakerin_id = tp.id
   AND pp.tahun_ajaran_id = :year_id
WHERE tp.pembina_guru_id IS NOT NULL
ORDER BY tp.nama ASC
SQL;

        $statement = $connection->prepare($sql);

        if ($statement !== false) {
            $statement->bindValue(':year_id', $yearId, PDO::PARAM_INT);

            if ($statement->execute()) {
                $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

                if ($rows !== false) {
                    foreach ($rows as $row) {
                        $teacherId = isset($row['guru_id']) ? (int) $row['guru_id'] : 0;
                        if ($teacherId <= 0) {
                            continue;
                        }

                        $teacher = self::resolveTeacherDetails($teacherId, $teacherDirectory);
                        if ($teacher === null) {
                            continue;
                        }

                        $placeName = trim((string) ($row['tempat_nama'] ?? 'Tempat Prakerin'));

                        self::appendPositionEntry($summary, 'Pembina Prakerin', [
                            'teacher_name' => $teacher['name'],
                            'teacher_nip' => $teacher['nip'],
                            'start_date' => $defaultStart,
                            'end_date' => $defaultEnd,
                            'note' => 'Tempat: ' . ($placeName !== '' ? $placeName : 'Tempat Prakerin'),
                        ]);
                    }
                }
            }
        }

        if (empty($summary)) {
            return [];
        }

        uksort($summary, static function ($a, $b): int {
            return strcasecmp((string) $a, (string) $b);
        });

        foreach ($summary as &$entries) {
            usort($entries, static function (array $a, array $b): int {
                return strcasecmp((string) ($a['teacher_name'] ?? ''), (string) ($b['teacher_name'] ?? ''));
            });
        }

        unset($entries);

        return $summary;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $letter
     * @return array<string, mixed>
     */
    public static function makeSignatureContext(array $context, array $letter, ?int $schoolYearId, ?int $teacherId): array
    {
        $yearId = (int) ($context['yearId'] ?? ($schoolYearId ?? 0));
        $teacherFilter = $teacherId !== null ? (int) $teacherId : 0;
        $documentType = 'assignment_letter';

        $documentKey = $yearId > 0 ? self::makeAssignmentDocumentKey($yearId, $teacherFilter) : null;
        $documentTitle = self::makeAssignmentDocumentTitle($context['schoolYear'] ?? null, $letter, $teacherFilter, $context['teachers'] ?? []);
        $payload = $yearId > 0 ? self::buildSignaturePayload($context, $letter, $teacherFilter) : [];

        $signature = [
            'year_id' => $yearId,
            'document_type' => $documentType,
            'document_key' => $documentKey,
            'document_title' => $documentTitle,
            'payload' => $payload,
            'available' => false,
            'reason' => null,
            'record' => null,
            'status' => 'inactive',
            'status_label' => 'Tidak tersedia',
            'status_class' => 'text-slate-500',
            'status_message' => 'TTD digital belum tersedia.',
            'verification_url' => null,
            'requestable' => false,
            'requested_at_formatted' => null,
            'approved_at_formatted' => null,
        ];

        if ($yearId <= 0) {
            $signature['reason'] = 'Pilih tahun ajaran terlebih dahulu.';
            $signature['status_message'] = $signature['reason'];

            return $signature;
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null || (int) ($activeYear['id'] ?? 0) !== $yearId) {
            $signature['reason'] = 'TTD digital hanya tersedia untuk tahun ajaran aktif.';
            $signature['status_message'] = $signature['reason'];

            return $signature;
        }

        if ((int) ($activeYear['digital_signature_enabled'] ?? 0) !== 1) {
            $signature['reason'] = 'TTD digital belum diaktifkan oleh admin.';
            $signature['status_message'] = $signature['reason'];

            return $signature;
        }

        if ($documentKey === null) {
            $signature['reason'] = 'Dokumen belum siap.';
            $signature['status_message'] = $signature['reason'];

            return $signature;
        }

        $signature['available'] = true;
        $signature['requestable'] = true;

        $record = DigitalDocumentSignature::findByDocument($yearId, $documentType, $documentKey);
        $signature['record'] = $record;

        if ($record === null) {
            $signature['status'] = 'not_requested';
            $signature['status_label'] = 'Belum diajukan';
            $signature['status_class'] = 'text-slate-500';
            $signature['status_message'] = 'Ajukan TTD digital agar kepala sekolah dapat memverifikasi dokumen ini.';

            return $signature;
        }

        $status = (string) ($record['status'] ?? 'pending');
        $signature['status'] = $status;
        $signature['requested_at_formatted'] = self::formatDateTime($record['created_at'] ?? $record['updated_at'] ?? null);
        $signature['approved_at_formatted'] = self::formatDateTime($record['approved_at'] ?? null);

        switch ($status) {
            case 'approved':
                $signature['status_label'] = 'Disetujui';
                $signature['status_class'] = 'text-emerald-600';
                $approvedAt = $signature['approved_at_formatted'];
                $signature['status_message'] = $approvedAt !== null
                    ? 'Disetujui oleh kepala sekolah pada ' . $approvedAt . '.'
                    : 'TTD digital sudah disetujui oleh kepala sekolah.';

                $token = (string) ($record['signature_token'] ?? '');

                if ($token !== '') {
                    $signature['verification_url'] = absolute_url('persuratan/validasi/' . $token);
                }

                break;
            case 'revoked':
                $signature['status_label'] = 'Dicabut';
                $signature['status_class'] = 'text-rose-600';
                $signature['status_message'] = 'Persetujuan sebelumnya dicabut. Ajukan ulang setelah dokumen diperbarui.';
                break;
            case 'pending':
            default:
                $signature['status_label'] = 'Menunggu';
                $signature['status_class'] = 'text-amber-600';
                $signature['status_message'] = 'Permintaan TTD digital menunggu persetujuan kepala sekolah.';
                break;
        }

        return $signature;
    }

    private static function makeAssignmentDocumentKey(int $yearId, int $teacherFilterId): string
    {
        return sprintf('assignment_letter:%d:%d', $yearId, $teacherFilterId > 0 ? $teacherFilterId : 0);
    }

    /**
     * @param array<string, mixed>|null $schoolYear
     * @param array<int, array<string, mixed>> $teachers
     */
    private static function makeAssignmentDocumentTitle(?array $schoolYear, array $letter, int $teacherFilterId, array $teachers): string
    {
        $baseTitle = trim((string) ($letter['subject'] ?? ''));

        if ($baseTitle === '') {
            $baseTitle = 'SK Penugasan Guru';
        }

        $schoolYearName = '';

        if (is_array($schoolYear)) {
            $schoolYearName = trim((string) ($schoolYear['nama'] ?? ''));
        }

        if ($schoolYearName !== '') {
            $baseTitle .= ' (' . $schoolYearName . ')';
        }

        if ($teacherFilterId > 0) {
            foreach ($teachers as $teacher) {
                if ((int) ($teacher['id'] ?? 0) === $teacherFilterId) {
                    $teacherName = trim((string) ($teacher['name'] ?? 'Guru'));

                    if ($teacherName !== '') {
                        return $baseTitle . ' - ' . $teacherName;
                    }
                }
            }
        }

        return $baseTitle;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $letter
     * @return array<string, mixed>
     */
    private static function buildSignaturePayload(array $context, array $letter, int $teacherFilterId): array
    {
        $teachers = [];

        foreach ($context['teachers'] ?? [] as $teacher) {
            if (!is_array($teacher)) {
                continue;
            }

            $assignments = [];

            foreach ($teacher['assignments'] ?? [] as $assignment) {
                if (!is_array($assignment)) {
                    continue;
                }

                $assignments[] = [
                    'subject_name' => $assignment['subject_name'] ?? null,
                    'subject_code' => $assignment['subject_code'] ?? null,
                    'classes' => $assignment['class_labels'] ?? [],
                    'schedule' => $assignment['schedule_labels'] ?? [],
                    'total_hours' => (int) ($assignment['total_hours'] ?? 0),
                ];
            }

            $teachers[] = [
                'id' => (int) ($teacher['id'] ?? 0),
                'name' => $teacher['name'] ?? null,
                'nip' => $teacher['nip'] ?? null,
                'class_count' => (int) ($teacher['class_count'] ?? 0),
                'subject_count' => (int) ($teacher['subject_count'] ?? 0),
                'total_hours' => (int) ($teacher['total_hours'] ?? 0),
                'assignments' => $assignments,
            ];
        }

        $schoolYearData = is_array($context['schoolYear'] ?? null) ? $context['schoolYear'] : [];
        $schoolProfileData = is_array($context['schoolProfile'] ?? null) ? $context['schoolProfile'] : null;

        return [
            'type' => 'assignment_letter',
            'generated_at' => date('c'),
            'teacher_filter_id' => $teacherFilterId,
            'school_year' => [
                'id' => $context['yearId'] ?? null,
                'name' => $schoolYearData['nama'] ?? null,
            ],
            'letter' => [
                'number' => $letter['number'] ?? null,
                'subject' => $letter['subject'] ?? null,
                'place' => $letter['place'] ?? null,
                'sign_date' => $letter['sign_date'] ?? null,
                'effective_start' => $letter['effective_start'] ?? null,
                'effective_end' => $letter['effective_end'] ?? null,
            ],
            'metrics' => $context['metrics'] ?? [],
            'teachers' => $teachers,
            'positions' => $context['positionSummary'] ?? [],
            'headmaster' => $context['headmaster'] ?? null,
            'school_profile' => $schoolProfileData,
        ];
    }

    /**
     * @return array<int, array{name: string, nip: string}>
     */
    private static function teacherDirectory(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $directory = [];

        foreach (Teacher::allOrdered() as $row) {
            $teacherId = isset($row['id']) ? (int) $row['id'] : 0;

            if ($teacherId <= 0) {
                continue;
            }

            $directory[$teacherId] = [
                'name' => trim((string) ($row['nama'] ?? 'Guru')),
                'nip' => trim((string) ($row['nip'] ?? '')),
            ];
        }

        $cache = $directory;

        return $directory;
    }

    /**
     * @param array<int, array{name: string, nip: string}> $directory
     * @return array{name: string, nip: string}|null
     */
    private static function resolveTeacherDetails(int $teacherId, array &$directory): ?array
    {
        if ($teacherId <= 0) {
            return null;
        }

        if (!array_key_exists($teacherId, $directory)) {
            $record = Teacher::find($teacherId);

            if ($record === null) {
                $directory[$teacherId] = null;
            } else {
                $directory[$teacherId] = [
                    'name' => trim((string) ($record['nama'] ?? 'Guru')),
                    'nip' => trim((string) ($record['nip'] ?? '')),
                ];
            }
        }

        return $directory[$teacherId] ?? null;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $summary
     * @param array<string, mixed> $payload
     */
    private static function appendPositionEntry(array &$summary, string $positionName, array $payload): void
    {
        $positionKey = $positionName !== '' ? $positionName : 'Jabatan Akademik';
        $note = isset($payload['note']) ? trim((string) $payload['note']) : '';
        $startDate = $payload['start_date'] ?? null;
        $endDate = $payload['end_date'] ?? null;

        $summary[$positionKey] ??= [];

        $summary[$positionKey][] = [
            'teacher_name' => trim((string) ($payload['teacher_name'] ?? 'Guru')) ?: 'Guru',
            'teacher_nip' => trim((string) ($payload['teacher_nip'] ?? '')),
            'start_date' => $startDate,
            'start_date_formatted' => self::formatDate($startDate),
            'end_date' => $endDate,
            'end_date_formatted' => self::formatDate($endDate),
            'note' => $note,
        ];
    }

    public static function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable((string) $value);
        } catch (\Exception) {
            return null;
        }

        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $monthIndex = (int) $date->format('n');
        $monthName = $months[$monthIndex] ?? $date->format('F');

        return sprintf('%s %s %s', $date->format('d'), $monthName, $date->format('Y'));
    }

    public static function formatDateTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $dateTime = new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }

        $dateLabel = self::formatDate($dateTime->format('Y-m-d'));

        if ($dateLabel === null) {
            return $dateTime->format('Y-m-d H:i');
        }

        return sprintf('%s %s', $dateLabel, $dateTime->format('H:i'));
    }

    /**
     * @param array<string, mixed> $schoolYear
     * @return array<string, mixed>|null
     */
    private static function resolveHeadmaster(?array $schoolYear): ?array
    {
        if ($schoolYear === null) {
            return null;
        }

        $headmasterId = isset($schoolYear['kepala_sekolah_id']) ? (int) $schoolYear['kepala_sekolah_id'] : 0;

        if ($headmasterId > 0) {
            $teacher = Teacher::find($headmasterId);

            if ($teacher !== null) {
                return [
                    'id' => (int) ($teacher['id'] ?? 0),
                    'name' => trim((string) ($teacher['nama'] ?? 'Kepala Sekolah')),
                    'nip' => trim((string) ($teacher['nip'] ?? '')),
                    'position' => 'Kepala Sekolah',
                ];
            }
        }

        $yearId = isset($schoolYear['id']) ? (int) $schoolYear['id'] : 0;

        if ($yearId <= 0) {
            return null;
        }

        $position = AcademicPosition::findByAssignsRole('kepala_sekolah');

        if ($position === null) {
            return null;
        }

        $grouped = TeacherAcademicPosition::byYearGroupedByPosition($yearId);
        $positionId = isset($position['id']) ? (int) $position['id'] : 0;
        $assigned = $positionId > 0 && isset($grouped[$positionId]) ? $grouped[$positionId] : [];

        if (empty($assigned)) {
            return null;
        }

        $record = $assigned[0];

        return [
            'id' => (int) ($record['guru_id'] ?? 0),
            'name' => trim((string) ($record['guru_nama'] ?? ($position['nama'] ?? 'Kepala Sekolah'))),
            'nip' => trim((string) ($record['guru_nip'] ?? '')),
            'position' => trim((string) ($position['nama'] ?? 'Kepala Sekolah')),
        ];
    }

    /**
     * @param array<string, mixed> $schedule
     */
    private static function formatScheduleEntry(array $schedule): string
    {
        static $dayOptions = null;

        if ($dayOptions === null) {
            $dayOptions = LessonSchedule::dayOptions();
        }

        $dayKey = strtolower(trim((string) ($schedule['hari'] ?? ''))); // @phpstan-ignore-line
        $dayLabel = $dayOptions[$dayKey] ?? ucfirst($dayKey);

        $start = trim((string) ($schedule['waktu_mulai'] ?? ''));
        $end = trim((string) ($schedule['waktu_selesai'] ?? ''));

        if ($start !== '' && strlen($start) >= 5) {
            $start = substr($start, 0, 5);
        }

        if ($end !== '' && strlen($end) >= 5) {
            $end = substr($end, 0, 5);
        }

        $classLevel = trim((string) ($schedule['kelas_tingkat'] ?? ''));
        $className = trim((string) ($schedule['kelas_nama'] ?? ''));
        $classLabel = trim($classLevel . ' ' . $className);
        $major = trim((string) ($schedule['jurusan_nama'] ?? ''));

        if ($major !== '') {
            $classLabel = $classLabel !== '' ? sprintf('%s (%s)', $classLabel, $major) : $major;
        }

        $timeSegment = '';

        if ($start !== '' && $end !== '') {
            $timeSegment = sprintf('%s-%s', $start, $end);
        } elseif ($start !== '') {
            $timeSegment = $start;
        } elseif ($end !== '') {
            $timeSegment = $end;
        }

        $parts = array_filter([$dayLabel, $timeSegment]);
        $label = implode(', ', $parts);

        if ($classLabel !== '') {
            $label = $label !== ''
                ? sprintf('%s · %s', $label, $classLabel)
                : $classLabel;
        }

        return $label !== '' ? $label : 'Jadwal belum diatur';
    }
}

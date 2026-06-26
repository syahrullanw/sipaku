<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\DigitalDocumentSignature;
use App\Models\SchoolProfile;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentGraduationStatus;
use App\Models\Subject;
use App\Models\Teacher;
use App\Support\LetterNumber;
use App\Support\SchoolYearDocumentSettings;
use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use DateTimeImmutable;
use PDO;

class HomeroomTranscriptController extends Controller
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
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        $classes = $activeYear !== null
            ? Classroom::homeroomClassesForTeacher($teacherId, $activeYearId)
            : [];

        if (empty($classes)) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId);
        }

        $classes = array_values(array_filter(
            $classes,
            static fn (array $class): bool => (int) ($class['tingkat'] ?? 0) === 12
        ));

        $rawKelasIds = $request->query('kelas_ids', '');
        if (is_array($rawKelasIds)) {
            $selectedClassIds = array_values(array_filter(array_map('intval', $rawKelasIds), static fn ($id) => $id > 0));
        } elseif ($rawKelasIds !== '') {
            $selectedClassIds = array_values(array_filter(array_map('intval', explode(',', $rawKelasIds)), static fn ($id) => $id > 0));
        } else {
            $singleId = (int) $request->query('kelas_id', 0);
            $selectedClassIds = $singleId > 0 ? [$singleId] : [];
        }

        if (empty($selectedClassIds) && !empty($classes)) {
            $selectedClassIds = [(int) ($classes[0]['id'] ?? 0)];
        }

        $firstSelectedClassId = !empty($selectedClassIds) ? $selectedClassIds[0] : 0;

        $selectedClass = null;
        if ($firstSelectedClassId > 0) {
            foreach ($classes as $class) {
                if ((int) ($class['id'] ?? 0) === $firstSelectedClassId) {
                    $selectedClass = $class;
                    break;
                }
            }
        }

        if ($selectedClass === null && !empty($classes)) {
            $selectedClass = $classes[0];
            $firstSelectedClassId = (int) ($selectedClass['id'] ?? 0);
            $selectedClassIds = [$firstSelectedClassId];
        }

        $students = [];
        $studentsById = [];
        $digitalSignatureRecords = [];
        $digitalSignatureSummary = [
            'total' => 0,
            'requested' => 0,
            'pending' => 0,
            'approved' => 0,
            'revoked' => 0,
            'not_requested' => 0,
        ];

        $documentType = 'student_transcript';

        $classLookup = [];
        foreach ($classes as $c) {
            $classLookup[(int) ($c['id'] ?? 0)] = $c;
        }

        foreach ($selectedClassIds as $cid) {
            $class = $classLookup[$cid] ?? null;
            if ($class === null) {
                continue;
            }

            $classYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
            if ($classYearId <= 0) {
                continue;
            }

            $classStudents = Student::byClass($cid, $classYearId);

            foreach ($classStudents as $s) {
                $sid = (int) ($s['id'] ?? 0);
                if ($sid > 0 && !isset($studentsById[$sid])) {
                    $studentsById[$sid] = true;
                    $students[] = $s;
                }
            }

            $classSignatureMap = DigitalDocumentSignature::mapByClass($classYearId, $cid, $documentType);
            foreach ($classSignatureMap as $studentId => $record) {
                if (!isset($digitalSignatureRecords[$studentId])) {
                    $digitalSignatureRecords[$studentId] = $record;
                }
            }
        }

        $selectedScope = $this->normalizeTranscriptScope((string) $request->query('scope', 'grade12'));

        $classSchoolYear = $activeYear;
        $digitalSignatureEnabled = $classSchoolYear !== null && (int) ($classSchoolYear['digital_signature_enabled'] ?? 0) === 1;
        $canRequestDigitalSignature = $digitalSignatureEnabled && $activeYearId > 0;
        $digitalSignatureSummary['total'] = count($students);
        $digitalSignatureSummary['not_requested'] = max(0, count($students));

        if ($activeYearId > 0 && !empty($students)) {
            foreach ($digitalSignatureRecords as $record) {
                $status = (string) ($record['status'] ?? 'pending');
                $digitalSignatureSummary['requested']++;

                if ($status === 'approved') {
                    $digitalSignatureSummary['approved']++;
                } elseif ($status === 'revoked') {
                    $digitalSignatureSummary['revoked']++;
                } else {
                    $digitalSignatureSummary['pending']++;
                }
            }

            $digitalSignatureSummary['not_requested'] = max(
                0,
                $digitalSignatureSummary['total'] - $digitalSignatureSummary['requested']
            );
        }

        $transcriptRequiresSignature = $selectedClass !== null && (int) ($selectedClass['tingkat'] ?? 0) >= 12;

        return $this->render('homeroom/transcripts/index', [
            'title' => 'Transkrip Nilai Siswa',
            'pageTitle' => 'Transkrip Nilai',
            'activeMenu' => 'homeroom-transcripts',
            'classes' => $classes,
            'selectedClassIds' => $selectedClassIds,
            'firstSelectedClassId' => $firstSelectedClassId,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'selectedScope' => $selectedScope,
            'digitalSignatureEnabled' => $digitalSignatureEnabled,
            'canRequestDigitalSignature' => $canRequestDigitalSignature,
            'digitalSignatureRecords' => $digitalSignatureRecords,
            'digitalSignatureSummary' => $digitalSignatureSummary,
            'digitalSignatureDocumentType' => $documentType,
            'transcriptRequiresSignature' => $transcriptRequiresSignature,
        ]);
    }

    public function searchStudentsAjax(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $keyword = trim((string) $request->query('q', ''));
        $classId = (int) $request->query('kelas_id', 0);

        if ($keyword === '') {
            return Response::json(['data' => []]);
        }

        $user = auth() ?? [];
        $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;

        if ($classId > 0) {
            $students = Student::byClass($classId, null, $keyword);
        } else {
            $classes = Classroom::homeroomClassesForTeacher($teacherId);
            $classIds = array_values(array_map(static fn ($c) => (int) ($c['id'] ?? 0), $classes));
            $students = Student::byClasses($classIds, null, $keyword);
        }

        $results = [];
        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }
            $results[] = [
                'id' => $studentId,
                'nama' => $student['nama'] ?? '-',
                'nisn' => $student['nisn'] ?? '-',
                'nipd' => $student['nipd'] ?? '-',
                'kelas_nama' => $student['kelas_nama'] ?? '-',
                'kelas_id' => (int) ($student['kelas_id'] ?? 0),
                'signature_status' => null,
                'signature_label' => null,
            ];
        }

        $activeYear = SchoolYear::active();
        $activeYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : 0;

        if ($activeYearId > 0 && !empty($results) && (int) ($activeYear['digital_signature_enabled'] ?? 0) === 1) {
            $studentIds = array_values(array_map(static fn ($r) => (int) ($r['id'] ?? 0), $results));
            $keyConditions = [];
            $keyParams = [$activeYearId, 'student_transcript'];
            foreach ($studentIds as $sid) {
                $keyConditions[] = 'document_key = ?';
                $keyParams[] = sprintf('transcript:%d', $sid);
            }
            $sigSql = 'SELECT student_id, status FROM digital_document_signatures WHERE tahun_ajaran_id = ? AND document_type = ? AND (' . implode(' OR ', $keyConditions) . ')';
            $sigStmt = Database::connection()->prepare($sigSql);
            if ($sigStmt !== false && $sigStmt->execute($keyParams)) {
                $sigRows = $sigStmt->fetchAll(PDO::FETCH_ASSOC);
                $sigMap = [];
                if ($sigRows !== false) {
                    foreach ($sigRows as $sr) {
                        $sigMap[(int) ($sr['student_id'] ?? 0)] = $sr['status'] ?? 'pending';
                    }
                }
                $labels = [
                    'pending' => 'Menunggu',
                    'approved' => 'Disetujui',
                    'revoked' => 'Dicabut',
                ];
                foreach ($results as &$res) {
                    $sid = (int) ($res['id'] ?? 0);
                    if (isset($sigMap[$sid])) {
                        $res['signature_status'] = $sigMap[$sid];
                        $res['signature_label'] = $labels[$sigMap[$sid]] ?? $sigMap[$sid];
                    }
                }
                unset($res);
            }
        }

        return Response::json(['data' => $results]);
    }

    public function print(Request $request): Response
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
        $classId = (int) $request->query('kelas_id', 0);
        $studentId = (int) $request->query('siswa_id', 0);
        $scope = $this->normalizeTranscriptScope((string) $request->query('scope', 'grade12'));

        if ($classId <= 0 || $studentId <= 0) {
            return $this->render('reports/sections/error', [
                'title' => 'Transkrip Nilai Siswa',
                'message' => 'Pilih kelas dan siswa terlebih dahulu sebelum mencetak transkrip.',
            ], 'print');
        }

        $class = Classroom::findWithRelations($classId);

        if ($class === null || (int) ($class['wali_kelas_id'] ?? 0) !== $teacherId) {
            return $this->render('reports/sections/error', [
                'title' => 'Transkrip Nilai Siswa',
                'message' => 'Data kelas tidak ditemukan atau akses ditolak.',
            ], 'print');
        }

        if ((int) ($class['tingkat'] ?? 0) !== 12) {
            return $this->render('reports/sections/error', [
                'title' => 'Transkrip Nilai Siswa',
                'message' => 'Transkrip nilai hanya tersedia untuk siswa kelas 12.',
            ], 'print');
        }

        $classYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
        $student = Student::findWithRelations($studentId);

        if ($student === null || (int) ($student['kelas_id'] ?? 0) !== $classId) {
            return $this->render('reports/sections/error', [
                'title' => 'Transkrip Nilai Siswa',
                'message' => 'Siswa tidak terdaftar pada kelas yang dipilih.',
            ], 'print');
        }

        if ($classYearId > 0 && (int) ($student['tahun_ajaran_id'] ?? 0) !== $classYearId) {
            return $this->render('reports/sections/error', [
                'title' => 'Transkrip Nilai Siswa',
                'message' => 'Tahun ajaran siswa tidak cocok dengan kelas yang dipilih.',
            ], 'print');
        }

        $transcript = $this->buildTranscriptContext($class, $student, $scope);

        if ($transcript === null) {
            return $this->render('reports/sections/error', [
                'title' => 'Transkrip Nilai Siswa',
                'message' => 'Data transkrip tidak tersedia.',
            ], 'print');
        }

        $transcript = $this->attachDigitalSignature($transcript);

        return $this->render('homeroom/transcripts/print', [
            'title' => 'Transkrip Nilai Siswa',
            'transcript' => $transcript,
        ], 'print');
    }

    public function printAll(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            return $this->render('reports/sections/error', [
                'title' => 'Transkrip Nilai Siswa',
                'message' => 'Menu ini hanya dapat diakses oleh wali kelas.',
            ], 'print');
        }

        $teacherId = (int) $user['teacher_id'];
        $scope = $this->normalizeTranscriptScope((string) $request->query('scope', 'grade12'));

        $kelasIds = $request->query('kelas_ids', '');
        $classIds = [];
        if ($kelasIds !== '') {
            $classIds = array_values(array_filter(
                array_map('intval', explode(',', $kelasIds)),
                static fn ($id) => $id > 0
            ));
        }
        if (empty($classIds)) {
            $classId = (int) $request->query('kelas_id', 0);
            if ($classId > 0) {
                $classIds = [$classId];
            }
        }

        if (empty($classIds)) {
            return $this->render('reports/sections/error', [
                'title' => 'Transkrip Nilai Siswa',
                'message' => 'Pilih kelas terlebih dahulu.',
            ], 'print');
        }

        $transcripts = [];

        foreach ($classIds as $classId) {
            $class = Classroom::findWithRelations($classId);

            if ($class === null || (int) ($class['wali_kelas_id'] ?? 0) !== $teacherId) {
                continue;
            }

            if ((int) ($class['tingkat'] ?? 0) !== 12) {
                continue;
            }

            $classYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
            $students = Student::byClass($classId, $classYearId > 0 ? $classYearId : null);

            if (empty($students)) {
                continue;
            }

            foreach ($students as $student) {
                if ((int) ($student['tahun_ajaran_id'] ?? 0) !== $classYearId) {
                    continue;
                }

                $transcript = $this->buildTranscriptContext($class, $student, $scope);

                if ($transcript === null) {
                    continue;
                }

                $transcript = $this->attachDigitalSignature($transcript);
                $transcripts[] = $transcript;
            }
        }

        if (empty($transcripts)) {
            return $this->render('reports/sections/error', [
                'title' => 'Transkrip Nilai Siswa',
                'message' => 'Tidak ada data transkrip yang tersedia.',
            ], 'print');
        }

        return $this->render('homeroom/transcripts/print-all', [
            'title' => 'Transkrip Nilai Siswa',
            'transcripts' => $transcripts,
        ], 'print');
    }

    /**
     * @param array<string, mixed> $class
     * @param array<string, mixed> $student
     *
     * @return array<string, mixed>|null
     */
    private function buildTranscriptContext(array $class, array $student, string $scope = 'all'): ?array
    {
        $studentId = (int) ($student['id'] ?? 0);
        $classId = (int) ($class['id'] ?? 0);

        if ($studentId <= 0 || $classId <= 0) {
            return null;
        }

        $schoolYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
        $schoolYear = $schoolYearId > 0 ? SchoolYear::find($schoolYearId) : null;

        $scope = $this->normalizeTranscriptScope($scope);
        $grades = $this->collectGradeSections($studentId, $class, $this->gradeLevelsForScope($scope));
        $schoolProfile = $this->getSchoolProfile($schoolYear);
        $graduationRecord = StudentGraduationStatus::findForStudent($studentId, $schoolYearId, $classId);

        $printedAt = new DateTimeImmutable();
        $printedDateLabel = $this->resolvePrintedDateLabel($schoolProfile, $class, $printedAt);
        $graduationDateLabel = $this->resolveGraduationDateLabel($schoolProfile, $printedDateLabel);
        $transcriptRows = $this->buildTranscriptRows($grades, $scope);
        $transcriptAverage = $this->averageTranscriptRows($transcriptRows);

        return [
            'student' => $student,
            'class' => $class,
            'schoolYear' => $schoolYear,
            'schoolYearId' => $schoolYearId,
            'schoolProfile' => $schoolProfile,
            'graduationRecord' => $graduationRecord,
            'grades' => $grades,
            'transcriptRows' => $transcriptRows,
            'transcriptAverage' => $transcriptAverage,
            'transcriptScope' => $scope,
            'transcriptScopeLabel' => 'Kelas 12',
            'printedAt' => $printedAt,
            'printedDateLabel' => $printedDateLabel,
            'graduationDateLabel' => $graduationDateLabel,
            'documentNumber' => $this->makeTranscriptNumber($schoolYearId, $studentId, $schoolProfile, $class, $schoolYear, $printedAt),
        ];
    }

    /**
     * @param array<string, mixed>|null $class
     * @param array<string, mixed>|null $student
     *
     * @return array<string, mixed>
     */
    private function summarizeDigitalSignature(?array $class, ?array $student): array
    {
        $default = [
            'available' => false,
            'status' => 'inactive',
            'statusLabel' => 'Tidak Aktif',
            'message' => 'TTD digital belum tersedia.',
            'record' => null,
            'canRequest' => false,
            'documentType' => 'student_transcript',
        ];

        if ($class === null || $student === null) {
            return $default;
        }

        $studentId = (int) ($student['id'] ?? 0);
        $classYearId = (int) ($class['tahun_ajaran_id'] ?? 0);

        if ($studentId <= 0 || $classYearId <= 0) {
            return $default;
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYear === null || $activeYearId !== $classYearId) {
            $default['message'] = 'TTD digital hanya tersedia untuk tahun ajaran aktif.';

            return $default;
        }

        if ((int) ($activeYear['digital_signature_enabled'] ?? 0) !== 1) {
            $default['message'] = 'TTD digital belum diaktifkan oleh admin untuk tahun ajaran ini.';

            return $default;
        }

        $documentType = 'student_transcript';
        $documentKey = $this->makeDocumentKey($documentType, $studentId);
        $record = DigitalDocumentSignature::findByDocument($activeYearId, $documentType, $documentKey);

        if ($record !== null) {
            $default['record'] = $this->formatSignatureRecord($record, $activeYear, $documentType);
            $recordStatus = $default['record']['status'] ?? 'pending';
            $default['status'] = $recordStatus;
            $default['statusLabel'] = $default['record']['statusLabel'] ?? 'Menunggu';
            $default['message'] = $default['record']['message'] ?? 'Status TTD digital tersedia.';
            $default['available'] = true;
            $default['canRequest'] = $recordStatus === 'revoked';

            return $default;
        }

        $default['available'] = true;
        $default['canRequest'] = true;
        $default['status'] = 'missing';
        $default['statusLabel'] = 'Belum Diajukan';
        $default['message'] = 'Ajukan TTD digital agar kepala sekolah dapat menyetujui transkrip ini.';

        return $default;
    }

    /**
     * @param array<string, mixed> $class
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectGradeSections(int $studentId, array $class, array $gradeLevels = [10, 11, 12]): array
    {
        $gradeLevels = array_values(array_filter(
            array_map(static fn ($level) => (int) $level, $gradeLevels),
            static fn (int $level) => in_array($level, [10, 11, 12], true)
        ));

        if (empty($gradeLevels)) {
            $gradeLevels = [10, 11, 12];
        }

        $records = $this->collectGradeRecords($studentId);

        $referenceGrade = (int) ($class['tingkat'] ?? 0);
        $referenceYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
        $referenceStart = $this->resolveSchoolYearStart($referenceYearId);

        if (in_array($referenceGrade, $gradeLevels, true)) {
            if (!isset($records[$referenceGrade]) || $this->compareDates($referenceStart, $records[$referenceGrade]['start_date']) > 0) {
                $records[$referenceGrade] = [
                    'class_id' => (int) ($class['id'] ?? 0),
                    'school_year_id' => $referenceYearId,
                    'start_date' => $referenceStart,
                    'source' => 'current',
                ];
            }
        }

        $sections = [];

        foreach ($gradeLevels as $gradeLevel) {
            $record = $records[$gradeLevel] ?? null;
            $classData = null;
            $schoolYear = null;
            $groups = [];
            $subjects = [];

            if ($record !== null) {
                $classData = Classroom::findWithRelations((int) ($record['class_id'] ?? 0));
                $schoolYearId = (int) ($record['school_year_id'] ?? 0);
                $schoolYear = $schoolYearId > 0 ? SchoolYear::find($schoolYearId) : null;

                $majorId = null;

                if ($classData !== null && isset($classData['jurusan_id'])) {
                    $majorId = (int) $classData['jurusan_id'];
                }

                if ($schoolYearId > 0) {
                    $groups = $this->collectSubjectScoresForYear($studentId, $schoolYearId, $majorId, (int) ($classData['id'] ?? 0));
                    $subjects = $this->flattenSubjects($groups);
                }
            }

            $summary = $this->summarizeSubjects($subjects);

            $sections[] = [
                'grade_level' => $gradeLevel,
                'grade_label' => $this->formatGradeLevelLabel($gradeLevel),
                'class' => $classData,
                'schoolYear' => $schoolYear,
                'groups' => $groups,
                'subjects' => $subjects,
                'summary' => $summary,
                'hasData' => !empty($subjects),
            ];
        }

        return $sections;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectGradeRecords(int $studentId): array
    {
        $connection = Database::connection();
        $records = [];

        $promotionQuery = <<<SQL
SELECT
    snk.kelas_id,
    snk.tahun_ajaran_id,
    ta.tanggal_mulai,
    k.tingkat
FROM status_naik_kelas snk
JOIN kelas k ON k.id = snk.kelas_id
JOIN tahun_ajaran ta ON ta.id = snk.tahun_ajaran_id
WHERE snk.siswa_id = :student_id
SQL;

        $promotionStatement = $connection->prepare($promotionQuery);

        if ($promotionStatement !== false) {
            $promotionStatement->bindValue(':student_id', $studentId, PDO::PARAM_INT);

            if ($promotionStatement->execute()) {
                $rows = $promotionStatement->fetchAll(PDO::FETCH_ASSOC);

                if ($rows !== false) {
                    foreach ($rows as $row) {
                        $gradeLevel = (int) ($row['tingkat'] ?? 0);
                        $classId = (int) ($row['kelas_id'] ?? 0);
                        $schoolYearId = (int) ($row['tahun_ajaran_id'] ?? 0);
                        $startDate = $row['tanggal_mulai'] ?? null;

                        if ($classId <= 0 || $schoolYearId <= 0) {
                            continue;
                        }

                        $this->storeGradeRecord($records, $gradeLevel, $classId, $schoolYearId, $startDate, 'promotion');
                    }
                }
            }
        }

        $graduationQuery = <<<SQL
SELECT
    sks.kelas_id,
    sks.tahun_ajaran_id,
    ta.tanggal_mulai,
    k.tingkat
FROM status_kelulusan_siswa sks
JOIN kelas k ON k.id = sks.kelas_id
JOIN tahun_ajaran ta ON ta.id = sks.tahun_ajaran_id
WHERE sks.siswa_id = :student_id
SQL;

        $graduationStatement = $connection->prepare($graduationQuery);

        if ($graduationStatement !== false) {
            $graduationStatement->bindValue(':student_id', $studentId, PDO::PARAM_INT);

            if ($graduationStatement->execute()) {
                $rows = $graduationStatement->fetchAll(PDO::FETCH_ASSOC);

                if ($rows !== false) {
                    foreach ($rows as $row) {
                        $gradeLevel = (int) ($row['tingkat'] ?? 0);
                        $classId = (int) ($row['kelas_id'] ?? 0);
                        $schoolYearId = (int) ($row['tahun_ajaran_id'] ?? 0);
                        $startDate = $row['tanggal_mulai'] ?? null;

                        if ($classId <= 0 || $schoolYearId <= 0) {
                            continue;
                        }

                        $this->storeGradeRecord($records, $gradeLevel, $classId, $schoolYearId, $startDate, 'graduation');
                    }
                }
            }
        }

        return $records;
    }

    /**
     * @param array<int, array<string, mixed>> $records
     */
    private function storeGradeRecord(array &$records, int $gradeLevel, int $classId, int $schoolYearId, ?string $startDate, string $source): void
    {
        if (!in_array($gradeLevel, [10, 11, 12], true)) {
            return;
        }

        $existing = $records[$gradeLevel] ?? null;

        if ($existing === null || $this->compareDates($startDate, $existing['start_date']) > 0) {
            $records[$gradeLevel] = [
                'class_id' => $classId,
                'school_year_id' => $schoolYearId,
                'start_date' => $startDate,
                'source' => $source,
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectSubjectScoresForYear(int $studentId, int $schoolYearId, ?int $majorId, int $classId): array
    {
        if ($studentId <= 0 || $schoolYearId <= 0 || $classId <= 0) {
            return [];
        }

        $connection = Database::connection();

        $groupOrderValues = [];
        foreach (Subject::GROUPS as $group) {
            $groupOrderValues[] = $connection->quote($group['code']);
        }

        $orderClause = !empty($groupOrderValues) ? implode(', ', $groupOrderValues) : '';

        $sql = <<<SQL
SELECT
    mp.id AS subject_id,
    mp.kode AS subject_code,
    mp.nama AS subject_name,
    mp.jenis AS subject_group,
    settings.enable_kkm,
    settings.nilai_kkm,
    settings.enable_keterampilan,
    knowledge.nilai_akhir AS knowledge_score,
    knowledge.predikat AS knowledge_predicate,
    knowledge.deskripsi AS knowledge_description,
    skill.nilai_akhir AS skill_score,
    skill.predikat AS skill_predicate,
    skill.deskripsi AS skill_description
FROM guru_mata_pelajaran gmp
JOIN guru_mata_pelajaran_kelas gmpk ON gmpk.guru_mata_pelajaran_id = gmp.id
JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
LEFT JOIN pengaturan_penilaian_mapel settings ON settings.guru_mata_pelajaran_id = gmp.id
LEFT JOIN penilaian_pengetahuan_siswa knowledge
    ON knowledge.guru_mata_pelajaran_id = gmp.id AND knowledge.siswa_id = :student_id
LEFT JOIN penilaian_keterampilan_siswa skill
    ON skill.guru_mata_pelajaran_id = gmp.id AND skill.siswa_id = :student_id
WHERE mp.tahun_ajaran_id = :school_year_id
  AND gmpk.kelas_id = :class_id
SQL;

        if ($majorId !== null && $majorId > 0) {
            $sql .= ' AND (mp.jurusan_id IS NULL OR mp.jurusan_id = :major_id)';
        }

        if ($orderClause !== '') {
            $sql .= sprintf(' ORDER BY FIELD(mp.jenis, %s), mp.nama ASC', $orderClause);
        } else {
            $sql .= ' ORDER BY mp.jenis ASC, mp.nama ASC';
        }

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':class_id', $classId, PDO::PARAM_INT);

        if ($majorId !== null && $majorId > 0) {
            $statement->bindValue(':major_id', $majorId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $subjects = [];

        foreach ($rows as $row) {
            $subjectId = (int) ($row['subject_id'] ?? 0);
            if ($subjectId <= 0) {
                continue;
            }

            if (!isset($subjects[$subjectId])) {
                $subjects[$subjectId] = [
                    'subject_id' => $subjectId,
                    'subject_code' => (string) ($row['subject_code'] ?? ''),
                    'subject_name' => (string) ($row['subject_name'] ?? ''),
                    'subject_group' => (string) ($row['subject_group'] ?? ''),
                    'kkm_enabled' => (bool) ((int) ($row['enable_kkm'] ?? 0) === 1),
                    'kkm_value' => $row['nilai_kkm'] !== null ? (float) $row['nilai_kkm'] : null,
                    'skill_enabled' => $row['enable_keterampilan'] === null ? true : ((int) $row['enable_keterampilan'] === 1),
                    'knowledge' => [
                        'score' => $row['knowledge_score'] !== null ? (float) $row['knowledge_score'] : null,
                        'predicate' => $row['knowledge_predicate'] ?? null,
                        'description' => $row['knowledge_description'] ?? null,
                    ],
                    'skill' => [
                        'score' => $row['skill_score'] !== null ? (float) $row['skill_score'] : null,
                        'predicate' => $row['skill_predicate'] ?? null,
                        'description' => $row['skill_description'] ?? null,
                    ],
                ];
                continue;
            }

            if ($subjects[$subjectId]['knowledge']['score'] === null && $row['knowledge_score'] !== null) {
                $subjects[$subjectId]['knowledge']['score'] = (float) $row['knowledge_score'];
                $subjects[$subjectId]['knowledge']['predicate'] = $row['knowledge_predicate'] ?? null;
                $subjects[$subjectId]['knowledge']['description'] = $row['knowledge_description'] ?? null;
            }

            if ($subjects[$subjectId]['skill']['score'] === null && $row['skill_score'] !== null) {
                $subjects[$subjectId]['skill']['score'] = (float) $row['skill_score'];
                $subjects[$subjectId]['skill']['predicate'] = $row['skill_predicate'] ?? null;
                $subjects[$subjectId]['skill']['description'] = $row['skill_description'] ?? null;
            }

            if ($subjects[$subjectId]['kkm_value'] === null && $row['nilai_kkm'] !== null) {
                $subjects[$subjectId]['kkm_value'] = (float) $row['nilai_kkm'];
                $subjects[$subjectId]['kkm_enabled'] = (int) ($row['enable_kkm'] ?? 0) === 1;
            }

            if (!$subjects[$subjectId]['skill_enabled'] && $row['enable_keterampilan'] !== null) {
                $subjects[$subjectId]['skill_enabled'] = (int) $row['enable_keterampilan'] === 1;
            }
        }

        $groupMap = [];
        foreach (Subject::GROUPS as $group) {
            $groupMap[$group['code']] = [
                'code' => $group['code'],
                'label' => $group['label'],
                'subjects' => [],
            ];
        }

        $groupMap['other'] = [
            'code' => 'other',
            'label' => 'Kelompok Mata Pelajaran Lainnya',
            'subjects' => [],
        ];

        foreach ($subjects as $subject) {
            $groupCode = $subject['subject_group'] !== '' ? $subject['subject_group'] : 'other';

            if (!isset($groupMap[$groupCode])) {
                $groupMap[$groupCode] = [
                    'code' => $groupCode,
                    'label' => $groupCode,
                    'subjects' => [],
                ];
            }

            $groupMap[$groupCode]['subjects'][] = $subject;
        }

        return array_values(array_filter($groupMap, static fn ($group) => !empty($group['subjects'])));
    }

    /**
     * @param array<int, array<string, mixed>> $groups
     *
     * @return array<int, array<string, mixed>>
     */
    private function flattenSubjects(array $groups): array
    {
        $subjects = [];

        foreach ($groups as $group) {
            $groupCode = $group['code'] ?? '';
            $groupLabel = $group['label'] ?? $groupCode;
            $groupSubjects = $group['subjects'] ?? [];

            foreach ($groupSubjects as $subject) {
                $subjects[] = array_merge($subject, [
                    'group_code' => $groupCode,
                    'group_label' => $groupLabel,
                ]);
            }
        }

        return $subjects;
    }

    /**
     * @param array<int, array<string, mixed>> $subjects
     *
     * @return array<string, mixed>
     */
    private function summarizeSubjects(array $subjects): array
    {
        $knowledgeTotal = 0.0;
        $knowledgeCount = 0;
        $skillTotal = 0.0;
        $skillCount = 0;
        $combinedTotal = 0.0;
        $combinedCount = 0;

        foreach ($subjects as $subject) {
            $knowledge = $subject['knowledge']['score'] ?? null;
            $skill = $subject['skill']['score'] ?? null;

            if ($knowledge !== null) {
                $knowledgeTotal += (float) $knowledge;
                $knowledgeCount++;
            }

            if ($skill !== null) {
                $skillTotal += (float) $skill;
                $skillCount++;
            }

            $availableScores = array_values(array_filter([
                $knowledge !== null ? (float) $knowledge : null,
                $skill !== null ? (float) $skill : null,
            ], static fn ($value) => $value !== null));

            if (!empty($availableScores)) {
                $combinedTotal += array_sum($availableScores) / count($availableScores);
                $combinedCount++;
            }
        }

        $format = static function (?float $value): ?float {
            if ($value === null) {
                return null;
            }

            return round($value, 2);
        };

        return [
            'subject_count' => count($subjects),
            'knowledge_average' => $format($knowledgeCount > 0 ? $knowledgeTotal / $knowledgeCount : null),
            'skill_average' => $format($skillCount > 0 ? $skillTotal / $skillCount : null),
            'combined_average' => $format($combinedCount > 0 ? $combinedTotal / $combinedCount : null),
        ];
    }

    private function normalizeTranscriptScope(string $scope): string
    {
        return 'grade12';
    }

    /**
     * @return array<int, int>
     */
    private function gradeLevelsForScope(string $scope): array
    {
        return [12];
    }

    /**
     * @param array<int, array<string, mixed>> $grades
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildTranscriptRows(array $grades, string $scope): array
    {
        $rows = [];
        $scope = $this->normalizeTranscriptScope($scope);

        foreach ($grades as $grade) {
            $gradeLabel = (string) ($grade['grade_label'] ?? '');
            $subjects = $grade['subjects'] ?? [];

            foreach ($subjects as $subject) {
                $subjectName = trim((string) ($subject['subject_name'] ?? ''));
                if ($subjectName === '') {
                    continue;
                }

                $rows[] = [
                    'subject_name' => $subjectName,
                    'display_name' => $subjectName,
                    'score' => $this->averageSubjectScore($subject),
                    'grade_label' => $gradeLabel,
                    'group_label' => $subject['group_label'] ?? null,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function averageTranscriptRows(array $rows): ?float
    {
        $total = 0.0;
        $count = 0;

        foreach ($rows as $row) {
            if (!array_key_exists('score', $row) || $row['score'] === null || $row['score'] === '') {
                continue;
            }

            $total += (float) $row['score'];
            $count++;
        }

        return $count > 0 ? round($total / $count, 2) : null;
    }

    /**
     * @param array<string, mixed> $subject
     */
    private function averageSubjectScore(array $subject): ?float
    {
        $knowledge = $subject['knowledge']['score'] ?? null;
        $skill = $subject['skill']['score'] ?? null;
        $availableScores = [];

        if ($knowledge !== null) {
            $availableScores[] = (float) $knowledge;
        }

        if ($skill !== null) {
            $availableScores[] = (float) $skill;
        }

        if (empty($availableScores)) {
            return null;
        }

        return round(array_sum($availableScores) / count($availableScores), 2);
    }

    private function formatGradeLevelLabel(int $gradeLevel): string
    {
        $labels = [
            10 => 'Kelas X',
            11 => 'Kelas XI',
            12 => 'Kelas XII',
        ];

        return $labels[$gradeLevel] ?? sprintf('Kelas %d', $gradeLevel);
    }

    /**
     * @param array<string, mixed> $schoolProfile
     * @param array<string, mixed> $class
     * @param array<string, mixed>|null $schoolYear
     */
    private function makeTranscriptNumber(
        int $schoolYearId,
        int $studentId,
        array $schoolProfile = [],
        array $class = [],
        ?array $schoolYear = null,
        ?DateTimeImmutable $printedAt = null
    ): string
    {
        if ($schoolYearId <= 0 || $studentId <= 0) {
            return '';
        }

        $customPrefix = rtrim(trim((string) ($schoolProfile['transkrip_nomor_prefix'] ?? '')), '/');
        if ($customPrefix !== '') {
            return sprintf('%s/%04d/%05d', $customPrefix, $schoolYearId, $studentId);
        }

        $documentDate = $this->resolveTranscriptDocumentDate($schoolProfile, $schoolYear, $printedAt);
        $sequence = str_pad((string) $studentId, 3, '0', STR_PAD_LEFT);
        $schoolCode = $this->resolveTranscriptSchoolCode($schoolProfile);
        $programCode = $this->resolveTranscriptProgramCode($class);
        $month = LetterNumber::romanMonth((int) $documentDate->format('n'));
        $year = $documentDate->format('Y');

        return sprintf('%s/%s/%s/%s/%s', $sequence, $schoolCode, $programCode, $month, $year);
    }

    /**
     * @param array<string, mixed> $schoolProfile
     * @param array<string, mixed>|null $schoolYear
     */
    private function resolveTranscriptDocumentDate(array $schoolProfile, ?array $schoolYear, ?DateTimeImmutable $printedAt): DateTimeImmutable
    {
        $candidates = [
            $schoolProfile['skl_titimangsa'] ?? null,
            $schoolYear['skl_titimangsa'] ?? null,
            $schoolProfile['tanggal_raport_tingkat_12'] ?? null,
            $schoolYear['tanggal_raport_tingkat_12'] ?? null,
            $schoolProfile['skl_tanggal_rapat_pleno'] ?? null,
            $schoolYear['skl_tanggal_rapat_pleno'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '' || $candidate === '0000-00-00') {
                continue;
            }

            $timestamp = strtotime($candidate);
            if ($timestamp !== false) {
                return new DateTimeImmutable(date('Y-m-d', $timestamp));
            }
        }

        return $printedAt ?? new DateTimeImmutable();
    }

    /**
     * @param array<string, mixed> $schoolProfile
     */
    private function resolveTranscriptSchoolCode(array $schoolProfile): string
    {
        $schoolName = trim((string) ($schoolProfile['nama'] ?? ''));
        $schoolCode = $schoolName !== '' ? $this->makeTranscriptCode($schoolName, true) : '';

        return $schoolCode !== '' ? $schoolCode : 'SEKOLAH';
    }

    /**
     * @param array<string, mixed> $class
     */
    private function resolveTranscriptProgramCode(array $class): string
    {
        $majorCode = trim((string) ($class['jurusan_kode'] ?? ''));
        if ($majorCode !== '') {
            return $this->makeTranscriptCode($majorCode);
        }

        $curriculum = strtolower(trim((string) ($class['kurikulum'] ?? '')));
        if ($curriculum === 'kurmer') {
            return 'KURMER';
        }

        if ($curriculum === 'k13') {
            return 'K13';
        }

        $majorName = trim((string) ($class['jurusan_nama'] ?? ''));
        if ($majorName !== '') {
            return $this->makeTranscriptCode($majorName);
        }

        return 'UMUM';
    }

    private function makeTranscriptCode(string $value, bool $keepShortFirstWord = false): string
    {
        $value = strtoupper(trim($value));
        $words = preg_split('/[^A-Z0-9]+/', $value) ?: [];
        $parts = [];

        foreach ($words as $index => $word) {
            if ($word === '') {
                continue;
            }

            if (ctype_digit($word)) {
                $parts[] = $word;
                continue;
            }

            if ($keepShortFirstWord && $index === 0 && strlen($word) <= 4) {
                $parts[] = $word;
                continue;
            }

            $parts[] = strlen($word) <= 3 ? $word : substr($word, 0, 2);
        }

        return implode('', $parts);
    }

    private function resolveGraduationDateLabel(array $schoolProfile, string $fallbackLabel): string
    {
        foreach (['skl_titimangsa', 'skl_tanggal_rapat_pleno', 'tanggal_raport_tingkat_12'] as $field) {
            $value = $schoolProfile[$field] ?? null;
            if (is_string($value) && $value !== '' && $value !== '0000-00-00') {
                return $this->formatIndonesianDate($value);
            }
        }

        return $fallbackLabel;
    }

    private function attachDigitalSignature(array $context): array
    {
        $studentId = isset($context['student']['id']) ? (int) $context['student']['id'] : 0;
        $schoolYearId = (int) ($context['schoolYearId'] ?? 0);

        $defaultSignature = [
            'enabled' => false,
            'status' => 'inactive',
            'message' => 'TTD digital belum tersedia.',
            'documentType' => 'student_transcript',
        ];

        if ($studentId <= 0 || $schoolYearId <= 0) {
            $defaultSignature['message'] = 'TTD digital membutuhkan data siswa dan tahun ajaran yang valid.';
            $context['digitalSignature'] = $defaultSignature;

            return $context;
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null || (int) ($activeYear['id'] ?? 0) !== $schoolYearId) {
            $defaultSignature['message'] = 'TTD digital hanya tersedia untuk tahun ajaran aktif.';
            $context['digitalSignature'] = $defaultSignature;

            return $context;
        }

        if ((int) ($activeYear['digital_signature_enabled'] ?? 0) !== 1) {
            $defaultSignature['message'] = 'TTD digital belum diaktifkan oleh admin.';
            $context['digitalSignature'] = $defaultSignature;

            return $context;
        }

        $classId = isset($context['class']['id']) ? (int) $context['class']['id'] : 0;
        $documentType = 'student_transcript';
        $documentKey = $this->makeDocumentKey($documentType, $studentId);
        $documentTitle = $this->makeDocumentTitle($documentType, $context);
        $payload = $this->buildDigitalSignaturePayload($context);
        $requestedBy = (int) (auth()['id'] ?? 0);

        $record = DigitalDocumentSignature::findByDocument(
            (int) $activeYear['id'],
            $documentType,
            $documentKey
        );

        if ($record === null) {
            $record = DigitalDocumentSignature::ensure(
                (int) $activeYear['id'],
                $documentType,
                $documentKey,
                $documentTitle,
                $payload,
                $studentId,
                $classId > 0 ? $classId : null,
                $requestedBy > 0 ? $requestedBy : null,
            );
        }

        if ($record === null) {
            $context['digitalSignature'] = [
                'enabled' => true,
                'status' => 'error',
                'message' => 'Gagal menyiapkan catatan TTD digital.',
                'documentType' => $documentType,
            ];

            return $context;
        }

        $context['digitalSignature'] = $this->formatSignatureRecord($record, $activeYear, $documentType);

        return $context;
    }

    private function makeDocumentKey(string $documentType, int $studentId): string
    {
        if ($documentType === 'student_transcript') {
            return sprintf('transcript:%d', $studentId);
        }

        return sprintf('%s:%d', $documentType, $studentId);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function makeDocumentTitle(string $documentType, array $context): string
    {
        $studentName = (string) ($context['student']['nama'] ?? 'Siswa');
        $schoolYear = $context['schoolYear'] ?? null;
        $schoolYearName = is_array($schoolYear) ? (string) ($schoolYear['nama'] ?? '') : '';
        $suffix = $schoolYearName !== '' ? ' (' . $schoolYearName . ')' : '';

        if ($documentType === 'student_transcript') {
            return sprintf('Transkrip Nilai%s - %s', $suffix, $studentName);
        }

        $label = ucwords(str_replace('_', ' ', $documentType));

        return sprintf('%s%s - %s', $label, $suffix, $studentName);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function buildDigitalSignaturePayload(array $context): array
    {
        $grades = [];
        $sections = $context['grades'] ?? [];

        foreach ($sections as $section) {
            $subjects = [];

            foreach ($section['subjects'] ?? [] as $subject) {
                $subjects[] = [
                    'code' => (string) ($subject['subject_code'] ?? ''),
                    'name' => (string) ($subject['subject_name'] ?? ''),
                    'group' => (string) ($subject['group_label'] ?? ''),
                    'knowledge_score' => $subject['knowledge']['score'] ?? null,
                    'skill_score' => $subject['skill']['score'] ?? null,
                ];
            }

            $summary = $section['summary'] ?? [];

            $grades[] = [
                'grade_level' => (int) ($section['grade_level'] ?? 0),
                'school_year' => isset($section['schoolYear']['nama']) ? (string) $section['schoolYear']['nama'] : null,
                'class_name' => isset($section['class']['nama']) ? (string) $section['class']['nama'] : null,
                'subjects' => $subjects,
                'summary' => [
                    'subject_count' => (int) ($summary['subject_count'] ?? 0),
                    'knowledge_average' => $summary['knowledge_average'] ?? null,
                    'skill_average' => $summary['skill_average'] ?? null,
                    'combined_average' => $summary['combined_average'] ?? null,
                ],
            ];
        }

        $student = $context['student'] ?? [];
        $class = $context['class'] ?? [];
        $graduationRecord = $context['graduationRecord'] ?? [];
        $transcriptRows = [];

        foreach ($context['transcriptRows'] ?? [] as $row) {
            $transcriptRows[] = [
                'subject_name' => (string) ($row['subject_name'] ?? ''),
                'display_name' => (string) ($row['display_name'] ?? ''),
                'score' => $row['score'] ?? null,
                'grade_label' => (string) ($row['grade_label'] ?? ''),
            ];
        }

        return [
            'document_type' => 'student_transcript',
            'school_year_id' => (int) ($context['schoolYearId'] ?? 0),
            'scope' => (string) ($context['transcriptScopeLabel'] ?? 'Kelas 10-12'),
            'student' => [
                'id' => (int) ($student['id'] ?? 0),
                'name' => (string) ($student['nama'] ?? ''),
                'nisn' => (string) ($student['nisn'] ?? ''),
                'nipd' => (string) ($student['nipd'] ?? ''),
                'diploma_number' => (string) ($graduationRecord['nomor_ijazah'] ?? ''),
                'specialization_type' => (string) ($graduationRecord['jenis_kekhususan'] ?? ''),
            ],
            'class' => [
                'id' => (int) ($class['id'] ?? 0),
                'name' => (string) ($class['nama'] ?? ''),
                'level' => (string) ($class['tingkat'] ?? ''),
            ],
            'grades' => $grades,
            'transcript_rows' => $transcriptRows,
            'transcript_average' => $context['transcriptAverage'] ?? null,
            'generated_at' => date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $activeYear
     *
     * @return array<string, mixed>
     */
    private function formatSignatureRecord(array $record, array $activeYear, string $documentType): array
    {
        $payload = [];

        if (isset($record['payload']) && is_string($record['payload'])) {
            $decoded = json_decode($record['payload'], true);

            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $status = (string) ($record['status'] ?? 'pending');

        $formatTimestamp = function (?string $raw): string {
            if (!is_string($raw) || $raw === '') {
                return '';
            }
            $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw);
            if ($dateTime !== false) {
                return $this->formatIndonesianDate($dateTime->format('Y-m-d')) . ' ' . $dateTime->format('H:i') . ' WIB';
            }
            return $raw;
        };

        $createdAtLabel = $formatTimestamp($record['created_at'] ?? null);
        $approvedAtLabel = $formatTimestamp($record['approved_at'] ?? null);

        $headmasterName = $this->resolveHeadmasterName((int) ($activeYear['kepala_sekolah_id'] ?? 0)) ?? '';

        $message = 'Menunggu persetujuan kepala sekolah.';

        if ($status === 'approved') {
            $message = 'TTD digital telah disetujui kepala sekolah.';
        } elseif ($status === 'revoked') {
            $message = 'TTD digital telah dicabut oleh kepala sekolah.';
        } elseif ($status === 'error') {
            $message = 'Terjadi kesalahan pada catatan TTD digital.';
        }

        $statusLabel = 'Menunggu Persetujuan';

        switch ($status) {
            case 'approved':
                $statusLabel = 'Disetujui';
                break;
            case 'revoked':
                $statusLabel = 'Dicabut';
                break;
            case 'error':
                $statusLabel = 'Terjadi Kesalahan';
                break;
        }

        $token = $record['signature_token'] ?? null;
        $verificationUrl = $token !== null && $token !== '' ? absolute_url('dokumen/validasi/' . $token) : null;

        return [
            'enabled' => true,
            'status' => $status,
            'statusLabel' => $statusLabel,
            'message' => $message,
            'documentType' => $documentType,
            'documentTitle' => (string) ($record['document_title'] ?? ''),
            'headmasterName' => $headmasterName,
            'signatureToken' => $token,
            'verificationUrl' => $verificationUrl,
            'approvalNote' => $record['approval_note'] ?? null,
            'createdAtLabel' => $createdAtLabel,
            'approvedAt' => $record['approved_at'] ?? null,
            'approvedAtLabel' => $approvedAtLabel,
            'payload' => $payload,
        ];
    }

    /**
     * @param array<string, mixed>|null $schoolYear
     * @return array<string, mixed>
     */
    private function getSchoolProfile(?array $schoolYear = null): array
    {
        static $cached = [];
        $cacheKey = $schoolYear !== null && isset($schoolYear['id']) ? 'year:' . (int) $schoolYear['id'] : 'active';

        if (isset($cached[$cacheKey])) {
            return $cached[$cacheKey];
        }

        $defaults = [
            'nama' => 'Nama Sekolah',
            'npsn' => '',
            'alamat' => '',
            'desa' => '',
            'kecamatan' => '',
            'kabupaten' => '',
            'provinsi' => '',
            'telepon' => '',
            'email' => '',
            'website' => '',
            'kepala_sekolah' => '',
            'kepala_sekolah_nip' => '',
            'akreditasi' => '',
            'logo_sekolah' => null,
            'logo_dinas' => null,
            'lambang_negara' => null,
            'kop_surat' => null,
            'skl_nomor_surat' => null,
            'skl_tanggal_rapat_pleno' => null,
            'skl_titimangsa' => null,
            'transkrip_nomor_prefix' => null,
            'tanggal_raport_tingkat_10_11' => null,
            'tanggal_raport_tingkat_12' => null,
            'tanggal_raport_tengah_semester' => null,
        ];

        $record = SchoolProfile::first();

        if ($record !== null) {
            $profile = array_merge($defaults, $record);
        } else {
            $profile = $defaults;
        }

        $activeYear = $schoolYear ?? SchoolYear::active();

        if ($activeYear !== null) {
            $headmasterId = (int) ($activeYear['kepala_sekolah_id'] ?? 0);
            $headmaster = $headmasterId > 0 ? Teacher::find($headmasterId) : null;

            if ($headmaster !== null) {
                $headmasterName = trim((string) ($headmaster['nama'] ?? ''));
                $headmasterNip = trim((string) ($headmaster['nip'] ?? ''));

                if ($headmasterName !== '') {
                    $profile['kepala_sekolah'] = $headmasterName;
                }

                if ($headmasterNip !== '') {
                    $profile['kepala_sekolah_nip'] = $headmasterNip;
                }
            }

            $profile['tanggal_raport_tingkat_10_11'] = $activeYear['tanggal_raport_tingkat_10_11'] ?? null;
            $profile['tanggal_raport_tingkat_12'] = $activeYear['tanggal_raport_tingkat_12'] ?? null;
            $profile['tanggal_raport_tengah_semester'] = $activeYear['tanggal_raport_tengah_semester'] ?? null;
        }

        $cached[$cacheKey] = SchoolYearDocumentSettings::merge($profile, $activeYear);

        return $cached[$cacheKey];
    }

    private function resolveHeadmasterName(int $teacherId): ?string
    {
        if ($teacherId <= 0) {
            return null;
        }

        $teacher = Teacher::find($teacherId);

        if ($teacher === null) {
            return null;
        }

        $name = trim((string) ($teacher['nama'] ?? ''));

        return $name === '' ? null : $name;
    }

    private function resolvePrintedDateLabel(array $schoolProfile, array $class, DateTimeImmutable $printedAt): string
    {
        $titimangsa = $schoolProfile['skl_titimangsa'] ?? null;

        if (is_string($titimangsa) && $titimangsa !== '' && $titimangsa !== '0000-00-00') {
            return $this->formatIndonesianDate($titimangsa);
        }

        $classLevel = isset($class['tingkat']) ? (int) $class['tingkat'] : 0;

        if ($classLevel === 12) {
            $configuredDate = $schoolProfile['tanggal_raport_tingkat_12'] ?? null;
        } else {
            $configuredDate = $schoolProfile['tanggal_raport_tingkat_10_11'] ?? null;
        }

        if (is_string($configuredDate) && $configuredDate !== '' && $configuredDate !== '0000-00-00') {
            return $this->formatIndonesianDate($configuredDate);
        }

        return $this->formatIndonesianDate($printedAt->format('Y-m-d'));
    }

    private function formatIndonesianDate(?string $date): string
    {
        if ($date === null || $date === '' || $date === '0000-00-00') {
            return '';
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

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return $date;
        }

        $day = (int) date('d', $timestamp);
        $month = (int) date('m', $timestamp);
        $year = date('Y', $timestamp);

        return sprintf('%d %s %s', $day, $months[$month] ?? $month, $year);
    }

    private function compareDates(?string $left, ?string $right): int
    {
        if ($left === $right) {
            return 0;
        }

        if ($left === null) {
            return -1;
        }

        if ($right === null) {
            return 1;
        }

        return strcmp($left, $right);
    }

    private function resolveSchoolYearStart(int $schoolYearId): ?string
    {
        if ($schoolYearId <= 0) {
            return null;
        }

        $year = SchoolYear::find($schoolYearId);

        if ($year === null) {
            return null;
        }

        $start = $year['tanggal_mulai'] ?? null;

        return is_string($start) && $start !== '' ? $start : null;
    }
}

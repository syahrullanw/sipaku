<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\GradeUploadBatch;
use App\Models\GradeUploadBatchAudit;
use App\Models\GradeUploadBatchRow;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentCompetencyScore;
use App\Models\StudentKnowledgeAssessment;
use App\Models\StudentKurmerSubjectSummary;
use App\Models\StudentTpAssessment;
use App\Models\SubjectAssessmentSetting;
use App\Models\SubjectCompetency;
use App\Models\SubjectLearningObjective;
use App\Models\SubjectTeacher;
use App\Models\SubjectTeacherClass;
use App\Services\AssessmentEvaluator;
use App\Services\GradeRescueGuard;
use App\Services\Import\SpreadsheetImporter;
use App\Support\GradeUploadStatus;
use App\Support\SimpleXlsxBuilder;
use App\Traits\HandlesImportUpload;
use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Throwable;

class HomeroomPerStudentGradeController extends Controller
{
    use HandlesImportUpload;

    protected ?string $layout = 'admin';

    private const FIXED_COLUMNS = ['nisn', 'nis', 'nama', 'kurikulum', 'tahun_ajaran', 'semester', 'kelas', 'mapel'];
    private const CONTROL_COLUMNS = ['template_version', 'context_token'];
    private const MAX_FILE_SIZE_BYTES = 5_000_000; // 5MB for multi-sheet
    private const MAX_DATA_ROWS = 1000;
    private const MAX_UPLOADS_PER_WINDOW = 10;
    private const UPLOAD_WINDOW_SECONDS = 600;

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
        $activeYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : null;

        $classes = $activeYearId !== null && $activeYearId > 0
            ? Classroom::homeroomClassesForTeacher($teacherId, $activeYearId)
            : Classroom::homeroomClassesForTeacher($teacherId);

        if (empty($classes)) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId);
        }

        $selectedClassId = (int) $request->query('kelas_id', 0);
        $selectedStudentId = (int) $request->query('siswa_id', 0);

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
        if ($selectedClass !== null) {
            $schoolYearId = (int) ($selectedClass['tahun_ajaran_id'] ?? 0);
            $students = Student::byClass($selectedClassId, $schoolYearId > 0 ? $schoolYearId : null);

            $majorId = isset($selectedClass['jurusan_id']) ? (int) $selectedClass['jurusan_id'] : null;
            $assignments = SubjectTeacher::bySchoolYearForClass($schoolYearId, $majorId, $selectedClassId);
            if (empty($assignments)) {
                $assignments = SubjectTeacher::bySchoolYearForClass($schoolYearId, $majorId);
            }
        }

        $selectedStudent = null;
        foreach ($students as $student) {
            if ((int) ($student['id'] ?? 0) === $selectedStudentId) {
                $selectedStudent = $student;
                break;
            }
        }

        $recentBatches = GradeUploadBatch::recentByTeacher($teacherId, 40);

        return $this->render('homeroom/grade-upload/per-siswa', [
            'title' => 'Upload Nilai Per Siswa',
            'pageTitle' => 'Upload Nilai Per Siswa',
            'activeMenu' => 'homeroom-grade-upload',
            'classes' => $classes,
            'selectedClassId' => $selectedClassId > 0 ? $selectedClassId : null,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'selectedStudentId' => $selectedStudentId > 0 ? $selectedStudentId : null,
            'selectedStudent' => $selectedStudent,
            'assignments' => $assignments,
            'recentBatches' => $recentBatches,
        ]);
    }

    public function studentsByClass(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();
        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            return $this->json(['ok' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $teacherId = (int) $user['teacher_id'];
        $classId = (int) $request->query('kelas_id', 0);
        if ($classId <= 0) {
            return $this->json(['ok' => false, 'message' => 'kelas_id wajib diisi.'], 422);
        }

        $class = Classroom::findWithRelations($classId);
        if ($class === null) {
            return $this->json(['ok' => false, 'message' => 'Kelas tidak ditemukan.'], 404);
        }

        $schoolYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
        if (!GradeRescueGuard::canTeacherAccessHomeroomClass($teacherId, $classId, $schoolYearId)) {
            return $this->json(['ok' => false, 'message' => 'Anda tidak memiliki akses ke kelas ini.'], 403);
        }

        $students = Student::byClass($classId, $schoolYearId > 0 ? $schoolYearId : null);
        $options = [];

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) continue;

            $label = trim((string) ($student['nama'] ?? ''));
            $nisn = trim((string) ($student['nisn'] ?? ''));
            $nipd = trim((string) ($student['nipd'] ?? ''));
            $identifiers = array_filter([$nipd, $nisn]);
            if (!empty($identifiers)) {
                $label .= ' - ' . implode(' / ', $identifiers);
            }

            $options[] = [
                'id' => $studentId,
                'label' => $label,
            ];
        }

        return $this->json([
            'ok' => true,
            'options' => $options,
        ]);
    }

    public function downloadTemplate(Request $request): Response
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

        if ($classId <= 0 || $studentId <= 0) {
            Session::flash('error', 'Parameter kelas_id dan siswa_id wajib diisi.');
            return $this->redirect('walikelas/nilai-upload/siswa');
        }

        $class = Classroom::findWithRelations($classId);
        if ($class === null) {
            Session::flash('error', 'Data kelas tidak ditemukan.');
            return $this->redirect('walikelas/nilai-upload/siswa');
        }

        $schoolYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
        if (!GradeRescueGuard::canTeacherAccessHomeroomClass($teacherId, $classId, $schoolYearId)) {
            Session::flash('error', 'Anda tidak memiliki akses ke kelas ini.');
            return $this->redirect('walikelas/nilai-upload/siswa');
        }

        $student = Student::findWithRelations($studentId);
        if ($student === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');
            return $this->redirect('walikelas/nilai-upload/siswa');
        }

        $majorId = isset($class['jurusan_id']) ? (int) $class['jurusan_id'] : null;
        $assignments = SubjectTeacher::bySchoolYearForClass($schoolYearId, $majorId, $classId);
        if (empty($assignments)) {
            $assignments = SubjectTeacher::bySchoolYearForClass($schoolYearId, $majorId);
        }

        $curriculum = strtolower(trim((string) ($class['kurikulum'] ?? 'k13')));
        $semester = $this->resolveSemesterText($class);
        if (!GradeRescueGuard::canRescueInput($schoolYearId, $semester)) {
            Session::flash('error', 'Periode rescue input nilai belum aktif untuk konteks ini.');
            return $this->redirect('walikelas/nilai-upload/siswa');
        }

        $sheets = [];
        $studentName = trim((string) ($student['nama'] ?? 'Siswa'));
        $classLabel = trim((string) (($class['tingkat'] ?? '') . ' ' . ($class['nama'] ?? '')));

        foreach ($assignments as $assignment) {
            $assignmentId = (int) ($assignment['id'] ?? 0);
            if ($assignmentId <= 0) continue;

            $assignmentClassIds = SubjectTeacherClass::classIds($assignmentId);
            if (!in_array($classId, $assignmentClassIds, true)) continue;

            $components = $this->resolveComponents($curriculum, $assignmentId, $classId);
            if (empty($components)) continue;

            $templateVersion = $this->buildTemplateVersion($assignmentId, $classId, $curriculum, $components);
            $requestId = GradeRescueGuard::buildRequestId($request);

            $subjectLabel = trim((string) ($assignment['mata_pelajaran_nama'] ?? ''));
            if (($assignment['mata_pelajaran_kode'] ?? '') !== '') {
                $subjectLabel .= ' (' . trim((string) $assignment['mata_pelajaran_kode']) . ')';
            }

            $header = array_merge(
                ['NISN', 'NIS', 'NAMA', 'KURIKULUM', 'TAHUN_AJARAN', 'SEMESTER', 'KELAS', 'MAPEL'],
                $components,
                ['TEMPLATE_VERSION', 'CONTEXT_TOKEN']
            );

            $contextToken = $this->buildContextToken($schoolYearId, $semester, $classId, $assignmentId, $templateVersion, $requestId);

            $existingScores = $this->getExistingScores($curriculum, $assignmentId, $classId, $studentId);

            $rowData = [
                (string) ($student['nisn'] ?? ''),
                (string) ($student['nipd'] ?? ''),
                $studentName,
                strtoupper($curriculum),
                (string) ($class['tahun_ajaran_nama'] ?? ''),
                $semester,
                $classLabel,
                $subjectLabel,
            ];

            foreach ($components as $comp) {
                $rowData[] = $existingScores[$comp] ?? '';
            }

            $rowData[] = $templateVersion;
            $rowData[] = $contextToken;

            $instructions = [
                ['PETUNJUK INPUT NILAI PER SISWA'],
                ['1. File ini berisi SATU siswa (' . $studentName . ') untuk SEMUA mata pelajaran.'],
                ['2. Setiap sheet mewakili satu mata pelajaran.'],
                ['3. Isi nilai sesuai kolom yang tersedia (angka 0-100 atau capaian BB/MB/BSH/SB).'],
                ['4. Kolom header berwarna kuning adalah area input nilai.'],
                ['5. Jangan ubah struktur header, TEMPLATE_VERSION, dan CONTEXT_TOKEN.'],
                ['6. Upload file ini kembali setelah semua nilai diisi.'],
                [''],
            ];

            $sheetRows = array_merge($instructions, [$header], [$rowData]);

            $highlightCells = [];
            $scoreStartColumn = 8 + 1;
            $scoreEndColumn = $scoreStartColumn + max(0, count($components) - 1);
            $headerRowNumber = count($instructions) + 1;
            $dataRowNumber = $headerRowNumber + 1;

            for ($col = $scoreStartColumn; $col <= $scoreEndColumn; $col++) {
                $columnLetter = $this->columnLetter($col);
                $highlightCells[] = $columnLetter . (string) $headerRowNumber;
                $highlightCells[] = $columnLetter . (string) $dataRowNumber;
            }

            $safeSheetName = preg_replace('/[^A-Za-z0-9\s\-]/', '', $subjectLabel);
            $safeSheetName = trim(mb_substr($safeSheetName, 0, 25));
            if ($safeSheetName === '') {
                $safeSheetName = 'Mapel_' . $assignmentId;
            }

            $sheets[] = [
                'name' => $safeSheetName,
                'rows' => $sheetRows,
                'options' => [
                    'highlight_cells' => $highlightCells,
                ],
            ];
        }

        if (empty($sheets)) {
            Session::flash('error', 'Tidak ada mata pelajaran yang tersedia untuk siswa ini.');
            return $this->redirect('walikelas/nilai-upload/siswa?kelas_id=' . $classId);
        }

        $recordSheetName = 'DATA_SISWA';
        $recordRows = [
            ['NISN', 'NIS', 'NAMA', 'KELAS', 'KURIKULUM', 'SEMESTER', 'JUMLAH_MAPEL'],
            [
                (string) ($student['nisn'] ?? ''),
                (string) ($student['nipd'] ?? ''),
                $studentName,
                $classLabel,
                strtoupper($curriculum),
                $semester,
                count($sheets),
            ],
        ];
        array_unshift($sheets, [
            'name' => $recordSheetName,
            'rows' => $recordRows,
            'options' => [],
        ]);

        $xlsxContent = SimpleXlsxBuilder::buildSheets($sheets);
        $filename = 'nilai-' . strtolower(preg_replace('/[^a-z0-9]+/', '-', $studentName)) . '-' . date('Ymd-His') . '.xlsx';

        return Response::make($xlsxContent, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function validateUpload(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/nilai-upload/siswa')) {
            return $response;
        }

        $user = auth();
        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            return $this->json(['ok' => false, 'message' => 'Hanya wali kelas yang dapat mengunggah nilai.'], 403);
        }

        $teacherId = (int) $user['teacher_id'];
        $targetStatus = $this->normalizeUploadTargetStatus((string) $request->input('desired_status', GradeUploadStatus::DRAFT));

        $upload = $request->file('import_file');
        if (!is_array($upload)) {
            return $this->json(['ok' => false, 'message' => 'File upload tidak ditemukan.'], 422);
        }

        if (!$this->checkUploadRateLimit($teacherId)) {
            return $this->json(['ok' => false, 'message' => 'Terlalu banyak percobaan upload. Coba kembali beberapa menit lagi.'], 429);
        }

        $fileSize = isset($upload['size']) ? (int) $upload['size'] : 0;
        if ($fileSize <= 0 || $fileSize > self::MAX_FILE_SIZE_BYTES) {
            return $this->json(['ok' => false, 'message' => 'Ukuran file tidak valid. Maksimal 5MB.'], 422);
        }

        $errorMessage = null;
        $path = $this->moveImportFile($upload, $errorMessage);
        if ($path === null) {
            return $this->json(['ok' => false, 'message' => $errorMessage ?? 'File tidak valid.'], 422);
        }

        if (!$this->isSpreadsheetMime($path)) {
            @unlink($path);
            return $this->json(['ok' => false, 'message' => 'Format file tidak dikenali sebagai spreadsheet.'], 422);
        }

        try {
            set_error_handler(static function () {});
            $xlsx = \Shuchkin\SimpleXLSX::parse($path);
            restore_error_handler();

            if ($xlsx === false) {
                return $this->json(['ok' => false, 'message' => 'Gagal membaca file Excel. Pastikan file dalam format .xlsx.'], 422);
            }

            $sheetNames = $xlsx->sheetNames();
            if (count($sheetNames) <= 1) {
                return $this->json(['ok' => false, 'message' => 'File tidak memiliki sheet data mapel. Gunakan template yang benar.'], 422);
            }

            $results = [];
            $allErrors = [];
            $overallOk = true;
            $subjectCount = 0;
            $validSubjectCount = 0;

            foreach ($sheetNames as $sheetIndex => $sheetName) {
                if ($sheetIndex === 0 && strtoupper($sheetName) === 'DATA_SISWA') {
                    continue;
                }

                $subjectCount++;
                $sheetRows = $xlsx->rows($sheetIndex);
                if (empty($sheetRows)) continue;

                $parsed = $this->parseSheetRows($sheetRows);
                if (!$parsed['ok']) {
                    $allErrors[] = [
                        'sheet' => $sheetName,
                        'message' => $parsed['message'],
                    ];
                    $overallOk = false;
                    continue;
                }

                $templateVersion = $parsed['template_version'];
                $contextToken = $parsed['context_token'];
                $components = $parsed['components'];
                $templateRows = $parsed['rows'];
                $decodedContext = $this->decodeContextToken($contextToken);

                if (!is_array($decodedContext)) {
                    $allErrors[] = [
                        'sheet' => $sheetName,
                        'message' => 'CONTEXT_TOKEN tidak valid. Unduh ulang template terbaru.',
                    ];
                    $overallOk = false;
                    continue;
                }

                $tokenClassId = (int) ($decodedContext['class_id'] ?? 0);
                $tokenAssignmentId = (int) ($decodedContext['assignment_id'] ?? 0);
                $tokenSchoolYearId = (int) ($decodedContext['school_year_id'] ?? 0);
                $tokenSemester = strtolower(trim((string) ($decodedContext['semester'] ?? '')));

                if ($tokenClassId <= 0 || $tokenAssignmentId <= 0 || $tokenSchoolYearId <= 0 || $tokenSemester === '') {
                    $allErrors[] = [
                        'sheet' => $sheetName,
                        'message' => 'CONTEXT_TOKEN tidak lengkap. Unduh ulang template terbaru.',
                    ];
                    $overallOk = false;
                    continue;
                }

                $classId = $tokenClassId;
                $assignmentId = $tokenAssignmentId;

                $class = Classroom::findWithRelations($classId);
                $assignment = SubjectTeacher::findWithRelations($assignmentId);

                if ($class === null || $assignment === null) {
                    $allErrors[] = [
                        'sheet' => $sheetName,
                        'message' => 'Kelas atau mapel dari template tidak ditemukan.',
                    ];
                    $overallOk = false;
                    continue;
                }

                $schoolYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
                $semester = $this->resolveSemesterText($class);

                if ($schoolYearId !== $tokenSchoolYearId || strtolower($semester) !== $tokenSemester) {
                    $allErrors[] = [
                        'sheet' => $sheetName,
                        'message' => 'Konteks template sudah tidak sesuai. Unduh ulang template terbaru.',
                    ];
                    $overallOk = false;
                    continue;
                }

                if (!GradeRescueGuard::canTeacherAccessHomeroomClass($teacherId, $classId, $schoolYearId)) {
                    $allErrors[] = [
                        'sheet' => $sheetName,
                        'message' => 'Anda tidak memiliki akses ke kelas ini.',
                    ];
                    $overallOk = false;
                    continue;
                }

                $assignmentClassIds = SubjectTeacherClass::classIds($assignmentId);
                if (!in_array($classId, $assignmentClassIds, true)) {
                    $allErrors[] = [
                        'sheet' => $sheetName,
                        'message' => 'Mapel tidak terdaftar di kelas yang dipilih.',
                    ];
                    $overallOk = false;
                    continue;
                }

                if (!GradeRescueGuard::canRescueInput($schoolYearId, $semester)) {
                    $allErrors[] = [
                        'sheet' => $sheetName,
                        'message' => 'Periode rescue input nilai belum aktif.',
                    ];
                    $overallOk = false;
                    continue;
                }

                $studentRows = Student::byClass($classId, $schoolYearId);
                $studentIndex = $this->buildStudentIndex($studentRows);

                $curriculum = strtoupper(trim((string) ($class['kurikulum'] ?? 'K13')));
                $classLabel = trim((string) (($class['tingkat'] ?? '') . ' ' . ($class['nama'] ?? '')));
                $yearLabel = trim((string) ($class['tahun_ajaran_nama'] ?? ''));
                $subjectLabel = trim((string) ($assignment['mata_pelajaran_nama'] ?? ''));
                if (($assignment['mata_pelajaran_kode'] ?? '') !== '') {
                    $subjectLabel .= ' (' . trim((string) $assignment['mata_pelajaran_kode']) . ')';
                }

                $rowErrors = [];
                $validScores = null;
                $studentInfo = null;

                foreach ($templateRows as $row) {
                    $nisn = trim((string) ($row['nisn'] ?? ''));
                    $nis = trim((string) ($row['nis'] ?? ''));
                    $name = trim((string) ($row['nama'] ?? ''));

                    $sid = $this->resolveStudentId($nisn, $nis, $studentIndex);
                    if ($sid === null) {
                        $rowErrors[] = 'Siswa tidak ditemukan di kelas berdasarkan NISN/NIS.';
                        continue;
                    }

                    if (strtoupper(trim((string) ($row['kurikulum'] ?? ''))) !== $curriculum) {
                        $rowErrors[] = 'KURIKULUM tidak sesuai template.';
                    }
                    if (trim((string) ($row['tahun_ajaran'] ?? '')) !== $yearLabel) {
                        $rowErrors[] = 'TAHUN_AJARAN tidak sesuai template.';
                    }
                    if (strtolower(trim((string) ($row['semester'] ?? ''))) !== $semester) {
                        $rowErrors[] = 'SEMESTER tidak sesuai template.';
                    }
                    if (trim((string) ($row['kelas'] ?? '')) !== $classLabel) {
                        $rowErrors[] = 'KELAS tidak sesuai template.';
                    }
                    if (trim((string) ($row['mapel'] ?? '')) !== $subjectLabel) {
                        $rowErrors[] = 'MAPEL tidak sesuai template.';
                    }

                    $payloadScores = [];
                    $curriculumLower = strtolower($curriculum);
                    foreach ($components as $component) {
                        $valueRaw = trim((string) ($row[$component] ?? ''));
                        if ($valueRaw === '') {
                            $payloadScores[$component] = null;
                            continue;
                        }

                        if ($curriculumLower === 'kurmer' && str_ends_with($component, '_capaian')) {
                            $capaian = AssessmentEvaluator::normalizeKurmerCapaian($valueRaw);
                            if ($capaian === null) {
                                $rowErrors[] = sprintf('Nilai %s harus BB/MB/BSH/SB.', strtoupper($component));
                                continue;
                            }
                            $payloadScores[$component] = $capaian;
                            continue;
                        }

                        if ($curriculumLower === 'kurmer' && $component === 'capaian_akhir') {
                            $capaian = AssessmentEvaluator::normalizeKurmerCapaian($valueRaw);
                            if ($capaian === null) {
                                $rowErrors[] = 'Nilai CAPAIAN_AKHIR harus BB/MB/BSH/SB.';
                                continue;
                            }
                            $payloadScores[$component] = $capaian;
                            continue;
                        }

                        if ($curriculumLower === 'kurmer' && in_array($component, ['deskripsi_umum', 'tindak_lanjut'], true)) {
                            $payloadScores[$component] = $valueRaw;
                            continue;
                        }

                        if (!is_numeric(str_replace(',', '.', $valueRaw))) {
                            $rowErrors[] = sprintf('Nilai %s harus berupa angka.', strtoupper($component));
                            continue;
                        }

                        $score = (float) str_replace(',', '.', $valueRaw);
                        if ($score < 0 || $score > 100) {
                            $rowErrors[] = sprintf('Nilai %s harus di rentang 0-100.', strtoupper($component));
                            continue;
                        }

                        $payloadScores[$component] = $score;
                    }

                    if (empty($rowErrors)) {
                        $validScores = $payloadScores;
                        $studentInfo = ['id' => $sid, 'nisn' => $nisn, 'nis' => $nis, 'nama' => $name];
                    }
                }

                if (!empty($rowErrors)) {
                    $allErrors[] = [
                        'sheet' => $sheetName,
                        'message' => 'Data tidak valid: ' . implode('; ', $rowErrors),
                        'mapel' => $subjectLabel,
                    ];
                    $overallOk = false;
                    continue;
                }

                if ($validScores === null || $studentInfo === null) {
                    $allErrors[] = [
                        'sheet' => $sheetName,
                        'message' => 'Tidak ada data siswa valid pada sheet ini.',
                    ];
                    $overallOk = false;
                    continue;
                }

                $batchCode = 'BATCH-' . strtoupper(substr(sha1((string) microtime(true) . '|' . $teacherId . '|' . $classId . '|' . $assignmentId . '|' . $sheetName), 0, 14));
                $now = date('Y-m-d H:i:s');
                $connection = Database::connection();
                $connection->beginTransaction();

                try {
                    $batchId = GradeUploadBatch::createAndReturnId([
                        'batch_code' => $batchCode,
                        'tahun_ajaran_id' => $schoolYearId,
                        'semester' => $semester,
                        'kelas_id' => $classId,
                        'guru_mata_pelajaran_id' => $assignmentId,
                        'uploaded_by_user_id' => (int) ($user['id'] ?? 0),
                        'uploaded_by_teacher_id' => $teacherId,
                        'source_role' => 'walikelas',
                        'file_name' => (string) ($upload['name'] ?? 'upload.xlsx'),
                        'template_version' => $templateVersion,
                        'status' => GradeUploadStatus::VALIDATING,
                        'total_rows' => 0,
                        'valid_rows' => 0,
                        'invalid_rows' => 0,
                        'request_id' => GradeRescueGuard::buildRequestId($request),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($batchId === null || $batchId <= 0) {
                        throw new \RuntimeException('Gagal membuat batch upload untuk sheet ' . $sheetName);
                    }

                    $rowNo = 0;
                    $rowNo++;
                    $studentId = (int) $studentInfo['id'];

                    GradeUploadBatchRow::create([
                        'batch_upload_nilai_id' => $batchId,
                        'row_no' => $rowNo,
                        'siswa_id' => $studentId,
                        'is_valid' => 1,
                        'error_messages' => null,
                        'payload_json' => json_encode([
                            'row' => $studentInfo,
                            'scores' => $validScores,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $existingBatch = GradeUploadBatch::findLatestByContext(
                        $teacherId, $schoolYearId, $semester, $classId, $assignmentId
                    );
                    if ($existingBatch !== null && strtoupper(trim((string) ($existingBatch['status'] ?? ''))) === GradeUploadStatus::DRAFT) {
                        $this->rollbackSingleBatch($existingBatch, (int) ($user['id'] ?? 0), $teacherId);
                    }

                    GradeUploadBatch::updateById($batchId, [
                        'status' => GradeUploadStatus::VALIDATED,
                        'total_rows' => $rowNo,
                        'valid_rows' => $rowNo,
                        'invalid_rows' => 0,
                        'updated_at' => $now,
                    ]);

                    $connection->commit();

                    $savedBatch = GradeUploadBatch::findByBatchCode($batchCode);
                    if ($savedBatch !== null) {
                        $applyResult = $this->applySingleBatchData(
                            $savedBatch,
                            (int) ($user['id'] ?? 0),
                            $teacherId,
                            $targetStatus
                        );

                        $results[] = [
                            'sheet' => $sheetName,
                            'mapel' => $subjectLabel,
                            'batch_code' => $batchCode,
                            'status' => $targetStatus,
                            'result' => $applyResult,
                        ];
                        $validSubjectCount++;
                    }
                } catch (Throwable $e) {
                    if ($connection->inTransaction()) {
                        $connection->rollBack();
                    }
                    $allErrors[] = [
                        'sheet' => $sheetName,
                        'message' => 'Gagal memproses: ' . $e->getMessage(),
                    ];
                    $overallOk = false;
                }
            }

            $responseData = [
                'ok' => $overallOk && empty($allErrors),
                'message' => $overallOk && empty($allErrors)
                    ? 'Semua nilai berhasil diproses.'
                    : 'Beberapa mapel bermasalah. Perbaiki lalu upload ulang.',
                'summary' => [
                    'total_subjects' => $subjectCount,
                    'success_subjects' => $validSubjectCount,
                    'error_count' => count($allErrors),
                ],
                'results' => $results,
                'errors' => $allErrors,
            ];

            return $this->json($responseData, $overallOk ? 200 : 422);
        } catch (Throwable $exception) {
            return $this->json(['ok' => false, 'message' => 'Gagal memproses file: ' . $exception->getMessage()], 500);
        } finally {
            if (is_file($path ?? '')) {
                @unlink($path);
            }
        }
    }

    private function parseSheetRows(array $rows): array
    {
        if (empty($rows)) {
            return ['ok' => false, 'message' => 'Sheet kosong.'];
        }

        $headerIndex = null;
        $headerMap = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) continue;

            $normalized = [];
            foreach ($row as $col) {
                $normalized[] = $this->normalizeHeader((string) $col);
            }

            if ($this->containsRequiredHeader($normalized)) {
                $headerIndex = $index;
                $headerMap = $normalized;
                break;
            }
        }

        if ($headerIndex === null) {
            return ['ok' => false, 'message' => 'Header template tidak ditemukan.'];
        }

        $components = [];
        foreach ($headerMap as $column) {
            if ($column === '' || in_array($column, self::FIXED_COLUMNS, true) || in_array($column, self::CONTROL_COLUMNS, true)) {
                continue;
            }
            $components[] = $column;
        }

        if (empty($components)) {
            return ['ok' => false, 'message' => 'Kolom komponen nilai tidak ditemukan.'];
        }

        $parsedRows = [];
        $templateVersion = '';
        $contextToken = '';

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (!is_array($row)) continue;

            $record = [];
            $isEmpty = true;

            foreach ($headerMap as $colIndex => $columnName) {
                if ($columnName === '') continue;

                $value = isset($row[$colIndex]) ? trim((string) $row[$colIndex]) : '';
                if ($value !== '') {
                    $isEmpty = false;
                }
                $record[$columnName] = $value;
            }

            if ($isEmpty) continue;

            if ($templateVersion === '' && isset($record['template_version'])) {
                $templateVersion = trim((string) $record['template_version']);
            }
            if ($contextToken === '' && isset($record['context_token'])) {
                $contextToken = trim((string) $record['context_token']);
            }

            $parsedRows[] = $record;
        }

        if (empty($parsedRows)) {
            return ['ok' => false, 'message' => 'Tidak ada data siswa pada sheet.'];
        }

        if ($templateVersion === '' || $contextToken === '') {
            return ['ok' => false, 'message' => 'Kolom TEMPLATE_VERSION / CONTEXT_TOKEN tidak terisi.'];
        }

        return [
            'ok' => true,
            'message' => 'OK',
            'rows' => $parsedRows,
            'header' => $headerMap,
            'components' => $components,
            'template_version' => $templateVersion,
            'context_token' => $contextToken,
        ];
    }

    private function containsRequiredHeader(array $header): bool
    {
        foreach (array_merge(self::FIXED_COLUMNS, self::CONTROL_COLUMNS) as $required) {
            if (!in_array($required, $header, true)) {
                return false;
            }
        }
        return true;
    }

    private function normalizeHeader(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/i', '_', $normalized) ?? '';
        return trim($normalized, '_');
    }

    private function resolveComponents(string $curriculum, int $assignmentId, int $classId): array
    {
        if ($curriculum === 'kurmer') {
            $objectives = SubjectLearningObjective::byAssignment($assignmentId, $classId);
            if (empty($objectives)) return [];

            $components = [];
            foreach ($objectives as $objective) {
                $id = (int) ($objective['id'] ?? 0);
                $code = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', (string) ($objective['kode_tp'] ?? ('TP' . $id))));
                if ($id <= 0) continue;
                $components[] = 'TP_' . $id . '_' . $code . '_CAPAIAN';
                $components[] = 'TP_' . $id . '_' . $code . '_NILAI';
            }
            $components[] = 'CAPAIAN_AKHIR';
            $components[] = 'DESKRIPSI_UMUM';
            $components[] = 'TINDAK_LANJUT';
            $components[] = 'NILAI_OPSIONAL';
            return $components;
        }

        // K13
        $competencies = SubjectCompetency::byAssignment($assignmentId, 'pengetahuan', $classId);
        if (empty($competencies)) return [];

        $components = [];
        foreach ($competencies as $competency) {
            $id = (int) ($competency['id'] ?? 0);
            $code = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', (string) ($competency['kode'] ?? ('KD' . $id))));
            if ($id <= 0) continue;
            $components[] = 'KD_' . $id . '_' . $code;
        }
        $components[] = 'UTS';
        $components[] = 'UAS';
        return $components;
    }

    private function getExistingScores(string $curriculum, int $assignmentId, int $classId, int $studentId): array
    {
        $scores = [];

        if ($curriculum === 'kurmer') {
            $tpAssessments = StudentTpAssessment::mapByAssignmentAndClass($assignmentId, $classId, [$studentId]);
            $studentTps = $tpAssessments[$studentId] ?? [];

            $objectives = SubjectLearningObjective::byAssignment($assignmentId, $classId);
            foreach ($objectives as $obj) {
                $id = (int) ($obj['id'] ?? 0);
                if ($id <= 0) continue;
                $code = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', (string) ($obj['kode_tp'] ?? ('TP' . $id))));
                $tpData = $studentTps[$id] ?? null;

                if ($tpData !== null) {
                    $scores['TP_' . $id . '_' . $code . '_CAPAIAN'] = $tpData['capaian_enum'] ?? '';
                    $scores['TP_' . $id . '_' . $code . '_NILAI'] = $tpData['nilai_opsional'] ?? '';
                } else {
                    $scores['TP_' . $id . '_' . $code . '_CAPAIAN'] = '';
                    $scores['TP_' . $id . '_' . $code . '_NILAI'] = '';
                }
            }

            $kurmerSummary = StudentKurmerSubjectSummary::byAssignmentAndClass($assignmentId, $classId, [$studentId]);
            $summary = $kurmerSummary[$studentId] ?? null;

            $scores['CAPAIAN_AKHIR'] = $summary['capaian_akhir_enum'] ?? '';
            $scores['DESKRIPSI_UMUM'] = $summary['deskripsi_umum'] ?? '';
            $scores['TINDAK_LANJUT'] = $summary['tindak_lanjut'] ?? '';
            $scores['NILAI_OPSIONAL'] = $summary['nilai_opsional'] ?? '';

            return $scores;
        }

        // K13
        $knowledge = StudentKnowledgeAssessment::findForStudent($assignmentId, $studentId);
        $competencies = SubjectCompetency::byAssignment($assignmentId, 'pengetahuan', $classId);
        $kdScores = StudentCompetencyScore::byAssignmentAndType($assignmentId, 'pengetahuan', [$studentId]);

        $kdScoreMap = [];
        foreach ($kdScores as $kdScore) {
            $kdScoreMap[(int) ($kdScore['kd_id'] ?? 0)] = $kdScore['nilai'] ?? '';
        }

        foreach ($competencies as $comp) {
            $id = (int) ($comp['id'] ?? 0);
            $code = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', (string) ($comp['kode'] ?? ('KD' . $id))));
            if ($id <= 0) continue;
            $scores['KD_' . $id . '_' . $code] = $kdScoreMap[$id] ?? '';
        }

        $scores['UTS'] = $knowledge['nilai_uts'] ?? '';
        $scores['UAS'] = $knowledge['nilai_uas'] ?? '';

        return $scores;
    }

    private function buildTemplateVersion(int $assignmentId, int $classId, string $curriculum, array $components): string
    {
        $signature = implode('|', [
            'per-siswa',
            (string) $assignmentId,
            (string) $classId,
            $curriculum,
            implode(',', $components),
            date('Ymd'),
        ]);
        return 'TPL-PS-' . strtoupper(substr(sha1($signature), 0, 12));
    }

    private function buildContextToken(
        int $schoolYearId,
        string $semester,
        int $classId,
        int $assignmentId,
        string $templateVersion,
        string $requestId
    ): string {
        $payload = json_encode([
            'school_year_id' => $schoolYearId,
            'semester' => $semester,
            'class_id' => $classId,
            'assignment_id' => $assignmentId,
            'template_version' => $templateVersion,
            'request_id' => $requestId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($payload) || $payload === '') return '';

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    private function decodeContextToken(string $token): ?array
    {
        $normalized = strtr($token, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $raw = base64_decode($normalized, true);
        if (!is_string($raw) || $raw === '') return null;

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<int, array<string, mixed>> $studentRows
     * @return array<string, int>
     */
    private function buildStudentIndex(array $studentRows): array
    {
        $map = [];
        foreach ($studentRows as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) continue;

            $nisn = trim((string) ($student['nisn'] ?? ''));
            $nis = trim((string) ($student['nipd'] ?? ''));

            if ($nisn !== '') $map['nisn:' . $nisn] = $studentId;
            if ($nis !== '') $map['nis:' . $nis] = $studentId;
        }
        return $map;
    }

    /**
     * @param array<string, int> $studentIndex
     */
    private function resolveStudentId(string $nisn, string $nis, array $studentIndex): ?int
    {
        if ($nisn !== '' && isset($studentIndex['nisn:' . $nisn])) {
            return $studentIndex['nisn:' . $nisn];
        }
        if ($nis !== '' && isset($studentIndex['nis:' . $nis])) {
            return $studentIndex['nis:' . $nis];
        }
        return null;
    }

    private function resolveSemesterText(array $class): string
    {
        $semesterNumber = (int) ($class['tahun_ajaran_semester'] ?? 1);
        return $semesterNumber === 2 ? 'genap' : 'ganjil';
    }

    private function normalizeUploadTargetStatus(string $status): string
    {
        $normalized = strtoupper(trim($status));
        return $normalized === GradeUploadStatus::FINAL ? GradeUploadStatus::FINAL : GradeUploadStatus::DRAFT;
    }

    private function applySingleBatchData(
        array $batch,
        int $actorUserId,
        int $actorTeacherId,
        string $finalStatus
    ): array {
        $assignmentId = (int) ($batch['guru_mata_pelajaran_id'] ?? 0);
        $setting = SubjectAssessmentSetting::ensureDefault($assignmentId);
        $weights = SubjectAssessmentSetting::resolveWeights($setting);
        $kkmEnabled = (int) ($setting['enable_kkm'] ?? 0) === 1;
        $kkmValue = isset($setting['nilai_kkm']) ? (float) $setting['nilai_kkm'] : null;
        $isKurmer = $this->isKurmerBatch($batch);

        $rows = GradeUploadBatchRow::byBatch((int) ($batch['id'] ?? 0), false);
        if (empty($rows)) {
            throw new \RuntimeException('Tidak ada baris valid untuk disimpan.');
        }

        $conn = Database::connection();
        $now = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        try {
            $conn->beginTransaction();

            foreach ($rows as $row) {
                $studentId = (int) ($row['siswa_id'] ?? 0);
                if ($studentId <= 0) continue;

                $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
                $scores = is_array($payload) && isset($payload['scores']) && is_array($payload['scores']) ? $payload['scores'] : [];

                if ($isKurmer) {
                    $this->commitKurmerRow(
                        (int) ($batch['id'] ?? 0),
                        $assignmentId,
                        (int) ($batch['kelas_id'] ?? 0),
                        $studentId,
                        $scores,
                        $actorUserId,
                        $actorTeacherId,
                        $now
                    );
                    $updated++;
                } else {
                    [$didInsert, $didUpdate] = $this->commitK13Row(
                        (int) ($batch['id'] ?? 0),
                        $assignmentId,
                        $studentId,
                        $scores,
                        $weights,
                        $kkmEnabled,
                        $kkmValue,
                        $actorUserId,
                        $actorTeacherId,
                        $now
                    );
                    $inserted += $didInsert;
                    $updated += $didUpdate;
                }
            }

            GradeUploadBatch::updateById((int) ($batch['id'] ?? 0), [
                'status' => $finalStatus,
                'committed_at' => $now,
                'updated_at' => $now,
            ]);

            $conn->commit();
        } catch (Throwable $exception) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $exception;
        }

        GradeRescueGuard::log('Committed per-student grade batch', [
            'batch_code' => (string) ($batch['batch_code'] ?? ''),
            'batch_id' => (int) ($batch['id'] ?? 0),
            'teacher_id' => $actorTeacherId,
            'user_id' => $actorUserId,
            'inserted' => $inserted,
            'updated' => $updated,
            'status' => $finalStatus,
        ]);

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'affected' => $inserted + $updated,
        ];
    }

    private function isKurmerBatch(array $batch): bool
    {
        $classId = (int) ($batch['kelas_id'] ?? 0);
        if ($classId <= 0) return false;

        $class = Classroom::find($classId);
        return strtolower((string) ($class['kurikulum'] ?? 'k13')) === 'kurmer';
    }

    /**
     * @param array<string, mixed> $scores
     * @return array{0:int,1:int}
     */
    private function commitK13Row(
        int $batchId,
        int $assignmentId,
        int $studentId,
        array $scores,
        array $weights,
        bool $kkmEnabled,
        ?float $kkmValue,
        int $actorUserId,
        int $actorTeacherId,
        string $now
    ): array {
        $nilaiKd = $this->computeK13KdScoreAndSaveDetails(
            $batchId, $assignmentId, $studentId, $scores, $actorUserId, $actorTeacherId, $now
        );
        $nilaiUts = $this->scoreFromPayload($scores, 'uts');
        $nilaiUas = $this->scoreFromPayload($scores, 'uas');
        $nilaiAkhir = $this->computeFinalScore($nilaiKd, $nilaiUts, $nilaiUas, $weights);
        $predikat = $nilaiAkhir !== null
            ? AssessmentEvaluator::determinePredicate($nilaiAkhir, $kkmEnabled, $kkmValue)
            : null;

        $before = StudentKnowledgeAssessment::findForStudent($assignmentId, $studentId);
        $ok = StudentKnowledgeAssessment::upsert($assignmentId, $studentId, $nilaiKd, $nilaiUts, $nilaiUas, $nilaiAkhir, $predikat, null);
        if (!$ok) {
            throw new \RuntimeException('Gagal menyimpan nilai pengetahuan siswa ID ' . $studentId);
        }

        $after = StudentKnowledgeAssessment::findForStudent($assignmentId, $studentId);
        $action = $before === null ? 'INSERT' : 'UPDATE';

        GradeUploadBatchAudit::create([
            'batch_upload_nilai_id' => $batchId,
            'table_name' => 'penilaian_pengetahuan_siswa',
            'record_id' => isset($after['id']) ? (int) $after['id'] : null,
            'siswa_id' => $studentId,
            'action' => $action,
            'before_json' => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'after_json' => $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'actor_user_id' => $actorUserId,
            'actor_teacher_id' => $actorTeacherId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$action === 'INSERT' ? 1 : 0, $action === 'UPDATE' ? 1 : 0];
    }

    /**
     * @param array<string, mixed> $scores
     */
    private function commitKurmerRow(
        int $batchId,
        int $assignmentId,
        int $classId,
        int $studentId,
        array $scores,
        int $actorUserId,
        int $actorTeacherId,
        string $now
    ): void {
        $tpSummarySources = [];
        foreach ($scores as $component => $value) {
            if (!is_string($component) || !str_starts_with($component, 'tp_')) continue;
            if (!preg_match('/^tp_(\d+)_.*_(capaian|nilai)$/', $component, $matches)) continue;

            $tpId = (int) ($matches[1] ?? 0);
            $suffix = (string) ($matches[2] ?? '');
            if ($tpId <= 0) continue;

            if (!isset($tpSummarySources[$tpId])) {
                $tpSummarySources[$tpId] = ['capaian' => null, 'nilai' => null];
            }
            $tpSummarySources[$tpId][$suffix] = $value;
        }

        foreach ($tpSummarySources as $tpId => $payload) {
            $capaian = AssessmentEvaluator::normalizeKurmerCapaian($payload['capaian'] ?? null);
            if ($capaian === null) continue;

            $nilai = AssessmentEvaluator::normalizeScore($payload['nilai'] ?? null);
            $beforeTpMap = StudentTpAssessment::mapByAssignmentAndClass($assignmentId, $classId, [$studentId]);
            $beforeTp = $beforeTpMap[$studentId][$tpId] ?? null;

            StudentTpAssessment::upsert($assignmentId, $classId, $tpId, $studentId, $capaian, $nilai, null);

            $afterTpMap = StudentTpAssessment::mapByAssignmentAndClass($assignmentId, $classId, [$studentId]);
            $afterTp = $afterTpMap[$studentId][$tpId] ?? null;
            $tpAction = $beforeTp === null ? 'INSERT' : 'UPDATE';

            GradeUploadBatchAudit::create([
                'batch_upload_nilai_id' => $batchId,
                'table_name' => 'penilaian_tp_siswa',
                'record_id' => isset($afterTp['id']) ? (int) $afterTp['id'] : null,
                'siswa_id' => $studentId,
                'action' => $tpAction,
                'before_json' => $beforeTp !== null ? json_encode($beforeTp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'after_json' => $afterTp !== null ? json_encode($afterTp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'actor_user_id' => $actorUserId,
                'actor_teacher_id' => $actorTeacherId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $before = StudentKurmerSubjectSummary::byAssignmentAndClass($assignmentId, $classId, [$studentId]);
        $beforeRow = $before[$studentId] ?? null;

        $capaianAkhir = AssessmentEvaluator::normalizeKurmerCapaian($scores['capaian_akhir'] ?? null);
        $deskripsi = isset($scores['deskripsi_umum']) && is_string($scores['deskripsi_umum']) ? trim($scores['deskripsi_umum']) : null;
        $tindakLanjut = isset($scores['tindak_lanjut']) && is_string($scores['tindak_lanjut']) ? trim($scores['tindak_lanjut']) : null;
        $nilaiOpsional = AssessmentEvaluator::normalizeScore($scores['nilai_opsional'] ?? null);

        $ok = StudentKurmerSubjectSummary::upsert($assignmentId, $classId, $studentId, $capaianAkhir, $deskripsi, $tindakLanjut, $nilaiOpsional, null);
        if (!$ok) {
            throw new \RuntimeException('Gagal menyimpan ringkasan Kurmer siswa ID ' . $studentId);
        }

        $after = StudentKurmerSubjectSummary::byAssignmentAndClass($assignmentId, $classId, [$studentId]);
        $afterRow = $after[$studentId] ?? null;
        $action = $beforeRow === null ? 'INSERT' : 'UPDATE';

        GradeUploadBatchAudit::create([
            'batch_upload_nilai_id' => $batchId,
            'table_name' => 'penilaian_kurmer_mapel_siswa',
            'record_id' => isset($afterRow['id']) ? (int) $afterRow['id'] : null,
            'siswa_id' => $studentId,
            'action' => $action,
            'before_json' => $beforeRow !== null ? json_encode($beforeRow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'after_json' => $afterRow !== null ? json_encode($afterRow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'actor_user_id' => $actorUserId,
            'actor_teacher_id' => $actorTeacherId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $scores
     */
    private function scoreFromPayload(array $scores, string $component): ?float
    {
        $value = $scores[$component] ?? null;
        return AssessmentEvaluator::normalizeScore($value);
    }

    /**
     * @param array<string, mixed> $scores
     */
    private function computeK13KdScoreAndSaveDetails(
        int $batchId,
        int $assignmentId,
        int $studentId,
        array $scores,
        int $actorUserId,
        int $actorTeacherId,
        string $now
    ): ?float {
        $kdValues = [];
        foreach ($scores as $component => $value) {
            if (!is_string($component) || !str_starts_with($component, 'kd_')) continue;
            if (!preg_match('/^kd_(\d+)_/', $component, $matches)) continue;

            $kdId = (int) ($matches[1] ?? 0);
            if ($kdId <= 0) continue;

            $score = AssessmentEvaluator::normalizeScore($value);
            if ($score === null) continue;

            $beforeKd = $this->findStudentKdScore($assignmentId, $kdId, $studentId);
            StudentCompetencyScore::upsert($assignmentId, $kdId, $studentId, $score, null);
            $afterKd = $this->findStudentKdScore($assignmentId, $kdId, $studentId);
            $kdAction = $beforeKd === null ? 'INSERT' : 'UPDATE';

            GradeUploadBatchAudit::create([
                'batch_upload_nilai_id' => $batchId,
                'table_name' => 'penilaian_kd_siswa',
                'record_id' => isset($afterKd['id']) ? (int) $afterKd['id'] : null,
                'siswa_id' => $studentId,
                'action' => $kdAction,
                'before_json' => $beforeKd !== null ? json_encode($beforeKd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'after_json' => $afterKd !== null ? json_encode($afterKd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'actor_user_id' => $actorUserId,
                'actor_teacher_id' => $actorTeacherId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $kdValues[] = $score;
        }

        if (empty($kdValues)) return null;
        return round(array_sum($kdValues) / count($kdValues), 2);
    }

    private function findStudentKdScore(int $assignmentId, int $kdId, int $studentId): ?array
    {
        if ($assignmentId <= 0 || $kdId <= 0 || $studentId <= 0) return null;

        $statement = Database::connection()->prepare(
            'SELECT * FROM penilaian_kd_siswa
             WHERE guru_mata_pelajaran_id = :assignment
               AND kd_id = :kd
               AND siswa_id = :student
             LIMIT 1'
        );
        if ($statement === false) return null;

        $statement->bindValue(':assignment', $assignmentId, \PDO::PARAM_INT);
        $statement->bindValue(':kd', $kdId, \PDO::PARAM_INT);
        $statement->bindValue(':student', $studentId, \PDO::PARAM_INT);

        if (!$statement->execute()) return null;
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * @param array{weight_kd: float, weight_uts: float, weight_uas: float} $weights
     */
    private function computeFinalScore(?float $nilaiKd, ?float $nilaiUts, ?float $nilaiUas, array $weights): ?float
    {
        if ($nilaiKd === null && $nilaiUts === null && $nilaiUas === null) return null;

        $kd = $nilaiKd ?? 0.0;
        $uts = $nilaiUts ?? 0.0;
        $uas = $nilaiUas ?? 0.0;

        $final = ($kd * $weights['weight_kd']) + ($uts * $weights['weight_uts']) + ($uas * $weights['weight_uas']);
        return round($final, 2);
    }

    private function rollbackSingleBatch(array $batch, int $actorUserId, int $actorTeacherId): void
    {
        $batchId = (int) ($batch['id'] ?? 0);
        if ($batchId <= 0) return;

        $audits = GradeUploadBatchAudit::byBatch($batchId);
        $conn = Database::connection();
        $now = date('Y-m-d H:i:s');

        try {
            $conn->beginTransaction();

            foreach ($audits as $audit) {
                $table = (string) ($audit['table_name'] ?? '');
                if (!in_array($table, ['penilaian_pengetahuan_siswa', 'penilaian_kd_siswa', 'penilaian_tp_siswa', 'penilaian_kurmer_mapel_siswa'], true)) continue;

                $action = (string) ($audit['action'] ?? '');
                $recordId = isset($audit['record_id']) ? (int) $audit['record_id'] : 0;

                if ($action === 'INSERT' && $recordId > 0) {
                    $this->deleteByTableAndId($table, $recordId);
                    continue;
                }

                if ($action === 'UPDATE') {
                    $before = json_decode((string) ($audit['before_json'] ?? '{}'), true);
                    if (!is_array($before) || empty($before)) continue;

                    $targetId = isset($before['id']) ? (int) $before['id'] : $recordId;
                    if ($targetId <= 0) continue;

                    $this->restoreBeforeStateByTable($table, $targetId, $before, $now);
                }
            }

            GradeUploadBatch::updateById($batchId, [
                'status' => GradeUploadStatus::ROLLED_BACK,
                'rolled_back_at' => $now,
                'rolled_back_by_user_id' => $actorUserId,
                'updated_at' => $now,
            ]);

            $conn->commit();
        } catch (Throwable) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
        }
    }

    private function restoreBeforeStateByTable(string $table, int $targetId, array $before, string $now): bool
    {
        if ($table === 'penilaian_pengetahuan_siswa') {
            return StudentKnowledgeAssessment::updateById($targetId, [
                'nilai_kd' => $before['nilai_kd'] ?? null,
                'nilai_uts' => $before['nilai_uts'] ?? null,
                'nilai_uas' => $before['nilai_uas'] ?? null,
                'nilai_akhir' => $before['nilai_akhir'] ?? null,
                'predikat' => $before['predikat'] ?? null,
                'deskripsi' => $before['deskripsi'] ?? null,
                'updated_at' => $now,
            ]);
        }
        if ($table === 'penilaian_kd_siswa') {
            return StudentCompetencyScore::updateById($targetId, [
                'nilai' => $before['nilai'] ?? null,
                'deskripsi' => $before['deskripsi'] ?? null,
                'updated_at' => $now,
            ]);
        }
        if ($table === 'penilaian_tp_siswa') {
            return StudentTpAssessment::updateById($targetId, [
                'capaian_enum' => $before['capaian_enum'] ?? null,
                'nilai_opsional' => $before['nilai_opsional'] ?? null,
                'catatan' => $before['catatan'] ?? null,
                'updated_at' => $now,
            ]);
        }
        if ($table === 'penilaian_kurmer_mapel_siswa') {
            return StudentKurmerSubjectSummary::updateById($targetId, [
                'capaian_akhir_enum' => $before['capaian_akhir_enum'] ?? null,
                'deskripsi_umum' => $before['deskripsi_umum'] ?? null,
                'tindak_lanjut' => $before['tindak_lanjut'] ?? null,
                'nilai_opsional' => $before['nilai_opsional'] ?? null,
                'sumber_tp' => $before['sumber_tp'] ?? null,
                'updated_at' => $now,
            ]);
        }
        return false;
    }

    private function deleteByTableAndId(string $table, int $id): bool
    {
        if ($id <= 0) return false;
        if ($table === 'penilaian_pengetahuan_siswa') return StudentKnowledgeAssessment::deleteById($id);
        if ($table === 'penilaian_kd_siswa') return StudentCompetencyScore::deleteById($id);
        if ($table === 'penilaian_tp_siswa') return StudentTpAssessment::deleteById($id);
        if ($table === 'penilaian_kurmer_mapel_siswa') return StudentKurmerSubjectSummary::deleteById($id);
        return false;
    }

    private function checkUploadRateLimit(int $teacherId): bool
    {
        if ($teacherId <= 0) return false;

        $key = 'grade_upload_rate_' . $teacherId;
        $history = $_SESSION[$key] ?? [];
        if (!is_array($history)) $history = [];

        $now = time();
        $windowStart = $now - self::UPLOAD_WINDOW_SECONDS;
        $history = array_values(array_filter($history, static fn ($ts): bool => is_int($ts) && $ts >= $windowStart));

        if (count($history) >= self::MAX_UPLOADS_PER_WINDOW) {
            $_SESSION[$key] = $history;
            return false;
        }

        $history[] = $now;
        $_SESSION[$key] = $history;
        return true;
    }

    private function isSpreadsheetMime(string $path): bool
    {
        if (!is_file($path)) return false;

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xls', 'xlsx'], true)) return false;
        if (!function_exists('finfo_open')) return true;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) return true;

        $mime = (string) finfo_file($finfo, $path);
        finfo_close($finfo);

        $allowed = [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream',
        ];

        return in_array(strtolower($mime), $allowed, true);
    }

    private function columnLetter(int $columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $remainder = ($columnNumber - 1) % 26;
            $letter = chr(65 + $remainder) . $letter;
            $columnNumber = (int) (($columnNumber - $remainder) / 26);
        }
        return $letter;
    }
}

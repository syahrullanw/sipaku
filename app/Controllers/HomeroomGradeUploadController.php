<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\GradeUploadBatchAudit;
use App\Models\GradeUploadBatch;
use App\Models\GradeUploadBatchRow;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentCompetencyScore;
use App\Models\StudentKnowledgeAssessment;
use App\Models\StudentKurmerSubjectSummary;
use App\Models\StudentTpAssessment;
use App\Models\SubjectAssessmentSetting;
use App\Models\SubjectTeacher;
use App\Models\SubjectTeacherClass;
use App\Services\AssessmentEvaluator;
use App\Services\GradeRescueGuard;
use App\Services\Import\SpreadsheetImporter;
use App\Support\GradeUploadStatus;
use App\Traits\HandlesImportUpload;
use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Throwable;

class HomeroomGradeUploadController extends Controller
{
    use HandlesImportUpload;

    protected ?string $layout = 'admin';

    private const FIXED_COLUMNS = ['nisn', 'nis', 'nama', 'kurikulum', 'tahun_ajaran', 'semester', 'kelas', 'mapel'];
    private const CONTROL_COLUMNS = ['template_version', 'context_token'];
    private const MAX_FILE_SIZE_BYTES = 2_500_000; // ~2.5MB
    private const MAX_DATA_ROWS = 1000;
    private const MAX_UPLOADS_PER_WINDOW = 10;
    private const UPLOAD_WINDOW_SECONDS = 600;
    private const CONTEXT_READY_SESSION_LIMIT = 25;

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

        $assignments = [];
        if ($selectedClass !== null) {
            $schoolYearId = (int) ($selectedClass['tahun_ajaran_id'] ?? 0);
            $majorId = isset($selectedClass['jurusan_id']) ? (int) $selectedClass['jurusan_id'] : null;

            $assignments = SubjectTeacher::bySchoolYearForClass($schoolYearId, $majorId, $selectedClassId);
            if (empty($assignments)) {
                $assignments = SubjectTeacher::bySchoolYearForClass($schoolYearId, $majorId);
            }
        }

        $selectedAssignmentId = (int) $request->query('assignment_id', 0);
        $recentBatches = GradeUploadBatch::recentByTeacher($teacherId, 40);
        $assignmentHistoryMap = $this->buildAssignmentHistoryMap($teacherId, $recentBatches);
        $assignments = $this->filterAssignmentsAlreadyInHistory($assignments, $selectedClassId, $assignmentHistoryMap, $selectedAssignmentId);

        $selectedAssignment = null;
        foreach ($assignments as $assignment) {
            if ((int) ($assignment['id'] ?? 0) === $selectedAssignmentId) {
                $selectedAssignment = $assignment;
                break;
            }
        }

        if ($selectedClassId > 0 && $selectedAssignmentId > 0) {
            $this->rememberContextReady($teacherId, $selectedClassId, $selectedAssignmentId);
        }

        $contextReadyEntries = $this->buildContextReadyEntries($teacherId, $recentBatches);

        return $this->render('homeroom/grade-upload/index', [
            'title' => 'Upload Nilai Rescue',
            'pageTitle' => 'Upload Nilai Rescue',
            'activeMenu' => 'homeroom-grade-upload',
            'classes' => $classes,
            'selectedClassId' => $selectedClassId > 0 ? $selectedClassId : null,
            'selectedClass' => $selectedClass,
            'assignments' => $assignments,
            'selectedAssignmentId' => $selectedAssignmentId > 0 ? $selectedAssignmentId : null,
            'selectedAssignment' => $selectedAssignment,
            'recentBatches' => $recentBatches,
            'contextReadyEntries' => $contextReadyEntries,
            'resumeBatchCode' => trim((string) $request->query('batch_code', '')),
        ]);
    }

    public function assignmentsByClass(Request $request): Response
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

        $majorId = isset($class['jurusan_id']) ? (int) $class['jurusan_id'] : null;
        $assignments = SubjectTeacher::bySchoolYearForClass($schoolYearId, $majorId, $classId);
        if (empty($assignments)) {
            $assignments = SubjectTeacher::bySchoolYearForClass($schoolYearId, $majorId);
        }

        $includeAssignmentId = (int) $request->query('include_assignment_id', 0);
        $recentBatches = GradeUploadBatch::recentByTeacher($teacherId, 40);
        $assignmentHistoryMap = $this->buildAssignmentHistoryMap($teacherId, $recentBatches);
        $assignments = $this->filterAssignmentsAlreadyInHistory($assignments, $classId, $assignmentHistoryMap, $includeAssignmentId);

        $options = [];
        foreach ($assignments as $assignment) {
            $assignmentId = (int) ($assignment['id'] ?? 0);
            if ($assignmentId <= 0) {
                continue;
            }
            $code = trim((string) ($assignment['mata_pelajaran_kode'] ?? ''));
            $name = trim((string) ($assignment['mata_pelajaran_nama'] ?? 'Mapel'));
            $label = $code !== '' ? ($code . ' - ' . $name) : $name;
            $options[] = [
                'id' => $assignmentId,
                'label' => $label,
            ];
        }

        return $this->json([
            'ok' => true,
            'options' => $options,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $assignments
     * @param array<string, bool> $historyMap
     * @return array<int, array<string, mixed>>
     */
    private function filterAssignmentsAlreadyInHistory(array $assignments, int $classId, array $historyMap, int $keepAssignmentId = 0): array
    {
        if ($classId <= 0 || empty($assignments) || empty($historyMap)) {
            return $assignments;
        }

        $filtered = [];
        foreach ($assignments as $assignment) {
            $assignmentId = (int) ($assignment['id'] ?? 0);
            if ($assignmentId <= 0) {
                continue;
            }

            if ($keepAssignmentId > 0 && $assignmentId === $keepAssignmentId) {
                $filtered[] = $assignment;
                continue;
            }

            if (isset($historyMap[$classId . ':' . $assignmentId])) {
                continue;
            }

            $filtered[] = $assignment;
        }

        return $filtered;
    }

    /**
     * @param array<int, array<string, mixed>> $recentBatches
     * @return array<string, bool>
     */
    private function buildAssignmentHistoryMap(int $teacherId, array $recentBatches = []): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $map = [];

        foreach ($recentBatches as $batch) {
            if (!is_array($batch)) {
                continue;
            }

            $classId = (int) ($batch['kelas_id'] ?? 0);
            $assignmentId = (int) ($batch['guru_mata_pelajaran_id'] ?? 0);
            if ($classId <= 0 || $assignmentId <= 0) {
                continue;
            }

            $map[$classId . ':' . $assignmentId] = true;
        }

        $entries = Session::get($this->contextReadySessionKey($teacherId), []);
        if (!is_array($entries)) {
            return $map;
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $classId = (int) ($entry['kelas_id'] ?? 0);
            $assignmentId = (int) ($entry['assignment_id'] ?? 0);
            if ($classId <= 0 || $assignmentId <= 0) {
                continue;
            }

            $map[$classId . ':' . $assignmentId] = true;
        }

        return $map;
    }

    public function validateUpload(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/nilai-upload')) {
            return $response;
        }

        $user = auth();
        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            return $this->json(['ok' => false, 'message' => 'Hanya wali kelas yang dapat mengunggah nilai.'], 403);
        }

        $teacherId = (int) $user['teacher_id'];
        $classId = (int) $request->input('kelas_id', 0);
        $assignmentId = (int) $request->input('assignment_id', 0);
        $requestId = GradeRescueGuard::buildRequestId($request);
        $targetStatus = $this->normalizeUploadTargetStatus((string) $request->input('desired_status', GradeUploadStatus::DRAFT));

        $upload = $request->file('import_file');
        if (!is_array($upload)) {
            return $this->json(['ok' => false, 'message' => 'File upload tidak ditemukan.'], 422);
        }

        if (!$this->checkUploadRateLimit($teacherId)) {
            return $this->json([
                'ok' => false,
                'message' => 'Terlalu banyak percobaan upload. Coba kembali beberapa menit lagi.',
            ], 429);
        }

        $fileSize = isset($upload['size']) ? (int) $upload['size'] : 0;
        if ($fileSize <= 0 || $fileSize > self::MAX_FILE_SIZE_BYTES) {
            return $this->json([
                'ok' => false,
                'message' => 'Ukuran file tidak valid. Maksimal 2.5MB.',
            ], 422);
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
            $rows = SpreadsheetImporter::readRows($path);
            $parsed = $this->parseTemplateRows($rows);
            if (!$parsed['ok']) {
                return $this->json(['ok' => false, 'message' => $parsed['message']], 422);
            }

            $templateRows = $parsed['rows'];
            $components = $parsed['components'];
            $fileTemplateVersion = $parsed['template_version'];
            $contextToken = $parsed['context_token'];
            $decodedContext = $this->decodeContextToken($contextToken);

            if (!is_array($decodedContext)) {
                return $this->json(['ok' => false, 'message' => 'CONTEXT_TOKEN tidak valid. Unduh ulang template terbaru.'], 422);
            }

            $tokenClassId = (int) ($decodedContext['class_id'] ?? 0);
            $tokenAssignmentId = (int) ($decodedContext['assignment_id'] ?? 0);
            $tokenSchoolYearId = (int) ($decodedContext['school_year_id'] ?? 0);
            $tokenSemester = strtolower(trim((string) ($decodedContext['semester'] ?? '')));

            if ($tokenClassId <= 0 || $tokenAssignmentId <= 0 || $tokenSchoolYearId <= 0 || $tokenSemester === '') {
                return $this->json(['ok' => false, 'message' => 'CONTEXT_TOKEN tidak lengkap. Unduh ulang template terbaru.'], 422);
            }

            $classId = $tokenClassId;
            $assignmentId = $tokenAssignmentId;

            $class = Classroom::findWithRelations($classId);
            $assignment = SubjectTeacher::findWithRelations($assignmentId);

            if ($class === null || $assignment === null) {
                return $this->json(['ok' => false, 'message' => 'Kelas atau mapel dari template tidak ditemukan.'], 404);
            }

            $schoolYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
            $semester = $this->resolveSemesterText($class, $assignment);
            if ($schoolYearId !== $tokenSchoolYearId || strtolower($semester) !== $tokenSemester) {
                return $this->json(['ok' => false, 'message' => 'Konteks template sudah tidak sesuai dengan data aktif. Unduh ulang template terbaru.'], 422);
            }

            if (!GradeRescueGuard::canTeacherAccessHomeroomClass($teacherId, $classId, $schoolYearId)) {
                return $this->json(['ok' => false, 'message' => 'Anda tidak memiliki akses ke kelas ini.'], 403);
            }

            $assignmentClassIds = SubjectTeacherClass::classIds($assignmentId);
            if (!in_array($classId, $assignmentClassIds, true)) {
                return $this->json(['ok' => false, 'message' => 'Mapel tidak terdaftar di kelas yang dipilih.'], 422);
            }

            if (!GradeRescueGuard::canRescueInput($schoolYearId, $semester)) {
                return $this->json(['ok' => false, 'message' => 'Periode rescue input nilai belum aktif.'], 422);
            }

            $existingBatch = GradeUploadBatch::findLatestByContext(
                $teacherId,
                $schoolYearId,
                $semester,
                $classId,
                $assignmentId
            );
            if ($existingBatch !== null) {
                $existingStatus = strtoupper(trim((string) ($existingBatch['status'] ?? '')));
                if (in_array($existingStatus, [GradeUploadStatus::FINAL, GradeUploadStatus::COMMITTED], true)) {
                    return $this->json([
                        'ok' => false,
                        'message' => 'Nilai untuk kelas dan mapel ini sudah final. Draft baru hanya bisa diupload jika status sebelumnya belum final.',
                        'existing_batch' => [
                            'batch_code' => (string) ($existingBatch['batch_code'] ?? ''),
                            'status' => $existingStatus,
                        ],
                    ], 409);
                }
            }

            if (count($templateRows) > self::MAX_DATA_ROWS) {
                return $this->json([
                    'ok' => false,
                    'message' => 'Jumlah baris melebihi batas maksimal ' . self::MAX_DATA_ROWS . ' baris.',
                ], 422);
            }

            if (!$this->validateTemplateControl($fileTemplateVersion, $contextToken, $schoolYearId, $semester, $classId, $assignmentId)) {
                return $this->json(['ok' => false, 'message' => 'Template tidak sesuai konteks. Unduh ulang template terbaru.'], 422);
            }

            $studentRows = Student::byClass($classId, $schoolYearId);
            $studentIndex = $this->buildStudentIndex($studentRows);

            $subjectLabel = trim((string) ($assignment['mata_pelajaran_nama'] ?? ''));
            if (($assignment['mata_pelajaran_kode'] ?? '') !== '') {
                $subjectLabel .= ' (' . trim((string) $assignment['mata_pelajaran_kode']) . ')';
            }

            $classLabel = trim((string) (($class['tingkat'] ?? '') . ' ' . ($class['nama'] ?? '')));
            $curriculum = strtoupper(trim((string) ($class['kurikulum'] ?? 'K13')));
            $yearLabel = trim((string) ($class['tahun_ajaran_nama'] ?? ''));

            $batchCode = 'BATCH-' . strtoupper(substr(sha1((string) microtime(true) . '|' . $teacherId . '|' . $classId . '|' . $assignmentId), 0, 14));
            $now = date('Y-m-d H:i:s');
            $connection = Database::connection();
            $connection->beginTransaction();

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
                'template_version' => $fileTemplateVersion,
                'status' => GradeUploadStatus::VALIDATING,
                'total_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'request_id' => $requestId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($batchId === null || $batchId <= 0) {
                throw new \RuntimeException('Gagal membuat batch upload.');
            }

            $seen = [];
            $validCount = 0;
            $invalidCount = 0;
            $rowNo = 0;
            $errorPreview = [];

            foreach ($templateRows as $row) {
                $rowNo++;
                $errors = [];

                $nisn = trim((string) ($row['nisn'] ?? ''));
                $nis = trim((string) ($row['nis'] ?? ''));
                $name = trim((string) ($row['nama'] ?? ''));

                $studentId = $this->resolveStudentId($nisn, $nis, $studentIndex);
                if ($studentId === null) {
                    $errors[] = 'Siswa tidak ditemukan di kelas berdasarkan NISN/NIS.';
                }

                if (strtoupper(trim((string) ($row['kurikulum'] ?? ''))) !== $curriculum) {
                    $errors[] = 'KURIKULUM tidak sesuai template.';
                }
                if (trim((string) ($row['tahun_ajaran'] ?? '')) !== $yearLabel) {
                    $errors[] = 'TAHUN_AJARAN tidak sesuai template.';
                }
                if (strtolower(trim((string) ($row['semester'] ?? ''))) !== $semester) {
                    $errors[] = 'SEMESTER tidak sesuai template.';
                }
                if (trim((string) ($row['kelas'] ?? '')) !== $classLabel) {
                    $errors[] = 'KELAS tidak sesuai template.';
                }
                if (trim((string) ($row['mapel'] ?? '')) !== $subjectLabel) {
                    $errors[] = 'MAPEL tidak sesuai template.';
                }

                if ($studentId !== null) {
                    $key = $studentId . '|' . $assignmentId . '|' . $semester;
                    if (isset($seen[$key])) {
                        $errors[] = 'Duplikat baris siswa-mapel-semester pada file.';
                    }
                    $seen[$key] = true;
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
                            $errors[] = sprintf('Nilai %s harus BB/MB/BSH/SB.', strtoupper($component));
                            continue;
                        }
                        $payloadScores[$component] = $capaian;
                        continue;
                    }

                    if ($curriculumLower === 'kurmer' && $component === 'capaian_akhir') {
                        $capaian = AssessmentEvaluator::normalizeKurmerCapaian($valueRaw);
                        if ($capaian === null) {
                            $errors[] = 'Nilai CAPAIAN_AKHIR harus BB/MB/BSH/SB.';
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
                        $errors[] = sprintf('Nilai %s harus berupa angka.', strtoupper($component));
                        continue;
                    }

                    $score = (float) str_replace(',', '.', $valueRaw);
                    if ($score < 0 || $score > 100) {
                        $errors[] = sprintf('Nilai %s harus di rentang 0-100.', strtoupper($component));
                        continue;
                    }

                    $payloadScores[$component] = $score;
                }

                $isValid = empty($errors);
                if ($isValid) {
                    $validCount++;
                } else {
                    $invalidCount++;
                    if (count($errorPreview) < 100) {
                        $errorPreview[] = [
                            'row_no' => $rowNo,
                            'nisn' => $nisn,
                            'nis' => $nis,
                            'nama' => $name,
                            'errors' => $errors,
                        ];
                    }
                }

                GradeUploadBatchRow::create([
                    'batch_upload_nilai_id' => $batchId,
                    'row_no' => $rowNo,
                    'siswa_id' => $studentId,
                    'is_valid' => $isValid ? 1 : 0,
                    'error_messages' => $isValid ? null : json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'payload_json' => json_encode([
                        'row' => $row,
                        'scores' => $payloadScores,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $status = $invalidCount > 0 ? GradeUploadStatus::FAILED : GradeUploadStatus::VALIDATED;
            GradeUploadBatch::updateById($batchId, [
                'status' => $status,
                'total_rows' => $rowNo,
                'valid_rows' => $validCount,
                'invalid_rows' => $invalidCount,
                'updated_at' => $now,
            ]);

            $connection->commit();

            GradeRescueGuard::log('Validated grade upload batch', [
                'request_id' => $requestId,
                'batch_code' => $batchCode,
                'batch_id' => $batchId,
                'teacher_id' => $teacherId,
                'user_id' => (int) ($user['id'] ?? 0),
                'kelas_id' => $classId,
                'assignment_id' => $assignmentId,
                'total_rows' => $rowNo,
                'valid_rows' => $validCount,
                'invalid_rows' => $invalidCount,
                'status' => $status,
            ]);

            if ($invalidCount > 0) {
                return $this->json([
                    'ok' => true,
                    'message' => 'File sudah dibaca, tetapi masih ada data yang perlu diperbaiki. Silakan revisi file yang sama lalu upload lagi.',
                    'batch' => [
                        'batch_id' => $batchId,
                        'batch_code' => $batchCode,
                        'status' => $status,
                        'status_label' => GradeUploadStatus::label($status),
                        'template_version' => $fileTemplateVersion,
                        'request_id' => $requestId,
                        'can_finalize' => false,
                        'can_reupload' => true,
                    ],
                    'summary' => [
                        'total_rows' => $rowNo,
                        'valid_rows' => $validCount,
                        'invalid_rows' => $invalidCount,
                        'components' => array_map(static fn (string $c): string => strtoupper($c), $components),
                    ],
                    'errors' => $errorPreview,
                ]);
            }

            if ($existingBatch !== null && strtoupper(trim((string) ($existingBatch['status'] ?? ''))) === GradeUploadStatus::DRAFT) {
                $this->rollbackBatchData($existingBatch, (int) ($user['id'] ?? 0), $teacherId);
            }

            $savedBatch = GradeUploadBatch::findByBatchCode($batchCode);
            if ($savedBatch === null) {
                throw new \RuntimeException('Batch upload tidak ditemukan setelah validasi.');
            }

            $result = $this->applyBatchData(
                $savedBatch,
                (int) ($user['id'] ?? 0),
                $teacherId,
                $targetStatus
            );

            return $this->json([
                'ok' => true,
                'message' => $targetStatus === GradeUploadStatus::FINAL
                    ? 'Nilai berhasil diupload dan langsung disimpan sebagai final.'
                    : 'Nilai berhasil diupload sebagai draft. Anda masih bisa memperbaiki file yang sama lalu upload ulang.',
                'batch' => [
                    'batch_id' => $batchId,
                    'batch_code' => $batchCode,
                    'status' => $targetStatus,
                    'status_label' => GradeUploadStatus::label($targetStatus),
                    'template_version' => $fileTemplateVersion,
                    'request_id' => $requestId,
                    'can_finalize' => $targetStatus === GradeUploadStatus::DRAFT,
                    'can_reupload' => $targetStatus === GradeUploadStatus::DRAFT,
                ],
                'summary' => [
                    'total_rows' => $rowNo,
                    'valid_rows' => $validCount,
                    'invalid_rows' => $invalidCount,
                    'components' => array_map(static fn (string $c): string => strtoupper($c), $components),
                ],
                'result' => $result,
                'errors' => $errorPreview,
            ]);
        } catch (Throwable $exception) {
            GradeRescueGuard::log('Validation failed', [
                'request_id' => $requestId,
                'teacher_id' => $teacherId,
                'kelas_id' => $classId,
                'assignment_id' => $assignmentId,
                'error' => $exception->getMessage(),
            ]);

            return $this->json(['ok' => false, 'message' => 'Gagal memproses file: ' . $exception->getMessage()], 500);
        } finally {
            if (is_file($path ?? '')) {
                @unlink($path);
            }
        }
    }

    public function finalizeDraft(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/nilai-upload')) {
            return $response;
        }

        $user = auth();
        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            return $this->json(['ok' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $teacherId = (int) $user['teacher_id'];
        $batchCode = trim((string) $request->input('batch_code', ''));
        if ($batchCode === '') {
            return $this->json(['ok' => false, 'message' => 'Kode upload wajib diisi.'], 422);
        }

        $batch = GradeUploadBatch::findByBatchCode($batchCode);
        if ($batch === null) {
            return $this->json(['ok' => false, 'message' => 'Data upload tidak ditemukan.'], 404);
        }

        $classId = (int) ($batch['kelas_id'] ?? 0);
        $schoolYearId = (int) ($batch['tahun_ajaran_id'] ?? 0);
        if (!GradeRescueGuard::canTeacherAccessHomeroomClass($teacherId, $classId, $schoolYearId)) {
            return $this->json(['ok' => false, 'message' => 'Anda tidak memiliki akses ke data ini.'], 403);
        }

        $status = strtoupper(trim((string) ($batch['status'] ?? '')));
        if (in_array($status, [GradeUploadStatus::FINAL, GradeUploadStatus::COMMITTED], true)) {
            return $this->json([
                'ok' => true,
                'message' => 'Nilai ini sudah berstatus final.',
                'batch' => [
                    'batch_code' => $batchCode,
                    'status' => GradeUploadStatus::FINAL,
                    'status_label' => GradeUploadStatus::label(GradeUploadStatus::FINAL),
                    'can_finalize' => false,
                    'can_reopen' => true,
                    'can_reupload' => false,
                ],
            ]);
        }

        if ($status !== GradeUploadStatus::DRAFT) {
            return $this->json(['ok' => false, 'message' => 'Hanya upload berstatus draft yang bisa dijadikan final.'], 422);
        }

        $now = date('Y-m-d H:i:s');
        GradeUploadBatch::updateById((int) ($batch['id'] ?? 0), [
            'status' => GradeUploadStatus::FINAL,
            'committed_at' => $batch['committed_at'] ?? $now,
            'updated_at' => $now,
        ]);

        return $this->json([
            'ok' => true,
            'message' => 'Status nilai berhasil diubah menjadi final.',
            'batch' => [
                'batch_code' => $batchCode,
                'status' => GradeUploadStatus::FINAL,
                'status_label' => GradeUploadStatus::label(GradeUploadStatus::FINAL),
                'can_finalize' => false,
                'can_reopen' => true,
                'can_reupload' => false,
            ],
        ]);
    }

    public function reopenFinal(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/nilai-upload')) {
            return $response;
        }

        $user = auth();
        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            return $this->json(['ok' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $teacherId = (int) $user['teacher_id'];
        $batchCode = trim((string) $request->input('batch_code', ''));
        if ($batchCode === '') {
            return $this->json(['ok' => false, 'message' => 'Kode upload wajib diisi.'], 422);
        }

        $batch = GradeUploadBatch::findByBatchCode($batchCode);
        if ($batch === null) {
            return $this->json(['ok' => false, 'message' => 'Data upload tidak ditemukan.'], 404);
        }

        $classId = (int) ($batch['kelas_id'] ?? 0);
        $schoolYearId = (int) ($batch['tahun_ajaran_id'] ?? 0);
        if (!GradeRescueGuard::canTeacherAccessHomeroomClass($teacherId, $classId, $schoolYearId)) {
            return $this->json(['ok' => false, 'message' => 'Anda tidak memiliki akses ke data ini.'], 403);
        }

        $status = strtoupper(trim((string) ($batch['status'] ?? '')));
        if (!in_array($status, [GradeUploadStatus::FINAL, GradeUploadStatus::COMMITTED], true)) {
            return $this->json(['ok' => false, 'message' => 'Hanya nilai yang sudah final yang bisa dibuka lagi untuk revisi.'], 422);
        }

        $now = date('Y-m-d H:i:s');
        GradeUploadBatch::updateById((int) ($batch['id'] ?? 0), [
            'status' => GradeUploadStatus::DRAFT,
            'updated_at' => $now,
        ]);

        return $this->json([
            'ok' => true,
            'message' => 'Nilai berhasil dibuka lagi untuk revisi. Status sekarang menjadi draft.',
            'batch' => [
                'batch_code' => $batchCode,
                'status' => GradeUploadStatus::DRAFT,
                'status_label' => GradeUploadStatus::label(GradeUploadStatus::DRAFT),
                'can_finalize' => true,
                'can_reopen' => false,
                'can_reupload' => true,
            ],
        ]);
    }

    public function preview(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();
        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            return $this->json(['ok' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $batchCode = trim((string) $request->query('batch_code', ''));
        if ($batchCode === '') {
            return $this->json(['ok' => false, 'message' => 'batch_code wajib diisi.'], 422);
        }

        $batch = GradeUploadBatch::findByBatchCode($batchCode);
        if ($batch === null) {
            return $this->json(['ok' => false, 'message' => 'Batch tidak ditemukan.'], 404);
        }

        $teacherId = (int) $user['teacher_id'];
        $classId = (int) ($batch['kelas_id'] ?? 0);
        $schoolYearId = (int) ($batch['tahun_ajaran_id'] ?? 0);
        if (!GradeRescueGuard::canTeacherAccessHomeroomClass($teacherId, $classId, $schoolYearId)) {
            return $this->json(['ok' => false, 'message' => 'Anda tidak memiliki akses ke batch ini.'], 403);
        }

        $rows = GradeUploadBatchRow::byBatch((int) ($batch['id'] ?? 0), true);
        $errors = [];
        foreach ($rows as $row) {
            $messages = json_decode((string) ($row['error_messages'] ?? '[]'), true);
            if (!is_array($messages)) {
                $messages = [];
            }

            $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
            $raw = is_array($payload) && isset($payload['row']) && is_array($payload['row']) ? $payload['row'] : [];
            $errors[] = [
                'row_no' => (int) ($row['row_no'] ?? 0),
                'nisn' => (string) ($raw['nisn'] ?? ''),
                'nis' => (string) ($raw['nis'] ?? ''),
                'nama' => (string) ($raw['nama'] ?? ''),
                'errors' => $messages,
            ];
        }

        return $this->json([
            'ok' => true,
            'batch' => [
                'batch_id' => (int) ($batch['id'] ?? 0),
                'batch_code' => (string) ($batch['batch_code'] ?? ''),
                'status' => (string) ($batch['status'] ?? ''),
                'status_label' => GradeUploadStatus::label((string) ($batch['status'] ?? '')),
                'template_version' => (string) ($batch['template_version'] ?? ''),
                'request_id' => (string) ($batch['request_id'] ?? ''),
            ],
            'summary' => [
                'total_rows' => (int) ($batch['total_rows'] ?? 0),
                'valid_rows' => (int) ($batch['valid_rows'] ?? 0),
                'invalid_rows' => (int) ($batch['invalid_rows'] ?? 0),
            ],
            'errors' => $errors,
        ]);
    }

    public function commit(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/nilai-upload')) {
            return $response;
        }

        $user = auth();
        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            return $this->json(['ok' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $teacherId = (int) $user['teacher_id'];
        $batchCode = trim((string) $request->input('batch_code', ''));
        $allowPartial = (int) $request->input('allow_partial', 0) === 1;

        if ($batchCode === '') {
            return $this->json(['ok' => false, 'message' => 'batch_code wajib diisi.'], 422);
        }

        $batch = GradeUploadBatch::findByBatchCode($batchCode);
        if ($batch === null) {
            return $this->json(['ok' => false, 'message' => 'Batch tidak ditemukan.'], 404);
        }

        $classId = (int) ($batch['kelas_id'] ?? 0);
        $schoolYearId = (int) ($batch['tahun_ajaran_id'] ?? 0);
        if (!GradeRescueGuard::canTeacherAccessHomeroomClass($teacherId, $classId, $schoolYearId)) {
            return $this->json(['ok' => false, 'message' => 'Anda tidak memiliki akses ke batch ini.'], 403);
        }

        $status = strtoupper(trim((string) ($batch['status'] ?? '')));
        if (!in_array($status, [GradeUploadStatus::VALIDATED, GradeUploadStatus::FAILED], true)) {
            return $this->json([
                'ok' => false,
                'message' => 'Batch tidak berada pada status yang bisa di-commit. Status saat ini: ' . ($status !== '' ? $status : '-'),
            ], 422);
        }

        $invalidRows = (int) ($batch['invalid_rows'] ?? 0);
        if ($invalidRows > 0 && !$allowPartial) {
            return $this->json([
                'ok' => false,
                'message' => 'Batch masih memiliki baris invalid. Set allow_partial=1 jika ingin commit baris valid saja.',
            ], 422);
        }

        try {
            $result = $this->applyBatchData(
                $batch,
                (int) ($user['id'] ?? 0),
                $teacherId,
                GradeUploadStatus::COMMITTED,
                $allowPartial
            );
        } catch (Throwable $exception) {
            return $this->json(['ok' => false, 'message' => 'Commit gagal: ' . $exception->getMessage()], 500);
        }

        return $this->json([
            'ok' => true,
            'message' => 'Commit berhasil.',
            'batch' => [
                'batch_id' => (int) ($batch['id'] ?? 0),
                'batch_code' => $batchCode,
                'status' => GradeUploadStatus::COMMITTED,
            ],
            'result' => $result,
        ]);
    }

    public function rollback(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/nilai-upload')) {
            return $response;
        }

        $user = auth();
        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            return $this->json(['ok' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $batchCode = trim((string) $request->input('batch_code', ''));
        if ($batchCode === '') {
            return $this->json(['ok' => false, 'message' => 'batch_code wajib diisi.'], 422);
        }

        $batch = GradeUploadBatch::findByBatchCode($batchCode);
        if ($batch === null) {
            return $this->json(['ok' => false, 'message' => 'Batch tidak ditemukan.'], 404);
        }

        $teacherId = (int) $user['teacher_id'];
        $classId = (int) ($batch['kelas_id'] ?? 0);
        $schoolYearId = (int) ($batch['tahun_ajaran_id'] ?? 0);
        if (!GradeRescueGuard::canTeacherAccessHomeroomClass($teacherId, $classId, $schoolYearId)) {
            return $this->json(['ok' => false, 'message' => 'Anda tidak memiliki akses ke batch ini.'], 403);
        }

        if ((string) ($batch['status'] ?? '') !== GradeUploadStatus::COMMITTED) {
            return $this->json(['ok' => false, 'message' => 'Hanya batch COMMITTED yang bisa di-rollback.'], 422);
        }

        $audits = GradeUploadBatchAudit::byBatch((int) ($batch['id'] ?? 0));
        if (empty($audits)) {
            return $this->json(['ok' => false, 'message' => 'Audit batch kosong, rollback tidak bisa dilakukan.'], 422);
        }

        $conn = Database::connection();
        $now = date('Y-m-d H:i:s');
        $rolledBack = 0;

        try {
            $conn->beginTransaction();

            for ($i = count($audits) - 1; $i >= 0; $i--) {
                $audit = $audits[$i];
                $table = (string) ($audit['table_name'] ?? '');
                if (!in_array($table, ['penilaian_pengetahuan_siswa', 'penilaian_kd_siswa', 'penilaian_tp_siswa', 'penilaian_kurmer_mapel_siswa'], true)) {
                    continue;
                }

                $action = (string) ($audit['action'] ?? '');
                $recordId = isset($audit['record_id']) ? (int) $audit['record_id'] : 0;

                if ($action === 'INSERT') {
                    if ($recordId > 0) {
                        $this->deleteByTableAndId($table, $recordId);
                        $rolledBack++;
                    }
                    continue;
                }

                if ($action === 'UPDATE') {
                    $before = json_decode((string) ($audit['before_json'] ?? '{}'), true);
                    if (!is_array($before) || empty($before)) {
                        continue;
                    }

                    $targetId = isset($before['id']) ? (int) $before['id'] : $recordId;
                    if ($targetId <= 0) {
                        continue;
                    }

                    if ($this->restoreBeforeStateByTable($table, $targetId, $before, $now)) {
                        $rolledBack++;
                    }
                }
            }

            GradeUploadBatch::updateById((int) $batch['id'], [
                'status' => GradeUploadStatus::ROLLED_BACK,
                'rolled_back_at' => $now,
                'rolled_back_by_user_id' => (int) ($user['id'] ?? 0),
                'updated_at' => $now,
            ]);

            $conn->commit();
        } catch (Throwable $exception) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            return $this->json(['ok' => false, 'message' => 'Rollback gagal: ' . $exception->getMessage()], 500);
        }

        GradeRescueGuard::log('Rolled back grade upload batch', [
            'batch_code' => $batchCode,
            'batch_id' => (int) ($batch['id'] ?? 0),
            'teacher_id' => $teacherId,
            'user_id' => (int) ($user['id'] ?? 0),
            'affected' => $rolledBack,
        ]);

        return $this->json([
            'ok' => true,
            'message' => 'Rollback batch berhasil.',
            'batch' => [
                'batch_id' => (int) ($batch['id'] ?? 0),
                'batch_code' => $batchCode,
                'status' => GradeUploadStatus::ROLLED_BACK,
            ],
            'result' => [
                'affected' => $rolledBack,
            ],
        ]);
    }

    private function normalizeUploadTargetStatus(string $status): string
    {
        $normalized = strtoupper(trim($status));

        return $normalized === GradeUploadStatus::FINAL ? GradeUploadStatus::FINAL : GradeUploadStatus::DRAFT;
    }

    /**
     * @param array<string, mixed> $batch
     * @return array{inserted:int,updated:int,affected:int}
     */
    private function applyBatchData(
        array $batch,
        int $actorUserId,
        int $actorTeacherId,
        string $finalStatus,
        bool $allowPartial = false
    ): array {
        $assignmentId = (int) ($batch['guru_mata_pelajaran_id'] ?? 0);
        $setting = SubjectAssessmentSetting::ensureDefault($assignmentId);
        $weights = SubjectAssessmentSetting::resolveWeights($setting);
        $kkmEnabled = (int) ($setting['enable_kkm'] ?? 0) === 1;
        $kkmValue = isset($setting['nilai_kkm']) ? (float) $setting['nilai_kkm'] : null;
        $isKurmer = $this->isKurmerBatch($batch);

        // Selalu simpan hanya baris yang valid.
        // Parameter kedua `byBatch()` berarti "hanya invalid", bukan "allow partial".
        $rows = GradeUploadBatchRow::byBatch((int) ($batch['id'] ?? 0), false);
        if (empty($rows)) {
            throw new \RuntimeException('Tidak ada baris valid untuk disimpan.');
        }

        if (!$allowPartial && count($rows) !== (int) ($batch['valid_rows'] ?? 0)) {
            throw new \RuntimeException('Inkonsistensi data batch terdeteksi. Upload ulang file lalu coba lagi.');
        }

        $conn = Database::connection();
        $now = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        try {
            $conn->beginTransaction();

            foreach ($rows as $row) {
                $studentId = (int) ($row['siswa_id'] ?? 0);
                if ($studentId <= 0) {
                    continue;
                }

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

        GradeRescueGuard::log('Committed grade upload batch', [
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

    /**
     * @param array<string, mixed> $batch
     */
    private function rollbackBatchData(array $batch, int $actorUserId, int $actorTeacherId): int
    {
        $batchId = (int) ($batch['id'] ?? 0);
        if ($batchId <= 0) {
            return 0;
        }

        $audits = GradeUploadBatchAudit::byBatch($batchId);
        if (empty($audits)) {
            GradeUploadBatch::updateById($batchId, [
                'status' => GradeUploadStatus::ROLLED_BACK,
                'rolled_back_at' => date('Y-m-d H:i:s'),
                'rolled_back_by_user_id' => $actorUserId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return 0;
        }

        $conn = Database::connection();
        $now = date('Y-m-d H:i:s');
        $rolledBack = 0;

        try {
            $conn->beginTransaction();

            for ($i = count($audits) - 1; $i >= 0; $i--) {
                $audit = $audits[$i];
                $table = (string) ($audit['table_name'] ?? '');
                if (!in_array($table, ['penilaian_pengetahuan_siswa', 'penilaian_kd_siswa', 'penilaian_tp_siswa', 'penilaian_kurmer_mapel_siswa'], true)) {
                    continue;
                }

                $action = (string) ($audit['action'] ?? '');
                $recordId = isset($audit['record_id']) ? (int) $audit['record_id'] : 0;

                if ($action === 'INSERT') {
                    if ($recordId > 0 && $this->deleteByTableAndId($table, $recordId)) {
                        $rolledBack++;
                    }
                    continue;
                }

                if ($action !== 'UPDATE') {
                    continue;
                }

                $before = json_decode((string) ($audit['before_json'] ?? '{}'), true);
                if (!is_array($before) || empty($before)) {
                    continue;
                }

                $targetId = isset($before['id']) ? (int) $before['id'] : $recordId;
                if ($targetId <= 0) {
                    continue;
                }

                if ($this->restoreBeforeStateByTable($table, $targetId, $before, $now)) {
                    $rolledBack++;
                }
            }

            GradeUploadBatch::updateById($batchId, [
                'status' => GradeUploadStatus::ROLLED_BACK,
                'rolled_back_at' => $now,
                'rolled_back_by_user_id' => $actorUserId,
                'updated_at' => $now,
            ]);

            $conn->commit();
        } catch (Throwable $exception) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            throw $exception;
        }

        GradeRescueGuard::log('Rolled back grade upload batch', [
            'batch_code' => (string) ($batch['batch_code'] ?? ''),
            'batch_id' => $batchId,
            'teacher_id' => $actorTeacherId,
            'user_id' => $actorUserId,
            'affected' => $rolledBack,
        ]);

        return $rolledBack;
    }

    /**
     * @param array<string, mixed> $before
     */
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
        if ($id <= 0) {
            return false;
        }

        if ($table === 'penilaian_pengetahuan_siswa') {
            return StudentKnowledgeAssessment::deleteById($id);
        }

        if ($table === 'penilaian_kd_siswa') {
            return StudentCompetencyScore::deleteById($id);
        }

        if ($table === 'penilaian_tp_siswa') {
            return StudentTpAssessment::deleteById($id);
        }

        if ($table === 'penilaian_kurmer_mapel_siswa') {
            return StudentKurmerSubjectSummary::deleteById($id);
        }

        return false;
    }

    public function batchStatus(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();
        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            return $this->json(['ok' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $batchCode = trim((string) $request->query('batch_code', ''));
        if ($batchCode === '') {
            return $this->json(['ok' => false, 'message' => 'batch_code wajib diisi.'], 422);
        }

        $batch = GradeUploadBatch::findByBatchCode($batchCode);
        if ($batch === null) {
            return $this->json(['ok' => false, 'message' => 'Batch tidak ditemukan.'], 404);
        }

        $teacherId = (int) $user['teacher_id'];
        $classId = (int) ($batch['kelas_id'] ?? 0);
        $schoolYearId = (int) ($batch['tahun_ajaran_id'] ?? 0);
        if (!GradeRescueGuard::canTeacherAccessHomeroomClass($teacherId, $classId, $schoolYearId)) {
            return $this->json(['ok' => false, 'message' => 'Anda tidak memiliki akses ke batch ini.'], 403);
        }

        return $this->json([
            'ok' => true,
            'batch' => [
                'batch_id' => (int) ($batch['id'] ?? 0),
                'batch_code' => (string) ($batch['batch_code'] ?? ''),
                'status' => (string) ($batch['status'] ?? ''),
                'status_label' => GradeUploadStatus::label((string) ($batch['status'] ?? '')),
                'total_rows' => (int) ($batch['total_rows'] ?? 0),
                'valid_rows' => (int) ($batch['valid_rows'] ?? 0),
                'invalid_rows' => (int) ($batch['invalid_rows'] ?? 0),
                'template_version' => (string) ($batch['template_version'] ?? ''),
                'request_id' => (string) ($batch['request_id'] ?? ''),
                'committed_at' => $batch['committed_at'] ?? null,
                'rolled_back_at' => $batch['rolled_back_at'] ?? null,
                'can_finalize' => strtoupper((string) ($batch['status'] ?? '')) === GradeUploadStatus::DRAFT,
                'can_reopen' => in_array(strtoupper((string) ($batch['status'] ?? '')), [GradeUploadStatus::FINAL, GradeUploadStatus::COMMITTED], true),
                'can_reupload' => !in_array(strtoupper((string) ($batch['status'] ?? '')), [GradeUploadStatus::FINAL, GradeUploadStatus::COMMITTED], true),
            ],
        ]);
    }

    private function resolveSemesterText(array $class, array $assignment): string
    {
        $semesterNumber = (int) ($assignment['mata_pelajaran_tahun_ajaran_semester'] ?? ($class['tahun_ajaran_semester'] ?? 1));

        return $semesterNumber === 2 ? 'genap' : 'ganjil';
    }

    /**
     * @param array<int, array<int|string|float|bool|null>> $rows
     * @return array{ok:bool,message:string,rows?:array<int, array<string, string>>,header?:array<int,string>,components?:array<int,string>,template_version?:string,context_token?:string}
     */
    private function parseTemplateRows(array $rows): array
    {
        if (empty($rows)) {
            return ['ok' => false, 'message' => 'File kosong.'];
        }

        $headerIndex = null;
        $headerMap = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

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
            return ['ok' => false, 'message' => 'Header template tidak ditemukan atau format tidak sesuai.'];
        }

        $components = [];
        foreach ($headerMap as $column) {
            if ($column === '' || in_array($column, self::FIXED_COLUMNS, true) || in_array($column, self::CONTROL_COLUMNS, true)) {
                continue;
            }
            $components[] = $column;
        }

        if (empty($components)) {
            return ['ok' => false, 'message' => 'Kolom komponen nilai tidak ditemukan pada template.'];
        }

        $parsedRows = [];
        $templateVersion = '';
        $contextToken = '';

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (!is_array($row)) {
                continue;
            }

            $record = [];
            $isEmpty = true;

            foreach ($headerMap as $colIndex => $columnName) {
                if ($columnName === '') {
                    continue;
                }

                $value = isset($row[$colIndex]) ? trim((string) $row[$colIndex]) : '';
                if ($value !== '') {
                    $isEmpty = false;
                }
                $record[$columnName] = $value;
            }

            if ($isEmpty) {
                continue;
            }

            if ($templateVersion === '' && isset($record['template_version'])) {
                $templateVersion = trim((string) $record['template_version']);
            }
            if ($contextToken === '' && isset($record['context_token'])) {
                $contextToken = trim((string) $record['context_token']);
            }

            $parsedRows[] = $record;
        }

        if (empty($parsedRows)) {
            return ['ok' => false, 'message' => 'Tidak ada data siswa pada template.'];
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

    /**
     * @param array<int, string> $header
     */
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

    /**
     * @param array<int, array<string, mixed>> $studentRows
     * @return array<string, int>
     */
    private function buildStudentIndex(array $studentRows): array
    {
        $map = [];

        foreach ($studentRows as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $nisn = trim((string) ($student['nisn'] ?? ''));
            $nis = trim((string) ($student['nipd'] ?? ''));

            if ($nisn !== '') {
                $map['nisn:' . $nisn] = $studentId;
            }
            if ($nis !== '') {
                $map['nis:' . $nis] = $studentId;
            }
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

    private function validateTemplateControl(
        string $templateVersion,
        string $contextToken,
        int $schoolYearId,
        string $semester,
        int $classId,
        int $assignmentId
    ): bool {
        $decoded = $this->decodeContextToken($contextToken);
        if (!is_array($decoded)) {
            return false;
        }

        return (string) ($decoded['template_version'] ?? '') === $templateVersion
            && (int) ($decoded['school_year_id'] ?? 0) === $schoolYearId
            && strtolower((string) ($decoded['semester'] ?? '')) === strtolower($semester)
            && (int) ($decoded['class_id'] ?? 0) === $classId
            && (int) ($decoded['assignment_id'] ?? 0) === $assignmentId;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeContextToken(string $token): ?array
    {
        $normalized = strtr($token, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $raw = base64_decode($normalized, true);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $batch
     */
    private function isKurmerBatch(array $batch): bool
    {
        $classId = (int) ($batch['kelas_id'] ?? 0);
        if ($classId <= 0) {
            return false;
        }

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
            $batchId,
            $assignmentId,
            $studentId,
            $scores,
            $actorUserId,
            $actorTeacherId,
            $now
        );
        $nilaiUts = $this->scoreFromPayload($scores, 'uts');
        $nilaiUas = $this->scoreFromPayload($scores, 'uas');
        $nilaiAkhir = $this->computeFinalScore($nilaiKd, $nilaiUts, $nilaiUas, $weights);
        $predikat = $nilaiAkhir !== null
            ? AssessmentEvaluator::determinePredicate($nilaiAkhir, $kkmEnabled, $kkmValue)
            : null;

        $before = StudentKnowledgeAssessment::findForStudent($assignmentId, $studentId);

        $ok = StudentKnowledgeAssessment::upsert(
            $assignmentId,
            $studentId,
            $nilaiKd,
            $nilaiUts,
            $nilaiUas,
            $nilaiAkhir,
            $predikat,
            null
        );

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
            if (!is_string($component) || !str_starts_with($component, 'tp_')) {
                continue;
            }

            if (!preg_match('/^tp_(\d+)_.*_(capaian|nilai)$/', $component, $matches)) {
                continue;
            }

            $tpId = (int) ($matches[1] ?? 0);
            $suffix = (string) ($matches[2] ?? '');
            if ($tpId <= 0) {
                continue;
            }

            if (!isset($tpSummarySources[$tpId])) {
                $tpSummarySources[$tpId] = ['capaian' => null, 'nilai' => null];
            }
            $tpSummarySources[$tpId][$suffix] = $value;
        }

        foreach ($tpSummarySources as $tpId => $payload) {
            $capaian = AssessmentEvaluator::normalizeKurmerCapaian($payload['capaian'] ?? null);
            if ($capaian === null) {
                continue;
            }
            $nilai = AssessmentEvaluator::normalizeScore($payload['nilai'] ?? null);
            $beforeTpMap = StudentTpAssessment::mapByAssignmentAndClass($assignmentId, $classId, [$studentId]);
            $beforeTp = $beforeTpMap[$studentId][$tpId] ?? null;

            StudentTpAssessment::upsert(
                $assignmentId,
                $classId,
                (int) $tpId,
                $studentId,
                $capaian,
                $nilai,
                null
            );

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

        $ok = StudentKurmerSubjectSummary::upsert(
            $assignmentId,
            $classId,
            $studentId,
            $capaianAkhir,
            $deskripsi,
            $tindakLanjut,
            $nilaiOpsional,
            null
        );
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
    ): ?float
    {
        $kdValues = [];
        foreach ($scores as $component => $value) {
            if (!is_string($component) || !str_starts_with($component, 'kd_')) {
                continue;
            }
            if (!preg_match('/^kd_(\d+)_/', $component, $matches)) {
                continue;
            }

            $kdId = (int) ($matches[1] ?? 0);
            if ($kdId <= 0) {
                continue;
            }

            $score = AssessmentEvaluator::normalizeScore($value);
            if ($score === null) {
                continue;
            }

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

        if (empty($kdValues)) {
            return null;
        }

        return round(array_sum($kdValues) / count($kdValues), 2);
    }

    private function findStudentKdScore(int $assignmentId, int $kdId, int $studentId): ?array
    {
        if ($assignmentId <= 0 || $kdId <= 0 || $studentId <= 0) {
            return null;
        }

        $statement = Database::connection()->prepare(
            'SELECT * FROM penilaian_kd_siswa
             WHERE guru_mata_pelajaran_id = :assignment
               AND kd_id = :kd
               AND siswa_id = :student
             LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':assignment', $assignmentId, \PDO::PARAM_INT);
        $statement->bindValue(':kd', $kdId, \PDO::PARAM_INT);
        $statement->bindValue(':student', $studentId, \PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param array{weight_kd: float, weight_uts: float, weight_uas: float} $weights
     */
    private function computeFinalScore(?float $nilaiKd, ?float $nilaiUts, ?float $nilaiUas, array $weights): ?float
    {
        if ($nilaiKd === null && $nilaiUts === null && $nilaiUas === null) {
            return null;
        }

        $kd = $nilaiKd ?? 0.0;
        $uts = $nilaiUts ?? 0.0;
        $uas = $nilaiUas ?? 0.0;

        $final = ($kd * $weights['weight_kd']) + ($uts * $weights['weight_uts']) + ($uas * $weights['weight_uas']);

        return round($final, 2);
    }

    private function checkUploadRateLimit(int $teacherId): bool
    {
        if ($teacherId <= 0) {
            return false;
        }

        $key = 'grade_upload_rate_' . $teacherId;
        $history = $_SESSION[$key] ?? [];
        if (!is_array($history)) {
            $history = [];
        }

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
        if (!is_file($path)) {
            return false;
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xls', 'xlsx'], true)) {
            return false;
        }

        if (!function_exists('finfo_open')) {
            return true;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return true;
        }

        $mime = (string) finfo_file($finfo, $path);
        finfo_close($finfo);

        $allowed = [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip', // sebagian xlsx dikenali sebagai zip
            'application/octet-stream', // fallback beberapa environment
        ];

        return in_array(strtolower($mime), $allowed, true);
    }

    private function contextReadySessionKey(int $teacherId): string
    {
        return 'grade_rescue_context_ready_' . $teacherId;
    }

    private function rememberContextReady(int $teacherId, int $classId, int $assignmentId): void
    {
        if ($teacherId <= 0 || $classId <= 0 || $assignmentId <= 0) {
            return;
        }

        $key = $this->contextReadySessionKey($teacherId);
        $entries = Session::get($key, []);
        if (!is_array($entries)) {
            $entries = [];
        }

        $signature = $classId . ':' . $assignmentId;
        $now = date('Y-m-d H:i:s');
        $next = [];
        $next[] = [
            'kelas_id' => $classId,
            'assignment_id' => $assignmentId,
            'saved_at' => $now,
        ];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entryClassId = (int) ($entry['kelas_id'] ?? 0);
            $entryAssignmentId = (int) ($entry['assignment_id'] ?? 0);
            if ($entryClassId <= 0 || $entryAssignmentId <= 0) {
                continue;
            }
            if (($entryClassId . ':' . $entryAssignmentId) === $signature) {
                continue;
            }
            $next[] = [
                'kelas_id' => $entryClassId,
                'assignment_id' => $entryAssignmentId,
                'saved_at' => (string) ($entry['saved_at'] ?? $now),
            ];
            if (count($next) >= self::CONTEXT_READY_SESSION_LIMIT) {
                break;
            }
        }

        Session::set($key, $next);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildContextReadyEntries(int $teacherId, array $recentBatches = []): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $entries = Session::get($this->contextReadySessionKey($teacherId), []);
        if (!is_array($entries) || empty($entries)) {
            return [];
        }

        $existingContextMap = [];
        foreach ($recentBatches as $batch) {
            if (!is_array($batch)) {
                continue;
            }
            $batchClassId = (int) ($batch['kelas_id'] ?? 0);
            $batchAssignmentId = (int) ($batch['guru_mata_pelajaran_id'] ?? 0);
            if ($batchClassId <= 0 || $batchAssignmentId <= 0) {
                continue;
            }
            $existingContextMap[$batchClassId . ':' . $batchAssignmentId] = true;
        }

        $results = [];
        $keptForSession = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $classId = (int) ($entry['kelas_id'] ?? 0);
            $assignmentId = (int) ($entry['assignment_id'] ?? 0);
            if ($classId <= 0 || $assignmentId <= 0) {
                continue;
            }
            if (isset($existingContextMap[$classId . ':' . $assignmentId])) {
                continue;
            }

            $class = Classroom::findWithRelations($classId);
            $assignment = SubjectTeacher::findWithRelations($assignmentId);
            if ($class === null || $assignment === null) {
                continue;
            }

            $savedAt = (string) ($entry['saved_at'] ?? '');
            $results[] = [
                'kelas_id' => $classId,
                'assignment_id' => $assignmentId,
                'kelas_tingkat' => (string) ($class['tingkat'] ?? ''),
                'kelas_nama' => (string) ($class['nama'] ?? ''),
                'mapel_kode' => (string) ($assignment['mata_pelajaran_kode'] ?? ''),
                'mapel_nama' => (string) ($assignment['mata_pelajaran_nama'] ?? ''),
                'saved_at' => $savedAt,
            ];
            $keptForSession[] = [
                'kelas_id' => $classId,
                'assignment_id' => $assignmentId,
                'saved_at' => $savedAt !== '' ? $savedAt : date('Y-m-d H:i:s'),
            ];
        }

        $key = $this->contextReadySessionKey($teacherId);
        if (count($keptForSession) !== count($entries)) {
            if (empty($keptForSession)) {
                Session::forget($key);
            } else {
                Session::set($key, $keptForSession);
            }
        }

        return $results;
    }
}

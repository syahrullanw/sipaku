<?php

namespace App\Controllers;

use App\Models\AttitudeScore;
use App\Models\Classroom;
use App\Models\HomeroomNote;
use App\Models\SchoolProfile;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Models\StudentAttendance;
use App\Models\StudentExtracurricular;
use App\Models\StudentPlacementHistory;
use App\Models\StudentPrakerinPlacement;
use App\Models\P5Project;
use App\Models\P5ProjectElement;
use App\Models\P5StudentAssessment;
use App\Models\P5StudentSummary;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\DigitalDocumentSignature;
use App\Models\PrakerinAssessment;
use App\Services\AttitudeFormatter;
use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use DateTimeImmutable;
use PDO;

class ReportPrintController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $data = $this->prepareSelectionData($request, 'report_card');

        return $this->render('reports/index', array_merge([
            'title' => 'Cetak Raport',
            'pageTitle' => 'Cetak Raport',
            'activeMenu' => 'report-cards',
        ], $data));
    }

    public function midtermIndex(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $classId = (int) $request->query('kelas_id', 0);
        if ($classId > 0) {
            $classRecord = Classroom::find($classId);
            if ($classRecord !== null && strtolower((string) ($classRecord['kurikulum'] ?? 'k13')) === 'kurmer') {
                return $this->render('reports/sections/error', [
                    'title' => 'Laporan Tengah Semester',
                    'message' => 'Cetak raport tengah semester tidak tersedia untuk kelas Kurikulum Merdeka.',
                ], 'print');
            }
        }

        $data = $this->prepareSelectionData($request, 'midterm_report');
        $selectedClass = $data['selectedClass'] ?? null;
        if (is_array($selectedClass) && strtolower((string) ($selectedClass['kurikulum'] ?? 'k13')) === 'kurmer') {
            return $this->render('reports/sections/error', [
                'title' => 'Laporan Tengah Semester',
                'message' => 'Cetak raport tengah semester tidak tersedia untuk kelas Kurikulum Merdeka.',
            ], 'print');
        }

        return $this->render('reports/midterm', array_merge([
            'title' => 'Laporan Tengah Semester',
            'pageTitle' => 'Laporan Tengah Semester',
            'activeMenu' => 'midterm-report',
        ], $data));
    }

    public function midtermPrint(Request $request): Response
    {
        $classId = (int) $request->query('kelas_id', 0);
        if ($classId > 0) {
            $classRecord = Classroom::find($classId);
            if ($classRecord !== null && strtolower((string) ($classRecord['kurikulum'] ?? 'k13')) === 'kurmer') {
                return $this->render('reports/sections/error', [
                    'title' => 'Laporan Tengah Semester',
                    'message' => 'Cetak raport tengah semester tidak tersedia untuk kelas Kurikulum Merdeka.',
                ], 'print');
            }
        }

        return $this->renderSection($request, 'reports/sections/midterm', 'Laporan Tengah Semester', 'midterm_report');
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareSelectionData(Request $request, string $documentType = 'report_card'): array
    {
        if ($documentType === '') {
            $documentType = 'report_card';
        }

        $user = auth() ?? [];
        $role = (string) ($user['role'] ?? '');
        $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($role === 'guru' && $teacherId > 0) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId, $activeYearId > 0 ? $activeYearId : null);
        } else {
            $classes = Classroom::allWithRelations($activeYearId > 0 ? $activeYearId : null);
        }

        $classes = array_values(array_filter($classes, static function ($class) {
            return (int) ($class['id'] ?? 0) > 0;
        }));

        // Deduplikasi kelas berdasarkan ID
        $seenIds = [];
        $uniqueClasses = [];
        foreach ($classes as $class) {
            $id = (int) ($class['id'] ?? 0);
            if ($id > 0 && !isset($seenIds[$id])) {
                $seenIds[$id] = true;
                $uniqueClasses[] = $class;
            }
        }
        $classes = $uniqueClasses;
        unset($seenIds, $uniqueClasses);

        if ($documentType === 'midterm_report') {
            $classes = array_values(array_filter($classes, static function ($class) {
                return strtolower((string) ($class['kurikulum'] ?? 'k13')) !== 'kurmer';
            }));
        }

        $rawKelasIds = $request->query('kelas_ids', '');
        if (is_array($rawKelasIds)) {
            $rawKelasIds = array_values(array_filter(array_map(static fn ($id) => (int) $id, $rawKelasIds), static fn (int $id) => $id > 0));
            $requestedClassId = !empty($rawKelasIds) ? $rawKelasIds[0] : 0;
        } elseif ($rawKelasIds !== '') {
            $rawKelasIds = array_values(array_filter(array_map('intval', explode(',', $rawKelasIds)), static fn (int $id) => $id > 0));
            $requestedClassId = !empty($rawKelasIds) ? $rawKelasIds[0] : 0;
        } else {
            $rawKelasIds = [];
            $requestedClassId = (int) $request->query('kelas_id', 0);
        }

        $selectedClassId = $requestedClassId;
        $selectedClass = null;

        if (!empty($classes)) {
            if ($selectedClassId > 0) {
                foreach ($classes as $class) {
                    if ((int) ($class['id'] ?? 0) === $selectedClassId) {
                        $selectedClass = $class;
                        break;
                    }
                }
            }

            if ($selectedClass === null) {
                $selectedClass = $classes[0];
                $selectedClassId = (int) ($selectedClass['id'] ?? 0);
            }
        } else {
            $selectedClassId = 0;
        }

        $selectedStudentId = (int) $request->query('siswa_id', 0);
        $selectedStudent = null;

        $classIdsToUse = !empty($rawKelasIds) ? $rawKelasIds : ($selectedClassId > 0 ? [$selectedClassId] : []);

        $students = [];
        $studentsById = [];
        $digitalSignatureMap = [];
        $digitalSignatureSummary = [
            'total' => 0,
            'requested' => 0,
            'pending' => 0,
            'approved' => 0,
            'revoked' => 0,
            'not_requested' => 0,
        ];

        $classLookup = [];
        foreach ($classes as $c) {
            $classLookup[(int) ($c['id'] ?? 0)] = $c;
        }

        foreach ($classIdsToUse as $cid) {
            $class = $classLookup[$cid] ?? null;

            if ($class === null) {
                continue;
            }

            $schoolYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
            if ($schoolYearId <= 0) {
                continue;
            }

            $classStudents = $schoolYearId > 0
                ? StudentPlacementHistory::studentsByClassYear($cid, $schoolYearId)
                : [];

            if (empty($classStudents) && $schoolYearId > 0) {
                $classStudents = Student::byClass($cid, null);
            }

            foreach ($classStudents as $s) {
                $sid = (int) ($s['id'] ?? 0);
                if ($sid > 0 && !isset($studentsById[$sid])) {
                    $studentsById[$sid] = true;
                    $students[] = $s;
                }
            }

            $classSignatureMap = DigitalDocumentSignature::mapByClass($schoolYearId, $cid, $documentType);
            foreach ($classSignatureMap as $studentId => $record) {
                if (!isset($digitalSignatureMap[$studentId])) {
                    $digitalSignatureMap[$studentId] = $record;
                }
            }
        }

        if ($selectedStudentId > 0) {
            $selectedStudent = Student::findWithRelations($selectedStudentId);
        }

        $semester = 1;
        if ($activeYear !== null) {
            $activeSemester = (int) ($activeYear['semester_aktif'] ?? 0);
            if (in_array($activeSemester, [1, 2], true)) {
                $semester = $activeSemester;
            }
        }

        $schoolProfile = $this->getSchoolProfile();
        $semesterOptions = [
            1 => 'Semester 1 (Ganjil)',
            2 => 'Semester 2 (Genap)',
        ];
        $classSchoolYear = $activeYear;
        $digitalSignatureEnabled = $classSchoolYear !== null && (int) ($classSchoolYear['digital_signature_enabled'] ?? 0) === 1;
        $canRequestDigitalSignature = $digitalSignatureEnabled && $activeYearId > 0;
        $digitalSignatureSummary['total'] = count($students);
        $digitalSignatureSummary['not_requested'] = max(0, count($students));
        $selectedStudentSignature = null;

        if ($activeYearId > 0 && !empty($students)) {
            foreach ($digitalSignatureMap as $record) {
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

            if ($selectedStudentId > 0 && isset($digitalSignatureMap[$selectedStudentId])) {
                $selectedStudentSignature = $digitalSignatureMap[$selectedStudentId];
            }
        }

        $paperSize = strtolower((string) $request->query('paper', 'f4'));
        if (!in_array($paperSize, ['f4', 'a4'], true)) {
            $paperSize = 'f4';
        }

        return [
            'classes' => $classes,
            'selectedClass' => $selectedClass,
            'selectedClassId' => $selectedClassId,
            'students' => $students,
            'selectedStudentId' => $selectedStudentId,
            'selectedStudent' => $selectedStudent,
            'semester' => $semester,
            'semesterOptions' => $semesterOptions,
            'schoolProfile' => $schoolProfile,
            'canPrint' => $selectedStudentId > 0,
            'isHomeroom' => $role === 'guru' && $teacherId > 0,
            'digitalSignatureEnabled' => $digitalSignatureEnabled,
            'canRequestDigitalSignature' => $canRequestDigitalSignature,
            'digitalSignatureRecords' => $digitalSignatureMap,
            'selectedStudentSignature' => $selectedStudentSignature,
            'digitalSignatureSummary' => $digitalSignatureSummary,
            'activeSchoolYearId' => $activeYearId,
            'digitalSignatureDocumentType' => $documentType,
            'paperSize' => $paperSize,
            'paperOptions' => [
                'f4' => 'F4 / Folio (33 x 21,5 cm)',
                'a4' => 'A4 (29,7 x 21 cm)',
            ],
            'selectedClassIds' => $classIdsToUse,
        ];
    }

    public function cover(Request $request): Response
    {
        return $this->renderSection($request, 'reports/sections/cover', 'Cetak Raport - Cover', 'report_card');
    }

    public function schoolInfo(Request $request): Response
    {
        return $this->renderSection($request, 'reports/sections/school-info', 'Cetak Raport - Informasi Sekolah', 'report_card');
    }

    public function studentBio(Request $request): Response
    {
        return $this->renderSection($request, 'reports/sections/biodata', 'Cetak Raport - Biodata Siswa', 'report_card');
    }

    public function gradeSheet(Request $request): Response
    {
        return $this->renderSection($request, 'reports/sections/grade', 'Cetak Raport - Hasil Penilaian', 'report_card');
    }

    public function achievements(Request $request): Response
    {
        return $this->renderSection($request, 'reports/sections/achievements', 'Cetak Raport - Prestasi & Catatan', 'report_card');
    }

    public function gradeAndAchievements(Request $request): Response
    {
        return $this->renderSection($request, 'reports/sections/grade-achievements', 'Cetak Raport - Hasil Penilaian & Prestasi', 'report_card');
    }

    public function fullReport(Request $request): Response
    {
        return $this->renderSection($request, 'reports/sections/full', 'Cetak Raport - Lengkap', 'report_card');
    }

    public function printClassSection(Request $request, string $section): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $allowedSections = ['cover', 'informasi-sekolah', 'biodata', 'hasil-penilaian', 'prestasi-catatan', 'hasil-penilaian-prestasi', 'lengkap'];
        $section = strtolower(trim($section));
        if (!in_array($section, $allowedSections, true)) {
            return $this->render('reports/sections/error', [
                'title' => 'Cetak Raport Kelas',
                'message' => 'Bagian raport tidak valid.',
            ], 'print');
        }

        $viewMap = [
            'cover' => 'reports/sections/cover',
            'informasi-sekolah' => 'reports/sections/school-info',
            'biodata' => 'reports/sections/biodata',
            'hasil-penilaian' => 'reports/sections/grade',
            'prestasi-catatan' => 'reports/sections/achievements',
            'hasil-penilaian-prestasi' => 'reports/sections/grade-achievements',
            'lengkap' => 'reports/sections/full',
        ];

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

        $semester = (int) $request->query('semester', 0);
        $paperSize = strtolower((string) $request->query('paper', 'f4'));
        if (!in_array($paperSize, ['f4', 'a4'], true)) {
            $paperSize = 'f4';
        }
        $title = 'Cetak Raport Kelas - ' . ucwords(str_replace('-', ' ', $section));

        if (empty($classIds) || $semester <= 0) {
            return $this->render('reports/sections/error', [
                'title' => $title,
                'message' => 'Pilih kelas dan semester terlebih dahulu.',
            ], 'print');
        }

        $user = auth() ?? [];
        $role = (string) ($user['role'] ?? '');
        $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;

        $reports = [];
        $firstClass = null;

        foreach ($classIds as $classId) {
            $class = Classroom::findWithRelations($classId);
            if ($class === null) {
                continue;
            }

            if ($role === 'guru' && $teacherId > 0) {
                if ((int) ($class['wali_kelas_id'] ?? 0) !== $teacherId) {
                    continue;
                }
            } elseif ($role !== 'admin') {
                continue;
            }

            if ($firstClass === null) {
                $firstClass = $class;
            }

            $schoolYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
            $students = $schoolYearId > 0
                ? StudentPlacementHistory::studentsByClassYear($classId, $schoolYearId)
                : [];

            if (empty($students) && $schoolYearId > 0) {
                $students = Student::byClass($classId, null);
            }

            foreach ($students as $student) {
                $studentId = (int) ($student['id'] ?? 0);
                if ($studentId <= 0) {
                    continue;
                }

                $context = $this->buildReportContextForStudent($studentId, $semester, 'report_card', true, $classId);
                if ($context !== null) {
                    $reports[] = $context;
                }
            }
        }

        if (empty($reports)) {
            return $this->render('reports/sections/error', [
                'title' => $title,
                'message' => 'Data raport tidak ditemukan.',
            ], 'print');
        }

        $sectionView = $viewMap[$section];

        return $this->render('reports/sections/class-print', [
            'title' => $title,
            'reports' => $reports,
            'class' => $firstClass ?? [],
            'semester' => $semester,
            'section' => $sectionView,
            'paperSize' => $paperSize,
        ], 'print');
    }

    public function gradeSheetClass(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $classId = (int) $request->query('kelas_id', 0);
        if ($classId <= 0) {
            $kelasIds = $request->query('kelas_ids', '');
            if ($kelasIds !== '') {
                $ids = array_map('intval', explode(',', $kelasIds));
                $ids = array_values(array_filter($ids, static fn ($id) => $id > 0));
                $classId = !empty($ids) ? $ids[0] : 0;
            }
        }
        $semester = (int) $request->query('semester', 0);
        $paperSize = strtolower((string) $request->query('paper', 'f4'));
        if (!in_array($paperSize, ['f4', 'a4'], true)) {
            $paperSize = 'f4';
        }
        $title = 'Cetak Raport - Hasil Penilaian Kelas';

        if ($classId <= 0) {
            return $this->render('reports/sections/error', [
                'title' => $title,
                'message' => 'Pilih kelas dan semester terlebih dahulu sebelum mencetak raport.',
            ], 'print');
        }

        $class = Classroom::findWithRelations($classId);
        if ($class === null) {
            return $this->render('reports/sections/error', [
                'title' => $title,
                'message' => 'Data kelas tidak ditemukan atau akses ditolak.',
            ], 'print');
        }

        $user = auth() ?? [];
        $role = (string) ($user['role'] ?? '');
        $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;

        if ($role === 'admin') {
            // Admin memiliki akses penuh.
        } elseif ($role === 'guru' && $teacherId > 0) {
            if ((int) ($class['wali_kelas_id'] ?? 0) !== $teacherId) {
                return $this->render('reports/sections/error', [
                    'title' => $title,
                    'message' => 'Cetak raport kelas hanya tersedia untuk wali kelas yang bersangkutan.',
                ], 'print');
            }
        } else {
            return $this->render('reports/sections/error', [
                'title' => $title,
                'message' => 'Anda tidak memiliki hak akses untuk mencetak raport kelas ini.',
            ], 'print');
        }

        $schoolYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
        $students = $schoolYearId > 0
            ? StudentPlacementHistory::studentsByClassYear($classId, $schoolYearId)
            : [];

        if (empty($students) && $schoolYearId > 0) {
            $students = Student::byClass($classId, null);
        }

        if (empty($students)) {
            return $this->render('reports/sections/error', [
                'title' => $title,
                'message' => 'Belum ada siswa yang terdaftar pada kelas ini.',
            ], 'print');
        }

        $reports = [];

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $context = $this->buildReportContextForStudent($studentId, $semester, 'report_card', true, $classId);

            if ($context !== null) {
                $reports[] = $context;
            }
        }

        if (empty($reports)) {
            return $this->render('reports/sections/error', [
                'title' => $title,
                'message' => 'Data raport siswa tidak ditemukan untuk kelas ini.',
            ], 'print');
        }

        $firstReport = $reports[0];
        $resolvedSemester = (int) ($firstReport['semester'] ?? $semester);
        $semesterLabel = $firstReport['semesterLabel'] ?? ($resolvedSemester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)');
        $schoolYear = $firstReport['schoolYear'] ?? null;

        if ($schoolYear === null && $schoolYearId > 0) {
            $schoolYear = SchoolYear::find($schoolYearId);
        }

        return $this->render('reports/sections/grade-class', [
            'title' => $title,
            'class' => $class,
            'schoolYear' => $schoolYear,
            'semester' => $resolvedSemester,
            'semesterLabel' => $semesterLabel,
            'reports' => $reports,
            'paperSize' => $paperSize,
        ], 'print');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildReportContext(Request $request, ?string $documentType = null): ?array
    {
        $studentId = (int) $request->query('siswa_id', 0);
        $semester = (int) $request->query('semester', 0);
        $classId = (int) $request->query('kelas_id', 0);

        return $this->buildReportContextForStudent($studentId, $semester, $documentType, false, $classId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildReportContextForStudent(int $studentId, int $semester = 0, ?string $documentType = null, bool $suppressFlash = false, ?int $preferredClassId = null): ?array
    {
        if ($studentId <= 0) {
            if (!$suppressFlash) {
                Session::flash('error', 'Pilih siswa terlebih dahulu sebelum mencetak raport.');
            }

            return null;
        }

        $user = auth() ?? [];
        $role = (string) ($user['role'] ?? '');
        $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;

        $student = Student::findWithRelations($studentId);
        if ($student === null) {
            if (!$suppressFlash) {
                Session::flash('error', 'Data siswa tidak ditemukan.');
            }

            return null;
        }

        $classId = $preferredClassId !== null && $preferredClassId > 0
            ? $preferredClassId
            : (int) ($student['kelas_id'] ?? 0);
        if ($classId <= 0) {
            if (!$suppressFlash) {
                Session::flash('error', 'Siswa belum memiliki data kelas terhubung.');
            }

            return null;
        }

        $class = Classroom::findWithRelations($classId);
        if ($class === null) {
            if (!$suppressFlash) {
                Session::flash('error', 'Data kelas siswa tidak ditemukan.');
            }

            return null;
        }

        if ($role === 'admin') {
            // Admin memiliki akses penuh.
        } elseif ($role === 'guru' && $teacherId > 0) {
            if ((int) ($class['wali_kelas_id'] ?? 0) !== $teacherId) {
                if (!$suppressFlash) {
                    Session::flash('error', 'Menu cetak raport hanya dapat diakses oleh wali kelas untuk siswa yang diampu.');
                }

                return null;
            }
        } else {
            if (!$suppressFlash) {
                Session::flash('error', 'Anda tidak memiliki hak akses untuk mencetak raport.');
            }

            return null;
        }

        $schoolYearId = $preferredClassId !== null && $preferredClassId > 0
            ? (int) ($class['tahun_ajaran_id'] ?? 0)
            : (int) ($student['tahun_ajaran_id'] ?? 0);
        if ($schoolYearId <= 0) {
            $schoolYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
        }

        $schoolYear = $schoolYearId > 0 ? SchoolYear::find($schoolYearId) : null;

        $resolvedSemester = $semester;
        if (!in_array($resolvedSemester, [1, 2], true)) {
            $resolvedSemester = $preferredClassId !== null && $preferredClassId > 0
                ? (int) ($class['tahun_ajaran_semester'] ?? 0)
                : (int) ($student['tahun_ajaran_semester'] ?? 0);
            if (!in_array($resolvedSemester, [1, 2], true)) {
                $guessedFromClass = (int) ($class['tahun_ajaran_semester'] ?? 0);
                if (in_array($guessedFromClass, [1, 2], true)) {
                    $resolvedSemester = $guessedFromClass;
                } else {
                    $resolvedSemester = $schoolYear !== null ? (int) ($schoolYear['semester_aktif'] ?? 1) : 1;
                }
            }
        }

        $majorId = (int) ($class['jurusan_id'] ?? 0);
        if ($majorId <= 0) {
            $majorId = (int) ($student['kelas_jurusan_id'] ?? 0);
            if ($majorId <= 0) {
                $majorId = null;
            }
        }

        $subjects = $this->collectSubjectScores($studentId, $schoolYearId, $majorId, $classId);

        $attitudes = [
            'spiritual' => $this->extractAttitudeNote($classId, $schoolYearId, $studentId, 'spiritual'),
            'social' => $this->extractAttitudeNote($classId, $schoolYearId, $studentId, 'sosial'),
        ];

        $attendanceRecords = StudentAttendance::byClass($classId, $schoolYearId > 0 ? $schoolYearId : null);
        $attendance = $attendanceRecords[$studentId] ?? [
            'sakit' => 0,
            'izin' => 0,
            'bolos' => 0,
            'alpa' => 0,
        ];

        $achievementRecords = StudentAchievement::byClass($classId, $schoolYearId > 0 ? $schoolYearId : null);
        $achievements = array_values(array_filter($achievementRecords, static function ($achievement) use ($studentId) {
            return (int) ($achievement['siswa_id'] ?? 0) === $studentId;
        }));

        $homeroomNotes = HomeroomNote::byClass($classId, $schoolYearId > 0 ? $schoolYearId : null);
        $homeroomNote = $homeroomNotes[$studentId] ?? '';

        $extracurriculars = [];
        $prakerin = null;
        $cocurriculars = [];
        $p5Projects = [];

        if ($schoolYearId > 0) {
            $extracurricularDetails = StudentExtracurricular::detailedByClass($classId, $schoolYearId);
            $extracurriculars = array_values($extracurricularDetails[$studentId] ?? []);

            $prakerinPlacements = StudentPrakerinPlacement::byClass($classId, $schoolYearId);
            $prakerinPlacement = $prakerinPlacements[$studentId] ?? null;

            $prakerinAssessments = PrakerinAssessment::byStudents([$studentId], $schoolYearId);
            $prakerinAssessment = $prakerinAssessments[$studentId] ?? null;

            if ($prakerinPlacement !== null || $prakerinAssessment !== null) {
                $prakerin = [
                    'place_id' => $prakerinPlacement['tempat_prakerin_id'] ?? ($prakerinAssessment['tempat_prakerin_id'] ?? null),
                    'place_name' => $prakerinPlacement['tempat_nama'] ?? null,
                    'start_date' => $prakerinPlacement['tanggal_mulai'] ?? null,
                    'end_date' => $prakerinPlacement['tanggal_selesai'] ?? null,
                    'nilai_keaktifan' => $prakerinAssessment['nilai_keaktifan'] ?? null,
                    'nilai_jurnal' => $prakerinAssessment['nilai_jurnal'] ?? null,
                    'nilai_laporan' => $prakerinAssessment['nilai_laporan'] ?? null,
                    'nilai_akhir' => $prakerinAssessment['nilai_akhir'] ?? null,
                    'predikat' => $prakerinAssessment['predikat'] ?? null,
                ];
            }

            if (($class['kurikulum'] ?? 'k13') === 'kurmer') {
                $cocurriculars = $this->collectCocurricularSummaries($classId, $studentId, $schoolYearId, $resolvedSemester);
            }

            $p5Projects = $this->collectP5Assessments($classId, $studentId, $schoolYearId);
        }

        $schoolProfile = $this->getSchoolProfile();

        $printedAt = new DateTimeImmutable();
        $printedDateLabel = $this->resolvePrintedDateLabel($schoolProfile, $class, $documentType, $printedAt);

        $context = [
            'student' => $student,
            'class' => $class,
            'schoolYear' => $schoolYear,
            'schoolYearId' => $schoolYearId,
            'school' => $schoolProfile,
            'semester' => $resolvedSemester,
            'semesterLabel' => $resolvedSemester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)',
            'subjects' => $subjects,
            'curriculum' => $class['kurikulum'] ?? 'k13',
            'attitudes' => $attitudes,
            'attendance' => $attendance,
            'achievements' => $achievements,
            'homeroomNote' => $homeroomNote,
            'extracurriculars' => $extracurriculars,
            'prakerin' => $prakerin,
            'cocurriculars' => $cocurriculars,
            'p5_projects' => $p5Projects ?? [],
            'printedAt' => $printedAt,
            'printedDateLabel' => $printedDateLabel,
        ];

        if ($documentType !== null) {
            $context = $this->attachDigitalSignature($context, $documentType);
        }

        return $context;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectSubjectScores(int $studentId, int $schoolYearId, ?int $majorId, ?int $classId = null): array
    {
        if ($studentId <= 0 || $schoolYearId <= 0) {
            return [];
        }

        $curriculum = 'k13';
        if ($classId !== null && $classId > 0) {
            $classRecord = Classroom::find($classId);
            if ($classRecord !== null && !empty($classRecord['kurikulum'])) {
                $curriculum = (string) $classRecord['kurikulum'];
            }
        }

        $connection = Database::connection();

        $groupOrderValues = [];
        foreach (Subject::GROUPS as $group) {
            $groupOrderValues[] = $connection->quote($group['code']);
        }
        $orderClause = !empty($groupOrderValues) ? implode(', ', $groupOrderValues) : '';

        $classJoin = '';
        $classFilter = '';
        if ($classId !== null && $classId > 0) {
            $classJoin = 'JOIN guru_mata_pelajaran_kelas gmpk ON gmpk.guru_mata_pelajaran_id = gmp.id';
            $classFilter = ' AND gmpk.kelas_id = :class_id';
        }

        $baseSql = <<<SQL
SELECT
    mp.id AS subject_id,
    mp.kode AS subject_code,
    mp.nama AS subject_name,
    mp.jenis AS subject_group,
    mp.jurusan_id AS subject_major_id,
    settings.enable_kkm,
    settings.nilai_kkm,
    settings.enable_keterampilan,
    knowledge.nilai_akhir AS knowledge_score,
    knowledge.predikat AS knowledge_predicate,
    knowledge.deskripsi AS knowledge_description,
    skill.nilai_akhir AS skill_score,
    skill.predikat AS skill_predicate,
    skill.deskripsi AS skill_description,
    kurmer.capaian_akhir_enum AS kurmer_capaian,
    kurmer.deskripsi_umum AS kurmer_deskripsi,
    kurmer.tindak_lanjut AS kurmer_tindak_lanjut,
    kurmer.nilai_opsional AS kurmer_nilai,
    kurmer.sumber_tp AS kurmer_sumber_tp
FROM guru_mata_pelajaran gmp
JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
{$classJoin}
LEFT JOIN pengaturan_penilaian_mapel settings ON settings.guru_mata_pelajaran_id = gmp.id
LEFT JOIN penilaian_pengetahuan_siswa knowledge
    ON knowledge.guru_mata_pelajaran_id = gmp.id AND knowledge.siswa_id = :student_id
LEFT JOIN penilaian_keterampilan_siswa skill
    ON skill.guru_mata_pelajaran_id = gmp.id AND skill.siswa_id = :student_id
LEFT JOIN penilaian_kurmer_mapel_siswa kurmer
    ON kurmer.guru_mata_pelajaran_id = gmp.id AND kurmer.siswa_id = :student_id
    AND (:class_id IS NULL OR kurmer.kelas_id = :class_id)
WHERE mp.tahun_ajaran_id = :school_year_id
{$classFilter}
SQL;

        if ($majorId !== null && $majorId > 0) {
            $baseSql .= ' AND (mp.jurusan_id IS NULL OR mp.jurusan_id = :major_id)';
        }

        if ($orderClause !== '') {
            $baseSql .= sprintf(' ORDER BY FIELD(mp.jenis, %s), mp.nama ASC', $orderClause);
        } else {
            $baseSql .= ' ORDER BY mp.jenis ASC, mp.nama ASC';
        }

        $statement = $connection->prepare($baseSql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':class_id', $classId !== null && $classId > 0 ? $classId : null, $classId !== null && $classId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);

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

            $kurmerSourcesRaw = $row['kurmer_sumber_tp'] ?? null;
            if (is_string($kurmerSourcesRaw)) {
                $decoded = json_decode($kurmerSourcesRaw, true);
                $kurmerSources = is_array($decoded) ? $decoded : [];
            } elseif (is_array($kurmerSourcesRaw)) {
                $kurmerSources = $kurmerSourcesRaw;
            } else {
                $kurmerSources = [];
            }
            $kurmerSources = array_values(array_filter($kurmerSources, static fn ($item) => is_array($item) || is_object($item)));
            $kurmerSources = array_map(static function ($item): array {
                if (is_array($item)) {
                    return $item;
                }

                if (is_object($item)) {
                    return (array) $item;
                }

                return [];
            }, $kurmerSources);
            $kurmerSources = array_values(array_filter($kurmerSources, static fn (array $item): bool => !empty($item)));

            if (!isset($subjects[$subjectId])) {
                $subjects[$subjectId] = [
                    'subject_id' => $subjectId,
                    'subject_code' => (string) ($row['subject_code'] ?? ''),
                    'subject_name' => (string) ($row['subject_name'] ?? ''),
                    'subject_group' => (string) ($row['subject_group'] ?? ''),
                    'kurmer' => $curriculum === 'kurmer',
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
                    'kurmer_summary' => [
                        'capaian' => $row['kurmer_capaian'] ?? null,
                        'description' => $row['kurmer_deskripsi'] ?? null,
                        'tindak_lanjut' => $row['kurmer_tindak_lanjut'] ?? null,
                        'score' => $row['kurmer_nilai'] !== null ? (float) $row['kurmer_nilai'] : null,
                        'tp_sources' => $kurmerSources,
                    ],
                ];
                continue;
            }

            // Perbarui catatan nilai jika entri sebelumnya kosong.
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

            if (empty($subjects[$subjectId]['kurmer_summary']['capaian']) && !empty($row['kurmer_capaian'])) {
                $subjects[$subjectId]['kurmer_summary'] = [
                    'capaian' => $row['kurmer_capaian'],
                    'description' => $row['kurmer_deskripsi'] ?? null,
                    'tindak_lanjut' => $row['kurmer_tindak_lanjut'] ?? null,
                    'score' => $row['kurmer_nilai'] !== null ? (float) $row['kurmer_nilai'] : null,
                    'tp_sources' => $kurmerSources,
                ];
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

        $grouped = array_values(array_filter($groupMap, static fn ($group) => !empty($group['subjects'])));

        return $grouped;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectCocurricularSummaries(int $classId, int $studentId, int $schoolYearId, int $semester): array
    {
        if ($classId <= 0 || $studentId <= 0 || $schoolYearId <= 0) {
            return [];
        }

        if (!in_array($semester, [1, 2], true)) {
            $semester = 1;
        }

        $sql = <<<SQL
SELECT
    kegiatan.id,
    kegiatan.nama,
    kegiatan.tema,
    kegiatan.deskripsi,
    ring.deskripsi_umum,
    ring.tindak_lanjut
FROM kokurikuler_kegiatan kegiatan
JOIN kokurikuler_ringkasan ring ON ring.kegiatan_id = kegiatan.id AND ring.siswa_id = :student_id
WHERE kegiatan.kelas_id = :class_id
  AND kegiatan.tahun_ajaran_id = :school_year_id
  AND kegiatan.semester = :semester
ORDER BY kegiatan.created_at ASC, kegiatan.id ASC
SQL;

        $statement = Database::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':semester', $semester, PDO::PARAM_INT);
        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $records = [];

        foreach ($rows as $row) {
            $records[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => trim((string) ($row['nama'] ?? 'Kegiatan Kokurikuler')),
                'theme' => trim((string) ($row['tema'] ?? '')),
                'description' => trim((string) ($row['deskripsi'] ?? '')),
                'summary' => trim((string) ($row['deskripsi_umum'] ?? '')),
                'follow_up' => trim((string) ($row['tindak_lanjut'] ?? '')),
            ];
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectP5Assessments(int $classId, int $studentId, int $schoolYearId): array
    {
        if ($classId <= 0 || $studentId <= 0 || $schoolYearId <= 0) {
            return [];
        }

        $projects = array_filter(P5Project::byClass($classId), static function (array $project) use ($schoolYearId): bool {
            return (int) ($project['tahun_ajaran_id'] ?? 0) === $schoolYearId;
        });

        if (empty($projects)) {
            return [];
        }

        $results = [];

        foreach ($projects as $project) {
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            $elements = P5ProjectElement::byProject($projectId);
            $assessments = P5StudentAssessment::mapByProject($projectId, [$studentId]);
            $summaries = P5StudentSummary::byProject($projectId, [$studentId]);
            $summary = $summaries[$studentId] ?? null;

            $elementScores = [];
            foreach ($elements as $element) {
                $eid = (int) ($element['id'] ?? 0);
                if ($eid <= 0) {
                    continue;
                }

                $assessment = $assessments[$studentId][$eid] ?? null;
                $elementScores[] = [
                    'id' => $eid,
                    'code' => $element['elemen_kode'] ?? '',
                    'name' => $element['elemen_nama'] ?? '',
                    'tp' => $element['tp_deskripsi'] ?? null,
                    'capaian' => $assessment['capaian_enum'] ?? null,
                    'nilai' => $assessment['nilai_opsional'] ?? null,
                    'catatan' => $assessment['catatan'] ?? null,
                ];
            }

            $results[] = [
                'id' => $projectId,
                'title' => $project['judul'] ?? '',
                'theme' => $project['tema'] ?? '',
                'description' => $project['deskripsi'] ?? null,
                'start_date' => $project['tanggal_mulai'] ?? null,
                'end_date' => $project['tanggal_selesai'] ?? null,
                'mentor' => $project['guru_pembimbing_nama'] ?? null,
                'elements' => $elementScores,
                'summary' => $summary,
            ];
        }

        return $results;
    }

    private function extractAttitudeNote(int $classId, int $schoolYearId, int $studentId, string $type): string
    {
        $records = AttitudeScore::byClassAndType($classId, $type);
        $record = $records[$studentId] ?? null;

        if ($record === null) {
            return '';
        }

        if ($schoolYearId > 0 && (int) ($record['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            return '';
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

        return '';
    }

    private function getSchoolProfile(): array
    {
        static $cached;

        if ($cached !== null) {
            return $cached;
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
            'akreditasi' => '',
            'logo_sekolah' => null,
            'logo_dinas' => null,
            'lambang_negara' => null,
            'tanggal_raport_tingkat_10_11' => null,
            'tanggal_raport_tingkat_12' => null,
            'tanggal_raport_tengah_semester' => null,
        ];

        $record = SchoolProfile::first();

        if ($record !== null) {
            $cached = array_merge($defaults, $record);
        } else {
            $cached = $defaults;
        }

        $activeYear = SchoolYear::active();

        if ($activeYear !== null) {
            $headmasterName = $this->resolveHeadmasterName((int) ($activeYear['kepala_sekolah_id'] ?? 0));
            if ($headmasterName !== null) {
                $cached['kepala_sekolah'] = $headmasterName;
            }

            $cached['tanggal_raport_tingkat_10_11'] = $activeYear['tanggal_raport_tingkat_10_11'] ?? null;
            $cached['tanggal_raport_tingkat_12'] = $activeYear['tanggal_raport_tingkat_12'] ?? null;
            $cached['tanggal_raport_tengah_semester'] = $activeYear['tanggal_raport_tengah_semester'] ?? null;
        }

        return $cached;
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

    private function resolvePrintedDateLabel(array $schoolProfile, array $class, ?string $documentType, DateTimeImmutable $printedAt): string
    {
        $documentType = $documentType ?? 'report_card';
        $classLevel = isset($class['tingkat']) ? (int) $class['tingkat'] : 0;

        if ($documentType === 'midterm_report') {
            $configuredDate = $schoolProfile['tanggal_raport_tengah_semester'] ?? null;
        } elseif ($classLevel === 12) {
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

        $timestamp = strtotime($date);

        if ($timestamp === false) {
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

        $month = $months[(int) date('n', $timestamp)] ?? date('F', $timestamp);

        return sprintf('%d %s %s', (int) date('j', $timestamp), $month, date('Y', $timestamp));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function attachDigitalSignature(array $context, string $documentType): array
    {
        $studentId = isset($context['student']['id']) ? (int) $context['student']['id'] : 0;
        $schoolYearId = (int) ($context['schoolYearId'] ?? 0);

        $defaultSignature = [
            'enabled' => false,
            'status' => 'inactive',
            'message' => 'TTD digital belum tersedia.',
            'documentType' => $documentType,
        ];

        if ($studentId <= 0 || $schoolYearId <= 0) {
            $defaultSignature['message'] = 'TTD digital membutuhkan data siswa dan tahun ajaran yang valid.';
            $context['digitalSignature'] = $defaultSignature;

            return $context;
        }

        $signatureYear = SchoolYear::find($schoolYearId);
        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);
        $isActiveSignatureYear = $activeYearId > 0 && $activeYearId === $schoolYearId;

        if ($signatureYear === null) {
            $defaultSignature['message'] = 'Tahun ajaran dokumen tidak ditemukan.';
            $context['digitalSignature'] = $defaultSignature;

            return $context;
        }

        if ((int) ($signatureYear['digital_signature_enabled'] ?? 0) !== 1) {
            $defaultSignature['message'] = 'TTD digital belum diaktifkan oleh admin.';
            $context['digitalSignature'] = $defaultSignature;

            return $context;
        }

        $classId = isset($context['class']['id']) ? (int) $context['class']['id'] : 0;
        $semester = isset($context['semester']) ? (int) $context['semester'] : 0;

        $documentKey = $this->makeDocumentKey($documentType, $studentId, $semester);
        $documentTitle = $this->makeDocumentTitle($documentType, $context);
        $payload = $this->buildDigitalSignaturePayload($context, $documentType);
        $requestedBy = (int) (auth()['id'] ?? 0);

        $record = DigitalDocumentSignature::findByDocument($schoolYearId, $documentType, $documentKey);

        if ($record === null && $isActiveSignatureYear) {
            $record = DigitalDocumentSignature::ensure(
                $schoolYearId,
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
                'status' => 'not_requested',
                'statusLabel' => 'Belum Diajukan',
                'message' => $isActiveSignatureYear
                    ? 'TTD digital belum diajukan.'
                    : 'Tidak ada riwayat pengajuan TTD digital untuk dokumen ini.',
                'documentType' => $documentType,
            ];

            return $context;
        }

        $context['digitalSignature'] = $this->formatSignatureRecord($record, $signatureYear, $documentType);

        return $context;
    }

    private function makeDocumentKey(string $documentType, int $studentId, int $semester): string
    {
        switch ($documentType) {
            case 'report_card':
                return sprintf('raport:%d:%d', $studentId, $semester);
            case 'midterm_report':
                return sprintf('midterm:%d:%d', $studentId, $semester);
            default:
                return sprintf('%s:%d:%d', $documentType, $studentId, $semester);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function makeDocumentTitle(string $documentType, array $context): string
    {
        $studentName = (string) ($context['student']['nama'] ?? 'Siswa');
        $semester = isset($context['semester']) ? (int) $context['semester'] : 0;
        $schoolYear = $context['schoolYear'] ?? null;
        $schoolYearName = is_array($schoolYear) ? (string) ($schoolYear['nama'] ?? '') : '';

        switch ($documentType) {
            case 'report_card':
                $periodSuffix = $schoolYearName !== '' ? ' (' . $schoolYearName . ')' : '';

                return sprintf('Raport Semester %d%s - %s', $semester, $periodSuffix, $studentName);
            case 'midterm_report':
                $periodSuffix = $schoolYearName !== '' ? ' (' . $schoolYearName . ')' : '';

                return sprintf('Laporan Tengah Semester %d%s - %s', $semester, $periodSuffix, $studentName);
            default:
                $label = ucwords(str_replace('_', ' ', $documentType));

                return sprintf('%s - %s', $label, $studentName);
        }
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function buildDigitalSignaturePayload(array $context, string $documentType): array
    {
        $student = $context['student'] ?? [];
        $class = $context['class'] ?? [];
        $subjects = $context['subjects'] ?? [];
        $attendance = $context['attendance'] ?? [];
        $achievements = $context['achievements'] ?? [];
        $attitudes = $context['attitudes'] ?? [];
        $homeroomNote = (string) ($context['homeroomNote'] ?? '');
        $extracurriculars = $context['extracurriculars'] ?? [];
        $prakerin = $context['prakerin'] ?? null;
        $cocurriculars = $context['cocurriculars'] ?? [];
        $p5Projects = $context['p5_projects'] ?? [];

        $subjectSummaries = [];

        foreach ($subjects as $group) {
            $groupSubjects = $group['subjects'] ?? [];

            foreach ($groupSubjects as $subject) {
                $knowledge = $subject['knowledge'] ?? [];
                $skill = $subject['skill'] ?? [];

                $subjectSummaries[] = [
                    'id' => (int) ($subject['subject_id'] ?? 0),
                    'code' => (string) ($subject['subject_code'] ?? ''),
                    'name' => (string) ($subject['subject_name'] ?? ''),
                    'knowledge_score' => $knowledge['score'] ?? null,
                    'knowledge_predicate' => $knowledge['predicate'] ?? null,
                    'skill_score' => $skill['score'] ?? null,
                    'skill_predicate' => $skill['predicate'] ?? null,
                ];
            }
        }

        $achievementSummaries = [];

        foreach ($achievements as $achievement) {
            $achievementSummaries[] = [
                'type' => (string) ($achievement['jenis'] ?? ''),
                'note' => (string) ($achievement['keterangan'] ?? ''),
            ];
        }

        $extracurricularSummaries = [];

        if (is_array($extracurriculars)) {
            foreach ($extracurriculars as $activity) {
                if (!is_array($activity)) {
                    continue;
                }

                $extracurricularSummaries[] = [
                    'activity_id' => isset($activity['ekstrakurikuler_id']) ? (int) $activity['ekstrakurikuler_id'] : null,
                    'activity_name' => (string) ($activity['ekstrakurikuler_nama'] ?? ''),
                    'final_score' => isset($activity['nilai_akhir']) ? (float) $activity['nilai_akhir'] : null,
                    'predicate' => $activity['predikat'] ?? null,
                    'description' => $activity['deskripsi'] ?? null,
                ];
            }
        }

        $cocurricularSummaries = [];

        if (is_array($cocurriculars)) {
            foreach ($cocurriculars as $activity) {
                if (!is_array($activity)) {
                    continue;
                }

                $cocurricularSummaries[] = [
                    'id' => isset($activity['id']) ? (int) $activity['id'] : null,
                    'name' => (string) ($activity['name'] ?? ''),
                    'theme' => (string) ($activity['theme'] ?? ''),
                    'summary' => (string) ($activity['summary'] ?? ''),
                    'follow_up' => (string) ($activity['follow_up'] ?? ''),
                ];
            }
        }

        $prakerinSummary = null;

        if (is_array($prakerin)) {
            $hasData = false;

            foreach ($prakerin as $value) {
                if ($value !== null && $value !== '') {
                    $hasData = true;
                    break;
                }
            }

            if ($hasData) {
                $prakerinSummary = [
                    'place_id' => isset($prakerin['place_id']) ? (int) $prakerin['place_id'] : null,
                    'place_name' => (string) ($prakerin['place_name'] ?? ''),
                    'start_date' => $prakerin['start_date'] ?? null,
                    'end_date' => $prakerin['end_date'] ?? null,
                    'nilai_keaktifan' => isset($prakerin['nilai_keaktifan']) ? (float) $prakerin['nilai_keaktifan'] : null,
                    'nilai_jurnal' => isset($prakerin['nilai_jurnal']) ? (float) $prakerin['nilai_jurnal'] : null,
                    'nilai_laporan' => isset($prakerin['nilai_laporan']) ? (float) $prakerin['nilai_laporan'] : null,
                    'nilai_akhir' => isset($prakerin['nilai_akhir']) ? (float) $prakerin['nilai_akhir'] : null,
                    'predikat' => $prakerin['predikat'] ?? null,
                ];
            }
        }

        return [
            'document_type' => $documentType,
            'school_year_id' => (int) ($context['schoolYearId'] ?? 0),
            'semester' => (int) ($context['semester'] ?? 0),
            'student' => [
                'id' => (int) ($student['id'] ?? 0),
                'name' => (string) ($student['nama'] ?? ''),
                'nisn' => (string) ($student['nisn'] ?? ''),
                'nipd' => (string) ($student['nipd'] ?? ''),
            ],
            'class' => [
                'id' => (int) ($class['id'] ?? 0),
                'name' => (string) ($class['nama'] ?? ''),
                'level' => (string) ($class['tingkat'] ?? ''),
            ],
            'subjects' => $subjectSummaries,
            'attendance' => [
                'sakit' => (int) ($attendance['sakit'] ?? 0),
                'izin' => (int) ($attendance['izin'] ?? 0),
                'bolos' => (int) ($attendance['bolos'] ?? 0),
                'alpa' => (int) ($attendance['alpa'] ?? 0),
            ],
            'achievements' => $achievementSummaries,
            'extracurriculars' => $extracurricularSummaries,
            'cocurriculars' => $cocurricularSummaries,
            'prakerin' => $prakerinSummary,
            'attitudes' => [
                'spiritual' => (string) ($attitudes['spiritual'] ?? ''),
                'social' => (string) ($attitudes['social'] ?? ''),
            ],
            'homeroom_note' => $homeroomNote,
            'p5_projects' => $p5Projects,
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

        $approvedAtLabel = '';
        $approvedAtRaw = $record['approved_at'] ?? null;

        if (is_string($approvedAtRaw) && $approvedAtRaw !== '') {
            $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $approvedAtRaw);

            if ($dateTime !== false) {
                $approvedAtLabel = $this->formatIndonesianDate($dateTime->format('Y-m-d')) . ' ' . $dateTime->format('H:i');
            } else {
                $approvedAtLabel = $approvedAtRaw;
            }
        }

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
            'approvedAt' => $record['approved_at'] ?? null,
            'approvedAtLabel' => $approvedAtLabel,
            'payload' => $payload,
        ];
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
        $role = (string) ($user['role'] ?? '');
        $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;

        if ($classId > 0) {
            $students = \App\Models\Student::byClass($classId, null, $keyword);
        } elseif ($role === 'guru' && $teacherId > 0) {
            $classes = \App\Models\Classroom::homeroomClassesForTeacher($teacherId);
            $classIds = array_values(array_map(static fn ($c) => (int) ($c['id'] ?? 0), $classes));
            $students = \App\Models\Student::byClasses($classIds, null, $keyword);
        } else {
            $students = \App\Models\Student::allWithRelations(null, null, $keyword);
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

        $activeYear = \App\Models\SchoolYear::active();
        $activeYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : 0;
        $activeSemester = $activeYear !== null ? (int) ($activeYear['semester_aktif'] ?? 0) : 1;
        if (!in_array($activeSemester, [1, 2], true)) {
            $activeSemester = 1;
        }
        $documentType = 'report_card';

        if ($activeYearId > 0 && !empty($results) && (int) ($activeYear['digital_signature_enabled'] ?? 0) === 1) {
            $studentIds = array_values(array_map(static fn ($r) => (int) ($r['id'] ?? 0), $results));
            $sigSql = "SELECT student_id, status FROM digital_document_signatures WHERE tahun_ajaran_id = ? AND document_type = ? AND (";
            $keyConditions = [];
            $keyParams = [$activeYearId, $documentType];
            foreach ($studentIds as $sid) {
                $keyConditions[] = "document_key = ?";
                $keyParams[] = sprintf('raport:%d:%d', $sid, $activeSemester);
            }
            $sigSql .= implode(' OR ', $keyConditions) . ")";
            $sigStmt = \Core\Database::connection()->prepare($sigSql);
            if ($sigStmt !== false && $sigStmt->execute($keyParams)) {
                $sigRows = $sigStmt->fetchAll(\PDO::FETCH_ASSOC);
                $sigMap = [];
                if ($sigRows !== false) {
                    foreach ($sigRows as $sr) {
                        $sigMap[(int) ($sr['student_id'] ?? 0)] = $sr['status'] ?? 'pending';
                    }
                }
                foreach ($results as &$res) {
                    $sid = (int) ($res['id'] ?? 0);
                    if (isset($sigMap[$sid])) {
                        $status = $sigMap[$sid];
                        $res['signature_status'] = $status;
                        $labels = [
                            'pending' => 'Menunggu',
                            'approved' => 'Disetujui',
                            'revoked' => 'Dicabut',
                            'missing' => 'Belum Diajukan',
                        ];
                        $res['signature_label'] = $labels[$status] ?? $status;
                    }
                }
                unset($res);
            }
        }

        return Response::json(['data' => $results]);
    }

    private function renderSection(Request $request, string $view, string $title, ?string $documentType = null): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $context = $this->buildReportContext($request, $documentType);

        if ($context === null) {
            $message = Session::getFlash('error', 'Data raport tidak ditemukan atau akses ditolak.');

            return $this->render('reports/sections/error', [
                'title' => $title,
                'message' => $message,
            ], 'print');
        }

        $paperSize = strtolower((string) $request->query('paper', 'f4'));
        if (!in_array($paperSize, ['f4', 'a4'], true)) {
            $paperSize = 'f4';
        }

        return $this->render($view, [
            'title' => $title,
            'report' => $context,
            'paperSize' => $paperSize,
        ], 'print');
    }
}

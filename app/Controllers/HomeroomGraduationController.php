<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\DigitalDocumentSignature;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentGraduationStatus;
use App\Services\GraduationCertificateService;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class HomeroomGraduationController extends Controller
{
    protected ?string $layout = 'admin';

    /**
     * @param array<string, mixed> $context
     */
    protected function renderView(array $context = []): Response
    {
        return $this->render('homeroom/graduations/index', array_merge([
            'title' => 'Status Kelulusan',
            'pageTitle' => 'Status Kelulusan',
            'activeMenu' => 'homeroom-graduations',
        ], $context));
    }

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

        $activeYear = SchoolYear::active();
        $activeSemester = (int) ($activeYear['semester_aktif'] ?? 0);

        if ($activeSemester !== 2) {
            Session::flash('error', 'Status kelulusan hanya tersedia pada semester genap.');

            return $this->redirect('walikelas/catatan');
        }

        $teacherId = (int) $user['teacher_id'];

        $classes = $activeYear !== null
            ? Classroom::homeroomClassesForTeacher($teacherId, (int) ($activeYear['id'] ?? 0))
            : [];

        if (empty($classes)) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId);
        }

        $classes = array_values(array_filter($classes, static function ($class) {
            return (int) ($class['tingkat'] ?? 0) === 12;
        }));

        if (empty($classes)) {
            Session::flash('error', 'Tidak ada kelas tingkat 12 yang Anda ampu.');

            return $this->redirect('dashboard');
        }

        $selectedClassId = (int) $request->query('kelas_id', 0);
        $selectedClass = null;

        foreach ($classes as $class) {
            if ((int) ($class['id'] ?? 0) === $selectedClassId) {
                $selectedClass = $class;
                break;
            }
        }

        if ($selectedClass === null) {
            $selectedClass = $classes[0];
            $selectedClassId = (int) ($selectedClass['id'] ?? 0);
        }

        $students = [];
        $records = [];
        $signatureRecords = [];
        $signatureSummary = [
            'total' => 0,
            'requested' => 0,
            'pending' => 0,
            'approved' => 0,
            'revoked' => 0,
            'not_requested' => 0,
        ];
        $schoolYearId = $selectedClass !== null ? (int) ($selectedClass['tahun_ajaran_id'] ?? 0) : null;
        $schoolYearFilter = ($schoolYearId !== null && $schoolYearId > 0) ? $schoolYearId : null;

        if ($selectedClass !== null) {
            $students = Student::byClass($selectedClassId, $schoolYearFilter);
            $records = StudentGraduationStatus::byClass($selectedClassId, $schoolYearFilter);
            if ($schoolYearFilter !== null) {
                $signatureRecords = DigitalDocumentSignature::mapByClass(
                    $schoolYearFilter,
                    $selectedClassId,
                    GraduationCertificateService::DOCUMENT_TYPE
                );
                $signatureSummary = $this->summarizeSignatures($students, $signatureRecords);
            }
        }

        return $this->renderView([
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'records' => $records,
            'signatureRecords' => $signatureRecords,
            'signatureSummary' => $signatureSummary,
            'digitalSignatureEnabled' => (int) ($activeYear['digital_signature_enabled'] ?? 0) === 1,
            'oldStatuses' => old('status', []),
            'oldNotes' => old('catatan', []),
            'oldDiplomaNumbers' => old('nomor_ijazah', []),
            'oldSpecializationTypes' => old('jenis_kekhususan', []),
        ]);
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/status-lulus')) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $activeYear = SchoolYear::active();
        $activeSemester = (int) ($activeYear['semester_aktif'] ?? 0);

        if ($activeSemester !== 2) {
            Session::flash('error', 'Status kelulusan hanya dapat disimpan pada semester genap.');

            return $this->redirect('walikelas/catatan');
        }

        $teacherId = (int) $user['teacher_id'];
        $classId = (int) $request->input('kelas_id', 0);

        if ($classId <= 0) {
            Session::flash('error', 'Kelas tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/status-lulus');
        }

        $classes = Classroom::homeroomClassesForTeacher($teacherId);
        $allowedClasses = array_values(array_filter($classes, static function ($class) {
            return (int) ($class['tingkat'] ?? 0) === 12;
        }));

        $selectedClass = null;

        foreach ($allowedClasses as $class) {
            if ((int) ($class['id'] ?? 0) === $classId) {
                $selectedClass = $class;
                break;
            }
        }

        if ($selectedClass === null) {
            Session::flash('error', 'Anda tidak memiliki akses ke kelas terpilih.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/status-lulus');
        }

        $schoolYearId = (int) ($selectedClass['tahun_ajaran_id'] ?? 0);

        if ($schoolYearId <= 0) {
            Session::flash('error', 'Tahun ajaran pada kelas tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/status-lulus?kelas_id=' . urlencode((string) $classId));
        }

        $students = Student::byClass($classId, $schoolYearId);

        if (empty($students)) {
            Session::flash('error', 'Belum ada siswa pada kelas terpilih.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/status-lulus?kelas_id=' . urlencode((string) $classId));
        }

        $statuses = $request->input('status', []);
        $notes = $request->input('catatan', []);
        $diplomaNumbers = $request->input('nomor_ijazah', []);
        $specializationTypes = $request->input('jenis_kekhususan', []);

        if (!is_array($statuses)) {
            $statuses = [];
        }

        if (!is_array($notes)) {
            $notes = [];
        }

        if (!is_array($diplomaNumbers)) {
            $diplomaNumbers = [];
        }

        if (!is_array($specializationTypes)) {
            $specializationTypes = [];
        }

        $payloads = [];

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $payloads[$studentId] = [
                'status' => $statuses[$studentId] ?? null,
                'catatan' => $notes[$studentId] ?? null,
                'nomor_ijazah' => $diplomaNumbers[$studentId] ?? null,
                'jenis_kekhususan' => $specializationTypes[$studentId] ?? null,
            ];
        }

        try {
            StudentGraduationStatus::saveMany($classId, $schoolYearId, $teacherId, $payloads);
            Session::flash('success', 'Status kelulusan berhasil disimpan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Terjadi kesalahan saat menyimpan status kelulusan: ' . $exception->getMessage());
            Session::flashInput($request->all());
        }

        return $this->redirect('walikelas/status-lulus?kelas_id=' . urlencode((string) $classId));
    }

    /**
     * @param array<int, array<string, mixed>> $students
     * @param array<int, array<string, mixed>> $signatures
     *
     * @return array<string, int>
     */
    private function summarizeSignatures(array $students, array $signatures): array
    {
        $summary = [
            'total' => count($students),
            'requested' => 0,
            'pending' => 0,
            'approved' => 0,
            'revoked' => 0,
            'not_requested' => 0,
        ];

        foreach ($signatures as $record) {
            $status = (string) ($record['status'] ?? 'pending');
            $summary['requested']++;

            if ($status === 'approved') {
                $summary['approved']++;
            } elseif ($status === 'revoked') {
                $summary['revoked']++;
            } else {
                $summary['pending']++;
            }
        }

        $summary['not_requested'] = max(0, $summary['total'] - $summary['requested']);

        return $summary;
    }
}

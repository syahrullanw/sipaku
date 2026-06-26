<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\PrakerinAssessment;
use App\Models\PrakerinPlace;
use App\Models\SchoolYear;
use App\Models\HomeroomPrakerinConfirmation;
use App\Models\Student;
use App\Models\StudentPrakerinPlacement;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use Throwable;

class HomeroomPrakerinController extends Controller
{
    protected ?string $layout = 'admin';

    /**
     * @param array<string, mixed> $context
     */
    protected function renderView(array $context = []): Response
    {
        return $this->render('homeroom/prakerin/index', array_merge([
            'title' => 'Penempatan Prakerin',
            'pageTitle' => 'Penempatan Prakerin',
            'activeMenu' => 'homeroom-prakerin',
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

        $teacherId = (int) $user['teacher_id'];

        $activeYear = SchoolYear::active();
        $activeYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : null;

        $classes = [];

        if ($activeYearId !== null && $activeYearId > 0) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId, $activeYearId);
        }

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

        $places = PrakerinPlace::options();
        $prakerinRequired = true;
        if ($selectedClassId > 0) {
            $confirmationMap = HomeroomPrakerinConfirmation::mapByClassIds([$selectedClassId], $teacherId);
            $confirmation = $confirmationMap[$selectedClassId] ?? null;
            if ($confirmation !== null) {
                $prakerinRequired = (int) ($confirmation['prakerin_required'] ?? 1) === 1;
            }
        }

        $students = [];
        $placements = [];
        $unassignedStudents = [];
        $assessments = [];
        $classSchoolYearId = null;
        $isActiveMismatch = false;

        if ($selectedClass !== null) {
            $classSchoolYearId = isset($selectedClass['tahun_ajaran_id']) ? (int) $selectedClass['tahun_ajaran_id'] : null;

            if ($activeYearId !== null && $classSchoolYearId !== $activeYearId) {
                $isActiveMismatch = true;
            }

            $students = Student::byClass($selectedClassId, $classSchoolYearId);
            $placements = StudentPrakerinPlacement::byClass($selectedClassId, $classSchoolYearId);

            foreach ($students as $student) {
                $studentId = (int) ($student['id'] ?? 0);
                if ($studentId <= 0) {
                    continue;
                }

                $placement = $placements[$studentId]['tempat_prakerin_id'] ?? null;
                if ($placement === null || $placement <= 0) {
                    $unassignedStudents[] = $student;
                }
            }

            if (!empty($students) && $classSchoolYearId !== null && $classSchoolYearId > 0) {
                $studentIds = array_values(array_filter(array_map(
                    static fn (array $studentRow): int => (int) ($studentRow['id'] ?? 0),
                    $students
                ), static fn (int $id): bool => $id > 0));

                if (!empty($studentIds)) {
                    $assessments = PrakerinAssessment::byStudents($studentIds, $classSchoolYearId);
                }
            }
        }

        return $this->renderView([
            'activeYear' => $activeYear,
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'places' => $places,
            'students' => $students,
            'placements' => $placements,
            'unassignedStudents' => $unassignedStudents,
            'isActiveMismatch' => $isActiveMismatch,
            'assessments' => $assessments,
            'oldPlacements' => old('placements', []),
            'prakerinRequired' => $prakerinRequired,
        ]);
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/prakerin')) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $teacherId = (int) $user['teacher_id'];

        $classId = (int) $request->input('kelas_id', 0);

        if ($classId <= 0) {
            Session::flash('error', 'Kelas tidak valid.');

            return $this->redirect('walikelas/prakerin');
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null) {
            Session::flash('error', 'Belum ada semester aktif. Hubungi admin untuk mengatur tahun ajaran.');

            return $this->redirect('walikelas/prakerin');
        }

        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYearId <= 0) {
            Session::flash('error', 'Data semester aktif tidak valid.');

            return $this->redirect('walikelas/prakerin');
        }

        $availableClasses = Classroom::homeroomClassesForTeacher($teacherId);

        $selectedClass = null;
        foreach ($availableClasses as $class) {
            if ((int) ($class['id'] ?? 0) === $classId) {
                $selectedClass = $class;
                break;
            }
        }

        if ($selectedClass === null) {
            Session::flash('error', 'Anda tidak terdaftar sebagai wali kelas untuk kelas terpilih.');

            return $this->redirect('walikelas/prakerin');
        }

        $confirmationMap = HomeroomPrakerinConfirmation::mapByClassIds([$classId], $teacherId);
        $confirmation = $confirmationMap[$classId] ?? null;
        $prakerinRequired = $confirmation === null
            ? true
            : ((int) ($confirmation['prakerin_required'] ?? 1) === 1);

        if (!$prakerinRequired) {
            Session::flash('error', 'Penempatan prakerin dinonaktifkan untuk kelas ini.');

            return $this->redirect('walikelas/prakerin?kelas_id=' . urlencode((string) $classId));
        }

        $schoolYearId = isset($selectedClass['tahun_ajaran_id']) ? (int) $selectedClass['tahun_ajaran_id'] : 0;

        if ($schoolYearId !== $activeYearId) {
            Session::flash('error', 'Penempatan prakerin hanya dapat dilakukan pada semester aktif.');

            return $this->redirect('walikelas/prakerin?kelas_id=' . urlencode((string) $classId));
        }

        if ($schoolYearId <= 0) {
            Session::flash('error', 'Data tahun ajaran pada kelas tidak valid.');

            return $this->redirect('walikelas/prakerin?kelas_id=' . urlencode((string) $classId));
        }

        $students = Student::byClass($classId, $schoolYearId);

        if (empty($students)) {
            Session::flash('error', 'Belum ada siswa pada kelas terpilih.');

            return $this->redirect('walikelas/prakerin?kelas_id=' . urlencode((string) $classId));
        }

        $placeOptions = PrakerinPlace::options();

        if (empty($placeOptions)) {
            Session::flash('error', 'Belum ada data tempat prakerin. Hubungi admin untuk menambahkan daftar industri.');

            return $this->redirect('walikelas/prakerin?kelas_id=' . urlencode((string) $classId));
        }

        $placementInputs = $request->input('placements', []);

        if (!is_array($placementInputs)) {
            $placementInputs = [];
        }

        $payloads = [];
        $errors = [];

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $selectedPlace = $placementInputs[$studentId] ?? null;
            $placeId = is_array($selectedPlace) ? (int) reset($selectedPlace) : (int) $selectedPlace;

            if ($placeId <= 0 || !array_key_exists($placeId, $placeOptions)) {
                $errors[] = sprintf(
                    'Pilih tempat prakerin untuk %s.',
                    $student['nama'] ?? 'siswa'
                );
                continue;
            }

            $payloads[$studentId] = $placeId;
        }

        if (count($payloads) !== count($students)) {
            Session::flashInput($request->all());
            if (empty($errors)) {
                $errors[] = 'Semua siswa wajib memiliki tempat prakerin.';
            }
            Session::flash('error', implode(' ', $errors));

            return $this->redirect('walikelas/prakerin?kelas_id=' . urlencode((string) $classId));
        }

        try {
            StudentPrakerinPlacement::saveMany($classId, $schoolYearId, $teacherId, $payloads);
            Session::flash('success', 'Penempatan prakerin berhasil disimpan.');
        } catch (Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan penempatan prakerin: ' . $exception->getMessage());
        }

        return $this->redirect('walikelas/prakerin?kelas_id=' . urlencode((string) $classId));
    }
}

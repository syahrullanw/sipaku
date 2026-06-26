<?php

namespace App\Controllers;

use App\Models\Attitude;
use App\Models\AttitudeScore;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class HomeroomAttitudeController extends Controller
{
    protected ?string $layout = 'admin';

    /**
     * @param array<string, mixed> $context
     */
    protected function renderView(string $type, array $context = []): Response
    {
        $title = $type === 'sosial' ? 'Nilai Sikap Sosial' : 'Nilai Sikap Spiritual';

        return $this->render('homeroom/attitudes/index', array_merge([
            'title' => $title,
            'pageTitle' => $title,
            'activeMenu' => 'homeroom-attitudes-' . $type,
            'type' => $type,
            'typeLabel' => Attitude::TYPES[$type] ?? $title,
        ], $context));
    }

    public function index(Request $request, string $jenis): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $type = strtolower($jenis);

        if (!array_key_exists($type, Attitude::TYPES)) {
            return $this->redirect('walikelas/nilai-sikap/spiritual');
        }

        $teacherId = (int) $user['teacher_id'];

        $activeYear = SchoolYear::active();

        $classes = $activeYear !== null
            ? Classroom::homeroomClassesForTeacher($teacherId, (int) $activeYear['id'])
            : [];

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

        $isKurmerClass = ($selectedClass['kurikulum'] ?? 'k13') === 'kurmer';

        $students = [];
        $scores = [];
        $attitudeOptions = Attitude::options($type);
        $attitudeList = array_values(array_filter(
            Attitude::allOrdered($type),
            static fn (array $item): bool => ($item['status'] ?? 'aktif') === 'aktif'
        ));

        if ($selectedClass !== null && !$isKurmerClass) {
            $students = Student::byClass($selectedClassId, isset($selectedClass['tahun_ajaran_id']) ? (int) $selectedClass['tahun_ajaran_id'] : null);
            $scores = AttitudeScore::byClassAndType($selectedClassId, $type);
        }

        return $this->renderView($type, [
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'attitudeOptions' => $attitudeOptions,
            'scores' => $scores,
            'attitudeList' => $attitudeList,
            'oldSelalu' => old('selalu', []),
            'oldMeningkat' => old('meningkat', []),
            'oldNotes' => old('notes', []),
            'isKurmerClass' => $isKurmerClass,
        ]);
    }

    public function store(Request $request, string $jenis): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/nilai-sikap/' . urlencode($jenis))) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $type = strtolower($jenis);

        if (!array_key_exists($type, Attitude::TYPES)) {
            return $this->redirect('walikelas/nilai-sikap/spiritual');
        }

        $teacherId = (int) $user['teacher_id'];

        $classId = (int) $request->input('kelas_id', 0);

        if ($classId <= 0) {
            Session::flash('error', 'Kelas tidak valid.');

            return $this->redirect('walikelas/nilai-sikap/' . urlencode($type));
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

            return $this->redirect('walikelas/nilai-sikap/' . urlencode($type));
        }

        if (($selectedClass['kurikulum'] ?? 'k13') === 'kurmer') {
            Session::flash('error', 'Penilaian sikap tidak digunakan pada kelas Kurikulum Merdeka.');

            return $this->redirect('walikelas/nilai-sikap/' . urlencode($type) . '?kelas_id=' . urlencode((string) $classId));
        }

        $schoolYearId = (int) ($selectedClass['tahun_ajaran_id'] ?? 0);

        if ($schoolYearId <= 0) {
            Session::flash('error', 'Data tahun ajaran pada kelas tidak valid.');

            return $this->redirect('walikelas/nilai-sikap/' . urlencode($type) . '?kelas_id=' . urlencode((string) $classId));
        }

        $students = Student::byClass($classId, $schoolYearId);

        if (empty($students)) {
            Session::flash('error', 'Belum ada siswa pada kelas terpilih.');

            return $this->redirect('walikelas/nilai-sikap/' . urlencode($type) . '?kelas_id=' . urlencode((string) $classId));
        }

        $attitudeOptions = Attitude::options($type);

        $selaluInputs = $request->input('selalu', []);
        $meningkatInputs = $request->input('meningkat', []);
        $noteInputs = $request->input('notes', []);

        $payloads = [];
        $errors = [];

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            if (student_is_inactive($student)) {
                continue;
            }

            $selalu = $selaluInputs[$studentId] ?? [];
            if (!is_array($selalu)) {
                $selalu = [$selalu];
            }

            $selalu = array_values(array_filter(array_map(static fn ($value) => (int) $value, $selalu), static fn ($value) => $value > 0));

            $selalu = array_values(array_unique(array_filter($selalu, static fn ($id) => array_key_exists($id, $attitudeOptions))));

            $meningkat = $meningkatInputs[$studentId] ?? null;
            $meningkatId = null;
            if ($meningkat !== null && $meningkat !== '') {
                $candidate = (int) $meningkat;
                if ($candidate > 0 && array_key_exists($candidate, $attitudeOptions)) {
                    $meningkatId = $candidate;
                }
            }

            if (count($selalu) < 2 || $meningkatId === null) {
                $errors[] = sprintf(
                    'Lengkapi pilihan 2 sikap yang selalu dilakukan dan 1 sikap yang mulai meningkat untuk %s.',
                    $student['nama'] ?? 'siswa'
                );
            } elseif (count($selalu) > 1 && $selalu[0] === $selalu[1]) {
                $errors[] = sprintf(
                    'Pilih indikator berbeda untuk kategori "Selalu Dilakukan" bagi %s.',
                    $student['nama'] ?? 'siswa'
                );
            } elseif (in_array($meningkatId, array_slice($selalu, 0, 2), true)) {
                $errors[] = sprintf(
                    'Indikator "Mulai Meningkat" untuk %s tidak boleh sama dengan indikator yang dipilih sebagai "Selalu Dilakukan".',
                    $student['nama'] ?? 'siswa'
                );
            }

            $payloads[$studentId] = [
                'selalu' => array_slice($selalu, 0, 2),
                'meningkat' => $meningkatId,
                'catatan' => $noteInputs[$studentId] ?? null,
            ];
        }

        if (!empty($errors)) {
            Session::flashInput($request->all());
            Session::flash('error', implode(' ', $errors));

            return $this->redirect('walikelas/nilai-sikap/' . urlencode($type) . '?kelas_id=' . urlencode((string) $classId));
        }

        try {
            AttitudeScore::saveMany($classId, $schoolYearId, $teacherId, $type, $payloads);
            Session::flash('success', 'Nilai sikap berhasil disimpan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan nilai sikap: ' . $exception->getMessage());
        }

        return $this->redirect('walikelas/nilai-sikap/' . urlencode($type) . '?kelas_id=' . urlencode((string) $classId));
    }
}

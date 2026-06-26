<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentPromotionStatus;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class StudentPlacementController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);
        $activeSemester = (int) ($activeYear['semester_aktif'] ?? 0);
        $classOptions = [];
        $selectedClassId = (int) $request->query('kelas_id', 0);
        $searchKeyword = trim((string) $request->query('q', ''));
        $selectedClass = null;
        $assignedStudents = [];
        $activeYearStudentCount = Student::countBySchoolYear($activeYearId);
        $previousEvenYear = $activeYearId > 0 ? SchoolYear::previousEvenSemester($activeYearId) : null;

        if ($activeYear !== null) {
            $classOptions = Classroom::options($activeYearId);

            if ($selectedClassId === 0 && !empty($classOptions)) {
                $firstKey = array_key_first($classOptions);
                if ($firstKey !== null) {
                    $selectedClassId = (int) $firstKey;
                }
            }

            if ($selectedClassId > 0) {
                $selectedClass = Classroom::findWithRelations($selectedClassId);
                $assignedStudents = Student::byClass($selectedClassId, $activeYearId);
            }
        }

        $unassignedStudents = Student::unassigned();
        $promotionDisabledReason = null;
        $canPromoteFromPrevious = false;

        if ($activeYear === null) {
            $promotionDisabledReason = 'Tidak ada tahun ajaran aktif.';
        } elseif ($activeSemester !== 1) {
            $promotionDisabledReason = 'Naik kelas hanya tersedia saat Semester 1 (Ganjil).';
        } elseif ($activeYearStudentCount > 0) {
            $promotionDisabledReason = 'Penempatan semester aktif sudah berisi siswa. Kosongkan atau salin data secara manual sebelum menaikkan kelas.';
        } elseif ($previousEvenYear === null) {
            $promotionDisabledReason = 'Tidak ditemukan semester genap sebelumnya. Salin atau aktifkan data semester genap lebih dulu.';
        } else {
            $canPromoteFromPrevious = true;
        }

        return $this->render('master/student_placements/index', [
            'title' => 'Penempatan Siswa',
            'pageTitle' => 'Penempatan Siswa ke Kelas',
            'activeMenu' => 'student-placements',
            'activeYear' => $activeYear,
            'classOptions' => $classOptions,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'assignedStudents' => $assignedStudents,
            'unassignedStudents' => $unassignedStudents,
            'searchKeyword' => $searchKeyword,
            'canPromoteFromPrevious' => $canPromoteFromPrevious,
            'promotionDisabledReason' => $promotionDisabledReason,
            'promotionSourceYear' => $previousEvenYear,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/siswa/penempatan')) {
            return $response;
        }

        $searchKeyword = trim((string) $request->input('q', ''));

        $action = strtolower(trim((string) $request->input('action', '')));
        $classId = (int) $request->input('class_id', 0);
        $studentIds = $request->input('student_ids', []);
        $query = [];
        if ($classId > 0) {
            $query['kelas_id'] = $classId;
        }
        if ($searchKeyword !== '') {
            $query['q'] = $searchKeyword;
        }
        $redirectUrl = 'master/siswa/penempatan';
        if (!empty($query)) {
            $redirectUrl .= '?' . http_build_query($query);
        }

        if ($action === 'promote') {
            return $this->promoteFromPreviousSemester();
        }

        if (!in_array($action, ['assign', 'unassign'], true)) {
            Session::flash('error', 'Aksi tidak dikenal.');

            return $this->redirect($redirectUrl);
        }

        if (!is_array($studentIds)) {
            $studentIds = [];
        }

        $studentIds = array_values(array_filter(
            array_map(static fn ($id) => (int) $id, $studentIds),
            static fn (int $id) => $id > 0
        ));

        if (empty($studentIds)) {
            Session::flash('error', 'Pilih minimal satu siswa.');

            return $this->redirect($redirectUrl);
        }

        if ($action === 'assign') {
            if ($classId <= 0) {
                Session::flash('error', 'Pilih kelas tujuan terlebih dahulu.');

                return $this->redirect('master/siswa/penempatan');
            }

            $activeYear = SchoolYear::active();
            if ($activeYear === null) {
                Session::flash('error', 'Tidak ada tahun ajaran aktif.');

                return $this->redirect('master/siswa/penempatan');
            }

            $classroom = Classroom::find($classId);
            if ($classroom === null) {
                Session::flash('error', 'Kelas tidak ditemukan.');

                return $this->redirect('master/siswa/penempatan');
            }

            $classYearId = (int) ($classroom['tahun_ajaran_id'] ?? 0);
            if ($classYearId !== (int) $activeYear['id']) {
                Session::flash('error', 'Penempatan hanya bisa dilakukan pada kelas di tahun ajaran aktif.');

                return $this->redirect('master/siswa/penempatan');
            }

            $success = Student::assignToClass($studentIds, $classId, $classYearId);

            if ($success) {
                Session::flash('success', sprintf('Berhasil menempatkan %d siswa ke kelas terpilih.', count($studentIds)));
            } else {
                Session::flash('error', 'Gagal memperbarui penempatan siswa.');
            }

            return $this->redirect($redirectUrl);
        }

        $success = Student::clearClassAssignments($studentIds);

        if ($success) {
            Session::flash('success', sprintf('Berhasil mengosongkan kelas untuk %d siswa.', count($studentIds)));
        } else {
            Session::flash('error', 'Gagal mengosongkan penempatan siswa.');
        }

        return $this->redirect($redirectUrl);
    }

    private function promoteFromPreviousSemester(): Response
    {
        $redirectUrl = 'master/siswa/penempatan';

        $activeYear = SchoolYear::active();
        if ($activeYear === null) {
            Session::flash('error', 'Tidak ada tahun ajaran aktif.');
            return $this->redirect($redirectUrl);
        }

        $activeYearId = (int) ($activeYear['id'] ?? 0);
        $activeSemester = (int) ($activeYear['semester_aktif'] ?? 0);

        if ($activeSemester !== 1) {
            Session::flash('error', 'Naik kelas hanya dapat dilakukan pada semester ganjil.');
            return $this->redirect($redirectUrl);
        }

        if (Student::countBySchoolYear($activeYearId) > 0) {
            Session::flash('error', 'Penempatan siswa pada semester aktif sudah terisi.');
            return $this->redirect($redirectUrl);
        }

        $previousEvenYear = SchoolYear::previousEvenSemester($activeYearId);
        if ($previousEvenYear === null) {
            Session::flash('error', 'Tidak ditemukan semester genap sebelumnya untuk sumber data.');
            return $this->redirect($redirectUrl);
        }

        $sourceYearId = (int) ($previousEvenYear['id'] ?? 0);
        if ($sourceYearId <= 0) {
            Session::flash('error', 'Tahun ajaran sumber tidak valid.');
            return $this->redirect($redirectUrl);
        }

        $promotedStudents = StudentPromotionStatus::promotedStudentsForYear($sourceYearId);
        if (empty($promotedStudents)) {
            Session::flash('warning', 'Tidak ada siswa dengan status naik kelas pada semester genap sebelumnya.');
            return $this->redirect($redirectUrl);
        }

        $targetClasses = Classroom::byYear($activeYearId);
        if (empty($targetClasses)) {
            Session::flash('error', 'Belum ada kelas pada tahun ajaran aktif. Tambahkan kelas terlebih dahulu.');
            return $this->redirect($redirectUrl);
        }

        [$lookupBySuffix, $fallbackLookup] = $this->buildPromotionClassLookup($targetClasses);

        $assignments = [];
        $processedStudents = [];
        $skipped = 0;
        $duplicateCount = 0;
        $missingTargets = [];

        foreach ($promotedStudents as $record) {
            $studentId = (int) ($record['siswa_id'] ?? 0);

            if ($studentId <= 0 || isset($processedStudents[$studentId])) {
                $duplicateCount++;
                $skipped++;
                continue;
            }

            $targetClassId = $this->resolvePromotionTargetClass($record, $lookupBySuffix, $fallbackLookup);

            if ($targetClassId === null) {
                $missingTargets[] = $this->buildMissingTargetLabel($record);
                $skipped++;
                continue;
            }

            $assignments[$targetClassId][] = $studentId;
            $processedStudents[$studentId] = true;
        }

        $assignedCount = 0;

        foreach ($assignments as $targetClassId => $studentList) {
            if (empty($studentList)) {
                continue;
            }

            $success = Student::assignToClass($studentList, (int) $targetClassId, $activeYearId);

            if ($success) {
                $assignedCount += count($studentList);
            } else {
                $skipped += count($studentList);
                $missingTargets[] = 'ID kelas: ' . (int) $targetClassId;
            }
        }

        if ($assignedCount > 0) {
            $message = sprintf('Berhasil menaikkan kelas %d siswa ke semester aktif.', $assignedCount);
            if ($skipped > 0) {
                $message .= sprintf(' %d siswa dilewati karena kelas tujuan belum ditemukan.', $skipped);
            }
            if ($duplicateCount > 0) {
                $message .= sprintf(' %d siswa memiliki entri ganda dan dilewati.', $duplicateCount);
            }
            if (!empty($missingTargets)) {
                $message .= ' Pastikan kelas berikut tersedia di semester aktif: ' . implode(', ', array_unique(array_filter($missingTargets))) . '.';
            }
            Session::flash('success', $message);
        } else {
            $message = 'Tidak ada siswa yang dipromosikan. Pastikan kelas semester aktif sudah tersedia dan status naik kelas sudah ditentukan.';
            if (!empty($missingTargets)) {
                $message .= ' Kelas yang belum tersedia: ' . implode(', ', array_unique(array_filter($missingTargets))) . '.';
            }
            Session::flash('warning', $message);
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * @param array<int, array<string, mixed>> $classes
     *
     * @return array{array<string, int>, array<string, array<int>>}
     */
    private function buildPromotionClassLookup(array $classes): array
    {
        $lookupBySuffix = [];
        $fallbackLookup = [];

        foreach ($classes as $class) {
            $classId = (int) ($class['id'] ?? 0);
            $majorId = (int) ($class['jurusan_id'] ?? 0);
            $level = (int) ($class['tingkat'] ?? 0);

            if ($classId <= 0 || $majorId <= 0 || $level <= 0) {
                continue;
            }

            $suffix = $this->normalizeClassSuffix($class['nama'] ?? '');

            if ($suffix !== '') {
                $lookupBySuffix[sprintf('%d|%d|%s', $majorId, $level, $suffix)] = $classId;
            }

            $fallbackKey = sprintf('%d|%d', $majorId, $level);
            if (!isset($fallbackLookup[$fallbackKey])) {
                $fallbackLookup[$fallbackKey] = [];
            }
            $fallbackLookup[$fallbackKey][] = $classId;
        }

        return [$lookupBySuffix, $fallbackLookup];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, int> $lookupBySuffix
     * @param array<string, array<int>> $fallbackLookup
     */
    private function resolvePromotionTargetClass(array $record, array $lookupBySuffix, array $fallbackLookup): ?int
    {
        $majorId = (int) ($record['jurusan_id'] ?? 0);
        $currentLevel = (int) ($record['tingkat'] ?? 0);
        $targetLevel = $currentLevel > 0 ? $currentLevel + 1 : 0;

        if ($majorId <= 0 || $targetLevel <= 0) {
            return null;
        }

        $suffix = $this->normalizeClassSuffix($record['nama'] ?? '');

        if ($suffix !== '') {
            $key = sprintf('%d|%d|%s', $majorId, $targetLevel, $suffix);
            if (isset($lookupBySuffix[$key])) {
                return (int) $lookupBySuffix[$key];
            }
        }

        $fallbackKey = sprintf('%d|%d', $majorId, $targetLevel);
        if (!isset($fallbackLookup[$fallbackKey])) {
            return null;
        }

        $candidates = $fallbackLookup[$fallbackKey];

        if (count($candidates) === 1) {
            return (int) $candidates[0];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function buildMissingTargetLabel(array $record): string
    {
        $currentClassName = trim((string) ($record['nama'] ?? ''));
        $currentLevel = (int) ($record['tingkat'] ?? 0);
        $targetLevel = $currentLevel > 0 ? $currentLevel + 1 : null;

        $suffix = $this->normalizeClassSuffix($currentClassName);
        $parts = [];

        if ($targetLevel !== null) {
            $parts[] = 'Tingkat ' . $targetLevel;
        }

        if ($suffix !== '') {
            $parts[] = ucwords($suffix);
        } elseif ($currentClassName !== '') {
            $parts[] = $currentClassName;
        }

        return empty($parts) ? 'kelas tujuan' : implode(' ', $parts);
    }

    private function normalizeClassSuffix(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/', ' ', $name) ?? $name;
        $parts = explode(' ', $normalized);

        if (count($parts) > 1 && strcasecmp($parts[0], 'kelas') === 0) {
            array_shift($parts);
        }

        if (!empty($parts) && $this->isRomanNumeral($parts[0])) {
            array_shift($parts);
        } elseif (!empty($parts) && ctype_digit($parts[0])) {
            array_shift($parts);
        }

        if (empty($parts)) {
            return '';
        }

        return strtolower(implode(' ', $parts));
    }

    private function isRomanNumeral(string $value): bool
    {
        return (bool) preg_match('/^(X{0,3}(IX|IV|V?I{0,3})|I{1,3}|IV|V|VI{0,3}|VII|VIII|IX|X|XI|XII|XIII)$/i', $value);
    }
}

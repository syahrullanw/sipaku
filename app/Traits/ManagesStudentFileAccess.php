<?php

namespace App\Traits;

use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use Core\Session;

trait ManagesStudentFileAccess
{
    /**
     * @return array{
     *     byId: array<int, array<string, mixed>>,
     *     byNisn: array<string, int>,
     *     byNipd: array<string, int>
     * }|null
     */
    protected function resolveAccessibleStudentsForFileManagement(string $permissionErrorMessage): ?array
    {
        $user = auth();
        $role = (string) ($user['role'] ?? '');

        if ($role === 'admin') {
            $students = Student::allWithRelations();

            return $this->formatStudentMaps($students);
        }

        if ($role === 'guru') {
            $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;
            if ($teacherId <= 0) {
                Session::flash('error', 'Akun Anda belum terhubung dengan data guru.');

                return null;
            }

            $activeYear = SchoolYear::active();
            $activeYearId = (int) ($activeYear['id'] ?? 0);

            $classes = Classroom::homeroomClassesForTeacher(
                $teacherId,
                $activeYearId > 0 ? $activeYearId : null
            );

            if (empty($classes)) {
                $classes = Classroom::homeroomClassesForTeacher($teacherId);
            }

            if (empty($classes)) {
                Session::flash('error', 'Anda tidak memiliki kelas wali yang dapat dikelola.');

                return null;
            }

            $classIds = array_map(
                static fn (array $classroom): int => (int) ($classroom['id'] ?? 0),
                array_filter($classes, static fn ($classroom) => (int) ($classroom['id'] ?? 0) > 0),
            );

            if (empty($classIds)) {
                Session::flash('error', 'Data kelas wali tidak valid.');

                return null;
            }

            $students = Student::allWithRelations($classIds);

            return $this->formatStudentMaps($students);
        }

        Session::flash('error', $permissionErrorMessage);

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $students
     *
     * @return array{
     *     byId: array<int, array<string, mixed>>,
     *     byNisn: array<string, int>,
     *     byNipd: array<string, int>
     * }
     */
    protected function formatStudentMaps(array $students): array
    {
        $byId = [];
        $byNisn = [];
        $byNipd = [];

        foreach ($students as $student) {
            $id = (int) ($student['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $byId[$id] = $student;

            $nisn = strtolower(trim((string) ($student['nisn'] ?? '')));
            if ($nisn !== '') {
                $byNisn[$nisn] = $id;
            }

            $nipd = strtolower(trim((string) ($student['nipd'] ?? '')));
            if ($nipd !== '') {
                $byNipd[$nipd] = $id;
            }
        }

        return [
            'byId' => $byId,
            'byNisn' => $byNisn,
            'byNipd' => $byNipd,
        ];
    }
}

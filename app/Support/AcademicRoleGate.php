<?php

namespace App\Support;

use App\Models\AcademicPosition;
use App\Models\TeacherAcademicPosition;

class AcademicRoleGate
{
    private const ROLE_TATA_USAHA = 'tata_usaha';
    private const ROLE_WAKA_KURIKULUM = 'waka_kurikulum';
    private const ROLE_KEPALA_PRODI = 'kepala_prodi';

    /**
     * Determine whether the given user should be treated as staf tata usaha.
     *
     * @param array<string, mixed>|null $user
     */
    public static function isTataUsaha(?array $user = null): bool
    {
        AcademicPosition::ensureSystemPositions();

        if ($user === null) {
            $user = auth();
        }

        if (!is_array($user)) {
            return false;
        }

        $role = (string) ($user['role'] ?? '');

        if ($role === 'staff') {
            return true;
        }

        if ($role !== 'guru') {
            return false;
        }

        $teacherId = (int) ($user['teacher_id'] ?? 0);

        if ($teacherId <= 0) {
            return false;
        }

        return self::teacherHasRole($teacherId, self::ROLE_TATA_USAHA, null);
    }

    public static function isWakaKurikulum(?array $user = null): bool
    {
        AcademicPosition::ensureSystemPositions();

        if ($user === null) {
            $user = auth();
        }

        if (!is_array($user)) {
            return false;
        }

        if ((string) ($user['role'] ?? '') !== 'guru') {
            return false;
        }

        $teacherId = (int) ($user['teacher_id'] ?? 0);

        if ($teacherId <= 0) {
            return false;
        }

        return self::teacherHasRole($teacherId, self::ROLE_WAKA_KURIKULUM, null);
    }

    public static function isKepalaProdi(?int $majorId = null, ?array $user = null): bool
    {
        AcademicPosition::ensureSystemPositions();

        if ($user === null) {
            $user = auth();
        }

        if (!is_array($user) || (string) ($user['role'] ?? '') !== 'guru') {
            return false;
        }

        $teacherId = (int) ($user['teacher_id'] ?? 0);

        if ($teacherId <= 0) {
            return false;
        }

        return self::teacherHasRole($teacherId, self::ROLE_KEPALA_PRODI, $majorId);
    }

    private static function teacherHasRole(int $teacherId, string $roleCode, ?int $majorId): bool
    {
        $activeYearId = SchoolYearContext::id();

        if ($activeYearId === null) {
            return TeacherAcademicPosition::teacherHasAssignedRole($teacherId, $roleCode, null, $majorId);
        }

        return TeacherAcademicPosition::teacherHasAssignedRole($teacherId, $roleCode, $activeYearId, $majorId);
    }
}

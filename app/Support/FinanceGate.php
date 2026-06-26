<?php

namespace App\Support;

use App\Models\TeacherAcademicPosition;
use RuntimeException;

class FinanceGate
{
    /**
     * @param array<string, mixed>|null $user
     */
    public static function ensureRole(?array $user, string $role): void
    {
        if (!self::isRole($user, $role)) {
            throw new RuntimeException('Anda tidak memiliki akses ke modul ini.');
        }
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function ensureBendahara(?array $user): void
    {
        if (!self::isBendahara($user)) {
            throw new RuntimeException('Akses khusus bendahara.');
        }
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function isRole(?array $user, string $role): bool
    {
        return $user !== null && ($user['role'] ?? null) === $role;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function isBendahara(?array $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (($user['role'] ?? null) === 'bendahara') {
            return true;
        }

        if (($user['role'] ?? null) !== 'guru') {
            return false;
        }

        $teacherId = (int) ($user['teacher_id'] ?? 0);

        if ($teacherId <= 0) {
            return false;
        }

        $schoolYearId = SchoolYearContext::id();

        if ($schoolYearId === null) {
            return false;
        }
        return TeacherAcademicPosition::teacherHasAssignedRole($teacherId, 'bendahara', $schoolYearId);
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function isHeadmaster(?array $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (($user['role'] ?? null) === 'kepala_sekolah') {
            return true;
        }

        if (($user['role'] ?? null) !== 'guru') {
            return false;
        }

        $teacherId = (int) ($user['teacher_id'] ?? 0);

        if ($teacherId <= 0) {
            return false;
        }

        $activeYear = SchoolYearContext::resolve();

        if ($activeYear === null) {
            return false;
        }

        return (int) ($activeYear['kepala_sekolah_id'] ?? 0) === $teacherId;
    }
}

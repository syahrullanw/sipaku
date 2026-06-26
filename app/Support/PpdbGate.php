<?php

namespace App\Support;

use App\Models\PpdbPeriodResponsible;
use RuntimeException;

class PpdbGate
{
    /**
     * @param array<string, mixed>|null $user
     */
    public static function ensureAdmin(?array $user): void
    {
        if (!static::isAdmin($user)) {
            throw new RuntimeException('Akses modul PPDB terbatas untuk administrator.');
        }
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function ensureResponsible(?array $user, ?int $periodId = null): void
    {
        if (!static::isResponsible($user, $periodId)) {
            throw new RuntimeException('Anda tidak terdaftar sebagai penanggung jawab PPDB aktif.');
        }
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function isAdmin(?array $user): bool
    {
        return $user !== null && ($user['role'] ?? null) === 'admin';
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function isResponsible(?array $user, ?int $periodId = null): bool
    {
        if (static::isAdmin($user)) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        $role = (string) ($user['role'] ?? '');

        if ($role === 'kepala_sekolah') {
            return true;
        }

        if ($role !== 'guru') {
            return false;
        }

        $teacherId = (int) ($user['teacher_id'] ?? 0);

        if ($teacherId <= 0) {
            return false;
        }

        return PpdbPeriodResponsible::teacherHasAssignment($teacherId, $periodId, true);
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function teacherHasActiveAssignment(?array $user): bool
    {
        if ($user === null || ($user['role'] ?? null) !== 'guru') {
            return false;
        }

        $teacherId = (int) ($user['teacher_id'] ?? 0);

        if ($teacherId <= 0) {
            return false;
        }

        return PpdbPeriodResponsible::teacherHasAssignment($teacherId, null, true);
    }
}

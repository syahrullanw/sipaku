<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\GradeRescueWindow;
use Core\Log;
use Core\Request;

class GradeRescueGuard
{
    public static function buildRequestId(Request $request): string
    {
        $existing = $request->requestId();
        if ($existing !== null && $existing !== '') {
            return $existing;
        }

        try {
            return 'req_' . bin2hex(random_bytes(8));
        } catch (\Throwable) {
            return 'req_' . str_replace('.', '', (string) microtime(true));
        }
    }

    public static function canTeacherAccessHomeroomClass(int $teacherId, int $classId, ?int $schoolYearId = null): bool
    {
        if ($teacherId <= 0 || $classId <= 0) {
            return false;
        }

        $class = Classroom::findWithRelations($classId);
        if ($class === null) {
            return false;
        }

        if ((int) ($class['wali_kelas_id'] ?? 0) !== $teacherId) {
            return false;
        }

        if ($schoolYearId !== null && $schoolYearId > 0 && (int) ($class['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            return false;
        }

        return true;
    }

    public static function canRescueInput(int $schoolYearId, string $semester): bool
    {
        return GradeRescueWindow::activeForContext($schoolYearId, $semester) !== null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function log(string $message, array $context = []): void
    {
        Log::channel('grade-rescue')->info($message, $context);
    }
}
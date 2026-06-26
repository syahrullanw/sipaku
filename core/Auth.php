<?php

namespace Core;

use App\Models\User;

class Auth
{
    private const SESSION_KEY = 'auth_user';
    private const DEFAULT_TEACHER_PASSWORD = 'guru123';
    private static ?string $lastFailureReason = null;

    public static function attempt(string $username, string $password): bool
    {
        self::$lastFailureReason = null;

        $identifier = trim($username);
        $user = $identifier === '' ? null : User::findForLogin($identifier);

        if (!$user) {
            self::$lastFailureReason = 'Kredensial atau password tidak sesuai.';
            return false;
        }

        $hashed = $user['password'] ?? '';

        if (!is_string($hashed) || $hashed === '' || !password_verify($password, $hashed)) {
            if (!self::allowsStudentNisPassword($user, $identifier, $password)) {
                self::$lastFailureReason = 'Kredensial atau password tidak sesuai.';
                return false;
            }
        }

        if (isset($user['student_nis'])) {
            unset($user['student_nis']);
        }

        if (isset($user['student_nisn'])) {
            unset($user['student_nisn']);
        }

        if (($user['role'] ?? '') === 'guru' && isset($user['teacher_status']) && $user['teacher_status'] !== 'aktif') {
            self::$lastFailureReason = 'Akun guru nonaktif. Hubungi administrator sekolah.';
            return false;
        }

        if (($user['role'] ?? '') === 'siswa') {
            $studentEligibilityMessage = self::studentAccessRestrictionMessage($user);
            if ($studentEligibilityMessage !== null) {
                self::$lastFailureReason = $studentEligibilityMessage;
                return false;
            }
        }

        static::setUser($user);

        if (($user['role'] ?? '') === 'guru' && self::usesDefaultTeacherPassword($hashed)) {
            Session::flash('teacher_default_password_prompt', true);
        }

        return true;
    }

    public static function lastFailureReason(): ?string
    {
        return self::$lastFailureReason;
    }

    public static function check(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function setUser(array $user): void
    {
        unset($user['password']);
        unset($user['teacher_status']);
        unset($user['student_status']);
        unset($user['student_dapodik_status']);
        unset($user['student_class_id']);
        unset($user['student_school_year_id']);
        unset($user['student_joined_class_id']);
        unset($user['student_nis']);
        unset($user['student_nisn']);
        $_SESSION[self::SESSION_KEY] = $user;
    }

    private static function usesDefaultTeacherPassword(?string $hashedPassword): bool
    {
        if (!is_string($hashedPassword) || $hashedPassword === '') {
            return false;
        }

        return password_verify(self::DEFAULT_TEACHER_PASSWORD, $hashedPassword);
    }

    /**
     * @param array<string, mixed> $user
     */
    private static function allowsStudentNisPassword(array $user, string $identifier, string $password): bool
    {
        if (($user['role'] ?? '') !== 'siswa') {
            return false;
        }

        $nis = trim((string) ($user['student_nis'] ?? ''));
        if ($nis === '' || !hash_equals($nis, $password)) {
            return false;
        }

        $identifier = trim($identifier);
        $username = trim((string) ($user['username'] ?? ''));

        return hash_equals($nis, $identifier)
            || ($username !== '' && hash_equals($username, $identifier));
    }

    /**
     * @param array<string, mixed> $student
     */
    public static function studentAccessRestrictionMessage(array $student): ?string
    {
        $status = strtolower(trim((string) ($student['student_status'] ?? $student['status'] ?? '')));
        if ($status !== '' && $status !== 'aktif') {
            return 'Akun siswa nonaktif. Hubungi wali kelas atau administrator sekolah.';
        }

        $dapodikStatus = strtolower(trim((string) ($student['student_dapodik_status'] ?? $student['status_dapodik'] ?? '')));
        if ($dapodikStatus !== '' && $dapodikStatus !== 'aktif') {
            return 'Status Dapodik siswa tidak aktif. Hubungi wali kelas atau administrator sekolah.';
        }

        $classId = (int) ($student['student_class_id'] ?? $student['kelas_id'] ?? 0);
        $joinedClassId = (int) ($student['student_joined_class_id'] ?? $student['kelas_id'] ?? 0);
        if ($classId <= 0 || $joinedClassId <= 0) {
            return 'Akun siswa belum ditempatkan di kelas. Hubungi wali kelas atau administrator sekolah.';
        }

        return null;
    }
}

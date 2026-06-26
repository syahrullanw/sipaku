<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;

class StudentAccountManager
{
    /**
     * @param array<string, mixed> $student
     */
    public static function sync(array $student, bool $resetPassword = false): void
    {
        $studentId = (int) ($student['id'] ?? 0);
        if ($studentId <= 0) {
            return;
        }

        $nisn = trim((string) ($student['nisn'] ?? ''));
        $nipd = trim((string) ($student['nipd'] ?? ''));
        $name = trim((string) ($student['nama'] ?? ''));
        $email = self::sanitizeEmail($student['email'] ?? null);

        if ($name === '') {
            $name = 'Siswa';
        }

        $user = User::findByStudentId($studentId);
        $username = self::resolveUsername($nisn, $nipd, $studentId, $user !== null ? (int) $user['id'] : null);

        if ($username === null) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        if ($user === null) {
            $passwordSource = $nipd !== '' ? $nipd : ($nisn !== '' ? $nisn : $username);

            $payload = [
                'name' => $name,
                'username' => $username,
                'password' => password_hash($passwordSource, PASSWORD_BCRYPT),
                'email' => $email,
                'role' => 'siswa',
                'student_id' => $studentId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($email !== null && User::exists(['email' => $email])) {
                $payload['email'] = null;
            }

            try {
                User::create($payload);
            } catch (\Throwable) {
                // Abaikan kegagalan pembuatan akun agar proses utama tidak terhenti.
            }

            return;
        }

        $updates = [];

        if ($user['name'] !== $name) {
            $updates['name'] = $name;
        }

        if ($user['username'] !== $username && !User::exists(['username' => $username], (int) $user['id'])) {
            $updates['username'] = $username;
        }

        $currentEmail = isset($user['email']) && $user['email'] !== '' ? (string) $user['email'] : null;
        if ($email === null) {
            if ($currentEmail !== null) {
                $updates['email'] = null;
            }
        } elseif ($currentEmail === null || strtolower((string) $currentEmail) !== strtolower($email)) {
            if (!User::exists(['email' => $email], (int) $user['id'])) {
                $updates['email'] = $email;
            }
        }

        if ($resetPassword) {
            $passwordSource = $nipd !== '' ? $nipd : $nisn;
            if ($passwordSource !== '') {
                $updates['password'] = password_hash($passwordSource, PASSWORD_BCRYPT);
            }
        }

        if (!empty($updates)) {
            $updates['updated_at'] = $now;
            try {
                User::updateById((int) $user['id'], $updates);
            } catch (\Throwable) {
                // Dibiarkan senyap agar proses utama tetap berjalan.
            }
        }
    }

    public static function syncById(int $studentId, bool $resetPassword = false): void
    {
        if ($studentId <= 0) {
            return;
        }

        $student = Student::find($studentId);
        if ($student === null) {
            return;
        }

        self::sync($student, $resetPassword);
    }

    public static function delete(int $studentId): void
    {
        if ($studentId <= 0) {
            return;
        }

        $user = User::findByStudentId($studentId);
        if ($user === null) {
            return;
        }

        try {
            User::deleteById((int) $user['id']);
        } catch (\Throwable) {
            // Abaikan agar penghapusan siswa tetap berlanjut.
        }
    }

    private static function resolveUsername(string $nisn, string $nipd, int $studentId, ?int $ignoreUserId = null): ?string
    {
        $candidates = array_filter([$nipd, $nisn]);
        foreach ($candidates as $candidate) {
            $candidate = substr($candidate, 0, 50);
            if ($candidate === '') {
                continue;
            }

            if (!User::exists(['username' => $candidate], $ignoreUserId)) {
                return $candidate;
            }
        }

        $base = 'siswa' . $studentId;
        $base = substr($base, 0, 45);
        $suffix = 1;
        $username = $base;

        while (User::exists(['username' => $username], $ignoreUserId)) {
            $suffixString = (string) $suffix;
            $maxBaseLength = max(1, 50 - strlen($suffixString));
            $username = substr($base, 0, $maxBaseLength) . $suffixString;
            $suffix++;
        }

        return $username;
    }

    private static function sanitizeEmail(mixed $email): ?string
    {
        if (!is_string($email)) {
            return null;
        }

        $email = trim($email);
        if ($email === '') {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ?: null;
    }
}

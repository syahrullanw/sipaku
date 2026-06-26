<?php

namespace App\Services;

class ProfileCompletionReminder
{
    /**
     * @return array<string, string>
     */
    private const TEACHER_FIELDS = [
        'nama' => 'Nama Lengkap',
        'nik' => 'NIK',
        'jenis_kelamin' => 'Jenis Kelamin',
        'tempat_lahir' => 'Tempat Lahir',
        'tanggal_lahir' => 'Tanggal Lahir',
        'nama_ibu_kandung' => 'Nama Ibu Kandung',
        'agama' => 'Agama',
        'alamat' => 'Alamat Lengkap',
        'telepon' => 'Telepon',
        'email' => 'Email',
    ];

    /**
     * @return array<string, string>
     */
    private const STUDENT_FIELDS = [
        'email' => 'Email',
        'telepon' => 'Telepon Rumah',
        'hp' => 'Nomor HP',
        'alamat' => 'Alamat Lengkap',
        'dusun' => 'Dusun',
        'kelurahan' => 'Kelurahan',
        'kecamatan' => 'Kecamatan',
        'jenis_tinggal' => 'Jenis Tinggal',
        'alat_transportasi' => 'Alat Transportasi',
    ];

    /**
     * @param array<string, string> $fields
     *
     * @return array<string>
     */
    private static function missingFields(array $record, array $fields): array
    {
        $missing = [];

        foreach ($fields as $key => $label) {
            if (self::isValueMissing($record[$key] ?? null)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /**
     * @return array<string>
     */
    public static function missingTeacherFields(array $teacher): array
    {
        return self::missingFields($teacher, self::TEACHER_FIELDS);
    }

    /**
     * @return array<string>
     */
    public static function missingStudentFields(array $student): array
    {
        return self::missingFields($student, self::STUDENT_FIELDS);
    }

    private static function isValueMissing(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            $normalized = trim($value);
            return $normalized === '' || $normalized === '0000-00-00';
        }

        if (is_array($value)) {
            return empty($value);
        }

        return false;
    }
}

<?php

namespace App\Services\Ppdb;

use App\Models\PpdbRegistrant;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Services\StudentAccountManager;
use App\Support\StudentNipdGenerator;

class PpdbMigrationService
{
    /**
     * @param array<string, mixed> $registrant
     * @param array<string, string> $override
     */
    public static function migrate(array $registrant, int $schoolYearId, ?int $classId, array $override = []): ?int
    {
        $schoolYearId = max(0, $schoolYearId);
        if ($schoolYearId <= 0) {
            return null;
        }

        $schoolYear = SchoolYear::find($schoolYearId);
        if ($schoolYear === null) {
            return null;
        }

        $name = trim($override['nama'] ?? ($registrant['nama_lengkap'] ?? ''));
        if ($name === '') {
            $name = 'Siswa Baru';
        }

        $gender = strtoupper(trim($override['jenis_kelamin'] ?? ($registrant['jenis_kelamin'] ?? '')));
        if (!in_array($gender, ['L', 'P'], true)) {
            $gender = 'L';
        }

        $nipd = StudentNipdGenerator::generateNext($schoolYear, StudentNipdGenerator::TYPE_REGULAR);

        $nisn = trim($override['nisn'] ?? ($registrant['nisn'] ?? ''));
        if ($nisn === '') {
            $nisn = $nipd;
        }

        $nik = trim($override['nik'] ?? ($registrant['nik'] ?? ''));
        if ($nik === '') {
            $nik = str_pad((string) random_int(0, 9999999999999999), 16, '0', STR_PAD_LEFT);
        }

        $birthPlace = trim($override['tempat_lahir'] ?? ($registrant['tempat_lahir'] ?? ''));
        if ($birthPlace === '') {
            $birthPlace = 'Tidak diketahui';
        }

        $birthDate = trim($override['tanggal_lahir'] ?? ($registrant['tanggal_lahir'] ?? ''));
        if ($birthDate === '' || strtotime($birthDate) === false) {
            $birthDate = '2010-01-01';
        }

        $ayahNama = trim($override['ayah_nama'] ?? '');
        if ($ayahNama === '') {
            $ayahNama = 'Belum diisi';
        }

        $ibuNama = trim($override['ibu_nama'] ?? '');
        if ($ibuNama === '') {
            $ibuNama = 'Belum diisi';
        }

        $payload = [
            'tahun_ajaran_id' => $schoolYearId,
            'kelas_id' => $classId > 0 ? $classId : null,
            'nama' => $name,
            'nipd' => $nipd,
            'jenis_kelamin' => $gender,
            'nisn' => $nisn,
            'tempat_lahir' => $birthPlace,
            'tanggal_lahir' => $birthDate,
            'nik' => $nik,
            'alamat' => $override['alamat'] ?? ($registrant['alamat'] ?? null),
            'hp' => $override['telepon'] ?? ($registrant['telepon'] ?? null),
            'email' => $override['email'] ?? ($registrant['email'] ?? null),
            'ayah_nama' => $ayahNama,
            'ibu_nama' => $ibuNama,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($registrant['asal_sekolah'])) {
            $payload['sekolah_asal'] = $registrant['asal_sekolah'];
        }

        if (!empty($registrant['telepon_wali'])) {
            $payload['telepon'] = $registrant['telepon_wali'];
        }

        $studentId = Student::createAndReturnId($payload);

        if ($studentId === null) {
            return null;
        }

        StudentAccountManager::syncById($studentId, true);
        PpdbRegistrant::markMigrated((int) ($registrant['id'] ?? 0), $studentId);

        return $studentId;
    }
}

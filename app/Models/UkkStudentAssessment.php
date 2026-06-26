<?php

namespace App\Models;

use Core\Model;
use PDO;

class UkkStudentAssessment extends Model
{
    protected static ?string $table = 'ukk_penilaian_siswa';

    /**
     * @param array<int, int> $studentIds
     * @return array<int, array<string, mixed>>
     */
    public static function mapByStudents(array $studentIds, int $schoolYearId): array
    {
        if ($schoolYearId <= 0 || empty($studentIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $sql = <<<SQL
SELECT
    p.*,
    p.internal_assessor_teacher_id,
    p.internal_assessor_name,
    s.nama AS siswa_nama,
    s.nisn AS siswa_nisn,
    k.nama AS kelas_nama,
    j.nama AS jurusan_nama,
    sk.kode AS skkni_kode,
    sk.judul AS skkni_judul,
    sk.paket_ujian_id AS skkni_paket_id,
    pk.nama AS skkni_paket_nama,
    d.nama AS dudi_nama,
    gi.nama AS internal_assessor_teacher_name,
    a.nama AS asesor_nama
FROM ukk_penilaian_siswa p
LEFT JOIN siswa s ON s.id = p.siswa_id
LEFT JOIN kelas k ON k.id = p.kelas_id
LEFT JOIN jurusan j ON j.id = p.jurusan_id
LEFT JOIN ukk_skkni sk ON sk.id = p.skkni_id
LEFT JOIN ukk_paket_ujian pk ON pk.id = sk.paket_ujian_id
LEFT JOIN ukk_dudi d ON d.id = p.dudi_id
LEFT JOIN ukk_asesor a ON a.id = p.asesor_id
LEFT JOIN guru gi ON gi.id = p.internal_assessor_teacher_id
WHERE p.tahun_ajaran_id = ? AND p.siswa_id IN ({$placeholders})
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $bindIndex = 1;
        $statement->bindValue($bindIndex++, $schoolYearId, PDO::PARAM_INT);
        foreach ($studentIds as $studentId) {
            $statement->bindValue($bindIndex++, (int) $studentId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $studentId = (int) ($row['siswa_id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $calculatedFinalScore = self::calculateFinalScore($row['nilai_teori'] ?? null, $row['nilai_praktik'] ?? null);
            if ($calculatedFinalScore !== null) {
                $row['nilai_akhir'] = number_format($calculatedFinalScore, 2, '.', '');
            }

            $map[$studentId] = $row;
        }

        return $map;
    }

    public static function findByStudent(int $studentId, int $schoolYearId): ?array
    {
        if ($studentId <= 0 || $schoolYearId <= 0) {
            return null;
        }

        $sql = 'SELECT * FROM ukk_penilaian_siswa WHERE siswa_id = :student AND tahun_ajaran_id = :year LIMIT 1';
        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':student', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $calculatedFinalScore = self::calculateFinalScore($row['nilai_teori'] ?? null, $row['nilai_praktik'] ?? null);
        if ($calculatedFinalScore !== null) {
            $row['nilai_akhir'] = number_format($calculatedFinalScore, 2, '.', '');
        }

        return $row;
    }

    public static function calculateFinalScore(mixed $theoryScore, mixed $practiceScore): ?float
    {
        $theory = self::normalizeScore($theoryScore);
        $practice = self::normalizeScore($practiceScore);

        if ($theory === null || $practice === null) {
            return null;
        }

        return round(($theory * 0.4) + ($practice * 0.6), 2);
    }

    public static function formatIndonesianDate(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $month = $months[(int) date('n', $timestamp)] ?? date('F', $timestamp);

        return sprintf('%s %s %s', date('d', $timestamp), $month, date('Y', $timestamp));
    }

    public static function upsertForStudent(int $studentId, int $schoolYearId, array $payload): bool
    {
        if ($studentId <= 0 || $schoolYearId <= 0) {
            return false;
        }

        if (!Student::isActiveId($studentId)) {
            return false;
        }

        $existing = self::findByStudent($studentId, $schoolYearId);
        $connection = static::connection();
        $now = date('Y-m-d H:i:s');

        if ($existing !== null) {
            $fields = [];
            $params = [':id' => (int) $existing['id']];

            foreach ($payload as $key => $value) {
                $fields[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }

            $fields[] = 'updated_at = :updated_at';
            $params[':updated_at'] = $now;

            $sql = 'UPDATE ' . static::$table . ' SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $statement = $connection->prepare($sql);

            if ($statement === false) {
                return false;
            }

            foreach ($params as $placeholder => $value) {
                $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $statement->bindValue($placeholder, $value, $type);
            }

            return $statement->execute();
        }

        $payload['siswa_id'] = $studentId;
        $payload['tahun_ajaran_id'] = $schoolYearId;
        $payload['created_at'] = $now;
        $payload['updated_at'] = $now;

        return self::create($payload) !== false;
    }

    private static function normalizeScore(mixed $score): ?float
    {
        if ($score === null) {
            return null;
        }

        if (is_string($score)) {
            $score = trim($score);
            if ($score === '') {
                return null;
            }
        }

        if (!is_numeric($score)) {
            return null;
        }

        return (float) $score;
    }
}

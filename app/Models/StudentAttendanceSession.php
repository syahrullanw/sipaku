<?php

namespace App\Models;

use Core\Model;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class StudentAttendanceSession extends Model
{
    protected static ?string $table = 'presensi_siswa_sesi';

    private const MIN_DURATION_MINUTES = 5;
    private const MAX_DURATION_MINUTES = 360;

    public static function createForSchedule(
        array $schedule,
        string $date,
        string $agenda,
        int $durationMinutes,
        ?int $parallelClassId = null,
        ?int $actualTeacherId = null,
        string $sessionType = 'jadwal',
        ?string $replacementNote = null
    ): ?int
    {
        $scheduleId = (int) ($schedule['id'] ?? 0);
        $scheduledTeacherId = (int) ($schedule['guru_id'] ?? 0);
        $teacherId = $actualTeacherId !== null && $actualTeacherId > 0 ? $actualTeacherId : $scheduledTeacherId;
        $classId = (int) ($schedule['kelas_id'] ?? 0);
        $subjectId = (int) ($schedule['mata_pelajaran_id'] ?? 0);
        $yearId = (int) ($schedule['tahun_ajaran_id'] ?? 0);

        if (
            $scheduleId <= 0
            || $teacherId <= 0
            || $scheduledTeacherId <= 0
            || $classId <= 0
            || $subjectId <= 0
            || $yearId <= 0
        ) {
            return null;
        }

        $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $date);

        if ($parsedDate === false) {
            return null;
        }

        $token = static::generateUniqueToken();
        $duration = static::sanitizeDuration($durationMinutes);

        $now = new DateTimeImmutable('now');
        $validFrom = $now;
        $validUntil = $now->modify(sprintf('+%d minutes', $duration));

        $timestampNow = $now->format('Y-m-d H:i:s');

        $parallelClassId = $parallelClassId !== null && $parallelClassId > 0 ? $parallelClassId : null;
        $sessionType = $sessionType === 'pengganti' ? 'pengganti' : 'jadwal';
        $replacementNote = trim((string) $replacementNote);

        $id = static::createAndReturnId([
            'tahun_ajaran_id' => $yearId,
            'jadwal_pelajaran_id' => $scheduleId,
            'guru_id' => $teacherId,
            'guru_jadwal_id' => $scheduledTeacherId,
            'kelas_id' => $classId,
            'kelas_paralel_id' => $parallelClassId,
            'mata_pelajaran_id' => $subjectId,
            'tanggal' => $parsedDate->format('Y-m-d'),
            'agenda' => trim($agenda),
            'tipe_sesi' => $sessionType,
            'catatan_pengganti' => $sessionType === 'pengganti' ? $replacementNote : null,
            'token' => $token,
            'durasi_menit' => $duration,
            'valid_dari' => $validFrom->format('Y-m-d H:i:s'),
            'valid_sampai' => $validUntil->format('Y-m-d H:i:s'),
            'status' => 'aktif',
            'created_at' => $timestampNow,
            'updated_at' => $timestampNow,
        ]);

        return $id;
    }

    public static function markClosed(int $sessionId): bool
    {
        if ($sessionId <= 0) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        return static::updateById($sessionId, [
            'status' => 'ditutup',
            'ditutup_pada' => $now,
            'valid_sampai' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function findForTeacher(int $sessionId, int $teacherId): ?array
    {
        if ($sessionId <= 0 || $teacherId <= 0) {
            return null;
        }

        $sql = static::baseSelect() . ' WHERE sesi.id = :session_id AND sesi.guru_id = :teacher_id LIMIT 1';

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $statement->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    public static function findWithRelations(int $sessionId): ?array
    {
        if ($sessionId <= 0) {
            return null;
        }

        $sql = static::baseSelect() . ' WHERE sesi.id = :session_id LIMIT 1';
        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':session_id', $sessionId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forTeacher(int $teacherId, ?int $schoolYearId = null, int $limit = 20): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $sql = static::baseSelect() . ' WHERE sesi.guru_id = :teacher_id';
        $params = [':teacher_id' => $teacherId];

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $sql .= ' AND sesi.tahun_ajaran_id = :year_id';
            $params[':year_id'] = $schoolYearId;
        }

        $sql .= ' ORDER BY sesi.tanggal DESC, sesi.valid_dari DESC LIMIT :limit';

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_INT);
        }

        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function findActiveByToken(string $token): ?array
    {
        $normalized = trim($token);

        if ($normalized === '') {
            return null;
        }

        $sql = static::baseSelect() . ' WHERE sesi.token = :token AND sesi.status = \'aktif\' LIMIT 1';

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':token', $normalized, PDO::PARAM_STR);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        if ($record === false) {
            return null;
        }

        $expiresAt = isset($record['valid_sampai']) ? strtotime((string) $record['valid_sampai']) : false;

        if ($expiresAt !== false && $expiresAt < time()) {
            return null;
        }

        return $record;
    }

    public static function findByToken(string $token): ?array
    {
        $normalized = trim($token);

        if ($normalized === '') {
            return null;
        }

        $sql = static::baseSelect() . ' WHERE sesi.token = :token LIMIT 1';
        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':token', $normalized, PDO::PARAM_STR);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    public static function isActive(array $session): bool
    {
        $status = (string) ($session['status'] ?? '');

        if ($status !== 'aktif') {
            return false;
        }

        $expiresAt = isset($session['valid_sampai']) ? strtotime((string) $session['valid_sampai']) : false;

        return $expiresAt === false || $expiresAt >= time();
    }

    private static function sanitizeDuration(int $durationMinutes): int
    {
        return max(self::MIN_DURATION_MINUTES, min(self::MAX_DURATION_MINUTES, $durationMinutes));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recapForClass(
        int $classId,
        ?int $assignmentId = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        if ($classId <= 0) {
            return [];
        }

        $conditions = ['(sesi.kelas_id = :class_id OR sesi.kelas_paralel_id = :class_id)'];
        $params = [':class_id' => $classId];

        if ($assignmentId !== null && $assignmentId > 0) {
            $conditions[] = 'gmp.id = :assignment_id';
            $params[':assignment_id'] = $assignmentId;
        }

        if ($startDate !== null && $startDate !== '') {
            $conditions[] = 'sesi.tanggal >= :start_date';
            $params[':start_date'] = $startDate;
        }

        if ($endDate !== null && $endDate !== '') {
            $conditions[] = 'sesi.tanggal <= :end_date';
            $params[':end_date'] = $endDate;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $sql = <<<SQL
SELECT
    data.*,
    COALESCE(counts.total_hadir, 0) AS total_hadir,
    COALESCE(counts.total_izin, 0) AS total_izin,
    COALESCE(counts.total_sakit, 0) AS total_sakit,
    COALESCE(counts.total_bolos, 0) AS total_bolos,
    COALESCE(counts.total_alpa, 0) AS total_alpa
FROM (
    SELECT
        sesi.*,
        jp.hari,
        jp.waktu_mulai,
        jp.waktu_selesai,
        jp.jumlah_jam,
        gmp.id AS guru_mata_pelajaran_id,
        g_jadwal.nama AS guru_jadwal_nama,
        g_jadwal.nip AS guru_jadwal_nip,
        g.nama AS guru_nama,
        g.nip AS guru_nip,
        mp.nama AS mata_pelajaran_nama,
        mp.kode AS mata_pelajaran_kode,
        mp.jenis AS mata_pelajaran_jenis,
        k.nama AS kelas_nama,
        k.tingkat AS kelas_tingkat,
        j.nama AS jurusan_nama,
        k_paralel.nama AS kelas_paralel_nama,
        k_paralel.tingkat AS kelas_paralel_tingkat,
        j_paralel.nama AS jurusan_paralel_nama,
        ta.nama AS tahun_ajaran_nama,
        ta.semester_aktif AS tahun_ajaran_semester
    FROM presensi_siswa_sesi sesi
    JOIN jadwal_pelajaran jp ON jp.id = sesi.jadwal_pelajaran_id
    JOIN guru_mata_pelajaran gmp ON gmp.id = jp.guru_mata_pelajaran_id
    JOIN guru g_jadwal ON g_jadwal.id = COALESCE(sesi.guru_jadwal_id, gmp.guru_id)
    JOIN guru g ON g.id = sesi.guru_id
    JOIN mata_pelajaran mp ON mp.id = sesi.mata_pelajaran_id
    JOIN kelas k ON k.id = sesi.kelas_id
    LEFT JOIN jurusan j ON j.id = k.jurusan_id
    LEFT JOIN kelas k_paralel ON k_paralel.id = sesi.kelas_paralel_id
    LEFT JOIN jurusan j_paralel ON j_paralel.id = k_paralel.jurusan_id
    JOIN tahun_ajaran ta ON ta.id = sesi.tahun_ajaran_id
    {$whereClause}
) AS data
LEFT JOIN (
    SELECT
        sesi_id,
        SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) AS total_hadir,
        SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) AS total_izin,
        SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) AS total_sakit,
        SUM(CASE WHEN status = 'bolos' THEN 1 ELSE 0 END) AS total_bolos,
        SUM(CASE WHEN status = 'alpa' THEN 1 ELSE 0 END) AS total_alpa
    FROM presensi_siswa_sesi_detail
    GROUP BY sesi_id
) AS counts ON counts.sesi_id = data.id
ORDER BY data.tanggal DESC, data.valid_dari DESC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $paramType = in_array($placeholder, [':class_id', ':assignment_id'], true) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue($placeholder, $value, $paramType);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recapForTeachers(
        ?int $schoolYearId = null,
        ?int $teacherId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $classId = null,
        ?int $subjectId = null
    ): array {
        $conditions = [];
        $params = [];

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $conditions[] = 'sesi.tahun_ajaran_id = :school_year_id';
            $params[':school_year_id'] = $schoolYearId;
        }

        if ($teacherId !== null && $teacherId > 0) {
            $conditions[] = 'sesi.guru_id = :teacher_id';
            $params[':teacher_id'] = $teacherId;
        }

        if ($startDate !== null && $startDate !== '') {
            $conditions[] = 'sesi.tanggal >= :start_date';
            $params[':start_date'] = $startDate;
        }

        if ($endDate !== null && $endDate !== '') {
            $conditions[] = 'sesi.tanggal <= :end_date';
            $params[':end_date'] = $endDate;
        }

        if ($classId !== null && $classId > 0) {
            $conditions[] = '(sesi.kelas_id = :class_id OR sesi.kelas_paralel_id = :class_id)';
            $params[':class_id'] = $classId;
        }

        if ($subjectId !== null && $subjectId > 0) {
            $conditions[] = 'sesi.mata_pelajaran_id = :subject_id';
            $params[':subject_id'] = $subjectId;
        }

        $whereClause = '';
        if (!empty($conditions)) {
            $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        }

        $sql = <<<SQL
SELECT
    data.*,
    COALESCE(counts.total_hadir, 0) AS total_hadir,
    COALESCE(counts.total_izin, 0) AS total_izin,
    COALESCE(counts.total_sakit, 0) AS total_sakit,
    COALESCE(counts.total_bolos, 0) AS total_bolos,
    COALESCE(counts.total_alpa, 0) AS total_alpa
FROM (
    SELECT
        sesi.*,
        jp.hari,
        jp.waktu_mulai,
        jp.waktu_selesai,
        jp.jumlah_jam,
        gmp.id AS guru_mata_pelajaran_id,
        g_jadwal.nama AS guru_jadwal_nama,
        g_jadwal.nip AS guru_jadwal_nip,
        g.nama AS guru_nama,
        g.nip AS guru_nip,
        mp.nama AS mata_pelajaran_nama,
        mp.kode AS mata_pelajaran_kode,
        mp.jenis AS mata_pelajaran_jenis,
        k.nama AS kelas_nama,
        k.tingkat AS kelas_tingkat,
        j.nama AS jurusan_nama,
        k_paralel.nama AS kelas_paralel_nama,
        k_paralel.tingkat AS kelas_paralel_tingkat,
        j_paralel.nama AS jurusan_paralel_nama,
        ta.nama AS tahun_ajaran_nama,
        ta.semester_aktif AS tahun_ajaran_semester
    FROM presensi_siswa_sesi sesi
    JOIN jadwal_pelajaran jp ON jp.id = sesi.jadwal_pelajaran_id
    JOIN guru_mata_pelajaran gmp ON gmp.id = jp.guru_mata_pelajaran_id
    JOIN guru g_jadwal ON g_jadwal.id = COALESCE(sesi.guru_jadwal_id, gmp.guru_id)
    JOIN guru g ON g.id = sesi.guru_id
    JOIN mata_pelajaran mp ON mp.id = sesi.mata_pelajaran_id
    JOIN kelas k ON k.id = sesi.kelas_id
    LEFT JOIN jurusan j ON j.id = k.jurusan_id
    LEFT JOIN kelas k_paralel ON k_paralel.id = sesi.kelas_paralel_id
    LEFT JOIN jurusan j_paralel ON j_paralel.id = k_paralel.jurusan_id
    JOIN tahun_ajaran ta ON ta.id = sesi.tahun_ajaran_id
    {$whereClause}
) AS data
LEFT JOIN (
    SELECT
        sesi_id,
        SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) AS total_hadir,
        SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) AS total_izin,
        SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) AS total_sakit,
        SUM(CASE WHEN status = 'bolos' THEN 1 ELSE 0 END) AS total_bolos,
        SUM(CASE WHEN status = 'alpa' THEN 1 ELSE 0 END) AS total_alpa
    FROM presensi_siswa_sesi_detail
    GROUP BY sesi_id
) AS counts ON counts.sesi_id = data.id
ORDER BY data.tanggal DESC, data.valid_dari DESC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $paramType = match ($placeholder) {
                ':school_year_id', ':teacher_id', ':class_id', ':subject_id' => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($placeholder, $value, $paramType);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recapForTeacher(
        int $teacherId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $classId = null,
        ?int $subjectId = null
    ): array {
        if ($teacherId <= 0) {
            return [];
        }

        $conditions = ['sesi.guru_id = :teacher_id'];
        $params = [':teacher_id' => $teacherId];

        if ($startDate !== null && $startDate !== '') {
            $conditions[] = 'sesi.tanggal >= :start_date';
            $params[':start_date'] = $startDate;
        }

        if ($endDate !== null && $endDate !== '') {
            $conditions[] = 'sesi.tanggal <= :end_date';
            $params[':end_date'] = $endDate;
        }

        if ($classId !== null && $classId > 0) {
            $conditions[] = '(sesi.kelas_id = :class_id OR sesi.kelas_paralel_id = :class_id)';
            $params[':class_id'] = $classId;
        }

        if ($subjectId !== null && $subjectId > 0) {
            $conditions[] = 'sesi.mata_pelajaran_id = :subject_id';
            $params[':subject_id'] = $subjectId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $sql = <<<SQL
SELECT
    data.*,
    COALESCE(counts.total_hadir, 0) AS total_hadir,
    COALESCE(counts.total_izin, 0) AS total_izin,
    COALESCE(counts.total_sakit, 0) AS total_sakit,
    COALESCE(counts.total_bolos, 0) AS total_bolos,
    COALESCE(counts.total_alpa, 0) AS total_alpa
FROM (
    SELECT
        sesi.*,
        jp.hari,
        jp.waktu_mulai,
        jp.waktu_selesai,
        jp.jumlah_jam,
        gmp.id AS guru_mata_pelajaran_id,
        g_jadwal.nama AS guru_jadwal_nama,
        g_jadwal.nip AS guru_jadwal_nip,
        g.nama AS guru_nama,
        g.nip AS guru_nip,
        mp.nama AS mata_pelajaran_nama,
        mp.kode AS mata_pelajaran_kode,
        mp.jenis AS mata_pelajaran_jenis,
        k.nama AS kelas_nama,
        k.tingkat AS kelas_tingkat,
        j.nama AS jurusan_nama,
        k_paralel.nama AS kelas_paralel_nama,
        k_paralel.tingkat AS kelas_paralel_tingkat,
        j_paralel.nama AS jurusan_paralel_nama,
        ta.nama AS tahun_ajaran_nama,
        ta.semester_aktif AS tahun_ajaran_semester
    FROM presensi_siswa_sesi sesi
    JOIN jadwal_pelajaran jp ON jp.id = sesi.jadwal_pelajaran_id
    JOIN guru_mata_pelajaran gmp ON gmp.id = jp.guru_mata_pelajaran_id
    JOIN guru g_jadwal ON g_jadwal.id = COALESCE(sesi.guru_jadwal_id, gmp.guru_id)
    JOIN guru g ON g.id = sesi.guru_id
    JOIN mata_pelajaran mp ON mp.id = sesi.mata_pelajaran_id
    JOIN kelas k ON k.id = sesi.kelas_id
    LEFT JOIN jurusan j ON j.id = k.jurusan_id
    LEFT JOIN kelas k_paralel ON k_paralel.id = sesi.kelas_paralel_id
    LEFT JOIN jurusan j_paralel ON j_paralel.id = k_paralel.jurusan_id
    JOIN tahun_ajaran ta ON ta.id = sesi.tahun_ajaran_id
    {$whereClause}
) AS data
LEFT JOIN (
    SELECT
        sesi_id,
        SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) AS total_hadir,
        SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) AS total_izin,
        SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) AS total_sakit,
        SUM(CASE WHEN status = 'bolos' THEN 1 ELSE 0 END) AS total_bolos,
        SUM(CASE WHEN status = 'alpa' THEN 1 ELSE 0 END) AS total_alpa
    FROM presensi_siswa_sesi_detail
    GROUP BY sesi_id
) AS counts ON counts.sesi_id = data.id
ORDER BY data.tanggal DESC, data.valid_dari DESC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            if (in_array($placeholder, [':teacher_id', ':class_id', ':subject_id'], true)) {
                $statement->bindValue($placeholder, $value, PDO::PARAM_INT);
            } else {
                $statement->bindValue($placeholder, $value);
            }
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    private static function generateUniqueToken(): string
    {
        $attempts = 0;

        do {
            $token = bin2hex(random_bytes(16));
            $attempts++;
        } while (static::tokenExists($token) && $attempts < 10);

        if (static::tokenExists($token)) {
            throw new RuntimeException('Gagal menghasilkan token presensi unik.');
        }

        return $token;
    }

    private static function tokenExists(string $token): bool
    {
        $sql = 'SELECT 1 FROM ' . static::table() . ' WHERE token = :token LIMIT 1';
        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':token', $token, PDO::PARAM_STR);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    private static function baseSelect(): string
    {
        return <<<SQL
SELECT
    sesi.*,
    gmp.id AS guru_mata_pelajaran_id,
    g_jadwal.nama AS guru_jadwal_nama,
    g_jadwal.nip AS guru_jadwal_nip,
    jp.hari,
    jp.waktu_mulai,
    jp.waktu_selesai,
    jp.jumlah_jam,
    mp.nama AS mata_pelajaran_nama,
    mp.kode AS mata_pelajaran_kode,
    mp.jenis AS mata_pelajaran_jenis,
    g.nama AS guru_nama,
    g.nip AS guru_nip,
    k.nama AS kelas_nama,
    k.tingkat AS kelas_tingkat,
    j.nama AS jurusan_nama,
    k_paralel.nama AS kelas_paralel_nama,
    k_paralel.tingkat AS kelas_paralel_tingkat,
    j_paralel.nama AS jurusan_paralel_nama,
    ta.nama AS tahun_ajaran_nama,
    ta.semester_aktif AS tahun_ajaran_semester
FROM presensi_siswa_sesi sesi
JOIN jadwal_pelajaran jp ON jp.id = sesi.jadwal_pelajaran_id
JOIN guru_mata_pelajaran gmp ON gmp.id = jp.guru_mata_pelajaran_id
JOIN guru g_jadwal ON g_jadwal.id = COALESCE(sesi.guru_jadwal_id, gmp.guru_id)
JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
JOIN guru g ON g.id = sesi.guru_id
JOIN kelas k ON k.id = sesi.kelas_id
LEFT JOIN jurusan j ON j.id = k.jurusan_id
LEFT JOIN kelas k_paralel ON k_paralel.id = sesi.kelas_paralel_id
LEFT JOIN jurusan j_paralel ON j_paralel.id = k_paralel.jurusan_id
JOIN tahun_ajaran ta ON ta.id = sesi.tahun_ajaran_id
SQL;
    }
}

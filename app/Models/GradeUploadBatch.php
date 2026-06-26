<?php

namespace App\Models;

use App\Support\GradeUploadStatus;
use Core\Model;
use PDO;

class GradeUploadBatch extends Model
{
    protected static ?string $table = 'batch_upload_nilai';

    public static function findByBatchCode(string $batchCode): ?array
    {
        $batchCode = trim($batchCode);
        if ($batchCode === '') {
            return null;
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM batch_upload_nilai WHERE batch_code = :batch_code LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':batch_code', $batchCode);

        if (!$statement->execute()) {
            return null;
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public static function markStatus(int $id, string $status): bool
    {
        if ($id <= 0 || !GradeUploadStatus::isValid($status)) {
            return false;
        }

        return static::updateById($id, [
            'status' => strtoupper($status),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recentByTeacher(int $teacherId, int $limit = 30): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 100));
        $statement = static::connection()->prepare(
            'SELECT b.*,
                    k.tingkat AS kelas_tingkat,
                    k.nama AS kelas_nama,
                    mp.kode AS mapel_kode,
                    mp.nama AS mapel_nama,
                    ta.nama AS tahun_ajaran_nama
             FROM batch_upload_nilai b
             LEFT JOIN kelas k ON k.id = b.kelas_id
             LEFT JOIN guru_mata_pelajaran gmp ON gmp.id = b.guru_mata_pelajaran_id
             LEFT JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
             LEFT JOIN tahun_ajaran ta ON ta.id = b.tahun_ajaran_id
             WHERE b.uploaded_by_teacher_id = :teacher_id
             ORDER BY b.id DESC
             LIMIT :limit'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function findOpenByContext(
        int $teacherId,
        int $schoolYearId,
        string $semester,
        int $classId,
        int $assignmentId
    ): ?array {
        if ($teacherId <= 0 || $schoolYearId <= 0 || $classId <= 0 || $assignmentId <= 0) {
            return null;
        }

        $statement = static::connection()->prepare(
            "SELECT *
             FROM batch_upload_nilai
             WHERE uploaded_by_teacher_id = :teacher_id
               AND tahun_ajaran_id = :school_year_id
               AND semester = :semester
               AND kelas_id = :class_id
               AND guru_mata_pelajaran_id = :assignment_id
               AND status IN ('DRAFT', 'VALIDATING', 'VALIDATED', 'FAILED')
             ORDER BY id DESC
             LIMIT 1"
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':semester', $semester);
        $statement->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $statement->bindValue(':assignment_id', $assignmentId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public static function findLatestByContext(
        int $teacherId,
        int $schoolYearId,
        string $semester,
        int $classId,
        int $assignmentId
    ): ?array {
        if ($teacherId <= 0 || $schoolYearId <= 0 || $classId <= 0 || $assignmentId <= 0) {
            return null;
        }

        $statement = static::connection()->prepare(
            "SELECT *
             FROM batch_upload_nilai
             WHERE uploaded_by_teacher_id = :teacher_id
               AND tahun_ajaran_id = :school_year_id
               AND semester = :semester
               AND kelas_id = :class_id
               AND guru_mata_pelajaran_id = :assignment_id
             ORDER BY id DESC
             LIMIT 1"
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':semester', $semester);
        $statement->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $statement->bindValue(':assignment_id', $assignmentId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}

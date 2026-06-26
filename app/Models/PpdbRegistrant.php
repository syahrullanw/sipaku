<?php

namespace App\Models;

use Core\Model;
use PDO;

class PpdbRegistrant extends Model
{
    protected static ?string $table = 'ppdb_pendaftar';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forPeriod(int $periodId): array
    {
        $statement = static::connection()->prepare(
            'SELECT * FROM ppdb_pendaftar WHERE periode_id = :periode_id ORDER BY created_at DESC, id DESC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':periode_id', $periodId, PDO::PARAM_INT);
        $statement->execute();

        $records = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $records === false ? [] : $records;
    }

    /**
     * @return array<string, mixed>
     */
    public static function summaryForPeriod(int $periodId): array
    {
        if ($periodId <= 0) {
            return [];
        }

        $connection = static::connection();

        $total = (int) static::countTotal($periodId);
        $gender = static::countByColumn($periodId, 'jenis_kelamin');
        $selection = static::countByColumn($periodId, 'status_seleksi');
        $announcement = static::countByColumn($periodId, 'status_pengumuman');
        $reRegistration = static::countByColumn($periodId, 'status_daftar_ulang');
        $payment = static::countByColumn($periodId, 'status_pembayaran');
        $final = static::countByColumn($periodId, 'status_final');

        $paymentSumStatement = $connection->prepare(
            "SELECT COALESCE(SUM(nominal_pembayaran), 0) FROM ppdb_pendaftar WHERE periode_id = :periode_id AND status_pembayaran = 'lunas'"
        );

        $paymentNominal = 0.0;
        if ($paymentSumStatement !== false) {
            $paymentSumStatement->bindValue(':periode_id', $periodId, PDO::PARAM_INT);
            $paymentSumStatement->execute();
            $sum = $paymentSumStatement->fetchColumn();
            $paymentNominal = $sum === false ? 0.0 : (float) $sum;
        }

        $acceptedMigratedStatement = $connection->prepare(
            "SELECT COUNT(*) FROM ppdb_pendaftar WHERE periode_id = :periode_id AND status_final = 'diterima' AND siswa_id IS NOT NULL AND siswa_id > 0"
        );
        $acceptedPendingStatement = $connection->prepare(
            "SELECT COUNT(*) FROM ppdb_pendaftar WHERE periode_id = :periode_id AND status_final = 'diterima' AND (siswa_id IS NULL OR siswa_id = 0)"
        );

        $migrated = 0;
        if ($acceptedMigratedStatement !== false) {
            $acceptedMigratedStatement->bindValue(':periode_id', $periodId, PDO::PARAM_INT);
            $acceptedMigratedStatement->execute();
            $value = $acceptedMigratedStatement->fetchColumn();
            $migrated = $value === false ? 0 : (int) $value;
        }

        $pendingMigration = 0;
        if ($acceptedPendingStatement !== false) {
            $acceptedPendingStatement->bindValue(':periode_id', $periodId, PDO::PARAM_INT);
            $acceptedPendingStatement->execute();
            $value = $acceptedPendingStatement->fetchColumn();
            $pendingMigration = $value === false ? 0 : (int) $value;
        }

        return [
            'total' => $total,
            'gender' => $gender,
            'selection' => $selection,
            'announcement' => $announcement,
            're_registration' => $reRegistration,
            'payment' => $payment,
            'final' => $final,
            'payment_nominal' => $paymentNominal,
            'accepted_migrated' => $migrated,
            'accepted_pending' => $pendingMigration,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forPeriodWithFilters(int $periodId, ?string $finalStatus = null): array
    {
        if ($periodId <= 0) {
            return [];
        }

        $sql = 'SELECT * FROM ppdb_pendaftar WHERE periode_id = :periode_id';
        $params = [
            ':periode_id' => $periodId,
        ];

        if ($finalStatus !== null && $finalStatus !== '') {
            $sql .= ' AND status_final = :final';
            $params[':final'] = $finalStatus;
        }

        $sql .= ' ORDER BY nama_lengkap ASC';

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue($key, $value, $type);
        }

        $statement->execute();
        $records = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $records === false ? [] : $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function acceptedForPeriod(int $periodId, bool $onlyPendingMigration = false): array
    {
        $sql = 'SELECT * FROM ppdb_pendaftar WHERE periode_id = :periode_id AND status_final = :status';
        if ($onlyPendingMigration) {
            $sql .= ' AND (siswa_id IS NULL OR siswa_id = 0)';
        }
        $sql .= ' ORDER BY nama_lengkap ASC';

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':periode_id', $periodId, PDO::PARAM_INT);
        $statement->bindValue(':status', 'diterima');
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function generateCode(int $periodId): string
    {
        $prefix = 'PPDB' . str_pad((string) $periodId, 2, '0', STR_PAD_LEFT);

        do {
            $code = $prefix . strtoupper(dechex(random_int(0x1000, 0xFFFF)));
        } while (static::exists(['kode_pendaftaran' => $code]));

        return $code;
    }

    public static function createForPeriod(int $periodId, array $attributes): ?int
    {
        $payload = array_merge([
            'periode_id' => $periodId,
            'kode_pendaftaran' => static::generateCode($periodId),
            'sumber' => 'mandiri',
            'tanggal_daftar' => date('Y-m-d H:i:s'),
            'status_verifikasi' => 'lengkap',
            'status_seleksi' => 'belum_dijadwalkan',
            'status_pengumuman' => 'menunggu',
            'status_daftar_ulang' => 'tidak_dibuka',
            'status_pembayaran' => 'tidak_dibuka',
            'status_final' => 'pendaftar',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $attributes);

        $id = static::createAndReturnId($payload);

        return $id ?? null;
    }

    public static function hasPeriodRegistrant(int $periodId): bool
    {
        $statement = static::connection()->prepare(
            'SELECT COUNT(*) FROM ppdb_pendaftar WHERE periode_id = :periode_id'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':periode_id', $periodId, PDO::PARAM_INT);
        $statement->execute();
        $count = $statement->fetchColumn();

        return $count !== false && (int) $count > 0;
    }

    public static function sequenceNumberInPeriod(int $periodId, int $registrantId): int
    {
        if ($registrantId <= 0) {
            return 0;
        }

        $sql = <<<SQL
SELECT COUNT(*)
FROM ppdb_pendaftar AS registrants
INNER JOIN ppdb_pendaftar AS target ON target.id = :registrant_id
WHERE registrants.periode_id = target.periode_id
  AND registrants.id <= target.id
SQL;

        if ($periodId > 0) {
            $sql .= "\n  AND target.periode_id = :periode_id";
        }

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':registrant_id', $registrantId, PDO::PARAM_INT);
        if ($periodId > 0) {
            $statement->bindValue(':periode_id', $periodId, PDO::PARAM_INT);
        }

        $statement->execute();
        $count = $statement->fetchColumn();

        return $count === false ? 0 : (int) $count;
    }

    public static function updateSelection(int $id, ?string $schedule, ?string $status, ?float $score, ?int $userId = null): bool
    {
        $attributes = [
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($schedule !== null) {
            $attributes['jadwal_seleksi'] = $schedule;
        }

        if ($status !== null) {
            $attributes['status_seleksi'] = $status;
        }

        if ($score !== null) {
            $attributes['nilai_seleksi'] = $score;
        }

        if ($userId !== null) {
            $attributes['seleksi_diperbarui_oleh'] = $userId;
        }

        return static::updateById($id, $attributes);
    }

    /**
     * @return array<string, string>
     */
    public static function statusFinalOptions(): array
    {
        return [
            'pendaftar' => 'Pendaftar',
            'diterima' => 'Diterima',
            'cadangan' => 'Cadangan',
            'ditolak' => 'Ditolak',
            'mengundurkan_diri' => 'Mengundurkan Diri',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function verificationStatusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'lengkap' => 'Menunggu Verifikasi',
            'diverifikasi' => 'Terverifikasi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function selectionStatusOptions(): array
    {
        return [
            'belum_dijadwalkan' => 'Belum Dijadwalkan',
            'dijadwalkan' => 'Sudah Dijadwalkan',
            'lulus' => 'Lulus',
            'cadangan' => 'Cadangan',
            'tidak_lulus' => 'Tidak Lulus',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function announcementStatusOptions(): array
    {
        return [
            'menunggu' => 'Belum Diumumkan',
            'lulus' => 'Lulus',
            'cadangan' => 'Cadangan',
            'tidak_lulus' => 'Tidak Lulus',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function reRegistrationStatusOptions(): array
    {
        return [
            'tidak_dibuka' => 'Tahap Belum Dibuka',
            'menunggu' => 'Menunggu Konfirmasi',
            'selesai' => 'Selesai',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function paymentStatusOptions(): array
    {
        return [
            'tidak_dibuka' => 'Tahap Belum Dibuka',
            'menunggu' => 'Menunggu Pelunasan',
            'lunas' => 'Lunas',
            'dibebaskan' => 'Dibebaskan',
        ];
    }

    public static function updateAnnouncement(int $id, string $status, ?string $datetime): bool
    {
        $attributes = [
            'status_pengumuman' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($datetime === null && $status !== 'menunggu') {
            $datetime = date('Y-m-d H:i:s');
        }

        if ($datetime !== null) {
            $attributes['tanggal_pengumuman'] = $datetime;
        }

        return static::updateById($id, $attributes);
    }

    public static function updateReRegistration(int $id, string $status, ?string $datetime, ?int $userId = null): bool
    {
        $attributes = [
            'status_daftar_ulang' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($datetime === null && $status === 'selesai') {
            $datetime = date('Y-m-d H:i:s');
        }

        if ($datetime !== null) {
            $attributes['tanggal_daftar_ulang'] = $datetime;
        }

        if ($userId !== null) {
            $attributes['daftar_ulang_diperbarui_oleh'] = $userId;
        }

        return static::updateById($id, $attributes);
    }

    public static function updatePayment(int $id, string $status, ?float $amount, ?string $datetime, ?int $userId = null): bool
    {
        $attributes = [
            'status_pembayaran' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($status === 'lunas') {
            if ($datetime === null) {
                $datetime = date('Y-m-d H:i:s');
            }
            $attributes['tanggal_pembayaran'] = $datetime;
            if ($amount !== null) {
                $attributes['nominal_pembayaran'] = $amount;
            }
        } elseif ($datetime !== null) {
            $attributes['tanggal_pembayaran'] = $datetime;
        }

        if ($status === 'dibebaskan') {
            $attributes['nominal_pembayaran'] = 0.0;
        } elseif ($status !== 'lunas' && $amount === null) {
            $attributes['nominal_pembayaran'] = null;
        }

        if ($userId !== null) {
            $attributes['pembayaran_diperbarui_oleh'] = $userId;
        }

        return static::updateById($id, $attributes);
    }

    public static function markMigrated(int $id, int $studentId): bool
    {
        if ($studentId <= 0) {
            return false;
        }

        return static::updateById($id, [
            'siswa_id' => $studentId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected static function countTotal(int $periodId): int
    {
        $statement = static::connection()->prepare(
            'SELECT COUNT(*) FROM ppdb_pendaftar WHERE periode_id = :periode_id'
        );

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':periode_id', $periodId, PDO::PARAM_INT);
        $statement->execute();
        $count = $statement->fetchColumn();

        return $count === false ? 0 : (int) $count;
    }

    /**
     * @return array<string|null, int>
     */
    protected static function countByColumn(int $periodId, string $column): array
    {
        $allowed = [
            'jenis_kelamin',
            'status_seleksi',
            'status_pengumuman',
            'status_daftar_ulang',
            'status_pembayaran',
            'status_final',
        ];

        if (!in_array($column, $allowed, true)) {
            return [];
        }

        $sql = sprintf(
            'SELECT %1$s AS status, COUNT(*) AS total FROM ppdb_pendaftar WHERE periode_id = :periode_id GROUP BY %1$s',
            $column
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':periode_id', $periodId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $key = array_key_exists('status', $row) ? $row['status'] : null;
            if ($key === '') {
                $key = null;
            }
            $result[$key] = (int) ($row['total'] ?? 0);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByCode(string $code): ?array
    {
        $statement = static::connection()->prepare(
            'SELECT * FROM ppdb_pendaftar WHERE kode_pendaftaran = :code LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':code', $code);
        $statement->execute();
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }
}

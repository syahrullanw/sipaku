<?php

namespace App\Services;

use Core\Database;
use InvalidArgumentException;
use PDO;

class RaportCleanupService
{
    private const DATASETS = [
        'knowledge_assessments' => [
            'label' => 'Nilai Pengetahuan Mapel',
            'description' => 'Menghapus nilai pengetahuan (KD, UTS, UAS, akhir, deskripsi) yang diinput guru mata pelajaran.',
        ],
        'skill_assessments' => [
            'label' => 'Nilai Keterampilan Mapel',
            'description' => 'Menghapus nilai keterampilan dan deskripsi performa siswa pada tiap mata pelajaran.',
        ],
        'competency_scores' => [
            'label' => 'Nilai Kompetensi Dasar (KD)',
            'description' => 'Menghapus nilai per kompetensi dasar yang menjadi komponen penilaian pengetahuan.',
        ],
        'kurmer_tp_assessments' => [
            'label' => 'Nilai TP Kurikulum Merdeka',
            'description' => 'Menghapus capaian TP (BB/MB/BSH/SB), nilai opsional, dan catatan siswa pada kelas Kurmer.',
        ],
        'kurmer_subject_summaries' => [
            'label' => 'Ringkasan Nilai Mapel Kurmer',
            'description' => 'Menghapus capaian akhir, deskripsi, tindak lanjut, nilai opsional, dan sumber TP per mapel Kurmer.',
        ],
        'attitude_scores' => [
            'label' => 'Nilai Sikap Spiritual & Sosial',
            'description' => 'Menghapus catatan sikap spiritual dan sosial yang dicatat wali kelas.',
        ],
        'attendance_records' => [
            'label' => 'Rekap Presensi Siswa',
            'description' => 'Menghapus ringkasan kehadiran (sakit, izin, bolos, alpa) tiap siswa.',
        ],
        'attendance_sessions' => [
            'label' => 'Riwayat Sesi Presensi Mapel',
            'description' => 'Menghapus sesi presensi siswa per mata pelajaran beserta log absensinya.',
        ],
        'extracurricular_records' => [
            'label' => 'Nilai & Keikutsertaan Ekstrakurikuler',
            'description' => 'Menghapus penugasan ekstrakurikuler beserta nilai, predikat, dan deskripsi siswa.',
        ],
        'achievement_records' => [
            'label' => 'Prestasi Siswa',
            'description' => 'Menghapus seluruh entri prestasi siswa yang muncul di raport.',
        ],
        'prakerin_scores' => [
            'label' => 'Penilaian Prakerin',
            'description' => 'Menghapus nilai prakerin (keaktifan, jurnal, laporan, predikat) bagi siswa yang mengikuti prakerin.',
        ],
        'homeroom_notes' => [
            'label' => 'Catatan Wali Kelas',
            'description' => 'Menghapus catatan wali kelas pada masing-masing siswa.',
        ],
        'promotion_statuses' => [
            'label' => 'Status Naik Kelas',
            'description' => 'Menghapus status kenaikan kelas yang sudah ditetapkan wali kelas.',
        ],
        'graduation_statuses' => [
            'label' => 'Status Kelulusan',
            'description' => 'Menghapus status kelulusan siswa tingkat akhir.',
        ],
        'report_signatures' => [
            'label' => 'Persetujuan TTD Digital Raport',
            'description' => 'Menghapus riwayat permintaan/persetujuan tanda tangan digital untuk raport dan laporan tengah semester.',
        ],
    ];

    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? Database::connection();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function datasets(): array
    {
        return self::DATASETS;
    }

    /**
     * @return array<string, int>
     */
    public function countAll(int $yearId): array
    {
        $counts = [];

        foreach (self::DATASETS as $key => $definition) {
            $counts[$key] = $yearId > 0 ? $this->countDataset($key, $yearId) : 0;
        }

        return $counts;
    }

    /**
     * @param array<int, string> $datasetKeys
     * @return array<string, array<string, int>>
     */
    public function clean(int $yearId, array $datasetKeys): array
    {
        if ($yearId <= 0) {
            throw new InvalidArgumentException('Tahun ajaran tidak valid.');
        }

        $definitions = self::DATASETS;
        $normalized = [];

        foreach ($datasetKeys as $key) {
            $key = (string) $key;

            if ($key === '' || !isset($definitions[$key])) {
                continue;
            }

            if (!in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        if (empty($normalized)) {
            return [];
        }

        $report = [];
        $this->connection->beginTransaction();

        try {
            foreach ($normalized as $dataset) {
                $deleted = $this->cleanDataset($dataset, $yearId);
                $report[$dataset] = [
                    'deleted' => $deleted,
                ];
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        return $report;
    }

    private function countDataset(string $dataset, int $yearId): int
    {
        $statement = null;

        switch ($dataset) {
            case 'knowledge_assessments':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM penilaian_pengetahuan_siswa pps
                     JOIN guru_mata_pelajaran gmp ON gmp.id = pps.guru_mata_pelajaran_id
                     JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                     WHERE mp.tahun_ajaran_id = :year'
                );
                break;

            case 'skill_assessments':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM penilaian_keterampilan_siswa pks
                     JOIN guru_mata_pelajaran gmp ON gmp.id = pks.guru_mata_pelajaran_id
                     JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                     WHERE mp.tahun_ajaran_id = :year'
                );
                break;

            case 'competency_scores':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM penilaian_kd_siswa pks
                     JOIN guru_mata_pelajaran gmp ON gmp.id = pks.guru_mata_pelajaran_id
                     JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                     WHERE mp.tahun_ajaran_id = :year'
                );
                break;

            case 'kurmer_tp_assessments':
                $statement = $this->connection->prepare(
                    "SELECT COUNT(*) FROM penilaian_tp_siswa pts
                     JOIN kelas k ON k.id = pts.kelas_id
                     WHERE k.tahun_ajaran_id = :year AND k.kurikulum = 'kurmer'"
                );
                break;

            case 'kurmer_subject_summaries':
                $statement = $this->connection->prepare(
                    "SELECT COUNT(*) FROM penilaian_kurmer_mapel_siswa pk
                     JOIN kelas k ON k.id = pk.kelas_id
                     WHERE k.tahun_ajaran_id = :year AND k.kurikulum = 'kurmer'"
                );
                break;

            case 'attitude_scores':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM penilaian_sikap WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'attendance_records':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM presensi_siswa WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'attendance_sessions':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM presensi_siswa_sesi WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'extracurricular_records':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM siswa_ekstrakurikuler WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'achievement_records':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM prestasi_siswa WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'prakerin_scores':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM penilaian_prakerin WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'homeroom_notes':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM catatan_walikelas WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'promotion_statuses':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM status_naik_kelas WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'graduation_statuses':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM status_kelulusan_siswa WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'report_signatures':
                $statement = $this->connection->prepare(
                    "SELECT COUNT(*) FROM digital_document_signatures
                     WHERE tahun_ajaran_id = :year
                       AND document_type IN ('report_card', 'midterm_report')"
                );
                break;
        }

        if ($statement === null || $statement === false) {
            return 0;
        }

        $statement->bindValue(':year', $yearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return 0;
        }

        $result = $statement->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    private function cleanDataset(string $dataset, int $yearId): int
    {
        $statement = null;

        switch ($dataset) {
            case 'knowledge_assessments':
                $statement = $this->connection->prepare(
                    'DELETE pps FROM penilaian_pengetahuan_siswa pps
                     JOIN guru_mata_pelajaran gmp ON gmp.id = pps.guru_mata_pelajaran_id
                     JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                     WHERE mp.tahun_ajaran_id = :year'
                );
                break;

            case 'skill_assessments':
                $statement = $this->connection->prepare(
                    'DELETE pks FROM penilaian_keterampilan_siswa pks
                     JOIN guru_mata_pelajaran gmp ON gmp.id = pks.guru_mata_pelajaran_id
                     JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                     WHERE mp.tahun_ajaran_id = :year'
                );
                break;

            case 'competency_scores':
                $statement = $this->connection->prepare(
                    'DELETE pks FROM penilaian_kd_siswa pks
                     JOIN guru_mata_pelajaran gmp ON gmp.id = pks.guru_mata_pelajaran_id
                     JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                     WHERE mp.tahun_ajaran_id = :year'
                );
                break;

            case 'kurmer_tp_assessments':
                $statement = $this->connection->prepare(
                    "DELETE pts FROM penilaian_tp_siswa pts
                     JOIN kelas k ON k.id = pts.kelas_id
                     WHERE k.tahun_ajaran_id = :year AND k.kurikulum = 'kurmer'"
                );
                break;

            case 'kurmer_subject_summaries':
                $statement = $this->connection->prepare(
                    "DELETE pk FROM penilaian_kurmer_mapel_siswa pk
                     JOIN kelas k ON k.id = pk.kelas_id
                     WHERE k.tahun_ajaran_id = :year AND k.kurikulum = 'kurmer'"
                );
                break;

            case 'attitude_scores':
                $statement = $this->connection->prepare(
                    'DELETE FROM penilaian_sikap WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'attendance_records':
                $statement = $this->connection->prepare(
                    'DELETE FROM presensi_siswa WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'attendance_sessions':
                $statement = $this->connection->prepare(
                    'DELETE FROM presensi_siswa_sesi WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'extracurricular_records':
                $statement = $this->connection->prepare(
                    'DELETE FROM siswa_ekstrakurikuler WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'achievement_records':
                $statement = $this->connection->prepare(
                    'DELETE FROM prestasi_siswa WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'prakerin_scores':
                $statement = $this->connection->prepare(
                    'DELETE FROM penilaian_prakerin WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'homeroom_notes':
                $statement = $this->connection->prepare(
                    'DELETE FROM catatan_walikelas WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'promotion_statuses':
                $statement = $this->connection->prepare(
                    'DELETE FROM status_naik_kelas WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'graduation_statuses':
                $statement = $this->connection->prepare(
                    'DELETE FROM status_kelulusan_siswa WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'report_signatures':
                $statement = $this->connection->prepare(
                    "DELETE FROM digital_document_signatures
                     WHERE tahun_ajaran_id = :year
                       AND document_type IN ('report_card', 'midterm_report')"
                );
                break;
        }

        if ($statement === null || $statement === false) {
            return 0;
        }

        $statement->bindValue(':year', $yearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return 0;
        }

        return $statement->rowCount();
    }
}

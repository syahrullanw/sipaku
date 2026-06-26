<?php

namespace App\Services;

use Core\Database;
use InvalidArgumentException;
use PDO;

class PeriodicDataCopyService
{
    private const DATASETS = [
        'classes' => [
            'label' => 'Kelas',
        ],
        'student_class_placements' => [
            'label' => 'Penempatan Siswa',
        ],
        'subjects' => [
            'label' => 'Mata Pelajaran',
        ],
        'subject_teachers' => [
            'label' => 'Guru Pengampu',
        ],
        'lesson_schedules' => [
            'label' => 'Jadwal Pelajaran',
        ],
        'assessment_settings' => [
            'label' => 'Pengaturan Penilaian Mapel',
        ],
        'subject_competencies' => [
            'label' => 'Kompetensi Dasar',
        ],
        'extracurriculars' => [
            'label' => 'Ekstrakurikuler',
        ],
        'teacher_positions' => [
            'label' => 'Jabatan Akademik Guru',
        ],
        'student_positions' => [
            'label' => 'Jabatan Akademik Siswa',
        ],
    ];

    private PDO $connection;

    public function __construct()
    {
        $this->connection = Database::connection();
    }

    /**
     * @return array<string, string>
     */
    public static function datasetLabels(): array
    {
        $labels = [];

        foreach (self::DATASETS as $key => $config) {
            $labels[$key] = $config['label'];
        }

        return $labels;
    }

    /**
     * @return array<string, int>
     */
    public function countForYear(?int $yearId): array
    {
        $counts = [];

        foreach (self::DATASETS as $key => $config) {
            $counts[$key] = ($yearId ?? 0) > 0
                ? $this->countDataset($key, (int) $yearId)
                : 0;
        }

        return $counts;
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function copy(int $sourceYearId, int $targetYearId): array
    {
        if ($sourceYearId <= 0 || $targetYearId <= 0) {
            throw new InvalidArgumentException('Tahun ajaran tidak valid.');
        }

        if ($sourceYearId === $targetYearId) {
            throw new InvalidArgumentException('Tahun ajaran sumber dan tujuan tidak boleh sama.');
        }

        $now = date('Y-m-d H:i:s');

        $report = [];

        foreach (self::DATASETS as $key => $config) {
            $report[$key] = [
                'copied' => 0,
                'skipped' => 0,
            ];
        }

        $classMap = [];
        $subjectMap = [];
        $assignmentMap = [];

        $this->connection->beginTransaction();

        try {
            $skipStudentPlacements = $this->isEvenSemester($sourceYearId);

            $classResult = $this->copyClasses($sourceYearId, $targetYearId, $now);
            $report['classes'] = $classResult['report'];
            $classMap = $classResult['map'];

            if ($skipStudentPlacements) {
                $report['student_class_placements'] = [
                    'copied' => 0,
                    'skipped' => $this->countDataset('student_class_placements', $sourceYearId),
                ];
            } else {
                $report['student_class_placements'] = $this->copyStudentClassPlacements($sourceYearId, $targetYearId, $classMap, $now);
            }

            $subjectResult = $this->copySubjects($sourceYearId, $targetYearId, $now);
            $report['subjects'] = $subjectResult['report'];
            $subjectMap = $subjectResult['map'];

            $assignmentResult = $this->copySubjectTeachers($sourceYearId, $targetYearId, $subjectMap, $now);
            $report['subject_teachers'] = $assignmentResult['report'];
            $assignmentMap = $assignmentResult['map'];
            $this->copySubjectTeacherClasses($sourceYearId, $assignmentMap, $classMap, $now);

            $report['lesson_schedules'] = $this->copyLessonSchedules($sourceYearId, $targetYearId, $assignmentMap, $classMap, $now);
            $report['assessment_settings'] = $this->copyAssessmentSettings($sourceYearId, $assignmentMap, $now);
            $report['subject_competencies'] = $this->copySubjectCompetencies($sourceYearId, $assignmentMap, $classMap, $now);

            $report['extracurriculars'] = $this->copyExtracurriculars($sourceYearId, $targetYearId, $now);
            $report['teacher_positions'] = $this->copyTeacherPositions($sourceYearId, $targetYearId, $now);
            $report['student_positions'] = $this->copyStudentPositions($sourceYearId, $targetYearId, $now);

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        return $report;
    }

    /**
     * @return array<string, int>
     */
    public function copyLessonSchedulesOnly(int $sourceYearId, int $targetYearId): array
    {
        if ($sourceYearId <= 0 || $targetYearId <= 0) {
            throw new InvalidArgumentException('Tahun ajaran tidak valid.');
        }

        if ($sourceYearId === $targetYearId) {
            throw new InvalidArgumentException('Tahun ajaran sumber dan tujuan tidak boleh sama.');
        }

        $now = date('Y-m-d H:i:s');
        $this->connection->beginTransaction();

        try {
            $classMap = $this->existingClassMap($sourceYearId, $targetYearId);
            $subjectMap = $this->existingSubjectMap($sourceYearId, $targetYearId);
            $assignmentMap = $this->existingAssignmentMap($sourceYearId, $subjectMap);

            $report = $this->copyLessonSchedules($sourceYearId, $targetYearId, $assignmentMap, $classMap, $now);

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
            case 'classes':
                $statement = $this->connection->prepare('SELECT COUNT(*) FROM kelas WHERE tahun_ajaran_id = :year');
                break;

            case 'subjects':
                $statement = $this->connection->prepare('SELECT COUNT(*) FROM mata_pelajaran WHERE tahun_ajaran_id = :year');
                break;

            case 'subject_teachers':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM guru_mata_pelajaran gmp
                     JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                     WHERE mp.tahun_ajaran_id = :year'
                );
                break;

            case 'lesson_schedules':
                $statement = $this->connection->prepare('SELECT COUNT(*) FROM jadwal_pelajaran WHERE tahun_ajaran_id = :year');
                break;

            case 'assessment_settings':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM pengaturan_penilaian_mapel ppm
                     JOIN guru_mata_pelajaran gmp ON gmp.id = ppm.guru_mata_pelajaran_id
                     JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                     WHERE mp.tahun_ajaran_id = :year'
                );
                break;

            case 'subject_competencies':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM mata_pelajaran_kd kd
                     JOIN guru_mata_pelajaran gmp ON gmp.id = kd.guru_mata_pelajaran_id
                     JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                     WHERE mp.tahun_ajaran_id = :year'
                );
                break;

            case 'extracurriculars':
                $statement = $this->connection->prepare('SELECT COUNT(*) FROM ekstrakurikuler WHERE tahun_ajaran_id = :year');
                break;

            case 'teacher_positions':
                $statement = $this->connection->prepare('SELECT COUNT(*) FROM guru_jabatan_akademik WHERE tahun_ajaran_id = :year');
                break;

            case 'student_positions':
                $statement = $this->connection->prepare('SELECT COUNT(*) FROM siswa_jabatan_akademik WHERE tahun_ajaran_id = :year');
                break;

            case 'student_class_placements':
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM siswa s
                     JOIN kelas k ON k.id = s.kelas_id
                     WHERE k.tahun_ajaran_id = :year'
                );
                break;
        }

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
        $statement->execute();
        $result = $statement->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    /**
     * @return array<string, int>
     */
    /**
     * @return array{report: array<string, int>, map: array<int, int>}
     */
    private function copyClasses(int $sourceYearId, int $targetYearId, string $timestamp): array
    {
        $report = [
            'copied' => 0,
            'skipped' => 0,
        ];
        $map = [];

        $statement = $this->connection->prepare('SELECT * FROM kelas WHERE tahun_ajaran_id = :year ORDER BY id ASC');
        if ($statement === false) {
            return ['report' => $report, 'map' => $map];
        }

        $statement->bindValue(':year', $sourceYearId, PDO::PARAM_INT);
        $statement->execute();

        $classes = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($classes === false) {
            return ['report' => $report, 'map' => $map];
        }

        $existsQuery = $this->connection->prepare(
            'SELECT id FROM kelas WHERE tahun_ajaran_id = :target AND jurusan_id = :major AND nama = :name LIMIT 1'
        );

        $insertQuery = $this->connection->prepare(
            'INSERT INTO kelas (tahun_ajaran_id, jurusan_id, tingkat, nama, wali_kelas_id, created_at, updated_at)
             VALUES (:year, :major, :level, :name, :homeroom, :created_at, :updated_at)'
        );

        foreach ($classes as $class) {
            $existsQuery->execute([
                ':target' => $targetYearId,
                ':major' => (int) ($class['jurusan_id'] ?? 0),
                ':name' => (string) ($class['nama'] ?? ''),
            ]);

            $existingId = $existsQuery->fetchColumn();

            if ($existingId !== false) {
                $map[(int) $class['id']] = (int) $existingId;
                $report['skipped']++;
                continue;
            }

            $insertQuery->execute([
                ':year' => $targetYearId,
                ':major' => (int) ($class['jurusan_id'] ?? 0),
                ':level' => (int) ($class['tingkat'] ?? 0),
                ':name' => (string) ($class['nama'] ?? ''),
                ':homeroom' => $class['wali_kelas_id'] !== null ? (int) $class['wali_kelas_id'] : null,
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);

            $newId = (int) $this->connection->lastInsertId();
            if ($newId <= 0) {
                $existsQuery->execute([
                    ':target' => $targetYearId,
                    ':major' => (int) ($class['jurusan_id'] ?? 0),
                    ':name' => (string) ($class['nama'] ?? ''),
                ]);
                $newId = (int) $existsQuery->fetchColumn();
            }

            if ($newId > 0) {
                $map[(int) $class['id']] = $newId;
            }

            $report['copied']++;
        }

        return ['report' => $report, 'map' => $map];
    }

    /**
     * @param array<int, int> $assignmentMap
     * @param array<int, int> $classMap
     *
     * @return array<string, int>
     */
    private function copyLessonSchedules(int $sourceYearId, int $targetYearId, array $assignmentMap, array $classMap, string $timestamp): array
    {
        $report = [
            'copied' => 0,
            'skipped' => 0,
        ];

        if (empty($assignmentMap) || empty($classMap)) {
            return $report;
        }

        $statement = $this->connection->prepare(
            'SELECT *
             FROM jadwal_pelajaran
             WHERE tahun_ajaran_id = :year
             ORDER BY id ASC'
        );

        if ($statement === false) {
            return $report;
        }

        $statement->bindValue(':year', $sourceYearId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return $report;
        }

        $existsQuery = $this->connection->prepare(
            'SELECT id FROM jadwal_pelajaran
             WHERE tahun_ajaran_id = :target
               AND guru_mata_pelajaran_id = :assignment
               AND kelas_id = :class
               AND hari = :day
               AND waktu_mulai = :start_time
               AND waktu_selesai = :end_time
             LIMIT 1'
        );

        $insertQuery = $this->connection->prepare(
            'INSERT INTO jadwal_pelajaran (
                tahun_ajaran_id, guru_mata_pelajaran_id, kelas_id, hari, waktu_mulai, waktu_selesai,
                jumlah_jam, created_at, updated_at
            ) VALUES (
                :year, :assignment, :class, :day, :start_time, :end_time,
                :lesson_count, :created_at, :updated_at
            )'
        );

        if ($existsQuery === false || $insertQuery === false) {
            return $report;
        }

        foreach ($rows as $row) {
            $sourceAssignmentId = (int) ($row['guru_mata_pelajaran_id'] ?? 0);
            $sourceClassId = (int) ($row['kelas_id'] ?? 0);
            $targetAssignmentId = $assignmentMap[$sourceAssignmentId] ?? null;
            $targetClassId = $classMap[$sourceClassId] ?? null;

            if ($targetAssignmentId === null || $targetClassId === null) {
                $report['skipped']++;
                continue;
            }

            $existsQuery->execute([
                ':target' => $targetYearId,
                ':assignment' => $targetAssignmentId,
                ':class' => $targetClassId,
                ':day' => (string) ($row['hari'] ?? ''),
                ':start_time' => (string) ($row['waktu_mulai'] ?? ''),
                ':end_time' => (string) ($row['waktu_selesai'] ?? ''),
            ]);

            if ($existsQuery->fetchColumn() !== false) {
                $report['skipped']++;
                continue;
            }

            $insertQuery->execute([
                ':year' => $targetYearId,
                ':assignment' => $targetAssignmentId,
                ':class' => $targetClassId,
                ':day' => (string) ($row['hari'] ?? ''),
                ':start_time' => (string) ($row['waktu_mulai'] ?? ''),
                ':end_time' => (string) ($row['waktu_selesai'] ?? ''),
                ':lesson_count' => (int) ($row['jumlah_jam'] ?? 0),
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);

            $report['copied']++;
        }

        return $report;
    }

    /**
     * @return array{report: array<string, int>, map: array<int, int>}
     */
    private function copySubjects(int $sourceYearId, int $targetYearId, string $timestamp): array
    {
        $report = [
            'copied' => 0,
            'skipped' => 0,
        ];
        $map = [];

        $statement = $this->connection->prepare('SELECT * FROM mata_pelajaran WHERE tahun_ajaran_id = :year ORDER BY id ASC');
        if ($statement === false) {
            return ['report' => $report, 'map' => $map];
        }

        $statement->bindValue(':year', $sourceYearId, PDO::PARAM_INT);
        $statement->execute();

        $subjects = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($subjects === false) {
            return ['report' => $report, 'map' => $map];
        }

        $existsQuery = $this->connection->prepare(
            'SELECT id FROM mata_pelajaran WHERE tahun_ajaran_id = :target AND kode = :code LIMIT 1'
        );

        $insertQuery = $this->connection->prepare(
            'INSERT INTO mata_pelajaran (tahun_ajaran_id, kode, nama, jenis, jurusan_id, deskripsi, created_at, updated_at)
             VALUES (:year, :code, :name, :type, :major, :description, :created_at, :updated_at)'
        );

        foreach ($subjects as $subject) {
            $existsQuery->execute([
                ':target' => $targetYearId,
                ':code' => (string) ($subject['kode'] ?? ''),
            ]);

            $existingId = $existsQuery->fetchColumn();

            if ($existingId !== false) {
                $map[(int) $subject['id']] = (int) $existingId;
                $report['skipped']++;
                continue;
            }

            $insertQuery->execute([
                ':year' => $targetYearId,
                ':code' => (string) ($subject['kode'] ?? ''),
                ':name' => (string) ($subject['nama'] ?? ''),
                ':type' => (string) ($subject['jenis'] ?? ''),
                ':major' => $subject['jurusan_id'] !== null ? (int) $subject['jurusan_id'] : null,
                ':description' => $subject['deskripsi'] ?? null,
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);

            $newId = (int) $this->connection->lastInsertId();
            $map[(int) $subject['id']] = $newId;
            $report['copied']++;
        }

        return ['report' => $report, 'map' => $map];
    }

    /**
     * @return array<int, int>
     */
    private function existingClassMap(int $sourceYearId, int $targetYearId): array
    {
        $statement = $this->connection->prepare(
            'SELECT source.id AS source_id, target.id AS target_id
             FROM kelas source
             JOIN kelas target
               ON target.tahun_ajaran_id = :target
              AND target.jurusan_id <=> source.jurusan_id
              AND target.nama = source.nama
             WHERE source.tahun_ajaran_id = :source
             ORDER BY source.id ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':source', $sourceYearId, PDO::PARAM_INT);
        $statement->bindValue(':target', $targetYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $sourceId = (int) ($row['source_id'] ?? 0);
            $targetId = (int) ($row['target_id'] ?? 0);
            if ($sourceId > 0 && $targetId > 0) {
                $map[$sourceId] = $targetId;
            }
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    private function existingSubjectMap(int $sourceYearId, int $targetYearId): array
    {
        $statement = $this->connection->prepare(
            'SELECT source.id AS source_id, target.id AS target_id
             FROM mata_pelajaran source
             JOIN mata_pelajaran target
               ON target.tahun_ajaran_id = :target
              AND target.kode = source.kode
             WHERE source.tahun_ajaran_id = :source
             ORDER BY source.id ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':source', $sourceYearId, PDO::PARAM_INT);
        $statement->bindValue(':target', $targetYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $sourceId = (int) ($row['source_id'] ?? 0);
            $targetId = (int) ($row['target_id'] ?? 0);
            if ($sourceId > 0 && $targetId > 0) {
                $map[$sourceId] = $targetId;
            }
        }

        return $map;
    }

    /**
     * @param array<int, int> $subjectMap
     *
     * @return array<int, int>
     */
    private function existingAssignmentMap(int $sourceYearId, array $subjectMap): array
    {
        if (empty($subjectMap)) {
            return [];
        }

        $statement = $this->connection->prepare(
            'SELECT gmp.id, gmp.mata_pelajaran_id, gmp.guru_id
             FROM guru_mata_pelajaran gmp
             JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
             WHERE mp.tahun_ajaran_id = :source
             ORDER BY gmp.id ASC'
        );

        $targetQuery = $this->connection->prepare(
            'SELECT id FROM guru_mata_pelajaran
             WHERE mata_pelajaran_id = :subject AND guru_id = :teacher
             LIMIT 1'
        );

        if ($statement === false || $targetQuery === false) {
            return [];
        }

        $statement->bindValue(':source', $sourceYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $sourceId = (int) ($row['id'] ?? 0);
            $sourceSubjectId = (int) ($row['mata_pelajaran_id'] ?? 0);
            $targetSubjectId = $subjectMap[$sourceSubjectId] ?? null;

            if ($sourceId <= 0 || $targetSubjectId === null) {
                continue;
            }

            $targetQuery->execute([
                ':subject' => $targetSubjectId,
                ':teacher' => (int) ($row['guru_id'] ?? 0),
            ]);

            $targetId = $targetQuery->fetchColumn();
            if ($targetId !== false) {
                $map[$sourceId] = (int) $targetId;
            }
        }

        return $map;
    }

    /**
     * @param array<int, int> $subjectMap
     *
     * @return array{report: array<string, int>, map: array<int, int>}
     */
    private function copySubjectTeachers(int $sourceYearId, int $targetYearId, array $subjectMap, string $timestamp): array
    {
        $report = [
            'copied' => 0,
            'skipped' => 0,
        ];
        $map = [];

        if (empty($subjectMap)) {
            return ['report' => $report, 'map' => $map];
        }

        $statement = $this->connection->prepare(
            'SELECT gmp.*
             FROM guru_mata_pelajaran gmp
             JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
             WHERE mp.tahun_ajaran_id = :year
             ORDER BY gmp.id ASC'
        );

        if ($statement === false) {
            return ['report' => $report, 'map' => $map];
        }

        $statement->bindValue(':year', $sourceYearId, PDO::PARAM_INT);
        $statement->execute();

        $assignments = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($assignments === false) {
            return ['report' => $report, 'map' => $map];
        }

        $existsQuery = $this->connection->prepare(
            'SELECT id FROM guru_mata_pelajaran WHERE mata_pelajaran_id = :subject AND guru_id = :teacher LIMIT 1'
        );

        $insertQuery = $this->connection->prepare(
            'INSERT INTO guru_mata_pelajaran (mata_pelajaran_id, guru_id, catatan, created_at, updated_at)
             VALUES (:subject, :teacher, :note, :created_at, :updated_at)'
        );

        foreach ($assignments as $assignment) {
            $sourceSubjectId = (int) ($assignment['mata_pelajaran_id'] ?? 0);
            $targetSubjectId = $subjectMap[$sourceSubjectId] ?? null;

            if ($targetSubjectId === null) {
                $report['skipped']++;
                continue;
            }

            $existsQuery->execute([
                ':subject' => $targetSubjectId,
                ':teacher' => (int) ($assignment['guru_id'] ?? 0),
            ]);

            $existingId = $existsQuery->fetchColumn();

            if ($existingId !== false) {
                $map[(int) $assignment['id']] = (int) $existingId;
                $report['skipped']++;
                continue;
            }

            $insertQuery->execute([
                ':subject' => $targetSubjectId,
                ':teacher' => (int) ($assignment['guru_id'] ?? 0),
                ':note' => $assignment['catatan'] ?? null,
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);

            $newId = (int) $this->connection->lastInsertId();
            $map[(int) $assignment['id']] = $newId;
            $report['copied']++;
        }

        return ['report' => $report, 'map' => $map];
    }

    /**
     * @param array<int, int> $assignmentMap
     * @param array<int, int> $classMap
     */
    private function copySubjectTeacherClasses(int $sourceYearId, array $assignmentMap, array $classMap, string $timestamp): void
    {
        if (empty($assignmentMap) || empty($classMap)) {
            return;
        }

        $statement = $this->connection->prepare(
            'SELECT gmpk.guru_mata_pelajaran_id, gmpk.kelas_id
             FROM guru_mata_pelajaran_kelas gmpk
             JOIN guru_mata_pelajaran gmp ON gmp.id = gmpk.guru_mata_pelajaran_id
             JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
             WHERE mp.tahun_ajaran_id = :year
             ORDER BY gmpk.id ASC'
        );

        if ($statement === false) {
            return;
        }

        $statement->bindValue(':year', $sourceYearId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false || empty($rows)) {
            return;
        }

        $existsQuery = $this->connection->prepare(
            'SELECT id FROM guru_mata_pelajaran_kelas WHERE guru_mata_pelajaran_id = :assignment AND kelas_id = :class LIMIT 1'
        );

        $insertQuery = $this->connection->prepare(
            'INSERT INTO guru_mata_pelajaran_kelas (guru_mata_pelajaran_id, kelas_id, created_at, updated_at)
             VALUES (:assignment, :class, :created_at, :updated_at)'
        );

        if ($existsQuery === false || $insertQuery === false) {
            return;
        }

        foreach ($rows as $row) {
            $sourceAssignmentId = (int) ($row['guru_mata_pelajaran_id'] ?? 0);
            $sourceClassId = (int) ($row['kelas_id'] ?? 0);
            $targetAssignmentId = $assignmentMap[$sourceAssignmentId] ?? null;
            $targetClassId = $classMap[$sourceClassId] ?? null;

            if ($targetAssignmentId === null || $targetClassId === null) {
                continue;
            }

            $existsQuery->execute([
                ':assignment' => $targetAssignmentId,
                ':class' => $targetClassId,
            ]);

            if ($existsQuery->fetchColumn() !== false) {
                continue;
            }

            $insertQuery->execute([
                ':assignment' => $targetAssignmentId,
                ':class' => $targetClassId,
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);
        }
    }

    /**
     * @param array<int, int> $assignmentMap
     *
     * @return array<string, int>
     */
    private function copyAssessmentSettings(int $sourceYearId, array $assignmentMap, string $timestamp): array
    {
        $report = [
            'copied' => 0,
            'skipped' => 0,
        ];

        if (empty($assignmentMap)) {
            return $report;
        }

        $statement = $this->connection->prepare(
            'SELECT ppm.*
             FROM pengaturan_penilaian_mapel ppm
             JOIN guru_mata_pelajaran gmp ON gmp.id = ppm.guru_mata_pelajaran_id
             JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
             WHERE mp.tahun_ajaran_id = :year
             ORDER BY ppm.id ASC'
        );

        if ($statement === false) {
            return $report;
        }

        $statement->bindValue(':year', $sourceYearId, PDO::PARAM_INT);
        $statement->execute();

        $settings = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($settings === false) {
            return $report;
        }

        $existsQuery = $this->connection->prepare(
            'SELECT id FROM pengaturan_penilaian_mapel WHERE guru_mata_pelajaran_id = :assignment LIMIT 1'
        );

        $insertQuery = $this->connection->prepare(
            'INSERT INTO pengaturan_penilaian_mapel (
                guru_mata_pelajaran_id, enable_keterampilan, enable_kkm, nilai_kkm,
                bobot_manual, bobot_kd, bobot_uts, bobot_uas, created_at, updated_at
            ) VALUES (
                :assignment, :enable_keterampilan, :enable_kkm, :nilai_kkm,
                :bobot_manual, :bobot_kd, :bobot_uts, :bobot_uas, :created_at, :updated_at
            )'
        );

        foreach ($settings as $setting) {
            $sourceAssignmentId = (int) ($setting['guru_mata_pelajaran_id'] ?? 0);
            $targetAssignmentId = $assignmentMap[$sourceAssignmentId] ?? null;

            if ($targetAssignmentId === null) {
                $report['skipped']++;
                continue;
            }

            $existsQuery->execute([
                ':assignment' => $targetAssignmentId,
            ]);

            $existingId = $existsQuery->fetchColumn();

            if ($existingId !== false) {
                $report['skipped']++;
                continue;
            }

            $insertQuery->execute([
                ':assignment' => $targetAssignmentId,
                ':enable_keterampilan' => (int) ($setting['enable_keterampilan'] ?? 1),
                ':enable_kkm' => (int) ($setting['enable_kkm'] ?? 0),
                ':nilai_kkm' => $setting['nilai_kkm'] !== null ? (float) $setting['nilai_kkm'] : null,
                ':bobot_manual' => (int) ($setting['bobot_manual'] ?? 0),
                ':bobot_kd' => $setting['bobot_kd'] !== null ? (float) $setting['bobot_kd'] : null,
                ':bobot_uts' => $setting['bobot_uts'] !== null ? (float) $setting['bobot_uts'] : null,
                ':bobot_uas' => $setting['bobot_uas'] !== null ? (float) $setting['bobot_uas'] : null,
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);

            $report['copied']++;
        }

        return $report;
    }

    /**
     * @param array<int, int> $assignmentMap
     * @param array<int, int> $classMap
     *
     * @return array<string, int>
     */
    private function copySubjectCompetencies(int $sourceYearId, array $assignmentMap, array $classMap, string $timestamp): array
    {
        $report = [
            'copied' => 0,
            'skipped' => 0,
        ];

        if (empty($assignmentMap) || empty($classMap)) {
            return $report;
        }

        $statement = $this->connection->prepare(
            'SELECT kd.*
             FROM mata_pelajaran_kd kd
             JOIN guru_mata_pelajaran gmp ON gmp.id = kd.guru_mata_pelajaran_id
             JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
             WHERE mp.tahun_ajaran_id = :year
             ORDER BY kd.id ASC'
        );

        if ($statement === false) {
            return $report;
        }

        $statement->bindValue(':year', $sourceYearId, PDO::PARAM_INT);
        $statement->execute();

        $competencies = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($competencies === false) {
            return $report;
        }

        $existsQuery = $this->connection->prepare(
            'SELECT id FROM mata_pelajaran_kd WHERE guru_mata_pelajaran_id = :assignment AND kelas_id = :class AND jenis = :type AND kode = :code LIMIT 1'
        );

        $insertQuery = $this->connection->prepare(
            'INSERT INTO mata_pelajaran_kd (guru_mata_pelajaran_id, kelas_id, jenis, kode, deskripsi, created_at, updated_at)
             VALUES (:assignment, :class, :type, :code, :description, :created_at, :updated_at)'
        );

        foreach ($competencies as $competency) {
            $sourceAssignmentId = (int) ($competency['guru_mata_pelajaran_id'] ?? 0);
            $targetAssignmentId = $assignmentMap[$sourceAssignmentId] ?? null;
            $sourceClassId = (int) ($competency['kelas_id'] ?? 0);
            $targetClassId = $classMap[$sourceClassId] ?? null;

            if ($targetAssignmentId === null || $targetClassId === null) {
                $report['skipped']++;
                continue;
            }

            $existsQuery->execute([
                ':assignment' => $targetAssignmentId,
                ':class' => $targetClassId,
                ':type' => (string) ($competency['jenis'] ?? ''),
                ':code' => (string) ($competency['kode'] ?? ''),
            ]);

            $existingId = $existsQuery->fetchColumn();

            if ($existingId !== false) {
                $report['skipped']++;
                continue;
            }

            $insertQuery->execute([
                ':assignment' => $targetAssignmentId,
                ':class' => $targetClassId,
                ':type' => (string) ($competency['jenis'] ?? ''),
                ':code' => (string) ($competency['kode'] ?? ''),
                ':description' => $competency['deskripsi'] ?? null,
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);

            $report['copied']++;
        }

        return $report;
    }

    /**
     * @return array<string, int>
     */
    private function copyExtracurriculars(int $sourceYearId, int $targetYearId, string $timestamp): array
    {
        $report = [
            'copied' => 0,
            'skipped' => 0,
        ];

        $statement = $this->connection->prepare(
            'SELECT * FROM ekstrakurikuler WHERE tahun_ajaran_id = :year ORDER BY id ASC'
        );

        if ($statement === false) {
            return $report;
        }

        $statement->bindValue(':year', $sourceYearId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return $report;
        }

        $existsQuery = $this->connection->prepare(
            'SELECT id FROM ekstrakurikuler WHERE tahun_ajaran_id = :target AND nama = :name LIMIT 1'
        );

        $insertQuery = $this->connection->prepare(
            'INSERT INTO ekstrakurikuler (tahun_ajaran_id, nama, deskripsi, pembina_guru_id, jadwal, created_at, updated_at)
             VALUES (:year, :name, :description, :mentor, :schedule, :created_at, :updated_at)'
        );

        foreach ($rows as $row) {
            $existsQuery->execute([
                ':target' => $targetYearId,
                ':name' => (string) ($row['nama'] ?? ''),
            ]);

            $existingId = $existsQuery->fetchColumn();

            if ($existingId !== false) {
                $report['skipped']++;
                continue;
            }

            $insertQuery->execute([
                ':year' => $targetYearId,
                ':name' => (string) ($row['nama'] ?? ''),
                ':description' => $row['deskripsi'] ?? null,
                ':mentor' => $row['pembina_guru_id'] !== null ? (int) $row['pembina_guru_id'] : null,
                ':schedule' => $row['jadwal'] ?? null,
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);

            $report['copied']++;
        }

        return $report;
    }

    /**
     * @return array<string, int>
     */
    private function copyTeacherPositions(int $sourceYearId, int $targetYearId, string $timestamp): array
    {
        $report = [
            'copied' => 0,
            'skipped' => 0,
        ];

        $statement = $this->connection->prepare(
            'SELECT * FROM guru_jabatan_akademik WHERE tahun_ajaran_id = :year ORDER BY id ASC'
        );

        if ($statement === false) {
            return $report;
        }

        $statement->bindValue(':year', $sourceYearId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return $report;
        }

        $existsQuery = $this->connection->prepare(
            'SELECT id FROM guru_jabatan_akademik
             WHERE tahun_ajaran_id = :target AND guru_id = :teacher AND jabatan_akademik_id = :position
             LIMIT 1'
        );

        $insertQuery = $this->connection->prepare(
            'INSERT INTO guru_jabatan_akademik (
                tahun_ajaran_id, guru_id, jabatan_akademik_id, tanggal_mulai, tanggal_selesai, catatan, created_at, updated_at
            ) VALUES (
                :year, :teacher, :position, :start_date, :end_date, :note, :created_at, :updated_at
            )'
        );

        foreach ($rows as $row) {
            $existsQuery->execute([
                ':target' => $targetYearId,
                ':teacher' => (int) ($row['guru_id'] ?? 0),
                ':position' => (int) ($row['jabatan_akademik_id'] ?? 0),
            ]);

            $existingId = $existsQuery->fetchColumn();

            if ($existingId !== false) {
                $report['skipped']++;
                continue;
            }

            $insertQuery->execute([
                ':year' => $targetYearId,
                ':teacher' => (int) ($row['guru_id'] ?? 0),
                ':position' => (int) ($row['jabatan_akademik_id'] ?? 0),
                ':start_date' => $row['tanggal_mulai'] ?? null,
                ':end_date' => $row['tanggal_selesai'] ?? null,
                ':note' => $row['catatan'] ?? null,
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);

            $report['copied']++;
        }

        return $report;
    }

    /**
     * @return array<string, int>
     */
    private function copyStudentPositions(int $sourceYearId, int $targetYearId, string $timestamp): array
    {
        $report = [
            'copied' => 0,
            'skipped' => 0,
        ];

        $statement = $this->connection->prepare(
            'SELECT * FROM siswa_jabatan_akademik WHERE tahun_ajaran_id = :year ORDER BY id ASC'
        );

        if ($statement === false) {
            return $report;
        }

        $statement->bindValue(':year', $sourceYearId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return $report;
        }

        $existsQuery = $this->connection->prepare(
            'SELECT id FROM siswa_jabatan_akademik
             WHERE tahun_ajaran_id = :target AND siswa_id = :student AND jabatan_akademik_id = :position
             LIMIT 1'
        );

        $insertQuery = $this->connection->prepare(
            'INSERT INTO siswa_jabatan_akademik (
                tahun_ajaran_id, siswa_id, jabatan_akademik_id, tanggal_mulai, tanggal_selesai, catatan, created_at, updated_at
            ) VALUES (
                :year, :student, :position, :start_date, :end_date, :note, :created_at, :updated_at
            )'
        );

        foreach ($rows as $row) {
            $existsQuery->execute([
                ':target' => $targetYearId,
                ':student' => (int) ($row['siswa_id'] ?? 0),
                ':position' => (int) ($row['jabatan_akademik_id'] ?? 0),
            ]);

            $existingId = $existsQuery->fetchColumn();

            if ($existingId !== false) {
                $report['skipped']++;
                continue;
            }

            $insertQuery->execute([
                ':year' => $targetYearId,
                ':student' => (int) ($row['siswa_id'] ?? 0),
                ':position' => (int) ($row['jabatan_akademik_id'] ?? 0),
                ':start_date' => $row['tanggal_mulai'] ?? null,
                ':end_date' => $row['tanggal_selesai'] ?? null,
                ':note' => $row['catatan'] ?? null,
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);

            $report['copied']++;
        }

        return $report;
    }

    /**
     * @param array<int, int> $classMap
     *
     * @return array<string, int>
     */
    private function copyStudentClassPlacements(int $sourceYearId, int $targetYearId, array $classMap, string $timestamp): array
    {
        $report = [
            'copied' => 0,
            'skipped' => 0,
        ];

        if ($sourceYearId <= 0 || $targetYearId <= 0 || empty($classMap)) {
            return $report;
        }

        $statement = $this->connection->prepare(
            'SELECT s.id, s.kelas_id, s.tahun_ajaran_id
             FROM siswa s
             JOIN kelas k ON k.id = s.kelas_id
             WHERE k.tahun_ajaran_id = :source'
        );

        if ($statement === false) {
            return $report;
        }

        $statement->bindValue(':source', $sourceYearId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false || empty($rows)) {
            return $report;
        }

        $updateQuery = $this->connection->prepare(
            'UPDATE siswa
             SET kelas_id = :class_id,
                 tahun_ajaran_id = :year_id,
                 updated_at = :updated_at
             WHERE id = :student_id
               AND (tahun_ajaran_id IS NULL OR tahun_ajaran_id = :source_year)'
        );

        if ($updateQuery === false) {
            return $report;
        }

        foreach ($rows as $row) {
            $studentId = (int) ($row['id'] ?? 0);
            $sourceClassId = (int) ($row['kelas_id'] ?? 0);
            $currentYearId = isset($row['tahun_ajaran_id']) ? (int) $row['tahun_ajaran_id'] : null;

            if ($studentId <= 0 || $sourceClassId <= 0) {
                $report['skipped']++;
                continue;
            }

            $targetClassId = $classMap[$sourceClassId] ?? null;

            if ($targetClassId === null || $targetClassId <= 0) {
                $report['skipped']++;
                continue;
            }

            if ($currentYearId === $targetYearId) {
                $report['skipped']++;
                continue;
            }

            $updateQuery->bindValue(':class_id', $targetClassId, PDO::PARAM_INT);
            $updateQuery->bindValue(':year_id', $targetYearId, PDO::PARAM_INT);
            $updateQuery->bindValue(':updated_at', $timestamp);
            $updateQuery->bindValue(':student_id', $studentId, PDO::PARAM_INT);
            $updateQuery->bindValue(':source_year', $sourceYearId, PDO::PARAM_INT);

            if ($updateQuery->execute()) {
                $affected = $updateQuery->rowCount();
                if ($affected > 0) {
                    $report['copied']++;
                    continue;
                }
            }

            $report['skipped']++;
        }

        return $report;
    }

    private function isEvenSemester(int $yearId): bool
    {
        if ($yearId <= 0) {
            return false;
        }

        $statement = $this->connection->prepare('SELECT semester_aktif FROM tahun_ajaran WHERE id = :id LIMIT 1');

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':id', $yearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return false;
        }

        $semester = $statement->fetchColumn();

        return (int) $semester === 2;
    }
}

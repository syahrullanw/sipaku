<?php

namespace App\Services\Migration;

use App\Models\Subject;
use Core\Database;
use DateInterval;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class LegacyRaporMigrationService
{
    public const LEGACY_PREFIX = 'legacy_';

    /**
     * @var array<string, string>
     */
    public const DATASET_LABELS = [
        'majors' => 'Jurusan',
        'teachers' => 'Guru',
        'school_years' => 'Tahun Ajaran',
        'classes' => 'Kelas',
        'subjects' => 'Mata Pelajaran',
    ];

    private PDO $connection;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $legacyTableCache = [];

    /**
     * @var array<string, array<int, int>>
     */
    private array $legacyMappingCache = [];

    private bool $legacyMappingTableEnsured = false;

    /**
     * @var array<string, int|null>
     */
    private array $majorCodeCache = [];

    public function __construct()
    {
        $this->connection = Database::connection();
    }

    public function defaultSqlPath(): string
    {
        return base_path('smkisnus_rapor.sql');
    }

    public function sqlFileExists(?string $path = null): bool
    {
        $target = $path ?? $this->defaultSqlPath();

        return is_file($target);
    }

    public function hasLegacyTables(): bool
    {
        return !empty($this->listLegacyTables());
    }

    /**
     * @return array<int, string>
     */
    public function listLegacyTables(): array
    {
        if (!empty($this->legacyTableCache)) {
            return array_column($this->legacyTableCache, 'name');
        }

        $statement = $this->connection->query("SHOW TABLES LIKE '" . self::LEGACY_PREFIX . "%'");

        if ($statement === false) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_NUM);

        $tables = [];
        foreach ($rows as $row) {
            if (!empty($row[0])) {
                $tables[] = (string) $row[0];
            }
        }

        $this->legacyTableCache = array_map(static fn (string $name): array => ['name' => $name], $tables);

        return $tables;
    }

    public function dropLegacyTables(): void
    {
        $tables = $this->listLegacyTables();

        if (empty($tables)) {
            return;
        }

        $this->connection->beginTransaction();

        try {
            foreach ($tables as $table) {
                $this->connection->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
            }

            $this->connection->commit();
            $this->legacyTableCache = [];
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function importFromSql(?string $path = null, bool $force = false): array
    {
        $targetPath = $path ?? $this->defaultSqlPath();

        if (!is_file($targetPath)) {
            throw new RuntimeException(sprintf('File SQL tidak ditemukan di path %s.', $targetPath));
        }

        if ($this->hasLegacyTables()) {
            if (!$force) {
                throw new RuntimeException('Data legacy sudah terimport. Hapus terlebih dahulu atau aktifkan opsi timpa.');
            }

            $this->dropLegacyTables();
        }

        $sqlContents = file_get_contents($targetPath);

        if ($sqlContents === false || $sqlContents === '') {
            throw new RuntimeException('File SQL kosong atau tidak dapat dibaca.');
        }

        $transformed = $this->rewriteLegacyTableNames($sqlContents);
        $statements = $this->splitSqlStatements($transformed);

        $executed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($statements as $statement) {
            $trimmed = trim($statement);

            if ($trimmed === '') {
                $skipped++;
                continue;
            }

            try {
                $this->connection->exec($trimmed);
                $executed++;
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        $this->legacyTableCache = [];

        return [
            'statements' => count($statements),
            'executed' => $executed,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function legacyTableCounts(): array
    {
        $tables = [
            'm_jurusan',
            'm_kelas',
            'm_mapel',
            'm_guru',
            'm_siswa',
            't_guru_mapel',
            't_kelas_siswa',
            't_kkm',
            't_mapel_kd',
            't_nilai',
            't_nilai_ket',
            't_nilai_sikap_sp',
            't_nilai_sikap_so',
            't_prestasi',
            't_raport_siswa',
            't_walikelas',
            'tahun',
            'ttd_digital_signatures',
        ];

        $counts = [];

        foreach ($tables as $table) {
            $legacyTable = self::LEGACY_PREFIX . $table;

            if (!$this->tableExists($legacyTable)) {
                $counts[$table] = 0;
                continue;
            }

            $statement = $this->connection->query(sprintf('SELECT COUNT(*) FROM `%s`', $legacyTable));
            $count = 0;

            if ($statement !== false) {
                $value = $statement->fetchColumn();
                if ($value !== false) {
                    $count = (int) $value;
                }
            }

            $counts[$table] = $count;
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function migrate(array $datasets, bool $dryRun = false): array
    {
        $selected = array_values(array_filter(array_unique(array_map('strval', $datasets))));
        $report = [];

        foreach ($selected as $dataset) {
            if (!array_key_exists($dataset, self::DATASET_LABELS)) {
                $report[$dataset] = [
                    'status' => 'skipped',
                    'message' => 'Dataset tidak dikenali.',
                ];
                continue;
            }

            try {
                switch ($dataset) {
                    case 'majors':
                        $result = $this->migrateMajors($dryRun);
                        break;
                    case 'teachers':
                        $result = $this->migrateTeachers($dryRun);
                        break;
                    case 'school_years':
                        $result = $this->migrateSchoolYears($dryRun);
                        break;
                    case 'classes':
                        $result = $this->migrateClasses($dryRun);
                        break;
                    case 'subjects':
                        $result = $this->migrateSubjects($dryRun);
                        break;
                    default:
                        $result = [
                            'status' => 'skipped',
                            'message' => 'Dataset belum diimplementasikan.',
                        ];
                }
            } catch (\Throwable $exception) {
                $result = [
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ];
            }

            $report[$dataset] = $result;
        }

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function migrateMajors(bool $dryRun = false): array
    {
        $legacyTable = self::LEGACY_PREFIX . 'm_jurusan';

        if (!$this->tableExists($legacyTable)) {
            return [
                'status' => 'skipped',
                'message' => 'Tabel legacy jurusan belum diimport.',
            ];
        }

        $statement = $this->connection->query(sprintf('SELECT * FROM `%s` ORDER BY id ASC', $legacyTable));

        if ($statement === false) {
            throw new RuntimeException('Gagal membaca data jurusan legacy.');
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [
                'status' => 'skipped',
                'message' => 'Tidak ada data jurusan legacy yang ditemukan.',
            ];
        }

        $inserted = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');
        $transactionStarted = false;

        if (!$dryRun && !$this->connection->inTransaction()) {
            $transactionStarted = $this->connection->beginTransaction();
        }

        try {
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $code = trim((string) ($row['kode_jurusan'] ?? ''));
                $name = trim((string) ($row['nama_jurusan'] ?? ''));

                if ($id <= 0 || $code === '' || $name === '') {
                    $skipped++;
                    continue;
                }

                if ($this->getLegacyMapping('majors', $id) !== null) {
                    $skipped++;
                    continue;
                }

                $existingMajor = $this->findMajorByIdOrCode($id, $code);
                if ($existingMajor !== null) {
                    $this->storeLegacyMapping('majors', $id, (int) ($existingMajor['id'] ?? 0));
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $inserted++;
                    continue;
                }

                $sql = <<<SQL
INSERT INTO jurusan (id, kode, nama, status, created_at, updated_at)
VALUES (:id, :kode, :nama, :status, :created_at, :updated_at)
SQL;

                $statement = $this->connection->prepare($sql);

                if ($statement === false) {
                    throw new RuntimeException('Gagal mempersiapkan penyimpanan data jurusan.');
                }

                $statement->bindValue(':id', $id, PDO::PARAM_INT);
                $statement->bindValue(':kode', $code);
                $statement->bindValue(':nama', $name);
                $statement->bindValue(':status', 'aktif');
                $statement->bindValue(':created_at', $now);
                $statement->bindValue(':updated_at', $now);

                if (!$statement->execute()) {
                    throw new RuntimeException('Gagal menyimpan data jurusan: ' . $code);
                }

                $this->storeLegacyMapping('majors', $id, $id);
                $inserted++;
            }

            if ($transactionStarted && $this->connection->inTransaction()) {
                $this->connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($transactionStarted && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }

        return [
            'status' => 'success',
            'imported' => $inserted,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function migrateTeachers(bool $dryRun = false): array
    {
        $legacyTable = self::LEGACY_PREFIX . 'm_guru';

        if (!$this->tableExists($legacyTable)) {
            return [
                'status' => 'skipped',
                'message' => 'Tabel legacy guru belum diimport.',
            ];
        }

        $statement = $this->connection->query(sprintf('SELECT * FROM `%s` ORDER BY id ASC', $legacyTable));

        if ($statement === false) {
            throw new RuntimeException('Gagal membaca data guru legacy.');
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [
                'status' => 'skipped',
                'message' => 'Tidak ada data guru legacy yang ditemukan.',
            ];
        }

        $inserted = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');
        $transactionStarted = false;

        if (!$dryRun && !$this->connection->inTransaction()) {
            $transactionStarted = $this->connection->beginTransaction();
        }

        try {
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $name = trim((string) ($row['nama'] ?? ''));

                if ($id <= 0 || $name === '') {
                    $skipped++;
                    continue;
                }

                if ($this->getLegacyMapping('teachers', $id) !== null) {
                    $skipped++;
                    continue;
                }

                $existingTeacher = $this->findTeacherById($id);
                if ($existingTeacher !== null) {
                    $this->storeLegacyMapping('teachers', $id, (int) ($existingTeacher['id'] ?? 0));
                    $skipped++;
                    continue;
                }

                $nip = trim((string) ($row['nip'] ?? ''));
                $gender = $this->normalizeGender($row['jk'] ?? null);
                $status = (string) ($row['stat_data'] ?? '');
                $teacherStatus = $status === 'A' ? 'aktif' : 'nonaktif';

                if ($dryRun) {
                    $inserted++;
                    continue;
                }

                $sql = <<<SQL
INSERT INTO guru (
    id, nama, nip, jenis_kelamin, status, created_at, updated_at
) VALUES (
    :id, :nama, :nip, :jk, :status, :created_at, :updated_at
)
SQL;

                $statement = $this->connection->prepare($sql);

                if ($statement === false) {
                    throw new RuntimeException('Gagal mempersiapkan penyimpanan data guru.');
                }

                $statement->bindValue(':id', $id, PDO::PARAM_INT);
                $statement->bindValue(':nama', $name);

                if ($nip === '') {
                    $statement->bindValue(':nip', null, PDO::PARAM_NULL);
                } else {
                    $statement->bindValue(':nip', $nip);
                }

                if ($gender === null) {
                    $statement->bindValue(':jk', null, PDO::PARAM_NULL);
                } else {
                    $statement->bindValue(':jk', $gender);
                }

                $statement->bindValue(':status', $teacherStatus);
                $statement->bindValue(':created_at', $now);
                $statement->bindValue(':updated_at', $now);

                if (!$statement->execute()) {
                    throw new RuntimeException('Gagal menyimpan data guru: ' . $name);
                }

                $this->storeLegacyMapping('teachers', $id, $id);
                $inserted++;
            }

            if ($transactionStarted && $this->connection->inTransaction()) {
                $this->connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($transactionStarted && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }

        return [
            'status' => 'success',
            'imported' => $inserted,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function migrateSchoolYears(bool $dryRun = false): array
    {
        $legacyTable = self::LEGACY_PREFIX . 'tahun';

        if (!$this->tableExists($legacyTable)) {
            return [
                'status' => 'skipped',
                'message' => 'Tabel legacy tahun ajaran belum diimport.',
            ];
        }

        $statement = $this->connection->query(sprintf('SELECT * FROM `%s` ORDER BY id ASC', $legacyTable));

        if ($statement === false) {
            throw new RuntimeException('Gagal membaca data tahun ajaran legacy.');
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [
                'status' => 'skipped',
                'message' => 'Tidak ada data tahun ajaran legacy yang ditemukan.',
            ];
        }

        $inserted = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');
        $transactionStarted = false;

        if (!$dryRun && !$this->connection->inTransaction()) {
            $transactionStarted = $this->connection->beginTransaction();
        }

        try {
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $code = trim((string) ($row['tahun'] ?? ''));

                if ($id <= 0 || $code === '') {
                    $skipped++;
                    continue;
                }

                if ($this->getLegacyMapping('school_years', $id) !== null) {
                    $skipped++;
                    continue;
                }

                $existingYear = $this->findSchoolYearByIdOrCode($id, $code);
                if ($existingYear !== null) {
                    $this->storeLegacyMapping('school_years', $id, (int) ($existingYear['id'] ?? 0));
                    $skipped++;
                    continue;
                }

                $semester = $this->resolveSemester($code);
                $yearStart = $this->resolveAcademicYearStart($code);
                $name = sprintf('%d/%d', $yearStart, $yearStart + 1);

                $reportDate = $this->normalizeDate($row['tgl_raport'] ?? null);
                $reportGrade12 = $this->normalizeDate($row['tgl_raport_kelas3'] ?? null);
                $midterm = $this->normalizeDate($row['tgl_raport_uts'] ?? null);

                if ($reportDate !== null) {
                    $endDate = $reportDate;

                    $startDate = (new DateTimeImmutable($reportDate))
                        ->sub(new DateInterval('P5M'))
                        ->format('Y-m-d');
                } else {
                    if ($semester === 1) {
                        $startDate = sprintf('%d-07-01', $yearStart);
                        $endDate = sprintf('%d-12-31', $yearStart);
                    } else {
                        $startDate = sprintf('%d-01-01', $yearStart + 1);
                        $endDate = sprintf('%d-06-30', $yearStart + 1);
                    }
                }

                $isActive = (string) ($row['aktif'] ?? '') === 'Y';
                $digitalEnabled = (string) ($row['ttd_digital_enabled'] ?? '') === 'Y';
                $digitalEnabledAt = $this->normalizeDateTime($row['ttd_digital_updated_at'] ?? null);
                $headmasterId = (int) ($row['idKepsek'] ?? 0);

                $headmasterExists = $headmasterId > 0 && $this->recordExists('guru', 'id', $headmasterId);
                $headmasterReference = $headmasterExists ? $headmasterId : null;

                if ($dryRun) {
                    $inserted++;
                    continue;
                }

                $sql = <<<SQL
INSERT INTO tahun_ajaran (
    id,
    kode,
    nama,
    tanggal_mulai,
    tanggal_selesai,
    status,
    semester_aktif,
    tanggal_raport_tingkat_10_11,
    tanggal_raport_tingkat_12,
    tanggal_raport_tengah_semester,
    kepala_sekolah_id,
    digital_signature_enabled,
    digital_signature_enabled_at,
    created_at,
    updated_at
) VALUES (
    :id,
    :kode,
    :nama,
    :tanggal_mulai,
    :tanggal_selesai,
    :status,
    :semester,
    :raport_10_11,
    :raport_12,
    :raport_tengah,
    :kepala_sekolah_id,
    :digital_enabled,
    :digital_enabled_at,
    :created_at,
    :updated_at
)
SQL;

                $statement = $this->connection->prepare($sql);

                if ($statement === false) {
                    throw new RuntimeException('Gagal menyiapkan penyimpanan data tahun ajaran.');
                }

                $statement->bindValue(':id', $id, PDO::PARAM_INT);
                $statement->bindValue(':kode', $code);
                $statement->bindValue(':nama', $name);
                $statement->bindValue(':tanggal_mulai', $startDate);
                $statement->bindValue(':tanggal_selesai', $endDate);
                $statement->bindValue(':status', $isActive ? 'aktif' : 'nonaktif');
                $statement->bindValue(':semester', $semester, PDO::PARAM_INT);
                $statement->bindValue(':raport_10_11', $reportDate);
                $statement->bindValue(':raport_12', $reportGrade12);
                $statement->bindValue(':raport_tengah', $midterm);

                if ($headmasterReference === null) {
                    $statement->bindValue(':kepala_sekolah_id', null, PDO::PARAM_NULL);
                } else {
                    $statement->bindValue(':kepala_sekolah_id', $headmasterReference, PDO::PARAM_INT);
                }

                $statement->bindValue(':digital_enabled', $digitalEnabled ? 1 : 0, PDO::PARAM_INT);

                if ($digitalEnabledAt === null) {
                    $statement->bindValue(':digital_enabled_at', null, PDO::PARAM_NULL);
                } else {
                    $statement->bindValue(':digital_enabled_at', $digitalEnabledAt);
                }

                $statement->bindValue(':created_at', $now);
                $statement->bindValue(':updated_at', $now);

                if (!$statement->execute()) {
                    throw new RuntimeException('Gagal menyimpan data tahun ajaran: ' . $code);
                }

                $this->storeLegacyMapping('school_years', $id, $id);
                $inserted++;
            }

            if ($transactionStarted && $this->connection->inTransaction()) {
                $this->connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($transactionStarted && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }

        return [
            'status' => 'success',
            'imported' => $inserted,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function migrateClasses(bool $dryRun = false): array
    {
        $legacyTable = self::LEGACY_PREFIX . 'm_kelas';

        if (!$this->tableExists($legacyTable)) {
            return [
                'status' => 'skipped',
                'message' => 'Tabel legacy kelas belum diimport.',
            ];
        }

        $statement = $this->connection->query(sprintf('SELECT * FROM `%s` ORDER BY id ASC', $legacyTable));

        if ($statement === false) {
            throw new RuntimeException('Gagal membaca data kelas legacy.');
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [
                'status' => 'skipped',
                'message' => 'Tidak ada data kelas legacy yang ditemukan.',
            ];
        }

        $activeYear = $this->activeSchoolYear();

        if ($activeYear === null) {
            return [
                'status' => 'skipped',
                'message' => 'Tidak ada tahun ajaran aktif pada ' . config('app.name', 'Aplikasi Sekolah') . '.',
            ];
        }

        $activeYearId = (int) ($activeYear['id'] ?? 0);
        if ($activeYearId <= 0) {
            return [
                'status' => 'skipped',
                'message' => 'Tahun ajaran aktif tidak valid.',
            ];
        }

        $inserted = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');
        $transactionStarted = false;

        if (!$dryRun && !$this->connection->inTransaction()) {
            $transactionStarted = $this->connection->beginTransaction();
        }

        try {
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $name = trim((string) ($row['nama'] ?? ''));
                $level = $this->resolveClassLevel($row);
                $majorCode = strtoupper(trim((string) ($row['jurusan'] ?? '')));
                $majorId = $majorCode !== '' ? $this->findMajorIdByCode($majorCode) : null;

                if ($id <= 0 || $name === '' || $level <= 0) {
                    $skipped++;
                    continue;
                }

                if ($majorCode !== '' && $majorId === null) {
                    $skipped++;
                    continue;
                }

                if ($this->getLegacyMapping('classes', $id) !== null) {
                    $skipped++;
                    continue;
                }

                $existingById = $this->findClassByPrimaryKey($id);
                if ($existingById !== null) {
                    $this->storeLegacyMapping('classes', $id, (int) ($existingById['id'] ?? 0));
                    $skipped++;
                    continue;
                }

                $existingByAttributes = $this->findClassRecord($activeYearId, $name, $majorId);
                if ($existingByAttributes !== null) {
                    $this->storeLegacyMapping('classes', $id, (int) ($existingByAttributes['id'] ?? 0));
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $inserted++;
                    continue;
                }

                $sql = <<<SQL
INSERT INTO kelas (
    tahun_ajaran_id, jurusan_id, tingkat, nama, wali_kelas_id, created_at, updated_at
) VALUES (
    :tahun_ajaran_id, :jurusan_id, :tingkat, :nama, NULL, :created_at, :updated_at
)
SQL;

                $insertStatement = $this->connection->prepare($sql);

                if ($insertStatement === false) {
                    throw new RuntimeException('Gagal mempersiapkan penyimpanan data kelas.');
                }

                $insertStatement->bindValue(':tahun_ajaran_id', $activeYearId, PDO::PARAM_INT);
                if ($majorId === null) {
                    $insertStatement->bindValue(':jurusan_id', null, PDO::PARAM_NULL);
                } else {
                    $insertStatement->bindValue(':jurusan_id', $majorId, PDO::PARAM_INT);
                }
                $insertStatement->bindValue(':tingkat', $level, PDO::PARAM_INT);
                $insertStatement->bindValue(':nama', $name);
                $insertStatement->bindValue(':created_at', $now);
                $insertStatement->bindValue(':updated_at', $now);

                if (!$insertStatement->execute()) {
                    throw new RuntimeException('Gagal menyimpan data kelas: ' . $name);
                }

                $newId = (int) $this->connection->lastInsertId();
                if ($newId > 0) {
                    $this->storeLegacyMapping('classes', $id, $newId);
                }

                $inserted++;
            }

            if ($transactionStarted && $this->connection->inTransaction()) {
                $this->connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($transactionStarted && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }

        return [
            'status' => 'success',
            'imported' => $inserted,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
            'target_year' => $activeYear['nama'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function migrateSubjects(bool $dryRun = false): array
    {
        $legacyTable = self::LEGACY_PREFIX . 'm_mapel';

        if (!$this->tableExists($legacyTable)) {
            return [
                'status' => 'skipped',
                'message' => 'Tabel legacy mata pelajaran belum diimport.',
            ];
        }

        $statement = $this->connection->query(sprintf('SELECT * FROM `%s` ORDER BY id ASC', $legacyTable));

        if ($statement === false) {
            throw new RuntimeException('Gagal membaca data mata pelajaran legacy.');
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [
                'status' => 'skipped',
                'message' => 'Tidak ada data mata pelajaran legacy yang ditemukan.',
            ];
        }

        $activeYear = $this->activeSchoolYear();
        if ($activeYear === null) {
            return [
                'status' => 'skipped',
                'message' => 'Tidak ada tahun ajaran aktif pada ' . config('app.name', 'Aplikasi Sekolah') . '.',
            ];
        }

        $activeYearId = (int) ($activeYear['id'] ?? 0);
        if ($activeYearId <= 0) {
            return [
                'status' => 'skipped',
                'message' => 'Tahun ajaran aktif tidak valid.',
            ];
        }

        $allowedGroups = array_map(
            static fn (array $group): string => (string) $group['code'],
            Subject::GROUPS
        );
        $majorRequiredGroups = Subject::MAJOR_REQUIRED_TYPES;

        $inserted = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');

        if (!$dryRun) {
            $this->connection->beginTransaction();
        }

        try {
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $code = strtoupper(trim((string) ($row['kd_singkat'] ?? '')));
                $name = trim((string) ($row['nama'] ?? ''));
                $group = strtoupper(trim((string) ($row['kelompok'] ?? '')));
                $subGroup = strtoupper(trim((string) ($row['tambahan_sub'] ?? '')));

                if ($id <= 0 || $code === '' || $name === '') {
                    $skipped++;
                    continue;
                }

                if ($group === '' || !in_array($group, $allowedGroups, true)) {
                    $skipped++;
                    continue;
                }

                $majorId = null;
                if ($subGroup !== '') {
                    $majorId = $this->findMajorIdByCode($subGroup);
                }

                if (in_array($group, $majorRequiredGroups, true)) {
                    if ($majorId === null) {
                        $skipped++;
                        continue;
                    }
                }

                if ($this->getLegacyMapping('subjects', $id) !== null) {
                    $skipped++;
                    continue;
                }

                $existingById = $this->findSubjectByPrimaryKey($id);
                if ($existingById !== null) {
                    $this->storeLegacyMapping('subjects', $id, (int) ($existingById['id'] ?? 0));
                    $skipped++;
                    continue;
                }

                $existingByCode = $this->findSubjectRecord($activeYearId, $code);
                if ($existingByCode !== null) {
                    $this->storeLegacyMapping('subjects', $id, (int) ($existingByCode['id'] ?? 0));
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $inserted++;
                    continue;
                }

                $sql = <<<SQL
INSERT INTO mata_pelajaran (
    tahun_ajaran_id, kode, nama, jenis, jurusan_id, deskripsi, created_at, updated_at
) VALUES (
    :tahun_ajaran_id, :kode, :nama, :jenis, :jurusan_id, NULL, :created_at, :updated_at
)
SQL;

                $insertStatement = $this->connection->prepare($sql);

                if ($insertStatement === false) {
                    throw new RuntimeException('Gagal mempersiapkan penyimpanan data mata pelajaran.');
                }

                $insertStatement->bindValue(':tahun_ajaran_id', $activeYearId, PDO::PARAM_INT);
                $insertStatement->bindValue(':kode', $code);
                $insertStatement->bindValue(':nama', $name);
                $insertStatement->bindValue(':jenis', $group);
                if ($majorId === null) {
                    $insertStatement->bindValue(':jurusan_id', null, PDO::PARAM_NULL);
                } else {
                    $insertStatement->bindValue(':jurusan_id', $majorId, PDO::PARAM_INT);
                }
                $insertStatement->bindValue(':created_at', $now);
                $insertStatement->bindValue(':updated_at', $now);

                if (!$insertStatement->execute()) {
                    throw new RuntimeException('Gagal menyimpan data mata pelajaran: ' . $name);
                }

                $newId = (int) $this->connection->lastInsertId();
                if ($newId > 0) {
                    $this->storeLegacyMapping('subjects', $id, $newId);
                }

                $inserted++;
            }

            if (!$dryRun) {
                $this->connection->commit();
            }
        } catch (\Throwable $exception) {
            if (!$dryRun) {
                $this->connection->rollBack();
            }

            throw $exception;
        }

        return [
            'status' => 'success',
            'imported' => $inserted,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
            'target_year' => $activeYear['nama'] ?? null,
        ];
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->connection->prepare('SHOW TABLES LIKE :table');

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':table', $table);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    private function recordExists(string $table, string $column, mixed $value): bool
    {
        $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = :value', $table, $column);
        $statement = $this->connection->prepare($sql);

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':value', $value);
        $statement->execute();

        $count = $statement->fetchColumn();

        return $count !== false && (int) $count > 0;
    }

    private function ensureLegacyMappingTable(): void
    {
        if ($this->legacyMappingTableEnsured) {
            return;
        }

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS legacy_id_map (
    dataset VARCHAR(64) NOT NULL,
    legacy_id INT NOT NULL,
    new_id INT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_dataset_legacy (dataset, legacy_id),
    KEY idx_dataset_new_id (dataset, new_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;

        $this->connection->exec($sql);
        $this->legacyMappingTableEnsured = true;
    }

    private function storeLegacyMapping(string $dataset, int $legacyId, int $newId): void
    {
        if ($legacyId <= 0 || $newId <= 0) {
            return;
        }

        $this->ensureLegacyMappingTable();

        if (!isset($this->legacyMappingCache[$dataset])) {
            $this->legacyMappingCache[$dataset] = [];
        }

        $this->legacyMappingCache[$dataset][$legacyId] = $newId;

        $sql = <<<SQL
INSERT INTO legacy_id_map (dataset, legacy_id, new_id)
VALUES (:dataset, :legacy_id, :new_id)
ON DUPLICATE KEY UPDATE new_id = VALUES(new_id)
SQL;

        $statement = $this->connection->prepare($sql);

        if ($statement === false) {
            return;
        }

        $statement->bindValue(':dataset', $dataset);
        $statement->bindValue(':legacy_id', $legacyId, PDO::PARAM_INT);
        $statement->bindValue(':new_id', $newId, PDO::PARAM_INT);
        $statement->execute();
    }

    private function getLegacyMapping(string $dataset, int $legacyId): ?int
    {
        if ($legacyId <= 0) {
            return null;
        }

        if (isset($this->legacyMappingCache[$dataset][$legacyId])) {
            return $this->legacyMappingCache[$dataset][$legacyId];
        }

        $this->ensureLegacyMappingTable();

        $sql = 'SELECT new_id FROM legacy_id_map WHERE dataset = :dataset AND legacy_id = :legacy_id LIMIT 1';
        $statement = $this->connection->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':dataset', $dataset);
        $statement->bindValue(':legacy_id', $legacyId, PDO::PARAM_INT);
        $statement->execute();

        $newId = $statement->fetchColumn();

        if ($newId === false) {
            return null;
        }

        if (!isset($this->legacyMappingCache[$dataset])) {
            $this->legacyMappingCache[$dataset] = [];
        }

        $this->legacyMappingCache[$dataset][$legacyId] = (int) $newId;

        return $this->legacyMappingCache[$dataset][$legacyId];
    }

    private function findClassRecord(int $schoolYearId, string $name, ?int $majorId): ?array
    {
        $sql = 'SELECT * FROM kelas WHERE tahun_ajaran_id = :year AND nama = :name';

        if ($majorId === null) {
            $sql .= ' AND jurusan_id IS NULL';
        } else {
            $sql .= ' AND jurusan_id = :major';
        }

        $sql .= ' LIMIT 1';

        $statement = $this->connection->prepare($sql);
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':name', $name);

        if ($majorId !== null) {
            $statement->bindValue(':major', $majorId, PDO::PARAM_INT);
        }

        $statement->execute();
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function findClassByPrimaryKey(int $id): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM kelas WHERE id = :id LIMIT 1');
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function findSubjectRecord(int $schoolYearId, string $code): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM mata_pelajaran WHERE tahun_ajaran_id = :year AND kode = :code LIMIT 1');
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':code', $code);
        $statement->execute();

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function findSubjectByPrimaryKey(int $id): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM mata_pelajaran WHERE id = :id LIMIT 1');
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveClassLevel(array $row): int
    {
        $level = (int) ($row['tingkat'] ?? 0);

        if ($level > 0) {
            return $level;
        }

        $name = (string) ($row['nama'] ?? '');

        if (preg_match('/\d+/', $name, $matches) === 1) {
            return (int) $matches[0];
        }

        return 0;
    }

    private function findMajorIdByCode(string $code): ?int
    {
        $normalized = strtoupper(trim($code));

        if ($normalized === '') {
            return null;
        }

        if (array_key_exists($normalized, $this->majorCodeCache)) {
            return $this->majorCodeCache[$normalized];
        }

        $statement = $this->connection->prepare('SELECT id FROM jurusan WHERE kode = :kode LIMIT 1');

        if ($statement === false) {
            $this->majorCodeCache[$normalized] = null;

            return null;
        }

        $statement->bindValue(':kode', $normalized);
        $statement->execute();

        $id = $statement->fetchColumn();
        $this->majorCodeCache[$normalized] = $id === false ? null : (int) $id;

        return $this->majorCodeCache[$normalized];
    }

    private function findMajorByIdOrCode(int $id, string $code): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM jurusan WHERE id = :id OR kode = :kode LIMIT 1');
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->bindValue(':kode', $code);
        $statement->execute();

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function findTeacherById(int $id): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM guru WHERE id = :id LIMIT 1');
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function findSchoolYearByIdOrCode(int $id, string $code): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM tahun_ajaran WHERE id = :id OR kode = :kode LIMIT 1');
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->bindValue(':kode', $code);
        $statement->execute();

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeSchoolYear(): ?array
    {
        $statement = $this->connection->query("SELECT * FROM tahun_ajaran WHERE status = 'aktif' ORDER BY tanggal_mulai DESC LIMIT 1");

        if ($statement === false) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        if ($record !== false) {
            return $record;
        }

        $fallback = $this->connection->query('SELECT * FROM tahun_ajaran ORDER BY tanggal_mulai DESC LIMIT 1');

        if ($fallback === false) {
            return null;
        }

        $record = $fallback->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function rewriteLegacyTableNames(string $sql): string
    {
        $replacements = [
            '/CREATE TABLE IF NOT EXISTS `([^`]+)`/i' => 'CREATE TABLE IF NOT EXISTS `' . self::LEGACY_PREFIX . '$1`',
            '/CREATE TABLE `([^`]+)`/i' => 'CREATE TABLE `' . self::LEGACY_PREFIX . '$1`',
            '/INSERT INTO `([^`]+)`/i' => 'INSERT INTO `' . self::LEGACY_PREFIX . '$1`',
            '/ALTER TABLE `([^`]+)`/i' => 'ALTER TABLE `' . self::LEGACY_PREFIX . '$1`',
            '/DROP TABLE IF EXISTS `([^`]+)`/i' => 'DROP TABLE IF EXISTS `' . self::LEGACY_PREFIX . '$1`',
            '/LOCK TABLES `([^`]+)`/i' => 'LOCK TABLES `' . self::LEGACY_PREFIX . '$1`',
            '/REFERENCES `([^`]+)`/i' => 'REFERENCES `' . self::LEGACY_PREFIX . '$1`',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $sql = preg_replace($pattern, $replacement, $sql);
        }

        return $sql;
    }

    /**
     * @return array<int, string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $length = strlen($sql);
        $statements = [];
        $buffer = '';
        $inString = false;
        $stringDelimiter = '';
        $escapeNext = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inString) {
                $buffer .= $char;

                if ($escapeNext) {
                    $escapeNext = false;
                    continue;
                }

                if ($char === '\\') {
                    $escapeNext = true;
                    continue;
                }

                if ($char === $stringDelimiter) {
                    $inString = false;
                    $stringDelimiter = '';
                }

                continue;
            }

            if ($char === '\'' || $char === '"') {
                $inString = true;
                $stringDelimiter = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }

                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    private function normalizeGender(mixed $value): ?string
    {
        $gender = strtoupper(trim((string) $value));

        if ($gender === 'L' || $gender === 'P') {
            return $gender;
        }

        return null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function resolveSemester(string $code): int
    {
        $lastChar = substr($code, -1);

        if ($lastChar === '2') {
            return 2;
        }

        return 1;
    }

    private function resolveAcademicYearStart(string $code): int
    {
        $trimmed = preg_replace('/[^0-9]/', '', substr($code, 0, 4));

        if ($trimmed === '' || !ctype_digit($trimmed)) {
            return (int) date('Y');
        }

        return (int) $trimmed;
    }
}

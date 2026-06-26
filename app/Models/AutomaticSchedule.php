<?php

namespace App\Models;

use Core\Database;
use Core\Model;
use PDO;

class AutomaticSchedule extends Model
{
    protected static ?string $table = 'jadwal_draft';

    public const DAYS = [
        'senin' => 'Senin',
        'selasa' => 'Selasa',
        'rabu' => 'Rabu',
        'kamis' => 'Kamis',
        'jumat' => 'Jumat',
        'sabtu' => 'Sabtu',
    ];

    private static bool $schemaEnsured = false;

    public static function ensureSchema(?PDO $connection = null): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $connection ??= static::connection();

        $connection->exec(
            "CREATE TABLE IF NOT EXISTS jadwal_ruangan (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                kode VARCHAR(32) NOT NULL,
                nama VARCHAR(120) NOT NULL,
                jenis ENUM('kelas','lab','bengkel','lainnya') NOT NULL DEFAULT 'kelas',
                kapasitas SMALLINT UNSIGNED NULL,
                status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY unique_jadwal_ruangan_kode (kode)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $connection->exec(
            "CREATE TABLE IF NOT EXISTS jadwal_hari_aktif (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tahun_ajaran_id INT UNSIGNED NOT NULL,
                hari ENUM('senin','selasa','rabu','kamis','jumat','sabtu','minggu') NOT NULL,
                urutan TINYINT UNSIGNED NOT NULL,
                aktif TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY unique_jadwal_hari_tahun (tahun_ajaran_id, hari),
                KEY idx_jadwal_hari_tahun (tahun_ajaran_id, aktif, urutan),
                CONSTRAINT fk_jadwal_hari_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $connection->exec(
            "CREATE TABLE IF NOT EXISTS jadwal_jam_pelajaran (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tahun_ajaran_id INT UNSIGNED NOT NULL,
                hari ENUM('senin','selasa','rabu','kamis','jumat','sabtu','minggu') NOT NULL,
                jam_ke TINYINT UNSIGNED NOT NULL,
                waktu_mulai TIME NOT NULL,
                waktu_selesai TIME NOT NULL,
                tipe ENUM('pelajaran','istirahat','kegiatan') NOT NULL DEFAULT 'pelajaran',
                label VARCHAR(120) NULL,
                aktif TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY unique_jadwal_jam_tahun_hari (tahun_ajaran_id, hari, jam_ke),
                KEY idx_jadwal_jam_tahun (tahun_ajaran_id, hari, aktif, jam_ke),
                CONSTRAINT fk_jadwal_jam_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $connection->exec(
            "CREATE TABLE IF NOT EXISTS jadwal_kegiatan_tetap (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tahun_ajaran_id INT UNSIGNED NOT NULL,
                hari ENUM('senin','selasa','rabu','kamis','jumat','sabtu','minggu') NOT NULL,
                jam_ke_mulai TINYINT UNSIGNED NOT NULL,
                jam_ke_selesai TINYINT UNSIGNED NOT NULL,
                nama VARCHAR(150) NOT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                KEY idx_jadwal_kegiatan_tahun (tahun_ajaran_id, hari, jam_ke_mulai),
                CONSTRAINT fk_jadwal_kegiatan_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $connection->exec(
            "CREATE TABLE IF NOT EXISTS jadwal_ketersediaan_guru (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tahun_ajaran_id INT UNSIGNED NOT NULL,
                guru_id INT UNSIGNED NOT NULL,
                hari ENUM('senin','selasa','rabu','kamis','jumat','sabtu','minggu') NOT NULL,
                jam_ke TINYINT UNSIGNED NOT NULL,
                status ENUM('tersedia','tidak_tersedia') NOT NULL DEFAULT 'tidak_tersedia',
                catatan VARCHAR(255) NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY unique_jadwal_availability (tahun_ajaran_id, guru_id, hari, jam_ke),
                KEY idx_jadwal_availability_guru (guru_id, tahun_ajaran_id),
                CONSTRAINT fk_jadwal_availability_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
                CONSTRAINT fk_jadwal_availability_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $connection->exec(
            "CREATE TABLE IF NOT EXISTS jadwal_batas_guru (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tahun_ajaran_id INT UNSIGNED NOT NULL,
                guru_id INT UNSIGNED NULL,
                maksimal_jam_per_hari TINYINT UNSIGNED NOT NULL DEFAULT 8,
                maksimal_jam_per_minggu SMALLINT UNSIGNED NOT NULL DEFAULT 40,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                KEY idx_jadwal_batas_tahun_guru (tahun_ajaran_id, guru_id),
                CONSTRAINT fk_jadwal_batas_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
                CONSTRAINT fk_jadwal_batas_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $connection->exec(
            "CREATE TABLE IF NOT EXISTS jadwal_preferensi_generate (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tahun_ajaran_id INT UNSIGNED NOT NULL,
                blok_produktif_min TINYINT UNSIGNED NOT NULL DEFAULT 2,
                blok_produktif_maks TINYINT UNSIGNED NOT NULL DEFAULT 4,
                blok_umum_maks TINYINT UNSIGNED NOT NULL DEFAULT 2,
                maks_mapel_berat_berurutan TINYINT UNSIGNED NOT NULL DEFAULT 2,
                prioritas_praktik_pagi TINYINT(1) NOT NULL DEFAULT 1,
                hindari_mapel_sama_per_hari TINYINT(1) NOT NULL DEFAULT 1,
                sebar_beban_guru TINYINT(1) NOT NULL DEFAULT 1,
                rapatkan_jadwal_kelas TINYINT(1) NOT NULL DEFAULT 1,
                bobot_jam_guru_harian TINYINT UNSIGNED NOT NULL DEFAULT 7,
                bobot_jam_kelas_harian TINYINT UNSIGNED NOT NULL DEFAULT 3,
                penalti_slot_sore_produktif TINYINT UNSIGNED NOT NULL DEFAULT 25,
                penalti_mapel_sama_hari TINYINT UNSIGNED NOT NULL DEFAULT 30,
                penalti_jam_kosong_guru TINYINT UNSIGNED NOT NULL DEFAULT 18,
                penalti_jam_kosong_kelas TINYINT UNSIGNED NOT NULL DEFAULT 15,
                penalti_mapel_berat_berurutan TINYINT UNSIGNED NOT NULL DEFAULT 22,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY unique_jadwal_preferensi_tahun (tahun_ajaran_id),
                CONSTRAINT fk_jadwal_preferensi_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $connection->exec(
            "CREATE TABLE IF NOT EXISTS jadwal_preferensi_waktu (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tahun_ajaran_id INT UNSIGNED NOT NULL,
                jam_masuk TIME NOT NULL DEFAULT '07:00:00',
                durasi_jp_menit TINYINT UNSIGNED NOT NULL DEFAULT 45,
                jeda_jp_menit TINYINT UNSIGNED NOT NULL DEFAULT 0,
                jumlah_jp_per_hari TINYINT UNSIGNED NOT NULL DEFAULT 8,
                istirahat_pertama_setelah_jp TINYINT UNSIGNED NOT NULL DEFAULT 4,
                durasi_istirahat_pertama_menit TINYINT UNSIGNED NOT NULL DEFAULT 15,
                istirahat_dzuhur_setelah_jp TINYINT UNSIGNED NOT NULL DEFAULT 6,
                durasi_istirahat_dzuhur_menit TINYINT UNSIGNED NOT NULL DEFAULT 45,
                durasi_istirahat_jumat_menit TINYINT UNSIGNED NOT NULL DEFAULT 75,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY unique_jadwal_preferensi_waktu_tahun (tahun_ajaran_id),
                CONSTRAINT fk_jadwal_preferensi_waktu_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $connection->exec(
            "CREATE TABLE IF NOT EXISTS jadwal_kelas_paralel (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tahun_ajaran_id INT UNSIGNED NOT NULL,
                guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
                nama VARCHAR(150) NULL,
                kelas_ids_json LONGTEXT NOT NULL,
                aktif TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                KEY idx_jadwal_kelas_paralel_tahun (tahun_ajaran_id, guru_mata_pelajaran_id, aktif),
                CONSTRAINT fk_jadwal_kelas_paralel_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
                CONSTRAINT fk_jadwal_kelas_paralel_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $connection->exec(
            "CREATE TABLE IF NOT EXISTS jadwal_draft (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tahun_ajaran_id INT UNSIGNED NOT NULL,
                semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
                tingkat TINYINT UNSIGNED NULL,
                nama VARCHAR(150) NOT NULL,
                status ENUM('draft','aktif','arsip') NOT NULL DEFAULT 'draft',
                total_item INT UNSIGNED NOT NULL DEFAULT 0,
                total_gagal INT UNSIGNED NOT NULL DEFAULT 0,
                conflict_json LONGTEXT NULL,
                created_by INT UNSIGNED NULL,
                activated_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                KEY idx_jadwal_draft_context (tahun_ajaran_id, semester, tingkat, status),
                CONSTRAINT fk_jadwal_draft_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $connection->exec(
            "CREATE TABLE IF NOT EXISTS jadwal_draft_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                draft_id INT UNSIGNED NOT NULL,
                tahun_ajaran_id INT UNSIGNED NOT NULL,
                semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
                guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
                guru_id INT UNSIGNED NOT NULL,
                kelas_id INT UNSIGNED NOT NULL,
                ruangan_id INT UNSIGNED NULL,
                hari ENUM('senin','selasa','rabu','kamis','jumat','sabtu','minggu') NULL,
                jam_ke_mulai TINYINT UNSIGNED NULL,
                jam_ke_selesai TINYINT UNSIGNED NULL,
                waktu_mulai TIME NULL,
                waktu_selesai TIME NULL,
                jumlah_jam TINYINT UNSIGNED NOT NULL,
                parallel_group_id INT UNSIGNED NULL,
                status ENUM('generated','manual','fixed','failed') NOT NULL DEFAULT 'generated',
                is_locked TINYINT(1) NOT NULL DEFAULT 0,
                catatan VARCHAR(255) NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                KEY idx_jadwal_draft_items_draft (draft_id, hari, jam_ke_mulai),
                KEY idx_jadwal_draft_items_guru (guru_id, hari, jam_ke_mulai),
                KEY idx_jadwal_draft_items_kelas (kelas_id, hari, jam_ke_mulai),
                CONSTRAINT fk_jadwal_draft_items_draft FOREIGN KEY (draft_id) REFERENCES jadwal_draft(id) ON DELETE CASCADE,
                CONSTRAINT fk_jadwal_draft_items_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
                CONSTRAINT fk_jadwal_draft_items_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE,
                CONSTRAINT fk_jadwal_draft_items_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
                CONSTRAINT fk_jadwal_draft_items_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
                CONSTRAINT fk_jadwal_draft_items_parallel FOREIGN KEY (parallel_group_id) REFERENCES jadwal_kelas_paralel(id) ON DELETE SET NULL,
                CONSTRAINT fk_jadwal_draft_items_ruangan FOREIGN KEY (ruangan_id) REFERENCES jadwal_ruangan(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        self::ensureDraftItemColumns($connection);
        self::ensureLessonScheduleColumns($connection);

        self::$schemaEnsured = true;
    }

    public static function seedDefaultSettings(int $schoolYearId, ?PDO $connection = null): void
    {
        if ($schoolYearId <= 0) {
            return;
        }

        $connection ??= static::connection();
        self::ensureSchema($connection);

        $now = date('Y-m-d H:i:s');
        $dayStatement = $connection->prepare(
            'INSERT INTO jadwal_hari_aktif (tahun_ajaran_id, hari, urutan, aktif, created_at, updated_at)
             VALUES (:year_id, :day, :order_no, 1, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE updated_at = updated_at'
        );

        $order = 1;
        foreach (array_keys(self::DAYS) as $day) {
            $dayStatement->execute([
                ':year_id' => $schoolYearId,
                ':day' => $day,
                ':order_no' => $order++,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $dayStatement->closeCursor();
        }

        $periodCount = self::countRows($connection, 'jadwal_jam_pelajaran', 'tahun_ajaran_id = :year_id', [':year_id' => $schoolYearId]);
        if ($periodCount === 0) {
            $insertPeriod = $connection->prepare(
                'INSERT INTO jadwal_jam_pelajaran
                    (tahun_ajaran_id, hari, jam_ke, waktu_mulai, waktu_selesai, tipe, label, aktif, created_at, updated_at)
                 VALUES
                    (:year_id, :day, :lesson_no, :start_time, :end_time, :type, :label, 1, :created_at, :updated_at)'
            );

            foreach (array_keys(self::DAYS) as $day) {
                foreach (self::defaultPeriodsForDay($day) as $period) {
                    $insertPeriod->execute([
                        ':year_id' => $schoolYearId,
                        ':day' => $day,
                        ':lesson_no' => $period['jam_ke'],
                        ':start_time' => $period['waktu_mulai'],
                        ':end_time' => $period['waktu_selesai'],
                        ':type' => $period['tipe'],
                        ':label' => $period['label'],
                        ':created_at' => $now,
                        ':updated_at' => $now,
                    ]);
                    $insertPeriod->closeCursor();
                }
            }
        }

        $activityCount = self::countRows($connection, 'jadwal_kegiatan_tetap', 'tahun_ajaran_id = :year_id', [':year_id' => $schoolYearId]);
        if ($activityCount === 0) {
            $insertActivity = $connection->prepare(
                'INSERT INTO jadwal_kegiatan_tetap
                    (tahun_ajaran_id, hari, jam_ke_mulai, jam_ke_selesai, nama, created_at, updated_at)
                 VALUES
                    (:year_id, :day, :start_no, :end_no, :name, :created_at, :updated_at)'
            );

            foreach ([
                ['senin', 1, 1, 'Upacara'],
                ['jumat', 1, 1, 'Sholawat / Istighosah'],
                ['sabtu', 9, 10, 'Pramuka'],
            ] as $activity) {
                $insertActivity->execute([
                    ':year_id' => $schoolYearId,
                    ':day' => $activity[0],
                    ':start_no' => $activity[1],
                    ':end_no' => $activity[2],
                    ':name' => $activity[3],
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
                $insertActivity->closeCursor();
            }
        }

        self::seedDefaultPreferences($schoolYearId, $connection);
        self::seedDefaultTimePreferences($schoolYearId, $connection);
    }

    /**
     * @return array<string, int>
     */
    public static function defaultPreferences(): array
    {
        return [
            'blok_produktif_min' => 2,
            'blok_produktif_maks' => 4,
            'blok_umum_maks' => 2,
            'maks_mapel_berat_berurutan' => 2,
            'prioritas_praktik_pagi' => 1,
            'hindari_mapel_sama_per_hari' => 1,
            'sebar_beban_guru' => 1,
            'rapatkan_jadwal_kelas' => 1,
            'bobot_jam_guru_harian' => 7,
            'bobot_jam_kelas_harian' => 3,
            'penalti_slot_sore_produktif' => 25,
            'penalti_mapel_sama_hari' => 30,
            'penalti_jam_kosong_guru' => 18,
            'penalti_jam_kosong_kelas' => 15,
            'penalti_mapel_berat_berurutan' => 22,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public static function defaultTimePreferences(): array
    {
        return [
            'jam_masuk' => '07:00',
            'durasi_jp_menit' => 45,
            'jeda_jp_menit' => 0,
            'jumlah_jp_per_hari' => 8,
            'istirahat_pertama_setelah_jp' => 4,
            'durasi_istirahat_pertama_menit' => 15,
            'istirahat_dzuhur_setelah_jp' => 6,
            'durasi_istirahat_dzuhur_menit' => 45,
            'durasi_istirahat_jumat_menit' => 75,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public static function timePreferences(int $schoolYearId): array
    {
        if ($schoolYearId <= 0) {
            return self::defaultTimePreferences();
        }

        self::seedDefaultSettings($schoolYearId);

        $statement = static::connection()->prepare(
            'SELECT * FROM jadwal_preferensi_waktu WHERE tahun_ajaran_id = :year_id LIMIT 1'
        );
        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $preferences = self::defaultTimePreferences();
        if ($row === false) {
            return $preferences;
        }

        foreach ($preferences as $key => $value) {
            $preferences[$key] = $row[$key] ?? $value;
        }

        return self::normalizeTimePreferences($preferences);
    }

    /**
     * @param array<string, mixed> $preferences
     */
    public static function saveTimePreferences(int $schoolYearId, array $preferences): bool
    {
        if ($schoolYearId <= 0) {
            return false;
        }

        self::ensureSchema();
        $connection = static::connection();
        $preferences = self::normalizeTimePreferences(array_merge(self::defaultTimePreferences(), $preferences));
        $now = date('Y-m-d H:i:s');

        $connection->beginTransaction();

        try {
            $statement = $connection->prepare(
                'INSERT INTO jadwal_preferensi_waktu
                    (tahun_ajaran_id, jam_masuk, durasi_jp_menit, jeda_jp_menit, jumlah_jp_per_hari,
                     istirahat_pertama_setelah_jp, durasi_istirahat_pertama_menit,
                     istirahat_dzuhur_setelah_jp, durasi_istirahat_dzuhur_menit, durasi_istirahat_jumat_menit,
                     created_at, updated_at)
                 VALUES
                    (:year_id, :start_time, :lesson_duration, :gap_duration, :daily_lessons,
                     :first_break_after, :first_break_duration,
                     :dzuhur_break_after, :dzuhur_break_duration, :friday_break_duration,
                     :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    jam_masuk = VALUES(jam_masuk),
                    durasi_jp_menit = VALUES(durasi_jp_menit),
                    jeda_jp_menit = VALUES(jeda_jp_menit),
                    jumlah_jp_per_hari = VALUES(jumlah_jp_per_hari),
                    istirahat_pertama_setelah_jp = VALUES(istirahat_pertama_setelah_jp),
                    durasi_istirahat_pertama_menit = VALUES(durasi_istirahat_pertama_menit),
                    istirahat_dzuhur_setelah_jp = VALUES(istirahat_dzuhur_setelah_jp),
                    durasi_istirahat_dzuhur_menit = VALUES(durasi_istirahat_dzuhur_menit),
                    durasi_istirahat_jumat_menit = VALUES(durasi_istirahat_jumat_menit),
                    updated_at = VALUES(updated_at)'
            );

            $statement->execute([
                ':year_id' => $schoolYearId,
                ':start_time' => $preferences['jam_masuk'],
                ':lesson_duration' => $preferences['durasi_jp_menit'],
                ':gap_duration' => $preferences['jeda_jp_menit'],
                ':daily_lessons' => $preferences['jumlah_jp_per_hari'],
                ':first_break_after' => $preferences['istirahat_pertama_setelah_jp'],
                ':first_break_duration' => $preferences['durasi_istirahat_pertama_menit'],
                ':dzuhur_break_after' => $preferences['istirahat_dzuhur_setelah_jp'],
                ':dzuhur_break_duration' => $preferences['durasi_istirahat_dzuhur_menit'],
                ':friday_break_duration' => $preferences['durasi_istirahat_jumat_menit'],
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            self::replacePeriodsFromTimePreferences($schoolYearId, $preferences, $connection);
            $connection->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return false;
        }
    }

    /**
     * @return array<string, int>
     */
    public static function preferences(int $schoolYearId): array
    {
        if ($schoolYearId <= 0) {
            return self::defaultPreferences();
        }

        self::seedDefaultSettings($schoolYearId);

        $statement = static::connection()->prepare(
            'SELECT * FROM jadwal_preferensi_generate WHERE tahun_ajaran_id = :year_id LIMIT 1'
        );
        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $preferences = self::defaultPreferences();
        if ($row === false) {
            return $preferences;
        }

        foreach ($preferences as $key => $value) {
            $preferences[$key] = (int) ($row[$key] ?? $value);
        }

        return self::normalizePreferences($preferences);
    }

    /**
     * @param array<string, int> $preferences
     */
    public static function savePreferences(int $schoolYearId, array $preferences): bool
    {
        if ($schoolYearId <= 0) {
            return false;
        }

        self::ensureSchema();
        $preferences = self::normalizePreferences(array_merge(self::defaultPreferences(), $preferences));
        $now = date('Y-m-d H:i:s');

        $statement = static::connection()->prepare(
            'INSERT INTO jadwal_preferensi_generate
                (tahun_ajaran_id, blok_produktif_min, blok_produktif_maks, blok_umum_maks, maks_mapel_berat_berurutan,
                 prioritas_praktik_pagi, hindari_mapel_sama_per_hari, sebar_beban_guru, rapatkan_jadwal_kelas,
                 bobot_jam_guru_harian, bobot_jam_kelas_harian, penalti_slot_sore_produktif, penalti_mapel_sama_hari,
                 penalti_jam_kosong_guru, penalti_jam_kosong_kelas, penalti_mapel_berat_berurutan, created_at, updated_at)
             VALUES
                (:year_id, :productive_min, :productive_max, :general_max, :heavy_max,
                 :prefer_morning, :avoid_same_subject, :spread_teacher, :compact_class,
                 :teacher_weight, :class_weight, :late_productive_penalty, :same_subject_penalty,
                 :teacher_gap_penalty, :class_gap_penalty, :heavy_sequence_penalty, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                blok_produktif_min = VALUES(blok_produktif_min),
                blok_produktif_maks = VALUES(blok_produktif_maks),
                blok_umum_maks = VALUES(blok_umum_maks),
                maks_mapel_berat_berurutan = VALUES(maks_mapel_berat_berurutan),
                prioritas_praktik_pagi = VALUES(prioritas_praktik_pagi),
                hindari_mapel_sama_per_hari = VALUES(hindari_mapel_sama_per_hari),
                sebar_beban_guru = VALUES(sebar_beban_guru),
                rapatkan_jadwal_kelas = VALUES(rapatkan_jadwal_kelas),
                bobot_jam_guru_harian = VALUES(bobot_jam_guru_harian),
                bobot_jam_kelas_harian = VALUES(bobot_jam_kelas_harian),
                penalti_slot_sore_produktif = VALUES(penalti_slot_sore_produktif),
                penalti_mapel_sama_hari = VALUES(penalti_mapel_sama_hari),
                penalti_jam_kosong_guru = VALUES(penalti_jam_kosong_guru),
                penalti_jam_kosong_kelas = VALUES(penalti_jam_kosong_kelas),
                penalti_mapel_berat_berurutan = VALUES(penalti_mapel_berat_berurutan),
                updated_at = VALUES(updated_at)'
        );

        return $statement->execute([
            ':year_id' => $schoolYearId,
            ':productive_min' => $preferences['blok_produktif_min'],
            ':productive_max' => $preferences['blok_produktif_maks'],
            ':general_max' => $preferences['blok_umum_maks'],
            ':heavy_max' => $preferences['maks_mapel_berat_berurutan'],
            ':prefer_morning' => $preferences['prioritas_praktik_pagi'],
            ':avoid_same_subject' => $preferences['hindari_mapel_sama_per_hari'],
            ':spread_teacher' => $preferences['sebar_beban_guru'],
            ':compact_class' => $preferences['rapatkan_jadwal_kelas'],
            ':teacher_weight' => $preferences['bobot_jam_guru_harian'],
            ':class_weight' => $preferences['bobot_jam_kelas_harian'],
            ':late_productive_penalty' => $preferences['penalti_slot_sore_produktif'],
            ':same_subject_penalty' => $preferences['penalti_mapel_sama_hari'],
            ':teacher_gap_penalty' => $preferences['penalti_jam_kosong_guru'],
            ':class_gap_penalty' => $preferences['penalti_jam_kosong_kelas'],
            ':heavy_sequence_penalty' => $preferences['penalti_mapel_berat_berurutan'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function activeDays(int $schoolYearId): array
    {
        self::seedDefaultSettings($schoolYearId);

        $statement = static::connection()->prepare(
            'SELECT * FROM jadwal_hari_aktif WHERE tahun_ajaran_id = :year_id AND aktif = 1 ORDER BY urutan ASC'
        );
        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function periodsByDay(int $schoolYearId): array
    {
        self::seedDefaultSettings($schoolYearId);

        $statement = static::connection()->prepare(
            'SELECT * FROM jadwal_jam_pelajaran WHERE tahun_ajaran_id = :year_id AND aktif = 1 ORDER BY hari ASC, jam_ke ASC'
        );
        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();

        $map = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $day = (string) ($row['hari'] ?? '');
            if ($day === '') {
                continue;
            }
            $map[$day] ??= [];
            $map[$day][(int) ($row['jam_ke'] ?? 0)] = $row;
        }

        return $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function fixedActivities(int $schoolYearId): array
    {
        self::seedDefaultSettings($schoolYearId);

        $statement = static::connection()->prepare(
            'SELECT * FROM jadwal_kegiatan_tetap WHERE tahun_ajaran_id = :year_id ORDER BY hari ASC, jam_ke_mulai ASC'
        );
        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rooms(): array
    {
        self::ensureSchema();

        $statement = static::connection()->query("SELECT * FROM jadwal_ruangan WHERE status = 'aktif' ORDER BY jenis ASC, nama ASC");

        return $statement !== false ? ($statement->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array<int, string>
     */
    public static function roomOptions(): array
    {
        $options = [];
        foreach (self::rooms() as $room) {
            $options[(int) $room['id']] = trim(($room['kode'] ?? '') . ' - ' . ($room['nama'] ?? ''));
        }

        return $options;
    }

    /**
     * @param array<int> $classIds
     * @return array<int, array<string, mixed>>
     */
    public static function parallelClassGroups(int $schoolYearId, ?int $level = null, array $classIds = []): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        self::ensureSchema();
        $allowedClassRows = self::classroomsForContext($schoolYearId, $level, $classIds);
        $allowedClassIds = array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $allowedClassRows)));
        $allowedClassSet = array_fill_keys($allowedClassIds, true);
        $classLabels = [];
        foreach (self::classroomsForContext($schoolYearId) as $classroom) {
            $classId = (int) ($classroom['id'] ?? 0);
            if ($classId <= 0) {
                continue;
            }
            $label = trim('Kelas ' . (string) ($classroom['tingkat'] ?? '-') . ' ' . (string) ($classroom['nama'] ?? '-'));
            if (!empty($classroom['jurusan_nama'])) {
                $label .= ' (' . $classroom['jurusan_nama'] . ')';
            }
            $classLabels[$classId] = $label;
        }

        $statement = static::connection()->prepare(
            'SELECT
                jkp.*,
                g.nama AS guru_nama,
                mp.kode AS mata_pelajaran_kode,
                mp.nama AS mata_pelajaran_nama
             FROM jadwal_kelas_paralel jkp
             JOIN guru_mata_pelajaran gmp ON gmp.id = jkp.guru_mata_pelajaran_id
             JOIN guru g ON g.id = gmp.guru_id
             JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
             WHERE jkp.tahun_ajaran_id = :year_id AND jkp.aktif = 1
             ORDER BY mp.nama ASC, g.nama ASC, jkp.id ASC'
        );
        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();

        $groups = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $decoded = json_decode((string) ($row['kelas_ids_json'] ?? '[]'), true);
            if (!is_array($decoded)) {
                continue;
            }

            $groupClassIds = array_values(array_unique(array_filter(array_map('intval', $decoded))));
            if (!empty($allowedClassSet)) {
                $groupClassIds = array_values(array_filter($groupClassIds, static fn (int $classId): bool => isset($allowedClassSet[$classId])));
            }
            if (count($groupClassIds) < 2) {
                continue;
            }

            $row['kelas_ids'] = $groupClassIds;
            $row['kelas_labels'] = array_values(array_map(
                static fn (int $classId): string => $classLabels[$classId] ?? ('Kelas #' . $classId),
                $groupClassIds
            ));
            $groups[] = $row;
        }

        return $groups;
    }

    /**
     * @param array<int, array{guru_mata_pelajaran_id:int, nama:string, kelas_ids:array<int>}> $groups
     */
    public static function saveParallelClassGroups(int $schoolYearId, array $groups): bool
    {
        if ($schoolYearId <= 0) {
            return false;
        }

        self::ensureSchema();
        $connection = static::connection();
        $connection->beginTransaction();

        try {
            $delete = $connection->prepare('DELETE FROM jadwal_kelas_paralel WHERE tahun_ajaran_id = :year_id');
            $delete->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
            $delete->execute();
            $delete->closeCursor();

            $insert = $connection->prepare(
                'INSERT INTO jadwal_kelas_paralel
                    (tahun_ajaran_id, guru_mata_pelajaran_id, nama, kelas_ids_json, aktif, created_at, updated_at)
                 VALUES
                    (:year_id, :assignment_id, :name, :class_ids_json, 1, :created_at, :updated_at)'
            );
            $now = date('Y-m-d H:i:s');

            foreach ($groups as $group) {
                $assignmentId = (int) ($group['guru_mata_pelajaran_id'] ?? 0);
                $groupClassIds = array_values(array_unique(array_filter(array_map('intval', $group['kelas_ids'] ?? []))));
                if ($assignmentId <= 0 || count($groupClassIds) < 2) {
                    continue;
                }

                $insert->execute([
                    ':year_id' => $schoolYearId,
                    ':assignment_id' => $assignmentId,
                    ':name' => trim((string) ($group['nama'] ?? '')) !== '' ? trim((string) $group['nama']) : null,
                    ':class_ids_json' => json_encode($groupClassIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
                $insert->closeCursor();
            }

            $connection->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return false;
        }
    }

    /**
     * @param array<int> $classIds
     * @return array<int, array<string, mixed>>
     */
    public static function assignmentsForGeneration(int $schoolYearId, ?int $level = null, array $classIds = []): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        self::ensureSchema();
        $connection = static::connection();
        $where = ['mp.tahun_ajaran_id = :year_id', 'k.tahun_ajaran_id = :year_id'];
        $params = [':year_id' => $schoolYearId];
        $classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));

        if ($level !== null && $level > 0) {
            $where[] = 'k.tingkat = :level';
            $params[':level'] = $level;
        }

        if (!empty($classIds)) {
            $placeholders = [];
            foreach ($classIds as $index => $classId) {
                $placeholder = ':class_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $classId;
            }
            $where[] = 'k.id IN (' . implode(', ', $placeholders) . ')';
        }

        $sql = '
            SELECT
                gmp.id AS guru_mata_pelajaran_id,
                gmp.guru_id,
                g.nama AS guru_nama,
                g.nip AS guru_nip,
                mp.id AS mata_pelajaran_id,
                mp.kode AS mata_pelajaran_kode,
                mp.nama AS mata_pelajaran_nama,
                mp.jenis AS mata_pelajaran_jenis,
                mp.jurusan_id AS mata_pelajaran_jurusan_id,
                k.id AS kelas_id,
                k.tingkat AS kelas_tingkat,
                k.nama AS kelas_nama,
                k.jurusan_id AS kelas_jurusan_id,
                j.nama AS jurusan_nama
            FROM guru_mata_pelajaran_kelas gmpk
            JOIN guru_mata_pelajaran gmp ON gmp.id = gmpk.guru_mata_pelajaran_id
            JOIN guru g ON g.id = gmp.guru_id
            JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
            JOIN kelas k ON k.id = gmpk.kelas_id
            LEFT JOIN jurusan j ON j.id = k.jurusan_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY k.tingkat ASC, k.nama ASC, mp.jenis ASC, mp.nama ASC, g.nama ASC';

        $statement = $connection->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int> $classIds
     * @return array<string, int>
     */
    public static function activeHourTargets(int $schoolYearId, int $semester, array $classIds = []): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        self::ensureSchema();
        $semesterFilter = self::lessonScheduleColumnExists('semester') ? ' AND jp.semester = :semester' : '';
        $statusFilter = self::lessonScheduleColumnExists('status_jadwal') ? " AND jp.status_jadwal = 'aktif'" : '';
        $classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        $classFilter = '';
        if (!empty($classIds)) {
            $placeholders = [];
            foreach ($classIds as $index => $classId) {
                $placeholders[] = ':class_' . $index;
            }
            $classFilter = ' AND jp.kelas_id IN (' . implode(', ', $placeholders) . ')';
        }

        $statement = static::connection()->prepare(
            'SELECT jp.guru_mata_pelajaran_id, jp.kelas_id, SUM(jp.jumlah_jam) AS total_jam
             FROM jadwal_pelajaran jp
             WHERE jp.tahun_ajaran_id = :year_id' . $semesterFilter . $statusFilter . $classFilter . '
             GROUP BY jp.guru_mata_pelajaran_id, jp.kelas_id'
        );
        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        if ($semesterFilter !== '') {
            $statement->bindValue(':semester', $semester, PDO::PARAM_INT);
        }
        foreach ($classIds as $index => $classId) {
            $statement->bindValue(':class_' . $index, $classId, PDO::PARAM_INT);
        }
        $statement->execute();

        $targets = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = (int) ($row['guru_mata_pelajaran_id'] ?? 0) . ':' . (int) ($row['kelas_id'] ?? 0);
            $targets[$key] = (int) ($row['total_jam'] ?? 0);
        }

        return $targets;
    }

    /**
     * @param array<int> $classIds
     * @return array<int, array<string, mixed>>
     */
    public static function classroomsForContext(int $schoolYearId, ?int $level = null, array $classIds = []): array
    {
        $where = ['k.tahun_ajaran_id = :year_id'];
        $params = [':year_id' => $schoolYearId];
        $classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));

        if ($level !== null && $level > 0) {
            $where[] = 'k.tingkat = :level';
            $params[':level'] = $level;
        }

        if (!empty($classIds)) {
            $placeholders = [];
            foreach ($classIds as $index => $classId) {
                $placeholder = ':class_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $classId;
            }
            $where[] = 'k.id IN (' . implode(', ', $placeholders) . ')';
        }

        $statement = static::connection()->prepare(
            'SELECT k.*, j.nama AS jurusan_nama
             FROM kelas k
             LEFT JOIN jurusan j ON j.id = k.jurusan_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY k.tingkat ASC, k.nama ASC'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function latestDraft(int $schoolYearId, int $semester, ?int $level = null): ?array
    {
        self::ensureSchema();

        $where = ['tahun_ajaran_id = :year_id', 'semester = :semester'];
        $params = [':year_id' => $schoolYearId, ':semester' => $semester];

        if ($level !== null && $level > 0) {
            $where[] = 'tingkat = :level';
            $params[':level'] = $level;
        } else {
            $where[] = 'tingkat IS NULL';
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM jadwal_draft WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 1'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_INT);
        }
        $statement->execute();

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    public static function findDraft(int $draftId): ?array
    {
        self::ensureSchema();

        $statement = static::connection()->prepare('SELECT * FROM jadwal_draft WHERE id = :id LIMIT 1');
        $statement->bindValue(':id', $draftId, PDO::PARAM_INT);
        $statement->execute();
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function draftItems(int $draftId): array
    {
        self::ensureSchema();

        $statement = static::connection()->prepare(
            'SELECT
                jdi.*,
                g.nama AS guru_nama,
                g.nip AS guru_nip,
                mp.kode AS mata_pelajaran_kode,
                mp.nama AS mata_pelajaran_nama,
                mp.jenis AS mata_pelajaran_jenis,
                k.nama AS kelas_nama,
                k.tingkat AS kelas_tingkat,
                j.nama AS jurusan_nama,
                r.kode AS ruangan_kode,
                r.nama AS ruangan_nama
             FROM jadwal_draft_items jdi
             JOIN guru_mata_pelajaran gmp ON gmp.id = jdi.guru_mata_pelajaran_id
             JOIN guru g ON g.id = jdi.guru_id
             JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
             JOIN kelas k ON k.id = jdi.kelas_id
             LEFT JOIN jurusan j ON j.id = k.jurusan_id
             LEFT JOIN jadwal_ruangan r ON r.id = jdi.ruangan_id
             WHERE jdi.draft_id = :draft_id
             ORDER BY FIELD(jdi.hari, "senin","selasa","rabu","kamis","jumat","sabtu","minggu"), jdi.jam_ke_mulai ASC, k.tingkat ASC, k.nama ASC'
        );
        $statement->bindValue(':draft_id', $draftId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function createDraft(array $attributes): int
    {
        self::ensureSchema();

        return static::createAndReturnId($attributes) ?? 0;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public static function insertDraftItems(array $items): void
    {
        if (empty($items)) {
            return;
        }

        self::ensureSchema();
        $statement = static::connection()->prepare(
            'INSERT INTO jadwal_draft_items
                (draft_id, tahun_ajaran_id, semester, guru_mata_pelajaran_id, guru_id, kelas_id, ruangan_id, hari,
                 jam_ke_mulai, jam_ke_selesai, waktu_mulai, waktu_selesai, jumlah_jam, parallel_group_id, status, is_locked, catatan, created_at, updated_at)
             VALUES
                (:draft_id, :year_id, :semester, :assignment_id, :teacher_id, :class_id, :room_id, :day,
                 :start_no, :end_no, :start_time, :end_time, :lesson_count, :parallel_group_id, :status, :is_locked, :note, :created_at, :updated_at)'
        );

        foreach ($items as $item) {
            $statement->execute([
                ':draft_id' => $item['draft_id'],
                ':year_id' => $item['tahun_ajaran_id'],
                ':semester' => $item['semester'],
                ':assignment_id' => $item['guru_mata_pelajaran_id'],
                ':teacher_id' => $item['guru_id'],
                ':class_id' => $item['kelas_id'],
                ':room_id' => $item['ruangan_id'] ?? null,
                ':day' => $item['hari'] ?? null,
                ':start_no' => $item['jam_ke_mulai'] ?? null,
                ':end_no' => $item['jam_ke_selesai'] ?? null,
                ':start_time' => $item['waktu_mulai'] ?? null,
                ':end_time' => $item['waktu_selesai'] ?? null,
                ':lesson_count' => $item['jumlah_jam'],
                ':parallel_group_id' => !empty($item['parallel_group_id']) ? (int) $item['parallel_group_id'] : null,
                ':status' => $item['status'] ?? 'generated',
                ':is_locked' => !empty($item['is_locked']) ? 1 : 0,
                ':note' => $item['catatan'] ?? null,
                ':created_at' => $item['created_at'] ?? date('Y-m-d H:i:s'),
                ':updated_at' => $item['updated_at'] ?? date('Y-m-d H:i:s'),
            ]);
            $statement->closeCursor();
        }
    }

    public static function updateDraftStats(int $draftId, array $conflicts = []): void
    {
        self::ensureSchema();

        $statement = static::connection()->prepare(
            'SELECT COUNT(*) AS total_item, SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS total_gagal
             FROM jadwal_draft_items WHERE draft_id = :draft_id'
        );
        $statement->bindValue(':draft_id', $draftId, PDO::PARAM_INT);
        $statement->execute();
        $stats = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        static::updateById($draftId, [
            'total_item' => (int) ($stats['total_item'] ?? 0),
            'total_gagal' => (int) ($stats['total_gagal'] ?? 0),
            'conflict_json' => json_encode($conflicts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int> $classIds
     * @return array<int, array<string, mixed>>
     */
    public static function lockedItemsForRegenerate(?int $draftId, int $schoolYearId, int $semester, ?int $level = null, array $classIds = []): array
    {
        self::ensureSchema();
        $classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        $classFilter = '';
        if (!empty($classIds)) {
            $placeholders = [];
            foreach ($classIds as $index => $classId) {
                $placeholders[] = ':locked_class_' . $index;
            }
            $classFilter = ' AND k.id IN (' . implode(', ', $placeholders) . ')';
        }

        if ($draftId !== null && $draftId > 0) {
            $statement = static::connection()->prepare(
                'SELECT
                    jdi.*,
                    mp.kode AS mata_pelajaran_kode,
                    mp.nama AS mata_pelajaran_nama,
                    mp.jenis AS mata_pelajaran_jenis,
                    k.tingkat AS kelas_tingkat
                 FROM jadwal_draft_items jdi
                 JOIN guru_mata_pelajaran gmp ON gmp.id = jdi.guru_mata_pelajaran_id
                 JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
                 JOIN kelas k ON k.id = jdi.kelas_id
                 WHERE jdi.draft_id = :draft_id AND jdi.is_locked = 1 AND jdi.status <> "failed"' . $classFilter
            );
            $statement->bindValue(':draft_id', $draftId, PDO::PARAM_INT);
            foreach ($classIds as $index => $classId) {
                $statement->bindValue(':locked_class_' . $index, $classId, PDO::PARAM_INT);
            }
            $statement->execute();

            return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (!self::lessonScheduleColumnExists('is_locked')) {
            return [];
        }

        $semesterFilter = self::lessonScheduleColumnExists('semester') ? ' AND jp.semester = :semester' : '';
        $statusFilter = self::lessonScheduleColumnExists('status_jadwal') ? " AND jp.status_jadwal = 'aktif'" : '';
        $levelFilter = $level !== null && $level > 0 ? ' AND k.tingkat = :level' : '';

        $statement = static::connection()->prepare(
            'SELECT
                jp.id,
                jp.tahun_ajaran_id,
                COALESCE(jp.semester, :semester_default) AS semester,
                jp.guru_mata_pelajaran_id,
                gmp.guru_id,
                jp.kelas_id,
                jp.ruangan_id,
                jp.hari,
                jp.jam_ke_mulai,
                jp.jam_ke_selesai,
                jp.waktu_mulai,
                jp.waktu_selesai,
                jp.jumlah_jam,
                jp.parallel_group_id,
                jp.is_locked,
                mp.kode AS mata_pelajaran_kode,
                mp.nama AS mata_pelajaran_nama,
                mp.jenis AS mata_pelajaran_jenis,
                k.tingkat AS kelas_tingkat
             FROM jadwal_pelajaran jp
             JOIN guru_mata_pelajaran gmp ON gmp.id = jp.guru_mata_pelajaran_id
             JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
             JOIN kelas k ON k.id = jp.kelas_id
             WHERE jp.tahun_ajaran_id = :year_id
               AND jp.is_locked = 1' . $semesterFilter . $statusFilter . $levelFilter . $classFilter
        );
        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':semester_default', $semester, PDO::PARAM_INT);
        if ($semesterFilter !== '') {
            $statement->bindValue(':semester', $semester, PDO::PARAM_INT);
        }
        if ($levelFilter !== '') {
            $statement->bindValue(':level', $level, PDO::PARAM_INT);
        }
        foreach ($classIds as $index => $classId) {
            $statement->bindValue(':locked_class_' . $index, $classId, PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function updateItem(int $itemId, array $attributes): bool
    {
        self::ensureSchema();
        $attributes['updated_at'] = date('Y-m-d H:i:s');

        $columns = array_keys($attributes);
        $assignments = array_map(static fn (string $column): string => $column . ' = :' . $column, $columns);
        $statement = static::connection()->prepare(
            'UPDATE jadwal_draft_items SET ' . implode(', ', $assignments) . ' WHERE id = :id LIMIT 1'
        );
        foreach ($attributes as $column => $value) {
            $statement->bindValue(':' . $column, $value);
        }
        $statement->bindValue(':id', $itemId, PDO::PARAM_INT);

        return $statement->execute();
    }

    public static function deleteItem(int $itemId): bool
    {
        self::ensureSchema();

        $statement = static::connection()->prepare('DELETE FROM jadwal_draft_items WHERE id = :id LIMIT 1');
        $statement->bindValue(':id', $itemId, PDO::PARAM_INT);

        return $statement->execute();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function teacherAvailabilityBlocks(int $schoolYearId): array
    {
        self::ensureSchema();

        $statement = static::connection()->prepare(
            "SELECT * FROM jadwal_ketersediaan_guru
             WHERE tahun_ajaran_id = :year_id AND status = 'tidak_tersedia'"
        );
        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int|string, array{daily: int, weekly: int}>
     */
    public static function teacherLimits(int $schoolYearId): array
    {
        self::ensureSchema();

        $statement = static::connection()->prepare('SELECT * FROM jadwal_batas_guru WHERE tahun_ajaran_id = :year_id');
        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();

        $limits = ['default' => ['daily' => 8, 'weekly' => 40]];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $teacherId = (int) ($row['guru_id'] ?? 0);
            $key = $teacherId > 0 ? $teacherId : 'default';
            $limits[$key] = [
                'daily' => max(1, (int) ($row['maksimal_jam_per_hari'] ?? 8)),
                'weekly' => max(1, (int) ($row['maksimal_jam_per_minggu'] ?? 40)),
            ];
        }

        return $limits;
    }

    /**
     * @param array<int> $classIds
     */
    public static function activateDraft(int $draftId, array $classIds): bool
    {
        self::ensureSchema();

        $draft = self::findDraft($draftId);
        if ($draft === null) {
            return false;
        }

        $items = array_values(array_filter(
            self::draftItems($draftId),
            static fn (array $item): bool => ($item['status'] ?? '') !== 'failed' && !empty($item['hari']) && (int) ($item['jam_ke_mulai'] ?? 0) > 0
        ));

        if (empty($items)) {
            return false;
        }

        $classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        if (empty($classIds)) {
            $classIds = array_values(array_unique(array_map(static fn (array $item): int => (int) $item['kelas_id'], $items)));
        }

        $connection = static::connection();
        $connection->beginTransaction();

        try {
            $yearId = (int) $draft['tahun_ajaran_id'];
            $semester = (int) $draft['semester'];
            $placeholders = [];
            foreach ($classIds as $index => $classId) {
                $placeholders[] = ':class_' . $index;
            }

            $archiveSql = 'UPDATE jadwal_pelajaran SET status_jadwal = "arsip", updated_at = :updated_at
                           WHERE tahun_ajaran_id = :year_id AND semester = :semester AND status_jadwal = "aktif"
                             AND kelas_id IN (' . implode(', ', $placeholders) . ')';
            $archive = $connection->prepare($archiveSql);
            $archive->bindValue(':updated_at', date('Y-m-d H:i:s'));
            $archive->bindValue(':year_id', $yearId, PDO::PARAM_INT);
            $archive->bindValue(':semester', $semester, PDO::PARAM_INT);
            foreach ($classIds as $index => $classId) {
                $archive->bindValue(':class_' . $index, $classId, PDO::PARAM_INT);
            }
            $archive->execute();

            $insert = $connection->prepare(
                'INSERT INTO jadwal_pelajaran
                    (tahun_ajaran_id, semester, draft_id, guru_mata_pelajaran_id, kelas_id, hari, waktu_mulai, waktu_selesai,
                     jumlah_jam, jam_ke_mulai, jam_ke_selesai, ruangan_id, parallel_group_id, status_jadwal, is_locked, sumber, created_at, updated_at)
                 VALUES
                    (:year_id, :semester, :draft_id, :assignment_id, :class_id, :day, :start_time, :end_time,
                     :lesson_count, :start_no, :end_no, :room_id, :parallel_group_id, "aktif", :is_locked, :source, :created_at, :updated_at)'
            );

            $now = date('Y-m-d H:i:s');
            foreach ($items as $item) {
                $insert->execute([
                    ':year_id' => $yearId,
                    ':semester' => $semester,
                    ':draft_id' => $draftId,
                    ':assignment_id' => (int) $item['guru_mata_pelajaran_id'],
                    ':class_id' => (int) $item['kelas_id'],
                    ':day' => $item['hari'],
                    ':start_time' => $item['waktu_mulai'],
                    ':end_time' => $item['waktu_selesai'],
                    ':lesson_count' => (int) $item['jumlah_jam'],
                    ':start_no' => (int) $item['jam_ke_mulai'],
                    ':end_no' => (int) $item['jam_ke_selesai'],
                    ':room_id' => $item['ruangan_id'] !== null ? (int) $item['ruangan_id'] : null,
                    ':parallel_group_id' => !empty($item['parallel_group_id']) ? (int) $item['parallel_group_id'] : null,
                    ':is_locked' => !empty($item['is_locked']) ? 1 : 0,
                    ':source' => in_array((string) ($item['status'] ?? ''), ['manual', 'fixed'], true) ? 'manual' : 'generate',
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
                $insert->closeCursor();
            }

            $connection->prepare(
                'UPDATE jadwal_draft SET status = "arsip", updated_at = :updated_at
                 WHERE tahun_ajaran_id = :year_id AND semester = :semester AND id <> :draft_id AND status = "aktif"'
            )->execute([
                ':updated_at' => $now,
                ':year_id' => $yearId,
                ':semester' => $semester,
                ':draft_id' => $draftId,
            ]);

            static::updateById($draftId, [
                'status' => 'aktif',
                'activated_at' => $now,
                'updated_at' => $now,
            ]);

            $connection->commit();

            return true;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public static function lessonScheduleColumnExists(string $column): bool
    {
        $connection = Database::connection();
        $statement = $connection->query('SHOW COLUMNS FROM jadwal_pelajaran LIKE ' . $connection->quote($column));

        return $statement !== false && $statement->fetch(PDO::FETCH_ASSOC) !== false;
    }

    private static function seedDefaultPreferences(int $schoolYearId, PDO $connection): void
    {
        $defaults = self::defaultPreferences();
        $now = date('Y-m-d H:i:s');
        $statement = $connection->prepare(
            'INSERT INTO jadwal_preferensi_generate
                (tahun_ajaran_id, blok_produktif_min, blok_produktif_maks, blok_umum_maks, maks_mapel_berat_berurutan,
                 prioritas_praktik_pagi, hindari_mapel_sama_per_hari, sebar_beban_guru, rapatkan_jadwal_kelas,
                 bobot_jam_guru_harian, bobot_jam_kelas_harian, penalti_slot_sore_produktif, penalti_mapel_sama_hari,
                 penalti_jam_kosong_guru, penalti_jam_kosong_kelas, penalti_mapel_berat_berurutan, created_at, updated_at)
             VALUES
                (:year_id, :productive_min, :productive_max, :general_max, :heavy_max,
                 :prefer_morning, :avoid_same_subject, :spread_teacher, :compact_class,
                 :teacher_weight, :class_weight, :late_productive_penalty, :same_subject_penalty,
                 :teacher_gap_penalty, :class_gap_penalty, :heavy_sequence_penalty, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE updated_at = updated_at'
        );
        $statement->execute([
            ':year_id' => $schoolYearId,
            ':productive_min' => $defaults['blok_produktif_min'],
            ':productive_max' => $defaults['blok_produktif_maks'],
            ':general_max' => $defaults['blok_umum_maks'],
            ':heavy_max' => $defaults['maks_mapel_berat_berurutan'],
            ':prefer_morning' => $defaults['prioritas_praktik_pagi'],
            ':avoid_same_subject' => $defaults['hindari_mapel_sama_per_hari'],
            ':spread_teacher' => $defaults['sebar_beban_guru'],
            ':compact_class' => $defaults['rapatkan_jadwal_kelas'],
            ':teacher_weight' => $defaults['bobot_jam_guru_harian'],
            ':class_weight' => $defaults['bobot_jam_kelas_harian'],
            ':late_productive_penalty' => $defaults['penalti_slot_sore_produktif'],
            ':same_subject_penalty' => $defaults['penalti_mapel_sama_hari'],
            ':teacher_gap_penalty' => $defaults['penalti_jam_kosong_guru'],
            ':class_gap_penalty' => $defaults['penalti_jam_kosong_kelas'],
            ':heavy_sequence_penalty' => $defaults['penalti_mapel_berat_berurutan'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $statement->closeCursor();
    }

    private static function seedDefaultTimePreferences(int $schoolYearId, PDO $connection): void
    {
        $defaults = self::normalizeTimePreferences(self::defaultTimePreferences());
        $now = date('Y-m-d H:i:s');
        $statement = $connection->prepare(
            'INSERT INTO jadwal_preferensi_waktu
                (tahun_ajaran_id, jam_masuk, durasi_jp_menit, jeda_jp_menit, jumlah_jp_per_hari,
                 istirahat_pertama_setelah_jp, durasi_istirahat_pertama_menit,
                 istirahat_dzuhur_setelah_jp, durasi_istirahat_dzuhur_menit, durasi_istirahat_jumat_menit,
                 created_at, updated_at)
             VALUES
                (:year_id, :start_time, :lesson_duration, :gap_duration, :daily_lessons,
                 :first_break_after, :first_break_duration,
                 :dzuhur_break_after, :dzuhur_break_duration, :friday_break_duration,
                 :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE updated_at = updated_at'
        );
        $statement->execute([
            ':year_id' => $schoolYearId,
            ':start_time' => $defaults['jam_masuk'],
            ':lesson_duration' => $defaults['durasi_jp_menit'],
            ':gap_duration' => $defaults['jeda_jp_menit'],
            ':daily_lessons' => $defaults['jumlah_jp_per_hari'],
            ':first_break_after' => $defaults['istirahat_pertama_setelah_jp'],
            ':first_break_duration' => $defaults['durasi_istirahat_pertama_menit'],
            ':dzuhur_break_after' => $defaults['istirahat_dzuhur_setelah_jp'],
            ':dzuhur_break_duration' => $defaults['durasi_istirahat_dzuhur_menit'],
            ':friday_break_duration' => $defaults['durasi_istirahat_jumat_menit'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $statement->closeCursor();
    }

    /**
     * @param array<string, int> $preferences
     * @return array<string, int>
     */
    private static function normalizePreferences(array $preferences): array
    {
        $preferences['blok_produktif_maks'] = min(4, max(2, (int) ($preferences['blok_produktif_maks'] ?? 4)));
        $preferences['blok_produktif_min'] = min(
            $preferences['blok_produktif_maks'],
            max(1, (int) ($preferences['blok_produktif_min'] ?? 2))
        );
        $preferences['blok_umum_maks'] = min(2, max(1, (int) ($preferences['blok_umum_maks'] ?? 2)));
        $preferences['maks_mapel_berat_berurutan'] = min(4, max(1, (int) ($preferences['maks_mapel_berat_berurutan'] ?? 2)));

        foreach ([
            'prioritas_praktik_pagi',
            'hindari_mapel_sama_per_hari',
            'sebar_beban_guru',
            'rapatkan_jadwal_kelas',
        ] as $key) {
            $preferences[$key] = !empty($preferences[$key]) ? 1 : 0;
        }

        foreach ([
            'bobot_jam_guru_harian',
            'bobot_jam_kelas_harian',
            'penalti_slot_sore_produktif',
            'penalti_mapel_sama_hari',
            'penalti_jam_kosong_guru',
            'penalti_jam_kosong_kelas',
            'penalti_mapel_berat_berurutan',
        ] as $key) {
            $preferences[$key] = min(99, max(0, (int) ($preferences[$key] ?? 0)));
        }

        return $preferences;
    }

    /**
     * @param array<string, mixed> $preferences
     * @return array<string, int|string>
     */
    private static function normalizeTimePreferences(array $preferences): array
    {
        $dailyLessons = min(14, max(1, (int) ($preferences['jumlah_jp_per_hari'] ?? 8)));

        return [
            'jam_masuk' => self::normalizeClockTime($preferences['jam_masuk'] ?? '07:00'),
            'durasi_jp_menit' => min(90, max(20, (int) ($preferences['durasi_jp_menit'] ?? 45))),
            'jeda_jp_menit' => min(20, max(0, (int) ($preferences['jeda_jp_menit'] ?? 0))),
            'jumlah_jp_per_hari' => $dailyLessons,
            'istirahat_pertama_setelah_jp' => min($dailyLessons, max(0, (int) ($preferences['istirahat_pertama_setelah_jp'] ?? 4))),
            'durasi_istirahat_pertama_menit' => min(120, max(0, (int) ($preferences['durasi_istirahat_pertama_menit'] ?? 15))),
            'istirahat_dzuhur_setelah_jp' => min($dailyLessons, max(0, (int) ($preferences['istirahat_dzuhur_setelah_jp'] ?? 6))),
            'durasi_istirahat_dzuhur_menit' => min(150, max(0, (int) ($preferences['durasi_istirahat_dzuhur_menit'] ?? 45))),
            'durasi_istirahat_jumat_menit' => min(180, max(0, (int) ($preferences['durasi_istirahat_jumat_menit'] ?? 75))),
        ];
    }

    private static function normalizeClockTime(mixed $value): string
    {
        $text = trim((string) $value);
        $time = \DateTimeImmutable::createFromFormat('H:i', $text)
            ?: \DateTimeImmutable::createFromFormat('H:i:s', $text);

        return $time instanceof \DateTimeImmutable ? $time->format('H:i:s') : '07:00:00';
    }

    /**
     * @param array<string, int|string> $preferences
     */
    private static function replacePeriodsFromTimePreferences(int $schoolYearId, array $preferences, PDO $connection): void
    {
        $now = date('Y-m-d H:i:s');
        $delete = $connection->prepare('DELETE FROM jadwal_jam_pelajaran WHERE tahun_ajaran_id = :year_id');
        $delete->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $delete->execute();

        $insert = $connection->prepare(
            'INSERT INTO jadwal_jam_pelajaran
                (tahun_ajaran_id, hari, jam_ke, waktu_mulai, waktu_selesai, tipe, label, aktif, created_at, updated_at)
             VALUES
                (:year_id, :day, :lesson_no, :start_time, :end_time, :type, :label, 1, :created_at, :updated_at)'
        );

        foreach (array_keys(self::DAYS) as $day) {
            foreach (self::periodsFromTimePreferences($day, $preferences) as $period) {
                $insert->execute([
                    ':year_id' => $schoolYearId,
                    ':day' => $day,
                    ':lesson_no' => $period['jam_ke'],
                    ':start_time' => $period['waktu_mulai'],
                    ':end_time' => $period['waktu_selesai'],
                    ':type' => $period['tipe'],
                    ':label' => $period['label'],
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
                $insert->closeCursor();
            }
        }
    }

    /**
     * @param array<string, int|string> $preferences
     * @return array<int, array<string, mixed>>
     */
    private static function periodsFromTimePreferences(string $day, array $preferences): array
    {
        $periods = [];
        $slotNo = 1;
        $current = new \DateTimeImmutable('2000-01-01 ' . (string) $preferences['jam_masuk']);
        $lessonDuration = (int) $preferences['durasi_jp_menit'];
        $gapDuration = (int) $preferences['jeda_jp_menit'];
        $dailyLessons = (int) $preferences['jumlah_jp_per_hari'];
        $firstBreakAfter = (int) $preferences['istirahat_pertama_setelah_jp'];
        $firstBreakDuration = (int) $preferences['durasi_istirahat_pertama_menit'];
        $dzuhurBreakAfter = (int) $preferences['istirahat_dzuhur_setelah_jp'];
        $dzuhurBreakDuration = $day === 'jumat'
            ? (int) $preferences['durasi_istirahat_jumat_menit']
            : (int) $preferences['durasi_istirahat_dzuhur_menit'];

        for ($lessonNo = 1; $lessonNo <= $dailyLessons; $lessonNo++) {
            $start = $current;
            $end = $current->modify('+' . $lessonDuration . ' minutes');
            $periods[] = [
                'jam_ke' => $slotNo++,
                'waktu_mulai' => $start->format('H:i:s'),
                'waktu_selesai' => $end->format('H:i:s'),
                'tipe' => 'pelajaran',
                'label' => 'JP ' . $lessonNo,
            ];
            $current = $end;

            if ($lessonNo === $firstBreakAfter && $firstBreakDuration > 0) {
                $periods[] = self::breakPeriod($slotNo++, $current, $firstBreakDuration, 'Istirahat');
                $current = $current->modify('+' . $firstBreakDuration . ' minutes');
            }

            if ($lessonNo === $dzuhurBreakAfter && $dzuhurBreakDuration > 0) {
                $label = $day === 'jumat' ? 'Sholat Jumat / Istirahat' : 'Sholat Dzuhur / Istirahat';
                $periods[] = self::breakPeriod($slotNo++, $current, $dzuhurBreakDuration, $label);
                $current = $current->modify('+' . $dzuhurBreakDuration . ' minutes');
            }

            if ($lessonNo < $dailyLessons && $gapDuration > 0) {
                $current = $current->modify('+' . $gapDuration . ' minutes');
            }
        }

        return $periods;
    }

    /**
     * @return array<string, mixed>
     */
    private static function breakPeriod(int $slotNo, \DateTimeImmutable $start, int $duration, string $label): array
    {
        $end = $start->modify('+' . $duration . ' minutes');

        return [
            'jam_ke' => $slotNo,
            'waktu_mulai' => $start->format('H:i:s'),
            'waktu_selesai' => $end->format('H:i:s'),
            'tipe' => 'istirahat',
            'label' => $label,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function defaultPeriodsForDay(string $day): array
    {
        $periods = [
            ['jam_ke' => 1, 'waktu_mulai' => '07:00:00', 'waktu_selesai' => '07:45:00', 'tipe' => 'pelajaran', 'label' => 'JP 1'],
            ['jam_ke' => 2, 'waktu_mulai' => '07:45:00', 'waktu_selesai' => '08:30:00', 'tipe' => 'pelajaran', 'label' => 'JP 2'],
            ['jam_ke' => 3, 'waktu_mulai' => '08:30:00', 'waktu_selesai' => '09:15:00', 'tipe' => 'pelajaran', 'label' => 'JP 3'],
            ['jam_ke' => 4, 'waktu_mulai' => '09:15:00', 'waktu_selesai' => '10:00:00', 'tipe' => 'pelajaran', 'label' => 'JP 4'],
            ['jam_ke' => 5, 'waktu_mulai' => '10:00:00', 'waktu_selesai' => '10:15:00', 'tipe' => 'istirahat', 'label' => 'Istirahat'],
            ['jam_ke' => 6, 'waktu_mulai' => '10:15:00', 'waktu_selesai' => '11:00:00', 'tipe' => 'pelajaran', 'label' => 'JP 5'],
            ['jam_ke' => 7, 'waktu_mulai' => '11:00:00', 'waktu_selesai' => '11:45:00', 'tipe' => 'pelajaran', 'label' => 'JP 6'],
            ['jam_ke' => 8, 'waktu_mulai' => '11:45:00', 'waktu_selesai' => '12:30:00', 'tipe' => 'istirahat', 'label' => 'Sholat Dzuhur / Istirahat'],
            ['jam_ke' => 9, 'waktu_mulai' => '12:30:00', 'waktu_selesai' => '13:15:00', 'tipe' => 'pelajaran', 'label' => 'JP 7'],
            ['jam_ke' => 10, 'waktu_mulai' => '13:15:00', 'waktu_selesai' => '14:00:00', 'tipe' => 'pelajaran', 'label' => 'JP 8'],
        ];

        if ($day === 'senin') {
            $periods[0]['tipe'] = 'kegiatan';
            $periods[0]['label'] = 'Upacara';
        }

        if ($day === 'jumat') {
            $periods[0]['tipe'] = 'kegiatan';
            $periods[0]['label'] = 'Sholawat / Istighosah';
            $periods[7]['waktu_mulai'] = '11:30:00';
            $periods[7]['waktu_selesai'] = '12:45:00';
            $periods[7]['label'] = 'Sholat Jumat / Istirahat';
        }

        if ($day === 'sabtu') {
            $periods[8]['tipe'] = 'kegiatan';
            $periods[8]['label'] = 'Pramuka';
            $periods[9]['tipe'] = 'kegiatan';
            $periods[9]['label'] = 'Pramuka';
        }

        return $periods;
    }

    private static function ensureLessonScheduleColumns(PDO $connection): void
    {
        $columns = [
            'semester' => 'ALTER TABLE jadwal_pelajaran ADD COLUMN semester TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER tahun_ajaran_id',
            'draft_id' => 'ALTER TABLE jadwal_pelajaran ADD COLUMN draft_id INT UNSIGNED NULL AFTER semester',
            'jam_ke_mulai' => 'ALTER TABLE jadwal_pelajaran ADD COLUMN jam_ke_mulai TINYINT UNSIGNED NULL AFTER jumlah_jam',
            'jam_ke_selesai' => 'ALTER TABLE jadwal_pelajaran ADD COLUMN jam_ke_selesai TINYINT UNSIGNED NULL AFTER jam_ke_mulai',
            'ruangan_id' => 'ALTER TABLE jadwal_pelajaran ADD COLUMN ruangan_id INT UNSIGNED NULL AFTER jam_ke_selesai',
            'parallel_group_id' => 'ALTER TABLE jadwal_pelajaran ADD COLUMN parallel_group_id INT UNSIGNED NULL AFTER ruangan_id',
            'status_jadwal' => 'ALTER TABLE jadwal_pelajaran ADD COLUMN status_jadwal ENUM("aktif","arsip") NOT NULL DEFAULT "aktif" AFTER parallel_group_id',
            'is_locked' => 'ALTER TABLE jadwal_pelajaran ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER status_jadwal',
            'sumber' => 'ALTER TABLE jadwal_pelajaran ADD COLUMN sumber ENUM("manual","generate","aktivasi") NOT NULL DEFAULT "manual" AFTER is_locked',
        ];

        foreach ($columns as $column => $sql) {
            if (!self::columnExists($connection, 'jadwal_pelajaran', $column)) {
                $connection->exec($sql);
            }
        }

        self::ensureIndex($connection, 'jadwal_pelajaran', 'idx_jadwal_status_context', 'CREATE INDEX idx_jadwal_status_context ON jadwal_pelajaran (tahun_ajaran_id, semester, status_jadwal, kelas_id)');
        self::ensureIndex($connection, 'jadwal_pelajaran', 'idx_jadwal_slot_guru', 'CREATE INDEX idx_jadwal_slot_guru ON jadwal_pelajaran (tahun_ajaran_id, semester, hari, jam_ke_mulai, guru_mata_pelajaran_id)');
    }

    private static function ensureDraftItemColumns(PDO $connection): void
    {
        $columns = [
            'parallel_group_id' => 'ALTER TABLE jadwal_draft_items ADD COLUMN parallel_group_id INT UNSIGNED NULL AFTER jumlah_jam',
        ];

        foreach ($columns as $column => $sql) {
            if (!self::columnExists($connection, 'jadwal_draft_items', $column)) {
                $connection->exec($sql);
            }
        }
    }

    private static function columnExists(PDO $connection, string $table, string $column): bool
    {
        $statement = $connection->query('SHOW COLUMNS FROM ' . $table . ' LIKE ' . $connection->quote($column));

        return $statement !== false && $statement->fetch(PDO::FETCH_ASSOC) !== false;
    }

    private static function ensureIndex(PDO $connection, string $table, string $index, string $createSql): void
    {
        $statement = $connection->query('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ' . $connection->quote($index));
        if ($statement !== false && $statement->fetch(PDO::FETCH_ASSOC) !== false) {
            return;
        }

        $connection->exec($createSql);
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function countRows(PDO $connection, string $table, string $where, array $params): int
    {
        $statement = $connection->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $where);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_INT);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }
}

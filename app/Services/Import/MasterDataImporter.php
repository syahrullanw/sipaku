<?php

namespace App\Services\Import;

use App\Models\Attitude;
use App\Models\Major;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\StudentAccountManager;
use App\Support\StudentImportTemplate;
use App\Support\StudentNipdGenerator;
use Core\Database;
use RuntimeException;

class MasterDataImporter
{
    /**
     * @return array{processed:int, inserted:int, updated:int, skipped:int, accounts_created:int, errors:array<int, string>}
     */
    public function importTeachers(string $path): array
    {
        $rows = SpreadsheetImporter::readAssociative($path);
        $now = date('Y-m-d H:i:s');

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $accountsCreated = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            $name = $this->value($row, ['nama', 'name', 'nama_guru']);
            if ($name === '') {
                $skipped++;
                $errors[] = sprintf('Baris %d: kolom nama tidak boleh kosong.', $line);
                continue;
            }

            $nip = $this->value($row, ['nip']);
            $email = $this->sanitizeEmail($this->value($row, ['email', 'surel', 'surat_elektronik']));
            $phone = $this->value($row, ['telepon', 'telp', 'no_telepon', 'no_telp', 'nomor_telepon', 'nomor_telp', 'hp', 'no_hp', 'nomor_hp']);
            $address = $this->value($row, ['alamat', 'alamat_lengkap', 'address']);

            $payload = [
                'nama' => $name,
                'nip' => $nip !== '' ? $nip : null,
                'email' => $email,
                'telepon' => $phone !== '' ? $phone : null,
                'alamat' => $address !== '' ? $address : null,
                'updated_at' => $now,
            ];

            $existing = $this->resolveTeacher($nip, $email);
            $teacherId = $existing !== null ? (int) $existing['id'] : null;

            if ($teacherId === null) {
                if ($nip !== '' && Teacher::exists(['nip' => $nip])) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: NIP %s sudah terdaftar.', $line, $nip);
                    continue;
                }

                if ($email !== null && Teacher::exists(['email' => $email])) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: Email %s sudah terdaftar.', $line, $email);
                    continue;
                }

                $createPayload = $payload;
                $createPayload['created_at'] = $now;
                $createPayload['status'] = 'aktif';

                try {
                    Teacher::create($createPayload);
                    $inserted++;
                    $teacherId = (int) Database::connection()->lastInsertId();

                    if ($teacherId > 0) {
                        $accountCreated = $this->ensureTeacherAccount($teacherId, $createPayload);
                        if ($accountCreated) {
                            $accountsCreated++;
                        }
                    }
                } catch (\Throwable $exception) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: gagal menyimpan guru baru (%s).', $line, $exception->getMessage());
                }

                continue;
            }

            if ($nip !== '' && Teacher::exists(['nip' => $nip], $teacherId)) {
                $skipped++;
                $errors[] = sprintf('Baris %d: NIP %s digunakan oleh guru lain.', $line, $nip);
                continue;
            }

            if ($email !== null && Teacher::exists(['email' => $email], $teacherId)) {
                $skipped++;
                $errors[] = sprintf('Baris %d: Email %s digunakan oleh guru lain.', $line, $email);
                continue;
            }

            try {
                Teacher::updateById($teacherId, $payload);
                $this->syncTeacherAccount($teacherId, $payload);
                $updated++;
            } catch (\Throwable $exception) {
                $skipped++;
                $errors[] = sprintf('Baris %d: gagal memperbarui guru (%s).', $line, $exception->getMessage());
            }
        }

        return [
            'processed' => count($rows),
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'accounts_created' => $accountsCreated,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{processed:int, inserted:int, updated:int, skipped:int, errors:array<int, string>}
     */
    public function importStudents(string $path): array
    {
        $this->assertStudentImportHeaders($path);

        $rows = SpreadsheetImporter::readAssociative($path);
        $now = date('Y-m-d H:i:s');
        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYear === null || $activeYearId <= 0) {
            throw new RuntimeException('Tidak ada tahun ajaran aktif untuk membuat NIPD siswa.');
        }

        $processed = 0;
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $studentAliases = StudentImportTemplate::requiredHeaderAliases();

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            if (StudentImportTemplate::isInstructionRow($row)) {
                continue;
            }

            $processed++;

            $name = $this->value($row, $studentAliases['nama']);
            $nipd = $this->value($row, StudentImportTemplate::nipdHeaderAliases());
            $nisn = $this->value($row, $studentAliases['nisn']);
            $nik = $this->value($row, $studentAliases['nik']);
            $genderRaw = $this->value($row, $studentAliases['jenis_kelamin']);
            $birthPlace = $this->value($row, $studentAliases['tempat_lahir']);
            $birthDateRaw = $this->value($row, $studentAliases['tanggal_lahir']);
            $father = $this->value($row, $studentAliases['ayah_nama']);
            $mother = $this->value($row, $studentAliases['ibu_nama']);

            $mobilePhone = $this->value($row, $studentAliases['hp']);
            $homePhone = $this->value($row, [
                'telepon',
                'telp',
                'no_telepon',
                'no_telp',
                'nomor_telepon',
                'nomor_telp',
            ]);

            $gender = $this->normalizeGender($genderRaw);
            $birthDate = $this->parseDate($birthDateRaw);

            $required = [
                'nama' => $name,
                'nisn' => $nisn,
                'nik' => $nik,
                'jenis kelamin' => $gender,
                'tempat lahir' => $birthPlace,
                'tanggal lahir' => $birthDate,
                'nama ayah' => $father,
                'nama ibu' => $mother,
                'nomor hp' => $mobilePhone,
            ];

            $missing = array_keys(array_filter($required, static fn ($value) => $value === '' || $value === null));
            if (!empty($missing)) {
                $skipped++;
                $errors[] = sprintf('Baris %d: kolom wajib hilang (%s).', $line, implode(', ', $missing));
                continue;
            }

            if (strlen($nisn) !== 10 || !ctype_digit($nisn)) {
                $skipped++;
                $errors[] = sprintf('Baris %d: NISN harus berisi 10 digit angka.', $line);
                continue;
            }

            if (strlen($nik) !== 16 || !ctype_digit($nik)) {
                $skipped++;
                $errors[] = sprintf('Baris %d: NIK harus berisi 16 digit angka.', $line);
                continue;
            }

            $existing = Student::findByNisn($nisn);
            if ($existing === null && $nipd !== '') {
                $existing = Student::findByNipd($nipd);
            }
            if ($existing === null) {
                $existing = Student::findByNik($nik);
            }

            $studentId = $existing !== null ? (int) $existing['id'] : null;

            if ($studentId === null) {
                if (Student::exists(['nisn' => $nisn])) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: NISN %s sudah terdaftar.', $line, $nisn);
                    continue;
                }
                if (Student::exists(['nik' => $nik])) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: NIK %s sudah terdaftar.', $line, $nik);
                    continue;
                }
            } else {
                if (Student::exists(['nisn' => $nisn], $studentId)) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: NISN %s digunakan oleh siswa lain.', $line, $nisn);
                    continue;
                }
                if (Student::exists(['nik' => $nik], $studentId)) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: NIK %s digunakan oleh siswa lain.', $line, $nik);
                    continue;
                }
            }

            $payload = [
                'nama' => $name,
                'nipd' => $studentId === null
                    ? StudentNipdGenerator::generateNext($activeYear, StudentNipdGenerator::TYPE_REGULAR)
                    : (string) ($existing['nipd'] ?? ''),
                'nisn' => $nisn,
                'nik' => $nik,
                'jenis_kelamin' => $gender,
                'tempat_lahir' => $birthPlace,
                'tanggal_lahir' => $birthDate,
                'ayah_nama' => $father,
                'ibu_nama' => $mother,
                'status' => 'aktif',
                'status_dapodik' => 'aktif',
                'updated_at' => $now,
            ];

            $optional = [
                'agama',
                'alamat',
                'email',
                'ayah_tahun_lahir',
                'ayah_pekerjaan',
                'ayah_penghasilan',
                'ibu_tahun_lahir',
                'ibu_pekerjaan',
                'ibu_penghasilan',
                'wali_nama',
                'wali_tahun_lahir',
                'wali_pekerjaan',
                'wali_penghasilan',
                'sekolah_asal',
            ];

            foreach ($optional as $field) {
                $value = $this->value($row, [$field]);
                $payload[$field] = $value !== '' ? $value : null;
            }

            $payload['ayah_tahun_lahir'] = $this->sanitizeYear($payload['ayah_tahun_lahir'] ?? null);
            $payload['ibu_tahun_lahir'] = $this->sanitizeYear($payload['ibu_tahun_lahir'] ?? null);
            $payload['wali_tahun_lahir'] = $this->sanitizeYear($payload['wali_tahun_lahir'] ?? null);

            $payload['telepon'] = $homePhone !== '' ? $homePhone : null;
            $payload['hp'] = $mobilePhone;
            $payload['email'] = $this->sanitizeEmail($payload['email'] ?? null);

            if ($studentId === null) {
                $payload['created_at'] = $now;
                $payload['tahun_ajaran_id'] = $activeYearId;

                try {
                    if (!Student::create($payload)) {
                        $skipped++;
                        $errors[] = sprintf('Baris %d: gagal menyimpan siswa baru.', $line);
                    } else {
                        $inserted++;
                        $newStudentId = (int) Database::connection()->lastInsertId();
                        $studentRecord = $newStudentId > 0 ? Student::find($newStudentId) : null;
                        if ($studentRecord === null) {
                            $studentRecord = Student::findByNisn($nisn) ?? Student::findByNipd((string) ($payload['nipd'] ?? ''));
                        }

                        if ($studentRecord !== null) {
                            StudentAccountManager::sync($studentRecord, true);
                        }
                    }
                } catch (\Throwable $exception) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: gagal menyimpan siswa baru (%s).', $line, $exception->getMessage());
                }

                continue;
            }

            try {
                if (!Student::updateById($studentId, $payload)) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: gagal memperbarui siswa.', $line);
                    continue;
                }

                $updated++;
                $updatedRecord = Student::find($studentId) ?? Student::findByNisn($nisn);
                if ($updatedRecord !== null) {
                    $originalNisn = (string) ($existing['nisn'] ?? '');
                    $currentNisn = (string) ($updatedRecord['nisn'] ?? '');
                    $shouldResetPassword = $currentNisn !== '' && $currentNisn !== $originalNisn;
                    StudentAccountManager::sync($updatedRecord, $shouldResetPassword);
                }
            } catch (\Throwable $exception) {
                $skipped++;
                $errors[] = sprintf('Baris %d: gagal memperbarui siswa (%s).', $line, $exception->getMessage());
            }
        }

        return [
            'processed' => $processed,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{processed:int, inserted:int, updated:int, skipped:int, errors:array<int, string>}
     */
    public function importSubjects(string $path, int $activeSchoolYearId): array
    {
        if ($activeSchoolYearId <= 0 || SchoolYear::find($activeSchoolYearId) === null) {
            throw new RuntimeException('Tahun ajaran aktif tidak ditemukan.');
        }

        $rows = SpreadsheetImporter::readAssociative($path);
        $now = date('Y-m-d H:i:s');

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $groupOptions = Subject::groupOptions();
        $groupCodes = array_keys($groupOptions);
        $groupLabelLookup = [];
        foreach ($groupOptions as $code => $label) {
            $groupLabelLookup[$this->normalizeKey($code)] = $code;
            $groupLabelLookup[$this->normalizeKey($label)] = $code;

            $tokens = preg_split('/[^a-z0-9]+/i', $label, -1, PREG_SPLIT_NO_EMPTY);
            if ($tokens !== false) {
                foreach ($tokens as $token) {
                    $groupLabelLookup[$this->normalizeKey($token)] = $code;
                }
            }
        }

        $majors = Major::options(false);
        $majorLookup = [];
        foreach ($majors as $id => $name) {
            $majorLookup[$this->normalizeKey($name)] = (int) $id;
        }

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            $code = strtoupper($this->value($row, ['kode', 'kode_mapel', 'kode_mata_pelajaran']));
            $name = $this->value($row, ['nama', 'nama_mapel', 'nama_mata_pelajaran']);
            $groupRaw = $this->value($row, ['jenis', 'kelompok', 'kelompok_mapel', 'jenis_mapel', 'group', 'grup']);
            $group = strtoupper($groupRaw);
            if (!in_array($group, $groupCodes, true)) {
                $normalizedGroup = $this->normalizeKey($groupRaw);
                if ($normalizedGroup !== '' && isset($groupLabelLookup[$normalizedGroup])) {
                    $group = $groupLabelLookup[$normalizedGroup];
                } else {
                    $primaryToken = strtoupper((string) ($this->firstToken($groupRaw) ?? ''));
                    if ($primaryToken !== '' && isset($groupLabelLookup[$this->normalizeKey($primaryToken)])) {
                        $group = $groupLabelLookup[$this->normalizeKey($primaryToken)];
                    } elseif ($primaryToken !== '' && in_array($primaryToken, $groupCodes, true)) {
                        $group = $primaryToken;
                    }
                }
            }
            $majorName = $this->value($row, ['jurusan', 'program_keahlian', 'kompetensi', 'jurusan_nama']);
            $description = $this->value($row, ['deskripsi', 'keterangan']);

            if ($code === '' || $name === '' || $group === '') {
                $skipped++;
                $errors[] = sprintf('Baris %d: kolom kode, nama, dan jenis wajib diisi.', $line);
                continue;
            }

            if (!in_array($group, $groupCodes, true)) {
                $skipped++;
                $errors[] = sprintf(
                    'Baris %d: jenis %s tidak dikenal. Gunakan salah satu kode berikut: %s.',
                    $line,
                    $groupRaw !== '' ? $groupRaw : '[kosong]',
                    implode(', ', $groupCodes)
                );
                continue;
            }

            $payload = [
                'tahun_ajaran_id' => $activeSchoolYearId,
                'kode' => $code,
                'nama' => $name,
                'jenis' => $group,
                'deskripsi' => $description !== '' ? $description : null,
                'updated_at' => $now,
            ];

            if (in_array($group, ['C2', 'C3'], true)) {
                if ($majorName === '') {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: jenis %s memerlukan jurusan.', $line, $groupOptions[$group] ?? $group);
                    continue;
                }

                $key = $this->normalizeKey($majorName);
                $majorId = $majorLookup[$key] ?? null;
                if ($majorId === null) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: jurusan "%s" tidak ditemukan.', $line, $majorName);
                    continue;
                }
                $payload['jurusan_id'] = $majorId;
            } elseif ($majorName !== '') {
                $key = $this->normalizeKey($majorName);
                $payload['jurusan_id'] = $majorLookup[$key] ?? null;
            } else {
                $payload['jurusan_id'] = null;
            }

            $existing = Subject::findByCodeAndYear($code, $activeSchoolYearId);
            $subjectId = $existing !== null ? (int) $existing['id'] : null;

            if ($subjectId === null) {
                if (Subject::exists(['tahun_ajaran_id' => $activeSchoolYearId, 'kode' => $code])) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: kode %s sudah digunakan.', $line, $code);
                    continue;
                }
                if (Subject::exists(['tahun_ajaran_id' => $activeSchoolYearId, 'nama' => $name])) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: nama %s sudah digunakan.', $line, $name);
                    continue;
                }

                $payload['created_at'] = $now;

                try {
                    Subject::create($payload);
                    $inserted++;
                } catch (\Throwable $exception) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: gagal menyimpan mata pelajaran (%s).', $line, $exception->getMessage());
                }

                continue;
            }

            if (Subject::exists(['tahun_ajaran_id' => $activeSchoolYearId, 'kode' => $code], $subjectId)) {
                $skipped++;
                $errors[] = sprintf('Baris %d: kode %s digunakan oleh mata pelajaran lain.', $line, $code);
                continue;
            }

            if (Subject::exists(['tahun_ajaran_id' => $activeSchoolYearId, 'nama' => $name], $subjectId)) {
                $skipped++;
                $errors[] = sprintf('Baris %d: nama %s digunakan oleh mata pelajaran lain.', $line, $name);
                continue;
            }

            try {
                Subject::updateById($subjectId, $payload);
                $updated++;
            } catch (\Throwable $exception) {
                $skipped++;
                $errors[] = sprintf('Baris %d: gagal memperbarui mata pelajaran (%s).', $line, $exception->getMessage());
            }
        }

        return [
            'processed' => count($rows),
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{processed:int, inserted:int, updated:int, skipped:int, errors:array<int, string>}
     */
    public function importAttitudes(string $path, string $defaultType): array
    {
        if (!array_key_exists($defaultType, Attitude::TYPES)) {
            throw new RuntimeException('Jenis sikap tidak valid.');
        }

        $rows = SpreadsheetImporter::readAssociative($path);
        $now = date('Y-m-d H:i:s');

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            $rawType = strtolower($this->value($row, ['jenis', 'tipe', 'jenis_sikap']));
            $type = $this->normalizeAttitudeType($rawType !== '' ? $rawType : null, $defaultType);

            if (!array_key_exists($type, Attitude::TYPES)) {
                $skipped++;
                $errors[] = sprintf('Baris %d: jenis sikap tidak dikenal.', $line);
                continue;
            }

            $code = strtoupper($this->value($row, ['kode', 'kode_sikap', 'kode_indikator']));
            $name = $this->value($row, ['nama', 'indikator', 'nama_sikap']);
            $description = $this->value($row, ['deskripsi', 'keterangan', 'uraian']);
            $statusRaw = strtolower($this->value($row, ['status']));
            $status = $statusRaw !== '' ? $statusRaw : 'aktif';

            if ($code === '' || $name === '') {
                $skipped++;
                $errors[] = sprintf('Baris %d: kolom kode dan nama wajib diisi.', $line);
                continue;
            }

            if (!in_array($status, ['aktif', 'nonaktif'], true)) {
                $skipped++;
                $errors[] = sprintf('Baris %d: status %s tidak valid (gunakan aktif/nonaktif).', $line, $statusRaw);
                continue;
            }

            $existing = Attitude::findByTypeAndCode($type, $code);
            $attitudeId = $existing !== null ? (int) $existing['id'] : null;

            if ($attitudeId === null) {
                if (Attitude::exists(['jenis' => $type, 'kode' => $code])) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: kode %s sudah digunakan untuk jenis %s.', $line, $code, Attitude::TYPES[$type]);
                    continue;
                }

                if (Attitude::exists(['jenis' => $type, 'nama' => $name])) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: nama %s sudah digunakan untuk jenis %s.', $line, $name, Attitude::TYPES[$type]);
                    continue;
                }

                $payload = [
                    'jenis' => $type,
                    'kode' => $code,
                    'nama' => $name,
                    'deskripsi' => $description !== '' ? $description : null,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                try {
                    Attitude::create($payload);
                    $inserted++;
                } catch (\Throwable $exception) {
                    $skipped++;
                    $errors[] = sprintf('Baris %d: gagal menyimpan data sikap (%s).', $line, $exception->getMessage());
                }

                continue;
            }

            if (Attitude::exists(['jenis' => $type, 'kode' => $code], $attitudeId)) {
                $payload = [
                    'nama' => $name,
                    'deskripsi' => $description !== '' ? $description : null,
                    'status' => $status,
                    'updated_at' => $now,
                ];
            } else {
                $payload = [
                    'kode' => $code,
                    'nama' => $name,
                    'deskripsi' => $description !== '' ? $description : null,
                    'status' => $status,
                    'updated_at' => $now,
                ];
            }

            if (Attitude::exists(['jenis' => $type, 'nama' => $name], $attitudeId)) {
                $skipped++;
                $errors[] = sprintf('Baris %d: nama %s digunakan oleh data sikap lain.', $line, $name);
                continue;
            }

            try {
                Attitude::updateById($attitudeId, $payload);
                $updated++;
            } catch (\Throwable $exception) {
                $skipped++;
                $errors[] = sprintf('Baris %d: gagal memperbarui data sikap (%s).', $line, $exception->getMessage());
            }
        }

        return [
            'processed' => count($rows),
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function value(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $value = $row[$key];
                if ($value === null) {
                    continue;
                }
                return is_scalar($value) ? trim((string) $value) : '';
            }
        }

        return '';
    }

    private function sanitizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = trim($email);
        if ($email === '') {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null;
    }

    private function normalizeGender(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'l', 'laki', 'laki-laki', 'pria', 'male', 'boy' => 'L',
            'p', 'perempuan', 'wanita', 'female', 'girl' => 'P',
            default => '',
        };
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $excelDate = (float) $value;
            if ($excelDate <= 0) {
                return null;
            }
            $timestamp = ($excelDate - 25569) * 86400;
            if ($timestamp <= 0) {
                return null;
            }

            return gmdate('Y-m-d', (int) $timestamp);
        }

        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d', 'd.m.Y'];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $string);
            if ($date instanceof \DateTimeInterface) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($string);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function sanitizeYear(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}$/', $value)) {
            return $value;
        }

        return null;
    }

    private function assertStudentImportHeaders(string $path): void
    {
        $headers = SpreadsheetImporter::readHeaderNames($path);
        if (empty($headers)) {
            throw new RuntimeException('Format template siswa tidak sesuai. Unduh template import dari sistem terlebih dahulu.');
        }

        $headerLookup = array_fill_keys($headers, true);
        $missing = [];

        foreach (StudentImportTemplate::requiredHeaderAliases() as $label => $aliases) {
            $found = false;
            foreach ($aliases as $alias) {
                if (isset($headerLookup[$alias])) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $missing[] = $label;
            }
        }

        if (!empty($missing)) {
            throw new RuntimeException(sprintf(
                'Format template siswa tidak sesuai. Unduh template import dari sistem. Kolom wajib belum ada: %s.',
                implode(', ', $missing)
            ));
        }
    }

    private function normalizeAttitudeType(?string $value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $normalized = strtolower(trim($value));
        if (array_key_exists($normalized, Attitude::TYPES)) {
            return $normalized;
        }

        $normalizedKey = $this->normalizeKey($value);
        foreach (Attitude::TYPES as $code => $label) {
            if ($this->normalizeKey($label) === $normalizedKey) {
                return $code;
            }
        }

        return '__invalid__';
    }

    private function normalizeKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);

        return trim((string) $value, '_');
    }

    private function firstToken(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $parts = preg_split('/[^a-z0-9]+/i', $value, -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false || empty($parts)) {
            return null;
        }

        return $parts[0];
    }

    private function ensureTeacherAccount(int $teacherId, array $payload): bool
    {
        if ($teacherId <= 0) {
            return false;
        }

        $existing = User::findByTeacherId($teacherId);
        if ($existing !== null) {
            return false;
        }

        $teacher = Teacher::find($teacherId);
        if ($teacher === null) {
            return false;
        }

        $username = $this->generateUsernameFromName((string) ($teacher['nama'] ?? $payload['nama'] ?? 'guru'));
        $email = $this->sanitizeEmail($payload['email'] ?? ($teacher['email'] ?? null));
        $passwordPlain = $this->generateTemporaryPassword();

        if ($email !== null && User::exists(['email' => $email])) {
            $email = null;
        }

        $now = date('Y-m-d H:i:s');

        try {
            User::create([
                'name' => $teacher['nama'] ?? $payload['nama'] ?? 'Guru',
                'username' => $username,
                'password' => password_hash($passwordPlain, PASSWORD_BCRYPT),
                'email' => $email,
                'role' => 'guru',
                'teacher_id' => $teacherId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    private function syncTeacherAccount(int $teacherId, array $payload): void
    {
        $user = User::findByTeacherId($teacherId);
        if ($user === null) {
            return;
        }

        $updates = [
            'name' => $payload['nama'] ?? $user['name'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $email = $this->sanitizeEmail($payload['email'] ?? null);
        if ($email === null) {
            $updates['email'] = null;
        } elseif (!User::exists(['email' => $email], (int) $user['id'])) {
            $updates['email'] = $email;
        }

        User::updateById((int) $user['id'], $updates);
    }

    private function generateUsernameFromName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            $name = 'guru';
        }

        $parts = preg_split('/\s+/', $name);
        $firstWord = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) ($parts[0] ?? $name)));
        if ($firstWord === '') {
            $firstWord = 'guru';
        }

        $base = substr($firstWord, 0, 6);
        if ($base === '') {
            $base = 'guru';
        }

        if (strlen($base) < 3) {
            $base = str_pad($base, 3, 'a');
        }

        $username = $base;
        $suffix = 1;

        while (User::exists(['username' => $username])) {
            $suffixString = (string) $suffix;
            $maxBaseLength = max(1, 50 - strlen($suffixString));
            $username = substr($base, 0, $maxBaseLength) . $suffixString;
            $suffix++;
        }

        return strtolower($username);
    }

    private function generateTemporaryPassword(): string
    {
        return 'guru123';
    }

    private function resolveTeacher(string $nip, ?string $email): ?array
    {
        $teacher = Teacher::findByNip($nip);
        if ($teacher !== null) {
            return $teacher;
        }

        if ($email !== null) {
            $teacher = Teacher::findByEmail($email);
        }

        return $teacher;
    }
}

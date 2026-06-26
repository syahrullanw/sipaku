<?php

namespace App\Services\Import;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\Teacher;

class SubjectTeacherAssignmentImporter
{
    /**
     * @return array{processed:int, created:int, updated:int, skipped:int, errors:array<int, string>}
     */
    public function import(string $path, int $schoolYearId): array
    {
        $rows = SpreadsheetImporter::readAssociative($path);

        $processed = count($rows);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $classIndex = $this->buildClassIndex($schoolYearId);
        $now = date('Y-m-d H:i:s');

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            $subjectCode = $this->value($row, [
                'mata_pelajaran_kode',
                'kode_mata_pelajaran',
                'kode_mapel',
                'kode',
                'mapel_kode',
            ]);

            if ($subjectCode === '') {
                $skipped++;
                $errors[] = sprintf('Baris %d: kolom kode mata pelajaran wajib diisi.', $line);
                continue;
            }

            $subject = Subject::findByCodeAndYear($subjectCode, $schoolYearId);
            if ($subject === null) {
                $skipped++;
                $errors[] = sprintf('Baris %d: mata pelajaran dengan kode "%s" tidak ditemukan pada tahun ajaran ini.', $line, $subjectCode);
                continue;
            }

            [$teacher, $teacherError] = $this->resolveTeacher($row);
            if ($teacherError !== null) {
                $skipped++;
                $errors[] = sprintf('Baris %d: %s', $line, $teacherError);
                continue;
            }

            if ($teacher === null) {
                $skipped++;
                $errors[] = sprintf('Baris %d: guru tidak dapat ditentukan. Sertakan NIP atau email guru.', $line);
                continue;
            }

            $classesRaw = $this->value($row, ['kelas', 'daftar_kelas', 'classes', 'class']);
            if ($classesRaw === '') {
                $skipped++;
                $errors[] = sprintf('Baris %d: kolom kelas wajib diisi.', $line);
                continue;
            }

            [$classIds, $classError] = $this->matchClasses($classesRaw, $classIndex);
            if ($classError !== null) {
                $skipped++;
                $errors[] = sprintf('Baris %d: %s', $line, $classError);
                continue;
            }

            if (empty($classIds)) {
                $skipped++;
                $errors[] = sprintf('Baris %d: tidak ada kelas yang cocok dengan nilai "%s".', $line, $classesRaw);
                continue;
            }

            $subjectMajorId = isset($subject['jurusan_id']) ? (int) $subject['jurusan_id'] : null;
            if ($subjectMajorId !== null && $subjectMajorId > 0) {
                $invalidClasses = [];
                foreach ($classIds as $classId) {
                    $class = $classIndex['classes'][$classId] ?? null;
                    if ($class === null) {
                        continue;
                    }
                    $classMajorId = isset($class['jurusan_id']) ? (int) $class['jurusan_id'] : null;
                    if ($classMajorId !== null && $classMajorId !== $subjectMajorId) {
                        $invalidClasses[] = $this->formatClassLabel($class);
                    }
                }

                if (!empty($invalidClasses)) {
                    $skipped++;
                    $errors[] = sprintf(
                        'Baris %d: kelas %s tidak sesuai dengan jurusan mata pelajaran.',
                        $line,
                        implode(', ', $invalidClasses)
                    );
                    continue;
                }
            }

            $noteValue = $this->value($row, ['catatan', 'keterangan', 'note'], true);

            $assignment = SubjectTeacher::findBySubjectAndTeacher(
                (int) $subject['id'],
                (int) $teacher['id']
            );

            try {
                if ($assignment === null) {
                    $payload = [
                        'mata_pelajaran_id' => (int) $subject['id'],
                        'guru_id' => (int) $teacher['id'],
                        'catatan' => $noteValue === null ? null : ($noteValue !== '' ? $noteValue : null),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    SubjectTeacher::createWithClasses($payload, $classIds);
                    $created++;
                    continue;
                }

                $updatePayload = [
                    'updated_at' => $now,
                ];

                if ($noteValue !== null) {
                    $updatePayload['catatan'] = $noteValue !== '' ? $noteValue : null;
                }

                SubjectTeacher::updateWithClasses((int) $assignment['id'], $updatePayload, $classIds);
                $updated++;
            } catch (\Throwable $exception) {
                $skipped++;
                $errors[] = sprintf('Baris %d: gagal menyimpan data (%s).', $line, $exception->getMessage());
            }
        }

        return [
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function value(array $row, array $keys, bool $nullable = false): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];
            if ($value === null) {
                return $nullable ? null : '';
            }

            if (is_scalar($value)) {
                $string = trim((string) $value);

                return $string;
            }

            return $nullable ? null : '';
        }

        return $nullable ? null : '';
    }

    /**
     * @return array{
     *     lookup: array<string, int>,
     *     classes: array<int, array<string, mixed>>
     * }
     */
    private function buildClassIndex(int $schoolYearId): array
    {
        $classes = Classroom::byYear($schoolYearId);
        $lookup = [];
        $classData = [];

        foreach ($classes as $class) {
            $classId = (int) ($class['id'] ?? 0);
            if ($classId <= 0) {
                continue;
            }

            $classData[$classId] = $class;

            foreach ($this->generateClassKeys($class) as $key) {
                $lookup[$key] = $classId;
            }
        }

        return [
            'lookup' => $lookup,
            'classes' => $classData,
        ];
    }

    /**
     * @param array<string, mixed> $class
     * @return array<int, string>
     */
    private function generateClassKeys(array $class): array
    {
        $keys = [];
        $id = isset($class['id']) ? (int) $class['id'] : 0;
        if ($id > 0) {
            $keys[] = (string) $id;
        }

        $name = isset($class['nama']) ? (string) $class['nama'] : '';
        if ($name !== '') {
            $keys[] = $this->normalizeKey($name);
        }

        $level = isset($class['tingkat']) ? (string) $class['tingkat'] : '';
        if ($level !== '' && $name !== '') {
            $keys[] = $this->normalizeKey($level . ' ' . $name);
            $keys[] = $this->normalizeKey($this->romanizeLevel($level) . ' ' . $name);
        }

        $major = isset($class['jurusan_nama']) ? (string) $class['jurusan_nama'] : null;
        if ($major !== null && $major !== '') {
            if ($name !== '') {
                $keys[] = $this->normalizeKey($name . ' ' . $major);
                if ($level !== '') {
                    $keys[] = $this->normalizeKey($level . ' ' . $name . ' ' . $major);
                    $keys[] = $this->normalizeKey($this->romanizeLevel($level) . ' ' . $name . ' ' . $major);
                }
            }
        }

        return array_values(array_unique($keys));
    }

    private function normalizeKey(string $value): string
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $value)));

        return $normalized;
    }

    private function romanizeLevel(string $value): string
    {
        $clean = trim($value);
        if ($clean === '') {
            return $clean;
        }

        if (ctype_digit($clean)) {
            $int = (int) $clean;
            return match ($int) {
                10 => 'X',
                11 => 'XI',
                12 => 'XII',
                default => $clean,
            };
        }

        return strtoupper($clean);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{0: ?array<string, mixed>, 1: ?string}
     */
    private function resolveTeacher(array $row): array
    {
        $idValue = $this->value($row, ['guru_id', 'id_guru']);
        if ($idValue !== '' && ctype_digit($idValue)) {
            $teacher = Teacher::find((int) $idValue);
            if ($teacher !== null) {
                return [$teacher, null];
            }
        }

        $nip = $this->value($row, ['guru_nip', 'nip']);
        if ($nip !== '') {
            $teacher = Teacher::findByNip($nip);
            if ($teacher !== null) {
                return [$teacher, null];
            }
        }

        $email = $this->value($row, ['guru_email', 'email']);
        if ($email !== '') {
            $teacher = Teacher::findByEmail($email);
            if ($teacher !== null) {
                return [$teacher, null];
            }
        }

        $name = $this->value($row, ['guru_nama', 'nama_guru'], true);
        if ($name !== null && $name !== '') {
            $matches = Teacher::findAllByNameInsensitive($name);
            if (count($matches) === 1) {
                return [$matches[0], null];
            }

            if (count($matches) > 1) {
                return [null, sprintf('nama guru "%s" ditemukan lebih dari satu. Gunakan NIP atau email.', $name)];
            }
        }

        return [null, null];
    }

    /**
     * @param array{lookup: array<string, int>, classes: array<int, array<string, mixed>>} $classIndex
     * @return array{0: array<int>, 1: ?string}
     */
    private function matchClasses(string $raw, array $classIndex): array
    {
        $parts = preg_split('/[,;]+/', $raw);
        if ($parts === false) {
            $parts = [$raw];
        }

        $lookup = $classIndex['lookup'];
        $classes = $classIndex['classes'];

        $matched = [];
        $missing = [];

        foreach ($parts as $part) {
            $label = trim((string) $part);
            if ($label === '') {
                continue;
            }

            $normalized = $this->normalizeKey($label);

            $classId = null;
            if (isset($lookup[$normalized])) {
                $classId = $lookup[$normalized];
            } elseif (ctype_digit($label) && isset($classes[(int) $label])) {
                $classId = (int) $label;
            }

            if ($classId === null) {
                $missing[] = $label;
                continue;
            }

            $matched[$classId] = true;
        }

        if (!empty($missing)) {
            return [[], sprintf('kelas berikut tidak ditemukan: %s.', implode(', ', $missing))];
        }

        return [array_keys($matched), null];
    }

    /**
     * @param array<string, mixed> $class
     */
    private function formatClassLabel(array $class): string
    {
        $level = isset($class['tingkat']) ? (string) $class['tingkat'] : '';
        $name = isset($class['nama']) ? (string) $class['nama'] : '';
        if ($level !== '' && $name !== '') {
            return sprintf('%s %s', $level, $name);
        }

        if ($name !== '') {
            return $name;
        }

        return 'ID ' . (int) ($class['id'] ?? 0);
    }
}

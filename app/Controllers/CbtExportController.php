<?php

namespace App\Controllers;

use App\Models\CbtStudentProfile;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Support\SimpleXlsxBuilder;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use ZipArchive;

class CbtExportController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $filters = $this->resolveFilters($request, true);
        $students = $this->fetchStudents($filters['class_id'], $filters['school_year_id'], $filters['keyword']);
        $studentIds = array_map(static fn (array $student) => (int) ($student['id'] ?? 0), $students);
        $profiles = CbtStudentProfile::mapByStudentIds($studentIds);
        $schoolYearOptions = SchoolYear::options();
        $classOptions = Classroom::options($filters['school_year_id'], $filters['class_id']);

        $rows = array_map(static function (array $student) use ($profiles): array {
            $studentId = (int) ($student['id'] ?? 0);
            $profile = $profiles[$studentId] ?? [];

            return [
                'id' => $studentId,
                'full_name' => (string) ($student['nama'] ?? ''),
                'nisn' => (string) ($student['nisn'] ?? ''),
                'class_name' => (string) ($student['kelas_nama'] ?? ''),
                'default_username' => (string) ($student['account_username'] ?? ''),
                'profile' => [
                    'username' => (string) ($profile['username'] ?? ''),
                    'password' => (string) ($profile['password'] ?? ''),
                    'exam_room' => (string) ($profile['exam_room'] ?? ''),
                    'exam_session' => (string) ($profile['exam_session'] ?? ''),
                ],
            ];
        }, $students);

        return $this->render('admin/cbt/export', [
            'title' => 'Export CBT',
            'pageTitle' => 'Export Data CBT',
            'activeMenu' => 'cbt-export',
            'students' => $rows,
            'schoolYearOptions' => $schoolYearOptions,
            'classOptions' => $classOptions,
            'selectedSchoolYearId' => $filters['school_year_id'],
            'selectedClassId' => $filters['class_id'],
            'keyword' => $filters['keyword'],
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/cbt/export')) {
            return $response;
        }

        $filters = $this->resolveFilters($request, true);
        $entries = $request->input('students', []);
        if (!is_array($entries)) {
            $entries = [];
        }

        $updated = 0;
        $failed = 0;

        foreach ($entries as $studentId => $payload) {
            $studentId = (int) $studentId;
            if ($studentId <= 0 || !is_array($payload)) {
                continue;
            }

            try {
                if (CbtStudentProfile::saveForStudent($studentId, [
                    'username' => $payload['username'] ?? null,
                    'password' => $payload['password'] ?? null,
                    'exam_room' => $payload['exam_room'] ?? null,
                    'exam_session' => $payload['exam_session'] ?? null,
                ])) {
                    $updated++;
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        if ($updated > 0) {
            Session::flash('success', "Konfigurasi CBT disimpan untuk {$updated} siswa.");
        } elseif ($failed === 0) {
            Session::flash('warning', 'Tidak ada perubahan yang disimpan.');
        }

        if ($failed > 0) {
            Session::flash('error', "Gagal menyimpan konfigurasi untuk {$failed} siswa.");
        }

        $query = $this->buildQueryString($filters);

        return $this->redirect('admin/cbt/export' . ($query !== '' ? ('?' . $query) : ''));
    }

    public function download(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $filters = $this->resolveFilters($request, true);
        $students = $this->fetchStudents($filters['class_id'], $filters['school_year_id'], $filters['keyword']);
        $studentIds = array_map(static fn (array $student) => (int) ($student['id'] ?? 0), $students);
        $profiles = CbtStudentProfile::mapByStudentIds($studentIds);

        $rows = [
            ['full_name', 'nisn', 'class_name', 'username', 'password', 'exam_room', 'exam_session'],
        ];

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            $profile = $profiles[$studentId] ?? [];

            $rows[] = [
                (string) ($student['nama'] ?? ''),
                (string) ($student['nisn'] ?? ''),
                $this->formatExportClassName((string) ($student['kelas_nama'] ?? '')),
                (string) ($profile['username'] ?? ''),
                (string) ($profile['password'] ?? ''),
                (string) ($profile['exam_room'] ?? ''),
                (string) ($profile['exam_session'] ?? ''),
            ];
        }

        $xlsxContent = SimpleXlsxBuilder::build($rows, 'CBT Export');

        if ($xlsxContent === '') {
            Session::flash('error', 'Gagal membuat file XLSX.');

            $query = $this->buildQueryString($filters);

            return $this->redirect('admin/cbt/export' . ($query !== '' ? ('?' . $query) : ''));
        }

        $filename = 'cbt-export-' . date('Ymd-His') . '.xlsx';

        return Response::make($xlsxContent, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function downloadPhotos(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $filters = $this->resolveFilters($request, true);
        $scope = strtolower(trim((string) $request->query('photo_scope', 'all')));
        if (!in_array($scope, ['all', 'class'], true)) {
            $scope = 'all';
        }

        $classId = $scope === 'class' ? $filters['class_id'] : null;
        if ($scope === 'class' && $classId === null) {
            Session::flash('error', 'Pilih kelas terlebih dahulu untuk ekspor foto per kelas.');

            $query = $this->buildQueryString($filters);

            return $this->redirect('admin/cbt/export' . ($query !== '' ? ('?' . $query) : ''));
        }

        $students = $this->fetchStudents($classId, $filters['school_year_id'], '');
        $photos = [];
        $usedNames = [];

        foreach ($students as $student) {
            $path = trim((string) ($student['foto_path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $absolute = public_path($path);
            if (!is_file($absolute)) {
                continue;
            }

            $nisn = preg_replace('/[^0-9]/', '', (string) ($student['nisn'] ?? '')) ?? '';
            if ($nisn === '') {
                continue;
            }

            $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'png'], true)) {
                continue;
            }

            $zipFilename = $nisn . '.' . $extension;
            if (isset($usedNames[$zipFilename])) {
                $usedNames[$zipFilename]++;
                $zipFilename = sprintf('%s-%d.%s', $nisn, $usedNames[$zipFilename], $extension);
            } else {
                $usedNames[$zipFilename] = 1;
            }

            $photos[] = [
                'absolute' => $absolute,
                'zip_filename' => $zipFilename,
            ];
        }

        if (empty($photos)) {
            Session::flash('warning', 'Tidak ada foto siswa yang dapat diekspor untuk filter yang dipilih.');

            $query = $this->buildQueryString($filters);

            return $this->redirect('admin/cbt/export' . ($query !== '' ? ('?' . $query) : ''));
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'cbt-foto-zip-');
        if ($tempFile === false) {
            Session::flash('error', 'Gagal menyiapkan arsip ZIP foto siswa.');

            $query = $this->buildQueryString($filters);

            return $this->redirect('admin/cbt/export' . ($query !== '' ? ('?' . $query) : ''));
        }

        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::OVERWRITE) !== true) {
            @unlink($tempFile);
            Session::flash('error', 'Gagal membuat arsip ZIP foto siswa.');

            $query = $this->buildQueryString($filters);

            return $this->redirect('admin/cbt/export' . ($query !== '' ? ('?' . $query) : ''));
        }

        foreach ($photos as $photo) {
            $zip->addFile($photo['absolute'], $photo['zip_filename']);
        }
        $zip->close();

        $contents = @file_get_contents($tempFile);
        @unlink($tempFile);

        if ($contents === false) {
            Session::flash('error', 'Gagal membaca arsip ZIP foto siswa.');

            $query = $this->buildQueryString($filters);

            return $this->redirect('admin/cbt/export' . ($query !== '' ? ('?' . $query) : ''));
        }

        $filenameParts = ['foto-siswa'];
        if ($scope === 'class' && $classId !== null) {
            $classLabel = $this->resolveClassLabel($filters['school_year_id'], $classId);
            if ($classLabel !== '') {
                $filenameParts[] = $classLabel;
            }
        } else {
            $filenameParts[] = 'semua-kelas';
        }
        $filenameParts[] = date('Ymd-His');
        $filename = implode('-', $filenameParts) . '.zip';

        return Response::make($contents, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function fetchStudents(?int $classId, ?int $schoolYearId, string $keyword): array
    {
        return Student::forCbt($classId, $schoolYearId, $keyword);
    }

    /**
     * @return array{school_year_id: ?int, class_id: ?int, keyword: string}
     */
    private function resolveFilters(Request $request, bool $fallbackToActive): array
    {
        $rawYear = $request->input('school_year_id', null);
        $rawClass = $request->input('class_id', null);
        $keyword = trim((string) $request->input('q', ''));

        $schoolYearId = $this->parseNumericFilter($rawYear);
        if ($fallbackToActive && $rawYear === null && $schoolYearId === null) {
            $activeYear = SchoolYear::active();
            if ($activeYear !== null) {
                $schoolYearId = (int) $activeYear['id'];
            }
        }

        $classId = $this->parseNumericFilter($rawClass);

        return [
            'school_year_id' => $schoolYearId,
            'class_id' => $classId,
            'keyword' => $keyword,
        ];
    }

    private function parseNumericFilter(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || strtolower($value) === 'all') {
                return null;
            }
        }

        if (is_numeric($value)) {
            $int = (int) $value;

            return $int > 0 ? $int : null;
        }

        return null;
    }

    /**
     * @param array{school_year_id:?int,class_id:?int,keyword:string} $filters
     */
    private function buildQueryString(array $filters): string
    {
        $params = [];

        $params['school_year_id'] = $filters['school_year_id'] !== null
            ? $filters['school_year_id']
            : 'all';

        if ($filters['class_id'] !== null) {
            $params['class_id'] = $filters['class_id'];
        }

        if ($filters['keyword'] !== '') {
            $params['q'] = $filters['keyword'];
        }

        return empty($params) ? '' : http_build_query($params);
    }

    private function resolveClassLabel(?int $schoolYearId, int $classId): string
    {
        if ($classId <= 0) {
            return '';
        }

        $options = Classroom::options($schoolYearId, $classId);
        $label = trim((string) ($options[$classId] ?? ''));
        if ($label === '') {
            return '';
        }

        $slug = strtolower($label);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    private function formatExportClassName(string $className): string
    {
        $normalized = trim($className);
        if ($normalized === '') {
            $normalized = 'Belum Ditentukan';
        }

        return preg_replace('/\s+/', '', $normalized) ?? '';
    }
}

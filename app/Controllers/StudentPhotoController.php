<?php

namespace App\Controllers;

use App\Models\Student;
use App\Services\ManagedFileStorage;
use App\Traits\ManagesStudentFileAccess;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use ZipArchive;

class StudentPhotoController extends Controller
{
    use ManagesStudentFileAccess;

    private const MAX_PHOTO_SIZE = 1048576;
    private const ALLOWED_PHOTO_EXTENSIONS = ['jpg', 'png'];
    private const ALLOWED_PHOTO_MIME_TYPES = ['image/jpeg', 'image/png'];

    protected ?string $layout = 'admin';

    public function storeSelf(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'siswa/data-diri/foto')) {
            return $response;
        }

        $user = auth();
        if (!is_array($user) || ($user['role'] ?? '') !== 'siswa' || empty($user['student_id'])) {
            Session::flash('error', 'Upload foto mandiri hanya tersedia untuk siswa.');

            return $this->redirect('dashboard');
        }

        $studentId = (int) $user['student_id'];
        $student = Student::findWithRelations($studentId);

        if ($student === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');

            return $this->redirect('dashboard');
        }

        $file = $request->file('foto');
        if ($file === null) {
            Session::flash('error', 'Pilih berkas foto terlebih dahulu.');

            return $this->redirect('siswa/data-diri');
        }

        $result = $this->processUploadedPhoto($file, $student);

        if ($result === null) {
            return $this->redirect('siswa/data-diri');
        }

        if (!Student::updatePhoto($studentId, $result)) {
            $this->deletePhotoFile($result);
            Session::flash('error', 'Gagal menyimpan foto siswa.');

            return $this->redirect('siswa/data-diri');
        }

        Session::flash('success', 'Foto berhasil diperbarui.');

        return $this->redirect('siswa/data-diri');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $redirectPath = $this->resolveRedirectPath($request, 'master/siswa');

        if ($response = $this->guardCsrf($request, $redirectPath)) {
            return $response;
        }

        $context = $this->resolveAccessibleStudentsForFileManagement('Anda tidak memiliki hak untuk mengelola foto siswa.');
        if ($context === null) {
            return $this->redirect($redirectPath);
        }

        $studentId = (int) $request->input('student_id', 0);
        if ($studentId <= 0 || !isset($context['byId'][$studentId])) {
            Session::flash('error', 'Siswa tidak ditemukan atau tidak dalam cakupan akses Anda.');
            return $this->redirect($redirectPath);
        }

        $file = $request->file('foto');
        if ($file === null) {
            Session::flash('error', 'Pilih berkas foto terlebih dahulu.');
            return $this->redirect($redirectPath);
        }

        $result = $this->processUploadedPhoto($file, $context['byId'][$studentId]);

        if ($result === null) {
            return $this->redirect($redirectPath);
        }

        if (!Student::updatePhoto($studentId, $result)) {
            Session::flash('error', 'Gagal menyimpan foto siswa.');
            return $this->redirect($redirectPath);
        }

        Session::flash('success', 'Foto siswa berhasil diperbarui.');

        return $this->redirect($redirectPath);
    }

    public function bulkIndex(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $context = $this->resolveAccessibleStudentsForFileManagement('Anda tidak memiliki hak untuk mengelola foto siswa.');
        if ($context === null) {
            return $this->redirect('master/siswa');
        }

        $students = array_values($context['byId']);
        usort($students, static fn (array $left, array $right): int => strcmp((string) ($left['nama'] ?? ''), (string) ($right['nama'] ?? '')));

        $studentsWithoutPhoto = array_values(array_filter($students, static fn (array $student): bool => trim((string) ($student['foto_path'] ?? '')) === ''));
        $user = auth();
        $role = (string) ($user['role'] ?? '');

        return $this->render('master/students/photo-bulk', [
            'title' => 'Upload Foto Massal',
            'pageTitle' => 'Upload Foto Massal',
            'activeMenu' => $role === 'admin' ? 'students' : 'homeroom-students',
            'students' => $students,
            'studentsWithoutPhoto' => $studentsWithoutPhoto,
            'returnTo' => 'master/siswa/foto/massal',
        ], 'admin');
    }

    public function bulk(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $redirectPath = $this->resolveRedirectPath($request, 'master/siswa/foto/massal');

        if ($response = $this->guardCsrf($request, $redirectPath)) {
            return $response;
        }

        $context = $this->resolveAccessibleStudentsForFileManagement('Anda tidak memiliki hak untuk mengelola foto siswa.');
        if ($context === null) {
            return $this->redirect($redirectPath);
        }

        $zipFile = $request->file('foto_zip');
        if ($zipFile === null) {
            Session::flash('error', 'Pilih berkas ZIP yang berisi foto siswa.');
            return $this->redirect($redirectPath);
        }

        $zipError = (int) ($zipFile['error'] ?? \UPLOAD_ERR_NO_FILE);
        if ($zipError !== \UPLOAD_ERR_OK) {
            Session::flash('error', 'Gagal mengunggah berkas ZIP: ' . $this->describeUploadError($zipError, 'berkas ZIP'));
            return $this->redirect($redirectPath);
        }

        $tmpPath = (string) ($zipFile['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            Session::flash('error', 'Berkas ZIP tidak valid atau tidak diterima dengan benar oleh server.');
            return $this->redirect($redirectPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            Session::flash('error', 'Berkas ZIP tidak dapat dibuka. Pastikan file tidak rusak dan benar-benar berformat ZIP.');
            return $this->redirect($redirectPath);
        }

        $success = 0;
        $skipped = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);
            if ($entry === false) {
                continue;
            }

            $name = $entry['name'] ?? '';
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($extension, self::ALLOWED_PHOTO_EXTENSIONS, true)) {
                $skipped[] = sprintf('%s (format tidak didukung)', $name);
                continue;
            }

            $identifier = strtolower(trim(pathinfo($name, PATHINFO_FILENAME)));
            if ($identifier === '') {
                $skipped[] = sprintf('%s (nama berkas tidak valid)', $name);
                continue;
            }

            $studentId = $context['byNisn'][$identifier] ?? $context['byNipd'][$identifier] ?? null;
            if ($studentId === null || !isset($context['byId'][$studentId])) {
                $skipped[] = sprintf('%s (siswa tidak ditemukan)', $name);
                continue;
            }

            $contents = $zip->getFromIndex($i);
            if ($contents === false || $contents === '') {
                $skipped[] = sprintf('%s (tidak dapat dibaca)', $name);
                continue;
            }

            if (strlen($contents) > self::MAX_PHOTO_SIZE) {
                $skipped[] = sprintf('%s (lebih dari 1 MB)', $name);
                continue;
            }

            if (!$this->isValidImage($contents)) {
                $skipped[] = sprintf('%s (format harus PNG atau JPG)', $name);
                continue;
            }

            $stored = $this->storePhotoContents($contents, $extension, $context['byId'][$studentId]);

            if ($stored === null) {
                $skipped[] = sprintf('%s (gagal menyimpan)', $name);
                continue;
            }

            if (!Student::updatePhoto($studentId, $stored)) {
                $this->deletePhotoFile($stored);
                $skipped[] = sprintf('%s (gagal memperbarui data)', $name);
                continue;
            }

            $success++;
            $context['byId'][$studentId]['foto_path'] = $stored;
        }

        $zip->close();

        if ($success > 0) {
            $message = sprintf('Berhasil memperbarui foto untuk %d siswa.', $success);
            if (!empty($skipped)) {
                $message .= ' Beberapa berkas dilewati: ' . implode('; ', array_slice($skipped, 0, 10));
                if (count($skipped) > 10) {
                    $message .= sprintf(' dan %d lainnya.', count($skipped) - 10);
                }
            }
            Session::flash('success', $message);
        } else {
            $message = 'Tidak ada foto yang berhasil diperbarui.';
            if (!empty($skipped)) {
                $message .= ' Alasan: ' . implode('; ', array_slice($skipped, 0, 10));
                if (count($skipped) > 10) {
                    $message .= sprintf(' dan %d lainnya.', count($skipped) - 10);
                }
            }
            Session::flash('warning', $message);
        }

        return $this->redirect($redirectPath);
    }

    public function export(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $context = $this->resolveAccessibleStudentsForFileManagement('Anda tidak memiliki hak untuk mengekspor foto siswa.');
        if ($context === null) {
            return $this->redirect('master/siswa');
        }

        $photos = [];
        $usedNames = [];

        foreach ($context['byId'] as $student) {
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
            if (!in_array($extension, self::ALLOWED_PHOTO_EXTENSIONS, true)) {
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
            Session::flash('warning', 'Tidak ada foto siswa yang dapat diekspor.');

            return $this->redirect('master/siswa');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'foto-siswa-zip-');
        if ($tempFile === false) {
            Session::flash('error', 'Gagal menyiapkan arsip ZIP foto siswa.');

            return $this->redirect('master/siswa');
        }

        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::OVERWRITE) !== true) {
            @unlink($tempFile);
            Session::flash('error', 'Gagal membuat arsip ZIP foto siswa.');

            return $this->redirect('master/siswa');
        }

        foreach ($photos as $photo) {
            $zip->addFile($photo['absolute'], $photo['zip_filename']);
        }
        $zip->close();

        $contents = @file_get_contents($tempFile);
        @unlink($tempFile);

        if ($contents === false) {
            Session::flash('error', 'Gagal membaca arsip ZIP foto siswa.');

            return $this->redirect('master/siswa');
        }

        $filename = 'foto-siswa-' . date('Ymd-His') . '.zip';

        return Response::make($contents, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $student
     */
    private function processUploadedPhoto(array $file, array $student): ?string
    {
        $error = (int) ($file['error'] ?? \UPLOAD_ERR_NO_FILE);
        if ($error !== \UPLOAD_ERR_OK) {
            Session::flash('error', 'Gagal mengunggah foto siswa: ' . $this->describeUploadError($error, 'foto siswa'));
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            Session::flash('error', 'Berkas foto tidak valid atau tidak diterima dengan benar oleh server.');
            return null;
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, self::ALLOWED_PHOTO_EXTENSIONS, true)) {
            Session::flash('error', 'Format foto harus PNG atau JPG.');
            return null;
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_PHOTO_SIZE) {
            Session::flash('error', 'Ukuran foto maksimal 1 MB.');
            return null;
        }

        $binary = file_get_contents($tmpName);
        if ($binary === false || !$this->isValidImage($binary)) {
            Session::flash('error', 'Berkas foto harus berupa PNG atau JPG yang valid.');
            return null;
        }

        return $this->storePhotoContents($binary, $extension, $student);
    }

    /**
     * @param array<string, mixed> $student
     */
    private function storePhotoContents(string $binary, string $extension, array $student): ?string
    {
        $stored = ManagedFileStorage::storePublicContents($binary, 'data-siswa', 'foto-siswa', 'siswa-' . (int) ($student['id'] ?? 0), $extension, [
            'existing_path' => $student['foto_path'] ?? null,
            'related_type' => 'siswa',
            'related_id' => (int) ($student['id'] ?? 0),
            'original_name' => 'foto-siswa-' . (int) ($student['id'] ?? 0) . '.' . $extension,
        ]);

        if ($stored === null) {
            Session::flash('error', 'Gagal menyimpan foto siswa.');
            return null;
        }

        return $stored;
    }

    private function deletePhotoFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        ManagedFileStorage::deletePublic($path);
    }

    private function isValidImage(string $binary): bool
    {
        if ($binary === '') {
            return false;
        }

        $info = @getimagesizefromstring($binary);
        if ($info === false) {
            return false;
        }

        $mimeType = strtolower((string) ($info['mime'] ?? ''));

        return in_array($mimeType, self::ALLOWED_PHOTO_MIME_TYPES, true);
    }

    private function resolveRedirectPath(Request $request, string $default): string
    {
        $candidate = trim((string) $request->input('redirect_to', ''));

        if ($candidate === '') {
            return $default;
        }

        if (!preg_match('#^[a-z0-9\-/]+(\?[a-z0-9_\-=&%]+)?$#i', $candidate)) {
            return $default;
        }

        if (!str_starts_with($candidate, 'master/siswa')) {
            return $default;
        }

        return $candidate;
    }

    private function describeUploadError(int $errorCode, string $label): string
    {
        return match ($errorCode) {
            \UPLOAD_ERR_INI_SIZE => sprintf('%s melebihi batas ukuran upload server.', $label),
            \UPLOAD_ERR_FORM_SIZE => sprintf('%s melebihi batas ukuran yang diizinkan form.', $label),
            \UPLOAD_ERR_PARTIAL => sprintf('%s hanya terunggah sebagian. Kemungkinan koneksi terputus saat proses upload.', $label),
            \UPLOAD_ERR_NO_FILE => sprintf('%s belum dipilih.', $label),
            \UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara upload di server tidak tersedia.',
            \UPLOAD_ERR_CANT_WRITE => sprintf('Server gagal menyimpan %s ke media penyimpanan sementara.', $label),
            \UPLOAD_ERR_EXTENSION => sprintf('Upload %s dihentikan oleh konfigurasi ekstensi PHP di server.', $label),
            default => sprintf('terjadi kesalahan upload dengan kode %d pada %s.', $errorCode, $label),
        };
    }
}

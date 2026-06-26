<?php

namespace App\Controllers;

use App\Models\Student;
use App\Services\ManagedFileStorage;
use App\Support\StudentDocumentFields;
use App\Traits\ManagesStudentFileAccess;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use ZipArchive;

class StudentDocumentController extends Controller
{
    use ManagesStudentFileAccess;

    protected ?string $layout = 'admin';

    public function selfIndex(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $student = $this->resolveSelfStudent();
        if ($student === null) {
            return $this->redirect('dashboard');
        }

        $fields = StudentDocumentFields::all();
        $statuses = [];

        foreach ($fields as $key => $definition) {
            $path = trim((string) ($student[$definition['column']] ?? ''));
            $statuses[$key] = [
                'label' => $definition['label'],
                'input' => $definition['input'],
                'path' => $path,
                'is_complete' => $path !== '',
            ];
        }

        return $this->render('student/documents/index', [
            'title' => 'Berkas Fisik Saya',
            'pageTitle' => 'Berkas Fisik Saya',
            'activeMenu' => 'student-documents',
            'student' => $student,
            'documentFields' => $fields,
            'documentStatuses' => $statuses,
        ], 'admin');
    }

    public function storeSelf(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'siswa/berkas')) {
            return $response;
        }

        $student = $this->resolveSelfStudent();
        if ($student === null) {
            return $this->redirect('dashboard');
        }

        return $this->storeForStudent($request, $student, 'siswa/berkas');
    }

    public function downloadSelf(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'siswa/berkas/unduh')) {
            return $response;
        }

        $student = $this->resolveSelfStudent();
        if ($student === null) {
            return $this->redirect('dashboard');
        }

        $documentKey = (string) $request->input('document_key', '');
        $fields = StudentDocumentFields::all();

        if ($documentKey !== 'all' && !isset($fields[$documentKey])) {
            Session::flash('error', 'Jenis dokumen tidak valid.');

            return $this->redirect('siswa/berkas');
        }

        if ($documentKey === 'all') {
            return $this->downloadAllDocuments($student, $fields, 'siswa/berkas');
        }

        return $this->downloadSingleDocument($student, $documentKey, $fields, 'siswa/berkas');
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

        $context = $this->resolveAccessibleStudentsForFileManagement('Anda tidak memiliki hak untuk mengelola data fisik siswa.');
        if ($context === null) {
            return $this->redirect($redirectPath);
        }

        $studentId = (int) $request->input('student_id', 0);
        if ($studentId <= 0 || !isset($context['byId'][$studentId])) {
            Session::flash('error', 'Siswa tidak ditemukan atau berada di luar akses Anda.');

            return $this->redirect($redirectPath);
        }

        $student = $context['byId'][$studentId];
        return $this->storeForStudent($request, $student, $redirectPath);
    }

    /**
     * Handle secure download of stored documents.
     */
    public function download(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $redirectPath = $this->resolveRedirectPath($request, 'master/siswa');

        if ($response = $this->guardCsrf($request, $redirectPath)) {
            return $response;
        }

        $context = $this->resolveAccessibleStudentsForFileManagement('Anda tidak memiliki hak untuk mengunduh data fisik siswa.');
        if ($context === null) {
            return $this->redirect($redirectPath);
        }

        $studentId = (int) $request->input('student_id', 0);
        if ($studentId <= 0 || !isset($context['byId'][$studentId])) {
            Session::flash('error', 'Siswa tidak ditemukan atau berada di luar akses Anda.');

            return $this->redirect($redirectPath);
        }

        $documentKey = (string) $request->input('document_key', '');
        $fields = StudentDocumentFields::all();

        if ($documentKey !== 'all' && !isset($fields[$documentKey])) {
            Session::flash('error', 'Jenis dokumen tidak valid.');

            return $this->redirect($redirectPath);
        }

        if ($documentKey === 'all') {
            return $this->downloadAllDocuments($context['byId'][$studentId], $fields, $redirectPath);
        }

        return $this->downloadSingleDocument($context['byId'][$studentId], $documentKey, $fields, $redirectPath);
    }

    /**
     * @param array<string, mixed> $student
     */
    private function storeForStudent(Request $request, array $student, string $redirectPath): Response
    {
        $studentId = (int) ($student['id'] ?? 0);
        $files = $request->files();
        $fields = StudentDocumentFields::all();

        $updates = [];
        $storedPaths = [];
        $previousPaths = [];
        $updatedLabels = [];

        foreach ($fields as $key => $definition) {
            $inputName = $definition['input'];
            $upload = is_array($files) ? ($files[$inputName] ?? null) : null;

            if (!is_array($upload) || (int) ($upload['error'] ?? \UPLOAD_ERR_NO_FILE) === \UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $stored = $this->storeDocumentFile(
                $upload,
                $key,
                $definition['label'],
                $student
            );

            if ($stored === null) {
                foreach ($storedPaths as $path) {
                    $this->deleteStoredFile($path);
                }

                return $this->redirect($redirectPath);
            }

            $column = $definition['column'];
            $updates[$column] = $stored;
            $storedPaths[] = $stored;
            $previousPaths[] = $student[$column] ?? null;
            $updatedLabels[] = $definition['label'];
        }

        if (empty($updates)) {
            Session::flash('warning', 'Pilih minimal satu berkas untuk diunggah.');

            return $this->redirect($redirectPath);
        }

        if ($studentId <= 0 || !Student::updateDocuments($studentId, $updates)) {
            foreach ($storedPaths as $path) {
                $this->deleteStoredFile($path);
            }

            Session::flash('error', 'Gagal menyimpan dokumen fisik siswa.');

            return $this->redirect($redirectPath);
        }

        foreach ($previousPaths as $path) {
            $this->deleteStoredFile($path);
        }

        $summary = implode(', ', $updatedLabels);
        Session::flash('success', 'Dokumen fisik diperbarui: ' . $summary . '.');

        return $this->redirect($redirectPath);
    }

    /**
     * @param array<string, mixed> $student
     * @param array<string, array<string, string>> $fields
     */
    private function downloadSingleDocument(array $student, string $documentKey, array $fields, string $redirectPath): Response
    {
        $column = $fields[$documentKey]['column'];
        $path = trim((string) ($student[$column] ?? ''));

        if ($path === '') {
            Session::flash('error', sprintf('Dokumen %s belum tersedia.', $fields[$documentKey]['label']));

            return $this->redirect($redirectPath);
        }

        $absolute = public_path($path);
        if (!is_file($absolute)) {
            Session::flash('error', 'Berkas dokumen tidak ditemukan di server.');

            return $this->redirect($redirectPath);
        }

        $contents = @file_get_contents($absolute);
        if ($contents === false) {
            Session::flash('error', 'Gagal membaca berkas dokumen.');

            return $this->redirect($redirectPath);
        }

        $filename = basename($absolute);
        $mime = mime_content_type($absolute) ?: 'application/octet-stream';

        return Response::make($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSelfStudent(): ?array
    {
        $user = auth();
        if (!is_array($user) || ($user['role'] ?? '') !== 'siswa' || empty($user['student_id'])) {
            Session::flash('error', 'Menu berkas fisik hanya tersedia untuk siswa.');

            return null;
        }

        $student = Student::findWithRelations((int) $user['student_id']);
        if ($student === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');

            return null;
        }

        return $student;
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $student
     */
    private function storeDocumentFile(array $file, string $fieldKey, string $label, array $student): ?string
    {
        $error = (int) ($file['error'] ?? \UPLOAD_ERR_NO_FILE);
        if ($error !== \UPLOAD_ERR_OK) {
            Session::flash('error', sprintf('Gagal mengunggah %s. Pastikan ukuran file tidak melebihi 10 MB.', $label));

            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            Session::flash('error', sprintf('Berkas %s tidak valid.', $label));

            return null;
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

        if ($extension === '' || !in_array($extension, $allowed, true)) {
            Session::flash('error', sprintf('%s harus berformat PDF, JPG, JPEG, PNG, atau WEBP.', $label));

            return null;
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 10 * 1024 * 1024) {
            Session::flash('error', sprintf('Ukuran %s maksimal 10 MB.', $label));

            return null;
        }

        $studentName = trim((string) ($student['nama'] ?? ''));
        $studentNisn = trim((string) ($student['nisn'] ?? ''));
        $nameToken = $this->slugifyFileToken($studentName, '_');
        if ($nameToken === '') {
            $nameToken = 'tanpa_nama';
        }
        $nisnToken = preg_replace('/[^0-9]/', '', $studentNisn) ?? '';
        if ($nisnToken === '') {
            $nisnToken = 'tanpa-nisn';
        }

        $prefix = sprintf('%s-%s-%s', $fieldKey, $nameToken, $nisnToken);
        $stored = ManagedFileStorage::storeUploadedPublic($file, 'data-siswa', 'dokumen-fisik', $prefix, $extension, [
            'related_type' => 'siswa',
            'related_id' => (int) ($student['id'] ?? 0),
        ]);

        if ($stored === null) {
            Session::flash('error', sprintf('Gagal menyimpan %s.', $label));

            return null;
        }

        return $stored;
    }

    private function slugifyFileToken(string $value, string $separator = '-'): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', $separator, $value) ?? '';

        return trim($value, $separator);
    }

    private function deleteStoredFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        ManagedFileStorage::deletePublic($path);
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

        if (!str_starts_with($candidate, 'master/siswa') && !str_starts_with($candidate, 'siswa/berkas')) {
            return $default;
        }

        return $candidate;
    }

    /**
     * @param array<string, mixed> $student
     * @param array<string, array{label: string, column: string, input: string}> $fields
     */
    private function downloadAllDocuments(array $student, array $fields, string $redirectPath = 'master/siswa'): Response
    {
        $documents = [];
        foreach ($fields as $key => $definition) {
            $column = $definition['column'];
            $path = trim((string) ($student[$column] ?? ''));
            if ($path === '') {
                continue;
            }

            $absolute = public_path($path);
            if (!is_file($absolute)) {
                continue;
            }

            $documents[] = [
                'path' => $absolute,
                'label' => $definition['label'],
                'key' => $key,
            ];
        }

        if (empty($documents)) {
            Session::flash('error', 'Tidak ada dokumen yang dapat diunduh untuk siswa ini.');

            return $this->redirect($redirectPath);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'fisik-zip-');
        if ($tempFile === false) {
            Session::flash('error', 'Gagal menyiapkan arsip ZIP.');

            return $this->redirect($redirectPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::OVERWRITE) !== true) {
            @unlink($tempFile);
            Session::flash('error', 'Gagal membuat arsip ZIP.');

            return $this->redirect($redirectPath);
        }

        foreach ($documents as $document) {
            $filename = sprintf(
                '%s-%s.%s',
                preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) ($student['nama'] ?? 'siswa'))),
                $document['key'],
                pathinfo($document['path'], PATHINFO_EXTENSION)
            );
            $zip->addFile($document['path'], $filename);
        }
        $zip->close();

        $contents = @file_get_contents($tempFile);
        @unlink($tempFile);

        if ($contents === false) {
            Session::flash('error', 'Gagal membaca arsip ZIP.');

            return $this->redirect($redirectPath);
        }

        $studentName = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) ($student['nama'] ?? 'siswa')));
        $zipName = sprintf('data-fisik-%s-%s.zip', $studentName, date('YmdHis'));

        return Response::make($contents, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $zipName . '"',
        ]);
    }
}

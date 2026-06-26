<?php

namespace App\Controllers;

use App\Models\SchoolProfile;
use App\Services\ManagedFileStorage;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class SchoolProfileController extends Controller
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

        $schools = SchoolProfile::allOrdered();
        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? SchoolProfile::find($editId) : null;

        if ($editing === null && !empty($schools)) {
            $editing = SchoolProfile::first();
        }

        return $this->render('master/schools/index', [
            'title' => 'Profil Sekolah',
            'pageTitle' => 'Master Sekolah',
            'activeMenu' => 'schools',
            'schools' => $schools,
            'editingSchool' => $editing,
            'limitReached' => !empty($schools),
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

        if ($response = $this->guardCsrf($request, 'master/sekolah')) {
            return $response;
        }

        if (SchoolProfile::count() > 0) {
            Session::flash('error', 'Hanya boleh ada satu profil sekolah. Gunakan formulir untuk memperbarui data yang sudah ada.');

            return $this->redirect('master/sekolah');
        }

        $payload = $this->validate($request);

        if ($payload === null) {
            return $this->redirect('master/sekolah');
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            SchoolProfile::create($payload);
            Session::flash('success', 'Profil sekolah berhasil ditambahkan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menambahkan profil sekolah: ' . $exception->getMessage());
        }

        return $this->redirect('master/sekolah');
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/sekolah')) {
            return $response;
        }

        $school = SchoolProfile::find($id);

        if ($school === null) {
            Session::flash('error', 'Profil sekolah tidak ditemukan.');

            return $this->redirect('master/sekolah');
        }

        $payload = $this->validate($request, false, $id, $school);

        if ($payload === null) {
            return $this->redirect('master/sekolah?edit=' . urlencode((string) $id));
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            SchoolProfile::updateById($id, $payload);
            Session::flash('success', 'Profil sekolah berhasil diperbarui.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui profil sekolah: ' . $exception->getMessage());
        }

        return $this->redirect('master/sekolah');
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/sekolah')) {
            return $response;
        }

        $school = SchoolProfile::find($id);

        if ($school === null) {
            Session::flash('error', 'Profil sekolah tidak ditemukan.');

            return $this->redirect('master/sekolah');
        }

        try {
            SchoolProfile::deleteById($id);
            Session::flash('success', 'Profil sekolah dihapus.');

            $this->deleteLogoFile($school['logo_sekolah'] ?? null);
            $this->deleteLogoFile($school['logo_dinas'] ?? null);
            $this->deleteLogoFile($school['lambang_negara'] ?? null);
            $this->deleteLogoFile($school['app_icon'] ?? null);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus profil sekolah: ' . $exception->getMessage());
        }

        return $this->redirect('master/sekolah');
    }

    /**
     * @param array<string, mixed>|null $existing
     */
    private function validate(Request $request, bool $isCreate = true, ?int $ignoreId = null, ?array $existing = null): ?array
    {
        SchoolProfile::ensureSchema();

        $latitudeRaw = trim((string) $request->input('latitude', ''));
        $longitudeRaw = trim((string) $request->input('longitude', ''));
        $radiusRaw = trim((string) $request->input('presensi_radius_meter', ''));

        $data = [
            'nama' => trim((string) $request->input('nama', '')),
            'npsn' => trim((string) $request->input('npsn', '')),
            'nss' => trim((string) $request->input('nss', '')),
            'alamat' => trim((string) $request->input('alamat', '')),
            'desa' => trim((string) $request->input('desa', '')),
            'kecamatan' => trim((string) $request->input('kecamatan', '')),
            'kabupaten' => trim((string) $request->input('kabupaten', '')),
            'provinsi' => trim((string) $request->input('provinsi', '')),
            'kode_pos' => trim((string) $request->input('kode_pos', '')),
            'telepon' => trim((string) $request->input('telepon', '')),
            'email' => trim((string) $request->input('email', '')),
            'website' => trim((string) $request->input('website', '')),
            'akreditasi' => trim((string) $request->input('akreditasi', '')),
            'latitude' => $latitudeRaw,
            'longitude' => $longitudeRaw,
            'presensi_radius_meter' => $radiusRaw,
        ];

        if ($data['nama'] === '') {
            Session::flash('error', 'Nama sekolah wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['npsn'] !== '' && SchoolProfile::exists(['npsn' => $data['npsn']], $ignoreId)) {
            Session::flash('error', 'NPSN sudah terdaftar.');
            Session::flashInput($request->all());

            return null;
        }

        if (SchoolProfile::exists(['nama' => $data['nama']], $ignoreId)) {
            Session::flash('error', 'Nama sekolah sudah terdaftar.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['email'] !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            Session::flash('error', 'Format email sekolah tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['website'] !== '' && filter_var($data['website'], FILTER_VALIDATE_URL) === false) {
            Session::flash('error', 'Format website sekolah tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['akreditasi'] !== '') {
            $data['akreditasi'] = strtoupper($data['akreditasi']);
            if (!preg_match('/^[A-E](?:\+|-)?$/', $data['akreditasi'])) {
                Session::flash('error', 'Nilai akreditasi tidak valid.');
                Session::flashInput($request->all());

                return null;
            }
        } else {
            $data['akreditasi'] = null;
        }

        $locationProvided = ($latitudeRaw !== '' || $longitudeRaw !== '' || $radiusRaw !== '');

        if ($locationProvided) {
            if ($latitudeRaw === '' || $longitudeRaw === '') {
                Session::flash('error', 'Isi koordinat latitude dan longitude untuk lokasi sekolah.');
                Session::flashInput($request->all());

                return null;
            }

            if (!is_numeric($latitudeRaw) || !is_numeric($longitudeRaw)) {
                Session::flash('error', 'Koordinat lokasi sekolah harus berupa angka.');
                Session::flashInput($request->all());

                return null;
            }

            $latitude = (float) $latitudeRaw;
            $longitude = (float) $longitudeRaw;

            if ($latitude < -90.0 || $latitude > 90.0) {
                Session::flash('error', 'Nilai latitude harus berada di antara -90 dan 90.');
                Session::flashInput($request->all());

                return null;
            }

            if ($longitude < -180.0 || $longitude > 180.0) {
                Session::flash('error', 'Nilai longitude harus berada di antara -180 dan 180.');
                Session::flashInput($request->all());

                return null;
            }

            $radius = $radiusRaw !== '' ? (int) $radiusRaw : 0;

            if ($radius <= 0) {
                Session::flash('error', 'Radius validasi presensi harus lebih dari nol meter.');
                Session::flashInput($request->all());

                return null;
            }

            $data['latitude'] = $latitude;
            $data['longitude'] = $longitude;
            $data['presensi_radius_meter'] = $radius;
        }

        foreach (['npsn', 'nss', 'alamat', 'desa', 'kecamatan', 'kabupaten', 'provinsi', 'kode_pos', 'telepon', 'email', 'website'] as $field) {
            if ($data[$field] === '') {
                $data[$field] = null;
            }
        }

        if (!$locationProvided) {
            $data['latitude'] = null;
            $data['longitude'] = null;
            $data['presensi_radius_meter'] = null;
        }

        $files = $request->files();

        $logoSekolah = $this->processLogoUpload($files['logo_sekolah'] ?? null, 'logo-sekolah', $existing['logo_sekolah'] ?? null, $request);
        if ($logoSekolah === null && $isCreate) {
            $this->flashLogoError('Logo sekolah wajib diunggah.', $request);

            return null;
        }

        $logoDinas = $this->processLogoUpload($files['logo_dinas'] ?? null, 'logo-dinas', $existing['logo_dinas'] ?? null, $request);
        $lambangNegara = $this->processLogoUpload($files['lambang_negara'] ?? null, 'lambang-negara', $existing['lambang_negara'] ?? null, $request);
        $appIcon = $this->processLogoUpload($files['app_icon'] ?? null, 'app-icon', $existing['app_icon'] ?? null, $request);

        if ($logoSekolah === false || $logoDinas === false || $lambangNegara === false || $appIcon === false) {
            Session::flashInput($request->all());

            return null;
        }

        if ($logoSekolah !== null) {
            $data['logo_sekolah'] = $logoSekolah;
        } elseif (isset($existing['logo_sekolah'])) {
            $data['logo_sekolah'] = $existing['logo_sekolah'];
        } else {
            $data['logo_sekolah'] = null;
        }

        if ($logoDinas !== null) {
            $data['logo_dinas'] = $logoDinas;
        } elseif (isset($existing['logo_dinas'])) {
            $data['logo_dinas'] = $existing['logo_dinas'];
        } else {
            $data['logo_dinas'] = null;
        }

        if ($lambangNegara !== null) {
            $data['lambang_negara'] = $lambangNegara;
        } elseif (isset($existing['lambang_negara'])) {
            $data['lambang_negara'] = $existing['lambang_negara'];
        } else {
            $data['lambang_negara'] = null;
        }

        if ($appIcon !== null) {
            $data['app_icon'] = $appIcon;
        } elseif (isset($existing['app_icon'])) {
            $data['app_icon'] = $existing['app_icon'];
        } else {
            $data['app_icon'] = null;
        }

        return $data;
    }

    /**
     * @param array<string, mixed>|null $file
     * @return string|bool|null
     */
    private function processLogoUpload(?array $file, string $prefix, ?string $existingPath = null, ?Request $request = null): string|bool|null
    {
        $noFileError = \UPLOAD_ERR_NO_FILE;
        $fileError = $file['error'] ?? $noFileError;

        if ($file === null || $fileError === $noFileError) {
            return null;
        }

        if ($fileError !== \UPLOAD_ERR_OK) {
            $this->flashLogoError('Gagal mengunggah berkas logo. Silakan coba lagi.', $request);

            return false;
        }

        $tmpName = $file['tmp_name'] ?? '';

        if (!is_string($tmpName) || $tmpName === '' || !is_uploaded_file($tmpName)) {
            $this->flashLogoError('Berkas logo tidak valid.', $request);

            return false;
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];

        if ($extension === '' || !array_key_exists($extension, $allowed)) {
            $this->flashLogoError('Format file logo harus JPG, PNG, WEBP, atau SVG.', $request);

            return false;
        }

        if ($extension !== 'svg') {
            $imageInfo = @getimagesize($tmpName);
            if ($imageInfo === false) {
                $this->flashLogoError('File logo harus berupa gambar yang valid.', $request);

                return false;
            }
        }

        $stored = ManagedFileStorage::storeUploadedPublic($file, 'profil-sekolah', 'asset', $prefix, $extension, [
            'existing_path' => $existingPath,
            'related_type' => 'sekolah',
        ]);

        if ($stored === null) {
            $this->flashLogoError('Gagal menyimpan file logo.', $request);

            return false;
        }

        return $stored;
    }

    private function flashLogoError(string $message, ?Request $request): void
    {
        Session::flash('error', $message);
        if ($request !== null) {
            Session::flashInput($request->all());
        }
    }

    private function deleteLogoFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        ManagedFileStorage::deletePublic($path);
    }
}

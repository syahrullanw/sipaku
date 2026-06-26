<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\Major;
use App\Models\SchoolYear;
use App\Models\Teacher;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class ClassroomController extends Controller
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

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);
        $classes = $activeYearId > 0 ? Classroom::allWithRelations($activeYearId) : [];
        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? Classroom::find($editId) : null;
        $yearOptions = SchoolYear::options();
        $selectedYearId = $editing !== null ? (int) ($editing['tahun_ajaran_id'] ?? 0) : (int) ($activeYear['id'] ?? 0);
        $selectedYearLabel = $yearOptions[$selectedYearId] ?? null;
        if ($selectedYearLabel === null && $selectedYearId > 0) {
            $yearRecord = SchoolYear::find($selectedYearId);
            if ($yearRecord !== null) {
                $selectedYearLabel = sprintf('%s - %s', $yearRecord['nama'], (int) ($yearRecord['semester_aktif'] ?? 1) === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)');
            }
        }
        if ($selectedYearLabel === null && $activeYear !== null) {
            $selectedYearLabel = sprintf('%s - %s', $activeYear['nama'], (int) ($activeYear['semester_aktif'] ?? 1) === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)');
        }

        return $this->render('master/classes/index', [
            'title' => 'Kelas',
            'pageTitle' => 'Master Kelas',
            'activeMenu' => 'classes',
            'classes' => $classes,
            'editingClass' => $editing,
            'majorsOptions' => Major::options(true, $editing['jurusan_id'] ?? null),
            'teacherOptions' => Teacher::options(true, isset($editing['wali_kelas_id']) ? (int) $editing['wali_kelas_id'] : null),
            'activeYear' => $activeYear,
            'selectedYearId' => $selectedYearId,
            'selectedYearLabel' => $selectedYearLabel,
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

        if ($response = $this->guardCsrf($request, 'master/kelas')) {
            return $response;
        }

        $payload = $this->validate($request);

        if ($payload === null) {
            return $this->redirect('master/kelas');
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            Classroom::create($payload);
            Session::flash('success', 'Kelas berhasil ditambahkan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menambahkan kelas: ' . $exception->getMessage());
        }

        return $this->redirect('master/kelas');
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/kelas')) {
            return $response;
        }

        $payload = $this->validate($request, false, $id);

        if ($payload === null) {
            return $this->redirect('master/kelas');
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            Classroom::updateById($id, $payload);
            Session::flash('success', 'Kelas berhasil diperbarui.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui kelas: ' . $exception->getMessage());
        }

        return $this->redirect('master/kelas');
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/kelas')) {
            return $response;
        }

        try {
            Classroom::deleteById($id);
            Session::flash('success', 'Kelas dihapus.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus kelas: ' . $exception->getMessage());
        }

        return $this->redirect('master/kelas');
    }

    private function validate(Request $request, bool $isCreate = true, ?int $ignoreId = null): ?array
    {
        $yearId = 0;
        $currentClass = null;
        if ($isCreate) {
            $activeYear = SchoolYear::active();
            if ($activeYear === null) {
                Session::flash('error', 'Tidak ada tahun ajaran aktif. Silakan aktifkan tahun ajaran terlebih dahulu.');
                Session::flashInput($request->all());

                return null;
            }

            $yearId = (int) $activeYear['id'];
        } else {
            if ($ignoreId === null) {
                Session::flash('error', 'Data kelas tidak valid.');
                Session::flashInput($request->all());

                return null;
            }

            $currentClass = Classroom::find($ignoreId);

            if ($currentClass === null) {
                Session::flash('error', 'Data kelas tidak ditemukan.');
                Session::flashInput($request->all());

                return null;
            }

            $yearId = (int) ($currentClass['tahun_ajaran_id'] ?? 0);
        }

        if ($yearId <= 0 || SchoolYear::find($yearId) === null) {
            Session::flash('error', 'Tahun ajaran tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        $data = [
            'tahun_ajaran_id' => $yearId,
            'jurusan_id' => (int) $request->input('jurusan_id', 0),
            'tingkat' => (int) $request->input('tingkat', $isCreate ? 0 : ($currentClass['tingkat'] ?? 0)),
            'nama' => trim((string) $request->input('nama', $isCreate ? '' : ($currentClass['nama'] ?? ''))),
            'kurikulum' => strtolower((string) $request->input('kurikulum', $currentClass['kurikulum'] ?? 'k13')),
            'wali_kelas_id' => $request->input('wali_kelas_id') ? (int) $request->input('wali_kelas_id') : null,
        ];

        if ($data['jurusan_id'] <= 0 || $data['tingkat'] <= 0 || $data['nama'] === '') {
            Session::flash('error', 'Pastikan jurusan, tingkat dan nama kelas terisi.');
            Session::flashInput($request->all());

            return null;
        }

        if (!in_array($data['kurikulum'], ['k13', 'kurmer'], true)) {
            $data['kurikulum'] = 'k13';
        }

        $major = Major::find($data['jurusan_id']);

        if ($major === null) {
            Session::flash('error', 'Jurusan tidak valid atau sudah dihapus.');
            Session::flashInput($request->all());

            return null;
        }

        if ($isCreate && ($major['status'] ?? 'nonaktif') !== 'aktif') {
            Session::flash('error', 'Jurusan tidak aktif.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['tingkat'] < 10 || $data['tingkat'] > 13) {
            Session::flash('error', 'Tingkat kelas harus berada pada rentang X-XIII.');
            Session::flashInput($request->all());

            return null;
        }

        if (Classroom::exists([
            'tahun_ajaran_id' => $data['tahun_ajaran_id'],
            'jurusan_id' => $data['jurusan_id'],
            'nama' => $data['nama'],
        ], $ignoreId)) {
            Session::flash('error', 'Nama kelas sudah digunakan pada tahun ajaran dan jurusan tersebut.');
            Session::flashInput($request->all());

            return null;
        }

        return $data;
    }
}

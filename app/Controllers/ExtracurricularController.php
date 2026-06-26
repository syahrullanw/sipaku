<?php

namespace App\Controllers;

use App\Models\Extracurricular;
use App\Models\Teacher;
use App\Models\SchoolYear;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class ExtracurricularController extends Controller
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

        $activeSchoolYear = SchoolYear::active();
        $activities = Extracurricular::allOrdered(
            $activeSchoolYear !== null ? (int) $activeSchoolYear['id'] : null
        );
        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? Extracurricular::find($editId) : null;
        $mentorOptions = Teacher::options(
            false,
            $editing !== null ? (int) ($editing['pembina_guru_id'] ?? 0) : null
        );

        return $this->render('master/extracurriculars/index', [
            'title' => 'Ekstrakurikuler',
            'pageTitle' => 'Master Ekstrakurikuler',
            'activeMenu' => 'extracurriculars',
            'activities' => $activities,
            'editingActivity' => $editing,
            'mentorOptions' => $mentorOptions,
            'activeSchoolYear' => $activeSchoolYear,
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

        if ($response = $this->guardCsrf($request, 'master/ekskul')) {
            return $response;
        }

        $payload = $this->validate($request);

        if ($payload === null) {
            return $this->redirect('master/ekskul');
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            Extracurricular::create($payload);
            Session::flash('success', 'Ekstrakurikuler berhasil ditambahkan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menambahkan ekstrakurikuler: ' . $exception->getMessage());
        }

        return $this->redirect('master/ekskul');
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/ekskul')) {
            return $response;
        }

        $payload = $this->validate($request, false, $id);

        if ($payload === null) {
            return $this->redirect('master/ekskul?edit=' . urlencode((string) $id));
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            Extracurricular::updateById($id, $payload);
            Session::flash('success', 'Ekstrakurikuler berhasil diperbarui.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui ekstrakurikuler: ' . $exception->getMessage());
        }

        return $this->redirect('master/ekskul');
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/ekskul')) {
            return $response;
        }

        try {
            Extracurricular::deleteById($id);
            Session::flash('success', 'Ekstrakurikuler dihapus.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus ekstrakurikuler: ' . $exception->getMessage());
        }

        return $this->redirect('master/ekskul');
    }

    private function validate(Request $request, bool $isCreate = true, ?int $ignoreId = null): ?array
    {
        $activeSchoolYear = SchoolYear::active();

        if ($activeSchoolYear === null) {
            Session::flash('error', 'Tahun ajaran aktif tidak ditemukan. Hubungi administrator.');
            Session::flashInput($request->all());

            return null;
        }

        $data = [
            'nama' => trim((string) $request->input('nama', '')),
            'deskripsi' => trim((string) $request->input('deskripsi', '')),
            'pembina_guru_id' => (int) $request->input('pembina_guru_id', 0),
            'jadwal' => trim((string) $request->input('jadwal', '')),
            'tahun_ajaran_id' => (int) $activeSchoolYear['id'],
        ];

        if ($data['nama'] === '') {
            Session::flash('error', 'Nama ekstrakurikuler wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['pembina_guru_id'] <= 0) {
            Session::flash('error', 'Pembina wajib dipilih dari daftar guru.');
            Session::flashInput($request->all());

            return null;
        }

        foreach (['deskripsi', 'jadwal'] as $field) {
            if ($data[$field] === '') {
                $data[$field] = null;
            }
        }

        if (Extracurricular::exists([
            'tahun_ajaran_id' => $data['tahun_ajaran_id'],
            'nama' => $data['nama'],
        ], $ignoreId)) {
            Session::flash('error', 'Nama ekstrakurikuler sudah terdaftar pada tahun ajaran aktif.');
            Session::flashInput($request->all());

            return null;
        }

        return $data;
    }
}

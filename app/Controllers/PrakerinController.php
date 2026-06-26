<?php

namespace App\Controllers;

use App\Models\PrakerinPlace;
use App\Models\Teacher;
use App\Models\SchoolYear;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class PrakerinController extends Controller
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
        $places = PrakerinPlace::allOrdered();
        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? PrakerinPlace::find($editId) : null;
        $mentorOptions = Teacher::options(
            false,
            $editing !== null ? (int) ($editing['pembina_guru_id'] ?? 0) : null
        );

        return $this->render('master/prakerin/index', [
            'title' => 'Tempat Prakerin',
            'pageTitle' => 'Master Tempat Prakerin',
            'activeMenu' => 'prakerin',
            'places' => $places,
            'editingPlace' => $editing,
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

        if ($response = $this->guardCsrf($request, 'master/prakerin')) {
            return $response;
        }

        $payload = $this->validate($request);

        if ($payload === null) {
            return $this->redirect('master/prakerin');
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            PrakerinPlace::create($payload);
            Session::flash('success', 'Tempat prakerin berhasil ditambahkan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menambahkan tempat prakerin: ' . $exception->getMessage());
        }

        return $this->redirect('master/prakerin');
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/prakerin')) {
            return $response;
        }

        $payload = $this->validate($request, false, $id);

        if ($payload === null) {
            return $this->redirect('master/prakerin?edit=' . urlencode((string) $id));
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            PrakerinPlace::updateById($id, $payload);
            Session::flash('success', 'Tempat prakerin berhasil diperbarui.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui tempat prakerin: ' . $exception->getMessage());
        }

        return $this->redirect('master/prakerin');
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/prakerin')) {
            return $response;
        }

        try {
            PrakerinPlace::deleteById($id);
            Session::flash('success', 'Tempat prakerin dihapus.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus tempat prakerin: ' . $exception->getMessage());
        }

        return $this->redirect('master/prakerin');
    }

    private function validate(Request $request, bool $isCreate = true, ?int $ignoreId = null): ?array
    {
        $data = [
            'nama' => trim((string) $request->input('nama', '')),
            'deskripsi' => trim((string) $request->input('deskripsi', '')),
            'pembina_guru_id' => (int) $request->input('pembina_guru_id', 0),
        ];

        if ($data['nama'] === '') {
            Session::flash('error', 'Nama tempat prakerin wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        if (PrakerinPlace::exists(['nama' => $data['nama']], $ignoreId)) {
            Session::flash('error', 'Nama tempat prakerin sudah terdaftar.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['pembina_guru_id'] <= 0) {
            Session::flash('error', 'Pembina wajib dipilih dari daftar guru.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['deskripsi'] === '') {
            $data['deskripsi'] = null;
        }

        return $data;
    }
}

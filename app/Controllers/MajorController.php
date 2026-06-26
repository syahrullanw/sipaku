<?php

namespace App\Controllers;

use App\Models\Major;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class MajorController extends Controller
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

        $majors = Major::allOrdered();
        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? Major::find($editId) : null;

        return $this->render('master/majors/index', [
            'title' => 'Jurusan',
            'pageTitle' => 'Master Jurusan',
            'activeMenu' => 'majors',
            'majors' => $majors,
            'editingMajor' => $editing,
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

        if ($response = $this->guardCsrf($request, 'master/jurusan')) {
            return $response;
        }

        $payload = $this->validate($request);

        if ($payload === null) {
            return $this->redirect('master/jurusan');
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            Major::create($payload);
            Session::flash('success', 'Jurusan berhasil ditambahkan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menambahkan jurusan: ' . $exception->getMessage());
        }

        return $this->redirect('master/jurusan');
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/jurusan')) {
            return $response;
        }

        $payload = $this->validate($request, false, $id);

        if ($payload === null) {
            return $this->redirect('master/jurusan');
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            Major::updateById($id, $payload);
            Session::flash('success', 'Jurusan berhasil diperbarui.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui jurusan: ' . $exception->getMessage());
        }

        return $this->redirect('master/jurusan');
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/jurusan')) {
            return $response;
        }

        try {
            Major::deleteById($id);
            Session::flash('success', 'Jurusan dihapus.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus jurusan: ' . $exception->getMessage());
        }

        return $this->redirect('master/jurusan');
    }

    private function validate(Request $request, bool $isCreate = true, ?int $ignoreId = null): ?array
    {
        $data = [
            'kode' => trim((string) $request->input('kode', '')),
            'nama' => trim((string) $request->input('nama', '')),
            'deskripsi' => trim((string) $request->input('deskripsi', '')),
        ];

        if ($data['kode'] === '' || $data['nama'] === '') {
            Session::flash('error', 'Kode dan nama jurusan wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        if (Major::exists(['kode' => $data['kode']], $ignoreId)) {
            Session::flash('error', 'Kode jurusan sudah digunakan.');
            Session::flashInput($request->all());

            return null;
        }

        if (Major::exists(['nama' => $data['nama']], $ignoreId)) {
            Session::flash('error', 'Nama jurusan sudah terdaftar.');
            Session::flashInput($request->all());

            return null;
        }

        $status = strtolower((string) $request->input('status', 'aktif'));
        if (!in_array($status, ['aktif', 'nonaktif'], true)) {
            Session::flash('error', 'Status jurusan tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['deskripsi'] === '') {
            $data['deskripsi'] = null;
        }

        $data['status'] = $status;

        return $data;
    }
}

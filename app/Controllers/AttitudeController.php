<?php

namespace App\Controllers;

use App\Models\Attitude;
use App\Services\Import\MasterDataImporter;
use App\Traits\HandlesImportUpload;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class AttitudeController extends Controller
{
    use HandlesImportUpload;

    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $type = (string) $request->query('jenis', 'spiritual');
        if (!array_key_exists($type, Attitude::TYPES)) {
            $type = 'spiritual';
        }

        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? Attitude::find($editId) : null;

        if ($editing !== null && array_key_exists($editing['jenis'] ?? '', Attitude::TYPES)) {
            $type = (string) $editing['jenis'];
        }

        $attitudes = Attitude::allOrdered($type);

        return $this->render('master/attitudes/index', [
            'title' => 'Data Sikap',
            'pageTitle' => 'Data Sikap',
            'activeMenu' => 'attitudes',
            'typeOptions' => Attitude::typeOptions(),
            'selectedType' => $type,
            'attitudes' => $attitudes,
            'editingAttitude' => $editing,
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

        if ($response = $this->guardCsrf($request, 'master/data-sikap')) {
            return $response;
        }

        $payload = $this->validate($request);

        if ($payload === null) {
            $selectedType = (string) $request->input('jenis', 'spiritual');
            if (!array_key_exists($selectedType, Attitude::TYPES)) {
                $selectedType = 'spiritual';
            }

            return $this->redirect('master/data-sikap?jenis=' . urlencode($selectedType));
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            Attitude::create($payload);
            Session::flash('success', 'Data sikap berhasil ditambahkan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menambahkan data sikap: ' . $exception->getMessage());
        }

        return $this->redirect('master/data-sikap?jenis=' . urlencode($payload['jenis']));
    }

    public function import(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/data-sikap')) {
            return $response;
        }

        $type = strtolower(trim((string) $request->input('jenis', 'spiritual')));
        if (!array_key_exists($type, Attitude::TYPES)) {
            Session::flash('error', 'Jenis sikap tidak valid.');
            return $this->redirect('master/data-sikap');
        }

        $files = $request->files();
        $upload = is_array($files) ? ($files['import_file'] ?? null) : null;

        if (!is_array($upload)) {
            Session::flash('error', 'File import tidak ditemukan.');

            return $this->redirect('master/data-sikap?jenis=' . urlencode($type));
        }

        $errorMessage = null;
        $path = $this->moveImportFile($upload, $errorMessage);

        if ($path === null) {
            Session::flash('error', $errorMessage ?? 'File import tidak valid.');

            return $this->redirect('master/data-sikap?jenis=' . urlencode($type));
        }

        try {
            $importer = new MasterDataImporter();
            $result = $importer->importAttitudes($path, $type);

            $summary = sprintf(
                'Import data sikap selesai. %d baris diproses: %d baru, %d diperbarui, %d dilewati.',
                $result['processed'],
                $result['inserted'],
                $result['updated'],
                $result['skipped']
            );

            Session::flash('success', $summary);

            if (!empty($result['errors'])) {
                $preview = array_slice($result['errors'], 0, 5);
                $warning = implode(' ', $preview);
                if (count($result['errors']) > 5) {
                    $warning .= sprintf(' Dan %d kesalahan lainnya.', count($result['errors']) - 5);
                }
                Session::flash('warning', $warning);
            }
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memproses file import: ' . $exception->getMessage());
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return $this->redirect('master/data-sikap?jenis=' . urlencode($type));
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/data-sikap')) {
            return $response;
        }

        $attitude = Attitude::find($id);
        if ($attitude === null) {
            Session::flash('error', 'Data sikap tidak ditemukan.');
            return $this->redirect('master/data-sikap');
        }

        $payload = $this->validate($request, false, $id);

        if ($payload === null) {
            return $this->redirect('master/data-sikap?edit=' . urlencode((string) $id));
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            Attitude::updateById($id, $payload);
            Session::flash('success', 'Data sikap berhasil diperbarui.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui data sikap: ' . $exception->getMessage());
        }

        return $this->redirect('master/data-sikap?jenis=' . urlencode($payload['jenis']));
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/data-sikap')) {
            return $response;
        }

        $attitude = Attitude::find($id);
        if ($attitude === null) {
            Session::flash('error', 'Data sikap tidak ditemukan.');
            return $this->redirect('master/data-sikap');
        }

        try {
            Attitude::deleteById($id);
            Session::flash('success', 'Data sikap dihapus.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus data sikap: ' . $exception->getMessage());
        }

        $type = (string) ($attitude['jenis'] ?? 'spiritual');
        if (!array_key_exists($type, Attitude::TYPES)) {
            $type = 'spiritual';
        }

        return $this->redirect('master/data-sikap?jenis=' . urlencode($type));
    }

    private function validate(Request $request, bool $isCreate = true, ?int $ignoreId = null): ?array
    {
        $data = [
            'jenis' => strtolower(trim((string) $request->input('jenis', 'spiritual'))),
            'kode' => trim((string) $request->input('kode', '')),
            'nama' => trim((string) $request->input('nama', '')),
            'deskripsi' => trim((string) $request->input('deskripsi', '')),
            'status' => strtolower(trim((string) $request->input('status', 'aktif'))),
        ];

        if (!array_key_exists($data['jenis'], Attitude::TYPES)) {
            Session::flash('error', 'Jenis sikap tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['kode'] === '' || $data['nama'] === '') {
            Session::flash('error', 'Kode dan nama sikap wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        if (!in_array($data['status'], ['aktif', 'nonaktif'], true)) {
            Session::flash('error', 'Status data sikap tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        if (Attitude::exists(['jenis' => $data['jenis'], 'kode' => $data['kode']], $ignoreId)) {
            Session::flash('error', 'Kode sikap sudah digunakan untuk jenis tersebut.');
            Session::flashInput($request->all());

            return null;
        }

        if (Attitude::exists(['jenis' => $data['jenis'], 'nama' => $data['nama']], $ignoreId)) {
            Session::flash('error', 'Nama sikap sudah terdaftar untuk jenis tersebut.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['deskripsi'] === '') {
            $data['deskripsi'] = null;
        }

        return $data;
    }
}

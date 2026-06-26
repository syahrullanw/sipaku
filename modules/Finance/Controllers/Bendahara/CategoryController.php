<?php

namespace Modules\Finance\Controllers\Bendahara;

use App\Models\BillingCategory;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $categories = BillingCategory::ordered();
        $editingId = (int) $request->query('edit', 0);
        $editingCategory = null;

        if ($editingId > 0) {
            $editingCategory = BillingCategory::find($editingId) ?? null;
        }

        return $this->render('finance/bendahara/categories/index', [
            'title' => 'Kategori Tagihan',
            'pageTitle' => 'Kelola Kategori Tagihan',
            'activeMenu' => 'finance-bendahara-categories',
            'categories' => $categories,
            'editingCategory' => $editingCategory,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/bendahara/kategori')) {
            return $response;
        }

        $codeInput = strtoupper(trim((string) $request->input('kode', '')));
        $code = preg_replace('/\s+/', '', $codeInput);
        $code = is_string($code) ? $code : '';
        $name = trim((string) $request->input('nama', ''));
        $type = (string) $request->input('tipe', 'rutin');
        $status = (string) $request->input('status', 'aktif');
        $order = $request->input('urutan', null);

        if ($code === '') {
            Session::flash('error', 'Kode kategori tidak boleh kosong.');
            Session::flashInput(array_merge($request->all(), ['__form' => 'create']));

            return $this->redirect('keuangan/bendahara/kategori#form-kategori');
        }

        if (!preg_match('/^[A-Z0-9_-]+$/', $code)) {
            Session::flash('error', 'Kode kategori hanya boleh berisi huruf, angka, garis bawah, atau tanda hubung.');
            Session::flashInput(array_merge($request->all(), ['__form' => 'create']));

            return $this->redirect('keuangan/bendahara/kategori#form-kategori');
        }

        if ($name === '') {
            Session::flash('error', 'Nama kategori tidak boleh kosong.');
            Session::flashInput(array_merge($request->all(), ['__form' => 'create']));

            return $this->redirect('keuangan/bendahara/kategori#form-kategori');
        }

        $allowedTypes = ['rutin', 'insidental'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'rutin';
        }

        $allowedStatus = ['aktif', 'nonaktif'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'aktif';
        }

        $orderValue = $order === null || $order === '' ? null : (int) $order;
        if ($orderValue !== null && $orderValue < 0) {
            $orderValue = 0;
        }

        if (BillingCategory::exists(['kode' => $code])) {
            Session::flash('error', 'Kode kategori sudah digunakan.');
            Session::flashInput(array_merge($request->all(), ['__form' => 'create']));

            return $this->redirect('keuangan/bendahara/kategori#form-kategori');
        }

        if (BillingCategory::exists(['nama' => $name])) {
            Session::flash('error', 'Nama kategori sudah digunakan.');
            Session::flashInput(array_merge($request->all(), ['__form' => 'create']));

            return $this->redirect('keuangan/bendahara/kategori#form-kategori');
        }

        $now = date('Y-m-d H:i:s');
        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
        $auditedUserId = $userId !== null && $userId > 0 ? $userId : null;

        $created = BillingCategory::create([
            'kode' => $code,
            'nama' => $name,
            'tipe' => $type,
            'status' => $status,
            'urutan' => $orderValue,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $auditedUserId,
            'updated_by' => $auditedUserId,
        ]);

        if ($created) {
            Session::flash('success', 'Kategori baru berhasil ditambahkan.');
        } else {
            Session::flash('error', 'Gagal menambahkan kategori baru.');
            Session::flashInput(array_merge($request->all(), ['__form' => 'create']));
        }

        return $this->redirect('keuangan/bendahara/kategori');
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/bendahara/kategori')) {
            return $response;
        }

        $category = BillingCategory::find($id);

        if ($category === null) {
            Session::flash('error', 'Kategori tidak ditemukan.');

            return $this->redirect('keuangan/bendahara/kategori');
        }

        $name = trim((string) $request->input('nama', ''));
        $type = (string) $request->input('tipe', 'rutin');
        $status = (string) $request->input('status', 'aktif');
        $order = $request->input('urutan', null);

        $allowedTypes = ['rutin', 'insidental'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'rutin';
        }

        $allowedStatus = ['aktif', 'nonaktif'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'aktif';
        }

        $orderValue = $order === null || $order === '' ? null : (int) $order;
        if ($orderValue !== null && $orderValue < 0) {
            $orderValue = 0;
        }

        if ($name === '') {
            Session::flash('error', 'Nama kategori tidak boleh kosong.');
            Session::flashInput(array_merge($request->all(), [
                '__form' => 'update',
                '__edit_id' => $id,
            ]));

            return $this->redirect('keuangan/bendahara/kategori?edit=' . $id);
        }

        if (BillingCategory::exists(['nama' => $name], $id)) {
            Session::flash('error', 'Nama kategori sudah digunakan.');
            Session::flashInput(array_merge($request->all(), [
                '__form' => 'update',
                '__edit_id' => $id,
            ]));

            return $this->redirect('keuangan/bendahara/kategori?edit=' . $id);
        }

        $now = date('Y-m-d H:i:s');
        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
        $auditedUserId = $userId !== null && $userId > 0 ? $userId : null;

        $updated = BillingCategory::updateById($id, [
            'nama' => $name,
            'tipe' => $type,
            'status' => $status,
            'urutan' => $orderValue,
            'updated_at' => $now,
            'updated_by' => $auditedUserId,
        ]);

        if ($updated) {
            Session::flash('success', 'Kategori berhasil diperbarui.');
        } else {
            Session::flash('error', 'Gagal memperbarui kategori.');
        }

        return $this->redirect('keuangan/bendahara/kategori');
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/bendahara/kategori')) {
            return $response;
        }

        $category = BillingCategory::find($id);
        if ($category === null) {
            Session::flash('error', 'Kategori tidak ditemukan.');

            return $this->redirect('keuangan/bendahara/kategori');
        }

        $connection = Database::connection();
        $usageStatement = $connection->prepare('SELECT COUNT(*) FROM tagihan WHERE kategori_id = :id');

        if ($usageStatement !== false) {
            $usageStatement->bindValue(':id', $id, \PDO::PARAM_INT);
            $usageStatement->execute();
            $usageCount = $usageStatement->fetchColumn();

            if ($usageCount !== false && (int) $usageCount > 0) {
                Session::flash('error', 'Kategori tidak dapat dihapus karena masih digunakan oleh tagihan aktif.');

                return $this->redirect('keuangan/bendahara/kategori');
            }
        }

        if (BillingCategory::deleteById($id)) {
            Session::flash('success', 'Kategori berhasil dihapus.');
        } else {
            Session::flash('error', 'Gagal menghapus kategori.');
        }

        return $this->redirect('keuangan/bendahara/kategori');
    }
}

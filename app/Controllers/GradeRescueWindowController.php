<?php

namespace App\Controllers;

use App\Models\GradeRescueWindow;
use App\Models\SchoolYear;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class GradeRescueWindowController extends Controller
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

        $periods = GradeRescueWindow::allWithSchoolYear();
        $years = SchoolYear::allOrdered();
        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? GradeRescueWindow::find($editId) : null;

        return $this->render('admin/settings/grade-rescue-periods', [
            'title' => 'Periode Rescue Nilai',
            'pageTitle' => 'Periode Rescue Nilai',
            'activeMenu' => 'grade-rescue-periods',
            'periods' => $periods,
            'years' => $years,
            'editing' => $editing,
        ]);
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }
        if ($response = $this->ensureAdmin()) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, 'admin/periode-rescue-nilai')) {
            return $response;
        }

        $payload = $this->validatePayload($request);
        if ($payload === null) {
            return $this->redirect('admin/periode-rescue-nilai');
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');
        $payload['dibuat_oleh'] = (int) (auth()['id'] ?? 0) ?: null;
        $payload['diperbarui_oleh'] = (int) (auth()['id'] ?? 0) ?: null;

        if (GradeRescueWindow::create($payload)) {
            Session::flash('success', 'Periode rescue nilai berhasil ditambahkan.');
        } else {
            Session::flash('error', 'Gagal menambahkan periode rescue nilai.');
        }

        return $this->redirect('admin/periode-rescue-nilai');
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }
        if ($response = $this->ensureAdmin()) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, 'admin/periode-rescue-nilai')) {
            return $response;
        }

        $existing = GradeRescueWindow::find($id);
        if ($existing === null) {
            Session::flash('error', 'Periode rescue tidak ditemukan.');

            return $this->redirect('admin/periode-rescue-nilai');
        }

        $payload = $this->validatePayload($request, $id);
        if ($payload === null) {
            return $this->redirect('admin/periode-rescue-nilai?edit=' . $id);
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');
        $payload['diperbarui_oleh'] = (int) (auth()['id'] ?? 0) ?: null;

        if (GradeRescueWindow::updateById($id, $payload)) {
            Session::flash('success', 'Periode rescue nilai berhasil diperbarui.');
        } else {
            Session::flash('error', 'Gagal memperbarui periode rescue nilai.');
        }

        return $this->redirect('admin/periode-rescue-nilai');
    }

    public function toggleStatus(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }
        if ($response = $this->ensureAdmin()) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, 'admin/periode-rescue-nilai')) {
            return $response;
        }

        $existing = GradeRescueWindow::find($id);
        if ($existing === null) {
            Session::flash('error', 'Periode rescue tidak ditemukan.');

            return $this->redirect('admin/periode-rescue-nilai');
        }

        $nextStatus = ((string) ($existing['status'] ?? 'nonaktif')) === 'aktif' ? 'nonaktif' : 'aktif';
        $ok = GradeRescueWindow::updateById($id, [
            'status' => $nextStatus,
            'updated_at' => date('Y-m-d H:i:s'),
            'diperbarui_oleh' => (int) (auth()['id'] ?? 0) ?: null,
        ]);

        if ($ok) {
            Session::flash('success', 'Status periode rescue berhasil diperbarui.');
        } else {
            Session::flash('error', 'Gagal memperbarui status periode rescue.');
        }

        return $this->redirect('admin/periode-rescue-nilai');
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }
        if ($response = $this->ensureAdmin()) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, 'admin/periode-rescue-nilai')) {
            return $response;
        }

        if (GradeRescueWindow::deleteById($id)) {
            Session::flash('success', 'Periode rescue berhasil dihapus.');
        } else {
            Session::flash('error', 'Gagal menghapus periode rescue.');
        }

        return $this->redirect('admin/periode-rescue-nilai');
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): ?array
    {
        $schoolYearId = (int) $request->input('tahun_ajaran_id', 0);
        $semester = strtolower(trim((string) $request->input('semester', '')));
        $name = trim((string) $request->input('nama', ''));
        $status = strtolower(trim((string) $request->input('status', 'aktif')));
        $startRaw = trim((string) $request->input('mulai_at', ''));
        $endRaw = trim((string) $request->input('selesai_at', ''));
        $note = trim((string) $request->input('catatan', ''));

        if ($schoolYearId <= 0 || $name === '' || $startRaw === '' || $endRaw === '') {
            Session::flash('error', 'Tahun ajaran, nama periode, mulai, dan selesai wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        if (!in_array($semester, ['ganjil', 'genap'], true)) {
            Session::flash('error', 'Semester harus ganjil atau genap.');
            Session::flashInput($request->all());

            return null;
        }

        if (!in_array($status, ['aktif', 'nonaktif'], true)) {
            Session::flash('error', 'Status tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        $start = strtotime($startRaw);
        $end = strtotime($endRaw);
        if ($start === false || $end === false) {
            Session::flash('error', 'Format tanggal dan waktu tidak valid.');
            Session::flashInput($request->all());

            return null;
        }
        if ($start >= $end) {
            Session::flash('error', 'Waktu selesai harus lebih besar dari waktu mulai.');
            Session::flashInput($request->all());

            return null;
        }

        if (GradeRescueWindow::hasOverlappingWindow($schoolYearId, $semester, date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end), $ignoreId)) {
            Session::flash('error', 'Rentang periode bertabrakan dengan periode rescue lain pada tahun ajaran dan semester yang sama.');
            Session::flashInput($request->all());

            return null;
        }

        return [
            'tahun_ajaran_id' => $schoolYearId,
            'semester' => $semester,
            'nama' => $name,
            'mulai_at' => date('Y-m-d H:i:s', $start),
            'selesai_at' => date('Y-m-d H:i:s', $end),
            'status' => $status,
            'catatan' => $note !== '' ? $note : null,
        ];
    }
}

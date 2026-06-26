<?php

namespace Modules\Ppdb\Controllers\Admin;

use App\Models\PpdbPeriod;
use App\Models\PpdbPeriodResponsible;
use App\Models\SchoolYear;
use App\Models\Teacher;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Ppdb\Controllers\Controller;

class PeriodController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        $periods = PpdbPeriod::allWithResponsibles();
        $editId = (int) $request->query('edit', 0);
        $editingPeriod = $editId > 0 ? PpdbPeriod::find($editId) : null;
        $editingResponsibles = $editingPeriod !== null
            ? array_map(static fn ($record) => (int) ($record['guru_id'] ?? 0), PpdbPeriodResponsible::forPeriod($editId))
            : [];

        $teacherOptions = Teacher::options(true);
        foreach ($editingResponsibles as $teacherId) {
            if ($teacherId > 0 && !isset($teacherOptions[$teacherId])) {
                $teacher = Teacher::find($teacherId);
                if ($teacher !== null) {
                    $label = (string) ($teacher['nama'] ?? 'Guru');
                    if (($teacher['status'] ?? 'aktif') !== 'aktif') {
                        $label .= ' (Nonaktif)';
                    }
                    $teacherOptions[$teacherId] = $label;
                }
            }
        }
        asort($teacherOptions);

        return $this->render('ppdb/admin/periods/index', [
            'title' => 'Periode PPDB',
            'pageTitle' => 'Manajemen Periode PPDB',
            'activeMenu' => 'ppdb-periods',
            'periods' => $periods,
            'stageDefinitions' => PpdbPeriod::stages(),
            'editingPeriod' => $editingPeriod,
            'editingResponsibles' => $editingResponsibles,
            'teacherOptions' => $teacherOptions,
            'schoolYearOptions' => SchoolYear::options(),
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'ppdb/admin/periode')) {
            return $response;
        }

        $validated = $this->validatePayload($request);

        if ($validated === null) {
            return $this->redirect('ppdb/admin/periode');
        }

        [$data, $responsibles] = $validated;
        $connection = Database::connection();

        try {
            $connection->beginTransaction();

            $data['token_pendaftaran'] = PpdbPeriod::generateToken();
            $data['dibuat_oleh'] = $this->user()['id'] ?? null;
            $data['diperbarui_oleh'] = $this->user()['id'] ?? null;
            $timestamp = date('Y-m-d H:i:s');
            $data['created_at'] = $timestamp;
            $data['updated_at'] = $timestamp;

            $periodId = PpdbPeriod::createAndReturnId($data);

            if ($periodId === null) {
                throw new \RuntimeException('Gagal menyimpan periode PPDB baru.');
            }

            $this->syncResponsibles($periodId, $responsibles);

            $connection->commit();
            Session::flash('success', 'Periode PPDB berhasil dibuat.');
        } catch (\Throwable $exception) {
            $connection->rollBack();
            Session::flash('error', 'Gagal menyimpan periode PPDB: ' . $exception->getMessage());
        }

        return $this->redirect('ppdb/admin/periode');
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'ppdb/admin/periode')) {
            return $response;
        }

        $period = PpdbPeriod::find($id);

        if ($period === null) {
            Session::flash('error', 'Periode PPDB tidak ditemukan.');

            return $this->redirect('ppdb/admin/periode');
    }

        $validated = $this->validatePayload($request, $id, $period);

        if ($validated === null) {
            return $this->redirect('ppdb/admin/periode?edit=' . $id);
        }

        [$data, $responsibles] = $validated;
        $connection = Database::connection();

        try {
            $connection->beginTransaction();

            $data['diperbarui_oleh'] = $this->user()['id'] ?? null;
            $data['updated_at'] = date('Y-m-d H:i:s');

            PpdbPeriod::updateById($id, $data);
            PpdbPeriodResponsible::deleteByPeriod($id);
            $this->syncResponsibles($id, $responsibles);

            $connection->commit();
            Session::flash('success', 'Periode PPDB diperbarui.');
        } catch (\Throwable $exception) {
            $connection->rollBack();
            Session::flash('error', 'Gagal memperbarui periode: ' . $exception->getMessage());
        }

        return $this->redirect('ppdb/admin/periode');
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'ppdb/admin/periode')) {
            return $response;
        }

        try {
            PpdbPeriod::deleteById($id);
            Session::flash('success', 'Periode PPDB dihapus.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus periode: ' . $exception->getMessage());
        }

        return $this->redirect('ppdb/admin/periode');
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int>}|null
     */
    private function validatePayload(Request $request, ?int $ignoreId = null, ?array $existing = null): ?array
    {
        $input = $request->all();

        $data = [
            'kode' => strtoupper(trim((string) $request->input('kode', $existing['kode'] ?? ''))),
            'nama' => trim((string) $request->input('nama', $existing['nama'] ?? '')),
            'tahun_masuk' => trim((string) $request->input('tahun_masuk', $existing['tahun_masuk'] ?? '')),
            'tahun_ajaran_target_id' => (int) $request->input('tahun_ajaran_target_id', $existing['tahun_ajaran_target_id'] ?? 0),
            'pendaftaran_mulai' => (string) $request->input('pendaftaran_mulai', $existing['pendaftaran_mulai'] ?? ''),
            'pendaftaran_selesai' => (string) $request->input('pendaftaran_selesai', $existing['pendaftaran_selesai'] ?? ''),
            'seleksi_mulai' => (string) $request->input('seleksi_mulai', $existing['seleksi_mulai'] ?? ''),
            'seleksi_selesai' => (string) $request->input('seleksi_selesai', $existing['seleksi_selesai'] ?? ''),
            'pengumuman_mulai' => (string) $request->input('pengumuman_mulai', $existing['pengumuman_mulai'] ?? ''),
            'pengumuman_selesai' => (string) $request->input('pengumuman_selesai', $existing['pengumuman_selesai'] ?? ''),
            'daftar_ulang_mulai' => (string) $request->input('daftar_ulang_mulai', $existing['daftar_ulang_mulai'] ?? ''),
            'daftar_ulang_selesai' => (string) $request->input('daftar_ulang_selesai', $existing['daftar_ulang_selesai'] ?? ''),
            'pembayaran_mulai' => (string) $request->input('pembayaran_mulai', $existing['pembayaran_mulai'] ?? ''),
            'pembayaran_selesai' => (string) $request->input('pembayaran_selesai', $existing['pembayaran_selesai'] ?? ''),
            'status' => (string) $request->input('status', $existing['status'] ?? 'draft'),
            'catatan' => trim((string) $request->input('catatan', $existing['catatan'] ?? '')),
        ];

        if ($data['kode'] === '' || $data['nama'] === '') {
            Session::flash('error', 'Kode dan nama periode wajib diisi.');
            Session::flashInput($input);

            return null;
        }

        if (!preg_match('/^[A-Z0-9\-]+$/', $data['kode'])) {
            Session::flash('error', 'Kode periode hanya boleh berisi huruf, angka, dan tanda minus (-).');
            Session::flashInput($input);

            return null;
        }

        if (!in_array($data['status'], ['draft', 'aktif', 'selesai', 'arsip'], true)) {
            $data['status'] = 'draft';
        }

        if ($data['tahun_ajaran_target_id'] <= 0) {
            $data['tahun_ajaran_target_id'] = null;
        }

        foreach ([
            'pendaftaran_mulai',
            'pendaftaran_selesai',
            'seleksi_mulai',
            'seleksi_selesai',
            'pengumuman_mulai',
            'pengumuman_selesai',
            'daftar_ulang_mulai',
            'daftar_ulang_selesai',
            'pembayaran_mulai',
            'pembayaran_selesai',
        ] as $dateField) {
            if ($data[$dateField] === '') {
                $data[$dateField] = null;
                continue;
            }

            if (strtotime($data[$dateField]) === false) {
                Session::flash('error', 'Format tanggal tidak valid pada kolom ' . str_replace('_', ' ', $dateField) . '.');
                Session::flashInput($input);

                return null;
            }
        }

        if (PpdbPeriod::exists(['kode' => $data['kode']], $ignoreId)) {
            Session::flash('error', 'Kode periode sudah digunakan.');
            Session::flashInput($input);

            return null;
        }

        $stages = PpdbPeriod::stages();
        $stageFlags = [];
        foreach ($stages as $key => $definition) {
            $column = $definition['column'];
            $checked = $request->input('stage_' . $key, null) !== null;
            $stageFlags[$column] = $checked ? 1 : 0;
            $input['stage_' . $key] = $checked ? 1 : 0;
        }

        foreach ($stageFlags as $column => $flag) {
            $data[$column] = $flag;
        }

        $responsibleInput = $request->input('responsibles', []);
        $responsibles = array_values(array_unique(array_filter(array_map(static function ($value) {
            return (int) $value;
        }, is_array($responsibleInput) ? $responsibleInput : [$responsibleInput]), static function (int $id): bool {
            return $id > 0;
        })));

        $input['responsibles'] = $responsibles;

        if ($data['tahun_masuk'] === '') {
            $data['tahun_masuk'] = null;
        }

        if ($data['catatan'] === '') {
            $data['catatan'] = null;
        }

        return [$data, $responsibles];
    }

    /**
     * @param array<int> $responsibles
     */
    private function syncResponsibles(int $periodId, array $responsibles): void
    {
        $roles = ['ketua', 'sekretaris'];

        foreach ($responsibles as $index => $teacherId) {
            $role = $roles[$index] ?? 'anggota';
            PpdbPeriodResponsible::assign($periodId, $teacherId, $role);
        }
    }
}

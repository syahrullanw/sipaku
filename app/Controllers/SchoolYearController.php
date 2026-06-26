<?php

namespace App\Controllers;

use App\Models\SchoolYear;
use App\Models\Teacher;
use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;

class SchoolYearController extends Controller
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

        $years = SchoolYear::allOrdered();
        $editId = (int) $request->query('edit', 0);
        $editingYear = $editId > 0 ? SchoolYear::find($editId) : null;
        $selectedHeadmasterId = $editingYear !== null ? (int) ($editingYear['kepala_sekolah_id'] ?? 0) : 0;
        $teacherOptions = Teacher::options(true, $selectedHeadmasterId ?: null);
        $teacherMap = [];

        foreach (Teacher::allOrdered() as $teacher) {
            $id = (int) ($teacher['id'] ?? 0);
            if ($id > 0) {
                $teacherMap[$id] = (string) ($teacher['nama'] ?? '');
            }
        }

        return $this->render('master/school-years/index', [
            'title' => 'Tahun Ajaran',
            'pageTitle' => 'Master Tahun Ajaran',
            'activeMenu' => 'years',
            'years' => $years,
            'editingYear' => $editingYear,
            'teacherOptions' => $teacherOptions,
            'teachersById' => $teacherMap,
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

        if ($response = $this->guardCsrf($request, 'master/tahun-ajaran')) {
            return $response;
        }

        $payload = $this->validate($request);

        if ($payload === null) {
            return $this->redirect('master/tahun-ajaran');
        }

        $connection = Database::connection();

        try {
            $connection->beginTransaction();

            if (($payload['status'] ?? 'nonaktif') === 'aktif') {
                $connection->exec("UPDATE tahun_ajaran SET status = 'nonaktif'");
            }

            $payload['created_at'] = date('Y-m-d H:i:s');
            $payload['updated_at'] = date('Y-m-d H:i:s');

            SchoolYear::create($payload);

            $connection->commit();

            Session::flash('success', 'Tahun ajaran berhasil ditambahkan.');
        } catch (\Throwable $exception) {
            $connection->rollBack();
            Session::flash('error', 'Gagal menambahkan tahun ajaran: ' . $exception->getMessage());
        }

        return $this->redirect('master/tahun-ajaran');
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/tahun-ajaran')) {
            return $response;
        }

        $year = SchoolYear::find($id);

        if ($year === null) {
            Session::flash('error', 'Tahun ajaran tidak ditemukan.');

            return $this->redirect('master/tahun-ajaran');
        }

        $payload = $this->validate($request, false, $id, $year);

        if ($payload === null) {
            return $this->redirect('master/tahun-ajaran');
        }

        $connection = Database::connection();

        try {
            $connection->beginTransaction();

            if (($payload['status'] ?? 'nonaktif') === 'aktif') {
                $statement = $connection->prepare("UPDATE tahun_ajaran SET status = 'nonaktif' WHERE id <> :id");
                $statement->execute([':id' => $id]);
            }

            $payload['updated_at'] = date('Y-m-d H:i:s');

            SchoolYear::updateById($id, $payload);

            $connection->commit();

            Session::flash('success', 'Tahun ajaran berhasil diperbarui.');
        } catch (\Throwable $exception) {
            $connection->rollBack();
            Session::flash('error', 'Gagal memperbarui tahun ajaran: ' . $exception->getMessage());
        }

        return $this->redirect('master/tahun-ajaran');
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/tahun-ajaran')) {
            return $response;
        }

        try {
            SchoolYear::deleteById($id);
            Session::flash('success', 'Tahun ajaran dihapus.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus tahun ajaran: ' . $exception->getMessage());
        }

        return $this->redirect('master/tahun-ajaran');
    }

    private function validate(Request $request, bool $isCreate = true, ?int $ignoreId = null, ?array $existing = null): ?array
    {
        SchoolYear::ensureSchema();

        $data = [
            'kode' => trim((string) $request->input('kode', '')),
            'nama' => trim((string) $request->input('nama', '')),
            'tanggal_mulai' => (string) $request->input('tanggal_mulai', ''),
            'tanggal_selesai' => (string) $request->input('tanggal_selesai', ''),
            'status' => (string) $request->input('status', 'nonaktif'),
            'semester_aktif' => (int) $request->input('semester_aktif', 1),
            'skl_nomor_surat' => trim((string) $request->input('skl_nomor_surat', '')),
            'skl_tanggal_rapat_pleno' => trim((string) $request->input('skl_tanggal_rapat_pleno', '')),
            'skl_titimangsa' => trim((string) $request->input('skl_titimangsa', '')),
            'transkrip_nomor_prefix' => trim((string) $request->input('transkrip_nomor_prefix', '')),
        ];
        $digitalSignatureInput = $request->input('digital_signature_enabled', $existing !== null ? (int) ($existing['digital_signature_enabled'] ?? 0) : 0);
        $enableDigitalSignature = (int) $digitalSignatureInput === 1;
        $dateFields = [
            'tanggal_raport_tingkat_10_11' => (string) $request->input('tanggal_raport_tingkat_10_11', ''),
            'tanggal_raport_tingkat_12' => (string) $request->input('tanggal_raport_tingkat_12', ''),
            'tanggal_raport_tengah_semester' => (string) $request->input('tanggal_raport_tengah_semester', ''),
        ];
        $headmasterId = (int) $request->input('kepala_sekolah_id', 0);

        if ($data['kode'] === '' || $data['nama'] === '' || $data['tanggal_mulai'] === '' || $data['tanggal_selesai'] === '') {
            Session::flash('error', 'Semua kolom wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        if (strtotime($data['tanggal_mulai']) === false || strtotime($data['tanggal_selesai']) === false) {
            Session::flash('error', 'Tanggal mulai dan selesai tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        if (strtotime($data['tanggal_mulai']) > strtotime($data['tanggal_selesai'])) {
            Session::flash('error', 'Tanggal selesai harus lebih besar dari tanggal mulai.');
            Session::flashInput($request->all());

            return null;
        }

        foreach ($dateFields as $field => $value) {
            if ($value === '') {
                $data[$field] = null;
                continue;
            }

            if (strtotime($value) === false) {
                Session::flash('error', 'Tanggal raport tidak valid.');
                Session::flashInput($request->all());

                return null;
            }

            $data[$field] = $value;
        }

        foreach (['skl_tanggal_rapat_pleno', 'skl_titimangsa'] as $dateField) {
            if ($data[$dateField] === '') {
                $data[$dateField] = null;
                continue;
            }

            if (strtotime($data[$dateField]) === false) {
                Session::flash('error', 'Tanggal pengaturan SKL tidak valid.');
                Session::flashInput($request->all());

                return null;
            }

            $data[$dateField] = date('Y-m-d', strtotime($data[$dateField]));
        }

        if ($data['skl_nomor_surat'] !== '' && strlen($data['skl_nomor_surat']) > 190) {
            Session::flash('error', 'Nomor surat SKL maksimal 190 karakter.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['transkrip_nomor_prefix'] !== '' && strlen($data['transkrip_nomor_prefix']) > 80) {
            Session::flash('error', 'Prefix nomor transkrip maksimal 80 karakter.');
            Session::flashInput($request->all());

            return null;
        }

        foreach (['skl_nomor_surat', 'transkrip_nomor_prefix'] as $optionalTextField) {
            if ($data[$optionalTextField] === '') {
                $data[$optionalTextField] = null;
            }
        }

        if ($headmasterId > 0) {
            $teacher = Teacher::find($headmasterId);

            if ($teacher === null) {
                Session::flash('error', 'Guru yang dipilih sebagai kepala sekolah tidak ditemukan.');
                Session::flashInput($request->all());

                return null;
            }

            $statusGuru = (string) ($teacher['status'] ?? 'nonaktif');
            $existingHeadmasterId = $existing !== null ? (int) ($existing['kepala_sekolah_id'] ?? 0) : 0;

            if ($statusGuru !== 'aktif' && $headmasterId !== $existingHeadmasterId) {
                Session::flash('error', 'Pilih kepala sekolah dari daftar guru aktif.');
                Session::flashInput($request->all());

                return null;
            }

            $data['kepala_sekolah_id'] = $headmasterId;
        } else {
            $data['kepala_sekolah_id'] = null;
        }

        if (!in_array($data['semester_aktif'], [1, 2], true)) {
            Session::flash('error', 'Semester aktif tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        if (SchoolYear::exists(['kode' => $data['kode']], $ignoreId)) {
            Session::flash('error', 'Kode tahun ajaran sudah digunakan.');
            Session::flashInput($request->all());

            return null;
        }

        if (SchoolYear::exists(['nama' => $data['nama']], $ignoreId)) {
            Session::flash('error', 'Nama tahun ajaran sudah terdaftar.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['status'] === 'aktif') {
            if ($data['kepala_sekolah_id'] === null) {
                Session::flash('error', 'Kepala sekolah wajib dipilih untuk tahun ajaran aktif.');
                Session::flashInput($request->all());

                return null;
            }

            foreach (['tanggal_raport_tingkat_10_11', 'tanggal_raport_tingkat_12', 'tanggal_raport_tengah_semester'] as $requiredField) {
                if ($data[$requiredField] === null) {
                    Session::flash('error', 'Semua tanggal raport wajib diisi untuk tahun ajaran aktif.');
                    Session::flashInput($request->all());

                    return null;
                }
            }
        }

        $wasDigitalSignatureEnabled = $existing !== null && (int) ($existing['digital_signature_enabled'] ?? 0) === 1;
        $data['digital_signature_enabled'] = $enableDigitalSignature ? 1 : 0;

        if ($enableDigitalSignature) {
            $isActiveYear = $data['status'] === 'aktif' || ($existing !== null && ($existing['status'] ?? '') === 'aktif' && $data['status'] === ($existing['status'] ?? ''));

            if (!$isActiveYear) {
                Session::flash('error', 'Aktifkan status tahun ajaran terlebih dahulu sebelum mengaktifkan TTD digital.');
                Session::flashInput($request->all());

                return null;
            }

            if ($data['kepala_sekolah_id'] === null && (!$wasDigitalSignatureEnabled || $existing === null)) {
                Session::flash('error', 'Pilih kepala sekolah sebelum mengaktifkan TTD digital.');
                Session::flashInput($request->all());

                return null;
            }
        }

        if ($enableDigitalSignature && !$wasDigitalSignatureEnabled) {
            $data['digital_signature_enabled_at'] = date('Y-m-d H:i:s');
            $currentUserId = (int) (auth()['id'] ?? 0);
            $data['digital_signature_enabled_by'] = $currentUserId > 0 ? $currentUserId : null;
        } elseif (!$enableDigitalSignature) {
            $data['digital_signature_enabled_at'] = null;
            $data['digital_signature_enabled_by'] = null;
        } else {
            if ($existing !== null) {
                $data['digital_signature_enabled_at'] = $existing['digital_signature_enabled_at'] ?? null;
                $data['digital_signature_enabled_by'] = $existing['digital_signature_enabled_by'] ?? null;
            } else {
                $data['digital_signature_enabled_at'] = null;
                $data['digital_signature_enabled_by'] = null;
            }
        }

        return $data;
    }
}

<?php

namespace Modules\Ppdb\Controllers\Guru;

use App\Models\Extracurricular;
use App\Models\Major;
use App\Models\PpdbPeriod;
use App\Models\PpdbPeriodResponsible;
use App\Models\PpdbRegistrant;
use App\Services\PpdbRegistrationNotifier;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Ppdb\Controllers\Controller;

class RegistrantController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->ensureResponsibleAccess(null, 'ppdb/guru')) {
            return $response;
        }

        $user = $this->user();
        $teacherId = (int) ($user['teacher_id'] ?? 0);

        if ($teacherId <= 0) {
            Session::flash('error', 'Akun guru tidak valid.');

            return $this->redirect('dashboard');
        }

        $assignments = PpdbPeriodResponsible::teacherActiveAssignments($teacherId);

        if (empty($assignments)) {
            Session::flash('error', 'Anda belum terdaftar sebagai penanggung jawab PPDB aktif.');

            return $this->redirect('dashboard');
        }

        $periodOptions = [];
        foreach ($assignments as $assignment) {
            $periodOptions[(int) $assignment['id']] = sprintf(
                '%s (%s)',
                $assignment['nama'] ?? 'Periode',
                ucfirst((string) ($assignment['status'] ?? 'aktif'))
            );
        }

        $requestedId = (int) $request->query('periode_id', 0);
        $periodId = $requestedId > 0 ? $requestedId : (int) array_key_first($periodOptions);

        if (!isset($periodOptions[$periodId])) {
            Session::flash('error', 'Anda tidak memiliki akses ke periode PPDB yang dipilih.');

            return $this->redirect('ppdb/guru');
        }

        if ($response = $this->ensureResponsibleAccess($periodId, 'ppdb/guru')) {
            return $response;
        }

        $period = PpdbPeriod::find($periodId);
        $registrants = PpdbRegistrant::forPeriod($periodId);
        $canRegister = $period !== null ? PpdbPeriod::isStageEnabled($period, 'pendaftaran') : false;

        $canUpdateSelection = $period !== null ? PpdbPeriod::isStageEnabled($period, 'seleksi') : false;
        $canUpdateAnnouncement = $period !== null ? PpdbPeriod::isStageEnabled($period, 'pengumuman') : false;
        $canUpdateReRegistration = $period !== null ? PpdbPeriod::isStageEnabled($period, 'daftar_ulang') : false;
        $canUpdatePayment = $period !== null ? PpdbPeriod::isStageEnabled($period, 'pembayaran') : false;

        return $this->render('ppdb/admin/registrants/index', [
            'title' => 'Pendaftar PPDB',
            'pageTitle' => 'Data Pendaftar PPDB',
            'activeMenu' => 'ppdb-teacher-registrants',
            'periodOptions' => $periodOptions,
            'selectedPeriodId' => $periodId,
            'selectedPeriod' => $period,
            'registrants' => $registrants,
            'canManualRegister' => $canRegister,
            'showManualForm' => true,
            'indexRoute' => 'ppdb/guru/pendaftar',
            'createRoute' => 'ppdb/guru/pendaftar/tambah',
            'statusFinalOptions' => PpdbRegistrant::statusFinalOptions(),
            'selectionStatusOptions' => PpdbRegistrant::selectionStatusOptions(),
            'announcementStatusOptions' => PpdbRegistrant::announcementStatusOptions(),
            'reRegistrationStatusOptions' => PpdbRegistrant::reRegistrationStatusOptions(),
            'paymentStatusOptions' => PpdbRegistrant::paymentStatusOptions(),
            'canUpdateSelection' => $canUpdateSelection,
            'canUpdateAnnouncement' => $canUpdateAnnouncement,
            'canUpdateReRegistration' => $canUpdateReRegistration,
            'canUpdatePayment' => $canUpdatePayment,
            'selectionUpdateRoutePrefix' => 'ppdb/guru/pendaftar',
            'announcementUpdateRoutePrefix' => 'ppdb/guru/pendaftar',
            'reRegistrationUpdateRoutePrefix' => 'ppdb/guru/pendaftar',
            'paymentUpdateRoutePrefix' => 'ppdb/guru/pendaftar',
            'deleteRoutePrefix' => 'ppdb/guru/pendaftar',
        ], 'admin');
    }

    public function create(Request $request): Response
    {
        if ($response = $this->ensureResponsibleAccess(null, 'ppdb/guru')) {
            return $response;
        }

        $user = $this->user();
        $teacherId = (int) ($user['teacher_id'] ?? 0);

        if ($teacherId <= 0) {
            Session::flash('error', 'Akun guru tidak valid.');

            return $this->redirect('dashboard');
        }

        $assignments = PpdbPeriodResponsible::teacherActiveAssignments($teacherId);

        if (empty($assignments)) {
            Session::flash('error', 'Anda belum terdaftar sebagai penanggung jawab PPDB aktif.');

            return $this->redirect('dashboard');
        }

        $periodOptions = [];
        foreach ($assignments as $assignment) {
            $periodOptions[(int) $assignment['id']] = sprintf(
                '%s (%s)',
                $assignment['nama'] ?? 'Periode',
                ucfirst((string) ($assignment['status'] ?? 'aktif'))
            );
        }

        $requestedId = (int) $request->query('periode_id', 0);
        $periodId = $requestedId > 0 ? $requestedId : (int) array_key_first($periodOptions);

        if (!isset($periodOptions[$periodId])) {
            Session::flash('error', 'Anda tidak memiliki akses ke periode PPDB yang dipilih.');

            return $this->redirect('ppdb/guru/pendaftar');
        }

        if ($response = $this->ensureResponsibleAccess($periodId, 'ppdb/guru/pendaftar')) {
            return $response;
        }

        $period = PpdbPeriod::find($periodId);
        $canRegister = $period !== null ? PpdbPeriod::isStageEnabled($period, 'pendaftaran') : false;

        return $this->render('ppdb/teacher/registrants/create', [
            'title' => 'Tambah Pendaftar PPDB',
            'pageTitle' => 'Tambah Data Pendaftar PPDB',
            'activeMenu' => 'ppdb-teacher-registrants',
            'periodOptions' => $periodOptions,
            'selectedPeriodId' => $periodId,
            'selectedPeriod' => $period,
            'canManualRegister' => $canRegister,
            'majorOptions' => Major::options(true),
            'extracurricularOptions' => Extracurricular::options(),
            'backRoute' => 'ppdb/guru/pendaftar',
            'storeRoute' => 'ppdb/guru/pendaftar',
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureResponsibleAccess(null, 'ppdb/guru')) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'ppdb/guru/pendaftar')) {
            return $response;
        }

        $periodId = (int) $request->input('periode_id', 0);

        if ($response = $this->ensureResponsibleAccess($periodId, 'ppdb/guru/pendaftar')) {
            return $response;
        }

        $period = $periodId > 0 ? PpdbPeriod::find($periodId) : null;

        if ($period === null) {
            Session::flash('error', 'Periode PPDB tidak ditemukan.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/guru/pendaftar');
        }

        if (!PpdbPeriod::isStageEnabled($period, 'pendaftaran')) {
            Session::flash('error', 'Tahap pendaftaran sedang dinonaktifkan untuk periode ini.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/guru/pendaftar/tambah?periode_id=' . $periodId);
        }

        $payload = [
            'nama_lengkap' => trim((string) $request->input('nama_lengkap', '')),
            'tanggal_lahir' => (string) $request->input('tanggal_lahir', ''),
            'jenis_kelamin' => (string) $request->input('jenis_kelamin', ''),
            'alamat' => trim((string) $request->input('alamat', '')),
            'nama_wali' => trim((string) $request->input('nama_wali', '')),
            'telepon' => trim((string) $request->input('telepon', '')),
            'telepon_wali' => trim((string) $request->input('telepon', '')),
            'sumber' => 'panitia',
            'status_verifikasi' => 'diverifikasi',
        ];

        $selectedMajorId = (int) $request->input('major_id', 0);
        $selectedExtracurricularId = (int) $request->input('extracurricular_id', 0);
        $majorOptions = Major::options(true);
        $extracurricularOptions = Extracurricular::options();

        if ($payload['nama_lengkap'] === '') {
            Session::flash('error', 'Nama lengkap pendaftar wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/guru/pendaftar/tambah?periode_id=' . $periodId);
        }

        if (!in_array($payload['jenis_kelamin'], ['L', 'P'], true)) {
            Session::flash('error', 'Jenis kelamin tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/guru/pendaftar/tambah?periode_id=' . $periodId);
        }

        if ($payload['alamat'] === '') {
            Session::flash('error', 'Alamat wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/guru/pendaftar/tambah?periode_id=' . $periodId);
        }

        if ($payload['nama_wali'] === '') {
            Session::flash('error', 'Nama orang ibu wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/guru/pendaftar/tambah?periode_id=' . $periodId);
        }

        if ($payload['telepon'] === '') {
            Session::flash('error', 'No HP/WhatsApp wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/guru/pendaftar/tambah?periode_id=' . $periodId);
        }

        if ($payload['tanggal_lahir'] !== '' && strtotime($payload['tanggal_lahir']) === false) {
            Session::flash('error', 'Tanggal lahir tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/guru/pendaftar/tambah?periode_id=' . $periodId);
        }

        if ($payload['tanggal_lahir'] === '') {
            $payload['tanggal_lahir'] = null;
        }

        if ($selectedMajorId > 0 && !isset($majorOptions[$selectedMajorId])) {
            Session::flash('error', 'Pilihan jurusan tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/guru/pendaftar/tambah?periode_id=' . $periodId);
        }

        if ($selectedExtracurricularId > 0 && !isset($extracurricularOptions[$selectedExtracurricularId])) {
            Session::flash('error', 'Pilihan ekskul tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/guru/pendaftar/tambah?periode_id=' . $periodId);
        }

        $notes = [];
        if ($selectedMajorId > 0) {
            $notes[] = 'Jurusan Pilihan: ' . (string) $majorOptions[$selectedMajorId];
        }
        if ($selectedExtracurricularId > 0) {
            $notes[] = 'Pilihan Ekskul: ' . (string) $extracurricularOptions[$selectedExtracurricularId];
        }
        if (!empty($notes)) {
            $payload['catatan'] = implode("\n", $notes);
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        $newId = PpdbRegistrant::createForPeriod($periodId, $payload);

        if ($newId === null) {
            Session::flash('error', 'Gagal menambahkan pendaftar baru.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/guru/pendaftar/tambah?periode_id=' . $periodId);
        }

        $registrant = PpdbRegistrant::find($newId);
        $code = $registrant['kode_pendaftaran'] ?? '';

        $successMessage = 'Pendaftar berhasil ditambahkan. Kode pendaftaran: ' . $code;

        if ($registrant !== null) {
            $notifyResult = PpdbRegistrationNotifier::notifyRegistrant($registrant, $period ?? []);
            $successMessage .= $notifyResult['success']
                ? ' ' . $notifyResult['message']
                : ' Notifikasi WhatsApp belum terkirim: ' . $notifyResult['message'];
        }

        Session::flash('success', $successMessage);

        return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
    }

    public function destroy(Request $request, int $id): Response
    {
        $registrant = PpdbRegistrant::find($id);

        if ($registrant === null) {
            Session::flash('error', 'Pendaftar tidak ditemukan.');

            return $this->redirect('ppdb/guru/pendaftar');
        }

        $periodId = (int) ($registrant['periode_id'] ?? 0);
        $redirectUrl = 'ppdb/guru/pendaftar' . ($periodId > 0 ? '?periode_id=' . $periodId : '');

        if ($response = $this->ensureResponsibleAccess($periodId, 'ppdb/guru/pendaftar')) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, $redirectUrl)) {
            return $response;
        }

        try {
            $wasMigrated = (int) ($registrant['siswa_id'] ?? 0) > 0;

            if (!PpdbRegistrant::deleteById($id)) {
                Session::flash('error', 'Gagal menghapus pendaftar.');

                return $this->redirect($redirectUrl);
            }

            Session::flash(
                'success',
                $wasMigrated
                    ? 'Pendaftar PPDB dihapus. Data siswa hasil migrasi tidak ikut dihapus.'
                    : 'Pendaftar PPDB dihapus.'
            );
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus pendaftar: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    public function updateSelection(Request $request, int $id): Response
    {
        $registrant = PpdbRegistrant::find($id);

        if ($registrant === null) {
            Session::flash('error', 'Pendaftar tidak ditemukan.');

            return $this->redirect('ppdb/guru/pendaftar');
        }

        $periodId = (int) ($registrant['periode_id'] ?? 0);

        if ($response = $this->ensureResponsibleAccess($periodId, 'ppdb/guru/pendaftar')) {
            return $response;
        }

        $period = PpdbPeriod::find($periodId);

        if ($period === null || !PpdbPeriod::isStageEnabled($period, 'seleksi')) {
            Session::flash('error', 'Tahap seleksi belum diaktifkan.');

            return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'ppdb/guru/pendaftar?periode_id=' . $periodId)) {
            return $response;
        }

        $scheduleDate = (string) $request->input('jadwal_tanggal', '');
        $scheduleTime = (string) $request->input('jadwal_waktu', '');
        $scoreInput = trim((string) $request->input('nilai_seleksi', ''));
        $status = (string) $request->input('status_seleksi', $registrant['status_seleksi'] ?? 'belum_dijadwalkan');

        $selectionOptions = array_keys(PpdbRegistrant::selectionStatusOptions());

        if (!in_array($status, $selectionOptions, true)) {
            Session::flash('error', 'Status seleksi tidak valid.');

            return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
        }

        $schedule = null;

        if ($scheduleDate !== '') {
            $dateValid = strtotime($scheduleDate) !== false;
            $timeValid = $scheduleTime === '' || strtotime($scheduleDate . ' ' . $scheduleTime) !== false;

            if (!$dateValid || !$timeValid) {
                Session::flash('error', 'Tanggal atau waktu seleksi tidak valid.');

                return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
            }

            $schedule = $scheduleTime !== ''
                ? date('Y-m-d H:i:s', strtotime($scheduleDate . ' ' . $scheduleTime))
                : date('Y-m-d 09:00:00', strtotime($scheduleDate));
        }

        $score = null;
        if ($scoreInput !== '') {
            if (!is_numeric($scoreInput)) {
                Session::flash('error', 'Nilai seleksi harus berupa angka.');

                return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
            }

            $score = (float) $scoreInput;
        }

        if (!PpdbRegistrant::updateSelection($id, $schedule, $status, $score, $this->user()['id'] ?? null)) {
            Session::flash('error', 'Gagal memperbarui data seleksi.');

            return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
        }

        Session::flash('success', 'Data seleksi diperbarui.');

        return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
    }

    public function updateAnnouncement(Request $request, int $id): Response
    {
        $registrant = PpdbRegistrant::find($id);

        if ($registrant === null) {
            Session::flash('error', 'Pendaftar tidak ditemukan.');

            return $this->redirect('ppdb/guru/pendaftar');
        }

        $periodId = (int) ($registrant['periode_id'] ?? 0);

        if ($response = $this->ensureResponsibleAccess($periodId, 'ppdb/guru/pendaftar')) {
            return $response;
        }

        $period = PpdbPeriod::find($periodId);

        if ($period === null || !PpdbPeriod::isStageEnabled($period, 'pengumuman')) {
            Session::flash('error', 'Tahap pengumuman belum diaktifkan.');

            return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'ppdb/guru/pendaftar?periode_id=' . $periodId)) {
            return $response;
        }

        $status = (string) $request->input('status_pengumuman', $registrant['status_pengumuman'] ?? 'menunggu');
        $statusOptions = array_keys(PpdbRegistrant::announcementStatusOptions());

        if (!in_array($status, $statusOptions, true)) {
            Session::flash('error', 'Status pengumuman tidak valid.');

            return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
        }

        $date = (string) $request->input('pengumuman_tanggal', '');
        $time = (string) $request->input('pengumuman_waktu', '');
        $datetime = null;

        if ($date !== '') {
            $timestamp = strtotime($date . ($time !== '' ? ' ' . $time : ' 09:00:00'));
            if ($timestamp === false) {
                Session::flash('error', 'Tanggal atau waktu pengumuman tidak valid.');

                return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
            }
            $datetime = date('Y-m-d H:i:s', $timestamp);
        }

        if (!PpdbRegistrant::updateAnnouncement($id, $status, $datetime)) {
            Session::flash('error', 'Gagal memperbarui pengumuman.');

            return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
        }

        Session::flash('success', 'Data pengumuman diperbarui.');

        return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
    }

    public function updateReRegistration(Request $request, int $id): Response
    {
        $registrant = PpdbRegistrant::find($id);

        if ($registrant === null) {
            Session::flash('error', 'Pendaftar tidak ditemukan.');

            return $this->redirect('ppdb/guru/pendaftar');
        }

        $periodId = (int) ($registrant['periode_id'] ?? 0);

        if ($response = $this->ensureResponsibleAccess($periodId, 'ppdb/guru/pendaftar')) {
            return $response;
        }

        $period = PpdbPeriod::find($periodId);

        if ($period === null || !PpdbPeriod::isStageEnabled($period, 'daftar_ulang')) {
            Session::flash('error', 'Tahap daftar ulang belum diaktifkan.');

            return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'ppdb/guru/pendaftar?periode_id=' . $periodId)) {
            return $response;
        }

        $status = (string) $request->input('status_daftar_ulang', $registrant['status_daftar_ulang'] ?? 'menunggu');
        $statusOptions = array_keys(PpdbRegistrant::reRegistrationStatusOptions());

        if (!in_array($status, $statusOptions, true)) {
            Session::flash('error', 'Status daftar ulang tidak valid.');

            return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
        }

        $date = (string) $request->input('daftar_ulang_tanggal', '');
        $time = (string) $request->input('daftar_ulang_waktu', '');
        $datetime = null;

        if ($date !== '') {
            $timestamp = strtotime($date . ($time !== '' ? ' ' . $time : ' 09:00:00'));
            if ($timestamp === false) {
                Session::flash('error', 'Tanggal atau waktu daftar ulang tidak valid.');

                return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
            }
            $datetime = date('Y-m-d H:i:s', $timestamp);
        }

        if (!PpdbRegistrant::updateReRegistration($id, $status, $datetime, $this->user()['id'] ?? null)) {
            Session::flash('error', 'Gagal memperbarui daftar ulang.');

            return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
        }

        Session::flash('success', 'Data daftar ulang diperbarui.');

        return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
    }

    public function updatePayment(Request $request, int $id): Response
    {
        $registrant = PpdbRegistrant::find($id);

        if ($registrant === null) {
            Session::flash('error', 'Pendaftar tidak ditemukan.');

            return $this->redirect('ppdb/guru/pendaftar');
        }

        $periodId = (int) ($registrant['periode_id'] ?? 0);

        if ($response = $this->ensureResponsibleAccess($periodId, 'ppdb/guru/pendaftar')) {
            return $response;
        }

        $period = PpdbPeriod::find($periodId);

        if ($period === null || !PpdbPeriod::isStageEnabled($period, 'pembayaran')) {
            Session::flash('error', 'Tahap pembayaran belum diaktifkan.');

            return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'ppdb/guru/pendaftar?periode_id=' . $periodId)) {
            return $response;
        }

        $status = (string) $request->input('status_pembayaran', $registrant['status_pembayaran'] ?? 'menunggu');
        $statusOptions = array_keys(PpdbRegistrant::paymentStatusOptions());

        if (!in_array($status, $statusOptions, true)) {
            Session::flash('error', 'Status pembayaran tidak valid.');

            return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
        }

        $amountInput = trim((string) $request->input('nominal_pembayaran', ''));
        $amount = null;
        if ($amountInput !== '') {
            if (!is_numeric($amountInput)) {
                Session::flash('error', 'Nominal pembayaran harus berupa angka.');

                return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
            }
            $amount = (float) $amountInput;
        }

        $date = (string) $request->input('pembayaran_tanggal', '');
        $time = (string) $request->input('pembayaran_waktu', '');
        $datetime = null;

        if ($date !== '') {
            $timestamp = strtotime($date . ($time !== '' ? ' ' . $time : ' 09:00:00'));
            if ($timestamp === false) {
                Session::flash('error', 'Tanggal atau waktu pembayaran tidak valid.');

                return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
            }
            $datetime = date('Y-m-d H:i:s', $timestamp);
        }

        if ($status === 'lunas' && $amount === null) {
            Session::flash('error', 'Nominal pembayaran wajib diisi untuk status lunas.');

            return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
        }

        if (!PpdbRegistrant::updatePayment($id, $status, $amount, $datetime, $this->user()['id'] ?? null)) {
            Session::flash('error', 'Gagal memperbarui pembayaran.');

            return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
        }

        Session::flash('success', 'Data pembayaran diperbarui.');

        return $this->redirect('ppdb/guru/pendaftar?periode_id=' . $periodId);
    }
}

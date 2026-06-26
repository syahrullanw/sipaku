<?php

namespace Modules\Ppdb\Controllers\Guru;

use App\Models\PpdbPeriod;
use App\Models\PpdbPeriodResponsible;
use App\Services\PpdbBroadcastService;
use App\Support\PpdbMessageSetting;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Ppdb\Controllers\Controller;

class BroadcastController extends Controller
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

        $periodId = (int) $request->query('periode_id', 0);
        if ($periodId <= 0 && !empty($periodOptions)) {
            $periodId = (int) array_key_first($periodOptions);
        }

        if (!isset($periodOptions[$periodId])) {
            Session::flash('error', 'Anda tidak memiliki akses ke periode PPDB yang dipilih.');

            return $this->redirect('ppdb/guru/broadcast');
        }

        $period = PpdbPeriod::find($periodId);
        $templates = PpdbMessageSetting::get();

        return $this->render('ppdb/admin/broadcast/index', [
            'title' => 'Broadcast PPDB',
            'pageTitle' => 'Broadcast Pesan PPDB',
            'activeMenu' => 'ppdb-broadcast-guru',
            'periodOptions' => $periodOptions,
            'selectedPeriodId' => $periodId,
            'selectedPeriod' => $period,
            'broadcastTemplate' => $templates['broadcast_template'],
            'registrationTemplate' => $templates['registration_template'],
            'placeholders' => PpdbMessageSetting::placeholders(),
            'placeholderDescriptions' => PpdbMessageSetting::placeholderDescriptions(),
            'submitRoute' => 'ppdb/guru/broadcast',
            'indexRoute' => 'ppdb/guru/broadcast',
            'isAdmin' => false,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureResponsibleAccess(null, 'ppdb/guru')) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'ppdb/guru/broadcast')) {
            return $response;
        }

        $action = (string) $request->input('action', '');
        $periodId = (int) $request->input('periode_id', 0);

        if ($action === 'save_templates') {
            $registrationTemplate = trim((string) $request->input('registration_template', ''));
            $broadcastTemplate = trim((string) $request->input('broadcast_template', ''));

            try {
                PpdbMessageSetting::save($registrationTemplate, $broadcastTemplate);
                Session::flash('success', 'Template pesan PPDB berhasil disimpan.');
            } catch (\Throwable $exception) {
                Session::flash('error', 'Gagal menyimpan template: ' . $exception->getMessage());
                Session::flashInput($request->all());
            }

            return $this->redirect('ppdb/guru/broadcast?periode_id=' . $periodId);
        }

        if ($action !== 'send_broadcast') {
            Session::flash('error', 'Aksi tidak valid.');

            return $this->redirect('ppdb/guru/broadcast');
        }

        if ($response = $this->ensureResponsibleAccess($periodId, 'ppdb/guru/broadcast')) {
            return $response;
        }

        $period = $periodId > 0 ? PpdbPeriod::find($periodId) : null;
        $message = trim((string) $request->input('broadcast_message', ''));

        if ($period === null) {
            Session::flash('error', 'Periode PPDB tidak ditemukan.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/guru/broadcast');
        }

        if ($message === '') {
            Session::flash('error', 'Pesan broadcast wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/guru/broadcast?periode_id=' . $periodId);
        }

        try {
            $summary = PpdbBroadcastService::dispatchToPeriod($period, $message);
            Session::flash(
                'success',
                sprintf(
                    'Broadcast diproses. Total: %d, terkirim langsung: %d, masuk antrian: %d, gagal: %d, tanpa nomor: %d.',
                    $summary['total'],
                    $summary['sent'],
                    $summary['queued'],
                    $summary['failed'],
                    $summary['skipped_no_phone']
                )
            );
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memproses broadcast: ' . $exception->getMessage());
            Session::flashInput($request->all());
        }

        return $this->redirect('ppdb/guru/broadcast?periode_id=' . $periodId);
    }
}

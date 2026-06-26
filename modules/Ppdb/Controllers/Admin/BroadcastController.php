<?php

namespace Modules\Ppdb\Controllers\Admin;

use App\Models\PpdbPeriod;
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
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        $periodOptions = PpdbPeriod::options('aktif');
        $periodId = (int) $request->query('periode_id', 0);
        if ($periodId <= 0 && !empty($periodOptions)) {
            $periodId = (int) array_key_first($periodOptions);
        }

        $period = $periodId > 0 ? PpdbPeriod::find($periodId) : null;
        $templates = PpdbMessageSetting::get();

        return $this->render('ppdb/admin/broadcast/index', [
            'title' => 'Broadcast PPDB',
            'pageTitle' => 'Broadcast Pesan PPDB',
            'activeMenu' => 'ppdb-broadcast-admin',
            'periodOptions' => $periodOptions,
            'selectedPeriodId' => $periodId,
            'selectedPeriod' => $period,
            'broadcastTemplate' => $templates['broadcast_template'],
            'registrationTemplate' => $templates['registration_template'],
            'placeholders' => PpdbMessageSetting::placeholders(),
            'placeholderDescriptions' => PpdbMessageSetting::placeholderDescriptions(),
            'submitRoute' => 'ppdb/admin/broadcast',
            'indexRoute' => 'ppdb/admin/broadcast',
            'isAdmin' => true,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'ppdb/admin/broadcast')) {
            return $response;
        }

        $action = (string) $request->input('action', '');

        if ($action === 'save_templates') {
            $registrationTemplate = trim((string) $request->input('registration_template', ''));
            $broadcastTemplate = trim((string) $request->input('broadcast_template', ''));
            $periodId = (int) $request->input('periode_id', 0);

            try {
                PpdbMessageSetting::save($registrationTemplate, $broadcastTemplate);
                Session::flash('success', 'Template pesan PPDB berhasil disimpan.');
            } catch (\Throwable $exception) {
                Session::flash('error', 'Gagal menyimpan template: ' . $exception->getMessage());
                Session::flashInput($request->all());
            }

            return $this->redirect('ppdb/admin/broadcast' . ($periodId > 0 ? '?periode_id=' . $periodId : ''));
        }

        if ($action !== 'send_broadcast') {
            Session::flash('error', 'Aksi tidak valid.');

            return $this->redirect('ppdb/admin/broadcast');
        }

        $periodId = (int) $request->input('periode_id', 0);
        $period = $periodId > 0 ? PpdbPeriod::find($periodId) : null;
        $message = trim((string) $request->input('broadcast_message', ''));

        if ($period === null) {
            Session::flash('error', 'Periode PPDB tidak ditemukan.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/admin/broadcast');
        }

        if ($message === '') {
            Session::flash('error', 'Pesan broadcast wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/admin/broadcast?periode_id=' . $periodId);
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

        return $this->redirect('ppdb/admin/broadcast?periode_id=' . $periodId);
    }
}

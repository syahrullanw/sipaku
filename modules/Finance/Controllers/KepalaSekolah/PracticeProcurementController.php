<?php

namespace Modules\Finance\Controllers\KepalaSekolah;

use App\Models\PracticeProcurementRequest;
use App\Models\SchoolYear;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class PracticeProcurementController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardHeadmaster('dashboard')) {
            return $response;
        }

        $activeYearId = $this->activeSchoolYearId();
        $activeYear = $activeYearId !== null ? SchoolYear::find($activeYearId) : null;

        $tab = (string) $request->query('status', PracticeProcurementRequest::STATUS_SUBMITTED);
        $validTabs = [
            PracticeProcurementRequest::STATUS_SUBMITTED,
            PracticeProcurementRequest::STATUS_APPROVED,
            PracticeProcurementRequest::STATUS_REJECTED,
            'all',
        ];

        if (!in_array($tab, $validTabs, true)) {
            $tab = PracticeProcurementRequest::STATUS_SUBMITTED;
        }

        $filters = [];
        if ($activeYearId !== null) {
            $filters['year_id'] = $activeYearId;
        }

        if ($tab !== 'all') {
            $filters['statuses'] = [$tab];
        }

        $requests = PracticeProcurementRequest::list($filters);

        return $this->render('finance/kepsek/procurements/index', [
            'title' => 'Persetujuan Pengadaan Praktikum',
            'pageTitle' => 'Persetujuan Pengadaan Praktikum',
            'activeMenu' => 'finance-headmaster-procurements',
            'activeYear' => $activeYear,
            'activeYearId' => $activeYearId,
            'statusTabs' => $validTabs,
            'currentTab' => $tab,
            'requests' => $requests,
            'statusLabels' => PracticeProcurementRequest::statusLabels(),
        ], 'admin');
    }

    public function approve(Request $request, int $id): Response
    {
        if ($response = $this->guardHeadmaster('keuangan/kepala-sekolah/pengadaan')) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/kepala-sekolah/pengadaan')) {
            return $response;
        }

        $existing = PracticeProcurementRequest::find($id);

        if ($existing === null) {
            Session::flash('error', 'Pengajuan tidak ditemukan.');

            return $this->redirect('keuangan/kepala-sekolah/pengadaan');
        }

        if ((string) ($existing['status'] ?? '') !== PracticeProcurementRequest::STATUS_SUBMITTED) {
            Session::flash('error', 'Hanya pengajuan yang menunggu review yang dapat disetujui.');

            return $this->redirect('keuangan/kepala-sekolah/pengadaan');
        }

        $userId = (int) ($this->user()['id'] ?? 0);
        $note = trim((string) $request->input('catatan', ''));
        $now = date('Y-m-d H:i:s');

        PracticeProcurementRequest::updateById($id, [
            'status' => PracticeProcurementRequest::STATUS_APPROVED,
            'reviewed_at' => $now,
            'reviewed_by_user_id' => $userId > 0 ? $userId : null,
            'review_note' => $note !== '' ? $note : null,
            'updated_at' => $now,
        ]);

        Session::flash('success', 'Pengajuan disetujui dan diteruskan kepada bendahara.');

        return $this->redirect('keuangan/kepala-sekolah/pengadaan');
    }

    public function reject(Request $request, int $id): Response
    {
        if ($response = $this->guardHeadmaster('keuangan/kepala-sekolah/pengadaan')) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/kepala-sekolah/pengadaan')) {
            return $response;
        }

        $existing = PracticeProcurementRequest::find($id);

        if ($existing === null) {
            Session::flash('error', 'Pengajuan tidak ditemukan.');

            return $this->redirect('keuangan/kepala-sekolah/pengadaan');
        }

        if ((string) ($existing['status'] ?? '') !== PracticeProcurementRequest::STATUS_SUBMITTED) {
            Session::flash('error', 'Hanya pengajuan yang menunggu review yang dapat ditolak.');

            return $this->redirect('keuangan/kepala-sekolah/pengadaan');
        }

        $note = trim((string) $request->input('catatan', ''));
        if ($note === '') {
            Session::flash('error', 'Catatan penolakan wajib diisi.');

            return $this->redirect('keuangan/kepala-sekolah/pengadaan');
        }

        $userId = (int) ($this->user()['id'] ?? 0);
        $now = date('Y-m-d H:i:s');

        PracticeProcurementRequest::updateById($id, [
            'status' => PracticeProcurementRequest::STATUS_REJECTED,
            'reviewed_at' => $now,
            'reviewed_by_user_id' => $userId > 0 ? $userId : null,
            'review_note' => $note,
            'updated_at' => $now,
        ]);

        Session::flash('success', 'Pengajuan ditolak dan dikembalikan ke Kaprodi.');

        return $this->redirect('keuangan/kepala-sekolah/pengadaan');
    }
}

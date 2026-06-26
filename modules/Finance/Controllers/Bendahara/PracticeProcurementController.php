<?php

namespace Modules\Finance\Controllers\Bendahara;

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
        if ($response = $this->guardBendahara('dashboard')) {
            return $response;
        }

        $activeYearId = $this->activeSchoolYearId();
        $activeYear = $activeYearId !== null ? SchoolYear::find($activeYearId) : null;

        $tab = (string) $request->query('status', PracticeProcurementRequest::STATUS_APPROVED);
        $validTabs = [
            PracticeProcurementRequest::STATUS_APPROVED,
            PracticeProcurementRequest::STATUS_FUNDED,
            PracticeProcurementRequest::STATUS_REPORTED,
            'all',
        ];

        if (!in_array($tab, $validTabs, true)) {
            $tab = PracticeProcurementRequest::STATUS_APPROVED;
        }

        $filters = [];
        if ($activeYearId !== null) {
            $filters['year_id'] = $activeYearId;
        }

        if ($tab !== 'all') {
            $filters['statuses'] = [$tab];
        } else {
            $filters['statuses'] = [
                PracticeProcurementRequest::STATUS_APPROVED,
                PracticeProcurementRequest::STATUS_FUNDED,
                PracticeProcurementRequest::STATUS_REPORTED,
            ];
        }

        $requests = PracticeProcurementRequest::list($filters);

        return $this->render('finance/bendahara/procurements/index', [
            'title' => 'Pengadaan Alat Praktik',
            'pageTitle' => 'Pengadaan Alat Praktik Jurusan',
            'activeMenu' => 'finance-bendahara-procurements',
            'activeYear' => $activeYear,
            'activeYearId' => $activeYearId,
            'statusTabs' => $validTabs,
            'currentTab' => $tab,
            'requests' => $requests,
            'statusLabels' => PracticeProcurementRequest::statusLabels(),
        ], 'admin');
    }

    public function fund(Request $request, int $id): Response
    {
        if ($response = $this->guardBendahara('keuangan/bendahara/pengadaan')) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/bendahara/pengadaan')) {
            return $response;
        }

        $existing = PracticeProcurementRequest::find($id);

        if ($existing === null) {
            Session::flash('error', 'Pengajuan tidak ditemukan.');

            return $this->redirect('keuangan/bendahara/pengadaan');
        }

        if ((string) ($existing['status'] ?? '') !== PracticeProcurementRequest::STATUS_APPROVED) {
            Session::flash('error', 'Hanya pengajuan yang sudah disetujui kepala sekolah yang dapat dicairkan.');

            return $this->redirect('keuangan/bendahara/pengadaan');
        }

        $note = trim((string) $request->input('catatan', ''));
        $userId = (int) ($this->user()['id'] ?? 0);
        $now = date('Y-m-d H:i:s');

        PracticeProcurementRequest::updateById($id, [
            'status' => PracticeProcurementRequest::STATUS_FUNDED,
            'funded_at' => $now,
            'funded_by_user_id' => $userId > 0 ? $userId : null,
            'funding_note' => $note !== '' ? $note : null,
            'updated_at' => $now,
        ]);

        Session::flash('success', 'Status pengadaan diperbarui menjadi Dicairkan.');

        return $this->redirect('keuangan/bendahara/pengadaan');
    }
}

<?php

namespace Modules\Finance\Controllers\Guru;

use App\Models\AccountabilityReport;
use App\Models\ActivityFund;
use App\Models\UnexpectedExpense;
use App\Services\Finance\AccountabilityReportService;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class AccountabilityController extends Controller
{
    public function activity(Request $request, int $activityId): Response
    {
        if ($response = $this->guardRole('guru')) {
            return $response;
        }

        $activity = $this->loadTeacherActivity($activityId);

        if ($activity === null) {
            Session::flash('error', 'Pengajuan dana kegiatan tidak ditemukan.');

            return $this->redirect('keuangan/guru/dana-kegiatan');
        }

        if (!in_array((string) ($activity['status'] ?? ''), ['disetujui', 'selesai'], true)) {
            Session::flash('warning', 'LPJ hanya dapat diisi setelah pengajuan disetujui.');

            return $this->redirect('keuangan/guru/dana-kegiatan');
        }

        $report = AccountabilityReport::findByEntity('dana_kegiatan', $activityId);

        return $this->render('finance/guru/accountability/activity', [
            'title' => 'LPJ Dana Kegiatan',
            'pageTitle' => 'LPJ Dana Kegiatan',
            'activeMenu' => 'finance-guru-activities',
            'activity' => $activity,
            'report' => $report,
            'defaultTitle' => 'LPJ - ' . ($activity['judul'] ?? ''),
            'defaultDate' => date('Y-m-d\TH:i'),
        ], 'admin');
    }

    public function storeActivity(Request $request, int $activityId): Response
    {
        if ($response = $this->guardRole('guru')) {
            return $response;
        }

        $redirect = 'keuangan/guru/dana-kegiatan/' . $activityId . '/lpj';

        if ($response = $this->guardCsrfOrRedirect($request, $redirect)) {
            return $response;
        }

        $activity = $this->loadTeacherActivity($activityId);

        if ($activity === null) {
            Session::flash('error', 'Pengajuan dana kegiatan tidak ditemukan.');

            return $this->redirect('keuangan/guru/dana-kegiatan');
        }

        if (!in_array((string) ($activity['status'] ?? ''), ['disetujui', 'selesai'], true)) {
            Session::flash('warning', 'LPJ hanya dapat diisi setelah pengajuan disetujui.');

            return $this->redirect('keuangan/guru/dana-kegiatan');
        }

        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
        $files = $request->files();

        try {
            AccountabilityReportService::submit('dana_kegiatan', $activityId, [
                'judul' => $request->input('judul'),
                'nominal' => $request->input('nominal'),
                'tanggal' => $request->input('tanggal'),
                'deskripsi' => $request->input('deskripsi'),
                'lampiran' => $files['lampiran'] ?? null,
                'dibuat_oleh' => $userId,
            ]);

            Session::flash('success', 'LPJ dana kegiatan berhasil disimpan.');

            return $this->redirect('keuangan/guru/dana-kegiatan');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan LPJ: ' . $exception->getMessage());

            return $this->redirect($redirect);
        }
    }

    public function unexpected(Request $request, int $expenseId): Response
    {
        if ($response = $this->guardRole('guru')) {
            return $response;
        }

        $expense = $this->loadTeacherUnexpectedExpense($expenseId);

        if ($expense === null) {
            Session::flash('error', 'Pengeluaran tak terduga tidak ditemukan.');

            return $this->redirect('keuangan/guru');
        }

        $report = AccountabilityReport::findByEntity('pengeluaran_tak_terduga', $expenseId);

        return $this->render('finance/guru/accountability/unexpected', [
            'title' => 'LPJ Pengeluaran Tak Terduga',
            'pageTitle' => 'LPJ Pengeluaran Tak Terduga',
            'activeMenu' => 'finance-guru-dashboard',
            'expense' => $expense,
            'report' => $report,
            'defaultTitle' => 'LPJ - ' . ($expense['deskripsi'] ?? $expense['kode_transaksi'] ?? 'Pengeluaran Tak Terduga'),
            'defaultDate' => date('Y-m-d\TH:i'),
        ], 'admin');
    }

    public function storeUnexpected(Request $request, int $expenseId): Response
    {
        if ($response = $this->guardRole('guru')) {
            return $response;
        }

        $redirect = 'keuangan/guru/pengeluaran-tak-terduga/' . $expenseId . '/lpj';

        if ($response = $this->guardCsrfOrRedirect($request, $redirect)) {
            return $response;
        }

        $expense = $this->loadTeacherUnexpectedExpense($expenseId);

        if ($expense === null) {
            Session::flash('error', 'Pengeluaran tak terduga tidak ditemukan.');

            return $this->redirect('keuangan/guru');
        }

        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
        $files = $request->files();

        try {
            AccountabilityReportService::submit('pengeluaran_tak_terduga', $expenseId, [
                'judul' => $request->input('judul'),
                'nominal' => $request->input('nominal'),
                'tanggal' => $request->input('tanggal'),
                'deskripsi' => $request->input('deskripsi'),
                'lampiran' => $files['lampiran'] ?? null,
                'dibuat_oleh' => $userId,
            ]);

            Session::flash('success', 'LPJ pengeluaran tak terduga berhasil disimpan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan LPJ: ' . $exception->getMessage());

            return $this->redirect($redirect);
        }

        return $this->redirect('keuangan/guru');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadTeacherActivity(int $activityId): ?array
    {
        if ($activityId <= 0) {
            return null;
        }

        $user = $this->user();
        $teacherId = $user !== null ? (int) ($user['teacher_id'] ?? 0) : 0;

        if ($teacherId <= 0) {
            return null;
        }

        $activity = ActivityFund::find($activityId);

        return $activity !== null && (int) ($activity['guru_id'] ?? 0) === $teacherId
            ? $activity
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadTeacherUnexpectedExpense(int $expenseId): ?array
    {
        if ($expenseId <= 0) {
            return null;
        }

        $user = $this->user();
        $teacherId = $user !== null ? (int) ($user['teacher_id'] ?? 0) : 0;

        if ($teacherId <= 0) {
            return null;
        }

        $expense = UnexpectedExpense::find($expenseId);

        if ($expense === null || (int) ($expense['guru_id'] ?? 0) !== $teacherId) {
            return null;
        }

        return $expense;
    }
}

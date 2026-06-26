<?php

namespace Modules\Finance\Controllers\Siswa;

use App\Models\AccountabilityReport;
use App\Models\UnexpectedExpense;
use App\Services\Finance\AccountabilityReportService;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class AccountabilityController extends Controller
{
    public function unexpected(Request $request, int $expenseId): Response
    {
        if ($response = $this->guardRole('siswa')) {
            return $response;
        }

        $expense = $this->loadStudentUnexpectedExpense($expenseId);

        if ($expense === null) {
            Session::flash('error', 'Pengeluaran tak terduga tidak ditemukan.');

            return $this->redirect('keuangan/siswa');
        }

        $report = AccountabilityReport::findByEntity('pengeluaran_tak_terduga', $expenseId);

        return $this->render('finance/siswa/accountability/unexpected', [
            'title' => 'LPJ Pengeluaran Tak Terduga',
            'pageTitle' => 'LPJ Pengeluaran Tak Terduga',
            'activeMenu' => 'finance-siswa-dashboard',
            'expense' => $expense,
            'report' => $report,
            'defaultTitle' => 'LPJ - ' . ($expense['deskripsi'] ?? $expense['kode_transaksi'] ?? 'Pengeluaran Tak Terduga'),
            'defaultDate' => date('Y-m-d\TH:i'),
        ], 'admin');
    }

    public function storeUnexpected(Request $request, int $expenseId): Response
    {
        if ($response = $this->guardRole('siswa')) {
            return $response;
        }

        $redirect = 'keuangan/siswa/pengeluaran-tak-terduga/' . $expenseId . '/lpj';

        if ($response = $this->guardCsrfOrRedirect($request, $redirect)) {
            return $response;
        }

        $expense = $this->loadStudentUnexpectedExpense($expenseId);

        if ($expense === null) {
            Session::flash('error', 'Pengeluaran tak terduga tidak ditemukan.');

            return $this->redirect('keuangan/siswa');
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

        return $this->redirect('keuangan/siswa');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadStudentUnexpectedExpense(int $expenseId): ?array
    {
        if ($expenseId <= 0) {
            return null;
        }

        $user = $this->user();
        $studentId = $user !== null ? (int) ($user['student_id'] ?? 0) : 0;

        if ($studentId <= 0) {
            return null;
        }

        $expense = UnexpectedExpense::find($expenseId);

        if ($expense === null || (int) ($expense['siswa_id'] ?? 0) !== $studentId) {
            return null;
        }

        return $expense;
    }
}


<?php

namespace Modules\Finance\Controllers\Bendahara;

use App\Models\GeneralCashTransaction;
use App\Models\SavingsPoolAdjustment;
use App\Services\Finance\GeneralCashService;
use App\Services\Finance\SavingsPoolService;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class GeneralCashController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        $balance = $schoolYearId !== null ? GeneralCashService::balance($schoolYearId) : 0.0;
        $transactions = $schoolYearId !== null ? GeneralCashTransaction::latestForYear($schoolYearId, 20) : [];
        $savingsOutstanding = $schoolYearId !== null ? SavingsPoolService::outstanding($schoolYearId) : 0.0;
        $savingsAdjustments = $schoolYearId !== null ? SavingsPoolAdjustment::latest($schoolYearId, 10) : [];
        $billingOptions = $schoolYearId !== null ? $this->billingCashOptions($schoolYearId) : [];
        $purchaseOptions = $schoolYearId !== null ? $this->purchaseCashOptions($schoolYearId) : [];

        return $this->render('finance/bendahara/general-cash/index', [
            'title' => 'Manajemen Kas Utama',
            'pageTitle' => 'Kas Utama Sekolah',
            'activeMenu' => 'finance-bendahara-general-cash',
            'hasActiveYear' => $schoolYearId !== null,
            'balance' => $balance,
            'transactions' => $transactions,
            'billingOptions' => $billingOptions,
            'purchaseOptions' => $purchaseOptions,
            'savingsOutstanding' => $savingsOutstanding,
            'savingsHistory' => $savingsAdjustments,
        ], 'admin');
    }

    public function storeExternal(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $redirect = 'keuangan/bendahara/kas-utama';

        if ($response = $this->guardCsrfOrRedirect($request, $redirect)) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        if ($schoolYearId === null) {
            Session::flash('error', 'Tahun ajaran aktif belum ditetapkan.');

            return $this->redirect($redirect);
        }

        $amount = $this->normalizeAmount((string) $request->input('amount', '0'));
        $note = trim((string) $request->input('note', ''));
        $recordedAt = (string) $request->input('recorded_at', '');

        $timestamp = date('Y-m-d H:i:s');
        if ($recordedAt !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d\TH:i', $recordedAt);
            if ($parsed instanceof \DateTimeInterface) {
                $timestamp = $parsed->format('Y-m-d H:i:s');
            }
        }

        try {
            $user = $this->user();
            $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
            GeneralCashService::addExternal($schoolYearId, $amount, [
                'description' => $note,
                'recorded_at' => $timestamp,
                'user_id' => $userId,
            ]);
            Session::flash('success', 'Dana eksternal berhasil ditambahkan ke kas utama.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menambahkan dana: ' . $exception->getMessage());
        }

        return $this->redirect($redirect);
    }

    public function transferFromBilling(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $redirect = 'keuangan/bendahara/kas-utama';

        if ($response = $this->guardCsrfOrRedirect($request, $redirect)) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        if ($schoolYearId === null) {
            Session::flash('error', 'Tahun ajaran aktif belum ditetapkan.');

            return $this->redirect($redirect);
        }

        $billingId = (int) $request->input('billing_id', 0);
        $amount = $this->normalizeAmount((string) $request->input('amount', '0'));
        $note = trim((string) $request->input('note', ''));
        $recordedAt = (string) $request->input('recorded_at', '');

        $timestamp = date('Y-m-d H:i:s');
        if ($recordedAt !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d\TH:i', $recordedAt);
            if ($parsed instanceof \DateTimeInterface) {
                $timestamp = $parsed->format('Y-m-d H:i:s');
            }
        }

        try {
            $user = $this->user();
            $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
            GeneralCashService::transferFromBilling($schoolYearId, $billingId, $amount, [
                'description' => $note,
                'recorded_at' => $timestamp,
                'user_id' => $userId,
            ]);
            Session::flash('success', 'Dana kas tagihan berhasil dipindahkan ke kas utama.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memindahkan dana: ' . $exception->getMessage());
        }

        return $this->redirect($redirect);
    }

    public function transferToBilling(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $redirect = 'keuangan/bendahara/kas-utama';

        if ($response = $this->guardCsrfOrRedirect($request, $redirect)) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        if ($schoolYearId === null) {
            Session::flash('error', 'Tahun ajaran aktif belum ditetapkan.');

            return $this->redirect($redirect);
        }

        $billingId = (int) $request->input('billing_id', 0);
        $amount = $this->normalizeAmount((string) $request->input('amount', '0'));
        $note = trim((string) $request->input('note', ''));
        $recordedAt = (string) $request->input('recorded_at', '');

        $timestamp = date('Y-m-d H:i:s');
        if ($recordedAt !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d\TH:i', $recordedAt);
            if ($parsed instanceof \DateTimeInterface) {
                $timestamp = $parsed->format('Y-m-d H:i:s');
            }
        }

        try {
            $user = $this->user();
            $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
            GeneralCashService::transferToBilling($schoolYearId, $billingId, $amount, [
                'description' => $note,
                'recorded_at' => $timestamp,
                'user_id' => $userId,
            ]);
            Session::flash('success', 'Dana kas utama berhasil dikembalikan ke kas tagihan.');
            $this->flashGeneralCashDeficitWarning($schoolYearId);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal mengembalikan dana: ' . $exception->getMessage());
        }

        return $this->redirect($redirect);
    }

    public function transferFromPurchase(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $redirect = 'keuangan/bendahara/kas-utama';

        if ($response = $this->guardCsrfOrRedirect($request, $redirect)) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        if ($schoolYearId === null) {
            Session::flash('error', 'Tahun ajaran aktif belum ditetapkan.');

            return $this->redirect($redirect);
        }

        $purchaseId = (int) $request->input('purchase_id', 0);
        $amount = $this->normalizeAmount((string) $request->input('amount', '0'));
        $note = trim((string) $request->input('note', ''));
        $recordedAt = (string) $request->input('recorded_at', '');

        $timestamp = date('Y-m-d H:i:s');
        if ($recordedAt !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d\TH:i', $recordedAt);
            if ($parsed instanceof \DateTimeInterface) {
                $timestamp = $parsed->format('Y-m-d H:i:s');
            }
        }

        try {
            $user = $this->user();
            $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
            GeneralCashService::transferFromPurchase($schoolYearId, $purchaseId, $amount, [
                'description' => $note,
                'recorded_at' => $timestamp,
                'user_id' => $userId,
            ]);
            Session::flash('success', 'Dana kas pembelian berhasil dipindahkan ke kas utama.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memindahkan dana: ' . $exception->getMessage());
        }

        return $this->redirect($redirect);
    }

    public function transferToPurchase(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $redirect = 'keuangan/bendahara/kas-utama';

        if ($response = $this->guardCsrfOrRedirect($request, $redirect)) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        if ($schoolYearId === null) {
            Session::flash('error', 'Tahun ajaran aktif belum ditetapkan.');

            return $this->redirect($redirect);
        }

        $purchaseId = (int) $request->input('purchase_id', 0);
        $amount = $this->normalizeAmount((string) $request->input('amount', '0'));
        $note = trim((string) $request->input('note', ''));
        $recordedAt = (string) $request->input('recorded_at', '');

        $timestamp = date('Y-m-d H:i:s');
        if ($recordedAt !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d\TH:i', $recordedAt);
            if ($parsed instanceof \DateTimeInterface) {
                $timestamp = $parsed->format('Y-m-d H:i:s');
            }
        }

        try {
            $user = $this->user();
            $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
            GeneralCashService::transferToPurchase($schoolYearId, $purchaseId, $amount, [
                'description' => $note,
                'recorded_at' => $timestamp,
                'user_id' => $userId,
            ]);
            Session::flash('success', 'Dana kas utama berhasil dialokasikan ke kas pembelian.');
            $this->flashGeneralCashDeficitWarning($schoolYearId);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memindahkan dana: ' . $exception->getMessage());
        }

        return $this->redirect($redirect);
    }

    public function borrowFromSavings(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $redirect = 'keuangan/bendahara/kas-utama';

        if ($response = $this->guardCsrfOrRedirect($request, $redirect)) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        if ($schoolYearId === null) {
            Session::flash('error', 'Tahun ajaran aktif belum ditetapkan.');

            return $this->redirect($redirect);
        }

        $amount = $this->normalizeAmount((string) $request->input('amount', '0'));
        $note = trim((string) $request->input('note', ''));
        $recordedAt = (string) $request->input('recorded_at', '');

        $timestamp = date('Y-m-d H:i:s');
        if ($recordedAt !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d\TH:i', $recordedAt);
            if ($parsed instanceof \DateTimeInterface) {
                $timestamp = $parsed->format('Y-m-d H:i:s');
            }
        }

        try {
            $user = $this->user();
            $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
            GeneralCashService::borrowFromSavings($schoolYearId, $amount, [
                'description' => $note,
                'recorded_at' => $timestamp,
                'user_id' => $userId,
            ]);
            Session::flash('success', 'Dana tabungan berhasil dipindahkan sementara ke kas utama.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memproses pinjaman tabungan: ' . $exception->getMessage());
        }

        return $this->redirect($redirect);
    }

    public function returnToSavings(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $redirect = 'keuangan/bendahara/kas-utama';

        if ($response = $this->guardCsrfOrRedirect($request, $redirect)) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        if ($schoolYearId === null) {
            Session::flash('error', 'Tahun ajaran aktif belum ditetapkan.');

            return $this->redirect($redirect);
        }

        $amount = $this->normalizeAmount((string) $request->input('amount', '0'));
        $note = trim((string) $request->input('note', ''));
        $recordedAt = (string) $request->input('recorded_at', '');

        $timestamp = date('Y-m-d H:i:s');
        if ($recordedAt !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d\TH:i', $recordedAt);
            if ($parsed instanceof \DateTimeInterface) {
                $timestamp = $parsed->format('Y-m-d H:i:s');
            }
        }

        try {
            $user = $this->user();
            $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
            GeneralCashService::returnToSavings($schoolYearId, $amount, [
                'description' => $note,
                'recorded_at' => $timestamp,
                'user_id' => $userId,
            ]);
            Session::flash('success', 'Pengembalian dana tabungan berhasil dicatat.');
            $this->flashGeneralCashDeficitWarning($schoolYearId);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal mengembalikan dana tabungan: ' . $exception->getMessage());
        }

        return $this->redirect($redirect);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function billingCashOptions(int $schoolYearId): array
    {
        $connection = Database::connection();
        $statement = $connection->prepare(
            'SELECT
                t.id,
                t.judul,
                t.kode,
                tk.saldo_akhir
             FROM tagihan t
             JOIN tagihan_kas tk ON tk.tagihan_id = t.id
             WHERE t.tahun_ajaran_id = :year
             ORDER BY t.judul ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['judul'] ?? 'Tagihan'),
                'code' => (string) ($row['kode'] ?? ''),
                'balance' => (float) ($row['saldo_akhir'] ?? 0.0),
            ];
        }, $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function purchaseCashOptions(int $schoolYearId): array
    {
        $connection = Database::connection();
        $statement = $connection->prepare(
            'SELECT
                p.id,
                p.kode,
                p.item_label,
                s.nama AS siswa_nama,
                COALESCE(pk.saldo_akhir, 0) AS saldo_akhir
             FROM pembelian_perlengkapan p
             JOIN siswa s ON s.id = p.siswa_id
             LEFT JOIN pembelian_kas pk ON pk.pembelian_id = p.id
             WHERE p.tahun_ajaran_id = :year
               AND p.status <> \'dibatalkan\'
             ORDER BY p.created_at DESC
             LIMIT 60'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        return array_map(static function (array $row): array {
            $itemLabel = trim((string) ($row['item_label'] ?? 'Pembelian'));
            $student = trim((string) ($row['siswa_nama'] ?? ''));

            return [
                'id' => (int) ($row['id'] ?? 0),
                'title' => $itemLabel !== '' ? $itemLabel : 'Pembelian Perlengkapan',
                'student' => $student,
                'code' => (string) ($row['kode'] ?? ''),
                'balance' => (float) ($row['saldo_akhir'] ?? 0.0),
            ];
        }, $rows);
    }

    private function normalizeAmount(string $raw): float
    {
        $clean = preg_replace('/[^\d,\.]/', '', $raw);

        if ($clean === null || $clean === '') {
            return 0.0;
        }

        $lastComma = strrpos($clean, ',');
        $lastDot = strrpos($clean, '.');

        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif ($lastDot !== false && ($lastComma === false || $lastDot > $lastComma)) {
            $clean = str_replace(',', '', $clean);
        } else {
            $clean = str_replace(['.', ','], '', $clean);
        }

        return (float) $clean;
    }
}

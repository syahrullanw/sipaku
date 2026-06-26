<?php

namespace Modules\Finance\Controllers\Bendahara;

use App\Models\TeacherLoan;
use App\Services\Finance\BillingCashService;
use App\Services\Finance\CashflowService;
use App\Services\Finance\GeneralCashService;
use App\Support\FinanceCache;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class LoanController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        $awaitingLoans = $this->fetchLoansByStatuses($schoolYearId, ['diajukan', 'menunggu_acc']);
        $readyLoans = $this->fetchReadyLoans($schoolYearId);
        $historyLoans = $this->fetchDisbursedLoans($schoolYearId);
        $cashOptions = $this->fetchActiveCashOptions($schoolYearId);
        $generalCashBalance = $schoolYearId !== null ? GeneralCashService::balance($schoolYearId) : 0.0;

        return $this->render('finance/bendahara/loans/index', [
            'title' => 'Kasbon Guru',
            'pageTitle' => 'Pencairan Kasbon Guru',
            'activeMenu' => 'finance-bendahara-loans',
            'awaitingLoans' => $awaitingLoans,
            'readyLoans' => $readyLoans,
            'historyLoans' => $historyLoans,
            'cashOptions' => $cashOptions,
            'generalCashBalance' => $generalCashBalance,
            'hasActiveYear' => $schoolYearId !== null,
        ], 'admin');
    }

    public function disburse(Request $request, int $loanId): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $redirectUrl = 'keuangan/bendahara/kasbon';

        if ($response = $this->guardCsrfOrRedirect($request, $redirectUrl)) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        if ($schoolYearId === null || $schoolYearId <= 0) {
            Session::flash('error', 'Tahun ajaran aktif belum ditetapkan.');

            return $this->redirect($redirectUrl);
        }

        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;

        $note = trim((string) ($request->input('note') ?? ''));
        $disbursementInput = (string) ($request->input('disbursement_time') ?? '');

        $disbursementTime = date('Y-m-d H:i:s');
        if ($disbursementInput !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d\TH:i', $disbursementInput);
            if ($parsed instanceof \DateTimeInterface) {
                $disbursementTime = $parsed->format('Y-m-d H:i:s');
            }
        }

        $connection = Database::connection();

        $loanStatement = $connection->prepare(
            'SELECT kg.*, g.nama AS guru_nama
             FROM kasbon_guru kg
             LEFT JOIN guru g ON g.id = kg.guru_id
             WHERE kg.id = :id
             LIMIT 1'
        );

        if ($loanStatement === false) {
            Session::flash('error', 'Gagal memuat data kasbon.');

            return $this->redirect($redirectUrl);
        }

        $loanStatement->bindValue(':id', $loanId, \PDO::PARAM_INT);
        $loanStatement->execute();

        $loan = $loanStatement->fetch(\PDO::FETCH_ASSOC);

        if ($loan === false) {
            Session::flash('error', 'Data kasbon tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        if ((int) ($loan['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            Session::flash('error', 'Kasbon tidak terdaftar pada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ((string) ($loan['status'] ?? '') !== 'disetujui' || !empty($loan['tanggal_cair'])) {
            Session::flash('error', 'Kasbon belum siap atau sudah dicairkan sebelumnya.');

            return $this->redirect($redirectUrl);
        }

        $amount = (float) ($loan['nominal_diminta'] ?? 0.0);

        if ($amount <= 0.0) {
            Session::flash('error', 'Nominal kasbon tidak valid.');

            return $this->redirect($redirectUrl);
        }

        $sourceInput = trim((string) $request->input('cash_source', ''));
        if ($sourceInput === '') {
            Session::flash('error', 'Pilih sumber kas pencairan terlebih dahulu.');

            return $this->redirect($redirectUrl);
        }

        $sourceType = 'billing';
        $billingSourceId = 0;

        if ($sourceInput === 'general') {
            $sourceType = 'general';
        } elseif (preg_match('/^billing:(\d+)$/', $sourceInput, $matches)) {
            $billingSourceId = (int) $matches[1];
        } elseif (ctype_digit($sourceInput)) {
            $billingSourceId = (int) $sourceInput;
        } else {
            Session::flash('error', 'Sumber kas tidak valid.');

            return $this->redirect($redirectUrl);
        }

        $cash = null;

        if ($sourceType === 'billing') {
            if ($billingSourceId <= 0) {
                Session::flash('error', 'Pilih kas tagihan yang valid.');

                return $this->redirect($redirectUrl);
            }

            $cashStatement = $connection->prepare(
                'SELECT tk.tagihan_id, tk.saldo_akhir, t.kode, t.judul
                 FROM tagihan_kas tk
                 JOIN tagihan t ON t.id = tk.tagihan_id
                 WHERE tk.tagihan_id = :id
                   AND t.tahun_ajaran_id = :year
                   AND t.status = :status
                 LIMIT 1'
            );

            if ($cashStatement === false) {
                Session::flash('error', 'Gagal memuat data kas.');

                return $this->redirect($redirectUrl);
            }

            $cashStatement->bindValue(':id', $billingSourceId, \PDO::PARAM_INT);
            $cashStatement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
            $cashStatement->bindValue(':status', 'aktif');
            $cashStatement->execute();

            $cash = $cashStatement->fetch(\PDO::FETCH_ASSOC);

            if ($cash === false) {
                Session::flash('error', 'Kas tagihan tidak ditemukan atau tidak aktif.');

                return $this->redirect($redirectUrl);
            }

            if ((float) ($cash['saldo_akhir'] ?? 0.0) < $amount) {
                Session::flash('error', 'Saldo kas tagihan tidak mencukupi untuk pencairan kasbon ini.');

                return $this->redirect($redirectUrl);
            }
        } else {
            $cash = [
                'judul' => 'Kas Utama',
                'kode' => '',
            ];
        }

        $description = $this->buildCashflowDescription($loan, $cash, $note);
        $mergedNotes = $this->mergeNotes($loan, $cash, $note, $disbursementTime);

        try {
            $connection->beginTransaction();

            TeacherLoan::updateById($loanId, [
                'tanggal_cair' => $disbursementTime,
                'diverifikasi_oleh' => $userId,
                'updated_at' => $disbursementTime,
                'updated_by' => $userId,
                'catatan' => $mergedNotes,
            ]);

            if ($sourceType === 'billing') {
                BillingCashService::decrease($billingSourceId, $amount);
            } else {
                GeneralCashService::withdrawForLoan($schoolYearId, $amount, [
                    'description' => $note === '' ? ('Pencairan kasbon ' . ($loan['kode'] ?? '')) : $note,
                    'recorded_at' => $disbursementTime,
                    'user_id' => $userId,
                    'loan_id' => $loanId,
                ]);
            }

            CashflowService::record('keluar', 'kasbon', $amount, [
                'reference_id' => $loanId,
                'reference_code' => $loan['kode'] ?? null,
                'description' => $description,
                'user_id' => $userId,
                'recorded_at' => $disbursementTime,
                'school_year_id' => $schoolYearId,
            ]);

            if ($connection->inTransaction()) {
                $connection->commit();
            }

            FinanceCache::forget('kepsek_dashboard_loan_' . $schoolYearId);
            FinanceCache::forget('bendahara_dashboard_stats_' . $schoolYearId);
            FinanceCache::forget('bendahara_dashboard_stats_0');

            $this->flashGeneralCashDeficitWarning($schoolYearId);
            Session::flash('success', 'Kasbon berhasil dicairkan.');
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            Session::flash('error', 'Gagal mencairkan kasbon: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchLoansByStatuses(?int $schoolYearId, array $statuses): array
    {
        if ($schoolYearId === null || $schoolYearId <= 0 || empty($statuses)) {
            return [];
        }

        $statusValues = array_values($statuses);
        $statusPlaceholders = [];
        foreach ($statusValues as $index => $status) {
            $statusPlaceholders[] = ':status_' . $index;
        }
        $placeholders = implode(',', $statusPlaceholders);
        $connection = Database::connection();

        $sql = <<<SQL
SELECT kg.*, g.nama AS guru_nama
FROM kasbon_guru kg
LEFT JOIN guru g ON g.id = kg.guru_id
WHERE kg.tahun_ajaran_id = :year
  AND kg.status IN ({$placeholders})
ORDER BY kg.created_at ASC
SQL;

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);

        foreach ($statusValues as $index => $status) {
            $statement->bindValue(':status_' . $index, $status);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchReadyLoans(?int $schoolYearId): array
    {
        if ($schoolYearId === null || $schoolYearId <= 0) {
            return [];
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            "SELECT kg.*, g.nama AS guru_nama
             FROM kasbon_guru kg
             LEFT JOIN guru g ON g.id = kg.guru_id
             WHERE kg.tahun_ajaran_id = :year
               AND kg.status = 'disetujui'
             ORDER BY kg.tanggal_acc ASC, kg.created_at ASC"
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

        return array_values(array_filter($rows, static function (array $row): bool {
            $value = $row['tanggal_cair'] ?? null;
            if ($value === null) {
                return true;
            }

            $trimmed = is_string($value) ? trim($value) : '';

            return $trimmed === '' || $trimmed === '0000-00-00 00:00:00';
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchDisbursedLoans(?int $schoolYearId): array
    {
        if ($schoolYearId === null || $schoolYearId <= 0) {
            return [];
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            'SELECT kg.*, g.nama AS guru_nama
             FROM kasbon_guru kg
             LEFT JOIN guru g ON g.id = kg.guru_id
             WHERE kg.tahun_ajaran_id = :year
             ORDER BY kg.tanggal_cair DESC
             LIMIT 30'
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

        $filtered = array_filter($rows, static function (array $row): bool {
            $value = $row['tanggal_cair'] ?? null;
            if ($value === null) {
                return false;
            }

            $trimmed = is_string($value) ? trim($value) : '';

            return $trimmed !== '' && $trimmed !== '0000-00-00 00:00:00';
        });

        return array_slice(array_values($filtered), 0, 15);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchActiveCashOptions(?int $schoolYearId): array
    {
        if ($schoolYearId === null || $schoolYearId <= 0) {
            return [];
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            'SELECT tk.tagihan_id, tk.saldo_akhir, t.kode, t.judul
             FROM tagihan_kas tk
             JOIN tagihan t ON t.id = tk.tagihan_id
             WHERE t.tahun_ajaran_id = :year
               AND t.status = :status
               AND tk.saldo_akhir > 0
             ORDER BY t.judul ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
        $statement->bindValue(':status', 'aktif');
        $statement->execute();

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['tagihan_id'] ?? 0),
                'judul' => (string) ($row['judul'] ?? 'Kas Tagihan'),
                'kode' => (string) ($row['kode'] ?? ''),
                'saldo' => (float) ($row['saldo_akhir'] ?? 0.0),
            ];
        }, $rows);
    }

    /**
     * @param array<string, mixed> $loan
     * @param array<string, mixed> $cash
     */
    private function buildCashflowDescription(array $loan, array $cash, string $note): string
    {
        $teacherName = trim((string) ($loan['guru_nama'] ?? 'Guru'));
        $loanCode = trim((string) ($loan['kode'] ?? ''));
        $cashTitle = trim((string) ($cash['judul'] ?? 'Kas Aktif'));
        $cashCode = trim((string) ($cash['kode'] ?? ''));

        $parts = [];
        $parts[] = sprintf('Pencairan kasbon %s', $teacherName === '' ? 'Guru' : $teacherName);
        if ($loanCode !== '') {
            $parts[] = '(#' . $loanCode . ')';
        }

        $description = implode(' ', $parts);
        $description .= sprintf(' melalui kas %s', $cashTitle);
        if ($cashCode !== '') {
            $description .= ' (#' . $cashCode . ')';
        }

        if ($note !== '') {
            $description .= ' - ' . $note;
        }

        return $description;
    }

    /**
     * @param array<string, mixed> $loan
     * @param array<string, mixed> $cash
     */
    private function mergeNotes(array $loan, array $cash, string $note, string $disbursementTime): ?string
    {
        $existing = trim((string) ($loan['catatan'] ?? ''));

        $cashTitle = trim((string) ($cash['judul'] ?? 'Kas Aktif'));
        $cashCode = trim((string) ($cash['kode'] ?? ''));
        $timestampLabel = date('d M Y H:i', strtotime($disbursementTime));

        $entry = sprintf(
            'Pencairan kasbon melalui %s%s pada %s',
            $cashTitle,
            $cashCode !== '' ? ' (#' . $cashCode . ')' : '',
            $timestampLabel
        );

        if ($note !== '') {
            $entry .= ' - ' . $note;
        }

        if ($existing === '') {
            return $entry;
        }

        return $existing . "\n" . $entry;
    }
}

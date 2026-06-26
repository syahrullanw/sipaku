<?php

namespace Modules\Finance\Controllers\Bendahara;

use App\Models\ActivityFund;
use App\Models\AccountabilityReport;
use App\Models\User;
use App\Services\Finance\ActivityFundService;
use App\Services\Finance\ApprovalService;
use App\Services\Finance\CashflowService;
use App\Services\Finance\GeneralCashService;
use App\Support\FinanceCache;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class ActivityFundController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        $pendingActivities = $this->fetchByStatuses($schoolYearId, ['diajukan']);
        $awaitingApprovalActivities = $this->fetchByStatuses($schoolYearId, ['menunggu_acc']);
        $readyActivities = $this->fetchReadyActivities($schoolYearId);
        $historyActivities = $this->fetchHistoryActivities($schoolYearId);
        $generalCashBalance = $schoolYearId !== null ? GeneralCashService::balance($schoolYearId) : 0.0;

        return $this->render('finance/bendahara/activities/index', [
            'title' => 'Dana Kegiatan',
            'pageTitle' => 'Pengajuan Dana Kegiatan',
            'activeMenu' => 'finance-bendahara-activities',
            'hasActiveYear' => $schoolYearId !== null,
            'pendingActivities' => $pendingActivities,
            'awaitingApprovalActivities' => $awaitingApprovalActivities,
            'readyActivities' => $readyActivities,
            'historyActivities' => $historyActivities,
            'generalCashBalance' => $generalCashBalance,
        ], 'admin');
    }

    public function verify(Request $request, int $activityId): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $redirectUrl = 'keuangan/bendahara/dana-kegiatan';

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

        $activity = $this->findActivity($activityId);

        if ($activity === null) {
            Session::flash('error', 'Pengajuan dana kegiatan tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        if ((int) ($activity['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            Session::flash('error', 'Pengajuan tidak terdaftar pada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ((string) ($activity['status'] ?? '') !== 'diajukan') {
            Session::flash('info', 'Pengajuan sudah diproses sebelumnya.');

            return $this->redirect($redirectUrl);
        }

        $headmasterUserId = $this->resolveHeadmasterUserId();

        if ($headmasterUserId === null) {
            Session::flash('error', 'Belum ada kepala sekolah yang ditetapkan. Tidak dapat meneruskan pengajuan.');

            return $this->redirect($redirectUrl);
        }

        $note = trim((string) ($request->input('catatan') ?? ''));
        $now = date('Y-m-d H:i:s');

        $connection = Database::connection();

        try {
            $connection->beginTransaction();

            $mergedNote = $this->appendTimelineNote(
                $activity['catatan'] ?? null,
                $this->formatVerificationMessage($now, $note)
            );

            ActivityFund::updateById($activityId, [
                'status' => 'menunggu_acc',
                'diverifikasi_oleh' => $userId,
                'catatan' => $mergedNote,
                'updated_at' => $now,
                'updated_by' => $userId,
            ]);

            ApprovalService::request([
                'entity_type' => 'dana_kegiatan',
                'entity_id' => $activityId,
                'approver_id' => $headmasterUserId,
                'tanggal' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($connection->inTransaction()) {
                $connection->commit();
            }

            FinanceCache::forget('kepsek_dashboard_summary_' . date('Y_m'));
            FinanceCache::forget('bendahara_dashboard_stats_' . $schoolYearId);
            FinanceCache::forget('bendahara_dashboard_stats_0');

            Session::flash('success', 'Pengajuan berhasil diverifikasi dan diteruskan ke kepala sekolah.');
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            Session::flash('error', 'Gagal memverifikasi pengajuan: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    public function reject(Request $request, int $activityId): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $redirectUrl = 'keuangan/bendahara/dana-kegiatan';

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

        $activity = $this->findActivity($activityId);

        if ($activity === null) {
            Session::flash('error', 'Pengajuan dana kegiatan tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        if ((int) ($activity['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            Session::flash('error', 'Pengajuan tidak terdaftar pada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ((string) ($activity['status'] ?? '') !== 'diajukan') {
            Session::flash('info', 'Pengajuan tidak bisa ditolak karena sudah diproses.');

            return $this->redirect($redirectUrl);
        }

        $note = trim((string) ($request->input('catatan') ?? ''));
        $now = date('Y-m-d H:i:s');

        $mergedNote = $this->appendTimelineNote(
            $activity['catatan'] ?? null,
            $this->formatRejectionMessage($now, $note)
        );

        ActivityFund::updateById($activityId, [
            'status' => 'ditolak',
            'catatan' => $mergedNote,
            'updated_at' => $now,
            'updated_by' => $userId,
        ]);

        FinanceCache::forget('bendahara_dashboard_stats_' . $schoolYearId);
        FinanceCache::forget('bendahara_dashboard_stats_0');

        Session::flash('success', 'Pengajuan dana kegiatan ditolak.');

        return $this->redirect($redirectUrl);
    }

    public function disburse(Request $request, int $activityId): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $redirectUrl = 'keuangan/bendahara/dana-kegiatan';

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

        $activity = $this->findActivity($activityId);

        if ($activity === null) {
            Session::flash('error', 'Pengajuan dana kegiatan tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        if ((int) ($activity['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            Session::flash('error', 'Pengajuan tidak terdaftar pada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ((string) ($activity['status'] ?? '') !== 'disetujui') {
            Session::flash('error', 'Pengajuan belum disetujui kepala sekolah atau sudah dicairkan.');

            return $this->redirect($redirectUrl);
        }

        $rawAmount = (string) ($request->input('nominal') ?? '');
        $amount = $this->normalizeAmount($rawAmount);

        if ($amount <= 0) {
            Session::flash('error', 'Nominal pencairan harus lebih dari nol.');

            return $this->redirect($redirectUrl);
        }

        $expenseType = trim((string) ($request->input('jenis_pengeluaran') ?? 'Pencairan Dana Kegiatan'));
        if ($expenseType === '') {
            $expenseType = 'Pencairan Dana Kegiatan';
        }

        if (mb_strlen($expenseType) > 120) {
            $expenseType = mb_substr($expenseType, 0, 120);
        }

        $note = trim((string) ($request->input('catatan') ?? ''));
        $disbursementInput = (string) ($request->input('waktu_pencairan') ?? '');
        $disbursementTime = date('Y-m-d H:i:s');

        if ($disbursementInput !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d\TH:i', $disbursementInput);
            if ($parsed instanceof \DateTimeInterface) {
                $disbursementTime = $parsed->format('Y-m-d H:i:s');
            }
        }

        $connection = Database::connection();

        try {
            $connection->beginTransaction();

            $realizationId = ActivityFundService::recordRealization([
                'dana_kegiatan_id' => $activityId,
                'jenis_pengeluaran' => $expenseType,
                'nominal' => $amount,
                'catatan' => $note === '' ? null : $note,
                'tanggal' => $disbursementTime,
                'dicatat_oleh' => $userId,
            ]);

            GeneralCashService::withdrawForActivity($schoolYearId, $amount, [
                'description' => $expenseType,
                'recorded_at' => $disbursementTime,
                'user_id' => $userId,
                'activity_id' => $activityId,
            ]);

            CashflowService::record('keluar', 'kegiatan', $amount, [
                'reference_id' => $realizationId,
                'reference_code' => $activity['kode'] ?? null,
                'description' => $expenseType,
                'user_id' => $userId,
                'recorded_at' => $disbursementTime,
                'school_year_id' => $schoolYearId,
            ]);

            $mergedNote = $this->appendTimelineNote(
                $activity['catatan'] ?? null,
                $this->formatDisbursementMessage($disbursementTime, $amount, $note)
            );

            ActivityFund::updateById($activityId, [
                'status' => 'selesai',
                'catatan' => $mergedNote,
                'updated_at' => $disbursementTime,
                'updated_by' => $userId,
            ]);

            if ($connection->inTransaction()) {
                $connection->commit();
            }

            FinanceCache::forget('bendahara_dashboard_stats_' . $schoolYearId);
            FinanceCache::forget('bendahara_dashboard_stats_0');
            FinanceCache::forget('kepsek_dashboard_summary_' . date('Y_m'));

            $this->flashGeneralCashDeficitWarning($schoolYearId);
            Session::flash('success', 'Dana kegiatan berhasil dicairkan.');
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            Session::flash('error', 'Gagal mencairkan dana kegiatan: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    public function lpj(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();
        $statusFilter = trim((string) $request->query('status', ''));

        $allowedStatuses = ['diajukan', 'diverifikasi_bendahara', 'menunggu_acc', 'disetujui', 'selesai', 'ditolak'];
        $activities = [];

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $connection = Database::connection();

            $sql = <<<SQL
SELECT dk.*, g.nama AS guru_nama
FROM dana_kegiatan dk
LEFT JOIN guru g ON g.id = dk.guru_id
WHERE dk.tahun_ajaran_id = :year
  AND dk.status IN ('diverifikasi_bendahara','menunggu_acc','disetujui','selesai','ditolak')
SQL;

            if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
                $sql .= ' AND dk.status = :status';
            }

            $sql .= ' ORDER BY dk.updated_at DESC, dk.id DESC';

            $statement = $connection->prepare($sql);

            if ($statement !== false) {
                $statement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
                if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
                    $statement->bindValue(':status', $statusFilter);
                }
                $statement->execute();
                $activities = $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        }

        $activityIds = array_map(static fn (array $activity): int => (int) ($activity['id'] ?? 0), $activities);
        $reports = AccountabilityReport::mapByEntity('dana_kegiatan', $activityIds);

        return $this->render('finance/bendahara/activities/lpj', [
            'title' => 'Progress LPJ Dana Kegiatan',
            'pageTitle' => 'Progress LPJ Dana Kegiatan',
            'activeMenu' => 'finance-bendahara-activities',
            'hasActiveYear' => $schoolYearId !== null,
            'activities' => $activities,
            'reports' => $reports,
            'statusFilter' => $statusFilter,
            'allowedStatuses' => $allowedStatuses,
        ], 'admin');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchByStatuses(?int $schoolYearId, array $statuses): array
    {
        if ($schoolYearId === null || $schoolYearId <= 0 || empty($statuses)) {
            return [];
        }

        $statusPlaceholders = [];
        foreach ($statuses as $index => $status) {
            $statusPlaceholders[] = ':status_' . $index;
        }

        $placeholderSql = implode(',', $statusPlaceholders);
        $connection = Database::connection();
        $sql = <<<SQL
SELECT dk.*, g.nama AS guru_nama
FROM dana_kegiatan dk
LEFT JOIN guru g ON g.id = dk.guru_id
WHERE dk.tahun_ajaran_id = :year
  AND dk.status IN ({$placeholderSql})
ORDER BY dk.created_at ASC
SQL;

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);

        foreach ($statuses as $index => $status) {
            $statement->bindValue(':status_' . $index, $status);
        }

        $statement->execute();
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchReadyActivities(?int $schoolYearId): array
    {
        if ($schoolYearId === null || $schoolYearId <= 0) {
            return [];
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            "SELECT dk.*, g.nama AS guru_nama
             FROM dana_kegiatan dk
             LEFT JOIN guru g ON g.id = dk.guru_id
             WHERE dk.tahun_ajaran_id = :year
               AND dk.status = 'disetujui'
             ORDER BY dk.tanggal_acc ASC, dk.created_at ASC"
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchHistoryActivities(?int $schoolYearId): array
    {
        if ($schoolYearId === null || $schoolYearId <= 0) {
            return [];
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            "SELECT dk.*, g.nama AS guru_nama
             FROM dana_kegiatan dk
             LEFT JOIN guru g ON g.id = dk.guru_id
             WHERE dk.tahun_ajaran_id = :year
             ORDER BY dk.updated_at DESC, dk.id DESC
             LIMIT 25"
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findActivity(int $activityId): ?array
    {
        if ($activityId <= 0) {
            return null;
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            'SELECT dk.*, g.nama AS guru_nama
             FROM dana_kegiatan dk
             LEFT JOIN guru g ON g.id = dk.guru_id
             WHERE dk.id = :id
             LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $activityId, \PDO::PARAM_INT);
        $statement->execute();

        $record = $statement->fetch(\PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function resolveHeadmasterUserId(): ?int
    {
        $activeYear = \App\Support\SchoolYearContext::resolve();

        if ($activeYear === null) {
            return null;
        }

        $headmasterTeacherId = (int) ($activeYear['kepala_sekolah_id'] ?? 0);

        if ($headmasterTeacherId <= 0) {
            return null;
        }

        $headmasterUser = User::findByTeacherId($headmasterTeacherId);

        return $headmasterUser !== null ? (int) ($headmasterUser['id'] ?? 0) : null;
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

    private function appendTimelineNote(?string $existing, string $message): string
    {
        $trimmedExisting = trim((string) $existing);

        if ($trimmedExisting === '') {
            return $message;
        }

        return $trimmedExisting . "\n\n" . $message;
    }

    private function formatVerificationMessage(string $timestamp, string $note): string
    {
        $base = 'Verifikasi bendahara pada ' . date('d M Y H:i', strtotime($timestamp));

        if ($note !== '') {
            $base .= "\n" . $note;
        }

        return $base;
    }

    private function formatRejectionMessage(string $timestamp, string $note): string
    {
        $base = 'Pengajuan ditolak bendahara pada ' . date('d M Y H:i', strtotime($timestamp));

        if ($note !== '') {
            $base .= "\n" . $note;
        }

        return $base;
    }

    private function formatDisbursementMessage(string $timestamp, float $amount, string $note): string
    {
        $formattedAmount = 'Rp ' . number_format($amount, 0, ',', '.');
        $base = 'Pencairan dana kegiatan (' . $formattedAmount . ') pada ' . date('d M Y H:i', strtotime($timestamp)) . ' melalui kas utama.';

        if ($note !== '') {
            $base .= "\n" . $note;
        }

        return $base;
    }
}

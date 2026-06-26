<?php

namespace Modules\Finance\Controllers\Bendahara;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentSavingTransaction;
use App\Services\Finance\CashflowService;
use App\Services\Finance\SavingsPoolService;
use App\Services\Finance\SavingsService;
use App\Support\FinanceCache;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class SavingsController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();
        $selectedClassId = (int) ($request->input('class_id') ?? 0);
        $searchQuery = trim((string) ($request->input('q') ?? ''));
        $selectedStudentId = (int) ($request->input('student_id') ?? 0);

        $classOptions = $this->buildClassOptions($schoolYearId);
        $students = [];
        $totalBalance = 0.0;
        $activeAccounts = 0;

        if ($schoolYearId !== null && ($selectedClassId > 0 || $searchQuery !== '')) {
            $students = $this->fetchStudentsWithSavings($schoolYearId, $selectedClassId, $searchQuery);

            foreach ($students as $student) {
                if (!empty($student['saving']) && is_array($student['saving'])) {
                    $activeAccounts++;
                    $totalBalance += (float) ($student['saving']['saldo_terakhir'] ?? 0.0);
                }
            }
        }

        $studentOptions = [];
        foreach ($students as $student) {
            $labelParts = [$student['name']];

            $nipd = trim((string) ($student['student']['nipd'] ?? ''));
            $nisn = trim((string) ($student['student']['nisn'] ?? ''));

            if ($nipd !== '') {
                $labelParts[] = $nipd;
            } elseif ($nisn !== '') {
                $labelParts[] = $nisn;
            }

            $labelParts[] = $student['class_label'];

            $studentRecord = is_array($student['student'] ?? null) ? $student['student'] : [];
            $studentOptions[(int) $student['id']] = [
                'label' => implode(' · ', array_filter($labelParts, static fn ($part) => $part !== '')),
                'status' => (string) ($studentRecord['status'] ?? ''),
                'disabled' => Student::isInactiveRecord($studentRecord),
            ];
        }

        $overallBalance = 0.0;
        $overallAccounts = 0;
        $borrowedFromSavings = 0.0;
        if ($schoolYearId !== null) {
            [$overallBalance, $overallAccounts] = $this->summarizeSavings($schoolYearId);
            $borrowedFromSavings = SavingsPoolService::outstanding($schoolYearId);
            if ($borrowedFromSavings < 0) {
                $borrowedFromSavings = 0.0;
            }
        }
        $validBalance = max(0.0, $overallBalance - $borrowedFromSavings);

        $now = date('Y-m-d\TH:i');

        return $this->render('finance/bendahara/savings/index', [
            'title' => 'Tabungan Siswa',
            'pageTitle' => 'Tabungan Siswa',
            'activeMenu' => 'finance-bendahara-savings',
            'filters' => [
                'class_id' => $selectedClassId,
                'query' => $searchQuery,
                'student_id' => $selectedStudentId,
            ],
            'classOptions' => $classOptions,
            'students' => $students,
            'studentOptions' => $studentOptions,
            'totalBalance' => $totalBalance,
            'activeAccounts' => $activeAccounts,
            'hasActiveYear' => $schoolYearId !== null,
            'overallBalance' => $overallBalance,
            'overallAccounts' => $overallAccounts,
            'borrowedFromSavings' => $borrowedFromSavings,
            'validSavingsBalance' => $validBalance,
            'defaultTransactionTime' => $now,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $selectedClassId = (int) ($request->input('class_id') ?? 0);
        $searchQuery = trim((string) ($request->input('q') ?? ''));

        $redirectUrl = 'keuangan/bendahara/tabungan';
        $redirectQuery = [];

        if ($selectedClassId > 0) {
            $redirectQuery['class_id'] = $selectedClassId;
        }

        if ($searchQuery !== '') {
            $redirectQuery['q'] = $searchQuery;
        }

        if (!empty($redirectQuery)) {
            $redirectUrl .= '?' . http_build_query($redirectQuery);
        }

        if ($response = $this->guardCsrfOrRedirect($request, $redirectUrl)) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        if ($schoolYearId === null) {
            Session::flash('error', 'Tidak dapat mencatat tabungan karena tahun ajaran aktif belum ditetapkan.');

            return $this->redirect($redirectUrl);
        }

        $studentId = (int) ($request->input('student_id') ?? 0);
        $type = (string) ($request->input('type') ?? '');
        $rawAmount = (string) ($request->input('amount') ?? '');
        $note = trim((string) ($request->input('note') ?? ''));
        $transactionTimeInput = (string) ($request->input('transaction_time') ?? '');

        if ($studentId <= 0) {
            Session::flash('error', 'Pilih siswa yang valid sebelum mencatat transaksi tabungan.');

            return $this->redirect($redirectUrl);
        }

        if (!in_array($type, ['setor', 'tarik'], true)) {
            Session::flash('error', 'Jenis transaksi tabungan tidak dikenal.');

            return $this->redirect($redirectUrl);
        }

        $amount = $this->normalizeAmount($rawAmount);

        if ($amount <= 0) {
            Session::flash('error', 'Nominal tabungan harus lebih dari nol.');

            return $this->redirect($redirectUrl);
        }

        $student = Student::find($studentId);

        if ($student === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        if ((int) ($student['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            Session::flash('error', 'Siswa tidak terdaftar pada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if (!Student::hasActiveStatus($student)) {
            Session::flash('error', 'Transaksi tabungan tidak dapat diproses karena status siswa nonaktif.');

            return $this->redirect($redirectUrl);
        }

        if ($selectedClassId > 0 && (int) ($student['kelas_id'] ?? 0) !== $selectedClassId) {
            Session::flash('error', 'Siswa tidak termasuk dalam kelas yang dipilih.');

            return $this->redirect($redirectUrl);
        }

        $transactionTime = date('Y-m-d H:i:s');

        if ($transactionTimeInput !== '') {
            $date = \DateTime::createFromFormat('Y-m-d\TH:i', $transactionTimeInput);

            if ($date instanceof \DateTime) {
                $transactionTime = $date->format('Y-m-d H:i:s');
            }
        }

        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;

        try {
            $accountId = SavingsService::ensureAccount($studentId, $schoolYearId, [
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $transactionId = SavingsService::recordTransaction($accountId, $type, $amount, [
                'dicatat_oleh' => $userId,
                'tanggal' => $transactionTime,
                'catatan' => $note === '' ? null : $note,
            ]);

            $transaction = StudentSavingTransaction::find($transactionId);
            $description = sprintf(
                '%s tabungan %s',
                ucfirst($type),
                $student['nama'] ?? 'Siswa'
            );

            $cashflowType = $type === 'setor' ? 'masuk' : 'keluar';

            CashflowService::record($cashflowType, 'tabungan', $amount, [
                'reference_id' => $transactionId,
                'reference_code' => $transaction['kode_transaksi'] ?? null,
                'description' => $transaction !== null && !empty($transaction['kode_transaksi'])
                    ? $description . ' (#' . $transaction['kode_transaksi'] . ')'
                    : $description,
                'user_id' => $userId,
                'recorded_at' => $transactionTime,
                'school_year_id' => $schoolYearId,
            ]);

            FinanceCache::forget('bendahara_dashboard_stats_' . $schoolYearId);
            FinanceCache::forget('bendahara_dashboard_stats_0');

            Session::flash('success', 'Transaksi tabungan siswa berhasil dicatat.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal mencatat tabungan siswa: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildClassOptions(?int $schoolYearId): array
    {
        $classes = $schoolYearId !== null
            ? Classroom::byYear($schoolYearId)
            : Classroom::allWithRelations(null);

        $options = [];

        foreach ($classes as $class) {
            $id = (int) ($class['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $grade = trim((string) ($class['tingkat'] ?? ''));
            $name = trim((string) ($class['nama'] ?? ''));
            $label = trim($grade . ' ' . $name);

            if ($label === '') {
                $label = 'Kelas #' . $id;
            }

            $options[] = [
                'id' => $id,
                'label' => $label,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchStudentsWithSavings(int $schoolYearId, int $classId, string $keyword): array
    {
        $connection = Database::connection();

        $conditions = ['s.tahun_ajaran_id = :year_id'];
        $params = [
            ':year_id' => $schoolYearId,
        ];

        if ($classId > 0) {
            $conditions[] = 's.kelas_id = :class_id';
            $params[':class_id'] = $classId;
        }

        if ($keyword !== '') {
            $conditions[] = '(s.nama LIKE :keyword OR s.nipd LIKE :keyword OR s.nisn LIKE :keyword)';
            $params[':keyword'] = '%' . $keyword . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $sql = <<<SQL
SELECT
    s.id,
    s.nama,
    s.nipd,
    s.nisn,
    s.status,
    s.kelas_id,
    k.nama AS kelas_nama,
    k.tingkat AS kelas_tingkat
FROM siswa s
LEFT JOIN kelas k ON k.id = s.kelas_id
{$whereClause}
ORDER BY s.nama ASC
LIMIT 200
SQL;

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $key => $value) {
            $paramType = $key === ':keyword' ? \PDO::PARAM_STR : \PDO::PARAM_INT;
            $statement->bindValue($key, $value, $paramType);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $studentIds = array_unique(array_map(static fn ($row) => (int) ($row['id'] ?? 0), $rows));

        $savingsByStudent = $this->fetchSavingsForStudents($schoolYearId, $studentIds);
        $historiesBySaving = $this->fetchHistoriesForSavings(array_column($savingsByStudent, 'id'), 5);

        $results = [];

        foreach ($rows as $row) {
            $studentId = (int) ($row['id'] ?? 0);

            if ($studentId <= 0) {
                continue;
            }

            $saving = $savingsByStudent[$studentId] ?? null;

            if ($saving !== null) {
                $savingId = (int) ($saving['id'] ?? 0);

                if ($savingId > 0) {
                    $history = $historiesBySaving[$savingId] ?? [];
                    $saving['history'] = $history;
                    $saving['last_transaction'] = $history[0] ?? null;
                } else {
                    $saving['history'] = [];
                    $saving['last_transaction'] = null;
                }
            }

            $results[] = [
                'id' => $studentId,
                'name' => $row['nama'] ?? '-',
                'class_label' => $this->formatClassLabel($row),
                'student' => $row,
                'saving' => $saving,
            ];
        }

        return $results;
    }

    /**
     * @param array<int, int> $studentIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchSavingsForStudents(int $schoolYearId, array $studentIds): array
    {
        $filteredIds = array_values(array_filter(array_map(static fn ($id) => (int) $id, $studentIds), static fn (int $id) => $id > 0));

        if (empty($filteredIds)) {
            return [];
        }

        $placeholders = [];

        foreach ($filteredIds as $index => $_) {
            $placeholders[] = ':student_' . $index;
        }

        $sql = 'SELECT * FROM tabungan_siswa WHERE tahun_ajaran_id = :year_id AND siswa_id IN (' . implode(', ', $placeholders) . ')';
        $statement = Database::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year_id', $schoolYearId, \PDO::PARAM_INT);

        foreach ($filteredIds as $index => $studentId) {
            $statement->bindValue(':student_' . $index, $studentId, \PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $result = [];

        foreach ($rows as $row) {
            $studentId = (int) ($row['siswa_id'] ?? 0);

            if ($studentId <= 0) {
                continue;
            }

            $result[$studentId] = $row;
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $savingIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function fetchHistoriesForSavings(array $savingIds, int $limitPerSaving = 5): array
    {
        $ids = array_values(array_filter(array_map(static fn ($id) => (int) $id, $savingIds), static fn (int $id) => $id > 0));

        if (empty($ids)) {
            return [];
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            'SELECT * FROM tabungan_transaksi WHERE tabungan_id = :tabungan_id ORDER BY tanggal DESC, id DESC LIMIT :limit'
        );

        if ($statement === false) {
            return [];
        }

        $result = [];

        foreach ($ids as $savingId) {
            $statement->bindValue(':tabungan_id', $savingId, \PDO::PARAM_INT);
            $statement->bindValue(':limit', $limitPerSaving, \PDO::PARAM_INT);

            if ($statement->execute()) {
                $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

                if ($rows !== false) {
                    $result[$savingId] = $rows;
                } else {
                    $result[$savingId] = [];
                }
            } else {
                $result[$savingId] = [];
            }

            $statement->closeCursor();
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function formatClassLabel(array $row): string
    {
        $grade = trim((string) ($row['kelas_tingkat'] ?? ''));
        $name = trim((string) ($row['kelas_nama'] ?? ''));

        if ($grade !== '' && $name !== '') {
            return $grade . ' ' . $name;
        }

        if ($name !== '') {
            return $name;
        }

        if ($grade !== '') {
            return $grade;
        }

        return 'Kelas tidak diketahui';
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

    /**
     * @return array{0: float, 1: int}
     */
    private function summarizeSavings(int $schoolYearId): array
    {
        $connection = Database::connection();
        $statement = $connection->prepare(
            'SELECT
                COALESCE(SUM(saldo_terakhir), 0) AS total_balance,
                COUNT(*) AS account_count
             FROM tabungan_siswa
             WHERE tahun_ajaran_id = :year
               AND status = \'aktif\''
        );

        if ($statement === false) {
            return [0.0, 0];
        }

        $statement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
        if (!$statement->execute()) {
            return [0.0, 0];
        }

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return [0.0, 0];
        }

        return [
            (float) ($row['total_balance'] ?? 0.0),
            (int) ($row['account_count'] ?? 0),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Finance\Controllers\Bendahara;

use App\Models\Classroom;
use Core\Database;
use Core\Request;
use Core\Response;
use Modules\Finance\Controllers\Controller;

class StudentLedgerController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();
        $selectedClassId = (int) ($request->input('class_id') ?? 0);
        $searchQuery = trim((string) ($request->input('q') ?? ''));

        $classOptions = $this->buildClassOptions($schoolYearId);
        $filters = [
            'class_id' => $selectedClassId,
            'query' => $searchQuery,
        ];

        if ($schoolYearId === null) {
            return $this->render('finance/bendahara/students/index', [
                'title' => 'Rekap Keuangan Siswa',
                'pageTitle' => 'Rekap Keuangan Siswa',
                'activeMenu' => 'finance-bendahara-student-ledger',
                'hasActiveYear' => false,
                'classOptions' => $classOptions,
                'filters' => $filters,
                'students' => [],
                'summary' => [
                    'total_students' => 0,
                    'total_billed' => 0.0,
                    'total_paid' => 0.0,
                    'total_outstanding' => 0.0,
                    'total_savings' => 0.0,
                ],
            ], 'admin');
        }

        $students = $this->fetchStudents($schoolYearId, $selectedClassId, $searchQuery);
        $studentIds = array_keys($students);

        $savings = $this->fetchSavings($schoolYearId, $studentIds);
        $billings = $this->fetchBillingItems($schoolYearId, $studentIds);
        $payments = $this->fetchPayments($schoolYearId, $studentIds);

        $summary = [
            'total_students' => count($students),
            'total_billed' => 0.0,
            'total_paid' => 0.0,
            'total_outstanding' => 0.0,
            'total_savings' => 0.0,
        ];

        foreach ($students as $studentId => &$student) {
            $studentSavings = $savings[$studentId] ?? null;
            $studentBillings = $billings[$studentId] ?? [];
            $studentPayments = $payments[$studentId] ?? [];

            $totalBilled = 0.0;
            $totalPaid = 0.0;
            $totalOutstanding = 0.0;
            $lastPaymentAt = null;

            foreach ($studentBillings as $billing) {
                $nominal = (float) ($billing['nominal'] ?? 0.0);
                $remaining = (float) ($billing['sisa_nominal'] ?? 0.0);
                $paid = max(0.0, $nominal - $remaining);

                $totalBilled += $nominal;
                $totalPaid += $paid;
                $totalOutstanding += max(0.0, $remaining);
            }

            foreach ($studentPayments as $payment) {
                $timestamp = (string) ($payment['tanggal_bayar'] ?? '');
                if ($timestamp !== '') {
                    $current = strtotime($timestamp);
                    if ($current !== false) {
                        if ($lastPaymentAt === null || $current > $lastPaymentAt) {
                            $lastPaymentAt = $current;
                        }
                    }
                }
            }

            if ($studentSavings !== null) {
                $summary['total_savings'] += (float) ($studentSavings['saldo_terakhir'] ?? 0.0);
            }

            $summary['total_billed'] += $totalBilled;
            $summary['total_paid'] += $totalPaid;
            $summary['total_outstanding'] += $totalOutstanding;

            $student['savings'] = $studentSavings;
            $student['billings'] = $studentBillings;
            $student['payments'] = $studentPayments;
            $student['summary'] = [
                'total_billed' => $totalBilled,
                'total_paid' => $totalPaid,
                'total_outstanding' => $totalOutstanding,
                'last_payment_at' => $lastPaymentAt !== null ? date('Y-m-d H:i:s', $lastPaymentAt) : null,
                'active_billing' => count(array_filter($studentBillings, static function (array $item): bool {
                    return ((float) ($item['sisa_nominal'] ?? 0.0)) > 0.0;
                })),
                'completed_billing' => count(array_filter($studentBillings, static function (array $item): bool {
                    return ((float) ($item['sisa_nominal'] ?? 0.0)) <= 0.0;
                })),
            ];
        }
        unset($student);

        return $this->render('finance/bendahara/students/index', [
            'title' => 'Rekap Keuangan Siswa',
            'pageTitle' => 'Rekap Tagihan & Pembayaran Siswa',
            'activeMenu' => 'finance-bendahara-student-ledger',
            'hasActiveYear' => true,
            'classOptions' => $classOptions,
            'filters' => $filters,
            'students' => $students,
            'summary' => $summary,
        ], 'admin');
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
    private function fetchStudents(int $schoolYearId, int $classId, string $keyword): array
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
    s.hp,
    s.telepon,
    s.kelas_id,
    k.nama AS kelas_nama,
    k.tingkat AS kelas_tingkat
FROM siswa s
LEFT JOIN kelas k ON k.id = s.kelas_id
{$whereClause}
ORDER BY k.tingkat ASC, k.nama ASC, s.nama ASC
SQL;

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $key => $value) {
            if ($key === ':keyword') {
                $statement->bindValue($key, $value, \PDO::PARAM_STR);
            } else {
                $statement->bindValue($key, $value, \PDO::PARAM_INT);
            }
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $results = [];

        foreach ($rows as $row) {
            $studentId = (int) ($row['id'] ?? 0);

            if ($studentId <= 0) {
                continue;
            }

            $results[$studentId] = [
                'id' => $studentId,
                'name' => (string) ($row['nama'] ?? '-'),
                'class_label' => $this->formatClassLabel($row),
                'student' => $row,
                'contact' => [
                    'hp' => trim((string) ($row['hp'] ?? '')),
                    'telepon' => trim((string) ($row['telepon'] ?? '')),
                ],
            ];
        }

        return $results;
    }

    /**
     * @param array<int, int> $studentIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchSavings(int $schoolYearId, array $studentIds): array
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
     * @param array<int, int> $studentIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function fetchBillingItems(int $schoolYearId, array $studentIds): array
    {
        $filteredIds = array_values(array_filter(array_map(static fn ($id) => (int) $id, $studentIds), static fn (int $id) => $id > 0));

        if (empty($filteredIds)) {
            return [];
        }

        $placeholders = [];

        foreach ($filteredIds as $index => $_) {
            $placeholders[] = ':student_' . $index;
        }

        $sql = <<<SQL
SELECT
    ti.*,
    t.kode AS tagihan_kode,
    t.judul AS tagihan_judul,
    t.tanggal_jatuh_tempo,
    t.status AS tagihan_status,
    t.rutin_tipe,
    kt.nama AS kategori_nama
FROM tagihan_item ti
JOIN tagihan t ON t.id = ti.tagihan_id
LEFT JOIN kategori_tagihan kt ON kt.id = t.kategori_id
WHERE t.tahun_ajaran_id = :year_id
  AND ti.siswa_id IN ({{student_placeholders}})
ORDER BY t.created_at DESC, ti.id DESC
SQL;

        $sql = str_replace('{{student_placeholders}}', implode(', ', $placeholders), $sql);

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

            if (!isset($result[$studentId])) {
                $result[$studentId] = [];
            }

            $result[$studentId][] = $row;
        }

        return $result;
    }

    /**
     * @param array<int, int> $studentIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function fetchPayments(int $schoolYearId, array $studentIds): array
    {
        $filteredIds = array_values(array_filter(array_map(static fn ($id) => (int) $id, $studentIds), static fn (int $id) => $id > 0));

        if (empty($filteredIds)) {
            return [];
        }

        $placeholders = [];

        foreach ($filteredIds as $index => $_) {
            $placeholders[] = ':student_' . $index;
        }

        $sql = <<<SQL
SELECT
    p.*,
    ti.siswa_id,
    ti.tagihan_id,
    t.kode AS tagihan_kode,
    t.judul AS tagihan_judul
FROM pembayaran p
JOIN tagihan_item ti ON ti.id = p.tagihan_item_id
JOIN tagihan t ON t.id = ti.tagihan_id
WHERE t.tahun_ajaran_id = :year_id
  AND ti.siswa_id IN ({{student_placeholders}})
ORDER BY p.tanggal_bayar DESC, p.id DESC
SQL;

        $sql = str_replace('{{student_placeholders}}', implode(', ', $placeholders), $sql);

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

            if (!isset($result[$studentId])) {
                $result[$studentId] = [];
            }

            $result[$studentId][] = $row;
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
}

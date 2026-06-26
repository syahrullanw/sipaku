<?php

namespace Modules\Finance\Controllers\Bendahara;

use App\Models\Billing;
use App\Models\BillingCategory;
use App\Models\BillingItem;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Services\Finance\BillingService;
use App\Services\Finance\CashflowService;
use App\Services\Finance\PaymentDetailService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\RecurringBillingService;
use App\Services\WhatsappGatewayService;
use App\Support\FinanceCache;
use App\Support\FinancePaymentSlipToken;
use App\Models\WhatsappGatewaySetting;
use DateTimeImmutable;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();
        $classInput = $request->query('kelas_id', []);
        if ($classInput === null) {
            $selectedClassIds = [];
        } elseif (is_array($classInput)) {
            $selectedClassIds = array_values(array_unique(array_filter(array_map(static fn ($id) => (int) $id, $classInput), static fn (int $id) => $id > 0)));
        } else {
            $selectedClassIds = [(int) $classInput];
        }

        if (count($selectedClassIds) === 1) {
            $selectedClassIds = [reset($selectedClassIds)];
        }

        $billings = [];
        $students = [];
        $categories = BillingCategory::active();
        $classes = $schoolYearId !== null ? Classroom::options($schoolYearId) : Classroom::options();
        $billingSchoolYearIds = $this->relatedBillingSchoolYearIds($schoolYearId);

        if ($schoolYearId !== null) {
            $billings = $this->loadBillingsWithSummary($billingSchoolYearIds);
            $students = !empty($selectedClassIds)
                ? Student::options($selectedClassIds, $schoolYearId)
                : Student::options(null, $schoolYearId);
        }

        return $this->render('finance/bendahara/billings/index', [
            'title' => 'Tagihan Siswa',
            'pageTitle' => 'Kelola Tagihan',
            'activeMenu' => 'finance-bendahara-billings',
            'billings' => $billings,
            'categories' => $categories,
            'classes' => $classes,
            'students' => $students,
            'selectedClassIds' => $selectedClassIds,
            'activeSchoolYearId' => $schoolYearId,
            'defaultWhatsappTemplate' => $this->defaultWhatsappTemplate(),
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/bendahara/tagihan')) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        if ($schoolYearId === null) {
            Session::flash('error', 'Tahun ajaran aktif tidak ditemukan.');

            return $this->redirect('keuangan/bendahara/tagihan');
        }

        $categoryId = (int) $request->input('kategori_id', 0);
        $title = trim((string) $request->input('judul', ''));
        $description = trim((string) $request->input('deskripsi', ''));
        $nominal = (float) $request->input('nominal', 0);
        $whatsappTemplate = trim((string) $request->input('whatsapp_message_template', ''));
        $billingType = (string) $request->input('jenis_penagihan', 'tidak');
        $allowedBillingTypes = ['tidak', 'mingguan', 'bulanan'];
        if (!in_array($billingType, $allowedBillingTypes, true)) {
            $billingType = 'tidak';
        }
        $category = $categoryId > 0 ? BillingCategory::find($categoryId) : null;
        $categoryType = (string) ($category['tipe'] ?? '');
        if ($billingType !== 'tidak' && $categoryType !== 'rutin') {
            Session::flash('error', 'Kategori insidental hanya diperbolehkan jenis Sekali (insidental).');

            return $this->redirect('keuangan/bendahara/tagihan');
        }
        $dueDate = $billingType === 'tidak' ? ($request->input('tanggal_jatuh_tempo') ?: null) : null;
        $weeklyDay = null;
        $monthlyDate = null;
        $startDateInput = trim((string) $request->input('rutin_mulai', ''));
        $startDate = null;

        if ($billingType === 'mingguan') {
            $weeklyDay = (int) $request->input('rutin_hari_mingguan', 0);
            if ($weeklyDay < 1 || $weeklyDay > 7) {
                Session::flash('error', 'Pilih hari untuk penagihan mingguan.');

                return $this->redirect('keuangan/bendahara/tagihan');
            }
        } elseif ($billingType === 'bulanan') {
            $monthlyDate = (int) $request->input('rutin_tanggal_bulanan', 0);
            if ($monthlyDate < 1 || $monthlyDate > 31) {
                Session::flash('error', 'Pilih tanggal 1-31 untuk penagihan bulanan.');

                return $this->redirect('keuangan/bendahara/tagihan');
            }
        }

        if ($billingType !== 'tidak') {
            if ($startDateInput === '') {
                Session::flash('error', 'Tanggal mulai tagihan rutin wajib diisi.');

                return $this->redirect('keuangan/bendahara/tagihan');
            }

            $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $startDateInput);
            if ($parsedDate === false) {
                Session::flash('error', 'Tanggal mulai tidak valid.');

                return $this->redirect('keuangan/bendahara/tagihan');
            }

            $startDate = $parsedDate;
        }

        $studentIds = $request->input('students', []);

        if ($categoryId <= 0 || $title === '' || $nominal <= 0) {
            Session::flash('error', 'Mohon lengkapi kategori, judul, dan nominal tagihan.');

            return $this->redirect('keuangan/bendahara/tagihan');
        }

        if (!is_array($studentIds) || empty($studentIds)) {
            Session::flash('error', 'Pilih minimal satu siswa untuk ditagih.');

            return $this->redirect('keuangan/bendahara/tagihan');
        }

        $studentIds = array_unique(array_map(static fn ($id) => (int) $id, $studentIds));
        $studentIds = array_values(array_filter($studentIds, static fn (int $id) => $id > 0));

        if (empty($studentIds)) {
            Session::flash('error', 'Pilihan siswa tidak valid.');

            return $this->redirect('keuangan/bendahara/tagihan');
        }

        $now = date('Y-m-d H:i:s');
        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
        if ($whatsappTemplate === '') {
            $whatsappTemplate = $this->defaultWhatsappTemplate();
        }

        $items = [];
        $studentsWithoutPhone = [];
        foreach ($studentIds as $studentId) {
            $student = Student::find($studentId);
            if ($student === null) {
                continue;
            }

            $studentPhone = $this->resolveStudentPhoneFromRecord($student);
            if ($studentPhone === '') {
                $studentsWithoutPhone[] = trim((string) ($student['nama'] ?? 'Siswa #' . $studentId));
            }

            $items[] = [
                'siswa_id' => $studentId,
                'kelas_id' => $student['kelas_id'] ?? null,
                'nominal' => $nominal,
                'nominal_periode' => $nominal,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $userId,
                'updated_by' => $userId,
            ];
        }

        if (empty($items)) {
            Session::flash('error', 'Tidak ada siswa valid untuk dibuatkan tagihan.');

            return $this->redirect('keuangan/bendahara/tagihan');
        }

        try {
            $nextSchedule = RecurringBillingService::initialNextSchedule($billingType, $weeklyDay, $monthlyDate, $startDate ?? null);

            $billingId = BillingService::createBilling([
                'tahun_ajaran_id' => $schoolYearId,
                'kategori_id' => $categoryId,
                'judul' => $title,
                'deskripsi' => $description !== '' ? $description : null,
                'nominal_total' => round($nominal * count($items), 2),
                'metode_penagihan' => 'per_siswa',
                'tanggal_jatuh_tempo' => $dueDate ?: null,
                'rutin_tipe' => $billingType,
                'rutin_jadwal_berikutnya' => $nextSchedule,
                'rutin_hari_mingguan' => $weeklyDay,
                'rutin_tanggal_bulanan' => $monthlyDate,
                'whatsapp_message_template' => $whatsappTemplate,
                'status' => 'aktif',
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $userId,
                'updated_by' => $userId,
            ], $items);

            FinanceCache::forget('bendahara_dashboard_stats_' . $schoolYearId);
            FinanceCache::forget('bendahara_dashboard_stats_0');

            Session::flash('success', 'Tagihan baru berhasil dibuat (ID #' . $billingId . ').');
            if (!empty($studentsWithoutPhone)) {
                $uniqueNames = array_values(array_unique(array_filter($studentsWithoutPhone, static fn (string $name): bool => $name !== '')));
                if (!empty($uniqueNames)) {
                    $preview = array_slice($uniqueNames, 0, 5);
                    $moreCount = count($uniqueNames) - count($preview);
                    $message = 'Nomor WhatsApp belum tersedia untuk ' . implode(', ', $preview);
                    if ($moreCount > 0) {
                        $message .= sprintf(' dan %d siswa lainnya', $moreCount);
                    }
                    $message .= '. Pesan bukti bayar tidak akan terkirim sampai nomor diisi.';
                    Session::flash('warning', $message);
                }
            }
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal membuat tagihan: ' . $exception->getMessage());
        }

        return $this->redirect('keuangan/bendahara/tagihan');
    }

    public function pay(Request $request, int $id): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $billingId = max(0, (int) $id);

        if ($billingId <= 0) {
            Session::flash('error', 'Tagihan tidak ditemukan.');

            return $this->redirect('keuangan/bendahara/tagihan');
        }

        $billing = $this->loadBillingDetail($billingId);

        if ($billing === null) {
            Session::flash('error', 'Tagihan tidak ditemukan atau tidak aktif.');

            return $this->redirect('keuangan/bendahara/tagihan');
        }

        $activeSchoolYearId = $this->activeSchoolYearId();
        if (!$this->isBillingInActiveCycle($billing, $activeSchoolYearId)) {
            Session::flash('error', 'Tagihan tidak termasuk tahun ajaran aktif.');

            return $this->redirect('keuangan/bendahara/tagihan');
        }

        $items = $this->loadBillingItemsWithStudent($billingId);

        $enrichedItems = [];
        $totalRemaining = 0.0;
        $selectableCount = 0;

        foreach ($items as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $remaining = round((float) ($item['sisa_nominal'] ?? 0.0), 2);
            $periodAmount = round((float) ($item['nominal_periode'] ?? 0.0), 2);
            if ($periodAmount <= 0.0) {
                $periodAmount = round((float) ($item['nominal'] ?? 0.0), 2);
            }

            $weeksDue = 0;
            if ($remaining > 0.0) {
                if ($periodAmount > 0.0) {
                    $weeksDue = (int) floor(($remaining + 0.0001) / $periodAmount);
                    if ($weeksDue <= 0) {
                        $weeksDue = 1;
                    }
                } else {
                    $weeksDue = 1;
                }
            }

            $studentActive = Student::hasActiveStatus(['siswa_status' => $item['siswa_status'] ?? null]);
            if ($remaining > 0.0) {
                $totalRemaining += $remaining;
            }

            $canSelect = $remaining > 0.0 && $studentActive;
            if ($canSelect) {
                $selectableCount++;
            }

            $enrichedItems[] = [
                'id' => $itemId,
                'student_id' => (int) ($item['siswa_id'] ?? 0),
                'student_name' => (string) ($item['siswa_nama'] ?? '-'),
                'student_nis' => (string) ($item['siswa_nipd'] ?? ''),
                'student_status' => (string) ($item['siswa_status'] ?? ''),
                'class_name' => (string) ($item['kelas_nama'] ?? '-'),
                'status' => (string) ($item['status'] ?? ''),
                'remaining' => $remaining,
                'period_amount' => $periodAmount,
                'weeks_due' => $weeksDue,
                'can_select' => $canSelect,
                'last_payment_at' => $item['last_payment_at'] ?? null,
                'saving_balance' => (float) ($item['tabungan_saldo'] ?? 0.0),
            ];
        }

        return $this->render('finance/bendahara/billings/pay', [
            'title' => 'Pembayaran Tagihan',
            'pageTitle' => 'Pembayaran Tagihan',
            'activeMenu' => 'finance-bendahara-billings',
            'billing' => $billing,
            'billingItems' => $enrichedItems,
            'totalRemaining' => $totalRemaining,
            'selectableCount' => $selectableCount,
        ], 'admin');
    }

    public function payStore(Request $request, int $id): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $billingId = max(0, (int) $id);

        if ($billingId <= 0) {
            Session::flash('error', 'Tagihan tidak ditemukan.');

            return $this->redirect('keuangan/bendahara/tagihan');
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/bendahara/tagihan/' . $billingId . '/pembayaran')) {
            return $response;
        }

        $billing = $this->loadBillingDetail($billingId);

        if ($billing === null) {
            Session::flash('error', 'Tagihan tidak ditemukan atau tidak aktif.');

            return $this->redirect('keuangan/bendahara/tagihan');
        }

        $activeSchoolYearId = $this->activeSchoolYearId();
        if (!$this->isBillingInActiveCycle($billing, $activeSchoolYearId)) {
            Session::flash('error', 'Tagihan tidak termasuk tahun ajaran aktif.');

            return $this->redirect('keuangan/bendahara/tagihan');
        }

        $selectedItems = $request->input('items', []);

        if (!is_array($selectedItems) || empty($selectedItems)) {
            Session::flash('error', 'Pilih minimal satu siswa untuk dicatat pembayarannya.');

            return $this->redirect('keuangan/bendahara/tagihan/' . $billingId . '/pembayaran');
        }

        $itemIds = array_unique(array_map(static fn ($value): int => (int) $value, $selectedItems));
        $itemIds = array_values(array_filter($itemIds, static fn (int $value): bool => $value > 0));

        if (empty($itemIds)) {
            Session::flash('error', 'Pilihan siswa tidak valid.');

            return $this->redirect('keuangan/bendahara/tagihan/' . $billingId . '/pembayaran');
        }

        $notes = trim((string) $request->input('catatan', ''));
        $method = (string) $request->input('metode', 'tunai');

        if (!in_array($method, ['tunai', 'transfer', 'tabungan'], true)) {
            $method = 'tunai';
        }

        $recurringType = (string) ($billing['rutin_tipe'] ?? 'tidak');
        $isRecurring = in_array($recurringType, ['mingguan', 'bulanan'], true);

        $weeksInput = $isRecurring ? $request->input('weeks', []) : [];
        if (!is_array($weeksInput)) {
            $weeksInput = [];
        }

        $paymentModes = $isRecurring ? [] : $request->input('mode', []);
        if (!is_array($paymentModes)) {
            $paymentModes = [];
        }

        $amountInputs = $isRecurring ? [] : $request->input('amounts', []);
        if (!is_array($amountInputs)) {
            $amountInputs = [];
        }

        $items = $this->loadBillingItemsWithStudent($billingId);
        $itemsById = [];

        foreach ($items as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            if ($itemId > 0) {
                $itemsById[$itemId] = $item;
            }
        }

        $savingsBalances = [];
        if ($method === 'tabungan') {
            foreach ($itemsById as $item) {
                $studentKey = (int) ($item['siswa_id'] ?? 0);
                if ($studentKey > 0 && !isset($savingsBalances[$studentKey])) {
                    $savingsBalances[$studentKey] = (float) ($item['tabungan_saldo'] ?? 0.0);
                }
            }
        }

        $normalizeAmount = static function (mixed $raw): float {
            if (is_numeric($raw)) {
                return round((float) $raw, 2);
            }

            if (!is_string($raw)) {
                return 0.0;
            }

            $normalized = str_replace(',', '.', $raw);
            $normalized = preg_replace('/[^0-9.]/', '', $normalized);

            if ($normalized === null || $normalized === '') {
                return 0.0;
            }

            $lastDot = strrpos($normalized, '.');
            if ($lastDot !== false) {
                $integerPart = preg_replace('/\./', '', substr($normalized, 0, $lastDot));
                $decimalPart = substr($normalized, $lastDot + 1);
                $normalized = ($integerPart !== false ? $integerPart : '') . '.' . $decimalPart;
            } else {
                $normalized = preg_replace('/\./', '', $normalized);
            }

            return is_numeric($normalized) ? round((float) $normalized, 2) : 0.0;
        };

        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
        $now = date('Y-m-d H:i:s');
        $paymentPlans = [];
        $generatedPaymentIds = [];
        $processed = 0;
        $totalPaid = 0.0;

        foreach ($itemIds as $itemId) {
            if (!isset($itemsById[$itemId])) {
                continue;
            }

            $item = $itemsById[$itemId];
            $remaining = round((float) ($item['sisa_nominal'] ?? 0.0), 2);

            if (!Student::hasActiveStatus(['siswa_status' => $item['siswa_status'] ?? null])) {
                Session::flash('error', sprintf(
                    'Pembayaran untuk %s tidak dapat diproses karena status siswa nonaktif.',
                    $item['siswa_nama'] ?? 'Siswa'
                ));
                Session::flashInput($request->all());

                return $this->redirect('keuangan/bendahara/tagihan/' . $billingId . '/pembayaran');
            }

            if ($remaining <= 0.0) {
                continue;
            }

            if ($isRecurring) {
                $periodAmount = round((float) ($item['nominal_periode'] ?? 0.0), 2);
                if ($periodAmount <= 0.0) {
                    $periodAmount = round((float) ($item['nominal'] ?? 0.0), 2);
                }

                $maxWeeks = 1;
                if ($periodAmount > 0.0) {
                    $maxWeeks = (int) floor(($remaining + 0.0001) / $periodAmount);
                    if ($maxWeeks <= 0) {
                        $maxWeeks = $remaining > 0.0 ? 1 : 0;
                    }
                } elseif ($remaining <= 0.0) {
                    $maxWeeks = 0;
                }

                if ($maxWeeks <= 0) {
                    continue;
                }

                $selectedWeeksRaw = $weeksInput[$itemId] ?? $weeksInput[(string) $itemId] ?? null;
                $selectedWeeks = $selectedWeeksRaw === 'all' ? $maxWeeks : (int) $selectedWeeksRaw;
                if ($selectedWeeks <= 0) {
                    $selectedWeeks = 1;
                }
                if ($selectedWeeks > $maxWeeks) {
                    $selectedWeeks = $maxWeeks;
                }

                $amount = $periodAmount > 0.0
                    ? round($periodAmount * $selectedWeeks, 2)
                    : round($remaining, 2);

                if ($amount > $remaining) {
                    $amount = $remaining;
                }

                if ($amount <= 0.0) {
                    continue;
                }

                $studentIdForPlan = (int) ($item['siswa_id'] ?? 0);
                if ($method === 'tabungan') {
                    $availableSavings = $savingsBalances[$studentIdForPlan] ?? (float) ($item['tabungan_saldo'] ?? 0.0);
                    if ($amount > $availableSavings + 0.0001) {
                        Session::flash('error', sprintf(
                            'Saldo tabungan %s tidak mencukupi untuk pembayaran sejumlah %s.',
                            $item['siswa_nama'] ?? 'Siswa',
                            number_format($amount, 0, ',', '.')
                        ));
                        Session::flashInput($request->all());

                        return $this->redirect('keuangan/bendahara/tagihan/' . $billingId . '/pembayaran');
                    }

                    $savingsBalances[$studentIdForPlan] = $availableSavings - $amount;
                }

                $descriptionParts = [];
                if ($selectedWeeks >= $maxWeeks && $amount >= $remaining) {
                    $descriptionParts[] = 'Pelunasan semua tunggakan';
                } else {
                    $descriptionParts[] = 'Pembayaran hingga minggu ke-' . $selectedWeeks;
                }
                if ($notes !== '') {
                    $descriptionParts[] = $notes;
                }
                $description = implode(' — ', array_filter($descriptionParts, static fn (string $part): bool => $part !== ''));

                $studentPhone = $this->resolveStudentPhoneFromItem($item);

                $paymentPlans[] = [
                    'item_id' => $itemId,
                    'student_name' => (string) ($item['siswa_nama'] ?? 'Siswa'),
                    'student_id' => $studentIdForPlan,
                    'student_phone' => $studentPhone,
                    'student_nipd' => (string) ($item['siswa_nipd'] ?? ''),
                    'student_nisn' => (string) ($item['siswa_nisn'] ?? ''),
                    'class_name' => (string) ($item['kelas_nama'] ?? ''),
                    'amount' => round($amount, 2),
                    'description' => $description,
                    'is_partial' => $amount + 0.0001 < $remaining,
                    'remaining_before' => $remaining,
                ];

                continue;
            }

            $modeRaw = $paymentModes[$itemId] ?? $paymentModes[(string) $itemId] ?? 'full';
            $mode = is_string($modeRaw) ? strtolower($modeRaw) : 'full';
            if (!in_array($mode, ['full', 'partial'], true)) {
                $mode = 'full';
            }

            $amount = $remaining;
            if ($mode === 'partial') {
                $amountRaw = $amountInputs[$itemId] ?? $amountInputs[(string) $itemId] ?? null;
                $amountValue = $normalizeAmount($amountRaw);

                if ($amountValue <= 0.0) {
                    Session::flash('error', sprintf(
                        'Nominal pembayaran sebagian untuk %s harus lebih dari nol.',
                        $item['siswa_nama'] ?? 'Siswa'
                    ));
                    Session::flashInput($request->all());

                    return $this->redirect('keuangan/bendahara/tagihan/' . $billingId . '/pembayaran');
                }

                if ($amountValue > $remaining + 0.0001) {
                    Session::flash('error', sprintf(
                        'Nominal pembayaran sebagian untuk %s melebihi sisa tagihan.',
                        $item['siswa_nama'] ?? 'Siswa'
                    ));
                    Session::flashInput($request->all());

                    return $this->redirect('keuangan/bendahara/tagihan/' . $billingId . '/pembayaran');
                }

                $amount = $amountValue;
            }

            if ($amount <= 0.0) {
                continue;
            }

            $studentIdForPlan = (int) ($item['siswa_id'] ?? 0);
            if ($method === 'tabungan') {
                $availableSavings = $savingsBalances[$studentIdForPlan] ?? (float) ($item['tabungan_saldo'] ?? 0.0);
                if ($amount > $availableSavings + 0.0001) {
                    Session::flash('error', sprintf(
                        'Saldo tabungan %s tidak mencukupi untuk pembayaran sejumlah %s.',
                        $item['siswa_nama'] ?? 'Siswa',
                        number_format($amount, 0, ',', '.')
                    ));
                    Session::flashInput($request->all());

                    return $this->redirect('keuangan/bendahara/tagihan/' . $billingId . '/pembayaran');
                }

                $savingsBalances[$studentIdForPlan] = $availableSavings - $amount;
            }

            $descriptionParts = [];
            if ($amount + 0.0001 >= $remaining) {
                $descriptionParts[] = 'Pelunasan penuh';
            } else {
                $descriptionParts[] = 'Pembayaran sebagian Rp ' . number_format($amount, 0, ',', '.');
            }
            if ($notes !== '') {
                $descriptionParts[] = $notes;
            }
            $description = implode(' — ', array_filter($descriptionParts, static fn (string $part): bool => $part !== ''));

            $studentPhone = $this->resolveStudentPhoneFromItem($item);

            $paymentPlans[] = [
                'item_id' => $itemId,
                'student_name' => (string) ($item['siswa_nama'] ?? 'Siswa'),
                'student_id' => $studentIdForPlan,
                'student_phone' => $studentPhone,
                'student_nipd' => (string) ($item['siswa_nipd'] ?? ''),
                'student_nisn' => (string) ($item['siswa_nisn'] ?? ''),
                'class_name' => (string) ($item['kelas_nama'] ?? ''),
                'amount' => round($amount, 2),
                'description' => $description,
                'is_partial' => $amount + 0.0001 < $remaining,
                'remaining_before' => $remaining,
            ];
        }

        if (empty($paymentPlans)) {
            Session::flash('error', 'Tidak ada pembayaran yang diproses. Pastikan sisa tagihan masih tersedia.');

            return $this->redirect('keuangan/bendahara/tagihan/' . $billingId . '/pembayaran');
        }

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        $postCommitNotifications = [];

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $methodLabel = match ($method) {
                'tunai' => 'Tunai',
                'transfer' => 'Transfer',
                'tabungan' => 'Saldo Tabungan',
                default => ucfirst($method),
            };

            foreach ($paymentPlans as $plan) {
                $paymentId = PaymentService::record([
                    'tagihan_item_id' => $plan['item_id'],
                    'metode' => $method,
                    'nominal' => $plan['amount'],
                    'status' => 'disetujui',
                    'tanggal_bayar' => $now,
                    'diverifikasi_oleh' => $userId,
                    'diverifikasi_pada' => $now,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'catatan' => $plan['description'] !== '' ? $plan['description'] : null,
                ]);

                $paymentCode = $this->loadPaymentCode($paymentId);
                $studentName = $plan['student_name'] ?: 'Siswa';

                CashflowService::record('masuk', 'tagihan', $plan['amount'], [
                    'reference_id' => $paymentId,
                    'reference_code' => $paymentCode,
                    'description' => sprintf(
                        'Pembayaran tagihan %s - %s (%s%s)',
                        $billing['kode'] ?? ('#' . $billingId),
                        $studentName,
                        $methodLabel,
                        $plan['is_partial'] ? ', parsial' : ''
                    ),
                    'user_id' => $userId,
                    'school_year_id' => (int) ($billing['tahun_ajaran_id'] ?? 0) ?: null,
                ]);

                $postCommitNotifications[] = [
                    'student_name' => $studentName,
                    'student_phone' => $plan['student_phone'] ?? '',
                    'amount' => $plan['amount'],
                    'description' => $plan['description'],
                    'item_id' => $plan['item_id'],
                    'payment_code' => $paymentCode,
                    'payment_id' => $paymentId,
                    'timestamp' => $now,
                    'class_name' => $plan['class_name'] ?? '',
                    'student_nipd' => $plan['student_nipd'] ?? '',
                    'student_nisn' => $plan['student_nisn'] ?? '',
                ];

                $processed++;
                $totalPaid += $plan['amount'];
                $generatedPaymentIds[] = $paymentId;
            }

            if ($manageTransaction && $connection->inTransaction()) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            Session::flash('error', 'Gagal mencatat pembayaran: ' . $exception->getMessage());

            return $this->redirect('keuangan/bendahara/tagihan/' . $billingId . '/pembayaran');
        }

        $yearId = (int) ($billing['tahun_ajaran_id'] ?? 0);
        FinanceCache::forget('bendahara_dashboard_stats_' . ($yearId ?: 0));
        FinanceCache::forget('bendahara_dashboard_stats_0');
        FinanceCache::forget('kepsek_dashboard_summary_' . date('Y_m'));
        FinanceCache::forget('kepsek_dashboard_revenue_' . date('Y_m'));

        $whatsappResults = null;
        if (!empty($postCommitNotifications)) {
            $messageTemplate = (string) ($billing['whatsapp_message_template'] ?? '');
            $whatsappResults = $this->dispatchWhatsappReceipts(
                $billing,
                $postCommitNotifications,
                $messageTemplate,
                $methodLabel
            );
        }

        Session::flash('success', sprintf(
            'Berhasil mencatat pembayaran untuk %d siswa dengan total Rp %s.',
            $processed,
            number_format($totalPaid, 0, ',', '.')
        ));

        if (is_array($whatsappResults)) {
            $warningMessages = [];
            if ($whatsappResults['settings_missing'] ?? false) {
                $warningMessages[] = 'Pengaturan WhatsApp Gateway belum lengkap sehingga bukti bayar tidak dikirim otomatis.';
            }
            if (!empty($whatsappResults['missing_phone'] ?? [])) {
                $preview = array_slice($whatsappResults['missing_phone'], 0, 5);
                $moreCount = count($whatsappResults['missing_phone']) - count($preview);
                $message = 'Nomor WhatsApp belum tersedia untuk ' . implode(', ', $preview);
                if ($moreCount > 0) {
                    $message .= sprintf(' dan %d siswa lainnya', $moreCount);
                }
                $message .= '.';
                $warningMessages[] = $message;
            }
            if (!empty($whatsappResults['failed'] ?? [])) {
                $preview = array_slice($whatsappResults['failed'], 0, 5);
                $moreCount = count($whatsappResults['failed']) - count($preview);
                $message = 'Gagal mengirim bukti bayar WhatsApp ke ' . implode(', ', $preview);
                if ($moreCount > 0) {
                    $message .= sprintf(' dan %d siswa lainnya', $moreCount);
                }
                $message .= '. Periksa kembali token dan endpoint gateway.';
                $warningMessages[] = $message;
            }

            if (!empty($warningMessages)) {
                Session::flash('warning', implode(' ', $warningMessages));
            }
        }

        if (!empty($generatedPaymentIds)) {
            Session::flash('generated_payment_ids', $generatedPaymentIds);
        }

        return $this->redirect('keuangan/bendahara/tagihan/' . $billingId . '/pembayaran');
    }

    public function updateWhatsappTemplate(Request $request, int $id): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/bendahara/tagihan')) {
            return $response;
        }

        $billingId = max(0, (int) $id);
        if ($billingId <= 0) {
            Session::flash('error', 'Tagihan tidak ditemukan.');

            return $this->redirect('keuangan/bendahara/tagihan');
        }

        $billing = $this->loadBillingDetail($billingId);

        if ($billing === null) {
            Session::flash('error', 'Tagihan tidak ditemukan atau sudah dihapus.');

            return $this->redirect('keuangan/bendahara/tagihan');
        }

        $activeSchoolYearId = $this->activeSchoolYearId();
        if (!$this->isBillingInActiveCycle($billing, $activeSchoolYearId)) {
            Session::flash('error', 'Tagihan ini tidak termasuk tahun ajaran aktif.');

            return $this->redirect('keuangan/bendahara/tagihan');
        }

        $template = trim((string) $request->input('whatsapp_message_template', ''));
        $now = date('Y-m-d H:i:s');
        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;

        try {
            $payload = [
                'whatsapp_message_template' => $template !== '' ? $template : null,
                'updated_at' => $now,
            ];

            if ($userId !== null) {
                $payload['updated_by'] = $userId;
            }

            Billing::updateById($billingId, $payload);

            Session::flash('success', 'Template WhatsApp untuk tagihan ' . ($billing['kode'] ?? '#' . $billingId) . ' berhasil diperbarui.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui template WhatsApp: ' . $exception->getMessage());
        }

        return $this->redirect('keuangan/bendahara/tagihan');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadBillingsWithSummary(array $schoolYearIds): array
    {
        $schoolYearIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $schoolYearIds),
            static fn (int $id): bool => $id > 0
        )));

        if (empty($schoolYearIds)) {
            return [];
        }

        $connection = Database::connection();
        $placeholders = [];

        foreach ($schoolYearIds as $index => $yearId) {
            $placeholders[] = ':year_' . $index;
        }

        $statement = $connection->prepare(
            "SELECT
                t.*,
                kt.nama AS kategori_nama,
                ta.nama AS tahun_ajaran_nama,
                ta.semester_aktif AS tahun_ajaran_semester,
                COALESCE(tk.saldo_akhir, 0) AS kas_saldo,
                (
                    SELECT COUNT(*) FROM tagihan_item ti WHERE ti.tagihan_id = t.id
                ) AS total_penerima,
                (
                    SELECT COALESCE(SUM(ti.sisa_nominal), 0) FROM tagihan_item ti WHERE ti.tagihan_id = t.id
                ) AS total_sisa,
                (
                    SELECT COALESCE(SUM(p.nominal), 0)
                    FROM pembayaran p
                    JOIN tagihan_item ti2 ON ti2.id = p.tagihan_item_id
                    WHERE ti2.tagihan_id = t.id AND p.status = 'disetujui'
                ) AS total_terbayar
            FROM tagihan t
            JOIN kategori_tagihan kt ON kt.id = t.kategori_id
            JOIN tahun_ajaran ta ON ta.id = t.tahun_ajaran_id
            LEFT JOIN tagihan_kas tk ON tk.tagihan_id = t.id
            WHERE t.tahun_ajaran_id IN (" . implode(', ', $placeholders) . ")
              AND t.status <> 'dibatalkan'
            ORDER BY
                ta.tanggal_mulai DESC,
                ta.semester_aktif ASC,
                CASE WHEN kt.tipe = 'rutin' THEN 0 ELSE 1 END,
                CASE
                    WHEN t.status = 'aktif' THEN 0
                    WHEN t.status = 'ditutup' THEN 1
                    ELSE 2
                END,
                t.created_at DESC,
                t.id DESC"
        );

        if ($statement === false) {
            return [];
        }

        foreach ($schoolYearIds as $index => $yearId) {
            $statement->bindValue(':year_' . $index, $yearId, \PDO::PARAM_INT);
        }

        $statement->execute();

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    protected function defaultWhatsappTemplate(): string
    {
        return "Halo {{nama_siswa}}, pembayaran untuk tagihan {{judul_tagihan}} sebesar {{nominal_bayar}} telah kami terima pada {{tanggal_pembayaran}}. Unduh bukti pembayaran: {{link_bukti_bayar}}. Sisa tagihan: {{sisa_tagihan}}. Terima kasih.";
    }

    protected function resolveStudentPhoneFromRecord(?array $student): string
    {
        if (!is_array($student)) {
            return '';
        }

        $mobile = trim((string) ($student['hp'] ?? ''));
        if ($mobile !== '') {
            return $mobile;
        }

        return trim((string) ($student['telepon'] ?? ''));
    }

    protected function resolveStudentPhoneFromItem(array $item): string
    {
        $mobile = trim((string) ($item['siswa_hp'] ?? ''));
        if ($mobile !== '') {
            return $mobile;
        }

        return trim((string) ($item['siswa_telepon'] ?? ''));
    }

    protected function formatCurrencyValue(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    /**
     * @param array<int, array<string, mixed>> $notifications
     * @return array<string, mixed>
     */
    protected function dispatchWhatsappReceipts(array $billing, array $notifications, string $template, string $methodLabel): array
    {
        $results = [
            'missing_phone' => [],
            'failed' => [],
            'settings_missing' => false,
        ];

        if (empty($notifications)) {
            return $results;
        }

        $settings = WhatsappGatewaySetting::first();
        if ($settings === null) {
            $results['settings_missing'] = true;

            return $results;
        }

        foreach ($notifications as $notification) {
            $phone = WhatsappGatewayService::normalizePhone((string) ($notification['student_phone'] ?? ''));
            if ($phone === '') {
                $name = trim((string) ($notification['student_name'] ?? 'Siswa'));
                $results['missing_phone'][] = $name !== '' ? $name : 'Siswa';
                continue;
            }

            $item = BillingItem::find((int) ($notification['item_id'] ?? 0));
            if ($item === null) {
                continue;
            }

            $remaining = (float) ($item['sisa_nominal'] ?? 0);
            $total = (float) ($item['nominal'] ?? 0);
            $amountPaid = (float) ($notification['amount'] ?? 0);
            $timestamp = (string) ($notification['timestamp'] ?? date('Y-m-d H:i:s'));

            $paymentId = (int) ($notification['payment_id'] ?? 0);
            $payment = $paymentId > 0 ? PaymentDetailService::findById($paymentId) : null;
            $shareableUrl = $payment !== null ? FinancePaymentSlipToken::buildPublicUrl($payment) : null;

            $pdfUrl = $shareableUrl !== null
                ? $shareableUrl . '/pdf'
                : ($paymentId > 0 ? absolute_url('keuangan/bendahara/pembayaran/' . $paymentId . '/slip/pdf') : null);
            $htmlUrl = $shareableUrl !== null
                ? $shareableUrl
                : ($paymentId > 0 ? absolute_url('keuangan/bendahara/pembayaran/' . $paymentId . '/slip') : null);

            $variables = [
                'nama_siswa' => $notification['student_name'] ?? 'Siswa',
                'judul_tagihan' => $billing['judul'] ?? 'Tagihan',
                'kode_tagihan' => $billing['kode'] ?? ('#' . ($billing['id'] ?? '')),
                'nominal_tagihan' => $this->formatCurrencyValue($total),
                'nominal_tagihan_angka' => number_format($total, 2, '.', ''),
                'nominal_bayar' => $this->formatCurrencyValue($amountPaid),
                'nominal_bayar_angka' => number_format($amountPaid, 2, '.', ''),
                'sisa_tagihan' => $this->formatCurrencyValue($remaining),
                'sisa_tagihan_angka' => number_format($remaining, 2, '.', ''),
                'metode_pembayaran' => $methodLabel,
                'tanggal_pembayaran' => date('d M Y H:i', strtotime($timestamp)),
                'kode_pembayaran' => $notification['payment_code'] ?? '',
                'catatan_pembayaran' => $notification['description'] ?? '',
                'nama_sekolah' => config('app.name'),
                'kelas_siswa' => $notification['class_name'] ?? '',
                'nis_siswa' => $notification['student_nipd'] ?? '',
                'nisn_siswa' => $notification['student_nisn'] ?? '',
            ];

            if ($pdfUrl !== null) {
                $variables['link_bukti_bayar'] = $pdfUrl;
                $variables['link_bukti_bayar_pdf'] = $pdfUrl;
            }
            if ($htmlUrl !== null) {
                $variables['link_bukti_bayar_html'] = $htmlUrl;
            }

            $sent = WhatsappGatewayService::send([
                'phone' => $phone,
                'template' => $template !== '' ? $template : null,
                'variables' => $variables,
            ], $settings);

            if (!$sent) {
                $name = trim((string) ($notification['student_name'] ?? 'Siswa'));
                $results['failed'][] = $name !== '' ? $name : 'Siswa';
            }
        }

        $results['missing_phone'] = array_values(array_unique($results['missing_phone']));
        $results['failed'] = array_values(array_unique($results['failed']));

        return $results;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function loadBillingDetail(int $billingId): ?array
    {
        $connection = Database::connection();
        $statement = $connection->prepare(
            "SELECT
                t.*,
                kt.nama AS kategori_nama,
                COALESCE(tk.saldo_masuk, 0) AS kas_saldo_masuk,
                COALESCE(tk.saldo_keluar, 0) AS kas_saldo_keluar,
                COALESCE(tk.saldo_akhir, 0) AS kas_saldo
             FROM tagihan t
             JOIN kategori_tagihan kt ON kt.id = t.kategori_id
             LEFT JOIN tagihan_kas tk ON tk.tagihan_id = t.id
             WHERE t.id = :id
             LIMIT 1"
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $billingId, \PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadBillingItemsWithStudent(int $billingId): array
    {
        $connection = Database::connection();
        $statement = $connection->prepare(
            "SELECT
                ti.*,
                s.nama AS siswa_nama,
                s.nipd AS siswa_nipd,
                s.nisn AS siswa_nisn,
                s.status AS siswa_status,
                s.hp AS siswa_hp,
                s.telepon AS siswa_telepon,
                k.nama AS kelas_nama,
                pay.last_payment_at,
                ts.saldo_terakhir AS tabungan_saldo
             FROM tagihan_item ti
             JOIN tagihan t ON t.id = ti.tagihan_id
             JOIN siswa s ON s.id = ti.siswa_id
             LEFT JOIN kelas k ON k.id = ti.kelas_id
             LEFT JOIN tabungan_siswa ts ON ts.siswa_id = ti.siswa_id AND ts.tahun_ajaran_id = t.tahun_ajaran_id
             LEFT JOIN (
                SELECT tagihan_item_id, MAX(tanggal_bayar) AS last_payment_at
                FROM pembayaran
                WHERE status = 'disetujui'
                GROUP BY tagihan_item_id
             ) AS pay ON pay.tagihan_item_id = ti.id
             WHERE ti.tagihan_id = :billing
             ORDER BY s.nama ASC, ti.id ASC"
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':billing', $billingId, \PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    protected function loadPaymentCode(int $paymentId): ?string
    {
        if ($paymentId <= 0) {
            return null;
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            'SELECT kode_transaksi FROM pembayaran WHERE id = :id LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $paymentId, \PDO::PARAM_INT);
        $statement->execute();

        $code = $statement->fetchColumn();

        return $code === false ? null : (string) $code;
    }

    /**
     * @return array<int>
     */
    protected function relatedBillingSchoolYearIds(?int $schoolYearId): array
    {
        if ($schoolYearId === null || $schoolYearId <= 0) {
            return [];
        }

        $relatedIds = SchoolYear::relatedIds($schoolYearId);

        return !empty($relatedIds) ? $relatedIds : [$schoolYearId];
    }

    /**
     * @param array<string, mixed>|null $billing
     */
    protected function isBillingInActiveCycle(?array $billing, ?int $activeSchoolYearId): bool
    {
        if ($billing === null) {
            return false;
        }

        if ($activeSchoolYearId === null || $activeSchoolYearId <= 0) {
            return true;
        }

        $billingYearId = (int) ($billing['tahun_ajaran_id'] ?? 0);

        if ($billingYearId <= 0) {
            return false;
        }

        return in_array($billingYearId, $this->relatedBillingSchoolYearIds($activeSchoolYearId), true);
    }

    public function generateRecurring(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/bendahara/tagihan')) {
            return $response;
        }

        try {
            $generated = RecurringBillingService::generateDue();
            if ($generated > 0) {
                Session::flash('success', "Berhasil generate {$generated} siklus tagihan rutin.");
            } else {
                Session::flash('info', 'Tidak ada tagihan rutin yang harus digenerate hari ini.');
            }
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal generate tagihan rutin: ' . $exception->getMessage());
        }

        return $this->redirect('keuangan/bendahara/tagihan');
    }
}

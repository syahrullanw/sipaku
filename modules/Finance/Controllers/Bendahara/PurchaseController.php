<?php

declare(strict_types=1);

namespace Modules\Finance\Controllers\Bendahara;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\SupplyPurchase;
use App\Models\WhatsappGatewaySetting;
use App\Services\Finance\PurchaseCashService;
use App\Services\Finance\PurchasePaymentService;
use App\Services\Finance\TransactionCodeGenerator;
use App\Services\Finance\BillingCashService;
use App\Services\Finance\CashflowService;
use App\Services\Finance\GeneralCashService;
use App\Services\Finance\PaymentService;
use App\Models\Billing;
use App\Models\BillingItem;
use App\Models\Payment;
use App\Services\WhatsappGatewayService;
use App\Support\FinanceCache;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Log;
use Modules\Finance\Controllers\Controller;

class PurchaseController extends Controller
{
    private const PAYMENT_METHODS = ['cash', 'tabungan', 'sekolah'];
    private const PURCHASE_TYPES = ['atribut', 'seragam', 'lain'];

    public function index(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();
        $selectedClassId = (int) ($request->query('class_id') ?? 0);
        $searchQuery = trim((string) ($request->query('q') ?? ''));

        $classOptions = Classroom::options($schoolYearId);
        $studentOptions = $schoolYearId !== null
            ? Student::options($selectedClassId > 0 ? $selectedClassId : null, $schoolYearId)
            : [];

        $purchases = $schoolYearId !== null
            ? SupplyPurchase::forSchoolYear($schoolYearId, $selectedClassId > 0 ? $selectedClassId : null, $searchQuery)
            : [];

        $summary = $this->summarizePurchases($purchases);
        $paymentablePurchases = array_values(array_filter($purchases, static fn (array $purchase): bool => (float) ($purchase['sisa_nominal'] ?? 0) > 0
            && Student::hasActiveStatus(['siswa_status' => $purchase['siswa_status'] ?? null])));

        return $this->render('finance/bendahara/purchases/index', [
            'title' => 'Pembelian Perlengkapan',
            'pageTitle' => 'Pembelian Perlengkapan',
            'activeMenu' => 'finance-bendahara-purchases',
            'hasActiveYear' => $schoolYearId !== null,
            'classOptions' => $classOptions,
            'studentOptions' => $studentOptions,
            'filters' => [
                'class_id' => $selectedClassId,
                'query' => $searchQuery,
            ],
            'purchases' => $purchases,
            'summary' => $summary,
            'purchaseTypes' => [
                'atribut' => 'Atribut & Perlengkapan',
                'seragam' => 'Seragam',
                'lain' => 'Lainnya',
            ],
            'paymentMethods' => [
                'cash' => 'Tagih siswa (tunai)',
                'tabungan' => 'Potong tabungan siswa',
                'sekolah' => 'Sekolah menutup dari kas utama',
            ],
            'defaultPurchaseType' => 'lain',
            'defaultPaymentMethod' => 'cash',
            'paymentablePurchases' => $paymentablePurchases,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/bendahara/pembelian')) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();
        if ($schoolYearId === null) {
            Session::flash('error', 'Tidak ada tahun ajaran aktif untuk mencatat pembelian.');

            return $this->redirect('keuangan/bendahara/pembelian');
        }

        $studentId = (int) ($request->input('student_id') ?? 0);
        $itemLabel = trim((string) ($request->input('item_label') ?? ''));
        $note = trim((string) ($request->input('note') ?? ''));
        $jenis = (string) ($request->input('jenis') ?? 'lain');
        $paymentMethod = (string) ($request->input('payment_method') ?? 'cash');
        $amount = max(0.0, $this->normalizeAmount((string) ($request->input('amount') ?? '0')));

        if ($studentId <= 0 || $itemLabel === '' || $amount <= 0) {
            Session::flash('error', 'Lengkapi nama siswa, deskripsi pembelian, dan nominal yang valid.');

            return $this->redirect('keuangan/bendahara/pembelian');
        }

        if (!in_array($jenis, self::PURCHASE_TYPES, true)) {
            $jenis = 'lain';
        }

        if (!in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            $paymentMethod = 'cash';
        }

        $student = Student::find($studentId);
        if ($student === null || (int) ($student['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            Session::flash('error', 'Siswa tidak ditemukan dalam tahun ajaran aktif.');

            return $this->redirect('keuangan/bendahara/pembelian');
        }

        if ($student !== null && !Student::hasActiveStatus($student)) {
            Session::flash('error', 'Pembelian tidak dapat dicatat karena status siswa nonaktif.');

            return $this->redirect('keuangan/bendahara/pembelian');
        }

        $now = date('Y-m-d H:i:s');
        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
        $studentName = trim((string) ($student['nama'] ?? 'Siswa')) ?: 'Siswa';
        $description = 'Pembelian ' . $itemLabel . ' untuk ' . $studentName;
        $message = 'Pembelian tercatat dan menunggu penyelesaian siswa.';
        $purchaseId = null;
        $paymentResult = null;
        $paymentMethodLabel = $paymentMethod === 'tabungan' ? 'Saldo Tabungan' : ($paymentMethod === 'sekolah' ? 'Kas Sekolah' : 'Tunai Siswa');

        try {
            $purchaseCode = TransactionCodeGenerator::generate('PB', static fn (string $candidate): bool => SupplyPurchase::exists(['kode' => $candidate]));
            $purchaseId = SupplyPurchase::createAndReturnId([
                'kode' => $purchaseCode,
                'tagihan_id' => null,
                'siswa_id' => $studentId,
                'tahun_ajaran_id' => $schoolYearId,
                'item_label' => $itemLabel,
                'jenis' => $jenis,
                'metode_pembayaran' => $paymentMethod,
                'nominal' => $amount,
                'nominal_terbayar' => 0.0,
                'sisa_nominal' => $amount,
                'status' => 'menunggu_pembayaran',
                'catatan' => $note !== '' ? $note : null,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            if ($purchaseId !== null) {
                PurchaseCashService::initialize($purchaseId);
            }

            if ($paymentMethod === 'tabungan' || $paymentMethod === 'sekolah') {
                if ($purchaseId === null) {
                    throw new \RuntimeException('Pembelian tidak ditemukan setelah pencatatan.');
                }

                $paymentResult = PurchasePaymentService::record($purchaseId, $amount, $paymentMethod, [
                    'note' => $description,
                    'user_id' => $userId,
                    'paid_at' => $now,
                ]);

                if ($paymentMethod === 'tabungan') {
                    $message = 'Pembelian berhasil dicatat dan dipotong langsung dari tabungan siswa.';
                } else {
                    $message = 'Pembelian tercatat dan dibayarkan dari kas sekolah.';
                }
            }

            $purchase = $purchaseId !== null ? SupplyPurchase::find($purchaseId) : null;

            if ($purchase !== null) {
                $creationNotification = $this->dispatchPurchaseCreationNotification(
                    $purchase,
                    $student,
                    $amount,
                    $description,
                    $this->defaultPurchaseInvoiceTemplate()
                );
                $this->maybeFlashWhatsappResult($creationNotification, 'pembelian');

                if ($paymentResult !== null) {
                    $paymentNotification = $this->dispatchPurchasePaymentNotification(
                        $purchase,
                        $student,
                        $amount,
                        $paymentResult['payment'] ?? [],
                        $paymentMethodLabel,
                        $description,
                        $this->defaultPurchasePaymentTemplate()
                    );
                    $this->maybeFlashWhatsappResult($paymentNotification, 'pembayaran');
                }
            }

            $this->refreshDashboardCache($schoolYearId);
            Session::flash('success', $message);
        } catch (\Throwable $exception) {
            if ($purchaseId !== null && $paymentResult === null) {
                SupplyPurchase::deleteById($purchaseId);
            }

            Session::flash('error', 'Gagal merekam pembelian: ' . $exception->getMessage());
        }

        return $this->redirect('keuangan/bendahara/pembelian');
    }

    public function pay(Request $request): Response
    {
        $isJsonRequest = $this->isJsonRequest($request);

        if ($response = $this->guardBendahara()) {
            if ($isJsonRequest) {
                return $this->json([
                    'success' => false,
                    'message' => 'Modul ini hanya dapat diakses oleh bendahara aktif.',
                    'purchase_id' => (int) ($request->input('purchase_id') ?? 0),
                    'outstanding' => 0.0,
                    'formatted_outstanding' => $this->formatCurrencyValue(0.0),
                ], 403);
            }

            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/bendahara/pembelian')) {
            if ($isJsonRequest) {
                return $this->json([
                    'success' => false,
                    'message' => 'Sesi tidak valid atau telah kedaluwarsa. Silakan coba lagi.',
                    'purchase_id' => (int) ($request->input('purchase_id') ?? 0),
                    'outstanding' => 0.0,
                    'formatted_outstanding' => $this->formatCurrencyValue(0.0),
                ], 419);
            }

            return $response;
        }

        \Core\Log::channel('finance')->info('Purchase pay request received', [
            'purchase_id' => $request->input('purchase_id'),
            'amount' => $request->input('amount'),
            'payment_method' => $request->input('payment_method'),
            'user_id' => $this->user()['id'] ?? null,
        ]);

        \Core\Log::channel('finance')->info('Purchase pay headers', [
            'accept' => $request->header('Accept'),
            'xhr' => $request->header('X-Requested-With'),
        ]);

        $purchaseId = (int) ($request->input('purchase_id') ?? 0);
        $paymentMethod = (string) ($request->input('payment_method') ?? 'cash');
        $paymentMode = (string) ($request->input('payment_mode') ?? 'partial');
        $amount = max(0.0, $this->normalizeAmount((string) ($request->input('amount') ?? '0')));
        $ajaxSignal = (string) ($request->input('ajax') ?? '');
        $isJson = $ajaxSignal === '1' || $isJsonRequest;

        $respondWithJsonError = function (string $message, float $outstandingValue = 0.0, int $status = 400) use ($isJson, $purchaseId): ?Response {
            if (!$isJson) {
                return null;
            }

            return $this->json([
                'success' => false,
                'message' => $message,
                'purchase_id' => $purchaseId,
                'outstanding' => $outstandingValue,
                'formatted_outstanding' => $this->formatCurrencyValue($outstandingValue),
            ], $status);
        };

        if ($purchaseId <= 0 || $amount <= 0) {
            $message = 'Pilih pembelian dan nominal yang valid.';
            if ($response = $respondWithJsonError($message)) {
                return $response;
            }

            Session::flash('error', $message);

            return $this->redirect('keuangan/bendahara/pembelian');
        }

        $purchase = $purchaseId > 0 ? SupplyPurchase::find($purchaseId) : null;
        if ($purchase === null) {
            $message = 'Pembelian tidak ditemukan.';
            if ($response = $respondWithJsonError($message)) {
                return $response;
            }

            Session::flash('error', $message);

            return $this->redirect('keuangan/bendahara/pembelian');
        }

        $methodLabel = match ($paymentMethod) {
            'tabungan' => 'Saldo Tabungan',
            'sekolah' => 'Kas Sekolah',
            default => 'Tunai Siswa',
        };

        $billingId = (int) ($purchase['tagihan_id'] ?? 0);
        $studentId = (int) ($purchase['siswa_id'] ?? 0);
        $student = $studentId > 0 ? Student::find($studentId) : null;

        if (!Student::hasActiveStatus($student)) {
            $message = 'Pembayaran pembelian tidak dapat diproses karena status siswa nonaktif.';
            if ($response = $respondWithJsonError($message)) {
                return $response;
            }

            Session::flash('error', $message);

            return $this->redirect('keuangan/bendahara/pembelian');
        }

        if ($billingId <= 0) {
            $outstanding = max(0.0, (float) ($purchase['sisa_nominal'] ?? ((float) ($purchase['nominal'] ?? 0) - (float) ($purchase['nominal_terbayar'] ?? 0))));

            if ($outstanding <= 0.0) {
                $message = 'Tidak ada sisa pembelian untuk dibayarkan.';
                if ($response = $respondWithJsonError($message, $outstanding)) {
                    return $response;
                }

                Session::flash('info', $message);

                return $this->redirect('keuangan/bendahara/pembelian');
            }

            if ($amount > $outstanding + 0.0001) {
                $message = 'Nominal pembayaran tidak boleh melebihi sisa pembelian.';
                if ($response = $respondWithJsonError($message, $outstanding)) {
                    return $response;
                }

                Session::flash('error', $message);

                return $this->redirect('keuangan/bendahara/pembelian');
            }

            if ($paymentMode === 'full') {
                $amount = $outstanding;
            }

            if ($amount > $outstanding + 0.0001) {
                $message = 'Nominal pembayaran tidak boleh melebihi sisa pembelian.';
                if ($response = $respondWithJsonError($message, $outstanding)) {
                    return $response;
                }

                Session::flash('error', $message);

                return $this->redirect('keuangan/bendahara/pembelian');
            }

            $now = date('Y-m-d H:i:s');
            $user = $this->user();
            $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
            $description = 'Pembayaran pembelian ' . ($purchase['item_label'] ?? 'perlengkapan') . ' untuk ' . ($student['nama'] ?? 'Siswa');

            try {
                $result = PurchasePaymentService::record($purchaseId, $amount, $paymentMethod, [
                    'note' => $description,
                    'user_id' => $userId,
                    'paid_at' => $now,
                ]);

                $updatedPurchase = $result['purchase'] ?? SupplyPurchase::find($purchaseId);

                if ($updatedPurchase !== null) {
                    $paymentNotification = $this->dispatchPurchasePaymentNotification(
                        $updatedPurchase,
                        $student ?? [],
                        $amount,
                        $result['payment'] ?? [],
                        $methodLabel,
                        $description,
                        $this->defaultPurchasePaymentTemplate()
                    );
                    $this->maybeFlashWhatsappResult($paymentNotification, 'pembayaran');
                }

                $remainingAfter = $result['remaining'] ?? max(0.0, (float) ($updatedPurchase['sisa_nominal'] ?? 0.0));

                $payload['success'] = true;
                $payload['message'] = 'Pembayaran berhasil dicatat.';
                $payload['outstanding'] = $remainingAfter;
                $payload['formatted_outstanding'] = $this->formatCurrencyValue($remainingAfter);

                if ($isJson) {
                    return $this->json($payload);
                }

                Session::flash('purchase_payment_status', $payload['message']);
                Session::flash('purchase_payment_level', 'success');
                Session::flash('success', $payload['message']);

                return $this->redirect('keuangan/bendahara/pembelian');
            } catch (\Throwable $exception) {
                $message = 'Gagal mencatat pembayaran: ' . $exception->getMessage();
                if ($response = $respondWithJsonError($message, $outstanding)) {
                    return $response;
                }

                Session::flash('error', $message);

                return $this->redirect('keuangan/bendahara/pembelian');
            }
        }

        $itemId = $billingId > 0 && $studentId > 0 ? $this->findBillingItemId($billingId, $studentId) : 0;
        $item = $itemId > 0 ? BillingItem::find($itemId) : null;
        $outstanding = $item !== null ? max(0.0, (float) ($item['sisa_nominal'] ?? 0.0)) : 0.0;

        if ($itemId <= 0 || $billingId <= 0 || $studentId <= 0 || $student === null || $item === null) {
            $message = 'Data pembelian tidak lengkap.';
            if ($response = $respondWithJsonError($message, $outstanding)) {
                return $response;
            }

            Session::flash('error', $message);

            return $this->redirect('keuangan/bendahara/pembelian');
        }

        if ($outstanding <= 0.0) {
            \Core\Log::channel('finance')->info('Purchase pay outstanding mismatch', [
                'purchase_id' => $purchaseId,
                'item_id' => $itemId,
                'item_outstanding' => $item['sisa_nominal'] ?? null,
            ]);

            $message = 'Tidak ada sisa tagihan untuk pembelian ini.';
            if ($response = $respondWithJsonError($message, $outstanding)) {
                return $response;
            }

            Session::flash('info', $message);

            return $this->redirect('keuangan/bendahara/pembelian');
        }

        if ($paymentMode === 'full') {
            $amount = $outstanding;
        }

        if ($amount > $outstanding + 0.0001) {
            $message = 'Nominal pembayaran tidak boleh melebihi sisa tagihan.';
            if ($response = $respondWithJsonError($message, $outstanding)) {
                return $response;
            }

            Session::flash('error', $message);

            return $this->redirect('keuangan/bendahara/pembelian');
        }

        $now = date('Y-m-d H:i:s');
        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
        $description = 'Pembayaran pembelian ' . ($purchase['item_label'] ?? 'perlengkapan') . ' untuk ' . ($student['nama'] ?? 'Siswa');

        $paymentId = 0;
        $payment = null;
        $payload = [
            'success' => false,
            'message' => '',
            'purchase_id' => $purchaseId,
            'outstanding' => $outstanding,
            'formatted_outstanding' => $this->formatCurrencyValue($outstanding),
        ];
        $ajaxSignal = (string) ($request->input('ajax') ?? '');
        $isJson = $ajaxSignal === '1' || $isJsonRequest;

        \Core\Log::channel('finance')->info('Purchase pay detection', [
            'ajax' => $ajaxSignal,
            'is_json' => $isJson,
            'accept' => $request->header('Accept'),
            'xhr' => $request->header('X-Requested-With'),
        ]);

        try {
            $paymentMethodForRecord = $paymentMethod === 'tabungan' ? 'tabungan' : 'tunai';

            $paymentId = PaymentService::record([
                'tagihan_item_id' => $itemId,
                'metode' => $paymentMethodForRecord,
                'nominal' => $amount,
                'status' => 'disetujui',
                'tanggal_bayar' => $now,
                'catatan' => $description,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $payment = Payment::find($paymentId);

            if ($payment === null) {
                throw new \RuntimeException('Gagal menyimpan pembayaran.');
            }

            if ($purchaseId > 0) {
                PurchaseCashService::increase($purchaseId, $amount);
            }

            if ($paymentMethod === 'tabungan') {
                CashflowService::record('masuk', 'tagihan', $amount, [
                    'reference_id' => $paymentId,
                    'reference_code' => $payment['kode_transaksi'] ?? null,
                    'description' => $description,
                    'user_id' => $userId,
                    'school_year_id' => (int) ($purchase['tahun_ajaran_id'] ?? 0) ?: null,
                    'recorded_at' => $payment['tanggal_bayar'] ?? $now,
                ]);
            } elseif ($paymentMethod === 'sekolah') {
                BillingCashService::decrease($billingId, $amount);

                GeneralCashService::withdrawForPurchase((int) ($purchase['tahun_ajaran_id'] ?? 0), $amount, [
                    'billing_id' => $billingId,
                    'description' => $description,
                    'recorded_at' => $payment['tanggal_bayar'] ?? $now,
                    'user_id' => $userId,
                ]);

                CashflowService::record('keluar', 'kas_umum', $amount, [
                    'reference_id' => $paymentId,
                    'reference_code' => $payment['kode_transaksi'] ?? null,
                    'description' => $description,
                    'user_id' => $userId,
                    'school_year_id' => (int) ($purchase['tahun_ajaran_id'] ?? 0) ?: null,
                    'recorded_at' => $payment['tanggal_bayar'] ?? $now,
                ]);
            }

            $billing = Billing::find($billingId);
            if ($billing !== null) {
                $legacyContext = [
                    'id' => $purchase['id'] ?? null,
                    'kode' => $billing['kode'] ?? ('#' . ($billing['id'] ?? '')),
                    'item_label' => $purchase['item_label'] ?? ($billing['judul'] ?? 'Pembelian Perlengkapan'),
                    'nominal' => (float) ($item['nominal'] ?? $purchase['nominal'] ?? $amount),
                    'nominal_terbayar' => max(0.0, (float) ($item['nominal'] ?? $purchase['nominal'] ?? 0) - max(0.0, $outstanding - $amount)),
                    'sisa_nominal' => max(0.0, $outstanding - $amount),
                    'created_at' => $purchase['created_at'] ?? $billing['created_at'] ?? null,
                ];

                $notification = $this->dispatchPurchasePaymentNotification(
                    $legacyContext,
                    $student,
                    $amount,
                    $payment,
                    $methodLabel,
                    $description,
                    $this->defaultPurchasePaymentTemplate()
                );
                $this->maybeFlashWhatsappResult($notification, 'pembayaran');
            }

            FinanceCache::forget('bendahara_dashboard_stats_' . ((int) ($purchase['tahun_ajaran_id'] ?? 0) ?: 0));
            FinanceCache::forget('bendahara_dashboard_stats_0');

            $payload['success'] = true;
            $payload['message'] = 'Pembayaran berhasil dicatat.';
        } catch (\Throwable $exception) {
            if ($paymentId > 0) {
                Payment::deleteById($paymentId);
            }

            $payload['message'] = 'Gagal mencatat pembayaran: ' . $exception->getMessage();
        }

        $latestItem = $itemId > 0 ? BillingItem::find($itemId) : null;
        $remainingAfter = $latestItem !== null ? max(0.0, (float) ($latestItem['sisa_nominal'] ?? 0.0)) : $outstanding;
        $payload['outstanding'] = $remainingAfter;
        $payload['formatted_outstanding'] = $this->formatCurrencyValue($remainingAfter);

        if ($isJson) {
            \Core\Log::channel('finance')->info('Purchase pay response JSON', [
                'purchase_id' => $purchaseId,
                'payload' => $payload,
            ]);

            return $this->json($payload);
        }

        Session::flash('purchase_payment_status', $payload['message']);
        Session::flash('purchase_payment_level', $payload['success'] ? 'success' : 'error');
        if ($payload['success']) {
            Session::flash('success', $payload['message']);
        } else {
            Session::flash('error', $payload['message']);
        }

        return $this->redirect('keuangan/bendahara/pembelian');
    }

    protected function defaultPurchaseInvoiceTemplate(): string
    {
        return "Halo {{nama_siswa}}, tagihan pembelian {{item_label}} sebesar {{nominal_tagihan}} telah dibuat dengan kode {{kode_tagihan}}. Silakan bayarkan sesuai metode dan kirim bukti jika sudah melakukan pembayaran.";
    }

    protected function defaultPurchasePaymentTemplate(): string
    {
        return $this->formatPaymentTemplate();
    }

    protected function formatPaymentTemplate(): string
    {
        return "Halo {{nama_siswa}}, pembayaran pembelian {{judul_tagihan}} sebesar {{nominal_bayar}} telah kami terima pada {{tanggal_pembayaran}}. Unduh bukti pembayaran: {{link_bukti_bayar}}. Sisa tagihan: {{sisa_tagihan}}.";
    }

    private function resolveStudentPhoneFromRecord(?array $student): string
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

    private function formatCurrencyValue(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    private function dispatchPurchaseCreationNotification(array $purchase, array $student, float $amount, string $description, string $template): array
    {
        $results = [
            'missing_phone' => [],
            'failed' => [],
            'settings_missing' => false,
        ];

        $settings = WhatsappGatewaySetting::first();
        if ($settings === null) {
            $results['settings_missing'] = true;

            return $results;
        }

        $phone = WhatsappGatewayService::normalizePhone($this->resolveStudentPhoneFromRecord($student));
        if ($phone === '') {
            $results['missing_phone'][] = trim((string) ($student['nama'] ?? 'Siswa')) ?: 'Siswa';

            return $results;
        }

        $variables = [
            'nama_siswa' => trim((string) ($student['nama'] ?? 'Siswa')),
            'kode_tagihan' => $purchase['kode'] ?? ('PB/' . ($purchase['id'] ?? '')),
            'item_label' => $purchase['item_label'] ?? 'Pembelian Perlengkapan',
            'nominal_tagihan' => $this->formatCurrencyValue($amount),
            'nominal_tagihan_angka' => number_format($amount, 2, '.', ''),
            'keterangan_tagihan' => $description,
            'nama_sekolah' => config('app.name'),
            'tanggal_tagihan' => date('d M Y H:i', strtotime((string) ($purchase['created_at'] ?? 'now'))),
        ];

        $sent = WhatsappGatewayService::send([
            'phone' => $phone,
            'template' => $template !== '' ? $template : null,
            'variables' => $variables,
        ], $settings);

        if (!$sent) {
            $results['failed'][] = $variables['nama_siswa'] ?: 'Siswa';
        }

        return $results;
    }

    private function dispatchPurchasePaymentNotification(
        array $purchase,
        ?array $student,
        float $amount,
        array $payment,
        string $paymentMethod,
        string $description,
        string $template
    ): array {
        $results = [
            'missing_phone' => [],
            'failed' => [],
            'settings_missing' => false,
        ];

        $settings = WhatsappGatewaySetting::first();
        if ($settings === null) {
            $results['settings_missing'] = true;

            return $results;
        }

        $phone = WhatsappGatewayService::normalizePhone($this->resolveStudentPhoneFromRecord($student));
        if ($phone === '') {
            $results['missing_phone'][] = trim((string) ($student['nama'] ?? 'Siswa')) ?: 'Siswa';

            return $results;
        }

        $total = (float) ($purchase['nominal'] ?? 0.0);
        $remaining = max(0.0, (float) ($purchase['sisa_nominal'] ?? max(0.0, $total - (float) ($purchase['nominal_terbayar'] ?? 0.0))));

        $paymentTimestamp = isset($payment['tanggal_bayar']) ? strtotime((string) $payment['tanggal_bayar']) : time();

        $variables = [
            'nama_siswa' => trim((string) ($student['nama'] ?? 'Siswa')),
            'judul_tagihan' => $purchase['item_label'] ?? 'Pembelian Perlengkapan',
            'kode_tagihan' => $purchase['kode'] ?? ('PB/' . ($purchase['id'] ?? '')),
            'nominal_tagihan' => $this->formatCurrencyValue($total),
            'nominal_tagihan_angka' => number_format($total, 2, '.', ''),
            'nominal_bayar' => $this->formatCurrencyValue($amount),
            'nominal_bayar_angka' => number_format($amount, 2, '.', ''),
            'sisa_tagihan' => $this->formatCurrencyValue($remaining),
            'sisa_tagihan_angka' => number_format($remaining, 2, '.', ''),
            'metode_pembayaran' => $paymentMethod,
            'tanggal_pembayaran' => date('d M Y H:i', $paymentTimestamp),
            'kode_pembayaran' => $payment['kode_transaksi'] ?? '',
            'catatan_pembayaran' => $description,
            'nama_sekolah' => config('app.name'),
        ];

        $sent = WhatsappGatewayService::send([
            'phone' => $phone,
            'template' => $template !== '' ? $template : null,
            'variables' => $variables,
        ], $settings);

        if (!$sent) {
            $results['failed'][] = $variables['nama_siswa'] ?: 'Siswa';
        }

        return $results;
    }

    private function maybeFlashWhatsappResult(array $result, string $context): void
    {
        if (!empty($result['settings_missing'])) {
            Session::flash('warning', 'Notifikasi WhatsApp otomatis untuk ' . $context . ' belum dapat terkirim, pengaturan gateway belum lengkap.');
        }

        if (!empty($result['missing_phone'])) {
            Session::flash('warning', 'Nomor telepon siswa belum tersedia untuk ' . $context . ': ' . implode(', ', $result['missing_phone']));
        }

        if (!empty($result['failed'])) {
            Session::flash('warning', 'Gagal mengirim notifikasi WhatsApp untuk ' . $context . ' kepada: ' . implode(', ', $result['failed']) . '.');
        }
    }

    private function findBillingItemId(int $billingId, int $studentId): ?int
    {
        $connection = Database::connection();
        $statement = $connection->prepare(
            'SELECT id FROM tagihan_item WHERE tagihan_id = :billing AND siswa_id = :student LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':billing', $billingId, \PDO::PARAM_INT);
        $statement->bindValue(':student', $studentId, \PDO::PARAM_INT);
        $statement->execute();

        $result = $statement->fetchColumn();

        return $result === false ? null : (int) $result;
    }

    /**
     * @param array<int, array<string, mixed>> $purchases
     */
    private function summarizePurchases(array $purchases): array
    {
        $totalAmount = 0.0;
        $outstanding = 0.0;

        foreach ($purchases as $purchase) {
            $totalAmount += (float) ($purchase['nominal'] ?? 0.0);
            $outstanding += (float) ($purchase['sisa_nominal'] ?? 0.0);
        }

        return [
            'total' => count($purchases),
            'total_amount' => $totalAmount,
            'outstanding' => $outstanding,
        ];
    }

    private function normalizeAmount(string $raw): float
    {
        $cleaned = preg_replace('/[^0-9,\.]/', '', $raw);
        if ($cleaned === null) {
            return 0.0;
        }

        $normalized = str_replace(',', '.', $cleaned);

        return (float) $normalized;
    }

    private function refreshDashboardCache(?int $schoolYearId): void
    {
        $yearKey = $schoolYearId ?? 0;
        FinanceCache::forget('bendahara_dashboard_stats_' . $yearKey);
        FinanceCache::forget('bendahara_dashboard_stats_0');
    }

    private function isJsonRequest(Request $request): bool
    {
        $xhr = strtolower((string) $request->header('X-Requested-With', '') ?? '');

        if ($xhr === 'xmlhttprequest') {
            return true;
        }

        $accept = (string) ($request->header('Accept', '') ?? '');

        return $accept !== '' && str_contains($accept, 'application/json');
    }
}

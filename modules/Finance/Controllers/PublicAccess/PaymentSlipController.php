<?php

declare(strict_types=1);

namespace Modules\Finance\Controllers\PublicAccess;

use App\Services\Finance\PaymentDetailService;
use App\Services\Finance\PaymentSlipExporter;
use App\Support\FinancePaymentSlipToken;
use Core\Request;
use Core\Response;
use Modules\Finance\Controllers\Controller;

class PaymentSlipController extends Controller
{
    public function show(Request $request, int $paymentId, string $token): Response
    {
        $payment = $this->resolveAuthorizedPayment($paymentId, $token);

        if ($payment === null) {
            return $this->renderInvalid();
        }

        return $this->render('finance/bendahara/payments/slip', [
            'title' => 'Bukti Pembayaran',
            'payment' => $payment,
            'paperSize' => 'a4',
            'shareableUrl' => FinancePaymentSlipToken::buildPublicUrl($payment),
        ], 'print');
    }

    public function pdf(Request $request, int $paymentId, string $token): Response
    {
        $payment = $this->resolveAuthorizedPayment($paymentId, $token);

        if ($payment === null) {
            return Response::make('Slip pembayaran tidak ditemukan atau token tidak valid.', 404);
        }

        try {
            $pdfBinary = PaymentSlipExporter::generate($payment);
        } catch (\Throwable $exception) {
            return Response::make('Gagal membuat PDF bukti pembayaran.', 500);
        }

        $code = (string) ($payment['kode_transaksi'] ?? ('PAY-' . $paymentId));
        $safeName = str_replace(['"', "'"], '', $code !== '' ? $code : ('PAY-' . $paymentId));
        $filename = 'Slip-' . $safeName . '.pdf';

        return Response::make($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($pdfBinary),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveAuthorizedPayment(int $paymentId, string $token): ?array
    {
        if ($paymentId <= 0 || trim($token) === '') {
            return null;
        }

        $payment = PaymentDetailService::findById($paymentId);

        if ($payment === null) {
            return null;
        }

        if (!FinancePaymentSlipToken::isValid($token, $payment)) {
            return null;
        }

        return $payment;
    }

    private function renderInvalid(): Response
    {
        return $this->render('finance/public/payment-slip-invalid', [
            'title' => 'Bukti Pembayaran Tidak Ditemukan',
        ], 'app');
    }
}

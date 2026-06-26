<?php

declare(strict_types=1);

use Core\Router;
use Modules\Finance\Controllers\Bendahara\BillingController as BendaharaBillingController;
use Modules\Finance\Controllers\Bendahara\CategoryController as BendaharaCategoryController;
use Modules\Finance\Controllers\Bendahara\DashboardController as BendaharaDashboardController;
use Modules\Finance\Controllers\Bendahara\GeneralCashController as BendaharaGeneralCashController;
use Modules\Finance\Controllers\Bendahara\LoanController as BendaharaLoanController;
use Modules\Finance\Controllers\Bendahara\PaymentController as BendaharaPaymentController;
use Modules\Finance\Controllers\Bendahara\ReportController as BendaharaReportController;
use Modules\Finance\Controllers\Bendahara\TeacherAttendanceRecapController as BendaharaTeacherAttendanceRecapController;
use Modules\Finance\Controllers\Bendahara\SavingsController as BendaharaSavingsController;
use Modules\Finance\Controllers\Bendahara\StudentLedgerController as BendaharaStudentLedgerController;
use Modules\Finance\Controllers\Bendahara\TeacherSalaryController as BendaharaTeacherSalaryController;
use Modules\Finance\Controllers\Bendahara\UnexpectedExpenseController as BendaharaUnexpectedExpenseController;
use Modules\Finance\Controllers\Bendahara\ActivityFundController as BendaharaActivityFundController;
use Modules\Finance\Controllers\Bendahara\PracticeProcurementController as BendaharaPracticeProcurementController;
use Modules\Finance\Controllers\Bendahara\PurchaseController as BendaharaPurchaseController;
use Modules\Finance\Controllers\Guru\DashboardController as GuruDashboardController;
use Modules\Finance\Controllers\Guru\LoanController as GuruLoanController;
use Modules\Finance\Controllers\Guru\ActivityFundController as GuruActivityFundController;
use Modules\Finance\Controllers\Guru\AccountabilityController as GuruAccountabilityController;
use Modules\Finance\Controllers\Guru\SalaryController as GuruSalaryController;
use Modules\Finance\Controllers\Kaprodi\PracticeProcurementController as KaprodiPracticeProcurementController;
use Modules\Finance\Controllers\KepalaSekolah\ApprovalController as KepalaApprovalController;
use Modules\Finance\Controllers\KepalaSekolah\DashboardController as KepalaDashboardController;
use Modules\Finance\Controllers\KepalaSekolah\PracticeProcurementController as KepalaPracticeProcurementController;
use Modules\Finance\Controllers\KepalaSekolah\ReportController as KepalaReportController;
use Modules\Finance\Controllers\PublicAccess\PaymentSlipController as PublicPaymentSlipController;
use Modules\Finance\Controllers\Siswa\DashboardController as SiswaDashboardController;
use Modules\Finance\Controllers\Siswa\PaymentController as SiswaPaymentController;
use Modules\Finance\Controllers\Siswa\AccountabilityController as SiswaAccountabilityController;

/** @var Router $router */

// Public access
$router->get('/keuangan/pembayaran/slip/{id}/{token}', [PublicPaymentSlipController::class, 'show']);
$router->get('/keuangan/pembayaran/slip/{id}/{token}/pdf', [PublicPaymentSlipController::class, 'pdf']);
// Short public alias for slip links
$router->get('/p/s/{id}/{token}', [PublicPaymentSlipController::class, 'show']);
$router->get('/p/s/{id}/{token}/pdf', [PublicPaymentSlipController::class, 'pdf']);

// Bendahara
$router->get('/keuangan/bendahara', [BendaharaDashboardController::class, 'index']);
$router->get('/keuangan/bendahara/tagihan', [BendaharaBillingController::class, 'index']);
$router->post('/keuangan/bendahara/tagihan', [BendaharaBillingController::class, 'store']);
$router->post('/keuangan/bendahara/tagihan/generate-rutin', [BendaharaBillingController::class, 'generateRecurring']);
$router->get('/keuangan/bendahara/tagihan/{id}/pembayaran', [BendaharaBillingController::class, 'pay']);
$router->post('/keuangan/bendahara/tagihan/{id}/pembayaran', [BendaharaBillingController::class, 'payStore']);
$router->post('/keuangan/bendahara/tagihan/{id}/whatsapp-template', [BendaharaBillingController::class, 'updateWhatsappTemplate']);
$router->get('/keuangan/bendahara/pembelian', [BendaharaPurchaseController::class, 'index']);
$router->post('/keuangan/bendahara/pembelian', [BendaharaPurchaseController::class, 'store']);
$router->post('/keuangan/bendahara/pembelian/bayar', [BendaharaPurchaseController::class, 'pay']);
$router->get('/keuangan/bendahara/kategori', [BendaharaCategoryController::class, 'index']);
$router->post('/keuangan/bendahara/kategori', [BendaharaCategoryController::class, 'store']);
$router->post('/keuangan/bendahara/kategori/{id}', [BendaharaCategoryController::class, 'update']);
$router->post('/keuangan/bendahara/kategori/{id}/hapus', [BendaharaCategoryController::class, 'destroy']);
$router->get('/keuangan/bendahara/tabungan', [BendaharaSavingsController::class, 'index']);
$router->post('/keuangan/bendahara/tabungan', [BendaharaSavingsController::class, 'store']);
$router->get('/keuangan/bendahara/rekap-siswa', [BendaharaStudentLedgerController::class, 'index']);
$router->get('/keuangan/bendahara/pembayaran', [BendaharaPaymentController::class, 'index']);
$router->get('/keuangan/bendahara/pembayaran/{id}/slip', [BendaharaPaymentController::class, 'slip']);
$router->get('/keuangan/bendahara/pembayaran/{id}/slip/pdf', [BendaharaPaymentController::class, 'slipPdf']);
$router->get('/keuangan/bendahara/pembayaran/{id}/lampiran', [BendaharaPaymentController::class, 'attachment']);
$router->post('/keuangan/bendahara/pembayaran/{id}/approve', [BendaharaPaymentController::class, 'approve']);
$router->post('/keuangan/bendahara/pembayaran/{id}/reject', [BendaharaPaymentController::class, 'reject']);
$router->get('/keuangan/bendahara/kas-utama', [BendaharaGeneralCashController::class, 'index']);
$router->post('/keuangan/bendahara/kas-utama/eksternal', [BendaharaGeneralCashController::class, 'storeExternal']);
$router->post('/keuangan/bendahara/kas-utama/transfer-masuk', [BendaharaGeneralCashController::class, 'transferFromBilling']);
$router->post('/keuangan/bendahara/kas-utama/transfer-keluar', [BendaharaGeneralCashController::class, 'transferToBilling']);
$router->post('/keuangan/bendahara/kas-utama/pembelian/transfer-masuk', [BendaharaGeneralCashController::class, 'transferFromPurchase']);
$router->post('/keuangan/bendahara/kas-utama/pembelian/transfer-keluar', [BendaharaGeneralCashController::class, 'transferToPurchase']);
$router->post('/keuangan/bendahara/kas-utama/tabungan/pinjam', [BendaharaGeneralCashController::class, 'borrowFromSavings']);
$router->post('/keuangan/bendahara/kas-utama/tabungan/kembalikan', [BendaharaGeneralCashController::class, 'returnToSavings']);
$router->get('/keuangan/bendahara/presensi-guru', [BendaharaTeacherAttendanceRecapController::class, 'index']);
$router->get('/keuangan/bendahara/pengeluaran-tak-terduga', [BendaharaUnexpectedExpenseController::class, 'index']);
$router->post('/keuangan/bendahara/pengeluaran-tak-terduga', [BendaharaUnexpectedExpenseController::class, 'store']);
$router->get('/keuangan/bendahara/pengadaan', [BendaharaPracticeProcurementController::class, 'index']);
$router->post('/keuangan/bendahara/pengadaan/{id}/fund', [BendaharaPracticeProcurementController::class, 'fund']);
$router->get('/keuangan/bendahara/kasbon', [BendaharaLoanController::class, 'index']);
$router->post('/keuangan/bendahara/kasbon/{id}/cairkan', [BendaharaLoanController::class, 'disburse']);
$router->get('/keuangan/bendahara/gaji-guru', [BendaharaTeacherSalaryController::class, 'index']);
$router->post('/keuangan/bendahara/gaji-guru/pengaturan', [BendaharaTeacherSalaryController::class, 'storeSettings']);
$router->post('/keuangan/bendahara/gaji-guru/penggajian', [BendaharaTeacherSalaryController::class, 'saveRecord']);
$router->post('/keuangan/bendahara/gaji-guru/penggajian/{id}/status', [BendaharaTeacherSalaryController::class, 'updateStatus']);
$router->get('/keuangan/bendahara/gaji-guru/penggajian/slip/{id}', [BendaharaTeacherSalaryController::class, 'slip']);
$router->get('/keuangan/bendahara/gaji-guru/penggajian/slip/{id}/pdf', [BendaharaTeacherSalaryController::class, 'slipPdf']);
$router->get('/keuangan/bendahara/laporan', [BendaharaReportController::class, 'index']);
$router->get('/keuangan/bendahara/dana-kegiatan', [BendaharaActivityFundController::class, 'index']);
$router->get('/keuangan/bendahara/dana-kegiatan/lpj', [BendaharaActivityFundController::class, 'lpj']);
$router->post('/keuangan/bendahara/dana-kegiatan/{id}/verifikasi', [BendaharaActivityFundController::class, 'verify']);
$router->post('/keuangan/bendahara/dana-kegiatan/{id}/tolak', [BendaharaActivityFundController::class, 'reject']);
$router->post('/keuangan/bendahara/dana-kegiatan/{id}/cairkan', [BendaharaActivityFundController::class, 'disburse']);

// Siswa
$router->get('/keuangan/siswa', [SiswaDashboardController::class, 'index']);
$router->post('/keuangan/siswa/tagihan/{id}/bayar-tabungan', [SiswaPaymentController::class, 'payFromSavings']);
$router->get('/keuangan/siswa/pengeluaran-tak-terduga/{id}/lpj', [SiswaAccountabilityController::class, 'unexpected']);
$router->post('/keuangan/siswa/pengeluaran-tak-terduga/{id}/lpj', [SiswaAccountabilityController::class, 'storeUnexpected']);

// Guru
$router->get('/keuangan/guru', [GuruDashboardController::class, 'index']);
$router->get('/keuangan/guru/kasbon', [GuruLoanController::class, 'index']);
$router->post('/keuangan/guru/kasbon', [GuruLoanController::class, 'store']);
$router->get('/keuangan/guru/dana-kegiatan', [GuruActivityFundController::class, 'index']);
$router->post('/keuangan/guru/dana-kegiatan', [GuruActivityFundController::class, 'store']);
$router->get('/keuangan/guru/dana-kegiatan/{id}/lpj', [GuruAccountabilityController::class, 'activity']);
$router->post('/keuangan/guru/dana-kegiatan/{id}/lpj', [GuruAccountabilityController::class, 'storeActivity']);
$router->get('/keuangan/guru/pengeluaran-tak-terduga/{id}/lpj', [GuruAccountabilityController::class, 'unexpected']);
$router->post('/keuangan/guru/pengeluaran-tak-terduga/{id}/lpj', [GuruAccountabilityController::class, 'storeUnexpected']);
$router->get('/keuangan/guru/gaji/slip/{id}/pdf', [GuruSalaryController::class, 'slipPdf']);
$router->get('/keuangan/kaprodi/pengadaan', [KaprodiPracticeProcurementController::class, 'index']);
$router->post('/keuangan/kaprodi/pengadaan', [KaprodiPracticeProcurementController::class, 'store']);
$router->post('/keuangan/kaprodi/pengadaan/{id}/ajukan', [KaprodiPracticeProcurementController::class, 'submit']);
$router->post('/keuangan/kaprodi/pengadaan/{id}/lpj', [KaprodiPracticeProcurementController::class, 'report']);

// Kepala Sekolah
$router->get('/keuangan/kepala-sekolah', [KepalaDashboardController::class, 'index']);
$router->get('/keuangan/kepala-sekolah/approval', [KepalaApprovalController::class, 'index']);
$router->post('/keuangan/kepala-sekolah/approval/{id}/approve', [KepalaApprovalController::class, 'approve']);
$router->post('/keuangan/kepala-sekolah/approval/{id}/reject', [KepalaApprovalController::class, 'reject']);
$router->get('/keuangan/kepala-sekolah/laporan', [KepalaReportController::class, 'index']);
$router->get('/keuangan/kepala-sekolah/pengadaan', [KepalaPracticeProcurementController::class, 'index']);
$router->post('/keuangan/kepala-sekolah/pengadaan/{id}/approve', [KepalaPracticeProcurementController::class, 'approve']);
$router->post('/keuangan/kepala-sekolah/pengadaan/{id}/reject', [KepalaPracticeProcurementController::class, 'reject']);

<?php

declare(strict_types=1);

use Core\Router;
use Modules\Ppdb\Controllers\Admin\MigrationController;
use Modules\Ppdb\Controllers\Admin\PeriodController;
use Modules\Ppdb\Controllers\Admin\BroadcastController as AdminBroadcastController;
use Modules\Ppdb\Controllers\Admin\RegistrantController as AdminRegistrantController;
use Modules\Ppdb\Controllers\Admin\ReportController;
use Modules\Ppdb\Controllers\Frontend\RegistrationController;
use Modules\Ppdb\Controllers\Guru\BroadcastController as GuruBroadcastController;
use Modules\Ppdb\Controllers\Guru\DashboardController as GuruDashboardController;
use Modules\Ppdb\Controllers\Guru\RegistrantController as GuruRegistrantController;

/** @var Router $router */

$router->get('/ppdb/admin/periode', [PeriodController::class, 'index']);
$router->post('/ppdb/admin/periode', [PeriodController::class, 'store']);
$router->post('/ppdb/admin/periode/{id}/update', [PeriodController::class, 'update']);
$router->post('/ppdb/admin/periode/{id}/delete', [PeriodController::class, 'destroy']);
$router->get('/ppdb/admin/migrasi', [MigrationController::class, 'index']);
$router->post('/ppdb/admin/migrasi', [MigrationController::class, 'store']);
$router->get('/ppdb/admin/laporan', [ReportController::class, 'index']);
$router->get('/ppdb/admin/broadcast', [AdminBroadcastController::class, 'index']);
$router->post('/ppdb/admin/broadcast', [AdminBroadcastController::class, 'store']);
$router->get('/ppdb/admin/pendaftar', [AdminRegistrantController::class, 'index']);
$router->post('/ppdb/admin/pendaftar', [AdminRegistrantController::class, 'store']);
$router->post('/ppdb/admin/pendaftar/{id}/hapus', [AdminRegistrantController::class, 'destroy']);
$router->post('/ppdb/admin/pendaftar/{id}/seleksi', [AdminRegistrantController::class, 'updateSelection']);
$router->post('/ppdb/admin/pendaftar/{id}/pengumuman', [AdminRegistrantController::class, 'updateAnnouncement']);
$router->post('/ppdb/admin/pendaftar/{id}/daftar-ulang', [AdminRegistrantController::class, 'updateReRegistration']);
$router->post('/ppdb/admin/pendaftar/{id}/pembayaran', [AdminRegistrantController::class, 'updatePayment']);
$router->get('/ppdb/guru', [GuruDashboardController::class, 'index']);
$router->get('/ppdb/guru/broadcast', [GuruBroadcastController::class, 'index']);
$router->post('/ppdb/guru/broadcast', [GuruBroadcastController::class, 'store']);
$router->get('/ppdb/guru/pendaftar', [GuruRegistrantController::class, 'index']);
$router->get('/ppdb/guru/pendaftar/tambah', [GuruRegistrantController::class, 'create']);
$router->post('/ppdb/guru/pendaftar', [GuruRegistrantController::class, 'store']);
$router->post('/ppdb/guru/pendaftar/{id}/hapus', [GuruRegistrantController::class, 'destroy']);
$router->post('/ppdb/guru/pendaftar/{id}/seleksi', [GuruRegistrantController::class, 'updateSelection']);
$router->post('/ppdb/guru/pendaftar/{id}/pengumuman', [GuruRegistrantController::class, 'updateAnnouncement']);
$router->post('/ppdb/guru/pendaftar/{id}/daftar-ulang', [GuruRegistrantController::class, 'updateReRegistration']);
$router->post('/ppdb/guru/pendaftar/{id}/pembayaran', [GuruRegistrantController::class, 'updatePayment']);
$router->get('/ppdb/pendaftaran/{token}', [RegistrationController::class, 'show']);
$router->post('/ppdb/pendaftaran/{token}', [RegistrationController::class, 'submit']);

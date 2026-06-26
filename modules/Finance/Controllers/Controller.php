<?php

namespace Modules\Finance\Controllers;

use App\Services\Finance\GeneralCashService;
use App\Support\AcademicRoleGate;
use App\Support\FinanceGate;
use App\Support\SchoolYearContext;
use Core\Controller as BaseController;
use Core\Request;
use Core\Response;
use Core\Session;

abstract class Controller extends BaseController
{
    protected ?string $layout = 'admin';

    protected function guardAuthenticated(): ?Response
    {
        return $this->ensureAuthenticated();
    }

    /**
     * Pastikan pengguna adalah bendahara aktif.
     */
    protected function guardBendahara(string $redirect = 'dashboard'): ?Response
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        if (!FinanceGate::isBendahara($this->user())) {
            Session::flash('error', 'Modul ini hanya dapat diakses oleh bendahara aktif.');

            return $this->redirect($redirect);
        }

        return null;
    }

    protected function guardRole(string $role, string $redirect = 'dashboard'): ?Response
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        if (!FinanceGate::isRole($this->user(), $role)) {
            Session::flash('error', 'Anda tidak memiliki akses ke modul ini.');

            return $this->redirect($redirect);
        }

        return null;
    }

    protected function guardHeadmaster(string $redirect = 'dashboard'): ?Response
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        if (!FinanceGate::isHeadmaster($this->user())) {
            Session::flash('error', 'Modul ini hanya dapat diakses oleh kepala sekolah aktif.');

            return $this->redirect($redirect);
        }

        return null;
    }

    protected function guardWakaKurikulum(string $redirect = 'dashboard'): ?Response
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        if (!AcademicRoleGate::isWakaKurikulum($this->user())) {
            Session::flash('error', 'Akses khusus Waka Kurikulum.');

            return $this->redirect($redirect);
        }

        return null;
    }

    protected function guardKaprodi(string $redirect = 'dashboard'): ?Response
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        if (!AcademicRoleGate::isKepalaProdi(null, $this->user())) {
            Session::flash('error', 'Modul ini khusus kepala program studi.');

            return $this->redirect($redirect);
        }

        return null;
    }

    protected function guardCsrfOrRedirect(Request $request, string $redirectUrl): ?Response
    {
        if ($response = $this->guardCsrf($request, $redirectUrl)) {
            return $response;
        }

        return null;
    }

    protected function flashGeneralCashDeficitWarning(int $schoolYearId): void
    {
        if ($schoolYearId <= 0) {
            return;
        }

        if (GeneralCashService::balance($schoolYearId) < 0) {
            Session::flash('warning', 'Saldo kas utama berada pada posisi minus. Mohon tindak lanjuti untuk menutup defisit.');
        }
    }

    protected function formatCurrency(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function user(): ?array
    {
        return auth();
    }

    protected function activeSchoolYearId(): ?int
    {
        return SchoolYearContext::id();
    }
}

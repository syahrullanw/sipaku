<?php

namespace App\Controllers;

use App\Support\MaintenanceMode;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class MaintenanceModeController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        return $this->render('admin/settings/maintenance-mode', [
            'title' => 'Maintenance Mode',
            'pageTitle' => 'Maintenance Mode',
            'activeMenu' => 'maintenance-mode',
            'isEnabled' => MaintenanceMode::isEnabled(),
        ], 'admin');
    }

    public function toggle(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/maintenance-mode')) {
            return $response;
        }

        $enable = (string) $request->input('action', 'enable') !== 'disable';

        try {
            MaintenanceMode::setEnabled($enable);
            Session::flash(
                'success',
                $enable
                    ? 'Maintenance mode diaktifkan. Hanya admin yang dapat mengakses aplikasi.'
                    : 'Maintenance mode dinonaktifkan. Aplikasi kembali dapat diakses pengguna.'
            );
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal mengubah maintenance mode: ' . $exception->getMessage());
        }

        return $this->redirect('admin/maintenance-mode');
    }
}

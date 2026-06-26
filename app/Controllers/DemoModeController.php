<?php

namespace App\Controllers;

use App\Support\DemoMode;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class DemoModeController extends Controller
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

        return $this->render('admin/settings/demo-mode', [
            'title' => 'Mode Demo',
            'pageTitle' => 'Mode Demo',
            'activeMenu' => 'demo-mode',
            'isEnabled' => DemoMode::isEnabled(),
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

        if ($response = $this->guardCsrf($request, 'admin/demo-mode')) {
            return $response;
        }

        $target = (string) $request->input('action', 'enable');
        $password = (string) $request->input('password', '');

        if (!DemoMode::validatePassword($password)) {
            Session::flash('error', 'Password mode demo tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('admin/demo-mode');
        }

        $enable = $target !== 'disable';

        try {
            DemoMode::setEnabled($enable);
            $message = $enable
                ? 'Mode demo diaktifkan. Data sensitif akan disembunyikan.'
                : 'Mode demo dimatikan. Data sensitif akan kembali ditampilkan.';
            Session::flash('success', $message);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal mengubah mode demo: ' . $exception->getMessage());
        }

        return $this->redirect('admin/demo-mode');
    }
}

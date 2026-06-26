<?php

namespace App\Controllers;

use App\Support\LoginSessionSetting;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class LoginSessionSettingController extends Controller
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

        return $this->render('admin/settings/login-session', [
            'title' => 'Pengaturan Sesi Login',
            'pageTitle' => 'Pengaturan Sesi Login',
            'activeMenu' => 'session-timeout',
            'timeoutMinutes' => LoginSessionSetting::getMinutes(),
            'timeoutBounds' => [
                'min' => LoginSessionSetting::minMinutes(),
                'max' => LoginSessionSetting::maxMinutes(),
                'default' => LoginSessionSetting::defaultMinutes(),
            ],
        ], 'admin');
    }

    public function update(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/pengaturan/sesi-login')) {
            return $response;
        }

        $rawMinutes = (int) $request->input('timeout_minutes', 0);

        if ($rawMinutes <= 0) {
            Session::flash('error', 'Masukkan jumlah menit sesi yang valid.');
            Session::flashInput($request->all());

            return $this->redirect('admin/pengaturan/sesi-login');
        }

        try {
            $saved = LoginSessionSetting::saveMinutes($rawMinutes);
            $message = 'Durasi sesi login disimpan ke ' . number_format($saved) . ' menit.';
            if ($saved !== $rawMinutes) {
                $message .= ' Nilai disesuaikan ke rentang ' . LoginSessionSetting::minMinutes() . '–' . LoginSessionSetting::maxMinutes() . ' menit.';
            }

            Session::flash('success', $message);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan pengaturan sesi login: ' . $exception->getMessage());
            Session::flashInput($request->all());
        }

        return $this->redirect('admin/pengaturan/sesi-login');
    }
}

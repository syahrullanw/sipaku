<?php

namespace App\Controllers;

use App\Models\User;
use Core\Csrf;
use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class PasswordController extends Controller
{
    protected ?string $layout = 'admin';

    public function edit(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        return $this->render('admin/profile/password', [
            'title' => 'Ganti Password',
            'pageTitle' => 'Ganti Password',
            'activeMenu' => 'password',
        ], 'admin');
    }

    public function update(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'profile/password')) {
            return $response;
        }

        $currentUser = auth();

        if ($currentUser === null) {
            Session::flash('error', 'Sesi tidak valid.');

            return $this->redirect('login');
        }

        $oldPassword = (string) $request->input('old_password', '');
        $newPassword = (string) $request->input('password', '');
        $confirmPassword = (string) $request->input('password_confirmation', '');

        if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
            Session::flash('error', 'Semua kolom password wajib diisi.');

            return $this->redirect('profile/password');
        }

        if (strlen($newPassword) < 8) {
            Session::flash('error', 'Password baru minimal 8 karakter.');

            return $this->redirect('profile/password');
        }

        if ($newPassword !== $confirmPassword) {
            Session::flash('error', 'Konfirmasi password baru tidak cocok.');

            return $this->redirect('profile/password');
        }

        $freshUser = User::find((int) $currentUser['id']);

        if ($freshUser === null || !password_verify($oldPassword, (string) ($freshUser['password'] ?? ''))) {
            Session::flash('error', 'Password lama tidak sesuai.');

            return $this->redirect('profile/password');
        }

        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);

        try {
            User::updateById((int) $freshUser['id'], [
                'password' => $hashed,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            Session::flash('success', 'Password berhasil diperbarui.');
            Csrf::regenerate();
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui password: ' . $exception->getMessage());
        }

        return $this->redirect('profile/password');
    }
}

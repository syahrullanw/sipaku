<?php

namespace App\Controllers;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\PpdbPeriod;
use App\Services\ProfileCompletionReminder;
use App\Support\MaintenanceMode;
use App\Support\SchoolYearContext;
use Core\Auth;
use Core\Controller;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\Session;

class AuthController extends Controller
{
    protected ?string $layout = 'auth';

    public function showLoginForm(Request $request): Response
    {
        if (MaintenanceMode::isEnabled()) {
            $currentUser = auth();
            if (is_array($currentUser) && ($currentUser['role'] ?? '') !== 'admin') {
                MaintenanceMode::rejectNonAdminLogin();
            }
        }

        if ($response = $this->ensureGuest()) {
            return $response;
        }

        $ppdbRegistrationLink = null;
        $activePpdbPeriod = PpdbPeriod::active();

        if (
            is_array($activePpdbPeriod)
            && PpdbPeriod::isStageEnabled($activePpdbPeriod, 'pendaftaran')
        ) {
            $identifier = trim((string) ($activePpdbPeriod['kode'] ?? ''));

            if ($identifier === '') {
                $identifier = trim((string) ($activePpdbPeriod['token_pendaftaran'] ?? ''));
            }

            if ($identifier !== '') {
                $ppdbRegistrationLink = base_url('ppdb/pendaftaran/' . $identifier);
            }
        }

        return $this->render('auth/login', [
            'title' => 'Masuk ' . config('app.name', 'Aplikasi Sekolah'),
            'error' => session_flash('error'),
            'success' => session_flash('success'),
            'ppdbRegistrationLink' => $ppdbRegistrationLink,
        ]);
    }

    public function login(Request $request): Response
    {
        if ($response = $this->ensureGuest()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'login')) {
            return $response;
        }

        $identifier = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');

        if ($identifier === '' || $password === '') {
            Session::flash('error', 'Isi kredensial (username/email/NIP/NIS) dan password.');
            Session::flashInput($request->all());

            return $this->redirect('login');
        }

        if (!Auth::attempt($identifier, $password)) {
            Session::flash('error', Auth::lastFailureReason() ?? 'Kredensial atau password tidak sesuai.');
            Session::flashInput($request->all());

            return $this->redirect('login');
        }

        $authenticatedUser = auth();
        if (
            MaintenanceMode::isEnabled()
            && (!is_array($authenticatedUser) || ($authenticatedUser['role'] ?? '') !== 'admin')
        ) {
            MaintenanceMode::rejectNonAdminLogin();
            Session::flashInput(['username' => $identifier]);

            return $this->redirect('login');
        }

        Session::flash('success', 'Selamat datang kembali!');
        Csrf::regenerate();

        $missingFields = [];
        $profileRole = '';

        if (is_array($authenticatedUser)) {
            $profileRole = (string) ($authenticatedUser['role'] ?? '');

            if ($profileRole === 'guru' && !empty($authenticatedUser['teacher_id'])) {
                $teacher = Teacher::find((int) $authenticatedUser['teacher_id']);

                if ($teacher !== null) {
                    $missingFields = ProfileCompletionReminder::missingTeacherFields($teacher);
                }
            } elseif ($profileRole === 'siswa' && !empty($authenticatedUser['student_id'])) {
                $student = Student::find((int) $authenticatedUser['student_id']);

                if ($student !== null) {
                    $missingFields = ProfileCompletionReminder::missingStudentFields($student);
                }
            }
        }

        if (!empty($missingFields)) {
            Session::set('profile_completion_prompt_pending', true);
            Session::set('profile_completion_missing_fields', $missingFields);
            Session::set('profile_completion_prompt_role', $profileRole);
        } else {
            Session::forget('profile_completion_prompt_pending');
            Session::forget('profile_completion_missing_fields');
            Session::forget('profile_completion_prompt_role');
        }

        $intended = Session::get('intended_url');

        if (is_string($intended) && $intended !== '') {
            Session::forget('intended_url');

            return $this->redirect($intended);
        }

        return $this->redirect('dashboard');
    }

    public function logout(Request $request): Response
    {
        if ($response = $this->guardCsrf($request, 'login')) {
            return $response;
        }

        Auth::logout();
        SchoolYearContext::clear();
        Csrf::regenerate();
        Session::flash('success', 'Anda telah keluar.');

        return $this->redirect('login');
    }
}

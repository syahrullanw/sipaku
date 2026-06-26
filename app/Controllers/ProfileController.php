<?php

namespace App\Controllers;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\TeacherProfileForm;
use App\Support\DemoMode;
use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class ProfileController extends Controller
{
    protected ?string $layout = 'admin';

    public function edit(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $currentUser = auth();

        if (!is_array($currentUser)) {
            return $this->redirect('login');
        }

        $demoModeEnabled = DemoMode::isEnabled();
        $role = (string) ($currentUser['role'] ?? 'admin');
        $viewData = [
            'title' => 'Profil Saya',
            'pageTitle' => 'Profil Saya',
            'activeMenu' => 'profile',
            'role' => $role,
            'demoModeEnabled' => $demoModeEnabled,
        ];

        if ($role === 'guru' && !empty($currentUser['teacher_id'])) {
            $teacherId = (int) $currentUser['teacher_id'];
            $teacher = Teacher::find($teacherId);

            if ($teacher === null) {
                Session::flash('error', 'Data guru tidak ditemukan.');

                return $this->redirect('dashboard');
            }

            $options = TeacherProfileForm::options();
            $viewTeacher = $demoModeEnabled ? DemoMode::maskTeacher($teacher) : $teacher;

            $viewData = array_merge($viewData, [
                'editingTeacher' => $viewTeacher,
                'genderOptions' => $options['genders'],
                'religionOptions' => $options['religions'],
                'maritalStatusOptions' => $options['maritalStatuses'],
                'gtkTypeOptions' => $options['gtkTypes'],
                'employmentStatusOptions' => $options['employmentStatuses'],
                'educationOptions' => $options['educationLevels'],
                'studyStatusOptions' => $options['studyStatuses'],
                'schoolIndukValue' => $teacher['sekolah_induk'] ?? '',
                'selectedGender' => $teacher['jenis_kelamin'] ?? '',
                'selectedReligion' => $teacher['agama'] ?? '',
                'selectedMaritalStatus' => $teacher['status_perkawinan'] ?? '',
                'selectedGtkType' => $teacher['jenis_gtk'] ?? '',
                'selectedEmploymentStatus' => $teacher['status_kepegawaian'] ?? '',
                'selectedEducation' => $teacher['pendidikan_terakhir'] ?? '',
                'selectedStudyStatus' => $teacher['status_kuliah'] ?? '',
            ]);
        } elseif ($role === 'siswa' && !empty($currentUser['student_id'])) {
            $studentId = (int) $currentUser['student_id'];
            $student = Student::find($studentId);

            if ($student === null) {
                Session::flash('error', 'Data siswa tidak ditemukan.');

                return $this->redirect('dashboard');
            }

            if ($demoModeEnabled) {
                $student['email'] = DemoMode::maskEmail($student['email'] ?? null);
                $student['telepon'] = DemoMode::maskPhone($student['telepon'] ?? null);
                $student['hp'] = DemoMode::maskPhone($student['hp'] ?? null);
                $student['alamat'] = DemoMode::maskAddress($student['alamat'] ?? null);
                $student['dusun'] = DemoMode::maskAddress($student['dusun'] ?? null);
                $student['kelurahan'] = DemoMode::maskAddress($student['kelurahan'] ?? null);
                $student['kecamatan'] = DemoMode::maskAddress($student['kecamatan'] ?? null);
                $student['kode_pos'] = DemoMode::maskIdentifier($student['kode_pos'] ?? null);
            }

            $viewData['student'] = $student;
        } else {
            if ($demoModeEnabled) {
                $currentUser['email'] = DemoMode::maskEmail($currentUser['email'] ?? null);
            }
            $viewData['profileUser'] = $currentUser;
        }

        return $this->render('admin/profile/edit', $viewData, 'admin');
    }

    public function update(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if (DemoMode::isEnabled()) {
            Session::flash('warning', 'Mode demo aktif. Perubahan profil dinonaktifkan.');

            return $this->redirect('profile');
        }

        if ($response = $this->guardCsrf($request, 'profile')) {
            return $response;
        }

        $currentUser = auth();

        if (!is_array($currentUser)) {
            return $this->redirect('login');
        }

        $role = (string) ($currentUser['role'] ?? 'admin');
        $redirect = $this->redirect('profile');
        $now = date('Y-m-d H:i:s');

        if ($role === 'guru' && !empty($currentUser['teacher_id'])) {
            $teacherId = (int) $currentUser['teacher_id'];
            $payload = TeacherProfileForm::validate($request, $teacherId);

            if ($payload === null) {
                return $redirect;
            }

            $payload['updated_at'] = $now;

            $emailCandidate = $payload['email'] ?? '';
            if ($emailCandidate !== null && $emailCandidate !== '' && User::exists(['email' => $emailCandidate], (int) $currentUser['id'])) {
                Session::flash('error', 'Email sudah digunakan oleh pengguna lain.');
                Session::flashInput($request->all());

                return $redirect;
            }

            try {
                Teacher::updateById($teacherId, $payload);
                $this->syncUserAccount((int) $currentUser['id'], $payload['nama'] ?? null, $payload['email'] ?? null);
                Session::flash('success', 'Profil guru berhasil diperbarui.');
            } catch (\Throwable $exception) {
                Session::flash('error', 'Gagal memperbarui profil guru: ' . $exception->getMessage());
            }

            $this->refreshAuthenticatedUser((int) $currentUser['id']);

            return $redirect;
        }

        if ($role === 'siswa' && !empty($currentUser['student_id'])) {
            $studentId = (int) $currentUser['student_id'];
            $student = Student::find($studentId);

            if ($student === null) {
                Session::flash('error', 'Data siswa tidak ditemukan.');

                return $redirect;
            }

            $fields = [
                'email' => trim((string) $request->input('email', '')),
                'telepon' => trim((string) $request->input('telepon', '')),
                'hp' => trim((string) $request->input('hp', '')),
                'alamat' => trim((string) $request->input('alamat', '')),
                'dusun' => trim((string) $request->input('dusun', '')),
                'kelurahan' => trim((string) $request->input('kelurahan', '')),
                'kecamatan' => trim((string) $request->input('kecamatan', '')),
                'kode_pos' => trim((string) $request->input('kode_pos', '')),
                'jenis_tinggal' => trim((string) $request->input('jenis_tinggal', '')),
                'alat_transportasi' => trim((string) $request->input('alat_transportasi', '')),
            ];

            if ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
                Session::flash('error', 'Format email tidak valid.');
                Session::flashInput($request->all());

                return $redirect;
            }

            if ($fields['email'] !== '' && User::exists(['email' => $fields['email']], (int) $currentUser['id'])) {
                Session::flash('error', 'Email sudah digunakan oleh pengguna lain.');
                Session::flashInput($request->all());

                return $redirect;
            }

            foreach (['telepon', 'hp', 'kode_pos'] as $key) {
                if ($fields[$key] === '') {
                    $fields[$key] = null;
                }
            }

            foreach (['alamat', 'dusun', 'kelurahan', 'kecamatan', 'jenis_tinggal', 'alat_transportasi'] as $key) {
                if ($fields[$key] === '') {
                    $fields[$key] = null;
                }
            }

            $fields['updated_at'] = $now;

            try {
                Student::updateById($studentId, $fields);
                $this->syncUserAccount((int) $currentUser['id'], null, $fields['email']);
                Session::flash('success', 'Profil siswa berhasil diperbarui.');
            } catch (\Throwable $exception) {
                Session::flash('error', 'Gagal memperbarui profil siswa: ' . $exception->getMessage());
            }

            $this->refreshAuthenticatedUser((int) $currentUser['id']);

            return $redirect;
        }

        $name = trim((string) $request->input('name', ''));
        $email = trim((string) $request->input('email', ''));

        if ($name === '') {
            Session::flash('error', 'Nama tidak boleh kosong.');
            Session::flashInput($request->all());

            return $redirect;
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Format email tidak valid.');
            Session::flashInput($request->all());

            return $redirect;
        }

        if ($email !== '' && User::exists(['email' => $email], (int) $currentUser['id'])) {
            Session::flash('error', 'Email sudah digunakan oleh pengguna lain.');
            Session::flashInput($request->all());

            return $redirect;
        }

        $updatePayload = [
            'name' => $name,
            'updated_at' => $now,
        ];

        $updatePayload['email'] = $email !== '' ? $email : null;

        try {
            User::updateById((int) $currentUser['id'], $updatePayload);
            Session::flash('success', 'Profil berhasil diperbarui.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui profil: ' . $exception->getMessage());
        }

        $this->refreshAuthenticatedUser((int) $currentUser['id']);

        return $redirect;
    }

    private function syncUserAccount(int $userId, ?string $name, ?string $email): void
    {
        $updates = ['updated_at' => date('Y-m-d H:i:s')];

        if ($name !== null && $name !== '') {
            $updates['name'] = $name;
        }

        if ($email === null || $email === '') {
            $updates['email'] = null;
        } elseif (!User::exists(['email' => $email], $userId)) {
            $updates['email'] = $email;
        }

        if (count($updates) > 1) {
            User::updateById($userId, $updates);
        }
    }

    private function refreshAuthenticatedUser(int $userId): void
    {
        $freshUser = User::find($userId);

        if ($freshUser !== null) {
            Auth::setUser($freshUser);
        }
    }
}

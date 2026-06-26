<?php

namespace App\Controllers;

use App\Models\Teacher;
use App\Models\User;
use App\Models\WhatsappGatewaySetting;
use App\Services\WhatsappGatewayService;
use App\Support\UserPasswordMessageSetting;
use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class UserController extends Controller
{
    protected ?string $layout = 'admin';

    /**
     * @var array<int, string>
     */
    private array $roles = ['admin', 'staff', 'guru'];

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $keyword = trim((string) $request->query('q', ''));
        $users = User::search(['keyword' => $keyword]);
        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? User::find($editId) : null;

        if ($editing !== null && ($editing['role'] ?? '') === 'guru') {
            Session::flash('error', 'Akun guru dikelola otomatis. Gunakan tombol reset password.');
            return $this->redirect('admin/pengguna');
        }

        return $this->render('admin/users/index', [
            'title' => 'Pengguna',
            'pageTitle' => 'Manajemen Pengguna',
            'activeMenu' => 'users',
            'users' => $users,
            'editingUser' => $editing,
            'roles' => $this->roles,
            'keyword' => $keyword,
            'whatsappTemplates' => UserPasswordMessageSetting::get(),
            'whatsappPlaceholders' => UserPasswordMessageSetting::placeholders(),
            'whatsappGatewayConfigured' => WhatsappGatewaySetting::first() !== null,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/pengguna')) {
            return $response;
        }

        $payload = $this->validate($request);

        if ($payload === null) {
            return $this->redirect('admin/pengguna');
        }

        $payload['email'] = $payload['email'] !== '' ? $payload['email'] : null;
        $payload['password'] = password_hash($payload['password'], PASSWORD_BCRYPT);
        unset($payload['password_confirmation']);

        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            User::create($payload);
            Session::flash('success', 'Pengguna baru berhasil ditambahkan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menambahkan pengguna: ' . $exception->getMessage());
        }

        return $this->redirect('admin/pengguna');
    }

    public function updateWhatsappTemplate(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/pengguna')) {
            return $response;
        }

        $defaultTemplate = trim((string) $request->input('default_password_template', ''));
        $resetTemplate = trim((string) $request->input('reset_password_template', ''));

        if ($defaultTemplate === '' || $resetTemplate === '') {
            Session::flash('error', 'Template WhatsApp tidak boleh kosong.');
            Session::flashInput($request->all());

            return $this->redirect('admin/pengguna');
        }

        try {
            UserPasswordMessageSetting::save($defaultTemplate, $resetTemplate);
            Session::flash('success', 'Template WhatsApp password pengguna berhasil disimpan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan template: ' . $exception->getMessage());
        }

        return $this->redirect('admin/pengguna');
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/pengguna')) {
            return $response;
        }

        $targetUser = User::find($id);
        if ($targetUser === null) {
            Session::flash('error', 'Data pengguna tidak ditemukan.');
            return $this->redirect('admin/pengguna');
        }

        if (($targetUser['role'] ?? '') === 'guru') {
            Session::flash('error', 'Akun guru tidak dapat diedit manual. Gunakan tombol reset password.');
            return $this->redirect('admin/pengguna');
        }

        $payload = $this->validate($request, false, $id);

        if ($payload === null) {
            return $this->redirect('admin/pengguna?edit=' . $id);
        }

        $updates = [
            'name' => $payload['name'],
            'username' => $payload['username'],
            'role' => $payload['role'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $updates['email'] = $payload['email'] !== '' ? $payload['email'] : null;

        if (!empty($payload['password'])) {
            $updates['password'] = password_hash($payload['password'], PASSWORD_BCRYPT);
        }

        try {
            User::updateById($id, $updates);
            Session::flash('success', 'Pengguna berhasil diperbarui.');

            $current = auth();
            if ($current !== null && (int) $current['id'] === $id) {
                Auth::setUser(array_merge($current, [
                    'name' => $updates['name'],
                    'username' => $updates['username'],
                    'email' => $updates['email'],
                    'role' => $updates['role'],
                ]));
            }
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui pengguna: ' . $exception->getMessage());
        }

        return $this->redirect('admin/pengguna');
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/pengguna')) {
            return $response;
        }

        $currentUser = auth();
        $targetUser = User::find($id);

        if ($targetUser === null) {
            Session::flash('error', 'Data pengguna tidak ditemukan.');

            return $this->redirect('admin/pengguna');
        }

        if ($currentUser !== null && (int) $currentUser['id'] === $id) {
            Session::flash('error', 'Anda tidak dapat menghapus akun sendiri.');

            return $this->redirect('admin/pengguna');
        }

        if (($targetUser['role'] ?? '') === 'guru') {
            Session::flash('error', 'Akun guru terhubung dengan data guru sehingga tidak dapat dihapus.');
            return $this->redirect('admin/pengguna');
        }

        try {
            User::deleteById($id);
            Session::flash('success', 'Pengguna dihapus.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus pengguna: ' . $exception->getMessage());
        }

        return $this->redirect('admin/pengguna');
    }

    public function sendWhatsappDefaultPassword(Request $request, int $id): Response
    {
        return $this->dispatchWhatsappPassword($request, $id, false);
    }

    public function sendWhatsappResetPassword(Request $request, int $id): Response
    {
        return $this->dispatchWhatsappPassword($request, $id, true);
    }

    public function resetPassword(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/pengguna')) {
            return $response;
        }

        $user = User::find($id);
        if ($user === null) {
            Session::flash('error', 'Data pengguna tidak ditemukan.');
            return $this->redirect('admin/pengguna');
        }

        if (($user['role'] ?? '') !== 'guru') {
            Session::flash('error', 'Reset password khusus untuk akun guru.');
            return $this->redirect('admin/pengguna');
        }

        $temporaryPassword = $this->generateTemporaryPassword();

        try {
            User::updateById($id, [
                'password' => password_hash($temporaryPassword, PASSWORD_BCRYPT),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            Session::flash(
                'success',
                sprintf(
                    'Password untuk akun guru %s (%s) berhasil direset. Password baru: %s',
                    $user['name'] ?? 'Guru',
                    $user['username'] ?? '-',
                    $temporaryPassword
                )
            );
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal mereset password: ' . $exception->getMessage());
        }

        return $this->redirect('admin/pengguna');
    }

    private function validate(Request $request, bool $isCreate = true, ?int $ignoreId = null): ?array
    {
        $data = [
            'name' => trim((string) $request->input('name', '')),
            'username' => trim((string) $request->input('username', '')),
            'email' => trim((string) $request->input('email', '')),
            'role' => trim((string) $request->input('role', '')),
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
        ];

        if ($data['name'] === '' || $data['username'] === '' || $data['role'] === '') {
            Session::flash('error', 'Nama, username, dan peran wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        if (!in_array($data['role'], $this->roles, true)) {
            Session::flash('error', 'Peran pengguna tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['role'] === 'guru') {
            Session::flash('error', 'Akun guru dibuat otomatis melalui menu Master Guru.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Format email tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        if ($isCreate && $data['password'] === '') {
            Session::flash('error', 'Password wajib diisi untuk pengguna baru.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['password'] !== '') {
            if (strlen($data['password']) < 8) {
                Session::flash('error', 'Password minimal 8 karakter.');
                Session::flashInput($request->all());

                return null;
            }

            if ($data['password'] !== $data['password_confirmation']) {
                Session::flash('error', 'Konfirmasi password tidak cocok.');
                Session::flashInput($request->all());

                return null;
            }
        }

        if (User::exists(['username' => $data['username']], $ignoreId)) {
            Session::flash('error', 'Username sudah digunakan.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['email'] !== '' && User::exists(['email' => $data['email']], $ignoreId)) {
            Session::flash('error', 'Email sudah digunakan.');
            Session::flashInput($request->all());

            return null;
        }

        return $data;
    }

    private function dispatchWhatsappPassword(Request $request, int $id, bool $isReset): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/pengguna')) {
            return $response;
        }

        $user = User::find($id);
        if ($user === null) {
            Session::flash('error', 'Data pengguna tidak ditemukan.');

            return $this->redirect('admin/pengguna');
        }

        if (empty($user['teacher_id'])) {
            Session::flash('error', 'Pengiriman WhatsApp hanya tersedia untuk akun yang terhubung dengan data guru.');

            return $this->redirect('admin/pengguna');
        }

        $phone = $this->resolveWhatsappPhone($user);
        if ($phone === '') {
            Session::flash('error', 'Nomor WhatsApp belum diisi pada data guru terkait.');

            return $this->redirect('admin/pengguna');
        }

        $gatewaySettings = WhatsappGatewaySetting::first();
        if ($gatewaySettings === null) {
            Session::flash('error', 'Pengaturan WhatsApp Gateway belum diset. Lengkapi terlebih dahulu di menu Integrasi.');

            return $this->redirect('admin/pengguna');
        }

        $templates = UserPasswordMessageSetting::get();
        $template = $isReset ? $templates['reset_password_template'] : $templates['default_password_template'];
        $temporaryPassword = $this->generateTemporaryPassword();
        $variables = [
            'nama' => $user['name'] ?? 'Pengguna',
            'username' => $user['username'] ?? '',
            'password_default' => $temporaryPassword,
            'login_url' => $this->defaultLoginUrl(),
            'reset_url' => $this->defaultResetUrl(),
            'peran' => strtoupper((string) ($user['role'] ?? '')),
            'sekolah' => config('app.name'),
            'telepon' => $phone,
        ];

        $result = WhatsappGatewayService::sendDetailed([
            'phone' => $phone,
            'template' => $template,
            'variables' => $variables,
        ], $gatewaySettings);

        if (!$result['success']) {
            $statusInfo = $result['status'] !== null ? ' (HTTP ' . $result['status'] . ')' : '';
            Session::flash('error', ($result['error'] ?? 'Gagal mengirim pesan WhatsApp.') . $statusInfo);

            return $this->redirect('admin/pengguna');
        }

        $statusNote = $result['queued']
            ? 'Pesan WhatsApp ditambahkan ke antrian.'
            : 'Pesan WhatsApp berhasil dikirim.';

        Session::flash('success', $statusNote . ' Nomor tujuan: ' . $phone . '.');

        return $this->redirect('admin/pengguna');
    }

    private function resolveWhatsappPhone(array $user): string
    {
        $phone = '';
        $teacherId = (int) ($user['teacher_id'] ?? 0);

        if ($teacherId > 0) {
            $teacher = Teacher::find($teacherId);
            if ($teacher !== null) {
                $phone = trim((string) ($teacher['telepon'] ?? ''));
            }
        }

        return $phone;
    }

    private function defaultLoginUrl(): string
    {
        return absolute_url('login');
    }

    private function defaultResetUrl(): string
    {
        return absolute_url('profile/password');
    }

    protected function ensureAdmin(string $redirect = 'dashboard'): ?Response
    {
        $user = auth();

        if (is_array($user) && \App\Support\UserModuleRules::allowsCurrentRequest($user, true)) {
            return null;
        }

        if (($user['role'] ?? '') !== 'admin') {
            Session::flash('error', 'Anda tidak memiliki hak akses ke fitur ini.');

            return $this->redirect($redirect);
        }

        return null;
    }

    private function generateTemporaryPassword(): string
    {
        return 'guru123';
    }
}

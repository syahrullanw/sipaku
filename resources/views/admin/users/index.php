<?php $isEditing = isset($editingUser) && $editingUser !== null; ?>

<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">
                <?= $isEditing ? 'Ubah Pengguna' : 'Tambah Pengguna' ?>
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Akun guru dibuat otomatis saat menambah data guru. Formulir ini hanya untuk akun admin atau staf.
            </p>
            <form
                action="<?= htmlspecialchars($isEditing ? base_url('admin/pengguna/' . $editingUser['id'] . '/update') : base_url('admin/pengguna'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-600">Nama Lengkap</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars((string) old('name', $editingUser['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    />
                </div>
                <div>
                    <label for="username" class="block text-sm font-medium text-slate-600">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="<?= htmlspecialchars((string) old('username', $editingUser['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    />
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-600">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars((string) old('email', $editingUser['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="nama@smk.sch.id"
                    />
                </div>
                <div>
                    <label for="role" class="block text-sm font-medium text-slate-600">Peran</label>
                    <?php $roleValue = old('role', $editingUser['role'] ?? 'staff'); ?>
                    <select
                        id="role"
                        name="role"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    >
                        <?php foreach ($roles as $role): ?>
                            <?php if ($role === 'guru') { continue; } ?>
                            <option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>" <?= $roleValue === $role ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-600">Password <?= $isEditing ? '(opsional)' : '' ?></label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            <?= $isEditing ? '' : 'required' ?>
                            placeholder="<?= $isEditing ? 'Kosongkan jika tidak diubah' : 'Minimal 8 karakter' ?>"
                        />
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-slate-600">Konfirmasi Password</label>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            <?= $isEditing ? '' : 'required' ?>
                        />
                    </div>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 focus:outline-none focus:ring">
                        <?= $isEditing ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                    <?php if ($isEditing): ?>
                        <a href="<?= htmlspecialchars(base_url('admin/pengguna'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                            Batal
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-7 space-y-4">
        <form action="<?= htmlspecialchars(base_url('admin/pengguna'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex items-center gap-3">
            <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"
                placeholder="Cari nama, username, email, atau NIP..."
                class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
            />
            <button type="submit" class="inline-flex items-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Cari</button>
        </form>

        <?php
            $defaultWaTemplate = (string) old('default_password_template', $whatsappTemplates['default_password_template'] ?? '');
            $resetWaTemplate = (string) old('reset_password_template', $whatsappTemplates['reset_password_template'] ?? '');
            $placeholders = isset($whatsappPlaceholders) && is_array($whatsappPlaceholders) ? implode(', ', $whatsappPlaceholders) : '';
            $whatsappReady = (bool) ($whatsappGatewayConfigured ?? false);
        ?>
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-800">Template WhatsApp Password</h2>
                    <p class="mt-1 text-xs text-slate-500">Sesuaikan pesan otomatis untuk membagikan password default dan tautan ganti password ke guru.</p>
                </div>
                <?php if (!$whatsappReady): ?>
                    <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                        Gateway belum dikonfigurasi
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        Gateway aktif
                    </span>
                <?php endif; ?>
            </div>
            <form action="<?= htmlspecialchars(base_url('admin/pengguna/template-whatsapp'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-5 px-6 pb-6 pt-5">
                <?= csrf_field() ?>
                <div>
                    <label for="default_password_template" class="mb-1 block text-sm font-semibold text-slate-700">Pesan Password Default</label>
                    <textarea
                        id="default_password_template"
                        name="default_password_template"
                        rows="4"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        required
                    ><?= htmlspecialchars($defaultWaTemplate, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label for="reset_password_template" class="mb-1 block text-sm font-semibold text-slate-700">Pesan Reset Password</label>
                    <textarea
                        id="reset_password_template"
                        name="reset_password_template"
                        rows="4"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        required
                    ><?= htmlspecialchars($resetWaTemplate, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">
                        Placeholder: <?= htmlspecialchars($placeholders, ENT_QUOTES, 'UTF-8') ?>.
                    </p>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 focus:outline-none focus:ring">
                        Simpan Template
                    </button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-800">Daftar Pengguna</h2>
                <p class="mt-1 text-xs text-slate-500">Guru dapat direset password-nya melalui tombol khusus. Mereka juga bisa masuk menggunakan NIP.</p>
            </div>
            <div class="overflow-x-auto table-scroll">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Nama</th>
                            <th class="px-6 py-4">Username</th>
                            <th class="px-6 py-4">Email</th>
                            <th class="px-6 py-4">NIP</th>
                            <th class="px-6 py-4">Peran</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="px-6 py-4 font-semibold text-slate-700"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-slate-500"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-slate-500"><?= htmlspecialchars($user['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-slate-500"><?= htmlspecialchars($user['nip'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-slate-500"><?= htmlspecialchars(strtoupper($user['role']), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-right">
                                    <?php
                                        $userRole = (string) ($user['role'] ?? '');
                                        $whatsappNumber = trim((string) ($user['guru_telepon'] ?? ''));
                                        $waDisabledAttr = $whatsappReady ? '' : 'disabled title="Gateway WhatsApp belum aktif"';
                                    ?>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <?php if ($userRole === 'guru'): ?>
                                            <?php if ($whatsappNumber !== ''): ?>
                                                <form action="<?= htmlspecialchars(base_url('admin/pengguna/' . $user['id'] . '/whatsapp/default-password'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Kirim pesan WhatsApp password default ke <?= htmlspecialchars($whatsappNumber, ENT_QUOTES, 'UTF-8') ?>?');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="inline-flex items-center rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" <?= $waDisabledAttr ?>>
                                                        WA Password
                                                    </button>
                                                </form>
                                                <form action="<?= htmlspecialchars(base_url('admin/pengguna/' . $user['id'] . '/whatsapp/reset-password'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Kirim tautan reset password ke <?= htmlspecialchars($whatsappNumber, ENT_QUOTES, 'UTF-8') ?>?');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="inline-flex items-center rounded-lg border border-sky-200 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-50" <?= $waDisabledAttr ?>>
                                                        WA Reset
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="inline-flex items-center rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-semibold text-amber-600">
                                                    Isi nomor WA di data guru
                                                </span>
                                            <?php endif; ?>
                                            <form action="<?= htmlspecialchars(base_url('admin/pengguna/' . $user['id'] . '/reset-password'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Reset password untuk akun guru ini?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">
                                                    Reset Password
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <a href="<?= htmlspecialchars(base_url('admin/pengguna?edit=' . urlencode((string) $user['id'])), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">Edit</a>
                                            <?php if ((auth()['id'] ?? null) !== (int) $user['id']): ?>
                                                <form action="<?= htmlspecialchars(base_url('admin/pengguna/' . $user['id'] . '/delete'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus pengguna ini?');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Hapus</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($userRole === 'guru'): ?>
                                        <p class="mt-2 text-[11px] text-right text-slate-400">
                                            WA: <?= htmlspecialchars($whatsappNumber !== '' ? $whatsappNumber : 'Belum tersedia', ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-400">Belum ada data pengguna.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

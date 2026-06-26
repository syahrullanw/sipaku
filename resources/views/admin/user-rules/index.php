<?php
    $roles = is_array($roles ?? null) ? $roles : [];
    $modules = is_array($modules ?? null) ? $modules : [];
    $permissions = is_array($permissions ?? null) ? $permissions : [];
    $groupedModules = [];

    foreach ($modules as $key => $module) {
        $group = (string) ($module['group'] ?? 'Lainnya');
        $groupedModules[$group][$key] = $module;
    }
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Hak Akses Modul</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Atur Akses Berdasarkan Jenis User</h2>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">
                    Centang modul yang boleh diakses oleh setiap jenis user. Aturan ini memengaruhi tampilan menu dan akses URL langsung.
                </p>
            </div>
            <form action="<?= htmlspecialchars(base_url('admin/user-rules'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Kembalikan semua user rules ke aturan default aplikasi?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset" />
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                    <i class="ri-refresh-line text-base"></i>
                    Reset Default
                </button>
            </form>
        </div>
    </div>

    <form action="<?= htmlspecialchars(base_url('admin/user-rules'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save" />

        <?php foreach ($groupedModules as $group => $items): ?>
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800"><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-xs text-slate-500">Modul kontekstual tetap mengikuti syarat data terkait, misalnya wali kelas, kepala prodi, atau penanggung jawab PPDB.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="sticky left-0 z-10 bg-slate-50 px-6 py-4">Modul</th>
                                <?php foreach ($roles as $role => $label): ?>
                                    <th class="px-4 py-4 text-center"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php foreach ($items as $key => $module): ?>
                                <?php
                                    $lockedAdmin = (bool) ($module['locked_admin'] ?? false);
                                    $contextual = (bool) ($module['contextual'] ?? false);
                                ?>
                                <tr>
                                    <td class="sticky left-0 z-10 bg-white px-6 py-4">
                                        <div class="font-semibold text-slate-800"><?= htmlspecialchars((string) ($module['label'] ?? $key), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-400">
                                            <span><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($contextual): ?>
                                                <span class="rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-700">Kontekstual</span>
                                            <?php endif; ?>
                                            <?php if ($lockedAdmin): ?>
                                                <span class="rounded-full bg-indigo-50 px-2 py-0.5 font-semibold text-indigo-700">Wajib admin</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <?php foreach ($roles as $role => $label): ?>
                                        <?php
                                            $checked = (bool) ($permissions[$role][$key] ?? false);
                                            $disabled = $lockedAdmin && $role === 'admin';
                                        ?>
                                        <td class="px-4 py-4 text-center">
                                            <input
                                                type="checkbox"
                                                name="permissions[<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>][<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>]"
                                                value="1"
                                                <?= $checked ? 'checked' : '' ?>
                                                <?= $disabled ? 'disabled' : '' ?>
                                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                title="<?= htmlspecialchars($label . ' - ' . (string) ($module['label'] ?? $key), ENT_QUOTES, 'UTF-8') ?>"
                                            />
                                            <?php if ($disabled): ?>
                                                <input type="hidden" name="permissions[<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>][<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>]" value="1" />
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="sticky bottom-4 z-20 flex justify-end">
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-500 focus:outline-none focus:ring">
                <i class="ri-save-3-line text-base"></i>
                Simpan User Rules
            </button>
        </div>
    </form>
</div>

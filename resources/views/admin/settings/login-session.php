<?php
    $bounds = $timeoutBounds ?? ['min' => 5, 'max' => 480, 'default' => 30];
    $timeoutValue = (int) old('timeout_minutes', $timeoutMinutes ?? $bounds['default']);
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Pengaturan</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">Durasi Sesi Login</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                    Sesuaikan berapa menit sesi pengguna tetap aktif saat tidak melakukan aktivitas apapun. Sesi akan berakhir otomatis setelah durasi yang ditentukan.
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-500 dark:border-slate-600 dark:text-slate-300">
                <p class="text-xs uppercase tracking-wide text-slate-400">Durasi saat ini</p>
                <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-white">
                    <?= number_format($timeoutValue) ?> menit
                </p>
                <p class="text-xs text-slate-400">
                    Rentang <?= number_format((int) $bounds['min']) ?>–<?= number_format((int) $bounds['max']) ?> menit
                </p>
            </div>
        </div>

        <form action="<?= htmlspecialchars(base_url('admin/pengaturan/sesi-login'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-6 space-y-4">
            <?= csrf_field() ?>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="timeout-minutes" class="text-sm font-medium text-slate-600 dark:text-slate-200">Durasi Sesi (menit)</label>
                    <input
                        type="number"
                        id="timeout-minutes"
                        name="timeout_minutes"
                        min="<?= (int) $bounds['min'] ?>"
                        max="<?= (int) $bounds['max'] ?>"
                        value="<?= (int) $timeoutValue ?>"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-slate-700"
                    />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Minimal <?= number_format((int) $bounds['min']) ?> menit, maksimal <?= number_format((int) $bounds['max']) ?> menit. perubahan berlaku setelah pengguna melakukan login ulang.
                    </p>
                </div>
                <div class="flex flex-col justify-between gap-3">
                    <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                        <p class="font-semibold text-slate-800 dark:text-white">Catatan</p>
                        <p class="mt-1 text-xs">
                            Buat sesi lebih singkat untuk keamanan tambahan, atau lebih panjang bila pengguna sering meninggalkan layar. Nilai akan disesuaikan jika keluar dari rentang.
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Simpan Pengaturan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

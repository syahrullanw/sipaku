<?php
    $backups = array_values($backups ?? []);
    $report = is_array($report ?? null) ? $report : null;
    $appVersion = (string) config('app.version', '1.0.5');
    $formatSize = static function (int|float $bytes): string {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 2) . ' ' . $units[$power];
    };
?>

<div class="grid gap-6 xl:grid-cols-12">
    <div class="space-y-6 xl:col-span-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">Buat Backup Baru</h2>
            <p class="mt-2 text-sm text-slate-500">
                Buat backup penuh berisi database SQL, asset penting, dan manifest versi aplikasi. Proses ini dapat memerlukan waktu tergantung besar data.
            </p>

            <form
                action="<?= htmlspecialchars(base_url('admin/backup-restore/backup'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <i class="ri-database-2-line text-lg"></i>
                    Buat Full Backup
                </button>
            </form>

            <div class="mt-6 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                <p class="font-semibold text-slate-700">Tips</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Jalankan backup saat aktivitas pengguna minim.</li>
                    <li>Simpan file backup di lokasi berbeda untuk keamanan tambahan.</li>
                    <li>Backup disimpan di direktori <span class="font-semibold text-slate-700">storage/backups</span>.</li>
                    <li>Paket ZIP memuat <span class="font-semibold text-slate-700">database.sql</span>, asset, dan <span class="font-semibold text-slate-700">manifest.json</span>.</li>
                </ul>
            </div>

            <div class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-xs text-indigo-700">
                <p class="font-semibold">Versi aplikasi aktif</p>
                <p class="mt-1 font-mono text-sm"><?= htmlspecialchars($appVersion, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">Restore Data</h2>
            <p class="mt-2 text-sm text-slate-500">
                Pilih file backup yang tersedia atau unggah berkas SQL/ZIP baru untuk mengembalikan data. Restore ZIP akan memulihkan database dan asset penting.
            </p>

            <form
                action="<?= htmlspecialchars(base_url('admin/backup-restore/restore'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                enctype="multipart/form-data"
                class="mt-5 space-y-4"
            >
                <?= csrf_field() ?>
                <div>
                    <label for="backup_file" class="block text-sm font-medium text-slate-600">Unggah File SQL / ZIP</label>
                    <input
                        id="backup_file"
                        name="backup_file"
                        type="file"
                        accept=".sql,.zip"
                        class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                    />
                    <p class="mt-1 text-xs text-slate-500">Format yang didukung: file .sql lama atau paket .zip backup penuh.</p>
                </div>

                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <span class="flex-1 border-t border-dashed border-slate-200"></span>
                    <span>Pilih salah satu</span>
                    <span class="flex-1 border-t border-dashed border-slate-200"></span>
                </div>

                <div>
                    <label for="existing_backup" class="block text-sm font-medium text-slate-600">Backup Tersimpan</label>
                    <select
                        id="existing_backup"
                        name="existing_backup"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                    >
                        <option value="">Pilih file dari storage/backups</option>
                        <?php foreach ($backups as $item): ?>
                            <option value="<?= htmlspecialchars((string) ($item['filename'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string) ($item['filename'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Jika memilih opsi ini, file unggahan akan diabaikan.</p>
                </div>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2"
                >
                    <i class="ri-history-line text-lg"></i>
                    Jalankan Restore
                </button>

                <p class="text-xs text-rose-500">
                    Pastikan Anda memiliki backup terbaru sebelum melakukan restore. Restore ZIP akan mengganti database dan direktori asset yang termasuk di paket backup.
                </p>
            </form>
        </div>
    </div>

    <div class="space-y-6 xl:col-span-8">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-800">Daftar Backup</h2>
                    <p class="mt-1 text-sm text-slate-500">File hasil backup yang tersimpan di <span class="font-medium text-slate-700">storage/backups</span>.</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                    Total: <?= count($backups) ?>
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Nama File</th>
                            <th class="px-6 py-3">Tipe</th>
                            <th class="px-6 py-3">Ukuran</th>
                            <th class="px-6 py-3">Dibuat</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($backups as $item): ?>
                            <?php
                                $name = (string) ($item['filename'] ?? '');
                                $size = (int) ($item['size'] ?? 0);
                                $modified = (int) ($item['modified'] ?? 0);
                                $type = (string) ($item['type'] ?? 'database');
                                $formattedSize = $formatSize($size);
                                $formattedDate = $modified > 0
                                    ? date('d M Y H:i', $modified)
                                    : '-';
                            ?>
                            <tr>
                                <td class="px-6 py-4 font-medium text-slate-700">
                                    <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $type === 'full' ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700' ?>">
                                        <?= htmlspecialchars($type === 'full' ? 'Full Backup' : 'Database SQL', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    <?= htmlspecialchars($formattedSize, ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    <?= htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a
                                        href="<?= htmlspecialchars(base_url('admin/backup-restore/download/' . rawurlencode($name)), ENT_QUOTES, 'UTF-8') ?>"
                                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-indigo-300 hover:text-indigo-600"
                                    >
                                        <i class="ri-download-2-line text-base"></i>
                                        Unduh
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($backups)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-6 text-center text-sm text-slate-400">
                                    Belum ada file backup yang tersimpan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($report !== null): ?>
            <?php
                $details = $report['details'] ?? [];
                $type = (string) ($report['type'] ?? '');
                $timestamp = (string) ($report['timestamp'] ?? '');
                $isBackup = $type === 'backup';
                $errors = is_array($details['errors'] ?? null) ? array_filter($details['errors']) : [];
            ?>
            <div class="rounded-2xl border <?= $isBackup ? 'border-emerald-200 bg-emerald-50/60' : 'border-amber-200 bg-amber-50/60' ?> p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold <?= $isBackup ? 'text-emerald-700' : 'text-amber-700' ?>">
                            Aktivitas Terakhir: <?= $isBackup ? 'Backup' : 'Restore' ?>
                        </h3>
                        <?php if ($timestamp !== ''): ?>
                            <p class="mt-1 text-sm <?= $isBackup ? 'text-emerald-600' : 'text-amber-600' ?>">
                                <?= htmlspecialchars($timestamp, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php if ($isBackup && !empty($details['filename'])): ?>
                        <span class="inline-flex items-center rounded-full bg-white/70 px-3 py-1 text-xs font-semibold text-emerald-700 shadow">
                            <?= htmlspecialchars((string) $details['filename'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php elseif (!$isBackup && !empty($details['source'])): ?>
                        <span class="inline-flex items-center rounded-full bg-white/70 px-3 py-1 text-xs font-semibold text-amber-700 shadow">
                            <?= htmlspecialchars((string) $details['source'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endif; ?>
                </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <?php if ($isBackup): ?>
                    <div class="rounded-xl bg-white/70 px-4 py-3 text-sm text-slate-700 shadow">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Jumlah Tabel</p>
                        <p class="mt-1 text-lg font-semibold text-slate-800">
                            <?= number_format((int) ($details['tables'] ?? 0)) ?>
                        </p>
                    </div>
                    <div class="rounded-xl bg-white/70 px-4 py-3 text-sm text-slate-700 shadow">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Total Baris</p>
                        <p class="mt-1 text-lg font-semibold text-slate-800">
                            <?= number_format((int) ($details['rows'] ?? 0)) ?>
                        </p>
                    </div>
                    <div class="rounded-xl bg-white/70 px-4 py-3 text-sm text-slate-700 shadow">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Ukuran File</p>
                        <p class="mt-1 text-lg font-semibold text-slate-800">
                            <?= htmlspecialchars($formatSize((int) ($details['size'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                    <?php if (!empty($details['app_version'])): ?>
                        <div class="rounded-xl bg-white/70 px-4 py-3 text-sm text-slate-700 shadow">
                            <p class="text-xs uppercase tracking-wide text-slate-400">Versi Aplikasi</p>
                            <p class="mt-1 text-lg font-semibold text-slate-800">
                                <?= htmlspecialchars((string) $details['app_version'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="rounded-xl bg-white/70 px-4 py-3 text-sm text-slate-700 shadow">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Total Pernyataan</p>
                        <p class="mt-1 text-lg font-semibold text-slate-800">
                            <?= number_format((int) ($details['statements'] ?? 0)) ?>
                        </p>
                    </div>
                    <div class="rounded-xl bg-white/70 px-4 py-3 text-sm text-slate-700 shadow">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Berhasil Dieksekusi</p>
                        <p class="mt-1 text-lg font-semibold text-slate-800">
                            <?= number_format((int) ($details['executed'] ?? 0)) ?>
                        </p>
                    </div>
                    <div class="rounded-xl bg-white/70 px-4 py-3 text-sm text-slate-700 shadow">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Error</p>
                        <p class="mt-1 text-lg font-semibold text-slate-800">
                            <?= number_format(count($errors)) ?>
                        </p>
                    </div>
                    <?php if (!empty($details['assets_restored']) && is_array($details['assets_restored'])): ?>
                        <div class="rounded-xl bg-white/70 px-4 py-3 text-sm text-slate-700 shadow sm:col-span-2">
                            <p class="text-xs uppercase tracking-wide text-slate-400">Asset Dipulihkan</p>
                            <div class="mt-2 space-y-1">
                                <?php foreach ($details['assets_restored'] as $asset): ?>
                                    <p>
                                        <?= htmlspecialchars((string) ($asset['label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        ·
                                        <?= htmlspecialchars((string) ($asset['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        ·
                                        <?= number_format((int) ($asset['files'] ?? 0)) ?> file
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="mt-4 rounded-xl bg-white/80 px-4 py-3 text-sm text-amber-700 shadow">
                    <p class="text-sm font-semibold text-amber-800">Daftar Error</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <?php foreach ($errors as $message): ?>
                            <li><?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

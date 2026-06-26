<?php
    $currentVersion = (string) ($currentVersion ?? '0.0.0');
    $history = is_array($history ?? null) ? $history : [];
    $report = is_array($report ?? null) ? $report : null;
    $maxUploadSize = (int) ($maxUploadSize ?? 52428800);
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
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                    <i class="ri-refresh-line text-xl"></i>
                </div>
                <div>
                    <h2 class="text-base font-semibold text-slate-800">Update Aplikasi</h2>
                    <p class="text-sm text-slate-500">Perbarui SIPAKU versi terbaru</p>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                <p class="text-xs text-slate-500">Versi saat ini</p>
                <p class="mt-1 font-mono text-lg font-bold text-slate-800">v<?= htmlspecialchars($currentVersion, ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                <p class="font-semibold">Peringatan</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Pastikan Anda memiliki backup database sebelum melakukan update.</li>
                    <li>Gunakan file ZIP update resmi dari pengembang SIPAKU.</li>
                    <li>File ZIP harus mengandung file <strong>VERSION</strong> di root.</li>
                    <li>Proses backup file lama akan otomatis dibuat sebelum update.</li>
                </ul>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">Upload File Update</h2>
            <p class="mt-2 text-sm text-slate-500">
                Unggah file ZIP pembaruan yang berisi file-file yang akan diperbarui.
            </p>

            <form
                action="<?= htmlspecialchars(base_url('admin/update'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                enctype="multipart/form-data"
                class="mt-5 space-y-4"
            >
                <?= csrf_field() ?>

                <div>
                    <label for="update_zip" class="block text-sm font-medium text-slate-600">File ZIP</label>
                    <input
                        id="update_zip"
                        name="update_zip"
                        type="file"
                        accept=".zip"
                        required
                        class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Format ZIP maksimal <?= htmlspecialchars($formatSize($maxUploadSize), ENT_QUOTES, 'UTF-8') ?>.
                    </p>
                </div>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <i class="ri-upload-cloud-2-line text-lg"></i>
                    Unggah & Ekstrak
                </button>
            </form>

            <div class="mt-4 rounded-xl bg-white px-4 py-3 text-xs text-slate-500 shadow-sm">
                <p>
                    File ZIP akan diekstrak langsung ke direktori aplikasi. File yang sudah ada akan di-backup otomatis ke
                    <span class="font-semibold text-slate-700">storage/backups/updates/</span>.
                </p>
            </div>
        </div>

        <?php if (!empty($history)): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-slate-800">Riwayat Update</h2>
                <p class="mt-1 text-sm text-slate-500">Paket update yang tersimpan di server.</p>

                <div class="mt-4 space-y-2">
                    <?php foreach ($history as $item): ?>
                        <?php
                            $ver = (string) ($item['version'] ?? '-');
                            $modified = (int) ($item['modified'] ?? 0);
                            $formattedDate = $modified > 0 ? date('d M Y H:i', $modified) : '-';
                        ?>
                        <div class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">v</span>
                                <span class="font-mono font-semibold text-slate-700"><?= htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <span class="text-xs text-slate-400"><?= htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="space-y-6 xl:col-span-8">
        <?php if ($report !== null): ?>
            <?php
                $oldVer = (string) ($report['old_version'] ?? '?');
                $newVer = (string) ($report['new_version'] ?? '?');
                $errors = is_array($report['errors'] ?? null) ? array_filter($report['errors']) : [];
                $files = is_array($report['files_extracted'] ?? null) ? $report['files_extracted'] : [];
                $backupPath = (string) ($report['backup_path'] ?? '');
                $totalExtracted = (int) ($report['total_extracted'] ?? count($files));
                $totalSkipped = (int) ($report['total_skipped'] ?? 0);
                $hasErrors = !empty($errors);
                $timestamp = (string) ($report['timestamp'] ?? '');
            ?>

            <div class="rounded-2xl border <?= $hasErrors ? 'border-amber-200 bg-amber-50/60' : 'border-emerald-200 bg-emerald-50/60' ?> p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold <?= $hasErrors ? 'text-amber-700' : 'text-emerald-700' ?>">
                            <i class="<?= $hasErrors ? 'ri-alert-line' : 'ri-checkbox-circle-line' ?> mr-1 text-lg"></i>
                            Laporan Update
                        </h3>
                        <?php if ($timestamp !== ''): ?>
                            <p class="mt-1 text-sm <?= $hasErrors ? 'text-amber-600' : 'text-emerald-600' ?>">
                                <?= htmlspecialchars($timestamp, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full bg-white/70 px-3 py-1 text-xs font-semibold text-slate-700 shadow">
                        <?= htmlspecialchars($oldVer, ENT_QUOTES, 'UTF-8') ?>
                        <i class="ri-arrow-right-line"></i>
                        <?= htmlspecialchars($newVer, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl bg-white/70 px-4 py-3 text-sm text-slate-700 shadow">
                        <p class="text-xs uppercase tracking-wide text-slate-400">File Diekstrak</p>
                        <p class="mt-1 text-lg font-semibold text-slate-800"><?= number_format($totalExtracted) ?></p>
                    </div>
                    <?php if ($totalSkipped > 0): ?>
                        <div class="rounded-xl bg-white/70 px-4 py-3 text-sm text-slate-700 shadow">
                            <p class="text-xs uppercase tracking-wide text-slate-400">File Dilewati</p>
                            <p class="mt-1 text-lg font-semibold text-slate-800"><?= number_format($totalSkipped) ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="rounded-xl bg-white/70 px-4 py-3 text-sm text-slate-700 shadow">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Error</p>
                        <p class="mt-1 text-lg font-semibold <?= $hasErrors ? 'text-rose-600' : 'text-slate-800' ?>">
                            <?= number_format(count($errors)) ?>
                        </p>
                    </div>
                    <?php if ($backupPath !== ''): ?>
                        <div class="rounded-xl bg-white/70 px-4 py-3 text-sm text-slate-700 shadow">
                            <p class="text-xs uppercase tracking-wide text-slate-400">Lokasi Backup</p>
                            <p class="mt-1 truncate text-xs font-mono text-slate-600" title="<?= htmlspecialchars($backupPath, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(basename($backupPath), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
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

                <?php if (is_array($report['migration'] ?? null)): ?>
                    <?php
                        $mig = $report['migration'];
                        $migErrors = is_array($mig['errors'] ?? null) ? $mig['errors'] : [];
                        $migExecuted = (int) ($mig['executed'] ?? 0);
                        $sqlBackupPath = (string) ($mig['sql_backup_path'] ?? '');
                    ?>
                    <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50/60 p-4 shadow-sm">
                        <div class="flex items-center gap-2">
                            <i class="ri-database-2-line text-lg text-sky-600"></i>
                            <h4 class="text-sm font-semibold text-sky-800">Migration Database</h4>
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-lg bg-white/70 px-3 py-2 text-sm text-slate-700 shadow">
                                <p class="text-xs uppercase tracking-wide text-slate-400">Pernyataan SQL</p>
                                <p class="mt-1 text-lg font-semibold text-sky-700"><?= number_format($migExecuted) ?></p>
                            </div>
                            <div class="rounded-lg bg-white/70 px-3 py-2 text-sm text-slate-700 shadow">
                                <p class="text-xs uppercase tracking-wide text-slate-400">Error</p>
                                <p class="mt-1 text-lg font-semibold <?= !empty($migErrors) ? 'text-rose-600' : 'text-emerald-600' ?>">
                                    <?= number_format(count($migErrors)) ?>
                                </p>
                            </div>
                            <?php if ($sqlBackupPath !== ''): ?>
                                <div class="rounded-lg bg-white/70 px-3 py-2 text-sm text-slate-700 shadow sm:col-span-2">
                                    <p class="text-xs uppercase tracking-wide text-slate-400">Backup Database</p>
                                    <p class="mt-1 truncate text-xs font-mono text-slate-600" title="<?= htmlspecialchars($sqlBackupPath, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(basename($sqlBackupPath), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($migErrors)): ?>
                            <div class="mt-3 rounded-lg bg-white/80 px-3 py-2 text-xs text-amber-700">
                                <p class="text-xs font-semibold text-amber-800">Error Migration</p>
                                <ul class="mt-1 list-disc space-y-0.5 pl-4">
                                    <?php foreach ($migErrors as $msg): ?>
                                        <li><?= htmlspecialchars((string) $msg, ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($files)): ?>
                    <div class="mt-4">
                        <details class="rounded-xl bg-white/70 px-4 py-3 text-sm shadow">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-700">
                                <i class="ri-file-list-3-line mr-1"></i>
                                Daftar File yang Diekstrak (<?= number_format(count($files)) ?>)
                            </summary>
                            <ul class="mt-3 max-h-80 space-y-1 overflow-y-auto pl-5">
                                <?php sort($files); ?>
                                <?php foreach ($files as $file): ?>
                                    <li class="text-xs text-slate-600">
                                        <i class="ri-file-line mr-1 text-slate-400"></i>
                                        <?= htmlspecialchars((string) $file, ENT_QUOTES, 'UTF-8') ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-10 shadow-sm">
                <div class="text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
                        <i class="ri-refresh-line text-3xl text-slate-400"></i>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-slate-800">Belum Ada Update</h3>
                    <p class="mt-2 text-sm text-slate-500">
                        Unggah file ZIP pembaruan untuk memulai proses update aplikasi.
                    </p>
                    <p class="mt-4 text-xs text-slate-400">
                        Pastikan file ZIP berasal dari sumber terpercaya dan mengandung file VERSION di root-nya.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

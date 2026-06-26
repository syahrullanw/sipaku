<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-semibold text-slate-800 dark:text-gray-100"><?= htmlspecialchars($pageTitle ?? 'Dashboard PPDB', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-gray-400">Ringkasan periode PPDB yang sedang Anda tangani.</p>
    </header>

    <div class="grid gap-6 lg:grid-cols-2">
        <?php if (empty($assignments)): ?>
            <div class="rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <i class="ri-information-line text-3xl text-slate-400 dark:text-gray-500"></i>
                <p class="mt-3 text-sm text-slate-500 dark:text-gray-400">Belum ada periode PPDB aktif yang ditugaskan.</p>
            </div>
        <?php else: ?>
            <?php foreach ($assignments as $assignment): ?>
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300"><?= htmlspecialchars($assignment['peran'] ?? 'anggota', ENT_QUOTES, 'UTF-8') ?></p>
                            <h2 class="mt-1 text-xl font-semibold text-slate-800 dark:text-gray-100"><?= htmlspecialchars($assignment['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></h2>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-300">
                            Aktif
                        </span>
                    </div>
                    <dl class="mt-4 space-y-2 text-sm text-slate-600 dark:text-gray-300">
                        <div class="flex justify-between gap-2">
                            <dt>Pendaftaran</dt>
                            <dd class="font-medium text-slate-700 dark:text-gray-200">
                                <?php
                                    $start = $assignment['pendaftaran_mulai'] ?? null;
                                    $end = $assignment['pendaftaran_selesai'] ?? null;
                                    if ($start || $end) {
                                        $parts = [];
                                        if ($start) { $parts[] = date('d M Y', strtotime($start)); }
                                        if ($end) { $parts[] = date('d M Y', strtotime($end)); }
                                        echo htmlspecialchars(implode(' – ', $parts), ENT_QUOTES, 'UTF-8');
                                    } else {
                                ?>
                                        <span class="text-slate-400 dark:text-gray-500">Belum dijadwalkan</span>
                                <?php
                                    }
                                ?>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt>Seleksi</dt>
                            <dd class="font-medium text-slate-700 dark:text-gray-200">
                                <?php
                                    $start = $assignment['seleksi_mulai'] ?? null;
                                    $end = $assignment['seleksi_selesai'] ?? null;
                                    if ($start || $end) {
                                        $parts = [];
                                        if ($start) { $parts[] = date('d M Y', strtotime($start)); }
                                        if ($end) { $parts[] = date('d M Y', strtotime($end)); }
                                        echo htmlspecialchars(implode(' – ', $parts), ENT_QUOTES, 'UTF-8');
                                    } else {
                                ?>
                                        <span class="text-slate-400 dark:text-gray-500">Belum dijadwalkan</span>
                                <?php
                                    }
                                ?>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt>Tahun Masuk</dt>
                            <dd class="font-medium text-slate-700 dark:text-gray-200"><?= htmlspecialchars($assignment['tahun_masuk'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    </dl>
                    <div class="mt-5 flex flex-wrap gap-2 text-xs">
                        <?php
                            $stages = [
                                'pendaftaran' => $assignment['pendaftaran_diaktifkan'] ?? false,
                                'seleksi' => $assignment['seleksi_diaktifkan'] ?? false,
                                'pengumuman' => $assignment['pengumuman_diaktifkan'] ?? false,
                                'daftar_ulang' => $assignment['daftar_ulang_diaktifkan'] ?? false,
                                'pembayaran' => $assignment['pembayaran_diaktifkan'] ?? false,
                            ];
                            $labels = [
                                'pendaftaran' => 'Pendaftaran',
                                'seleksi' => 'Seleksi',
                                'pengumuman' => 'Pengumuman',
                                'daftar_ulang' => 'Daftar Ulang',
                                'pembayaran' => 'Pembayaran',
                            ];
                        ?>
                        <?php foreach ($stages as $key => $enabled): ?>
                            <span class="inline-flex items-center rounded-full border px-3 py-1 font-medium <?= $enabled ? 'border-emerald-200 bg-emerald-50 text-emerald-600 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300' : 'border-slate-200 text-slate-400 dark:border-slate-600 dark:text-slate-400'; ?>">
                                <?= htmlspecialchars($labels[$key] ?? ucfirst($key), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

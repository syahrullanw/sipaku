<?php
    /** @var array<int, string> $periodOptions */
    /** @var array<int, array<string, mixed>> $registrants */
    /** @var array<string, string> $statusFinalOptions */

    $successMessage = session_flash('success');
    $errorMessage = session_flash('error');
    $periodId = $selectedPeriodId ?? 0;
    $period = $selectedPeriod ?? null;
    $selectionStatusOptions = $selectionStatusOptions ?? [];
    $announcementStatusOptions = $announcementStatusOptions ?? [];
    $reRegistrationStatusOptions = $reRegistrationStatusOptions ?? [];
    $paymentStatusOptions = $paymentStatusOptions ?? [];
    $indexRoute = $indexRoute ?? 'ppdb/admin/pendaftar';
    $createRoute = $createRoute ?? 'ppdb/admin/pendaftar';
    $showManualForm = $showManualForm ?? false;
    $canUpdateSelection = $canUpdateSelection ?? false;
    $canUpdateAnnouncement = $canUpdateAnnouncement ?? false;
    $canUpdateReRegistration = $canUpdateReRegistration ?? false;
    $canUpdatePayment = $canUpdatePayment ?? false;
    $selectionUpdateRoutePrefix = $selectionUpdateRoutePrefix ?? 'ppdb/admin/pendaftar';
    $announcementUpdateRoutePrefix = $announcementUpdateRoutePrefix ?? $selectionUpdateRoutePrefix;
    $reRegistrationUpdateRoutePrefix = $reRegistrationUpdateRoutePrefix ?? $selectionUpdateRoutePrefix;
    $paymentUpdateRoutePrefix = $paymentUpdateRoutePrefix ?? $selectionUpdateRoutePrefix;
    $deleteRoutePrefix = $deleteRoutePrefix ?? $selectionUpdateRoutePrefix;
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 dark:text-gray-100">Data Pendaftar</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-gray-400">Kelola pendaftar PPDB dan pantau status tiap tahapan.</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($showManualForm && $periodId > 0): ?>
                <a href="<?= htmlspecialchars(base_url($createRoute . '?periode_id=' . (int) $periodId), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900">
                    Tambah Data Pendaftar
                </a>
            <?php endif; ?>
        <?php if (!empty($periodOptions)): ?>
            <form action="<?= htmlspecialchars(base_url($indexRoute), ENT_QUOTES, 'UTF-8') ?>" method="get" class="flex items-center gap-3">
                <label class="text-sm font-medium text-slate-600 dark:text-gray-300">
                    Periode
                    <select name="periode_id" class="ml-2 rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" onchange="this.form.submit()">
                        <?php foreach ($periodOptions as $id => $label): ?>
                            <option value="<?= (int) $id ?>" <?= (int) $periodId === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
            <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="grid gap-6 xl:grid-cols-[2fr,1fr]">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
                <h2 class="text-lg font-semibold text-slate-800 dark:text-gray-100">Daftar Pendaftar</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-gray-400">
                    <?= $period !== null ? htmlspecialchars($period['nama'] ?? '-', ENT_QUOTES, 'UTF-8') : 'Periode belum dipilih.' ?>
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
	                    <thead class="bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-800/60 dark:text-gray-400">
	                        <tr>
	                            <th class="px-4 py-3 text-left">Pendaftar</th>
	                            <th class="px-4 py-3 text-left">Asal Sekolah</th>
	                            <th class="px-4 py-3 text-left">Kontak</th>
	                            <th class="px-4 py-3 text-left">Tahapan</th>
	                            <th class="px-4 py-3 text-left">Kode</th>
	                            <th class="px-4 py-3 text-right">Aksi</th>
	                        </tr>
	                    </thead>
	                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
	                        <?php if (empty($registrants) || $periodId <= 0): ?>
	                            <tr>
	                                <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-gray-400">
	                                    <?= $periodId > 0 ? 'Belum ada pendaftar yang tercatat.' : 'Pilih periode terlebih dahulu.' ?>
	                                </td>
	                            </tr>
                        <?php else: ?>
                            <?php foreach ($registrants as $registrant): ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-700/40">
                                    <td class="px-4 py-4 align-top">
                                        <div class="font-semibold text-slate-800 dark:text-gray-100"><?= htmlspecialchars($registrant['nama_lengkap'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-gray-400">
                                            <?= htmlspecialchars(($registrant['jenis_kelamin'] ?? '') === 'L' ? 'Laki-laki' : (($registrant['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : 'Jenis kelamin belum diisi'), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="mt-1 text-xs text-slate-400 dark:text-gray-500">
                                            Didaftarkan: <?= htmlspecialchars(date('d M Y H:i', strtotime($registrant['tanggal_daftar'] ?? $registrant['created_at'] ?? 'now')), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-slate-600 dark:text-gray-300">
                                        <?= htmlspecialchars($registrant['asal_sekolah'] ?: '-', ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-slate-600 dark:text-gray-300">
                                        <?php if (!empty($registrant['telepon'])): ?>
                                            <div>HP: <?= htmlspecialchars($registrant['telepon'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($registrant['email'])): ?>
                                            <div>Email: <?= htmlspecialchars($registrant['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <?php if (empty($registrant['telepon']) && empty($registrant['email'])): ?>
                                            <span class="text-xs text-slate-400">Tidak ada kontak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 align-top text-xs text-slate-500 dark:text-gray-400 space-y-3">
                                        <div>Verifikasi: <span class="font-medium text-slate-700 dark:text-gray-200"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $registrant['status_verifikasi'] ?? 'draft')), ENT_QUOTES, 'UTF-8') ?></span></div>
                                        <div>
                                            Seleksi: <span class="font-medium text-slate-700 dark:text-gray-200"><?= htmlspecialchars($selectionStatusOptions[$registrant['status_seleksi'] ?? 'belum_dijadwalkan'] ?? 'Belum Dijadwalkan', ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if (!empty($registrant['jadwal_seleksi'])): ?>
                                                <div class="mt-1 text-[11px] text-slate-400 dark:text-gray-500">
                                                    Jadwal: <?= htmlspecialchars(date('d M Y H:i', strtotime($registrant['jadwal_seleksi'])), ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($registrant['nilai_seleksi'])): ?>
                                                <div class="text-[11px] text-slate-400 dark:text-gray-500">
                                                    Nilai: <?= htmlspecialchars(number_format((float) $registrant['nilai_seleksi'], 2), ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            Pengumuman: <span class="font-medium text-slate-700 dark:text-gray-200"><?= htmlspecialchars($announcementStatusOptions[$registrant['status_pengumuman'] ?? 'menunggu'] ?? 'Menunggu', ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if (!empty($registrant['tanggal_pengumuman'])): ?>
                                                <div class="mt-1 text-[11px] text-slate-400 dark:text-gray-500">
                                                    Diterbitkan: <?= htmlspecialchars(date('d M Y H:i', strtotime($registrant['tanggal_pengumuman'])), ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            Daftar Ulang: <span class="font-medium text-slate-700 dark:text-gray-200"><?= htmlspecialchars($reRegistrationStatusOptions[$registrant['status_daftar_ulang'] ?? 'tidak_dibuka'] ?? 'Tahap Belum Dibuka', ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if (!empty($registrant['tanggal_daftar_ulang'])): ?>
                                                <div class="text-[11px] text-slate-400 dark:text-gray-500">
                                                    Konfirmasi: <?= htmlspecialchars(date('d M Y H:i', strtotime($registrant['tanggal_daftar_ulang'])), ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            Pembayaran: <span class="font-medium text-slate-700 dark:text-gray-200"><?= htmlspecialchars($paymentStatusOptions[$registrant['status_pembayaran'] ?? 'tidak_dibuka'] ?? 'Tahap Belum Dibuka', ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if (!empty($registrant['nominal_pembayaran'])): ?>
                                                <div class="text-[11px] text-slate-400 dark:text-gray-500">
                                                    Nominal: Rp<?= htmlspecialchars(number_format((float) $registrant['nominal_pembayaran'], 0, ',', '.'), ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($registrant['tanggal_pembayaran'])): ?>
                                                <div class="text-[11px] text-slate-400 dark:text-gray-500">
                                                    Tanggal: <?= htmlspecialchars(date('d M Y H:i', strtotime($registrant['tanggal_pembayaran'])), ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>Status Akhir: <span class="font-semibold text-indigo-600 dark:text-indigo-300"><?= htmlspecialchars($statusFinalOptions[$registrant['status_final'] ?? 'pendaftar'] ?? 'Pendaftar', ENT_QUOTES, 'UTF-8') ?></span></div>

                                        <?php if (($canUpdateSelection ?? false) && !empty($selectionStatusOptions)): ?>
                                            <?php
                                                $selectionAction = ($selectionUpdateRoutePrefix ?? 'ppdb/admin/pendaftar') . '/' . (int) $registrant['id'] . '/seleksi';
                                                $scheduledAt = $registrant['jadwal_seleksi'] ?? null;
                                                $scheduledDate = $scheduledAt ? date('Y-m-d', strtotime($scheduledAt)) : '';
                                                $scheduledTime = $scheduledAt ? date('H:i', strtotime($scheduledAt)) : '';
                                            ?>
                                            <form action="<?= htmlspecialchars(base_url($selectionAction), ENT_QUOTES, 'UTF-8') ?>" method="post" class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-[11px] dark:border-slate-700 dark:bg-slate-800/40">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="periode_id" value="<?= (int) $periodId ?>" />
                                                <p class="mb-2 font-semibold text-slate-600 dark:text-gray-200">Atur Seleksi</p>
                                                <div class="grid gap-2 sm:grid-cols-2">
                                                    <label class="flex flex-col gap-1">
                                                        <span>Tanggal</span>
                                                        <input type="date" name="jadwal_tanggal" value="<?= htmlspecialchars($scheduledDate, ENT_QUOTES, 'UTF-8') ?>" class="rounded border border-slate-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-gray-100" />
                                                    </label>
                                                    <label class="flex flex-col gap-1">
                                                        <span>Waktu</span>
                                                        <input type="time" name="jadwal_waktu" value="<?= htmlspecialchars($scheduledTime, ENT_QUOTES, 'UTF-8') ?>" class="rounded border border-slate-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-gray-100" />
                                                    </label>
                                                </div>
                                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                                    <label class="flex flex-col gap-1">
                                                        <span>Status</span>
                                                        <select name="status_seleksi" class="rounded border border-slate-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-gray-100">
                                                            <?php foreach ($selectionStatusOptions as $value => $label): ?>
                                                                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($registrant['status_seleksi'] ?? 'belum_dijadwalkan') === (string) $value ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="flex flex-col gap-1">
                                                        <span>Nilai</span>
                                                        <input type="text" name="nilai_seleksi" value="<?= htmlspecialchars($registrant['nilai_seleksi'] !== null ? (string) $registrant['nilai_seleksi'] : '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Opsional" class="rounded border border-slate-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-gray-100" />
                                                    </label>
                                                </div>
                                                <div class="mt-3 text-right">
                                                    <button type="submit" class="inline-flex items-center rounded bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-slate-100 dark:focus:ring-offset-slate-900">Simpan</button>
                                                </div>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (($canUpdateAnnouncement ?? false) && !empty($announcementStatusOptions)): ?>
                                            <?php
                                                $announcementAction = ($announcementUpdateRoutePrefix ?? 'ppdb/admin/pendaftar') . '/' . (int) $registrant['id'] . '/pengumuman';
                                                $announcementAt = $registrant['tanggal_pengumuman'] ?? null;
                                                $announcementDate = $announcementAt ? date('Y-m-d', strtotime($announcementAt)) : '';
                                                $announcementTime = $announcementAt ? date('H:i', strtotime($announcementAt)) : '';
                                            ?>
                                            <form action="<?= htmlspecialchars(base_url($announcementAction), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-[11px] dark:border-amber-500/30 dark:bg-amber-500/10">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="periode_id" value="<?= (int) $periodId ?>" />
                                                <p class="mb-2 font-semibold text-amber-700 dark:text-amber-200">Pengumuman</p>
                                                <div class="grid gap-2 sm:grid-cols-2">
                                                    <label class="flex flex-col gap-1">
                                                        <span>Status</span>
                                                        <select name="status_pengumuman" class="rounded border border-amber-200 px-2 py-1 text-xs focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-amber-500/40 dark:bg-slate-900/40 dark:text-amber-100">
                                                            <?php foreach ($announcementStatusOptions as $value => $label): ?>
                                                                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($registrant['status_pengumuman'] ?? 'menunggu') === (string) $value ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="flex flex-col gap-1">
                                                        <span>Tanggal</span>
                                                        <input type="date" name="pengumuman_tanggal" value="<?= htmlspecialchars($announcementDate, ENT_QUOTES, 'UTF-8') ?>" class="rounded border border-amber-200 px-2 py-1 text-xs focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-amber-500/40 dark:bg-slate-900/40 dark:text-amber-100" />
                                                    </label>
                                                    <label class="flex flex-col gap-1">
                                                        <span>Waktu</span>
                                                        <input type="time" name="pengumuman_waktu" value="<?= htmlspecialchars($announcementTime, ENT_QUOTES, 'UTF-8') ?>" class="rounded border border-amber-200 px-2 py-1 text-xs focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-amber-500/40 dark:bg-slate-900/40 dark:text-amber-100" />
                                                    </label>
                                                </div>
                                                <div class="mt-3 text-right">
                                                    <button type="submit" class="inline-flex items-center rounded bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-amber-100 dark:focus:ring-offset-slate-900">Simpan</button>
                                                </div>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (($canUpdateReRegistration ?? false) && !empty($reRegistrationStatusOptions)): ?>
                                            <?php
                                                $reRegistrationAction = ($reRegistrationUpdateRoutePrefix ?? 'ppdb/admin/pendaftar') . '/' . (int) $registrant['id'] . '/daftar-ulang';
                                                $reRegistrationAt = $registrant['tanggal_daftar_ulang'] ?? null;
                                                $reRegistrationDate = $reRegistrationAt ? date('Y-m-d', strtotime($reRegistrationAt)) : '';
                                                $reRegistrationTime = $reRegistrationAt ? date('H:i', strtotime($reRegistrationAt)) : '';
                                            ?>
                                            <form action="<?= htmlspecialchars(base_url($reRegistrationAction), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-[11px] dark:border-emerald-500/30 dark:bg-emerald-500/10">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="periode_id" value="<?= (int) $periodId ?>" />
                                                <p class="mb-2 font-semibold text-emerald-700 dark:text-emerald-200">Daftar Ulang</p>
                                                <div class="grid gap-2 sm:grid-cols-2">
                                                    <label class="flex flex-col gap-1">
                                                        <span>Status</span>
                                                        <select name="status_daftar_ulang" class="rounded border border-emerald-200 px-2 py-1 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:border-emerald-500/40 dark:bg-slate-900/40 dark:text-emerald-100">
                                                            <?php foreach ($reRegistrationStatusOptions as $value => $label): ?>
                                                                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($registrant['status_daftar_ulang'] ?? 'menunggu') === (string) $value ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="flex flex-col gap-1">
                                                        <span>Tanggal</span>
                                                        <input type="date" name="daftar_ulang_tanggal" value="<?= htmlspecialchars($reRegistrationDate, ENT_QUOTES, 'UTF-8') ?>" class="rounded border border-emerald-200 px-2 py-1 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:border-emerald-500/40 dark:bg-slate-900/40 dark:text-emerald-100" />
                                                    </label>
                                                    <label class="flex flex-col gap-1">
                                                        <span>Waktu</span>
                                                        <input type="time" name="daftar_ulang_waktu" value="<?= htmlspecialchars($reRegistrationTime, ENT_QUOTES, 'UTF-8') ?>" class="rounded border border-emerald-200 px-2 py-1 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:border-emerald-500/40 dark:bg-slate-900/40 dark:text-emerald-100" />
                                                    </label>
                                                </div>
                                                <div class="mt-3 text-right">
                                                    <button type="submit" class="inline-flex items-center rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-emerald-100 dark:focus:ring-offset-slate-900">Simpan</button>
                                                </div>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (($canUpdatePayment ?? false) && !empty($paymentStatusOptions)): ?>
                                            <?php
                                                $paymentAction = ($paymentUpdateRoutePrefix ?? 'ppdb/admin/pendaftar') . '/' . (int) $registrant['id'] . '/pembayaran';
                                                $paymentDate = $registrant['tanggal_pembayaran'] ?? null;
                                                $paymentDateValue = $paymentDate ? date('Y-m-d', strtotime($paymentDate)) : '';
                                                $paymentTimeValue = $paymentDate ? date('H:i', strtotime($paymentDate)) : '';
                                            ?>
                                            <form action="<?= htmlspecialchars(base_url($paymentAction), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-3 rounded-lg border border-slate-300 bg-white p-3 text-[11px] shadow-sm dark:border-slate-600 dark:bg-slate-800/40">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="periode_id" value="<?= (int) $periodId ?>" />
                                                <p class="mb-2 font-semibold text-slate-700 dark:text-gray-200">Pembayaran</p>
                                                <div class="grid gap-2 sm:grid-cols-2">
                                                    <label class="flex flex-col gap-1">
                                                        <span>Status</span>
                                                        <select name="status_pembayaran" class="rounded border border-slate-300 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-gray-100">
                                                            <?php foreach ($paymentStatusOptions as $value => $label): ?>
                                                                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($registrant['status_pembayaran'] ?? 'menunggu') === (string) $value ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="flex flex-col gap-1">
                                                        <span>Nominal (Rp)</span>
                                                        <input type="text" name="nominal_pembayaran" value="<?= htmlspecialchars($registrant['nominal_pembayaran'] !== null ? (string) $registrant['nominal_pembayaran'] : '', ENT_QUOTES, 'UTF-8') ?>" class="rounded border border-slate-300 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-gray-100" />
                                                    </label>
                                                    <label class="flex flex-col gap-1">
                                                        <span>Tanggal</span>
                                                        <input type="date" name="pembayaran_tanggal" value="<?= htmlspecialchars($paymentDateValue, ENT_QUOTES, 'UTF-8') ?>" class="rounded border border-slate-300 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-gray-100" />
                                                    </label>
                                                    <label class="flex flex-col gap-1">
                                                        <span>Waktu</span>
                                                        <input type="time" name="pembayaran_waktu" value="<?= htmlspecialchars($paymentTimeValue, ENT_QUOTES, 'UTF-8') ?>" class="rounded border border-slate-300 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-gray-100" />
                                                    </label>
                                                </div>
                                                <div class="mt-3 text-right">
                                                    <button type="submit" class="inline-flex items-center rounded bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 focus:ring-offset-slate-100 dark:focus:ring-offset-slate-900">Simpan</button>
                                                </div>
                                            </form>
                                        <?php endif; ?>
                                    </td>
	                                    <td class="px-4 py-4 align-top text-sm font-semibold text-indigo-600 dark:text-indigo-300">
	                                        <?= htmlspecialchars($registrant['kode_pendaftaran'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
	                                    </td>
	                                    <td class="px-4 py-4 align-top">
	                                        <?php $deleteAction = ($deleteRoutePrefix ?? 'ppdb/admin/pendaftar') . '/' . (int) $registrant['id'] . '/hapus'; ?>
	                                        <form action="<?= htmlspecialchars(base_url($deleteAction), ENT_QUOTES, 'UTF-8') ?>" method="post" class="flex justify-end" onsubmit="return confirm('Hapus pendaftar PPDB ini? Riwayat pembayaran PPDB ikut terhapus. Data siswa hasil migrasi tidak ikut terhapus.');">
	                                            <?= csrf_field() ?>
	                                            <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-600 shadow-sm hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 dark:border-rose-500/40 dark:bg-slate-900 dark:text-rose-200 dark:hover:bg-rose-500/10 dark:focus:ring-offset-slate-900">
	                                                <i class="ri-delete-bin-line mr-1 text-sm"></i>
	                                                Hapus
	                                            </button>
	                                        </form>
	                                    </td>
	                                </tr>
	                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-lg font-semibold text-slate-800 dark:text-gray-100">Ringkasan Tahapan</h2>
                <?php if ($period === null): ?>
                    <p class="mt-3 text-sm text-slate-500 dark:text-gray-400">Pilih periode untuk melihat ringkasan tahapan.</p>
                <?php else: ?>
                    <?php
                        $stageStatus = [
                            'pendaftaran' => ['label' => 'Pendaftaran', 'column' => 'pendaftaran_diaktifkan'],
                            'seleksi' => ['label' => 'Seleksi Akademik', 'column' => 'seleksi_diaktifkan'],
                            'pengumuman' => ['label' => 'Pengumuman', 'column' => 'pengumuman_diaktifkan'],
                            'daftar_ulang' => ['label' => 'Daftar Ulang', 'column' => 'daftar_ulang_diaktifkan'],
                            'pembayaran' => ['label' => 'Pembayaran', 'column' => 'pembayaran_diaktifkan'],
                        ];
                    ?>
                    <ul class="mt-4 space-y-2 text-sm">
                        <?php foreach ($stageStatus as $stage => $info): ?>
                            <?php $enabled = !empty($period[$info['column']]); ?>
                            <li class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700">
                                <span class="font-medium text-slate-700 dark:text-gray-200"><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $enabled ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-slate-200 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300' ?>">
                                    <?= $enabled ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if ($showManualForm && $period !== null && !($canManualRegister ?? false)): ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-lg font-semibold text-slate-800 dark:text-gray-100">Input Pendaftar Manual</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-gray-400">Tahap pendaftaran sedang dinonaktifkan, tombol tambah data tidak tersedia.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

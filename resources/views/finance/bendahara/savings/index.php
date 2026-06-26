<?php
/** @var array<string, mixed> $filters */
/** @var array<int, array<string, mixed>> $classOptions */
/** @var array<int, array<string, mixed>> $students */
/** @var array<int, array<string, mixed>> $studentOptions */
/** @var float $totalBalance */
/** @var int $activeAccounts */
/** @var bool $hasActiveYear */
/** @var string $defaultTransactionTime */
/** @var float $overallBalance */
/** @var int $overallAccounts */
/** @var float $borrowedFromSavings */
/** @var float $validSavingsBalance */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$selectedClassId = (int) ($filters['class_id'] ?? 0);
$searchQuery = (string) ($filters['query'] ?? '');
$selectedStudentId = (int) ($filters['student_id'] ?? 0);
?>

<div class="space-y-8">
    <?php if (!$hasActiveYear): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="font-semibold">Tahun ajaran aktif belum ditetapkan.</p>
            <p class="mt-1">Tetapkan tahun ajaran aktif terlebih dahulu agar pencatatan tabungan dapat dilakukan.</p>
        </div>
    <?php endif; ?>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Tabungan Siswa</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Pantau saldo tabungan seluruh siswa dan catat transaksi setor maupun tarik dengan cepat.
                </p>
            </div>
        </div>

        <form method="get" class="mt-5 grid gap-4 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <label for="filter-class" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Pilih Kelas</label>
                <select
                    id="filter-class"
                    name="class_id"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                    <option value="">Semua kelas</option>
                    <?php foreach ($classOptions as $option): ?>
                        <?php $optionId = (int) ($option['id'] ?? 0); ?>
                        <option value="<?= $optionId ?>" <?= $selectedClassId === $optionId ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($option['label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lg:col-span-4">
                <label for="filter-query" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Cari Siswa</label>
                <input
                    type="text"
                    id="filter-query"
                    name="q"
                    value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Nama, NIPD, atau NISN"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
            </div>
            <div class="lg:col-span-4 flex items-end gap-3">
                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                >
                    Terapkan Filter
                </button>
                <?php if ($selectedClassId > 0 || $searchQuery !== ''): ?>
                    <a
                        href="<?= htmlspecialchars(base_url('keuangan/bendahara/tabungan'), ENT_QUOTES, 'UTF-8') ?>"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-slate-200/60 bg-white/80 p-5 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Saldo Keseluruhan</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency($overallBalance), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?= number_format($overallAccounts, 0, ',', '.') ?> akun aktif</p>
        </div>
        <div class="rounded-xl border border-slate-200/60 bg-white/80 p-5 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Saldo Valid</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-600 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency($validSavingsBalance), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Dapat dicairkan tanpa mengganggu pinjaman</p>
        </div>
        <div class="rounded-xl border border-amber-200/70 bg-amber-50 p-5 text-amber-700 shadow-sm dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="text-xs uppercase tracking-wide font-semibold">Saldo Terpinjam</p>
            <p class="mt-2 text-2xl font-semibold"><?= htmlspecialchars($formatCurrency($borrowedFromSavings), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-xs">Dana tabungan yang sementara digunakan oleh kas utama.</p>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="border-b border-slate-200/60 pb-4 dark:border-slate-700/60">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Catat Transaksi Tabungan</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Pilih siswa, tentukan jenis transaksi, dan masukkan nominal untuk mencatat setor atau tarik tabungan.
            </p>
        </div>

        <?php if (!$hasActiveYear): ?>
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                <p>Aktifkan tahun ajaran terlebih dahulu untuk mencatat transaksi tabungan.</p>
            </div>
        <?php elseif (empty($studentOptions)): ?>
            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-300">
                <p>Pilih kelas atau gunakan pencarian di atas untuk menampilkan siswa yang akan dicatat tabungannya.</p>
            </div>
        <?php else: ?>
            <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/tabungan'), ENT_QUOTES, 'UTF-8') ?>" class="mt-5 grid gap-4 lg:grid-cols-12">
                <?= csrf_field() ?>
                <input type="hidden" name="class_id" value="<?= $selectedClassId > 0 ? $selectedClassId : '' ?>">
                <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">

                <div class="lg:col-span-4">
                    <label for="transaction-student" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Siswa</label>
                    <select
                        id="transaction-student"
                        name="student_id"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                        <option value="">Pilih siswa</option>
                        <?php foreach ($studentOptions as $id => $option): ?>
                            <?php
                                $optionLabel = (string) ($option['label'] ?? '');
                                $optionDisabled = !empty($option['disabled']);
                            ?>
                            <option value="<?= (int) $id ?>" <?= $selectedStudentId === (int) $id ? 'selected' : '' ?> <?= $optionDisabled ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($optionLabel . ($optionDisabled ? ' - Nonaktif' : ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="lg:col-span-2">
                    <label for="transaction-type" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Jenis Transaksi</label>
                    <select
                        id="transaction-type"
                        name="type"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                        <option value="setor">Setor</option>
                        <option value="tarik">Tarik</option>
                    </select>
                </div>

                <div class="lg:col-span-3">
                    <label for="transaction-amount" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Nominal</label>
                    <input
                        type="text"
                        id="transaction-amount"
                        name="amount"
                        placeholder="cth. 250000"
                        inputmode="decimal"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        required
                    >
                </div>

                <div class="lg:col-span-3">
                    <label for="transaction-time" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tanggal &amp; Waktu</label>
                    <input
                        type="datetime-local"
                        id="transaction-time"
                        name="transaction_time"
                        value="<?= htmlspecialchars($defaultTransactionTime, ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        required
                    >
                </div>

                <div class="lg:col-span-12">
                    <label for="transaction-note" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Catatan (opsional)</label>
                    <textarea
                        id="transaction-note"
                        name="note"
                        rows="2"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        placeholder="Tambahkan keterangan untuk memudahkan pelacakan transaksi"
                    ></textarea>
                </div>

                <div class="lg:col-span-12 flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                    >
                        Simpan Transaksi
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php if (!empty($students)): ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200/60 bg-white/80 p-4 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <p class="text-sm text-slate-500 dark:text-slate-400">Tabungan aktif</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"><?= number_format($activeAccounts, 0, ',', '.') ?></p>
            </div>
            <div class="rounded-xl border border-sky-200/60 bg-sky-50 p-4 shadow-sm shadow-sky-100 dark:border-sky-500/40 dark:bg-sky-500/10">
                <p class="text-sm font-medium text-sky-700 dark:text-sky-200">Total saldo tabungan</p>
                <p class="mt-1 text-2xl font-semibold text-sky-800 dark:text-sky-100">
                    <?= htmlspecialchars($formatCurrency((float) $totalBalance), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Daftar Tabungan Siswa</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/40 dark:text-slate-400">
                        <tr>
                            <th class="px-6 py-3">Siswa</th>
                            <th class="px-6 py-3">Identitas</th>
                            <th class="px-6 py-3 text-right">Saldo Terakhir</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Histori</th>
                            <th class="px-6 py-3">Transaksi Terakhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white/70 text-slate-700 dark:divide-slate-800 dark:bg-slate-900/40 dark:text-slate-200">
                        <?php foreach ($students as $student): ?>
                            <?php
                                $saving = $student['saving'] ?? null;
                                $lastTransaction = is_array($saving) ? ($saving['last_transaction'] ?? null) : null;
                                $hasSaving = is_array($saving);
                                $balance = $hasSaving ? (float) ($saving['saldo_terakhir'] ?? 0.0) : 0.0;
                            ?>
                            <tr>
                                <td class="px-6 py-4 align-top">
                                    <div class="font-semibold text-slate-900 dark:text-white">
                                        <?= htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?= student_status_badge($student['student'] ?? [], 'ml-1 align-middle') ?>
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        <?= htmlspecialchars($student['class_label'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-top text-xs text-slate-500 dark:text-slate-400">
                                    <?php if (!empty($student['student']['nipd'])): ?>
                                        <div>NIPD: <?= htmlspecialchars((string) $student['student']['nipd'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($student['student']['nisn'])): ?>
                                        <div>NISN: <?= htmlspecialchars((string) $student['student']['nisn'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 align-top text-right font-semibold <?= $hasSaving ? 'text-slate-900 dark:text-white' : 'text-slate-400 dark:text-slate-500' ?>">
                                    <?= htmlspecialchars($formatCurrency($balance), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <?php if ($hasSaving): ?>
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200">
                                            <?= htmlspecialchars(strtoupper((string) ($saving['status'] ?? 'aktif')), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                            Belum aktif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 align-top text-xs text-slate-500 dark:text-slate-400">
                                    <?php
                                    $history = $hasSaving ? ($saving['history'] ?? []) : [];
                                    ?>
                                    <?php if ($hasSaving && !empty($history)): ?>
                                        <details class="group [&_summary]:cursor-pointer">
                                            <summary class="text-slate-600 underline-offset-2 transition hover:underline dark:text-slate-300">
                                                Lihat riwayat (<?= count($history) ?>)
                                            </summary>
                                            <ul class="mt-3 space-y-2">
                                                <?php foreach ($history as $entry): ?>
                                                    <li class="rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                                                        <div class="flex items-center justify-between gap-3">
                                                            <span class="font-semibold <?= ($entry['jenis'] ?? '') === 'setor' ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' ?>">
                                                                <?= htmlspecialchars(ucfirst((string) ($entry['jenis'] ?? '-')), ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                            <span class="font-semibold text-slate-800 dark:text-slate-200">
                                                                <?= htmlspecialchars($formatCurrency((float) ($entry['nominal'] ?? 0.0)), ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                        </div>
                                                        <p class="mt-1 text-xs"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($entry['tanggal'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                                                        <?php if (!empty($entry['kode_transaksi'])): ?>
                                                            <p class="text-xs text-slate-400 dark:text-slate-500">#<?= htmlspecialchars((string) $entry['kode_transaksi'], ENT_QUOTES, 'UTF-8') ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($entry['catatan'])): ?>
                                                            <p class="mt-1 text-xs italic text-slate-500 dark:text-slate-400">“<?= htmlspecialchars((string) $entry['catatan'], ENT_QUOTES, 'UTF-8') ?>”</p>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    <?php elseif ($hasSaving): ?>
                                        <span class="text-slate-400 dark:text-slate-500">Belum ada histori.</span>
                                    <?php else: ?>
                                        <span class="text-slate-400 dark:text-slate-600">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 align-top text-xs text-slate-500 dark:text-slate-400">
                                    <?php if (is_array($lastTransaction)): ?>
                                        <div class="font-semibold text-slate-700 dark:text-slate-200">
                                            <?= htmlspecialchars(ucfirst((string) ($lastTransaction['jenis'] ?? '-')), ENT_QUOTES, 'UTF-8') ?>
                                            <?= htmlspecialchars($formatCurrency((float) ($lastTransaction['nominal'] ?? 0.0)), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div>
                                            <?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($lastTransaction['tanggal'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <?php if (!empty($lastTransaction['kode_transaksi'])): ?>
                                            <div class="text-slate-400 dark:text-slate-500">
                                                #<?= htmlspecialchars((string) $lastTransaction['kode_transaksi'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-400 dark:text-slate-500">Belum ada transaksi</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($hasActiveYear): ?>
        <div class="rounded-xl border border-slate-200/60 bg-white/80 px-6 py-5 text-sm text-slate-500 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:text-slate-400 dark:shadow-none">
            <p>Belum ada siswa untuk filter yang dipilih. Pilih kelas lain atau gunakan pencarian untuk menemukan siswa.</p>
        </div>
    <?php endif; ?>
</div>

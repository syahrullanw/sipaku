<?php
/** @var bool $hasActiveYear */
/** @var array<int, array<string, mixed>> $students */
/** @var array<int, array{id: int, label: string}> $classOptions */
/** @var array<string, mixed> $summary */
/** @var array<string, mixed> $filters */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$selectedClassId = (int) ($filters['class_id'] ?? 0);
$searchQuery = (string) ($filters['query'] ?? '');

$billingStatusStyles = [
    'menunggu_pembayaran' => ['label' => 'Menunggu Pembayaran', 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'],
    'menunggu_verifikasi' => ['label' => 'Menunggu Verifikasi', 'class' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300'],
    'cicilan_berjalan' => ['label' => 'Cicilan Berjalan', 'class' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300'],
    'lunas' => ['label' => 'Lunas', 'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'],
    'gagal' => ['label' => 'Dibatalkan', 'class' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300'],
    'dibatalkan' => ['label' => 'Dibatalkan', 'class' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300'],
];

$paymentStatusStyles = [
    'menunggu_verifikasi' => ['label' => 'Menunggu Verifikasi', 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'],
    'disetujui' => ['label' => 'Disetujui', 'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'],
    'ditolak' => ['label' => 'Ditolak', 'class' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300'],
];

$paymentMethodLabels = [
    'tunai' => 'Tunai',
    'transfer' => 'Transfer',
    'tabungan' => 'Tabungan',
];
?>

<div class="space-y-8">
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-slate-200/60 bg-white/70 p-5 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Siswa</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= number_format((int) ($summary['total_students'] ?? 0), 0, ',', '.') ?></p>
        </div>
        <div class="rounded-xl border border-sky-200/60 bg-sky-50 p-5 shadow-sm shadow-sky-100 dark:border-sky-500/40 dark:bg-sky-500/10 dark:shadow-none">
            <p class="text-sm font-medium text-sky-700 dark:text-sky-200">Total Nominal Tagihan</p>
            <p class="mt-2 text-2xl font-semibold text-sky-800 dark:text-sky-100"><?= htmlspecialchars($formatCurrency((float) ($summary['total_billed'] ?? 0.0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-xl border border-emerald-200/60 bg-emerald-50 p-5 shadow-sm shadow-emerald-100 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:shadow-none">
            <p class="text-sm font-medium text-emerald-700 dark:text-emerald-200">Total Pembayaran Diterima</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-800 dark:text-emerald-100"><?= htmlspecialchars($formatCurrency((float) ($summary['total_paid'] ?? 0.0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-xl border border-amber-200/60 bg-amber-50 p-5 shadow-sm shadow-amber-100 dark:border-amber-500/40 dark:bg-amber-500/10 dark:shadow-none">
            <p class="text-sm font-medium text-amber-700 dark:text-amber-200">Total Piutang Tagihan</p>
            <p class="mt-2 text-2xl font-semibold text-amber-800 dark:text-amber-100"><?= htmlspecialchars($formatCurrency((float) ($summary['total_outstanding'] ?? 0.0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-xl border border-slate-200/60 bg-white/70 p-5 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Saldo Tabungan</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($summary['total_savings'] ?? 0.0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Filter Data Siswa</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Gunakan filter untuk memfokuskan kelas atau mencari siswa tertentu.</p>
            </div>
            <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/rekap-siswa'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Reset Filter</a>
        </div>
        <form action="<?= htmlspecialchars(base_url('keuangan/bendahara/rekap-siswa'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="mt-5 grid gap-4 md:grid-cols-3 lg:grid-cols-4">
            <div>
                <label for="class_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Kelas</label>
                <select id="class_id" name="class_id" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                    <option value="0">Semua kelas</option>
                    <?php foreach ($classOptions as $option): ?>
                        <?php $optionId = (int) ($option['id'] ?? 0); ?>
                        <option value="<?= htmlspecialchars((string) $optionId, ENT_QUOTES, 'UTF-8') ?>" <?= $optionId === $selectedClassId ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($option['label'] ?? ('Kelas #' . $optionId)), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2 lg:col-span-2">
                <label for="search_q" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama / NIPD / NISN</label>
                <input type="text" id="search_q" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cari siswa berdasarkan nama atau identitas" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200" />
            </div>
            <div class="md:col-span-1 lg:col-span-1 flex items-end">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 dark:bg-sky-500 dark:hover:bg-sky-600 dark:focus:ring-offset-slate-900">Terapkan Filter</button>
            </div>
        </form>
    </div>

    <?php if (!$hasActiveYear): ?>
        <div class="rounded-xl border border-amber-200/60 bg-amber-50 p-5 text-sm text-amber-700 shadow-sm shadow-amber-100 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            Tahun ajaran aktif belum ditetapkan. Set tahun ajaran melalui menu konteks untuk menampilkan data tagihan siswa.
        </div>
    <?php elseif (empty($students)): ?>
        <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 text-sm text-slate-500 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:text-slate-300">
            Belum ada data siswa yang cocok dengan filter saat ini.
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($students as $student): ?>
                <?php
                    $summaryStudent = $student['summary'] ?? [];
                    $totalBilled = (float) ($summaryStudent['total_billed'] ?? 0.0);
                    $totalPaid = (float) ($summaryStudent['total_paid'] ?? 0.0);
                    $totalOutstanding = (float) ($summaryStudent['total_outstanding'] ?? 0.0);
                    $lastPaymentAt = (string) ($summaryStudent['last_payment_at'] ?? '');
                    $savings = $student['savings'] ?? null;
                    $savingsBalance = (float) ($savings['saldo_terakhir'] ?? 0.0);
                    $billings = is_array($student['billings'] ?? null) ? $student['billings'] : [];
                    $payments = is_array($student['payments'] ?? null) ? $student['payments'] : [];
                    $activeBilling = (int) ($summaryStudent['active_billing'] ?? 0);
                    $completedBilling = (int) ($summaryStudent['completed_billing'] ?? 0);
                ?>
                <details class="group rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 open:shadow-none dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none" <?= $searchQuery !== '' ? 'open' : '' ?>>
                    <summary class="flex cursor-pointer flex-col gap-4 px-4 py-5 sm:px-6 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-base font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars((string) ($student['name'] ?? 'Siswa'), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars((string) ($student['class_label'] ?? 'Kelas tidak diketahui'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                                    <?= number_format($activeBilling, 0, ',', '.') ?> tagihan aktif
                                </span>
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                                    <?= number_format($completedBilling, 0, ',', '.') ?> tagihan lunas
                                </span>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-4">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Total Tagihan</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency($totalBilled), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Pembayaran Masuk</p>
                                <p class="mt-1 text-sm font-semibold text-emerald-700 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency($totalPaid), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Sisa Tagihan</p>
                                <p class="mt-1 text-sm font-semibold text-amber-700 dark:text-amber-300"><?= htmlspecialchars($formatCurrency($totalOutstanding), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Saldo Tabungan</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency($savingsBalance), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                    </summary>
                    <div class="border-t border-slate-200/60 px-4 py-5 sm:px-6 dark:border-slate-700/70">
                        <div class="grid gap-6 lg:grid-cols-4">
                            <div class="lg:col-span-1">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Profil Siswa</h3>
                                <dl class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                    <?php
                                        $studentRaw = $student['student'] ?? [];
                                        $nipd = trim((string) ($studentRaw['nipd'] ?? ''));
                                        $nisn = trim((string) ($studentRaw['nisn'] ?? ''));
                                        $hp = trim((string) ($student['contact']['hp'] ?? ''));
                                        $phone = trim((string) ($student['contact']['telepon'] ?? ''));
                                    ?>
                                    <div>
                                        <dt class="font-medium text-slate-500 dark:text-slate-400">NIPD</dt>
                                        <dd><?= htmlspecialchars($nipd !== '' ? $nipd : '-', ENT_QUOTES, 'UTF-8') ?></dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-slate-500 dark:text-slate-400">NISN</dt>
                                        <dd><?= htmlspecialchars($nisn !== '' ? $nisn : '-', ENT_QUOTES, 'UTF-8') ?></dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-slate-500 dark:text-slate-400">Kontak</dt>
                                        <dd>
                                            <?php if ($hp !== ''): ?>
                                                <div>HP: <?= htmlspecialchars($hp, ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                            <?php if ($phone !== ''): ?>
                                                <div>Telepon: <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                            <?php if ($hp === '' && $phone === ''): ?>
                                                <div>-</div>
                                            <?php endif; ?>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-slate-500 dark:text-slate-400">Pembayaran Terakhir</dt>
                                        <dd>
                                            <?php if ($lastPaymentAt !== '' && strtotime($lastPaymentAt) !== false): ?>
                                                <?= htmlspecialchars(date('d M Y H:i', strtotime($lastPaymentAt)), ENT_QUOTES, 'UTF-8') ?>
                                            <?php else: ?>
                                                <span class="text-slate-400 dark:text-slate-500">Belum ada pembayaran</span>
                                            <?php endif; ?>
                                        </dd>
                                    </div>
                                </dl>
                                <?php if ($savings !== null): ?>
                                    <div class="mt-4 rounded-lg border border-slate-200/70 bg-slate-50 p-4 text-sm shadow-sm dark:border-slate-700/70 dark:bg-slate-800/40">
                                        <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tabungan Aktif</h4>
                                        <p class="mt-1 text-slate-500 dark:text-slate-400">Saldo valid: <?= htmlspecialchars($formatCurrency($savingsBalance), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Status: <?= htmlspecialchars((string) ($savings['status'] ?? 'aktif'), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                <?php else: ?>
                                    <p class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-500 shadow-sm dark:bg-slate-800/40 dark:text-slate-400">
                                        Tabungan siswa belum diaktifkan.
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="space-y-6 lg:col-span-3">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Tagihan Siswa</h3>
                                        <span class="text-xs font-medium text-slate-400 dark:text-slate-500"><?= number_format(is_countable($billings) ? count($billings) : 0, 0, ',', '.') ?> item</span>
                                    </div>
                                    <?php if (empty($billings)): ?>
                                        <p class="mt-3 rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-500 dark:bg-slate-800/40 dark:text-slate-400">
                                            Siswa belum mempunyai tagihan pada tahun ajaran ini.
                                        </p>
                                    <?php else: ?>
                                        <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200/60 dark:border-slate-700/70">
                                            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                                                <thead class="bg-slate-50 dark:bg-slate-800/60">
                                                    <tr>
                                                        <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Tagihan</th>
                                                        <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Nominal</th>
                                                        <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Terbayar</th>
                                                        <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Sisa</th>
                                                        <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                                    <?php foreach ($billings as $item): ?>
                                                        <?php
                                                            $statusKey = (string) ($item['status'] ?? '');
                                                            $statusInfo = $billingStatusStyles[$statusKey] ?? ['label' => ucfirst(str_replace('_', ' ', $statusKey)), 'class' => 'bg-slate-100 text-slate-600 dark:bg-slate-800/40 dark:text-slate-300'];
                                                            $nominal = (float) ($item['nominal'] ?? 0.0);
                                                            $remaining = (float) ($item['sisa_nominal'] ?? 0.0);
                                                            $paid = max(0.0, $nominal - $remaining);
                                                            $dueDate = (string) ($item['tanggal_jatuh_tempo'] ?? '');
                                                        ?>
                                                        <tr class="bg-white/90 hover:bg-slate-50 dark:bg-slate-900/40 dark:hover:bg-slate-800/50">
                                                            <td class="px-4 py-3 align-top">
                                                                <p class="font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars((string) ($item['tagihan_judul'] ?? 'Tagihan'), ENT_QUOTES, 'UTF-8') ?></p>
                                                                <p class="text-xs text-slate-500 dark:text-slate-400">Kode: <?= htmlspecialchars((string) ($item['tagihan_kode'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                                                <?php if ($dueDate !== ''): ?>
                                                                    <p class="text-xs text-slate-400 dark:text-slate-500">Jatuh tempo: <?= htmlspecialchars(date('d M Y', strtotime($dueDate)), ENT_QUOTES, 'UTF-8') ?></p>
                                                                <?php endif; ?>
                                                                <?php if (!empty($item['kategori_nama'])): ?>
                                                                    <p class="text-xs text-slate-400 dark:text-slate-500">Kategori: <?= htmlspecialchars((string) $item['kategori_nama'], ENT_QUOTES, 'UTF-8') ?></p>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="px-4 py-3 align-top text-slate-700 dark:text-slate-200"><?= htmlspecialchars($formatCurrency($nominal), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-4 py-3 align-top text-emerald-700 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency($paid), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-4 py-3 align-top text-amber-700 dark:text-amber-300"><?= htmlspecialchars($formatCurrency(max(0.0, $remaining)), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-4 py-3 align-top">
                                                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusInfo['class'] ?>">
                                                                    <?= htmlspecialchars((string) $statusInfo['label'], ENT_QUOTES, 'UTF-8') ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Riwayat Pembayaran</h3>
                                        <span class="text-xs font-medium text-slate-400 dark:text-slate-500"><?= number_format(is_countable($payments) ? count($payments) : 0, 0, ',', '.') ?> transaksi</span>
                                    </div>
                                    <?php if (empty($payments)): ?>
                                        <p class="mt-3 rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-500 dark:bg-slate-800/40 dark:text-slate-400">
                                            Belum ada pembayaran yang tercatat untuk siswa ini.
                                        </p>
                                    <?php else: ?>
                                        <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200/60 dark:border-slate-700/70">
                                            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                                                <thead class="bg-slate-50 dark:bg-slate-800/60">
                                                    <tr>
                                                        <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Tanggal</th>
                                                        <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Kode</th>
                                                        <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Tagihan</th>
                                                        <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Metode</th>
                                                        <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Nominal</th>
                                                        <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Sisa Setelah</th>
                                                        <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                                    <?php foreach ($payments as $payment): ?>
                                                        <?php
                                                            $paymentStatusKey = (string) ($payment['status'] ?? '');
                                                            $paymentStatus = $paymentStatusStyles[$paymentStatusKey] ?? ['label' => ucfirst(str_replace('_', ' ', $paymentStatusKey)), 'class' => 'bg-slate-100 text-slate-600 dark:bg-slate-800/40 dark:text-slate-300'];
                                                            $methodKey = (string) ($payment['metode'] ?? '');
                                                            $paymentMethod = $paymentMethodLabels[$methodKey] ?? ucfirst($methodKey);
                                                            $paidAmount = (float) ($payment['nominal'] ?? 0.0);
                                                            $remainingAfter = (float) ($payment['sisa_setelah'] ?? 0.0);
                                                            $paidAt = (string) ($payment['tanggal_bayar'] ?? '');
                                                        ?>
                                                        <tr class="bg-white/90 hover:bg-slate-50 dark:bg-slate-900/40 dark:hover:bg-slate-800/50">
                                                            <td class="px-4 py-3 align-top text-slate-600 dark:text-slate-300"><?= htmlspecialchars($paidAt !== '' ? date('d M Y H:i', strtotime($paidAt)) : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-4 py-3 align-top text-slate-600 dark:text-slate-300"><?= htmlspecialchars((string) ($payment['kode_transaksi'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-4 py-3 align-top">
                                                                <p class="text-slate-700 dark:text-slate-200"><?= htmlspecialchars((string) ($payment['tagihan_judul'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                                                <p class="text-xs text-slate-400 dark:text-slate-500">Kode: <?= htmlspecialchars((string) ($payment['tagihan_kode'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                                            </td>
                                                            <td class="px-4 py-3 align-top text-slate-600 dark:text-slate-300"><?= htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-4 py-3 align-top text-emerald-700 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency($paidAmount), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-4 py-3 align-top text-amber-700 dark:text-amber-300"><?= htmlspecialchars($formatCurrency($remainingAfter), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-4 py-3 align-top">
                                                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $paymentStatus['class'] ?>">
                                                                    <?= htmlspecialchars((string) $paymentStatus['label'], ENT_QUOTES, 'UTF-8') ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

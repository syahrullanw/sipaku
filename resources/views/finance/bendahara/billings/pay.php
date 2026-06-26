<?php
/** @var array<string, mixed> $billing */
/** @var array<int, array<string, mixed>> $billingItems */
/** @var float $totalRemaining */
/** @var int $selectableCount */

$weekdayNames = [
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
    7 => 'Minggu',
];

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$recurringType = (string) ($billing['rutin_tipe'] ?? 'tidak');
$recurringLabel = match ($recurringType) {
    'mingguan' => 'Rutin mingguan',
    'bulanan' => 'Rutin bulanan',
    default => 'Sekali',
};
$isRecurring = in_array($recurringType, ['mingguan', 'bulanan'], true);
$nextSchedule = $billing['rutin_jadwal_berikutnya']
    ? date('d M Y', strtotime((string) $billing['rutin_jadwal_berikutnya']))
    : '-';
$lastGenerated = $billing['rutin_terakhir_generate']
    ? date('d M Y', strtotime((string) $billing['rutin_terakhir_generate']))
    : '-';
$dueLabel = $billing['tanggal_jatuh_tempo']
    ? date('d M Y', strtotime((string) $billing['tanggal_jatuh_tempo']))
    : '-';
$weeklyDay = isset($billing['rutin_hari_mingguan'])
    ? ($weekdayNames[(int) $billing['rutin_hari_mingguan']] ?? null)
    : null;
$monthlyDate = isset($billing['rutin_tanggal_bulanan'])
    ? (int) $billing['rutin_tanggal_bulanan']
    : null;
?>

<?php
$generatedPaymentIds = session_flash('generated_payment_ids', []);
if (!is_array($generatedPaymentIds)) {
    $generatedPaymentIds = [];
}
?>

<div class="mb-6 flex items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($billing['judul'] ?? 'Tagihan', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Kode: <?= htmlspecialchars($billing['kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?> •
            Kategori: <?= htmlspecialchars($billing['kategori_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?> •
            Jenis: <?= htmlspecialchars($recurringLabel, ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
    <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/tagihan'), ENT_QUOTES, 'UTF-8') ?>"
       class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
        &larr; Kembali ke daftar tagihan
    </a>
</div>

<?php if (!empty($generatedPaymentIds)): ?>
    <div class="mb-6 rounded-xl border border-emerald-300/70 bg-emerald-50 px-6 py-5 text-sm text-emerald-800 shadow-sm dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
        <h3 class="text-base font-semibold">Slip pembayaran berhasil dibuat</h3>
        <p class="mt-2 text-xs">Unduh atau cetak bukti resmi untuk diserahkan kepada siswa.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($generatedPaymentIds as $paymentId): ?>
                <div class="inline-flex items-center gap-2">
                    <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/pembayaran/' . $paymentId . '/slip'), ENT_QUOTES, 'UTF-8') ?>"
                       target="_blank"
                       rel="noopener"
                       class="inline-flex items-center rounded-lg bg-emerald-500 px-3 py-1 text-xs font-semibold text-white shadow-sm hover:bg-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-400/70 focus:ring-offset-1 dark:focus:ring-offset-slate-900">
                        Lihat Slip #<?= htmlspecialchars((string) $paymentId, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/pembayaran/' . $paymentId . '/slip/pdf'), ENT_QUOTES, 'UTF-8') ?>"
                       class="inline-flex items-center rounded-lg border border-emerald-500 px-3 py-1 text-xs font-semibold text-emerald-600 shadow-sm hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-400/70 focus:ring-offset-1 dark:border-emerald-400/60 dark:text-emerald-200 dark:hover:bg-emerald-500/10 dark:focus:ring-offset-slate-900">
                        Unduh PDF
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="mt-2 text-xs text-emerald-700 dark:text-emerald-300">Slip juga tersedia melalui menu "Pembayaran Siswa" untuk akses ulang.</p>
    </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/60 dark:bg-slate-900/60 dark:shadow-none">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Ringkasan Tagihan</h2>
        <dl class="space-y-3 text-sm text-slate-700 dark:text-slate-200">
            <div class="flex justify-between">
                <dt>Total penerima</dt>
                <dd><?= number_format(count($billingItems), 0, ',', '.') ?> siswa</dd>
            </div>
            <div class="flex justify-between">
                <dt>Total sisa tagihan</dt>
                <dd class="font-semibold text-amber-600 dark:text-amber-300"><?= htmlspecialchars($formatCurrency($totalRemaining), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div class="flex justify-between">
                <dt>Saldo kas tagihan</dt>
                <dd class="font-semibold text-emerald-600 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency((float) ($billing['kas_saldo'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div class="flex justify-between">
                <dt>Metode penagihan</dt>
                <dd><?= htmlspecialchars($recurringLabel, ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php if ($recurringType === 'mingguan' && $weeklyDay !== null): ?>
                <div class="flex justify-between">
                    <dt>Hari penagihan</dt>
                    <dd><?= htmlspecialchars($weeklyDay, ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
            <?php elseif ($recurringType === 'bulanan' && $monthlyDate !== null): ?>
                <div class="flex justify-between">
                    <dt>Tanggal penagihan</dt>
                    <dd>Tanggal <?= htmlspecialchars((string) $monthlyDate, ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
            <?php endif; ?>
            <?php if ($recurringType !== 'tidak'): ?>
                <div class="flex justify-between">
                    <dt>Jadwal berikutnya</dt>
                    <dd><?= htmlspecialchars($nextSchedule, ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt>Terakhir generate</dt>
                    <dd><?= htmlspecialchars($lastGenerated, ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
            <?php else: ?>
                <div class="flex justify-between">
                    <dt>Jatuh tempo</dt>
                    <dd><?= htmlspecialchars($dueLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
            <?php endif; ?>
        </dl>
        <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
            <?php if ($isRecurring): ?>
                Pilih siswa yang membayar ke bendahara. Untuk tagihan rutin dengan tunggakan lebih dari satu minggu, tentukan sampai minggu ke berapa pembayaran akan dilunaskan atau pilih opsi lunas semua.
            <?php else: ?>
                Pilih siswa yang membayar ke bendahara. Tentukan apakah pembayaran dilunasi penuh atau masukkan nominal untuk pembayaran sebagian.
            <?php endif; ?>
        </p>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            Setelah disimpan, sistem otomatis menghasilkan bukti pembayaran digital yang dapat diunduh atau dicetak dari menu riwayat pembayaran.
        </p>
    </div>

    <div class="lg:col-span-2 rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/60 dark:bg-slate-900/60 dark:shadow-none">
        <form action="<?= htmlspecialchars(base_url('keuangan/bendahara/tagihan/' . ($billing['id'] ?? 0) . '/pembayaran'), ENT_QUOTES, 'UTF-8') ?>" method="post" id="billing-payment-form" class="flex h-full flex-col">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="post">

            <div class="mb-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                        Total dipilih: <span id="selected-count" class="font-bold"><?= number_format(0, 0, ',', '.') ?></span> siswa
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        <?php if ($isRecurring): ?>
                            Nilai pembayaran akan otomatis menyesuaikan dengan jumlah minggu yang dipilih.
                        <?php else: ?>
                            Nilai pembayaran akan mengikuti pilihan bayar penuh atau nominal pembayaran sebagian.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                        Total pembayaran: <span id="selected-total" class="font-bold text-emerald-600 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency(0), ENT_QUOTES, 'UTF-8') ?></span>
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Sisa tagihan keseluruhan: <?= htmlspecialchars($formatCurrency($totalRemaining), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            </div>

            <div class="mb-4 grid gap-4 md:grid-cols-2">
                <div>
                    <label for="payment-method" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Metode pembayaran</label>
                    <select id="payment-method" name="metode" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer</option>
                        <option value="tabungan">Saldo Tabungan</option>
                    </select>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Metode “Saldo Tabungan” akan langsung memotong tabungan siswa yang dipilih apabila saldonya mencukupi.</p>
                </div>
                <div>
                    <label for="payment-note" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Catatan bendahara <span class="text-xs font-normal text-slate-400">(opsional)</span></label>
                    <input type="text" id="payment-note" name="catatan" maxlength="120" placeholder="Misal: Pembayaran SPP kelas X" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm placeholder:text-slate-400 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"/>
                </div>
            </div>

            <div class="flex-1 overflow-x-auto border border-slate-200/70 rounded-xl dark:border-slate-700/60">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>
                            <th class="w-12 py-3 pl-4 pr-2 font-semibold">
                                <input type="checkbox" id="select-all-items" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:focus:ring-offset-slate-900" <?= $selectableCount === 0 ? 'disabled' : '' ?>>
                            </th>
                            <th class="py-3 pr-4 font-semibold">Siswa</th>
                            <th class="py-3 pr-4 text-right font-semibold">Sisa tagihan</th>
                            <th class="py-3 pr-4 text-right font-semibold">Saldo tabungan</th>
                            <?php if ($isRecurring): ?>
                                <th class="py-3 pr-4 text-right font-semibold">Per minggu</th>
                                <th class="py-3 pr-4 text-center font-semibold">Minggu tertunggak</th>
                            <?php endif; ?>
                            <th class="py-3 pr-4 text-center font-semibold">Pembayaran terakhir</th>
                            <th class="py-3 pr-4 font-semibold">
                                <?= $isRecurring ? 'Bayar sampai' : 'Pengaturan pembayaran' ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                        <?php if (empty($billingItems)): ?>
                            <tr>
                                <td colspan="<?= $isRecurring ? '8' : '6' ?>" class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">Tidak ada data siswa untuk tagihan ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($billingItems as $item): ?>
                                <?php
                                $itemId = (int) ($item['id'] ?? 0);
                                $remaining = (float) ($item['remaining'] ?? 0.0);
                                $periodAmount = (float) ($item['period_amount'] ?? 0.0);
                                $weeksDue = (int) ($item['weeks_due'] ?? 0);
                                $canSelect = (bool) ($item['can_select'] ?? false);
                                $status = (string) ($item['status'] ?? '');
                                $studentBadgeRecord = [
                                    'siswa_id' => (int) ($item['student_id'] ?? 0),
                                    'siswa_status' => (string) ($item['student_status'] ?? ''),
                                ];
                                $studentInactive = student_is_inactive($studentBadgeRecord);

                                $statusBadge = match ($status) {
                                    'lunas' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
                                    'menunggu_verifikasi' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
                                    'cicilan_berjalan' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300',
                                    default => 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300',
                                };
                                $lastPaymentRaw = $item['last_payment_at'] ?? null;
                                $lastPaymentLabel = null;
                                if ($lastPaymentRaw) {
                                    $timestamp = strtotime((string) $lastPaymentRaw);
                                    if ($timestamp !== false) {
                                        $lastPaymentLabel = date('d M Y', $timestamp);
                                    }
                                }
                                ?>
                                <tr data-item-row="<?= htmlspecialchars((string) $itemId, ENT_QUOTES, 'UTF-8') ?>">
                                    <td class="py-3 pl-4 pr-2 align-top">
                                        <input type="checkbox"
                                               name="items[]"
                                               value="<?= htmlspecialchars((string) $itemId, ENT_QUOTES, 'UTF-8') ?>"
                                               class="item-checkbox rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:focus:ring-offset-slate-900"
                                               data-item-id="<?= htmlspecialchars((string) $itemId, ENT_QUOTES, 'UTF-8') ?>"
                                               data-remaining="<?= htmlspecialchars((string) $remaining, ENT_QUOTES, 'UTF-8') ?>"
                                               data-saving="<?= htmlspecialchars((string) ($item['saving_balance'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                                               <?= $canSelect ? '' : 'disabled' ?>>
                                    </td>
                                    <td class="py-3 pr-4 align-top">
                                        <p class="font-semibold text-slate-900 dark:text-white">
                                            <?= htmlspecialchars($item['student_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            <?= student_status_badge($studentBadgeRecord, 'ml-1 align-middle') ?>
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            NIS: <?= htmlspecialchars($item['student_nis'] ?: '-', ENT_QUOTES, 'UTF-8') ?> •
                                            Kelas: <?= htmlspecialchars($item['class_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                        <span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold <?= $statusBadge ?>">
                                            <?= htmlspecialchars(str_replace('_', ' ', $status !== '' ? ucwords(str_replace('_', ' ', $status)) : 'Tidak diketahui'), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 text-right align-top font-semibold text-amber-600 dark:text-amber-300"><?= htmlspecialchars($formatCurrency($remaining), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-3 pr-4 text-right align-top <?= ((float) ($item['saving_balance'] ?? 0)) + 0.0001 < $remaining ? 'text-rose-600 dark:text-rose-300' : 'text-slate-700 dark:text-slate-200' ?>">
                                        <?= htmlspecialchars($formatCurrency((float) ($item['saving_balance'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <?php if ($isRecurring): ?>
                                        <td class="py-3 pr-4 text-right align-top"><?= htmlspecialchars($formatCurrency($periodAmount), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="py-3 pr-4 text-center align-top">
                                            <?php if ($weeksDue > 0): ?>
                                                <?= htmlspecialchars((string) $weeksDue, ENT_QUOTES, 'UTF-8') ?> minggu
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="py-3 pr-4 text-center align-top">
                                        <?php if ($lastPaymentLabel !== null): ?>
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                <?= htmlspecialchars($lastPaymentLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <?php if ($status === 'lunas'): ?>
                                                <p class="mt-1 text-[11px] text-emerald-600 dark:text-emerald-300">Pelunasan tercatat</p>
                                            <?php else: ?>
                                                <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Pembayaran terakhir</p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-400 dark:bg-slate-800 dark:text-slate-500">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 pr-4 align-top">
                                        <?php if ($isRecurring): ?>
                                            <?php if ($canSelect && $weeksDue > 1): ?>
                                                <select
                                                    name="weeks[<?= htmlspecialchars((string) $itemId, ENT_QUOTES, 'UTF-8') ?>]"
                                                    class="weeks-select w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                    data-item-id="<?= htmlspecialchars((string) $itemId, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-period="<?= htmlspecialchars((string) $periodAmount, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-remaining="<?= htmlspecialchars((string) $remaining, ENT_QUOTES, 'UTF-8') ?>"
                                                    disabled>
                                                    <?php for ($week = 1; $week <= $weeksDue; $week++): ?>
                                                        <option value="<?= $week ?>"><?= htmlspecialchars('Sampai minggu ke-' . $week, ENT_QUOTES, 'UTF-8') ?></option>
                                                    <?php endfor; ?>
                                                    <option value="all">Lunasi semua</option>
                                                </select>
                                            <?php elseif ($canSelect): ?>
                                                <input type="hidden" name="weeks[<?= htmlspecialchars((string) $itemId, ENT_QUOTES, 'UTF-8') ?>]" value="1">
                                                <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                    <?= $weeksDue > 0 ? 'Bayar minggu ini' : 'Tidak ada tunggakan' ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center rounded-lg <?= $studentInactive ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300' : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300' ?> px-2 py-1 text-xs">
                                                    <?= $studentInactive ? 'Siswa nonaktif' : 'Tidak ada sisa tagihan' ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($canSelect): ?>
                                                <div class="space-y-2 text-xs text-slate-600 dark:text-slate-300" data-payment-options="<?= htmlspecialchars((string) $itemId, ENT_QUOTES, 'UTF-8') ?>">
                                                    <label class="flex items-center gap-2">
                                                        <input type="radio"
                                                               name="mode[<?= htmlspecialchars((string) $itemId, ENT_QUOTES, 'UTF-8') ?>]"
                                                               value="full"
                                                               class="mode-radio h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:focus:ring-offset-slate-900"
                                                               data-item-id="<?= htmlspecialchars((string) $itemId, ENT_QUOTES, 'UTF-8') ?>"
                                                               checked
                                                               disabled>
                                                        <span>Bayar penuh (<?= htmlspecialchars($formatCurrency($remaining), ENT_QUOTES, 'UTF-8') ?>)</span>
                                                    </label>
                                                    <label class="flex flex-col gap-2">
                                                        <span class="flex items-center gap-2">
                                                            <input type="radio"
                                                                   name="mode[<?= htmlspecialchars((string) $itemId, ENT_QUOTES, 'UTF-8') ?>]"
                                                                   value="partial"
                                                                   class="mode-radio h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:focus:ring-offset-slate-900"
                                                                   data-item-id="<?= htmlspecialchars((string) $itemId, ENT_QUOTES, 'UTF-8') ?>"
                                                                   disabled>
                                                            <span>Bayar sebagian</span>
                                                        </span>
                                                        <input type="number"
                                                               name="amounts[<?= htmlspecialchars((string) $itemId, ENT_QUOTES, 'UTF-8') ?>]"
                                                               min="0"
                                                               step="100"
                                                               placeholder="Masukkan nominal"
                                                               inputmode="decimal"
                                                               class="amount-input w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                               data-item-id="<?= htmlspecialchars((string) $itemId, ENT_QUOTES, 'UTF-8') ?>"
                                                               data-remaining="<?= htmlspecialchars((string) $remaining, ENT_QUOTES, 'UTF-8') ?>"
                                                               disabled>
                                                        <p class="mt-1 hidden text-[11px] font-medium text-amber-600" data-partial-warning="<?= htmlspecialchars((string) $itemId, ENT_QUOTES, 'UTF-8') ?>">Masukkan nominal pembayaran yang valid.</p>
                                                    </label>
                                                </div>
                                            <?php else: ?>
                                                <span class="inline-flex items-center rounded-lg <?= $studentInactive ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300' : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300' ?> px-2 py-1 text-xs">
                                                    <?= $studentInactive ? 'Siswa nonaktif' : 'Tidak ada sisa tagihan' ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-5 flex flex-col gap-3 border-t border-slate-200 pt-4 text-sm dark:border-slate-700 md:flex-row md:items-center md:justify-between">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    <?php if ($isRecurring): ?>
                        Centang siswa yang melakukan pembayaran. Pilihan minggu akan aktif setelah siswa dipilih. Total pembayaran dihitung otomatis.
                    <?php else: ?>
                        Centang siswa yang melakukan pembayaran. Pilih bayar penuh atau masukkan nominal pembayaran sebagian setelah siswa dipilih.
                    <?php endif; ?>
                </p>
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:bg-slate-300 dark:bg-sky-500 dark:hover:bg-sky-600 dark:focus:ring-offset-slate-900"
                        id="submit-payment"
                        <?= $selectableCount === 0 ? 'disabled' : '' ?>>
                    Simpan Pembayaran
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var form = document.getElementById('billing-payment-form');
        if (!form) {
            return;
        }

        var isRecurring = <?= $isRecurring ? 'true' : 'false' ?>;
        var checkboxes = Array.prototype.slice.call(form.querySelectorAll('.item-checkbox'));
        if (checkboxes.length === 0) {
            return;
        }

        var selects = Array.prototype.slice.call(form.querySelectorAll('.weeks-select'));
        var modeRadios = Array.prototype.slice.call(form.querySelectorAll('.mode-radio'));
        var amountInputs = Array.prototype.slice.call(form.querySelectorAll('.amount-input'));
        var selectAll = document.getElementById('select-all-items');
        var totalField = document.getElementById('selected-total');
        var countField = document.getElementById('selected-count');
        var submitButton = document.getElementById('submit-payment');

        var currencyFormatter = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
            minimumFractionDigits: 0
        });

        var findSelect = function (itemId) {
            return selects.find(function (select) {
                return select.getAttribute('data-item-id') === String(itemId);
            }) || null;
        };

        var findModeRadios = function (itemId) {
            return modeRadios.filter(function (radio) {
                return radio.getAttribute('data-item-id') === String(itemId);
            });
        };

        var findAmountInput = function (itemId) {
            return amountInputs.find(function (input) {
                return input.getAttribute('data-item-id') === String(itemId);
            }) || null;
        };

        var findPartialWarning = function (itemId) {
            return form.querySelector('[data-partial-warning="' + itemId + '"]');
        };

        var updateRecurringSelectState = function (checkbox) {
            var select = findSelect(checkbox.getAttribute('data-item-id'));
            if (!select) {
                return;
            }
            select.disabled = !checkbox.checked;
        };

        var updateNonRecurringOptions = function (checkbox) {
            var itemId = checkbox.getAttribute('data-item-id');
            var radios = findModeRadios(itemId);
            var amountInput = findAmountInput(itemId);
            var warning = findPartialWarning(itemId);

            if (!checkbox.checked || checkbox.disabled) {
                radios.forEach(function (radio, index) {
                    radio.disabled = true;
                    radio.checked = index === 0;
                });
                if (amountInput) {
                    amountInput.disabled = true;
                    amountInput.value = '';
                    amountInput.classList.remove('ring-1', 'ring-amber-500');
                }
                if (warning) {
                    warning.classList.add('hidden');
                }
                return;
            }

            var hasChecked = radios.some(function (radio) {
                return radio.checked;
            });

            radios.forEach(function (radio, index) {
                radio.disabled = false;
                if (!hasChecked && index === 0) {
                    radio.checked = true;
                }
            });

            var partialSelected = radios.some(function (radio) {
                return radio.checked && radio.value === 'partial';
            });

            if (amountInput) {
                amountInput.disabled = !partialSelected;
                if (!partialSelected) {
                    amountInput.value = '';
                    amountInput.classList.remove('ring-1', 'ring-amber-500');
                }
            }

            if (warning) {
                warning.classList.add('hidden');
            }
        };

        var refreshSummary = function () {
            var total = 0;
            var count = 0;
            var valid = true;

            checkboxes.forEach(function (checkbox) {
                if (!checkbox.checked || checkbox.disabled) {
                    return;
                }

                var itemId = checkbox.getAttribute('data-item-id');
                var remaining = parseFloat(checkbox.getAttribute('data-remaining') || '0') || 0;
                var amount = remaining;

                if (isRecurring) {
                    var select = findSelect(itemId);
                    if (select) {
                        var period = parseFloat(select.getAttribute('data-period') || '0') || 0;
                        var selectedValue = select.value;
                        if (selectedValue === 'all') {
                            amount = remaining;
                        } else {
                            var weeks = parseInt(selectedValue, 10);
                            if (!isNaN(weeks) && weeks > 0) {
                                amount = period > 0 ? Math.min(period * weeks, remaining) : remaining;
                            }
                        }
                    }
                } else {
                    var radios = findModeRadios(itemId);
                    var mode = 'full';
                    radios.forEach(function (radio) {
                        if (radio.checked) {
                            mode = radio.value;
                        }
                    });

                    var warning = findPartialWarning(itemId);
                    if (warning) {
                        warning.classList.add('hidden');
                    }

                    if (mode === 'partial') {
                        var input = findAmountInput(itemId);
                        var value = input ? parseFloat(input.value || '0') : 0;

                        if (input) {
                            input.classList.remove('ring-1', 'ring-amber-500');
                        }

                        if (!input || isNaN(value) || value <= 0) {
                            valid = false;
                            amount = 0;
                            if (warning) {
                                warning.textContent = 'Masukkan nominal pembayaran yang valid.';
                                warning.classList.remove('hidden');
                            }
                            if (input) {
                                input.classList.add('ring-1', 'ring-amber-500');
                            }
                        } else if (value > remaining + 0.0001) {
                            valid = false;
                            amount = 0;
                            if (warning) {
                                warning.textContent = 'Nominal melebihi sisa tagihan.';
                                warning.classList.remove('hidden');
                            }
                            if (input) {
                                input.classList.add('ring-1', 'ring-amber-500');
                            }
                        } else {
                            amount = value;
                        }
                    } else {
                        var amountField = findAmountInput(itemId);
                        if (amountField) {
                            amountField.classList.remove('ring-1', 'ring-amber-500');
                        }
                    }
                }

                total += amount;
                count++;
            });

            if (totalField) {
                totalField.textContent = currencyFormatter.format(total);
            }
            if (countField) {
                countField.textContent = count;
            }
            if (submitButton) {
                submitButton.disabled = count === 0 || !valid;
            }
        };

        checkboxes.forEach(function (checkbox) {
            if (isRecurring) {
                updateRecurringSelectState(checkbox);
            } else {
                updateNonRecurringOptions(checkbox);
            }

            checkbox.addEventListener('change', function () {
                if (isRecurring) {
                    updateRecurringSelectState(checkbox);
                } else {
                    updateNonRecurringOptions(checkbox);
                }
                refreshSummary();
            });
        });

        selects.forEach(function (select) {
            select.addEventListener('change', refreshSummary);
        });

        modeRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                var itemId = radio.getAttribute('data-item-id');
                var checkbox = form.querySelector('.item-checkbox[data-item-id="' + itemId + '"]');
                if (checkbox) {
                    updateNonRecurringOptions(checkbox);
                }
                refreshSummary();
            });
        });

        amountInputs.forEach(function (input) {
            input.addEventListener('input', function () {
                var itemId = input.getAttribute('data-item-id');
                var warning = findPartialWarning(itemId);
                if (warning) {
                    warning.classList.add('hidden');
                }
                input.classList.remove('ring-1', 'ring-amber-500');
                refreshSummary();
            });
        });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                var targetChecked = selectAll.checked;

                checkboxes.forEach(function (checkbox) {
                    if (!checkbox.disabled) {
                        checkbox.checked = targetChecked;
                        if (isRecurring) {
                            updateRecurringSelectState(checkbox);
                        } else {
                            updateNonRecurringOptions(checkbox);
                        }
                    }
                });

                refreshSummary();
            });
        }

        refreshSummary();
    })();
</script>

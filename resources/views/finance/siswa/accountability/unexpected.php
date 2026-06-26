<?php
/** @var array<string, mixed> $expense */
/** @var array<string, mixed>|null $report */
/** @var string $defaultTitle */
/** @var string $defaultDate */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$requester = (string) ($expense['pemohon_nama'] ?? '-');
$recordedAt = (string) ($expense['tanggal'] ?? 'now');

$existingAttachment = $report['bukti_path'] ?? null;
$currentTitle = old('judul', $report['judul'] ?? $defaultTitle);
$currentAmount = old('nominal', isset($report['nominal']) ? (string) $report['nominal'] : (string) ($expense['nominal'] ?? '0'));

$reportedAt = $report['tanggal'] ?? null;
if ($reportedAt !== null && $reportedAt !== '') {
    $reportedAtInput = date('Y-m-d\TH:i', strtotime((string) $reportedAt));
} else {
    $reportedAtInput = $defaultDate;
}
$currentDate = old('tanggal', $reportedAtInput);
$currentDescription = old('deskripsi', $report['deskripsi'] ?? '');
?>

<div class="space-y-6">
    <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-slate-900 dark:text-white">LPJ Pengeluaran Tak Terduga</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Lengkapi laporan pertanggungjawaban atas dana yang telah diterima. Sertakan bukti pengeluaran bila tersedia.
                </p>
            </div>
            <div class="rounded-lg border border-slate-200/70 bg-white px-4 py-3 text-sm dark:border-slate-700/70 dark:bg-slate-900/80">
                <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Kode Transaksi</p>
                <p class="mt-1 font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars((string) ($expense['kode_transaksi'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Penerima Dana</dt>
                <dd class="mt-1 font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($requester, ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Nominal</dt>
                <dd class="mt-1 font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($expense['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Tanggal Pengeluaran</dt>
                <dd class="mt-1 font-medium text-slate-900 dark:text-white"><?= htmlspecialchars(date('d M Y H:i', strtotime($recordedAt)), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Deskripsi</dt>
                <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">
                    <?= $expense['deskripsi'] !== null && $expense['deskripsi'] !== '' ? nl2br(htmlspecialchars((string) $expense['deskripsi'], ENT_QUOTES, 'UTF-8')) : '<span class="text-slate-400 dark:text-slate-500">-</span>' ?>
                </dd>
            </div>
        </dl>
    </div>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <form method="post" action="<?= htmlspecialchars(base_url('keuangan/siswa/pengeluaran-tak-terduga/' . (int) ($expense['id'] ?? 0) . '/lpj'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="space-y-5">
            <?= csrf_field() ?>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="lpj-title" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Judul LPJ <span class="text-rose-500">*</span></label>
                    <input
                        type="text"
                        id="lpj-title"
                        name="judul"
                        value="<?= htmlspecialchars((string) $currentTitle, ENT_QUOTES, 'UTF-8') ?>"
                        maxlength="180"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        required
                    >
                </div>
                <div>
                    <label for="lpj-amount" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Realisasi Dana <span class="text-rose-500">*</span></label>
                    <input
                        type="text"
                        id="lpj-amount"
                        name="nominal"
                        value="<?= htmlspecialchars((string) $currentAmount, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="cth. 500000"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        required
                    >
                </div>
                <div>
                    <label for="lpj-date" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tanggal Laporan <span class="text-rose-500">*</span></label>
                    <input
                        type="datetime-local"
                        id="lpj-date"
                        name="tanggal"
                        value="<?= htmlspecialchars($currentDate, ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        required
                    >
                </div>
                <div>
                    <label for="lpj-file" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Lampiran Bukti (Opsional)</label>
                    <input
                        type="file"
                        id="lpj-file"
                        name="lampiran"
                        accept=".pdf,.jpg,.jpeg,.png"
                        class="block w-full text-sm text-slate-700 file:mr-4 file:cursor-pointer file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:text-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200 dark:hover:file:bg-slate-700"
                    >
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Format: PDF/JPG/PNG, maksimal <?= htmlspecialchars((string) config('finance.max_receipt_size_kb', 2048), ENT_QUOTES, 'UTF-8') ?> KB.</p>
                    <?php if ($existingAttachment !== null): ?>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            Lampiran saat ini: <a href="<?= htmlspecialchars(base_url('storage/' . ltrim((string) $existingAttachment, '/')), ENT_QUOTES, 'UTF-8') ?>" class="text-indigo-600 hover:underline dark:text-indigo-300" target="_blank" rel="noopener noreferrer">Lihat berkas</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <label for="lpj-description" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Rincian Penggunaan Dana</label>
                <textarea
                    id="lpj-description"
                    name="deskripsi"
                    rows="5"
                    placeholder="Tuliskan detail penggunaan dana dan sisa yang dikembalikan (jika ada)."
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                ><?= htmlspecialchars((string) $currentDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                <a href="<?= htmlspecialchars(base_url('keuangan/siswa'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex w-full items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">
                    Batal
                </a>
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto dark:focus:ring-offset-slate-900"
                >
                    Simpan LPJ
                </button>
            </div>
        </form>
    </div>
</div>


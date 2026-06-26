<?php
/** @var array<int, string> $teacherOptions */
/** @var array<int, array<string, mixed>> $expenses */
/** @var array<string, mixed> $filters */
/** @var bool $hasActiveYear */
/** @var float $generalCashBalance */
/** @var string $defaultRecordedAt */
/** @var array<int, string> $studentOptions */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$selectedTeacherId = (int) ($filters['teacher_id'] ?? 0);
$defaultRecordedAt = $defaultRecordedAt ?? date('Y-m-d\TH:i');
?>

<div class="space-y-8 px-4 sm:px-6 lg:px-8">
    <?php if (!$hasActiveYear): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="font-semibold">Tahun ajaran aktif belum ditetapkan.</p>
            <p class="mt-1">Atur tahun ajaran aktif terlebih dahulu agar pengeluaran dapat dicatat dan dikaitkan dengan periode yang sesuai.</p>
        </div>
    <?php endif; ?>

    <div class="mx-auto max-w-4xl rounded-xl border border-slate-200/60 bg-white/80 p-5 shadow-sm shadow-slate-100 sm:p-6 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Pengeluaran Tak Terduga</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Catat pengeluaran yang segera harus dibayarkan tanpa menunggu persetujuan kepala sekolah. Pastikan pemohon dan alasan penggunaan dana tercatat jelas.
                </p>
            </div>
            <div class="rounded-lg border border-slate-200/70 bg-white px-4 py-3 text-right text-sm dark:border-slate-700/70 dark:bg-slate-900/70">
                <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Saldo Kas Utama</p>
                <p class="mt-1 text-base font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) $generalCashBalance), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </div>

    <div class="mx-auto grid max-w-6xl grid-cols-1 gap-6 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
        <div>
            <div class="rounded-xl border border-slate-200/60 bg-white/80 p-5 shadow-sm shadow-slate-100 sm:p-6 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Catat Pengeluaran Baru</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Pengeluaran akan langsung mengurangi saldo kas utama. Pastikan nominal dan pemohon sesuai dengan bukti pengeluaran.
                </p>

                <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/pengeluaran-tak-terduga'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 space-y-5">
                    <?= csrf_field() ?>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="requester-type" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Jenis Pemohon</label>
                            <select
                                id="requester-type"
                                name="requester_type"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >
                                <option value="guru">Guru</option>
                                <option value="siswa">Siswa</option>
                                <option value="lainnya">Pihak Lain</option>
                            </select>
                        </div>
                        <div data-requester="teacher" class="sm:col-span-1">
                            <label for="teacher-id" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Guru Pemohon</label>
                            <select
                                id="teacher-id"
                                name="teacher_id"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >
                                <option value="">Pilih guru</option>
                                <?php foreach ($teacherOptions as $id => $label): ?>
                                    <option value="<?= (int) $id ?>" <?= $selectedTeacherId === (int) $id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div data-requester="student" class="hidden sm:col-span-1">
                            <label for="student-id" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Siswa Pemohon</label>
                            <select
                                id="student-id"
                                name="student_id"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >
                                <option value="">Pilih siswa</option>
                                <?php foreach ($studentOptions as $id => $label): ?>
                                    <?php $optionDisabled = str_contains((string) $label, ' - Nonaktif'); ?>
                                    <option value="<?= (int) $id ?>" <?= $optionDisabled ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div data-requester="other" class="hidden sm:col-span-1">
                            <label for="requester-name" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Nama Pemohon</label>
                            <input
                                type="text"
                                id="requester-name"
                                name="requester_name"
                                placeholder="cth. Komite Sekolah"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="amount" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Nominal Pengeluaran</label>
                            <input
                                type="text"
                                id="amount"
                                name="amount"
                                placeholder="cth. 1500000"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Gunakan angka saja tanpa titik/koma atau gunakan format rupiah standar.</p>
                        </div>
                        <div>
                            <label for="recorded-at" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Waktu Pengeluaran</label>
                            <input
                                type="datetime-local"
                                id="recorded-at"
                                name="recorded_at"
                                value="<?= htmlspecialchars($defaultRecordedAt, ENT_QUOTES, 'UTF-8') ?>"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >
                        </div>
                    </div>
                    <div>
                        <label for="description" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Keterangan</label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            placeholder="Tuliskan ringkas alasan pengeluaran…"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        ></textarea>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 sm:w-auto dark:focus:ring-offset-slate-900"
                        >
                            Simpan Pengeluaran
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div>
            <div class="rounded-xl border border-slate-200/60 bg-white/80 p-5 shadow-sm shadow-slate-100 sm:p-6 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Riwayat Pengeluaran</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Daftar pengeluaran tak terduga terbaru yang dicatat bendahara.</p>
                    </div>
                    <form method="get" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div>
                            <label for="filter-teacher" class="sr-only">Filter guru</label>
                            <select
                                id="filter-teacher"
                                name="teacher_id"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 sm:w-52 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >
                                <option value="">Semua pemohon</option>
                                <?php foreach ($teacherOptions as $id => $label): ?>
                                    <option value="<?= (int) $id ?>" <?= $selectedTeacherId === (int) $id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 sm:w-auto dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                            Terapkan
                        </button>
                    </form>
                </div>

                <?php if (empty($expenses)): ?>
                    <p class="mt-6 text-sm text-slate-500 dark:text-slate-400">Belum ada pengeluaran yang tercatat pada periode ini.</p>
                <?php else: ?>
                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                <tr>
                                    <th class="py-3 pr-4 font-semibold">Tanggal</th>
                                    <th class="py-3 pr-4 font-semibold">Pemohon</th>
                                    <th class="py-3 pr-4 font-semibold">Keterangan</th>
                                    <th class="py-3 pr-4 text-right font-semibold">Nominal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                                <?php foreach ($expenses as $expense): ?>
                                    <?php
                                        $requesterType = (string) ($expense['tipe_pemohon'] ?? 'lainnya');
                                        $requesterLabel = (string) ($expense['pemohon_nama'] ?? '-');
                                        if ($requesterType === 'guru' && !empty($expense['guru_nama'])) {
                                            $requesterLabel = (string) $expense['guru_nama'];
                                        } elseif ($requesterType === 'siswa' && !empty($expense['siswa_nama'])) {
                                            $requesterLabel = (string) $expense['siswa_nama'];
                                        }
                                        $code = (string) ($expense['kode_transaksi'] ?? '');
                                        $description = trim((string) ($expense['deskripsi'] ?? ''));
                                        $recordedAt = (string) ($expense['tanggal'] ?? 'now');
                                    ?>
                                    <tr>
                                        <td class="py-3 pr-4 align-top">
                                            <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars(date('d M Y H:i', strtotime($recordedAt)), ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php if ($code !== ''): ?>
                                                <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 pr-4 align-top">
                                            <p class="font-medium text-slate-900 dark:text-white">
                                                <?= htmlspecialchars($requesterLabel, ENT_QUOTES, 'UTF-8') ?>
                                                <?php if ($requesterType === 'siswa'): ?>
                                                    <?= student_status_badge($expense, 'ml-1 align-middle') ?>
                                                <?php endif; ?>
                                            </p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                <?php
                                                    $typeLabel = match ($requesterType) {
                                                        'guru' => 'Guru',
                                                        'siswa' => 'Siswa',
                                                        default => 'Pihak Lain',
                                                    };
                                                ?>
                                                <?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                        </td>
                                        <td class="py-3 pr-4 align-top">
                                            <?php if ($description !== ''): ?>
                                                <p><?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) ?></p>
                                            <?php else: ?>
                                                <p class="text-slate-400 dark:text-slate-500">Tidak ada keterangan.</p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 pr-0 text-right align-top font-semibold text-rose-600 dark:text-rose-300">
                                            <?= htmlspecialchars($formatCurrency((float) ($expense['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
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

<script>
(function () {
    const typeSelect = document.getElementById('requester-type');
    if (!typeSelect) {
        return;
    }

    const teacherSection = document.querySelector('[data-requester="teacher"]');
    const otherSection = document.querySelector('[data-requester="other"]');
    const studentSection = document.querySelector('[data-requester="student"]');

    const toggleSections = function () {
        const type = typeSelect.value;
        if (teacherSection instanceof HTMLElement) {
            teacherSection.classList.toggle('hidden', type !== 'guru');
        }
        if (studentSection instanceof HTMLElement) {
            studentSection.classList.toggle('hidden', type !== 'siswa');
        }
        if (otherSection instanceof HTMLElement) {
            otherSection.classList.toggle('hidden', type !== 'lainnya');
        }
    };

    typeSelect.addEventListener('change', toggleSections);
    toggleSections();
})();
</script>

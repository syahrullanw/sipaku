<?php
/** @var array<int, array<string, mixed>> $billings */
/** @var array<int, array<string, mixed>> $categories */
/** @var array<int|string, string> $classes */
/** @var array<int|string, string> $students */
/** @var array<int> $selectedClassIds */
/** @var int|null $activeSchoolYearId */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$weekdayNames = [
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => "Jumat",
    6 => 'Sabtu',
    7 => 'Minggu',
];
$defaultTemplateValue = isset($defaultWhatsappTemplate) && is_string($defaultWhatsappTemplate)
    ? $defaultWhatsappTemplate
    : 'Halo {{nama_siswa}}, pembayaran untuk tagihan {{judul_tagihan}} sebesar Rp {{nominal_bayar}} telah kami terima pada {{tanggal_pembayaran}}. Sisa tagihan: {{sisa_tagihan}}. Terima kasih.';
$whatsappTemplateValue = (string) old('whatsapp_message_template', $defaultTemplateValue);
?>

<div class="grid gap-5 md:gap-6 lg:grid-cols-5 lg:items-start">
    <div class="min-w-0 lg:col-span-2 rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="border-b border-slate-200/60 px-4 py-4 sm:px-6 dark:border-slate-700/60">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Buat Tagihan Baru</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Tagihan dibuat per siswa dan otomatis aktif.</p>
        </div>
        <div class="px-4 py-5 sm:px-6">
            <?php if ($activeSchoolYearId === null): ?>
                <p class="text-sm text-amber-600 dark:text-amber-300">Tidak ada tahun ajaran aktif. Set tahun ajaran sebelum membuat tagihan.</p>
            <?php else: ?>
                <div class="mb-4 space-y-3 text-xs">
                    <form action="<?= htmlspecialchars(base_url('keuangan/bendahara/tagihan'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm sm:px-4 sm:py-4 dark:border-slate-700 dark:bg-slate-900">
                        <fieldset>
                            <legend class="text-sm font-semibold text-slate-700 dark:text-slate-200">Filter siswa berdasarkan kelas</legend>
                            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Pilih satu atau beberapa kelas untuk menampilkan daftar siswa. Kosongkan untuk melihat semua siswa.</p>
                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
                                <?php foreach ($classes as $id => $label): ?>
                                    <?php $isChecked = in_array((int) $id, $selectedClassIds, true); ?>
                                    <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 shadow-sm hover:border-sky-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                        <input type="checkbox" name="kelas_id[]" value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= $isChecked ? 'checked' : '' ?> class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900" />
                                        <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/tagihan'), ENT_QUOTES, 'UTF-8') ?>" class="text-center text-xs font-medium text-slate-500 hover:text-slate-700 sm:text-left dark:text-slate-400 dark:hover:text-slate-200">Reset filter</a>
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 dark:bg-sky-500 dark:hover:bg-sky-600 dark:focus:ring-offset-slate-900 sm:w-auto">Terapkan</button>
                        </div>
                    </form>
                    <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/kategori'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 sm:w-auto sm:justify-start dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        Kelola Kategori
                    </a>
                </div>
                <form action="<?= htmlspecialchars(base_url('keuangan/bendahara/tagihan'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-4 sm:space-y-5">
                    <?= csrf_field() ?>
                    <div>
                        <label for="kategori_id" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Kategori Tagihan</label>
                        <select id="kategori_id" name="kategori_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                            <option value="">-- Pilih kategori --</option>
                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?= htmlspecialchars($category['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-type="<?= htmlspecialchars((string) ($category['tipe'] ?? 'insidental'), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <?= htmlspecialchars(($category['nama'] ?? '-') . ' (' . ($category['tipe'] ?? 'rutin') . ')', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="weekly-config" class="hidden">
                        <label for="rutin_hari_mingguan" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Hari Penagihan Mingguan</label>
                        <select id="rutin_hari_mingguan" name="rutin_hari_mingguan" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" disabled>
                            <option value="">-- Pilih hari --</option>
                            <option value="1">Senin</option>
                            <option value="2">Selasa</option>
                            <option value="3">Rabu</option>
                            <option value="4">Kamis</option>
                            <option value="5">Jumat</option>
                            <option value="6">Sabtu</option>
                            <option value="7">Minggu</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Tagihan otomatis bertambah setiap minggu pada hari yang dipilih.</p>
                    </div>
                    <div id="monthly-config" class="hidden">
                        <label for="rutin_tanggal_bulanan" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tanggal Penagihan Bulanan</label>
                        <input type="number" id="rutin_tanggal_bulanan" name="rutin_tanggal_bulanan" min="1" max="31" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" disabled />
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Tagihan otomatis bertambah setiap bulan pada tanggal yang dipilih. Jika bulan lebih pendek, sistem menyesuaikan ke tanggal terakhir.</p>
                    </div>
                    <div>
                        <label for="jenis_penagihan" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Jenis Penagihan</label>
                        <select id="jenis_penagihan" name="jenis_penagihan" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                            <option value="tidak">Sekali (insidental)</option>
                            <option value="mingguan">Rutin - Mingguan</option>
                            <option value="bulanan">Rutin - Bulanan</option>
                        </select>
                        <p id="billing-type-note" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Tagihan rutin akan digenerate otomatis sesuai jadwal yang dipilih dan tidak memiliki tanggal jatuh tempo.
                        </p>
                    </div>
                    <div id="start-date-field" class="hidden">
                        <label for="rutin_mulai" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tanggal Mulai Tagihan</label>
                        <input
                            type="date"
                            id="rutin_mulai"
                            name="rutin_mulai"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"
                            disabled
                        />
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Tentukan tanggal awal penagihan rutin agar sistem tahu sejak kapan jadwal dihitung.</p>
                    </div>
                    <div>
                        <label for="judul" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Judul Tagihan</label>
                        <input type="text" id="judul" name="judul" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" placeholder="cth: SPP September 2024" required />
                    </div>
                    <div>
                        <label for="deskripsi" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Deskripsi (opsional)</label>
                        <textarea id="deskripsi" name="deskripsi" rows="3" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"></textarea>
                    </div>
                    <div>
                        <label for="whatsapp_message_template" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Template Pesan WhatsApp Bukti Bayar</label>
                        <textarea
                            id="whatsapp_message_template"
                            name="whatsapp_message_template"
                            rows="4"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"><?= htmlspecialchars($whatsappTemplateValue, ENT_QUOTES, 'UTF-8') ?></textarea>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Gunakan placeholder seperti <code>{{nama_siswa}}</code>, <code>{{judul_tagihan}}</code>, <code>{{nominal_bayar}}</code>, <code>{{sisa_tagihan}}</code>, <code>{{tanggal_pembayaran}}</code>, dan <code>{{kode_pembayaran}}</code>. Nilai akan diganti otomatis saat bukti bayar dikirim.
                        </p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label for="nominal" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Nominal per Siswa</label>
                            <input type="number" id="nominal" name="nominal" min="0" step="1000" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" required />
                        </div>
                        <div id="due-date-field" class="transition-opacity">
                            <label for="tanggal_jatuh_tempo" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Jatuh Tempo</label>
                            <input type="date" id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Hanya digunakan untuk tagihan sekali.</p>
                        </div>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">Pilih Siswa</label>
                        <div class="mb-2 flex flex-col gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-500 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 md:flex-row md:items-center md:justify-between">
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-200">
                                <input type="checkbox" id="check-all-students" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800" />
                                Pilih semua siswa
                            </label>
                            <div class="relative flex-1 md:max-w-xs">
                                <input type="search" id="filter-students" placeholder="Cari nama siswa..." class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-8 pr-3 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                                <span class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35m0 0A7.5 7.5 0 1 0 6.65 6.65a7.5 7.5 0 0 0 10.6 10.6Z" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    <div class="max-h-60 overflow-y-auto rounded-lg border border-slate-200 bg-white px-3 py-2 sm:max-h-64 dark:border-slate-700 dark:bg-slate-900">
                            <?php if (empty($students)): ?>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Tidak ada siswa pada filter yang dipilih.</p>
                            <?php else: ?>
                                <ul id="student-list" class="space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                    <?php foreach ($students as $id => $label): ?>
                                        <li class="flex items-center gap-2" data-student-name="<?= htmlspecialchars(strtolower($label), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="checkbox" id="student-<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" name="students[]" value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" class="student-checkbox h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800" />
                                            <label for="student-<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" class="cursor-pointer"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-sky-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:ring-offset-1 focus:ring-offset-white sm:w-auto dark:focus:ring-offset-slate-900">
                            Simpan Tagihan
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="min-w-0 lg:col-span-3 rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="border-b border-slate-200/60 px-4 py-4 sm:px-6 dark:border-slate-700/60">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Tagihan Tahun Ajaran Aktif</h2>
                <form action="<?= htmlspecialchars(base_url('keuangan/bendahara/tagihan/generate-rutin'), ENT_QUOTES, 'UTF-8') ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600 shadow-sm hover:border-slate-400 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:text-white">
                        Generate Tagihan Rutin
                    </button>
                </form>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Jalankan ulang generate tagihan rutin bila cron belum berjalan otomatis.
            </p>
        </div>
        <div class="overflow-x-auto px-4 py-4 sm:px-6">
            <?php if (empty($billings)): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada tagihan yang dibuat.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-slate-200 text-xs sm:text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>
                            <th class="py-3 pr-4 font-semibold">Tagihan</th>
                            <th class="py-3 pr-4 font-semibold">Kategori</th>
                            <th class="py-3 pr-4 text-right font-semibold">Penerima</th>
                            <th class="py-3 pr-4 text-right font-semibold">Terbayar</th>
                            <th class="py-3 pr-4 text-right font-semibold">Kas</th>
                            <th class="py-3 pr-4 text-right font-semibold">Sisa</th>
                            <th class="py-3 pr-4 font-semibold">Status</th>
                            <th class="py-3 pr-0 font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                        <?php foreach ($billings as $billing): ?>
                            <?php
                            $status = (string) ($billing['status'] ?? '');
                            $badgeClass = match ($status) {
                                'aktif' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300',
                                'ditutup' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
                                default => 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300',
                            };
                            $recurringType = (string) ($billing['rutin_tipe'] ?? 'tidak');
                            $isRecurring = in_array($recurringType, ['mingguan', 'bulanan'], true);
                            $recurringLabel = match ($recurringType) {
                                'mingguan' => 'Rutin mingguan',
                                'bulanan' => 'Rutin bulanan',
                                default => 'Sekali',
                            };
                            $dueLabel = $billing['tanggal_jatuh_tempo']
                                ? date('d M Y', strtotime((string) $billing['tanggal_jatuh_tempo']))
                                : '-';
                            $nextSchedule = $billing['rutin_jadwal_berikutnya']
                                ? date('d M Y', strtotime((string) $billing['rutin_jadwal_berikutnya']))
                                : '-';
                            $lastGenerated = $billing['rutin_terakhir_generate']
                                ? date('d M Y', strtotime((string) $billing['rutin_terakhir_generate']))
                                : null;
                            $waTemplate = (string) ($billing['whatsapp_message_template'] ?? '');
                            $hasWaTemplate = trim($waTemplate) !== '';
                            $waTemplateJson = json_encode($waTemplate, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
                            $waTemplateJson = $waTemplateJson === false ? '""' : $waTemplateJson;
                            $billingTitle = trim(($billing['judul'] ?? '') !== '' ? (string) $billing['judul'] : 'Tagihan');
                            $billingActionTitle = $billingTitle;
                            if (!empty($billing['kode'])) {
                                $billingActionTitle .= ' (' . $billing['kode'] . ')';
                            }
                            ?>
                            <tr>
                                <td class="py-3 pr-4">
                                    <p class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($billing['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Kode: <?= htmlspecialchars($billing['kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Jenis: <?= htmlspecialchars($recurringLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php if ($isRecurring): ?>
                                        <?php if ($recurringType === 'mingguan'): ?>
                                            <?php
                                            $dayName = $weekdayNames[(int) ($billing['rutin_hari_mingguan'] ?? 0)] ?? 'Senin';
                                            ?>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">Jadwal: setiap <?= htmlspecialchars($dayName, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php elseif ($recurringType === 'bulanan'): ?>
                                            <?php $monthlyDate = (int) ($billing['rutin_tanggal_bulanan'] ?? 1); ?>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">Jadwal: setiap tanggal <?= htmlspecialchars((string) $monthlyDate, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Generate berikutnya: <?= htmlspecialchars($nextSchedule, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if ($lastGenerated !== null): ?>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">Terakhir generate: <?= htmlspecialchars($lastGenerated, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Jatuh tempo: <?= htmlspecialchars($dueLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 pr-4"><?= htmlspecialchars($billing['kategori_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-3 pr-4 text-right"><?= number_format((int) ($billing['total_penerima'] ?? 0), 0, ',', '.') ?> siswa</td>
                                <td class="py-3 pr-4 text-right text-emerald-600 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency((float) ($billing['total_terbayar'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-3 pr-4 text-right text-emerald-600 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency((float) ($billing['kas_saldo'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-3 pr-4 text-right text-amber-600 dark:text-amber-300"><?= htmlspecialchars($formatCurrency((float) ($billing['total_sisa'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-3 pr-4">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $badgeClass ?>"><?= htmlspecialchars(ucwords($status), ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="py-3 pr-0">
                                    <?php $canExecutePayment = $status === 'aktif'; ?>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <?php if ($canExecutePayment): ?>
                                            <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/tagihan/' . ($billing['id'] ?? 0) . '/pembayaran'), ENT_QUOTES, 'UTF-8') ?>"
                                               class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 dark:bg-sky-500 dark:hover:bg-sky-600 dark:focus:ring-offset-slate-900">
                                                Pembayaran
                                            </a>
                                        <?php else: ?>
                                            <span class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-400 dark:border-slate-700 dark:text-slate-500">
                                                Tidak tersedia
                                            </span>
                                        <?php endif; ?>
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 shadow-sm transition hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:border-indigo-500/40 dark:text-indigo-200 dark:hover:bg-indigo-500/10"
                                            data-wa-edit
                                            data-billing-id="<?= htmlspecialchars((string) ($billing['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-billing-title="<?= htmlspecialchars($billingActionTitle, ENT_QUOTES, 'UTF-8') ?>"
                                            data-wa-template="<?= htmlspecialchars($waTemplateJson, ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            Edit Pesan WA
                                        </button>
                                    </div>
                                    <?php if (!$hasWaTemplate): ?>
                                        <p class="mt-2 text-xs font-medium text-amber-600 dark:text-amber-300">Template WA belum diatur.</p>
                                    <?php else: ?>
                                        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">Template WA sudah disimpan.</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-900/60 px-4 py-6 backdrop-blur-sm" data-wa-modal>
    <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4 dark:border-slate-800">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white" data-wa-modal-title>Tagihan</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Perbarui template WhatsApp yang akan dipakai saat bukti pembayaran dikirim untuk tagihan ini.
                </p>
            </div>
            <button type="button" class="text-slate-400 transition hover:text-slate-600 dark:hover:text-slate-200" data-wa-close aria-label="Tutup pop-up">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>
        <form method="post" class="space-y-5 px-6 pb-6 pt-5" data-base-action="<?= htmlspecialchars(base_url('keuangan/bendahara/tagihan/__ID__/whatsapp-template'), ENT_QUOTES, 'UTF-8') ?>">
            <?= csrf_field() ?>
            <div class="space-y-3">
                <label for="wa-template-textarea" class="text-sm font-medium text-slate-600 dark:text-slate-300">Template Pesan WhatsApp</label>
                <textarea
                    id="wa-template-textarea"
                    name="whatsapp_message_template"
                    rows="6"
                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    placeholder="Contoh: Halo {{nama_siswa}}, pembayaran tagihan {{judul_tagihan}} sebesar {{nominal_bayar}} telah diterima."
                ></textarea>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Placeholder yang dapat digunakan:
                    <code>{{nama_siswa}}</code>,
                    <code>{{judul_tagihan}}</code>,
                    <code>{{nominal_bayar}}</code>,
                    <code>{{sisa_tagihan}}</code>,
                    <code>{{tanggal_pembayaran}}</code>,
                    <code>{{kode_pembayaran}}</code>,
                    <code>{{metode_pembayaran}}</code>,
                    <code>{{nama_sekolah}}</code>,
                    <code>{{link_bukti_bayar}}</code>,
                    <code>{{link_bukti_bayar_html}}</code>.
                </p>
            </div>
            <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-end dark:border-slate-800">
                <button type="button" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-1 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800" data-wa-close>
                    Batal
                </button>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <i class="ri-save-line text-base"></i>
                    Simpan Template
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.querySelector('[data-wa-modal]');
    if (!modal) {
        return;
    }

    const form = modal.querySelector('form');
    const textarea = modal.querySelector('#wa-template-textarea');
    const titleNode = modal.querySelector('[data-wa-modal-title]');
    const baseAction = form?.dataset?.baseAction ?? '';
    const body = document.body;

    const openModal = (id, title, template) => {
        if (!form || !textarea) {
            return;
        }
        if (baseAction.includes('__ID__')) {
            form.action = baseAction.replace('__ID__', id);
        } else {
            form.action = baseAction;
        }
        textarea.value = template || '';
        if (titleNode) {
            titleNode.textContent = title || 'Tagihan';
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        body.classList.add('overflow-hidden');
        textarea.focus({ preventScroll: true });
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        body.classList.remove('overflow-hidden');
    };

    document.querySelectorAll('[data-wa-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-billing-id');
            const title = button.getAttribute('data-billing-title') ?? '';
            const templateRaw = button.getAttribute('data-wa-template') ?? '""';
            let template = '';
            try {
                template = JSON.parse(templateRaw);
            } catch (error) {
                template = '';
            }
            openModal(id, title, template);
        });
    });

    modal.querySelectorAll('[data-wa-close]').forEach((closeButton) => {
        closeButton.addEventListener('click', (event) => {
            event.preventDefault();
            closeModal();
        });
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
});
</script>
<script>
    (function () {
        var typeSelect = document.getElementById('jenis_penagihan');
        var dueField = document.getElementById('due-date-field');
        var dueInput = document.getElementById('tanggal_jatuh_tempo');
        var weeklyConfig = document.getElementById('weekly-config');
        var monthlyConfig = document.getElementById('monthly-config');
        var weeklySelect = document.getElementById('rutin_hari_mingguan');
        var monthlyInput = document.getElementById('rutin_tanggal_bulanan');
        var checkAll = document.getElementById('check-all-students');
        var studentList = document.getElementById('student-list');
        var searchInput = document.getElementById('filter-students');

        if (!typeSelect || !dueField || !dueInput) {
            return;
        }

        var toggleDueDate = function () {
            var value = typeSelect.value;
            var isRecurring = value === 'mingguan' || value === 'bulanan';
            var isWeekly = value === 'mingguan';
            var isMonthly = value === 'bulanan';

            dueField.classList.toggle('opacity-60', isRecurring);
            dueField.classList.toggle('pointer-events-none', isRecurring);
            dueInput.disabled = isRecurring;

            if (isRecurring) {
                dueInput.value = '';
            }

            if (weeklyConfig && weeklySelect) {
                weeklyConfig.classList.toggle('hidden', !isWeekly);
                weeklySelect.disabled = !isWeekly;
                weeklySelect.required = isWeekly;
                if (!isWeekly) {
                    weeklySelect.value = '';
                }
            }

            if (monthlyConfig && monthlyInput) {
                monthlyConfig.classList.toggle('hidden', !isMonthly);
                monthlyInput.disabled = !isMonthly;
                monthlyInput.required = isMonthly;
                if (!isMonthly) {
                    monthlyInput.value = '';
                }
            }
        };

        typeSelect.addEventListener('change', toggleDueDate);
        toggleDueDate();

        if (checkAll && studentList) {
            var checkboxes = studentList.querySelectorAll('.student-checkbox');

            var refreshCheckAllState = function () {
                var total = 0;
                var checked = 0;

                checkboxes.forEach(function (checkbox) {
                    var row = checkbox.closest('li');
                    if (!row || row.classList.contains('hidden')) {
                        return;
                    }

                    total++;
                    if (checkbox.checked) {
                        checked++;
                    }
                });

                if (total === 0 || checked === 0) {
                    checkAll.indeterminate = false;
                    checkAll.checked = false;
                } else if (checked === total) {
                    checkAll.indeterminate = false;
                    checkAll.checked = true;
                } else {
                    checkAll.indeterminate = true;
                }
            };

            checkAll.addEventListener('change', function () {
                var targetChecked = checkAll.checked;
                checkAll.indeterminate = false;

                checkboxes.forEach(function (checkbox) {
                    if (!checkbox.closest('li').classList.contains('hidden')) {
                        checkbox.checked = targetChecked;
                    }
                });

                refreshCheckAllState();
            });

            checkboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', refreshCheckAllState);
            });

            if (searchInput) {
                var applyFilter = function () {
                    var keyword = searchInput.value.trim().toLowerCase();

                    checkboxes.forEach(function (checkbox) {
                        var row = checkbox.closest('li');
                        if (!row) {
                            return;
                        }

                        var name = row.getAttribute('data-student-name') || '';
                        var visible = keyword === '' || name.indexOf(keyword) !== -1;
                        row.classList.toggle('hidden', !visible);
                    });

                    refreshCheckAllState();
                };

                searchInput.addEventListener('input', applyFilter);
                applyFilter();
            } else {
                refreshCheckAllState();
            }
        }
    })();
</script>
<script>
    (function () {
        var categorySelect = document.getElementById('kategori_id');
        var typeSelect = document.getElementById('jenis_penagihan');
        var helper = document.getElementById('billing-type-note');
        var startField = document.getElementById('start-date-field');
        var startInput = document.getElementById('rutin_mulai');

        var updateBillingTypeOptions = function () {
            if (!categorySelect || !typeSelect) {
                return;
            }

            var selectedOption = categorySelect.options[categorySelect.selectedIndex] ?? null;
            var categoryType = selectedOption?.dataset?.type ?? '';
            var isRoutine = categoryType.toLowerCase() === 'rutin';

            Array.prototype.forEach.call(typeSelect.options, function (option) {
                if (option.value === '' || option.value === 'tidak') {
                    option.disabled = false;
                    return;
                }

                option.disabled = !isRoutine;
            });

            if (!isRoutine && typeSelect.value !== 'tidak') {
                typeSelect.value = 'tidak';
                typeSelect.dispatchEvent(new Event('change'));
            }

            if (helper) {
                helper.textContent = isRoutine
                    ? 'Kategori rutin dapat memilih jenis Mingguan atau Bulanan.'
                    : 'Kategori insidental hanya dapat dibuat sebagai Sekali (insidental).';
            }
            if (startField && startInput) {
                startField.classList.toggle('hidden', !isRoutine);
                startInput.disabled = !isRoutine;
                startInput.required = isRoutine;
                if (!isRoutine) {
                    startInput.value = '';
                }
            }
        };

            if (categorySelect && typeSelect) {
                categorySelect.addEventListener('change', updateBillingTypeOptions);
                updateBillingTypeOptions();
            }
        })();
    </script>

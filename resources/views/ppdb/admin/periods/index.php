<?php
    /** @var array<int, array<string, mixed>> $periods */
    /** @var array<string, array<string, string>> $stageDefinitions */
    /** @var array<string, string> $teacherOptions */
    /** @var array<int, string> $schoolYearOptions */

    $successMessage = session_flash('success');
    $errorMessage = session_flash('error');
    $editing = $editingPeriod ?? null;
    $editingId = $editing['id'] ?? null;
    $formAction = $editingId !== null
        ? base_url('ppdb/admin/periode/' . $editingId . '/update')
        : base_url('ppdb/admin/periode');
    $formTitle = $editingId !== null ? 'Ubah Periode PPDB' : 'Tambah Periode PPDB';
    $responsibleSelected = old('responsibles', $editingResponsibles ?? []);
    if (!is_array($responsibleSelected)) {
        $responsibleSelected = $responsibleSelected === null ? [] : [$responsibleSelected];
    }
    $responsibleSelected = array_values(array_unique(array_map(static fn ($value) => (int) $value, $responsibleSelected)));
    $statusOptions = [
        'draft' => 'Draft',
        'aktif' => 'Aktif',
        'selesai' => 'Selesai',
        'arsip' => 'Arsip',
    ];
?>

<div class="space-y-6">
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
                <h2 class="text-lg font-semibold text-slate-800 dark:text-gray-100">Daftar Periode PPDB</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-gray-400">Pantau status seluruh periode PPDB dan tahapan aktif-nya.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-800/60 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Periode</th>
                            <th class="px-4 py-3 text-left">Jadwal Pendaftaran</th>
                            <th class="px-4 py-3 text-left">Tahap Aktif</th>
                            <th class="px-4 py-3 text-left">Penanggung Jawab</th>
                            <th class="px-4 py-3 text-left">Link Pendaftaran</th>
                            <th class="px-4 py-3 text-right">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <?php if (empty($periods)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-gray-400">Belum ada periode PPDB.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($periods as $period): ?>
                                <?php
                                    $start = $period['pendaftaran_mulai'] ?? null;
                                    $end = $period['pendaftaran_selesai'] ?? null;
                                    $scheduleParts = [];
                                    if ($start) {
                                        $scheduleParts[] = date('d M Y', strtotime($start));
                                    }
                                    if ($end) {
                                        $scheduleParts[] = date('d M Y', strtotime($end));
                                    }
                                    $badgeClasses = match ($period['status'] ?? 'draft') {
                                        'aktif' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
                                        'selesai' => 'bg-slate-200 text-slate-700 dark:bg-slate-600/40 dark:text-slate-200',
                                        'arsip' => 'bg-slate-200 text-slate-600 dark:bg-slate-700/40 dark:text-slate-300',
                                        default => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
                                    };
                                ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-700/40">
                                    <td class="px-4 py-4 align-top">
                                        <div class="text-sm font-semibold text-slate-800 dark:text-gray-100"><?= htmlspecialchars($period['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="mt-1 text-xs uppercase tracking-wide text-slate-400 dark:text-gray-500">Kode: <?= htmlspecialchars($period['kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if (!empty($period['tahun_masuk'])): ?>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-gray-400">TP <?= htmlspecialchars($period['tahun_masuk'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <?php if (!empty($scheduleParts)): ?>
                                            <div class="text-sm text-slate-700 dark:text-gray-200"><?= htmlspecialchars(implode(' – ', $scheduleParts), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 dark:text-gray-500">Belum disetel</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex flex-wrap gap-2">
                                            <?php foreach ($stageDefinitions as $key => $definition): ?>
                                                <?php $enabled = !empty($period[$definition['column']]); ?>
                                                <span class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium <?= $enabled ? 'border-indigo-200 bg-indigo-50 text-indigo-600 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300' : 'border-slate-200 text-slate-400 dark:border-slate-600 dark:text-slate-400'; ?>">
                                                    <?= htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <?php if (!empty($period['penanggung_jawab_nama'])): ?>
                                            <div class="text-sm text-slate-700 dark:text-gray-200"><?= htmlspecialchars($period['penanggung_jawab_nama'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 dark:text-gray-500">Belum ditetapkan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 align-top text-xs text-slate-600 dark:text-gray-300 space-y-2">
                                        <?php if (!empty($period['kode'])): ?>
                                            <?php $friendlyUrl = absolute_url('ppdb/pendaftaran/' . $period['kode']); ?>
                                            <div>
                                                <p class="font-semibold text-slate-700 dark:text-gray-100">Link Mudah</p>
                                                <div class="mt-1 flex items-center gap-2">
                                                    <input type="text" readonly value="<?= htmlspecialchars($friendlyUrl, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 dark:border-slate-600 dark:bg-slate-900/60" onclick="this.select()" />
                                                    <button type="button" class="rounded-lg bg-indigo-100 px-2 py-1 text-[11px] font-semibold text-indigo-600 hover:bg-indigo-200 dark:bg-indigo-500/20 dark:text-indigo-200" data-copy-target="<?= htmlspecialchars($friendlyUrl, ENT_QUOTES, 'UTF-8') ?>">Salin</button>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($period['token_pendaftaran'])): ?>
                                            <?php $secureUrl = absolute_url('ppdb/pendaftaran/' . $period['token_pendaftaran']); ?>
                                            <div>
                                                <p class="font-semibold text-slate-700 dark:text-gray-100">Link Token</p>
                                                <div class="mt-1 flex items-center gap-2">
                                                    <input type="text" readonly value="<?= htmlspecialchars($secureUrl, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 dark:border-slate-600 dark:bg-slate-900/60" onclick="this.select()" />
                                                    <button type="button" class="rounded-lg bg-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-300 dark:bg-slate-700/40 dark:text-slate-200" data-copy-target="<?= htmlspecialchars($secureUrl, ENT_QUOTES, 'UTF-8') ?>">Salin</button>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="inline-block rounded-lg bg-slate-100 px-2 py-1 text-[11px] text-slate-500 dark:bg-slate-700/40 dark:text-slate-300">Token belum tersedia</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-right align-top">
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $badgeClasses ?>">
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $period['status'] ?? 'draft')), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-right align-top">
                                        <div class="flex justify-end gap-2">
                                            <a href="<?= htmlspecialchars(base_url('ppdb/admin/periode?edit=' . (int) $period['id']), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-slate-600 dark:text-gray-200 dark:hover:border-indigo-300 dark:hover:text-indigo-200">
                                                Edit
                                            </a>
                                            <form action="<?= htmlspecialchars(base_url('ppdb/admin/periode/' . (int) $period['id'] . '/delete'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus periode ini? Aksi tidak dapat dibatalkan.');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 dark:border-rose-500/40 dark:text-rose-300 dark:hover:bg-rose-500/10">
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800 dark:text-gray-100"><?= htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-gray-400">Atur jadwal dan tahapan PPDB beserta guru penanggung jawab.</p>
                </div>
                <?php if ($editingId !== null): ?>
                    <a href="<?= htmlspecialchars(base_url('ppdb/admin/periode'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-300">Batal</a>
                <?php endif; ?>
            </div>
            <form action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-6 space-y-5">
                <?= csrf_field() ?>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Kode Periode
                        <input type="text" name="kode" value="<?= htmlspecialchars((string) old('kode', $editing['kode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Nama Periode
                        <input type="text" name="nama" value="<?= htmlspecialchars((string) old('nama', $editing['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Tahun Masuk
                        <input type="text" name="tahun_masuk" placeholder="2025/2026" value="<?= htmlspecialchars((string) old('tahun_masuk', $editing['tahun_masuk'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Tahun Ajaran Tujuan
                        <select name="tahun_ajaran_target_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100">
                            <option value="">Pilih...</option>
                            <?php foreach ($schoolYearOptions as $yearId => $label): ?>
                                <option value="<?= (int) $yearId ?>" <?= (int) old('tahun_ajaran_target_id', $editing['tahun_ajaran_target_id'] ?? 0) === (int) $yearId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Pendaftaran Mulai
                        <input type="date" name="pendaftaran_mulai" value="<?= htmlspecialchars((string) old('pendaftaran_mulai', $editing['pendaftaran_mulai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Pendaftaran Selesai
                        <input type="date" name="pendaftaran_selesai" value="<?= htmlspecialchars((string) old('pendaftaran_selesai', $editing['pendaftaran_selesai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Seleksi Mulai
                        <input type="date" name="seleksi_mulai" value="<?= htmlspecialchars((string) old('seleksi_mulai', $editing['seleksi_mulai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Seleksi Selesai
                        <input type="date" name="seleksi_selesai" value="<?= htmlspecialchars((string) old('seleksi_selesai', $editing['seleksi_selesai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Pengumuman Mulai
                        <input type="date" name="pengumuman_mulai" value="<?= htmlspecialchars((string) old('pengumuman_mulai', $editing['pengumuman_mulai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Pengumuman Selesai
                        <input type="date" name="pengumuman_selesai" value="<?= htmlspecialchars((string) old('pengumuman_selesai', $editing['pengumuman_selesai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Daftar Ulang Mulai
                        <input type="date" name="daftar_ulang_mulai" value="<?= htmlspecialchars((string) old('daftar_ulang_mulai', $editing['daftar_ulang_mulai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Daftar Ulang Selesai
                        <input type="date" name="daftar_ulang_selesai" value="<?= htmlspecialchars((string) old('daftar_ulang_selesai', $editing['daftar_ulang_selesai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Pembayaran Mulai
                        <input type="date" name="pembayaran_mulai" value="<?= htmlspecialchars((string) old('pembayaran_mulai', $editing['pembayaran_mulai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Pembayaran Selesai
                        <input type="date" name="pembayaran_selesai" value="<?= htmlspecialchars((string) old('pembayaran_selesai', $editing['pembayaran_selesai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                </div>

                <div class="space-y-3">
                    <p class="text-sm font-semibold text-slate-600 dark:text-gray-200">Tahapan yang Diaktifkan</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <?php foreach ($stageDefinitions as $key => $definition): ?>
                            <?php
                                $column = $definition['column'];
                                $defaultValue = $editingId !== null
                                    ? (int) ($editing[$column] ?? 0)
                                    : ($key === 'pendaftaran' ? 1 : 0);
                                $checked = (int) old('stage_' . $key, $defaultValue) === 1;
                            ?>
                            <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:border-indigo-400 dark:border-slate-600 dark:text-gray-200 dark:hover:border-indigo-400">
                                <input type="checkbox" name="stage_<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" value="1" <?= $checked ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                                <?= htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8') ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-600 dark:text-gray-200">Guru Penanggung Jawab</label>
                    <p class="mt-1 text-xs text-slate-500 dark:text-gray-400">Pilih minimal satu guru. Urutan pertama menjadi ketua, kedua sekretaris.</p>
                    <select name="responsibles[]" multiple size="6" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100">
                        <?php foreach ($teacherOptions as $teacherId => $label): ?>
                            <option value="<?= (int) $teacherId ?>" <?= in_array((int) $teacherId, $responsibleSelected, true) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Status Periode
                        <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100">
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) old('status', $editing['status'] ?? 'draft') === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-200">
                        Catatan
                        <textarea name="catatan" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100"><?= htmlspecialchars((string) old('catatan', $editing['catatan'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <?php if ($editingId !== null): ?>
                        <a href="<?= htmlspecialchars(base_url('ppdb/admin/periode'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300 hover:bg-slate-100 dark:border-slate-600 dark:text-gray-200 dark:hover:border-slate-500 dark:hover:bg-slate-700">Reset</a>
                    <?php endif; ?>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900">
                        Simpan Periode
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<script>
    (function () {
        document.querySelectorAll('[data-copy-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                const text = button.getAttribute('data-copy-target');
                if (!text) {
                    return;
                }
                navigator.clipboard?.writeText(text).then(function () {
                    button.textContent = 'Tersalin';
                    setTimeout(function () {
                        button.textContent = 'Salin';
                    }, 1500);
                }).catch(function () {
                    const input = document.createElement('input');
                    input.value = text;
                    document.body.appendChild(input);
                    input.select();
                    document.execCommand('copy');
                    document.body.removeChild(input);
                    button.textContent = 'Tersalin';
                    setTimeout(function () {
                        button.textContent = 'Salin';
                    }, 1500);
                });
            });
        });
    })();
</script>

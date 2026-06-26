<?php
/** @var array<int, string> $assignedMajors */
/** @var array<int, array<string, mixed>> $requests */
/** @var array<string, string> $statusLabels */
/** @var array<string, mixed>|null $activeYear */
/** @var array<string, mixed>|null $editingRequest */
/** @var array<int, string> $lpjAllowedMimes */
/** @var int $lpjMaxSizeKb */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');

$statusClass = static function (string $status): string {
    return match ($status) {
        'submitted' => 'bg-amber-100 text-amber-700',
        'approved' => 'bg-emerald-100 text-emerald-700',
        'rejected' => 'bg-rose-100 text-rose-700',
        'funded' => 'bg-indigo-100 text-indigo-700',
        'reported' => 'bg-slate-200 text-slate-700',
        default => 'bg-slate-200 text-slate-700',
    };
};

$formActionUrl = htmlspecialchars(base_url('keuangan/kaprodi/pengadaan'), ENT_QUOTES, 'UTF-8');
$canSubmit = !empty($assignedMajors) && $activeYear !== null;
$lpjMimeLabels = array_map(static function (string $mime): string {
    return match ($mime) {
        'application/pdf' => 'PDF',
        'image/jpeg' => 'JPG',
        'image/png' => 'PNG',
        default => strtoupper($mime),
    };
}, $lpjAllowedMimes);
?>

<div class="space-y-8">
    <div class="rounded-2xl border border-slate-200 bg-white/90 shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-800">Pengajuan Pengadaan Alat Praktik</h2>
            <p class="mt-1 text-sm text-slate-500">
                Ajukan kebutuhan alat praktik untuk jurusan yang Anda pimpin. Pengajuan akan diperiksa kepala sekolah sebelum dicairkan bendahara. Setelah pencairan, lengkapi LPJ sebagai pertanggungjawaban.
            </p>
        </div>
        <div class="px-6 py-5 space-y-4">
            <?php if (!$canSubmit): ?>
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <p class="font-semibold">Pengajuan belum dapat dilakukan.</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        <?php if (empty($assignedMajors)): ?>
                            <li>Anda belum ditetapkan sebagai kepala program studi pada tahun ajaran ini.</li>
                        <?php endif; ?>
                        <?php if ($activeYear === null): ?>
                            <li>Tahun ajaran aktif belum dipilih.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php else: ?>
                <?php $isEditing = $editingRequest !== null; ?>
                <form method="post" action="<?= $formActionUrl ?>" class="space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="request_id" value="<?= $isEditing ? (int) ($editingRequest['id'] ?? 0) : 0 ?>">
                    <div class="grid gap-4 lg:grid-cols-12">
                        <div class="lg:col-span-4">
                            <label for="field-jurusan" class="text-sm font-semibold text-slate-700">Jurusan <span class="text-rose-500">*</span></label>
                            <select
                                id="field-jurusan"
                                name="jurusan_id"
                                class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                required
                            >
                                <option value="">Pilih Jurusan</option>
                                <?php foreach ($assignedMajors as $majorId => $label): ?>
                                    <?php
                                        $currentMajor = $isEditing
                                            ? (int) ($editingRequest['jurusan_id'] ?? 0)
                                            : (int) old('jurusan_id', 0);
                                    ?>
                                    <option value="<?= (int) $majorId ?>" <?= $currentMajor === (int) $majorId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="lg:col-span-8">
                            <label for="field-judul" class="text-sm font-semibold text-slate-700">Judul Pengadaan <span class="text-rose-500">*</span></label>
                            <input
                                type="text"
                                id="field-judul"
                                name="judul"
                                maxlength="150"
                                value="<?= htmlspecialchars((string) old('judul', $isEditing ? ($editingRequest['judul'] ?? '') : ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="Contoh: Pengadaan Mesin Bubut Mini 2024"
                                required
                            >
                        </div>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-12">
                        <div class="lg:col-span-8">
                            <label for="field-tujuan" class="text-sm font-semibold text-slate-700">Tujuan / Latar Belakang</label>
                            <textarea
                                id="field-tujuan"
                                name="tujuan"
                                rows="3"
                                class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="Jelaskan kebutuhan jurusan atau kompetensi yang didukung"
                            ><?= htmlspecialchars((string) old('tujuan', $isEditing ? ($editingRequest['tujuan'] ?? '') : ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="lg:col-span-4">
                            <label for="field-estimasi" class="text-sm font-semibold text-slate-700">Estimasi Biaya <span class="text-rose-500">*</span></label>
                            <input
                                type="text"
                                id="field-estimasi"
                                name="estimasi"
                                inputmode="decimal"
                                value="<?= htmlspecialchars((string) old('estimasi', $isEditing ? (string) ($editingRequest['total_estimasi'] ?? '0') : ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="Contoh: 12.500.000"
                                required
                            >
                        </div>
                    </div>
                    <div>
                        <label for="field-rincian" class="text-sm font-semibold text-slate-700">Rincian Kebutuhan</label>
                        <textarea
                            id="field-rincian"
                            name="rincian_kebutuhan"
                            rows="4"
                            class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="Tuliskan daftar alat, spesifikasi, dan jumlah yang diperlukan"
                        ><?= htmlspecialchars((string) old('rincian_kebutuhan', $isEditing ? ($editingRequest['rincian_kebutuhan'] ?? '') : ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            name="action"
                            value="draft"
                            class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Simpan Draft
                        </button>
                        <button
                            type="submit"
                            name="action"
                            value="submit"
                            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            Kirim Pengajuan
                        </button>
                        <?php if ($isEditing): ?>
                            <a href="<?= htmlspecialchars(base_url('keuangan/kaprodi/pengadaan'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Batalkan Edit</a>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white/90 shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-800">Riwayat Pengajuan</h2>
                <?php if ($activeYear !== null): ?>
                    <p class="text-xs text-slate-500">Tahun Ajaran: <?= htmlspecialchars((string) ($activeYear['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Pengajuan</th>
                        <th class="px-6 py-3">Estimasi</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-6 text-center text-slate-500">Belum ada pengajuan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $row): ?>
                            <?php
                                $status = (string) ($row['status'] ?? '');
                                $isEditable = in_array($status, ['draft', 'rejected'], true);
                            ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-800"><?= htmlspecialchars((string) ($row['judul'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-500">Kode: <?= htmlspecialchars((string) ($row['kode'] ?? ''), ENT_QUOTES, 'UTF-8') ?> • Jurusan: <?= htmlspecialchars((string) ($row['jurusan_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php if (!empty($row['review_note'])): ?>
                                        <p class="mt-1 text-xs text-slate-500">Catatan Kepala Sekolah: <?= htmlspecialchars((string) $row['review_note'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($row['funding_note'])): ?>
                                        <p class="mt-1 text-xs text-slate-500">Catatan Bendahara: <?= htmlspecialchars((string) $row['funding_note'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($row['lpj_deskripsi'])): ?>
                                        <p class="mt-1 text-xs text-slate-500">Ringkasan LPJ: <?= nl2br(htmlspecialchars((string) $row['lpj_deskripsi'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($row['lpj_lampiran'])): ?>
                                        <?php $lpjPath = (string) $row['lpj_lampiran']; ?>
                                        <p class="mt-1 text-xs">
                                            <a class="text-indigo-600 hover:underline" href="<?= htmlspecialchars(base_url('storage/' . ltrim($lpjPath, '/')), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                Lihat Lampiran LPJ
                                            </a>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-slate-700 font-semibold"><?= $formatCurrency((float) ($row['total_estimasi'] ?? 0)) ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusClass($status) ?>">
                                        <?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <?php if ($isEditable): ?>
                                            <a href="<?= htmlspecialchars(base_url('keuangan/kaprodi/pengadaan?edit=' . (int) ($row['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Ubah</a>
                                            <form method="post" action="<?= htmlspecialchars(base_url('keuangan/kaprodi/pengadaan/' . (int) ($row['id'] ?? 0) . '/ajukan'), ENT_QUOTES, 'UTF-8') ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Ajukan</button>
                                            </form>
                                        <?php elseif ($status === 'funded'): ?>
                                            <form
                                                method="post"
                                                action="<?= htmlspecialchars(base_url('keuangan/kaprodi/pengadaan/' . (int) ($row['id'] ?? 0) . '/lpj'), ENT_QUOTES, 'UTF-8') ?>"
                                                enctype="multipart/form-data"
                                                class="w-full space-y-2"
                                            >
                                                <?= csrf_field() ?>
                                                <textarea
                                                    name="lpj_deskripsi"
                                                    rows="2"
                                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:border-emerald-500 focus:outline-none focus:ring"
                                                    placeholder="Tuliskan ringkasan penggunaan dana dan hasilnya"
                                                    required
                                                ></textarea>
                                                <input
                                                    type="file"
                                                    name="lpj_lampiran"
                                                    accept=".pdf,.jpg,.jpeg,.png"
                                                    class="block w-full text-xs text-slate-600 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                                                >
                                                <p class="text-[11px] text-slate-400">
                                                    Format: <?= htmlspecialchars(implode(', ', $lpjMimeLabels), ENT_QUOTES, 'UTF-8') ?> · Maks <?= (int) $lpjMaxSizeKb ?> KB
                                                </p>
                                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">
                                                    Kirim LPJ
                                                </button>
                                            </form>
                                        <?php elseif ($status === 'reported'): ?>
                                            <p class="text-xs text-slate-400">LPJ telah dikirim.</p>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400">Tidak ada aksi</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
    $cachedStatuses = isset($records) && is_array($records) ? $records : [];
    $oldStatusValues = isset($oldStatuses) && is_array($oldStatuses) ? $oldStatuses : [];
    $oldNoteValues = isset($oldNotes) && is_array($oldNotes) ? $oldNotes : [];
    $oldDiplomaNumberValues = isset($oldDiplomaNumbers) && is_array($oldDiplomaNumbers) ? $oldDiplomaNumbers : [];
    $oldSpecializationTypeValues = isset($oldSpecializationTypes) && is_array($oldSpecializationTypes) ? $oldSpecializationTypes : [];
    $signatureRecords = isset($signatureRecords) && is_array($signatureRecords) ? $signatureRecords : [];
    $signatureSummary = isset($signatureSummary) && is_array($signatureSummary)
        ? $signatureSummary
        : ['total' => 0, 'requested' => 0, 'pending' => 0, 'approved' => 0, 'revoked' => 0, 'not_requested' => 0];
    $digitalSignatureEnabled = (bool) ($digitalSignatureEnabled ?? false);
?>
<style>
    #kelas_id,
    #kelas_id option,
    select[name^="status["],
    select[name^="status["] option,
    input[name^="nomor_ijazah["],
    input[name^="jenis_kekhususan["] {
        color: #334155 !important;
        -webkit-text-fill-color: #334155 !important;
        background-color: #ffffff !important;
        opacity: 1 !important;
    }

    .dark #kelas_id,
    .dark #kelas_id option,
    .dark select[name^="status["],
    .dark select[name^="status["] option,
    .dark input[name^="nomor_ijazah["],
    .dark input[name^="jenis_kekhususan["] {
        color: #e2e8f0 !important;
        -webkit-text-fill-color: #e2e8f0 !important;
        background-color: #0f172a !important;
        opacity: 1 !important;
    }

    #kelas_id {
        color-scheme: light !important;
    }

    .dark #kelas_id {
        color-scheme: dark !important;
    }
</style>

<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Status Kelulusan</h2>
            <p class="text-sm text-slate-500">
                Tetapkan status kelulusan untuk siswa tingkat 12 pada semester genap.
            </p>
        </div>
    </div>

    <?php if (empty($classes ?? [])): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            Anda belum tercatat sebagai wali kelas pada data kelas manapun. Hubungi admin untuk menugaskan Anda sebagai wali kelas.
        </div>
    <?php else: ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
            <form method="get" class="flex flex-col gap-3 md:flex-row md:items-center">
                <label for="kelas_id" class="text-sm font-medium text-slate-600">Pilih Kelas</label>
                <div class="flex gap-3">
                    <select
                        id="kelas_id"
                        name="kelas_id"
                        class="w-64 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500"
                        style="color:#334155;background-color:#ffffff;"
                    >
                        <?php foreach ($classes as $class): ?>
                            <?php $id = (int) ($class['id'] ?? 0); ?>
                            <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= $id === (int) ($selectedClassId ?? 0) ? 'selected' : '' ?> style="color:#334155;background-color:#ffffff;">
                                <?= htmlspecialchars('Tingkat ' . ($class['tingkat'] ?? '-') . ' · ' . ($class['nama'] ?? 'Kelas') . ' (' . ($class['tahun_ajaran_nama'] ?? '-') . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400"
                    >
                        Tampilkan
                    </button>
                </div>
            </form>

            <?php if (empty($students ?? [])): ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
                    Belum ada siswa yang terdaftar pada kelas ini.
                </div>
            <?php else: ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-800">Pengajuan TTD Digital SKL</h3>
                            <p class="mt-1 text-xs text-slate-500">
                                Sistem hanya mengajukan siswa yang statusnya lulus dan nilai semua mapel sudah tuntas.
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600">Total <?= htmlspecialchars((string) ($signatureSummary['total'] ?? 0), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-amber-700">Menunggu <?= htmlspecialchars((string) ($signatureSummary['pending'] ?? 0), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-emerald-700">Disetujui <?= htmlspecialchars((string) ($signatureSummary['approved'] ?? 0), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600">Belum diajukan <?= htmlspecialchars((string) ($signatureSummary['not_requested'] ?? 0), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                        <form action="<?= htmlspecialchars(base_url('walikelas/skl/ajukan'), ENT_QUOTES, 'UTF-8') ?>" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) ($selectedClassId ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:cursor-not-allowed disabled:opacity-60"
                                <?= $digitalSignatureEnabled ? '' : 'disabled' ?>
                                onclick="return confirm('Ajukan TTD digital SKL untuk siswa yang sudah memenuhi syarat?');"
                            >
                                <i class="ri-shield-check-line text-lg"></i>
                                Ajukan TTD Digital SKL
                            </button>
                            <?php if (!$digitalSignatureEnabled): ?>
                                <p class="mt-2 text-xs text-amber-700">TTD digital belum aktif pada tahun ajaran ini.</p>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <form action="<?= htmlspecialchars(base_url('walikelas/status-lulus'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) ($selectedClassId ?? 0), ENT_QUOTES, 'UTF-8') ?>">

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">No</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Nama Siswa</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Status</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Nomor Ijazah</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Jenis Kekhususan</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">TTD SKL</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Cetak SKL</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php foreach ($students as $index => $student): ?>
                                    <?php
                                        $studentId = (int) ($student['id'] ?? 0);
                                        $record = isset($cachedStatuses[$studentId]) ? $cachedStatuses[$studentId] : ['status' => null, 'catatan' => null];
                                        $statusValue = $oldStatusValues[$studentId] ?? ($record['status'] ?? '');
                                        $noteValue = $oldNoteValues[$studentId] ?? ($record['catatan'] ?? '');
                                        $diplomaNumberValue = $oldDiplomaNumberValues[$studentId] ?? ($record['nomor_ijazah'] ?? '');
                                        $specializationTypeValue = $oldSpecializationTypeValues[$studentId] ?? ($record['jenis_kekhususan'] ?? '');
                                        $studentInactive = student_is_inactive($student);
                                        $inactiveTitle = 'Siswa nonaktif; status kelulusan tidak dapat diinput.';
                                        $passOnlyTitle = 'Field ini hanya diisi untuk siswa yang dinyatakan lulus.';
                                        $signature = $signatureRecords[$studentId] ?? null;
                                        $signatureStatus = $signature['status'] ?? 'belum';
                                        $signatureLabel = 'Belum diajukan';
                                        $signatureClass = 'border-slate-200 bg-slate-50 text-slate-500';
                                        if ($signatureStatus === 'pending') {
                                            $signatureLabel = 'Menunggu';
                                            $signatureClass = 'border-amber-200 bg-amber-50 text-amber-700';
                                        } elseif ($signatureStatus === 'approved') {
                                            $signatureLabel = 'Disetujui';
                                            $signatureClass = 'border-emerald-200 bg-emerald-50 text-emerald-700';
                                        } elseif ($signatureStatus === 'revoked') {
                                            $signatureLabel = 'Dicabut';
                                            $signatureClass = 'border-rose-200 bg-rose-50 text-rose-700';
                                        }
                                        $printUrl = null;
                                        if ($signature !== null && ($signature['status'] ?? '') === 'approved') {
                                            $printUrl = base_url('kelulusan/skl/' . urlencode((string) ($signature['id'] ?? 0)) . '/cetak');
                                        }
                                    ?>
                                    <tr data-graduation-row>
                                        <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="px-4 py-3 font-medium text-slate-700">
                                            <p>
                                                <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                            </p>
                                            <p class="text-xs text-slate-400"><?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <select
                                                name="status[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                data-graduation-status
                                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                style="color:#334155;background-color:#ffffff;"
                                                <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                            >
                                                <option value="" style="color:#334155;background-color:#ffffff;">Belum ditentukan</option>
                                                <option value="lulus" <?= $statusValue === 'lulus' ? 'selected' : '' ?> style="color:#334155;background-color:#ffffff;">Lulus</option>
                                                <option value="tidak_lulus" <?= $statusValue === 'tidak_lulus' ? 'selected' : '' ?> style="color:#334155;background-color:#ffffff;">Tidak Lulus</option>
                                            </select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input
                                                type="text"
                                                name="nomor_ijazah[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                value="<?= htmlspecialchars((string) $diplomaNumberValue, ENT_QUOTES, 'UTF-8') ?>"
                                                class="w-44 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                placeholder="Nomor ijazah"
                                                data-pass-only-field
                                                title="<?= htmlspecialchars($studentInactive ? $inactiveTitle : $passOnlyTitle, ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $studentInactive ? 'disabled' : '' ?>
                                            >
                                        </td>
                                        <td class="px-4 py-3">
                                            <input
                                                type="text"
                                                name="jenis_kekhususan[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                value="<?= htmlspecialchars((string) $specializationTypeValue, ENT_QUOTES, 'UTF-8') ?>"
                                                class="w-48 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                placeholder="Jenis kekhususan"
                                                data-pass-only-field
                                                title="<?= htmlspecialchars($studentInactive ? $inactiveTitle : $passOnlyTitle, ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $studentInactive ? 'disabled' : '' ?>
                                            >
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold <?= $signatureClass ?>">
                                                <?= htmlspecialchars($signatureLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if ($printUrl !== null): ?>
                                                <a
                                                    href="<?= htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                    target="_blank"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                >
                                                    <i class="ri-printer-line"></i>
                                                    Cetak
                                                </a>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <textarea
                                                name="catatan[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                rows="2"
                                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                placeholder="Catatan tambahan (opsional)"
                                                <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                            ><?= htmlspecialchars((string) $noteValue, ENT_QUOTES, 'UTF-8') ?></textarea>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between">
                        <p class="text-xs text-slate-400">Pilih status kelulusan atau biarkan kosong jika belum ditentukan.</p>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        >
                            <i class="ri-save-3-line text-lg"></i>
                            Simpan Status
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-graduation-row]').forEach(function (row) {
            const statusSelect = row.querySelector('[data-graduation-status]');
            const passOnlyFields = row.querySelectorAll('[data-pass-only-field]');

            if (!statusSelect || passOnlyFields.length === 0) {
                return;
            }

            const syncFields = function () {
                const canEdit = statusSelect.value === 'lulus' && !statusSelect.disabled;
                passOnlyFields.forEach(function (field) {
                    field.disabled = !canEdit;
                    field.classList.toggle('bg-slate-100', !canEdit);
                });
            };

            statusSelect.addEventListener('change', syncFields);
            syncFields();
        });
    });
</script>

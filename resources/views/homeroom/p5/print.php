<?php
    $classes = isset($classes) && is_array($classes) ? $classes : [];
    $students = isset($students) && is_array($students) ? $students : [];
    $selectedClassId = isset($selectedClassId) ? (int) $selectedClassId : 0;
    $selectedStudentId = isset($selectedStudentId) ? (int) $selectedStudentId : 0;
    $selectedClass = isset($selectedClass) && is_array($selectedClass) ? $selectedClass : null;
    $hasClasses = !empty($classes);
    $hasStudents = !empty($students);
    $paperSize = 'f4';
    $paperOptions = [
        'f4' => 'F4 / Folio (33 x 21,5 cm)',
        'a4' => 'A4 (29,7 x 21 cm)',
    ];
    $digitalSignatureEnabled = (bool) ($digitalSignatureEnabled ?? false);
    $digitalSignatureSummary = $digitalSignatureSummary ?? ['total' => 0, 'requested' => 0, 'pending' => 0, 'approved' => 0, 'revoked' => 0, 'not_requested' => 0, 'canRequest' => false, 'canRequestClass' => false];
    $selectedStudentSignature = $selectedStudentSignature ?? null;

    $printParams = [
        'kelas_id' => $selectedClassId,
        'siswa_id' => $selectedStudentId,
        'paper' => $paperSize,
    ];
    $printUrl = base_url('walikelas/p5/cetak/print') . '?' . http_build_query($printParams);
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Projek Profil Pelajar Pancasila</p>
        <h2 class="text-xl font-semibold text-slate-800">Cetak Rapor P5</h2>
        <p class="mt-1 text-sm text-slate-500">
            Pilih kelas Kurmer dan siswa untuk mencetak rapor P5 (proyek Profil Pelajar Pancasila).
        </p>
    </div>

    <?php if (!$hasClasses): ?>
        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-600">
            Tidak ada kelas Kurikulum Merdeka yang Anda ampuh sebagai wali kelas.
        </div>
    <?php else: ?>
        <div class="grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-800">Parameter Cetak</h3>
                    <form method="get" class="mt-4 space-y-4">
                        <div>
                            <label for="kelas_id" class="block text-sm font-medium text-slate-600">Kelas Kurmer</label>
                            <select
                                id="kelas_id"
                                name="kelas_id"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring"
                                onchange="this.form.submit()"
                            >
                                <?php foreach ($classes as $class): ?>
                                    <?php $classId = (int) ($class['id'] ?? 0); ?>
                                    <option value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>" <?= $classId === $selectedClassId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(($class['tingkat'] ?? '-') . ' ' . ($class['nama'] ?? '-') . ' · ' . strtoupper((string) ($class['kurikulum'] ?? 'k13')), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mt-2 text-xs text-slate-500">Hanya kelas Kurikulum Merdeka yang ditampilkan.</p>
                        </div>

                        <div>
                            <label for="siswa_id" class="block text-sm font-medium text-slate-600">Siswa</label>
                            <?php if (!$hasStudents): ?>
                                <div class="mt-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                                    Belum ada siswa pada kelas ini.
                                </div>
                            <?php else: ?>
                                <select
                                    id="siswa_id"
                                    name="siswa_id"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring"
                                >
                                    <?php foreach ($students as $student): ?>
                                        <?php $sid = (int) ($student['id'] ?? 0); ?>
                                        <option value="<?= htmlspecialchars((string) $sid, ENT_QUOTES, 'UTF-8') ?>" <?= $sid === $selectedStudentId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(($student['nama'] ?? '-') . ' — ' . ($student['nisn'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label for="paper" class="block text-sm font-medium text-slate-600">Ukuran Kertas</label>
                            <select
                                id="paper"
                                name="paper"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring"
                                disabled
                            >
                                <?php foreach ($paperOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $value === $paperSize ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mt-2 text-xs text-slate-500">Format default F4. Hubungi admin bila perlu ukuran lain.</p>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a
                                href="<?= htmlspecialchars(base_url('walikelas/p5'), ENT_QUOTES, 'UTF-8') ?>"
                                class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition-colors duration-200 hover:bg-slate-100"
                            >
                                <i class="ri-arrow-left-line text-base text-slate-500"></i>
                                Kelola Projek
                            </a>
                            <a
                                href="<?= htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8') ?>"
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition duration-200 hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 <?= !$hasStudents ? 'pointer-events-none opacity-50' : '' ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <i class="ri-printer-line text-base"></i>
                                Cetak Rapor P5
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-7">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-800">Catatan</h3>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-600">
                        <li>Rapor P5 menampilkan semua projek P5 di kelas terpilih untuk tahun ajaran berjalan.</li>
                        <li>Pastikan ringkasan dan capaian projek sudah disimpan di menu “Projek P5”.</li>
                        <li>Jika siswa belum memiliki data projek, halaman cetak akan menampilkan keterangan kosong.</li>
                    </ul>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-slate-800">Status TTD Digital</h3>
                            <p class="text-sm text-slate-500">Pengajuan QR ke kepala sekolah untuk rapor P5.</p>
                        </div>
                        <?php if ($digitalSignatureEnabled): ?>
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Aktif</span>
                        <?php else: ?>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Nonaktif</span>
                        <?php endif; ?>
                    </div>

                    <?php
                        $statusLabel = 'Belum diajukan';
                        $statusMessage = 'Ajukan TTD digital agar kepala sekolah dapat menyetujui rapor P5.';
                        if (is_array($selectedStudentSignature)) {
                            $sigStatus = (string) ($selectedStudentSignature['status'] ?? 'pending');
                            if ($sigStatus === 'approved') {
                                $statusLabel = 'Disetujui';
                                $statusMessage = 'TTD digital sudah disetujui. Pastikan QR tampil saat mencetak.';
                            } elseif ($sigStatus === 'pending') {
                                $statusLabel = 'Menunggu persetujuan';
                                $statusMessage = 'Permohonan dikirim. Tunggu persetujuan kepala sekolah.';
                            } elseif ($sigStatus === 'revoked') {
                                $statusLabel = 'Dicabut';
                                $statusMessage = 'TTD digital dicabut. Ajukan ulang setelah perbaikan.';
                            } else {
                                $statusLabel = ucfirst($sigStatus);
                            }
                        }
                    ?>

                    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <p class="font-semibold"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-1 text-slate-600"><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (is_array($selectedStudentSignature) && !empty($selectedStudentSignature['verificationUrl'] ?? null)): ?>
                            <a
                                href="<?= htmlspecialchars($selectedStudentSignature['verificationUrl'], ENT_QUOTES, 'UTF-8') ?>"
                                target="_blank"
                                class="mt-2 inline-flex items-center gap-2 text-xs font-semibold text-indigo-600 hover:text-indigo-500"
                            >
                                <i class="ri-external-link-line"></i>
                                Tautan verifikasi
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-3 text-xs text-slate-600">
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <p class="font-semibold text-slate-700">Total Siswa</p>
                            <p><?= (int) ($digitalSignatureSummary['total'] ?? 0) ?></p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <p class="font-semibold text-slate-700">Sudah diajukan</p>
                            <p><?= (int) ($digitalSignatureSummary['requested'] ?? 0) ?></p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <p class="font-semibold text-slate-700">Menunggu</p>
                            <p><?= (int) ($digitalSignatureSummary['pending'] ?? 0) ?></p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <p class="font-semibold text-slate-700">Disetujui</p>
                            <p><?= (int) ($digitalSignatureSummary['approved'] ?? 0) ?></p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <p class="font-semibold text-slate-700">Dicabut</p>
                            <p><?= (int) ($digitalSignatureSummary['revoked'] ?? 0) ?></p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <p class="font-semibold text-slate-700">Belum diajukan</p>
                            <p><?= (int) ($digitalSignatureSummary['not_requested'] ?? 0) ?></p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <form
                            action="<?= htmlspecialchars(base_url('walikelas/p5/ttd-digital/request'), ENT_QUOTES, 'UTF-8') ?>"
                            method="post"
                            class="flex-1 space-y-2"
                            onsubmit="return confirm('Ajukan TTD digital P5 untuk siswa terpilih?');"
                        >
                            <?= csrf_field() ?>
                            <input type="hidden" name="class_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="student_id" value="<?= htmlspecialchars((string) $selectedStudentId, ENT_QUOTES, 'UTF-8') ?>">
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring focus:ring-indigo-200 disabled:cursor-not-allowed disabled:bg-slate-300"
                                <?= ($digitalSignatureSummary['canRequest'] ?? false) ? '' : 'disabled' ?>
                            >
                                <i class="ri-checkbox-circle-line text-lg"></i>
                                Ajukan TTD Digital P5 (Siswa)
                            </button>
                        </form>
                        <form
                            action="<?= htmlspecialchars(base_url('walikelas/p5/ttd-digital/request-class'), ENT_QUOTES, 'UTF-8') ?>"
                            method="post"
                            class="flex-1 space-y-2"
                            onsubmit="return confirm('Ajukan TTD digital P5 untuk seluruh siswa di kelas ini?');"
                        >
                            <?= csrf_field() ?>
                            <input type="hidden" name="class_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 focus:outline-none focus:ring focus:ring-emerald-200 disabled:cursor-not-allowed disabled:bg-slate-300"
                                <?= ($digitalSignatureSummary['canRequestClass'] ?? false) ? '' : 'disabled' ?>
                            >
                                <i class="ri-group-line text-lg"></i>
                                Ajukan TTD Digital P5 (Kelas)
                            </button>
                        </form>
                    </div>
                    <?php if (!$digitalSignatureEnabled): ?>
                        <p class="text-xs text-amber-600 mt-2">TTD digital belum diaktifkan pada tahun ajaran ini.</p>
                    <?php elseif ($selectedStudentId <= 0): ?>
                        <p class="text-xs text-amber-600 mt-2">Pilih siswa terlebih dahulu untuk pengajuan per siswa.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

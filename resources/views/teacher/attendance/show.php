<?php
    $sessionData = isset($sessionData) && is_array($sessionData) ? $sessionData : null;
    $studentsData = isset($students) && is_array($students) ? $students : [];
    $recordsData = isset($records) && is_array($records) ? $records : [];
    $statusOptionsData = isset($statusOptions) && is_array($statusOptions) ? $statusOptions : [];
    $countsData = isset($counts) && is_array($counts) ? $counts : [];
    $isActiveSession = isset($isActive) ? (bool) $isActive : false;
    $scanUrlValue = isset($scanUrl) ? (string) $scanUrl : '';
    $successMessage = session_flash('success');
    $errorMessage = session_flash('error');
    $infoMessage = session_flash('info');
    $statusKeys = array_keys($statusOptionsData);
    $statusKeys = !empty($statusKeys) ? $statusKeys : ['hadir', 'izin', 'sakit', 'bolos', 'alpa'];
    $isReplacementSession = $sessionData !== null && (string) ($sessionData['tipe_sesi'] ?? 'jadwal') === 'pengganti';
    $replacementNote = $sessionData !== null ? trim((string) ($sessionData['catatan_pengganti'] ?? '')) : '';
    $scheduledTeacherName = $sessionData !== null ? trim((string) ($sessionData['guru_jadwal_nama'] ?? '')) : '';
?>

<?php if ($sessionData === null): ?>
    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-700 shadow-sm">
        Data sesi presensi tidak ditemukan.
    </div>
<?php else: ?>
    <div class="space-y-6 overflow-x-hidden">
        <?php if (!empty($successMessage)): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($infoMessage)): ?>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                <?= htmlspecialchars($infoMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="grid gap-6 lg:grid-cols-12">
            <div class="space-y-4 lg:col-span-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-lg font-semibold text-slate-800">Detail Sesi</h2>
                        <?php
                            $statusKey = (string) ($sessionData['status'] ?? 'ditutup');
                            $expiresAt = isset($sessionData['valid_sampai']) ? strtotime((string) $sessionData['valid_sampai']) : false;
                            $statusLabel = $isActiveSession ? 'Aktif' : 'Ditutup';
                            $badgeClass = $isActiveSession ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200';
                            if ($statusKey === 'aktif' && $expiresAt !== false && $expiresAt < time()) {
                                $statusLabel = 'Kedaluwarsa';
                                $badgeClass = 'bg-amber-50 text-amber-700 border-amber-200';
                            }
                        ?>
                        <span class="inline-flex items-center rounded-full border <?= $badgeClass ?> px-3 py-1 text-xs font-semibold self-start sm:self-auto">
                            <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <dl class="mt-4 grid gap-4 text-sm text-slate-600 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal</dt>
                            <dd class="mt-2 text-sm font-semibold text-slate-800">
                                <?= isset($sessionData['tanggal']) ? htmlspecialchars(date('d M Y', strtotime((string) $sessionData['tanggal'])), ENT_QUOTES, 'UTF-8') : '-' ?>
                            </dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mata Pelajaran</dt>
                            <dd class="mt-2 text-sm text-slate-700 break-words">
                                <?= htmlspecialchars((string) ($sessionData['mata_pelajaran_nama'] ?? 'Mata Pelajaran'), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($sessionData['mata_pelajaran_kode'])): ?>
                                    <span class="text-xs text-slate-400">(
                                        <?= htmlspecialchars((string) $sessionData['mata_pelajaran_kode'], ENT_QUOTES, 'UTF-8') ?>
                                    )</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kelas</dt>
                            <?php
                            $classLabel = sprintf(
                                'Kelas %s %s',
                                htmlspecialchars((string) ($sessionData['kelas_tingkat'] ?? '-'), ENT_QUOTES, 'UTF-8'),
                                htmlspecialchars((string) ($sessionData['kelas_nama'] ?? '-'), ENT_QUOTES, 'UTF-8')
                            );
                            if (!empty($sessionData['jurusan_nama'])) {
                                $classLabel .= sprintf(' (%s)', htmlspecialchars((string) $sessionData['jurusan_nama'], ENT_QUOTES, 'UTF-8'));
                            }
                            $parallelClassLabel = '';
                            if (!empty($sessionData['kelas_paralel_id'])) {
                                $parallelClassLabel = sprintf(
                                    'Kelas %s %s',
                                    htmlspecialchars((string) ($sessionData['kelas_paralel_tingkat'] ?? '-'), ENT_QUOTES, 'UTF-8'),
                                    htmlspecialchars((string) ($sessionData['kelas_paralel_nama'] ?? '-'), ENT_QUOTES, 'UTF-8')
                                );
                                if (!empty($sessionData['jurusan_paralel_nama'])) {
                                    $parallelClassLabel .= sprintf(' (%s)', htmlspecialchars((string) $sessionData['jurusan_paralel_nama'], ENT_QUOTES, 'UTF-8'));
                                }
                            }
                            if ($parallelClassLabel !== '') {
                                $classLabel .= ' + ' . $parallelClassLabel;
                            }
                        ?>
                            <dd class="mt-2 text-sm text-slate-700 break-words"><?= $classLabel ?></dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Agenda</dt>
                            <dd class="mt-2 whitespace-pre-line text-sm text-slate-700 break-words">
                                <?= htmlspecialchars((string) ($sessionData['agenda'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                            </dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status Jadwal</dt>
                            <dd class="mt-2 text-sm text-slate-700 break-words">
                                <?php if ($isReplacementSession): ?>
                                    <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Guru Pengganti</span>
                                    <?php if ($scheduledTeacherName !== ''): ?>
                                        <p class="mt-2 text-xs text-slate-500">Jadwal asli: <?= htmlspecialchars($scheduledTeacherName, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if ($replacementNote !== ''): ?>
                                        <p class="mt-1 whitespace-pre-line text-xs text-slate-600"><?= htmlspecialchars($replacementNote, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Sesuai Jadwal</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">QR Aktif</dt>
                            <dd class="mt-2 text-sm text-slate-700 leading-relaxed">
                                <?= isset($sessionData['valid_dari']) ? htmlspecialchars(date('d M Y H:i', strtotime((string) $sessionData['valid_dari'])), ENT_QUOTES, 'UTF-8') : '-' ?>
                                &mdash;
                                <?= isset($sessionData['valid_sampai']) ? htmlspecialchars(date('d M Y H:i', strtotime((string) $sessionData['valid_sampai'])), ENT_QUOTES, 'UTF-8') : '-' ?>
                            </dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Durasi</dt>
                            <dd class="mt-2 text-sm font-semibold text-slate-800">
                                <?= htmlspecialchars((string) ($sessionData['durasi_menit'] ?? 0), ENT_QUOTES, 'UTF-8') ?> menit
                            </dd>
                        </div>
                    </dl>
                    <?php if ($scanUrlValue !== ''): ?>
                        <div class="mt-5 space-y-2 text-xs">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Link Presensi</p>
                            <div class="flex flex-col gap-2">
                                <code class="block w-full max-w-full break-all rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                    <?= htmlspecialchars($scanUrlValue, ENT_QUOTES, 'UTF-8') ?>
                                </code>
                                <button
                                    type="button"
                                    data-copy-target="attendance-scan-url"
                                    class="inline-flex w-full items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-100 sm:w-auto"
                                >
                                    <i class="ri-file-copy-line mr-1 text-sm"></i>
                                    Salin Link
                                </button>
                                <input type="hidden" id="attendance-scan-url" value="<?= htmlspecialchars($scanUrlValue, ENT_QUOTES, 'UTF-8') ?>" />
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!$isActiveSession): ?>
                        <p class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">
                            Sesi ini sudah ditutup. Siswa tidak dapat lagi menggunakan QR untuk presensi.
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($scanUrlValue !== ''): ?>
                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-6 shadow-sm">
                        <h3 class="text-sm font-semibold text-indigo-800">QR Presensi</h3>
                        <div
                            id="attendance-qr-container"
                            class="mx-auto mt-4 flex h-56 w-56 items-center justify-center rounded-xl border border-white bg-white p-3 shadow-inner"
                            aria-hidden="true"
                        ></div>
                        <p class="mt-3 text-center text-xs text-indigo-700">
                            Minta siswa memindai QR ini untuk mencatat kehadiran.
                        </p>
                        <?php if ($isActiveSession): ?>
                            <form
                                action="<?= htmlspecialchars(base_url('guru/presensi/' . (int) ($sessionData['id'] ?? 0) . '/tutup'), ENT_QUOTES, 'UTF-8') ?>"
                                method="post"
                                class="mt-4 flex justify-center"
                            >
                                <?= csrf_field() ?>
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100"
                                >
                                    <i class="ri-lock-line mr-1 text-sm"></i>
                                    Tutup Sesi Presensi
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-700">Rekap Status Kehadiran</h3>
                    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                        <?php foreach ($statusKeys as $statusKey): ?>
                            <?php
                                $label = $statusOptionsData[$statusKey] ?? ucfirst($statusKey);
                                $total = (int) ($countsData[$statusKey] ?? 0);
                            ?>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm">
                                <dt class="text-slate-500"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></dt>
                                <dd class="mt-1 text-lg font-semibold text-slate-800"><?= number_format($total) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            </div>

            <div class="lg:col-span-7">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-800">Presensi Manual</h3>
                            <p class="text-xs text-slate-500">
                                Perbarui status kehadiran siswa secara manual bila ada yang belum memindai QR.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="text-xs text-slate-400">
                                Total siswa: <?= number_format(count($studentsData)) ?>
                            </p>
                            <?php if (!empty($statusOptionsData)): ?>
                                <div class="flex items-center gap-2">
                                    <label for="mass-status" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Tandai semua
                                    </label>
                                    <select
                                        id="mass-status"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Pilih status</option>
                                        <?php foreach ($statusOptionsData as $statusKey => $label): ?>
                                            <option value="<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button
                                        type="button"
                                        id="apply-mass-status"
                                        class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm hover:border-indigo-300 hover:text-indigo-600"
                                    >
                                        <i class="ri-checkbox-multiple-line mr-1 text-sm"></i>
                                        Terapkan
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (empty($studentsData)): ?>
                        <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                            Belum ada siswa terdaftar pada kelas ini.
                        </p>
                    <?php else: ?>
                        <form
                            action="<?= htmlspecialchars(base_url('guru/presensi/' . (int) ($sessionData['id'] ?? 0) . '/manual'), ENT_QUOTES, 'UTF-8') ?>"
                            method="post"
                            class="mt-4 space-y-4"
                        >
                            <?= csrf_field() ?>
                            <div class="overflow-x-auto rounded-xl border border-slate-200">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Nama Siswa</th>
                                            <th class="px-4 py-3 text-left">Status</th>
                                            <th class="px-4 py-3 text-left">Metode</th>
                                            <th class="px-4 py-3 text-left">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 bg-white">
                                        <?php foreach ($studentsData as $student): ?>
                                            <?php
                                                if (!is_array($student)) {
                                                    continue;
                                                }
                                                $studentId = (int) ($student['id'] ?? 0);
                                                if ($studentId <= 0) {
                                                    continue;
                                                }
                                                $studentName = (string) ($student['nama'] ?? 'Siswa');
                                                $studentClassLabel = '';
                                                if (!empty($sessionData['kelas_paralel_id']) && !empty($student['kelas_nama'])) {
                                                    $studentClassLabel = (string) $student['kelas_nama'];
                                                }
                                                $record = $recordsData[$studentId] ?? null;
                                                $currentStatus = (string) ($record['status'] ?? 'hadir');
                                                $currentMethod = (string) ($record['metode'] ?? '-');
                                                $currentNote = (string) ($record['catatan'] ?? '');
                                                $methodLabel = match ($currentMethod) {
                                                    'qr' => 'QR',
                                                    'manual' => 'Manual',
                                                    default => '-',
                                                };
                                                $statusLabel = $statusOptionsData[$currentStatus] ?? ucfirst($currentStatus);
                                            ?>
                                            <tr>
                                                <td class="px-4 py-3 align-top">
                                                    <p class="font-semibold text-slate-700">
                                                        <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?>
                                                    </p>
                                                    <?php if ($studentClassLabel !== ''): ?>
                                                        <p class="text-xs text-slate-400">
                                                            Kelas: <?= htmlspecialchars($studentClassLabel, ENT_QUOTES, 'UTF-8') ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <p class="text-xs text-slate-400">
                                                        NISN: <?= htmlspecialchars((string) ($student['nisn'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                                    </p>
                                                </td>
                                                <td class="px-4 py-3 align-top">
                                                    <select
                                                        name="status[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                        class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        data-student-status-select
                                                    >
                                                        <?php foreach ($statusOptionsData as $statusKey => $label): ?>
                                                            <option value="<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>" <?= $statusKey === $currentStatus ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td class="px-4 py-3 align-top">
                                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">
                                                        <?= htmlspecialchars($methodLabel, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                    <?php if ($record !== null && !empty($record['presensi_pada'])): ?>
                                                        <p class="mt-1 text-[11px] text-slate-400">
                                                            <?= htmlspecialchars(date('d M Y H:i', strtotime((string) $record['presensi_pada'])), ENT_QUOTES, 'UTF-8') ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3 align-top">
                                                    <textarea
                                                        name="catatan[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                        rows="1"
                                                        class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    ><?= htmlspecialchars($currentNote, ENT_QUOTES, 'UTF-8') ?></textarea>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="flex items-center justify-end">
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                >
                                    <i class="ri-save-3-line mr-2 text-base"></i>
                                    Simpan Presensi Manual
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <script src="<?= htmlspecialchars(asset('js/qrcode.min.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const qrContainer = document.getElementById("attendance-qr-container");
            const urlField = document.getElementById("attendance-scan-url");

            if (qrContainer && urlField && typeof QRCode !== "undefined") {
                const url = urlField.value;
                while (qrContainer.firstChild) {
                    qrContainer.removeChild(qrContainer.firstChild);
                }
                // Render QR code into container
                const qr = new QRCode(qrContainer, {
                    text: url,
                    width: 224,
                    height: 224,
                    correctLevel: QRCode.CorrectLevel.H,
                });
                void qr;
            }

            document.querySelectorAll("[data-copy-target]").forEach((button) => {
                button.addEventListener("click", () => {
                    const targetId = button.getAttribute("data-copy-target");
                    if (!targetId) {
                        return;
                    }
                    const input = document.getElementById(targetId);
                    if (!input) {
                        return;
                    }
                    const value = input.value;
                    navigator.clipboard.writeText(value).then(() => {
                        button.textContent = "Tersalin!";
                        setTimeout(() => {
                            button.textContent = "Salin Link";
                        }, 2000);
                    }).catch(() => {
                        button.textContent = "Gagal menyalin";
                        setTimeout(() => {
                            button.textContent = "Salin Link";
                        }, 2000);
                    });
                });
            });

            const applyMassStatusButton = document.getElementById("apply-mass-status");
            const massStatusSelect = document.getElementById("mass-status");
            if (applyMassStatusButton && massStatusSelect) {
                applyMassStatusButton.addEventListener("click", () => {
                    const selectedStatus = massStatusSelect.value;
                    if (!selectedStatus) {
                        return;
                    }
                    document.querySelectorAll('[data-student-status-select]').forEach((selectElement) => {
                        if (!(selectElement instanceof HTMLSelectElement)) {
                            return;
                        }
                        selectElement.value = selectedStatus;
                    });
                });
            }
        });
    </script>
<?php endif; ?>

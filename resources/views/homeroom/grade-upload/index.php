<?php
    $classes = isset($classes) && is_array($classes) ? $classes : [];
    $assignments = isset($assignments) && is_array($assignments) ? $assignments : [];
    $selectedClassId = isset($selectedClassId) ? (int) $selectedClassId : 0;
    $selectedAssignmentId = isset($selectedAssignmentId) ? (int) $selectedAssignmentId : 0;
    $recentBatches = isset($recentBatches) && is_array($recentBatches) ? $recentBatches : [];
    $contextReadyEntries = isset($contextReadyEntries) && is_array($contextReadyEntries) ? $contextReadyEntries : [];
    $resumeBatchCode = trim((string) ($resumeBatchCode ?? ''));
    $statusBadgeClasses = [
        'DRAFT' => 'bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-200',
        'FINAL' => 'bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200',
        'COMMITTED' => 'bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200',
        'VALIDATED' => 'bg-sky-100 text-sky-800 ring-1 ring-inset ring-sky-200',
        'VALIDATING' => 'bg-violet-100 text-violet-800 ring-1 ring-inset ring-violet-200',
        'FAILED' => 'bg-rose-100 text-rose-800 ring-1 ring-inset ring-rose-200',
        'ROLLED_BACK' => 'bg-orange-100 text-orange-800 ring-1 ring-inset ring-orange-200',
    ];
?>

<div class="space-y-6" id="grade-upload-page">
    <div class="rounded-3xl border border-slate-200 bg-gradient-to-r from-slate-50 via-white to-emerald-50 p-6 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">Wali Kelas</p>
        <h2 class="mt-2 text-2xl font-semibold text-slate-800">Upload Nilai Rescue</h2>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars(base_url('walikelas/nilai-upload'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                Per Mapel
            </a>
            <a href="<?= htmlspecialchars(base_url('walikelas/nilai-upload/siswa'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Per Siswa
            </a>
        </div>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
            Gunakan halaman ini dengan urutan sederhana: pilih kelas dan mata pelajaran, download template, isi nilai, lalu upload sebagai draft atau final.
        </p>
        <div class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                <div class="font-semibold text-slate-800">1. Pilih</div>
                <div class="mt-1">Tentukan kelas dan mata pelajaran.</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                <div class="font-semibold text-slate-800">2. Download</div>
                <div class="mt-1">Ambil template Excel yang sesuai.</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                <div class="font-semibold text-slate-800">3. Upload</div>
                <div class="mt-1">Kirim file nilai yang sudah diisi.</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                <div class="font-semibold text-slate-800">4. Selesai</div>
                <div class="mt-1">Biarkan draft atau ubah menjadi final.</div>
            </div>
        </div>
    </div>

    <?php if (empty($classes)): ?>
        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
            Anda belum terdaftar sebagai wali kelas pada tahun ajaran aktif.
        </div>
    <?php else: ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-sm font-bold text-white">1</div>
                <div>
                    <h3 class="text-lg font-semibold text-slate-800">Pilih Kelas dan Mata Pelajaran</h3>
                    <p class="mt-1 text-sm text-slate-500">Tentukan dulu kelas dan mata pelajaran yang nilainya ingin diupload.</p>
                </div>
            </div>
            <form method="get" action="<?= htmlspecialchars(base_url('walikelas/nilai-upload'), ENT_QUOTES, 'UTF-8') ?>" class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="kelas_id" class="block text-sm font-medium text-slate-700">Kelas</label>
                    <select id="kelas_id" name="kelas_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <?php foreach ($classes as $class): ?>
                            <?php $classId = (int) ($class['id'] ?? 0); ?>
                            <?php $classLabel = trim(((string) ($class['tingkat'] ?? '')) . ' ' . ((string) ($class['nama'] ?? ''))); ?>
                            <option value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedClassId === $classId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($classLabel !== '' ? $classLabel : ('Kelas #' . $classId), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="assignment_id" class="block text-sm font-medium text-slate-700">Mata Pelajaran</label>
                    <select id="assignment_id" name="assignment_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">-- Pilih mata pelajaran --</option>
                        <?php foreach ($assignments as $assignment): ?>
                            <?php $assignmentId = (int) ($assignment['id'] ?? 0); ?>
                            <?php
                                $code = trim((string) ($assignment['mata_pelajaran_kode'] ?? ''));
                                $name = trim((string) ($assignment['mata_pelajaran_nama'] ?? 'Mapel'));
                                $label = $code !== '' ? $code . ' - ' . $name : $name;
                            ?>
                            <option value="<?= htmlspecialchars((string) $assignmentId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedAssignmentId === $assignmentId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($assignments)): ?>
                        <p class="mt-2 text-xs text-amber-700">Semua mata pelajaran untuk kelas ini sudah pernah dibuat template atau sudah ada di riwayat upload. Gunakan bagian riwayat di bawah untuk melanjutkan.</p>
                    <?php endif; ?>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Lanjutkan dengan Pilihan Ini
                    </button>
                </div>
            </form>
            <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kelas Terpilih</div>
                    <div id="selected-class-summary" class="mt-1 font-semibold text-slate-800">Pilih kelas terlebih dahulu.</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Mapel Terpilih</div>
                    <div id="selected-assignment-summary" class="mt-1 font-semibold text-slate-800">Pilih mata pelajaran terlebih dahulu.</div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-sm font-bold text-white">2</div>
                <div>
                    <h3 class="text-lg font-semibold text-slate-800">Download Template Nilai</h3>
                    <p class="mt-1 text-sm text-slate-500">Template cukup didownload sekali. Kalau statusnya masih draft, file yang sama bisa dipakai lagi untuk revisi.</p>
                </div>
            </div>
            <?php if ($selectedClassId > 0 && $selectedAssignmentId > 0): ?>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                    Anda tidak perlu membuat template baru setiap kali revisi. Cukup perbaiki file Excel yang lama, lalu upload kembali selama statusnya masih draft.
                </div>
                <div class="flex flex-wrap gap-3">
                    <button id="btn-generate-template" type="button" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Siapkan Template
                    </button>
                    <a
                        id="download-template-link"
                        href="<?= htmlspecialchars(base_url('walikelas/nilai-upload/template?kelas_id=' . urlencode((string) $selectedClassId) . '&assignment_id=' . urlencode((string) $selectedAssignmentId)), ENT_QUOTES, 'UTF-8') ?>"
                        class="hidden inline-flex items-center rounded-lg border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50"
                    >
                        Download File Template
                    </a>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-500">Pilih kelas dan mata pelajaran terlebih dahulu.</p>
            <?php endif; ?>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-800 text-sm font-bold text-white">3</div>
                <div>
                    <h3 class="text-lg font-semibold text-slate-800">Upload File Nilai</h3>
                    <p class="mt-1 text-sm text-slate-500">Upload file Excel yang sudah diisi. Secara bawaan sistem akan menyimpannya sebagai draft.</p>
                </div>
            </div>

            <form id="upload-form" class="space-y-4" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars(base_url('walikelas/nilai-upload/validate'), ENT_QUOTES, 'UTF-8') ?>">
                <?= csrf_field() ?>
                <input id="hidden_kelas_id" type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">
                <input id="hidden_assignment_id" type="hidden" name="assignment_id" value="<?= htmlspecialchars((string) $selectedAssignmentId, ENT_QUOTES, 'UTF-8') ?>">

                <div>
                    <label for="import_file" class="block text-sm font-medium text-slate-700">File Excel Nilai</label>
                    <input id="import_file" name="import_file" type="file" accept=".xls,.xlsx" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" required>
                </div>

                <fieldset class="rounded-2xl border border-slate-200 p-4">
                    <legend class="px-2 text-sm font-medium text-slate-700">Simpan Hasil Upload Sebagai</legend>
                    <div class="mt-2 grid gap-3 md:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4 hover:border-indigo-300 hover:bg-indigo-50/40">
                            <input id="status-draft" type="radio" name="desired_status" value="DRAFT" checked class="mt-1 h-4 w-4 border-slate-300 text-indigo-600">
                            <span>
                                <span class="block text-sm font-semibold text-slate-800">Draft</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">Pilih ini jika nilai masih mungkin direvisi. Ini pilihan paling aman.</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4 hover:border-emerald-300 hover:bg-emerald-50/40">
                            <input id="status-final" type="radio" name="desired_status" value="FINAL" class="mt-1 h-4 w-4 border-slate-300 text-emerald-600">
                            <span>
                                <span class="block text-sm font-semibold text-slate-800">Final</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">Pilih ini jika nilainya sudah benar dan tidak ingin direvisi lagi.</span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Upload Nilai
                </button>
            </form>

            <div id="result-box" class="hidden rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"></div>

            <div id="current-batch-box" class="hidden rounded-2xl border border-slate-200 bg-slate-50 p-5 space-y-4">
                <div>
                    <p class="text-sm font-semibold text-slate-800">Status Upload Saat Ini</p>
                    <p id="current-batch-text" class="mt-2 text-sm leading-6 text-slate-600"></p>
                    <p id="current-batch-help" class="mt-2 text-xs leading-5 text-slate-500"></p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button id="btn-finalize" type="button" class="hidden inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Jadikan Final
                    </button>
                    <button id="btn-reopen" type="button" class="hidden inline-flex items-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                        Buka Lagi untuk Revisi
                    </button>
                    <button id="btn-refresh-status" type="button" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                        Perbarui Status
                    </button>
                </div>
            </div>

            <div id="error-table-wrap" class="hidden overflow-x-auto">
                <div class="mb-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    Beberapa baris masih perlu diperbaiki. Perbaiki file Excel yang sama, lalu upload lagi.
                </div>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-100">
                        <tr>
                            <th class="px-3 py-2 text-left">Baris</th>
                            <th class="px-3 py-2 text-left">NISN/NIS</th>
                            <th class="px-3 py-2 text-left">Nama</th>
                            <th class="px-3 py-2 text-left">Masalah</th>
                        </tr>
                    </thead>
                    <tbody id="error-table-body" class="divide-y divide-slate-200 bg-white"></tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-amber-500 text-sm font-bold text-white">4</div>
                <div>
                    <h3 class="text-lg font-semibold text-slate-800">Riwayat Upload</h3>
                    <p class="mt-1 text-sm text-slate-500">Gunakan bagian ini untuk melihat upload sebelumnya, memilih draft lama, atau mengecek status nilai final.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-100">
                        <tr>
                            <th class="px-3 py-2 text-left">Kelas dan Mapel</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-left">Ringkasan</th>
                            <th class="px-3 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <?php if (empty($recentBatches) && empty($contextReadyEntries)): ?>
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-slate-500">Belum ada upload nilai.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contextReadyEntries as $context): ?>
                                <?php
                                    $activeClassId = (int) ($context['kelas_id'] ?? 0);
                                    $activeAssignmentId = (int) ($context['assignment_id'] ?? 0);
                                    $activeClassLabel = trim(((string) ($context['kelas_tingkat'] ?? '')) . ' ' . ((string) ($context['kelas_nama'] ?? '')));
                                    $activeMapelCode = trim((string) ($context['mapel_kode'] ?? ''));
                                    $activeMapelName = trim((string) ($context['mapel_nama'] ?? ''));
                                    $activeMapelLabel = trim(($activeMapelCode !== '' ? ($activeMapelCode . ' - ') : '') . $activeMapelName);
                                ?>
                                <tr class="bg-emerald-50/50">
                                    <td class="px-3 py-2 text-slate-600">
                                        <div><?= htmlspecialchars($activeClassLabel !== '' ? $activeClassLabel : '-', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($activeMapelLabel !== '' ? $activeMapelLabel : '-', ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="px-3 py-2"><span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Template siap</span></td>
                                    <td class="px-3 py-2 text-xs text-slate-600">Template sudah pernah dipilih. Bisa langsung dipakai lagi.</td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="flex justify-end gap-2">
                                            <a
                                                href="<?= htmlspecialchars(base_url('walikelas/nilai-upload/template?kelas_id=' . urlencode((string) $activeClassId) . '&assignment_id=' . urlencode((string) $activeAssignmentId)), ENT_QUOTES, 'UTF-8') ?>"
                                                class="inline-flex items-center rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                            >
                                                Download Lagi
                                            </a>
                                            <button
                                                type="button"
                                                data-use-context="1"
                                                data-kelas-id="<?= htmlspecialchars((string) $activeClassId, ENT_QUOTES, 'UTF-8') ?>"
                                                data-assignment-id="<?= htmlspecialchars((string) $activeAssignmentId, ENT_QUOTES, 'UTF-8') ?>"
                                                class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50"
                                            >
                                                Gunakan Lagi
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($recentBatches as $batch): ?>
                                <?php
                                    $batchCode = (string) ($batch['batch_code'] ?? '');
                                    $ctxClass = trim(((string) ($batch['kelas_tingkat'] ?? '')) . ' ' . ((string) ($batch['kelas_nama'] ?? '')));
                                    $ctxMapel = trim(((string) ($batch['mapel_kode'] ?? '')) . ' - ' . ((string) ($batch['mapel_nama'] ?? '')));
                                    $statusRaw = strtoupper(trim((string) ($batch['status'] ?? '')));
                                    $statusLabel = \App\Support\GradeUploadStatus::label($statusRaw);
                                    $badgeClass = $statusBadgeClasses[$statusRaw] ?? 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-200';
                                ?>
                                <tr>
                                    <td class="px-3 py-2 text-slate-600">
                                        <div><?= htmlspecialchars($ctxClass !== '' ? $ctxClass : '-', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($ctxMapel !== '' ? $ctxMapel : '-', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-[11px] text-slate-400"><?= htmlspecialchars($batchCode, ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="px-3 py-2"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td class="px-3 py-2 text-xs text-slate-600">
                                        Total: <?= (int) ($batch['total_rows'] ?? 0) ?> |
                                        Benar: <?= (int) ($batch['valid_rows'] ?? 0) ?> |
                                        Perlu cek: <?= (int) ($batch['invalid_rows'] ?? 0) ?>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="flex justify-end gap-2">
                                            <a
                                                href="<?= htmlspecialchars(base_url('walikelas/nilai-upload/template?kelas_id=' . urlencode((string) ($batch['kelas_id'] ?? 0)) . '&assignment_id=' . urlencode((string) ($batch['guru_mata_pelajaran_id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?>"
                                                class="inline-flex items-center rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                            >
                                                Download Lagi
                                            </a>
                                            <button
                                                type="button"
                                                data-load-batch="<?= htmlspecialchars($batchCode, ENT_QUOTES, 'UTF-8') ?>"
                                                data-kelas-id="<?= htmlspecialchars((string) ($batch['kelas_id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                                                data-assignment-id="<?= htmlspecialchars((string) ($batch['guru_mata_pelajaran_id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                                                class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50"
                                            >
                                                Pilih Upload Ini
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
(() => {
    const uploadForm = document.getElementById("upload-form");
    const classSelect = document.getElementById("kelas_id");
    const assignmentSelect = document.getElementById("assignment_id");
    const hiddenClassInput = document.getElementById("hidden_kelas_id");
    const hiddenAssignmentInput = document.getElementById("hidden_assignment_id");
    const resultBox = document.getElementById("result-box");
    const currentBatchBox = document.getElementById("current-batch-box");
    const currentBatchText = document.getElementById("current-batch-text");
    const currentBatchHelp = document.getElementById("current-batch-help");
    const errorWrap = document.getElementById("error-table-wrap");
    const errorBody = document.getElementById("error-table-body");
    const btnGenerateTemplate = document.getElementById("btn-generate-template");
    const downloadTemplateLink = document.getElementById("download-template-link");
    const btnFinalize = document.getElementById("btn-finalize");
    const btnReopen = document.getElementById("btn-reopen");
    const btnRefreshStatus = document.getElementById("btn-refresh-status");
    const loadBatchButtons = document.querySelectorAll("[data-load-batch]");
    const useContextButtons = document.querySelectorAll("[data-use-context]");
    const resumeBatchCode = "<?= htmlspecialchars($resumeBatchCode, ENT_QUOTES, 'UTF-8') ?>";
    const storageKey = "grade_rescue_last_batch";
    const assignmentsUrl = "<?= htmlspecialchars(base_url('walikelas/nilai-upload/assignments'), ENT_QUOTES, 'UTF-8') ?>";
    const templateBaseUrl = "<?= htmlspecialchars(base_url('walikelas/nilai-upload/template'), ENT_QUOTES, 'UTF-8') ?>";
    const statusUrl = "<?= htmlspecialchars(base_url('walikelas/nilai-upload/status'), ENT_QUOTES, 'UTF-8') ?>";
    const previewUrl = "<?= htmlspecialchars(base_url('walikelas/nilai-upload/preview'), ENT_QUOTES, 'UTF-8') ?>";
    const finalizeUrl = "<?= htmlspecialchars(base_url('walikelas/nilai-upload/finalize'), ENT_QUOTES, 'UTF-8') ?>";
    const reopenUrl = "<?= htmlspecialchars(base_url('walikelas/nilai-upload/reopen'), ENT_QUOTES, 'UTF-8') ?>";
    let currentBatchCode = "";

    const showMessage = (text, isError = false) => {
        if (!resultBox) return;
        resultBox.classList.remove("hidden");
        resultBox.classList.toggle("border-rose-200", isError);
        resultBox.classList.toggle("bg-rose-50", isError);
        resultBox.classList.toggle("text-rose-700", isError);
        resultBox.classList.toggle("border-emerald-200", !isError);
        resultBox.classList.toggle("bg-emerald-50", !isError);
        resultBox.classList.toggle("text-emerald-800", !isError);
        resultBox.textContent = text;
    };

    const renderErrors = (errors) => {
        if (!errorWrap || !errorBody) return;
        errorBody.innerHTML = "";
        if (!Array.isArray(errors) || errors.length === 0) {
            errorWrap.classList.add("hidden");
            return;
        }
        errors.forEach((item) => {
            const tr = document.createElement("tr");
            const err = Array.isArray(item.errors) ? item.errors.join("; ") : "-";
            tr.innerHTML = `
                <td class="px-3 py-2">${item.row_no ?? "-"}</td>
                <td class="px-3 py-2">${(item.nisn ?? "") || (item.nis ?? "-")}</td>
                <td class="px-3 py-2">${item.nama ?? "-"}</td>
                <td class="px-3 py-2 text-rose-700">${err}</td>
            `;
            errorBody.appendChild(tr);
        });
        errorWrap.classList.remove("hidden");
    };

    const fetchJson = async (url, options = {}) => {
        const response = await fetch(url, options);
        const data = await response.json().catch(() => ({ ok: false, message: "Respon tidak valid." }));
        if (!response.ok || data.ok === false) {
            throw new Error(data.message || `HTTP ${response.status}`);
        }
        return data;
    };

    const saveCurrentBatch = () => {
        try {
            if (currentBatchCode) {
                localStorage.setItem(storageKey, currentBatchCode);
            }
        } catch (_) {}
    };

    const refreshDownloadLink = () => {
        if (!downloadTemplateLink) return;
        const classId = classSelect?.value || "";
        const assignmentId = assignmentSelect?.value || "";
        if (!classId || !assignmentId) {
            downloadTemplateLink.classList.add("hidden");
            return;
        }
        downloadTemplateLink.href = `${templateBaseUrl}?kelas_id=${encodeURIComponent(classId)}&assignment_id=${encodeURIComponent(assignmentId)}`;
    };

    const updateContextSummary = () => {
        const classSummary = document.getElementById("selected-class-summary");
        const assignmentSummary = document.getElementById("selected-assignment-summary");
        const classLabel = classSelect?.selectedOptions?.[0]?.textContent?.trim() || "";
        const assignmentLabel = assignmentSelect?.selectedOptions?.[0]?.textContent?.trim() || "";

        if (classSummary) {
            classSummary.textContent = classLabel && !classLabel.startsWith("--")
                ? classLabel
                : "Pilih kelas terlebih dahulu.";
        }

        if (assignmentSummary) {
            assignmentSummary.textContent = assignmentLabel && !assignmentLabel.startsWith("--")
                ? assignmentLabel
                : "Pilih mata pelajaran terlebih dahulu.";
        }
    };

    const renderAssignmentOptions = (options, keepSelectedId = "") => {
        if (!assignmentSelect) return;
        const previous = keepSelectedId || assignmentSelect.value || "";
        assignmentSelect.innerHTML = "";

        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = "-- Pilih mata pelajaran --";
        assignmentSelect.appendChild(placeholder);

        let selectedFound = false;
        (Array.isArray(options) ? options : []).forEach((item) => {
            const id = String(item?.id ?? "");
            if (!id) return;
            const option = document.createElement("option");
            option.value = id;
            option.textContent = String(item?.label ?? ("Mapel #" + id));
            if (id === previous) {
                option.selected = true;
                selectedFound = true;
            }
            assignmentSelect.appendChild(option);
        });

        if (!selectedFound && previous) {
            const fallbackOption = document.createElement("option");
            fallbackOption.value = previous;
            fallbackOption.textContent = "Mapel dari riwayat";
            fallbackOption.selected = true;
            assignmentSelect.appendChild(fallbackOption);
            selectedFound = true;
        }

        if (!selectedFound) {
            assignmentSelect.value = "";
            if (hiddenAssignmentInput) hiddenAssignmentInput.value = "";
        } else if (hiddenAssignmentInput) {
            hiddenAssignmentInput.value = assignmentSelect.value;
        }
    };

    const refreshAssignmentsByClass = async (classId, keepSelectedId = "") => {
        if (!classId) {
            renderAssignmentOptions([], "");
            return;
        }
        try {
            if (assignmentSelect) assignmentSelect.disabled = true;
            let url = assignmentsUrl + "?kelas_id=" + encodeURIComponent(classId);
            if (keepSelectedId) {
                url += "&include_assignment_id=" + encodeURIComponent(keepSelectedId);
            }
            const data = await fetchJson(url);
            renderAssignmentOptions(data?.options || [], keepSelectedId);
        } catch (error) {
            renderAssignmentOptions([], "");
            showMessage(error.message || "Gagal memuat daftar mata pelajaran.", true);
        } finally {
            if (assignmentSelect) assignmentSelect.disabled = false;
        }
    };

    const renderCurrentBatch = (batch, summary = null) => {
        if (!currentBatchBox || !currentBatchText) return;
        if (!batch || !batch.batch_code) {
            currentBatchBox.classList.add("hidden");
            return;
        }

        const total = summary?.total_rows ?? batch.total_rows ?? 0;
        const valid = summary?.valid_rows ?? batch.valid_rows ?? 0;
        const invalid = summary?.invalid_rows ?? batch.invalid_rows ?? 0;
        const statusLabel = batch.status_label || batch.status || "-";

        currentBatchText.textContent = `Kode upload ${batch.batch_code}. Status saat ini ${statusLabel}. Jumlah data ${total}, data benar ${valid}, data yang perlu dicek ${invalid}.`;
        if (currentBatchHelp) {
            let helpText = "Upload ini bisa Anda pantau dari sini.";
            if (batch.can_finalize) {
                helpText = "Jika semua nilai sudah benar, klik Jadikan Final. Jika masih ada perubahan, cukup revisi file yang sama lalu upload lagi.";
            } else if (batch.can_reopen) {
                helpText = "Nilai ini sudah final. Jika ingin mengubahnya lagi, klik Buka Lagi untuk Revisi.";
            } else if (invalid > 0) {
                helpText = "Masih ada data yang perlu diperbaiki sebelum selesai.";
            }
            currentBatchHelp.textContent = helpText;
        }
        currentBatchBox.classList.remove("hidden");

        if (btnFinalize) {
            const canFinalize = Boolean(batch.can_finalize);
            btnFinalize.classList.toggle("hidden", !canFinalize);
        }
        if (btnReopen) {
            const canReopen = Boolean(batch.can_reopen);
            btnReopen.classList.toggle("hidden", !canReopen);
        }
    };

    const activateContext = async (classId, assignmentId) => {
        if (!classId) {
            return;
        }

        if (classSelect) {
            classSelect.value = classId;
        }
        if (hiddenClassInput) {
            hiddenClassInput.value = classId;
        }

        await refreshAssignmentsByClass(classId, assignmentId);

        if (assignmentSelect && assignmentId) {
            assignmentSelect.value = assignmentId;
        }
        if (hiddenAssignmentInput && assignmentId) {
            hiddenAssignmentInput.value = assignmentId;
        }

        refreshDownloadLink();
        updateContextSummary();
        downloadTemplateLink?.classList.remove("hidden");
    };

    const loadBatch = async (batchCode) => {
        if (!batchCode) return;

        currentBatchCode = batchCode;
        saveCurrentBatch();

        const statusData = await fetchJson(statusUrl + "?batch_code=" + encodeURIComponent(batchCode));
        renderCurrentBatch(statusData.batch || {}, statusData.batch || {});
        showMessage(`Upload dipilih. Status saat ini: ${statusData?.batch?.status_label || statusData?.batch?.status || "-"}.`);

        const previewData = await fetchJson(previewUrl + "?batch_code=" + encodeURIComponent(batchCode));
        renderErrors(previewData.errors || []);
    };

    btnGenerateTemplate?.addEventListener("click", () => {
        refreshDownloadLink();
        downloadTemplateLink?.classList.remove("hidden");
        showMessage("Template siap. Silakan download file Excel lalu isi nilainya.");
    });

    uploadForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        try {
            const classId = classSelect?.value || "";
            const assignmentId = assignmentSelect?.value || "";
            if (hiddenClassInput) hiddenClassInput.value = classId;
            if (hiddenAssignmentInput) hiddenAssignmentInput.value = assignmentId;

            if (!classId || !assignmentId) {
                showMessage("Pilih kelas dan mata pelajaran terlebih dahulu.", true);
                return;
            }

            showMessage("Sedang memproses file nilai...");
            const formData = new FormData(uploadForm);
            const data = await fetchJson(uploadForm.action, {
                method: "POST",
                body: formData,
            });

            currentBatchCode = data?.batch?.batch_code || "";
            saveCurrentBatch();
            renderCurrentBatch(data?.batch || {}, data?.summary || {});
            renderErrors(data?.errors || []);
            showMessage(data?.message || "Upload berhasil.");
        } catch (error) {
            showMessage(error.message || "Upload gagal.", true);
        }
    });

    btnFinalize?.addEventListener("click", async () => {
        if (!currentBatchCode) {
            showMessage("Belum ada draft yang bisa dijadikan final.", true);
            return;
        }

        try {
            showMessage("Mengubah status menjadi final...");
            const token = uploadForm?.querySelector('input[name="_token"]')?.value || "";
            const data = await fetchJson(finalizeUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
                },
                body: new URLSearchParams({
                    _token: token,
                    batch_code: currentBatchCode,
                }).toString(),
            });

            renderCurrentBatch(data?.batch || {});
            showMessage(data?.message || "Status berhasil diubah menjadi final.");
        } catch (error) {
            showMessage(error.message || "Gagal mengubah status.", true);
        }
    });

    btnReopen?.addEventListener("click", async () => {
        if (!currentBatchCode) {
            showMessage("Belum ada nilai final yang dipilih.", true);
            return;
        }

        try {
            showMessage("Membuka nilai final untuk revisi...");
            const token = uploadForm?.querySelector('input[name="_token"]')?.value || "";
            const data = await fetchJson(reopenUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
                },
                body: new URLSearchParams({
                    _token: token,
                    batch_code: currentBatchCode,
                }).toString(),
            });

            renderCurrentBatch(data?.batch || {});
            showMessage(data?.message || "Nilai berhasil dibuka lagi untuk revisi.");
        } catch (error) {
            showMessage(error.message || "Gagal membuka nilai untuk revisi.", true);
        }
    });

    btnRefreshStatus?.addEventListener("click", async () => {
        if (!currentBatchCode) {
            showMessage("Belum ada upload yang dipilih.", true);
            return;
        }

        try {
            await loadBatch(currentBatchCode);
        } catch (error) {
            showMessage(error.message || "Gagal memuat status upload.", true);
        }
    });

    loadBatchButtons.forEach((button) => {
        button.addEventListener("click", async () => {
            const batchCode = button.getAttribute("data-load-batch") || "";
            const classId = button.getAttribute("data-kelas-id") || "";
            const assignmentId = button.getAttribute("data-assignment-id") || "";
            try {
                await activateContext(classId, assignmentId);
                await loadBatch(batchCode);
                showMessage("Upload dipilih. Kelas, mata pelajaran, dan status upload sudah dimuat.");
            } catch (error) {
                showMessage(error.message || "Gagal memuat data upload.", true);
            }
        });
    });

    useContextButtons.forEach((button) => {
        button.addEventListener("click", async () => {
            const classId = button.getAttribute("data-kelas-id") || "";
            const assignmentId = button.getAttribute("data-assignment-id") || "";

            await activateContext(classId, assignmentId);
            showMessage("Kelas dan mata pelajaran sudah dipilih. Template siap dipakai kembali.");
        });
    });

    classSelect?.addEventListener("change", async () => {
        const classId = classSelect.value || "";
        if (hiddenClassInput) hiddenClassInput.value = classId;
        downloadTemplateLink?.classList.add("hidden");
        await refreshAssignmentsByClass(classId, "");
        refreshDownloadLink();
        updateContextSummary();
    });

    assignmentSelect?.addEventListener("change", () => {
        if (hiddenAssignmentInput) {
            hiddenAssignmentInput.value = assignmentSelect.value || "";
        }
        downloadTemplateLink?.classList.add("hidden");
        refreshDownloadLink();
        updateContextSummary();
    });

    (async () => {
        try {
            if (classSelect && assignmentSelect) {
                await refreshAssignmentsByClass(classSelect.value || "", assignmentSelect.value || "");
            }
            refreshDownloadLink();
            updateContextSummary();

            const fromStorage = (() => {
                try {
                    return localStorage.getItem(storageKey) || "";
                } catch (_) {
                    return "";
                }
            })();

            const initial = resumeBatchCode || fromStorage;
            if (initial) {
                await loadBatch(initial);
            }
        } catch (_) {
            // no-op
        }
    })();
})();
</script>

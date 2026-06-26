<?php
    $students = $students ?? [];
    $schoolYearOptions = $schoolYearOptions ?? [];
    $classOptions = $classOptions ?? [];
    $selectedSchoolYearId = $selectedSchoolYearId ?? null;
    $selectedClassId = $selectedClassId ?? null;
    $keyword = $keyword ?? '';
    $queryParams = [];

    $queryParams['school_year_id'] = $selectedSchoolYearId !== null ? $selectedSchoolYearId : 'all';

    if ($selectedClassId !== null) {
        $queryParams['class_id'] = $selectedClassId;
    }

    if ($keyword !== '') {
        $queryParams['q'] = $keyword;
    }

    $queryString = empty($queryParams) ? '' : '?' . http_build_query($queryParams);
    $downloadUrl = base_url('admin/cbt/export/download' . $queryString);
    $photoExportUrl = base_url('admin/cbt/export/photos');
    $downloadButtonClasses = 'inline-flex items-center justify-center gap-2 rounded-xl border border-indigo-200 px-4 py-2.5 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50';
    $downloadButtonAttrs = '';
    $photoExportButtonClasses = 'inline-flex items-center justify-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-2.5 text-sm font-semibold text-sky-700 transition hover:bg-sky-100';
    $photoExportButtonAttrs = '';
    $emptyExamRoomCount = 0;
    $emptyExamSessionCount = 0;
    $studentsWithEmptyExportFields = 0;

    foreach ($students as $student) {
        $examRoomEmpty = trim((string) ($student['profile']['exam_room'] ?? '')) === '';
        $examSessionEmpty = trim((string) ($student['profile']['exam_session'] ?? '')) === '';

        if ($examRoomEmpty) {
            $emptyExamRoomCount++;
        }

        if ($examSessionEmpty) {
            $emptyExamSessionCount++;
        }

        if ($examRoomEmpty || $examSessionEmpty) {
            $studentsWithEmptyExportFields++;
        }
    }

    if (empty($students)) {
        $downloadButtonClasses = 'pointer-events-none inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-400';
        $downloadButtonAttrs = ' aria-disabled="true"';
        $photoExportButtonClasses = 'pointer-events-none inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-400';
        $photoExportButtonAttrs = ' aria-disabled="true"';
    }
?>

<div class="space-y-6">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-colors duration-300 dark:border-slate-700 dark:bg-slate-800/70">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex-1">
                <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Konfigurasi Export CBT</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                    Lengkapi data opsional seperti username/password CBT, kode ruang, dan sesi ujian sebelum mengunduh file.
                </p>
                <ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-slate-500 dark:text-slate-300">
                    <li><span class="font-semibold text-slate-700 dark:text-slate-100">Kolom wajib:</span> Nama Lengkap, NISN, dan Nama Kelas otomatis terisi dari data master siswa.</li>
                    <li>Gunakan tombol generate untuk mengisi username &amp; password otomatis bagi kolom yang masih kosong.</li>
                    <li>Form tindakan massal dapat menerapkan ruang ujian &amp; sesi ujian ke seluruh siswa sesuai filter aktif.</li>
                </ul>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                <a
                    href="<?= htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') ?>"
                    class="<?= htmlspecialchars($downloadButtonClasses, ENT_QUOTES, 'UTF-8') ?>"
                    data-cbt-download
                    data-empty-export-fields="<?= (int) $studentsWithEmptyExportFields ?>"
                    data-empty-exam-room="<?= (int) $emptyExamRoomCount ?>"
                    data-empty-exam-session="<?= (int) $emptyExamSessionCount ?>"
                    <?= $downloadButtonAttrs ?>
                >
                    <i class="ri-download-2-line text-lg"></i>
                    <span>Download XLSX</span>
                </a>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring dark:border-slate-500/40 dark:text-slate-100"
                    data-generate-credentials
                >
                    <i class="ri-magic-line text-lg"></i>
                    <span>Generate Username &amp; Password</span>
                </button>
            </div>
        </div>

        <form method="get" action="<?= htmlspecialchars(base_url('admin/cbt/export'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 grid gap-4 md:grid-cols-4">
            <div>
                <label for="school_year_id" class="text-sm font-medium text-slate-600 dark:text-slate-200">Tahun Ajaran</label>
                <select
                    id="school_year_id"
                    name="school_year_id"
                    class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-transparent dark:text-slate-100"
                >
                    <option value="" <?= $selectedSchoolYearId === null ? 'selected' : '' ?>>Semua Tahun</option>
                    <?php foreach ($schoolYearOptions as $id => $label): ?>
                        <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= (int) $selectedSchoolYearId === (int) $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="class_id" class="text-sm font-medium text-slate-600 dark:text-slate-200">Kelas</label>
                <select
                    id="class_id"
                    name="class_id"
                    class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-transparent dark:text-slate-100"
                >
                    <option value="" <?= $selectedClassId === null ? 'selected' : '' ?>>Semua Kelas</option>
                    <?php foreach ($classOptions as $id => $label): ?>
                        <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= (int) $selectedClassId === (int) $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="keyword" class="text-sm font-medium text-slate-600 dark:text-slate-200">Pencarian</label>
                <input
                    type="text"
                    id="keyword"
                    name="q"
                    value="<?= htmlspecialchars((string) $keyword, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Nama / NISN / kelas"
                    class="mt-2 block w-full rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-700 transition focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-transparent dark:text-slate-100"
                />
            </div>
            <div class="flex items-end">
                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring dark:bg-indigo-600 dark:hover:bg-indigo-500">
                    <i class="ri-search-line text-lg"></i>
                    Terapkan Filter
                </button>
            </div>
        </form>

        <form class="mt-6 rounded-xl border border-dashed border-slate-200 p-4 text-sm dark:border-slate-600" data-bulk-room-form>
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-100">Tindakan Massal Ruang &amp; Sesi</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-300">Isi salah satu atau keduanya kemudian terapkan ke seluruh siswa yang sedang ditampilkan.</p>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div>
                    <label for="bulk_exam_room" class="block text-xs font-medium text-slate-500 dark:text-slate-300">Ruang Ujian</label>
                    <input
                        type="text"
                        id="bulk_exam_room"
                        name="bulk_exam_room"
                        class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-transparent dark:text-slate-100"
                        placeholder="Contoh: LAB-1"
                    />
                </div>
                <div>
                    <label for="bulk_exam_session" class="block text-xs font-medium text-slate-500 dark:text-slate-300">Sesi Ujian</label>
                    <input
                        type="text"
                        id="bulk_exam_session"
                        name="bulk_exam_session"
                        class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-transparent dark:text-slate-100"
                        placeholder="Contoh: Sesi 1"
                    />
                </div>
                <div class="flex items-end gap-2">
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring dark:bg-indigo-500 dark:hover:bg-indigo-400"
                    >
                        <i class="ri-arrow-right-up-line text-lg"></i>
                        Terapkan ke Tabel
                    </button>
                </div>
            </div>
            <p class="mt-2 text-xs font-medium text-emerald-600 dark:text-emerald-300" data-bulk-message></p>
        </form>

        <form method="get" action="<?= htmlspecialchars($photoExportUrl, ENT_QUOTES, 'UTF-8') ?>" class="mt-6 rounded-xl border border-dashed border-sky-200 bg-sky-50/50 p-4 text-sm dark:border-sky-700/50 dark:bg-sky-950/20" data-photo-export-form>
            <input type="hidden" name="school_year_id" value="<?= htmlspecialchars((string) ($selectedSchoolYearId ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-100">Ekspor Foto Siswa</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-300">Unduh foto siswa dalam ZIP dengan nama file menggunakan NISN. Pilih apakah foto diekspor untuk semua kelas atau hanya kelas tertentu.</p>
            <div class="mt-4 grid gap-4 lg:grid-cols-[1.4fr_1fr_auto] lg:items-end">
                <div>
                    <p class="block text-xs font-medium text-slate-500 dark:text-slate-300">Cakupan Ekspor</p>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 dark:border-slate-600 dark:bg-transparent dark:text-slate-100">
                            <input type="radio" name="photo_scope" value="all" class="h-4 w-4 border-slate-300 text-sky-600 focus:ring-sky-500" checked data-photo-scope />
                            <span>Semua Kelas</span>
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 dark:border-slate-600 dark:bg-transparent dark:text-slate-100">
                            <input type="radio" name="photo_scope" value="class" class="h-4 w-4 border-slate-300 text-sky-600 focus:ring-sky-500" data-photo-scope />
                            <span>Kelas Tertentu</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label for="photo_export_class_id" class="block text-xs font-medium text-slate-500 dark:text-slate-300">Pilih Kelas</label>
                    <select
                        id="photo_export_class_id"
                        name="class_id"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:border-sky-500 focus:outline-none focus:ring disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-600 dark:bg-transparent dark:text-slate-100 dark:disabled:bg-slate-800/40"
                        disabled
                        data-photo-class-select
                    >
                        <option value="" <?= $selectedClassId === null ? 'selected' : '' ?>>Pilih kelas</option>
                        <?php foreach ($classOptions as $id => $label): ?>
                            <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= (int) $selectedClassId === (int) $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button
                    type="submit"
                    class="<?= htmlspecialchars($photoExportButtonClasses, ENT_QUOTES, 'UTF-8') ?>"
                    <?= $photoExportButtonAttrs ?>
                >
                    <i class="ri-file-zip-line text-lg"></i>
                    <span>Ekspor Foto ZIP</span>
                </button>
            </div>
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-300" data-photo-export-hint>Mode saat ini: semua kelas dalam tahun ajaran yang dipilih.</p>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm transition-colors duration-300 dark:border-slate-700 dark:bg-slate-800/70">
        <div class="border-b border-slate-100 px-6 py-4 transition-colors duration-300 dark:border-slate-700">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100">Data Siswa</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-300">Isi username/password CBT sesuai kebutuhan. Kosongkan untuk melepas konfigurasi.</p>
                </div>
                <div class="text-sm text-slate-500 dark:text-slate-300">
                    <?= count($students) ?> siswa ditemukan
                </div>
            </div>
        </div>
        <form action="<?= htmlspecialchars(base_url('admin/cbt/export/konfigurasi'), ENT_QUOTES, 'UTF-8') ?>" method="post" data-cbt-form>
            <?= csrf_field() ?>
            <input type="hidden" name="school_year_id" value="<?= htmlspecialchars((string) ($selectedSchoolYearId ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="class_id" value="<?= htmlspecialchars((string) ($selectedClassId ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="q" value="<?= htmlspecialchars((string) $keyword, ENT_QUOTES, 'UTF-8') ?>" />
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm transition-colors duration-300 dark:divide-slate-700" data-cbt-table>
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-900/40 dark:text-slate-200">
                        <tr>
                            <th class="px-6 py-3 text-left">Nama Lengkap</th>
                            <th class="px-6 py-3 text-left">NISN</th>
                            <th class="px-6 py-3 text-left">Kelas</th>
                            <th class="px-6 py-3 text-left">Username CBT</th>
                            <th class="px-6 py-3 text-left">Password CBT</th>
                            <th class="px-6 py-3 text-left">Ruang Ujian</th>
                            <th class="px-6 py-3 text-left">Sesi Ujian</th>
                            <th class="px-6 py-3 text-left">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-transparent">
                        <?php foreach ($students as $student): ?>
                            <tr
                                data-student-row
                                data-student-id="<?= (int) $student['id'] ?>"
                                data-default-username="<?= htmlspecialchars((string) $student['default_username'], ENT_QUOTES, 'UTF-8') ?>"
                                data-nisn="<?= htmlspecialchars((string) $student['nisn'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-100">
                                    <p class="font-semibold"><?= htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php if (!empty($student['default_username'])): ?>
                                        <p class="text-xs text-slate-500 dark:text-slate-300">Akun siswa: <?= htmlspecialchars($student['default_username'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500 dark:text-slate-300"><?= htmlspecialchars($student['nisn'] !== '' ? $student['nisn'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-slate-500 dark:text-slate-300"><?= htmlspecialchars($student['class_name'] !== '' ? $student['class_name'] : 'Belum ditempatkan', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4">
                                    <input
                                        type="text"
                                        name="students[<?= (int) $student['id'] ?>][username]"
                                        value="<?= htmlspecialchars($student['profile']['username'], ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="<?= htmlspecialchars($student['default_username'] !== '' ? 'Default: ' . $student['default_username'] : 'Contoh: siswa001', ENT_QUOTES, 'UTF-8') ?>"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-transparent dark:text-slate-100"
                                        data-field="username"
                                    />
                                </td>
                                <td class="px-6 py-4">
                                    <input
                                        type="text"
                                        name="students[<?= (int) $student['id'] ?>][password]"
                                        value="<?= htmlspecialchars($student['profile']['password'], ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="Contoh: CBT2024"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-transparent dark:text-slate-100"
                                        data-field="password"
                                    />
                                </td>
                                <td class="px-6 py-4">
                                    <input
                                        type="text"
                                        name="students[<?= (int) $student['id'] ?>][exam_room]"
                                        value="<?= htmlspecialchars($student['profile']['exam_room'], ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="Contoh: LAB-1"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-transparent dark:text-slate-100"
                                        data-field="exam_room"
                                    />
                                </td>
                                <td class="px-6 py-4">
                                    <input
                                        type="text"
                                        name="students[<?= (int) $student['id'] ?>][exam_session]"
                                        value="<?= htmlspecialchars($student['profile']['exam_session'], ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="Contoh: Sesi 1"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-transparent dark:text-slate-100"
                                        data-field="exam_session"
                                    />
                                </td>
                                <td class="px-6 py-4">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-700/60"
                                        data-generate-row
                                    >
                                        <i class="ri-sparkling-2-line text-sm"></i>
                                        Generate
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-sm text-slate-400 dark:text-slate-300">
                                    Tidak ada siswa yang sesuai dengan filter yang dipilih.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-slate-100 px-6 py-4 text-sm transition-colors duration-300 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-slate-500 dark:text-slate-300">Tip: isi secara massal lalu klik simpan. Kosongkan kolom untuk menghapus data CBT siswa.</p>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    <i class="ri-save-3-line text-lg"></i>
                    Simpan Konfigurasi
                </button>
            </div>
        </form>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const table = document.querySelector('[data-cbt-table]');
        const generateAllButton = document.querySelector('[data-generate-credentials]');
        const bulkForm = document.querySelector('[data-bulk-room-form]');
        const photoExportForm = document.querySelector('[data-photo-export-form]');
        const downloadButton = document.querySelector('[data-cbt-download]');

        const generatePassword = () => {
            const characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            let result = '';
            for (let i = 0; i < 8; i++) {
                result += characters.charAt(Math.floor(Math.random() * characters.length));
            }
            return result;
        };

        const generateForRow = (row, force = false) => {
            if (!row) {
                return;
            }

            const usernameInput = row.querySelector('[data-field="username"]');
            const passwordInput = row.querySelector('[data-field="password"]');
            const defaultUsername = row.getAttribute('data-default-username') ?? '';
            const nisn = row.getAttribute('data-nisn') ?? '';
            const studentId = row.getAttribute('data-student-id') ?? '';

            if (usernameInput && (force || usernameInput.value.trim() === '')) {
                const fallback = defaultUsername !== '' ? defaultUsername : (nisn !== '' ? nisn : ('cbt' + studentId));
                usernameInput.value = fallback;
            }

            if (passwordInput && (force || passwordInput.value.trim() === '')) {
                passwordInput.value = generatePassword();
            }
        };

        generateAllButton?.addEventListener('click', () => {
            if (!table) {
                return;
            }
            table.querySelectorAll('[data-student-row]').forEach((row) => generateForRow(row, false));
        });

        downloadButton?.addEventListener('click', (event) => {
            const emptyExportFields = Number.parseInt(downloadButton.getAttribute('data-empty-export-fields') ?? '0', 10);
            if (!Number.isFinite(emptyExportFields) || emptyExportFields <= 0) {
                return;
            }

            const emptyExamRoom = Number.parseInt(downloadButton.getAttribute('data-empty-exam-room') ?? '0', 10) || 0;
            const emptyExamSession = Number.parseInt(downloadButton.getAttribute('data-empty-exam-session') ?? '0', 10) || 0;
            const message = [
                'Data yang akan diekspor masih memiliki field kosong.',
                '',
                `Ruang ujian kosong: ${emptyExamRoom} siswa`,
                `Sesi ujian kosong: ${emptyExamSession} siswa`,
                '',
                'Lanjutkan download XLSX?'
            ].join('\n');

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });

        table?.addEventListener('click', (event) => {
            const trigger = event.target instanceof Element ? event.target.closest('[data-generate-row]') : null;
            if (!trigger) {
                return;
            }
            const row = trigger.closest('[data-student-row]');
            generateForRow(row, true);
        });

        bulkForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!table) {
                return;
            }
            const roomInput = bulkForm.querySelector('#bulk_exam_room');
            const sessionInput = bulkForm.querySelector('#bulk_exam_session');
            const roomValue = roomInput ? roomInput.value.trim() : '';
            const sessionValue = sessionInput ? sessionInput.value.trim() : '';
            if (roomValue === '' && sessionValue === '') {
                const message = bulkForm.querySelector('[data-bulk-message]');
                if (message) {
                    message.textContent = 'Isi minimal salah satu kolom sebelum menerapkan.';
                    setTimeout(() => {
                        message.textContent = '';
                    }, 3500);
                }
                return;
            }

            table.querySelectorAll('[data-student-row]').forEach((row) => {
                const roomField = row.querySelector('[data-field="exam_room"]');
                const sessionField = row.querySelector('[data-field="exam_session"]');
                if (roomField && roomValue !== '') {
                    roomField.value = roomValue;
                }
                if (sessionField && sessionValue !== '') {
                    sessionField.value = sessionValue;
                }
            });

            const message = bulkForm.querySelector('[data-bulk-message]');
            if (message) {
                message.textContent = 'Nilai diterapkan ke seluruh kolom pada tabel saat ini. Jangan lupa klik Simpan.';
                setTimeout(() => {
                    message.textContent = '';
                }, 4000);
            }
        });

        if (photoExportForm) {
            const scopeInputs = Array.from(photoExportForm.querySelectorAll('[data-photo-scope]'));
            const classSelect = photoExportForm.querySelector('[data-photo-class-select]');
            const hint = photoExportForm.querySelector('[data-photo-export-hint]');

            const syncPhotoExportState = () => {
                const selectedScope = scopeInputs.find((input) => input.checked)?.value ?? 'all';
                const isClassScope = selectedScope === 'class';

                if (classSelect) {
                    classSelect.disabled = !isClassScope;
                }

                if (hint) {
                    hint.textContent = isClassScope
                        ? 'Mode saat ini: hanya foto dari kelas yang dipilih akan dimasukkan ke ZIP.'
                        : 'Mode saat ini: semua kelas dalam tahun ajaran yang dipilih.';
                }
            };

            scopeInputs.forEach((input) => {
                input.addEventListener('change', syncPhotoExportState);
            });

            photoExportForm.addEventListener('submit', (event) => {
                const selectedScope = scopeInputs.find((input) => input.checked)?.value ?? 'all';
                const classValue = classSelect instanceof HTMLSelectElement ? classSelect.value.trim() : '';

                if (selectedScope === 'class' && classValue === '') {
                    event.preventDefault();
                    if (hint) {
                        hint.textContent = 'Pilih kelas terlebih dahulu sebelum mengekspor foto per kelas.';
                    }
                    classSelect?.focus();
                    return;
                }

                syncPhotoExportState();
            });

            syncPhotoExportState();
        }
    });
</script>

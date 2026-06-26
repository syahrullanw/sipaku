<?php
    $activeYear = $activeYear ?? null;
    $classOptions = $classOptions ?? [];
    $selectedClassId = (int) ($selectedClassId ?? 0);
    $selectedClass = $selectedClass ?? null;
    $assignedStudents = $assignedStudents ?? [];
    $unassignedStudents = $unassignedStudents ?? [];
    $searchKeyword = isset($searchKeyword) ? trim((string) $searchKeyword) : '';
    $searchActive = $searchKeyword !== '';
    $canPromoteFromPrevious = (bool) ($canPromoteFromPrevious ?? false);
    $promotionDisabledReason = $promotionDisabledReason ?? null;
    $promotionSourceYear = $promotionSourceYear ?? null;
    $promotionSourceLabel = null;
    if (is_array($promotionSourceYear)) {
        $sourceSemester = (int) ($promotionSourceYear['semester_aktif'] ?? 0);
        $promotionSourceLabel = sprintf(
            '%s - %s',
            $promotionSourceYear['nama'] ?? 'Tahun Ajaran',
            $sourceSemester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)'
        );
    }

    $makeStudentLabel = static function (array $student): string {
        $label = $student['nama'] ?? '';
        $parts = [];
        if (!empty($student['nipd'])) {
            $parts[] = $student['nipd'];
        }
        if (!empty($student['nisn'])) {
            $parts[] = $student['nisn'];
        }

        if (!empty($parts)) {
            $label .= ' · ' . implode(' / ', $parts);
        }

        return trim($label);
    };

    $normalizeStudentKeywords = static function (array $student): string {
        $values = [
            $student['nama'] ?? '',
            $student['nipd'] ?? '',
            $student['nisn'] ?? '',
            $student['nik'] ?? '',
        ];

        $combined = implode(' ', array_filter(array_map(static fn ($value) => trim((string) $value), $values), static fn ($value) => $value !== ''));

        if ($combined === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($combined, 'UTF-8');
        }

        return strtolower($combined);
    };
?>

<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">Tahun Ajaran Aktif</h2>
            <?php if ($activeYear === null): ?>
                <p class="mt-3 text-sm text-slate-500">
                    Belum ada tahun ajaran yang aktif. Aktifkan tahun ajaran terlebih dahulu untuk menempatkan siswa ke kelas.
                </p>
            <?php else: ?>
                <dl class="mt-4 space-y-3 text-sm text-slate-600">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Nama</dt>
                        <dd class="font-semibold text-slate-700"><?= htmlspecialchars($activeYear['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Semester</dt>
                        <dd class="font-semibold text-slate-700"><?= (int) ($activeYear['semester_aktif'] ?? 1) === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)' ?></dd>
                    </div>
                    <div class="flex justify-between gap-2 text-xs text-slate-500">
                        <dt>Periode</dt>
                        <dd>
                            <?= htmlspecialchars($activeYear['tanggal_mulai'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            &ndash;
                            <?= htmlspecialchars($activeYear['tanggal_selesai'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                    </div>
                </dl>

                <form method="get" class="mt-6 space-y-3" data-student-class-form>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($searchKeyword, ENT_QUOTES, 'UTF-8') ?>" data-student-class-query />
                    <div>
                        <label for="kelas_id" class="block text-sm font-medium text-slate-600">Pilih Kelas</label>
                        <select
                            id="kelas_id"
                            name="kelas_id"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        >
                            <?php foreach ($classOptions as $id => $label): ?>
                                <option value="<?= (int) $id ?>" <?= $selectedClassId === (int) $id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Terapkan
                    </button>
                </form>
            <?php endif; ?>

            <div class="mt-6 rounded-lg bg-slate-50 px-3 py-3 text-xs text-slate-500">
                <p>Gunakan halaman ini untuk menempatkan siswa baru ke kelas aktif atau memindahkan siswa antar kelas.</p>
            </div>

            <?php if ($activeYear !== null): ?>
                <form method="post" action="<?= htmlspecialchars(base_url('master/siswa/penempatan'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 space-y-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="promote" />
                    <input type="hidden" name="q" value="<?= htmlspecialchars($searchKeyword, ENT_QUOTES, 'UTF-8') ?>" />
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
                        <?= $canPromoteFromPrevious ? '' : 'disabled' ?>
                    >
                        <i class="ri-upload-2-line text-base"></i>
                        Naikkan Kelas Siswa Otomatis
                    </button>
                    <?php if ($promotionDisabledReason !== null): ?>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars($promotionDisabledReason, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php elseif ($promotionSourceLabel !== null): ?>
                        <p class="text-xs text-slate-500">Sumber data: <?= htmlspecialchars($promotionSourceLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6 lg:col-span-8">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-800">Penempatan ke Kelas</h2>
                    <?php if ($selectedClass !== null): ?>
                        <p class="text-sm text-slate-500">
                            <?= htmlspecialchars($selectedClass['tingkat'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            <?= htmlspecialchars($selectedClass['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            · <?= htmlspecialchars($selectedClass['jurusan_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    <?php else: ?>
                        <p class="text-sm text-slate-500">Pilih kelas tujuan terlebih dahulu sebelum menempatkan siswa.</p>
                    <?php endif; ?>
                </div>
                <?php if ($selectedClass !== null): ?>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                        Saat ini: <?= count($assignedStudents) ?> siswa
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($activeYear === null): ?>
                <div class="mt-6 rounded-lg bg-amber-50 px-4 py-4 text-sm text-amber-700">
                    Aktifkan tahun ajaran terlebih dahulu untuk mulai menempatkan siswa.
                </div>
            <?php elseif ($selectedClass === null): ?>
                <div class="mt-6 rounded-lg bg-amber-50 px-4 py-4 text-sm text-amber-700">
                    Pilih kelas pada panel sebelah kiri untuk menampilkan daftar siswa.
                </div>
            <?php else: ?>
                <form
                    method="get"
                    class="mt-6 flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm sm:flex-row sm:items-end sm:gap-4"
                    data-student-search-form
                >
                    <div class="flex-1">
                        <label for="search_student" class="block text-sm font-medium text-slate-600">Cari Siswa</label>
                        <input
                            type="search"
                            id="search_student"
                            name="q"
                            value="<?= htmlspecialchars($searchKeyword, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Cari nama, NIPD, NISN, atau NIK"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            data-student-search
                            autocomplete="off"
                        />
                    </div>
                    <input type="hidden" name="kelas_id" value="<?= (int) $selectedClassId ?>" />
                    <div class="flex items-center gap-2 self-start sm:self-end">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                            data-student-search-clear
                            <?= $searchActive ? '' : 'style="display: none;"' ?>
                        >
                            <i class="ri-close-circle-line text-base"></i>
                            Reset
                        </button>
                    </div>
                </form>

                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <form method="post" action="<?= htmlspecialchars(base_url('master/siswa/penempatan'), ENT_QUOTES, 'UTF-8') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="assign" />
                            <input type="hidden" name="class_id" value="<?= $selectedClassId ?>" />
                            <input type="hidden" name="q" value="<?= htmlspecialchars($searchKeyword, ENT_QUOTES, 'UTF-8') ?>" />
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-slate-700">Siswa Belum Ditempatkan</h3>
                                <span
                                    class="text-xs text-slate-400"
                                    data-student-count="unassigned"
                                    data-count-label="siswa"
                                    data-base-count="<?= count($unassignedStudents) ?>"
                                >
                                    <?= count($unassignedStudents) ?> siswa
                                </span>
                            </div>
                            <div
                                class="mt-3 max-h-80 space-y-2 overflow-y-auto pr-2 text-sm"
                                data-student-list="unassigned"
                            >
                                <?php if (empty($unassignedStudents)): ?>
                                    <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-400">Semua siswa sudah memiliki kelas.</p>
                                <?php else: ?>
                                    <?php foreach ($unassignedStudents as $student): ?>
                                        <?php $keywords = $normalizeStudentKeywords($student); ?>
                                        <label
                                            class="flex items-start gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:border-indigo-300"
                                            data-student-item
                                            data-student-keywords="<?= htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <input type="checkbox" name="student_ids[]" value="<?= (int) $student['id'] ?>" class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                                            <span>
                                                <span class="font-semibold text-slate-700"><?= htmlspecialchars($makeStudentLabel($student), ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="block text-xs text-slate-400">Belum ditempatkan</span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                    <p class="hidden rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-400" data-student-empty-message>Tidak ada siswa yang cocok dengan pencarian ini.</p>
                                <?php endif; ?>
                            </div>
                            <button
                                type="submit"
                                class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                                <?= empty($unassignedStudents) ? 'disabled' : '' ?>
                                data-student-action-button="unassigned"
                            >
                                Tempatkan ke Kelas
                            </button>
                        </form>
                    </div>

                    <div class="rounded-xl border border-slate-200 p-4">
                        <form method="post" action="<?= htmlspecialchars(base_url('master/siswa/penempatan'), ENT_QUOTES, 'UTF-8') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="unassign" />
                            <input type="hidden" name="class_id" value="<?= $selectedClassId ?>" />
                            <input type="hidden" name="q" value="<?= htmlspecialchars($searchKeyword, ENT_QUOTES, 'UTF-8') ?>" />
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-slate-700">Siswa di Kelas Ini</h3>
                                <span
                                    class="text-xs text-slate-400"
                                    data-student-count="assigned"
                                    data-count-label="siswa"
                                    data-base-count="<?= count($assignedStudents) ?>"
                                >
                                    <?= count($assignedStudents) ?> siswa
                                </span>
                            </div>
                            <div
                                class="mt-3 max-h-80 space-y-2 overflow-y-auto pr-2 text-sm"
                                data-student-list="assigned"
                            >
                                <?php if (empty($assignedStudents)): ?>
                                    <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-400">Belum ada siswa pada kelas ini.</p>
                                <?php else: ?>
                                    <?php foreach ($assignedStudents as $student): ?>
                                        <?php $keywords = $normalizeStudentKeywords($student); ?>
                                        <label
                                            class="flex items-start gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:border-rose-300"
                                            data-student-item
                                            data-student-keywords="<?= htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <input type="checkbox" name="student_ids[]" value="<?= (int) $student['id'] ?>" class="mt-1 h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                                            <span>
                                                <span class="font-semibold text-slate-700"><?= htmlspecialchars($makeStudentLabel($student), ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="block text-xs text-slate-400">Ditempatkan</span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                    <p class="hidden rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-400" data-student-empty-message>Tidak ada siswa yang cocok dengan pencarian ini.</p>
                                <?php endif; ?>
                            </div>
                            <button
                                type="submit"
                                class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-500 disabled:opacity-60"
                                <?= empty($assignedStudents) ? 'disabled' : '' ?>
                                data-student-action-button="assigned"
                            >
                                Kosongkan Penempatan
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-student-search-form]');
    const input = document.querySelector('[data-student-search]');
    const resetButton = document.querySelector('[data-student-search-clear]');
    const listContainers = document.querySelectorAll('[data-student-list]');
    const actionButtons = document.querySelectorAll('[data-student-action-button]');
    const countBadges = new Map();
    const classQueryInputs = document.querySelectorAll('[data-student-class-query]');

    document.querySelectorAll('[data-student-count]').forEach((badge) => {
        const key = badge.dataset.studentCount;
        if (key) {
            countBadges.set(key, badge);
        }
    });

    if (form) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
        });
    }

    if (!input) {
        return;
    }

    const toggleResetButton = (hasQuery) => {
        if (!resetButton) {
            return;
        }
        resetButton.style.display = hasQuery ? '' : 'none';
    };

    const updateActionButtonState = (key, visibleCount, checkedCount) => {
        const shouldDisable = visibleCount === 0 && checkedCount === 0;
        actionButtons.forEach((button) => {
            if ((button.dataset.studentActionButton || '') === key) {
                if (shouldDisable) {
                    button.setAttribute('disabled', 'disabled');
                } else {
                    button.removeAttribute('disabled');
                }
            }
        });
    };

    const applyFilter = () => {
        const rawQuery = input.value.trim();
        const query = rawQuery.toLowerCase();
        const hasQuery = rawQuery !== '';
        toggleResetButton(hasQuery);
        classQueryInputs.forEach((hiddenInput) => {
            hiddenInput.value = rawQuery;
        });

        listContainers.forEach((container) => {
            const key = container.dataset.studentList || '';
            const items = container.querySelectorAll('[data-student-item]');
            const emptyMessage = container.querySelector('[data-student-empty-message]');
            let visibleCount = 0;
            const checkedCount = container.querySelectorAll('[data-student-item] input[type="checkbox"]:checked').length;

            if (items.length === 0) {
                const badge = countBadges.get(key);
                if (badge) {
                    const baseCount = parseInt(badge.dataset.baseCount ?? '0', 10);
                    const label = badge.dataset.countLabel ?? '';
                    badge.textContent = `${baseCount} ${label}`.trim();
                }
                updateActionButtonState(key, 0, checkedCount);
                return;
            }

            items.forEach((item) => {
                const keywords = (item.dataset.studentKeywords ?? '').toLowerCase();
                const matches = !hasQuery || keywords.includes(query);
                item.style.display = matches ? '' : 'none';
                if (matches) {
                    visibleCount++;
                }
            });

            if (emptyMessage) {
                emptyMessage.classList.toggle('hidden', visibleCount !== 0);
            }

            const badge = countBadges.get(key);
            if (badge) {
                const label = badge.dataset.countLabel ?? '';
                badge.textContent = `${visibleCount} ${label}`.trim();
            }

            updateActionButtonState(key, visibleCount, checkedCount);
        });

        const currentUrl = new URL(window.location.href);
        if (hasQuery) {
            currentUrl.searchParams.set('q', rawQuery);
        } else {
            currentUrl.searchParams.delete('q');
        }
        window.history.replaceState({}, document.title, currentUrl.toString());
    };

    input.addEventListener('input', applyFilter);

    if (resetButton) {
        resetButton.addEventListener('click', () => {
            input.value = '';
            input.focus();
            applyFilter();
        });
    }

    applyFilter();
});
</script>

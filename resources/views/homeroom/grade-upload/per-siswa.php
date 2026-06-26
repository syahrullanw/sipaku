<?php
    $classes = isset($classes) && is_array($classes) ? $classes : [];
    $students = isset($students) && is_array($students) ? $students : [];
    $selectedClassId = isset($selectedClassId) ? (int) $selectedClassId : 0;
    $selectedStudentId = isset($selectedStudentId) ? (int) $selectedStudentId : 0;
    $recentBatches = isset($recentBatches) && is_array($recentBatches) ? $recentBatches : [];
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

<div class="space-y-6" id="per-siswa-page">
    <div class="rounded-3xl border border-slate-200 bg-gradient-to-r from-slate-50 via-white to-sky-50 p-6 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">Wali Kelas</p>
        <h2 class="mt-2 text-2xl font-semibold text-slate-800">Upload Nilai Per Siswa</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
            Input nilai satu siswa untuk semua mata pelajaran sekaligus. Pilih siswa, download template, isi nilai di setiap sheet mapel, lalu upload kembali.
        </p>
        <div class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                <div class="font-semibold text-slate-800">1. Pilih Siswa</div>
                <div class="mt-1">Tentukan kelas dan siswa yang akan diinput nilainya.</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                <div class="font-semibold text-slate-800">2. Download</div>
                <div class="mt-1">Ambil template Excel multi-mapel sesuai siswa.</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                <div class="font-semibold text-slate-800">3. Isi Nilai</div>
                <div class="mt-1">Input nilai di setiap sheet mapel pada file Excel.</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                <div class="font-semibold text-slate-800">4. Upload</div>
                <div class="mt-1">Upload file Excel yang sudah diisi nilainya.</div>
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
                    <h3 class="text-lg font-semibold text-slate-800">Pilih Kelas dan Siswa</h3>
                    <p class="mt-1 text-sm text-slate-500">Tentukan kelas, lalu pilih siswa yang nilainya ingin diinput.</p>
                </div>
            </div>
            <form method="get" action="<?= htmlspecialchars(base_url('walikelas/nilai-upload/siswa'), ENT_QUOTES, 'UTF-8') ?>" class="grid gap-4 md:grid-cols-2">
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
                    <label for="siswa_id" class="block text-sm font-medium text-slate-700">Siswa</label>
                    <select id="siswa_id" name="siswa_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">-- Pilih siswa --</option>
                        <?php foreach ($students as $student): ?>
                            <?php $sid = (int) ($student['id'] ?? 0); ?>
                            <?php
                                $studentLabel = trim((string) ($student['nama'] ?? ''));
                                $nisn = trim((string) ($student['nisn'] ?? ''));
                                $nipd = trim((string) ($student['nipd'] ?? ''));
                                $identifiers = array_filter([$nipd, $nisn]);
                                if (!empty($identifiers)) {
                                    $studentLabel .= ' - ' . implode(' / ', $identifiers);
                                }
                            ?>
                            <option value="<?= htmlspecialchars((string) $sid, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedStudentId === $sid ? 'selected' : '' ?>>
                                <?= htmlspecialchars($studentLabel, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Lanjutkan dengan Pilihan Ini
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-sm font-bold text-white">2</div>
                <div>
                    <h3 class="text-lg font-semibold text-slate-800">Download Template Nilai Per Siswa</h3>
                    <p class="mt-1 text-sm text-slate-500">Template berisi SEMUA mata pelajaran untuk siswa yang dipilih. Masing-masing mapel ada di sheet terpisah. Nilai yang sudah ada akan diisi otomatis.</p>
                </div>
            </div>
            <?php if ($selectedClassId > 0 && $selectedStudentId > 0): ?>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                    Template akan mencakup semua mapel yang terdaftar di kelas ini. Setiap sheet mewakili satu mapel dengan format nilai sesuai preferensi guru pengampu.
                </div>
                <div class="flex flex-wrap gap-3">
                    <button id="btn-generate-template" type="button" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Siapkan Template
                    </button>
                    <a
                        id="download-template-link"
                        href="<?= htmlspecialchars(base_url('walikelas/nilai-upload/siswa/template?kelas_id=' . urlencode((string) $selectedClassId) . '&siswa_id=' . urlencode((string) $selectedStudentId)), ENT_QUOTES, 'UTF-8') ?>"
                        class="hidden inline-flex items-center rounded-lg border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50"
                    >
                        Download File Template
                    </a>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-500">Pilih kelas dan siswa terlebih dahulu.</p>
            <?php endif; ?>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-800 text-sm font-bold text-white">3</div>
                <div>
                    <h3 class="text-lg font-semibold text-slate-800">Upload File Nilai</h3>
                    <p class="mt-1 text-sm text-slate-500">Upload file Excel yang sudah diisi. Sistem akan memproses setiap sheet mapel secara otomatis.</p>
                </div>
            </div>

            <form id="upload-form" class="space-y-4" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars(base_url('walikelas/nilai-upload/siswa/validate'), ENT_QUOTES, 'UTF-8') ?>">
                <?= csrf_field() ?>

                <div>
                    <label for="import_file" class="block text-sm font-medium text-slate-700">File Excel Nilai (.xlsx)</label>
                    <input id="import_file" name="import_file" type="file" accept=".xls,.xlsx" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" required>
                </div>

                <fieldset class="rounded-2xl border border-slate-200 p-4">
                    <legend class="px-2 text-sm font-medium text-slate-700">Simpan Hasil Upload Sebagai</legend>
                    <div class="mt-2 grid gap-3 md:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4 hover:border-indigo-300 hover:bg-indigo-50/40">
                            <input id="status-draft" type="radio" name="desired_status" value="DRAFT" checked class="mt-1 h-4 w-4 border-slate-300 text-indigo-600">
                            <span>
                                <span class="block text-sm font-semibold text-slate-800">Draft</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">Pilih ini jika nilai masih mungkin direvisi.</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4 hover:border-emerald-300 hover:bg-emerald-50/40">
                            <input id="status-final" type="radio" name="desired_status" value="FINAL" class="mt-1 h-4 w-4 border-slate-300 text-emerald-600">
                            <span>
                                <span class="block text-sm font-semibold text-slate-800">Final</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">Pilih ini jika nilainya sudah benar dan final.</span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Upload Nilai
                </button>
            </form>

            <div id="result-box" class="hidden rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"></div>

            <div id="error-detail" class="hidden space-y-3">
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    Beberapa mapel bermasalah. Perbaiki file lalu upload ulang.
                </div>
                <div id="error-list" class="space-y-2"></div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-amber-500 text-sm font-bold text-white">4</div>
                <div>
                    <h3 class="text-lg font-semibold text-slate-800">Riwayat Upload</h3>
                    <p class="mt-1 text-sm text-slate-500">Upload sebelumnya untuk siswa di kelas Anda.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-100">
                        <tr>
                            <th class="px-3 py-2 text-left">Kode Batch</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-left">Ringkasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <?php if (empty($recentBatches)): ?>
                            <tr>
                                <td colspan="3" class="px-3 py-4 text-center text-slate-500">Belum ada upload nilai.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentBatches as $batch): ?>
                                <?php
                                    $batchCode = (string) ($batch['batch_code'] ?? '');
                                    $statusRaw = strtoupper(trim((string) ($batch['status'] ?? '')));
                                    $statusLabel = \App\Support\GradeUploadStatus::label($statusRaw);
                                    $badgeClass = $statusBadgeClasses[$statusRaw] ?? 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-200';
                                ?>
                                <tr>
                                    <td class="px-3 py-2 text-xs text-slate-500"><?= htmlspecialchars($batchCode, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-3 py-2"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td class="px-3 py-2 text-xs text-slate-600">
                                        Total: <?= (int) ($batch['total_rows'] ?? 0) ?> |
                                        Benar: <?= (int) ($batch['valid_rows'] ?? 0) ?> |
                                        Perlu cek: <?= (int) ($batch['invalid_rows'] ?? 0) ?>
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
    const studentSelect = document.getElementById("siswa_id");
    const resultBox = document.getElementById("result-box");
    const errorDetail = document.getElementById("error-detail");
    const errorList = document.getElementById("error-list");
    const btnGenerateTemplate = document.getElementById("btn-generate-template");
    const downloadTemplateLink = document.getElementById("download-template-link");
    const studentsUrl = "<?= htmlspecialchars(base_url('walikelas/nilai-upload/siswa/students'), ENT_QUOTES, 'UTF-8') ?>";
    const templateBaseUrl = "<?= htmlspecialchars(base_url('walikelas/nilai-upload/siswa/template'), ENT_QUOTES, 'UTF-8') ?>";

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

    const fetchJson = async (url, options = {}) => {
        const response = await fetch(url, options);
        const data = await response.json().catch(() => ({ ok: false, message: "Respon tidak valid." }));
        if (!response.ok || data.ok === false) {
            throw new Error(data.message || `HTTP ${response.status}`);
        }
        return data;
    };

    const renderStudentOptions = (options, keepSelectedId = "") => {
        if (!studentSelect) return;
        const previous = keepSelectedId || studentSelect.value || "";
        studentSelect.innerHTML = "";

        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = "-- Pilih siswa --";
        studentSelect.appendChild(placeholder);

        let selectedFound = false;
        (Array.isArray(options) ? options : []).forEach((item) => {
            const id = String(item?.id ?? "");
            if (!id) return;
            const option = document.createElement("option");
            option.value = id;
            option.textContent = String(item?.label ?? ("Siswa #" + id));
            if (id === previous) {
                option.selected = true;
                selectedFound = true;
            }
            studentSelect.appendChild(option);
        });

        if (!selectedFound) {
            studentSelect.value = "";
        }
    };

    const refreshStudentsByClass = async (classId, keepSelectedId = "") => {
        if (!classId) {
            renderStudentOptions([], "");
            return;
        }
        try {
            if (studentSelect) studentSelect.disabled = true;
            let url = studentsUrl + "?kelas_id=" + encodeURIComponent(classId);
            if (keepSelectedId) {
                url += "&include_student_id=" + encodeURIComponent(keepSelectedId);
            }
            const data = await fetchJson(url);
            renderStudentOptions(data?.options || [], keepSelectedId);
        } catch (error) {
            renderStudentOptions([], "");
            showMessage(error.message || "Gagal memuat daftar siswa.", true);
        } finally {
            if (studentSelect) studentSelect.disabled = false;
        }
    };

    const refreshDownloadLink = () => {
        if (!downloadTemplateLink) return;
        const classId = classSelect?.value || "";
        const studentId = studentSelect?.value || "";
        if (!classId || !studentId) {
            downloadTemplateLink.classList.add("hidden");
            return;
        }
        downloadTemplateLink.href = templateBaseUrl + "?kelas_id=" + encodeURIComponent(classId) + "&siswa_id=" + encodeURIComponent(studentId);
    };

    btnGenerateTemplate?.addEventListener("click", () => {
        refreshDownloadLink();
        downloadTemplateLink?.classList.remove("hidden");
        showMessage("Template siap. Silakan download file Excel, isi nilai di setiap sheet mapel, lalu upload kembali.");
    });

    uploadForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        try {
            const classId = classSelect?.value || "";
            const studentId = studentSelect?.value || "";

            if (!classId || !studentId) {
                showMessage("Pilih kelas dan siswa terlebih dahulu.", true);
                return;
            }

            if (!document.getElementById("import_file")?.files?.length) {
                showMessage("Pilih file Excel yang akan diupload.", true);
                return;
            }

            showMessage("Sedang memproses file nilai...");
            const formData = new FormData(uploadForm);
            const data = await fetchJson(uploadForm.action, {
                method: "POST",
                body: formData,
            });

            if (data?.errors && data.errors.length > 0) {
                if (errorDetail && errorList) {
                    errorDetail.classList.remove("hidden");
                    errorList.innerHTML = "";
                    data.errors.forEach((err) => {
                        const div = document.createElement("div");
                        div.className = "rounded-xl border border-rose-200 bg-white p-3 text-sm";
                        div.innerHTML = `<span class="font-semibold">${err.sheet || err.mapel || "?"}:</span> ${err.message}`;
                        errorList.appendChild(div);
                    });
                }
            } else {
                errorDetail?.classList.add("hidden");
            }

            if (data?.summary) {
                const msg = data.message + ` (${data.summary.success_subjects}/${data.summary.total_subjects} mapel berhasil)`;
                showMessage(msg, !data.ok);
            } else {
                showMessage(data?.message || "Upload berhasil.", !data.ok);
            }
        } catch (error) {
            showMessage(error.message || "Upload gagal.", true);
        }
    });

    classSelect?.addEventListener("change", async () => {
        const classId = classSelect.value || "";
        downloadTemplateLink?.classList.add("hidden");
        await refreshStudentsByClass(classId, "");
        refreshDownloadLink();
    });

    studentSelect?.addEventListener("change", () => {
        downloadTemplateLink?.classList.add("hidden");
        refreshDownloadLink();
    });

    (async () => {
        try {
            if (classSelect && studentSelect) {
                await refreshStudentsByClass(classSelect.value || "", studentSelect.value || "");
            }
            refreshDownloadLink();
        } catch (_) {}
    })();
})();
</script>

<?php
    $student = is_array($student ?? null) ? $student : [];
    $documentFields = is_array($documentFields ?? null) ? $documentFields : [];
    $documentStatuses = is_array($documentStatuses ?? null) ? $documentStatuses : [];
    $completed = 0;

    foreach ($documentStatuses as $status) {
        if (!empty($status['is_complete'])) {
            $completed++;
        }
    }

    $total = count($documentStatuses);
    $completionPercent = $total > 0 ? (int) floor(($completed / $total) * 100) : 0;
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-800">Berkas Fisik Saya</h2>
                <p class="mt-1 text-sm text-slate-500">
                    <?= htmlspecialchars((string) ($student['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                    <?= student_status_badge($student, 'ml-1 align-middle') ?>
                    <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                    · NIS <?= htmlspecialchars((string) ($student['nipd'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <div class="min-w-48 rounded-xl border border-slate-200 px-4 py-3">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <span>Kelengkapan</span>
                    <span><?= $completionPercent ?>%</span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-indigo-600" style="width: <?= $completionPercent ?>%"></div>
                </div>
                <p class="mt-2 text-xs text-slate-500"><?= $completed ?> dari <?= $total ?> dokumen sudah diunggah.</p>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-800">Upload / Revisi Berkas</h3>
                <p class="mt-1 text-sm text-slate-500">Kosongkan dokumen yang tidak ingin diganti. Format PDF, JPG, JPEG, PNG, atau WEBP maksimal 10 MB.</p>

                <form action="<?= htmlspecialchars(base_url('siswa/berkas'), ENT_QUOTES, 'UTF-8') ?>" method="post" enctype="multipart/form-data" class="mt-5 space-y-4">
                    <?= csrf_field() ?>
                    <?php foreach ($documentFields as $key => $definition): ?>
                        <?php
                            $status = $documentStatuses[$key] ?? [];
                            $isComplete = !empty($status['is_complete']);
                        ?>
                        <div class="rounded-xl border <?= $isComplete ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-white' ?> p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <label for="<?= htmlspecialchars((string) $definition['input'], ENT_QUOTES, 'UTF-8') ?>" class="block text-sm font-semibold text-slate-700">
                                        <?= htmlspecialchars((string) $definition['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </label>
                                    <p class="mt-1 text-xs <?= $isComplete ? 'text-emerald-700' : 'text-slate-500' ?>">
                                        <?= $isComplete ? 'Sudah diupload. Unggah file baru untuk revisi.' : 'Belum diupload.' ?>
                                    </p>
                                </div>
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold <?= $isComplete ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                                    <i class="<?= $isComplete ? 'ri-check-line' : 'ri-error-warning-line' ?>"></i>
                                    <?= $isComplete ? 'Lengkap' : 'Belum' ?>
                                </span>
                            </div>
                            <input
                                type="file"
                                id="<?= htmlspecialchars((string) $definition['input'], ENT_QUOTES, 'UTF-8') ?>"
                                name="<?= htmlspecialchars((string) $definition['input'], ENT_QUOTES, 'UTF-8') ?>"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                class="mt-3 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                            />
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                        <i class="ri-upload-cloud-line text-base"></i>
                        Simpan Berkas
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-7">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Status Dokumen</h3>
                    <p class="mt-1 text-sm text-slate-500">Penanda dokumen yang sudah dan belum tersedia.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($documentFields as $key => $definition): ?>
                        <?php
                            $status = $documentStatuses[$key] ?? [];
                            $isComplete = !empty($status['is_complete']);
                        ?>
                        <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 inline-flex h-9 w-9 items-center justify-center rounded-full <?= $isComplete ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                                    <i class="<?= $isComplete ? 'ri-checkbox-circle-line' : 'ri-close-circle-line' ?> text-lg"></i>
                                </span>
                                <div>
                                    <p class="font-semibold text-slate-800"><?= htmlspecialchars((string) $definition['label'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-1 text-xs <?= $isComplete ? 'text-emerald-700' : 'text-slate-500' ?>"><?= $isComplete ? 'File tersedia dan dapat direvisi kapan saja.' : 'File belum tersedia.' ?></p>
                                </div>
                            </div>
                            <?php if ($isComplete): ?>
                                <form action="<?= htmlspecialchars(base_url('siswa/berkas/unduh'), ENT_QUOTES, 'UTF-8') ?>" method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="document_key" value="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">
                                        <i class="ri-download-line"></i>
                                        Unduh
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($completed > 0): ?>
                    <div class="border-t border-slate-100 px-6 py-4">
                        <form action="<?= htmlspecialchars(base_url('siswa/berkas/unduh'), ENT_QUOTES, 'UTF-8') ?>" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="document_key" value="all">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                                <i class="ri-file-zip-line"></i>
                                Unduh Semua
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

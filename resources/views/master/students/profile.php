<?php
    $student = is_array($student ?? null) ? $student : [];
    $documentFields = is_array($documentFields ?? null) ? $documentFields : [];
    $documentStatuses = is_array($documentStatuses ?? null) ? $documentStatuses : [];
    $returnTo = trim((string) ($returnTo ?? ('master/siswa/' . (int) ($student['id'] ?? 0) . '/profil')));
    $studentId = (int) ($student['id'] ?? 0);
    $studentName = (string) ($student['nama'] ?? 'Tanpa Nama');
    $photoPath = trim((string) ($student['foto_path'] ?? ''));
    $photoUrl = $photoPath !== '' ? asset($photoPath) : null;
    $completedDocuments = 0;

    foreach ($documentStatuses as $status) {
        if (!empty($status['is_complete'])) {
            $completedDocuments++;
        }
    }

    $totalDocuments = count($documentStatuses);
    $documentPercent = $totalDocuments > 0 ? (int) round(($completedDocuments / $totalDocuments) * 100) : 0;
    $studentStatus = (string) ($student['status'] ?? '');
    $studentStatusLabel = $studentStatus !== '' ? ucfirst($studentStatus) : '-';
    $studentStatusStyle = $studentStatus === 'aktif' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600';
    $dapodikStatus = (string) ($student['status_dapodik'] ?? '');
    $dapodikStatusLabel = match ($dapodikStatus) {
        'aktif' => 'Aktif',
        'belum_masuk' => 'Belum Masuk Dapodik',
        'mutasi' => 'Mutasi',
        'pindah' => 'Pindah',
        'residu' => 'Residu',
        default => $dapodikStatus !== '' ? ucfirst($dapodikStatus) : '-',
    };
    $dapodikStatusStyle = match ($dapodikStatus) {
        'aktif' => 'bg-emerald-100 text-emerald-700',
        'belum_masuk', 'mutasi', 'pindah' => 'bg-amber-100 text-amber-700',
        'residu' => 'bg-rose-100 text-rose-700',
        default => 'bg-slate-100 text-slate-600',
    };
    $genderLabel = match ((string) ($student['jenis_kelamin'] ?? '')) {
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
        default => '-',
    };
    $birthParts = array_filter([
        trim((string) ($student['tempat_lahir'] ?? '')),
        trim((string) ($student['tanggal_lahir'] ?? '')),
    ], static fn ($value) => $value !== '');
    $birthLabel = !empty($birthParts) ? implode(', ', $birthParts) : '-';
    $className = trim((string) ($student['kelas_nama'] ?? ''));
    $yearName = trim((string) ($student['tahun_ajaran_nama'] ?? ''));
    $contactParts = array_values(array_filter([
        trim((string) ($student['hp'] ?? '')),
        trim((string) ($student['telepon'] ?? '')),
        trim((string) ($student['email'] ?? '')),
    ], static fn ($value) => $value !== ''));
    $contactLabel = !empty($contactParts) ? implode(' / ', $contactParts) : '-';
    $shortDocumentLabel = static function (string $label): string {
        $normalized = strtolower($label);

        if (str_contains($normalized, 'ijazah')) {
            return 'Ijazah';
        }
        if (str_contains($normalized, 'rapor') || str_contains($normalized, 'raport')) {
            return 'Rapor';
        }
        if (str_contains($normalized, 'kartu keluarga')) {
            return 'KK';
        }
        if (str_contains($normalized, 'akte') || str_contains($normalized, 'akta')) {
            return 'Akta';
        }
        if (str_contains($normalized, 'ayah')) {
            return 'KTP Ayah';
        }
        if (str_contains($normalized, 'ibu')) {
            return 'KTP Ibu';
        }

        return $label;
    };
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex min-w-0 gap-4">
                <div class="h-28 w-24 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                    <?php if ($photoUrl !== null): ?>
                        <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Foto <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?>" class="h-full w-full object-cover" />
                    <?php else: ?>
                        <div class="flex h-full w-full items-center justify-center text-slate-400">
                            <i class="ri-user-3-line text-4xl"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="min-w-0">
                    <h2 class="break-words text-xl font-semibold text-slate-900">
                        <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?>
                        <?= student_status_badge($student, 'ml-1 align-middle') ?>
                        <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        <?= htmlspecialchars($genderLabel, ENT_QUOTES, 'UTF-8') ?> &middot;
                        <?= htmlspecialchars($birthLabel, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <div class="mt-4 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                        <p><span class="font-semibold text-slate-700">Kelas:</span> <?= htmlspecialchars($className !== '' ? $className : 'Belum ditempatkan', ENT_QUOTES, 'UTF-8') ?></p>
                        <p><span class="font-semibold text-slate-700">Tahun:</span> <?= htmlspecialchars($yearName !== '' ? $yearName : '-', ENT_QUOTES, 'UTF-8') ?></p>
                        <p><span class="font-semibold text-slate-700">NIPD:</span> <?= htmlspecialchars((string) ($student['nipd'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p><span class="font-semibold text-slate-700">NISN:</span> <?= htmlspecialchars((string) ($student['nisn'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="sm:col-span-2"><span class="font-semibold text-slate-700">Kontak:</span> <?= htmlspecialchars($contactLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $studentStatusStyle ?>">
                            <?= htmlspecialchars($studentStatusLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $dapodikStatusStyle ?>">
                            <?= htmlspecialchars($dapodikStatusLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= htmlspecialchars(base_url('master/siswa'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                    <i class="ri-arrow-left-line"></i>
                    Daftar Siswa
                </a>
                <a href="<?= htmlspecialchars(base_url('master/siswa/' . $studentId . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    <i class="ri-pencil-line"></i>
                    Edit Data
                </a>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-800">Foto Siswa</h3>
                <p class="mt-1 text-sm text-slate-500"><?= $photoUrl !== null ? 'Foto tersedia. Upload file baru untuk mengganti.' : 'Foto belum tersedia.' ?></p>
                <form action="<?= htmlspecialchars(base_url('master/siswa/foto'), ENT_QUOTES, 'UTF-8') ?>" method="post" enctype="multipart/form-data" class="mt-5 space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="student_id" value="<?= $studentId ?>" />
                    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>" />
                    <input
                        type="file"
                        id="foto_siswa"
                        name="foto"
                        accept=".jpg,.png"
                        required
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                    />
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                        <i class="ri-upload-cloud-line"></i>
                        Simpan Foto
                    </button>
                </form>
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <span>Kelengkapan Data Fisik</span>
                    <span><?= $documentPercent ?>%</span>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-emerald-500" style="width: <?= $documentPercent ?>%"></div>
                </div>
                <p class="mt-3 text-sm text-slate-500"><?= $completedDocuments ?> dari <?= $totalDocuments ?> dokumen sudah tersedia.</p>
                <?php if ($completedDocuments > 0): ?>
                    <form action="<?= htmlspecialchars(base_url('master/siswa/dokumen/unduh'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="student_id" value="<?= $studentId ?>" />
                        <input type="hidden" name="document_key" value="all" />
                        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>" />
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                            <i class="ri-file-zip-line"></i>
                            Unduh Semua Dokumen
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="lg:col-span-8">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-800">Upload / Revisi Data Fisik</h3>
                <p class="mt-1 text-sm text-slate-500">Kosongkan dokumen yang tidak ingin diganti. Format PDF, JPG, JPEG, PNG, atau WEBP maksimal 10 MB.</p>
                <form action="<?= htmlspecialchars(base_url('master/siswa/dokumen'), ENT_QUOTES, 'UTF-8') ?>" method="post" enctype="multipart/form-data" class="mt-5 space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="student_id" value="<?= $studentId ?>" />
                    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>" />
                    <div class="grid gap-4 md:grid-cols-2">
                        <?php foreach ($documentFields as $key => $definition): ?>
                            <?php
                                $status = $documentStatuses[$key] ?? [];
                                $isComplete = !empty($status['is_complete']);
                            ?>
                            <div class="rounded-xl border <?= $isComplete ? 'border-emerald-200 bg-emerald-50/40' : 'border-amber-200 bg-amber-50/40' ?> p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <label for="<?= htmlspecialchars((string) $definition['input'], ENT_QUOTES, 'UTF-8') ?>" class="block text-sm font-semibold text-slate-700">
                                            <?= htmlspecialchars((string) $definition['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                        <p class="mt-1 text-xs <?= $isComplete ? 'text-emerald-700' : 'text-amber-700' ?>">
                                            <?= $isComplete ? 'Sudah tersedia. Upload file baru untuk revisi.' : 'Belum tersedia.' ?>
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold <?= $isComplete ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                                        <i class="<?= $isComplete ? 'ri-checkbox-circle-line' : 'ri-close-circle-line' ?>"></i>
                                        <?= $isComplete ? 'Ada' : 'Belum' ?>
                                    </span>
                                </div>
                                <input
                                    type="file"
                                    id="<?= htmlspecialchars((string) $definition['input'], ENT_QUOTES, 'UTF-8') ?>"
                                    name="<?= htmlspecialchars((string) $definition['input'], ENT_QUOTES, 'UTF-8') ?>"
                                    accept=".pdf,.jpg,.jpeg,.png,.webp"
                                    class="mt-3 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-100"
                                />
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                        <i class="ri-save-3-line"></i>
                        Simpan Data Fisik
                    </button>
                </form>
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Status Dokumen</h3>
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
                                    <p class="mt-1 text-xs <?= $isComplete ? 'text-emerald-700' : 'text-slate-500' ?>"><?= $isComplete ? 'File tersedia dan dapat direvisi.' : 'File belum tersedia.' ?></p>
                                </div>
                            </div>
                            <?php if ($isComplete): ?>
                                <div class="flex flex-wrap gap-2">
                                    <?php $docPath = trim((string) ($status['path'] ?? '')); ?>
                                    <?php if ($docPath !== ''): ?>
                                        <a
                                            href="<?= htmlspecialchars(asset($docPath), ENT_QUOTES, 'UTF-8') ?>"
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                                        >
                                            <i class="ri-eye-line"></i>
                                            Lihat
                                        </a>
                                    <?php endif; ?>
                                    <form action="<?= htmlspecialchars(base_url('master/siswa/dokumen/unduh'), ENT_QUOTES, 'UTF-8') ?>" method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="student_id" value="<?= $studentId ?>" />
                                        <input type="hidden" name="document_key" value="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>" />
                                        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>" />
                                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">
                                            <i class="ri-download-line"></i>
                                            Unduh
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

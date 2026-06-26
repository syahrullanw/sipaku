<?php
    $student = is_array($student ?? null) ? $student : [];
    $documentFields = is_array($documentFields ?? null) ? $documentFields : [];
    $documentStatuses = is_array($documentStatuses ?? null) ? $documentStatuses : [];
    $academicHistory = is_array($academicHistory ?? null) ? $academicHistory : [];
    $studentId = (int) ($student['id'] ?? 0);
    $studentName = (string) ($student['nama'] ?? 'Siswa');
    $photoPath = trim((string) ($student['foto_path'] ?? ''));
    $photoUrl = $photoPath !== '' ? asset($photoPath) : null;

    $valueOrDash = static function (mixed $value): string {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : '-';
    };
    $formatDate = static function (mixed $value) use ($valueOrDash): string {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '-';
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return $valueOrDash($raw);
        }

        return date('d M Y', $timestamp);
    };
    $formatScore = static function (mixed $value): string {
        return $value === null || $value === '' ? '-' : number_format((float) $value, 2);
    };
    $hasData = static function (mixed $value): bool {
        return trim((string) ($value ?? '')) !== '';
    };
    $historyFor = static function (string $key) use ($academicHistory, $studentId): array {
        $records = $academicHistory[$key][$studentId] ?? [];

        return is_array($records) ? array_values($records) : [];
    };

    $completedDocuments = 0;
    $missingDocumentLabels = [];
    foreach ($documentStatuses as $status) {
        if (!empty($status['is_complete'])) {
            $completedDocuments++;
        } else {
            $missingDocumentLabels[] = (string) ($status['label'] ?? 'Dokumen');
        }
    }
    $totalDocuments = count($documentStatuses);
    $documentPercent = $totalDocuments > 0 ? (int) round(($completedDocuments / $totalDocuments) * 100) : 0;

    $personalCompletenessFields = [
        'nama',
        'nipd',
        'nisn',
        'nik',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'alamat',
        'hp',
        'ayah_nama',
        'ibu_nama',
    ];
    $completedPersonalFields = 0;
    foreach ($personalCompletenessFields as $field) {
        if ($hasData($student[$field] ?? null)) {
            $completedPersonalFields++;
        }
    }
    $personalPercent = count($personalCompletenessFields) > 0
        ? (int) round(($completedPersonalFields / count($personalCompletenessFields)) * 100)
        : 0;

    $studentStatus = strtolower((string) ($student['status'] ?? ''));
    $studentStatusLabel = $studentStatus !== '' ? ucfirst($studentStatus) : '-';
    $studentStatusStyle = $studentStatus === 'aktif' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600';
    $dapodikStatus = strtolower((string) ($student['status_dapodik'] ?? ''));
    $dapodikStatusLabel = match ($dapodikStatus) {
        'aktif' => 'Aktif Dapodik',
        'belum_masuk' => 'Belum Masuk Dapodik',
        'mutasi' => 'Mutasi',
        'pindah' => 'Pindah',
        'residu' => 'Residu',
        default => $dapodikStatus !== '' ? ucfirst(str_replace('_', ' ', $dapodikStatus)) : '-',
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
    $classLabelParts = array_values(array_filter([
        trim((string) ($student['kelas_tingkat'] ?? '')),
        trim((string) ($student['kelas_nama'] ?? '')),
    ], static fn (string $value): bool => $value !== ''));
    $classLabel = !empty($classLabelParts) ? implode(' ', $classLabelParts) : 'Belum ditempatkan';
    $majorLabel = trim((string) ($student['jurusan_nama'] ?? ''));
    if ($majorLabel !== '') {
        $classLabel .= ' - ' . $majorLabel;
    }
    $birthLabel = trim(
        $valueOrDash($student['tempat_lahir'] ?? '') . ', ' . $formatDate($student['tanggal_lahir'] ?? null),
        ' ,-'
    );
    $birthLabel = $birthLabel !== '' ? $birthLabel : '-';

    $promotions = $historyFor('promotions');
    $graduations = $historyFor('graduations');
    $achievements = $historyFor('achievements');
    $extracurriculars = $historyFor('extracurriculars');
    $attendance = $historyFor('attendance');
    $attitudes = $historyFor('attitudes');
    $notes = $historyFor('notes');
    $prakerin = $historyFor('prakerin');
    $subjectHistoryEntries = $academicHistory['subjects'][$studentId] ?? [];
    $subjectHistoryEntries = is_array($subjectHistoryEntries) ? array_values($subjectHistoryEntries) : [];

    $subjectCount = 0;
    foreach ($subjectHistoryEntries as $entry) {
        $subjects = is_array($entry['subjects'] ?? null) ? $entry['subjects'] : [];
        $subjectCount += count($subjects);
    }
    $academicRecordCount = count($promotions)
        + count($graduations)
        + count($achievements)
        + count($extracurriculars)
        + count($attendance)
        + count($attitudes)
        + count($notes)
        + count($prakerin)
        + $subjectCount;

    $identityRows = [
        'NIPD' => $student['nipd'] ?? null,
        'NISN' => $student['nisn'] ?? null,
        'NIK' => $student['nik'] ?? null,
        'Jenis Kelamin' => $genderLabel,
        'Tempat, Tanggal Lahir' => $birthLabel,
        'Agama' => $student['agama'] ?? null,
        'Anak Ke' => $student['anak_ke'] ?? null,
        'Sekolah Asal' => $student['sekolah_asal'] ?? null,
    ];
    $placementRows = [
        'Kelas' => $classLabel,
        'Rombel Saat Ini' => $student['rombel_saat_ini'] ?? null,
        'Tahun Ajaran' => $student['tahun_ajaran_nama'] ?? null,
        'Status Siswa' => $studentStatusLabel,
        'Status Dapodik' => $dapodikStatusLabel,
    ];
    $contactRows = [
        'Email' => $student['email'] ?? null,
        'HP' => $student['hp'] ?? null,
        'Telepon' => $student['telepon'] ?? null,
        'Alamat' => $student['alamat'] ?? null,
        'Dusun' => $student['dusun'] ?? null,
        'Kelurahan/Desa' => $student['kelurahan'] ?? null,
        'Kecamatan' => $student['kecamatan'] ?? null,
        'Kode Pos' => $student['kode_pos'] ?? null,
        'Jenis Tinggal' => $student['jenis_tinggal'] ?? null,
        'Alat Transportasi' => $student['alat_transportasi'] ?? null,
    ];
    $familyGroups = [
        'Ayah' => [
            'Nama' => $student['ayah_nama'] ?? null,
            'Tahun Lahir' => $student['ayah_tahun_lahir'] ?? null,
            'Pendidikan' => $student['ayah_jenjang_pendidikan'] ?? null,
            'Pekerjaan' => $student['ayah_pekerjaan'] ?? null,
            'Penghasilan' => $student['ayah_penghasilan'] ?? null,
            'NIK' => $student['ayah_nik'] ?? null,
        ],
        'Ibu' => [
            'Nama' => $student['ibu_nama'] ?? null,
            'Tahun Lahir' => $student['ibu_tahun_lahir'] ?? null,
            'Pendidikan' => $student['ibu_jenjang_pendidikan'] ?? null,
            'Pekerjaan' => $student['ibu_pekerjaan'] ?? null,
            'Penghasilan' => $student['ibu_penghasilan'] ?? null,
            'NIK' => $student['ibu_nik'] ?? null,
        ],
        'Wali' => [
            'Nama' => $student['wali_nama'] ?? null,
            'Tahun Lahir' => $student['wali_tahun_lahir'] ?? null,
            'Pendidikan' => $student['wali_jenjang_pendidikan'] ?? null,
            'Pekerjaan' => $student['wali_pekerjaan'] ?? null,
            'Penghasilan' => $student['wali_penghasilan'] ?? null,
            'NIK' => $student['wali_nik'] ?? null,
        ],
    ];
    $attitudeLabels = [
        'spiritual' => 'Sikap Spiritual',
        'sosial' => 'Sikap Sosial',
    ];
    $kurmerLevelLabels = [
        'BB' => 'Belum Berkembang',
        'MB' => 'Mulai Berkembang',
        'BSH' => 'Berkembang Sesuai Harapan',
        'SB' => 'Sangat Berkembang',
    ];
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
                        <?= htmlspecialchars($genderLabel, ENT_QUOTES, 'UTF-8') ?>
                        &middot;
                        <?= htmlspecialchars($birthLabel, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <div class="mt-4 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                        <p><span class="font-semibold text-slate-700">Kelas:</span> <?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        <p><span class="font-semibold text-slate-700">Tahun:</span> <?= htmlspecialchars($valueOrDash($student['tahun_ajaran_nama'] ?? null), ENT_QUOTES, 'UTF-8') ?></p>
                        <p><span class="font-semibold text-slate-700">NIPD:</span> <?= htmlspecialchars($valueOrDash($student['nipd'] ?? null), ENT_QUOTES, 'UTF-8') ?></p>
                        <p><span class="font-semibold text-slate-700">NISN:</span> <?= htmlspecialchars($valueOrDash($student['nisn'] ?? null), ENT_QUOTES, 'UTF-8') ?></p>
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
                <a href="<?= htmlspecialchars(base_url('siswa/data-diri'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    <i class="ri-pencil-line"></i>
                    Edit Data Diri
                </a>
                <a href="<?= htmlspecialchars(base_url('siswa/berkas'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                    <i class="ri-folder-upload-line"></i>
                    Berkas Fisik
                </a>
            </div>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data Pribadi</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900"><?= $personalPercent ?>%</p>
            <p class="mt-1 text-xs text-slate-500"><?= $completedPersonalFields ?> dari <?= count($personalCompletenessFields) ?> data inti terisi.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data Fisik</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900"><?= $documentPercent ?>%</p>
            <p class="mt-1 text-xs text-slate-500"><?= $completedDocuments ?> dari <?= $totalDocuments ?> dokumen tersedia.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Semester Nilai</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900"><?= count($subjectHistoryEntries) ?></p>
            <p class="mt-1 text-xs text-slate-500"><?= $subjectCount ?> catatan mata pelajaran.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Riwayat Akademik</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900"><?= $academicRecordCount ?></p>
            <p class="mt-1 text-xs text-slate-500">Total catatan akademik tersimpan.</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-800">Kelengkapan Data</h3>
                <div class="mt-5 space-y-5">
                    <div>
                        <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <span>Data Pribadi Inti</span>
                            <span><?= $personalPercent ?>%</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-indigo-600" style="width: <?= $personalPercent ?>%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <span>Dokumen Fisik</span>
                            <span><?= $documentPercent ?>%</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-emerald-500" style="width: <?= $documentPercent ?>%"></div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Foto Siswa</p>
                            <p class="text-xs text-slate-500"><?= $photoUrl !== null ? 'Foto sudah tersedia.' : 'Foto belum tersedia.' ?></p>
                        </div>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= $photoUrl !== null ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                            <?= $photoUrl !== null ? 'Ada' : 'Belum' ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-800">Status Dokumen</h3>
                <div class="mt-4 space-y-3">
                    <?php foreach ($documentStatuses as $status): ?>
                        <?php $isComplete = !empty($status['is_complete']); ?>
                        <div class="flex items-center justify-between gap-3 rounded-xl border <?= $isComplete ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-white' ?> px-4 py-3">
                            <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars((string) ($status['label'] ?? 'Dokumen'), ENT_QUOTES, 'UTF-8') ?></p>
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold <?= $isComplete ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                                <i class="<?= $isComplete ? 'ri-checkbox-circle-line' : 'ri-close-circle-line' ?>"></i>
                                <?= $isComplete ? 'Ada' : 'Belum' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($missingDocumentLabels)): ?>
                    <p class="mt-4 text-xs text-amber-700">Belum lengkap: <?= htmlspecialchars(implode(', ', $missingDocumentLabels), ENT_QUOTES, 'UTF-8') ?>.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="lg:col-span-8">
            <div class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-800">Identitas Siswa</h3>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <?php foreach ($identityRows as $label => $value): ?>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></dt>
                                <dd class="mt-1 text-slate-700"><?= htmlspecialchars($valueOrDash($value), ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-800">Kelas & Status</h3>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <?php foreach ($placementRows as $label => $value): ?>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></dt>
                                <dd class="mt-1 text-slate-700"><?= htmlspecialchars($valueOrDash($value), ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </section>
            </div>

            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-800">Kontak & Domisili</h3>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <?php foreach ($contactRows as $label => $value): ?>
                        <div class="<?= $label === 'Alamat' ? 'sm:col-span-2' : '' ?>">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></dt>
                            <dd class="mt-1 text-slate-700"><?= nl2br(htmlspecialchars($valueOrDash($value), ENT_QUOTES, 'UTF-8')) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>

            <div class="mt-6 grid gap-6 xl:grid-cols-3">
                <?php foreach ($familyGroups as $groupLabel => $rows): ?>
                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-800"><?= htmlspecialchars((string) $groupLabel, ENT_QUOTES, 'UTF-8') ?></h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <?php foreach ($rows as $label => $value): ?>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></dt>
                                    <dd class="mt-1 text-slate-700"><?= htmlspecialchars($valueOrDash($value), ENT_QUOTES, 'UTF-8') ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <section class="space-y-4">
        <div>
            <h3 class="text-base font-semibold text-slate-800">Riwayat Akademik</h3>
        </div>
        <div class="space-y-6">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-200 p-4">
                    <h4 class="text-sm font-semibold text-slate-700">Status Naik Kelas</h4>
                    <?php if (empty($promotions)): ?>
                        <p class="mt-3 text-sm text-slate-500">Belum ada data status naik kelas.</p>
                    <?php else: ?>
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Tahun</th>
                                        <th class="px-3 py-2">Kelas</th>
                                        <th class="px-3 py-2">Status</th>
                                        <th class="px-3 py-2">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($promotions as $promotion): ?>
                                        <tr>
                                            <td class="px-3 py-2"><?= htmlspecialchars($valueOrDash($promotion['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-3 py-2"><?= htmlspecialchars($valueOrDash($promotion['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-3 py-2 capitalize"><?= htmlspecialchars($valueOrDash($promotion['status'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-3 py-2"><?= htmlspecialchars($valueOrDash($promotion['note'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <h4 class="text-sm font-semibold text-slate-700">Status Kelulusan</h4>
                    <?php if (empty($graduations)): ?>
                        <p class="mt-3 text-sm text-slate-500">Belum ada data kelulusan.</p>
                    <?php else: ?>
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Tahun</th>
                                        <th class="px-3 py-2">Kelas</th>
                                        <th class="px-3 py-2">Status</th>
                                        <th class="px-3 py-2">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($graduations as $graduation): ?>
                                        <tr>
                                            <td class="px-3 py-2"><?= htmlspecialchars($valueOrDash($graduation['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-3 py-2"><?= htmlspecialchars($valueOrDash($graduation['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-3 py-2 capitalize"><?= htmlspecialchars($valueOrDash($graduation['status'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-3 py-2"><?= htmlspecialchars($valueOrDash($graduation['note'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 p-4">
                <h4 class="text-sm font-semibold text-slate-700">Nilai Mapel per Semester</h4>
                <?php if (empty($subjectHistoryEntries)): ?>
                    <p class="mt-3 text-sm text-slate-500">Belum ada riwayat nilai mata pelajaran.</p>
                <?php else: ?>
                    <div class="mt-4 space-y-5">
                        <?php foreach ($subjectHistoryEntries as $historyEntry): ?>
                            <?php
                                $subjects = is_array($historyEntry['subjects'] ?? null) ? $historyEntry['subjects'] : [];
                                $kurmerSubjects = array_values(array_filter($subjects, static fn ($subject): bool => is_array($subject) && strtolower((string) ($subject['curriculum'] ?? '')) === 'kurmer'));
                                $k13Subjects = array_values(array_filter($subjects, static fn ($subject): bool => is_array($subject) && strtolower((string) ($subject['curriculum'] ?? '')) !== 'kurmer'));
                                $semester = (int) ($historyEntry['semester'] ?? 1);
                            ?>
                            <section class="rounded-lg border border-slate-100 bg-slate-50/40 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h5 class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($valueOrDash($historyEntry['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></h5>
                                    <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                                        <?= $semester === 2 ? 'Semester Genap' : 'Semester Ganjil' ?>
                                    </span>
                                </div>
                                <?php if (!empty($k13Subjects)): ?>
                                    <div class="mt-3 overflow-x-auto">
                                        <table class="min-w-full divide-y divide-slate-100 text-sm">
                                            <thead class="bg-white text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                <tr>
                                                    <th class="px-3 py-2">Mata Pelajaran</th>
                                                    <th class="px-3 py-2">Kelas</th>
                                                    <th class="px-3 py-2">Pengetahuan</th>
                                                    <th class="px-3 py-2">Keterampilan</th>
                                                    <th class="px-3 py-2">Rata-rata</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 bg-white">
                                                <?php foreach ($k13Subjects as $subject): ?>
                                                    <?php
                                                        $knowledgeScore = $subject['knowledge_score'] ?? null;
                                                        $skillScore = $subject['skill_score'] ?? null;
                                                        $averageScore = $subject['average_score'] ?? null;
                                                    ?>
                                                    <tr>
                                                        <td class="px-3 py-2 font-semibold text-slate-700"><?= htmlspecialchars($valueOrDash($subject['name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($valueOrDash($subject['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="px-3 py-2 text-slate-600">
                                                            <?= htmlspecialchars($formatScore($knowledgeScore), ENT_QUOTES, 'UTF-8') ?>
                                                            <?php if (!empty($subject['knowledge_predicate'])): ?>
                                                                <span class="text-xs text-slate-400">(<?= htmlspecialchars((string) $subject['knowledge_predicate'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="px-3 py-2 text-slate-600">
                                                            <?= htmlspecialchars($formatScore($skillScore), ENT_QUOTES, 'UTF-8') ?>
                                                            <?php if (!empty($subject['skill_predicate'])): ?>
                                                                <span class="text-xs text-slate-400">(<?= htmlspecialchars((string) $subject['skill_predicate'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars($formatScore($averageScore), ENT_QUOTES, 'UTF-8') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($kurmerSubjects)): ?>
                                    <div class="mt-3 overflow-x-auto">
                                        <table class="min-w-full divide-y divide-emerald-100 text-sm">
                                            <thead class="bg-emerald-50 text-left text-xs font-semibold uppercase tracking-wide text-emerald-700">
                                                <tr>
                                                    <th class="px-3 py-2">Mata Pelajaran Kurmer</th>
                                                    <th class="px-3 py-2">Kelas</th>
                                                    <th class="px-3 py-2">Capaian</th>
                                                    <th class="px-3 py-2">Nilai</th>
                                                    <th class="px-3 py-2">Narasi</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-emerald-100 bg-white">
                                                <?php foreach ($kurmerSubjects as $subject): ?>
                                                    <?php
                                                        $capaianCode = strtoupper(trim((string) ($subject['kurmer_capaian'] ?? '')));
                                                        $capaianLabel = $capaianCode !== '' ? ($kurmerLevelLabels[$capaianCode] ?? $capaianCode) : '-';
                                                        $description = trim((string) ($subject['kurmer_description'] ?? ''));
                                                        $followUp = trim((string) ($subject['kurmer_tindak_lanjut'] ?? ''));
                                                    ?>
                                                    <tr>
                                                        <td class="px-3 py-2 font-semibold text-slate-700"><?= htmlspecialchars($valueOrDash($subject['name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($valueOrDash($subject['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="px-3 py-2 font-semibold text-emerald-700"><?= htmlspecialchars($capaianLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($formatScore($subject['kurmer_score'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="px-3 py-2 text-slate-600">
                                                            <?= $description !== '' ? nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) : '-' ?>
                                                            <?php if ($followUp !== ''): ?>
                                                                <div class="mt-1 text-xs text-amber-600">Tindak lanjut: <?= nl2br(htmlspecialchars($followUp, ENT_QUOTES, 'UTF-8')) ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-200 p-4">
                    <h4 class="text-sm font-semibold text-slate-700">Riwayat Presensi</h4>
                    <?php if (empty($attendance)): ?>
                        <p class="mt-3 text-sm text-slate-500">Belum ada data presensi yang disimpan.</p>
                    <?php else: ?>
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Tahun</th>
                                        <th class="px-3 py-2">Kelas</th>
                                        <th class="px-3 py-2">Sakit</th>
                                        <th class="px-3 py-2">Izin</th>
                                        <th class="px-3 py-2">Bolos</th>
                                        <th class="px-3 py-2">Alpa</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($attendance as $record): ?>
                                        <tr>
                                            <td class="px-3 py-2"><?= htmlspecialchars($valueOrDash($record['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-3 py-2"><?= htmlspecialchars($valueOrDash($record['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-3 py-2"><?= (int) ($record['sick'] ?? 0) ?></td>
                                            <td class="px-3 py-2"><?= (int) ($record['permit'] ?? 0) ?></td>
                                            <td class="px-3 py-2"><?= (int) ($record['truant'] ?? 0) ?></td>
                                            <td class="px-3 py-2"><?= (int) ($record['absent'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <h4 class="text-sm font-semibold text-slate-700">Nilai Sikap</h4>
                    <?php if (empty($attitudes)): ?>
                        <p class="mt-3 text-sm text-slate-500">Belum ada penilaian sikap.</p>
                    <?php else: ?>
                        <div class="mt-3 space-y-3">
                            <?php foreach ($attitudes as $attitude): ?>
                                <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                    <div class="flex flex-wrap justify-between gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        <span><?= htmlspecialchars($attitudeLabels[$attitude['type'] ?? ''] ?? 'Penilaian Sikap', ENT_QUOTES, 'UTF-8') ?></span>
                                        <span><?= htmlspecialchars($valueOrDash($attitude['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <?php if (!empty($attitude['always'])): ?>
                                        <p class="mt-2"><span class="font-semibold text-slate-700">Menonjol:</span> <?= htmlspecialchars(implode(', ', (array) $attitude['always']), ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($attitude['improving'])): ?>
                                        <p class="mt-1"><span class="font-semibold text-slate-700">Perlu Peningkatan:</span> <?= htmlspecialchars((string) $attitude['improving'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($attitude['note'])): ?>
                                        <p class="mt-1"><span class="font-semibold text-slate-700">Catatan:</span> <?= htmlspecialchars((string) $attitude['note'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-200 p-4">
                    <h4 class="text-sm font-semibold text-slate-700">Prestasi</h4>
                    <?php if (empty($achievements)): ?>
                        <p class="mt-3 text-sm text-slate-500">Belum ada data prestasi.</p>
                    <?php else: ?>
                        <ul class="mt-3 space-y-3 text-sm text-slate-600">
                            <?php foreach ($achievements as $achievement): ?>
                                <li class="rounded-lg bg-slate-50 px-4 py-3">
                                    <p class="text-xs font-semibold text-slate-500"><?= htmlspecialchars($valueOrDash($achievement['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars($valueOrDash($achievement['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-1 font-semibold text-slate-700"><?= htmlspecialchars($valueOrDash($achievement['type'] ?? null), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p><?= htmlspecialchars($valueOrDash($achievement['description'] ?? null), ENT_QUOTES, 'UTF-8') ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <h4 class="text-sm font-semibold text-slate-700">Catatan Wali Kelas</h4>
                    <?php if (empty($notes)): ?>
                        <p class="mt-3 text-sm text-slate-500">Belum ada catatan wali kelas.</p>
                    <?php else: ?>
                        <ul class="mt-3 space-y-3 text-sm text-slate-600">
                            <?php foreach ($notes as $note): ?>
                                <li class="rounded-lg bg-slate-50 px-4 py-3">
                                    <p class="text-xs font-semibold text-slate-500"><?= htmlspecialchars($valueOrDash($note['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars($valueOrDash($note['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-1"><?= htmlspecialchars($valueOrDash($note['note'] ?? null), ENT_QUOTES, 'UTF-8') ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-200 p-4">
                    <h4 class="text-sm font-semibold text-slate-700">Ekskul & Pengembangan Diri</h4>
                    <?php if (empty($extracurriculars)): ?>
                        <p class="mt-3 text-sm text-slate-500">Belum ada penilaian ekskul.</p>
                    <?php else: ?>
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Tahun</th>
                                        <th class="px-3 py-2">Ekskul</th>
                                        <th class="px-3 py-2">Nilai</th>
                                        <th class="px-3 py-2">Predikat</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($extracurriculars as $extracurricular): ?>
                                        <tr>
                                            <td class="px-3 py-2"><?= htmlspecialchars($valueOrDash($extracurricular['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-3 py-2"><?= htmlspecialchars($valueOrDash($extracurricular['activity_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-3 py-2"><?= htmlspecialchars($formatScore($extracurricular['scores']['final'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-3 py-2"><?= htmlspecialchars($valueOrDash($extracurricular['predicate'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <h4 class="text-sm font-semibold text-slate-700">Riwayat Prakerin</h4>
                    <?php if (empty($prakerin)): ?>
                        <p class="mt-3 text-sm text-slate-500">Belum ada riwayat prakerin.</p>
                    <?php else: ?>
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Tahun</th>
                                        <th class="px-3 py-2">Tempat</th>
                                        <th class="px-3 py-2">Nilai</th>
                                        <th class="px-3 py-2">Predikat</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($prakerin as $record): ?>
                                        <tr>
                                            <td class="px-3 py-2"><?= htmlspecialchars($valueOrDash($record['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-3 py-2"><?= htmlspecialchars($valueOrDash($record['place_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-3 py-2"><?= htmlspecialchars($formatScore($record['scores']['final'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-3 py-2"><?= htmlspecialchars($valueOrDash($record['scores']['predicate'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

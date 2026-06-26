<?php
    /**
     * @var array<string, mixed>|null $activeYear
     * @var array<int, string> $majorOptions
     * @var array<int, string> $classOptions
     * @var array<int, array<string, mixed>> $students
     * @var array<int, array<string, mixed>> $packageList
     * @var array<int, array<string, mixed>> $skkniList
     * @var array<int, array<string, mixed>> $dudiList
     * @var array<int, array<int, array<string, mixed>>> $assessorMap
     * @var array<int, array<string, mixed>> $assessmentMap
     * @var array<string, mixed>|null $editingPackage
     * @var array<int, string> $teacherOptions
     */
    $selectedMajorId = (int) ($selectedMajorId ?? 0);
    $selectedClassId = (int) ($selectedClassId ?? 0);
    $activeYearName = $activeYear['nama'] ?? '-';
    $editingPackage = $editingPackage ?? null;
    $editingSkkni = $editingSkkni ?? null;
    $teacherOptions = $teacherOptions ?? [];
    $editingDudi = $editingDudi ?? null;
    $editingAssessor = $editingAssessor ?? null;
    $selectedTab = in_array(($selectedTab ?? 'nilai'), ['nilai', 'master'], true) ? (string) $selectedTab : 'nilai';
    $nilaiTabUrl = base_url('kaprodi/ukk?' . http_build_query([
        'jurusan_id' => $selectedMajorId,
        'kelas_id' => $selectedClassId,
        'tab' => 'nilai',
    ]));
    $masterTabUrl = base_url('kaprodi/ukk?' . http_build_query([
        'jurusan_id' => $selectedMajorId,
        'kelas_id' => $selectedClassId,
        'tab' => 'master',
    ]));
    $masterBaseUrl = base_url('kaprodi/ukk?' . http_build_query([
        'jurusan_id' => $selectedMajorId,
        'kelas_id' => $selectedClassId,
        'tab' => 'master',
    ]));

    $selectedPackageId = (int) old('paket_ujian_id', 0);
    if ($selectedPackageId === 0 && !empty($assessmentMap)) {
        $firstAssessment = reset($assessmentMap);
        $selectedPackageId = (int) ($firstAssessment['skkni_paket_id'] ?? 0);
    }
    if ($selectedPackageId === 0 && !empty($packageList)) {
        $firstPackage = reset($packageList);
        $selectedPackageId = (int) ($firstPackage['id'] ?? 0);
    }

    $selectedInternalTeacherId = (int) old('internal_assessor_teacher_id', 0);
    $selectedInternalName = (string) old('internal_assessor_name', '');
    if ($selectedInternalTeacherId === 0 && $selectedInternalName === '' && !empty($assessmentMap)) {
        $firstAssessment = reset($assessmentMap);
        $selectedInternalTeacherId = (int) ($firstAssessment['internal_assessor_teacher_id'] ?? 0);
        $selectedInternalName = (string) ($firstAssessment['internal_assessor_name'] ?? '');
    }

    $selectedDudiId = (int) old('dudi_id', 0);
    if ($selectedDudiId === 0 && !empty($assessmentMap)) {
        $firstAssessment = reset($assessmentMap);
        $selectedDudiId = (int) ($firstAssessment['dudi_id'] ?? 0);
    }

    $selectedCertificateDate = (string) old('tanggal_sertifikat', '');
    if ($selectedCertificateDate === '' && !empty($assessmentMap)) {
        foreach ($assessmentMap as $assessment) {
            $candidateDate = trim((string) ($assessment['tanggal_sertifikat'] ?? ''));
            if ($candidateDate !== '' && $candidateDate !== '0000-00-00') {
                $selectedCertificateDate = $candidateDate;
                break;
            }
        }
    }

    $skkniByPackage = [];
    foreach ($skkniList as $row) {
        $packageId = (int) ($row['paket_ujian_id'] ?? 0);
        if ($packageId <= 0) {
            continue;
        }

        if (!isset($skkniByPackage[$packageId])) {
            $skkniByPackage[$packageId] = [];
        }

        $skkniByPackage[$packageId][] = $row;
    }
?>
<div class="space-y-5">
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <p class="text-lg font-semibold text-slate-900">UKK & Skill Passport</p>
                <p class="text-sm text-slate-500">Tahun ajaran <?= htmlspecialchars($activeYearName, ENT_QUOTES, 'UTF-8') ?>.</p>
            </div>
            <form method="get" class="grid w-full grid-cols-1 gap-2 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] xl:w-auto">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($selectedTab, ENT_QUOTES, 'UTF-8') ?>">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Jurusan</label>
                    <select name="jurusan_id" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
                        <?php foreach ($majorOptions as $id => $label): ?>
                            <option value="<?= (int) $id ?>" <?= $selectedMajorId === (int) $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Kelas XII</label>
                    <select name="kelas_id" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
                        <option value="">Pilih kelas</option>
                        <?php foreach ($classOptions as $id => $label): ?>
                            <option value="<?= (int) $id ?>" <?= $selectedClassId === (int) $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full rounded bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">Terapkan</button>
                </div>
            </form>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-2 md:grid-cols-4">
            <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Siswa</p>
                <p class="mt-1 text-lg font-semibold text-slate-900"><?= count($students) ?></p>
            </div>
            <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Paket</p>
                <p class="mt-1 text-lg font-semibold text-slate-900"><?= count($packageList) ?></p>
            </div>
            <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">SKKNI</p>
                <p class="mt-1 text-lg font-semibold text-slate-900"><?= count($skkniList) ?></p>
            </div>
            <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">DUDI</p>
                <p class="mt-1 text-lg font-semibold text-slate-900"><?= count($dudiList) ?></p>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
            <a href="<?= htmlspecialchars($nilaiTabUrl, ENT_QUOTES, 'UTF-8') ?>" class="rounded px-4 py-2 text-sm font-semibold <?= $selectedTab === 'nilai' ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">Input Nilai</a>
            <a href="<?= htmlspecialchars($masterTabUrl, ENT_QUOTES, 'UTF-8') ?>" class="rounded px-4 py-2 text-sm font-semibold <?= $selectedTab === 'master' ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">Master UKK</a>
        </div>
    </div>

    <?php if ($selectedTab === 'master'): ?>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="space-y-6">
            <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-800"><?= $editingPackage ? 'Ubah Paket Ujian' : 'Tambah Paket Ujian' ?></h3>
                    <?php if ($editingPackage): ?>
                        <a href="<?= htmlspecialchars($masterBaseUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Batal</a>
                    <?php endif; ?>
                </div>
                <form method="post" action="<?= htmlspecialchars(base_url('kaprodi/ukk/paket'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) ($editingPackage['id'] ?? 0) ?>">
                    <input type="hidden" name="jurusan_id" value="<?= $selectedMajorId ?>">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Nama Paket Ujian</label>
                        <input type="text" name="nama" required value="<?= htmlspecialchars($editingPackage['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Deskripsi (opsional)</label>
                        <textarea name="deskripsi" rows="2" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars($editingPackage['deskripsi'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="submit" class="rounded bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700"><?= $editingPackage ? 'Simpan Perubahan' : 'Simpan Paket' ?></button>
                    </div>
                </form>
                <div class="mt-3">
                    <h4 class="text-sm font-semibold text-slate-700">Daftar Paket Ujian (<?= htmlspecialchars($activeYearName, ENT_QUOTES, 'UTF-8') ?>)</h4>
                    <div class="mt-2 overflow-x-auto">
                        <table class="w-full min-w-[520px] border-collapse text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-left font-semibold text-slate-700">
                                    <th class="border border-slate-200 px-2 py-2">Nama</th>
                                    <th class="border border-slate-200 px-2 py-2">Deskripsi</th>
                                    <th class="border border-slate-200 px-2 py-2">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($packageList)): ?>
                                    <tr>
                                        <td colspan="3" class="border border-slate-200 px-2 py-2 text-center text-slate-500">Belum ada paket ujian.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($packageList as $row): ?>
                                        <tr>
                                            <td class="border border-slate-200 px-2 py-2 font-semibold text-slate-800"><?= htmlspecialchars($row['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="border border-slate-200 px-2 py-2 text-slate-600"><?= htmlspecialchars($row['deskripsi'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="border border-slate-200 px-2 py-2">
                                                <div class="flex items-center gap-2">
                                                    <a href="<?= htmlspecialchars($masterBaseUrl . '&paket_edit=' . (int) ($row['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-teal-600 hover:text-teal-700">Ubah</a>
                                                    <form method="post" action="<?= htmlspecialchars(base_url('kaprodi/ukk/paket/' . (int) ($row['id'] ?? 0) . '/hapus'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Hapus paket ujian ini?');">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-800"><?= $editingSkkni ? 'Ubah SKKNI' : 'Tambah SKKNI' ?></h3>
                    <?php if ($editingSkkni): ?>
                        <a href="<?= htmlspecialchars($masterBaseUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Batal</a>
                    <?php endif; ?>
                </div>
                <form method="post" action="<?= htmlspecialchars(base_url('kaprodi/ukk/skkni'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) ($editingSkkni['id'] ?? 0) ?>">
                    <input type="hidden" name="jurusan_id" value="<?= $selectedMajorId ?>">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Paket Ujian</label>
                        <select name="paket_ujian_id" required class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Pilih paket ujian</option>
                            <?php foreach ($packageList as $row): ?>
                                <option value="<?= (int) ($row['id'] ?? 0) ?>" <?= (int) ($editingSkkni['paket_ujian_id'] ?? 0) === (int) ($row['id'] ?? 0) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($row['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-semibold text-slate-700">Kode SKKNI</label>
                            <input type="text" name="kode" required value="<?= htmlspecialchars($editingSkkni['kode'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-700">Judul</label>
                            <input type="text" name="judul" required value="<?= htmlspecialchars($editingSkkni['judul'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Deskripsi</label>
                        <textarea name="deskripsi" rows="2" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars($editingSkkni['deskripsi'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Unit Kompetensi (opsional)</label>
                        <textarea name="unit_kompetensi" rows="3" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm" placeholder="Pisahkan dengan baris baru"><?= htmlspecialchars($editingSkkni['unit_kompetensi'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="submit" class="rounded bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700"><?= $editingSkkni ? 'Simpan Perubahan' : 'Simpan SKKNI' ?></button>
                    </div>
                </form>
                <div class="mt-3">
                    <h4 class="text-sm font-semibold text-slate-700">Daftar SKKNI (<?= htmlspecialchars($activeYearName, ENT_QUOTES, 'UTF-8') ?>)</h4>
                    <div class="mt-2 overflow-x-auto">
                        <table class="w-full min-w-[600px] border-collapse text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-left font-semibold text-slate-700">
                                    <th class="border border-slate-200 px-2 py-2">Paket</th>
                                    <th class="border border-slate-200 px-2 py-2">Kode</th>
                                    <th class="border border-slate-200 px-2 py-2">Judul</th>
                                    <th class="border border-slate-200 px-2 py-2">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($skkniList)): ?>
                                    <tr>
                                        <td colspan="4" class="border border-slate-200 px-2 py-2 text-center text-slate-500">Belum ada SKKNI.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($skkniList as $row): ?>
                                        <tr>
                                            <td class="border border-slate-200 px-2 py-2 text-slate-600"><?= htmlspecialchars($row['paket_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="border border-slate-200 px-2 py-2 font-semibold text-slate-800"><?= htmlspecialchars($row['kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="border border-slate-200 px-2 py-2"><?= htmlspecialchars($row['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="border border-slate-200 px-2 py-2">
                                                <div class="flex items-center gap-2">
                                                    <a href="<?= htmlspecialchars($masterBaseUrl . '&skkni_edit=' . (int) ($row['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-teal-600 hover:text-teal-700">Ubah</a>
                                                    <form method="post" action="<?= htmlspecialchars(base_url('kaprodi/ukk/skkni/' . (int) ($row['id'] ?? 0) . '/hapus'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Hapus SKKNI ini?');">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-800"><?= $editingDudi ? 'Ubah DUDI' : 'Tambah DUDI' ?></h3>
                <?php if ($editingDudi): ?>
                    <a href="<?= htmlspecialchars($masterBaseUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Batal</a>
                <?php endif; ?>
            </div>
            <form method="post" action="<?= htmlspecialchars(base_url('kaprodi/ukk/dudi'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) ($editingDudi['id'] ?? 0) ?>">
                <input type="hidden" name="jurusan_id" value="<?= $selectedMajorId ?>">
                <div>
                    <label class="text-sm font-semibold text-slate-700">Nama DUDI</label>
                    <input type="text" name="nama" required value="<?= htmlspecialchars($editingDudi['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Penanggung Jawab</label>
                        <input type="text" name="penanggung_jawab" value="<?= htmlspecialchars($editingDudi['penanggung_jawab'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Kontak</label>
                        <input type="text" name="kontak" value="<?= htmlspecialchars($editingDudi['kontak'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Alamat</label>
                    <textarea name="alamat" rows="2" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars($editingDudi['alamat'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Catatan</label>
                    <textarea name="catatan" rows="2" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars($editingDudi['catatan'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="submit" class="rounded bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700"><?= $editingDudi ? 'Simpan Perubahan' : 'Simpan DUDI' ?></button>
                </div>
            </form>
            <div class="mt-3">
                <h4 class="text-sm font-semibold text-slate-700">Daftar DUDI</h4>
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full min-w-[520px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-left font-semibold text-slate-700">
                                <th class="border border-slate-200 px-2 py-2">Nama</th>
                                <th class="border border-slate-200 px-2 py-2">Kontak</th>
                                <th class="border border-slate-200 px-2 py-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dudiList)): ?>
                                <tr>
                                    <td colspan="3" class="border border-slate-200 px-2 py-2 text-center text-slate-500">Belum ada DUDI.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dudiList as $row): ?>
                                    <tr>
                                        <td class="border border-slate-200 px-2 py-2 font-semibold text-slate-800">
                                            <div><?= htmlspecialchars($row['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php if (!empty($row['alamat'])): ?>
                                                <div class="text-xs text-slate-500"><?= htmlspecialchars($row['alamat'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="border border-slate-200 px-2 py-2">
                                            <div><?= htmlspecialchars($row['penanggung_jawab'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="text-xs text-slate-500"><?= htmlspecialchars($row['kontak'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td class="border border-slate-200 px-2 py-2">
                                            <div class="flex items-center gap-2">
                                                <a href="<?= htmlspecialchars($masterBaseUrl . '&dudi_edit=' . (int) ($row['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-teal-600 hover:text-teal-700">Ubah</a>
                                                <form method="post" action="<?= htmlspecialchars(base_url('kaprodi/ukk/dudi/' . (int) ($row['id'] ?? 0) . '/hapus'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Hapus DUDI ini?');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Hapus</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-1 space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-800"><?= $editingAssessor ? 'Ubah Asesor' : 'Tambah Asesor' ?></h3>
                <?php if ($editingAssessor): ?>
                    <a href="<?= htmlspecialchars($masterBaseUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Batal</a>
                <?php endif; ?>
            </div>
            <form method="post" action="<?= htmlspecialchars(base_url('kaprodi/ukk/asesor'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) ($editingAssessor['id'] ?? 0) ?>">
                <div>
                    <label class="text-sm font-semibold text-slate-700">DUDI</label>
                    <select name="dudi_id" required class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                        <option value="">Pilih DUDI</option>
                        <?php foreach ($dudiList as $row): ?>
                            <option value="<?= (int) ($row['id'] ?? 0) ?>" <?= (int) ($editingAssessor['dudi_id'] ?? 0) === (int) ($row['id'] ?? 0) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Nama Asesor</label>
                    <input type="text" name="nama" required value="<?= htmlspecialchars($editingAssessor['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Jabatan</label>
                        <input type="text" name="jabatan" value="<?= htmlspecialchars($editingAssessor['jabatan'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">No. Registrasi</label>
                        <input type="text" name="nomor_registrasi" value="<?= htmlspecialchars($editingAssessor['nomor_registrasi'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Kontak</label>
                    <input type="text" name="kontak" value="<?= htmlspecialchars($editingAssessor['kontak'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="submit" class="rounded bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700"><?= $editingAssessor ? 'Simpan Perubahan' : 'Simpan Asesor' ?></button>
                </div>
            </form>
            <div class="border-t border-slate-100 pt-3">
                <h4 class="text-sm font-semibold text-slate-700">Daftar Asesor</h4>
                <div class="mt-2 space-y-2">
                    <?php if (empty($assessorMap)): ?>
                        <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">Belum ada asesor.</div>
                    <?php else: ?>
                        <?php foreach ($dudiList as $dudiRow): ?>
                            <?php
                                $dudiId = (int) ($dudiRow['id'] ?? 0);
                                $assessors = $assessorMap[$dudiId] ?? [];
                            ?>
                            <?php if (!empty($assessors)): ?>
                                <div class="rounded border border-slate-200">
                                    <div class="border-b border-slate-100 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
                                        <?= htmlspecialchars($dudiRow['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="divide-y divide-slate-100">
                                        <?php foreach ($assessors as $assessor): ?>
                                            <div class="flex items-center justify-between gap-3 px-3 py-2">
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($assessor['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                                    <p class="text-xs text-slate-500"><?= htmlspecialchars($assessor['jabatan'] ?? ($assessor['nomor_registrasi'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                                </div>
                                                <div class="flex shrink-0 items-center gap-2">
                                                    <a href="<?= htmlspecialchars($masterBaseUrl . '&asesor_edit=' . (int) ($assessor['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-teal-600 hover:text-teal-700">Ubah</a>
                                                    <form method="post" action="<?= htmlspecialchars(base_url('kaprodi/ukk/asesor/' . (int) ($assessor['id'] ?? 0) . '/hapus'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Hapus asesor ini?');">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Hapus</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>

    <?php if ($selectedTab === 'nilai'): ?>
    <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Input Nilai UKK</h3>
                    <p class="text-xs text-slate-500">Wajib pilih paket ujian dan DUDI. Asesor boleh berbeda per siswa.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="<?= htmlspecialchars(base_url('kaprodi/ukk/cetak/sertifikat?kelas_id=' . $selectedClassId), ENT_QUOTES, 'UTF-8') ?>" class="rounded bg-slate-900 px-4 py-2 text-xs font-semibold text-white shadow-sm shadow-slate-200 hover:bg-slate-800">Cetak Sertifikat Depan</a>
                    <a href="<?= htmlspecialchars(base_url('kaprodi/ukk/cetak/sertifikat?kelas_id=' . $selectedClassId . '&sisi=belakang'), ENT_QUOTES, 'UTF-8') ?>" class="rounded bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm shadow-indigo-200 hover:bg-indigo-700">Cetak Lembar Belakang</a>
                    <a href="<?= htmlspecialchars(base_url('kaprodi/ukk/cetak/skill-passport?kelas_id=' . $selectedClassId), ENT_QUOTES, 'UTF-8') ?>" class="rounded bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm shadow-emerald-200 hover:bg-emerald-700">Cetak Skill Passport Kelas</a>
                </div>
            </div>
            <form method="post" action="<?= htmlspecialchars(base_url('kaprodi/ukk/penilaian'), ENT_QUOTES, 'UTF-8') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="kelas_id" value="<?= $selectedClassId ?>">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Paket Ujian</label>
                        <select id="ukk-package-select" name="paket_ujian_id" required class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Pilih paket ujian</option>
                            <?php foreach ($packageList as $row): ?>
                                <option value="<?= (int) ($row['id'] ?? 0) ?>" <?= $selectedPackageId === (int) ($row['id'] ?? 0) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($row['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php $hasSelectedItems = !empty($skkniByPackage[$selectedPackageId] ?? []); ?>
                        <div class="mt-2 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600" id="ukk-package-skkni">
                            <div data-empty class="<?= $hasSelectedItems ? 'hidden' : '' ?>">SKKNI pada paket ini belum tersedia.</div>
                            <?php foreach ($packageList as $row): ?>
                                <?php
                                    $packageId = (int) ($row['id'] ?? 0);
                                    $items = $skkniByPackage[$packageId] ?? [];
                                    $isVisible = $packageId > 0 && $packageId === $selectedPackageId;
                                ?>
                                <div data-package="<?= $packageId ?>" class="<?= $isVisible ? '' : 'hidden' ?>">
                                    <?php if (empty($items)): ?>
                                        <div>SKKNI pada paket ini belum tersedia.</div>
                                    <?php else: ?>
                                        <ul class="list-disc pl-4">
                                            <?php foreach ($items as $item): ?>
                                                <li><?= htmlspecialchars(($item['kode'] ?? '-') . ' - ' . ($item['judul'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">DUDI Penguji</label>
                        <select name="dudi_id" required class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Pilih DUDI</option>
                            <?php foreach ($dudiList as $row): ?>
                                <option value="<?= (int) ($row['id'] ?? 0) ?>" <?= $selectedDudiId === (int) ($row['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars($row['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Penguji Internal (Guru Aktif)</label>
                        <select name="internal_assessor_teacher_id" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Pilih guru</option>
                            <?php foreach ($teacherOptions as $id => $label): ?>
                                <option value="<?= (int) $id ?>" <?= $selectedInternalTeacherId === (int) $id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Nama Penguji Internal (Manual)</label>
                        <input type="text" name="internal_assessor_name" value="<?= htmlspecialchars($selectedInternalName, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm" placeholder="Kosongkan jika pilih guru">
                        <p class="mt-1 text-xs text-slate-500">Jika diisi, pilihan guru akan diabaikan.</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Titimangsa Cetak</label>
                        <input type="date" name="tanggal_sertifikat" value="<?= htmlspecialchars($selectedCertificateDate, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-slate-500">Dipakai untuk sertifikat dan skill passport satu kelas.</p>
                    </div>
                </div>
                <div class="mt-3 rounded-lg border border-slate-200 bg-white p-3">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div class="md:col-span-2">
                            <label class="text-sm font-semibold text-slate-700">Prefix Nomor Sertifikat</label>
                            <input type="text" id="ukk-certificate-prefix" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm" placeholder="Contoh: SMKISNU-UKK/I/2026">
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-700">Mulai Dari</label>
                            <input type="text" id="ukk-certificate-start" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-sm" placeholder="Contoh: 001">
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs text-slate-500">Hanya mengisi nomor sertifikat yang masih kosong. Format hasil: 001/SMKISNU-UKK/I/2026.</p>
                        <button type="button" id="ukk-generate-certificate-numbers" class="rounded bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Generate Nomor Sertifikat</button>
                    </div>
                </div>

                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[960px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-left font-semibold text-slate-700">
                                <th class="border border-slate-200 px-2 py-2">Siswa</th>
                                <th class="border border-slate-200 px-2 py-2">Asesor</th>
                                <th class="border border-slate-200 px-2 py-2">Teori</th>
                                <th class="border border-slate-200 px-2 py-2">Praktik</th>
                                <th class="border border-slate-200 px-2 py-2">Nilai Akhir</th>
                                <th class="border border-slate-200 px-2 py-2">Predikat</th>
                                <th class="border border-slate-200 px-2 py-2">No. Sertifikat</th>
                                <th class="border border-slate-200 px-2 py-2">Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students) || $selectedClassId === 0): ?>
                                <tr>
                                    <td colspan="8" class="border border-slate-200 px-2 py-2 text-center text-slate-500">Pilih kelas XII untuk mulai input nilai.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <?php
                                        $sid = (int) ($student['id'] ?? 0);
                                        $existing = $assessmentMap[$sid] ?? [];
                                        $studentInactive = student_is_inactive($student);
                                        $inactiveTitle = 'Siswa nonaktif; nilai UKK tidak dapat diinput.';
                                    ?>
                                    <tr>
                                        <td class="border border-slate-200 px-2 py-2 font-semibold text-slate-800">
                                            <div>
                                                <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                            </div>
                                            <div class="text-xs text-slate-500">NISN <?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td class="border border-slate-200 px-2 py-2">
                                            <select name="assessments[<?= $sid ?>][asesor_id]" class="w-full rounded border border-slate-200 px-2 py-1 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                                <option value="">Pilih asesor</option>
                                                <?php foreach ($assessorMap as $dudiId => $assessors): ?>
                                                    <?php foreach ($assessors as $assessor): ?>
                                                        <option value="<?= (int) ($assessor['id'] ?? 0) ?>" <?= (int) ($existing['asesor_id'] ?? 0) === (int) ($assessor['id'] ?? 0) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($assessor['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="border border-slate-200 px-2 py-2">
                                            <input type="number" step="0.01" name="assessments[<?= $sid ?>][nilai_teori]" value="<?= htmlspecialchars($existing['nilai_teori'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded border border-slate-200 px-2 py-1 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" data-ukk-score="theory" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                        </td>
                                        <td class="border border-slate-200 px-2 py-2">
                                            <input type="number" step="0.01" name="assessments[<?= $sid ?>][nilai_praktik]" value="<?= htmlspecialchars($existing['nilai_praktik'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded border border-slate-200 px-2 py-1 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" data-ukk-score="practice" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                        </td>
                                        <td class="border border-slate-200 px-2 py-2">
                                            <input type="number" step="0.01" name="assessments[<?= $sid ?>][nilai_akhir]" value="<?= htmlspecialchars($existing['nilai_akhir'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded border border-slate-200 bg-slate-50 px-2 py-1 text-sm text-slate-700 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" placeholder="Otomatis" data-ukk-score="final" readonly <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                        </td>
                                        <td class="border border-slate-200 px-2 py-2">
                                            <input type="text" name="assessments[<?= $sid ?>][predikat]" value="<?= htmlspecialchars($existing['predikat'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded border border-slate-200 px-2 py-1 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" placeholder="A / B / Baik" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                        </td>
                                        <td class="border border-slate-200 px-2 py-2">
                                            <input type="text" name="assessments[<?= $sid ?>][nomor_sertifikat]" value="<?= htmlspecialchars($existing['nomor_sertifikat'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded border border-slate-200 px-2 py-1 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                        </td>
                                        <td class="border border-slate-200 px-2 py-2">
                                            <textarea name="assessments[<?= $sid ?>][catatan]" rows="1" class="w-full rounded border border-slate-200 px-2 py-1 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>><?= htmlspecialchars($existing['catatan'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex items-center justify-end">
                    <button type="submit" class="rounded bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">Simpan Nilai</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var select = document.getElementById('ukk-package-select');
        var container = document.getElementById('ukk-package-skkni');
        var generateButton = document.getElementById('ukk-generate-certificate-numbers');

        if (!select || !container) {
            return;
        }

        var updateList = function () {
            var selected = select.value;
            var blocks = container.querySelectorAll('[data-package]');
            var empty = container.querySelector('[data-empty]');
            var visible = false;

            blocks.forEach(function (block) {
                if (block.getAttribute('data-package') === selected) {
                    block.classList.remove('hidden');
                    visible = true;
                } else {
                    block.classList.add('hidden');
                }
            });

            if (empty) {
                if (visible) {
                    empty.classList.add('hidden');
                } else {
                    empty.classList.remove('hidden');
                }
            }
        };

        select.addEventListener('change', updateList);
        updateList();

        var updateFinalScore = function (row) {
            var theoryInput = row.querySelector('[data-ukk-score="theory"]');
            var practiceInput = row.querySelector('[data-ukk-score="practice"]');
            var finalInput = row.querySelector('[data-ukk-score="final"]');

            if (!theoryInput || !practiceInput || !finalInput) {
                return;
            }

            var theory = parseFloat(theoryInput.value);
            var practice = parseFloat(practiceInput.value);

            if (!Number.isFinite(theory) || !Number.isFinite(practice)) {
                finalInput.value = '';
                return;
            }

            finalInput.value = ((theory * 0.4) + (practice * 0.6)).toFixed(2);
        };

        document.querySelectorAll('[data-ukk-score="theory"], [data-ukk-score="practice"]').forEach(function (input) {
            var row = input.closest('tr');
            if (!row) {
                return;
            }

            input.addEventListener('input', function () {
                updateFinalScore(row);
            });
            updateFinalScore(row);
        });

        if (!generateButton) {
            return;
        }

        generateButton.addEventListener('click', function () {
            var prefixInput = document.getElementById('ukk-certificate-prefix');
            var startInput = document.getElementById('ukk-certificate-start');

            if (!prefixInput) {
                return;
            }

            var prefix = prefixInput.value.trim();
            if (prefix === '') {
                alert('Isi prefix nomor sertifikat terlebih dahulu.');
                prefixInput.focus();
                return;
            }

            var rawStart = startInput ? startInput.value.trim() : '';
            var startNumber = parseInt(rawStart, 10);
            if (!Number.isFinite(startNumber) || startNumber <= 0) {
                startNumber = 1;
            }

            var padWidth = rawStart && /^\d+$/.test(rawStart) ? rawStart.length : 0;
            var counter = startNumber;

            var inputs = document.querySelectorAll('input[name^="assessments"][name$="[nomor_sertifikat]"]');
            inputs.forEach(function (input) {
                if (input.value.trim() !== '') {
                    return;
                }

                var numberText = String(counter);
                if (padWidth > 0) {
                    numberText = numberText.padStart(padWidth, '0');
                }

                if (prefix.charAt(0) === '/') {
                    input.value = numberText + prefix;
                } else {
                    input.value = numberText + '/' + prefix;
                }
                counter += 1;
            });
        });
    });
</script>

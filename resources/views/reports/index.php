<?php
    /** @var array<int, array<string, mixed>> $classes */
    $hasClasses = !empty($classes ?? []);
    $hasStudents = !empty($students ?? []);
    $paperSize = strtolower((string) ($paperSize ?? 'f4'));
    if (!in_array($paperSize, ['f4', 'a4'], true)) {
        $paperSize = 'f4';
    }
    $paperOptions = $paperOptions ?? [
        'f4' => 'F4 / Folio (33 x 21,5 cm)',
        'a4' => 'A4 (29,7 x 21 cm)',
    ];
    $semesterLabel = $semesterOptions[$semester] ?? ($semester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)');
    $schoolName = $schoolProfile['nama'] ?? 'Nama Sekolah';
    $selectedClassIds = isset($selectedClassIds) ? array_values(array_filter($selectedClassIds, static fn ($id) => $id > 0)) : [];
    if (empty($selectedClassIds)) {
        if (isset($_GET['kelas_ids'])) {
            if (is_array($_GET['kelas_ids'])) {
                $selectedClassIds = array_values(array_filter(array_map('intval', $_GET['kelas_ids']), static fn ($id) => $id > 0));
            } else {
                $selectedClassIds = array_values(array_filter(array_map('intval', explode(',', $_GET['kelas_ids'])), static fn ($id) => $id > 0));
            }
        }
    }
    if (empty($selectedClassIds) && $selectedClassId > 0) {
        $selectedClassIds = [$selectedClassId];
    }
    $firstSelectedClassId = !empty($selectedClassIds) ? $selectedClassIds[0] : 0;
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-800">Cetak Raport</h2>
        <p class="mt-2 text-sm text-slate-500">
            Pilih kelas, cari siswa untuk mencetak raport perorangan, atau cetak raport seluruh siswa dalam kelas yang dipilih.
        </p>
    </div>

    <?php if (!$hasClasses): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            Belum ada data kelas yang tersedia untuk akun ini. Tambahkan kelas terlebih dahulu atau pastikan Anda tercatat sebagai wali kelas pada tahun ajaran aktif.
        </div>
    <?php else: ?>
        <div class="grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-800">Parameter Cetak</h3>
                    <form method="get" class="mt-5 space-y-4" id="print-params-form">
                        <div>
                            <label class="block text-sm font-medium text-slate-600">Pilih Kelas</label>
                            <div class="mt-2 space-y-2 max-h-60 overflow-y-auto rounded-lg border border-slate-200 p-3">
                                <?php foreach ($classes as $class): ?>
                                    <?php $classId = (int) ($class['id'] ?? 0); ?>
                                    <label class="flex items-center gap-3 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="kelas_ids[]"
                                            value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>"
                                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 class-checkbox"
                                            <?= in_array($classId, $selectedClassIds, true) ? 'checked' : '' ?>
                                            data-kelas-id="<?= $classId ?>"
                                        >
                                        <span><?= htmlspecialchars(($class['tingkat'] ?? '-') . ' • ' . ($class['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="mt-1 text-xs text-slate-400">Centang kelas yang akan dicetak raportnya.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-600">Semester</label>
                            <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                                <i class="ri-information-line mr-1 text-slate-400"></i>
                                <?= htmlspecialchars($semesterOptions[$semester] ?? 'Semester 1 (Ganjil)', ENT_QUOTES, 'UTF-8') ?>
                                <span class="ml-1 text-xs text-slate-400">(semester aktif)</span>
                            </div>
                        </div>

                        <div>
                            <label for="paper" class="block text-sm font-medium text-slate-600">Ukuran Kertas</label>
                            <select
                                id="paper"
                                name="paper"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            >
                                <?php foreach ($paperOptions as $value => $label): ?>
                                    <option
                                        value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $value === $paperSize ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        >
                            Terapkan
                        </button>
                    </form>

                </div>

                    <?php if (!empty($selectedClassIds)): ?>
                        <?php
                            $kelasPrintParams = [
                                'kelas_ids' => implode(',', $selectedClassIds),
                                'semester' => (int) ($semester ?? 1),
                                'paper' => $paperSize,
                            ];
                            $kelasQuery = http_build_query($kelasPrintParams);
                            $kelasBase = htmlspecialchars(rtrim(base_url('raport/cetak/kelas'), '/'), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="mt-4 rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">
                                    <i class="ri-printer-cloud-line text-sm"></i>
                                </span>
                                <h4 class="text-sm font-semibold text-indigo-800">Cetak Kelas Terpilih</h4>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <?php
                                    $classSections = [
                                        ['label' => 'Cover', 'path' => 'cover', 'icon' => 'ri-book-2-line'],
                                        ['label' => 'Informasi Sekolah', 'path' => 'informasi-sekolah', 'icon' => 'ri-school-line'],
                                        ['label' => 'Biodata Siswa', 'path' => 'biodata', 'icon' => 'ri-id-card-line'],
                                        ['label' => 'Hasil Penilaian & Prestasi', 'path' => 'hasil-penilaian-prestasi', 'icon' => 'ri-bar-chart-2-line'],
                                        ['label' => 'Raport Lengkap', 'path' => 'lengkap', 'icon' => 'ri-file-paper-2-line'],
                                    ];
                                ?>
                                <?php foreach ($classSections as $cs): ?>
                                    <?php $csUrl = $kelasBase . '/' . $cs['path'] . '?' . $kelasQuery; ?>
                                    <a href="<?= $csUrl ?>" target="_blank"
                                       class="flex items-center gap-2 rounded-xl border border-indigo-100 bg-white px-3 py-2.5 text-sm font-medium text-indigo-700 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-100 hover:shadow active:scale-[0.98]">
                                        <i class="<?= $cs['icon'] ?> text-base"></i>
                                        <span class="flex-1 truncate"><?= $cs['label'] ?></span>
                                        <i class="ri-printer-line text-xs text-indigo-300"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($canRequestDigitalSignature && !empty($students)): ?>
                                <?php
                                    $pendingSiswa = [];
                                    $missingSiswa = [];
                                    foreach ($students as $s) {
                                        $sid = (int) ($s['id'] ?? 0);
                                        if ($sid <= 0) continue;
                                        if (isset($digitalSignatureRecords[$sid])) {
                                            $st = $digitalSignatureRecords[$sid]['status'] ?? '';
                                            if ($st === 'pending') $pendingSiswa[] = $s['nama'];
                                        } else {
                                            $missingSiswa[] = $s['nama'];
                                        }
                                    }
                                ?>
                                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/70 p-4">
                                    <div class="flex items-center gap-2 mb-3">
                                        <i class="ri-checkbox-circle-line text-sm text-amber-600"></i>
                                        <h5 class="text-sm font-semibold text-amber-800">TTD Digital Raport</h5>
                                        <?php if ($digitalSignatureSummary['total'] > 0 && $digitalSignatureSummary['approved'] === $digitalSignatureSummary['total']): ?>
                                            <span class="ml-auto inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Lengkap</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="grid grid-cols-4 gap-2">
                                        <div class="rounded-lg bg-white/80 px-2 py-1.5 text-center">
                                            <p class="text-[10px] font-semibold text-slate-500">Total</p>
                                            <p class="text-sm font-bold text-slate-700"><?= (int) ($digitalSignatureSummary['total'] ?? 0) ?></p>
                                        </div>
                                        <div class="rounded-lg bg-white/80 px-2 py-1.5 text-center">
                                            <p class="text-[10px] font-semibold text-emerald-600">Disetujui</p>
                                            <p class="text-sm font-bold text-emerald-700"><?= (int) ($digitalSignatureSummary['approved'] ?? 0) ?></p>
                                        </div>
                                        <div class="rounded-lg bg-white/80 px-2 py-1.5 text-center">
                                            <p class="text-[10px] font-semibold text-amber-600">Menunggu</p>
                                            <p class="text-sm font-bold text-amber-700"><?= (int) ($digitalSignatureSummary['pending'] ?? 0) ?></p>
                                        </div>
                                        <div class="rounded-lg bg-white/80 px-2 py-1.5 text-center">
                                            <p class="text-[10px] font-semibold text-slate-500">Belum diajukan</p>
                                            <p class="text-sm font-bold text-slate-700"><?= (int) ($digitalSignatureSummary['not_requested'] ?? 0) ?></p>
                                        </div>
                                    </div>

                                    <?php if (!empty($pendingSiswa) || !empty($missingSiswa)): ?>
                                        <div class="mt-3 text-[9px] leading-relaxed text-slate-400">
                                            <?php if (!empty($pendingSiswa)): ?>
                                                <div>⏳ Menunggu: <?= htmlspecialchars(implode(', ', $pendingSiswa), ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($missingSiswa)): ?>
                                                <div>⬜ Belum diajukan: <?= htmlspecialchars(implode(', ', $missingSiswa), ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($missingSiswa) || !empty($pendingSiswa)): ?>
                                        <form
                                            method="post"
                                            action="<?= htmlspecialchars(base_url('raport/ttd-digital/request-class'), ENT_QUOTES, 'UTF-8') ?>"
                                            class="mt-3"
                                            onsubmit="return confirm('Ajukan TTD digital untuk seluruh siswa di kelas ini?');"
                                        >
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="class_id" value="<?= htmlspecialchars((string) $firstSelectedClassId, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="semester" value="<?= htmlspecialchars((string) ($semester ?? 1), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="document_type" value="<?= htmlspecialchars($digitalSignatureDocumentType ?? 'report_card', ENT_QUOTES, 'UTF-8') ?>">
                                            <button
                                                type="submit"
                                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring focus:ring-indigo-200"
                                            >
                                                <i class="ri-team-line text-sm"></i>
                                                Ajukan TTD Semua Siswa
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($canRequestDigitalSignature && empty($students)): ?>
                                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                                    Tidak ada siswa pada kelas ini.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
            </div>

            <div class="lg:col-span-7">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-800 mb-4">Cari Siswa</h3>
                    <div class="relative">
                        <div class="relative">
                            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input
                                type="text"
                                id="student-search-input"
                                placeholder="Cari berdasarkan nama atau NISN..."
                                class="w-full rounded-lg border border-slate-200 py-2.5 pl-10 pr-4 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                autocomplete="off"
                            >
                            <button
                                type="button"
                                id="student-search-clear"
                                class="absolute right-2 top-1/2 -translate-y-1/2 hidden rounded-full p-1 text-slate-400 hover:text-slate-600"
                            >
                                <i class="ri-close-line"></i>
                            </button>
                        </div>
                        <div id="search-spinner" class="mt-2 hidden text-center text-sm text-slate-400">
                            <i class="ri-loader-4-line animate-spin mr-1"></i> Mencari...
                        </div>
                        <div id="search-results" class="mt-2 max-h-80 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100 hidden"></div>
                        <div id="search-empty" class="mt-2 hidden rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                            <i class="ri-user-search-line text-2xl text-slate-300 mb-1"></i><br>
                            Ketik nama atau NISN untuk mencari siswa.
                        </div>
                    </div>

                    <div id="selected-student-panel" class="mt-6 hidden">
                        <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p id="selected-student-name" class="text-base font-semibold text-slate-800"></p>
                                    <p id="selected-student-info" class="text-sm text-slate-600 mt-0.5"></p>
                                    <p id="selected-student-class" class="text-sm text-slate-500 mt-0.5"></p>
                                </div>
                                <button type="button" id="clear-selected-student" class="rounded-full p-1 text-slate-400 hover:text-red-500">
                                    <i class="ri-close-circle-line text-lg"></i>
                                </button>
                            </div>
                            <input type="hidden" id="selected-siswa-id" value="0">
                            <input type="hidden" id="selected-kelas-id" value="0">
                        </div>
                        <div id="student-signature-section" class="mt-4"></div>
                        <div id="student-print-buttons" class="mt-4 grid gap-3 md:grid-cols-2"></div>
                        <p class="mt-4 text-xs text-slate-400">
                            Setiap tautan akan membuka tab baru dengan tampilan siap cetak. Tekan tombol <em>Cetak / Simpan PDF</em> pada halaman tersebut untuk melanjutkan.
                        </p>
                    </div>

                    <div id="search-info-default" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600">
                        <i class="ri-information-line mr-1 text-slate-400"></i>
                        Cari siswa berdasarkan nama atau NISN menggunakan kolom pencarian di atas. Setelah siswa dipilih, opsi cetak raport akan muncul.
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('student-search-input');
    const searchClear = document.getElementById('student-search-clear');
    const searchSpinner = document.getElementById('search-spinner');
    const searchResults = document.getElementById('search-results');
    const searchEmpty = document.getElementById('search-empty');
    const searchInfoDefault = document.getElementById('search-info-default');
    const selectedPanel = document.getElementById('selected-student-panel');
    const selectedName = document.getElementById('selected-student-name');
    const selectedInfo = document.getElementById('selected-student-info');
    const selectedClass = document.getElementById('selected-student-class');
    const selectedSiswaId = document.getElementById('selected-siswa-id');
    const selectedKelasId = document.getElementById('selected-kelas-id');
    const clearSelected = document.getElementById('clear-selected-student');
    const studentSignatureSection = document.getElementById('student-signature-section');
    const studentPrintButtons = document.getElementById('student-print-buttons');

    const paperSize = '<?= htmlspecialchars($paperSize, ENT_QUOTES, 'UTF-8') ?>';
    const semester = <?= (int) ($semester ?? 1) ?>;

    let searchTimeout = null;
    let currentSearchTerm = '';

    searchInput.addEventListener('input', function () {
        const term = this.value.trim();
        currentSearchTerm = term;

        if (term.length === 0) {
            searchResults.classList.add('hidden');
            searchEmpty.classList.add('hidden');
            searchClear.classList.add('hidden');
            return;
        }

        searchClear.classList.remove('hidden');

        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        searchTimeout = setTimeout(function () {
            performSearch(term);
        }, 300);
    });

    searchClear.addEventListener('click', function () {
        searchInput.value = '';
        searchInput.focus();
        searchResults.classList.add('hidden');
        searchEmpty.classList.add('hidden');
        searchClear.classList.add('hidden');
        currentSearchTerm = '';
    });

    clearSelected.addEventListener('click', function () {
        hideSelectedStudent();
    });

    function performSearch(term) {
        if (term !== currentSearchTerm) return;

        searchSpinner.classList.remove('hidden');
        searchResults.classList.add('hidden');
        searchEmpty.classList.add('hidden');

        fetch('<?= htmlspecialchars(base_url('raport/cari-siswa'), ENT_QUOTES, 'UTF-8') ?>?q=' + encodeURIComponent(term), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (res) { return res.json(); })
        .then(function (response) {
            searchSpinner.classList.add('hidden');

            if (term !== currentSearchTerm) return;

            var data = response.data || [];
            if (data.length === 0) {
                searchResults.classList.add('hidden');
                searchEmpty.classList.remove('hidden');
                searchEmpty.innerHTML = '<i class="ri-user-search-line text-2xl text-slate-300 mb-1"></i><br>Tidak ditemukan siswa dengan kata kunci "<strong>' + htmlEntities(term) + '</strong>".';
                return;
            }

            searchEmpty.classList.add('hidden');
            searchResults.classList.remove('hidden');
            searchResults.innerHTML = '';

            data.forEach(function (student) {
                var item = document.createElement('div');
                item.className = 'search-result-item flex items-center justify-between px-4 py-3 cursor-pointer transition hover:bg-indigo-50';
                item.setAttribute('data-siswa-id', student.id);
                item.setAttribute('data-kelas-id', student.kelas_id);
                item.setAttribute('data-nama', student.nama);
                item.setAttribute('data-nisn', student.nisn);
                item.setAttribute('data-nipd', student.nipd);
                item.setAttribute('data-kelas', student.kelas_nama);
                item.setAttribute('data-signature-status', student.signature_status || '');
                item.setAttribute('data-signature-label', student.signature_label || '');

                var infoHtml = '<div class="flex-1 min-w-0">' +
                    '<p class="text-sm font-semibold text-slate-800 truncate">' + htmlEntities(student.nama) + '</p>' +
                    '<p class="text-xs text-slate-500">NISN: ' + htmlEntities(student.nisn) + ' / NIPD: ' + htmlEntities(student.nipd) + ' • ' + htmlEntities(student.kelas_nama) + '</p>' +
                    '</div>' +
                    '<i class="ri-arrow-right-s-line text-lg text-slate-300 flex-shrink-0"></i>';

                item.innerHTML = infoHtml;
                item.addEventListener('click', function () {
                    selectStudent(
                        parseInt(this.getAttribute('data-siswa-id')),
                        parseInt(this.getAttribute('data-kelas-id')),
                        this.getAttribute('data-nama'),
                        this.getAttribute('data-nisn'),
                        this.getAttribute('data-nipd'),
                        this.getAttribute('data-kelas'),
                        this.getAttribute('data-signature-status'),
                        this.getAttribute('data-signature-label')
                    );
                });

                searchResults.appendChild(item);
            });
        })
        .catch(function () {
            searchSpinner.classList.add('hidden');
        });
    }

    function selectStudent(siswaId, kelasId, nama, nisn, nipd, kelasNama, signatureStatus, signatureLabel) {
        selectedSiswaId.value = siswaId;
        selectedKelasId.value = kelasId;
        selectedName.textContent = nama;
        selectedInfo.textContent = 'NISN: ' + (nisn || '-') + ' / NIPD: ' + (nipd || '-');
        selectedClass.textContent = 'Kelas: ' + (kelasNama || '-') + ' • Semester: ' + (semester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)') + ' • ' + '<?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?>';

        selectedPanel.classList.remove('hidden');
        searchInfoDefault.classList.add('hidden');

        var printQuery = 'siswa_id=' + siswaId + '&kelas_id=' + kelasId + '&semester=' + semester + '&paper=' + paperSize;
        var printBase = '<?= htmlspecialchars(rtrim(base_url('raport/cetak'), '/'), ENT_QUOTES, 'UTF-8') ?>';

        var sections = [
            { label: 'Cover', path: 'cover', icon: 'ri-book-2-line' },
            { label: 'Informasi Sekolah', path: 'informasi-sekolah', icon: 'ri-school-line' },
            { label: 'Biodata Siswa', path: 'biodata', icon: 'ri-id-card-line' },
            { label: 'Hasil Penilaian & Prestasi', path: 'hasil-penilaian-prestasi', icon: 'ri-bar-chart-2-line' },
            { label: 'Raport Lengkap', path: 'lengkap', icon: 'ri-file-paper-2-line' },
        ];

        studentPrintButtons.innerHTML = '';
        sections.forEach(function (sec) {
            var url = printBase + '/' + sec.path + '?' + printQuery;
            var link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            link.className = 'flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50';
            link.innerHTML = '<span class="inline-flex items-center gap-2"><i class="' + sec.icon + ' text-lg text-indigo-500"></i>' + sec.label + '</span><i class="ri-printer-line text-lg text-slate-400"></i>';
            studentPrintButtons.appendChild(link);
        });

        // Render TTD Digital section
        studentSignatureSection.innerHTML = '';
        var canShowTtd = <?= $canRequestDigitalSignature ? 'true' : 'false' ?>;
        if (canShowTtd && kelasId > 0 && siswaId > 0) {
            var statusColors = {
                'approved': 'bg-emerald-100 text-emerald-700',
                'pending': 'bg-amber-100 text-amber-700',
                'revoked': 'bg-rose-100 text-rose-700',
            };
            var defaultColor = 'bg-slate-100 text-slate-600';
            var isSubmitted = signatureStatus && signatureStatus !== 'missing' && signatureStatus !== 'null';
            var statusColor = statusColors[signatureStatus] || defaultColor;
            var label = signatureLabel || 'Belum Diajukan';

            var ttdHtml = '<div class="rounded-xl border border-slate-200 bg-slate-50 p-4">' +
                '<div class="flex items-start justify-between gap-3">' +
                '<div>' +
                '<h5 class="text-sm font-semibold text-slate-700">TTD Digital Raport</h5>' +
                '<p class="mt-0.5 text-xs text-slate-400">Ajukan tanda tangan digital ke kepala sekolah</p>' +
                '</div>' +
                '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold ' + statusColor + '">' + htmlEntities(label) + '</span>' +
                '</div>';

            if (isSubmitted && signatureStatus === 'approved') {
                ttdHtml += '<div class="mt-3 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">TTD digital telah disetujui kepala sekolah.</div>';
            } else if (isSubmitted && signatureStatus === 'pending') {
                ttdHtml += '<div class="mt-3 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-700">Menunggu persetujuan kepala sekolah.</div>';
            }

            if (!isSubmitted || signatureStatus === 'revoked' || signatureStatus === 'missing') {
                ttdHtml +=
                    '<form method="post" action="<?= htmlspecialchars(base_url('raport/ttd-digital/request'), ENT_QUOTES, 'UTF-8') ?>" class="mt-3" onsubmit="return confirm(\'Ajukan TTD digital untuk siswa ini?\');">' +
                    '<?= csrf_field() ?>' +
                    '<input type="hidden" name="student_id" value="' + siswaId + '">' +
                    '<input type="hidden" name="class_id" value="' + kelasId + '">' +
                    '<input type="hidden" name="semester" value="' + semester + '">' +
                    '<input type="hidden" name="document_type" value="<?= htmlspecialchars($digitalSignatureDocumentType ?? 'report_card', ENT_QUOTES, 'UTF-8') ?>">' +
                    '<button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring focus:ring-indigo-200">' +
                    '<i class="ri-checkbox-circle-line text-sm"></i> Ajukan TTD Digital' +
                    '</button>' +
                    '</form>';
            } else if (signatureStatus === 'pending') {
                ttdHtml += '<div class="mt-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-400 text-center">Menunggu persetujuan kepala sekolah</div>';
            }

            ttdHtml += '</div>';
            studentSignatureSection.innerHTML = ttdHtml;
        }

        searchInput.value = '';
        searchResults.classList.add('hidden');
        searchEmpty.classList.add('hidden');
        searchClear.classList.add('hidden');
        currentSearchTerm = '';
    }

    function hideSelectedStudent() {
        selectedPanel.classList.add('hidden');
        searchInfoDefault.classList.remove('hidden');
        selectedSiswaId.value = '0';
        selectedKelasId.value = '0';
        studentSignatureSection.innerHTML = '';
        studentPrintButtons.innerHTML = '';
    }

    function htmlEntities(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
</script>

<style>
    .search-result-item:last-child {
        border-bottom: none;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    .animate-spin {
        animation: spin 1s linear infinite;
        display: inline-block;
    }
</style>

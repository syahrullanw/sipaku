<?php
    $student = $student ?? [];
    $history = $academicHistory ?? [
        'promotions' => [],
        'graduations' => [],
        'achievements' => [],
        'extracurriculars' => [],
        'attendance' => [],
        'attitudes' => [],
        'notes' => [],
        'prakerin' => [],
        'subjects' => [],
    ];
    $schoolProfile = $schoolProfile ?? [];
    $valueOrDash = static function (mixed $value): string {
        if ($value === null) {
            return '-';
        }

        $string = trim((string) $value);

        return $string === '' ? '-' : $string;
    };
    $buildAddress = static function (array $student) use ($valueOrDash): string {
        $segments = [];
        $address = trim((string) ($student['alamat'] ?? ''));
        if ($address !== '') {
            $segments[] = $address;
        }

        $rt = $valueOrDash($student['rt'] ?? null);
        $rw = $valueOrDash($student['rw'] ?? null);
        if ($rt !== '-' || $rw !== '-') {
            $segments[] = sprintf('RT %s / RW %s', $rt, $rw);
        }

        $dusun = $valueOrDash($student['dusun'] ?? null);
        if ($dusun !== '-') {
            $segments[] = 'Dusun: ' . $dusun;
        }

        $kelurahan = $valueOrDash($student['kelurahan'] ?? null);
        if ($kelurahan !== '-') {
            $segments[] = 'Kelurahan: ' . $kelurahan;
        }

        $kecamatan = $valueOrDash($student['kecamatan'] ?? null);
        if ($kecamatan !== '-') {
            $segments[] = 'Kecamatan: ' . $kecamatan;
        }

        $postal = $valueOrDash($student['kode_pos'] ?? null);
        if ($postal !== '-') {
            $segments[] = 'Kode Pos: ' . $postal;
        }

        return implode(', ', $segments);
    };
    $parentData = static function (array $student, string $prefix) use ($valueOrDash): array {
        return [
            'Nama' => $student[$prefix . '_nama'] ?? null,
            'Tahun Lahir' => $student[$prefix . '_tahun_lahir'] ?? null,
            'Pendidikan' => $student[$prefix . '_jenjang_pendidikan'] ?? null,
            'Pekerjaan' => $student[$prefix . '_pekerjaan'] ?? null,
            'Penghasilan' => $student[$prefix . '_penghasilan'] ?? null,
            'NIK' => $student[$prefix . '_nik'] ?? null,
        ];
    };
    $genderMap = [
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
    ];
    $printIdentityRows = [
        ['label' => 'Nama Lengkap', 'value' => $student['nama'] ?? null],
        ['label' => 'NIPD', 'value' => $student['nipd'] ?? null],
        ['label' => 'NISN', 'value' => $student['nisn'] ?? null],
        ['label' => 'NIK', 'value' => $student['nik'] ?? null],
        ['label' => 'Nomor KK', 'value' => $student['nomor_kk'] ?? null],
        ['label' => 'Tempat/Tanggal Lahir', 'value' => trim(($student['tempat_lahir'] ?? '') . ' / ' . $valueOrDash($student['tanggal_lahir'] ?? null), ' / ')],
        ['label' => 'Jenis Kelamin', 'value' => $genderMap[$student['jenis_kelamin'] ?? ''] ?? '-'],
        ['label' => 'Agama', 'value' => $student['agama'] ?? null],
        ['label' => 'Alamat Lengkap', 'value' => $buildAddress($student)],
        ['label' => 'Telepon', 'value' => $student['telepon'] ?? null],
        ['label' => 'HP', 'value' => $student['hp'] ?? null],
        ['label' => 'Email', 'value' => $student['email'] ?? null],
        ['label' => 'Jenis Tinggal', 'value' => $student['jenis_tinggal'] ?? null],
        ['label' => 'Alat Transportasi', 'value' => $student['alat_transportasi'] ?? null],
    ];
    $parentSections = [
        'DATA AYAH' => $parentData($student, 'ayah'),
        'DATA IBU' => $parentData($student, 'ibu'),
        'DATA WALI' => $parentData($student, 'wali'),
    ];
    $autoPrint = (bool) ($autoPrint ?? false);
    $studentPromotions = $history['promotions'][$student['id'] ?? -1] ?? [];
    $studentGraduations = $history['graduations'][$student['id'] ?? -1] ?? [];
    $studentAttendance = $history['attendance'][$student['id'] ?? -1] ?? [];
    $studentAchievements = $history['achievements'][$student['id'] ?? -1] ?? [];
    $studentExtracurriculars = $history['extracurriculars'][$student['id'] ?? -1] ?? [];
    $studentAttitudes = $history['attitudes'][$student['id'] ?? -1] ?? [];
    $studentNotes = $history['notes'][$student['id'] ?? -1] ?? [];
    $studentPrakerin = $history['prakerin'][$student['id'] ?? -1] ?? [];
    $studentSubjectHistory = $history['subjects'][$student['id'] ?? -1] ?? [];
    $subjectHistoryEntries = array_values($studentSubjectHistory);
    if (!empty($subjectHistoryEntries)) {
        usort(
            $subjectHistoryEntries,
            static function (array $a, array $b): int {
                $left = $a['sort_key'] ?? null;
                $right = $b['sort_key'] ?? null;
                if ($left === $right) {
                    return 0;
                }
                if ($left === null) {
                    return 1;
                }
                if ($right === null) {
                    return -1;
                }

                return strcmp((string) $left, (string) $right);
            }
        );
    }
    $photoPath = trim((string) ($student['foto_path'] ?? ''));
    $photoUrl = $photoPath !== '' ? asset($photoPath) : null;
    $letterheadPath = (string) ($schoolProfile['kop_surat'] ?? '');
    $letterheadUrl = $letterheadPath !== '' ? asset($letterheadPath) : null;
?>

<div class="print-container">
    <div class="print-actions">
        <div class="paper-size-label">
            Buku Induk Siswa · <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
        </div>
        <button type="button" onclick="window.print()">
            Cetak / Simpan PDF
        </button>
    </div>
    <div class="print-content">
        <?php if ($letterheadUrl !== null): ?>
            <div style="margin-bottom: 12px;">
                <img src="<?= htmlspecialchars($letterheadUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Kop Sekolah" style="width: 100%; height: auto;" />
            </div>
        <?php endif; ?>
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
            <div style="width:90px;height:120px;border:1px solid #cbd5f5;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#f8fafc;">
                <?php if ($photoUrl !== null): ?>
                    <img
                        src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>"
                        alt="Foto <?= htmlspecialchars($student['nama'] ?? 'Siswa', ENT_QUOTES, 'UTF-8') ?>"
                        style="width:100%;height:100%;object-fit:cover;"
                    />
                <?php else: ?>
                    <span style="font-size:10pt;color:#94a3b8;text-align:center;padding:4px;">Foto belum tersedia</span>
                <?php endif; ?>
            </div>
            <div style="flex:1;text-align:center;">
                <h1 style="margin-bottom:8px;text-transform:uppercase;">Buku Induk Siswa</h1>
                <h2 style="margin:0;font-size:16pt;">
                    <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                    <?= student_status_badge($student, 'ml-1 align-middle') ?>
                    <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                </h2>
            </div>
        </div>

        <h3 style="margin-bottom:8px;text-transform:uppercase;font-size:12pt;">Data Diri</h3>
        <table style="margin-bottom:18px;">
            <?php foreach ($printIdentityRows as $row): ?>
                <tr>
                    <td style="width:35%;font-weight:bold;"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($valueOrDash($row['value']), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <?php foreach ($parentSections as $heading => $fields): ?>
            <h3 style="margin-bottom:8px;text-transform:uppercase;font-size:12pt;"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h3>
            <table style="margin-bottom:18px;">
                <?php foreach ($fields as $label => $fieldValue): ?>
                    <tr>
                        <td style="width:35%;font-weight:bold;"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($valueOrDash($fieldValue), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endforeach; ?>

        <h3 style="margin-bottom:8px;text-transform:uppercase;font-size:12pt;">Riwayat Naik Kelas</h3>
        <?php if (!empty($studentPromotions)): ?>
            <table style="margin-bottom:18px;">
                <thead>
                    <tr>
                        <th>Tahun Ajaran</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentPromotions as $promotion): ?>
                        <tr>
                            <td><?= htmlspecialchars($valueOrDash($promotion['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($promotion['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($promotion['status'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($promotion['note'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="margin-bottom:18px;">Belum ada data status naik kelas.</p>
        <?php endif; ?>

        <h3 style="margin-bottom:8px;text-transform:uppercase;font-size:12pt;">Riwayat Kelulusan</h3>
        <?php if (!empty($studentGraduations)): ?>
            <table style="margin-bottom:18px;">
                <thead>
                    <tr>
                        <th>Tahun Ajaran</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentGraduations as $graduation): ?>
                        <tr>
                            <td><?= htmlspecialchars($valueOrDash($graduation['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($graduation['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($graduation['status'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($graduation['note'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="margin-bottom:18px;">Belum ada data kelulusan.</p>
        <?php endif; ?>

        <h3 style="margin-bottom:8px;text-transform:uppercase;font-size:12pt;">Riwayat Presensi</h3>
        <?php if (!empty($studentAttendance)): ?>
            <table style="margin-bottom:18px;">
                <thead>
                    <tr>
                        <th>Tahun Ajaran</th>
                        <th>Kelas</th>
                        <th>Sakit</th>
                        <th>Izin</th>
                        <th>Bolos</th>
                        <th>Alpa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentAttendance as $record): ?>
                        <tr>
                            <td><?= htmlspecialchars($valueOrDash($record['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($record['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($record['sick'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($record['permit'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($record['truant'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($record['absent'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="margin-bottom:18px;">Belum ada data presensi.</p>
        <?php endif; ?>

        <h3 style="margin-bottom:8px;text-transform:uppercase;font-size:12pt;">Nilai Sikap</h3>
        <?php if (!empty($studentAttitudes)): ?>
            <table style="margin-bottom:18px;">
                <thead>
                    <tr>
                        <th>Tahun Ajaran</th>
                        <th>Jenis</th>
                        <th>Menonjol</th>
                        <th>Perlu Peningkatan</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentAttitudes as $attitude): ?>
                        <tr>
                            <td><?= htmlspecialchars($valueOrDash($attitude['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(ucwords($attitude['type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(empty($attitude['always']) ? '-' : implode(', ', $attitude['always']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($attitude['improving'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($attitude['note'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="margin-bottom:18px;">Belum ada penilaian sikap.</p>
        <?php endif; ?>

        <h3 style="margin-bottom:8px;text-transform:uppercase;font-size:12pt;">Prestasi Siswa</h3>
        <?php if (!empty($studentAchievements)): ?>
            <table style="margin-bottom:18px;">
                <thead>
                    <tr>
                        <th>Tahun Ajaran</th>
                        <th>Kelas</th>
                        <th>Jenis</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentAchievements as $achievement): ?>
                        <tr>
                            <td><?= htmlspecialchars($valueOrDash($achievement['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($achievement['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($achievement['type'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($achievement['description'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="margin-bottom:18px;">Belum ada data prestasi.</p>
        <?php endif; ?>

        <h3 style="margin-bottom:8px;text-transform:uppercase;font-size:12pt;">Ekskul & Pengembangan Diri</h3>
        <?php if (!empty($studentExtracurriculars)): ?>
            <table style="margin-bottom:18px;">
                <thead>
                    <tr>
                        <th>Tahun Ajaran</th>
                        <th>Ekskul</th>
                        <th>Nilai Akhir</th>
                        <th>Predikat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentExtracurriculars as $extracurricular): ?>
                        <tr>
                            <td><?= htmlspecialchars($valueOrDash($extracurricular['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($extracurricular['activity_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash(isset($extracurricular['scores']['final']) ? number_format((float) $extracurricular['scores']['final'], 2) : null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($extracurricular['predicate'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="margin-bottom:18px;">Belum ada nilai ekskul.</p>
        <?php endif; ?>

        <h3 style="margin-bottom:8px;text-transform:uppercase;font-size:12pt;">Catatan Wali Kelas</h3>
        <?php if (!empty($studentNotes)): ?>
            <table style="margin-bottom:18px;">
                <thead>
                    <tr>
                        <th>Tahun Ajaran</th>
                        <th>Kelas</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentNotes as $note): ?>
                        <tr>
                            <td><?= htmlspecialchars($valueOrDash($note['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($note['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($note['note'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="margin-bottom:18px;">Belum ada catatan wali kelas.</p>
        <?php endif; ?>

        <h3 style="margin-bottom:8px;text-transform:uppercase;font-size:12pt;">Riwayat Prakerin</h3>
        <?php if (!empty($studentPrakerin)): ?>
            <table style="margin-bottom:18px;">
                <thead>
                    <tr>
                        <th>Tahun Ajaran</th>
                        <th>Tempat</th>
                        <th>Nilai Akhir</th>
                        <th>Predikat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentPrakerin as $prakerin): ?>
                        <tr>
                            <td><?= htmlspecialchars($valueOrDash($prakerin['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($prakerin['place_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash(isset($prakerin['scores']['final']) ? number_format((float) $prakerin['scores']['final'], 2) : null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($valueOrDash($prakerin['scores']['predicate'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="margin-bottom:18px;">Belum ada riwayat prakerin.</p>
        <?php endif; ?>

        <h3 style="margin-bottom:8px;text-transform:uppercase;font-size:12pt;">Nilai Mapel per Semester</h3>
        <?php if (!empty($subjectHistoryEntries)): ?>
            <?php foreach ($subjectHistoryEntries as $historyEntry): ?>
                <div style="margin-bottom:18px;">
                    <p style="font-weight:bold;">
                        <?= htmlspecialchars($valueOrDash($historyEntry['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                        · Semester <?= htmlspecialchars(((int) ($historyEntry['semester'] ?? 1)) === 2 ? 'Genap' : 'Ganjil', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <?php
                        $subjects = isset($historyEntry['subjects']) && is_array($historyEntry['subjects']) ? $historyEntry['subjects'] : [];
                        $kurmerSubjects = array_values(array_filter($subjects, static fn ($subject) => is_array($subject) && strtolower((string) ($subject['curriculum'] ?? '')) === 'kurmer'));
                        $k13Subjects = array_values(array_filter($subjects, static fn ($subject) => !is_array($subject) ? false : strtolower((string) ($subject['curriculum'] ?? '')) !== 'kurmer'));
                        $kurmerLevelLabels = [
                            'BB' => 'Belum Berkembang',
                            'MB' => 'Mulai Berkembang',
                            'BSH' => 'Berkembang Sesuai Harapan',
                            'SB' => 'Sangat Berkembang',
                        ];
                    ?>
                    <?php if (!empty($k13Subjects)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Mata Pelajaran</th>
                                    <th>Kelas</th>
                                    <th>Pengetahuan</th>
                                    <th>Keterampilan</th>
                                    <th>Rata-rata</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($k13Subjects as $subject): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($subject['name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($valueOrDash($subject['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?= htmlspecialchars($subject['knowledge_score'] !== null ? number_format((float) $subject['knowledge_score'], 2) : '-', ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($subject['knowledge_predicate'])): ?>
                                                (<?= htmlspecialchars($subject['knowledge_predicate'], ENT_QUOTES, 'UTF-8') ?>)
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($subject['skill_score'] !== null ? number_format((float) $subject['skill_score'], 2) : '-', ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($subject['skill_predicate'])): ?>
                                                (<?= htmlspecialchars($subject['skill_predicate'], ENT_QUOTES, 'UTF-8') ?>)
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($subject['average_score'] !== null ? number_format((float) $subject['average_score'], 2) : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <?php if (!empty($kurmerSubjects)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Mata Pelajaran (KurMer)</th>
                                    <th>Kelas</th>
                                    <th>Capaian</th>
                                    <th>Nilai Opsional</th>
                                    <th>Narasi / Tindak Lanjut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kurmerSubjects as $subject): ?>
                                    <?php
                                        $capaianCode = strtoupper(trim((string) ($subject['kurmer_capaian'] ?? '')));
                                        $capaianLabel = $capaianCode !== '' ? ($kurmerLevelLabels[$capaianCode] ?? $capaianCode) : '-';
                                        $kurmerScore = isset($subject['kurmer_score']) ? number_format((float) $subject['kurmer_score'], 2) : '-';
                                        $description = trim((string) ($subject['kurmer_description'] ?? ''));
                                        $tindakLanjut = trim((string) ($subject['kurmer_tindak_lanjut'] ?? ''));
                                        $tpSourcesRaw = $subject['kurmer_tp_sources'] ?? null;
                                        if (is_string($tpSourcesRaw)) {
                                            $decoded = json_decode($tpSourcesRaw, true);
                                            $tpSourcesRaw = is_array($decoded) ? $decoded : [];
                                        }
                                        $tpSources = is_array($tpSourcesRaw) ? $tpSourcesRaw : [];
                                        $tpSummary = '';
                                        if (!empty($tpSources)) {
                                            $tpParts = [];
                                            $used = 0;
                                            foreach (array_slice($tpSources, 0, 2) as $tp) {
                                                $used++;
                                                $code = trim((string) ($tp['kode_tp'] ?? $tp['kode'] ?? ''));
                                                $tpDesc = trim((string) ($tp['deskripsi'] ?? $tp['description'] ?? $tp['tujuan'] ?? ''));
                                                $label = $code !== '' ? $code : 'TP';
                                                $tpParts[] = $tpDesc !== '' ? ($label . ' - ' . $tpDesc) : $label;
                                            }
                                            $remaining = count($tpSources) - $used;
                                            if ($remaining > 0) {
                                                $tpParts[] = $remaining . ' TP lain';
                                            }
                                            $tpSummary = implode('; ', array_filter($tpParts));
                                        }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($subject['name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($valueOrDash($subject['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?= htmlspecialchars($capaianCode !== '' ? $capaianCode : '-', ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($capaianLabel !== '' && $capaianLabel !== $capaianCode): ?>
                                                (<?= htmlspecialchars($capaianLabel, ENT_QUOTES, 'UTF-8') ?>)
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($kurmerScore, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if ($description !== ''): ?>
                                                <div><?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) ?></div>
                                            <?php endif; ?>
                                            <?php if ($tindakLanjut !== ''): ?>
                                                <div>Tindak lanjut: <?= nl2br(htmlspecialchars($tindakLanjut, ENT_QUOTES, 'UTF-8')) ?></div>
                                            <?php endif; ?>
                                            <?php if ($tpSummary !== ''): ?>
                                                <div style="font-size:9pt;color:#475569;">TP: <?= htmlspecialchars($tpSummary, ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                            <?php if ($description === '' && $tindakLanjut === '' && $tpSummary === ''): ?>
                                                <span>-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <?php if (empty($k13Subjects) && empty($kurmerSubjects)): ?>
                        <p>Belum ada data nilai mapel.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="margin-bottom:18px;">Belum ada data nilai mapel per semester.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($autoPrint): ?>
    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
<?php endif; ?>

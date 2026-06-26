<?php
    $schoolProfile = is_array($schoolProfile ?? null) ? $schoolProfile : [];
    $evaluation = is_array($evaluation ?? null) ? $evaluation : null;
    $criteria = $evaluation !== null && is_array($evaluation['criteria'] ?? null) ? $evaluation['criteria'] : [];
    $subjects = $evaluation !== null && is_array($evaluation['subjects'] ?? null) ? $evaluation['subjects'] : [];
    $student = $evaluation !== null && is_array($evaluation['student'] ?? null) ? $evaluation['student'] : [];
    $class = $evaluation !== null && is_array($evaluation['class'] ?? null) ? $evaluation['class'] : [];
    $schoolYear = $evaluation !== null && is_array($evaluation['school_year'] ?? null) ? $evaluation['school_year'] : [];
    $signatureRecord = $evaluation !== null && is_array($evaluation['signature_record'] ?? null) ? $evaluation['signature_record'] : null;
    $canPrint = $evaluation !== null && (bool) ($evaluation['can_print'] ?? false);
    $token = $signatureRecord !== null ? trim((string) ($signatureRecord['signature_token'] ?? '')) : '';
    $printUrl = $canPrint && $token !== '' ? base_url('kelulusan/cetak/' . rawurlencode($token)) : null;
    $verificationUrl = $canPrint && $token !== '' ? absolute_url('dokumen/validasi/' . $token) : null;
    $lookupError = $lookupError ?? null;
    $identifier = (string) ($identifier ?? '');
    $birthDate = (string) ($birthDate ?? '');
    $schoolName = trim((string) ($schoolProfile['nama'] ?? config('app.name', 'Sekolah')));
    $average = $evaluation['average'] ?? null;

    $formatScore = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        $number = round((float) $value, 2);
        if (abs($number - round($number)) < 0.01) {
            return number_format($number, 0, ',', '.');
        }
        return rtrim(rtrim(number_format($number, 2, ',', '.'), '0'), ',');
    };

    $allBlockingMessages = [];
    if ($evaluation !== null) {
        foreach (($evaluation['context_issues'] ?? []) as $issue) {
            $issue = trim((string) $issue);
            if ($issue !== '') {
                $allBlockingMessages[] = $issue;
            }
        }
        foreach ($criteria as $criterion) {
            if (!is_array($criterion) || (bool) ($criterion['passed'] ?? false)) {
                continue;
            }
            $message = trim((string) ($criterion['message'] ?? ''));
            if ($message !== '') {
                $allBlockingMessages[] = $message;
            }
        }
        $allBlockingMessages = array_values(array_unique($allBlockingMessages));
    }
?>

<style>
    .graduation-public {
        min-height: calc(100vh - 48px);
        color: #0f172a;
    }

    .graduation-shell {
        width: min(1120px, calc(100vw - 32px));
        margin: 0 auto;
        padding: 28px 0 48px;
    }

    .graduation-header {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(300px, .8fr);
        gap: 18px;
        align-items: stretch;
    }

    .graduation-panel,
    .graduation-result,
    .graduation-section {
        background: #ffffff;
        border: 1px solid #dbe3ef;
        border-radius: 18px;
        box-shadow: 0 20px 50px -32px rgba(15, 23, 42, .4);
    }

    .graduation-panel {
        padding: 28px;
    }

    .graduation-kicker {
        margin: 0 0 8px;
        color: #2563eb;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .graduation-title {
        margin: 0;
        font-size: clamp(28px, 4vw, 42px);
        line-height: 1.08;
        letter-spacing: 0;
    }

    .graduation-copy {
        margin: 14px 0 0;
        max-width: 720px;
        color: #475569;
        font-size: 15px;
        line-height: 1.7;
    }

    .graduation-form {
        display: grid;
        gap: 14px;
        margin-top: 22px;
    }

    .field-label {
        display: block;
        margin-bottom: 7px;
        color: #334155;
        font-size: 13px;
        font-weight: 700;
    }

    .field-control {
        box-sizing: border-box;
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 12px 14px;
        color: #0f172a;
        font: inherit;
        background: #ffffff;
    }

    .field-control:focus {
        border-color: #2563eb;
        outline: 3px solid rgba(37, 99, 235, .14);
    }

    .graduation-submit,
    .graduation-print,
    .graduation-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 44px;
        border-radius: 12px;
        border: 0;
        padding: 0 18px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
    }

    .graduation-submit,
    .graduation-print {
        background: #2563eb;
        color: #ffffff;
    }

    .graduation-link {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }

    .graduation-result {
        padding: 22px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 18px;
    }

    .status-pill {
        display: inline-flex;
        width: fit-content;
        align-items: center;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 800;
    }

    .status-ok {
        background: #dcfce7;
        color: #166534;
    }

    .status-wait {
        background: #fef3c7;
        color: #92400e;
    }

    .status-blocked {
        background: #fee2e2;
        color: #991b1b;
    }

    .student-name {
        margin: 10px 0 0;
        font-size: 22px;
        font-weight: 800;
    }

    .student-meta,
    .muted {
        color: #64748b;
        font-size: 13px;
        line-height: 1.6;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-top: 16px;
    }

    .summary-item {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px;
        background: #f8fafc;
    }

    .summary-item dt {
        margin: 0;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .summary-item dd {
        margin: 5px 0 0;
        font-size: 20px;
        font-weight: 800;
    }

    .graduation-section {
        margin-top: 18px;
        padding: 22px;
    }

    .section-title {
        margin: 0;
        font-size: 18px;
        font-weight: 800;
    }

    .criteria-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-top: 16px;
    }

    .criterion {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 14px;
        background: #ffffff;
    }

    .criterion-ok {
        border-color: #bbf7d0;
        background: #f0fdf4;
    }

    .criterion-wait {
        border-color: #fed7aa;
        background: #fff7ed;
    }

    .criterion strong {
        display: block;
        font-size: 13px;
    }

    .criterion span {
        display: block;
        margin-top: 6px;
        color: #64748b;
        font-size: 12px;
        line-height: 1.55;
    }

    .notice-list {
        margin: 14px 0 0;
        padding-left: 18px;
        color: #7f1d1d;
        font-size: 13px;
        line-height: 1.65;
    }

    .subject-table-wrap {
        margin-top: 16px;
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
    }

    .subject-table {
        width: 100%;
        min-width: 720px;
        border-collapse: collapse;
        font-size: 13px;
    }

    .subject-table th,
    .subject-table td {
        padding: 12px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
        vertical-align: top;
    }

    .subject-table th {
        background: #f8fafc;
        color: #475569;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .subject-table tr:last-child td {
        border-bottom: 0;
    }

    .score-bad {
        color: #b91c1c;
        font-weight: 800;
    }

    .score-good {
        color: #166534;
        font-weight: 800;
    }

    .empty-note {
        margin: 18px 0 0;
        border: 1px dashed #cbd5e1;
        border-radius: 16px;
        padding: 20px;
        background: #ffffff;
        color: #64748b;
        font-size: 14px;
        line-height: 1.6;
    }

    @media (max-width: 900px) {
        .graduation-header,
        .criteria-grid {
            grid-template-columns: 1fr;
        }

        .summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="graduation-public">
    <div class="graduation-shell">
        <div class="graduation-header">
            <section class="graduation-panel">
                <p class="graduation-kicker">Portal Kelulusan</p>
                <h1 class="graduation-title">Cek dan cetak Surat Keterangan Lulus</h1>
                <p class="graduation-copy">
                    <?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?> menyediakan akses mandiri untuk siswa tingkat akhir.
                    SKL hanya bisa dicetak jika nilai tuntas, wali kelas menetapkan lulus, pengajuan TTD digital tersedia, dan kepala sekolah sudah menyetujui.
                </p>

                <form class="graduation-form" method="get" action="<?= htmlspecialchars(base_url('kelulusan'), ENT_QUOTES, 'UTF-8') ?>">
                    <div>
                        <label class="field-label" for="nisn">NISN atau NIPD</label>
                        <input
                            class="field-control"
                            id="nisn"
                            name="nisn"
                            value="<?= htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') ?>"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="Masukkan NISN atau NIPD"
                        >
                    </div>
                    <div>
                        <label class="field-label" for="tanggal_lahir">Tanggal lahir</label>
                        <input
                            class="field-control"
                            id="tanggal_lahir"
                            name="tanggal_lahir"
                            type="date"
                            value="<?= htmlspecialchars($birthDate, ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>
                    <button class="graduation-submit" type="submit">Cek Status Kelulusan</button>
                </form>

                <?php if ($lookupError !== null): ?>
                    <ul class="notice-list">
                        <li><?= htmlspecialchars($lookupError, ENT_QUOTES, 'UTF-8') ?></li>
                    </ul>
                <?php endif; ?>
            </section>

            <aside class="graduation-result">
                <?php if ($evaluation === null): ?>
                    <div>
                        <span class="status-pill status-wait">Menunggu pencarian</span>
                        <p class="student-name">Status SKL</p>
                        <p class="student-meta">Masukkan identitas siswa untuk melihat keterangan kelulusan dan akses cetak.</p>
                    </div>
                <?php else: ?>
                    <div>
                        <span class="status-pill <?= $canPrint ? 'status-ok' : 'status-blocked' ?>">
                            <?= $canPrint ? 'Siap dicetak' : 'Belum dapat dicetak' ?>
                        </span>
                        <p class="student-name">
                            <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            <?= student_status_badge($student, 'ml-1 align-middle') ?>
                            <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                        </p>
                        <p class="student-meta">
                            NISN <?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?> &middot;
                            NIPD <?= htmlspecialchars($student['nipd'] ?? '-', ENT_QUOTES, 'UTF-8') ?><br>
                            Kelas <?= htmlspecialchars(trim(($class['tingkat'] ?? '') . ' ' . ($class['nama'] ?? '-')), ENT_QUOTES, 'UTF-8') ?>
                            &middot; Tahun Ajaran <?= htmlspecialchars($schoolYear['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <dl class="summary-grid">
                            <div class="summary-item">
                                <dt>Mapel</dt>
                                <dd><?= htmlspecialchars((string) count($subjects), ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                            <div class="summary-item">
                                <dt>Rata-rata</dt>
                                <dd><?= htmlspecialchars($formatScore($average), ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                            <div class="summary-item">
                                <dt>TTD</dt>
                                <dd><?= htmlspecialchars($signatureRecord['status'] ?? 'belum', ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                        </dl>
                    </div>

                    <div style="display:flex; flex-wrap:wrap; gap:10px;">
                        <?php if ($printUrl !== null): ?>
                            <a class="graduation-print" href="<?= htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Cetak SKL</a>
                        <?php endif; ?>
                        <?php if ($verificationUrl !== null): ?>
                            <a class="graduation-link" href="<?= htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Verifikasi Dokumen</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </aside>
        </div>

        <?php if ($evaluation !== null): ?>
            <section class="graduation-section">
                <h2 class="section-title">Keterangan Syarat</h2>
                <div class="criteria-grid">
                    <?php foreach ($criteria as $criterion): ?>
                        <?php
                            $passed = (bool) ($criterion['passed'] ?? false);
                            $details = is_array($criterion['details'] ?? null) ? $criterion['details'] : [];
                        ?>
                        <div class="criterion <?= $passed ? 'criterion-ok' : 'criterion-wait' ?>">
                            <strong><?= htmlspecialchars($criterion['title'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars($criterion['message'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!$passed && !empty($details)): ?>
                                <span><?= htmlspecialchars($details[0], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!$canPrint && !empty($allBlockingMessages)): ?>
                    <ul class="notice-list">
                        <?php foreach ($allBlockingMessages as $message): ?>
                            <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="graduation-section">
                <h2 class="section-title">Ringkasan Nilai</h2>
                <?php if (empty($subjects)): ?>
                    <p class="empty-note">Belum ada data nilai mata pelajaran yang dapat ditampilkan.</p>
                <?php else: ?>
                    <div class="subject-table-wrap">
                        <table class="subject-table">
                            <thead>
                                <tr>
                                    <th style="width:52px;">No</th>
                                    <th>Mata Pelajaran</th>
                                    <th style="width:110px;">KKM</th>
                                    <th style="width:130px;">Nilai Akhir</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $index => $subject): ?>
                                    <?php
                                        $passed = (bool) ($subject['passed'] ?? false);
                                        $issues = is_array($subject['issues'] ?? null) ? $subject['issues'] : [];
                                        $kkmEnabled = (bool) ($subject['kkm_enabled'] ?? false);
                                        $kkmValue = $subject['kkm_value'] ?? null;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($subject['name'] ?? 'Mata Pelajaran', ENT_QUOTES, 'UTF-8') ?></strong>
                                            <?php if (!empty($subject['code'])): ?>
                                                <div class="muted"><?= htmlspecialchars($subject['code'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $kkmEnabled && $kkmValue !== null ? htmlspecialchars($formatScore($kkmValue), ENT_QUOTES, 'UTF-8') : '-' ?></td>
                                        <td class="<?= $passed ? 'score-good' : 'score-bad' ?>">
                                            <?= htmlspecialchars($formatScore($subject['final_score'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td>
                                            <?php if ($passed): ?>
                                                <span class="score-good">Tuntas</span>
                                            <?php else: ?>
                                                <?= htmlspecialchars(implode(' ', $issues), ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>

<?php
    $isValid = (bool) ($isValid ?? false);
    $student = $student ?? null;
    $code = trim((string) ($code ?? ''));
    $school = $school ?? [];

    $schoolName = trim((string) ($school['nama'] ?? config('app.name', 'Aplikasi Sekolah')));

    $addressParts = array_filter([
        $school['alamat'] ?? null,
        $school['desa'] ?? null,
        $school['kecamatan'] ?? null,
        $school['kabupaten'] ?? null,
        $school['provinsi'] ?? null,
    ], static fn ($value) => is_string($value) && trim($value) !== '');
    $schoolAddress = implode(', ', array_map(static fn ($value) => trim((string) $value), $addressParts));

    $status = $isValid ? 'verified' : 'invalid';
    $statusLabel = $isValid ? 'Data Terverifikasi' : 'Kode Tidak Valid';
    $statusMessage = $isValid
        ? 'Data kartu pelajar ditemukan pada basis data resmi sekolah.'
        : 'Kode verifikasi tidak dikenali. Pastikan kartu berasal dari sistem resmi.';

    $statusClass = $isValid ? 'kp-status kp-status-success' : 'kp-status kp-status-danger';
    $statusBadge = $isValid ? 'kp-badge kp-badge-success' : 'kp-badge kp-badge-danger';

    $formattedCode = $code !== '' ? trim(chunk_split($code, 4, ' ')) : '';
?>

<style>
    .kp-verify-wrapper {
        min-height: 100vh;
        padding: 48px 16px;
        background: radial-gradient(circle at top right, #e0edff 0%, #f8fafc 45%, #ffffff 100%);
        font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        color: #0f172a;
    }
    .kp-card {
        max-width: 860px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 28px;
        box-shadow: 0 32px 60px -28px rgba(15, 23, 42, 0.35);
        border: 1px solid rgba(148, 163, 184, 0.18);
        padding: 40px;
    }
    .kp-header {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    @media (min-width: 768px) {
        .kp-header {
            flex-direction: row;
            justify-content: space-between;
            align-items: flex-start;
        }
    }
    .kp-title {
        margin: 0;
    }
    .kp-title h1 {
        margin: 4px 0 0;
        font-size: 30px;
        font-weight: 700;
        letter-spacing: .04em;
    }
    .kp-title p {
        margin: 6px 0 0;
        font-size: 14px;
        color: #64748b;
    }
    .kp-status {
        border-radius: 999px;
        padding: 10px 24px;
        font-size: 14px;
        font-weight: 600;
        display: inline-flex;
        flex-direction: column;
        gap: 4px;
        min-width: 220px;
        border: 1px solid transparent;
    }
    .kp-status span {
        font-size: 12px;
        font-weight: 500;
        color: inherit;
    }
    .kp-status-success {
        background: #dcfce7;
        color: #166534;
        border-color: rgba(22, 101, 52, 0.25);
    }
    .kp-status-danger {
        background: #fee2e2;
        color: #b91c1c;
        border-color: rgba(185, 28, 28, 0.25);
    }
    .kp-summary {
        margin-top: 28px;
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.05), rgba(59, 130, 246, 0.08));
        border-radius: 20px;
        border: 1px solid rgba(148, 163, 184, 0.25);
        padding: 22px 26px;
        font-size: 14px;
        color: #475569;
        line-height: 1.6;
    }
    .kp-section {
        margin-top: 32px;
    }
    .kp-section h2 {
        font-size: 16px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .12em;
        color: #334155;
        margin: 0 0 16px;
    }
    .kp-badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .kp-badge-success {
        background: #bbf7d0;
        color: #166534;
    }
    .kp-badge-danger {
        background: #fecaca;
        color: #b91c1c;
    }
    .kp-info-grid {
        display: grid;
        gap: 18px;
    }
    @media (min-width: 640px) {
        .kp-info-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    .kp-info-card {
        background: #f1f5f9;
        border-radius: 16px;
        border: 1px solid rgba(148, 163, 184, 0.3);
        padding: 16px 18px;
    }
    .kp-info-card dt {
        margin: 0;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .12em;
        color: #64748b;
    }
    .kp-info-card dd {
        margin: 10px 0 0;
        font-size: 15px;
        font-weight: 600;
        color: #0f172a;
        line-height: 1.45;
        word-break: break-word;
    }
    .kp-code-block {
        margin-top: 24px;
        background: #0f172a;
        color: #e2e8f0;
        padding: 18px 22px;
        border-radius: 18px;
        font-family: "Roboto Mono", "SFMono-Regular", Menlo, Monaco, Consolas, monospace;
        font-size: 14px;
        line-height: 1.7;
        box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.12);
        overflow-wrap: anywhere;
    }
    .kp-note {
        margin-top: 18px;
        font-size: 13px;
        color: #475569;
    }
    .kp-alert {
        margin-top: 20px;
        background: #fef2f2;
        border-radius: 18px;
        border: 1px solid rgba(248, 113, 113, 0.3);
        padding: 22px 24px;
        font-size: 14px;
        color: #b91c1c;
        line-height: 1.6;
    }
    .kp-alert strong {
        display: block;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: 8px;
    }
    .kp-footer {
        margin-top: 36px;
        font-size: 12px;
        text-align: center;
        color: #94a3b8;
    }
    @media (max-width: 640px) {
        .kp-card {
            padding: 28px 22px;
            border-radius: 20px;
        }
        .kp-status {
            width: 100%;
            align-items: center;
            text-align: center;
        }
    }
</style>

<div class="kp-verify-wrapper">
    <div class="kp-card">
        <div class="kp-header">
            <div class="kp-title">
                <p style="margin:0;letter-spacing:.24em;font-size:11px;text-transform:uppercase;color:#94a3b8;">
                    <?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?>
                </p>
                <h1>Verifikasi Kartu Pelajar</h1>
                <?php if ($schoolAddress !== ''): ?>
                    <p><?= htmlspecialchars($schoolAddress, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
            <div class="<?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                <span><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <div class="kp-summary">
            Gunakan halaman ini untuk memastikan keaslian kartu pelajar dengan memindai kode QR yang tercetak.
            Sistem hanya menampilkan data siswa jika kode sesuai dengan catatan resmi sekolah.
        </div>

        <?php if ($isValid && $student !== null): ?>
            <div class="kp-section">
                <h2>Identitas Siswa</h2>
                <div class="kp-info-grid">
                    <dl class="kp-info-card">
                        <dt>Nama Lengkap</dt>
                        <dd>
                            <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            <?= student_status_badge($student, 'ml-1 align-middle') ?>
                            <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                        </dd>
                    </dl>
                    <dl class="kp-info-card">
                        <dt>Nipd</dt>
                        <dd><?= htmlspecialchars($student['nipd'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>
                    </dl>
                    <dl class="kp-info-card">
                        <dt>Kelas</dt>
                        <dd><?= htmlspecialchars($student['kelas_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>
                    </dl>
                </div>

                <div class="kp-section" style="margin-top:28px;">
                    <h2>Kode Verifikasi</h2>
                    <span class="<?= htmlspecialchars($statusBadge, ENT_QUOTES, 'UTF-8') ?>">Kode Aktif</span>
                    <div class="kp-code-block">
                        <?= htmlspecialchars($formattedCode !== '' ? $formattedCode : $code, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <p class="kp-note">
                        Simpan kode ini untuk keperluan audit internal. Setiap kartu pelajar memiliki kode unik
                        yang dihasilkan berdasarkan identitas siswa dan tidak dapat dipalsukan tanpa akses ke sistem.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="kp-alert">
                <strong>Peringatan</strong>
                Kami tidak menemukan data siswa dengan kode verifikasi yang Anda masukkan. Pastikan kartu pelajar berasal
                dari sistem <?= htmlspecialchars(config('app.name', 'Aplikasi Sekolah'), ENT_QUOTES, 'UTF-8') ?> sekolah ini dan tidak mengalami kerusakan pada QR atau kode yang tercetak.
                <?php if ($code !== ''): ?>
                    <br /><br />
                    <span style="font-family:'Roboto Mono','SFMono-Regular',monospace;font-size:13px;color:#7f1d1d;">
                        Kode diterima: <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="kp-footer">
            &copy; <?= htmlspecialchars(date('Y'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?> · Sistem Informasi Akademik
        </div>
    </div>
</div>

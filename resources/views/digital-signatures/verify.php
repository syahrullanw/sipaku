<?php
$document = $document ?? null;
$payload = $payload ?? [];
$isApproved = $isApproved ?? false;
$schoolProfile = $schoolProfile ?? [];
$schoolYear = $schoolYear ?? null;
$headmaster = $headmaster ?? null;
$approver = $approver ?? null;
$token = trim((string) ($token ?? ''));

$status = $document['status'] ?? null;
$statusMessage = 'Masukkan token verifikasi untuk memeriksa keaslian dokumen.';

if ($document !== null) {
    switch ($status) {
        case 'approved':
            $statusMessage = 'TTD digital valid dan aktif.';
            break;
        case 'pending':
            $statusMessage = 'TTD digital menunggu persetujuan kepala sekolah.';
            break;
        case 'revoked':
            $statusMessage = 'TTD digital telah dicabut.';
            break;
        default:
            $statusMessage = 'Status TTD digital belum tersedia.';
            break;
    }
}

$student = $payload['student'] ?? [];
$class = $payload['class'] ?? [];
$semester = isset($payload['semester']) ? (int) $payload['semester'] : null;
$documentTitle = $document['document_title'] ?? 'Dokumen Tidak Dikenal';
$documentType = $document['document_type'] ?? '-';
$headmasterName = trim((string) ($headmaster['nama'] ?? ($document['headmasterName'] ?? '')));
$approverName = trim((string) ($approver['name'] ?? ''));
$approvedAtLabel = $document['approved_at'] ?? null;
if (isset($document['approvedAtLabel']) && $document['approvedAtLabel'] !== '') {
    $approvedAtLabel = $document['approvedAtLabel'];
}

$schoolName = trim((string) ($schoolProfile['nama'] ?? 'Sekolah'));
$schoolAddress = trim((string) ($schoolProfile['alamat'] ?? ''));
$tokenDisplay = $document['signature_token'] ?? $token;
$statusBadgeText = 'Tidak Diketahui';
$statusBadgeClass = 'status-badge status-badge-muted';

switch ($status) {
    case 'approved':
        $statusBadgeText = 'Aktif';
        $statusBadgeClass = 'status-badge status-badge-success';
        break;
    case 'pending':
        $statusBadgeText = 'Menunggu';
        $statusBadgeClass = 'status-badge status-badge-warning';
        break;
    case 'revoked':
        $statusBadgeText = 'Dicabut';
        $statusBadgeClass = 'status-badge status-badge-danger';
        break;
    default:
        if ($document === null && $token !== '') {
            $statusBadgeText = 'Tidak Ditemukan';
            $statusBadgeClass = 'status-badge status-badge-danger';
        }
        break;
}

$subjectCount = isset($payload['subjects']) && is_array($payload['subjects']) ? count($payload['subjects']) : 0;
$achievementCount = isset($payload['achievements']) && is_array($payload['achievements']) ? count($payload['achievements']) : 0;
$attendance = $payload['attendance'] ?? ['sakit' => 0, 'izin' => 0, 'bolos' => 0, 'alpa' => 0];
$attendanceSummary = sprintf(
    'Sakit %d · Izin %d · Tanpa keterangan %d',
    (int) ($attendance['sakit'] ?? 0),
    (int) ($attendance['izin'] ?? 0),
    (int) (($attendance['bolos'] ?? 0) + ($attendance['alpa'] ?? 0)),
);
$homeroomNote = trim((string) ($payload['homeroom_note'] ?? ''));

$jsonLink = $tokenDisplay !== '' ? absolute_url('dokumen/validasi/' . rawurlencode($tokenDisplay) . '?format=json') : null;
$baseValidationUrl = rtrim(absolute_url('dokumen/validasi'), '/');
?>

<style>
    .verify-wrapper {
        background: #f3f4f6;
        min-height: 100vh;
        padding: 40px 12px;
        font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        color: #0f172a;
    }
    .verify-card {
        max-width: 960px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 24px;
        box-shadow: 0 30px 60px -30px rgba(15, 23, 42, 0.35);
        padding: 32px;
        border: 1px solid #e2e8f0;
    }
    .verify-header {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    @media (min-width: 768px) {
        .verify-header {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
    }
    .verify-title {
        font-size: 28px;
        font-weight: 700;
        letter-spacing: .4px;
        margin: 0;
    }
    .verify-subtitle {
        margin: 4px 0 0;
        font-size: 14px;
        color: #64748b;
    }
    .token-form {
        display: flex;
        gap: 12px;
        align-items: center;
    }
    @media (max-width: 640px) {
        .token-form {
            flex-direction: column;
            align-items: stretch;
        }
    }
    .token-input {
        flex: 1;
        border: 1px solid #cbd5f5;
        border-radius: 999px;
        padding: 12px 18px;
        font-size: 15px;
        outline: none;
        transition: border-color .2s ease;
    }
    .token-input:focus {
        border-color: #2563eb;
    }
    .token-submit {
        border: none;
        background: #2563eb;
        color: #ffffff;
        font-weight: 600;
        padding: 12px 22px;
        border-radius: 999px;
        cursor: pointer;
        transition: background .2s ease;
    }
    .token-submit:hover {
        background: #1e3fa9;
    }
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
    }
    .status-badge-success {
        background: #dcfce7;
        color: #166534;
    }
    .status-badge-warning {
        background: #fef3c7;
        color: #92400e;
    }
    .status-badge-danger {
        background: #fee2e2;
        color: #b91c1c;
    }
    .status-badge-muted {
        background: #e2e8f0;
        color: #475569;
    }
    .token-snippet {
        background: #0f172a;
        color: #e2e8f0;
        padding: 14px 18px;
        border-radius: 14px;
        font-family: "Roboto Mono", "SFMono-Regular", Menlo, Monaco, Consolas, monospace;
        font-size: 14px;
        overflow-wrap: anywhere;
        margin-top: 16px;
    }
    .info-grid {
        display: grid;
        gap: 18px;
        margin-top: 24px;
    }
    @media (min-width: 768px) {
        .info-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    .info-card {
        border-top: 2px solid #e2e8f0;
        padding-top: 12px;
    }
    .info-card dt {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #64748b;
    }
    .info-card dd {
        font-size: 14px;
        margin: 8px 0 0;
        line-height: 1.5;
        color: #1e293b;
        white-space: pre-line;
    }
    .note-box {
        background: #f8fafc;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        padding: 16px 18px;
        margin-top: 24px;
        font-size: 14px;
        color: #1e293b;
    }
    .note-box strong {
        display: block;
        font-weight: 700;
        margin-bottom: 6px;
        text-transform: uppercase;
        font-size: 12px;
        color: #475569;
    }
    .summary-grid {
        display: grid;
        gap: 16px;
        margin-top: 24px;
    }
    @media (min-width: 768px) {
        .summary-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }
    .summary-card {
        background: #f1f5f9;
        border-radius: 16px;
        padding: 16px;
        border: 1px solid #e2e8f0;
        text-align: center;
    }
    .summary-card h4 {
        font-size: 12px;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: .1em;
        margin: 0;
    }
    .summary-card p {
        margin: 10px 0 0;
        font-size: 24px;
        font-weight: 700;
        color: #0f172a;
    }
    .summary-card span {
        display: block;
        margin-top: 6px;
        font-size: 13px;
        color: #475569;
    }
    .footer-note {
        text-align: center;
        font-size: 12px;
        color: #94a3b8;
        margin-top: 36px;
    }
    .json-link {
        font-size: 13px;
        color: #2563eb;
        text-decoration: none;
    }
    .json-link:hover {
        text-decoration: underline;
    }
</style>

<div class="verify-wrapper">
    <div class="verify-card">
        <div class="verify-header">
            <div>
                <h1 class="verify-title">Validasi TTD Digital</h1>
                <?php if ($schoolAddress !== ''): ?>
                    <p class="verify-subtitle"><?= htmlspecialchars($schoolAddress, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
            <form class="token-form" id="token-form">
                <input
                    type="text"
                    name="token"
                    class="token-input"
                    placeholder="Tempel token verifikasi di sini"
                    value="<?= htmlspecialchars($tokenDisplay, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="off"
                />
                <button type="submit" class="token-submit">Periksa</button>
            </form>
        </div>

        <?php if ($jsonLink !== null): ?>
            <div style="margin-top: 12px;">
                <a class="json-link" href="<?= htmlspecialchars($jsonLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                    Lihat respon JSON
                </a>
            </div>
        <?php endif; ?>

        <div style="margin-top: 24px;">
            <span class="<?= htmlspecialchars($statusBadgeClass, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($statusBadgeText, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

        <p style="margin-top: 12px; font-size: 14px; color: #334155;">
            <?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?>
        </p>

        <?php if ($tokenDisplay !== ''): ?>
            <div class="token-snippet">Token: <?= htmlspecialchars($tokenDisplay, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($document !== null): ?>
            <div class="info-grid">
                <div class="info-card">
                    <dt>Siswa</dt>
                    <dd>
                        <?= htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8') ?><br>
                        NIPD: <?= htmlspecialchars($student['nipd'] ?? '-', ENT_QUOTES, 'UTF-8') ?><br>
                        NISN: <?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                    </dd>
                </div>
                <div class="info-card">
                    <dt>Kelas</dt>
                    <dd>
                        <?= htmlspecialchars(trim(($class['level'] ?? '') . ' ' . ($class['name'] ?? '-')), ENT_QUOTES, 'UTF-8') ?><br>
                        Semester: <?= htmlspecialchars($semester !== null ? (string) $semester : '-', ENT_QUOTES, 'UTF-8') ?>
                    </dd>
                </div>
                <div class="info-card">
                    <dt>Raport</dt>
                    <dd>
                        Jenis: <?= htmlspecialchars(str_replace('_', ' ', $documentType), ENT_QUOTES, 'UTF-8') ?><br>
                        Judul: <?= htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8') ?><br>
                        Tahun Ajaran: <?= htmlspecialchars(is_array($schoolYear) ? ($schoolYear['nama'] ?? '-') : '-', ENT_QUOTES, 'UTF-8') ?>
                    </dd>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-card">
                    <dt>Penanggung Jawab</dt>
                    <dd>
                        Kepala Sekolah: <?= htmlspecialchars($headmasterName !== '' ? $headmasterName : '-', ENT_QUOTES, 'UTF-8') ?><br>
                        Disetujui oleh: <?= htmlspecialchars($approverName !== '' ? $approverName : '-', ENT_QUOTES, 'UTF-8') ?>
                    </dd>
                </div>
                <div class="info-card">
                    <dt>Waktu</dt>
                    <dd>
                        Disetujui: <?= htmlspecialchars($approvedAtLabel ?? '-', ENT_QUOTES, 'UTF-8') ?><br>
                        Update Terakhir: <?= htmlspecialchars($document['updated_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                    </dd>
                </div>
                <div class="info-card">
                    <dt>Status</dt>
                    <dd>
                        <?= htmlspecialchars(ucfirst((string) $status), ENT_QUOTES, 'UTF-8') ?><br>
                        Catatan: <?= htmlspecialchars($document['approval_note'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                    </dd>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Mata Pelajaran</h4>
                    <p><?= $subjectCount ?></p>
                    <span>Total mapel dengan nilai tercatat.</span>
                </div>
                <div class="summary-card">
                    <h4>Prestasi</h4>
                    <p><?= $achievementCount ?></p>
                    <span>Prestasi yang didokumentasikan.</span>
                </div>
                <div class="summary-card">
                    <h4>Kehadiran</h4>
                    <p><?= htmlspecialchars((string) ($attendance['sakit'] ?? 0), ENT_QUOTES, 'UTF-8') ?></p>
                    <span><?= htmlspecialchars($attendanceSummary, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="summary-card">
                    <h4>Catatan Wali</h4>
                    <p><?= $homeroomNote !== '' ? 'Ada' : 'Tidak Ada' ?></p>
                    <span><?= htmlspecialchars($homeroomNote !== '' ? $homeroomNote : 'Tidak ada catatan dari wali kelas.', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="note-box">
            <strong>Catatan:</strong>
            <?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <p class="footer-note">Sistem Informasi Akademik · Verifikasi TTD Digital</p>
    </div>
</div>

<script>
    (function () {
        const form = document.getElementById('token-form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const input = form.querySelector('input[name="token"]');
            if (!input) {
                return;
            }

            const token = input.value.trim();
            if (token === '') {
                input.focus();
                return;
            }

            window.location.href = '<?= htmlspecialchars($baseValidationUrl, ENT_QUOTES, 'UTF-8') ?>/' + encodeURIComponent(token);
        });
    })();
</script>

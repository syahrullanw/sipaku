<?php
$document = $document ?? null;
$payload = is_array($payload ?? null) ? $payload : [];
$isApproved = (bool) ($isApproved ?? false);
$schoolProfile = is_array($schoolProfile ?? null) ? $schoolProfile : [];
$schoolYear = is_array($schoolYear ?? null) ? $schoolYear : null;
$headmaster = is_array($headmaster ?? null) ? $headmaster : null;
$approver = is_array($approver ?? null) ? $approver : null;
$token = trim((string) ($token ?? ''));

$letter = is_array($payload['letter'] ?? null) ? $payload['letter'] : [];
$letterNumber = $letter['number'] ?? '-';
$letterSubject = $letter['subject'] ?? '-';
$letterPlace = $letter['place'] ?? '-';
$signDate = $letter['sign_date'] ?? null;
$effectiveStart = $letter['effective_start'] ?? null;
$effectiveEnd = $letter['effective_end'] ?? null;
$headmasterName = $headmaster['nama'] ?? ($payload['headmaster']['name'] ?? null);
$headmasterNip = $headmaster['nip'] ?? ($payload['headmaster']['nip'] ?? null);

$approvalNote = $document['approval_note'] ?? null;
$approvedAt = $document['approved_at'] ?? null;
$status = $document['status'] ?? null;

$statusBadgeClass = 'status-badge-muted';
$statusBadgeText = 'Tidak Diketahui';
$statusMessage = 'Masukkan token verifikasi untuk memeriksa keaslian surat.';

switch ($status) {
    case 'approved':
        $statusBadgeClass = 'status-badge-success';
        $statusBadgeText = 'Aktif';
        $statusMessage = 'TTD digital valid dan surat sudah disahkan kepala sekolah.';
        break;
    case 'pending':
        $statusBadgeClass = 'status-badge-warning';
        $statusBadgeText = 'Menunggu';
        $statusMessage = 'Surat menunggu persetujuan kepala sekolah.';
        break;
    case 'revoked':
        $statusBadgeClass = 'status-badge-danger';
        $statusBadgeText = 'Dicabut';
        $statusMessage = 'Persetujuan surat telah dicabut. Hubungi Tata Usaha untuk konfirmasi.';
        break;
    default:
        if ($document === null && $token !== '') {
            $statusBadgeClass = 'status-badge-danger';
            $statusBadgeText = 'Tidak Ditemukan';
            $statusMessage = 'Token tidak ditemukan atau bukan dokumen persuratan.';
        }
        break;
}

$schoolName = $schoolProfile['nama'] ?? 'Sekolah';
$schoolAddress = $schoolProfile['alamat'] ?? '';
$schoolPhone = $schoolProfile['telepon'] ?? ($schoolProfile['phone'] ?? '');
$schoolNpsn = $schoolProfile['npsn'] ?? '';
$schoolEmail = $schoolProfile['email'] ?? '';

$approvedAtLabel = null;
if ($approvedAt) {
    $timestamp = strtotime($approvedAt);
    $approvedAtLabel = $timestamp ? date('d/m/Y H:i', $timestamp) : $approvedAt;
}

$jsonLink = $token !== '' ? absolute_url('persuratan/validasi/' . rawurlencode($token) . '?format=json') : null;
$baseValidationUrl = rtrim(absolute_url('persuratan/validasi'), '/');
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
        max-width: 900px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 24px;
        box-shadow: 0 24px 60px -30px rgba(15, 23, 42, 0.35);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    .verify-header {
        padding: 32px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.05), rgba(59, 130, 246, 0.08));
    }
    .verify-title {
        margin: 0;
        font-size: 28px;
        font-weight: 700;
        letter-spacing: .4px;
        color: #0f172a;
    }
    .token-form {
        margin-top: 16px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }
    .token-input {
        flex: 1;
        min-width: 240px;
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        padding: 12px 16px;
        font-size: 15px;
        transition: border-color .2s ease;
    }
    .token-input:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
    }
    .token-submit {
        border: none;
        background: #2563eb;
        color: #ffffff;
        font-weight: 600;
        padding: 12px 22px;
        border-radius: 12px;
        cursor: pointer;
        transition: background .2s ease;
    }
    .token-submit:hover {
        background: #1d4ed8;
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
    .verify-body {
        padding: 32px;
        display: grid;
        gap: 24px;
    }
    .section-card {
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 20px 24px;
        background: #ffffff;
    }
    .section-title {
        margin: 0 0 12px;
        font-size: 16px;
        font-weight: 600;
        color: #0f172a;
    }
    .section-grid {
        display: grid;
        gap: 12px;
        font-size: 14px;
        color: #475569;
    }
    .section-grid strong {
        color: #1e293b;
        font-weight: 600;
    }
    .token-snippet {
        background: #0f172a;
        color: #e2e8f0;
        padding: 14px 18px;
        border-radius: 14px;
        font-family: "JetBrains Mono", "SFMono-Regular", Menlo, Monaco, Consolas, monospace;
        font-size: 13px;
        overflow-wrap: anywhere;
        margin-top: 12px;
    }
    .actions {
        margin-top: 16px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }
    .actions a {
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        color: #2563eb;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .actions a:hover {
        color: #1d4ed8;
    }
    @media (max-width: 640px) {
        .verify-body {
            padding: 24px;
        }
        .token-form {
            flex-direction: column;
        }
        .token-submit {
            width: 100%;
        }
    }
</style>

<div class="verify-wrapper">
    <div class="verify-card">
        <div class="verify-header">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="verify-title">Verifikasi Surat Persuratan</h1>
                    <p class="mt-2 text-sm text-slate-600">
                        Pastikan status QR code dan keaslian surat yang ditandatangani kepala sekolah.
                    </p>
                </div>
                <span class="status-badge <?= htmlspecialchars($statusBadgeClass, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($statusBadgeText, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            <form method="get" class="token-form">
                <input
                    type="text"
                    name="token"
                    class="token-input"
                    placeholder="Masukkan token verifikasi"
                    value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"
                    required
                />
                <button type="submit" class="token-submit">Periksa</button>
            </form>
            <p class="mt-3 text-xs text-slate-500"><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <div class="verify-body">
            <?php if ($document === null): ?>
                <div class="section-card">
                    <p class="text-sm text-slate-600">
                        <?= htmlspecialchars(
                            $token === ''
                                ? 'Masukkan token verifikasi dari QR code atau dokumen persuratan untuk memeriksa keasliannya.'
                                : 'Tidak ditemukan dokumen persuratan dengan token tersebut. Pastikan token benar dan masih berlaku.',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="section-card">
                    <h2 class="section-title">Informasi Surat</h2>
                    <div class="section-grid">
                        <div><strong>Judul Surat</strong><br><?= htmlspecialchars($document['document_title'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                        <div><strong>Nomor Surat</strong><br><?= htmlspecialchars($letterNumber !== '' ? $letterNumber : '-', ENT_QUOTES, 'UTF-8') ?></div>
                        <div><strong>Perihal</strong><br><?= htmlspecialchars($letterSubject !== '' ? $letterSubject : '-', ENT_QUOTES, 'UTF-8') ?></div>
                        <div><strong>Tempat &amp; Tanggal</strong><br>
                            <?= htmlspecialchars($letterPlace !== '' ? $letterPlace : '-', ENT_QUOTES, 'UTF-8') ?>,
                            <?= htmlspecialchars($signDate ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <?php if ($effectiveStart && $effectiveEnd): ?>
                            <div><strong>Masa Berlaku</strong><br><?= htmlspecialchars($effectiveStart, ENT_QUOTES, 'UTF-8') ?> &ndash; <?= htmlspecialchars($effectiveEnd, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if ($approvalNote): ?>
                            <div><strong>Catatan Persetujuan</strong><br><?= htmlspecialchars($approvalNote, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="section-title">Status Persetujuan</h2>
                    <div class="section-grid">
                        <div><strong>Status</strong><br><?= htmlspecialchars(ucfirst($status ?? 'Tidak diketahui'), ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if ($approvedAtLabel): ?>
                            <div><strong>Disetujui Pada</strong><br><?= htmlspecialchars($approvedAtLabel, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <div><strong>Kepala Sekolah</strong><br>
                            <?= htmlspecialchars($headmasterName ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($headmasterNip): ?>
                                <br><span class="text-xs text-slate-500">NIP: <?= htmlspecialchars($headmasterNip, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($approver !== null): ?>
                            <div><strong>Disetujui oleh</strong><br><?= htmlspecialchars($approver['name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="section-title">Profil Sekolah</h2>
                    <div class="section-grid">
                        <div><strong>Nama Sekolah</strong><br><?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if ($schoolNpsn): ?>
                            <div><strong>NPSN</strong><br><?= htmlspecialchars($schoolNpsn, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if ($schoolAddress): ?>
                            <div><strong>Alamat</strong><br><?= htmlspecialchars($schoolAddress, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if ($schoolPhone): ?>
                            <div><strong>Telepon</strong><br><?= htmlspecialchars($schoolPhone, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if ($schoolEmail): ?>
                            <div><strong>Email</strong><br><?= htmlspecialchars($schoolEmail, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="section-title">Token Verifikasi</h2>
                    <p class="text-sm text-slate-600">
                        Token ini dapat dibagikan untuk verifikasi mandiri surat secara daring. Simpan baik-baik dan jangan sebarkan ke pihak tidak berkepentingan.
                    </p>
                    <div class="token-snippet"><?= htmlspecialchars($document['signature_token'] ?? $token, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="actions">
                        <?php if ($jsonLink): ?>
                            <a href="<?= htmlspecialchars($jsonLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                Tampilkan dalam format JSON
                            </a>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($baseValidationUrl, ENT_QUOTES, 'UTF-8') ?>">
                            Verifikasi token lain
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

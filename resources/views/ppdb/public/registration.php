<?php
    $successMessage = $successMessage ?? session_flash('success');
    $errorMessage = $errorMessage ?? session_flash('error');
    $formIdentifier = $identifier ?? ($period['kode'] ?? $period['token_pendaftaran'] ?? '');
    $branding = app_branding();
    $schoolName = (string) ($branding['name'] ?? config('app.name', 'Sekolah'));
    $schoolLogo = app_logo_asset('icons/icon-192.png');
    $ppdbNote = trim((string) ($period['catatan'] ?? ''));
?>

<style>
    :root {
        --ppdb-bg: #f1f5f9;
        --ppdb-card: #ffffff;
        --ppdb-text: #0f172a;
        --ppdb-muted: #475569;
        --ppdb-border: #dbe5f0;
        --ppdb-primary: #1d4ed8;
        --ppdb-primary-soft: #dbeafe;
        --ppdb-danger: #b91c1c;
        --ppdb-danger-soft: #fee2e2;
        --ppdb-success: #166534;
        --ppdb-success-soft: #dcfce7;
    }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Segoe UI", Tahoma, Arial, sans-serif; color: var(--ppdb-text); background: linear-gradient(180deg, #e2e8f0 0%, var(--ppdb-bg) 320px); }
    .ppdb-shell { max-width: 980px; margin: 32px auto; padding: 0 16px 24px; }
    .ppdb-card { background: var(--ppdb-card); border: 1px solid var(--ppdb-border); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08); }
    .ppdb-header { padding: 24px; border-bottom: 1px solid var(--ppdb-border); background: linear-gradient(135deg, #eff6ff, #f8fafc); display: flex; gap: 16px; align-items: center; }
    .ppdb-logo { width: 68px; height: 68px; border-radius: 12px; object-fit: cover; border: 1px solid #bfdbfe; background: #fff; padding: 4px; }
    .ppdb-title { margin: 0; font-size: 30px; line-height: 1.1; }
    .ppdb-subtitle { margin: 6px 0 0; color: var(--ppdb-muted); font-size: 15px; }
    .ppdb-period { margin-top: 10px; display: inline-block; font-size: 13px; color: #1e40af; background: var(--ppdb-primary-soft); border: 1px solid #bfdbfe; border-radius: 999px; padding: 6px 12px; }
    .ppdb-content { padding: 24px; }
    .ppdb-alert { border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; font-size: 14px; font-weight: 600; }
    .ppdb-alert-success { background: var(--ppdb-success-soft); color: var(--ppdb-success); border: 1px solid #86efac; }
    .ppdb-alert-error { background: var(--ppdb-danger-soft); color: var(--ppdb-danger); border: 1px solid #fca5a5; }
    .ppdb-note { margin: 0 0 18px; padding: 12px 14px; border-radius: 10px; border: 1px dashed #93c5fd; background: #eff6ff; color: #1e3a8a; font-size: 14px; }
    .ppdb-section { margin-bottom: 18px; }
    .ppdb-section h2 { margin: 0 0 6px; font-size: 22px; }
    .ppdb-section p { margin: 0; color: var(--ppdb-muted); }
    .ppdb-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .ppdb-field { display: flex; flex-direction: column; gap: 6px; }
    .ppdb-field-full { grid-column: 1 / -1; }
    .ppdb-label { font-size: 14px; font-weight: 600; color: #1e293b; }
    .ppdb-input, .ppdb-select, .ppdb-textarea {
        width: 100%;
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 15px;
        color: #0f172a;
    }
    .ppdb-input:focus, .ppdb-select:focus, .ppdb-textarea:focus {
        outline: none;
        border-color: var(--ppdb-primary);
        box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15);
    }
    .ppdb-textarea { min-height: 96px; resize: vertical; }
    .ppdb-footer { margin-top: 20px; border-top: 1px solid var(--ppdb-border); padding-top: 16px; }
    .ppdb-list { margin: 8px 0 0; color: var(--ppdb-muted); }
    .ppdb-list li { margin-bottom: 6px; }
    .ppdb-submit {
        margin-top: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: none;
        border-radius: 10px;
        background: var(--ppdb-primary);
        color: #fff;
        padding: 12px 18px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
    }
    .ppdb-submit:hover { background: #1e40af; }
    @media (max-width: 768px) {
        .ppdb-header { align-items: flex-start; }
        .ppdb-title { font-size: 25px; }
        .ppdb-grid { grid-template-columns: 1fr; }
    }
</style>

<section class="ppdb-shell">
    <div class="ppdb-card">
        <header class="ppdb-header">
            <img src="<?= htmlspecialchars($schoolLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Logo Sekolah" class="ppdb-logo" />
            <div>
                <h1 class="ppdb-title">Pendaftaran PPDB</h1>
                <p class="ppdb-subtitle"><strong><?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?></strong></p>
                <p class="ppdb-subtitle">Formulir pendaftaran peserta didik baru secara online.</p>
                <span class="ppdb-period">
                    <?= htmlspecialchars($period['nama'] ?? 'Periode PPDB', ENT_QUOTES, 'UTF-8') ?>
                    <?php if (!empty($period['pendaftaran_mulai']) || !empty($period['pendaftaran_selesai'])): ?>
                        • <?= htmlspecialchars(date('d M Y', strtotime($period['pendaftaran_mulai'] ?? date('Y-m-d'))), ENT_QUOTES, 'UTF-8') ?>
                        - <?= htmlspecialchars(date('d M Y', strtotime($period['pendaftaran_selesai'] ?? date('Y-m-d'))), ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </span>
            </div>
        </header>

        <div class="ppdb-content">
            <?php if (!empty($successMessage)): ?>
                <div class="ppdb-alert ppdb-alert-success"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if (!empty($errorMessage)): ?>
                <div class="ppdb-alert ppdb-alert-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <p class="ppdb-note">
                <?= $ppdbNote !== '' ? nl2br(htmlspecialchars($ppdbNote, ENT_QUOTES, 'UTF-8')) : 'Silakan isi data dengan benar. Pastikan nomor HP/WhatsApp aktif untuk menerima informasi lanjutan dari panitia PPDB.' ?>
            </p>

            <form action="<?= htmlspecialchars(base_url('ppdb/pendaftaran/' . $formIdentifier), ENT_QUOTES, 'UTF-8') ?>" method="post">
                <?= csrf_field() ?>

                <div class="ppdb-section">
                    <h2>Data Calon Siswa</h2>
                    <p>Lengkapi informasi berikut sesuai data resmi.</p>
                </div>

                <div class="ppdb-grid">
                    <label class="ppdb-field">
                        <span class="ppdb-label">Nama Lengkap</span>
                        <input class="ppdb-input" type="text" name="nama_lengkap" value="<?= htmlspecialchars((string) old('nama_lengkap'), ENT_QUOTES, 'UTF-8') ?>" required />
                    </label>
                    <label class="ppdb-field">
                        <span class="ppdb-label">Jenis Kelamin</span>
                        <select class="ppdb-select" name="jenis_kelamin" required>
                            <option value="">Pilih...</option>
                            <option value="L" <?= (string) old('jenis_kelamin') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= (string) old('jenis_kelamin') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </label>
                    <label class="ppdb-field">
                        <span class="ppdb-label">Tempat Lahir</span>
                        <input class="ppdb-input" type="text" name="tempat_lahir" value="<?= htmlspecialchars((string) old('tempat_lahir'), ENT_QUOTES, 'UTF-8') ?>" />
                    </label>
                    <label class="ppdb-field">
                        <span class="ppdb-label">Tanggal Lahir</span>
                        <input class="ppdb-input" type="date" name="tanggal_lahir" value="<?= htmlspecialchars((string) old('tanggal_lahir'), ENT_QUOTES, 'UTF-8') ?>" />
                    </label>
                    <label class="ppdb-field">
                        <span class="ppdb-label">NIK</span>
                        <input class="ppdb-input" type="text" name="nik" value="<?= htmlspecialchars((string) old('nik'), ENT_QUOTES, 'UTF-8') ?>" maxlength="32" />
                    </label>
                    <label class="ppdb-field">
                        <span class="ppdb-label">NISN</span>
                        <input class="ppdb-input" type="text" name="nisn" value="<?= htmlspecialchars((string) old('nisn'), ENT_QUOTES, 'UTF-8') ?>" maxlength="32" />
                    </label>
                    <label class="ppdb-field ppdb-field-full">
                        <span class="ppdb-label">Alamat Lengkap</span>
                        <textarea class="ppdb-textarea" name="alamat"><?= htmlspecialchars((string) old('alamat'), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                    <label class="ppdb-field ppdb-field-full">
                        <span class="ppdb-label">Asal Sekolah</span>
                        <input class="ppdb-input" type="text" name="asal_sekolah" value="<?= htmlspecialchars((string) old('asal_sekolah'), ENT_QUOTES, 'UTF-8') ?>" />
                    </label>
                </div>

                <div class="ppdb-section" style="margin-top: 20px;">
                    <h2>Kontak</h2>
                    <p>Pastikan nomor dan email aktif untuk menerima informasi lanjutan.</p>
                </div>

                <div class="ppdb-grid">
                    <label class="ppdb-field">
                        <span class="ppdb-label">Nomor Telepon Siswa</span>
                        <input class="ppdb-input" type="text" name="telepon" value="<?= htmlspecialchars((string) old('telepon'), ENT_QUOTES, 'UTF-8') ?>" />
                    </label>
                    <label class="ppdb-field">
                        <span class="ppdb-label">Email (Opsional)</span>
                        <input class="ppdb-input" type="email" name="email" value="<?= htmlspecialchars((string) old('email'), ENT_QUOTES, 'UTF-8') ?>" />
                    </label>
                    <label class="ppdb-field">
                        <span class="ppdb-label">Nama Orang Tua/Wali</span>
                        <input class="ppdb-input" type="text" name="nama_wali" value="<?= htmlspecialchars((string) old('nama_wali'), ENT_QUOTES, 'UTF-8') ?>" />
                    </label>
                    <label class="ppdb-field">
                        <span class="ppdb-label">Nomor Telepon Orang Tua/Wali</span>
                        <input class="ppdb-input" type="text" name="telepon_wali" value="<?= htmlspecialchars((string) old('telepon_wali'), ENT_QUOTES, 'UTF-8') ?>" />
                    </label>
                </div>

                <div class="ppdb-footer">
                    <strong>Catatan:</strong>
                    <ul class="ppdb-list">
                        <li>Periksa kembali data yang dimasukkan sebelum menekan tombol kirim.</li>
                        <li>Simpan kode pendaftaran yang tampil setelah pengiriman sebagai bukti registrasi.</li>
                        <li>Panitia akan menghubungi melalui nomor/email yang tertera untuk jadwal seleksi.</li>
                    </ul>
                    <button type="submit" class="ppdb-submit">Kirim Pendaftaran</button>
                </div>
            </form>
        </div>
    </div>
</section>

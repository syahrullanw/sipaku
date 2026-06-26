<section style="max-width: 720px; margin: 40px auto; padding: 32px; background: #ffffff; border-radius: 16px; box-shadow: 0 15px 30px rgba(15, 23, 42, 0.1);">
    <h1 style="margin: 0 0 12px; font-size: 32px; font-weight: 700; color: #0f172a;">
        <?= htmlspecialchars($title ?? config('app.name', 'Aplikasi Sekolah'), ENT_QUOTES, 'UTF-8') ?>
    </h1>
    <p style="margin: 0 0 24px; color: #475569; line-height: 1.6;">
        Kerangka aplikasi berhasil dibuat. Mulai fase berikutnya dengan menambahkan modul, konfigurasi database dan fitur utama <?= htmlspecialchars(config('app.name', 'Aplikasi Sekolah'), ENT_QUOTES, 'UTF-8') ?> per tahun ajaran.
    </p>
    <dl style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px;">
        <div style="padding: 16px; border: 1px solid #e2e8f0; border-radius: 12px;">
            <dt style="font-size: 14px; text-transform: uppercase; font-weight: 600; color: #64748b;">Tahun Berjalan</dt>
            <dd style="margin: 8px 0 0; font-size: 20px; font-weight: 600; color: #1e293b;">
                <?= htmlspecialchars(($year ?? date('Y')) . '/' . ((int) ($year ?? date('Y')) + 1), ENT_QUOTES, 'UTF-8') ?>
            </dd>
        </div>
        <div style="padding: 16px; border: 1px solid #e2e8f0; border-radius: 12px;">
            <dt style="font-size: 14px; text-transform: uppercase; font-weight: 600; color: #64748b;">Versi Aplikasi</dt>
            <dd style="margin: 8px 0 0; font-size: 20px; font-weight: 600; color: #1e293b;">
                <?= htmlspecialchars(config('app.version', '0.1.0'), ENT_QUOTES, 'UTF-8') ?>
            </dd>
        </div>
    </dl>
</section>
